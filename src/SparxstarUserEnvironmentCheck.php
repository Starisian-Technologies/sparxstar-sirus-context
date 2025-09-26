<?php
/**
 * Client hint header registration for SPARXSTAR Environment Check.
 *
 * @package SparxstarUserEnvironmentCheck
 * @since 3.0.0
 */

namespace Starisian\SparxstarUserEnvironmentCheck;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Registers Accept-CH headers so browsers provide User-Agent Client Hints.
 */
final class SparxstarUserEnvironmentCheck {

        /**
         * Singleton instance of the client hint registrar.
         *
         * @var self|null
         */
        private static ?self $instance = null;

        /**
         * Retrieve the singleton instance.
         *
         * @return self
         */
        public static function get_instance(): self {
                if ( null === self::$instance ) {
                        self::$instance = new self();
                }

                return self::$instance;
        }

        /**
         * Hook registration.
         */
        private function __construct() {
                add_action( 'send_headers', [ $this, 'add_client_hints_header' ] );
        }

        /**
         * Send Accept-CH headers on front-end requests.
         *
         * @return void
         */
        public function add_client_hints_header(): void {
                if ( is_admin() ) {
                        return;
                }

                header( 'Accept-CH: Sec-CH-UA, Sec-CH-UA-Mobile, Sec-CH-UA-Platform, Sec-CH-UA-Model, Sec-CH-UA-Full-Version, Sec-CH-UA-Platform-Version, Sec-CH-UA-Bitness' );
        }
}

SparxstarUserEnvironmentCheck::get_instance();
