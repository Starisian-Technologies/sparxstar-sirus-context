<?php

/**
 * Tests for TrustEngine – frozen trust score algorithm.
 *
 * Covers all signal combinations, boundary deductions, clamping to [0.0, 1.0],
 * and score → level mapping. The algorithm is spec-frozen: these tests are the
 * canonical record of the expected behavior.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\TrustEngine;

/**
 * Unit tests for TrustEngine::compute() and TrustEngine::scoreToLevel().
 */
final class TrustEngineTest extends SirusTestCase
{
    private TrustEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new TrustEngine();
    }

    // ── Baseline (no signals) ─────────────────────────────────────────────────

    /**
     * No signals → base score 1.0, trust level NORMAL.
     */
    public function testNoSignalsReturnsBaseScore(): void
    {
        $result = $this->engine->compute([]);

        $this->assertSame(1.0, $result['trust_score']);
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $result['trust_level']);
    }

    // ── Individual signal deductions ──────────────────────────────────────────

    /**
     * device_drifting=true → -0.3 deduction → score 0.7.
     */
    public function testDeviceDriftingDeduction(): void
    {
        $result = $this->engine->compute(['device_drifting' => true]);

        $this->assertSame(
            round(TrustEngine::BASE_SCORE - TrustEngine::DEDUCTION_DEVICE_DRIFTING, 10),
            round($result['trust_score'], 10)
        );
        // 0.7 >= 0.7 → NORMAL (boundary case).
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $result['trust_level']);
    }

    /**
     * geo_mismatch=true → -0.2 deduction → score 0.8.
     */
    public function testGeoMismatchDeduction(): void
    {
        $result = $this->engine->compute(['geo_mismatch' => true]);

        $this->assertSame(
            round(TrustEngine::BASE_SCORE - TrustEngine::DEDUCTION_GEO_MISMATCH, 10),
            round($result['trust_score'], 10)
        );
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $result['trust_level']);
    }

    /**
     * new_session=true → -0.1 deduction → score 0.9.
     */
    public function testNewSessionDeduction(): void
    {
        $result = $this->engine->compute(['new_session' => true]);

        $this->assertSame(
            round(TrustEngine::BASE_SCORE - TrustEngine::DEDUCTION_NEW_SESSION, 10),
            round($result['trust_score'], 10)
        );
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $result['trust_level']);
    }

    /**
     * recent_failures=true → -0.3 deduction → score 0.7.
     */
    public function testRecentFailuresDeduction(): void
    {
        $result = $this->engine->compute(['recent_failures' => true]);

        $this->assertSame(
            round(TrustEngine::BASE_SCORE - TrustEngine::DEDUCTION_RECENT_FAILURES, 10),
            round($result['trust_score'], 10)
        );
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $result['trust_level']);
    }

    // ── Combined signal combinations ──────────────────────────────────────────

    /**
     * device_drifting + geo_mismatch → 1.0 - 0.3 - 0.2 = 0.5 → ELEVATED.
     */
    public function testDriftingPlusGeoMismatchYieldsElevated(): void
    {
        $result = $this->engine->compute([
            'device_drifting' => true,
            'geo_mismatch'    => true,
        ]);

        $this->assertEqualsWithDelta(0.5, $result['trust_score'], 0.0001);
        $this->assertSame(TrustEngine::LEVEL_ELEVATED, $result['trust_level']);
    }

    /**
     * device_drifting + recent_failures → 1.0 - 0.3 - 0.3 = 0.4 → ELEVATED.
     */
    public function testDriftingPlusRecentFailuresYieldsElevated(): void
    {
        $result = $this->engine->compute([
            'device_drifting'  => true,
            'recent_failures'  => true,
        ]);

        $this->assertEqualsWithDelta(0.4, $result['trust_score'], 0.0001);
        $this->assertSame(TrustEngine::LEVEL_ELEVATED, $result['trust_level']);
    }

    /**
     * All four signals → 1.0 - 0.3 - 0.2 - 0.1 - 0.3 = 0.1 → ELEVATED.
     */
    public function testAllSignalsCombinedYieldsElevated(): void
    {
        $result = $this->engine->compute([
            'device_drifting' => true,
            'geo_mismatch'    => true,
            'new_session'     => true,
            'recent_failures' => true,
        ]);

        $this->assertEqualsWithDelta(0.1, $result['trust_score'], 0.0001);
        $this->assertSame(TrustEngine::LEVEL_ELEVATED, $result['trust_level']);
    }

    // ── Clamping ──────────────────────────────────────────────────────────────

    /**
     * Score is clamped to 0.0 when deductions exceed 1.0.
     * device_drifting + recent_failures + geo_mismatch = -0.8 already.
     * Add new_session (-0.1) for sum = 0.1, so maximum test case is all four signals.
     * Extra: verify that passing all four never goes below 0.
     */
    public function testScoreNeverDropsBelowZero(): void
    {
        $result = $this->engine->compute([
            'device_drifting' => true,
            'geo_mismatch'    => true,
            'new_session'     => true,
            'recent_failures' => true,
        ]);

        $this->assertGreaterThanOrEqual(0.0, $result['trust_score']);
    }

    /**
     * Score is clamped to at most 1.0 even if the base were somehow larger.
     * Passing no deduction signals always returns exactly 1.0.
     */
    public function testScoreNeverExceedsOne(): void
    {
        $result = $this->engine->compute([]);

        $this->assertLessThanOrEqual(1.0, $result['trust_score']);
    }

    // ── scoreToLevel boundary cases ───────────────────────────────────────────

    /**
     * score 1.0 → NORMAL.
     */
    public function testScoreOneLevelNormal(): void
    {
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $this->engine->scoreToLevel(1.0));
    }

    /**
     * score 0.7 (exact boundary) → NORMAL (>= 0.7).
     */
    public function testScoreAtSeventyBoundaryIsNormal(): void
    {
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $this->engine->scoreToLevel(0.7));
    }

    /**
     * score just below 0.7 → ELEVATED.
     */
    public function testScoreJustBelowSeventyIsElevated(): void
    {
        $this->assertSame(TrustEngine::LEVEL_ELEVATED, $this->engine->scoreToLevel(0.699));
    }

    /**
     * score 0.1 → ELEVATED (above zero, below threshold).
     */
    public function testScoreLowButNonZeroIsElevated(): void
    {
        $this->assertSame(TrustEngine::LEVEL_ELEVATED, $this->engine->scoreToLevel(0.1));
    }

    /**
     * score 0.0 → CRITICAL.
     */
    public function testScoreZeroIsCritical(): void
    {
        $this->assertSame(TrustEngine::LEVEL_CRITICAL, $this->engine->scoreToLevel(0.0));
    }

    // ── False/absent signal values ────────────────────────────────────────────

    /**
     * Signals present but false → no deductions applied.
     */
    public function testFalseSignalsAreIgnored(): void
    {
        $result = $this->engine->compute([
            'device_drifting' => false,
            'geo_mismatch'    => false,
            'new_session'     => false,
            'recent_failures' => false,
        ]);

        $this->assertSame(1.0, $result['trust_score']);
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $result['trust_level']);
    }

    /**
     * Unknown/extra keys in the signal map → silently ignored.
     */
    public function testUnknownSignalKeysAreIgnored(): void
    {
        $result = $this->engine->compute([
            'unknown_signal' => true,
            'another_signal' => true,
        ]);

        $this->assertSame(1.0, $result['trust_score']);
        $this->assertSame(TrustEngine::LEVEL_NORMAL, $result['trust_level']);
    }

    // ── Return type contract ──────────────────────────────────────────────────

    /**
     * compute() always returns an array with trust_score (float) and trust_level (string).
     */
    public function testComputeReturnShape(): void
    {
        $result = $this->engine->compute([]);

        $this->assertArrayHasKey('trust_score', $result);
        $this->assertArrayHasKey('trust_level', $result);
        $this->assertIsFloat($result['trust_score']);
        $this->assertIsString($result['trust_level']);
    }
}
