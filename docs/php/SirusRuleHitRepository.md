# SirusRuleHitRepository

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository`

## Methods

### `insert(array $hit)`

SirusRuleHitRepository - DAL for the sirus_rule_hits table.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Provides strict read/write access to the sirus_rule_hits table.
All queries are prepared. No business logic lives here.
/
final readonly class SirusRuleHitRepository
{
    public function __construct(private \wpdb $wpdb)
    {
    }

    /**
Inserts a new rule hit row.
@param array<string, mixed> $hit
@return int Inserted row ID, or 0 on failure.

### `incrementHit(string $ruleKey, string $deviceId = '', string $sessionId = '')`

Increments the hit_count for an existing (rule_key, device_id, session_id) row.
If no row exists, inserts a new one.

### `getRecentHits(int $limit = 100)`

Returns the most recent rule hits ordered by created_at descending.
@return array<int, array<string, mixed>>

### `getHitsBySeverity(string $severity, int $since)`

Returns rule hits filtered by severity since a given timestamp.
@return array<int, array<string, mixed>>

### `pruneOldHits(int $days = 30)`

Deletes rule hit rows older than the given number of days.
@return int Number of rows deleted, or 0 on failure.

