<?php

/**
 * Tests for SirusDatabase schema management.
 *
 * Verifies that the sirus_events table DDL is included in the schema
 * update alongside the pre-existing tables.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\SirusDatabase;

/**
 * Validates that SirusDatabase creates the sirus_events table.
 */
final class SirusDatabaseEventsTableTest extends SirusTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['dbDelta_queries'] = [];
        $GLOBALS['wp_options']      = [];
        $GLOBALS['wpdb']            = new \wpdb();
    }

    /**
     * ensure_schema() should run dbDelta and include the sirus_events table DDL.
     */
    public function testEnsureSchemaCreatesEventsTable(): void
    {
        $db = new SirusDatabase($GLOBALS['wpdb']);
        $db->create_or_update_tables();

        $queries = $GLOBALS['dbDelta_queries'];

        $found = false;
        foreach ($queries as $sql) {
            if (stripos($sql, 'sirus_events') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected sirus_events table DDL to be passed to dbDelta.');
    }

    /**
     * The sirus_events DDL should contain the required columns.
     */
    public function testEventsTableDdlContainsRequiredColumns(): void
    {
        $db = new SirusDatabase($GLOBALS['wpdb']);
        $db->create_or_update_tables();

        $events_sql = '';
        foreach ($GLOBALS['dbDelta_queries'] as $sql) {
            if (stripos($sql, 'sirus_events') !== false) {
                $events_sql = $sql;
                break;
            }
        }

        $required_columns = [
            'event_type',
            'timestamp',
            'device_id',
            'session_id',
            'user_id',
            'url',
            'context_json',
            'metrics_json',
            'error_json',
        ];

        foreach ($required_columns as $col) {
            $this->assertStringContainsString($col, $events_sql, "Expected column '{$col}' in sirus_events DDL.");
        }
    }

    /**
     * The sirus_events DDL should declare the required indexes.
     */
    public function testEventsTableDdlContainsRequiredIndexes(): void
    {
        $db = new SirusDatabase($GLOBALS['wpdb']);
        $db->create_or_update_tables();

        $events_sql = '';
        foreach ($GLOBALS['dbDelta_queries'] as $sql) {
            if (stripos($sql, 'sirus_events') !== false) {
                $events_sql = $sql;
                break;
            }
        }

        foreach (['idx_event_type', 'idx_timestamp', 'idx_device', 'idx_session'] as $idx) {
            $this->assertStringContainsString($idx, $events_sql, "Expected index '{$idx}' in sirus_events DDL.");
        }
    }

    /**
     * ensure_schema() should not re-run after the schema version has been written.
     */
    public function testEnsureSchemaSkipsUpdateWhenVersionMatches(): void
    {
        $db = new SirusDatabase($GLOBALS['wpdb']);

        // First run: writes schema and records version.
        $db->ensure_schema();
        $queriesAfterFirst = count($GLOBALS['dbDelta_queries']);

        // Second run: version matches, no further dbDelta calls.
        $db->ensure_schema();
        $queriesAfterSecond = count($GLOBALS['dbDelta_queries']);

        $this->assertSame($queriesAfterFirst, $queriesAfterSecond, 'ensure_schema() should be a no-op when version matches.');
    }

    /**
     * SCHEMA_VERSION must stay a string (semver, e.g. '1.5.0'), and the
     * version-option comparisons in maybe_upgrade_schema()/ensure_schema()
     * must keep comparing it as a string, never (int)-casting it.
     *
     * History: an earlier revision of this class bumped SCHEMA_VERSION to
     * a plain int (1) while comparing via (int) get_option(...). Since
     * (int) '1.5.0' evaluates to 1 in PHP, any site that had already
     * recorded the string '1.5.0' in this option (i.e. anywhere the old
     * activation-hook-triggered path had ever actually run) would have
     * been silently treated as already up to date under that scheme,
     * permanently skipping the schema check. Reverted before merge, but
     * this test exists so the int-cast version of the bug can't quietly
     * come back.
     */
    public function testSchemaVersionIsStringNotInt(): void
    {
        $this->assertIsString(
            SirusDatabase::SCHEMA_VERSION,
            'SCHEMA_VERSION must be a string, not an int -- see class history for why.'
        );

        // A pre-existing site with '1.5.0' already recorded (the only value
        // this constant has ever held) must be treated as already migrated,
        // not re-triggered on every boot.
        update_option('sirus_db_version', '1.5.0');

        $db = new SirusDatabase($GLOBALS['wpdb']);
        $db->ensure_schema();

        $this->assertEmpty(
            $GLOBALS['dbDelta_queries'],
            'A site already on the current SCHEMA_VERSION must not re-run dbDelta().'
        );
    }
}
