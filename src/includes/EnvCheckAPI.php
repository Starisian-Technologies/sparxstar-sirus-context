<?php
/**
 * Session helpers for SPARXSTAR Environment Check.
 *
 * Bridges REST handlers and utility classes by providing an easy way to persist
 * environment snapshots within the PHP session.
 *
 * @package SparxstarUserEnvironmentCheck
 * @since 3.0.0
 */

namespace Starisian\SparxstarUserEnvironmentCheck\Includes;

use StarUserUtils;

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Thin wrapper responsible for persisting and retrieving session-scoped data.
 */
final class EnvCheckAPI {

        /**
         * Store an environment snapshot in the PHP session using {@see StarUserUtils}.
         *
         * @param array $snapshot The complete snapshot payload.
         * @param array $context  Additional metadata that should accompany the snapshot.
         * @return void
         */
        public static function store_session_snapshot( array $snapshot, array $context = [] ): void {
                StarUserUtils::storeEnvironmentSnapshot( $snapshot, $context );
        }

        /**
         * Retrieve the stored environment snapshot from the PHP session.
         *
         * @return array Snapshot data or an empty array if nothing has been stored.
         */
        public static function get_session_snapshot(): array {
                return StarUserUtils::getEnvironmentSnapshot();
        }
}
