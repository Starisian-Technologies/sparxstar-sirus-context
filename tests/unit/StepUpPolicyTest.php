<?php

/**
 * Tests for StepUpPolicy – frozen authentication step-up boundary.
 *
 * StepUpPolicy encodes a governance-sensitive frozen decision boundary:
 *   ResourceSensitivity::HIGH   — step-up always required (regardless of trust score)
 *   ResourceSensitivity::MEDIUM — step-up required when trust_score < 0.7
 *   ResourceSensitivity::LOW    — no step-up required
 *
 * These tests lock the boundary so downstream auth behavior cannot drift unintentionally.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\ResourceSensitivity;
use Starisian\Sparxstar\Sirus\core\StepUpPolicy;
use Starisian\Sparxstar\Sirus\dto\ContextPulse;

/**
 * Unit tests for StepUpPolicy::isRequired() and StepUpPolicy::getRequiredLevel().
 */
final class StepUpPolicyTest extends SirusTestCase
{
    /** @var StepUpPolicy */
    private StepUpPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new StepUpPolicy();
    }

    // ── isRequired — HIGH sensitivity ──────────────────────────────────────────

    /**
     * HIGH resource always requires step-up, regardless of trust score.
     */
    public function testHighAlwaysRequiresStepUpAtMaxTrust(): void
    {
        $this->assertTrue(
            $this->policy->isRequired($this->makePulse(1.0), ResourceSensitivity::HIGH)
        );
    }

    /**
     * HIGH resource requires step-up even with trust_score = 1.0 (perfect trust).
     */
    public function testHighRequiresStepUpAtPerfectTrust(): void
    {
        $this->assertTrue(
            $this->policy->isRequired($this->makePulse(trust_score: 1.0), ResourceSensitivity::HIGH)
        );
    }

    /**
     * HIGH resource requires step-up even with trust_score = 0.0 (zero trust).
     */
    public function testHighRequiresStepUpAtZeroTrust(): void
    {
        $this->assertTrue(
            $this->policy->isRequired($this->makePulse(trust_score: 0.0), ResourceSensitivity::HIGH)
        );
    }

    // ── isRequired — MEDIUM sensitivity ────────────────────────────────────────

    /**
     * MEDIUM resource: trust_score exactly at threshold (0.7) does NOT require step-up.
     */
    public function testMediumAtExactThresholdDoesNotRequireStepUp(): void
    {
        $this->assertFalse(
            $this->policy->isRequired(
                $this->makePulse(trust_score: StepUpPolicy::LEVEL_2_TRUST_THRESHOLD),
                ResourceSensitivity::MEDIUM
            )
        );
    }

    /**
     * MEDIUM resource: trust_score just above threshold (0.701) does NOT require step-up.
     */
    public function testMediumAboveThresholdDoesNotRequireStepUp(): void
    {
        $this->assertFalse(
            $this->policy->isRequired($this->makePulse(trust_score: 0.701), ResourceSensitivity::MEDIUM)
        );
    }

    /**
     * MEDIUM resource: trust_score just below threshold (0.699) REQUIRES step-up.
     */
    public function testMediumBelowThresholdRequiresStepUp(): void
    {
        $this->assertTrue(
            $this->policy->isRequired($this->makePulse(trust_score: 0.699), ResourceSensitivity::MEDIUM)
        );
    }

    /**
     * MEDIUM resource: trust_score = 0.0 REQUIRES step-up.
     */
    public function testMediumAtZeroTrustRequiresStepUp(): void
    {
        $this->assertTrue(
            $this->policy->isRequired($this->makePulse(trust_score: 0.0), ResourceSensitivity::MEDIUM)
        );
    }

    /**
     * MEDIUM resource: trust_score = 1.0 does NOT require step-up.
     */
    public function testMediumAtFullTrustDoesNotRequireStepUp(): void
    {
        $this->assertFalse(
            $this->policy->isRequired($this->makePulse(trust_score: 1.0), ResourceSensitivity::MEDIUM)
        );
    }

    // ── isRequired — LOW sensitivity ────────────────────────────────────────────

    /**
     * LOW resource never requires step-up, regardless of trust score.
     */
    public function testLowNeverRequiresStepUp(): void
    {
        foreach ([0.0, 0.5, 0.699, 0.7, 1.0] as $score) {
            $this->assertFalse(
                $this->policy->isRequired($this->makePulse(trust_score: $score), ResourceSensitivity::LOW),
                "LOW resource should not require step-up at trust_score={$score}"
            );
        }
    }

    // ── getRequiredLevel — HIGH sensitivity ────────────────────────────────────

    /**
     * HIGH resource: getRequiredLevel always returns HIGH.
     */
    public function testGetRequiredLevelForHighReturnsHigh(): void
    {
        $level = $this->policy->getRequiredLevel($this->makePulse(1.0), ResourceSensitivity::HIGH);

        $this->assertSame(ResourceSensitivity::HIGH, $level);
    }

    // ── getRequiredLevel — MEDIUM sensitivity ──────────────────────────────────

    /**
     * MEDIUM resource: below threshold → returns MEDIUM.
     */
    public function testGetRequiredLevelForMediumBelowThresholdReturnsMedium(): void
    {
        $level = $this->policy->getRequiredLevel(
            $this->makePulse(trust_score: 0.699),
            ResourceSensitivity::MEDIUM
        );

        $this->assertSame(ResourceSensitivity::MEDIUM, $level);
    }

    /**
     * MEDIUM resource: at or above threshold → returns null (no step-up).
     */
    public function testGetRequiredLevelForMediumAtThresholdReturnsNull(): void
    {
        $level = $this->policy->getRequiredLevel(
            $this->makePulse(trust_score: StepUpPolicy::LEVEL_2_TRUST_THRESHOLD),
            ResourceSensitivity::MEDIUM
        );

        $this->assertNull($level);
    }

    // ── getRequiredLevel — LOW sensitivity ────────────────────────────────────

    /**
     * LOW resource: getRequiredLevel always returns null.
     */
    public function testGetRequiredLevelForLowReturnsNull(): void
    {
        $level = $this->policy->getRequiredLevel(
            $this->makePulse(trust_score: 0.0),
            ResourceSensitivity::LOW
        );

        $this->assertNull($level);
    }

    // ── ResourceSensitivity enum sanity checks ─────────────────────────────────

    /**
     * ResourceSensitivity backed values are 1, 2, 3.
     */
    public function testResourceSensitivityBackedValues(): void
    {
        $this->assertSame(1, ResourceSensitivity::LOW->value);
        $this->assertSame(2, ResourceSensitivity::MEDIUM->value);
        $this->assertSame(3, ResourceSensitivity::HIGH->value);
    }

    /**
     * ResourceSensitivity::from() correctly resolves all three cases.
     */
    public function testResourceSensitivityFromInt(): void
    {
        $this->assertSame(ResourceSensitivity::LOW,    ResourceSensitivity::from(1));
        $this->assertSame(ResourceSensitivity::MEDIUM, ResourceSensitivity::from(2));
        $this->assertSame(ResourceSensitivity::HIGH,   ResourceSensitivity::from(3));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Builds a minimal ContextPulse for use in assertions.
     *
     * @param float $trust_score Trust score to use (default 1.0).
     */
    private function makePulse(float $trust_score = 1.0): ContextPulse
    {
        $now = time();

        return new ContextPulse(
            pulse_id:    'pulse-test-id',
            context_id:  'ctx-step-up-test',
            device_id:   'dev-step-up-test',
            session_id:  'sess-step-up-test',
            site_id:     '1',
            network_id:  '1',
            trust_score: $trust_score,
            trust_level: 'NORMAL',
            issued_at:   $now,
            expires:     $now + 60,
            sig:         str_repeat('a', 64),
        );
    }
}
