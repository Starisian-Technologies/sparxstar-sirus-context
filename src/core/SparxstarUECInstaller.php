<?php

declare(strict_types=1);

namespace Starisian\SparxstarUEC\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\SparxstarUEC\cron\SparxstarUECScheduler;

/**
 * Handles plugin lifecycle events across single and multisite contexts.
 */
class SparxstarUECInstaller
{
    private const NETWORK_BATCH_SIZE = 100;

    public const NETWORK_ACTIVATION_HOOK = 'sparxstar_uec_continue_network_activation';

    /**
     * Run activation tasks respecting network-wide installs.
     *
     * @param bool|mixed $network_wide Flag provided by WordPress when activated network-wide.
     */
    public static function spx_uec_activate($network_wide): void
    {
        global $wpdb;

        $network_wide = (bool) $network_wide;

        if (is_multisite() && $network_wide) {
            if (! is_super_admin()) {
                return;
            }

            self::process_network_activation_batch(0, $wpdb);

            return;
        }

        self::activate_site($wpdb);
    }

    /**
     * Run deactivation cleanup without removing data.
     *
     * @param bool|mixed $network_wide Flag provided by WordPress when deactivated network-wide.
     */
    public static function spx_uec_deactivate($network_wide): void
    {
        $network_wide = (bool) $network_wide;

        if (is_multisite() && $network_wide) {
            if (! is_super_admin()) {
                return;
            }

            $offset = 0;
            do {
                $sites = get_sites([ 'number' => self::NETWORK_BATCH_SIZE, 'offset' => $offset ]);
                foreach ($sites as $site) {
                    $blog_id = (int) $site->blog_id;
                    self::with_blog_context(
                        $blog_id,
                        static function (): void {
                            self::deactivate_site();
                        }
                    );
                }

                $offset += count($sites);
            } while (count($sites) === self::NETWORK_BATCH_SIZE);

            return;
        }

        self::deactivate_site();
    }

    /**
     * Initialise the plugin for a newly created site in a network.
     *
     * @param \WP_Site|int $new_site Newly created site object or blog ID supplied by WordPress.
     */
    public static function spx_uec_initialize_new_site(\WP_Site|int $new_site): void
    {
        if (! is_multisite()) {
            return;
        }

        global $wpdb;

        $blog_id = $new_site instanceof \WP_Site ? (int) $new_site->blog_id : $new_site;

        self::with_blog_context($blog_id, static function () use ($wpdb): void {
            self::activate_site($wpdb);
        });
    }

    /**
     * Perform activation logic for the current site only.
     *
     * @param \wpdb $wpdb Database adapter from the current blog context.
     */
    private static function activate_site(\wpdb $wpdb): void
    {
        // Database Loop Integrity Rule: this helper must never loop over sites.
        $database = new SparxstarUECDatabase($wpdb);
        $database->ensure_schema();

        self::seed_defaults();

        SparxstarUECScheduler::schedule_recurring('sparxstar_env_cleanup_snapshots', DAY_IN_SECONDS);
    }

    /**
     * Clear scheduled events for the current site only.
     */
    private static function deactivate_site(): void
    {
        SparxstarUECScheduler::clear('sparxstar_env_cleanup_snapshots');
    }

    /**
     * Register default options for the current site if they do not exist.
     */
    private static function seed_defaults(): void
    {
        add_option('sparxstar_uec_geoip_provider', 'none');
        add_option('sparxstar_uec_ipinfo_api_key', '');
        add_option('sparxstar_uec_maxmind_db_path', '');
    }

    public static function continue_network_activation(int $offset): void
    {
        if (! is_multisite() || ! is_super_admin()) {
            return;
        }

        global $wpdb;

        self::process_network_activation_batch($offset, $wpdb);
    }

    /**
     * @param \wpdb $wpdb Database adapter from the current request.
     */
    private static function process_network_activation_batch(int $offset, \wpdb $wpdb): void
    {
        $processed = self::process_network_batch(
            $offset,
            static function (int $blog_id) use ($wpdb): void {
                self::with_blog_context($blog_id, static function () use ($wpdb): void {
                    self::activate_site($wpdb);
                });
            }
        );

        if ($processed === self::NETWORK_BATCH_SIZE) {
            $next_offset = $offset + $processed;

            if (! wp_next_scheduled(self::NETWORK_ACTIVATION_HOOK, [ $next_offset ])) {
                wp_schedule_single_event(time() + 1, self::NETWORK_ACTIVATION_HOOK, [ $next_offset ]);
            }
        }
    }

    /**
     * @param callable(int):void $site_callback
     */
    private static function process_network_batch(int $offset, callable $site_callback): int
    {
        $sites = get_sites([ 'number' => self::NETWORK_BATCH_SIZE, 'offset' => $offset ]);

        foreach ($sites as $site) {
            $site_callback((int) $site->blog_id);
        }

        return count($sites);
    }

    /**
     * @param callable():void $operation
     */
    private static function with_blog_context(int $blog_id, callable $operation): void
    {
        switch_to_blog($blog_id);
        try {
            $operation();
        } finally {
            restore_current_blog();
        }
    }
}
