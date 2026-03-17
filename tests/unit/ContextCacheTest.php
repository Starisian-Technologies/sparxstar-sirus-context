<?php

/**
 * Tests for ContextCache – the request-level in-memory SirusContext cache.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\Sirus\core\ContextCache;
use Starisian\Sparxstar\Sirus\core\SirusContext;

/**
 * Validates the static get/set/clear lifecycle of ContextCache.
 */
final class ContextCacheTest extends TestCase
{
    /**
     * Clear the cache before every test to guarantee isolation.
     */
    protected function setUp(): void
    {
        ContextCache::clear();
    }

    /**
     * Helper to build a minimal SirusContext.
     */
    private function makeContext(string $ctx_id = 'ctx-test'): SirusContext
    {
        return new SirusContext(
            context_id:     $ctx_id,
            environment_id: 'env-1',
            network_id:     '1',
            site_id:        '1',
            device_id:      'dev-1',
            session_id:     'sess-1',
            identity_id:    null,
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    'anonymous',
            issued_at:      time(),
            expires:        time() + 300,
        );
    }

    /**
     * A fresh cache returns null before anything is stored.
     */
    public function testGetReturnsNullInitially(): void
    {
        $this->assertNull(ContextCache::get());
    }

    /**
     * After set(), get() returns the same context instance.
     */
    public function testSetAndGetReturnSameInstance(): void
    {
        $ctx = $this->makeContext();
        ContextCache::set($ctx);

        $this->assertSame($ctx, ContextCache::get());
    }

    /**
     * After clear(), get() returns null again.
     */
    public function testClearResetsToNull(): void
    {
        $ctx = $this->makeContext();
        ContextCache::set($ctx);
        ContextCache::clear();

        $this->assertNull(ContextCache::get());
    }

    /**
     * Successive set() calls replace the stored context.
     */
    public function testSetReplacesPreviousContext(): void
    {
        $first  = $this->makeContext('ctx-first');
        $second = $this->makeContext('ctx-second');

        ContextCache::set($first);
        ContextCache::set($second);

        $retrieved = ContextCache::get();
        $this->assertNotNull($retrieved);
        $this->assertSame('ctx-second', $retrieved->context_id);
    }
}
