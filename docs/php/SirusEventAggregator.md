# SirusEventAggregator

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\SirusEventAggregator`

## Methods

### `schedule_cron()`

SirusEventAggregator - Compiles raw sirus_events into pre-aggregated summary rows.
Runs on a 5-minute cron. Dashboard queries read from aggregates for performance.
Bucket sizes: '5m' (5-minute) and '1h' (1-hour).
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Compiles raw sirus_events into pre-aggregated summary rows.
/
final readonly class SirusEventAggregator
{
    public const CRON_HOOK = 'sirus_aggregate_events';

    public const CRON_INTERVAL_SEC = 300; // 5 minutes

    private const BUCKET_5M = '5m';

    private const BUCKET_1H = '1h';

    public function __construct(private \wpdb $wpdb)
    {
    }

    /**
Schedules the 5-minute aggregation cron if not already scheduled.

### `unschedule_cron()`

Removes the scheduled cron event.

### `compile()`

Compiles events from the current and previous windows into aggregate rows.
Both the current and the immediately preceding bucket are compiled on every
cron run, so a late or missed cron interval will not permanently skip a bucket.
Safe to call repeatedly — uses INSERT ... ON DUPLICATE KEY UPDATE.

### `getAggregates(string $bucket_size, int $since, int $limit = 200)`

Returns aggregate rows for a given bucket size since a given timestamp.
@param string $bucket_size '5m' or '1h'
@param int $since Unix timestamp lower bound for bucket_start.
@param int $limit Max rows to return.
@return array<int, array<string, mixed>>

### `prune(int $days = 7)`

Prunes aggregate rows older than $days days.

### `compile_bucket(string $bucket_size, int $window_secs, int $now = 0)`

Compiles events from the given bucket window into aggregate rows.
@param string $bucket_size Bucket label ('5m' or '1h').
@param int $window_secs Window size in seconds.
@param int $now Reference timestamp (defaults to current time if 0).

