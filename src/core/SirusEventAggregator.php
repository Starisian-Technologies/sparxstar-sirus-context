<?php

/**
 * SirusEventAggregator - Compiles raw sirus_events into pre-aggregated summary rows.
 *
 * Runs on a 5-minute cron. Dashboard queries read from aggregates for performance.
 *
 * Bucket sizes: '5m' (5-minute) and '1h' (1-hour).
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Compiles raw sirus_events into pre-aggregated summary rows.
 */
final readonly class SirusEventAggregator
{
    public const CRON_HOOK = 'sirus_aggregate_events';

    public const CRON_INTERVAL_SEC = 300; // 5 minutes

    private const BUCKET_5M = '5m';

    private const BUCKET_1H = '1h';

    public function __construct(private \wpdb $wpdb)
    {
    }

    /**
     * Schedules the 5-minute aggregation cron if not already scheduled.
     */
    public static function schedule_cron(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'every_5_minutes', self::CRON_HOOK);
        }
    }

    /**
     * Removes the scheduled cron event.
     */
    public static function unschedule_cron(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp !== false) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Compiles events from the current and previous windows into aggregate rows.
     *
     * Both the current and the immediately preceding bucket are compiled on every
     * cron run, so a late or missed cron interval will not permanently skip a bucket.
     * Safe to call repeatedly — uses INSERT ... ON DUPLICATE KEY UPDATE.
     */
    public function compile(): void
    {
        $now = time();

        $this->compile_bucket(self::BUCKET_5M, 300, $now);          // current 5-minute bucket
        $this->compile_bucket(self::BUCKET_5M, 300, $now - 300);    // previous 5-minute bucket
        $this->compile_bucket(self::BUCKET_1H, 3600, $now);         // current 1-hour bucket
        $this->compile_bucket(self::BUCKET_1H, 3600, $now - 3600);  // previous 1-hour bucket
    }

    /**
     * Returns aggregate rows for a given bucket size since a given timestamp.
     *
     * @param string $bucket_size '5m' or '1h'
     * @param int $since Unix timestamp lower bound for bucket_start.
     * @param int $limit Max rows to return.
     * @return array<int, array<string, mixed>>
     */
    public function getAggregates(string $bucket_size, int $since, int $limit = 200): array
    {
        $table = $this->wpdb->prefix . 'sirus_event_aggregates';

        $sql = $this->wpdb->prepare(
            sprintf('SELECT id, bucket_start, bucket_size, site_id, event_type, browser, device_type, network, event_count, session_count FROM %s WHERE bucket_size = %%s AND bucket_start >= %%d ORDER BY bucket_start DESC LIMIT %%d', $table), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $bucket_size,
            $since,
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Prunes aggregate rows older than $days days.
     */
    public function prune(int $days = 7): void
    {
        $table     = $this->wpdb->prefix . 'sirus_event_aggregates';
        $threshold = time() - ($days * DAY_IN_SECONDS);

        $this->wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $this->wpdb->prepare(
                'DELETE FROM %s WHERE bucket_start < ' . $table, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $threshold
            )
        );
    }

    /**
     * Compiles events from the given bucket window into aggregate rows.
     *
     * @param string $bucket_size Bucket label ('5m' or '1h').
     * @param int $window_secs Window size in seconds.
     * @param int $now Reference timestamp (defaults to current time if 0).
     */
    private function compile_bucket(string $bucket_size, int $window_secs, int $now = 0): void
    {
        $events_table = $this->wpdb->prefix . 'sirus_events';
        $agg_table    = $this->wpdb->prefix . 'sirus_event_aggregates';

        // Round now down to the start of this bucket window.
        $now          = $now > 0 ? $now : time();
        $bucket_start = (int) (floor($now / $window_secs) * $window_secs);
        $bucket_end   = $bucket_start + $window_secs;
        $site_id      = (int) get_current_blog_id();

        $sql = $this->wpdb->prepare(
            "INSERT INTO {$agg_table}
                (bucket_start, bucket_size, site_id, event_type, browser, device_type, network, event_count, session_count)
            SELECT
                %d AS bucket_start,
                %s AS bucket_size,
                %d AS site_id,
                event_type,
                browser,
                device_type,
                network,
                COUNT(*) AS event_count,
                COUNT(DISTINCT session_id) AS session_count
            FROM {$events_table}
            WHERE timestamp >= %d AND timestamp < %d
            GROUP BY event_type, browser, device_type, network
            AS src
            ON DUPLICATE KEY UPDATE
                event_count   = src.event_count,
                session_count = src.session_count", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $bucket_start,
            $bucket_size,
            $site_id,
            $bucket_start,
            $bucket_end
        );

        $this->wpdb->query($sql); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
    }
}
