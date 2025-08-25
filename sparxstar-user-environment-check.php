<?php
/**
 * Plugin Name: SPARXSTAR User Environment Check
 * Description: A network-wide utility that checks browser compatibility and logs anonymized technical data for diagnostics, with consent via the WP Consent API.
 * Version: 2.0
 * Author: Starisian Technologies
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueues the necessary CSS and JavaScript assets for the environment check.
 * Runs on both the front-end and the login page.
 */
function envcheck_enqueue_assets() {
    $script_path = __DIR__ . '/environment-check.js';
    $style_path  = __DIR__ . '/environment-check.css';

    // Only enqueue if the files actually exist.
    if ( ! file_exists( $script_path ) || ! file_exists( $style_path ) ) {
        return;
    }

    // Enqueue the JavaScript file.
    wp_enqueue_script(
        'envcheck-js',
        plugins_url( 'environment-check.js', __FILE__ ),
        [],
        filemtime( $script_path ), // Auto-versioning for cache-busting.
        true // Load in the footer.
    );

    // Enqueue the CSS for the warning banner.
    wp_enqueue_style(
        'envcheck-css',
        plugins_url( 'environment-check.css', __FILE__ ),
        [],
        filemtime( $style_path ) // Auto-versioning.
    );

    /**
     * Filter the consent category used for diagnostics.
     * Allows other plugins to change this from 'statistics' to 'statistics-anonymous', etc.
     *
     * @param string $category The consent category to check for.
     */
    $consent_category = apply_filters( 'envcheck_consent_category', 'statistics' );

    // Pass data from PHP to our JavaScript file.
    wp_localize_script(
        'envcheck-js',
        'envCheckData', // The JS expects an object with this name.
        [
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'envcheck_log_nonce' ),
            'consent_cat' => $consent_category,
        ]
    );
}
add_action( 'wp_enqueue_scripts', 'envcheck_enqueue_assets' );
add_action( 'login_enqueue_scripts', 'envcheck_enqueue_assets' );

/**
 * Creates the AJAX endpoint to securely receive and log the diagnostic data.
 */
function envcheck_handle_log_data() {
    // 1. Security First: Verify the nonce.
    check_ajax_referer( 'envcheck_log_nonce', 'nonce' );

    // 2. Get and decode the JSON data sent from the browser.
    $raw_data = isset( $_POST['data'] ) ? json_decode( stripslashes( $_POST['data'] ), true ) : null;

    if ( ! is_array( $raw_data ) ) {
        wp_send_json_error( 'Invalid data provided.' );
    }
    
    // 3. Respect server-side privacy signals (Do Not Track / Global Privacy Control).
    $privacy_signals = $raw_data['privacy'] ?? [];
    if ( ! empty( $privacy_signals['doNotTrack'] ) || ! empty( $privacy_signals['gpc'] ) ) {
        // If DNT/GPC is on, we only log the bare minimum for operational purposes.
        $log_data_diagnostics = [
            'privacy'    => $privacy_signals,
            'userAgent'  => $raw_data['userAgent'] ?? 'N/A',
            'os'         => $raw_data['os'] ?? 'N/A',
            'compatible' => $raw_data['compatible'] ?? 'unknown',
        ];
    } else {
        // Otherwise, log the full diagnostic payload.
        $log_data_diagnostics = $raw_data;
    }

    // 4. Prepare the final log entry in a structured, machine-readable format.
    $log_entry = [
        'timestamp_utc' => gmdate( 'Y-m-d H:i:s' ),
        'session_id'    => 'sid_' . substr( md5( ( $raw_data['userAgent'] ?? '' ) . ( $raw_data['os'] ?? '' ) ), 0, 12 ),
        'diagnostics'   => $log_data_diagnostics,
    ];

    // 5. Write the entry as a single JSON line to the log file.
    $log_file_path = WP_CONTENT_DIR . '/envcheck-diagnostics.log';
    error_log( wp_json_encode( $log_entry ) . "\n", 3, $log_file_path );

    wp_send_json_success( 'Data logged.' );
}
add_action( 'wp_ajax_envcheck_log', 'envcheck_handle_log_data' );
add_action( 'wp_ajax_nopriv_envcheck_log', 'envcheck_handle_log_data' ); // For logged-out users.
