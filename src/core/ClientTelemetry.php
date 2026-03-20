<?php

/**
 * ClientTelemetry - Aggregation and pruning for client error reports.
 *
 * Architecture (per spec §G):
 * - Raw reports: sparxstar_client_reports — 60-day rolling window
 * - Aggregation: sparxstar_client_error_stats — permanent summarized history
 *   All dashboard/analytics queries run against the stats table only.
 *   Raw reports exist for forensic investigation only.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Manages client-side error telemetry: ingestion, aggregation, and lifecycle pruning.
 */
final class ClientTelemetry
{
    /** Daily cron hook name. */
    public const CRON_HOOK = 'sparxstar_sirus_telemetry_prune';

    /** Raw reports retention period in days. */
    private const RETENTION_DAYS = 60;

    /**
     * @param \wpdb $wpdb WordPress database abstraction object.
     */
    public function __construct(private readonly \wpdb $wpdb) {}

    // -------------------------------------------------------------------------
    // Schema management
    // -------------------------------------------------------------------------

    /**
     * Creates or updates both telemetry tables using dbDelta.
     * Safe to call on every activation — idempotent.
     */
    public function ensure_schema(): void
    {
        $charset_collate = $this->wpdb->get_charset_collate();
        $reports_table   = $this->wpdb->prefix . 'sparxstar_client_reports';
        $stats_table     = $this->wpdb->prefix . 'sparxstar_client_error_stats';

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

        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta($sql_reports);
        dbDelta($sql_stats);
    }

    // -------------------------------------------------------------------------
    // Ingestion
    // -------------------------------------------------------------------------

    /**
     * Records a client error report and updates the aggregation stats table.
     *
     * @param string      $error_type    Short error type slug (e.g. 'js_error').
     * @param string      $error_message Human-readable error message.
     * @param array       $context       Additional structured context.
     * @param string|null $device_id     Optional device UUID.
     */
    public function record(
        string $error_type,
        string $error_message,
        array $context = [],
        ?string $device_id = null
    ): void {
        $site_id    = (int) get_current_blog_id();
        // Use a null byte as separator to eliminate delimiter-collision hash attacks
        // (e.g., 'type:msg' vs 'ty:pe:msg' producing the same input).
        $error_hash = hash('sha256', $error_type . "\x00" . $error_message);
        $now        = current_time('mysql', true);

        $reports_table = $this->wpdb->prefix . 'sparxstar_client_reports';
        $stats_table   = $this->wpdb->prefix . 'sparxstar_client_error_stats';

        // 1. Insert raw report.
        $this->wpdb->insert(
            $reports_table,
            [
                'error_hash'    => $error_hash,
                'site_id'       => $site_id,
                'device_id'     => $device_id,
                'error_type'    => sanitize_text_field($error_type),
                'error_message' => sanitize_text_field($error_message),
                'error_context' => wp_json_encode($context) ?: '{}',
                'timestamp'     => $now,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        // 2. Upsert aggregation stats — increment count, update last_seen.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT count FROM `{$stats_table}` WHERE `error_hash` = %s AND `site_id` = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $error_hash,
                $site_id
            )
        );

        if ($existing === null) {
            $this->wpdb->insert(
                $stats_table,
                [
                    'error_hash' => $error_hash,
                    'site_id'    => $site_id,
                    'count'      => 1,
                    'first_seen' => $now,
                    'last_seen'  => $now,
                ],
                ['%s', '%d', '%d', '%s', '%s']
            );
        } else {
            $this->wpdb->update(
                $stats_table,
                [
                    'count'     => (int) $existing + 1,
                    'last_seen' => $now,
                ],
                ['error_hash' => $error_hash, 'site_id' => $site_id],
                ['%d', '%s'],
                ['%s', '%d']
            );
        }
    }

    // -------------------------------------------------------------------------
    // Pruning
    // -------------------------------------------------------------------------

    /**
     * Deletes raw reports older than RETENTION_DAYS (60 days).
     * The aggregation stats table is never pruned — it accumulates history.
     *
     * This method is invoked by the sparxstar_sirus_telemetry_prune WP-Cron event
     * registered in SirusPlugin.
     */
    public function prune(): void
    {
        $reports_table = $this->wpdb->prefix . 'sparxstar_client_reports';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM `{$reports_table}` WHERE `timestamp` < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                self::RETENTION_DAYS
            )
        );
    }

    // -------------------------------------------------------------------------
    // Cron scheduling
    // -------------------------------------------------------------------------

    /**
     * Schedules the daily pruning cron event if it is not already scheduled.
     * Call this from the plugin activation hook.
     */
    public static function schedule_cron(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Unschedules the pruning cron event.
     * Call this from the plugin deactivation hook.
     */
    public static function unschedule_cron(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp !== false && $timestamp !== null) {
            wp_unschedule_event((int) $timestamp, self::CRON_HOOK);
        }
    }
}
