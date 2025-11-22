# SparxstarUECSessionManager

**Namespace:** `Starisian\SparxstarUEC\includes`

**Full Class Name:** `Starisian\SparxstarUEC\includes\SparxstarUECSessionManager`

## Methods

### `set_all(array $data)`

Set multiple values in the session at once.

### `get(string $key, ?string $default = null)`

Get a single value from the session.

### `lookup(string $key, ?int $user_id, ?string $session_id, ?string $default = null)`

Looks up a value for ANY USER/SESSION by querying the historical database record.

### `get_session_id()`

Retrieve the active PHP session identifier when available.

### `clear_snapshot_flag()`

Clear the snapshot creation flag to allow re-generation.
Used when admin views settings but no snapshot exists.

