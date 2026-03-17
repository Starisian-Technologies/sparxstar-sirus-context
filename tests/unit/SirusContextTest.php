<?php

/**
 * Tests for the SirusContext Data Transfer Object.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\Sirus\core\SirusContext;

/**
 * Validates the SirusContext DTO behavior and portable payload generation.
 */
final class SirusContextTest extends TestCase
{
    /**
     * Returns a fully-constructed SirusContext for reuse in multiple tests.
     */
    private function makeContext(
        ?string $identity_id = null,
        ?string $authority_id = null,
        array $capabilities = [],
        string $trust_level = 'anonymous',
        array $role_set = [],
    ): SirusContext {
        return new SirusContext(
            context_id:     'ctx-1234',
            environment_id: 'env-abc',
            network_id:     '1',
            site_id:        '1',
            device_id:      'dev-uuid-5678',
            session_id:     'sess-9abc',
            identity_id:    $identity_id,
            authority_id:   $authority_id,
            role_set:       $role_set,
            capabilities:   $capabilities,
            trust_level:    $trust_level,
            issued_at:      1000,
            expires:        1300,
        );
    }

    /**
     * Confirms CONTEXT_VERSION is the expected integer.
     */
    public function testContextVersionConstantIsOne(): void
    {
        $this->assertSame(1, SirusContext::CONTEXT_VERSION);
    }

    /**
     * Validates that all constructor properties are accessible and correct.
     */
    public function testConstructorPropertyAccess(): void
    {
        $ctx = $this->makeContext(
            identity_id:  'user-42',
            authority_id: 'starisian',
            capabilities: ['read', 'write'],
            trust_level:  'user',
            role_set:     ['editor'],
        );

        $this->assertSame('ctx-1234', $ctx->context_id);
        $this->assertSame('env-abc', $ctx->environment_id);
        $this->assertSame('1', $ctx->network_id);
        $this->assertSame('1', $ctx->site_id);
        $this->assertSame('dev-uuid-5678', $ctx->device_id);
        $this->assertSame('sess-9abc', $ctx->session_id);
        $this->assertSame('user-42', $ctx->identity_id);
        $this->assertSame('starisian', $ctx->authority_id);
        $this->assertSame(['editor'], $ctx->role_set);
        $this->assertSame(['read', 'write'], $ctx->capabilities);
        $this->assertSame('user', $ctx->trust_level);
        $this->assertSame(1000, $ctx->issued_at);
        $this->assertSame(1300, $ctx->expires);
    }

    /**
     * hasCapability returns true for a matching capability string.
     */
    public function testHasCapabilityReturnsTrueForKnownCapability(): void
    {
        $ctx = $this->makeContext(capabilities: ['read', 'write', 'publish']);

        $this->assertTrue($ctx->hasCapability('read'));
        $this->assertTrue($ctx->hasCapability('publish'));
    }

    /**
     * hasCapability returns false for an absent capability string.
     */
    public function testHasCapabilityReturnsFalseForUnknownCapability(): void
    {
        $ctx = $this->makeContext(capabilities: ['read']);

        $this->assertFalse($ctx->hasCapability('delete'));
        $this->assertFalse($ctx->hasCapability(''));
    }

    /**
     * hasAuthority returns true when authority_id matches.
     */
    public function testHasAuthorityReturnsTrueForMatchingAuthority(): void
    {
        $ctx = $this->makeContext(authority_id: 'tribal_authority');

        $this->assertTrue($ctx->hasAuthority('tribal_authority'));
    }

    /**
     * hasAuthority returns false when authority_id does not match.
     */
    public function testHasAuthorityReturnsFalseForMismatch(): void
    {
        $ctx = $this->makeContext(authority_id: 'starisian');

        $this->assertFalse($ctx->hasAuthority('aiwa'));
        $this->assertFalse($ctx->hasAuthority(''));
    }

    /**
     * hasAuthority returns false when authority_id is null.
     */
    public function testHasAuthorityReturnsFalseWhenAuthorityIsNull(): void
    {
        $ctx = $this->makeContext(authority_id: null);

        $this->assertFalse($ctx->hasAuthority('starisian'));
    }

    /**
     * toPortablePayload includes all required keys and excludes identity_id.
     */
    public function testToPortablePayloadStructure(): void
    {
        $ctx     = $this->makeContext(
            identity_id:  'user-secret',
            authority_id: 'sparxstar_network',
            capabilities: ['view'],
        );
        $payload = $ctx->toPortablePayload();

        // Must include these keys.
        $this->assertArrayHasKey('ctxv', $payload);
        $this->assertArrayHasKey('ctx', $payload);
        $this->assertArrayHasKey('env', $payload);
        $this->assertArrayHasKey('net', $payload);
        $this->assertArrayHasKey('site', $payload);
        $this->assertArrayHasKey('dev', $payload);
        $this->assertArrayHasKey('auth', $payload);
        $this->assertArrayHasKey('caps', $payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);

        // identity_id must NOT appear.
        $this->assertArrayNotHasKey('identity_id', $payload);

        // Spot-check values.
        $this->assertSame(SirusContext::CONTEXT_VERSION, $payload['ctxv']);
        $this->assertSame('ctx-1234', $payload['ctx']);
        $this->assertSame('sparxstar_network', $payload['auth']);
        $this->assertSame(['view'], $payload['caps']);
        $this->assertSame(1000, $payload['iat']);
        $this->assertSame(1300, $payload['exp']);
    }

    /**
     * toPortablePayload works when authority_id is null.
     */
    public function testToPortablePayloadWithNullAuthority(): void
    {
        $ctx     = $this->makeContext(authority_id: null);
        $payload = $ctx->toPortablePayload();

        $this->assertNull($payload['auth']);
    }
}
