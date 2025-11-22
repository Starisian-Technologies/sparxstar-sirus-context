# SparxstarUECSnapshotRepository

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECSnapshotRepository`

## Methods

### `get(?string $fingerprint, ?string $device_hash)`

Repository for retrieving snapshots from the database.
Version 2.0: Aligned with fingerprint-first identity architecture.
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
@param string|null $fingerprint The device's stable fingerprint.
@param string|null $device_hash The device's stable hardware hash.
@return array|null The complete snapshot data or null if not found.

### `flush()`

Flush any cache layers for a specific device identity.
(Placeholder for future object caching).

