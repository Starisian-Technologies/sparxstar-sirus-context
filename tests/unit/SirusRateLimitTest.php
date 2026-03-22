<?php

/**
 * Tests for SirusRateLimit – transient-based fixed-window rate limiter.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\helpers\SirusRateLimit;

/**
 * Validates SirusRateLimit allow() behaviour.
 */
final class SirusRateLimitTest extends SirusTestCase
{
    private SirusRateLimit $limiter;

    protected function setUp(): void
    {
        // Reset transient store before each test.
        $GLOBALS['transients'] = [];
        $this->limiter = new SirusRateLimit();
    }

    // ── allow: new device ─────────────────────────────────────────────────────

    /**
     * A brand-new device_id should always be allowed (first event in window).
     */
    public function testAllowReturnsTrueForNewDevice(): void
    {
        $result = $this->limiter->allow('device-new-abc123');
        $this->assertTrue($result);
    }

    // ── allow: under the limit ────────────────────────────────────────────────

    /**
     * Calls under the 200-event cap should all return true.
     */
    public function testAllowReturnsTrueUnderLimit(): void
    {
        $device = 'device-under-limit-1234';

        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($this->limiter->allow($device), "Expected allow on call #{$i}.");
        }
    }

    // ── allow: limit exceeded ─────────────────────────────────────────────────

    /**
     * Once the counter reaches 200 within a window, allow() returns false.
     */
    public function testAllowReturnsFalseOnceLimitExceeded(): void
    {
        $device = 'device-limit-exceeded-xyz';
        $key    = 'sirus_rl_' . md5($device);

        // Pre-seed the transient to simulate 200 events already in the window.
        $GLOBALS['transients'][$key] = [
            'count'        => 200,
            'window_start' => time(),
        ];

        $result = $this->limiter->allow($device);
        $this->assertFalse($result);
    }

    // ── allow: window reset ───────────────────────────────────────────────────

    /**
     * After the 1-hour window expires, allow() should reset the counter and return true.
     */
    public function testAllowReturnsTrueAfterWindowExpires(): void
    {
        $device = 'device-window-reset-abc';
        $key    = 'sirus_rl_' . md5($device);

        // Pre-seed a transient with an old window_start (2 hours ago) and a full count.
        $GLOBALS['transients'][$key] = [
            'count'        => 200,
            'window_start' => time() - 7200, // 2 hours ago — window has expired.
        ];

        $result = $this->limiter->allow($device);
        $this->assertTrue($result);

        // The transient should now have count=1 and a fresh window_start.
        $data = $GLOBALS['transients'][$key];
        $this->assertIsArray($data);
        $this->assertSame(1, $data['count']);
        $this->assertGreaterThan(time() - 5, $data['window_start']);
    }

    // ── allow: increments counter ─────────────────────────────────────────────

    /**
     * Each allowed call should increment the stored counter by 1.
     */
    public function testAllowIncrementsCounter(): void
    {
        $device = 'device-counter-abc12345';
        $key    = 'sirus_rl_' . md5($device);

        $this->limiter->allow($device); // call 1 → count=1
        $this->limiter->allow($device); // call 2 → count=2
        $this->limiter->allow($device); // call 3 → count=3

        $data = $GLOBALS['transients'][$key];
        $this->assertIsArray($data);
        $this->assertSame(3, $data['count']);
    }
}
