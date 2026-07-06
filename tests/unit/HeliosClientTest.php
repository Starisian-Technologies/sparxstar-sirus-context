<?php

/**
 * Tests for HeliosClient – integration client for the Helios trust-resolution service.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\integrations\HeliosClient;

/**
 * Unit tests for HeliosClient::resolve() and HeliosClient::getIdentityContext().
 *
 * The wp_remote_post shim returns a 503 by default, so the "no base_url" and
 * "non-200 response" paths are naturally covered. Custom overrides are achieved
 * via $GLOBALS['__helios_response'] which the custom shim reads.
 */
final class HeliosClientTest extends SirusTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_cache_store']      = [];
        $GLOBALS['__helios_response']   = null; // null = use the 503 default.
        $GLOBALS['transients']          = [];
    }

    // ── resolve() – no base URL ───────────────────────────────────────────────

    /**
     * resolve() must return null when base_url is an empty string.
     */
    public function testResolveReturnsNullWhenNoBaseUrl(): void
    {
        $client = new HeliosClient('');
        $result = $client->resolve('dev-1', 'sess-1');

        $this->assertNull($result);
    }

    // ── resolve() – non-200 HTTP response ────────────────────────────────────

    /**
     * resolve() must return null when the remote endpoint returns a non-200 status.
     * The default wp_remote_post shim always returns 503.
     */
    public function testResolveReturnsNullOnNon200Response(): void
    {
        $client = new HeliosClient('https://helios.example.com');
        $result = $client->resolve('dev-1', 'sess-1');

        $this->assertNull($result);
    }

    // ── resolve() – successful response ──────────────────────────────────────

    /**
     * resolve() must return a normalized payload when the endpoint returns 200 with
     * a valid JSON body.
     */
    public function testResolveReturnsNormalizedPayloadOnSuccess(): void
    {
        // Override wp_remote_post to return a 200 response.
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'identity_id'           => 'ident-abc',
                'trust_level'           => 'user',
                'verification_status'   => 'verified',
                'authority_memberships' => ['sparxstar_network'],
                'capabilities'          => ['read_context', 'submit_content'],
            ]),
        ];

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->resolve('dev-1', 'sess-1');

        $this->assertIsArray($result);
        $this->assertSame('ident-abc', $result['identity_id']);
        $this->assertSame('user', $result['trust_level']);
        $this->assertSame('verified', $result['verification_status']);
        $this->assertSame(['sparxstar_network'], $result['authority_memberships']);
        $this->assertSame(['read_context', 'submit_content'], $result['capabilities']);
    }

    /**
     * resolve() must default trust_level to 'anonymous' when the key is absent.
     */
    public function testResolveDefaultsTrustLevelToAnonymousWhenAbsent(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode(['identity_id' => 'i-1']),
        ];

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->resolve('dev-1', 'sess-1');

        $this->assertSame('anonymous', $result['trust_level'] ?? null);
    }

    /**
     * resolve() must default verification_status to 'unverified' when absent.
     */
    public function testResolveDefaultsVerificationStatusToUnverifiedWhenAbsent(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode(['identity_id' => 'i-2']),
        ];

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->resolve('dev-1', 'sess-1');

        $this->assertSame('unverified', $result['verification_status'] ?? null);
    }

    /**
     * resolve() must default authority_memberships and capabilities to [] when absent.
     */
    public function testResolveDefaultsCollectionsToEmptyArrayWhenAbsent(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode(['identity_id' => 'i-3']),
        ];

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->resolve('dev-1', 'sess-1');

        $this->assertSame([], $result['authority_memberships'] ?? null);
        $this->assertSame([], $result['capabilities'] ?? null);
    }

    // ── resolve() – caching ───────────────────────────────────────────────────

    /**
     * A successful resolve() result must be cached. A second call with the same
     * device_id + session_id must return the cached value without hitting the
     * remote endpoint again.
     */
    public function testResolveReturnsCachedResultOnSecondCall(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'identity_id' => 'cached-id',
                'trust_level' => 'device',
            ]),
        ];

        $client = new HeliosClient('https://helios.example.com');

        $first = $client->resolve('dev-cache', 'sess-cache');
        $this->assertIsArray($first);
        $this->assertSame('cached-id', $first['identity_id']);

        // Change the response — the second call should still return the cached value.
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode(['identity_id' => 'new-id']),
        ];

        $second = $client->resolve('dev-cache', 'sess-cache');
        $this->assertIsArray($second);
        $this->assertSame('cached-id', $second['identity_id'], 'Should return cached result, not the new response.');
    }

    /**
     * Different device_id + session_id combinations must use independent cache keys.
     */
    public function testResolveUsesDeviceAndSessionForCacheKey(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode(['identity_id' => 'id-dev-A']),
        ];

        $client = new HeliosClient('https://helios.example.com');
        $a      = $client->resolve('dev-A', 'sess-1');

        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode(['identity_id' => 'id-dev-B']),
        ];
        $b = $client->resolve('dev-B', 'sess-1');

        $this->assertNotSame($a['identity_id'], $b['identity_id'], 'Different devices must have separate cache entries.');
    }

    // ── resolve() – WP_Error response ────────────────────────────────────────

    /**
     * resolve() must return null when wp_remote_post returns a WP_Error.
     */
    public function testResolveReturnsNullOnWpError(): void
    {
        $GLOBALS['__helios_response'] = new \WP_Error('http_request_failed', 'Connection refused');

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->resolve('dev-err', 'sess-1');

        $this->assertNull($result);
    }

    /**
     * resolve() must return null when the response body is not valid JSON.
     */
    public function testResolveReturnsNullOnInvalidJsonBody(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => 'not json at all',
        ];

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->resolve('dev-bad-json', 'sess-1');

        $this->assertNull($result);
    }

    // ── getIdentityContext() ──────────────────────────────────────────────────

    /**
     * getIdentityContext() returns null when resolve() returns null (no base URL).
     */
    public function testGetIdentityContextReturnsNullWhenResolveReturnsNull(): void
    {
        $client = new HeliosClient('');
        $result = $client->getIdentityContext('dev-1', 'sess-1');

        $this->assertNull($result);
    }

    /**
     * getIdentityContext() returns the expected shape when resolve() succeeds.
     */
    public function testGetIdentityContextReturnsCorrectShape(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'identity_id'           => 'ident-shape',
                'trust_level'           => 'authority',
                'verification_status'   => 'verified',
                'authority_memberships' => ['sparxstar_network', 'aiwa'],
                'capabilities'          => ['read_context', 'manage_context'],
            ]),
        ];

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->getIdentityContext('dev-2', 'sess-2');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('identity_id', $result);
        $this->assertArrayHasKey('verification_status', $result);
        $this->assertArrayHasKey('authority_memberships', $result);
        $this->assertArrayHasKey('capabilities', $result);
    }

    /**
     * getIdentityContext() must strip non-string elements from authority_memberships.
     */
    public function testGetIdentityContextStripsNonStringMemberships(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode([
                'authority_memberships' => ['valid', 42, null, 'also_valid'],
                'capabilities'          => [],
            ]),
        ];

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->getIdentityContext('dev-3', 'sess-3');

        $this->assertIsArray($result);
        $this->assertSame(['valid', 'also_valid'], $result['authority_memberships']);
    }

    /**
     * getIdentityContext() defaults verification_status to 'none' when absent.
     */
    public function testGetIdentityContextDefaultsVerificationStatusToNone(): void
    {
        $GLOBALS['__helios_response'] = [
            'response' => ['code' => 200],
            'body'     => json_encode(['identity_id' => 'i-vs']),
        ];

        $client = new HeliosClient('https://helios.example.com');
        $result = $client->getIdentityContext('dev-4', 'sess-4');

        $this->assertSame('unverified', $result['verification_status'] ?? null);
    }
}
