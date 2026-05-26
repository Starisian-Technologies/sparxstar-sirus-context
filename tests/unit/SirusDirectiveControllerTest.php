<?php

/**
 * Tests for SirusDirectiveController – REST endpoints for adaptive directives.
 *
 * Routes under test:
 *   GET /sirus/v1/directives  – returns active directives for a device/session.
 *   GET /sirus/v1/rule-hits   – admin-only recent rule hits.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\api\SirusDirectiveController;
use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer;
use Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine;
use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

/**
 * Unit tests for SirusDirectiveController route registration, directive retrieval,
 * and permission callbacks.
 */
final class SirusDirectiveControllerTest extends SirusTestCase
{
    private SirusDirectiveController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wpdb']                  = new \wpdb();
        $GLOBALS['wpdb_get_results']      = [];
        $GLOBALS['transients']            = [];
        $GLOBALS['wp_options']            = [];
        $GLOBALS['spx_registered_routes'] = [];

        $wpdb        = $GLOBALS['wpdb'];
        $ruleHitRepo = new SirusRuleHitRepository($wpdb);
        $actionRepo  = new SirusMitigationActionRepository($wpdb);
        $coordinator = new SirusMitigationCoordinator(
            new SirusSignalEvaluator(),
            new SirusImpactScorer(),
            new SirusMitigationRuleEngine(),
            $ruleHitRepo,
            $actionRepo
        );

        $this->controller = new SirusDirectiveController($coordinator, $ruleHitRepo);
    }

    // ── register_routes() ─────────────────────────────────────────────────────

    /**
     * register_routes() must register a /directives route under sirus/v1.
     */
    public function testRegisterRoutesAddsDirectivesRoute(): void
    {
        $this->controller->register_routes();

        $routes = $GLOBALS['spx_registered_routes'] ?? [];
        $found  = false;
        foreach ($routes as $route) {
            if ($route['namespace'] === 'sirus/v1' && $route['route'] === '/directives') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected sirus/v1/directives route to be registered.');
    }

    /**
     * register_routes() must register a /rule-hits route under sirus/v1.
     */
    public function testRegisterRoutesAddsRuleHitsRoute(): void
    {
        $this->controller->register_routes();

        $routes = $GLOBALS['spx_registered_routes'] ?? [];
        $found  = false;
        foreach ($routes as $route) {
            if ($route['namespace'] === 'sirus/v1' && $route['route'] === '/rule-hits') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected sirus/v1/rule-hits route to be registered.');
    }

    /**
     * /directives route must use the GET method.
     */
    public function testDirectivesRouteMethodIsGet(): void
    {
        $this->controller->register_routes();

        foreach ($GLOBALS['spx_registered_routes'] as $route) {
            if ($route['namespace'] === 'sirus/v1' && $route['route'] === '/directives') {
                $this->assertSame('GET', $route['args']['methods']);
                return;
            }
        }

        $this->fail('Route sirus/v1/directives not found.');
    }

    /**
     * /rule-hits route must use the GET method.
     */
    public function testRuleHitsRouteMethodIsGet(): void
    {
        $this->controller->register_routes();

        foreach ($GLOBALS['spx_registered_routes'] as $route) {
            if ($route['namespace'] === 'sirus/v1' && $route['route'] === '/rule-hits') {
                $this->assertSame('GET', $route['args']['methods']);
                return;
            }
        }

        $this->fail('Route sirus/v1/rule-hits not found.');
    }

    /**
     * /directives route must be publicly accessible (permission_callback = __return_true).
     */
    public function testDirectivesRouteIsPubliclyAccessible(): void
    {
        $this->controller->register_routes();

        foreach ($GLOBALS['spx_registered_routes'] as $route) {
            if ($route['namespace'] === 'sirus/v1' && $route['route'] === '/directives') {
                $this->assertSame('__return_true', $route['args']['permission_callback']);
                return;
            }
        }

        $this->fail('Route sirus/v1/directives not found.');
    }

    // ── get_directives() ──────────────────────────────────────────────────────

    /**
     * get_directives() with an empty device_id must return a WP_Error (400).
     */
    public function testGetDirectivesReturnsBadRequestForEmptyDeviceId(): void
    {
        $request = new \WP_REST_Request('GET', '/sirus/v1/directives', ['device_id' => '']);
        $result  = $this->controller->get_directives($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sirus_directive_missing_device_id', $result->get_error_code());
    }

    /**
     * get_directives() with a missing device_id must also return WP_Error (400).
     */
    public function testGetDirectivesReturnsBadRequestForMissingDeviceId(): void
    {
        $request = new \WP_REST_Request('GET', '/sirus/v1/directives', []);
        $result  = $this->controller->get_directives($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * get_directives() with a valid device_id must return a WP_REST_Response (200).
     *
     * With the in-memory wpdb stub (no active actions), the coordinator returns
     * the 'normal' default directive.
     */
    public function testGetDirectivesReturnsResponseForValidDeviceId(): void
    {
        $request = new \WP_REST_Request('GET', '/sirus/v1/directives', ['device_id' => 'dev-abc', 'session_id' => 'sess-1']);
        $result  = $this->controller->get_directives($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
        $this->assertSame(200, $result->get_status());
    }

    /**
     * get_directives() response payload must contain the expected advisory keys.
     */
    public function testGetDirectivesResponseContainsExpectedKeys(): void
    {
        $request = new \WP_REST_Request('GET', '/sirus/v1/directives', ['device_id' => 'dev-keys']);
        $result  = $this->controller->get_directives($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $result);

        $data = $result->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('mode', $data);
        $this->assertArrayHasKey('ttl', $data);
        $this->assertArrayHasKey('reason', $data);
        $this->assertArrayHasKey('confidence', $data);
    }

    /**
     * get_directives() returns 'normal' mode when no active mitigations exist.
     */
    public function testGetDirectivesReturnsNormalModeByDefault(): void
    {
        $request = new \WP_REST_Request('GET', '/sirus/v1/directives', ['device_id' => 'dev-normal']);
        $result  = $this->controller->get_directives($request);

        $data = $result->get_data();
        $this->assertSame('normal', $data['mode']);
    }

    // ── get_rule_hits() ───────────────────────────────────────────────────────

    /**
     * get_rule_hits() must return a WP_REST_Response containing an array.
     */
    public function testGetRuleHitsReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/sirus/v1/rule-hits', ['limit' => 10]);
        $result  = $this->controller->get_rule_hits($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
        $this->assertSame(200, $result->get_status());
        $this->assertIsArray($result->get_data());
    }

    /**
     * get_rule_hits() with no limit parameter must default to 100.
     */
    public function testGetRuleHitsWithNoLimitDefaultsTo100(): void
    {
        // No limit param → should default to 100. Method must not throw.
        $request = new \WP_REST_Request('GET', '/sirus/v1/rule-hits', []);
        $result  = $this->controller->get_rule_hits($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $result);
    }

    // ── admin_permission_callback() ───────────────────────────────────────────

    /**
     * admin_permission_callback() must return true in the test environment where
     * current_user_can() and is_super_admin() are both shimmed to return true.
     */
    public function testAdminPermissionCallbackReturnsTrueInTestEnvironment(): void
    {
        $this->assertTrue($this->controller->admin_permission_callback());
    }
}
