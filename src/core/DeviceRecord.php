<?php

/**
 * DeviceRecord - Device continuity Data Transfer Object.
 *
 * Architecture note (spec §A + v1.2 Drift Tolerance):
 * - device_id     — Hard Anchor: server-issued UUID, stable canonical identity.
 * - device_secret — Server-generated cryptographic secret (64-char hex) that
 *                   the client stores in localStorage alongside device_id. A valid
 *                   device_id + device_secret pair confirms the device is legitimate
 *                   and prevents blind device_id guessing or spoofing.
 * - fingerprint_hash — Soft Signal: probabilistic, changes on browser update or
 *                   network change. Used to detect drift, never to replace device_id.
 * - drift_score   — Monotonic counter incremented each time a fingerprint change is
 *                   detected for an authenticated device. High scores may signal a
 *                   device that frequently changes environments (e.g. VPN users).
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
final readonly class DeviceRecord
{
    /**
     * Constructs a new DeviceRecord.
     *
     * @param string $device_id UUID identifying this device (server-issued).
     * @param string $device_secret 64-char hex secret returned to client on registration.
     * @param string $fingerprint_hash SHA-256 hash of the client fingerprint (soft signal).
     * @param string $environment_json JSON-encoded environment data snapshot.
     * @param int $first_seen Unix timestamp of first registration.
     * @param int $last_seen Unix timestamp of most recent activity.
     * @param string $trust_level Credential tier for this device (e.g. 'user', 'anonymous').
     *                            Always the persisted credential level — never 'STEP_UP_REQUIRED'.
     *                            Carry step-up intent via $step_up_required instead.
     * @param int $drift_score Number of fingerprint changes detected (default 0).
     * @param bool $step_up_required In-memory flag set when a verified device's fingerprint has
     *                               drifted (WEAK_MATCH path). Not persisted to DB. When true,
     *                               ContextEngine propagates STEP_UP_REQUIRED to SirusContext
     *                               trust_level so that StepUpPolicy fires correctly.
     */
    public function __construct(
        public string $device_id,
        public string $device_secret,
        public string $fingerprint_hash,
        public string $environment_json,
        public int $first_seen,
        public int $last_seen,
        public string $trust_level,
        public int $drift_score = 0,
        public bool $step_up_required = false,
    ) {
    }

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

    /**
     * Returns true if the given device_secret matches this record's secret.
     * Use constant-time comparison to prevent timing side-channels.
     *
     * @param string $candidate The secret string to compare.
     */
    public function verifySecret(string $candidate): bool
    {
        return $candidate !== '' && hash_equals($this->device_secret, $candidate);
    }
}
