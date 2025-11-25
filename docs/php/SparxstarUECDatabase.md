# SparxstarUECDatabase

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECDatabase`

## Methods

### `maybe_update_table_schema()`

Handles all direct database interactions for snapshots.
Version 3.0.0: Finalized schema. Uses LONGTEXT for compatibility.
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\SparxstarUEC\helpers\StarLogger;

final readonly class SparxstarUECDatabase
{
    private const TABLE_NAME              = SPX_ENV_CHECK_DB_TABLE_NAME;

    private const SNAPSHOT_RETENTION_DAYS = 90;

    // Bumped to 3.0.0 to force a final schema check/update on next load.
    private const DB_VERSION = '3.0.0';

    private \wpdb $wpdb;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->maybe_update_table_schema();
    }

    /**
Check and update the table schema if the DB_VERSION has changed.

### `create_or_update_table()`

Create or update the diagnostics snapshot table using dbDelta.

### `store_snapshot(array $data)`

Insert or update a snapshot.

