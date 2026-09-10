<?php
/**
 * Unit tests for the REST API controller.
 *
 * @package SparxstarUserEnvironmentCheck\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\SparxstarUEC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\SparxstarUEC\api\SparxstarUECRESTController;
use Starisian\SparxstarUEC\core\SparxstarUECDatabase;

/**
 * Validates that the REST controller registers the expected routes.
 */
final class SparxstarUECAPITest extends TestCase
{
    /**
     * Reset the route registry before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['spx_registered_routes'] = [];
    }

    /**
     * Verify that register_routes() registers the primary log endpoint.
     */
    public function test_register_routes_registers_log_endpoint(): void
    {
        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $controller->register_routes();

        $this->assertNotEmpty($GLOBALS['spx_registered_routes'], 'Expected at least one route to be registered.');
        $namespaces = array_column($GLOBALS['spx_registered_routes'], 'namespace');
        $this->assertContains('star-uec/v1', $namespaces);
    }

    /**
     * Verify that the /log route path is registered.
     */
    public function test_register_routes_includes_log_path(): void
    {
        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $controller->register_routes();

        $routes = array_column($GLOBALS['spx_registered_routes'], 'route');
        $this->assertContains('/log', $routes);
    }

    /**
     * Verify that the /recorder-log route path is also registered.
     */
    public function test_register_routes_includes_recorder_log_path(): void
    {
        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $controller->register_routes();

        $routes = array_column($GLOBALS['spx_registered_routes'], 'route');
        $this->assertContains('/recorder-log', $routes);
    }

    /**
     * Verify that permission checks reject requests with no nonce.
     */
    public function test_check_permissions_rejects_requests_without_a_nonce(): void
    {
        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $request    = new \WP_REST_Request('POST', '/star-uec/v1/log');

        $result = $controller->check_permissions($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_nonce', $result->get_error_code());
    }

    /**
     * Verify that permission checks reject requests with an invalid nonce.
     */
    public function test_check_permissions_rejects_requests_with_an_invalid_nonce(): void
    {
        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $request    = new \WP_REST_Request('POST', '/star-uec/v1/log');
        $request->set_header('X-WP-Nonce', 'definitely-invalid-nonce');

        $result = $controller->check_permissions($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_nonce', $result->get_error_code());
    }

    /**
     * Verify that permission checks accept requests with a valid nonce.
     */
    public function test_check_permissions_accepts_requests_with_a_valid_nonce(): void
    {
        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $request    = new \WP_REST_Request('POST', '/star-uec/v1/log');
        $nonce      = wp_create_nonce('wp_rest');
        $request->set_header('X-WP-Nonce', $nonce);

        $result = $controller->check_permissions($request);

        $this->assertTrue($result);
    }
}
