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
use Starisian\Sparxstar\Sirus\core\ClientTelemetry;
use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\IdentityResolver;
use Starisian\Sparxstar\Sirus\core\PulseGenerator;
use Starisian\Sparxstar\Sirus\core\ResourceSensitivity;
use Starisian\Sparxstar\Sirus\core\StepUpPolicy;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\helpers\IpAnonymizer;
use Starisian\Sparxstar\Sirus\services\SirusDeviceParser;

/**
 * Registers and handles REST routes for device registration, context retrieval, pulse issuance,
 * identity resolution, session status, and client telemetry.
 */
final class SirusRESTController
{
    private const NAMESPACE = 'sparxstar/v1';

    private const PULSE_COOKIE_NAME = 'spx_context_pulse';

    private const RATE_LIMIT_TRANSIENT_PREFIX = 'sirus_rl_';

    private const RATE_LIMIT_MAX = 30;

    public function __construct(
        private readonly DeviceContinuity $device_continuity,
        private readonly PulseGenerator $pulse_generator,
        private readonly IdentityResolver $identity_resolver,
        private readonly ?ClientTelemetry $client_telemetry,
        private readonly SirusDeviceParser $device_parser,
        private readonly NetworkContextBroker $network_context_broker,
    ) {
    }

    /**
     * Permission callback to enforce REST nonce validation and mitigate CSRF.
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
                    'visitor_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'device_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'device_secret' => [
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
                'callback'            => [ $this, 'handle_get_context' ],
                'permission_callback' => [ $this, 'verify_rest_nonce' ],
                'args'                => [
                    'device_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'ctx_token' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/pulse',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_generate_pulse' ],
                'permission_callback' => [ $this, 'verify_rest_nonce' ],
                'args'                => [
                    'device_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'resource_sensitivity' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'request_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/identity',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_identity' ],
                'permission_callback' => [ $this, 'verify_rest_nonce' ],
                'args'                => [
                    'device_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/session',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_session' ],
                'permission_callback' => [ $this, 'verify_rest_nonce' ],
                'args'                => [
                    'device_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/client-report',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_client_report' ],
                'permission_callback' => [ $this, 'verify_rest_nonce' ],
                'args'                => [
                    'error_type' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'error_message' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'device_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'context' => [
                        'required' => false,
                        'type'     => 'object',
                    ],
                ],
            ]
        );
    }

    /**
     * Handles POST /sparxstar/v1/device.
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
        $device_secret_param = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_secret') ?? ''))
        );

        $user_agent = sanitize_text_field(
            wp_unslash((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))
        );
        $ip_subnet        = IpAnonymizer::ipSubnet($raw_ip);
        $fingerprint_hash = hash('sha256', $visitor_id . $user_agent . $ip_subnet);

        $environment_data = $request->get_param('environment_data');
        $environment_data = is_array($environment_data)
            ? $this->sanitize_payload_object($environment_data)
            : [];

        $environment_data = $this->mergeEnvironmentFallbacks($environment_data, $user_agent, $raw_ip);

        $device_record = $this->device_continuity->resolveDevice(
            $device_id_param,
            $device_secret_param,
            $fingerprint_hash,
            $environment_data
        );

        $context = ContextEngine::buildFromDevice($device_record);
        $token   = $this->network_context_broker->issueToken($context, wp_salt('auth'));

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
     */
    public function handle_get_context(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ctx_token = sanitize_text_field(
            wp_unslash((string) ($request->get_param('ctx_token') ?? ''))
        );

        if ($ctx_token !== '') {
            $context = $this->network_context_broker->verifyToken($ctx_token, wp_salt('auth'));

            if ($context === null) {
                return new WP_Error(
                    'sirus_invalid_ctx_token',
                    __('Invalid or expired context token.', 'sparxstar-sirus'),
                    [ 'status' => 401 ]
                );
            }

            return new WP_REST_Response($context->toPortablePayload(), 200);
        }

        return new WP_REST_Response(ContextEngine::current()->toPortablePayload(), 200);
    }

    /**
     * Handles POST /sparxstar/v1/pulse.
     */
    public function handle_generate_pulse(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $device_id = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_id') ?? ''))
        );
        $request_id = sanitize_text_field(
            wp_unslash((string) ($request->get_param('request_id') ?? ''))
        );
        $resource_sensitivity = sanitize_text_field(
            wp_unslash((string) ($request->get_param('resource_sensitivity') ?? ''))
        );

