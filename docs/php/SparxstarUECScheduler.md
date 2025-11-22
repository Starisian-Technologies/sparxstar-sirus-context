# SparxstarUECScheduler

**Namespace:** `Starisian\SparxstarUEC\cron`

**Full Class Name:** `Starisian\SparxstarUEC\cron\SparxstarUECScheduler`

## Methods

### `schedule_recurring(string $hook, int $interval_in_seconds, array $args = [])`

STARISIAN TECHNOLOGIES CONFIDENTIAL
© 2023–2025 Starisian Technologies. All Rights Reserved.
Unified scheduler that prioritizes Action Scheduler and falls back to WP-Cron.
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
    private const CRON_SCHEDULE_KEY = 'sparxstar_uec_custom_interval';

    /**
Schedule a recurring event safely.

### `clear(string $hook, array $args = [])`

Clear all queued instances of a hook.

### `register_custom_interval(int $interval_in_seconds)`

Register a custom WP-Cron interval globally so it’s available before scheduling.

