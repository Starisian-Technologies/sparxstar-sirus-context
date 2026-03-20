<?php

/**
 * SirusEventController - REST API endpoint for Sirus observability events.
 *
 * Route: POST /wp-json/sirus/v1/event
 *
 * Accepts the canonical Sirus event payload, validates required fields,
 * sanitizes all inputs, and delegates persistence to SirusEventRepository.
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
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;

/**
 * Registers and handles the POST /sirus/v1/event REST route.
 * All input is validated and sanitized before being passed to the repository.
 */
final class SirusEventController
{
    private const NAMESPACE = 'sirus/v1';

    /**
     * @param SirusEventRepository $repository The event data access layer.
     */
    public function __construct(
        private readonly SirusEventRepository $repository,
    ) {}

    /**
     * Registers the REST API route for event ingestion.
     */
    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/event',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'create_event'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'event_type' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => [self::class, 'validate_event_type'],
                    ],
                    'timestamp' => [
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'device_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'session_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'user_id' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'default'           => 0,
                    ],
                    'url' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'context' => [
                        'required' => false,
                        'type'     => 'object',
                    ],
                    'metrics' => [
                        'required' => false,
                        'type'     => 'object',
                    ],
                    'error' => [
                        'required' => false,
                        'type'     => 'object',
                    ],
                ],
            ]
        );
    }

    /**
     * Handles POST /sirus/v1/event.
     *
     * Sanitizes, validates, and persists the incoming event payload.
     *
     * @param WP_REST_Request $request Incoming REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function create_event(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $event_type = sanitize_text_field(
            wp_unslash((string) ($request->get_param('event_type') ?? ''))
        );
        $timestamp = absint($request->get_param('timestamp') ?? 0);
        $device_id = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_id') ?? ''))
        );
        $session_id = sanitize_text_field(
            wp_unslash((string) ($request->get_param('session_id') ?? ''))
        );

        if ($event_type === '') {
            return new WP_Error(
                'sirus_event_missing_event_type',
                __('event_type is required.', 'sparxstar-sirus'),
                ['status' => 400]
            );
        }

        if (! in_array($event_type, SirusEventRepository::VALID_EVENT_TYPES, true)) {
            return new WP_Error(
                'sirus_event_invalid_event_type',
                __('event_type is not a recognised value.', 'sparxstar-sirus'),
                ['status' => 400]
            );
        }

        if ($timestamp === 0) {
            return new WP_Error(
                'sirus_event_missing_timestamp',
                __('timestamp is required and must be a positive integer.', 'sparxstar-sirus'),
                ['status' => 400]
            );
        }

        if ($device_id === '') {
            return new WP_Error(
                'sirus_event_missing_device_id',
                __('device_id is required.', 'sparxstar-sirus'),
                ['status' => 400]
            );
        }

        if ($session_id === '') {
            return new WP_Error(
                'sirus_event_missing_session_id',
                __('session_id is required.', 'sparxstar-sirus'),
                ['status' => 400]
            );
        }

        $url_param = $request->get_param('url');
        $url       = ($url_param !== null && $url_param !== '')
            ? sanitize_text_field(wp_unslash((string) $url_param))
            : null;

        $context_raw = $request->get_param('context');
        $context     = is_array($context_raw) ? $this->sanitize_json_object($context_raw) : [];

        $metrics_raw = $request->get_param('metrics');
        $metrics     = is_array($metrics_raw) ? $this->sanitize_json_object($metrics_raw) : null;

        $error_raw = $request->get_param('error');
        $error     = is_array($error_raw) ? $this->sanitize_json_object($error_raw) : null;

        $event = [
            'event_type' => $event_type,
            'timestamp'  => $timestamp,
            'device_id'  => $device_id,
            'session_id' => $session_id,
            'user_id'    => absint($request->get_param('user_id') ?? 0),
            'url'        => $url,
            'context'    => $context,
            'metrics'    => $metrics,
            'error'      => $error,
        ];

        $id = $this->repository->insert($event);

        if ($id === 0) {
            return new WP_Error(
                'sirus_event_insert_failed',
                __('Failed to record event.', 'sparxstar-sirus'),
                ['status' => 500]
            );
        }

        return new WP_REST_Response(['id' => $id, 'status' => 'recorded'], 201);
    }

    /**
     * Validates that the event_type is one of the canonical enum values.
     *
     * @param mixed $value The raw value from the request.
     * @return bool|WP_Error
     */
    public static function validate_event_type(mixed $value): bool|WP_Error
    {
        if (! is_string($value)) {
            return new WP_Error(
                'sirus_event_invalid_type',
                __('event_type must be a string.', 'sparxstar-sirus'),
                ['status' => 400]
            );
        }

        if (! in_array($value, SirusEventRepository::VALID_EVENT_TYPES, true)) {
            return new WP_Error(
                'sirus_event_unknown_type',
                sprintf(
                    /* translators: %s: supplied event_type value */
                    __('Unknown event_type: %s', 'sparxstar-sirus'),
                    $value
                ),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Recursively sanitizes a JSON-origin associative array.
     *
     * Strings are sanitized with sanitize_text_field.
     * Integers and floats are cast and preserved.
     * Booleans and nulls are preserved.
     * Nested arrays are handled recursively.
     *
     * @param array<mixed> $data Raw associative array from REST request.
     * @return array<string, mixed>
     */
    private function sanitize_json_object(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $safe_key = sanitize_key((string) $key);
            if ($safe_key === '') {
                continue;
            }

            if (is_array($value)) {
                $clean[$safe_key] = $this->sanitize_json_object($value);
            } elseif (is_int($value) || is_float($value)) {
                $clean[$safe_key] = $value;
            } elseif (is_bool($value)) {
                $clean[$safe_key] = $value;
            } elseif ($value === null) {
                $clean[$safe_key] = null;
            } else {
                $clean[$safe_key] = sanitize_text_field(wp_unslash((string) $value));
            }
        }

        return $clean;
    }
}
