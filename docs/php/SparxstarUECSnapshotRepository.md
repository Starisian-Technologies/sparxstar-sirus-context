# SparxstarUECSnapshotRepository

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECSnapshotRepository`

## Methods

### `get(?string $fingerprint, ?string $device_hash)`

Repository for retrieving snapshots from the database.
Version 2.1: Added Admin-specific retrieval methods.
/
declare(strict_types=1);

namespace Starisian\SparxstarUEC\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\SparxstarUEC\helpers\StarLogger;

final class SparxstarUECSnapshotRepository
{
    /**
Retrieve the latest snapshot for a given stable device identity.
USE CASE: Frontend verification (Current User).
@param string|null $fingerprint The device's stable fingerprint.
@param string|null $device_hash The device's stable hardware hash.
@return array|null The complete snapshot data or null if not found.

### `get_by_user_id(int $user_id)`

Retrieve the latest snapshot by User ID.
USE CASE: Admin Area Snapshot Viewer.

This bypasses the need for the Admin to have the User's fingerprint.
@param int $user_id The WordPress User ID.
@return array|null The complete snapshot data or null if not found.

### `hydrate(array $snapshot_row)`

Helper to rehydrate row data into the expected array format.

### `flush(?string $fingerprint = null, ?string $device_hash = null)`

Flush cache layers.
Updated to accept arguments to prevent fatal errors if called with parameters.
@param string|null $fingerprint Optional fingerprint to target flush.
@param string|null $device_hash Optional hash to target flush.

