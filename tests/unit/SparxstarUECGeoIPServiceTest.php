<?php

/**
 * Tests for SparxstarUECGeoIPService – GeoIP lookup with privacy enforcement.
 *
 * Privacy rules (non-negotiable per spec §H):
 * - Output is limited to country + region only.
 * - City-level, postal, and precise coordinate data are stripped.
 * - Exact coordinates (lat/lng) are rounded to 1 decimal place.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\SparxstarUEC\services\SparxstarUECGeoIPService;

/**
 * Unit tests for SparxstarUECGeoIPService::lookup() and privacy sanitization.
 */
final class SparxstarUECGeoIPServiceTest extends SirusTestCase
{
    private SparxstarUECGeoIPService $service;

    protected function setUp(): void
    {
        $GLOBALS['wp_options']                 = [];
        $GLOBALS['transients']                 = [];
        $GLOBALS['__wp_remote_get_response']   = null;
        $this->service                         = new SparxstarUECGeoIPService();
    }

    protected function tearDown(): void
    {
        $GLOBALS['__wp_remote_get_response'] = null;
    }

    // ── Invalid IP ────────────────────────────────────────────────────────────

    /**
     * lookup() must return null for an invalid IP address.
     */
    public function testLookupReturnsNullForInvalidIp(): void
    {
        $result = $this->service->lookup('not-an-ip');
        $this->assertNull($result);
    }

    /**
     * lookup() must return null for an empty string.
     */
    public function testLookupReturnsNullForEmptyString(): void
    {
        $result = $this->service->lookup('');
        $this->assertNull($result);
    }

    // ── Provider = 'none' ─────────────────────────────────────────────────────

    /**
     * lookup() returns null when provider is 'none' (default).
     */
    public function testLookupReturnsNullWhenProviderIsNone(): void
    {
        // get_option() shim returns the second arg as default; 'none' is the default.
        $result = $this->service->lookup('1.2.3.4');
        $this->assertNull($result);
    }

    // ── Provider = 'ipinfo' – no API key ─────────────────────────────────────

    /**
     * lookup() returns null when provider is 'ipinfo' but no API key is configured.
     */
    public function testLookupReturnsNullForIpinfoWithNoApiKey(): void
    {
        // Set provider = ipinfo; leave API key empty (default).
        $blog_id = $GLOBALS['current_blog_id'] ?? 1;
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_geoip_provider'] = 'ipinfo';

        $result = $this->service->lookup('1.2.3.4');
        $this->assertNull($result);
    }

    // ── Provider = 'ipinfo' – API key configured, remote returns 503 ─────────

    /**
     * lookup() returns null when the remote ipinfo.io endpoint returns a non-200 status.
     */
    public function testLookupReturnsNullWhenIpinfoEndpointFails(): void
    {
        $blog_id = $GLOBALS['current_blog_id'] ?? 1;
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_geoip_provider']  = 'ipinfo';
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_ipinfo_api_key']  = 'test-key';
        // Default __wp_remote_get_response is null → shim returns 503.

        $result = $this->service->lookup('1.2.3.4');
        $this->assertNull($result);
    }

    // ── Provider = 'ipinfo' – successful response ─────────────────────────────

    /**
     * lookup() returns privacy-sanitized region-level data on a successful ipinfo response.
     */
    public function testLookupReturnsRegionLevelDataOnSuccessfulIpinfoResponse(): void
    {
        $blog_id = $GLOBALS['current_blog_id'] ?? 1;
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_geoip_provider'] = 'ipinfo';
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_ipinfo_api_key'] = 'test-api-key';

        $GLOBALS['__wp_remote_get_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'country' => 'US',
                'region'  => 'California',
                'loc'     => '37.38,-122.08',
            ]),
        ];

        $result = $this->service->lookup('8.8.8.8');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('country', $result);
        $this->assertArrayHasKey('region', $result);
        $this->assertSame('US', $result['country']);
        $this->assertSame('California', $result['region']);
    }

    /**
     * lookup() must strip city-level precision by rounding lat/lng to 1 decimal place.
     */
    public function testLookupRoundsCoordinatesToOneDecimalPlace(): void
    {
        $blog_id = $GLOBALS['current_blog_id'] ?? 1;
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_geoip_provider'] = 'ipinfo';
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_ipinfo_api_key'] = 'key';

        $GLOBALS['__wp_remote_get_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'country' => 'US',
                'region'  => 'California',
                'loc'     => '37.386,-122.083',
            ]),
        ];

        $result = $this->service->lookup('8.8.8.8');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('approx_lat', $result);
        $this->assertArrayHasKey('approx_lng', $result);
        // Latitude 37.386 rounds to 37.4 at 1 decimal place.
        $this->assertEqualsWithDelta(37.4, (float) $result['approx_lat'], 0.00001);
        // Longitude -122.083 rounds to -122.1 at 1 decimal place.
        $this->assertEqualsWithDelta(-122.1, (float) $result['approx_lng'], 0.00001);
    }

    // ── Caching ───────────────────────────────────────────────────────────────

    /**
     * A successful lookup() result must be stored as a transient.
     */
    public function testLookupCachesSuccessfulResult(): void
    {
        $blog_id = $GLOBALS['current_blog_id'] ?? 1;
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_geoip_provider'] = 'ipinfo';
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_ipinfo_api_key'] = 'key';

        $GLOBALS['__wp_remote_get_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode(['country' => 'CA', 'region' => 'Ontario']),
        ];

        $result = $this->service->lookup('1.2.3.4');
        $this->assertIsArray($result);

        $transient_key = 'sparxstar_geoip_' . md5('1.2.3.4');
        $cached        = $GLOBALS['transients'][$transient_key] ?? null;
        $this->assertIsArray($cached, 'Successful lookup result must be cached as a transient.');
    }

    /**
     * A second lookup() call for the same IP must return the cached transient value.
     */
    public function testLookupReturnsCachedTransientOnSecondCall(): void
    {
        $blog_id = $GLOBALS['current_blog_id'] ?? 1;
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_geoip_provider'] = 'ipinfo';
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_ipinfo_api_key'] = 'key';

        // Seed the transient cache directly.
        $transient_key                         = 'sparxstar_geoip_' . md5('5.6.7.8');
        $GLOBALS['transients'][$transient_key] = ['country' => 'DE', 'region' => 'Bavaria'];

        // Even with a failing remote, the cached value should be returned.
        $result = $this->service->lookup('5.6.7.8');
        $this->assertIsArray($result);
        $this->assertSame('DE', $result['country']);
    }

    // ── Provider = 'maxmind' – no DB path ────────────────────────────────────

    /**
     * lookup() returns null when provider is 'maxmind' but no DB path is configured.
     */
    public function testLookupReturnsNullForMaxmindWithNoDbPath(): void
    {
        $blog_id = $GLOBALS['current_blog_id'] ?? 1;
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_geoip_provider']    = 'maxmind';
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_maxmind_db_path']   = '';

        $result = $this->service->lookup('1.2.3.4');
        $this->assertNull($result);
    }

    /**
     * lookup() returns null when provider is 'maxmind' and the DB file does not exist.
     */
    public function testLookupReturnsNullForMaxmindWithMissingDbFile(): void
    {
        $blog_id = $GLOBALS['current_blog_id'] ?? 1;
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_geoip_provider']    = 'maxmind';
        $GLOBALS['wp_options'][$blog_id]['sparxstar_uec_maxmind_db_path']   = '/nonexistent/path/GeoLite2-City.mmdb';

        $result = $this->service->lookup('1.2.3.4');
        $this->assertNull($result);
    }
}
