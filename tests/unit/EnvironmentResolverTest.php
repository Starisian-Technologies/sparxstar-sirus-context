<?php

/**
 * Tests for EnvironmentResolver – lightweight UA parsing and geo zone resolution.
 *
 * The Matomo DeviceDetector library is not available in the unit test environment,
 * so all tests exercise the built-in fallback heuristics (resolveWithFallback).
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\services\EnvironmentResolver;

/**
 * Unit tests for EnvironmentResolver UA parsing, geo zone derivation, and memoization.
 */
final class EnvironmentResolverTest extends SirusTestCase
{
    protected function setUp(): void
    {
        // Clear any previously set User-Agent and REMOTE_ADDR.
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
        // Reset apply_filters globals to avoid cross-test contamination.
        $GLOBALS['wp_options'] = [];
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function resolverWithUa(string $ua): EnvironmentResolver
    {
        $_SERVER['HTTP_USER_AGENT'] = $ua;
        return new EnvironmentResolver();
    }

    // ── Empty User-Agent ──────────────────────────────────────────────────────

    /**
     * An absent or empty UA string must return 'unknown' for all environment fields.
     */
    public function testEmptyUaReturnsAllUnknown(): void
    {
        $resolver = $this->resolverWithUa('');
        $result   = $resolver->resolve();

        $this->assertSame('unknown', $result['browser_name'], 'browser_name should be unknown for empty UA.');
        $this->assertSame('unknown', $result['os'], 'os should be unknown for empty UA.');
        $this->assertSame('unknown', $result['device_type'], 'device_type should be unknown for empty UA.');
        $this->assertSame('unknown', $result['network_effective_type'], 'network_effective_type should be unknown server-side.');
    }

    // ── Browser detection ─────────────────────────────────────────────────────

    /**
     * A Chrome User-Agent must be detected as 'Chrome'.
     */
    public function testChromeBrowserDetection(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $this->assertSame('Chrome', $this->resolverWithUa($ua)->getBrowserName());
    }

    /**
     * Firefox UA must be detected as 'Firefox'.
     */
    public function testFirefoxBrowserDetection(): void
    {
        $ua = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/119.0';
        $this->assertSame('Firefox', $this->resolverWithUa($ua)->getBrowserName());
    }

    /**
     * Microsoft Edge UA (Edg token) must be detected before Chrome.
     */
    public function testEdgeBrowserDetectedBeforeChrome(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
        $this->assertSame('Microsoft Edge', $this->resolverWithUa($ua)->getBrowserName());
    }

    /**
     * Opera UA (OPR token) must be detected before Chrome.
     */
    public function testOperaBrowserDetectedBeforeChrome(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0';
        $this->assertSame('Opera', $this->resolverWithUa($ua)->getBrowserName());
    }

    /**
     * Safari-only UA (no Chrome token) must be detected as 'Safari'.
     */
    public function testSafariDetection(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Safari/605.1.15';
        $this->assertSame('Safari', $this->resolverWithUa($ua)->getBrowserName());
    }

    // ── OS detection ──────────────────────────────────────────────────────────

    /**
     * Android UA must resolve to 'Android' OS.
     */
    public function testAndroidOsDetection(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36';
        $this->assertSame('Android', $this->resolverWithUa($ua)->getOs());
    }

    /**
     * iPhone UA must resolve to 'iOS' OS.
     */
    public function testIphoneOsDetection(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
        $this->assertSame('iOS', $this->resolverWithUa($ua)->getOs());
    }

    /**
     * Windows UA must resolve to 'Windows' OS.
     */
    public function testWindowsOsDetection(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
        $this->assertSame('Windows', $this->resolverWithUa($ua)->getOs());
    }

    /**
     * macOS UA must resolve to 'macOS' OS.
     */
    public function testMacOsDetection(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0) AppleWebKit/605.1.15 Safari/605.1.15';
        $this->assertSame('macOS', $this->resolverWithUa($ua)->getOs());
    }

    // ── Device type detection ─────────────────────────────────────────────────

