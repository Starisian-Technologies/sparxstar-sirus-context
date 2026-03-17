<?php
/**
 * DeviceRecord - Device continuity Data Transfer Object.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Immutable value object representing a persisted device record.
 */
final class DeviceRecord
{
    /**
     * Constructs a new DeviceRecord.
     *
     * @param string $device_id        UUID identifying this device.
     * @param string $fingerprint_hash SHA-256 hash of the client fingerprint.
     * @param string $environment_json JSON-encoded environment data snapshot.
     * @param int    $first_seen       Unix timestamp of first registration.
     * @param int    $last_seen        Unix timestamp of most recent activity.
     * @param string $trust_level      Current trust level for this device.
     */
    public function __construct(
        public readonly string $device_id,
        public readonly string $fingerprint_hash,
        public readonly string $environment_json,
        public readonly int    $first_seen,
        public readonly int    $last_seen,
        public readonly string $trust_level,
    ) {}

    /**
     * Returns true if the device has been seen within the configured TTL window.
     *
     * Filter: sparxstar_sirus_device_ttl_days (int) – Number of days of inactivity before
     * a device is considered expired. Default: 90. Return an integer from this filter.
     */
    public function isActive(): bool
    {
        $ttl = (int) apply_filters('sparxstar_sirus_device_ttl_days', 90);
        return (time() - $this->last_seen) < ($ttl * DAY_IN_SECONDS);
    }
}
