<?php

/**
 * DeviceRepositoryInterface - Contract for device persistence.
 *
 * Decouples DeviceContinuity (and tests) from the concrete DeviceRepository
 * implementation so consumers can be tested without a real database connection.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Defines the persistence contract for DeviceRecord objects.
 */
interface DeviceRepositoryInterface
{
    /**
     * Finds a DeviceRecord by its device_id UUID.
     *
     * @param string $device_id The device UUID to look up.
     */
    public function findByDeviceId(string $device_id): ?DeviceRecord;

    /**
     * Finds a DeviceRecord by its fingerprint hash.
     *
     * @param string $fingerprint_hash SHA-256 fingerprint hash to look up.
     */
    public function findByFingerprintHash(string $fingerprint_hash): ?DeviceRecord;

    /**
     * Inserts or replaces a DeviceRecord row.
     *
     * @param DeviceRecord $record The record to persist.
     */
    public function save(DeviceRecord $record): bool;

    /**
     * Updates the last_seen timestamp for the given device_id to now.
     *
     * @param string $device_id The device UUID to update.
     */
    public function updateLastSeen(string $device_id): void;

    /**
     * Updates the fingerprint_hash (and last_seen) for the given device_id.
     *
     * @param string $device_id        The device UUID to update.
     * @param string $fingerprint_hash New SHA-256 fingerprint hash.
     */
    public function updateFingerprintHash(string $device_id, string $fingerprint_hash): void;

    /**
     * Atomically increments the drift_score and updates last_seen for a device.
     *
     * @param string $device_id The device UUID to update.
     */
    public function incrementDriftScore(string $device_id): void;
}
