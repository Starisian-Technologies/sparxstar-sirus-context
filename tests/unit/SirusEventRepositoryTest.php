<?php

/**
 * Tests for SirusEventRepository – the Sirus observability event DAL.
 *
 * Uses the in-memory wpdb stub from the unit test bootstrap.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\SirusEventRepository;

/**
 * Validates SirusEventRepository query construction and return behaviour.
 */
final class SirusEventRepositoryTest extends SirusTestCase
{
    /** @var \wpdb */
    private \wpdb $wpdb;

    private SirusEventRepository $repo;

    protected function setUp(): void
    {
        // Reset the global wpdb stub before each test.
        $GLOBALS['wpdb'] = new \wpdb();
        $GLOBALS['wpdb_get_results'] = [];

        $this->wpdb = $GLOBALS['wpdb'];
        $this->repo  = new SirusEventRepository($this->wpdb);
    }

    // ── insert ────────────────────────────────────────────────────────────────

    /**
     * A complete event payload should produce a single insert and return the ID.
     */
    public function testInsertRecordsRowAndReturnsId(): void
    {
        $event = [
            'event_type' => 'js_error',
            'timestamp'  => 1710000000,
            'device_id'  => 'dev-abc',
            'session_id' => 'sess-xyz',
            'user_id'    => 42,
            'url'        => '/checkout',
            'context'    => ['browser' => 'Safari', 'os' => 'iOS'],
            'metrics'    => ['latency_ms' => 1200],
            'error'      => ['message' => 'undefined is not a function'],
        ];

        $id = $this->repo->insert($event);

        $this->assertSame(1, $id);
        $this->assertCount(1, $this->wpdb->queries);

        $query = $this->wpdb->queries[0];
        $this->assertSame('wp_sirus_events', $query['table']);
        $this->assertSame('js_error', $query['data']['event_type']);
        $this->assertSame(1710000000, $query['data']['timestamp']);
        $this->assertSame('dev-abc', $query['data']['device_id']);
        $this->assertSame('sess-xyz', $query['data']['session_id']);
        $this->assertSame(42, $query['data']['user_id']);
        $this->assertSame('/checkout', $query['data']['url']);
    }

    /**
     * An event without optional context/metrics/error fields should still insert.
     */
    public function testInsertWithMinimalPayload(): void
    {
        $event = [
            'event_type' => 'session_start',
            'timestamp'  => 1710000001,
            'device_id'  => 'dev-min',
            'session_id' => 'sess-min',
        ];

        $id = $this->repo->insert($event);

        $this->assertSame(1, $id);
        $this->assertSame('{}', $this->wpdb->queries[0]['data']['context_json']);
        $this->assertNull($this->wpdb->queries[0]['data']['metrics_json']);
        $this->assertNull($this->wpdb->queries[0]['data']['error_json']);
    }

    /**
     * When wpdb returns insert_id = 0 (i.e. on failure), insert() returns 0.
     */
    public function testInsertReturnsZeroOnDbFailure(): void
    {
        // Simulate a failed insert by pre-setting insert_id to 0.
        $this->wpdb->insert_id = 0;

        // Re-create repo with the pre-zeroed stub.
        $repo = new SirusEventRepository($this->wpdb);

        // Inject the failure by overriding insert to return false.
        $failingWpdb = new class extends \wpdb {
            public function insert(string $table, array $data): bool
            {
                return false;
            }
        };

        $repo   = new SirusEventRepository($failingWpdb);
        $result = $repo->insert([
            'event_type' => 'js_error',
            'timestamp'  => 1710000002,
            'device_id'  => 'x',
            'session_id' => 'y',
        ]);

        $this->assertSame(0, $result);
    }

    // ── getRecentEvents ───────────────────────────────────────────────────────

