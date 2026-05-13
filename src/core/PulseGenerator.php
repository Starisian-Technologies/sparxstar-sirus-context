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

    /** Delegated Infrastructure PulseGenerator instance. */
    private InfrastructurePulseGenerator $generator;
    /** Whether Infrastructure PulseGenerator availability has been checked. */
    private static bool $infrastructureChecked = false;
    /** Cached Infrastructure PulseGenerator availability result. */
    private static bool $infrastructureAvailable = false;

    /**
     * Initializes the delegated Infrastructure PulseGenerator.
     *
     * @throws \RuntimeException If the Infrastructure PulseGenerator is unavailable.
     */
    public function __construct()
    {
        if (! self::$infrastructureChecked) {
            self::$infrastructureAvailable = class_exists(InfrastructurePulseGenerator::class)
                && method_exists(InfrastructurePulseGenerator::class, 'generate')
                && defined(InfrastructurePulseGenerator::class . '::PULSE_TTL');
            self::$infrastructureChecked = true;
        }

        if (! self::$infrastructureAvailable) {
            throw new \RuntimeException(
                '[Sirus] Infrastructure PulseGenerator is unavailable. '
                . 'Install/update sparxstar-ouroboros-integrity to a version that exposes '
                . 'PulseGenerator::PULSE_TTL and PulseGenerator::generate().'
            );
        }

        $this->generator = new InfrastructurePulseGenerator();
    }

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
        return $this->generator->generate($context, $now, $ttlSeconds);
    }
}
