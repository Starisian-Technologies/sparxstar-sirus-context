<?php

/**
 * SirusRESTController - REST API endpoints for the Sirus Context Engine.
 *
 * Privacy requirements (non-negotiable per spec §H):
 * - IPs are anonymized (last octet zeroed) before any storage or logging.
 * - Rate limiting runs on the raw IP; only the anonymized form is stored.
 *
 * Device fingerprint derivation (spec §A):
 * - fingerprint_hash is computed server-side as sha256(visitor_id + user_agent + ip_subnet).
 *   The client sends raw signals; the server owns the hash computation.
 *
 * Device → Context binding (spec §A, issue #1):
 * - After resolving/registering a device, the context is built FROM that device
 *   via ContextEngine::buildFromDevice() so the two are always in sync.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\api;

if (! defined('ABSPATH')) {
    exit;
}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\helpers\IpAnonymizer;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\services\SirusDeviceParser;

/**
 * Registers and handles REST routes for device registration and context retrieval.
 * All input is sanitized before use. Rate limiting is enforced per raw IP;
 * only anonymized IPs are stored.
 */
final class SirusRESTController
{
    private const NAMESPACE = 'sparxstar/v1';

    private const RATE_LIMIT_TRANSIENT_PREFIX = 'sirus_rl_';

    private const RATE_LIMIT_MAX = 30;

    /**
     * @param DeviceContinuity $device_continuity The device continuity service.
     */
    public function __construct(
        private readonly DeviceContinuity $device_continuity,
    ) {
    }

    /**
     * Permission callback to enforce REST nonce validation and mitigate CSRF.
     *
     * Expects a valid X-WP-Nonce header created for the 'wp_rest' action.
     *
     * @param WP_REST_Request $request The current REST request.
     * @return bool|WP_Error True if the nonce is valid, otherwise WP_Error.
     */
    public function verify_rest_nonce(WP_REST_Request $request): bool|WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (! is_string($nonce) || $nonce === '') {
            return new WP_Error(
                'sparxstar_sirus_rest_nonce_missing',
                __('REST nonce is missing.', 'sparxstar'),
                [ 'status' => 403 ]
            );
        }

        if (! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'sparxstar_sirus_rest_nonce_invalid',
                __('REST nonce is invalid.', 'sparxstar'),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Registers the REST API routes for the Sirus Context Engine.
     */
    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/device',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_device_register' ],
                'permission_callback' => [ $this, 'verify_rest_nonce' ],
                'args'                => [
                    // visitor_id from FingerprintJS — used SERVER-SIDE to derive fingerprint_hash.
                    'visitor_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    // Stored device_id from localStorage (absent on first visit).
                    'device_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    // Stored device_secret from localStorage — verifies the device_id claim.
                    'device_secret' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    // Additional environment signals from the client.
                    'environment_data' => [
                        'required' => false,
                        'type'     => 'object',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/context',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_context' ],
                'permission_callback' => [ $this, 'verify_rest_nonce' ],
                'args'                => [
                    // Optional: resolve context for a specific device.
                    'device_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    // Optional: restore context from a signed cross-domain token.
                    'ctx_token' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );
    }

