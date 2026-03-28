# SirusMitigationActionRepository

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository`

## Methods

### `insert(array $action)`

SirusMitigationActionRepository - DAL for the sirus_mitigation_actions table.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Provides strict read/write access to the sirus_mitigation_actions table.
All queries are prepared. No business logic lives here.
/
final readonly class SirusMitigationActionRepository
{
    public function __construct(private \wpdb $wpdb)
    {
    }

    /**
Inserts a new mitigation action row.
@param array<string, mixed> $action
@return int Inserted row ID, or 0 on failure.

### `getActiveForDevice(string $deviceId)`

Returns active mitigation actions for a given device ID.
@return array<int, array<string, mixed>>

### `getActiveForSession(string $sessionId)`

Returns active mitigation actions for a given session ID.
@return array<int, array<string, mixed>>

### `expireAction(int $actionId)`

Marks a mitigation action as expired.

### `pruneExpiredActions(int $days = 30)`

Deletes expired and old mitigation action rows.
@return int Number of rows deleted, or 0 on failure.

