<?php

/**
 * PulseGenerator - Generates and signs ContextPulse instances.
 *
 * Sirus GENERATES pulses. Helios VERIFIES them.
 * Verification logic MUST NOT be placed in this repository.
 *
 * Signing algorithm: HMAC-SHA256 over a deterministic canonical string.
 *
 * Canonical string construction is delegated entirely to
 * ContextPulseSigningMaterial::build() (Ouroboros CO-001). Sirus does not
 * maintain its own copy of the format — see that class for the canonical
 * field order and encoding rules.
 *
 * The signing key is read exclusively from the SPARXSTAR_PULSE_SIGNING_KEY constant.
 * It MUST NOT be read from WordPress options, the database, or user input.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Infrastructure\DTOs\ContextPulse;
use Starisian\Sparxstar\Infrastructure\Constants\Platform;
use Starisian\Sparxstar\Sirus\services\EnvironmentResolver;
use Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive;
use Starisian\Sparxstar\Infrastructure\Utils\ContextPulseSigningMaterial;

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

    private readonly EnvironmentResolver $environmentResolver;

    public function __construct(
        ?EnvironmentResolver $environmentResolver = null
    ) {
        $this->environmentResolver = $environmentResolver ?? new EnvironmentResolver();
    }

    /**
     * Generates a signed ContextPulse from the given SirusContext.
     *
     * @param SirusContext $context The fully resolved context to pulse.
     * @param int $now Unix timestamp to use as issued_at. Pass 0 (default) to use time().
     * @param int $ttlSeconds Pulse TTL in seconds. Defaults to PULSE_TTL (60).
     * @throws \RuntimeException If SPARXSTAR_PULSE_SIGNING_KEY is not defined or too short.
     * @return ContextPulse The signed pulse, ready for transmission to Helios.
     */
    public function generate(SirusContext $context, int $now = 0, int $ttlSeconds = self::PULSE_TTL): ContextPulse
    {
        if ($ttlSeconds <= 0) {
            // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing exception message interpolating an integer; not echoed as HTML
            throw new \InvalidArgumentException(
                '[Sirus PulseGenerator] $ttlSeconds must be a positive integer; got ' . $ttlSeconds . '.'
            );
            // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $key = $this->resolveSigningKey();

        $pulse_id               = wp_generate_uuid4();
        $issued_at              = $now > 0 ? $now : time();
        $expires                = $issued_at + $ttlSeconds;
        $behavior_flags         = $this->deriveBehaviorFlags($context);
        $geo_zone               = $this->environmentResolver->getGeoZone();
        $network_effective_type = $this->environmentResolver->getNetworkEffectiveType();
        $session_duration       = $this->resolveSessionDuration($context->issued_at, $issued_at);

        // Build a provisional pulse (sig is the empty string — excluded from the signing payload).
        // ContextPulseSigningMaterial::build() is the canonical source for the format;
        // Sirus must not maintain a local copy of the signing string construction.
        $provisional = new ContextPulse(
            pulse_version:          Platform::PULSE_VERSION_CURRENT,
            pulse_id:               $pulse_id,
            context_id:             $context->context_id,
            device_id:              $context->device_id,
            session_id:             $context->session_id,
            site_id:                $context->site_id,
            network_id:             $context->network_id,
            trust_score:            $context->trust_score,
            trust_level:            $context->trust_level,
            behavior_flags:         $behavior_flags,
            geo_zone:               $geo_zone,
            network_effective_type: $network_effective_type,
            session_duration:       $session_duration,
            issued_at:              $issued_at,
            expires:                $expires,
            sig:                    '',
        );

        $sig = hash_hmac('sha256', ContextPulseSigningMaterial::build($provisional), $key);

        // Return the final signed pulse with the same canonical fields as the provisional pulse.
        return new ContextPulse(
            pulse_version:          Platform::PULSE_VERSION_CURRENT,
            pulse_id:               $pulse_id,
            context_id:             $context->context_id,
            device_id:              $context->device_id,
            session_id:             $context->session_id,
            site_id:                $context->site_id,
            network_id:             $context->network_id,
            trust_score:            $context->trust_score,
            trust_level:            $context->trust_level,
            behavior_flags:         $behavior_flags,
            geo_zone:               $geo_zone,
            network_effective_type: $network_effective_type,
            session_duration:       $session_duration,
            issued_at:              $issued_at,
            expires:                $expires,
            sig:                    $sig,
        );
    }

    /**
     * Derives pulse behavior flags from context trust signals.
     *
     * @return array<int, string>
     */
    private function deriveBehaviorFlags(SirusContext $context): array
    {
        $flags = [];

        if ($context->trust_level === TrustLevelPrimitive::STEP_UP_REQUIRED) {
            $flags[] = 'step_up_required';
            $flags[] = 'trust_level_elevated';
            $flags[] = 'trust_level_critical';
        }

        if ($context->trust_score < TrustEngine::NORMAL_THRESHOLD) {
            $flags[] = 'low_trust_score';
        }

        return array_values(array_unique($flags));
    }

    /**
     * Resolves session duration from session start and pulse issue timestamps.
     */
    private function resolveSessionDuration(int $session_start, int $issued_at): int
    {
        $duration = $issued_at - $session_start;
        return max($duration, 0);
    }

    /**
     * Resolves the HMAC signing key from the SPARXSTAR_PULSE_SIGNING_KEY constant.
     *
     * @throws \RuntimeException If the constant is missing or the key is too short.
     * @return string The signing key.
     */
    private function resolveSigningKey(): string
    {
        if (! defined('SPARXSTAR_PULSE_SIGNING_KEY')) {
            throw new \RuntimeException(
                '[Sirus] PulseGenerator: SPARXSTAR_PULSE_SIGNING_KEY constant is not defined. '
                . 'Define it in wp-config.php before using PulseGenerator.'
            );
        }

        $key = (string) constant('SPARXSTAR_PULSE_SIGNING_KEY');

        $minimum_key_length = Platform::PULSE_MIN_SIGNING_KEY_BYTES;

        if (strlen($key) < $minimum_key_length) {
            // phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing exception message interpolating an integer; not echoed as HTML
            throw new \RuntimeException(
                '[Sirus] PulseGenerator: SPARXSTAR_PULSE_SIGNING_KEY must be at least '
                . $minimum_key_length . ' bytes.'
            );
            // phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $key;
    }
}
