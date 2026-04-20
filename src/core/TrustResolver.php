<?php

/**
 * TrustResolver - Derives a dynamic trust score from a DeviceRecord.
 *
 * TrustEngine computes trust from boolean session/network signals.
 * TrustResolver computes trust from the device's credential level (what the
 * record says the device is) then applies the same spec-frozen deductions for
 * drift and new-session conditions.
 *
 * Credential base scores:
 *   elder       = 0.95
 *   contributor = 0.90
 *   user        = 0.85
 *   device      = 0.70
 *   anonymous   = 0.50
 *   (any other) = 0.50
 *
 * Deductions applied from TrustEngine constants:
 *   drift_score > 0           → -DEDUCTION_DEVICE_DRIFTING (−0.3)
 *   first_seen === last_seen  → -DEDUCTION_NEW_SESSION      (−0.1)
 *
 * Result is clamped to [0.0, 1.0].
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Evaluates the trust score of a device by combining its credential-level base
 * with the same frozen deductions from TrustEngine.
 *
 * Called exclusively from ContextEngine::buildFromDevice().
 * Use TrustEngine::compute() for signal-only evaluation (no DeviceRecord context).
 */
final class TrustResolver
{
    /**
     * Credential base score map.
     *
     * Maps the device's trust_level string to a starting float in [0.0, 1.0].
     * Represents the inherent trustworthiness of a verified entity at this credential tier.
     *
     * @var array<string, float>
     */
    private const CREDENTIAL_BASE = [
        'elder'       => 0.95,
        'contributor' => 0.90,
        'user'        => 0.85,
        'device'      => 0.70,
        'anonymous'   => 0.50,
    ];

    /**
     * Default base score for unrecognised credential levels.
     */
    private const DEFAULT_BASE = 0.50;

    /**
     * Evaluates the trust score for a DeviceRecord.
     *
     * Starts from the credential-level base and applies TrustEngine deductions
     * for device drift (drift_score > 0) and new sessions (first_seen === last_seen).
     * The result is clamped to [0.0, 1.0].
     *
     * Note: DeviceRecord::trust_level is always expected to be a credential-tier
     * string (e.g. 'user', 'anonymous'). STEP_UP_REQUIRED is carried via the
     * separate DeviceRecord::$step_up_required flag and must never appear here.
     * This method treats any unrecognised trust_level (including 'STEP_UP_REQUIRED')
     * as DEFAULT_BASE for defence-in-depth.
     *
     * @param DeviceRecord $device The resolved device record.
     * @return float Trust score in [0.0, 1.0].
     */
    public static function evaluate(DeviceRecord $device): float
    {
        // Defence-in-depth: if trust_level is the step-up sentinel (not a credential
        // tier), fall through to DEFAULT_BASE rather than creating an unexpected score.
        $base = self::CREDENTIAL_BASE[$device->trust_level] ?? self::DEFAULT_BASE;

        // Apply frozen spec deductions (reuse TrustEngine constants for consistency).
        if ($device->drift_score > 0) {
            $base -= TrustEngine::DEDUCTION_DEVICE_DRIFTING;
        }

        if ($device->first_seen === $device->last_seen) {
            $base -= TrustEngine::DEDUCTION_NEW_SESSION;
        }

        // Clamp to [0.0, 1.0].
        return max(0.0, min(1.0, $base));
    }
}
