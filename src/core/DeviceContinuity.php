<?php
/**
 * DeviceContinuity - Resolves or registers device identity across requests.
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
     * 1. Match by device_id – if found and active, touch last_seen and return.
     * 2. Match by fingerprint_hash – if found, touch last_seen and return.
     * 3. Register a brand-new device record.
     *
     * @param string $device_id        The device UUID from the client cookie.
     * @param string $fingerprint_hash SHA-256 hash of the client fingerprint.
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
     *
     * @param string $fingerprint_hash SHA-256 hash of the client fingerprint.
     * @param array  $environment_data Raw environment data from the client.
     */
    public function registerDevice(string $fingerprint_hash, array $environment_data): DeviceRecord
    {
        $now              = time();
        $encoded          = wp_json_encode($environment_data);
        if ($encoded === false) {
            // Log encoding failure via error_log to avoid cross-namespace coupling.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[Sirus DeviceContinuity] wp_json_encode failed for environment_data; storing empty object.');
            $encoded = '{}';
        }
        $environment_json = $encoded;

        $record = new DeviceRecord(
            device_id:        wp_generate_uuid4(),
            fingerprint_hash: $fingerprint_hash,
            environment_json: $environment_json,
            first_seen:       $now,
            last_seen:        $now,
            trust_level:      'anonymous',
        );

        $this->repository->save($record);

        return $record;
    }
}
