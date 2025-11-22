# SparxstarUECKernel

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECKernel`

## Methods

### `__construct(wpdb $wpdb)`

Kernel: Constructs and wires all service objects.
This is the dependency injection container for the plugin.
It builds all services with their dependencies and exposes them
to the orchestrator. No WordPress hooks or side effects here.
@package SparxstarUserEnvironmentCheck
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC\core;

if (! defined('ABSPATH')) {
    exit;
}

use wpdb;
use Starisian\SparxstarUEC\admin\SparxstarUECAdmin;
use Starisian\SparxstarUEC\api\SparxstarUECRESTController;
use Starisian\SparxstarUEC\includes\SparxstarUECSessionManager;

/**
Kernel: Pure dependency wiring and service construction.
/
final readonly class SparxstarUECKernel
{
    private SparxstarUECDatabase $database;

    private SparxstarUECRESTController $api;

    private SparxstarUECAssetManager $asset_manager;

    private SparxstarUECSessionManager $session_manager;

    private SparxstarUECAdmin $admin;

    /**
Construct and wire all service objects.
@param wpdb $wpdb WordPress database object.

### `get_database()`

Get the database service.

### `get_api()`

Get the REST API controller.

### `get_assets()`

Get the asset manager.

### `get_session()`

Get the session manager.

### `get_admin()`

Get the admin interface handler.

