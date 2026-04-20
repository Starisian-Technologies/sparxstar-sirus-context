<?php

/**
 * MatchResult - Classification outcome for DeviceMatcher::classify().
 *
 * Represents one of three possible fingerprint similarity outcomes:
 *   STRONG_MATCH — score >= STRONG_MATCH_THRESHOLD (0.8): restore device normally.
 *   WEAK_MATCH   — score >= WEAK_MATCH_THRESHOLD   (0.6): restore device, flag STEP_UP_REQUIRED.
 *   NO_MATCH     — score <  WEAK_MATCH_THRESHOLD   (0.6): register as new device.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The three possible outcomes produced by DeviceMatcher::classify().
 */
enum MatchResult
{
    case STRONG_MATCH;
    case WEAK_MATCH;
    case NO_MATCH;
}
