# SparxstarUECRESTController

**Namespace:** `Starisian\SparxstarUEC\api`

**Full Class Name:** `Starisian\SparxstarUEC\api\SparxstarUECRESTController`

## Methods

### `register_routes()`

REST controller for handling environment diagnostics.
Version 2.1: Added deep logging to debug User ID mismatches.
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC\api;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Starisian\SparxstarUEC\StarUserUtils;
use Starisian\SparxstarUEC\core\SparxstarUECDatabase;
use Starisian\SparxstarUEC\services\SparxstarUECGeoIPService;
use Starisian\SparxstarUEC\helpers\StarLogger; // Import Logger

if (! defined('ABSPATH')) {
    exit;
}

final readonly class SparxstarUECRESTController
{
    public function __construct(private SparxstarUECDatabase $database) {}

    /**
Register REST endpoints for logging snapshots and recorder events.

### `handle_log_request(WP_REST_Request $request)`

Handle the incoming snapshot payload.

### `handle_recorder_log(WP_REST_Request $request)`

Handle incoming recorder event logs from external plugins.

@param WP_REST_Request $request The incoming request
@return WP_REST_Response The response

### `map_and_normalize_snapshot(array $payload)`

Transform the raw incoming payload into the canonical database schema.

