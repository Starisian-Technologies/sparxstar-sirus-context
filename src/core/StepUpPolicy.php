<?php

/**
 * StepUpPolicy - Determines whether step-up authentication is required.
 *
 * Policy is FROZEN per the Sirus Context Engine Spec v3.0 §15 / Helios Spec §11:
 *   trust_level === 'STEP_UP_REQUIRED' — step-up always required (pre-flagged context)
 *   ResourceSensitivity::HIGH          — step-up always required
 *   ResourceSensitivity::MEDIUM        — step-up required when trust_score < 0.7
 *   ResourceSensitivity::LOW           — no step-up required
 *
 * StepUpPolicy operates on ContextPulse (not SirusContext) so that the same
 * evaluation can run identically on the edge (Cloudflare Worker) and at the
 * origin (PHP). ContextPulse is the shared, signed artifact that both sides
 * receive; SirusContext is an internal origin-only object.
 *
 * This class produces a recommendation. Enforcement is the responsibility
 * of Helios. Sirus MUST NOT make authorization decisions.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\dto\ContextPulse;

/**
 * Evaluates whether a step-up authentication challenge should be issued for
 * a given ContextPulse and resource sensitivity level.
 *
 * Using ContextPulse as the input artifact ensures that:
 * - The same evaluation logic applies on the edge and at the origin.
 * - No internal SirusContext fields are leaked across trust boundaries.
 * - The input is always a signed, tamper-evident payload.
 */
final class StepUpPolicy
{
    /** Minimum trust_score for MEDIUM resources without step-up. Frozen per spec §15. */
    public const LEVEL_2_TRUST_THRESHOLD = 0.7;

    /**
     * Trust level value that signals a pre-flagged step-up requirement.
     *
     * When a pulse carries this trust_level, step-up is required unconditionally —
     * regardless of resource sensitivity or numeric trust_score.
     * Example: a context where the issuing system has already detected an anomaly
     * (e.g., concurrent sessions, privilege escalation attempt, manual admin flag).
     */
    public const TRUST_LEVEL_STEP_UP_REQUIRED = 'STEP_UP_REQUIRED';

    /**
     * Returns true if step-up authentication is required.
     *
     * Evaluation order (first match wins):
     *   1. trust_level === STEP_UP_REQUIRED → always require (pre-flagged)
     *   2. ResourceSensitivity::HIGH        → always require
     *   3. ResourceSensitivity::MEDIUM      → require when trust_score < threshold
     *   4. ResourceSensitivity::LOW         → never require
     *
     * @param ContextPulse        $pulse The signed context pulse carrying trust state.
     * @param ResourceSensitivity $level The resource sensitivity level.
     * @return bool True if step-up is required.
     */
    public function requiresStepUp(ContextPulse $pulse, ResourceSensitivity $level): bool
    {
        // Pre-flagged step-up: pulse trust_level already signals step-up required.
        if ($pulse->trust_level === self::TRUST_LEVEL_STEP_UP_REQUIRED) {
            return true;
        }

        // HIGH: step-up always required, regardless of trust score.
        if ($level === ResourceSensitivity::HIGH) {
            return true;
        }

        // MEDIUM: step-up required when trust_score is below threshold.
        if ($level === ResourceSensitivity::MEDIUM) {
            return $pulse->trust_score < self::LEVEL_2_TRUST_THRESHOLD;
        }

        // LOW: no step-up required.
        return false;
    }

    /**
     * Returns the recommended step-up sensitivity level, or null if no step-up is needed.
     *
     * Callers may use this to communicate the challenge level to Helios.
     * A null return means no challenge is needed — Helios should not prompt for step-up.
     *
     * @param ContextPulse        $pulse The signed context pulse carrying trust state.
     * @param ResourceSensitivity $level The resource sensitivity level.
     * @return ResourceSensitivity|null The required step-up level, or null (no step-up needed).
     */
    public function getRequiredLevel(ContextPulse $pulse, ResourceSensitivity $level): ?ResourceSensitivity
    {
        // Pre-flagged step-up: enforce a HIGH challenge level for safety.
        if ($pulse->trust_level === self::TRUST_LEVEL_STEP_UP_REQUIRED) {
            return ResourceSensitivity::HIGH;
        }

        if ($level === ResourceSensitivity::HIGH) {
            return ResourceSensitivity::HIGH;
        }

        if ($level === ResourceSensitivity::MEDIUM && $pulse->trust_score < self::LEVEL_2_TRUST_THRESHOLD) {
            return ResourceSensitivity::MEDIUM;
        }

        return null;
    }
}
