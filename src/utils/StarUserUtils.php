<?php
/**
 * Utility helpers for SPARXSTAR environment diagnostics.
 *
 * Provides sanitized request metadata access and session-scoped storage helpers
 * that support both authenticated and anonymous visitors.
 *
 * @package SparxstarUserEnvironmentCheck
 * @version 1.1.0
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Collection of static helper methods for retrieving sanitized visitor metadata
 * and persisting environment snapshots in PHP session scope.
 */
final class StarUserUtils {

        /**
         * Session namespace key used to avoid collisions with other plugins.
         */
        private const SESSION_NAMESPACE = 'sparxstar_env';

        /**
         * Session storage key for the most recent environment snapshot.
         */
        private const SESSION_KEY = 'sparxstar_env_snapshot';

        /**
         * Ensure a PHP session is initialised before attempting to read or write data.
         *
         * @return void
         */
        private static function ensure_session(): void {
                if ( PHP_SESSION_ACTIVE === session_status() ) {
                        return;
                }

                if ( headers_sent() ) {
                        return;
                }

                $options = [
                        'name'            => 'spxenv',
                        'cookie_httponly' => true,
                        'cookie_samesite' => 'Lax',
                ];

                if ( is_ssl() ) {
                        $options['cookie_secure'] = true;
                }

                @session_start( $options );
        }

        /**
         * Retrieve a value from the $_SERVER superglobal with sanitization applied.
         *
         * @param string $key The key to retrieve from $_SERVER.
         * @return string The sanitized value or an empty string when unset.
         */
        private static function get_server_value( string $key ): string {
                return isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : '';
        }

        /**
         * Sanitize and validate an IP address string.
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

                $filtered_ip = filter_var( $value, FILTER_VALIDATE_IP );

                return $filtered_ip ? trim( $filtered_ip ) : '';
        }

        /**
         * Retrieve the client IP address considering proxy headers.
         *
         * @return string The client's IP address or '0.0.0.0' if undetectable.
         */
        public static function getClientIP(): string {
                $ip_headers = [
                        'HTTP_CF_CONNECTING_IP',
                        'HTTP_CLIENT_IP',
                        'HTTP_X_FORWARDED_FOR',
                        'HTTP_X_FORWARDED',
                        'HTTP_X_CLUSTER_CLIENT_IP',
                        'HTTP_FORWARDED_FOR',
                        'HTTP_FORWARDED',
                        'REMOTE_ADDR',
                ];

                foreach ( $ip_headers as $header ) {
                        $value = self::get_server_value( $header );
                        if ( '' === $value ) {
                                continue;
                        }

                        $ips = array_map( 'trim', explode( ',', $value ) );
                        foreach ( $ips as $ip ) {
                                $filtered_ip = self::filter_ip_address( $ip );
                                if ( '' !== $filtered_ip ) {
                                        return $filtered_ip;
                                }
                        }
                }

                return '0.0.0.0';
        }

        /**
         * Persist an arbitrary value in the session namespace.
         *
         * @param string $key   Session key.
         * @param mixed  $value Value to store.
         * @return void
         */
        public static function setSessionValue( string $key, $value ): void {
                self::ensure_session();
                $_SESSION[ self::SESSION_NAMESPACE ][ $key ] = $value;
        }

        /**
         * Retrieve a value from the session namespace.
         *
         * @param string $key     Session key.
         * @param mixed  $default Default value when the key is absent.
         * @return mixed
         */
        public static function getSessionValue( string $key, $default = null ) {
                self::ensure_session();

                return $_SESSION[ self::SESSION_NAMESPACE ][ $key ] ?? $default;
        }

        /**
         * Store an environment snapshot and its context within the PHP session.
         *
         * @param array $snapshot Snapshot payload recorded from the client.
         * @param array $context  Supplemental server-side context data.
         * @return void
         */
        public static function storeEnvironmentSnapshot( array $snapshot, array $context = [] ): void {
                self::ensure_session();

                $_SESSION[ self::SESSION_NAMESPACE ][ self::SESSION_KEY ] = [
                        'snapshot'  => $snapshot,
                        'context'   => $context,
                        'stored_at' => gmdate( 'c' ),
                ];
        }

