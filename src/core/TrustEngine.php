<?php

/**
 * TrustEngine - Computes the trust score and trust level for a device/session context.
 *
 * Trust score algorithm is FROZEN. Deductions and clamping MUST NOT be changed
 * without a formal spec update.
 *
 * Frozen algorithm:
 *   base            =  1.0
 *   device drifting = -0.3
 *   geo mismatch    = -0.2
 *   new session     = -0.1
 *   recent failures = -0.3
 *   result clamped to [0.0, 1.0]
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Computes a numerical trust score and a named trust level for a given device context.
 *
 * Inputs are provided as a signal map so that callers can pass only the signals
 * they have resolved. Missing signals default to the most-trusted state (no deduction).
 */
final class TrustEngine
{
    /** Base trust score before any deductions. */
    public const BASE_SCORE = 1.0;

    /** Deduction applied when the device fingerprint has drifted. */
    public const DEDUCTION_DEVICE_DRIFTING = 0.3;

    /** Deduction applied when the resolved geo does not match the device's last-known geo. */
    public const DEDUCTION_GEO_MISMATCH = 0.2;

    /** Deduction applied when no prior session exists for this device. */
    public const DEDUCTION_NEW_SESSION = 0.1;

    /** Deduction applied when the device has recorded recent auth failures. */
    public const DEDUCTION_RECENT_FAILURES = 0.3;

    /**
     * Trust level returned when trust_score >= 0.7.
     * Used by StepUpPolicy: Level 2 auth is NOT required at this threshold.
     */
    public const LEVEL_NORMAL = 'NORMAL';

    /**
     * Trust level returned when trust_score < 0.7.
     * Used by StepUpPolicy: Level 2 auth IS required at this threshold.
     */
    public const LEVEL_ELEVATED = 'ELEVATED';

    /**
     * Trust level returned when trust_score = 0.0 (fully untrusted).
     */
    public const LEVEL_CRITICAL = 'CRITICAL';

    /**
     * Computes the trust score from a signal map and returns both score and level.
     *
     * Signal keys (all optional, default to false / not-present):
     *   - device_drifting  (bool)  True when fingerprint drift has been detected.
     *   - geo_mismatch     (bool)  True when resolved geo differs from last-known.
     *   - new_session      (bool)  True when no prior session exists for this device.
     *   - recent_failures  (bool)  True when the device has recent auth failures.
     *
     * @param array{
     *     device_drifting?: bool,
     *     geo_mismatch?: bool,
     *     new_session?: bool,
     *     recent_failures?: bool
     * } $signals Signal map for the current request.
     * @return array{trust_score: float, trust_level: string}
     */
    public function compute(array $signals): array
    {
        $score = self::BASE_SCORE;

        if (! empty($signals['device_drifting'])) {
            $score -= self::DEDUCTION_DEVICE_DRIFTING;
        }

        if (! empty($signals['geo_mismatch'])) {
            $score -= self::DEDUCTION_GEO_MISMATCH;
        }

        if (! empty($signals['new_session'])) {
            $score -= self::DEDUCTION_NEW_SESSION;
        }

        if (! empty($signals['recent_failures'])) {
            $score -= self::DEDUCTION_RECENT_FAILURES;
        }

        // Clamp to [0.0, 1.0].
        $score = max(0.0, min(1.0, $score));

        return [
            'trust_score' => $score,
            'trust_level' => $this->scoreToLevel($score),
        ];
    }

    /**
     * Maps a clamped score to a named trust level.
     *
     * @param float $score Clamped trust score in [0.0, 1.0].
     * @return string One of LEVEL_NORMAL, LEVEL_ELEVATED, or LEVEL_CRITICAL.
     */
    public function scoreToLevel(float $score): string
    {
        if ($score >= 0.7) {
            return self::LEVEL_NORMAL;
        }

        if ($score > 0.0) {
            return self::LEVEL_ELEVATED;
        }

        return self::LEVEL_CRITICAL;
    }
}
