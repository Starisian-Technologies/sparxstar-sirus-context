<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Infrastructure\DTOs;

/**
 * Canonical immutable ContextPulse DTO (Ouroboros CO-001 / PAM-002).
 *
 * Produced by Sirus (PulseGenerator). Consumed by Helios (PulseVerifier).
 * Sirus generates. Helios verifies. Never the other way around.
 *
 * The HMAC-SHA256 signed payload is pipe-delimited (|), with trust_score
 * formatted to 4 decimal places and behavior_flags as a comma-joined string:
 *   {pulse_id}|{context_id}|{device_id}|{session_id}|{site_id}|{network_id}|
 *   {trust_score_4dp}|{trust_level}|{issued_at}|{expires}|
 *   {behavior_flags_csv}|{geo_zone}|{network_effective_type}|{session_duration}
 *
 * PAM-002 restored fields (absent in PAM-001):
 *   behavior_flags, geo_zone, network_effective_type, session_duration
 */
final class ContextPulse
{
    /**
     * Field order matches the ref-02 canonical wire format.
     *
     * @param string   $pulse_id               UUID v4 — unique per generated pulse.
     * @param string   $context_id             Originating SirusContext identifier.
     * @param string   $device_id              Server-issued device continuity identifier.
     * @param string   $session_id             Session identifier.
     * @param string   $site_id                WordPress blog/site identifier.
     * @param string   $network_id             WordPress multisite network identifier.
     * @param float    $trust_score            Numerical trust score in [0.0, 1.0].
     * @param string   $trust_level            Trust level label (NORMAL, ELEVATED, …).
     * @param string[] $behavior_flags         PAM-002: active behavior flags for the session.
     * @param string   $geo_zone               PAM-002: coarse geo-zone identifier (e.g. "US-EAST").
     * @param string   $network_effective_type PAM-002: effective network type (4g, 3g, 2g, slow-2g, unknown).
     * @param int      $session_duration       PAM-002: seconds elapsed since session start.
     * @param int      $issued_at              Unix timestamp when this pulse was issued.
     * @param int      $expires                Unix timestamp when this pulse expires.
     * @param string   $sig                    HMAC-SHA256 signature over the 14-field canonical string.
     */
    public function __construct(
        public readonly string $pulse_id,
        public readonly string $context_id,
        public readonly string $device_id,
        public readonly string $session_id,
        public readonly string $site_id,
        public readonly string $network_id,
        public readonly float  $trust_score,
        public readonly string $trust_level,
        public readonly array  $behavior_flags,
        public readonly string $geo_zone,
        public readonly string $network_effective_type,
        public readonly int    $session_duration,
        public readonly int    $issued_at,
        public readonly int    $expires,
        public readonly string $sig,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException On missing required fields.
     */
    public static function fromArray(array $data): self
    {
        $required = [
            'pulse_id', 'context_id', 'device_id', 'session_id',
            'site_id', 'network_id', 'trust_score', 'trust_level',
            'issued_at', 'expires',
            'behavior_flags', 'geo_zone', 'network_effective_type', 'session_duration',
            'sig',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(
                    "ContextPulse missing required field: {$key}"
                );
            }
        }

        return new self(
            pulse_id:               (string) $data['pulse_id'],
            context_id:             (string) $data['context_id'],
            device_id:              (string) $data['device_id'],
            session_id:             (string) $data['session_id'],
            site_id:                (string) $data['site_id'],
            network_id:             (string) $data['network_id'],
            trust_score:            (float)  $data['trust_score'],
            trust_level:            (string) $data['trust_level'],
            behavior_flags:         (array)  $data['behavior_flags'],
            geo_zone:               (string) $data['geo_zone'],
            network_effective_type: (string) $data['network_effective_type'],
            session_duration:       (int)    $data['session_duration'],
            issued_at:              (int)    $data['issued_at'],
            expires:                (int)    $data['expires'],
            sig:                    (string) $data['sig'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'pulse_id'               => $this->pulse_id,
            'context_id'             => $this->context_id,
            'device_id'              => $this->device_id,
            'session_id'             => $this->session_id,
            'site_id'                => $this->site_id,
            'network_id'             => $this->network_id,
            'trust_score'            => $this->trust_score,
            'trust_level'            => $this->trust_level,
            'behavior_flags'         => $this->behavior_flags,
            'geo_zone'               => $this->geo_zone,
            'network_effective_type' => $this->network_effective_type,
            'session_duration'       => $this->session_duration,
            'issued_at'              => $this->issued_at,
            'expires'                => $this->expires,
            'sig'                    => $this->sig,
        ];
    }
}
