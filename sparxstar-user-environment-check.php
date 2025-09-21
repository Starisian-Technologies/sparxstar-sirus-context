<?php
/**
 * Plugin Name:       SPARXSTAR User Environment Check
 * Plugin URI:        https://github.com/Starisian-Technologies/sparxstar-user-environment-check
 * Description:       A network-wide utility that checks browser compatibility and logs anonymized technical data for diagnostics, with consent via the WP Consent API.
 * Version:           2.2.4 (Multinetwork Aware)
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Starisian Technologies (Max Barrett)
 * Author URI:        https://starisian.com
 * Text Domain:       sparxstar-user-environment-check
 * Domain Path:       /languages
 * License:           Proprietary
 * License URI:       https://github.com/Starisian-Technologies/sparxstar-user-environment-check/LICENSE
 * Update URI:        https://github.com/Starisian-Technologies/sparxstar-user-environment-check.git
 *
 * @package           SparxstarUserEnvironmentCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sparxstar_User_Environment_Check {

	private static $instance = null;

	// **MODIFIED**: This is no longer a constant, as it needs to be network-aware.
	private $log_dir;
	private $cron_hook;

	const RETENTION_DAYS   = 30;
	const TEXT_DOMAIN      = 'sparxstar-user-environment-check';

	/**
	 * Singleton init.
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// **NEW**: Set up network-aware properties for multinetwork compatibility.
		$network_id      = function_exists( 'get_current_network_id' ) ? get_current_network_id() : 1;
		$this->log_dir   = WP_CONTENT_DIR . '/envcheck-logs/network-' . $network_id;
		$this->cron_hook = 'envcheck_cron_housekeeping_' . $network_id;

		// i18n
		add_action( 'init', [ $this, 'load_textdomain' ] );
		// Assets
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		// REST route
		add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
		// Housekeeping using the network-specific cron hook.
		add_action( $this->cron_hook, [ $this, 'housekeeping' ] );
		add_action( 'init', [ $this, 'schedule_cron_jobs' ] );
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	public function enqueue_assets() {
		$consent_category = apply_filters( 'envcheck_consent_category', 'statistics' );
		if ( function_exists( 'wp_has_consent' ) && ! wp_has_consent( $consent_category ) ) {
			return;
		}

		$script_path = __DIR__ . '/assets/js/sparxstar-user-environment-check.min.js';
		$style_path  = __DIR__ . '/assets/css/sparxstar-user-environment-check.min.css';
		$base_url    = plugin_dir_url( __FILE__ );

		wp_enqueue_script(
			'envcheck-js',
			$base_url . 'assets/js/sparxstar-user-environment-check.min.js',
			[],
			filemtime( $script_path ),
			true
		);

		wp_enqueue_style(
			'envcheck-css',
			$base_url . 'assets/css/sparxstar-user-environment-check.min.css',
			[],
			filemtime( $style_path )
		);

		wp_localize_script(
			'envcheck-js',
			'envCheckData',
			[
				'nonce'    => wp_create_nonce( 'wp_rest' ), // Use wp_rest nonce for REST API.
				'ajax_url' => rest_url( 'env/v1/log' ),
				'i18n'     => [
					'notice'         => __( 'Notice:', self::TEXT_DOMAIN ),
					'update_message' => __( 'Your browser may be outdated. For the best experience, please', self::TEXT_DOMAIN ),
					'update_link'    => __( 'update your browser', self::TEXT_DOMAIN ),
					'dismiss'        => __( 'Dismiss', self::TEXT_DOMAIN ),
				],
			]
		);
	}

	public function register_rest_route() {
		register_rest_route(
			'env/v1',
			'/log',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_log_request' ],
				'permission_callback' => '__return_true', // Public, but we perform checks inside.
			]
		);
	}

	private function sanitize_recursive( $value ) {
		if ( is_array( $value ) ) {
			return array_map( [ $this, 'sanitize_recursive' ], $value );
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : $value;
	}

	public function handle_log_request( WP_REST_Request $request ) {
		// Consent enforcement
		$consent_category = apply_filters( 'envcheck_consent_category', 'statistics' );
		if ( function_exists( 'wp_has_consent' ) && ! wp_has_consent( $consent_category ) ) {
			return new WP_REST_Response( [ 'success' => false, 'data' => __( 'Consent not provided.', self::TEXT_DOMAIN ) ], 403 );
		}

		$ip      = $request->get_ip_address();
		$ip_hash = md5( $ip );

		// Rate-limit
		if ( ! is_user_logged_in() && get_transient( 'envcheck_rate_' . $ip_hash ) ) {
			return new WP_REST_Response( [ 'success' => false, 'data' => __( 'Rate limit hit. Please try again later.', self::TEXT_DOMAIN ) ], 429 );
		}
		set_transient( 'envcheck_rate_' . $ip_hash, 1, MINUTE_IN_SECONDS );

		$raw_data = $request->get_json_params();
		if ( ! is_array( $raw_data ) ) {
			return new WP_REST_Response( [ 'success' => false, 'data' => __( 'Invalid data format.', self::TEXT_DOMAIN ) ], 400 );
		}
		$sanitized_data = $this->sanitize_recursive( $raw_data );

		// Daily throttle with session awareness
		$current_user_id = get_current_user_id();
		$session_id      = $sanitized_data['sessionId'] ?? null;
		$daily_key       = $this->get_daily_key( $current_user_id, $ip_hash, $session_id );

		if ( get_site_transient( $daily_key ) ) {
			return new WP_REST_Response( [ 'success' => false, 'data' => __( 'Already logged today for this session/device.', self::TEXT_DOMAIN ) ], 429 );
		}
		set_site_transient( $daily_key, 1, DAY_IN_SECONDS );

		// Collect client hints
		$client_hints = [];
		foreach ( [ 'Sec-CH-UA','Sec-CH-UA-Mobile','Sec-CH-UA-Platform','Sec-CH-UA-Model','Sec-CH-UA-Arch','Sec-CH-UA-Bitness','Sec-CH-UA-Full-Version' ] as $h ) {
			$header_value = $request->get_header( $h );
			if ( ! empty( $header_value ) ) {
				$client_hints[ $h ] = sanitize_text_field( $header_value );
			}
		}

		$privacy = $sanitized_data['privacy'] ?? [];
		$payload = $sanitized_data;
		$payload['client_hints'] = $client_hints;

		if ( ! empty( $privacy['doNotTrack'] ) || ! empty( $privacy['gpc'] ) ) {
			$payload = [
				'privacy'      => $privacy,
				'userAgent'    => $sanitized_data['userAgent'] ?? 'N/A',
				'os'           => $sanitized_data['os'] ?? 'N/A',
				'language'     => $sanitized_data['language'] ?? 'N/A',
				'compatible'   => $sanitized_data['compatible'] ?? 'unknown',
				'features'     => $sanitized_data['features'] ?? [],
				'client_hints' => $client_hints,
				'sessionId'    => $sanitized_data['sessionId'] ?? null,
			];
		}

		// Allow other plugins to modify the payload before logging.
		$payload = apply_filters( 'sparxstar_env_snapshot_payload', $payload, $request );

		$entry = [
			'timestamp_utc' => gmdate( 'c' ),
			'user_id'       => $current_user_id,
			'site'          => [ 'home' => home_url(), 'blog_id' => get_current_blog_id() ],
			'diagnostics'   => $payload,
		];

		// **MODIFIED**: Use the network-aware log directory property.
		if ( ! is_dir( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );
		}
		@file_put_contents( $this->log_dir . '/.htaccess', "Require all denied\n", LOCK_EX );
		@file_put_contents( $this->log_dir . '/index.php', "<?php // Silence is golden", LOCK_EX );

		// **MODIFIED**: Use the network-aware log directory property.
		$log_file = $this->log_dir . '/envcheck-' . gmdate( 'Y-m-d' ) . '.ndjson';
		$result   = file_put_contents( $log_file, wp_json_encode( $entry ) . "\n", FILE_APPEND | LOCK_EX );

		if ( false === $result ) {
			error_log( 'SPARXSTAR EnvCheck: Log write failed to ' . $log_file );
			do_action( 'envcheck_log_error', $log_file, $entry );
			return new WP_REST_Response( [ 'success' => false, 'data' => __( 'Log write failed.', self::TEXT_DOMAIN ) ], 500 );
		}

		// Cache the latest snapshot in a transient for fast access.
		set_transient( 'sparxstar_env_last_' . $daily_key, $entry, DAY_IN_SECONDS );

		/**
		 * **NEW**: Fires after a new environment snapshot has been successfully saved.
		 *
		 * This allows other plugins to react to the new data in real-time.
		 *
		 * @since 2.2.3
		 * @param array $entry The complete log entry that was saved.
		 */
		do_action( 'sparxstar_env_snapshot_saved', $entry );

		return new WP_REST_Response( [ 'success' => true, 'data' => __( 'Data logged.', self::TEXT_DOMAIN ), 'snapshot_id' => $daily_key ], 200 );
	}

	/**
	 * Public method to retrieve the latest snapshot for a user/session.
	 *
	 * @param int|null    $user_id    The user ID to look for. Defaults to current user.
	 * @param string|null $session_id Optional session ID to narrow the search.
	 * @return array|null The snapshot entry array, or null if not found.
	 */
	public function get_snapshot( $user_id = null, $session_id = null ) {
		$user_id = is_null( $user_id ) ? get_current_user_id() : (int) $user_id;
		$ip_hash = md5( $_SERVER['REMOTE_ADDR'] ?? '' );
		$key     = $this->get_daily_key( $user_id, $ip_hash, $session_id );

		// 1. Try to get from the fast transient cache first.
		$cached_snapshot = get_transient( 'sparxstar_env_last_' . $key );
		if ( false !== $cached_snapshot && is_array( $cached_snapshot ) ) {
			return $cached_snapshot;
		}

		// 2. If not cached, read from today's log file.
		// **MODIFIED**: Use the network-aware log directory property.
		$log_file = $this->log_dir . '/envcheck-' . gmdate( 'Y-m-d' ) . '.ndjson';
		if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
			return null;
		}

		$lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		// Search backwards, as the most recent entry is likely the correct one.
		foreach ( array_reverse( $lines ) as $line ) {
			$entry = json_decode( $line, true );
			if ( ! $entry || ! isset( $entry['user_id'] ) ) {
				continue;
			}

			// Check if the user ID matches.
			if ( (int) $entry['user_id'] === $user_id ) {
				// If a session ID is provided, it must also match.
				if ( ! $session_id || ( $entry['diagnostics']['sessionId'] ?? null ) === $session_id ) {
					return $entry; // Found a match.
				}
			}
		}

		return null; // No snapshot found.
	}

	/**
	 * Helper to consistently generate the daily transient key.
	 */
	private function get_daily_key( $user_id, $ip_hash, $session_id = null ) {
		$key = $user_id ? 'envcheck_daily_user_' . $user_id : 'envcheck_daily_anon_' . $ip_hash;
		if ( $session_id ) {
			$key .= '_' . sanitize_key( $session_id );
		}
		return $key;
	}

	public function housekeeping() {
		// **MODIFIED**: Use the network-aware log directory property.
		if ( ! is_dir( $this->log_dir ) ) {
			return;
		}
		// **MODIFIED**: Use the network-aware log directory property.
		foreach ( new DirectoryIterator( $this->log_dir ) as $fileinfo ) {
			if ( $fileinfo->isFile() && 'ndjson' === $fileinfo->getExtension() && str_starts_with( $fileinfo->getFilename(), 'envcheck-' ) ) {
				if ( $fileinfo->getMTime() < time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) ) {
					@unlink( $fileinfo->getPathname() );
				}
			}
		}
	}

	public function schedule_cron_jobs() {
		// **MODIFIED**: Use the network-aware cron hook property.
		if ( ! wp_next_scheduled( $this->cron_hook ) ) {
			wp_schedule_event( time(), 'daily', $this->cron_hook );
		}
	}
}

/**
 * Global helper function for easy access to the environment snapshot.
 *
 * Provides a simple, predictable way for other plugins and themes to retrieve
 * the most recent environment data for a user and/or session.
 *
 * @param int|null    $user_id    The user ID. Defaults to the current user.
 * @param string|null $session_id Optional. The specific session ID to find.
 * @return array|null The full snapshot data or null if not found.
 */
if ( ! function_exists( 'sparxstar_get_env_snapshot' ) ) {
	function sparxstar_get_env_snapshot( $user_id = null, $session_id = null ) {
		return Sparxstar_User_Environment_Check::init()->get_snapshot( $user_id, $session_id );
	}
}


// Initialize the plugin.
Sparxstar_User_Environment_Check::init();