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
     * A site still carrying the old semver-string schema version (e.g.
     * '1.5.0', from before SCHEMA_VERSION became an int) must still run the
     * schema update. (int) '1.5.0' collapses to 1, which would collide with
     * this scheme's SCHEMA_VERSION = 1 under an int comparison and cause the
     * update to be silently skipped -- exactly the bug this test guards
     * against.
     */
    public function testEnsureSchemaRunsWhenStoredVersionIsOldSemverString(): void
    {
        update_option('sirus_db_version', '1.5.0');

        $db = new SirusDatabase($GLOBALS['wpdb']);
        $db->ensure_schema();

        $this->assertNotEmpty(
            $GLOBALS['dbDelta_queries'],
            'ensure_schema() must not treat an old semver-string version as up to date.'
        );
        $this->assertSame((string) SirusDatabase::SCHEMA_VERSION, get_option('sirus_db_version'));
    }

    /**
     * Same guard via maybe_upgrade_schema(), the actual boot-time entry point.
     */
    public function testMaybeUpgradeSchemaRunsWhenStoredVersionIsOldSemverString(): void
    {
        update_option('sirus_db_version', '1.5.0');

        $db = new SirusDatabase($GLOBALS['wpdb']);
        $db->maybe_upgrade_schema();

        $this->assertNotEmpty(
            $GLOBALS['dbDelta_queries'],
            'maybe_upgrade_schema() must not treat an old semver-string version as up to date.'
        );
    }
}
