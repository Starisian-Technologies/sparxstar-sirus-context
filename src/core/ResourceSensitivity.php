<?php

/**
 * ResourceSensitivity - Typed enum for resource sensitivity levels consumed by StepUpPolicy.
 *
 * Using a backed int enum instead of a bare int prevents invalid states from being
 * passed to StepUpPolicy and enables exhaustive match expressions in downstream code.
 *
 * Serialisation: `ResourceSensitivity::LEVEL_2->value` yields `2` (int).
 * Deserialisation: `ResourceSensitivity::from(2)` yields `ResourceSensitivity::LEVEL_2`.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Represents the sensitivity level of a resource being accessed.
 *
 * Used by StepUpPolicy to determine whether a step-up authentication challenge
 * is required before granting access.
 *
 * Sensitivity levels:
 *   LEVEL_1 (1) — public / low-sensitivity: no step-up required
 *   LEVEL_2 (2) — authenticated / medium-sensitivity: step-up if trust_score < 0.7
 *   LEVEL_3 (3) — protected / high-sensitivity: step-up always required
 *
 * This is a backed int enum so that it can be serialized and compared consistently
 * across PHP origin, TypeScript edge workers, and sovereign minimal deployments.
 * The integer backing values are part of the wire format and must never change.
 */
enum ResourceSensitivity: int
{
    /** Public resource. No step-up authentication required. */
    case LEVEL_1 = 1;

    /**
     * Authenticated resource.
     * Step-up required when trust_score < StepUpPolicy::LEVEL_2_TRUST_THRESHOLD (0.7).
     */
    case LEVEL_2 = 2;

    /**
     * Protected / sovereign resource.
     * Step-up always required regardless of trust score.
     */
    case LEVEL_3 = 3;
}
