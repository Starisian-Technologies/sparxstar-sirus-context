# SirusRESTController

**Namespace:** `Starisian\Sparxstar\Sirus\api`

**Full Class Name:** `Starisian\Sparxstar\Sirus\api\SirusRESTController`

## Description

SirusRESTController - REST API endpoints for the Sirus Context Engine.
Privacy requirements (non-negotiable per spec §H):
- IPs are anonymized (last octet zeroed) before any storage or logging.
- Rate limiting runs on the raw IP; only the anonymized form is stored.
Device fingerprint derivation (spec §A):
- fingerprint_hash is computed server-side as sha256(visitor_id + user_agent + ip_subnet).
  The client sends raw signals; the server owns the hash computation.
Device → Context binding (spec §A, issue #1):
- After resolving/registering a device, the context is built FROM that device
  via ContextEngine::buildFromDevice() so the two are always in sync.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\api;

if (! defined('ABSPATH')) {
    exit;
}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\helpers\IpAnonymizer;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\services\SirusDeviceParser;

/**
Registers and handles REST routes for device registration and context retrieval.
All input is sanitized before use. Rate limiting is enforced per raw IP;
only anonymized IPs are stored.

## Methods

### `verify_rest_nonce(WP_REST_Request $request)`

SirusRESTController - REST API endpoints for the Sirus Context Engine.
Privacy requirements (non-negotiable per spec §H):
- IPs are anonymized (last octet zeroed) before any storage or logging.
- Rate limiting runs on the raw IP; only the anonymized form is stored.
Device fingerprint derivation (spec §A):
- fingerprint_hash is computed server-side as sha256(visitor_id + user_agent + ip_subnet).
  The client sends raw signals; the server owns the hash computation.
Device → Context binding (spec §A, issue #1):
- After resolving/registering a device, the context is built FROM that device
  via ContextEngine::buildFromDevice() so the two are always in sync.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\api;

if (! defined('ABSPATH')) {
    exit;
}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\helpers\IpAnonymizer;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\services\SirusDeviceParser;

/**
Registers and handles REST routes for device registration and context retrieval.
All input is sanitized before use. Rate limiting is enforced per raw IP;
only anonymized IPs are stored.
/
final class SirusRESTController
{
    private const NAMESPACE = 'sparxstar/v1';

    private const RATE_LIMIT_TRANSIENT_PREFIX = 'sirus_rl_';

    private const RATE_LIMIT_MAX = 30;

    /**
@param DeviceContinuity $device_continuity The device continuity service.
/
    public function __construct(
        private readonly DeviceContinuity $device_continuity,
    ) {
    }

    /**
Permission callback to enforce REST nonce validation and mitigate CSRF.
Expects a valid X-WP-Nonce header created for the 'wp_rest' action.
@param WP_REST_Request $request The current REST request.
@return bool|WP_Error True if the nonce is valid, otherwise WP_Error.

### `register_routes()`

Registers the REST API routes for the Sirus Context Engine.

### `handle_device_register(WP_REST_Request $request)`

Handles POST /sparxstar/v1/device.
Server derives fingerprint_hash from visitor_id + UA + IP subnet.
Resolves or registers device continuity, runs server-side UA parsing,
builds the context from the resolved device (deterministic binding), and
returns a signed context token.
@param WP_REST_Request $request The incoming REST request.
@return WP_REST_Response|WP_Error

### `handle_get_context(WP_REST_Request $request)`

Handles GET /sparxstar/v1/context.
Resolution priority:
1. ctx_token — validates the signed cross-domain handoff token and restores context.
2. Fallback — returns the current request context via ContextEngine::current().
The ctx_token path implements the inbound side of the cross-domain handoff
defined in spec §F: signature and TTL are verified; an expired or tampered
token returns a 401. Signature verification uses HMAC-SHA256 with the WP auth
salt, so tokens are site-specific.
@param WP_REST_Request $request The incoming REST request.
@return WP_REST_Response|WP_Error

### `check_rate_limit(string $ip)`

Returns true if the given IP address is within its rate-limit window.
Uses a pair of transients: one counter and one fixed window expiry to ensure
the window does not slide on each increment. Allows up to RATE_LIMIT_MAX
requests per 60-second fixed window.
@param string $ip The raw client IP address to check (never stored).

### `sanitize_environment_data(array $data)`

Sanitizes each scalar value in the environment_data array.
@param array<mixed> $data Raw environment data from the request.
@return array<string, string>

### `get_raw_request_ip()`

Returns the raw client IP from REMOTE_ADDR only.
Using REMOTE_ADDR (not X-Forwarded-For) prevents rate-limit bypass via spoofed headers.
This value is used only for rate limiting and fingerprint hashing — never stored.
@return string The sanitized raw client IP address.

