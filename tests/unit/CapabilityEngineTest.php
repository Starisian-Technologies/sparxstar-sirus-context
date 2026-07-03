<?php

/**
 * Tests for CapabilityEngine – maps trust levels to capability sets.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Infrastructure\DTOs\CredentialTier;
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

    private function makeContext(string $credential_tier): SirusContext
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
            credential_tier: CredentialTier::from($credential_tier),
            trust_level:    TrustLevelPrimitive::NORMAL,
            trust_score:    1.0,
            issued_at:      1_700_000_000,
            expires:        1_700_000_300,
        );
    }

    // ── anonymous ─────────────────────────────────────────────────────────────

    /**
     * anonymous: only read_context is granted.
     */
    public function testAnonymousGrantsOnlyReadContext(): void
    {
        $caps = $this->engine->resolve($this->makeContext('anonymous'));

        $this->assertSame(['read_context'], $caps);
    }

    // ── device ────────────────────────────────────────────────────────────────

    /**
     * device: read_context + submit_environment.
     */
    public function testDeviceGrantsReadContextAndSubmitEnvironment(): void
    {
        $caps = $this->engine->resolve($this->makeContext('device'));

        $this->assertContains('read_context', $caps);
        $this->assertContains('submit_environment', $caps);
        $this->assertCount(2, $caps);
    }

    // ── contributor ───────────────────────────────────────────────────────────

    /**
     * contributor: read_context + submit_environment + submit_content.
     */
    public function testContributorGrantsThreeCapabilities(): void
    {
        $caps = $this->engine->resolve($this->makeContext('contributor'));

        $this->assertContains('read_context', $caps);
        $this->assertContains('submit_environment', $caps);
        $this->assertContains('submit_content', $caps);
        $this->assertCount(3, $caps);
    }

    // ── user ──────────────────────────────────────────────────────────────────

    /**
     * user: read_context + submit_environment + submit_content + read_profile.
     */
    public function testUserGrantsFourCapabilities(): void
    {
        $caps = $this->engine->resolve($this->makeContext('user'));

        $this->assertContains('read_context', $caps);
        $this->assertContains('submit_environment', $caps);
        $this->assertContains('submit_content', $caps);
        $this->assertContains('read_profile', $caps);
        $this->assertCount(4, $caps);
    }

    // ── authority ─────────────────────────────────────────────────────────────

    /**
     * authority: all six capabilities.
     */
    public function testAuthorityGrantsSixCapabilities(): void
    {
        $caps = $this->engine->resolve($this->makeContext('authority'));

        $this->assertContains('read_context', $caps);
        $this->assertContains('submit_environment', $caps);
        $this->assertContains('submit_content', $caps);
        $this->assertContains('read_profile', $caps);
        $this->assertContains('manage_context', $caps);
        $this->assertContains('resolve_authority', $caps);
        $this->assertCount(6, $caps);
    }

    /**
     * authority context returns the exact expected capability set in order.
     */
    public function testAuthorityContextGetsManagementCapabilities(): void
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
            $this->engine->resolve($this->makeContext('authority'))
        );
    }

    // ── capability set is a superset of the level below ───────────────────────

    /**
     * Each trust level's capabilities are a superset of the level below it.
     * This validates the additive permission model.
     */
    public function testCapabilitiesAreCumulativeAcrossLevels(): void
    {
        $anon        = $this->engine->resolve($this->makeContext('anonymous'));
        $device      = $this->engine->resolve($this->makeContext('device'));
        $contributor = $this->engine->resolve($this->makeContext('contributor'));
        $user        = $this->engine->resolve($this->makeContext('user'));
        $authority   = $this->engine->resolve($this->makeContext('authority'));

        foreach ($anon as $cap) {
            $this->assertContains($cap, $device);
        }
        foreach ($device as $cap) {
            $this->assertContains($cap, $contributor);
        }
        foreach ($contributor as $cap) {
            $this->assertContains($cap, $user);
        }
        foreach ($user as $cap) {
            $this->assertContains($cap, $authority);
        }
    }

    // ── resolve() always returns an array ────────────────────────────────────

    /**
     * resolve() must always return an array (never null, never non-array).
     */
    public function testResolveAlwaysReturnsArray(): void
    {
        foreach (['anonymous', 'device', 'contributor', 'user', 'authority'] as $level) {
            $result = $this->engine->resolve($this->makeContext($level));
            $this->assertIsArray($result, "Expected array for trust level '{$level}'.");
        }
    }

    // ── read_context is universal ─────────────────────────────────────────────

    /**
     * read_context must be present in every trust level's capability set.
     */
    public function testReadContextPresentForAllLevels(): void
    {
        foreach (['anonymous', 'device', 'contributor', 'user', 'authority'] as $level) {
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
            $engine->resolve($this->makeContext('user'))
        );
    }
}
