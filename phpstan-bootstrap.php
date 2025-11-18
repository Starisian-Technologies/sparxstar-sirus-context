<?php
/**
 * PHPStan bootstrap file to define constants.
 */

// Define WordPress ABSPATH if not already defined
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Define plugin constants
define( 'SPX_ENV_CHECK_LOADED', true );
define( 'SPX_ENV_CHECK_PLUGIN_FILE', __FILE__ );
define( 'SPX_ENV_CHECK_PLUGIN_PATH', __DIR__ . '/' );
define( 'SPX_ENV_CHECK_VERSION', '0.5.0' );
define( 'SPX_ENV_CHECK_TEXT_DOMAIN', 'sparxstar_user_environment_check' );
define( 'SPX_ENV_CHECK_DB_TABLE_NAME', 'Sparxstar_User_Environment' );