    /**
     * iPhone UA must resolve to 'smartphone' device type.
     */
    public function testIphoneDeviceTypeIsSmartphone(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148';
        $this->assertSame('smartphone', $this->resolverWithUa($ua)->getDeviceType());
    }

    /**
     * Mobile token in UA must resolve to 'smartphone'.
     */
    public function testMobileTokenResolvesToSmartphone(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36';
        $this->assertSame('smartphone', $this->resolverWithUa($ua)->getDeviceType());
    }

    /**
     * iPad UA must resolve to 'tablet' device type.
     */
    public function testIpadDeviceTypeIsTablet(): void
    {
        $ua = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';
        $this->assertSame('tablet', $this->resolverWithUa($ua)->getDeviceType());
    }

    /**
     * Desktop Windows UA must resolve to 'desktop' device type.
     */
    public function testDesktopWindowsDeviceType(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
        $this->assertSame('desktop', $this->resolverWithUa($ua)->getDeviceType());
    }

    // ── Network effective type ────────────────────────────────────────────────

    /**
     * Server-side: network_effective_type must always be 'unknown' (overridden by
     * client-reported signal later via REST event).
     */
    public function testNetworkEffectiveTypeIsUnknownServerSide(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0';
        $this->assertSame('unknown', $this->resolverWithUa($ua)->getNetworkEffectiveType());
    }

    // ── Memoization ───────────────────────────────────────────────────────────

    /**
     * resolve() memoizes the result: calling it twice returns the identical array.
     */
    public function testResolveMemoizesResult(): void
    {
        $resolver = $this->resolverWithUa('Mozilla/5.0 Chrome/120.0.0.0');
        $first    = $resolver->resolve();
        $second   = $resolver->resolve();

        $this->assertSame($first, $second, 'resolve() must return the same memoized result on repeated calls.');
    }

    /**
     * Convenience getters (getBrowserName, getOs, getDeviceType, getNetworkEffectiveType)
     * must delegate to the memoized resolve() result.
     */
    public function testConvenienceGettersMatchResolveResult(): void
    {
        $ua       = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36';
        $resolver = $this->resolverWithUa($ua);
        $result   = $resolver->resolve();

        $this->assertSame($result['browser_name'], $resolver->getBrowserName());
        $this->assertSame($result['os'], $resolver->getOs());
        $this->assertSame($result['device_type'], $resolver->getDeviceType());
        $this->assertSame($result['network_effective_type'], $resolver->getNetworkEffectiveType());
    }

    // ── resolve() return shape ────────────────────────────────────────────────

    /**
     * resolve() must always return all four required keys.
     */
    public function testResolveAlwaysContainsAllRequiredKeys(): void
    {
        $resolver = $this->resolverWithUa('SomeUnknownAgent/1.0');
        $result   = $resolver->resolve();

        $this->assertArrayHasKey('browser_name', $result);
        $this->assertArrayHasKey('os', $result);
        $this->assertArrayHasKey('device_type', $result);
        $this->assertArrayHasKey('network_effective_type', $result);
    }

    // ── getGeoZone – no data ──────────────────────────────────────────────────

    /**
     * getGeoZone() returns 'unknown' when the geolocation filter returns null.
     *
     * The apply_filters shim returns the first argument unchanged,
     * so sparxstar_env_geolocation_lookup returns null (its first argument in the
     * production call is null). This simulates a disabled geo provider.
     */
    public function testGetGeoZoneReturnsUnknownWhenNoGeoData(): void
    {
        $resolver = $this->resolverWithUa('Mozilla/5.0 Chrome/120.0.0.0');

        $this->assertSame('unknown', $resolver->getGeoZone());
    }

    /**
     * getGeoZone() memoizes the result on repeated calls.
     */
    public function testGetGeoZoneMemoizes(): void
    {
        $resolver = $this->resolverWithUa('Mozilla/5.0 Chrome/120.0.0.0');

        $first  = $resolver->getGeoZone();
        $second = $resolver->getGeoZone();

        $this->assertSame($first, $second);
    }
}
