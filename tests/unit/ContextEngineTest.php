<?php

/**
 * Tests for ContextEngine – the main Sirus context builder.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\Sirus\core\ContextCache;
use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\core\SirusContext;

/**
 * Validates the context building and caching behavior of ContextEngine.
 */
final class ContextEngineTest extends TestCase
{
    /**
     * Clears both the ContextCache and the device cookie before every test.
     */
    protected function setUp(): void
    {
        ContextCache::clear();
        // Ensure no stale cookie bleeds across tests.
        unset($_COOKIE['spx_device_id']);
    }

    /**
     * current() returns a SirusContext instance.
     */
    public function testCurrentReturnsSirusContext(): void
    {
        $ctx = ContextEngine::current();

        $this->assertInstanceOf(SirusContext::class, $ctx);
    }

    /**
     * current() returns the same instance on subsequent calls (caching).
     */
    public function testCurrentReturnsCachedInstance(): void
    {
        $first  = ContextEngine::current();
        $second = ContextEngine::current();

        $this->assertSame($first, $second);
    }

    /**
     * After clearing the cache, current() returns a new (different) instance.
     */
    public function testCurrentRebuildsAfterCacheClear(): void
    {
        $first = ContextEngine::current();
        ContextCache::clear();
        $second = ContextEngine::current();

        $this->assertNotSame($first, $second);
    }

    /**
     * A built context always has non-empty context_id, device_id and session_id.
     */
    public function testBuiltContextHasRequiredIdentifiers(): void
    {
        $ctx = ContextEngine::build();

        $this->assertNotEmpty($ctx->context_id);
        $this->assertNotEmpty($ctx->device_id);
        $this->assertNotEmpty($ctx->session_id);
    }

    /**
     * A freshly built context has trust_level 'anonymous' and no identity or authority.
     */
    public function testBuiltContextDefaultsToAnonymous(): void
    {
        $ctx = ContextEngine::build();

        $this->assertSame('anonymous', $ctx->trust_level);
        $this->assertNull($ctx->identity_id);
        $this->assertNull($ctx->authority_id);
    }

    /**
     * context_id must be a UUID v4 string.
     */
    public function testBuiltContextIdIsUuidV4Format(): void
    {
        $ctx   = ContextEngine::build();
        $regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $this->assertTrue((bool) preg_match($regex, $ctx->context_id));
    }

    /**
     * A valid UUID v4 cookie is used as the device_id.
     */
    public function testValidDeviceCookieIsUsedAsDeviceId(): void
    {
        $valid_uuid                     = 'a1b2c3d4-e5f6-4789-8abc-def012345678';
        $_COOKIE['spx_device_id']       = $valid_uuid;

        ContextCache::clear();
        $ctx = ContextEngine::build();

        $this->assertSame($valid_uuid, $ctx->device_id);
    }

    /**
     * An invalid (non-UUID v4) cookie is replaced with a freshly generated UUID.
     */
    public function testInvalidDeviceCookieIsIgnored(): void
    {
        $_COOKIE['spx_device_id'] = 'not-a-valid-uuid';

        ContextCache::clear();
        $ctx   = ContextEngine::build();
        $regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $this->assertTrue((bool) preg_match($regex, $ctx->device_id));
        $this->assertNotSame('not-a-valid-uuid', $ctx->device_id);

        unset($_COOKIE['spx_device_id']);
    }

    /**
     * expires is set in the future relative to issued_at.
     */
    public function testExpiresIsAfterIssuedAt(): void
    {
        $ctx = ContextEngine::build();

        $this->assertGreaterThan($ctx->issued_at, $ctx->expires);
    }

    /**
     * site_id is always a numeric string (current blog ID).
     */
    public function testSiteIdIsNumericString(): void
    {
        $ctx = ContextEngine::build();

        $this->assertIsNumeric($ctx->site_id);
    }
}
