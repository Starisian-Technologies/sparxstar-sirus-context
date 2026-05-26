<?php

/**
 * Tests for SirusRESTController – device registration and context REST endpoints.
 *
 * Covers:
 *   - verify_rest_nonce() with missing nonce → WP_Error 403
 *   - verify_rest_nonce() with valid nonce → true
 *   - register_routes() registers the expected routes
 *   - check_rate_limit logic (tested indirectly via handle_device_register)
 *
 * NOTE: handle_device_register() involves the full DeviceContinuity chain and is
 * not covered here. Route-level and nonce-verification tests are the priority.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\api\SirusRESTController;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\DeviceRepository;
use Starisian\Sparxstar\Sirus\core\IdentityResolver;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\core\PulseGenerator;
use Starisian\Sparxstar\Sirus\services\SirusDeviceParser;

/**
 * Extends the WP_REST_Request stub to add header support for nonce verification.
 */
final class TestWPRESTRequestWithHeaders extends \WP_REST_Request
{
    /** @var array<string, string> */
    private array $headers = [];

    /**
     * Sets a request header (lowercase normalised).
     *
     * @param string $header Header name.
     * @param string $value  Header value.
     */
    public function set_header(string $header, string $value): void
    {
        $this->headers[strtolower($header)] = $value;
    }

    /**
     * Returns the value of the specified header, or null.
     *
     * @param string $key Header name.
     * @return string|null
     */
    public function get_header(string $key): ?string
    {
        return $this->headers[strtolower($key)] ?? null;
    }
}

/**
 * Unit tests for SirusRESTController::verify_rest_nonce() and route registration.
 */
final class SirusRESTControllerTest extends SirusTestCase
{
    private SirusRESTController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wpdb']                  = new \wpdb();
        $GLOBALS['spx_registered_routes'] = [];
        $GLOBALS['transients']            = [];
        $GLOBALS['wp_nonce_overrides']    = [];

        $wpdb       = $GLOBALS['wpdb'];
        $repository = new DeviceRepository($wpdb);
        $continuity = new DeviceContinuity($repository);

        $this->controller = new SirusRESTController(
            $continuity,
            new PulseGenerator(),
            new IdentityResolver(),
            null,
            new SirusDeviceParser(),
            new NetworkContextBroker(),
        );
    }

    // ── register_routes() ─────────────────────────────────────────────────────

    /**
     * register_routes() must register a POST /sparxstar/v1/device route.
     */
    public function testRegisterRoutesAddsDeviceRoute(): void
    {
        $this->controller->register_routes();

        $routes = $GLOBALS['spx_registered_routes'] ?? [];
        $found  = false;
        foreach ($routes as $route) {
            if ($route['namespace'] === 'sparxstar/v1' && $route['route'] === '/device') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected sparxstar/v1/device route to be registered.');
    }

    /**
     * register_routes() must register a GET /sparxstar/v1/context route.
     */
    public function testRegisterRoutesAddsContextRoute(): void
    {
        $this->controller->register_routes();

        $routes = $GLOBALS['spx_registered_routes'] ?? [];
        $found  = false;
        foreach ($routes as $route) {
            if ($route['namespace'] === 'sparxstar/v1' && $route['route'] === '/context') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected sparxstar/v1/context route to be registered.');
    }

    /**
     * /device route must use the POST method.
     */
    public function testDeviceRouteMethodIsPost(): void
    {
        $this->controller->register_routes();

        foreach ($GLOBALS['spx_registered_routes'] as $route) {
            if ($route['namespace'] === 'sparxstar/v1' && $route['route'] === '/device') {
                $this->assertSame('POST', $route['args']['methods']);
                return;
            }
        }
        $this->fail('Route sparxstar/v1/device not found.');
    }

    /**
     * /context route must use the GET method.
     */
    public function testContextRouteMethodIsGet(): void
    {
        $this->controller->register_routes();

        foreach ($GLOBALS['spx_registered_routes'] as $route) {
            if ($route['namespace'] === 'sparxstar/v1' && $route['route'] === '/context') {
                $this->assertSame('GET', $route['args']['methods']);
                return;
            }
        }
        $this->fail('Route sparxstar/v1/context not found.');
    }

    // ── verify_rest_nonce() ───────────────────────────────────────────────────

    /**
     * verify_rest_nonce() must return WP_Error(403) when the X-WP-Nonce header is absent.
     */
    public function testVerifyRestNonceReturnsForbiddenWhenHeaderAbsent(): void
    {
        $request = new TestWPRESTRequestWithHeaders('POST', '/sparxstar/v1/device', []);
        // No X-WP-Nonce header set.

        $result = $this->controller->verify_rest_nonce($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sparxstar_sirus_rest_nonce_missing', $result->get_error_code());
    }

    /**
     * verify_rest_nonce() must return WP_Error(403) when the nonce header is an empty string.
     */
    public function testVerifyRestNonceReturnsForbiddenWhenHeaderIsEmptyString(): void
    {
        $request = new TestWPRESTRequestWithHeaders('POST', '/sparxstar/v1/device', []);
        $request->set_header('X-WP-Nonce', '');

        $result = $this->controller->verify_rest_nonce($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sparxstar_sirus_rest_nonce_missing', $result->get_error_code());
    }

    /**
     * verify_rest_nonce() must return true when a valid non-empty nonce is present.
     *
     * The wp_verify_nonce shim accepts a nonce that is registered in
     * $GLOBALS['wp_nonce_overrides']['wp_rest'], so we seed the override before
     * constructing the request.
     */
    public function testVerifyRestNonceReturnsTrueForValidNonce(): void
    {
        $GLOBALS['wp_nonce_overrides']['wp_rest']['some-valid-nonce-value'] = true;

        $request = new TestWPRESTRequestWithHeaders('POST', '/sparxstar/v1/device', []);
        $request->set_header('X-WP-Nonce', 'some-valid-nonce-value');

        $result = $this->controller->verify_rest_nonce($request);

        $this->assertTrue($result, 'verify_rest_nonce() should return true for a valid nonce.');
    }

    /**
     * verify_rest_nonce() must return WP_Error when wp_verify_nonce returns false.
     *
     * A non-empty nonce that does not match the expected nonce token (wp_create_nonce('wp_rest'))
     * causes wp_verify_nonce() to return false, which must produce a WP_Error with the
     * 'sparxstar_sirus_rest_nonce_invalid' error code.
     */
    public function testVerifyRestNonceReturnsForbiddenWhenNonceVerificationFails(): void
    {
        $request = new TestWPRESTRequestWithHeaders('POST', '/sparxstar/v1/device', []);
        // Non-empty but clearly wrong nonce — hash_equals(wp_create_nonce('wp_rest'), ...) returns false.
        $request->set_header('X-WP-Nonce', 'definitely-invalid-nonce');

        $result = $this->controller->verify_rest_nonce($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sparxstar_sirus_rest_nonce_invalid', $result->get_error_code());
    }
}
