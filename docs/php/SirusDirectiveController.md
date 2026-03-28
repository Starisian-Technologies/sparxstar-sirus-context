# SirusDirectiveController

**Namespace:** `Starisian\Sparxstar\Sirus\api`

**Full Class Name:** `Starisian\Sparxstar\Sirus\api\SirusDirectiveController`

## Description

SirusDirectiveController - REST API endpoints for Sirus adaptive directives.
Routes:
  GET /sirus/v1/directives  — returns active directives for a device/session.
  GET /sirus/v1/rule-hits   — admin-only recent rule hits.
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
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

/**
Registers and handles the directive and rule-hit REST routes.

## Methods

### `register_routes()`

SirusDirectiveController - REST API endpoints for Sirus adaptive directives.
Routes:
  GET /sirus/v1/directives  — returns active directives for a device/session.
  GET /sirus/v1/rule-hits   — admin-only recent rule hits.
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
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

/**
Registers and handles the directive and rule-hit REST routes.
/
final class SirusDirectiveController
{
    private const NAMESPACE = 'sirus/v1';

    public function __construct(
        private readonly SirusMitigationCoordinator $coordinator,
        private readonly SirusRuleHitRepository $ruleHitRepo,
    ) {
    }

    /**
Registers the REST API routes.

### `get_directives(WP_REST_Request $request)`

GET /sirus/v1/directives
Returns active directives for the requesting device/session.
@param WP_REST_Request $request Incoming REST request.
@return WP_REST_Response|WP_Error

### `get_rule_hits(WP_REST_Request $request)`

GET /sirus/v1/rule-hits
Admin-only. Returns recent rule hits.
@param WP_REST_Request $request Incoming REST request.
@return WP_REST_Response|WP_Error

### `admin_permission_callback()`

Permission callback for admin-only endpoints.

