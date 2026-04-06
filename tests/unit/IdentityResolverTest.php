<?php

/**
 * Tests for IdentityResolver — identity context resolution and schema normalization.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\IdentityResolver;
use Starisian\Sparxstar\Sirus\core\SirusContext;
use Starisian\Sparxstar\Sirus\integrations\HeliosClientInterface;

/**
 * Validates IdentityResolver's schema normalization and fallback behaviour.
 */
final class IdentityResolverTest extends SirusTestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns a minimal SirusContext suitable for driving resolve(). */
    private function makeContext(
        string $device_id  = 'dev-1',
        string $session_id = 'sess-1',
        ?string $identity_id = null
    ): SirusContext {
        return new SirusContext(
            context_id:     'ctx-1',
            environment_id: 'env-1',
            network_id:     '1',
            site_id:        '1',
            device_id:      $device_id,
            session_id:     $session_id,
            identity_id:    $identity_id,
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    'anonymous',
            issued_at:      time(),
            expires:        0,
        );
    }

    /**
     * Builds a fake HeliosClientInterface that returns $payload from getIdentityContext().
     *
     * @param array<string, mixed>|null $payload
     */
    private function makeHelios(?array $payload): HeliosClientInterface
    {
        return new class($payload) implements HeliosClientInterface {
            public function __construct(private readonly ?array $data) {}

            public function resolve(
                string $device_id,
                string $session_id,
                ?string $identity_claim = null
            ): ?array {
                return $this->data;
            }

            public function getIdentityContext(
                string $device_id,
                string $session_id,
                ?string $identity_claim = null
            ): ?array {
                return $this->data;
            }
        };
    }

    // ── Fallback behaviour ────────────────────────────────────────────────────

    /**
     * When no Helios client is provided, the fallback identity shape is returned.
     */
    public function testNoHeliosClientReturnsFallback(): void
    {
        $resolver = new IdentityResolver(null);
        $result   = $resolver->resolve($this->makeContext());

        $this->assertSame([
            'identity_id'           => null,
            'verification_status'   => 'none',
            'authority_memberships' => [],
            'capabilities'          => [],
        ], $result);
    }

    /**
     * When Helios returns null, the fallback identity shape is returned.
     */
    public function testHeliosReturnsNullFallsBackToDefault(): void
    {
        $resolver = new IdentityResolver($this->makeHelios(null));
        $result   = $resolver->resolve($this->makeContext());

        $this->assertSame([
            'identity_id'           => null,
            'verification_status'   => 'none',
            'authority_memberships' => [],
            'capabilities'          => [],
        ], $result);
    }

    // ── Schema normalization ──────────────────────────────────────────────────

    /**
     * When Helios returns a valid payload, all expected fields are preserved.
     */
    public function testValidHeliosPayloadIsNormalized(): void
    {
        $helios = $this->makeHelios([
            'identity_id'           => 'uid-123',
            'verification_status'   => 'verified',
            'authority_memberships' => ['org-a', 'org-b'],
            'capabilities'          => ['read', 'write'],
        ]);

        $result = (new IdentityResolver($helios))->resolve($this->makeContext());

        $this->assertSame('uid-123', $result['identity_id']);
        $this->assertSame('verified', $result['verification_status']);
        $this->assertSame(['org-a', 'org-b'], $result['authority_memberships']);
        $this->assertSame(['read', 'write'], $result['capabilities']);
    }

    /**
     * identity_id may be null (guest identity is valid).
     */
    public function testNullIdentityIdIsPreserved(): void
    {
        $helios = $this->makeHelios([
            'identity_id'           => null,
            'verification_status'   => 'none',
            'authority_memberships' => [],
            'capabilities'          => [],
        ]);

        $result = (new IdentityResolver($helios))->resolve($this->makeContext());

        $this->assertNull($result['identity_id']);
    }

    /**
     * Non-string elements in authority_memberships are dropped (not coerced).
     * The schema contract requires array<int, string>.
     */
    public function testNonStringAuthorityMembershipsAreDropped(): void
    {
        $helios = $this->makeHelios([
            'identity_id'           => 'uid-1',
            'verification_status'   => 'verified',
            'authority_memberships' => ['valid-org', 42, null, true, 'another-org'],
            'capabilities'          => [],
        ]);

        $result = (new IdentityResolver($helios))->resolve($this->makeContext());

        // Only string values survive; the integers, null, and booleans are dropped.
        $this->assertSame(['valid-org', 'another-org'], $result['authority_memberships']);
    }

    /**
     * Non-string elements in capabilities are dropped.
     */
    public function testNonStringCapabilitiesAreDropped(): void
    {
        $helios = $this->makeHelios([
            'identity_id'           => 'uid-1',
            'verification_status'   => 'verified',
            'authority_memberships' => [],
            'capabilities'          => ['read', 99, false, 'write'],
        ]);

        $result = (new IdentityResolver($helios))->resolve($this->makeContext());

        $this->assertSame(['read', 'write'], $result['capabilities']);
    }

    /**
     * trust_level from Helios is stripped — it must not appear in the normalized output.
     * Sirus does not expose trust level; that is Helios' internal domain.
     */
    public function testTrustLevelIsStrippedFromHeliosPayload(): void
    {
        $helios = $this->makeHelios([
            'identity_id'           => 'uid-1',
            'trust_level'           => 'elevated',
            'verification_status'   => 'verified',
            'authority_memberships' => [],
            'capabilities'          => [],
        ]);

        $result = (new IdentityResolver($helios))->resolve($this->makeContext());

        $this->assertArrayNotHasKey('trust_level', $result);
    }

    /**
     * Missing optional fields from Helios fall back to the fallback defaults.
     */
    public function testPartialHeliosPayloadFallsBackOnMissingFields(): void
    {
        // Helios returns only identity_id; everything else is missing.
        $helios = $this->makeHelios([
            'identity_id' => 'uid-partial',
        ]);

        $result = (new IdentityResolver($helios))->resolve($this->makeContext());

        $this->assertSame('uid-partial', $result['identity_id']);
        $this->assertSame('none', $result['verification_status']);
        $this->assertSame([], $result['authority_memberships']);
        $this->assertSame([], $result['capabilities']);
    }

    /**
     * Output keys are always exactly the four documented keys — no extras.
     */
    public function testOutputHasExactlyFourKeys(): void
    {
        $helios = $this->makeHelios([
            'identity_id'           => 'uid-1',
            'verification_status'   => 'verified',
            'authority_memberships' => [],
            'capabilities'          => [],
            'extra_key'             => 'should_not_appear',
        ]);

        $result = (new IdentityResolver($helios))->resolve($this->makeContext());

        $this->assertSame(
            ['identity_id', 'verification_status', 'authority_memberships', 'capabilities'],
            array_keys($result)
        );
    }
}
