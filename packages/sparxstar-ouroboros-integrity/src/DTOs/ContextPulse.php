<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Infrastructure\DTOs;

/**
 * Canonical immutable ContextPulse DTO (Ouroboros CO-001).
 *
 * Produced by Sirus (PulseGenerator). Consumed by Helios (PulseVerifier).
 * Sirus generates. Helios verifies. Never the other way around.
 *
 * The HMAC-SHA256 signed payload is pipe-delimited (|), with trust_score
 * formatted to 4 decimal places:
 *   {pulse_id}|{context_id}|{device_id}|{session_id}|{site_id}|{network_id}|{trust_score_4dp}|{trust_level}|{issued_at}|{expires}
 */
final class ContextPulse
{
    public function __construct(
        public readonly string $pulse_id,
        public readonly string $context_id,
        public readonly string $device_id,
        public readonly string $session_id,
        public readonly string $site_id,
        public readonly string $network_id,
        public readonly float  $trust_score,
        public readonly string $trust_level,
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
            'issued_at', 'expires', 'sig',
        ];

        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(
                    "ContextPulse missing required field: {$key}"
                );
            }
        }

        return new self(
            pulse_id:    (string) $data['pulse_id'],
            context_id:  (string) $data['context_id'],
            device_id:   (string) $data['device_id'],
            session_id:  (string) $data['session_id'],
            site_id:     (string) $data['site_id'],
            network_id:  (string) $data['network_id'],
            trust_score: (float)  $data['trust_score'],
            trust_level: (string) $data['trust_level'],
            issued_at:   (int)    $data['issued_at'],
            expires:     (int)    $data['expires'],
            sig:         (string) $data['sig'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'pulse_id'    => $this->pulse_id,
            'context_id'  => $this->context_id,
            'device_id'   => $this->device_id,
            'session_id'  => $this->session_id,
            'site_id'     => $this->site_id,
            'network_id'  => $this->network_id,
            'trust_score' => $this->trust_score,
            'trust_level' => $this->trust_level,
            'issued_at'   => $this->issued_at,
            'expires'     => $this->expires,
            'sig'         => $this->sig,
        ];
    }
}
