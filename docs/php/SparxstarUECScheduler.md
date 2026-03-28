# SparxstarUECScheduler

**Namespace:** `Starisian\SparxstarUEC\cron`

**Full Class Name:** `Starisian\SparxstarUEC\cron\SparxstarUECScheduler`

## Methods

### `schedule_recurring(string $hook, int $interval_in_seconds, array $args = [])`

STARISIAN TECHNOLOGIES CONFIDENTIAL
© 2023–2025 Starisian Technologies. All Rights Reserved.
Unified scheduler.
Version 3.1:
- Fixes Static Analysis errors regarding Action Scheduler.
- Prevents "Phantom Schedule" bugs by mapping to standard WP-Cron keys.
@package Starisian\SparxstarUEC\cron
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC\cron;

use Starisian\SparxstarUEC\helpers\StarLogger;

if (! defined('ABSPATH')) {
    exit;
}

final class SparxstarUECScheduler
{
    /**
Schedule a recurring event safely.
@param string $hook The action hook to execute.
@param int $interval_in_seconds How often to run (e.g., 3600, 86400).
@param array $args Arguments to pass to the hook.

### `clear(string $hook, array $args = [])`

Clear all queued instances of a hook.

### `get_wp_schedule_key(int $seconds)`

Helper: Maps raw seconds to standard WordPress schedule keys.
This avoids the need to dynamically register custom intervals.

