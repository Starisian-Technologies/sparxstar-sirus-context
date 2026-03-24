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
use Starisian\Sparxstar\Sirus\helpers\SirusRateLimit;

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
     * All canonical event types defined in SirusEventRepository::VALID_EVENT_TYPES should pass validation.
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

    // ── create_event: deduplication ───────────────────────────────────────────

    /**
     * create_event() should return 200 with status=deduplicated when repository returns DEDUP_SKIPPED.
     */
    public function testCreateEventReturnsDeduplicated(): void
    {
        $repo       = new SirusEventRepository($this->wpdb);
        $controller = new SirusEventController($repo);

        $event = [
            'event_type' => 'js_error',
            'timestamp'  => 1710000000,
            'device_id'  => 'dedup-device-abc123',
            'session_id' => 'sess-dedup-xyz',
            'url'        => '/test',
        ];

        // First call: records the event.
        $request = new \WP_REST_Request('POST', '/sirus/v1/event', $event);
        $result1 = $controller->create_event($request);
        $this->assertInstanceOf(\WP_REST_Response::class, $result1);
        $this->assertSame(201, $result1->get_status());

        // Second call: same payload — should be deduplicated.
        $request2 = new \WP_REST_Request('POST', '/sirus/v1/event', $event);
        $result2  = $controller->create_event($request2);
        $this->assertInstanceOf(\WP_REST_Response::class, $result2);
        $this->assertSame(200, $result2->get_status());
        $data = $result2->get_data();
        $this->assertSame('deduplicated', $data['status'] ?? '');
    }

    // ── create_event: device_id format validation ─────────────────────────────

    /**
     * create_event() should return 400 for a device_id that is too short (< 8 chars).
     */
    public function testCreateEventReturns400ForShortDeviceId(): void
    {
        $repo       = new SirusEventRepository($this->wpdb);
        $controller = new SirusEventController($repo);

        $request = new \WP_REST_Request('POST', '/sirus/v1/event', [
            'event_type' => 'session_start',
            'timestamp'  => 1710000000,
            'device_id'  => 'short',    // only 5 chars — invalid
            'session_id' => 'sess-valid-xyz',
        ]);

        $result = $controller->create_event($request);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sirus_event_invalid_device_id', $result->get_error_code());
    }

    /**
     * create_event() should return 400 for a device_id with invalid characters.
     */
    public function testCreateEventReturns400ForInvalidDeviceIdChars(): void
    {
        $repo       = new SirusEventRepository($this->wpdb);
        $controller = new SirusEventController($repo);

        $request = new \WP_REST_Request('POST', '/sirus/v1/event', [
            'event_type' => 'session_start',
            'timestamp'  => 1710000000,
            'device_id'  => 'device!!invalid@@chars',  // invalid chars
            'session_id' => 'sess-valid-xyz',
        ]);

        $result = $controller->create_event($request);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sirus_event_invalid_device_id', $result->get_error_code());
    }

    // ── create_event: rate limiter ────────────────────────────────────────────

    /**
     * create_event() should return 429 when the rate limiter blocks the request.
     */
    public function testCreateEventReturns429WhenRateLimited(): void
    {
        $repo = new SirusEventRepository($this->wpdb);

        $device = 'device-rate-limited-1234';
        $key    = 'sirus_rl_' . md5($device);

        // Pre-seed transient to simulate the rate limit being hit.
        $GLOBALS['transients'][$key] = [
            'count'        => 200,
            'window_start' => time(),
        ];

        $controller = new SirusEventController($repo, null, new SirusRateLimit());

        $request = new \WP_REST_Request('POST', '/sirus/v1/event', [
            'event_type' => 'session_start',
            'timestamp'  => 1710000000,
            'device_id'  => $device,
            'session_id' => 'sess-rate-xyz',
        ]);

        $result = $controller->create_event($request);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('sirus_event_rate_limited', $result->get_error_code());
    }

    // ── create_event: new event types ────────────────────────────────────────

    /**
     * create_event() should accept page_ready as a valid event_type.
     */
    public function testCreateEventAcceptsPageReady(): void
    {
        $repo       = new SirusEventRepository($this->wpdb);
        $controller = new SirusEventController($repo);

        $request = new \WP_REST_Request('POST', '/sirus/v1/event', [
            'event_type' => 'page_ready',
            'timestamp'  => 1710000000,
            'device_id'  => 'device-page-ready-ok',
            'session_id' => 'sess-page-xyz',
        ]);

        $result = $controller->create_event($request);
        $this->assertNotInstanceOf(\WP_Error::class, $result);
        $this->assertInstanceOf(\WP_REST_Response::class, $result);
    }

    /**
     * create_event() should accept action_success as a valid event_type.
     */
    public function testCreateEventAcceptsActionSuccess(): void
    {
        $repo       = new SirusEventRepository($this->wpdb);
        $controller = new SirusEventController($repo);

        $request = new \WP_REST_Request('POST', '/sirus/v1/event', [
            'event_type' => 'action_success',
            'timestamp'  => 1710000001,
            'device_id'  => 'device-action-ok-1234',
            'session_id' => 'sess-action-xyz',
        ]);

        $result = $controller->create_event($request);
        $this->assertNotInstanceOf(\WP_Error::class, $result);
        $this->assertInstanceOf(\WP_REST_Response::class, $result);
    }

    /**
     * create_event() should accept task_completed as a valid event_type.
     */
    public function testCreateEventAcceptsTaskCompleted(): void
    {
        $repo       = new SirusEventRepository($this->wpdb);
        $controller = new SirusEventController($repo);

        $request = new \WP_REST_Request('POST', '/sirus/v1/event', [
            'event_type' => 'task_completed',
            'timestamp'  => 1710000002,
            'device_id'  => 'device-task-done-1234',
            'session_id' => 'sess-task-xyz',
        ]);

        $result = $controller->create_event($request);
        $this->assertNotInstanceOf(\WP_Error::class, $result);
        $this->assertInstanceOf(\WP_REST_Response::class, $result);
    }
}
