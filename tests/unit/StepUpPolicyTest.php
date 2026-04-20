<?php

/**
 * Tests for StepUpPolicy – frozen authentication step-up boundary.
 *
 * StepUpPolicy encodes a governance-sensitive frozen decision boundary per spec §15 / Helios §11:
 *   trust_level === STEP_UP_REQUIRED   — step-up always required (pre-flagged context)
 *   ResourceSensitivity::LEVEL_3       — step-up always required (regardless of trust score)
 *   ResourceSensitivity::LEVEL_2       — step-up required when trust_score < 0.7
 *   ResourceSensitivity::LEVEL_1       — no step-up required
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
 * Unit tests for StepUpPolicy::requiresStepUp() and StepUpPolicy::getRequiredLevel().
 */
final class StepUpPolicyTest extends SirusTestCase
{
    /** @var StepUpPolicy */
    private StepUpPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new StepUpPolicy();
    }

    // ── STEP_UP_REQUIRED trust level — pre-flagged override ────────────────────

    /**
     * A pulse with trust_level === STEP_UP_REQUIRED always requires step-up,
     * even for LEVEL_1 sensitivity and a perfect trust score of 1.0.
     *
     * This is the security gate: a context pre-flagged for step-up cannot be
     * cleared by having a high numeric trust score at a low sensitivity level.
     */
    public function testStepUpRequiredTrustLevelAlwaysRequiresStepUpAtLevel1(): void
    {
        $pulse = $this->makePulse(trust_score: 1.0, trust_level: StepUpPolicy::TRUST_LEVEL_STEP_UP_REQUIRED);

        $this->assertTrue(
            $this->policy->requiresStepUp($pulse, ResourceSensitivity::LEVEL_1)
        );
    }

    /**
     * A pulse with trust_level === STEP_UP_REQUIRED always requires step-up for LEVEL_2.
     */
    public function testStepUpRequiredTrustLevelAlwaysRequiresStepUpAtLevel2(): void
    {
        $pulse = $this->makePulse(trust_score: 0.9, trust_level: StepUpPolicy::TRUST_LEVEL_STEP_UP_REQUIRED);

        $this->assertTrue(
            $this->policy->requiresStepUp($pulse, ResourceSensitivity::LEVEL_2)
        );
    }

    /**
     * getRequiredLevel returns non-null for a STEP_UP_REQUIRED pulse at LEVEL_1 sensitivity.
     */
    public function testGetRequiredLevelForStepUpRequiredAtLevel1ReturnsNonNull(): void
    {
        $pulse = $this->makePulse(trust_score: 1.0, trust_level: StepUpPolicy::TRUST_LEVEL_STEP_UP_REQUIRED);

        $level = $this->policy->getRequiredLevel($pulse, ResourceSensitivity::LEVEL_1);

        $this->assertNotNull($level);
    }

    // ── requiresStepUp — LEVEL_3 sensitivity ───────────────────────────────────

    /**
     * LEVEL_3 resource requires step-up even with trust_score = 1.0 (perfect trust).
     */
    public function testLevel3RequiresStepUpAtPerfectTrust(): void
    {
        $this->assertTrue(
            $this->policy->requiresStepUp($this->makePulse(trust_score: 1.0), ResourceSensitivity::LEVEL_3)
        );
    }

    /**
     * LEVEL_3 resource requires step-up even with trust_score = 0.0 (zero trust).
     */
    public function testLevel3RequiresStepUpAtZeroTrust(): void
    {
        $this->assertTrue(
            $this->policy->requiresStepUp($this->makePulse(trust_score: 0.0), ResourceSensitivity::LEVEL_3)
        );
    }

    // ── requiresStepUp — LEVEL_2 sensitivity ───────────────────────────────────

    /**
     * LEVEL_2 resource: trust_score exactly at threshold (0.7) does NOT require step-up.
     */
    public function testLevel2AtExactThresholdDoesNotRequireStepUp(): void
    {
        $this->assertFalse(
            $this->policy->requiresStepUp(
                $this->makePulse(trust_score: StepUpPolicy::LEVEL_2_TRUST_THRESHOLD),
                ResourceSensitivity::LEVEL_2
            )
        );
    }

    /**
     * LEVEL_2 resource: trust_score just above threshold (0.701) does NOT require step-up.
     */
    public function testLevel2AboveThresholdDoesNotRequireStepUp(): void
    {
        $this->assertFalse(
            $this->policy->requiresStepUp($this->makePulse(trust_score: 0.701), ResourceSensitivity::LEVEL_2)
        );
    }

    /**
     * LEVEL_2 resource: trust_score just below threshold (0.699) REQUIRES step-up.
     */
    public function testLevel2BelowThresholdRequiresStepUp(): void
    {
        $this->assertTrue(
            $this->policy->requiresStepUp($this->makePulse(trust_score: 0.699), ResourceSensitivity::LEVEL_2)
        );
    }

    /**
     * LEVEL_2 resource: trust_score = 0.0 REQUIRES step-up.
     */
    public function testLevel2AtZeroTrustRequiresStepUp(): void
    {
        $this->assertTrue(
            $this->policy->requiresStepUp($this->makePulse(trust_score: 0.0), ResourceSensitivity::LEVEL_2)
        );
    }

    /**
     * LEVEL_2 resource: trust_score = 1.0 does NOT require step-up.
     */
    public function testLevel2AtFullTrustDoesNotRequireStepUp(): void
    {
        $this->assertFalse(
            $this->policy->requiresStepUp($this->makePulse(trust_score: 1.0), ResourceSensitivity::LEVEL_2)
        );
    }

    // ── requiresStepUp — LEVEL_1 sensitivity ───────────────────────────────────

    /**
     * LEVEL_1 resource with NORMAL trust level never requires step-up.
     */
    public function testLevel1NeverRequiresStepUp(): void
    {
        foreach ([0.0, 0.5, 0.699, 0.7, 1.0] as $score) {
            $this->assertFalse(
                $this->policy->requiresStepUp($this->makePulse(trust_score: $score), ResourceSensitivity::LEVEL_1),
                "LEVEL_1 resource should not require step-up at trust_score={$score}"
            );
        }
    }

    // ── getRequiredLevel — LEVEL_3 sensitivity ─────────────────────────────────

    /**
     * LEVEL_3 resource: getRequiredLevel always returns LEVEL_3.
     */
    public function testGetRequiredLevelForLevel3ReturnsLevel3(): void
    {
        $level = $this->policy->getRequiredLevel($this->makePulse(1.0), ResourceSensitivity::LEVEL_3);

        $this->assertSame(ResourceSensitivity::LEVEL_3, $level);
    }

    // ── getRequiredLevel — LEVEL_2 sensitivity ─────────────────────────────────

    /**
     * LEVEL_2 resource: below threshold → returns LEVEL_2.
     */
    public function testGetRequiredLevelForLevel2BelowThresholdReturnsLevel2(): void
    {
        $level = $this->policy->getRequiredLevel(
            $this->makePulse(trust_score: 0.699),
            ResourceSensitivity::LEVEL_2
        );

        $this->assertSame(ResourceSensitivity::LEVEL_2, $level);
    }

    /**
     * LEVEL_2 resource: at or above threshold → returns null (no step-up).
     */
    public function testGetRequiredLevelForLevel2AtThresholdReturnsNull(): void
    {
        $level = $this->policy->getRequiredLevel(
            $this->makePulse(trust_score: StepUpPolicy::LEVEL_2_TRUST_THRESHOLD),
            ResourceSensitivity::LEVEL_2
        );

        $this->assertNull($level);
    }

    // ── getRequiredLevel — LEVEL_1 sensitivity ─────────────────────────────────

    /**
     * LEVEL_1 resource with NORMAL trust level: getRequiredLevel always returns null.
     */
    public function testGetRequiredLevelForLevel1ReturnsNull(): void
    {
        $level = $this->policy->getRequiredLevel(
            $this->makePulse(trust_score: 0.0),
            ResourceSensitivity::LEVEL_1
        );

        $this->assertNull($level);
    }

    // ── ResourceSensitivity enum sanity checks ─────────────────────────────────

    /**
     * ResourceSensitivity backed values are 1, 2, 3.
     */
    public function testResourceSensitivityBackedValues(): void
    {
        $this->assertSame(1, ResourceSensitivity::LEVEL_1->value);
        $this->assertSame(2, ResourceSensitivity::LEVEL_2->value);
        $this->assertSame(3, ResourceSensitivity::LEVEL_3->value);
    }

    /**
     * ResourceSensitivity::from() correctly resolves all three cases.
     */
    public function testResourceSensitivityFromInt(): void
    {
        $this->assertSame(ResourceSensitivity::LEVEL_1, ResourceSensitivity::from(1));
        $this->assertSame(ResourceSensitivity::LEVEL_2, ResourceSensitivity::from(2));
        $this->assertSame(ResourceSensitivity::LEVEL_3, ResourceSensitivity::from(3));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Builds a minimal ContextPulse for use in assertions.
     *
     * @param float  $trust_score Trust score to use (default 1.0).
     * @param string $trust_level Trust level string (default 'NORMAL').
     */
    private function makePulse(float $trust_score = 1.0, string $trust_level = 'NORMAL'): ContextPulse
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
            trust_level: $trust_level,
            issued_at:   $now,
            expires:     $now + 60,
            sig:         str_repeat('a', 64),
        );
    }
}
