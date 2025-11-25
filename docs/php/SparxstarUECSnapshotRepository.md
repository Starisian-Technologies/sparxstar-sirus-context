# SparxstarUECSnapshotRepository

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECSnapshotRepository`

## Methods

### `table()`

Repository for retrieving UEC snapshots.
Version 3.0: Full production build. Supports:
- Frontend lookups by fingerprint + device_hash
- Admin lookups by User ID
- Unified JSON payload column (snapshot_data)
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC\core;

if (!defined('ABSPATH')) {
    exit;
}

use wpdb;
use Starisian\SparxstarUEC\helpers\StarLogger;

final class SparxstarUECSnapshotRepository
{
    /**
Table name helper.

### `get(?string $fingerprint, ?string $device_hash)`

FRONTEND LOOKUP (fingerprint + device hash)

### `get_by_user_id(int $user_id)`

ADMIN LOOKUP (by WordPress User ID ONLY)
This is the correct production method.
The Admin DOES NOT and SHOULD NOT use fingerprint/device.

### `hydrate(array $row)`

Convert DB row → canonical array for Admin / API use.

### `flush(?string $fingerprint = null, ?string $device_hash = null)`

Optional cache flush helper.

