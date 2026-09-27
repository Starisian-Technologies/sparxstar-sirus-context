<?php

/**
 * Multisite-safe uninstall routine for SPARXSTAR Sirus — Context Engine.
 *
 * @package Starisian\SparxstarUEC
 */

declare(strict_types=1);

use Starisian\SparxstarUEC\core\SparxstarUECDatabase;

// Exit if uninstall was not triggered by WordPress.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (! function_exists('is_super_admin')) {
    return;
}

// Data removal is deliberately opt-in. Deactivation and a default uninstall
// retain all user-owned diagnostic data and settings.
if (! defined('SPX_ENV_CHECK_DELETE_ON_UNINSTALL') || SPX_ENV_CHECK_DELETE_ON_UNINSTALL !== true) {
    return;
}

$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

if (! class_exists(SparxstarUECDatabase::class)) {
    return;
}
/**
 * Remove plugin data for the current blog context.
 *
 * @param \wpdb $wpdb Database adapter scoped to the active blog.
 */
function spx_uec_uninstall_site(\wpdb $wpdb): void
{
    $database = new SparxstarUECDatabase($wpdb);
    $database->delete_table();

    delete_option('sparxstar_uec_db_version');
    delete_option('sparxstar_uec_geoip_provider');
    delete_option('sparxstar_uec_ipinfo_api_key');
    delete_option('sparxstar_uec_maxmind_db_path');

    wp_clear_scheduled_hook('sparxstar_env_cleanup_snapshots');
}

global $wpdb;

if (is_multisite()) {
    if (! is_super_admin()) {
        return;
    }

    $offset = 0;
    do {
        $sites = get_sites(['number' => 100, 'offset' => $offset]);
        foreach ($sites as $site) {
            $current_blog_id = (int) $site->blog_id;
            switch_to_blog($current_blog_id);
            try {
                spx_uec_uninstall_site($wpdb);
            } finally {
                restore_current_blog();
            }
        }
        $offset += count($sites);
    } while (count($sites) === 100);

    // Flushed once for the whole run: wp_cache_flush() is global, so calling
    // it per site would clear every site's cache once per site on a network.
    wp_cache_flush();
    return;
}

spx_uec_uninstall_site($wpdb);
wp_cache_flush();
