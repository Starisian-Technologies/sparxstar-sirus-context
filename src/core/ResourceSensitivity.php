<?php

/**
 * ResourceSensitivity - Typed enum for resource sensitivity levels consumed by StepUpPolicy.
 *
 * Using a backed int enum instead of a bare int prevents invalid states from being
 * passed to StepUpPolicy and enables exhaustive match expressions in downstream code.
 *
 * Serialisation: `ResourceSensitivity::MEDIUM->value` yields `2` (int).
 * Deserialisation: `ResourceSensitivity::from(2)` yields `ResourceSensitivity::MEDIUM`.
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
 *   LOW    (1) — public / low-sensitivity: no step-up required
 *   MEDIUM (2) — authenticated / medium-sensitivity: step-up if trust_score < 0.7
 *   HIGH   (3) — protected / high-sensitivity: step-up always required
 *
 * This is a backed int enum so that it can be serialized and compared consistently
 * across PHP origin, TypeScript edge workers, and sovereign minimal deployments.
 */
enum ResourceSensitivity: int
{
    /** Public resource. No step-up authentication required. */
    case LOW = 1;

    /**
     * Authenticated resource.
     * Step-up required when trust_score < StepUpPolicy::LEVEL_2_TRUST_THRESHOLD (0.7).
     */
    case MEDIUM = 2;

    /**
     * Protected / sovereign resource.
     * Step-up always required regardless of trust score.
     */
    case HIGH = 3;
}
