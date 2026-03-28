# SirusDatabase

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\SirusDatabase`

## Methods

### `__construct(private \wpdb $wpdb)`

SirusDatabase - Schema management for all Sirus database tables.
Manages:
- sirus_devices                 — device continuity records
- sparxstar_client_reports      — raw client error telemetry (60-day rolling)
- sparxstar_client_error_stats  — aggregated error statistics (permanent)
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Handles creation and migration of all Sirus database tables.
Uses dbDelta() for safe, idempotent schema management.
/
final readonly class SirusDatabase
{
    /** Current schema version. */
    private const SCHEMA_VERSION = '1.5.0';

    /** Option key used to track the installed schema version. */
    private const VERSION_OPTION = 'sirus_db_version';

    /**
@param \wpdb $wpdb WordPress database abstraction object.

### `ensure_schema()`

Ensures the schema is at the current version, running an update only if needed.

### `create_or_update_tables()`

Creates or alters all Sirus tables using dbDelta.

### `create_devices_table(string $charset_collate)`

Creates or updates the sirus_devices table.
@param string $charset_collate DB charset/collation string.

### `create_telemetry_tables(string $charset_collate)`

Creates or updates the client telemetry tables.
sparxstar_client_reports      — raw reports, 60-day rolling window
sparxstar_client_error_stats  — aggregated stats, permanent
@param string $charset_collate DB charset/collation string.

### `create_events_table(string $charset_collate)`

Creates or updates the sirus_events observability table.
Stores frontend error, network, session and capability events.
JSON fields hold optional context, metrics and error payloads.
@param string $charset_collate DB charset/collation string.

### `create_rule_hits_table(string $charset_collate)`

Creates or updates the sirus_rule_hits table.
@param string $charset_collate DB charset/collation string.

### `create_mitigation_actions_table(string $charset_collate)`

Creates or updates the sirus_mitigation_actions table.
@param string $charset_collate DB charset/collation string.

### `create_event_aggregates_table(string $charset_collate)`

Creates or updates the sirus_event_aggregates pre-aggregated summary table.
@param string $charset_collate DB charset/collation string.

