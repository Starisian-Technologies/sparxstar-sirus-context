<?php
/**
 * SirusRESTController - REST API endpoints for the Sirus Context Engine.
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
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;

/**
 * Registers and handles REST routes for device registration and context retrieval.
 * All input is sanitized before use. Rate limiting is enforced per IP.
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
    ) {}

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
                'callback'            => [$this, 'handle_device_register'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'fingerprint_hash' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'device_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'ua_string' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
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
                'callback'            => [$this, 'handle_get_context'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Handles POST /sparxstar/v1/device.
     *
     * Accepts a device fingerprint, resolves or registers device continuity,
     * and returns a signed context token.
     *
     * @param WP_REST_Request $request The incoming REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_device_register(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ip = $this->get_request_ip();

        if (! $this->check_rate_limit($ip)) {
            return new WP_Error(
                'sirus_rate_limited',
                __('Too many requests. Please try again later.', 'sparxstar-sirus'),
                ['status' => 429]
            );
        }

        $fingerprint_hash = sanitize_text_field(
            wp_unslash((string) $request->get_param('fingerprint_hash'))
        );

        if ($fingerprint_hash === '') {
            return new WP_Error(
                'sirus_missing_fingerprint',
                __('fingerprint_hash is required.', 'sparxstar-sirus'),
                ['status' => 400]
            );
        }

        $device_id_param = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_id') ?? ''))
        );

        $environment_data = $request->get_param('environment_data');
        $environment_data = is_array($environment_data)
            ? $this->sanitize_environment_data($environment_data)
            : [];

        $device_record = $this->device_continuity->resolveDevice(
            $device_id_param,
            $fingerprint_hash,
            $environment_data
        );

        $context = ContextEngine::current();
        $broker  = new NetworkContextBroker();
        $token   = $broker->generateToken($context);

        return new WP_REST_Response(
            [
                'device_id'     => $device_record->device_id,
                'trust_level'   => $device_record->trust_level,
                'context_token' => $token,
            ],
            200
        );
    }

    /**
     * Handles GET /sparxstar/v1/context.
     *
     * Returns the portable payload of the current SirusContext.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_context(): WP_REST_Response|WP_Error
    {
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
     * @param string $ip The client IP address to check.
     */
    private function check_rate_limit(string $ip): bool
    {
        $hash           = hash('sha256', $ip);
        $counter_key    = self::RATE_LIMIT_TRANSIENT_PREFIX . $hash;
        $expiry_key     = self::RATE_LIMIT_TRANSIENT_PREFIX . 'exp_' . $hash;

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
                $sanitized[$clean_key] = wp_json_encode($value) ?: '';
            } else {
                $sanitized[$clean_key] = sanitize_text_field(wp_unslash((string) $value));
            }
        }
        return $sanitized;
    }

    /**
     * Returns the client IP from REMOTE_ADDR only, isolated to this class.
     * Using REMOTE_ADDR (not X-Forwarded-For) prevents rate-limit bypass via spoofed headers.
     *
     * @return string The sanitized client IP address.
     */
    private function get_request_ip(): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
    }
}
