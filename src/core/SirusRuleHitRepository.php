<?php

/**
 * SirusRuleHitRepository - DAL for the sirus_rule_hits table.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Provides strict read/write access to the sirus_rule_hits table.
 * All queries are prepared. No business logic lives here.
 */
final readonly class SirusRuleHitRepository
{
    public function __construct(private \wpdb $wpdb)
    {
    }

    /**
     * Inserts a new rule hit row.
     *
     * @param array<string, mixed> $hit
     * @return int Inserted row ID, or 0 on failure.
     */
    public function insert(array $hit): int
    {
        $table = $this->wpdb->prefix . 'sirus_rule_hits';

        $row = [
            'rule_key'   => (string) ($hit['rule_key'] ?? ''),
            'signal_key' => (string) ($hit['signal_key'] ?? ''),
            'site_id'    => (int) get_current_blog_id(),
            'device_id'  => (string) ($hit['device_id'] ?? ''),
            'session_id' => (string) ($hit['session_id'] ?? ''),
            'hit_count'  => 1,
            'severity'   => (string) ($hit['severity'] ?? 'low'),
            'action_key' => (string) ($hit['action_key'] ?? ''),
            'status'     => 'triggered',
            'created_at' => time(),
            'updated_at' => time(),
        ];

        $formats = [ '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d' ];

        $result = $this->wpdb->insert($table, $row, $formats);

        if ($result === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Increments the hit_count for an existing (rule_key, device_id, session_id) row.
     * If no row exists, inserts a new one.
     */
    public function incrementHit(string $ruleKey, string $deviceId = '', string $sessionId = ''): void
    {
        $table = $this->wpdb->prefix . 'sirus_rule_hits';

        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                sprintf('SELECT id, hit_count FROM %s WHERE rule_key = %%s AND device_id = %%s AND session_id = %%s LIMIT 1', $table), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $ruleKey,
                $deviceId,
                $sessionId
            ),
            ARRAY_A
        );

        if (is_array($existing) && isset($existing['id'])) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->wpdb->query(
                $this->wpdb->prepare(
                    'UPDATE %s SET hit_count = hit_count + 1, updated_at = %d WHERE id = ' . $table, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    time(),
                    (int) $existing['id']
                )
            );
            return;
        }

        $this->insert(
            [
                'rule_key'   => $ruleKey,
                'signal_key' => '',
                'device_id'  => $deviceId,
                'session_id' => $sessionId,
                'severity'   => 'low',
                'action_key' => '',
            ]
        );
    }

    /**
     * Returns the most recent rule hits ordered by created_at descending.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentHits(int $limit = 100): array
    {
        $table = $this->wpdb->prefix . 'sirus_rule_hits';

        $sql = $this->wpdb->prepare(
            'SELECT * FROM %s ORDER BY created_at DESC LIMIT ' . $table, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $limit
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns rule hits filtered by severity since a given timestamp.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHitsBySeverity(string $severity, int $since): array
    {
        $table = $this->wpdb->prefix . 'sirus_rule_hits';

        $sql = $this->wpdb->prepare(
            sprintf('SELECT * FROM %s WHERE severity = %%s AND created_at >= %%d ORDER BY created_at DESC', $table), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $severity,
            $since
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Deletes rule hit rows older than the given number of days.
     *
     * @return int Number of rows deleted, or 0 on failure.
     */
    public function pruneOldHits(int $days = 30): int
    {
        $table  = $this->wpdb->prefix . 'sirus_rule_hits';
        $cutoff = time() - ($days * DAY_IN_SECONDS);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                'DELETE FROM %s WHERE created_at < ' . $table, // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $cutoff
            )
        );

        return is_int($result) ? $result : 0;
    }
}