        if ($device_id === '' || $request_id === '' || $resource_sensitivity === '') {
            return new WP_Error(
                'sirus_pulse_invalid_request',
                __('device_id, resource_sensitivity, and request_id are required.', 'sparxstar-sirus'),
                [ 'status' => 400 ]
            );
        }

        $sensitivity = $this->parseResourceSensitivity($resource_sensitivity);
        if ($sensitivity === null) {
            return new WP_Error(
                'sirus_pulse_invalid_sensitivity',
                __('resource_sensitivity is invalid.', 'sparxstar-sirus'),
                [ 'status' => 400 ]
            );
        }

        $context = ContextEngine::current();
        if ($context->device_id !== $device_id) {
            return new WP_Error(
                'sirus_pulse_device_mismatch',
                __('device_id does not match the current device context.', 'sparxstar-sirus'),
                [ 'status' => 403 ]
            );
        }

        $pulse       = $this->pulse_generator->generate($context);
        $step_up     = (new StepUpPolicy())->requiresStepUp($pulse, $sensitivity);

        $trust_level_value = $pulse->trust_level instanceof \BackedEnum
            ? $pulse->trust_level->value
            : (string) $pulse->trust_level;

        // Normalize pulse vars to scalars before JSON encoding for the cookie.
        $pulse_vars = get_object_vars($pulse);
        if (isset($pulse_vars['trust_level']) && $pulse_vars['trust_level'] instanceof \BackedEnum) {
            $pulse_vars['trust_level'] = $pulse_vars['trust_level']->value;
        }
        $cookie_payload = wp_json_encode($pulse_vars);

        $response_data = [
            'pulse_id'             => $pulse->pulse_id,
            'expires_at'           => $pulse->expires,
            'trust_level'          => $trust_level_value,
            'request_id'           => $request_id,
            'resource_sensitivity' => $sensitivity->value,
            'step_up_required'     => $step_up,
        ];

        if ($cookie_payload === false) {
            $response_data['cookie_omitted'] = true;
        }

        $response = new WP_REST_Response($response_data, 201);

        if ($cookie_payload !== false) {
            $response->header(
                'Set-Cookie',
                $this->buildPulseCookie(rawurlencode($cookie_payload), $pulse->expires)
            );
        }

