<?php

/**
 * PulseGenerator - Generates and signs ContextPulse instances.
 *
 * Sirus GENERATES pulses. Helios VERIFIES them.
 * Verification logic MUST NOT be placed in this repository.
 *
 * Signing algorithm: HMAC-SHA256 over a deterministic canonical string.
 *
 * Canonical string format (fields joined by '|' in this exact order):
 *   pulse_id|context_id|device_id|session_id|site_id|network_id|trust_score|trust_level|issued_at|expires
 *
 * The signing key is read exclusively from the SIRUS_PULSE_SIGNING_KEY constant.
 * It MUST NOT be read from WordPress options, the database, or user input.
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
 * Issues signed ContextPulse instances from a resolved SirusContext.
 *
 * The TTL is caller-controlled via $ttlSeconds so that governance-sensitive
 * operational modes (sovereign/high-connectivity/low-connectivity) can supply
 * the appropriate window without the generator making a policy decision.
 * The default of 60 seconds applies when no TTL is specified.
 */
final class PulseGenerator
{
    /** Default pulse TTL in seconds. Used when no $ttlSeconds is supplied. */
    public const PULSE_TTL = 60;

    /** Minimum required key length (bytes). */
    private const MIN_KEY_LENGTH = 32;

    /**
     * Generates a signed ContextPulse from the given SirusContext.
     *
     * @param SirusContext $context    The fully resolved context to pulse.
     * @param int          $now        Unix timestamp to use as issued_at. Pass 0 (default) to use time().
     * @param int          $ttlSeconds Pulse TTL in seconds. Defaults to PULSE_TTL (60).
     * @return ContextPulse The signed pulse, ready for transmission to Helios.
     * @throws \RuntimeException If SIRUS_PULSE_SIGNING_KEY is not defined or too short.
     */
    public function generate(SirusContext $context, int $now = 0, int $ttlSeconds = self::PULSE_TTL): ContextPulse
    {
        if ($ttlSeconds <= 0) {
            throw new \InvalidArgumentException(
                '[Sirus PulseGenerator] $ttlSeconds must be a positive integer; got ' . $ttlSeconds . '.'
            );
        }

        $key = $this->resolveSigningKey();

        $pulse_id  = wp_generate_uuid4();
        $issued_at = $now > 0 ? $now : time();
        $expires   = $issued_at + $ttlSeconds;

        $canonical = implode('|', [
            $pulse_id,
            $context->context_id,
            $context->device_id,
            $context->session_id,
            $context->site_id,
            $context->network_id,
            number_format($context->trust_score, 4, '.', ''),
            $context->trust_level,
            (string) $issued_at,
            (string) $expires,
        ]);

        $sig = hash_hmac('sha256', $canonical, $key);

        return new ContextPulse(
            pulse_id:    $pulse_id,
            context_id:  $context->context_id,
            device_id:   $context->device_id,
            session_id:  $context->session_id,
            site_id:     $context->site_id,
            network_id:  $context->network_id,
            trust_score: $context->trust_score,
            trust_level: $context->trust_level,
            issued_at:   $issued_at,
            expires:     $expires,
            sig:         $sig,
        );
    }

    /**
     * Resolves the HMAC signing key from the SIRUS_PULSE_SIGNING_KEY constant.
     *
     * @return string The signing key.
     * @throws \RuntimeException If the constant is missing or the key is too short.
     */
    private function resolveSigningKey(): string
    {
        if (! defined('SIRUS_PULSE_SIGNING_KEY')) {
            throw new \RuntimeException(
                '[Sirus] PulseGenerator: SIRUS_PULSE_SIGNING_KEY constant is not defined. '
                . 'Define it in wp-config.php before using PulseGenerator.'
            );
        }

        $key = (string) constant('SIRUS_PULSE_SIGNING_KEY');

        if (strlen($key) < self::MIN_KEY_LENGTH) {
            throw new \RuntimeException(
                '[Sirus] PulseGenerator: SIRUS_PULSE_SIGNING_KEY must be at least '
                . self::MIN_KEY_LENGTH . ' bytes.'
            );
        }

        return $key;
    }
}
