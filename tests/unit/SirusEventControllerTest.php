<?php

/**
 * Tests for SirusEventController – REST endpoint for Sirus observability events.
 *
 * Tests the validate_event_type static method and route registration.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\api\SirusEventController;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;

/**
 * Validates SirusEventController route registration and input validation.
 */
final class SirusEventControllerTest extends SirusTestCase
{
    private SirusEventController $controller;

    /** @var \wpdb */
    private \wpdb $wpdb;

    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['spx_registered_routes'] = [];

        $this->wpdb       = $GLOBALS['wpdb'];
        $repo             = new SirusEventRepository($this->wpdb);
        $this->controller = new SirusEventController($repo);
    }

    // ── register_routes ───────────────────────────────────────────────────────

    /**
     * register_routes() should register exactly one route under sirus/v1.
     */
    public function testRegisterRoutesAddsEventRoute(): void
    {
        $this->controller->register_routes();

        $routes = $GLOBALS['spx_registered_routes'] ?? [];

        $found = false;
        foreach ($routes as $route) {
            if ($route['namespace'] === 'sirus/v1' && $route['route'] === '/event') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected sirus/v1/event route to be registered.');
    }

    /**
     * The registered route should use the POST method.
     */
    public function testEventRouteMethodIsPost(): void
    {
        $this->controller->register_routes();

        $routes = $GLOBALS['spx_registered_routes'] ?? [];

        foreach ($routes as $route) {
            if ($route['namespace'] === 'sirus/v1' && $route['route'] === '/event') {
                $this->assertSame('POST', $route['args']['methods']);
                return;
            }
        }

        $this->fail('Route sirus/v1/event not found.');
    }

    // ── validate_event_type ───────────────────────────────────────────────────

    /**
     * All six canonical event types should pass validation.
     */
    public function testValidateEventTypeAcceptsAllValidTypes(): void
    {
        foreach (SirusEventRepository::VALID_EVENT_TYPES as $type) {
            $result = SirusEventController::validate_event_type($type);
            $this->assertTrue($result, "Expected '{$type}' to be valid.");
        }
    }

    /**
     * An unknown type string should return a WP_Error.
     */
    public function testValidateEventTypeRejectsUnknownType(): void
    {
        $result = SirusEventController::validate_event_type('unknown_event');
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * A non-string value should return a WP_Error.
     */
    public function testValidateEventTypeRejectsNonString(): void
    {
        $result = SirusEventController::validate_event_type(42);
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * An empty string should return a WP_Error (not a valid type).
     */
    public function testValidateEventTypeRejectsEmptyString(): void
    {
        $result = SirusEventController::validate_event_type('');
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    // ── registered route args ─────────────────────────────────────────────────

    /**
     * The event route should declare event_type, timestamp, device_id, session_id
     * as required args.
     */
    public function testEventRouteRequiredArgsAreDeclared(): void
    {
        $this->controller->register_routes();

        $routes = $GLOBALS['spx_registered_routes'] ?? [];
        $route_args = null;

        foreach ($routes as $route) {
            if ($route['namespace'] === 'sirus/v1' && $route['route'] === '/event') {
                $route_args = $route['args']['args'] ?? null;
                break;
            }
        }

        $this->assertNotNull($route_args, 'Route args not found.');

        foreach (['event_type', 'timestamp', 'device_id', 'session_id'] as $field) {
            $this->assertArrayHasKey($field, $route_args, "Expected '{$field}' in route args.");
            $this->assertTrue((bool) ($route_args[$field]['required'] ?? false), "Expected '{$field}' to be required.");
        }
    }
}
