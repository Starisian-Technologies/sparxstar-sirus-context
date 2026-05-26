<?php

/**
 * Tests for AuthorityResolver – maps trust level + WP capabilities to authority type.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive;
use Starisian\Sparxstar\Sirus\core\AuthorityResolver;
use Starisian\Sparxstar\Sirus\core\SirusContext;

if (! function_exists('user_can')) {
    /**
     * Controllable shim for WordPress' user_can() helper.
     *
     * Individual tests populate $GLOBALS['__user_can_map'][$user_id][$cap] = true|false
     * to control which capabilities are granted.
     *
     * @param int|object $user      User ID or WP_User.
     * @param string     $cap       Capability to check.
     * @return bool
     */
    function user_can(int|object $user, string $cap): bool
    {
        $uid = is_object($user) ? $user->ID : $user;
        return (bool) ($GLOBALS['__user_can_map'][$uid][$cap] ?? false);
    }
}

/**
 * Unit tests for AuthorityResolver::resolve().
 */
final class AuthorityResolverTest extends SirusTestCase
{
    private AuthorityResolver $resolver;

    protected function setUp(): void
    {
        $GLOBALS['__user_can_map'] = [];
        $this->resolver            = new AuthorityResolver();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function makeContext(string $trust_level = 'anonymous'): SirusContext
    {
        return new SirusContext(
            context_id:     'ctx-auth-test',
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
            issued_at:      time(),
            expires:        time() + 300,
        );
    }

    // ── Non-authority trust levels always return null ─────────────────────────

    /**
     * anonymous trust level never resolves to an authority.
     */
    public function testAnonymousTrustLevelReturnsNull(): void
    {
        $context = $this->makeContext('anonymous');
        $this->assertNull($this->resolver->resolve($context));
    }

    /**
     * device trust level never resolves to an authority.
     */
    public function testDeviceTrustLevelReturnsNull(): void
    {
        $context = $this->makeContext('device');
        $this->assertNull($this->resolver->resolve($context));
    }

    /**
     * contributor trust level never resolves to an authority.
     */
    public function testContributorTrustLevelReturnsNull(): void
    {
        $context = $this->makeContext('contributor');
        $this->assertNull($this->resolver->resolve($context));
    }

    /**
     * user trust level never resolves to an authority.
     */
    public function testUserTrustLevelReturnsNull(): void
    {
        $context = $this->makeContext('user');
        $this->assertNull($this->resolver->resolve($context));
    }

    // ── authority trust level + user_id = 0 (bootstrap default) ─────────────

    /**
     * Even with authority trust level, if get_current_user_id() returns 0 (no logged-in
     * user), the resolver must return null. The bootstrap always returns 0.
     */
    public function testAuthorityTrustLevelWithNoLoggedInUserReturnsNull(): void
    {
        $context = $this->makeContext('authority');
        // get_current_user_id() is shimmed to return 0, so resolver short-circuits.
        $this->assertNull($this->resolver->resolve($context));
    }

    // ── Constant values are stable ────────────────────────────────────────────

    /**
     * SPARXSTAR_NETWORK constant has the expected string value.
     */
    public function testSparxstarNetworkConstant(): void
    {
        $this->assertSame('sparxstar_network', AuthorityResolver::SPARXSTAR_NETWORK);
    }

    /**
     * STARISIAN constant has the expected string value.
     */
    public function testStarisianConstant(): void
    {
        $this->assertSame('starisian', AuthorityResolver::STARISIAN);
    }

    /**
     * AIWA constant has the expected string value.
     */
    public function testAiwaConstant(): void
    {
        $this->assertSame('aiwa', AuthorityResolver::AIWA);
    }

    /**
     * TRIBAL_AUTHORITY constant has the expected string value.
     */
    public function testTribalAuthorityConstant(): void
    {
        $this->assertSame('tribal_authority', AuthorityResolver::TRIBAL_AUTHORITY);
    }

    /**
     * PARTNER_INSTITUTION constant has the expected string value.
     */
    public function testPartnerInstitutionConstant(): void
    {
        $this->assertSame('partner_institution', AuthorityResolver::PARTNER_INSTITUTION);
    }
}
