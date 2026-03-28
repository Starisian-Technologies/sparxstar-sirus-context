# ClientTelemetry

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\ClientTelemetry`

## Description

ClientTelemetry - Aggregation and pruning for client error reports.
Architecture (per spec §G):
- Raw reports: sparxstar_client_reports — 60-day rolling window
- Aggregation: sparxstar_client_error_stats — permanent summarized history
  All dashboard/analytics queries run against the stats table only.
  Raw reports exist for forensic investigation only.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Manages client-side error telemetry: ingestion, aggregation, and lifecycle pruning.

## Methods

### `__construct(private readonly \wpdb $wpdb)`

ClientTelemetry - Aggregation and pruning for client error reports.
Architecture (per spec §G):
- Raw reports: sparxstar_client_reports — 60-day rolling window
- Aggregation: sparxstar_client_error_stats — permanent summarized history
  All dashboard/analytics queries run against the stats table only.
  Raw reports exist for forensic investigation only.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Manages client-side error telemetry: ingestion, aggregation, and lifecycle pruning.
/
final class ClientTelemetry
{
    /** Daily cron hook name. */
    public const CRON_HOOK = 'sparxstar_sirus_telemetry_prune';

    /** Raw reports retention period in days. */
    private const RETENTION_DAYS = 60;

    /**
@param \wpdb $wpdb WordPress database abstraction object.

### `ensure_schema()`

Creates or updates both telemetry tables using dbDelta.
Safe to call on every activation — idempotent.

### `prune()`

Records a client error report and updates the aggregation stats table.
@param string $error_type Short error type slug (e.g. 'js_error').
@param string $error_message Human-readable error message.
@param array $context Additional structured context.
@param string|null $device_id Optional device UUID.
/
    public function record(
        string $error_type,
        string $error_message,
        array $context = [],
        ?string $device_id = null
    ): void {
        $site_id = (int) get_current_blog_id();
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
            [ '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
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
                [ '%s', '%d', '%d', '%s', '%s' ]
            );
        } else {
            $this->wpdb->update(
                $stats_table,
                [
                    'count'     => (int) $existing + 1,
                    'last_seen' => $now,
                ],
                [
                    'error_hash' => $error_hash,
                    'site_id'    => $site_id,
                ],
                [ '%d', '%s' ],
                [ '%s', '%d' ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Pruning
    // -------------------------------------------------------------------------

    /**
Deletes raw reports older than RETENTION_DAYS (60 days).
The aggregation stats table is never pruned — it accumulates history.
This method is invoked by the sparxstar_sirus_telemetry_prune WP-Cron event
registered in SirusPlugin.

### `schedule_cron()`

Schedules the daily pruning cron event if it is not already scheduled.
Call this from the plugin activation hook.

### `unschedule_cron()`

Unschedules the pruning cron event.
Call this from the plugin deactivation hook.

