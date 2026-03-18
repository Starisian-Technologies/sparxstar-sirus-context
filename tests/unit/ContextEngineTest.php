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

    // ── buildFromDevice() ─────────────────────────────────────────────────────

    /**
     * buildFromDevice() returns a SirusContext using the supplied device_id.
     */
    public function testBuildFromDeviceUsesDeviceId(): void
    {
        $record = new \Starisian\Sparxstar\Sirus\core\DeviceRecord(
            device_id:        'fixed-device-uuid',
            device_secret:    'aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899',
            fingerprint_hash: 'abc123',
            environment_json: '{}',
            first_seen:       time() - 10,
            last_seen:        time(),
            trust_level:      'device',
        );

        $ctx = ContextEngine::buildFromDevice($record);

        $this->assertSame('fixed-device-uuid', $ctx->device_id);
    }

    /**
     * buildFromDevice() primes the ContextCache so current() returns the same instance.
     */
    public function testBuildFromDevicePrimesContextCache(): void
    {
        $record = new \Starisian\Sparxstar\Sirus\core\DeviceRecord(
            device_id:        'primed-device-uuid',
            device_secret:    'aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899',
            fingerprint_hash: 'def456',
            environment_json: '{}',
            first_seen:       time() - 10,
            last_seen:        time(),
            trust_level:      'anonymous',
        );

        $built   = ContextEngine::buildFromDevice($record);
        $current = ContextEngine::current();

        $this->assertSame($built, $current);
    }

    /**
     * buildFromDevice() seeds trust_level from the DeviceRecord.
     */
    public function testBuildFromDeviceUsesTrustLevelFromRecord(): void
    {
        $record = new \Starisian\Sparxstar\Sirus\core\DeviceRecord(
            device_id:        'trusted-device-uuid',
            device_secret:    'aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899',
            fingerprint_hash: 'ghi789',
            environment_json: '{}',
            first_seen:       time() - 10,
            last_seen:        time(),
            trust_level:      'contributor',
        );

        $ctx = ContextEngine::buildFromDevice($record);

        $this->assertSame('contributor', $ctx->trust_level);
    }

    // ── current() expiry eviction ─────────────────────────────────────────────

    /**
     * current() rebuilds the context when the cached one is expired.
     */
    public function testCurrentEvictsExpiredCacheAndRebuilds(): void
    {
        // Manually place an expired context into the cache.
        $expired = new SirusContext(
            context_id:     'old-ctx',
            environment_id: 'env',
            network_id:     '1',
            site_id:        '1',
            device_id:      'old-dev',
            session_id:     'old-sess',
            identity_id:    null,
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    'anonymous',
            issued_at:      1000,
            expires:        1001, // already expired
        );

        ContextCache::set($expired);

        $fresh = ContextEngine::current();

        // Must have returned a different (freshly built) context.
        $this->assertNotSame($expired, $fresh);
        // The fresh context must not be expired.
        $this->assertFalse($fresh->isExpired());
    }
}
