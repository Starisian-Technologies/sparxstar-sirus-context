<?php

/**
 * Tests for EnvironmentResolver – client-first resolution with UA fallback heuristics.
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
        parent::setUp();

        // Clear any previously set User-Agent and REMOTE_ADDR.
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR']);
        // Reset filter globals to avoid cross-test contamination.
        $GLOBALS['registered_filters'] = [];
        $GLOBALS['wp_options']         = [];
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

    // ── Client signals take precedence ────────────────────────────────────────

    public function testClientSignalsTakePrecedenceOverUaFallback(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';
        $_SERVER['REMOTE_ADDR']     = '198.51.100.42';

        $resolver = new EnvironmentResolver();
        $record   = $resolver->resolve(
            [
                'browser_name'           => 'Firefox',
                'browser_version'        => '126.0',
                'os'                     => 'Android',
                'os_version'             => '15',
                'device_type'            => 'smartphone',
                'device_brand'           => 'Samsung',
                'device_model'           => 'S24',
                'network_effective_type' => '3g',
                'timezone'               => 'Africa/Accra',
            ]
        );

        $this->assertSame('Firefox', $record->browser_name);
        $this->assertSame('126.0', $record->browser_version);
        $this->assertSame('Android', $record->os);
        $this->assertSame('15', $record->os_version);
        $this->assertSame('smartphone', $record->device_type);
        $this->assertSame('Samsung', $record->device_brand);
        $this->assertSame('S24', $record->device_model);
        $this->assertSame('3g', $record->network_effective_type);
        $this->assertSame('Africa/Accra', $record->time_zone);
    }

    public function testMissingClientSignalsAreFilledWithoutDependingOnOptionalMatomo(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

        $resolver = new EnvironmentResolver();
        $record   = $resolver->resolve(
            [
                'browser_name' => 'Firefox',
            ]
        );

        $this->assertSame('Firefox', $record->browser_name);
        $this->assertNotSame('', $record->os);
        $this->assertNotSame('unknown', $record->os);
        $this->assertNotSame('', $record->device_type);
        $this->assertNotSame('unknown', $record->device_type);
    }

    public function testNetworkTypeFallsBackToFilterWhenClientSignalMissing(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/123.0.0.0';
        $_SERVER['REMOTE_ADDR']     = '198.51.100.42';

        add_filter(
            'sparxstar_env_network_effective_type',
            static fn (string $type): string => $type === 'unknown' ? 'wifi' : $type
        );

        $resolver = new EnvironmentResolver();
        $record   = $resolver->resolve();

        $this->assertSame('wifi', $record->network_effective_type);
    }

    public function testGeolocationFilterProducesRegionLevelLocation(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/123.0.0.0';
        $_SERVER['REMOTE_ADDR']     = '198.51.100.42';

        add_filter(
            'sparxstar_env_geolocation_lookup',
            static function ($value, string $ip): array {
                unset($value);

                return [
                    'country'    => 'GH',
                    'region'     => 'Greater Accra',
                    'approx_lat' => 5.6037,
                    'approx_lng' => -0.1870,
                    'ip'         => $ip,
                    'lat'        => 5.60371234,
                    'lng'        => -0.18701234,
                ];
            },
            10,
            2
        );

        $resolver = new EnvironmentResolver();
        $record   = $resolver->resolve();

        $this->assertSame(
            [
                'country'    => 'GH',
                'region'     => 'Greater Accra',
                'approx_lat' => 5.6,
                'approx_lng' => -0.19,
            ],
            $record->location
        );
        $this->assertSame('gh_greater_accra', $resolver->getGeoZone());
    }

    // ── Empty User-Agent ──────────────────────────────────────────────────────

    /**
     * An absent or empty UA string must return 'unknown' for all environment fields.
     */
    public function testEmptyUaReturnsAllUnknown(): void
    {
        $resolver = $this->resolverWithUa('');
        $record   = $resolver->resolve();

        $this->assertSame('unknown', $record->browser_name, 'browser_name should be unknown for empty UA.');
        $this->assertSame('unknown', $record->os, 'os should be unknown for empty UA.');
        $this->assertSame('unknown', $record->device_type, 'device_type should be unknown for empty UA.');
        // In the PHPUnit CLI runtime, resolveNetworkType() returns 'cli' (not 'unknown').
        $this->assertSame('cli', $record->network_effective_type, 'network_effective_type is \'cli\' in CLI (PHPUnit) runtime.');
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
     * In the PHPUnit runtime, EnvironmentResolver executes in CLI mode, so the
     * network effective type resolves to 'cli' rather than a browser-derived value.
     */
    public function testNetworkEffectiveTypeIsCliInCliRuntime(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0';
        $this->assertSame('cli', $this->resolverWithUa($ua)->getNetworkEffectiveType());
    }

    // ── Memoization ───────────────────────────────────────────────────────────

    /**
     * resolve() memoizes the result: calling it twice returns the same object.
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
        $record   = $resolver->resolve();

        $this->assertSame($record->browser_name, $resolver->getBrowserName());
        $this->assertSame($record->os, $resolver->getOs());
        $this->assertSame($record->device_type, $resolver->getDeviceType());
        $this->assertSame($record->network_effective_type, $resolver->getNetworkEffectiveType());
    }

    // ── resolve() return shape ────────────────────────────────────────────────

    /**
     * resolve() must always return all required fields.
     */
    public function testResolveAlwaysContainsAllRequiredFields(): void
    {
        $resolver = $this->resolverWithUa('SomeUnknownAgent/1.0');
        $record   = $resolver->resolve();

        $this->assertNotEmpty($record->browser_name);
        $this->assertNotEmpty($record->os);
        $this->assertNotEmpty($record->device_type);
        $this->assertNotEmpty($record->network_effective_type);
    }

    // ── getGeoZone – no data ──────────────────────────────────────────────────

    /**
     * getGeoZone() returns 'unknown' when the geolocation filter returns null.
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
