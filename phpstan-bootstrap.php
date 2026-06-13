<?php

/**
 * PHPStan bootstrap file to define constants.
 */

// Define WordPress ABSPATH if not already defined
if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Define SparxstarUEC plugin constants (defined at runtime in the entry files;
// declared here so static analysis does not report them as constant.notFound).
define('SPX_ENV_CHECK_LOADED', true);
define('SPX_ENV_CHECK_PLUGIN_FILE', __FILE__);
define('SPX_ENV_CHECK_PLUGIN_PATH', __DIR__ . '/');
define('SPX_ENV_CHECK_VERSION', '0.5.0');
define('SPX_ENV_CHECK_TEXT_DOMAIN', 'sparxstar_user_environment_check');
// Must match the runtime fallback in sparxstar-sirus-context.php so static
// analysis sees the same value the entry file defines.
define('SPX_ENV_CHECK_DB_TABLE_NAME', 'sparxstar_env_snapshots');

// Sirus plugin constants (runtime-defined in sparxstar-sirus-context.php).
define('SIRUS_VERSION', '1.0.0');
define('SIRUS_PLUGIN_FILE', __DIR__ . '/sparxstar-sirus-context.php');
define('SIRUS_PLUGIN_PATH', __DIR__ . '/');
define('SIRUS_PLUGIN_SLUG', 'sparxstar-sirus-context');
