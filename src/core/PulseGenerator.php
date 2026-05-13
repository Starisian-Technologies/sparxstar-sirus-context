<?php

/**
 * PulseGenerator facade for Sirus.
 *
 * Sirus consumes the canonical PulseGenerator from sparxstar-ouroboros-integrity
 * and does not maintain local pulse generation/signing logic.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Infrastructure\DTOs\ContextPulse;
use Starisian\Sparxstar\Infrastructure\Signing\PulseGenerator as InfrastructurePulseGenerator;

/**
 * Sirus compatibility facade that delegates to the Infrastructure PulseGenerator.
 */
final class PulseGenerator
{
    /** Default pulse TTL in seconds. */
    public const PULSE_TTL = InfrastructurePulseGenerator::PULSE_TTL;

    /**
     * Generates a signed ContextPulse from the given SirusContext by delegation.
     *
     * @param SirusContext $context    The fully resolved context to pulse.
     * @param int          $now        Unix timestamp to use as issued_at. Pass 0 (default) to use time().
     * @param int          $ttlSeconds Pulse TTL in seconds. Defaults to PULSE_TTL (60).
     * @return ContextPulse The signed pulse, ready for transmission to Helios.
     * @throws \RuntimeException If the Infrastructure PulseGenerator is not available.
     */
    public function generate(SirusContext $context, int $now = 0, int $ttlSeconds = self::PULSE_TTL): ContextPulse
    {
        if (! class_exists(InfrastructurePulseGenerator::class)) {
            throw new \RuntimeException(
                '[Sirus] Infrastructure PulseGenerator is unavailable. '
                . 'Install/update sparxstar-ouroboros-integrity to a version that provides '
                . InfrastructurePulseGenerator::class . '.'
            );
        }

        return (new InfrastructurePulseGenerator())->generate($context, $now, $ttlSeconds);
    }
}
