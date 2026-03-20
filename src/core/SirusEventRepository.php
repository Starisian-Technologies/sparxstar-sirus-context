<?php

/**
 * SirusEventRepository - Data Access Layer for Sirus observability events.
 *
 * HARD RULE: This class contains ONLY clean reads and writes.
 * No business logic, no scoring, no aggregation logic, no AI logic.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Provides strict read/write access to the sirus_events table.
 * All queries are prepared. No business logic lives here.
 */
final class SirusEventRepository
{
    /**
     * Valid event types (canonical enum).
     */
    public const VALID_EVENT_TYPES = [
        'js_error',
        'api_error',
        'network_issue',
        'capability_failure',
        'session_start',
        'session_end',
    ];

    /**
     * @param \wpdb $wpdb WordPress database abstraction object.
     */
    public function __construct(private readonly \wpdb $wpdb) {}

    /**
     * Inserts a single event record into the database.
     *
     * @param array<string, mixed> $event Validated and sanitized event payload.
     * @return int The inserted row ID, or 0 on failure.
     */
    public function insert(array $event): int
    {
        $table = $this->wpdb->prefix . 'sirus_events';

        $row = [
            'event_type'   => (string) ($event['event_type'] ?? ''),
            'timestamp'    => (int) ($event['timestamp'] ?? 0),
            'device_id'    => (string) ($event['device_id'] ?? ''),
            'session_id'   => (string) ($event['session_id'] ?? ''),
            'user_id'      => (int) ($event['user_id'] ?? 0),
            'url'          => isset($event['url']) ? (string) $event['url'] : null,
            'context_json' => isset($event['context']) ? (wp_json_encode($event['context']) ?: '{}') : '{}',
            'metrics_json' => isset($event['metrics']) ? (wp_json_encode($event['metrics']) ?: null) : null,
            'error_json'   => isset($event['error']) ? (wp_json_encode($event['error']) ?: null) : null,
        ];

        $formats = ['%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s'];

        $result = $this->wpdb->insert($table, $row, $formats);

        if ($result === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Returns the most recent events, ordered by timestamp descending.
     *
     * @param int $limit Maximum number of events to return.
     * @return array<int, array<string, mixed>>
     */
    public function getRecentEvents(int $limit = 100): array
    {
        $table = $this->wpdb->prefix . 'sirus_events';

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY timestamp DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns events of a specific type occurring since a given timestamp.
     *
     * @param string $type  Event type to filter by.
     * @param int    $since Unix timestamp lower bound (inclusive).
     * @return array<int, array<string, mixed>>
     */
    public function getEventsByType(string $type, int $since): array
    {
        $table = $this->wpdb->prefix . 'sirus_events';

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE event_type = %s AND timestamp >= %d ORDER BY timestamp DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $type,
            $since
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Counts the number of distinct active sessions within a time window.
     *
     * A session is considered active if any event from that session was recorded
     * within the specified window (counting back from now).
     *
     * @param int $windowSeconds Number of seconds to look back from now.
     * @return int Count of distinct active session_id values.
     */
    public function getActiveSessions(int $windowSeconds): int
    {
        $table = $this->wpdb->prefix . 'sirus_events';
        $since = time() - $windowSeconds;

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE timestamp >= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $since
        );

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Returns the count of each error event type since a given timestamp.
     *
     * @param int $since Unix timestamp lower bound.
     * @return array<string, int> Map of event_type to count.
     */
    public function getErrorCountsByType(int $since): array
    {
        $table = $this->wpdb->prefix . 'sirus_events';

        $sql = $this->wpdb->prepare(
            "SELECT event_type, COUNT(*) AS cnt FROM {$table} WHERE event_type IN ('js_error','api_error','network_issue','capability_failure') AND timestamp >= %d GROUP BY event_type", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $since
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $result[(string) $row['event_type']] = (int) $row['cnt'];
            }
        }
        return $result;
    }

    /**
     * Returns the count of distinct affected sessions per URL for error events.
     *
     * @param int $since  Unix timestamp lower bound.
     * @param int $limit  Maximum number of URLs to return.
     * @return array<int, array<string, mixed>> Each row: url, error_count, affected_sessions.
     */
    public function getTopFailingUrls(int $since, int $limit = 10): array
    {
        $table = $this->wpdb->prefix . 'sirus_events';

        $sql = $this->wpdb->prepare(
            "SELECT url, COUNT(*) AS error_count, COUNT(DISTINCT session_id) AS affected_sessions FROM {$table} WHERE event_type IN ('js_error','api_error','network_issue','capability_failure') AND url IS NOT NULL AND timestamp >= %d GROUP BY url ORDER BY error_count DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $since,
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns recent error events grouped by context fields (browser/device).
     *
     * @param int $since Unix timestamp lower bound.
     * @param int $limit Maximum rows to return.
     * @return array<int, array<string, mixed>>
     */
    public function getRecentErrors(int $since, int $limit = 50): array
    {
        $table = $this->wpdb->prefix . 'sirus_events';

        $sql = $this->wpdb->prepare(
            "SELECT event_type, context_json, error_json, url, timestamp, device_id, session_id FROM {$table} WHERE event_type IN ('js_error','api_error','network_issue','capability_failure') AND timestamp >= %d ORDER BY timestamp DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $since,
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }
}