        return $response;
    }

    /**
     * Handles GET /sparxstar/v1/identity.
     */
    public function handle_get_identity(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $context = ContextEngine::current();
        $device_mismatch = $this->validateRequestedDevice($request, $context->device_id);
        if ($device_mismatch instanceof WP_Error) {
            return $device_mismatch;
        }

        return new WP_REST_Response(
            $this->identity_resolver->resolve($context),
            200
        );
    }

    /**
     * Handles GET /sparxstar/v1/session.
     */
    public function handle_get_session(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $context = ContextEngine::current();
        $device_mismatch = $this->validateRequestedDevice($request, $context->device_id);
        if ($device_mismatch instanceof WP_Error) {
            return $device_mismatch;
        }

        $active  = session_status() === PHP_SESSION_ACTIVE;

        return new WP_REST_Response(
            [
                'context_id' => $context->context_id,
                'device_id'  => $context->device_id,
                'session_id' => $context->session_id,
                'status'     => $active ? 'active' : 'ephemeral',
                'is_active'  => $active,
                'issued_at'  => $context->issued_at,
                'expires'    => $context->expires,
            ],
            200
        );
    }

    /**
     * Handles POST /sparxstar/v1/client-report.
     */
    public function handle_client_report(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if ($this->client_telemetry === null) {
            return new WP_Error(
                'sirus_client_telemetry_unavailable',
                __('Client telemetry is unavailable.', 'sparxstar-sirus'),
                [ 'status' => 503 ]
            );
        }

        $error_type = sanitize_text_field(
            wp_unslash((string) ($request->get_param('error_type') ?? ''))
        );
        $error_message = sanitize_text_field(
            wp_unslash((string) ($request->get_param('error_message') ?? ''))
        );
        $device_id = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_id') ?? ''))
        );

        if ($error_type === '' || $error_message === '') {
            return new WP_Error(
                'sirus_client_report_invalid_request',
                __('error_type and error_message are required.', 'sparxstar-sirus'),
                [ 'status' => 400 ]
            );
        }

        $context = ContextEngine::current();
        if ($device_id !== '' && $device_id !== $context->device_id) {
            return new WP_Error(
                'sirus_client_report_device_mismatch',
                __('device_id does not match the current device context.', 'sparxstar-sirus'),
                [ 'status' => 403 ]
            );
        }

        $context_payload = $request->get_param('context');
        $context_payload = is_array($context_payload)
            ? $this->sanitize_payload_object($context_payload)
            : [];

        $this->client_telemetry->record(
            $error_type,
            $error_message,
            $context_payload,
            $context->device_id
        );

        return new WP_REST_Response(
            [
                'status'    => 'accepted',
                'device_id' => $context->device_id,
            ],
            202
        );
    }

    /**
     * Returns true if the given IP address is within its rate-limit window.
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
            $window_ttl = 60;
            set_transient($counter_key, 1, $window_ttl);
            set_transient($expiry_key, time() + $window_ttl, $window_ttl);
        } else {
            $remaining = max(1, $expiry - time());
            set_transient($counter_key, $count + 1, $remaining);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data Raw payload data from the request.
     * @return array<string, mixed>
     */
    private function sanitize_payload_object(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $clean_key = sanitize_key((string) $key);
            if ($clean_key === '') {
                continue;
            }

            if (is_array($value)) {
                $sanitized[ $clean_key ] = $this->sanitize_payload_object($value);
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $sanitized[ $clean_key ] = $value;
                continue;
            }

            $sanitized[ $clean_key ] = sanitize_text_field(wp_unslash((string) $value));
        }

        return $sanitized;
    }

    /**
     * Merges server-side parser data only into keys the client did not supply.
     *
     * @param array<string, mixed> $environment_data Client-submitted environment data.
     * @return array<string, mixed>
     */
    private function mergeEnvironmentFallbacks(array $environment_data, string $user_agent, string $raw_ip): array
    {
        $device_info = $this->device_parser->parse($user_agent);

        $fallbacks = [
            'browser_name'   => $device_info['browser'],
            'browser_version'=> $device_info['browser_version'],
            'os'             => $device_info['os'],
            'os_version'     => $device_info['os_version'],
            'device_type'    => $device_info['device_type'],
            'device_brand'   => $device_info['brand'],
            'device_model'   => $device_info['model'],
            'is_bot'         => $device_info['is_bot'] ? '1' : '0',
            'ip_address'     => IpAnonymizer::anonymize($raw_ip),
        ];

        foreach ($fallbacks as $key => $value) {
            if (! isset($environment_data[$key]) || $environment_data[$key] === '') {
                $environment_data[$key] = $value;
            }
        }

        return $environment_data;
    }

    /**
     * Builds the Set-Cookie header value for a pulse.
     */
    private function buildPulseCookie(string $encodedPulse, int $expiresAt): string
    {
        $parts = [
            self::PULSE_COOKIE_NAME . '=' . $encodedPulse,
            'Path=/',
            'Expires=' . gmdate('D, d M Y H:i:s T', $expiresAt),
            'HttpOnly',
            'SameSite=Strict',
        ];

        if (function_exists('is_ssl') && is_ssl()) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    /**
     * Normalizes the declared resource sensitivity.
     */
    private function parseResourceSensitivity(string $value): ?ResourceSensitivity
    {
        $normalized = strtoupper(str_replace('-', '_', sanitize_text_field($value)));

        return match ($normalized) {
            '1', 'LEVEL_1' => ResourceSensitivity::LEVEL_1,
            '2', 'LEVEL_2' => ResourceSensitivity::LEVEL_2,
            '3', 'LEVEL_3' => ResourceSensitivity::LEVEL_3,
            default => null,
        };
    }

    /**
     * Validates an optional device_id request parameter against the current device context.
     */
    private function validateRequestedDevice(WP_REST_Request $request, string $currentDeviceId): ?WP_Error
    {
        $requested_device = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_id') ?? ''))
        );

        if ($requested_device === '' || $requested_device === $currentDeviceId) {
            return null;
        }

        return new WP_Error(
            'sirus_device_context_mismatch',
            __('device_id does not match the current device context.', 'sparxstar-sirus'),
            [ 'status' => 400 ]
        );
    }

    /**
     * Returns the raw client IP from REMOTE_ADDR only.
     */
    private function get_raw_request_ip(): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
    }
}
