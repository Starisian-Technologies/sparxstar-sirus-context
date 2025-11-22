# SparxstarUECDatabase

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECDatabase`

## Methods

### `maybe_update_table_schema()`

Handles all direct database interactions for snapshots.
Version 2.1: Backwards-compatible normalization of legacy snapshot payloads
into fingerprint + device_hash + session_id + snapshot_data.
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

    // DB Version 2.0 introduces fingerprint and device_hash for stable identity.
    private const DB_VERSION = '2.0';

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
This schema is designed for the fingerprint-first identity architecture.

### `store_snapshot(array $data)`

Insert or update a snapshot.
ACCEPTS EITHER:
- New normalized format:
  [
    'user_id'    => ?int,
    'fingerprint'=> string,
    'device_hash'=> string,
    'session_id' => ?string,
    'data'       => array,
    'updated_at' => 'Y-m-d H:i:s' UTC
  ]
- OR the existing merged "legacy" snapshot array:
  [
    'server_side_data'   => [...],
    'client_side_data'   => [...],
    'client_hints_data'  => [...],
    'user_id'            => '1',
    'session_id'         => '',
    'updated_at'         => '2025-11-16 22:22:17',
    ...
  ]

### `normalize_legacy_snapshot(array $raw)`

Normalize the current merged snapshot array (what you already have working)
into the new fingerprint/device_hash/session_id/data structure.
This is where we:
- Use identifiers.visitorId as fingerprint (FingerprintJS).
- Derive device_hash from client_hints_data or device.full.
- Fix the session_id mapping.

### `get_latest_snapshot(string $fingerprint, string $device_hash)`

Retrieve the newest snapshot for a given fingerprint and device_hash.

### `cleanup_old_snapshots()`

Remove snapshots older than the configured retention period.

