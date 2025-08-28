<?php
/**
 * Plugin Name:       SPARXSTAR User Environment Check
 * Plugin URI:        https://github.com/Starisian-Technologies/sparxstar-user-environment-check
 * Description:       A network-wide utility that checks browser compatibility and logs anonymized technical data for diagnostics, with consent via the WP Consent API.
 * Version:           2.2 (Hardened)
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Starisian Technologies (Max Barrett)
 * Author URI:        https://starisian.com
 * Text Domain:       sparxstar-user-environment-check
 * Domain Path:       /languages
 * License:           Proprietary
 * License URI:       https://github.com/Starisian-Technologies/sparxstar-user-environment-check/LICENSE
 * Update URI:        https://github.com/Starisian-Technologies/sparxstar-user-environment-check.git
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Constants
 */
if ( ! defined( 'ENVCHECK_LOG_DIR' ) ) {
    define( 'ENVCHECK_LOG_DIR', WP_CONTENT_DIR . '/envcheck-logs' );
}
if ( ! defined( 'ENVCHECK_RETENTION_DAYS' ) ) {
    define( 'ENVCHECK_RETENTION_DAYS', 30 );
}

/**
 * Load translations
 */
add_action( 'init', function () {
    load_plugin_textdomain(
        'sparxstar-user-environment-check',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
});

/**
 * Enqueue assets
 */
function envcheck_enqueue_assets() {
    $cat = apply_filters( 'envcheck_consent_category', 'statistics' );
    if ( function_exists( 'wp_has_consent' ) && ! wp_has_consent( $cat ) ) {
        return;
    }

    $script_path = __DIR__ . '/assets/js/sparxstar-user-environment-check.js';
    $style_path  = __DIR__ . '/assets/css/sparxstar-user-environment-check.css';
    $base_url    = plugin_dir_url( __FILE__ );

    wp_enqueue_script(
        'envcheck-js',
        $base_url . 'assets/js/sparxstar-user-environment-check.js',
        [],
        filemtime( $script_path ),
        true
    );
    wp_enqueue_style(
        'envcheck-css',
        $base_url . 'assets/css/sparxstar-user-environment-check.css',
        [],
        filemtime( $style_path )
    );

    wp_localize_script( 'envcheck-js', 'envCheckData', [
        'nonce'    => wp_create_nonce( 'envcheck_log_nonce' ),
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'i18n'     => [
            'notice'         => __( 'Notice:', 'sparxstar-user-environment-check' ),
            'update_message' => __( 'Your browser may be outdated. For the best experience, please', 'sparxstar-user-environment-check' ),
            'update_link'    => __( 'update your browser', 'sparxstar-user-environment-check' ),
            'dismiss'        => __( 'Dismiss', 'sparxstar-user-environment-check' ),
        ],
    ]);
}
add_action( 'wp_enqueue_scripts', 'envcheck_enqueue_assets' );
add_action( 'login_enqueue_scripts', 'envcheck_enqueue_assets' );

/**
 * Recursive sanitize
 */
function envcheck_sanitize_recursive( $value ) {
    if ( is_array( $value ) ) {
        return array_map( 'envcheck_sanitize_recursive', $value );
    }
    return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $value;
}

/**
 * Handle AJAX logging
 */
function envcheck_handle_log_data() {
    check_ajax_referer( 'envcheck_log_nonce', 'nonce' );

    // Consent enforcement
    $cat = apply_filters( 'envcheck_consent_category', 'statistics' );
    if ( function_exists( 'wp_has_consent' ) && ! wp_has_consent( $cat ) ) {
        wp_send_json_error( __( 'Consent not provided.', 'sparxstar-user-environment-check' ) );
    }

    // Daily throttle: once/day per IP hash
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $hash = md5( $ip );
    $daily_key = 'envcheck_daily_' . $hash;
    if ( get_site_transient( $daily_key ) ) {
        wp_send_json_error( __( 'Already logged today.', 'sparxstar-user-environment-check' ) );
    }
    set_site_transient( $daily_key, 1, DAY_IN_SECONDS );

    // Decode & sanitize
    $raw_json = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
    $raw_data = json_decode( $raw_json, true );
    if ( ! is_array( $raw_data ) ) {
        wp_send_json_error( __( 'Invalid data.', 'sparxstar-user-environment-check' ) );
    }
    $raw_data = envcheck_sanitize_recursive( $raw_data );

    // Privacy signals
    $privacy = $raw_data['privacy'] ?? [];
    $payload = ( ! empty( $privacy['doNotTrack'] ) || ! empty( $privacy['gpc'] ) )
        ? [
            'privacy'    => $privacy,
            'userAgent'  => $raw_data['userAgent'] ?? 'N/A',
            'os'         => $raw_data['os'] ?? 'N/A',
            'compatible' => $raw_data['compatible'] ?? 'unknown',
        ]
        : $raw_data;

    $entry = [
        'timestamp_utc' => gmdate( 'c' ),
        'site'          => [ 'home' => home_url(), 'blog_id' => get_current_blog_id() ],
        'diagnostics'   => $payload,
    ];

    // Ensure log dir
    if ( ! is_dir( ENVCHECK_LOG_DIR ) ) {
        wp_mkdir_p( ENVCHECK_LOG_DIR );
    }
    @file_put_contents( ENVCHECK_LOG_DIR . '/.htaccess', "Require all denied\n", LOCK_EX );
    @file_put_contents( ENVCHECK_LOG_DIR . '/index.php', "<?php // Silence is golden", LOCK_EX );

    // Write NDJSON
    $log_file = ENVCHECK_LOG_DIR . '/envcheck-' . gmdate( 'Y-m-d' ) . '.ndjson';
    if ( false === file_put_contents( $log_file, wp_json_encode( $entry ) . "\n", FILE_APPEND | LOCK_EX ) ) {
        wp_send_json_error( __( 'Log write failed.', 'sparxstar-user-environment-check' ) );
    }

    wp_send_json_success( __( 'Data logged.', 'sparxstar-user-environment-check' ) );
}
add_action( 'wp_ajax_envcheck_log', 'envcheck_handle_log_data' );
add_action( 'wp_ajax_nopriv_envcheck_log', 'envcheck_handle_log_data' );

/**
 * Cron: retention housekeeping
 */
function envcheck_housekeeping() {
    if ( ! is_dir( ENVCHECK_LOG_DIR ) ) {
        return;
    }
    foreach ( glob( ENVCHECK_LOG_DIR . '/envcheck-*.ndjson' ) as $file ) {
        if ( filemtime( $file ) < time() - ( ENVCHECK_RETENTION_DAYS * DAY_IN_SECONDS ) ) {
            @unlink( $file );
        }
    }
}
if ( ! wp_next_scheduled( 'envcheck_cron_housekeeping' ) ) {
    wp_schedule_event( time(), 'daily', 'envcheck_cron_housekeeping' );
}
add_action( 'envcheck_cron_housekeeping', 'envcheck_housekeeping' );
