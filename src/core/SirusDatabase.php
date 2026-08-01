<?php

/**
 * SirusDatabase - Schema management for all Sirus database tables.
 *
 * Manages:
 * - sirus_devices                 — device continuity records
 * - sparxstar_client_reports      — raw client error telemetry (60-day rolling)
 * - sparxstar_client_error_stats  — aggregated error statistics (permanent)
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles creation and migration of all Sirus database tables.
 * Uses dbDelta() for safe, idempotent schema management.
 *
 * As a must-use plugin, Sirus never receives an activation hook, so schema
 * creation cannot be gated behind register_activation_hook(). Instead,
 * maybe_upgrade_schema() is called on an early boot hook on every request;
 * it is safe to call repeatedly because the happy path is a single cheap
 * option read that returns immediately when the installed version already
 * matches SCHEMA_VERSION.
 *
 * Multisite note: this class never loops over sites (mirroring the
 * "Database Loop Integrity Rule" documented on
 * SparxstarUECInstaller::activate_site()). Each request already executes in
 * the context of a single site — $wpdb and get_option()/update_option() are
 * scoped to the current blog — so calling maybe_upgrade_schema() on every
 * request naturally provisions each site's tables the first time that site
 * is visited, without any explicit switch_to_blog() loop.
 */
final readonly class SirusDatabase
{
    /** Current schema version. Bump this when table definitions change. */
    public const SCHEMA_VERSION = 1;

    /** Option key used to track the installed schema version. */
    private const VERSION_OPTION = 'sirus_db_version';

    /**
     * @param \wpdb $wpdb WordPress database abstraction object.
     */
    public function __construct(private \wpdb $wpdb)
    {
    }

    /**
     * Boot-time schema check. Safe to call on every request.
     *
     * Reads the stored schema-version option and compares it to
     * SCHEMA_VERSION; calls ensure_schema() only when they differ, which
     * performs the actual dbDelta() run and records the new version.
     */
    public function maybe_upgrade_schema(): void
    {
        $installed = (int) get_option(self::VERSION_OPTION, 0);

        if ($installed === self::SCHEMA_VERSION) {
            return;
        }

        $this->ensure_schema();
    }

    /**
     * Ensures the schema is at the current version, running an update only if needed.
     */
    public function ensure_schema(): void
    {
        $installed = (int) get_option(self::VERSION_OPTION, 0);

        if ($installed === self::SCHEMA_VERSION) {
            return;
        }

        $this->create_or_update_tables();
        update_option(self::VERSION_OPTION, self::SCHEMA_VERSION, true);
    }

    /**
     * Creates or alters all Sirus tables using dbDelta.
     */
    public function create_or_update_tables(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $this->create_devices_table($charset_collate);
        $this->create_telemetry_tables($charset_collate);
        $this->create_events_table($charset_collate);
        $this->create_rule_hits_table($charset_collate);
        $this->create_mitigation_actions_table($charset_collate);
        $this->create_event_aggregates_table($charset_collate);
    }

    /**
     * Creates or updates the sirus_devices table.
     *
     * @param string $charset_collate DB charset/collation string.
     */
    private function create_devices_table(string $charset_collate): void
    {
        $table = $this->wpdb->prefix . 'sirus_devices';

        $sql = "CREATE TABLE {$table} (
            device_id varchar(36) NOT NULL,
            device_secret varchar(64) NOT NULL DEFAULT '',
            fingerprint_hash varchar(64) NOT NULL,
            environment_json longtext NOT NULL,
            first_seen int(11) unsigned NOT NULL,
            last_seen int(11) unsigned NOT NULL,
            trust_level varchar(32) NOT NULL DEFAULT 'anonymous',
            drift_score int(11) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (device_id),
            KEY fingerprint_hash (fingerprint_hash),
            KEY last_seen (last_seen)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the client telemetry tables.
     *
     * sparxstar_client_reports      — raw reports, 60-day rolling window
     * sparxstar_client_error_stats  — aggregated stats, permanent
     *
     * @param string $charset_collate DB charset/collation string.
     */
    private function create_telemetry_tables(string $charset_collate): void
    {
        $reports_table = $this->wpdb->prefix . 'sparxstar_client_reports';
        $stats_table   = $this->wpdb->prefix . 'sparxstar_client_error_stats';

        $sql_reports = "CREATE TABLE {$reports_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            error_hash varchar(64) NOT NULL,
            site_id bigint(20) unsigned NOT NULL DEFAULT 1,
            device_id varchar(36) DEFAULT NULL,
            error_type varchar(128) NOT NULL DEFAULT '',
            error_message text NOT NULL,
            error_context longtext NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY error_hash (error_hash),
            KEY site_id (site_id),
            KEY timestamp (timestamp)
        ) {$charset_collate};";

        $sql_stats = "CREATE TABLE {$stats_table} (
            error_hash varchar(64) NOT NULL,
            site_id bigint(20) unsigned NOT NULL DEFAULT 1,
            count bigint(20) unsigned NOT NULL DEFAULT 1,
            first_seen datetime NOT NULL,
            last_seen datetime NOT NULL,
            PRIMARY KEY  (error_hash, site_id),
            KEY site_id (site_id),
            KEY last_seen (last_seen)
        ) {$charset_collate};";

        dbDelta($sql_reports);
        dbDelta($sql_stats);
    }

    /**
     * Creates or updates the sirus_events observability table.
     *
     * Stores frontend error, network, session and capability events.
     * JSON fields hold optional context, metrics and error payloads.
     *
     * @param string $charset_collate DB charset/collation string.
     */
    private function create_events_table(string $charset_collate): void
    {
        $table = $this->wpdb->prefix . 'sirus_events';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            timestamp int(10) unsigned NOT NULL,
            device_id varchar(64) NOT NULL,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            url text NULL,
            browser varchar(100) NOT NULL DEFAULT '',
            device_type varchar(50) NOT NULL DEFAULT '',
            network varchar(50) NOT NULL DEFAULT '',
            context_json longtext NOT NULL,
            metrics_json longtext NULL,
            error_json longtext NULL,
            PRIMARY KEY  (id),
            KEY idx_event_type (event_type),
            KEY idx_timestamp (timestamp),
            KEY idx_device (device_id),
            KEY idx_session (session_id),
            KEY idx_browser (browser),
            KEY idx_device_type (device_type)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the sirus_rule_hits table.
     *
     * @param string $charset_collate DB charset/collation string.
     */
    private function create_rule_hits_table(string $charset_collate): void
    {
        $table = $this->wpdb->prefix . 'sirus_rule_hits';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rule_key varchar(100) NOT NULL,
            signal_key varchar(100) NOT NULL,
            site_id bigint(20) unsigned NOT NULL,
            device_id varchar(64) NULL,
            session_id varchar(64) NULL,
            hit_count int(10) unsigned NOT NULL DEFAULT 1,
            severity varchar(20) NOT NULL DEFAULT 'low',
            action_key varchar(100) NULL,
            status varchar(20) NOT NULL DEFAULT 'triggered',
            created_at int(10) unsigned NOT NULL,
            updated_at int(10) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_rule_key (rule_key),
            KEY idx_signal_key (signal_key),
            KEY idx_site_id (site_id),
            KEY idx_device_id (device_id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the sirus_mitigation_actions table.
     *
     * @param string $charset_collate DB charset/collation string.
     */
    private function create_mitigation_actions_table(string $charset_collate): void
    {
        $table = $this->wpdb->prefix . 'sirus_mitigation_actions';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action_key varchar(100) NOT NULL,
            site_id bigint(20) unsigned NOT NULL,
            device_id varchar(64) NULL,
            session_id varchar(64) NULL,
            response_mode varchar(20) NOT NULL DEFAULT 'normal',
            payload_json longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            expires_at int(10) unsigned NULL,
            created_at int(10) unsigned NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_action_key (action_key),
            KEY idx_site_id (site_id),
            KEY idx_device_id (device_id),
            KEY idx_session_id (session_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Creates or updates the sirus_event_aggregates pre-aggregated summary table.
     *
     * @param string $charset_collate DB charset/collation string.
     */
    private function create_event_aggregates_table(string $charset_collate): void
    {
        $table = $this->wpdb->prefix . 'sirus_event_aggregates';

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bucket_start int(10) unsigned NOT NULL,
            bucket_size varchar(10) NOT NULL,
            site_id bigint(20) unsigned NOT NULL DEFAULT 1,
            event_type varchar(50) NOT NULL,
            browser varchar(100) NOT NULL DEFAULT '',
            device_type varchar(50) NOT NULL DEFAULT '',
            network varchar(50) NOT NULL DEFAULT '',
            event_count int(10) unsigned NOT NULL DEFAULT 0,
            session_count int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_bucket_unique (bucket_start, bucket_size, site_id, event_type, browser, device_type, network),
            KEY idx_bucket_start (bucket_start),
            KEY idx_event_type (event_type)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
