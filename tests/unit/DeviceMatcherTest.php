<?php

/**
 * Tests for DeviceMatcher – fingerprint scoring and threshold classification.
 *
 * DeviceMatcher defines two thresholds:
 *   EXACT_THRESHOLD = 1.0  — identical fingerprint, no drift
 *   DRIFT_THRESHOLD = 0.6  — boundary between "same device with drift" and "new device"
 *
 * These tests lock the classification logic so that threshold changes or
 * off-by-one errors cannot silently alter device continuity behavior.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\DeviceMatcher;

/**
 * Unit tests for DeviceMatcher::scoreHash(), scoreComponents(),
 * isExactMatch(), isDrift(), and isNewDevice().
 */
final class DeviceMatcherTest extends SirusTestCase
{
    private DeviceMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new DeviceMatcher();
    }

    // ── scoreHash ─────────────────────────────────────────────────────────────

    /**
     * Identical hashes produce score 1.0.
     */
    public function testScoreHashReturnOneForIdenticalHashes(): void
    {
        $hash = hash('sha256', 'test-fingerprint-data');

        $this->assertSame(1.0, $this->matcher->scoreHash($hash, $hash));
    }

    /**
     * Different hashes produce score 0.0.
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

    // ── scoreComponents ───────────────────────────────────────────────────────

    /**
     * Identical component maps produce score 1.0.
     */
    public function testScoreComponentsReturnOneForIdenticalMaps(): void
    {
        $components = [
            'canvas_hash'   => 'abc123',
            'screen'        => '1920x1080',
            'timezone'      => 'America/Los_Angeles',
            'platform'      => 'MacIntel',
            'languages'     => 'en-US',
            'color_depth'   => '24',
            'hardware_conc' => '8',
        ];

        $this->assertSame(1.0, $this->matcher->scoreComponents($components, $components));
    }

    /**
     * Completely different component maps produce score 0.0.
     */
    public function testScoreComponentsReturnZeroForCompletelyDifferentMaps(): void
    {
        $stored = [
            'canvas_hash'   => 'abc',
            'screen'        => '1920x1080',
            'timezone'      => 'America/LA',
            'platform'      => 'MacIntel',
            'languages'     => 'en-US',
            'color_depth'   => '24',
            'hardware_conc' => '8',
        ];

        $current = [
            'canvas_hash'   => 'xyz',
            'screen'        => '1280x720',
            'timezone'      => 'Europe/Paris',
            'platform'      => 'Win32',
            'languages'     => 'fr-FR',
            'color_depth'   => '16',
            'hardware_conc' => '4',
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
     */
    public function testScoreComponentsPartialMatchOnlyCanvasDiffers(): void
    {
        $stored = [
            'canvas_hash'   => 'abc',
            'screen'        => '1920x1080',
            'timezone'      => 'America/LA',
            'platform'      => 'MacIntel',
            'languages'     => 'en-US',
            'color_depth'   => '24',
            'hardware_conc' => '8',
        ];

        $current = $stored;
        $current['canvas_hash'] = 'xyz'; // only this differs

        $score = $this->matcher->scoreComponents($stored, $current);

        // Expect approximately 0.70 (0.30 weight lost)
        $this->assertEqualsWithDelta(0.70, $score, 0.001);
    }

    /**
     * Only components present in both maps contribute to the score.
     */
    public function testScoreComponentsOnlyCommonKeysContribute(): void
    {
        $stored  = ['canvas_hash' => 'abc'];
        $current = ['canvas_hash' => 'abc', 'screen' => '1920x1080'];

        // Only canvas_hash is in both; it matches → score = 1.0
        $this->assertSame(1.0, $this->matcher->scoreComponents($stored, $current));
    }

    // ── isExactMatch ──────────────────────────────────────────────────────────

    /**
     * Score 1.0 is an exact match.
     */
    public function testIsExactMatchTrueForOnePointZero(): void
    {
        $this->assertTrue($this->matcher->isExactMatch(1.0));
    }

    /**
     * Score just below 1.0 is NOT an exact match.
     */
    public function testIsExactMatchFalseForScoreBelowOne(): void
    {
        $this->assertFalse($this->matcher->isExactMatch(0.99));
    }

    /**
     * Score 0.0 is not an exact match.
     */
    public function testIsExactMatchFalseForZero(): void
    {
        $this->assertFalse($this->matcher->isExactMatch(0.0));
    }

    // ── isDrift ───────────────────────────────────────────────────────────────

    /**
     * Score at DRIFT_THRESHOLD (0.6) is a drift match.
     */
    public function testIsDriftTrueAtDriftThreshold(): void
    {
        $this->assertTrue($this->matcher->isDrift(DeviceMatcher::DRIFT_THRESHOLD));
    }

    /**
     * Score above DRIFT_THRESHOLD but below EXACT_THRESHOLD (e.g., 0.8) is drift.
     */
    public function testIsDriftTrueForScoreBetweenThresholds(): void
    {
        $this->assertTrue($this->matcher->isDrift(0.8));
    }

    /**
     * Score at EXACT_THRESHOLD (1.0) is NOT drift (it's exact).
     */
    public function testIsDriftFalseAtExactThreshold(): void
    {
        $this->assertFalse($this->matcher->isDrift(DeviceMatcher::EXACT_THRESHOLD));
    }

    /**
     * Score below DRIFT_THRESHOLD (0.59) is NOT drift (it's a new device).
     */
    public function testIsDriftFalseBelowDriftThreshold(): void
    {
        $this->assertFalse($this->matcher->isDrift(0.59));
    }

    /**
     * Score 0.0 is not drift.
     */
    public function testIsDriftFalseForZero(): void
    {
        $this->assertFalse($this->matcher->isDrift(0.0));
    }

    // ── isNewDevice ───────────────────────────────────────────────────────────

    /**
     * Score 0.0 (no match) is a new device.
     */
    public function testIsNewDeviceTrueForZeroScore(): void
    {
        $this->assertTrue($this->matcher->isNewDevice(0.0));
    }

    /**
     * Score just below DRIFT_THRESHOLD (0.59) is a new device.
     */
    public function testIsNewDeviceTrueJustBelowDriftThreshold(): void
    {
        $this->assertTrue($this->matcher->isNewDevice(0.59));
    }

    /**
     * Score at DRIFT_THRESHOLD (0.6) is NOT a new device.
     */
    public function testIsNewDeviceFalseAtDriftThreshold(): void
    {
        $this->assertFalse($this->matcher->isNewDevice(DeviceMatcher::DRIFT_THRESHOLD));
    }

    /**
     * Score 1.0 (exact match) is not a new device.
     */
    public function testIsNewDeviceFalseForExactMatch(): void
    {
        $this->assertFalse($this->matcher->isNewDevice(1.0));
    }

    // ── Threshold constants ───────────────────────────────────────────────────

    /**
     * EXACT_THRESHOLD constant is 1.0.
     */
    public function testExactThresholdConstantIsOne(): void
    {
        $this->assertSame(1.0, DeviceMatcher::EXACT_THRESHOLD);
    }

    /**
     * DRIFT_THRESHOLD constant is 0.6.
     */
    public function testDriftThresholdConstantIsPointSix(): void
    {
        $this->assertSame(0.6, DeviceMatcher::DRIFT_THRESHOLD);
    }

    /**
     * The boundary is non-overlapping: at exactly DRIFT_THRESHOLD a score is
     * either drift (≥ threshold) or new device (< threshold), never both.
     *
     * At exactly 0.6:
     *   isDrift()     → true  (score >= 0.6 && score < 1.0)
     *   isNewDevice() → false (score >= 0.6)
     */
    public function testBoundaryAtDriftThresholdIsNonOverlapping(): void
    {
        $score = DeviceMatcher::DRIFT_THRESHOLD;

        $this->assertTrue($this->matcher->isDrift($score));
        $this->assertFalse($this->matcher->isNewDevice($score));
    }

    /**
     * The three classification methods are mutually exclusive for canonical scores.
     *
     * Any score is exactly one of: exactMatch, drift, or newDevice.
     */
    public function testClassificationsAreMutuallyExclusive(): void
    {
        $cases = [
            [0.0,  false, false, true],  // new device
            [0.59, false, false, true],  // new device
            [0.6,  false, true,  false], // drift
            [0.8,  false, true,  false], // drift
            [0.99, false, true,  false], // drift
            [1.0,  true,  false, false], // exact
        ];

        foreach ($cases as [$score, $exact, $drift, $new]) {
            $this->assertSame(
                $exact,
                $this->matcher->isExactMatch($score),
                "isExactMatch({$score})"
            );
            $this->assertSame(
                $drift,
                $this->matcher->isDrift($score),
                "isDrift({$score})"
            );
            $this->assertSame(
                $new,
                $this->matcher->isNewDevice($score),
                "isNewDevice({$score})"
            );
        }
    }
}
