<?php
/**
 * Utility helpers for SPARXSTAR environment diagnostics.
 *
 * @package SparxstarUserEnvironmentCheck
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collection of static helper methods for retrieving sanitized visitor metadata.
*/
final class StarUserUtils {

	/**
	 * Retrieve the direct remote IP address exposed by the server.
	 *
	 * @return string Normalized IP address or an empty string when unavailable.
	*/
	public static function star_getIP(): string {
		return self::filter_ip_address( $_SERVER['REMOTE_ADDR'] ?? '' );
	}

	/**
	 * Determine the most reliable client IP address using proxy-aware headers.
	 *
	 * @return string Normalized IP address or an empty string when it cannot be determined.
	*/
	public static function star_getUserIP(): string {
		$headers = [
		'HTTP_CLIENT_IP',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'REMOTE_ADDR',
		];

		foreach ( $headers as $header ) {
			$value = self::get_server_value( $header );
			if ( empty( $value ) ) {
				continue;
			}

	 // Handle comma-separated lists from X-Forwarded-For.
			$maybe_ip = strtok( $value, ',' );
			$maybe_ip = self::filter_ip_address( $maybe_ip );
			if ( $maybe_ip ) {
				return $maybe_ip;
			}
		}

		return '';
	}

	/**
	 * Retrieve the active PHP session identifier when available.
	 *
	 * @return string Session identifier or an empty string when no session is active.
	*/
	public static function star_getUserSessionID(): string {
		return PHP_SESSION_ACTIVE === session_status() ? session_id() : '';
	}

	/**
	 * Access the current user agent string with sanitization applied.
	 *
	 * @return string Sanitized user agent string.
	*/
	public static function star_getUserAgent(): string {
		return self::get_server_value( 'HTTP_USER_AGENT' );
	}

	/**
	 * Build the current request URL using sanitized server globals.
	 *
	 * @return string Normalized current URL or an empty string when incomplete data is available.
	*/
	public static function star_getCurrentURL(): string {
		$host = self::get_server_value( 'HTTP_HOST' );
		$uri  = self::get_server_value( 'REQUEST_URI' );

		if ( empty( $host ) || empty( $uri ) ) {
			return '';
		}

		$scheme = is_ssl() ? 'https://' : 'http://';

		return esc_url_raw( $scheme . $host . $uri );
	}

	/**
	 * Retrieve the referring URL from the request headers.
	 *
	 * @return string Normalized referrer URL or an empty string when not provided.
	*/
	public static function star_getReferrerURL(): string {
		$referer = self::get_server_value( 'HTTP_REFERER' );

		return $referer ? esc_url_raw( $referer ) : '';
	}

	/**
	 * Fetch geolocation data using an external provider hooked via WordPress filters.
	 *
	 * @return array Associative array of geolocation data supplied by integrations.
	*/
	public static function star_getIPGeoLocation(): array {
		$ip   = self::star_getUserIP();
		$data = apply_filters( 'sparxstar_env_geolocation_lookup', null, $ip );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * Retrieve a specific field from the geolocation payload.
	 *
	 * @param string $field Optional field name to fetch (city, region, country, location).
	 * @return string Human-readable geolocation string or a fallback message.
	*/
	public static function star_getGeoLocationData( string $field = '' ): string {
		$location = self::star_getIPGeoLocation();

		if ( empty( $location ) ) {
			return __( 'Location data unavailable.', 'sparxstar-user-environment-check' );
		}

		if ( '' === $field ) {
			return wp_json_encode( $location );
		}

		$map = [
		'city'     => 'city',
		'region'   => 'region',
		'country'  => 'country',
		'location' => 'loc',
		];

		$key = $map[ $field ] ?? null;
		if ( $key && ! empty( $location[ $key ] ) ) {
			return trim( (string) $location[ $key ] );
		}

		return __( 'Location data unavailable.', 'sparxstar-user-environment-check' );
	}

	/**
	 * Determine the preferred language from the Accept-Language header.
	 *
	 * @param string $ret_type Either 'code' for the ISO code or 'locale' for the full locale string.
	 * @return string Sanitized language representation.
	*/
	public static function star_getUserLanguage( string $ret_type = 'code' ): string {
		$raw = self::get_server_value( 'HTTP_ACCEPT_LANGUAGE' );
		if ( empty( $raw ) ) {
			return '';
		}

		$primary = explode( ',', $raw )[0];
		$primary = explode( ';', $primary )[0];
		$primary = trim( $primary );

		if ( 'code' === strtolower( $ret_type ) ) {
			return substr( $primary, 0, 2 );
		}

		return $primary;
	}

	/**
	 * Classify the visitor device type based on the user agent string.
	 *
	 * @return string One of "Mobile", "Tablet", or "Desktop".
	*/
	public static function star_getUserDeviceType(): string {
		$user_agent = strtolower( self::star_getUserAgent() );

		if ( preg_match( '/tablet|ipad/', $user_agent ) ) {
			return 'Tablet';
		}

		if ( preg_match( '/mobi|android/', $user_agent ) ) {
			return 'Mobile';
		}

		return 'Desktop';
	}

	/**
	 * Determine the visitor operating system using a lightweight signature map.
	 *
	 * @return string Friendly operating system name.
	*/
	public static function star_getUserOS(): string {
		$user_agent = strtolower( self::star_getUserAgent() );
		$map        = [
		'windows' => 'Windows',
		'mac'     => 'Mac',
		'linux'   => 'Linux',
		'unix'    => 'Unix',
		'ios'     => 'iOS',
		'android' => 'Android',
		];

		foreach ( $map as $needle => $label ) {
			if ( str_contains( $user_agent, $needle ) ) {
				return $label;
			}
		}

		return 'Other';
	}

	/**
	 * Sanitize and validate IP address strings.
	 *
	 * @param string|null $value Potential IP address.
	 * @return string Normalized IP or empty string on failure.
	*/
	private static function filter_ip_address( ?string $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );
		if ( str_contains( $value, ',' ) ) {
			$value = strtok( $value, ',' );
		}

		$value = sanitize_text_field( wp_unslash( $value ) );

		return filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '';
	}

	/**
	 * Retrieve a sanitized server variable by key.
	 *
	 * @param string $key Server array key to read.
	 * @return string Sanitized value or empty string.
	*/
	private static function get_server_value( string $key ): string {
		return isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
	}
}
