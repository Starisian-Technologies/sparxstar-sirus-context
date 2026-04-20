<?php

/**
 * Tests for DeviceMatcher – fingerprint scoring and three-way classification (spec §14.3).
 *
 * DeviceMatcher defines two thresholds:
 *   STRONG_MATCH_THRESHOLD = 0.8  — same device, no additional verification needed
 *   WEAK_MATCH_THRESHOLD   = 0.6  — same device with environment change, step-up required
 *
 * And one classify() method that maps any score to a MatchResult:
 *   score >= 0.8  → MatchResult::STRONG_MATCH
 *   score >= 0.6  → MatchResult::WEAK_MATCH
 *   score <  0.6  → MatchResult::NO_MATCH
 *
 * These tests lock the classification logic so that threshold changes or
 * off-by-one errors cannot silently alter device continuity behavior.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\DeviceMatcher;
use Starisian\Sparxstar\Sirus\core\MatchResult;

/**
 * Unit tests for DeviceMatcher::classify(), scoreHash(), and scoreComponents().
 */
final class DeviceMatcherTest extends SirusTestCase
{
    private DeviceMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new DeviceMatcher();
    }

    // ── Threshold constants ───────────────────────────────────────────────────

    /**
     * STRONG_MATCH_THRESHOLD is 0.8.
     */
    public function testStrongMatchThresholdConstantIsPointEight(): void
    {
        $this->assertSame(0.8, DeviceMatcher::STRONG_MATCH_THRESHOLD);
    }

    /**
     * WEAK_MATCH_THRESHOLD is 0.6.
     */
    public function testWeakMatchThresholdConstantIsPointSix(): void
    {
        $this->assertSame(0.6, DeviceMatcher::WEAK_MATCH_THRESHOLD);
    }

    // ── classify() ────────────────────────────────────────────────────────────

    /**
     * Score 1.0 (perfect hash match) is STRONG_MATCH.
     */
    public function testClassifyOnePointZeroIsStrongMatch(): void
    {
        $this->assertSame(MatchResult::STRONG_MATCH, DeviceMatcher::classify(1.0));
    }

    /**
     * Score at exactly STRONG_MATCH_THRESHOLD (0.8) is STRONG_MATCH.
     */
    public function testClassifyAtStrongThresholdIsStrongMatch(): void
    {
        $this->assertSame(MatchResult::STRONG_MATCH, DeviceMatcher::classify(0.8));
    }

    /**
     * Score above STRONG_MATCH_THRESHOLD (e.g. 0.9) is STRONG_MATCH.
     */
    public function testClassifyAboveStrongThresholdIsStrongMatch(): void
    {
        $this->assertSame(MatchResult::STRONG_MATCH, DeviceMatcher::classify(0.9));
    }

    /**
     * Score just below STRONG_MATCH_THRESHOLD (0.799…) is WEAK_MATCH.
     * A device scoring 0.75 should NOT be silently treated as a strong match —
     * this was the silent behavioral bug in the previous two-threshold model.
     */
    public function testClassifyJustBelowStrongThresholdIsWeakMatch(): void
    {
        $this->assertSame(MatchResult::WEAK_MATCH, DeviceMatcher::classify(0.799));
    }

    /**
     * Score 0.7 (between thresholds) is WEAK_MATCH.
     */
    public function testClassifyMidRangeIsWeakMatch(): void
    {
        $this->assertSame(MatchResult::WEAK_MATCH, DeviceMatcher::classify(0.7));
    }

    /**
     * Score at exactly WEAK_MATCH_THRESHOLD (0.6) is WEAK_MATCH.
     */
    public function testClassifyAtWeakThresholdIsWeakMatch(): void
    {
        $this->assertSame(MatchResult::WEAK_MATCH, DeviceMatcher::classify(0.6));
    }

    /**
     * Score just below WEAK_MATCH_THRESHOLD (0.599…) is NO_MATCH.
     */
    public function testClassifyJustBelowWeakThresholdIsNoMatch(): void
    {
        $this->assertSame(MatchResult::NO_MATCH, DeviceMatcher::classify(0.599));
    }

    /**
     * Score 0.0 is NO_MATCH (completely different device).
     */
    public function testClassifyZeroIsNoMatch(): void
    {
        $this->assertSame(MatchResult::NO_MATCH, DeviceMatcher::classify(0.0));
    }

    /**
     * Score 0.5 (below weak threshold) is NO_MATCH.
     */
    public function testClassifyBelowWeakThresholdIsNoMatch(): void
    {
        $this->assertSame(MatchResult::NO_MATCH, DeviceMatcher::classify(0.5));
    }

    /**
     * MatchResult cases are mutually exclusive: each canonical score maps to exactly one case.
     *
     * This locks the three-way partition so that boundary changes or floating-point
     * regressions cannot cause a score to appear in two cases simultaneously.
     */
    public function testClassifyResultsAreMutuallyExclusive(): void
    {
        $cases = [
            [0.0,   MatchResult::NO_MATCH],
            [0.59,  MatchResult::NO_MATCH],
            [0.6,   MatchResult::WEAK_MATCH],
            [0.7,   MatchResult::WEAK_MATCH],
            [0.799, MatchResult::WEAK_MATCH],
            [0.8,   MatchResult::STRONG_MATCH],
            [0.85,  MatchResult::STRONG_MATCH],
            [1.0,   MatchResult::STRONG_MATCH],
        ];

        foreach ($cases as [$score, $expected]) {
            $this->assertSame(
                $expected,
                DeviceMatcher::classify($score),
                "classify({$score}) should return {$expected->name}"
            );
        }
    }

    // ── scoreHash ─────────────────────────────────────────────────────────────

    /**
     * Identical hashes produce score 1.0 → STRONG_MATCH via classify().
     */
    public function testScoreHashReturnOneForIdenticalHashes(): void
    {
        $hash = hash('sha256', 'test-fingerprint-data');

        $this->assertSame(1.0, $this->matcher->scoreHash($hash, $hash));
    }

    /**
     * Different hashes produce score 0.0 → NO_MATCH via classify().
     */
    public function testScoreHashReturnZeroForDifferentHashes(): void
    {
        $a = hash('sha256', 'fingerprint-a');
        $b = hash('sha256', 'fingerprint-b');

        $this->assertSame(0.0, $this->matcher->scoreHash($a, $b));
    }

    /**
     * Empty stored hash produces score 0.0.
     */
    public function testScoreHashReturnZeroForEmptyStoredHash(): void
    {
        $this->assertSame(0.0, $this->matcher->scoreHash('', hash('sha256', 'current')));
    }

    /**
     * Empty current hash produces score 0.0.
     */
    public function testScoreHashReturnZeroForEmptyCurrentHash(): void
    {
        $this->assertSame(0.0, $this->matcher->scoreHash(hash('sha256', 'stored'), ''));
    }

    /**
     * Both hashes empty produces score 0.0.
     */
    public function testScoreHashReturnZeroForBothEmpty(): void
    {
        $this->assertSame(0.0, $this->matcher->scoreHash('', ''));
    }

    /**
     * scoreHash result feeds directly into classify(): identical → STRONG_MATCH.
     */
    public function testScoreHashIntegrationWithClassify(): void
    {
        $hash   = hash('sha256', 'canonical-fingerprint');
        $score  = $this->matcher->scoreHash($hash, $hash);
        $result = DeviceMatcher::classify($score);

        $this->assertSame(MatchResult::STRONG_MATCH, $result);
    }

    /**
     * scoreHash result feeds directly into classify(): different → NO_MATCH.
     */
    public function testScoreHashDifferentHashesClassifyAsNoMatch(): void
    {
        $stored  = hash('sha256', 'old-fingerprint');
        $current = hash('sha256', 'new-fingerprint');
        $score   = $this->matcher->scoreHash($stored, $current);
        $result  = DeviceMatcher::classify($score);

        $this->assertSame(MatchResult::NO_MATCH, $result);
    }

    // ── scoreComponents ───────────────────────────────────────────────────────

    /**
     * Identical component maps produce score 1.0.
     *
     * Keys use server-canonical snake_case (PHP is authoritative).
     * hardware_concurrency is the correct form of JS hardwareConcurrency.
     */
    public function testScoreComponentsReturnOneForIdenticalMaps(): void
    {
        $components = [
            'canvas_hash'          => 'abc123',
            'screen'               => '1920x1080',
            'timezone'             => 'America/Los_Angeles',
            'platform'             => 'MacIntel',
            'languages'            => 'en-US',
            'color_depth'          => '24',
            'hardware_concurrency' => '8',
        ];

        $this->assertSame(1.0, $this->matcher->scoreComponents($components, $components));
    }

    /**
     * Completely different component maps produce score 0.0.
     */
    public function testScoreComponentsReturnZeroForCompletelyDifferentMaps(): void
    {
        $stored = [
            'canvas_hash'          => 'abc',
            'screen'               => '1920x1080',
            'timezone'             => 'America/LA',
            'platform'             => 'MacIntel',
            'languages'            => 'en-US',
            'color_depth'          => '24',
            'hardware_concurrency' => '8',
        ];

        $current = [
            'canvas_hash'          => 'xyz',
            'screen'               => '1280x720',
            'timezone'             => 'Europe/Paris',
            'platform'             => 'Win32',
            'languages'            => 'fr-FR',
            'color_depth'          => '16',
            'hardware_concurrency' => '4',
        ];

        $this->assertSame(0.0, $this->matcher->scoreComponents($stored, $current));
    }

    /**
     * Empty stored map produces score 0.0.
     */
    public function testScoreComponentsReturnZeroForEmptyStoredMap(): void
    {
        $current = ['canvas_hash' => 'abc', 'screen' => '1920x1080'];

        $this->assertSame(0.0, $this->matcher->scoreComponents([], $current));
    }

    /**
     * Empty current map produces score 0.0.
     */
    public function testScoreComponentsReturnZeroForEmptyCurrentMap(): void
    {
        $stored = ['canvas_hash' => 'abc', 'screen' => '1920x1080'];

        $this->assertSame(0.0, $this->matcher->scoreComponents($stored, []));
    }

    /**
     * Partial match: only canvas_hash differs — score should reflect the weighted loss.
     *
     * canvas_hash weight = 0.30. All other components match.
     * Expected score = (1.0 - 0.30) = 0.70 (total_weight = 1.0 since all keys present).
     * This is below STRONG_MATCH_THRESHOLD (0.8), so classify() → WEAK_MATCH.
     */
    public function testScoreComponentsPartialMatchOnlyCanvasDiffers(): void
    {
        $stored = [
            'canvas_hash'          => 'abc',
            'screen'               => '1920x1080',
            'timezone'             => 'America/LA',
            'platform'             => 'MacIntel',
            'languages'            => 'en-US',
            'color_depth'          => '24',
            'hardware_concurrency' => '8',
        ];

        $current                 = $stored;
        $current['canvas_hash']  = 'xyz';

        $score  = $this->matcher->scoreComponents($stored, $current);
        $result = DeviceMatcher::classify($score);

        $this->assertEqualsWithDelta(0.70, $score, 0.001);
        $this->assertSame(MatchResult::WEAK_MATCH, $result);
    }

    /**
     * Only components present in both maps contribute to the score.
     */
    public function testScoreComponentsOnlyCommonKeysContribute(): void
    {
        $stored  = ['canvas_hash' => 'abc'];
        $current = ['canvas_hash' => 'abc', 'screen' => '1920x1080'];

        $this->assertSame(1.0, $this->matcher->scoreComponents($stored, $current));
    }

    /**
     * hardware_concurrency key (not hardware_conc) is scored correctly.
     *
     * This verifies the key-name fix: the old hardware_conc would score silently
     * as zero because it never matched the JS-emitted field name.
     */
    public function testHardwareConcurrencyKeyIsRecognised(): void
    {
        $stored  = ['hardware_concurrency' => '8'];
        $current = ['hardware_concurrency' => '8'];

        // Only hardware_concurrency present in both; it matches → score 1.0.
        $this->assertSame(1.0, $this->matcher->scoreComponents($stored, $current));
    }
}