    /**
     * getRecentEvents should issue a SELECT ordered by timestamp DESC.
     */
    public function testGetRecentEventsIssuesToTimestampDescQuery(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 2, 'event_type' => 'js_error', 'timestamp' => 1710000002],
            ['id' => 1, 'event_type' => 'session_start', 'timestamp' => 1710000001],
        ];

        $rows = $this->repo->getRecentEvents(50);

        $this->assertCount(2, $rows);
        $this->assertSame('js_error', $rows[0]['event_type']);

        // Confirm the query references the events table.
        $last_query = end($this->wpdb->queries);
        $this->assertStringContainsString('sirus_events', (string) $last_query['query']);
        $this->assertStringContainsString('50', (string) $last_query['query']);
    }

    /**
     * getRecentEvents returns an empty array when no rows are found.
     */
    public function testGetRecentEventsReturnsEmptyArrayOnNoRows(): void
    {
        $GLOBALS['wpdb_get_results'] = [];
        $rows = $this->repo->getRecentEvents();
        $this->assertSame([], $rows);
    }

    // ── getEventsByType ───────────────────────────────────────────────────────

    /**
     * getEventsByType should embed the event_type and timestamp in the query.
     */
    public function testGetEventsByTypeFiltersCorrectly(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 5, 'event_type' => 'api_error', 'timestamp' => 1710000010],
        ];

        $rows = $this->repo->getEventsByType('api_error', 1710000000);

        $this->assertCount(1, $rows);
        $this->assertSame('api_error', $rows[0]['event_type']);

        $last_query = end($this->wpdb->queries);
        $this->assertStringContainsString('sirus_events', (string) $last_query['query']);
    }

    // ── getActiveSessions ─────────────────────────────────────────────────────

    /**
     * getActiveSessions should issue a COUNT(DISTINCT session_id) query.
     */
    public function testGetActiveSessionsIssuesCountDistinctQuery(): void
    {
        // wpdb::get_var returns null from the stub — cast to 0.
        $count = $this->repo->getActiveSessions(900);

        $this->assertSame(0, $count);
        $last_query = end($this->wpdb->queries);
        $this->assertStringContainsString('COUNT(DISTINCT session_id)', (string) $last_query['query']);
        $this->assertStringContainsString('sirus_events', (string) $last_query['query']);
    }

    // ── getTopFailingUrls ─────────────────────────────────────────────────────

    /**
     * getTopFailingUrls should return rows including error_count and affected_sessions.
     */
    public function testGetTopFailingUrlsReturnsExpectedRows(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['url' => '/checkout', 'error_count' => 15, 'affected_sessions' => 8],
            ['url' => '/login', 'error_count' => 4, 'affected_sessions' => 2],
        ];

        $rows = $this->repo->getTopFailingUrls(1710000000, 5);

        $this->assertCount(2, $rows);
        $this->assertSame('/checkout', $rows[0]['url']);
        $this->assertSame(15, (int) $rows[0]['error_count']);
    }

    // ── getRecentErrors ───────────────────────────────────────────────────────

    /**
     * getRecentErrors should only return error-type events.
     */
    public function testGetRecentErrorsFiltersToErrorTypes(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['event_type' => 'js_error', 'context_json' => '{}', 'url' => '/home'],
        ];

        $rows = $this->repo->getRecentErrors(1710000000, 10);

        $this->assertCount(1, $rows);
        $last_query = end($this->wpdb->queries);
        $this->assertStringContainsString("'js_error'", (string) $last_query['query']);
    }

    // ── VALID_EVENT_TYPES constant ────────────────────────────────────────────

    /**
     * VALID_EVENT_TYPES should contain all six canonical event types.
     */
    public function testValidEventTypesContainsAllExpectedTypes(): void
    {
        $expected = [
            'js_error',
            'api_error',
            'network_issue',
            'capability_failure',
            'session_start',
            'session_end',
        ];

        foreach ($expected as $type) {
            $this->assertContains($type, SirusEventRepository::VALID_EVENT_TYPES);
        }

        $this->assertCount(count($expected), SirusEventRepository::VALID_EVENT_TYPES);
    }
}
