<?php

/**
 * StepUpPolicy - Determines whether step-up authentication is required.
 *
 * Policy is FROZEN per the Sirus Context Engine Spec v3.0:
 *   Level 3 resource: step-up always required.
 *   Level 2 resource: step-up required when trust_score < 0.7.
 *   Level 1 resource: no step-up required.
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

/**
 * Evaluates whether a step-up authentication challenge should be issued for
 * a given context and resource sensitivity level.
 *
 * Resource sensitivity levels:
 *   1 = public / low-sensitivity (no step-up)
 *   2 = authenticated / medium-sensitivity (step-up if trust_score < 0.7)
 *   3 = protected / high-sensitivity (step-up always)
 */
final class StepUpPolicy
{
    /** Minimum trust_score for Level 2 access without step-up. */
    public const LEVEL_2_TRUST_THRESHOLD = 0.7;

    /**
     * Returns true if step-up authentication is required for the given context
     * and resource sensitivity level.
     *
     * @param SirusContext $context           The current context.
     * @param int          $sensitivity_level Resource sensitivity (1, 2, or 3).
     * @return bool True if step-up is required.
     */
    public function isRequired(SirusContext $context, int $sensitivity_level): bool
    {
        // Level 3: step-up always required, regardless of trust score.
        if ($sensitivity_level >= 3) {
            return true;
        }

        // Level 2: step-up required when trust_score is below threshold.
        if ($sensitivity_level === 2) {
            return $context->trust_score < self::LEVEL_2_TRUST_THRESHOLD;
        }

        // Level 1 (or below): no step-up required.
        return false;
    }

    /**
     * Returns the recommended step-up level for the given context and sensitivity.
     *
     * When no step-up is required, returns 0.
     * When step-up is required:
     *   - Sensitivity 3 always recommends Level 3 step-up.
     *   - Sensitivity 2 recommends Level 2 step-up.
     *
     * @param SirusContext $context           The current context.
     * @param int          $sensitivity_level Resource sensitivity (1, 2, or 3).
     * @return int Recommended step-up level (0 = none, 2 = L2, 3 = L3).
     */
    public function getRequiredLevel(SirusContext $context, int $sensitivity_level): int
    {
        if ($sensitivity_level >= 3) {
            return 3;
        }

        if ($sensitivity_level === 2 && $context->trust_score < self::LEVEL_2_TRUST_THRESHOLD) {
            return 2;
        }

        return 0;
    }
}
