<?php

/**
 * Integration-style tests for the Sirus REST surface using the isolated bootstrap.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Integration
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Integration;

use Starisian\Sparxstar\Sirus\api\SirusRESTController;
use Starisian\Sparxstar\Sirus\core\ClientTelemetry;
use Starisian\Sparxstar\Sirus\core\ContextCache;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\DeviceRecord;
use Starisian\Sparxstar\Sirus\core\DeviceRepositoryInterface;
use Starisian\Sparxstar\Sirus\core\IdentityResolver;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\core\PulseGenerator;
use Starisian\Sparxstar\Sirus\integrations\HeliosClientInterface;
use Starisian\Sparxstar\Sirus\services\SirusDeviceParser;
use Starisian\Sparxstar\Sirus\Tests\Unit\SirusTestCase;

final class RestApiTest extends SirusTestCase
{
    private SirusRESTController $controller;

    private \wpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '198.51.100.21';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';

        $GLOBALS['spx_registered_routes'] = [];
        $GLOBALS['registered_filters'] = [];
        $GLOBALS['transients'] = [];
        $this->wpdb = new \wpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        ContextCache::clear();

        $repository = new class implements DeviceRepositoryInterface {
            /** @var array<string, DeviceRecord> */
            public array $records = [];

            public function findByDeviceId(string $device_id): ?DeviceRecord
            {
                return $this->records[$device_id] ?? null;
            }

            public function findByFingerprintHash(string $fingerprint_hash): ?DeviceRecord
            {
                foreach ($this->records as $record) {
                    if ($record->fingerprint_hash === $fingerprint_hash) {
                        return $record;
                    }
                }

                return null;
            }

            public function save(DeviceRecord $record): bool
            {
                $this->records[$record->device_id] = $record;
                return true;
            }

            public function updateLastSeen(string $device_id): void
            {
                unset($device_id);
            }

            public function updateFingerprintHash(string $device_id, string $fingerprint_hash): void
            {
                unset($device_id, $fingerprint_hash);
            }

            public function incrementDriftScore(string $device_id): void
            {
                unset($device_id);
            }
        };

        $identity_resolver = new IdentityResolver(
            new class implements HeliosClientInterface {
                public function resolve(
                    string $device_id,
                    string $session_id,
                    ?string $identity_claim = null
                ): ?array {
                    unset($device_id, $session_id, $identity_claim);

                    return null;
                }

                public function getIdentityContext(
                    string $device_id,
                    string $session_id,
                    ?string $identity_claim = null
                ): ?array {
                    unset($device_id, $session_id, $identity_claim);

                    return [
                        'identity_id'           => 'identity-1',
                        'verification_status'   => 'verified',
                        'authority_memberships' => [ 'starisian' ],
                        'capabilities'          => [ 'read_context' ],
                    ];
                }
            }
        );

        $this->controller = new SirusRESTController(
            new DeviceContinuity($repository),
            new PulseGenerator(),
            $identity_resolver,
            new ClientTelemetry($this->wpdb),
            new SirusDeviceParser(),
            new NetworkContextBroker()
        );
    }

    public function testRegisterRoutesIncludesAllSixS07Endpoints(): void
    {
        $this->controller->register_routes();

        $routes = array_map(
            static fn (array $route): string => $route['namespace'] . $route['route'],
            $GLOBALS['spx_registered_routes']
        );

        $this->assertContains('sparxstar/v1/device', $routes);
        $this->assertContains('sparxstar/v1/context', $routes);
        $this->assertContains('sparxstar/v1/pulse', $routes);
        $this->assertContains('sparxstar/v1/identity', $routes);
        $this->assertContains('sparxstar/v1/session', $routes);
        $this->assertContains('sparxstar/v1/client-report', $routes);
    }

    public function testSuccessPathsForAllSixEndpoints(): void
    {
        $device_request = $this->makeRequest(
            'POST',
            '/sparxstar/v1/device',
            [
                'visitor_id'       => 'visitor-123',
                'environment_data' => [ 'browser_name' => 'Firefox' ],
            ]
        );
        $device_response = $this->controller->handle_device_register($device_request);
        $this->assertInstanceOf(\WP_REST_Response::class, $device_response);
        $this->assertSame(200, $device_response->get_status());

        $context_response = $this->controller->handle_get_context(
            $this->makeRequest('GET', '/sparxstar/v1/context')
        );
        $this->assertInstanceOf(\WP_REST_Response::class, $context_response);
        $this->assertSame('CLI', $context_response->get_data()['dev'] ?? null);

        $pulse_response = $this->controller->handle_generate_pulse(
            $this->makeRequest(
                'POST',
                '/sparxstar/v1/pulse',
                [
                    'device_id'            => 'CLI',
                    'resource_sensitivity' => 'LEVEL_2',
                    'request_id'           => 'req-1',
                ]
            )
        );
        $this->assertInstanceOf(\WP_REST_Response::class, $pulse_response);
        $this->assertSame(201, $pulse_response->get_status());
        $this->assertStringContainsString('HttpOnly', (string) $pulse_response->get_header('Set-Cookie'));
        $this->assertStringContainsString('SameSite=Strict', (string) $pulse_response->get_header('Set-Cookie'));

        $identity_response = $this->controller->handle_get_identity(
            $this->makeRequest('GET', '/sparxstar/v1/identity', [ 'device_id' => 'CLI' ])
        );
        $this->assertInstanceOf(\WP_REST_Response::class, $identity_response);
        $this->assertSame('identity-1', $identity_response->get_data()['identity_id'] ?? null);

        $session_response = $this->controller->handle_get_session(
            $this->makeRequest('GET', '/sparxstar/v1/session', [ 'device_id' => 'CLI' ])
        );
        $this->assertInstanceOf(\WP_REST_Response::class, $session_response);
        $this->assertSame('CLI', $session_response->get_data()['session_id'] ?? null);
        $this->assertArrayHasKey('status', $session_response->get_data());
        $this->assertArrayNotHasKey('is_active', $session_response->get_data());

        $client_report_response = $this->controller->handle_client_report(
            $this->makeRequest(
                'POST',
                '/sparxstar/v1/client-report',
                [
                    'error_type'    => 'js_error',
                    'error_message' => 'Oops',
                    'device_id'     => 'CLI',
                    'context'       => [ 'component' => 'ui' ],
                ]
            )
        );
        $this->assertInstanceOf(\WP_REST_Response::class, $client_report_response);
        $this->assertSame(202, $client_report_response->get_status());
    }

    public function testPermissionDeniedForAllSixEndpointsWithInvalidNonce(): void
    {
        foreach (
            [
                [ 'POST', '/sparxstar/v1/device' ],
                [ 'GET', '/sparxstar/v1/context' ],
                [ 'POST', '/sparxstar/v1/pulse' ],
                [ 'GET', '/sparxstar/v1/identity' ],
                [ 'GET', '/sparxstar/v1/session' ],
                [ 'POST', '/sparxstar/v1/client-report' ],
            ] as [ $method, $route ]
        ) {
            $request = new \WP_REST_Request($method, $route);
            $request->set_header('X-WP-Nonce', 'invalid_nonce');

            $result = $this->controller->verify_rest_nonce($request);

            $this->assertInstanceOf(\WP_Error::class, $result);
            $this->assertSame('sparxstar_sirus_rest_nonce_invalid', $result->get_error_code());
        }
    }

    public function testMalformedInputIsRejectedForAllSixEndpoints(): void
    {
        $device_result = $this->controller->handle_device_register(
            $this->makeRequest('POST', '/sparxstar/v1/device', [ 'visitor_id' => '' ])
        );
        $this->assertInstanceOf(\WP_Error::class, $device_result);

        $context_result = $this->controller->handle_get_context(
            $this->makeRequest('GET', '/sparxstar/v1/context', [ 'ctx_token' => 'bad-token' ])
        );
        $this->assertInstanceOf(\WP_Error::class, $context_result);

        $context_device_result = $this->controller->handle_get_context(
            $this->makeRequest('GET', '/sparxstar/v1/context', [ 'device_id' => 'other-device' ])
        );
        $this->assertInstanceOf(\WP_Error::class, $context_device_result);

        $pulse_result = $this->controller->handle_generate_pulse(
            $this->makeRequest(
                'POST',
                '/sparxstar/v1/pulse',
                [
                    'device_id'            => 'CLI',
                    'resource_sensitivity' => 'LEVEL_99',
                    'request_id'           => 'req-1',
                ]
            )
        );
        $this->assertInstanceOf(\WP_Error::class, $pulse_result);

        $identity_result = $this->controller->handle_get_identity(
            $this->makeRequest('GET', '/sparxstar/v1/identity', [ 'device_id' => 'other-device' ])
        );
        $this->assertInstanceOf(\WP_Error::class, $identity_result);

        $session_result = $this->controller->handle_get_session(
            $this->makeRequest('GET', '/sparxstar/v1/session', [ 'device_id' => 'other-device' ])
        );
        $this->assertInstanceOf(\WP_Error::class, $session_result);

        $client_report_result = $this->controller->handle_client_report(
            $this->makeRequest(
                'POST',
                '/sparxstar/v1/client-report',
                [
                    'error_type' => '',
                ]
            )
        );
        $this->assertInstanceOf(\WP_Error::class, $client_report_result);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function makeRequest(string $method, string $route, array $params = []): \WP_REST_Request
    {
        $method  = strtoupper($method);
        $request = new \WP_REST_Request($method, $route);
        // The WP_REST_Request stub in tests/bootstrap-unit.php only implements
        // get_param()/set_param() (not set_body_params()/set_query_params(),
        // which real WordPress core provides but this minimal stub doesn't) --
        // and SirusRESTController reads exclusively via get_param() for both
        // body and query params, matching real WP_REST_Request's unified
        // accessor behavior. set_param() is correct for every HTTP method here.
        foreach ($params as $key => $value) {
            $request->set_param((string) $key, $value);
        }
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

        return $request;
    }
}
