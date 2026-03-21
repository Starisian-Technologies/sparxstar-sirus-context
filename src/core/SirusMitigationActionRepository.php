<?php

/**
 * SirusMitigationActionRepository - DAL for the sirus_mitigation_actions table.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Provides strict read/write access to the sirus_mitigation_actions table.
 * All queries are prepared. No business logic lives here.
 */
final class SirusMitigationActionRepository
{
    public function __construct(private readonly \wpdb $wpdb) {}

    /**
     * Inserts a new mitigation action row.
     *
     * @param array<string, mixed> $action
     * @return int Inserted row ID, or 0 on failure.
     */
    public function insert(array $action): int
    {
        $table = $this->wpdb->prefix . 'sirus_mitigation_actions';

        $payload = isset($action['payload']) && is_array($action['payload'])
            ? (wp_json_encode($action['payload']) ?: null)
            : null;

        $row = [
            'action_key'   => (string) ($action['action_key'] ?? ''),
            'site_id'      => (int) get_current_blog_id(),
            'device_id'    => (string) ($action['device_id'] ?? ''),
            'session_id'   => (string) ($action['session_id'] ?? ''),
            'response_mode'=> (string) ($action['response_mode'] ?? 'normal'),
            'payload_json' => $payload,
            'status'       => 'active',
            'expires_at'   => isset($action['expires_at']) ? (int) $action['expires_at'] : null,
            'created_at'   => time(),
        ];

        $formats = ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d'];

        $result = $this->wpdb->insert($table, $row, $formats);

        if ($result === false) {
            return 0;
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Returns active mitigation actions for a given device ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveForDevice(string $deviceId): array
    {
        $table = $this->wpdb->prefix . 'sirus_mitigation_actions';
        $now   = time();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE device_id = %s AND status = 'active' AND (expires_at IS NULL OR expires_at > %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $deviceId,
            $now
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns active mitigation actions for a given session ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveForSession(string $sessionId): array
    {
        $table = $this->wpdb->prefix . 'sirus_mitigation_actions';
        $now   = time();

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$table} WHERE session_id = %s AND status = 'active' AND (expires_at IS NULL OR expires_at > %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sessionId,
            $now
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Marks a mitigation action as expired.
     */
    public function expireAction(int $actionId): void
    {
        $table = $this->wpdb->prefix . 'sirus_mitigation_actions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$table} SET status = 'expired', expires_at = %d WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                time(),
                $actionId
            )
        );
    }

    /**
     * Deletes expired and old mitigation action rows.
     *
     * @return int Number of rows deleted, or 0 on failure.
     */
    public function pruneExpiredActions(int $days = 30): int
    {
        $table  = $this->wpdb->prefix . 'sirus_mitigation_actions';
        $cutoff = time() - ($days * DAY_IN_SECONDS);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $cutoff
            )
        );

        return is_int($result) ? $result : 0;
    }
}
