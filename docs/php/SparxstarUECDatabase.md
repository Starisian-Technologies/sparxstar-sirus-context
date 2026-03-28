# SparxstarUECDatabase

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECDatabase`

## Methods

### `__construct(\wpdb $wpdb)`

Handles all direct database interactions for snapshots.
Version 3.0.0: Finalized schema. Uses LONGTEXT for compatibility.
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\SparxstarUEC\helpers\StarLogger;

/**
Database gateway dedicated to the snapshots table for the current site.
/
final readonly class SparxstarUECDatabase
{
    /**
Unqualified table name suffix used for the snapshots table.
/
    private const TABLE_NAME = SPX_ENV_CHECK_DB_TABLE_NAME;

    /**
Default number of days to retain snapshots before cleanup.
/
    private const SNAPSHOT_RETENTION_DAYS = 90;

    /**
Schema version marker used to trigger dbDelta migrations.
Bumped to 3.0.0 to force a final schema check/update on next load.
/
    private const DB_VERSION = '3.0.0';

    /**
Database adapter scoped to the current blog context.
/
    private \wpdb $wpdb;

    /**
Build the database helper without mutating schema state.
@param \wpdb $wpdb WordPress database adapter instance.

### `ensure_schema()`

Check and update the table schema if the DB_VERSION has changed.

### `create_or_update_table()`

Create or update the diagnostics snapshot table using dbDelta.

### `store_snapshot(array $data)`

Insert or update a snapshot.

### `normalize_legacy_snapshot(array $raw)`

Normalise legacy payloads so they can be stored in the current schema.
@param array<string, mixed> $raw Raw snapshot payload.
@return array<string, mixed> Normalised snapshot array.

### `get_latest_snapshot(string $fingerprint, string $device_hash)`

Retrieve the newest snapshot for the supplied identity values.
@param string $fingerprint Unique fingerprint from the client.
@param string $device_hash Hash derived from device details.
@return array<string, mixed>|null Snapshot row data or null when missing.

### `delete_table()`

Drop the snapshots table for the current site context.

### `cleanup_old_snapshots()`

Remove snapshots older than the configured retention window.

### `get_table_name()`

Compose the site-scoped table name using the current blog prefix.

### `get_charset_collate()`

Retrieve the charset/collation string configured for the site database.

### `table_exists(string $table_name)`

Determine whether the snapshots table already exists for the current site.
@param string $table_name Fully-qualified table name including prefix.

