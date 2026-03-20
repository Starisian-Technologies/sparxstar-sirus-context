<?php

/**
 * SirusPlugin - Plugin bootstrap and hook registration for the Sirus Context Engine.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\api\SirusRESTController;
use Starisian\Sparxstar\Sirus\api\SirusEventController;
use Starisian\Sparxstar\Sirus\admin\SirusDashboardPage;
use Starisian\Sparxstar\Sirus\admin\SirusNetworkSettingsPage;
use Starisian\Sparxstar\Sirus\core\ClientTelemetry;
use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\DeviceRepository;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\core\SirusDatabase;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusPriorityScorer;

/**
 * Singleton orchestrator for the Sirus Context Engine plugin.
 * Registers all WordPress hooks and bootstraps the service layer.
 */
final class SirusPlugin
{
    /** @var SirusPlugin|null */
    private static ?SirusPlugin $instance = null;

    /**
     * Returns the single SirusPlugin instance, creating it if necessary.
     */
    public static function getInstance(): SirusPlugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor – use getInstance().
     */
    private function __construct()
    {
        $this->registerHooks();
    }

    /**
     * Registers WordPress hooks used by this plugin.
     */
    private function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('init', [$this, 'initAdminPages']);

        // Daily telemetry pruning cron.
        add_action(ClientTelemetry::CRON_HOOK, [$this, 'runTelemetryPrune']);
    }

    /**
     * Initialises admin and network-admin pages after plugins are loaded.
     *
     * Called on the plugins_loaded hook so both admin_menu and network_admin_menu
     * actions are available by the time WordPress fires them.
     */
    public function initAdminPages(): void
    {
        global $wpdb;

        // Network settings page: super-admin only, registered in network_admin_menu.
        new SirusNetworkSettingsPage();

        // Site dashboard: registered in admin_menu, gated by access control.
        $repo   = new SirusEventRepository($wpdb);
        $scorer = new SirusPriorityScorer();
        new SirusDashboardPage($repo, $scorer);
    }

    /**
     * Instantiates and registers the Sirus REST routes.
     */
    public function registerRestRoutes(): void
    {
        global $wpdb;
        $device_repo       = new DeviceRepository($wpdb);
        $device_continuity = new DeviceContinuity($device_repo);
        $controller        = new SirusRESTController($device_continuity);
        $controller->register_routes();

        $event_repo       = new SirusEventRepository($wpdb);
        $event_controller = new SirusEventController($event_repo);
        $event_controller->register_routes();
    }

    /**
     * Enqueues front-end assets and injects the current context token.
     * The script file is only enqueued when the asset exists on disk.
     */
    public function enqueueAssets(): void
    {
        if (is_admin()) {
            return;
        }

        $asset_path = SIRUS_PLUGIN_PATH . 'assets/js/sirus-context.js';
        if (! file_exists($asset_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[Sirus] Front-end asset not found, skipping enqueue: ' . $asset_path);
            }
            return;
        }

        $context = ContextEngine::current();
        $broker  = new NetworkContextBroker();
        $token   = $broker->generateToken($context);

        wp_enqueue_script(
            'sparxstar-sirus-context',
            plugins_url('assets/js/sirus-context.js', SIRUS_PLUGIN_FILE),
            [],
            SIRUS_VERSION,
            true
        );

        wp_localize_script(
            'sparxstar-sirus-context',
            'SirusContext',
            [
                'context_token' => $token,
                'device_id'     => $context->device_id,
                'session_id'    => $context->session_id,
                'site_id'       => $context->site_id,
                'rest_url'      => esc_url_raw(rest_url()),
                'nonce'         => wp_create_nonce('wp_rest'),
            ]
        );

        // Enqueue the Sirus observability bootstrap (error capture + session tracking).
        $bootstrap_path = SIRUS_PLUGIN_PATH . 'assets/js/sirus-bootstrap.js';
        if (file_exists($bootstrap_path)) {
            wp_enqueue_script(
                'sparxstar-sirus-bootstrap',
                plugins_url('assets/js/sirus-bootstrap.js', SIRUS_PLUGIN_FILE),
                ['sparxstar-sirus-context'],
                SIRUS_VERSION,
                true
            );
        }
    }

    /**
     * Runs the daily telemetry pruning job.
     * Removes raw client error reports older than 60 days.
     */
    public function runTelemetryPrune(): void
    {
        global $wpdb;
        $telemetry = new ClientTelemetry($wpdb);
        $telemetry->prune();
    }

    /**
     * Activation hook: ensures all Sirus database tables exist and schedules cron.
     */
    public static function onActivation(): void
    {
        global $wpdb;

        $db = new SirusDatabase($wpdb);
        $db->ensure_schema();

        ClientTelemetry::schedule_cron();
    }

    /**
     * Deactivation hook: removes scheduled cron events for this plugin.
     */
    public static function onDeactivation(): void
    {
        ClientTelemetry::unschedule_cron();
    }
}