        /**
         * Retrieve the stored environment snapshot from the PHP session.
         *
         * @return array Snapshot data or an empty array when nothing is stored.
         */
        public static function getEnvironmentSnapshot(): array {
                self::ensure_session();

                $stored = $_SESSION[ self::SESSION_NAMESPACE ][ self::SESSION_KEY ] ?? [];

                return is_array( $stored ) ? $stored : [];
        }

        /**
         * Retrieve the active PHP session identifier when available.
         *
         * @return string Session identifier or an empty string when no session is active.
         */
        public static function getSessionID(): string {
                return PHP_SESSION_ACTIVE === session_status() ? (string) session_id() : '';
        }

        /**
         * Access the current user agent string with sanitization applied.
         *
         * @return string Sanitized user agent string.
         */
        public static function getUserAgent(): string {
                return sanitize_text_field( self::get_server_value( 'HTTP_USER_AGENT' ) );
        }

        /**
         * Build the current request URL using sanitized server globals.
         *
         * @return string Normalized current URL or an empty string when incomplete data is available.
         */
        public static function getCurrentURL(): string {
                $host = self::get_server_value( 'HTTP_HOST' );
                $uri  = self::get_server_value( 'REQUEST_URI' );

                if ( '' === $host || '' === $uri ) {
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
        public static function getReferrerURL(): string {
                $referer = self::get_server_value( 'HTTP_REFERER' );

                return $referer ? esc_url_raw( $referer ) : '';
        }

        /**
         * Fetch geolocation data using an external provider hooked via WordPress filters.
         *
         * @param string $ip The IP address to lookup. Defaults to the current client IP.
         * @return array Associative array of geolocation data supplied by integrations.
         */
        public static function getIPGeoLocation( string $ip = '' ): array {
                if ( '' === $ip ) {
                        $ip = self::getClientIP();
                }

                $data = apply_filters( 'sparxstar_env_geolocation_lookup', null, $ip );

                return is_array( $data ) ? $data : [];
        }

        /**
         * Retrieve a specific field from the geolocation payload or the full JSON.
         *
         * @param string $field Optional field name to fetch.
         * @param string $ip    The IP address to lookup. Defaults to the current client IP.
         * @return string Human-readable geolocation string, JSON string, or a fallback message.
         */
        public static function getGeoLocationData( string $field = '', string $ip = '' ): string {
                $location = self::getIPGeoLocation( $ip );

                if ( empty( $location ) ) {
                        return __( 'Location data unavailable.', 'sparxstar-user-environment-check' );
                }

                if ( '' === $field ) {
                        return wp_json_encode( $location, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                }

                $map = [
                        'city'         => 'city',
                        'region'       => 'regionName',
                        'country'      => 'countryName',
                        'country_code' => 'countryCode',
                        'latitude'     => 'lat',
                        'longitude'    => 'lon',
                        'zip'          => 'zip',
                        'timezone'     => 'timezone',
                ];

                $key = $map[ strtolower( $field ) ] ?? null;
                if ( $key && isset( $location[ $key ] ) ) {
                        return sanitize_text_field( (string) $location[ $key ] );
                }

                return __( 'Specific location data unavailable.', 'sparxstar-user-environment-check' );
        }

        /**
         * Determine the preferred language from the Accept-Language header.
         *
         * @param string $ret_type Either 'code' or 'locale'.
         * @return string Sanitized language representation.
         */
        public static function getUserLanguage( string $ret_type = 'code' ): string {
                $raw = self::get_server_value( 'HTTP_ACCEPT_LANGUAGE' );
                if ( '' === $raw ) {
                        return '';
                }

                $parts   = explode( ',', $raw );
                $primary = trim( explode( ';', $parts[0] )[0] );

                if ( 'code' === strtolower( $ret_type ) ) {
                        return sanitize_text_field( substr( $primary, 0, 2 ) );
                }

                return sanitize_text_field( $primary );
        }

        /**
         * Determine the visitor operating system based on the User-Agent string.
         *
         * @return string Friendly operating system name (e.g., "Windows", "Mac").
         */
        public static function getUserOS(): string {
                $user_agent = strtolower( self::getUserAgent() );
                $map        = [
                        'windows'              => 'Windows',
                        'macintosh|mac os x|macos' => 'Mac',
                        'linux'                => 'Linux',
                        'ipad|ipod|iphone'     => 'iOS',
                        'android'              => 'Android',
                        'blackberry'           => 'BlackBerry',
                        'webos'                => 'webOS',
                        'windows phone'        => 'Windows Phone',
                ];

                foreach ( $map as $needle => $label ) {
                        if ( preg_match( '/' . $needle . '/', $user_agent ) ) {
                                return $label;
                        }
                }

                return 'Other';
        }

        /**
         * Get the approximate browser name based on the User-Agent string.
         *
         * @return string Friendly browser name (e.g., "Chrome", "Firefox").
         */
        public static function getUserBrowser(): string {
                $user_agent = self::getUserAgent();

                if ( preg_match( '/MSIE/i', $user_agent ) && ! preg_match( '/Opera/i', $user_agent ) ) {
                        return 'Internet Explorer';
                } elseif ( preg_match( '/Firefox/i', $user_agent ) ) {
                        return 'Firefox';
                } elseif ( preg_match( '/Chrome/i', $user_agent ) ) {
                        return 'Chrome';
                } elseif ( preg_match( '/Safari/i', $user_agent ) ) {
                        return 'Safari';
                } elseif ( preg_match( '/Opera/i', $user_agent ) ) {
                        return 'Opera';
                } elseif ( preg_match( '/Netscape/i', $user_agent ) ) {
                        return 'Netscape';
                } elseif ( preg_match( '/Edge/i', $user_agent ) ) {
                        return 'Edge';
                } elseif ( preg_match( '/CriOS/i', $user_agent ) ) {
                        return 'Chrome iOS';
                } elseif ( preg_match( '/FxiOS/i', $user_agent ) ) {
                        return 'Firefox iOS';
                }

                return 'Unknown';
        }

        /**
         * Determine if the request is from a bot/crawler using common user agent patterns.
         *
         * @return bool True if a bot is detected, false otherwise.
         */
        public static function isBot(): bool {
                $user_agent = self::getUserAgent();
                $bots       = [
                        'googlebot', 'bingbot', 'yandexbot', 'duckduckbot', 'slurp', 'baiduspider',
                        'facebookexternalhit', 'pinterestbot', 'linkedinbot', 'twitterbot', 'mj12bot',
                        'ahrefsbot', 'semrushbot', 'petalbot', 'sogouspider', 'exabot', 'facebot',
                        'ia_archiver', 'alexabot', 'megaindex', 'developers.google.com/speed/pagespeed/insights',
                        'gtmetrix', 'pingdom', 'uptimebot', 'lighthouse', 'w3c_validator', 'screaming frog',
                ];

                foreach ( $bots as $bot ) {
                        if ( false !== stripos( $user_agent, $bot ) ) {
                                return true;
                        }
                }
                return false;
        }

        /**
         * Retrieve the current HTTP method (GET, POST, etc.).
         *
         * @return string The HTTP method.
         */
        public static function getRequestMethod(): string {
                return sanitize_text_field( self::get_server_value( 'REQUEST_METHOD' ) );
        }

        /**
         * Check if the current request is an AJAX request.
         *
         * @return bool True if it's an AJAX request, false otherwise.
         */
        public static function isAjax(): bool {
                return ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
                        ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'xmlhttprequest' === strtolower( (string) $_SERVER['HTTP_X_REQUESTED_WITH'] ) );
        }

        /**
         * Get the current WordPress environment type.
         *
         * @return string The environment type (e.g., 'development', 'staging', 'production').
         */
        public static function getWpEnvironmentType(): string {
                if ( function_exists( 'wp_get_environment_type' ) ) {
                        return wp_get_environment_type();
                }

                if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
                        return WP_ENVIRONMENT_TYPE;
                }
                return 'production';
        }
}
