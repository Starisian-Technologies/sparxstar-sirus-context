<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Infrastructure\Signing;

use Starisian\Sparxstar\Infrastructure\DTOs\ContextPulse;

/**
 * Canonical HMAC-SHA256 signing material builder for ContextPulse (Ouroboros CO-001 / PAM-002).
 *
 * This is the canonical source for the signing string format.
 * Sirus imports this class to generate pulses.
 * Helios imports this class to verify pulses.
 * Neither Sirus nor Helios may maintain a local copy of this logic.
 *
 * Canonical 14-field pipe-delimited format (sig is excluded from the signing payload):
 *   {pulse_id}|{context_id}|{device_id}|{session_id}|{site_id}|{network_id}|
 *   {trust_score_4dp}|{trust_level}|{issued_at}|{expires}|
 *   {behavior_flags_json}|{geo_zone}|{network_effective_type}|{session_duration}
 *
 * Immutable signing rules (do not change without a PAM version bump):
 *   - trust_score is formatted with number_format($score, 4, '.', '') — 4 decimal places, no separator.
 *   - behavior_flags are sorted, then JSON-encoded with JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES.
 *     json_encode failure throws \JsonException (fail closed — JSON_THROW_ON_ERROR).
 *   - sig is excluded from the signing payload.
 *   - VERSION = 1 identifies this canonical format revision.
 */
final class ContextPulseSigningMaterial
{
    /** Canonical format version. Must be bumped on any field-order or encoding change. */
    public const VERSION = 1;

    /**
     * Builds the 14-field PAM-002 canonical string for HMAC-SHA256 signing.
     *
     * The $pulse->sig field is intentionally excluded from the output.
     *
     * @param ContextPulse $pulse The pulse whose signing material to build.
     *                            The behavior_flags array is expected to contain only strings.
     *                            Any non-serializable value will cause json_encode to throw \JsonException (fail closed).
     * @return string The pipe-delimited canonical string, ready for hash_hmac().
     * @throws \JsonException If behavior_flags cannot be JSON-encoded (fail closed).
     */
    public static function build(ContextPulse $pulse): string
    {
        $flags = $pulse->behavior_flags;
        sort($flags);

        return implode('|', [
            $pulse->pulse_id,
            $pulse->context_id,
            $pulse->device_id,
            $pulse->session_id,
            $pulse->site_id,
            $pulse->network_id,
            number_format($pulse->trust_score, 4, '.', ''),
            $pulse->trust_level,
            (string) $pulse->issued_at,
            (string) $pulse->expires,
            json_encode($flags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $pulse->geo_zone,
            $pulse->network_effective_type,
            (string) $pulse->session_duration,
            // sig is intentionally excluded from the signing payload
        ]);
    }
}
