<?php
/**
 * SirusDatabase - Schema management for the sirus_devices table.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles creation and migration of the sirus_devices database table.
 * Uses dbDelta() for safe, idempotent schema management.
 */
final class SirusDatabase
{
    /** Current schema version. */
    private const SCHEMA_VERSION = '1.0.0';

    /** Option key used to track the installed schema version. */
    private const VERSION_OPTION = 'sirus_db_version';

    /**
     * @param \wpdb $wpdb WordPress database abstraction object.
     */
    public function __construct(private readonly \wpdb $wpdb) {}

    /**
     * Ensures the schema is at the current version, running an update only if needed.
     */
    public function ensure_schema(): void
    {
        $installed = (string) get_option(self::VERSION_OPTION, '');

        if ($installed === self::SCHEMA_VERSION) {
            return;
        }

        $this->create_or_update_table();
        update_option(self::VERSION_OPTION, self::SCHEMA_VERSION, true);
    }

    /**
     * Creates or alters the sirus_devices table using dbDelta.
     */
    public function create_or_update_table(): void
    {
        $table          = $this->wpdb->prefix . 'sirus_devices';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            device_id varchar(36) NOT NULL,
            fingerprint_hash varchar(64) NOT NULL,
            environment_json longtext NOT NULL,
            first_seen int(11) unsigned NOT NULL,
            last_seen int(11) unsigned NOT NULL,
            trust_level varchar(32) NOT NULL DEFAULT 'anonymous',
            PRIMARY KEY  (device_id),
            KEY fingerprint_hash (fingerprint_hash),
            KEY last_seen (last_seen)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
