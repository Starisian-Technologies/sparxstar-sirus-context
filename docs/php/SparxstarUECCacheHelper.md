# SparxstarUECCacheHelper

**Namespace:** `Starisian\SparxstarUEC\includes`

**Full Class Name:** `Starisian\SparxstarUEC\includes\SparxstarUECCacheHelper`

## Methods

### `get(string $cache_key)`

Provides a caching layer for environment snapshots via the WordPress object cache.
/
declare(strict_types=1);

namespace Starisian\SparxstarUEC\includes;

if (! defined('ABSPATH')) {
    exit;
}

final class SparxstarUECCacheHelper
{
    private const GROUP = 'sparxstar_env';

    private const TTL = DAY_IN_SECONDS;

    /**
Retrieve a snapshot from the object cache.
@param string $cache_key The deterministic key for the resource.
@return array|null Null on cache miss, array on hit.

### `set(string $cache_key, array $snapshot = [])`

Store a snapshot in the object cache.
@param string $cache_key The key to store the data under.
@param array $snapshot The snapshot data to store.

### `delete(string $cache_key)`

Invalidate a snapshot from the object cache.
@param string $cache_key The key to delete.

### `make_key(?int $user_id, ?string $session_id, string $ip_hash)`

Build a deterministic cache key.

