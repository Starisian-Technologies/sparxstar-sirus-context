<?php

/**
 * Tests for SirusEventAggregator – 5-minute cron-based event aggregation engine.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\SirusEventAggregator;

/**
 * Validates SirusEventAggregator query delegation and cron scheduling.
 */
final class SirusEventAggregatorTest extends SirusTestCase
{
    /** @var \wpdb */
    private \wpdb $wpdb;

    private SirusEventAggregator $aggregator;

    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['wpdb_get_results'] = [];
        $GLOBALS['scheduled_hooks']  = [];
        $GLOBALS['transients']       = [];

        $this->wpdb       = $GLOBALS['wpdb'];
        $this->aggregator = new SirusEventAggregator($this->wpdb);
    }

    // ── getAggregates ─────────────────────────────────────────────────────────

    /**
     * getAggregates() should return an array from wpdb->get_results().
     */
    public function testGetAggregatesReturnsArray(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            [
                'id'            => 1,
                'bucket_start'  => 1710000000,
                'bucket_size'   => '5m',
                'site_id'       => 1,
                'event_type'    => 'js_error',
                'browser'       => 'Chrome',
                'device_type'   => 'desktop',
                'network'       => '4g',
                'event_count'   => 42,
                'session_count' => 10,
            ],
        ];

        $rows = $this->aggregator->getAggregates('5m', 1710000000 - 300);

        $this->assertIsArray($rows);
        $this->assertCount(1, $rows);
        $this->assertSame('5m', $rows[0]['bucket_size']);
        $this->assertSame(42, (int) $rows[0]['event_count']);
    }

    /**
     * getAggregates() should return an empty array when no rows are found.
     */
    public function testGetAggregatesReturnsEmptyArrayOnNoRows(): void
    {
        $GLOBALS['wpdb_get_results'] = [];
        $rows = $this->aggregator->getAggregates('1h', time() - 3600);
        $this->assertSame([], $rows);
    }

    /**
     * getAggregates() query should reference the aggregates table and contain the bucket_size.
     */
    public function testGetAggregatesIssuesToCorrectTable(): void
    {
        $GLOBALS['wpdb_get_results'] = [];
        $this->aggregator->getAggregates('1h', 1710000000, 50);

        $last_query = end($this->wpdb->queries);
        $this->assertStringContainsString('sirus_event_aggregates', (string) $last_query['query']);
    }

    // ── prune ─────────────────────────────────────────────────────────────────

    /**
     * prune() should issue a DELETE query against the aggregates table.
     */
    public function testPruneIssuesDeleteQuery(): void
    {
        $this->aggregator->prune(7);

        $last_query = end($this->wpdb->queries);
        $this->assertStringContainsString('DELETE', strtoupper((string) $last_query['query']));
        $this->assertStringContainsString('sirus_event_aggregates', (string) $last_query['query']);
        $this->assertStringContainsString('bucket_start', (string) $last_query['query']);
    }

    /**
     * prune() threshold should be 7 days in the past.
     */
    public function testPruneCutoffIsSevenDaysAgo(): void
    {
        $this->aggregator->prune(7);

        $last_query = end($this->wpdb->queries);
        $sql        = (string) $last_query['query'];

        preg_match('/bucket_start < (\d+)/', $sql, $matches);
        $this->assertNotEmpty($matches[1] ?? '', 'Expected numeric threshold in DELETE query.');

        $threshold = (int) ($matches[1] ?? 0);
        $expected  = time() - (7 * DAY_IN_SECONDS);

        // Allow 10 seconds tolerance for test execution time.
        $this->assertGreaterThanOrEqual($expected - 10, $threshold);
        $this->assertLessThanOrEqual($expected + 10, $threshold);
    }

    // ── schedule_cron ─────────────────────────────────────────────────────────

    /**
     * schedule_cron() should register the cron hook when not already scheduled.
     */
    public function testScheduleCronRegistersHook(): void
    {
        $GLOBALS['scheduled_hooks'] = [];

        SirusEventAggregator::schedule_cron();

        $found = false;
        foreach ($GLOBALS['scheduled_hooks'] as $entry) {
            if (($entry['hook'] ?? '') === SirusEventAggregator::CRON_HOOK) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected aggregation cron to be scheduled.');
    }

    /**
     * schedule_cron() should not duplicate the hook if already scheduled.
     */
    public function testScheduleCronDoesNotDuplicateHook(): void
    {
        $GLOBALS['scheduled_hooks'] = [];

        SirusEventAggregator::schedule_cron();
        SirusEventAggregator::schedule_cron();

        $count = 0;
        foreach ($GLOBALS['scheduled_hooks'] as $entry) {
            if (($entry['hook'] ?? '') === SirusEventAggregator::CRON_HOOK) {
                $count++;
            }
        }

        $this->assertSame(1, $count, 'Cron hook should not be duplicated.');
    }

    // ── unschedule_cron ───────────────────────────────────────────────────────

    /**
     * unschedule_cron() should remove the scheduled hook.
     */
    public function testUnscheduleCronRemovesHook(): void
    {
        $GLOBALS['scheduled_hooks'] = [];

        SirusEventAggregator::schedule_cron();
        SirusEventAggregator::unschedule_cron();

        $found = false;
        foreach ($GLOBALS['scheduled_hooks'] as $entry) {
            if (($entry['hook'] ?? '') === SirusEventAggregator::CRON_HOOK) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found, 'Expected aggregation cron to be removed after unschedule.');
    }

    // ── compile ───────────────────────────────────────────────────────────────

    /**
     * compile() should issue at least two queries (one per bucket size).
     */
    public function testCompileIssuesTwoQueries(): void
    {
        $beforeCount = count($this->wpdb->queries);

        $this->aggregator->compile();

        $afterCount = count($this->wpdb->queries);
        $this->assertGreaterThanOrEqual($beforeCount + 2, $afterCount, 'Expected at least 2 queries for 5m and 1h buckets.');
    }

    /**
     * compile() queries should reference the aggregates table.
     */
    public function testCompileQueriesTargetAggregatesTable(): void
    {
        $this->aggregator->compile();

        $found = false;
        foreach ($this->wpdb->queries as $q) {
            if (strpos((string) $q['query'], 'sirus_event_aggregates') !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected compile() to query sirus_event_aggregates.');
    }
}
