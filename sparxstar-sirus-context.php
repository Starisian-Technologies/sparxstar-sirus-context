<?php
/**
 * Plugin Name: SPARXSTAR Sirus — Context Engine
 * Plugin URI: https://sparxstar.com
 * Description: The network context engine managing environment awareness, device continuity, governance authority, and capability resolution across the Sparxstar ecosystem.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: Starisian Technologies
 * Author URI: https://starisian.com
 * Text Domain: sparxstar-sirus
 * Domain Path: /languages
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('SIRUS_VERSION', '1.0.0');
define('SIRUS_PLUGIN_FILE', __FILE__);
define('SIRUS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SIRUS_PLUGIN_SLUG', 'sparxstar-sirus-context');

// Backward compat: define old UEC constants if not already defined.
if (! defined('SPX_ENV_CHECK_VERSION')) {
    define('SPX_ENV_CHECK_VERSION', SIRUS_VERSION);
}
if (! defined('SPX_ENV_CHECK_PLUGIN_FILE')) {
    define('SPX_ENV_CHECK_PLUGIN_FILE', __FILE__);
}
if (! defined('SPX_ENV_CHECK_PLUGIN_PATH')) {
    define('SPX_ENV_CHECK_PLUGIN_PATH', SIRUS_PLUGIN_PATH);
}
if (! defined('SPX_ENV_CHECK_DB_TABLE_NAME')) {
    define('SPX_ENV_CHECK_DB_TABLE_NAME', 'sparxstar_env_snapshots');
}

// Load autoloader.
if (file_exists(SIRUS_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once SIRUS_PLUGIN_PATH . 'vendor/autoload.php';
}

register_activation_hook(__FILE__, [\Starisian\Sparxstar\Sirus\SirusPlugin::class, 'onActivation']);
register_deactivation_hook(__FILE__, [\Starisian\Sparxstar\Sirus\SirusPlugin::class, 'onDeactivation']);

add_action(
    'plugins_loaded',
    static function (): void {
        \Starisian\Sparxstar\Sirus\SirusPlugin::getInstance();
    },
    10
);
