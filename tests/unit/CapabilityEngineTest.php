<?php

/**
 * Tests for CapabilityEngine – maps trust levels to capability sets.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive;
use Starisian\Sparxstar\Sirus\core\CapabilityEngine;
use Starisian\Sparxstar\Sirus\core\SirusContext;

/**
 * Unit tests for CapabilityEngine::resolve().
 *
 * The sparxstar_sirus_capabilities filter is shimmed to pass through unchanged,
 * so these tests verify the base capability map exclusively.
 */
final class CapabilityEngineTest extends SirusTestCase
{
    private CapabilityEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['registered_filters'] = [];
        $this->engine                  = new CapabilityEngine();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeContext(string $trust_level): SirusContext
    {
        return new SirusContext(
            context_id:     'ctx-cap-test',
            environment_id: 'env-1',
            network_id:     '1',
            site_id:        '1',
            device_id:      'dev-1',
            session_id:     'sess-1',
            identity_id:    null,
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    TrustLevelPrimitive::from($trust_level),
            trust_score:    1.0,
            issued_at:      1_700_000_000,
            expires:        1_700_000_300,
        );
    }

    // ── LOCKED ────────────────────────────────────────────────────────────────

    /**
     * LOCKED: no capabilities granted.
     */
    public function testLockedGrantsNoCapabilities(): void
    {
        $caps = $this->engine->resolve($this->makeContext('LOCKED'));

        $this->assertSame([], $caps);
    }

    // ── STEP_UP_REQUIRED ─────────────────────────────────────────────────────

    /**
     * STEP_UP_REQUIRED: only read_context is granted.
     */
    public function testStepUpRequiredGrantsOnlyReadContext(): void
    {
        $caps = $this->engine->resolve($this->makeContext('STEP_UP_REQUIRED'));

        $this->assertSame(['read_context'], $caps);
    }

    // ── NORMAL ───────────────────────────────────────────────────────────────

    /**
     * NORMAL: full capability set is granted.
     */
    public function testNormalGrantsFullCapabilitySet(): void
    {
        $caps = $this->engine->resolve($this->makeContext('NORMAL'));

        $this->assertContains('read_context', $caps);
        $this->assertContains('submit_environment', $caps);
        $this->assertContains('submit_content', $caps);
        $this->assertContains('read_profile', $caps);
        $this->assertContains('manage_context', $caps);
        $this->assertContains('resolve_authority', $caps);
        $this->assertCount(6, $caps);
    }

    /**
     * NORMAL context returns the exact expected capability set in order.
     */
    public function testNormalContextGetsManagementCapabilities(): void
    {
        $this->assertSame(
            [
                'read_context',
                'submit_environment',
                'submit_content',
                'read_profile',
                'manage_context',
                'resolve_authority',
            ],
            $this->engine->resolve($this->makeContext('NORMAL'))
        );
    }

    // ── capability set is a superset of lower trust levels ───────────────────

    /**
     * Each trust level's capabilities are a superset of the level below it.
     */
    public function testCapabilitiesAreCumulativeAcrossLevels(): void
    {
        $locked   = $this->engine->resolve($this->makeContext('LOCKED'));
        $step_up  = $this->engine->resolve($this->makeContext('STEP_UP_REQUIRED'));
        $normal   = $this->engine->resolve($this->makeContext('NORMAL'));

        foreach ($locked as $cap) {
            $this->assertContains($cap, $step_up);
        }
        foreach ($step_up as $cap) {
            $this->assertContains($cap, $normal);
        }
    }

    // ── resolve() always returns an array ────────────────────────────────────

    /**
     * resolve() must always return an array (never null, never non-array).
     */
    public function testResolveAlwaysReturnsArray(): void
    {
        foreach (['LOCKED', 'STEP_UP_REQUIRED', 'NORMAL'] as $level) {
            $result = $this->engine->resolve($this->makeContext($level));
            $this->assertIsArray($result, "Expected array for trust level '{$level}'.");
        }
    }

    // ── read_context is present in non-locked levels ──────────────────────────

    /**
     * read_context must be present for STEP_UP_REQUIRED and NORMAL.
     */
    public function testReadContextPresentForNonLockedLevels(): void
    {
        foreach (['STEP_UP_REQUIRED', 'NORMAL'] as $level) {
            $caps = $this->engine->resolve($this->makeContext($level));
            $this->assertContains(
                'read_context',
                $caps,
                "Expected 'read_context' in capabilities for trust level '{$level}'."
            );
        }
    }

    // ── Filter augmentation ───────────────────────────────────────────────────

    /**
     * The sparxstar_sirus_capabilities filter can augment the resolved set.
     */
    public function testCapabilitiesFilterCanAugmentResolvedSet(): void
    {
        add_filter(
            'sparxstar_sirus_capabilities',
            static function (array $capabilities): array {
                $capabilities[] = 'custom_capability';
                return $capabilities;
            }
        );

        $engine = new CapabilityEngine();

        $this->assertContains(
            'custom_capability',
            $engine->resolve($this->makeContext('NORMAL'))
        );
    }
}
