<?php

/**
 * Tests for TrustResolver – credential-level base scores with drift/session deductions.
 *
 * TrustResolver derives a trust score from the DeviceRecord's trust_level string
 * as a base, then applies TrustEngine-consistent deductions for device drift and
 * new sessions.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\DeviceRecord;
use Starisian\Sparxstar\Sirus\core\TrustEngine;
use Starisian\Sparxstar\Sirus\core\TrustResolver;

/**
 * Unit tests for TrustResolver::evaluate().
 */
final class TrustResolverTest extends SirusTestCase
{
    // ── Credential base scores (no deductions) ────────────────────────────────

    /**
     * 'elder' trust_level → base 0.95.
     */
    public function testElderBaseScore(): void
    {
        $device = $this->makeDevice(trust_level: 'elder');

        $this->assertEqualsWithDelta(0.95, TrustResolver::evaluate($device), 0.0001);
    }

    /**
     * 'contributor' trust_level → base 0.90.
     */
    public function testContributorBaseScore(): void
    {
        $device = $this->makeDevice(trust_level: 'contributor');

        $this->assertEqualsWithDelta(0.90, TrustResolver::evaluate($device), 0.0001);
    }

    /**
     * 'user' trust_level → base 0.85.
     */
    public function testUserBaseScore(): void
    {
        $device = $this->makeDevice(trust_level: 'user');

        $this->assertEqualsWithDelta(0.85, TrustResolver::evaluate($device), 0.0001);
    }

    /**
     * 'device' trust_level → base 0.70.
     */
    public function testDeviceBaseScore(): void
    {
        $device = $this->makeDevice(trust_level: 'device');

        $this->assertEqualsWithDelta(0.70, TrustResolver::evaluate($device), 0.0001);
    }

    /**
     * 'anonymous' trust_level → base 0.50.
     */
    public function testAnonymousBaseScore(): void
    {
        $device = $this->makeDevice(trust_level: 'anonymous');

        $this->assertEqualsWithDelta(0.50, TrustResolver::evaluate($device), 0.0001);
    }

    /**
     * Unrecognised trust_level → default base 0.50.
     */
    public function testUnknownTrustLevelDefaultsToHalf(): void
    {
        $device = $this->makeDevice(trust_level: 'superadmin_legacy');

        $this->assertEqualsWithDelta(0.50, TrustResolver::evaluate($device), 0.0001);
    }

    // ── Drift deduction ───────────────────────────────────────────────────────

    /**
     * drift_score > 0 triggers DEDUCTION_DEVICE_DRIFTING (-0.3).
     * 'user' (0.85) - 0.3 = 0.55.
     */
    public function testDriftDeductionApplied(): void
    {
        $device = $this->makeDevice(trust_level: 'user', drift_score: 1);

        $expected = 0.85 - TrustEngine::DEDUCTION_DEVICE_DRIFTING;
        $this->assertEqualsWithDelta($expected, TrustResolver::evaluate($device), 0.0001);
    }

    /**
     * drift_score = 0 → no deduction.
     */
    public function testNoDriftDeductionWhenZero(): void
    {
        $device = $this->makeDevice(trust_level: 'user', drift_score: 0);

        $this->assertEqualsWithDelta(0.85, TrustResolver::evaluate($device), 0.0001);
    }

    /**
     * High drift_score (e.g. 5) still applies only one deduction (-0.3).
     */
    public function testHighDriftScoreStillOnlyOneDeduction(): void
    {
        $device_high = $this->makeDevice(trust_level: 'user', drift_score: 5);
        $device_low  = $this->makeDevice(trust_level: 'user', drift_score: 1);

        $this->assertEqualsWithDelta(
            TrustResolver::evaluate($device_low),
            TrustResolver::evaluate($device_high),
            0.0001
        );
    }

    // ── New session deduction ─────────────────────────────────────────────────

    /**
     * first_seen === last_seen → DEDUCTION_NEW_SESSION (-0.1).
     * 'user' (0.85) - 0.1 = 0.75.
     */
    public function testNewSessionDeductionApplied(): void
    {
        $now    = time();
        $device = $this->makeDevice(trust_level: 'user', first_seen: $now, last_seen: $now);

        $expected = 0.85 - TrustEngine::DEDUCTION_NEW_SESSION;
        $this->assertEqualsWithDelta($expected, TrustResolver::evaluate($device), 0.0001);
    }

    /**
     * first_seen !== last_seen → no new-session deduction.
     */
    public function testNoNewSessionDeductionWhenSeen(): void
    {
        $device = $this->makeDevice(trust_level: 'user', first_seen: time() - 10, last_seen: time());

        $this->assertEqualsWithDelta(0.85, TrustResolver::evaluate($device), 0.0001);
    }

    // ── Combined deductions ───────────────────────────────────────────────────

    /**
     * Drift + new session → both deductions applied.
     * 'contributor' (0.90) - 0.3 - 0.1 = 0.50.
     */
    public function testDriftAndNewSessionCombined(): void
    {
        $now    = time();
        $device = $this->makeDevice(
            trust_level: 'contributor',
            drift_score: 2,
            first_seen:  $now,
            last_seen:   $now
        );

        $expected = 0.90
            - TrustEngine::DEDUCTION_DEVICE_DRIFTING
            - TrustEngine::DEDUCTION_NEW_SESSION;
        $this->assertEqualsWithDelta($expected, TrustResolver::evaluate($device), 0.0001);
    }

    // ── Clamping ──────────────────────────────────────────────────────────────

    /**
     * Score never drops below 0.0 regardless of deductions.
     */
    public function testScoreNeverDropsBelowZero(): void
    {
        // anonymous base 0.50 with drift − 0.3 + new session − 0.1 = 0.10. Still > 0.
        $now    = time();
        $device = $this->makeDevice(
            trust_level: 'anonymous',
            drift_score: 5,
            first_seen:  $now,
            last_seen:   $now
        );

        $this->assertGreaterThanOrEqual(0.0, TrustResolver::evaluate($device));
    }

    /**
     * Score never exceeds 1.0.
     */
    public function testScoreNeverExceedsOne(): void
    {
        $device = $this->makeDevice(trust_level: 'elder');

        $this->assertLessThanOrEqual(1.0, TrustResolver::evaluate($device));
    }

    // ── Return type ───────────────────────────────────────────────────────────

    /**
     * evaluate() always returns a float.
     */
    public function testEvaluateReturnsFloat(): void
    {
        $device = $this->makeDevice(trust_level: 'user');

        $this->assertIsFloat(TrustResolver::evaluate($device));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Builds a minimal DeviceRecord for assertions.
     *
     * @param string $trust_level Credential level.
     * @param int    $drift_score Number of detected fingerprint changes.
     * @param int    $first_seen  Unix timestamp of first registration (default: now - 10).
     * @param int    $last_seen   Unix timestamp of last activity (default: now).
     */
    private function makeDevice(
        string $trust_level = 'user',
        int $drift_score = 0,
        ?int $first_seen = null,
        ?int $last_seen = null
    ): DeviceRecord {
        $now = time();

        return new DeviceRecord(
            device_id:        'test-device-uuid',
            device_secret:    'aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899',
            fingerprint_hash: 'abc123',
            environment_json: '{}',
            first_seen:       $first_seen ?? ($now - 10),
            last_seen:        $last_seen  ?? $now,
            trust_level:      $trust_level,
            drift_score:      $drift_score,
        );
    }
}
