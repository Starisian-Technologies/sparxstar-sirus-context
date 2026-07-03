<?php

/**
 * Tests for AuthorityResolver – maps trust level + WP capabilities to authority type.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Infrastructure\DTOs\CredentialTier;
use Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive;
use Starisian\Sparxstar\Sirus\core\AuthorityResolver;
use Starisian\Sparxstar\Sirus\core\SirusContext;

final class AuthorityResolverTest extends SirusTestCase
{
    private AuthorityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['__current_user_id']  = 0;
        $GLOBALS['__current_user_can'] = [];
        $GLOBALS['__user_can_map']     = [];
        $this->resolver                = new AuthorityResolver();
    }

    // ── Resolution with capabilities ──────────────────────────────────────────

    public function testManageNetworkWinsForAuthorityContext(): void
    {
        $GLOBALS['__current_user_id']              = 99;
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
        $GLOBALS['__current_user_id']              = 42;
        $GLOBALS['__user_can_map'][42]['manage_options'] = true;

        $resolver = new AuthorityResolver();

        $this->assertSame(
            AuthorityResolver::STARISIAN,
            $resolver->resolve($this->makeContext('authority'))
        );
    }

    /**
     * Capabilities are irrelevant for non-authority trust levels.
     */
    public function testNonAuthorityTrustLevelReturnsNullEvenWithCapabilities(): void
    {
        $GLOBALS['__current_user_id']              = 42;
        $GLOBALS['__user_can_map'][42]['manage_network'] = true;

        $resolver = new AuthorityResolver();

        $this->assertNull($resolver->resolve($this->makeContext('user')));
    }

    // ── Non-authority trust levels always return null ─────────────────────────

    /**
     * anonymous trust level never resolves to an authority.
     */
    public function testAnonymousTrustLevelReturnsNull(): void
    {
        $this->assertNull($this->resolver->resolve($this->makeContext('anonymous')));
    }

    /**
     * device trust level never resolves to an authority.
     */
    public function testDeviceTrustLevelReturnsNull(): void
    {
        $this->assertNull($this->resolver->resolve($this->makeContext('device')));
    }

    /**
     * contributor trust level never resolves to an authority.
     */
    public function testContributorTrustLevelReturnsNull(): void
    {
        $this->assertNull($this->resolver->resolve($this->makeContext('contributor')));
    }

    /**
     * user trust level never resolves to an authority.
     */
    public function testUserTrustLevelReturnsNull(): void
    {
        $this->assertNull($this->resolver->resolve($this->makeContext('user')));
    }

    // ── authority trust level + user_id = 0 ──────────────────────────────────

    /**
     * Even with authority trust level, if get_current_user_id() returns 0
     * (no logged-in user), the resolver must return null.
     */
    public function testAuthorityTrustLevelWithNoLoggedInUserReturnsNull(): void
    {
        // __current_user_id is 0 from setUp — resolver must short-circuit.
        $this->assertNull($this->resolver->resolve($this->makeContext('authority')));
    }

    // ── Constant values are stable ────────────────────────────────────────────

    public function testSparxstarNetworkConstant(): void
    {
        $this->assertSame('sparxstar_network', AuthorityResolver::SPARXSTAR_NETWORK);
    }

    public function testStarisianConstant(): void
    {
        $this->assertSame('starisian', AuthorityResolver::STARISIAN);
    }

    public function testAiwaConstant(): void
    {
        $this->assertSame('aiwa', AuthorityResolver::AIWA);
    }

    public function testTribalAuthorityConstant(): void
    {
        $this->assertSame('tribal_authority', AuthorityResolver::TRIBAL_AUTHORITY);
    }

    public function testPartnerInstitutionConstant(): void
    {
        $this->assertSame('partner_institution', AuthorityResolver::PARTNER_INSTITUTION);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeContext(string $credentialTier): SirusContext
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
            credential_tier: CredentialTier::from($credentialTier),
            trust_level:    TrustLevelPrimitive::NORMAL,
            trust_score:    1.0,
            issued_at:      1_700_000_000,
            expires:        1_700_000_300,
        );
    }
}
