<?php

/**
 * Tests for AuthorityResolver.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive;
use Starisian\Sparxstar\Sirus\core\AuthorityResolver;
use Starisian\Sparxstar\Sirus\core\SirusContext;

final class AuthorityResolverTest extends SirusTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['__current_user_id'] = 0;
        $GLOBALS['__current_user_can'] = [];
        $GLOBALS['__user_can_map'] = [];
    }

    public function testManageNetworkWinsForAuthorityContext(): void
    {
        $GLOBALS['__current_user_id'] = 99;
        $GLOBALS['__user_can_map'][99]['manage_network'] = true;
        $GLOBALS['__user_can_map'][99]['manage_options'] = true;

        $resolver = new AuthorityResolver();

        $this->assertSame(
            AuthorityResolver::SPARXSTAR_NETWORK,
            $resolver->resolve($this->makeContext('authority'))
        );
    }

    public function testManageOptionsFallsBackToStarisian(): void
    {
        $GLOBALS['__current_user_id'] = 42;
        $GLOBALS['__user_can_map'][42]['manage_options'] = true;

        $resolver = new AuthorityResolver();

        $this->assertSame(
            AuthorityResolver::STARISIAN,
            $resolver->resolve($this->makeContext('authority'))
        );
    }

    public function testNonAuthorityTrustLevelReturnsNull(): void
    {
        $GLOBALS['__current_user_id'] = 42;
        $GLOBALS['__user_can_map'][42]['manage_network'] = true;

        $resolver = new AuthorityResolver();

        $this->assertNull($resolver->resolve($this->makeContext('user')));
    }

    private function makeContext(string $trustLevel): SirusContext
    {
        return new SirusContext(
            context_id:     'ctx-auth',
            environment_id: 'env-auth',
            network_id:     '1',
            site_id:        '1',
            device_id:      'dev-auth',
            session_id:     'sess-auth',
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
