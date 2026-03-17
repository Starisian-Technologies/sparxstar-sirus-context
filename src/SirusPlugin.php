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
use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\DeviceRepository;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\core\SirusDatabase;

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
                'site_id'       => $context->site_id,
                'rest_url'      => esc_url_raw(rest_url('sparxstar/v1')),
                'nonce'         => wp_create_nonce('wp_rest'),
            ]
        );
    }

    /**
     * Activation hook: ensures the sirus_devices table exists.
     */
    public static function onActivation(): void
    {
        global $wpdb;
        $db = new SirusDatabase($wpdb);
        $db->ensure_schema();
    }

    /**
     * Deactivation hook: removes any scheduled cron events for this plugin.
     */
    public static function onDeactivation(): void
    {
        wp_clear_scheduled_hook('sparxstar_sirus_cleanup');
    }
}
