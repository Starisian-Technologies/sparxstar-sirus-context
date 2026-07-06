<?php

/**
 * Tests for ClientTelemetry – error telemetry ingestion, aggregation, and pruning.
 *
 * Architecture under test (spec §G):
 *   - raw reports → sparxstar_client_reports (60-day rolling window)
 *   - aggregation → sparxstar_client_error_stats (permanent history)
 *   - prune() only touches the raw reports table
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\ClientTelemetry;

/**
 * Unit tests for ClientTelemetry::record(), prune(), schedule_cron(),
 * unschedule_cron(), and ensure_schema().
 */
final class ClientTelemetryTest extends SirusTestCase
{
    private \wpdb $wpdb;

    private ClientTelemetry $telemetry;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['wpdb']             = new \wpdb();
        $GLOBALS['wpdb_get_var']     = null;
        $GLOBALS['scheduled_hooks']  = [];
        $GLOBALS['dbDelta_queries']  = [];
        $this->wpdb                  = $GLOBALS['wpdb'];
        $this->telemetry             = new ClientTelemetry($this->wpdb);
    }

    // ── ensure_schema ─────────────────────────────────────────────────────────

    /**
     * ensure_schema() must call dbDelta with SQL containing both table names.
     */
    public function testEnsureSchemaSendsCreateTableStatements(): void
    {
        $this->telemetry->ensure_schema();

        $queries = $GLOBALS['dbDelta_queries'];
        $this->assertNotEmpty($queries, 'Expected dbDelta to be called at least once.');

        $combined = implode(' ', $queries);
        $this->assertStringContainsString('sparxstar_client_reports', $combined);
        $this->assertStringContainsString('sparxstar_client_error_stats', $combined);
    }

    // ── record() — first occurrence (stats row does not exist) ───────────────

    /**
     * record() must insert rows into both the raw reports and stats tables.
     */
    public function testRecordInsertsRawReportAndStats(): void
    {
        $this->telemetry->record('js_error', 'Something broke', ['component' => 'collector'], 'dev-1');

        // queries[0] = INSERT reports, queries[1] = SELECT get_var (existing stats check), queries[2] = INSERT stats
        $this->assertCount(3, $this->wpdb->queries);
        $this->assertSame('wp_sparxstar_client_reports', $this->wpdb->queries[0]['table']);
        $this->assertSame('wp_sparxstar_client_error_stats', $this->wpdb->queries[2]['table']);
        $this->assertSame('dev-1', $this->wpdb->queries[0]['data']['device_id']);
    }

    /**
     * record() must insert a row into the raw reports table.
     */
    public function testRecordInsertsRawReport(): void
    {
        $before = count($this->wpdb->queries);
        $this->telemetry->record('js_error', 'TypeError: null', ['url' => '/test'], 'dev-123');
        $after = count($this->wpdb->queries);

        $this->assertGreaterThan($before, $after, 'Expected at least one query to be issued.');
    }

    /**
     * record() must write to the reports table (table name contains 'client_reports').
     */
    public function testRecordWritesToReportsTable(): void
    {
        $this->telemetry->record('network_error', 'Fetch failed', []);

        $tables = array_column($this->wpdb->queries, 'table');
        $found  = false;
        foreach ($tables as $table) {
            if ($table !== null && str_contains((string) $table, 'client_reports')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected an insert into the client_reports table.');
    }

    /**
     * record() must sanitize the error_type field (table row data).
     */
    public function testRecordSanitizesErrorType(): void
    {
        $this->telemetry->record('<script>alert("x")</script>', 'Some message', []);

        $inserts = array_filter($this->wpdb->queries, fn ($q) => isset($q['data']));
        foreach ($inserts as $insert) {
            foreach ($insert['data'] as $value) {
                if (is_string($value)) {
                    $this->assertStringNotContainsString('<script>', $value);
                }
            }
        }
    }

    /**
     * record() with null device_id must still succeed (nullable column).
     */
    public function testRecordAcceptsNullDeviceId(): void
    {
        // Must not throw.
        $this->telemetry->record('info_event', 'No device', [], null);
        $this->assertGreaterThan(0, count($this->wpdb->queries));
    }

    /**
     * record() produces a consistent error_hash for the same type+message.
     */
    public function testRecordProducesConsistentHashForSameInput(): void
    {
        $this->telemetry->record('js_error', 'Uncaught error', []);
        $first_hash = null;
        foreach ($this->wpdb->queries as $q) {
            if (isset($q['data']['error_hash'])) {
                $first_hash = $q['data']['error_hash'];
                break;
            }
        }

        $this->wpdb->queries = [];
        $this->telemetry->record('js_error', 'Uncaught error', []);
        $second_hash = null;
        foreach ($this->wpdb->queries as $q) {
            if (isset($q['data']['error_hash'])) {
                $second_hash = $q['data']['error_hash'];
                break;
            }
        }

        $this->assertNotNull($first_hash, 'Expected error_hash in first insert.');
        $this->assertSame($first_hash, $second_hash, 'error_hash must be deterministic for identical input.');
    }

    /**
     * Different error_type + error_message combinations must produce different hashes
     * (null-byte separator prevents delimiter-collision attacks).
     */
    public function testRecordProducesDifferentHashesForDifferentInput(): void
    {
        $this->telemetry->record('type_a', 'msg_b', []);
        $first_hash = null;
        foreach ($this->wpdb->queries as $q) {
            if (isset($q['data']['error_hash'])) {
                $first_hash = $q['data']['error_hash'];
                break;
            }
        }

        $this->wpdb->queries = [];
        $this->telemetry->record('type_ab', '_b', []);
        $second_hash = null;
        foreach ($this->wpdb->queries as $q) {
            if (isset($q['data']['error_hash'])) {
                $second_hash = $q['data']['error_hash'];
                break;
            }
        }

        $this->assertNotNull($first_hash);
        $this->assertNotNull($second_hash);
        $this->assertNotSame(
            $first_hash,
            $second_hash,
            'Null-byte separator must prevent type_a+msg_b and type_ab+_b from colliding.'
        );
    }

    // ── record() — subsequent occurrence (stats row already exists) ───────────

    /**
     * When get_var returns a non-null count, record() must update (not insert) the stats row.
     */
    public function testRecordUpdatesExistingAggregateWhenHashAlreadyExists(): void
    {
        $GLOBALS['wpdb_get_var'] = 3;

        $this->telemetry->record('js_error', 'Something broke', [], 'dev-2');

        $this->assertSame('wp_sparxstar_client_error_stats', $this->wpdb->queries[2]['table']);
        $this->assertSame(4, $this->wpdb->queries[2]['data']['count']);
    }

    // ── prune() ───────────────────────────────────────────────────────────────

    /**
     * prune() must issue a DELETE query targeting the raw reports table.
     */
    public function testPruneIssuesDeleteQuery(): void
    {
        $this->telemetry->prune();

        $queries = array_column($this->wpdb->queries, 'query');
        $found   = false;
        foreach ($queries as $sql) {
            if (is_string($sql) && str_contains(strtoupper($sql), 'DELETE')
                && str_contains($sql, 'client_reports')
            ) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected a DELETE query targeting the client_reports table.');
    }

    /**
     * prune() must only touch the raw reports table, not stats or other tables.
     */
    public function testPruneDeletesOnlyFromRawReportsTable(): void
    {
        $this->telemetry->prune();

        $query = $this->wpdb->queries[0]['query'] ?? '';
        $this->assertStringContainsString('wp_sparxstar_client_reports', $query);
        $this->assertStringNotContainsString('postmeta', $query);
    }

    /**
     * prune() must use a 60-day interval in the DELETE statement.
     */
    public function testPruneUsesRetentionPeriodOf60Days(): void
    {
        $this->telemetry->prune();

        $queries  = array_column($this->wpdb->queries, 'query');
        $combined = implode(' ', array_filter($queries, is_string(...)));

        $this->assertStringContainsString('60', $combined);
    }

    // ── schedule_cron() ───────────────────────────────────────────────────────

    /**
     * schedule_cron() must register the cron event when it is not already scheduled.
     */
    public function testScheduleCronRegistersEventWhenNotScheduled(): void
    {
        $GLOBALS['scheduled_hooks'] = [];

        ClientTelemetry::schedule_cron();

        $found = false;
        foreach ($GLOBALS['scheduled_hooks'] as $entry) {
            if (($entry['hook'] ?? '') === ClientTelemetry::CRON_HOOK) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected cron hook to be scheduled.');
    }

    /**
     * schedule_cron() is idempotent — calling it twice does not duplicate the event.
     */
    public function testScheduleCronIsIdempotent(): void
    {
        $GLOBALS['scheduled_hooks'] = [];

        ClientTelemetry::schedule_cron();
        ClientTelemetry::schedule_cron();

        $count = 0;
        foreach ($GLOBALS['scheduled_hooks'] as $entry) {
            if (($entry['hook'] ?? '') === ClientTelemetry::CRON_HOOK) {
                $count++;
            }
        }

        $this->assertSame(1, $count, 'schedule_cron() must not register the event twice.');
    }

    // ── unschedule_cron() ─────────────────────────────────────────────────────

    /**
     * unschedule_cron() removes a previously scheduled event.
     */
    public function testUnscheduleCronRemovesScheduledEvent(): void
    {
        $GLOBALS['scheduled_hooks'] = [];

        ClientTelemetry::schedule_cron();
        ClientTelemetry::unschedule_cron();

        $found = false;
        foreach ($GLOBALS['scheduled_hooks'] as $entry) {
            if (($entry['hook'] ?? '') === ClientTelemetry::CRON_HOOK) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found, 'Expected the cron hook to have been removed.');
    }

    /**
     * unschedule_cron() is safe to call when no event is scheduled.
     */
    public function testUnscheduleCronIsSafeWhenNotScheduled(): void
    {
        $GLOBALS['scheduled_hooks'] = [];

        // Must not throw.
        ClientTelemetry::unschedule_cron();
        $this->assertTrue(true);
    }

    // ── CRON_HOOK constant ────────────────────────────────────────────────────

    /**
     * CRON_HOOK constant must be the expected string.
     */
    public function testCronHookConstant(): void
    {
        $this->assertSame('sparxstar_sirus_telemetry_prune', ClientTelemetry::CRON_HOOK);
    }
}
