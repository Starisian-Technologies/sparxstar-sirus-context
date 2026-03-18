<?php

/**
 * DeviceContinuity - Resolves or registers device identity across requests.
 *
 * Architecture note (spec §A):
 * The device_id is always server-issued and is the stable canonical identity.
 * The fingerprint_hash is probabilistic metadata — it detects drift (e.g. browser
 * update, network change) but does NOT replace the device_id. When drift is detected
 * the stored hash is updated so subsequent visits continue resolving correctly.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Manages device continuity by resolving existing records or registering new devices.
 */
final class DeviceContinuity
{
    /**
     * @param DeviceRepository $repository The device persistence layer.
     */
    public function __construct(private readonly DeviceRepository $repository) {}

    /**
     * Resolves a device by ID or fingerprint hash, or registers a new one.
     *
     * Lookup priority:
     * 1. Match by device_id — if found and active:
     *    a. If fingerprint has changed (drift), update stored hash + last_seen.
     *    b. Otherwise just touch last_seen.
     *    Returns the (possibly updated) record in both cases.
     * 2. Match by fingerprint_hash — if found, touch last_seen and return.
     * 3. Register a brand-new device record.
     *
     * The fingerprint_hash detects drift. The device_id is the stable identity.
     *
     * @param string $device_id        The device UUID from the client's localStorage.
     * @param string $fingerprint_hash SHA-256 hash of the client fingerprint (server-derived).
     * @param array  $environment_data Raw environment data from the client.
     */
    public function resolveDevice(
        string $device_id,
        string $fingerprint_hash,
        array $environment_data
    ): DeviceRecord {
        if ($device_id !== '') {
            $existing = $this->repository->findByDeviceId($device_id);

            if ($existing !== null && $existing->isActive()) {
                // Fingerprint drift: the device_id matched but the fingerprint has changed.
                // Only update when both hashes are non-empty to avoid patching incomplete records.
                if (
                    $fingerprint_hash !== '' &&
                    $existing->fingerprint_hash !== '' &&
                    $existing->fingerprint_hash !== $fingerprint_hash
                ) {
                    $this->repository->updateFingerprintHash($device_id, $fingerprint_hash);

                    // Return a new record value reflecting the drift-corrected state.
                    return new DeviceRecord(
                        device_id:        $existing->device_id,
                        fingerprint_hash: $fingerprint_hash,
                        environment_json: $existing->environment_json,
                        first_seen:       $existing->first_seen,
                        last_seen:        time(),
                        trust_level:      $existing->trust_level,
                    );
                }

                // No drift — simply touch last_seen and return the existing record.
                $this->repository->updateLastSeen($device_id);
                return $existing;
            }
        }

        if ($fingerprint_hash !== '') {
            $by_fp = $this->repository->findByFingerprintHash($fingerprint_hash);
            if ($by_fp !== null) {
                $this->repository->updateLastSeen($by_fp->device_id);
                return $by_fp;
            }
        }

        return $this->registerDevice($fingerprint_hash, $environment_data);
    }

    /**
     * Creates and persists a brand-new DeviceRecord.
     * The device_id is always server-generated (UUID v4).
     *
     * @param string $fingerprint_hash SHA-256 hash of the client fingerprint.
     * @param array  $environment_data Raw environment data from the client.
     */
    public function registerDevice(string $fingerprint_hash, array $environment_data): DeviceRecord
    {
        $now     = time();
        $encoded = wp_json_encode($environment_data);
        if ($encoded === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[Sirus DeviceContinuity] wp_json_encode failed for environment_data; storing empty object.');
            $encoded = '{}';
        }

        $record = new DeviceRecord(
            device_id:        wp_generate_uuid4(),
            fingerprint_hash: $fingerprint_hash,
            environment_json: $encoded,
            first_seen:       $now,
            last_seen:        $now,
            trust_level:      'anonymous',
        );

        $this->repository->save($record);

        return $record;
    }
}
