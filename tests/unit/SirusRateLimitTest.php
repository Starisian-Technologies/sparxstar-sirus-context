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
        $key    = 'sirus_rl_' . md5('device:' . $device);

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
        $key    = 'sirus_rl_' . md5('device:' . $device);

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
        $key    = 'sirus_rl_' . md5('device:' . $device);

        $this->limiter->allow($device); // call 1 → count=1
        $this->limiter->allow($device); // call 2 → count=2
        $this->limiter->allow($device); // call 3 → count=3

        $data = $GLOBALS['transients'][$key];
        $this->assertIsArray($data);
        $this->assertSame(3, $data['count']);
    }

    // ── allow: IP dimension ───────────────────────────────────────────────────

    /**
     * allow() returns false when the IP subnet is at the rate limit, even if the
     * device dimension is still under limit. Device counter must not be incremented.
     */
    public function testAllowReturnsFalseWhenIpAtLimit(): void
    {
        $device    = 'device-ip-blocked-abc123';
        $ip_subnet = '192.168.1.0';
        $ip_key    = 'sirus_rl_' . md5('ip:' . $ip_subnet);
        $dev_key   = 'sirus_rl_' . md5('device:' . $device);

        // Simulate IP subnet at the rate limit.
        $GLOBALS['transients'][$ip_key] = [
            'count'        => 200,
            'window_start' => time(),
        ];

        $result = $this->limiter->allow($device, $ip_subnet);
        $this->assertFalse($result);

        // Device counter must not have been incremented when IP blocked.
        $this->assertArrayNotHasKey($dev_key, $GLOBALS['transients']);
    }

    /**
     * allow() returns true for a new IP subnet alongside a new device.
     * Both dimension counters should be initialised to 1.
     */
    public function testAllowReturnsTrueForNewIpAndDevice(): void
    {
        $device    = 'device-ip-new-abc12345';
        $ip_subnet = '10.0.0.0';
        $dev_key   = 'sirus_rl_' . md5('device:' . $device);
        $ip_key    = 'sirus_rl_' . md5('ip:' . $ip_subnet);

        $result = $this->limiter->allow($device, $ip_subnet);
        $this->assertTrue($result);

        $this->assertSame(1, $GLOBALS['transients'][$dev_key]['count']);
        $this->assertSame(1, $GLOBALS['transients'][$ip_key]['count']);
    }
}
