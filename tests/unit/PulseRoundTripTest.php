<?php

/**
 * Tests for pulse generate↔verify round-trip against canonical signing material.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Infrastructure\Constants\Platform;
use Starisian\Sparxstar\Infrastructure\DTOs\ContextPulse;
use Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive;
use Starisian\Sparxstar\Infrastructure\Utils\ContextPulseSigningMaterial;
use Starisian\Sparxstar\Sirus\core\PulseGenerator;
use Starisian\Sparxstar\Sirus\core\SirusContext;

final class PulseRoundTripTest extends SirusTestCase
{
    private const TEST_SIGNING_KEY = 'sirus-test-signing-key-x32bytes!';

    /** @var list<string>|null */
    private static ?array $validTrustLevels = null;

    private PulseGenerator $generator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (! defined('SIRUS_PULSE_SIGNING_KEY')) {
            define('SIRUS_PULSE_SIGNING_KEY', self::TEST_SIGNING_KEY);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new PulseGenerator();
    }

    public function testGeneratedPulseRoundTripsAgainstCanonicalSigningMaterial(): void
    {
        $pulse = $this->generator->generate($this->makeContext(), 1_700_000_000);

        $this->assertTrue($this->verifyPulsePayload($this->exportPulse($pulse), 1_700_000_010));
    }

    public function testGeneratedPulseCarriesCanonicalPam002Fields(): void
    {
        $pulse   = $this->generator->generate($this->makeContext(), 1_700_000_000);
        $payload = $this->exportPulse($pulse);

        foreach (
            [
                'pulse_id',
                'context_id',
                'device_id',
                'session_id',
                'site_id',
                'network_id',
                'trust_score',
                'trust_level',
                'behavior_flags',
                'geo_zone',
                'network_effective_type',
                'session_duration',
                'issued_at',
                'expires',
                'sig',
            ] as $field
        ) {
            $this->assertArrayHasKey($field, $payload);
        }

        $this->assertIsArray($payload['behavior_flags']);
        $this->assertIsString($payload['geo_zone']);
        $this->assertNotSame('', $payload['geo_zone']);
        $this->assertContains($payload['network_effective_type'], [ 'cli', 'unknown', 'slow-2g', '2g', '3g', '4g', '5g', 'wifi', 'ethernet', 'offline' ]);
        $this->assertIsInt($payload['session_duration']);
        $this->assertGreaterThanOrEqual(0, $payload['session_duration']);
    }

    public function testTamperedPayloadFailsVerification(): void
    {
        $pulse   = $this->generator->generate($this->makeContext(), 1_700_000_000);
        $payload = $this->exportPulse($pulse);
        $payload['trust_score'] = 0.1;

        $this->assertFalse($this->verifyPulsePayload($payload, 1_700_000_010));
    }

    public function testExpiredPayloadFailsVerification(): void
    {
        $pulse = $this->generator->generate($this->makeContext(), 1_700_000_000, 10);

        $this->assertFalse($this->verifyPulsePayload($this->exportPulse($pulse), 1_700_000_020));
    }

    public function testFutureSkewFailsVerification(): void
    {
        $pulse   = $this->generator->generate($this->makeContext(), 1_700_000_000);
        $payload = $this->exportPulse($pulse);
        $payload['issued_at'] = 1_700_000_200;

        $this->assertFalse($this->verifyPulsePayload($payload, 1_700_000_010));
    }

    public function testMalformedDeviceIdFailsVerification(): void
    {
        $pulse   = $this->generator->generate($this->makeContext(), 1_700_000_000);
        $payload = $this->exportPulse($pulse);
        $payload['device_id'] = 'bad id';

        $this->assertFalse($this->verifyPulsePayload($payload, 1_700_000_010));
    }

    public function testUnknownTrustLevelFailsVerification(): void
    {
        $pulse   = $this->generator->generate($this->makeContext(), 1_700_000_000);
        $payload = $this->exportPulse($pulse);
        $payload['trust_level'] = 'NOT_A_TRUST_LEVEL';

        $this->assertFalse($this->verifyPulsePayload($payload, 1_700_000_010));
    }

    public function testOutOfBoundsTrustScoreFailsVerification(): void
    {
        $pulse   = $this->generator->generate($this->makeContext(), 1_700_000_000);
        $payload = $this->exportPulse($pulse);
        $payload['trust_score'] = 1.5;

        $this->assertFalse($this->verifyPulsePayload($payload, 1_700_000_010));
    }

    /**
     * @return array<string, mixed>
     */
    private function exportPulse(ContextPulse $pulse): array
    {
        $payload = get_object_vars($pulse);

        if (isset($payload['trust_level']) && $payload['trust_level'] instanceof \BackedEnum) {
            $payload['trust_level'] = $payload['trust_level']->value;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function verifyPulsePayload(array $payload, int $now): bool
    {
        if (
            ! isset($payload['device_id'], $payload['trust_level'], $payload['trust_score'], $payload['issued_at'], $payload['expires'], $payload['sig'])
            || ! is_string($payload['device_id'])
            || ! is_string($payload['trust_level'])
            || ! is_numeric($payload['trust_score'])
            || ! is_int($payload['issued_at'])
            || ! is_int($payload['expires'])
            || ! is_string($payload['sig'])
        ) {
            return false;
        }

        if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $payload['device_id'])) {
            return false;
        }

        if (
            ! in_array(
                $payload['trust_level'],
                self::validTrustLevels(),
                true
            )
        ) {
            return false;
        }

        $trust_score = (float) $payload['trust_score'];
        if ($trust_score < 0.0 || $trust_score > 1.0) {
            return false;
        }

        if ($payload['expires'] < $now || $payload['issued_at'] > ($now + 60) || $payload['expires'] < $payload['issued_at']) {
            return false;
        }

        if (
            ! isset($payload['behavior_flags'], $payload['geo_zone'], $payload['network_effective_type'], $payload['session_duration'])
            || ! is_array($payload['behavior_flags'])
            || ! is_string($payload['geo_zone'])
            || $payload['geo_zone'] === ''
            || ! is_string($payload['network_effective_type'])
            || ! is_int($payload['session_duration'])
            || $payload['session_duration'] < 0
        ) {
            return false;
        }

        $candidate = new ContextPulse(
            pulse_version:          (int) ($payload['pulse_version'] ?? Platform::PULSE_VERSION_CURRENT),
            pulse_id:               (string) $payload['pulse_id'],
            context_id:             (string) $payload['context_id'],
            device_id:              $payload['device_id'],
            session_id:             (string) $payload['session_id'],
            site_id:                (string) $payload['site_id'],
            network_id:             (string) $payload['network_id'],
            trust_score:            $trust_score,
            trust_level:            TrustLevelPrimitive::from($payload['trust_level']),
            behavior_flags:         $payload['behavior_flags'],
            geo_zone:               $payload['geo_zone'],
            network_effective_type: $payload['network_effective_type'],
            session_duration:       $payload['session_duration'],
            issued_at:              $payload['issued_at'],
            expires:                $payload['expires'],
            sig:                    $payload['sig'],
        );

        $expected = hash_hmac('sha256', ContextPulseSigningMaterial::build($candidate), self::TEST_SIGNING_KEY);

        return hash_equals($expected, $payload['sig']);
    }

    private function makeContext(): SirusContext
    {
        return new SirusContext(
            context_id:     'ctx-roundtrip',
            environment_id: 'env-roundtrip',
            network_id:     '1',
            site_id:        '1',
            device_id:      'device-roundtrip-1',
            session_id:     'session-roundtrip-1',
            identity_id:    null,
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    TrustLevelPrimitive::from('NORMAL'),
            trust_score:    0.9,
            issued_at:      1_699_999_970,
            expires:        1_700_000_300,
        );
    }

    /**
     * @return list<string>
     */
    private static function validTrustLevels(): array
    {
        if (self::$validTrustLevels === null) {
            /** @var list<string> $values */
            $values = array_column(TrustLevelPrimitive::cases(), 'value');
            self::$validTrustLevels = $values;
        }

        return self::$validTrustLevels;
    }
}