    /**
     * Handles POST /sparxstar/v1/device.
     *
     * Server derives fingerprint_hash from visitor_id + UA + IP subnet.
     * Resolves or registers device continuity, runs server-side UA parsing,
     * builds the context from the resolved device (deterministic binding), and
     * returns a signed context token.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_device_register(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $raw_ip = $this->get_raw_request_ip();

        if (! $this->check_rate_limit($raw_ip)) {
            return new WP_Error(
                'sirus_rate_limited',
                __('Too many requests. Please try again later.', 'sparxstar-sirus'),
                [ 'status' => 429 ]
            );
        }

        $visitor_id = sanitize_text_field(
            wp_unslash((string) ($request->get_param('visitor_id') ?? ''))
        );

        if ($visitor_id === '') {
            return new WP_Error(
                'sirus_missing_visitor_id',
                __('visitor_id is required.', 'sparxstar-sirus'),
                [ 'status' => 400 ]
            );
        }

        $device_id_param = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_id') ?? ''))
        );

        // device_secret verifies the device_id claim (drift tolerance model).
        // Absent on first visit; present on subsequent visits from localStorage.
        $device_secret_param = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_secret') ?? ''))
        );

        // Server-side fingerprint derivation: sha256(visitorId + userAgent + ipSubnet).
        // This ensures the fingerprint is under server control and cannot be spoofed.
        $user_agent = sanitize_text_field(
            wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))
        );
        $ip_subnet        = IpAnonymizer::ipSubnet($raw_ip);
        $fingerprint_hash = hash('sha256', $visitor_id . $user_agent . $ip_subnet);

        // Collect environment data from the request.
        $environment_data = $request->get_param('environment_data');
        $environment_data = is_array($environment_data)
            ? $this->sanitize_environment_data($environment_data)
            : [];

        // Server-side UA parsing — the client never runs a detection library.
        $parser      = new SirusDeviceParser();
        $device_info = $parser->parse($user_agent);

        // Merge server-parsed device info into the environment snapshot.
        // Existing client signals are preserved; server data takes precedence for device keys.
        $environment_data = array_merge(
            $environment_data,
            [
                'server_ua_browser'         => $device_info['browser'],
                'server_ua_browser_version' => $device_info['browser_version'],
                'server_ua_os'              => $device_info['os'],
                'server_ua_os_version'      => $device_info['os_version'],
                'server_ua_device_type'     => $device_info['device_type'],
                'server_ua_brand'           => $device_info['brand'],
                'server_ua_model'           => $device_info['model'],
                'server_ua_is_bot'          => $device_info['is_bot'] ? '1' : '0',
                // Anonymized IP for storage — last octet zeroed, never full IP.
                'ip_anonymized' => IpAnonymizer::anonymize($raw_ip),
            ]
        );

        // 1. Resolve (or register) the device.
        // Pass both device_id and device_secret — the secret authenticates the device_id claim.
        $device_record = $this->device_continuity->resolveDevice(
            $device_id_param,
            $device_secret_param,
            $fingerprint_hash,
            $environment_data
        );

        // 2. Build context FROM the resolved device — ensures device and context always
        // reference the same device_id. This primes ContextCache so that any subsequent
        // call to ContextEngine::current() in this request returns the same context.
        $context = ContextEngine::buildFromDevice($device_record);

        $broker = new NetworkContextBroker();
        $token  = $broker->issueToken($context, wp_salt('auth'));

        return new WP_REST_Response(
            [
                'device_id'     => $device_record->device_id,
                'device_secret' => $device_record->device_secret,
                'trust_level'   => $device_record->trust_level,
                'context_token' => $token,
            ],
            200
        );
    }

    /**
     * Handles GET /sparxstar/v1/context.
     *
     * Resolution priority:
     * 1. ctx_token — validates the signed cross-domain handoff token and restores context.
     * 2. Fallback — returns the current request context via ContextEngine::current().
     *
     * The ctx_token path implements the inbound side of the cross-domain handoff
     * defined in spec §F: signature and TTL are verified; an expired or tampered
     * token returns a 401. Signature verification uses HMAC-SHA256 with the WP auth
     * salt, so tokens are site-specific.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_context(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ctx_token = sanitize_text_field(
            wp_unslash((string) ($request->get_param('ctx_token') ?? ''))
        );

        if ($ctx_token !== '') {
            $broker  = new NetworkContextBroker();
            $context = $broker->verifyToken($ctx_token, wp_salt('auth'));

            if ($context === null) {
                return new WP_Error(
                    'sirus_invalid_ctx_token',
                    __('Invalid or expired context token.', 'sparxstar-sirus'),
                    [ 'status' => 401 ]
                );
            }

            return new WP_REST_Response($context->toPortablePayload(), 200);
        }

        // No token — return the context for the current request.
        $context = ContextEngine::current();
        return new WP_REST_Response($context->toPortablePayload(), 200);
    }

    /**
     * Returns true if the given IP address is within its rate-limit window.
     *
     * Uses a pair of transients: one counter and one fixed window expiry to ensure
     * the window does not slide on each increment. Allows up to RATE_LIMIT_MAX
     * requests per 60-second fixed window.
     *
     * @param string $ip The raw client IP address to check (never stored).
     */
    private function check_rate_limit(string $ip): bool
    {
        $hash        = hash('sha256', $ip);
        $counter_key = self::RATE_LIMIT_TRANSIENT_PREFIX . $hash;
        $expiry_key  = self::RATE_LIMIT_TRANSIENT_PREFIX . 'exp_' . $hash;

        $count  = (int) get_transient($counter_key);
        $expiry = (int) get_transient($expiry_key);

        if ($count >= self::RATE_LIMIT_MAX) {
            return false;
        }

        if ($count === 0 || $expiry === 0) {
            // Start a new fixed 60-second window.
            $window_ttl = 60;
            set_transient($counter_key, 1, $window_ttl);
            set_transient($expiry_key, time() + $window_ttl, $window_ttl);
        } else {
            // Increment within the existing window; preserve original TTL.
            $remaining = max(1, $expiry - time());
            set_transient($counter_key, $count + 1, $remaining);
        }

        return true;
    }

    /**
     * Sanitizes each scalar value in the environment_data array.
     *
     * @param array<mixed> $data Raw environment data from the request.
     * @return array<string, string>
     */
    private function sanitize_environment_data(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $clean_key = sanitize_key((string) $key);
            if ($clean_key === '') {
                continue;
            }
            if (is_array($value)) {
                // Flatten nested arrays to JSON string.
                $sanitized[ $clean_key ] = wp_json_encode($value) ?: '';
            } else {
                $sanitized[ $clean_key ] = sanitize_text_field(wp_unslash((string) $value));
            }
        }
        return $sanitized;
    }

    /**
     * Returns the raw client IP from REMOTE_ADDR only.
     * Using REMOTE_ADDR (not X-Forwarded-For) prevents rate-limit bypass via spoofed headers.
     * This value is used only for rate limiting and fingerprint hashing — never stored.
     *
     * @return string The sanitized raw client IP address.
     */
    private function get_raw_request_ip(): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
    }
}
