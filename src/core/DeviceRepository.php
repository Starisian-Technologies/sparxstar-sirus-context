<?php

/**
 * DeviceRepository - Persistence layer for DeviceRecord objects.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles all database interactions for the sirus_devices table.
 * All queries use prepared statements via $wpdb.
 */
final readonly class DeviceRepository implements DeviceRepositoryInterface
{
    private string $table;

    /**
     * @param \wpdb $wpdb WordPress database abstraction object.
     */
    public function __construct(private \wpdb $wpdb)
    {
        $this->table = $this->wpdb->prefix . 'sirus_devices';
    }

    /**
     * Finds a DeviceRecord by its device_id UUID.
     *
     * @param string $device_id The device UUID to look up.
     */
    public function findByDeviceId(string $device_id): ?DeviceRecord
    {
        $sql = $this->wpdb->prepare(
            sprintf('SELECT * FROM `%s` WHERE `device_id` = %%s LIMIT 1', $this->table), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $device_id
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $this->wpdb->get_row($sql);

        if ($row === null) {
            return null;
        }

        return $this->rowToRecord($row);
    }

    /**
     * Finds a DeviceRecord by its fingerprint hash.
     *
     * @param string $fingerprint_hash SHA-256 fingerprint hash to look up.
     */
    public function findByFingerprintHash(string $fingerprint_hash): ?DeviceRecord
    {
        $sql = $this->wpdb->prepare(
            sprintf('SELECT * FROM `%s` WHERE `fingerprint_hash` = %%s LIMIT 1', $this->table), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $fingerprint_hash
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $row = $this->wpdb->get_row($sql);

        if ($row === null) {
            return null;
        }

        return $this->rowToRecord($row);
    }

    /**
     * Inserts or replaces a DeviceRecord row.
     *
     * @param DeviceRecord $record The record to persist.
     */
    public function save(DeviceRecord $record): bool
    {
        $result = $this->wpdb->replace(
            $this->table,
            [
                'device_id'        => $record->device_id,
                'device_secret'    => $record->device_secret,
                'fingerprint_hash' => $record->fingerprint_hash,
                'environment_json' => $record->environment_json,
                'first_seen'       => $record->first_seen,
                'last_seen'        => $record->last_seen,
                'trust_level'      => $record->trust_level,
                'drift_score'      => $record->drift_score,
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' ]
        );

        return $result !== false;
    }

    /**
     * Updates the last_seen timestamp for the given device_id to now.
     *
     * @param string $device_id The device UUID to update.
     */
    public function updateLastSeen(string $device_id): void
    {
        $this->wpdb->update(
            $this->table,
            [ 'last_seen' => time() ],
            [ 'device_id' => $device_id ],
            [ '%d' ],
            [ '%s' ]
        );
    }

    /**
     * Updates the fingerprint_hash (and last_seen) for the given device_id.
     *
     * Called when fingerprint drift is detected and the device has been authenticated
     * via its device_secret. The device_id remains the stable identity; we simply
     * record the new fingerprint so subsequent visits resolve correctly.
     *
     * @param string $device_id The device UUID to update.
     * @param string $fingerprint_hash New SHA-256 fingerprint hash.
     */
    public function updateFingerprintHash(string $device_id, string $fingerprint_hash): void
    {
        $this->wpdb->update(
            $this->table,
            [
                'fingerprint_hash' => $fingerprint_hash,
                'last_seen'        => time(),
            ],
            [ 'device_id' => $device_id ],
            [ '%s', '%d' ],
            [ '%s' ]
        );
    }

    /**
     * Atomically increments the drift_score and updates last_seen for a device.
     *
     * Should be called whenever a fingerprint change is detected on an authenticated
     * device. A high drift_score may indicate a highly mobile device (VPN, carrier
     * switching) and can inform future trust-level decisions.
     *
     * @param string $device_id The device UUID to update.
     */
    public function incrementDriftScore(string $device_id): void
    {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $this->wpdb->prepare(
            'UPDATE `%s` SET `drift_score` = `drift_score` + 1, `last_seen` = %d WHERE `device_id` = ' . $this->table,
            time(),
            $device_id
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $this->wpdb->query($sql);
    }

    /**
     * Maps a raw database row object to a DeviceRecord value object.
     *
     * @param object $row The raw row from wpdb.
     */
    private function rowToRecord(object $row): DeviceRecord
    {
        return new DeviceRecord(
            device_id:        (string) $row->device_id,
            device_secret:    (string) ($row->device_secret ?? ''),
            fingerprint_hash: (string) $row->fingerprint_hash,
            environment_json: (string) $row->environment_json,
            first_seen:       (int) $row->first_seen,
            last_seen:        (int) $row->last_seen,
            trust_level:      (string) $row->trust_level,
            drift_score:      (int) ($row->drift_score ?? 0),
        );
    }
}
