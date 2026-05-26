<?php

/**
 * Tests for CapabilityEngine.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive;
use Starisian\Sparxstar\Sirus\core\CapabilityEngine;
use Starisian\Sparxstar\Sirus\core\SirusContext;

final class CapabilityEngineTest extends SirusTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['registered_filters'] = [];
    }

    public function testAnonymousContextGetsMinimalCapabilities(): void
    {
        $engine = new CapabilityEngine();

        $this->assertSame(
            [ 'read_context' ],
            $engine->resolve($this->makeContext('anonymous'))
        );
    }

    public function testAuthorityContextGetsManagementCapabilities(): void
    {
        $engine = new CapabilityEngine();

        $this->assertSame(
            [
                'read_context',
                'submit_environment',
                'submit_content',
                'read_profile',
                'manage_context',
                'resolve_authority',
            ],
            $engine->resolve($this->makeContext('authority'))
        );
    }

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

    private function makeContext(string $trustLevel): SirusContext
    {
        return new SirusContext(
            context_id:     'ctx-cap',
            environment_id: 'env-cap',
            network_id:     '1',
            site_id:        '1',
            device_id:      'dev-cap',
            session_id:     'sess-cap',
            identity_id:    null,
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    TrustLevelPrimitive::from($trustLevel),
            trust_score:    1.0,
            issued_at:      1_700_000_000,
            expires:        1_700_000_300,
        );
    }
}
