<?php
/**
 * Environment Check REST API Handler
 * 
 * Handles the collection and storage of environment diagnostics with enhanced
 * security, session awareness, client hints, and concurrency handling.
 *
 * @package SparxstarUserEnvironmentCheck
 * @since 2.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EnvCheckAPI {
	
	private static $instance = null;
	
	/**
	 * Rate limiting settings
	 */
	private const RATE_LIMIT_WINDOW = 300; // 5 minutes
	private const RATE_LIMIT_MAX_REQUESTS = 10; // Max requests per window
	
	/**
	 * Singleton instance
	 */
	public static function init() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
		add_action( 'wp', [ $this, 'schedule_cleanup' ] );
	}

	/**
	 * Register the REST API endpoint
	 */
	public function register_rest_route() {
		register_rest_route( 'env/v1', '/log', [
			'methods'  => 'POST',
			'callback' => [ $this, 'handle_log_request' ],
			'permission_callback' => '__return_true', // Open endpoint for anonymized data
			'args' => [
				'data' => [
					'required' => true,
					'type' => 'object',
					'description' => 'Environment diagnostic data',
				],
			],
		] );
	}

	/**
	 * Handle the log request with enhanced security and session awareness
	 */
	public function handle_log_request( WP_REST_Request $request ) {
		// Verify nonce for additional security
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 
				'invalid_nonce', 
				'Invalid security token.', 
				[ 'status' => 403 ] 
			);
		}

		// Rate limiting check
		if ( ! $this->check_rate_limit() ) {
			return new WP_Error( 
				'rate_limited', 
				'Too many requests. Please wait before sending more data.', 
				[ 'status' => 429 ] 
			);
		}

		$data = $request->get_json_params();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', 'Invalid JSON payload.', [ 'status' => 400 ] );
		}

		// Enhanced data validation and sanitization
		$sanitized_data = $this->sanitize_diagnostic_data( $data );
		
		if ( is_wp_error( $sanitized_data ) ) {
			return $sanitized_data;
		}

		// Collect client hints from headers
		$client_hints = $this->collect_client_hints();
		if ( ! empty( $client_hints ) ) {
			$sanitized_data['clientHints'] = $client_hints;
		}

		// Enhanced session awareness
		$session_id = $sanitized_data['sessionId'] ?? null;
		$user_id = get_current_user_id();
		
		if ( $user_id ) {
			$snapshot_id = 'user_' . $user_id . ( $session_id ? "_$session_id" : '' );
		} else {
			// Enhanced fingerprinting with client IP detection
			$client_ip = $this->get_client_ip();
			$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
			$fingerprint = hash( 'sha256', $client_ip . $user_agent );
			$snapshot_id = 'anon_' . $fingerprint . ( $session_id ? "_$session_id" : '' );
		}

		// Store the diagnostic data with file locking for concurrency safety
		$result = $this->store_diagnostic_data( $snapshot_id, $sanitized_data );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Log the event for debugging
		error_log( sprintf( 
			'[EnvCheck] Diagnostic data stored: %s (User: %s, Session: %s)', 
			$snapshot_id, 
			$user_id ?: 'anonymous', 
			$session_id ?: 'none' 
		) );

		return [ 
			'status' => 'ok', 
			'snapshot_id' => $snapshot_id,
			'timestamp' => current_time( 'mysql', true )
		];
	}

	/**
	 * Get client IP with support for proxies and CDNs
	 */
	private function get_client_ip() {
		$ip_headers = [
			'HTTP_CF_CONNECTING_IP',     // Cloudflare
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		];

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = $_SERVER[ $header ];
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Collect Client Hints from headers for better device fingerprinting
	 */
	private function collect_client_hints() {
		$client_hints = [];
		
		$hint_headers = [
			'HTTP_SEC_CH_UA' => 'userAgent',
			'HTTP_SEC_CH_UA_MOBILE' => 'mobile',
			'HTTP_SEC_CH_UA_PLATFORM' => 'platform',
			'HTTP_SEC_CH_UA_PLATFORM_VERSION' => 'platformVersion',
			'HTTP_SEC_CH_UA_ARCH' => 'architecture',
			'HTTP_SEC_CH_UA_BITNESS' => 'bitness',
			'HTTP_SEC_CH_UA_MODEL' => 'model',
			'HTTP_SEC_CH_UA_FULL_VERSION' => 'fullVersion',
		];

		foreach ( $hint_headers as $header => $key ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$client_hints[ $key ] = sanitize_text_field( $_SERVER[ $header ] );
			}
		}

		return $client_hints;
	}

	/**
	 * Enhanced rate limiting with IP-based tracking
	 */
	private function check_rate_limit() {
		$client_ip = $this->get_client_ip();
		$rate_key = 'envcheck_rate_' . hash( 'md5', $client_ip );
		
		$current_requests = get_transient( $rate_key ) ?: 0;
		
		if ( $current_requests >= self::RATE_LIMIT_MAX_REQUESTS ) {
			return false;
		}
		
		set_transient( $rate_key, $current_requests + 1, self::RATE_LIMIT_WINDOW );
		
		return true;
	}

	/**
	 * Sanitize and validate diagnostic data
	 */
	private function sanitize_diagnostic_data( $data ) {
		$sanitized = [];
		
		// Allowed fields with their sanitization functions
		$allowed_fields = [
			'sessionId' => 'sanitize_text_field',
			'userAgent' => 'sanitize_text_field',
			'os' => 'sanitize_text_field',
			'language' => 'sanitize_text_field',
			'screen' => null, // Will be handled separately
			'features' => null, // Will be handled separately
			'privacy' => null, // Will be handled separately
			'compatible' => 'boolval',
			'storage' => null,
			'micPermission' => 'sanitize_text_field',
			'battery' => null,
		];

		foreach ( $allowed_fields as $field => $sanitizer ) {
			if ( isset( $data[ $field ] ) ) {
				if ( $sanitizer && is_callable( $sanitizer ) ) {
					$sanitized[ $field ] = call_user_func( $sanitizer, $data[ $field ] );
				} elseif ( is_array( $data[ $field ] ) ) {
					$sanitized[ $field ] = $this->sanitize_array_recursively( $data[ $field ] );
				} else {
					$sanitized[ $field ] = $data[ $field ];
				}
			}
		}

		// Validate session ID format
		if ( isset( $sanitized['sessionId'] ) && ! preg_match( '/^[a-zA-Z0-9_-]+$/', $sanitized['sessionId'] ) ) {
			return new WP_Error( 'invalid_session', 'Invalid session ID format.', [ 'status' => 400 ] );
		}

		// Strip potentially sensitive data if privacy signals are detected
		if ( ! empty( $sanitized['privacy']['doNotTrack'] ) || ! empty( $sanitized['privacy']['gpc'] ) ) {
			$sanitized = array_intersect_key( $sanitized, array_flip( [
				'sessionId', 'privacy', 'userAgent', 'os', 'compatible', 'features'
			] ) );
		}

		return $sanitized;
	}

	/**
	 * Recursively sanitize array data
	 */
	private function sanitize_array_recursively( $array ) {
		$sanitized = [];
		
		foreach ( $array as $key => $value ) {
			$clean_key = sanitize_key( $key );
			
			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = $this->sanitize_array_recursively( $value );
			} elseif ( is_string( $value ) ) {
				$sanitized[ $clean_key ] = sanitize_text_field( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $clean_key ] = is_float( $value ) ? floatval( $value ) : intval( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $clean_key ] = boolval( $value );
			}
		}
		
		return $sanitized;
	}

	/**
	 * Store diagnostic data with file locking for concurrency safety
	 */
	private function store_diagnostic_data( $snapshot_id, $data ) {
		$today = date( 'Y-m-d' );
		$upload_dir = wp_upload_dir();
		$log_dir = trailingslashit( $upload_dir['basedir'] ) . 'env-logs/';
		
		// Ensure directory exists
		if ( ! wp_mkdir_p( $log_dir ) ) {
			return new WP_Error( 'directory_error', 'Failed to create log directory.', [ 'status' => 500 ] );
		}

		$file = $log_dir . $today . '.json';

		// Load current daily log with file locking
		$entries = [];
		if ( file_exists( $file ) ) {
			$file_handle = fopen( $file, 'r' );
			if ( $file_handle && flock( $file_handle, LOCK_SH ) ) {
				$json = fread( $file_handle, filesize( $file ) );
				$entries = json_decode( $json, true ) ?: [];
				flock( $file_handle, LOCK_UN );
				fclose( $file_handle );
			} else {
				return new WP_Error( 'file_lock_error', 'Could not acquire file lock for reading.', [ 'status' => 500 ] );
			}
		}

		// Add or update entry
		$entries[ $snapshot_id ] = [
			'timestamp' => current_time( 'mysql', true ),
			'data' => $data,
			'ip_hash' => hash( 'sha256', $this->get_client_ip() ), // Store hashed IP for analytics
		];

		// Write back with exclusive lock
		$json_data = wp_json_encode( $entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$bytes_written = file_put_contents( $file, $json_data, LOCK_EX );
		
		if ( $bytes_written === false ) {
			return new WP_Error( 'write_error', 'Failed to write diagnostic data.', [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * Schedule cleanup job
	 */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'envcheck_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'envcheck_cleanup_logs' );
		}
		
		add_action( 'envcheck_cleanup_logs', [ $this, 'cleanup_old_logs' ] );
	}

	/**
	 * Clean up old log files
	 */
	public function cleanup_old_logs() {
		$upload_dir = wp_upload_dir();
		$log_dir = trailingslashit( $upload_dir['basedir'] ) . 'env-logs/';
		
		if ( ! is_dir( $log_dir ) ) {
			return;
		}

		$retention_days = defined( 'ENVCHECK_RETENTION_DAYS' ) ? ENVCHECK_RETENTION_DAYS : 30;
		$cutoff_time = time() - ( $retention_days * DAY_IN_SECONDS );
		
		$files = glob( $log_dir . '*.json' );
		$deleted_count = 0;
		
		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff_time ) {
				if ( unlink( $file ) ) {
					$deleted_count++;
				}
			}
		}
		
		if ( $deleted_count > 0 ) {
			error_log( sprintf( '[EnvCheck] Cleaned up %d old log files', $deleted_count ) );
		}
	}
}

// Initialize the API
EnvCheckAPI::init();
