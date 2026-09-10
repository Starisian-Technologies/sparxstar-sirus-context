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

final class TestUECRESTRequestWithJsonParams extends \WP_REST_Request
{
    /**
     * @param mixed $json_params
     */
    public function __construct(private readonly mixed $json_params)
    {
        parent::__construct('POST', '/star-uec/v1/log');
    }

    /**
     * @return mixed
     */
    public function get_json_params(): mixed
    {
        return $this->json_params;
    }
}

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
        $GLOBALS['transients']            = [];
        $GLOBALS['wp_nonce_overrides']    = [];
        $_SERVER['REMOTE_ADDR']           = '198.51.100.42';
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

    /**
     * Raw IP addresses in client-provided fields must be anonymized before storage.
     */
    public function testMapAndNormalizeSnapshotAnonymizesClientProvidedIpValues(): void
    {
        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $method = new \ReflectionMethod($controller, 'map_and_normalize_snapshot');
        $method->setAccessible(true);

        $normalized = $method->invoke($controller, [
            'client_side_data' => [
                'identifiers' => [
                    'fingerprint' => '203.0.113.42',
                    'session_id'  => 'session-123',
                ],
                'identifiers_extra' => [
                    'custom_ip' => '192.168.1.42',
                ],
            ],
            'server_side_data' => [
                'ipAddress' => '10.0.0.42',
            ],
            'client_hints_data' => [],
            'user_id' => 7,
        ]);

        $this->assertSame('203.0.113.0', $normalized['fingerprint']);
        $this->assertSame('192.168.1.0', $normalized['data']['client_side_data']['identifiers_extra']['custom_ip']);
        $this->assertSame('10.0.0.0', $normalized['data']['server_side_data']['ipAddress']);
    }

    /**
     * @dataProvider invalidNestedSnapshotPayloadProvider
     */
    public function test_handle_log_request_rejects_invalid_nested_snapshot_shape(mixed $payload): void
    {
        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $response   = $controller->handle_log_request(new TestUECRESTRequestWithJsonParams($payload));

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertSame('invalid_data', $response->get_error_code());
        $this->assertSame('Invalid JSON payload.', $response->get_error_message());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function invalidNestedSnapshotPayloadProvider(): array
    {
        return [
            'client_side_data must be array' => [
                [
                    'client_side_data' => 'invalid',
                ],
            ],
            'identifiers must be array' => [
                [
                    'client_side_data' => [
                        'identifiers' => 'invalid',
                    ],
                ],
            ],
        ];
    }

    /**
     * Verify that a valid REST nonce is accepted while the public-ingestion budget remains available.
     */
    public function test_check_permissions_accepts_valid_nonce_within_rate_limit(): void
    {
        $GLOBALS['wp_nonce_overrides']['wp_rest']['valid-public-ingest-nonce'] = true;

        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $request    = new \WP_REST_Request('POST', '/star-uec/v1/log');
        $request->set_header('X-WP-Nonce', 'valid-public-ingest-nonce');

        $this->assertTrue($controller->check_permissions($request));
    }

    /**
     * Verify that repeated anonymous public-ingestion requests are rate limited.
     */
    public function test_check_permissions_rejects_requests_after_rate_limit_is_exceeded(): void
    {
        $GLOBALS['wp_nonce_overrides']['wp_rest']['valid-public-ingest-nonce'] = true;

        $controller = new SparxstarUECRESTController(new SparxstarUECDatabase($GLOBALS['wpdb']));
        $request    = new \WP_REST_Request('POST', '/star-uec/v1/log');
        $request->set_header('X-WP-Nonce', 'valid-public-ingest-nonce');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->assertTrue($controller->check_permissions($request));
        }

        $result = $controller->check_permissions($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('rate_limited', $result->get_error_code());
    }
}
