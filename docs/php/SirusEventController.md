# SirusEventController

**Namespace:** `Starisian\Sparxstar\Sirus\api`

**Full Class Name:** `Starisian\Sparxstar\Sirus\api\SirusEventController`

## Description

SirusEventController - REST API endpoint for Sirus observability events.
Route: POST /wp-json/sirus/v1/event
Accepts the canonical Sirus event payload, validates required fields,
sanitizes all inputs, and delegates persistence to SirusEventRepository.
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
use Starisian\Sparxstar\Sirus\helpers\IpAnonymizer;
use Starisian\Sparxstar\Sirus\helpers\SirusRateLimit;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

/**
Registers and handles the POST /sirus/v1/event REST route.
All input is validated and sanitized before being passed to the repository.

## Methods

### `verify_rest_nonce(WP_REST_Request $request)`

SirusEventController - REST API endpoint for Sirus observability events.
Route: POST /wp-json/sirus/v1/event
Accepts the canonical Sirus event payload, validates required fields,
sanitizes all inputs, and delegates persistence to SirusEventRepository.
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
use Starisian\Sparxstar\Sirus\helpers\IpAnonymizer;
use Starisian\Sparxstar\Sirus\helpers\SirusRateLimit;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

/**
Registers and handles the POST /sirus/v1/event REST route.
All input is validated and sanitized before being passed to the repository.
/
final class SirusEventController
{
    private const NAMESPACE = 'sirus/v1';

    /**
@param SirusEventRepository $repository The event data access layer.
@param SirusMitigationCoordinator|null $coordinator Optional mitigation coordinator.
@param SirusRateLimit|null $rateLimiter Optional rate limiter.
/
    public function __construct(
        private readonly SirusEventRepository $repository,
        private readonly ?SirusMitigationCoordinator $coordinator = null,
        private readonly ?SirusRateLimit $rateLimiter = null,
    ) {
    }

    /**
Permission callback: verifies the WordPress REST nonce before accepting an event.
Accepts the nonce from the `X-WP-Nonce` header (standard REST path used by
fetch/XHR) **or** from a `_wpnonce` query-string parameter (required for
`navigator.sendBeacon()`, which cannot set custom headers).
@param WP_REST_Request $request The current REST request.
@return bool|WP_Error True if the nonce is valid, otherwise WP_Error(403).

### `register_routes()`

Registers the REST API route for event ingestion.

### `create_event(WP_REST_Request $request)`

Handles POST /sirus/v1/event.
Sanitizes, validates, and persists the incoming event payload.
@param WP_REST_Request $request Incoming REST request.
@return WP_REST_Response|WP_Error

### `validate_event_type(mixed $value)`

Validates that the event_type is one of the canonical enum values.
@param mixed $value The raw value from the request.
@return bool|WP_Error

### `is_valid_device_id(string $device_id)`

Validates that device_id is in an acceptable format.
Must be 8–64 characters, alphanumeric and hyphens only,
and contain at least one alphanumeric character.

### `sanitize_json_object(array $data)`

Recursively sanitizes a JSON-origin associative array.
Strings are sanitized with sanitize_text_field.
Integers and floats are cast and preserved.
Booleans and nulls are preserved.
Nested arrays are handled recursively.
@param array<mixed> $data Raw associative array from REST request.
@return array<string, mixed>

