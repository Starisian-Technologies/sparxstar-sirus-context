<?php

/**
 * SirusDirectiveController - REST API endpoints for Sirus adaptive directives.
 *
 * Routes:
 *   GET /sirus/v1/directives  — returns active directives for a device/session.
 *   GET /sirus/v1/rule-hits   — admin-only recent rule hits.
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
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

/**
 * Registers and handles the directive and rule-hit REST routes.
 */
final class SirusDirectiveController
{
    private const NAMESPACE = 'sirus/v1';

    public function __construct(
        private readonly SirusMitigationCoordinator $coordinator,
        private readonly SirusRuleHitRepository $ruleHitRepo,
    ) {
    }

    /**
     * Registers the REST API routes.
     */
    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/directives',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_directives' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'device_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'session_id' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'           => '',
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/rule-hits',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_rule_hits' ],
                'permission_callback' => [ $this, 'admin_permission_callback' ],
                'args'                => [
                    'limit' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                        'default'           => 100,
                    ],
                ],
            ]
        );
    }

    /**
     * GET /sirus/v1/directives
     *
     * Returns active directives for the requesting device/session.
     *
     * @param WP_REST_Request $request Incoming REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_directives(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $device_id = sanitize_text_field(
            wp_unslash((string) ($request->get_param('device_id') ?? ''))
        );
        $session_id = sanitize_text_field(
            wp_unslash((string) ($request->get_param('session_id') ?? ''))
        );

        if ($device_id === '') {
            return new WP_Error(
                'sirus_directive_missing_device_id',
                __('device_id is required.', 'sparxstar-sirus'),
                [ 'status' => 400 ]
            );
        }

        $directive = $this->coordinator->getRecommendedActions($device_id, $session_id);

        return new WP_REST_Response($directive, 200);
    }

    /**
     * GET /sirus/v1/rule-hits
     *
     * Admin-only. Returns recent rule hits.
     *
     * @param WP_REST_Request $request Incoming REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_rule_hits(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $limit_raw = $request->get_param('limit');
        $limit     = $limit_raw !== null ? absint($limit_raw) : 100;

        $hits = $this->ruleHitRepo->getRecentHits($limit);

        return new WP_REST_Response($hits, 200);
    }

    /**
     * Permission callback for admin-only endpoints.
     */
    public function admin_permission_callback(): bool
    {
        return current_user_can('manage_options') || is_super_admin();
    }
}
