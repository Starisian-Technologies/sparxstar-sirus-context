<?php

/**
 * Tests for SirusRuleHitRepository.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;

final class SirusRuleHitRepositoryTest extends SirusTestCase
{
    private \wpdb $wpdb;
    private SirusRuleHitRepository $repo;

    protected function setUp(): void
    {
        $GLOBALS['wpdb']             = new \wpdb();
        $GLOBALS['wpdb_get_results'] = [];

        $this->wpdb = $GLOBALS['wpdb'];
        $this->repo = new SirusRuleHitRepository($this->wpdb);
    }

    public function testInsertRecordsQuery(): void
    {
        $id = $this->repo->insert([
            'rule_key'   => 'slow_network_recorder_downgrade',
            'signal_key' => 'slow_network_high_error_rate',
            'device_id'  => 'dev-001',
            'session_id' => 'sess-001',
            'severity'   => 'high',
            'action_key' => 'enable_lightweight_recorder',
        ]);

        $this->assertSame(1, $id);
        $this->assertCount(1, $this->wpdb->queries);

        $query = $this->wpdb->queries[0];
        $this->assertSame('wp_sirus_rule_hits', $query['table']);
        $this->assertSame('slow_network_recorder_downgrade', $query['data']['rule_key']);
        $this->assertSame('high', $query['data']['severity']);
        $this->assertSame('triggered', $query['data']['status']);
        $this->assertSame(1, $query['data']['hit_count']);
    }

    public function testInsertSetsDefaultValues(): void
    {
        $this->repo->insert([
            'rule_key'   => 'test_rule',
            'signal_key' => 'test_signal',
            'severity'   => 'low',
            'action_key' => 'test_action',
        ]);

        $query = $this->wpdb->queries[0];
        $this->assertSame(1, $query['data']['site_id']); // get_current_blog_id() = 1
        $this->assertSame(1, $query['data']['hit_count']);
        $this->assertSame('triggered', $query['data']['status']);
    }

    public function testGetRecentHitsWithLimit(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'rule_key' => 'slow_network_recorder_downgrade', 'severity' => 'high'],
            ['id' => 2, 'rule_key' => 'safari_feature_break', 'severity' => 'high'],
        ];

        $hits = $this->repo->getRecentHits(50);

        $this->assertCount(2, $hits);
        $this->assertCount(1, $this->wpdb->queries);

        $recorded_query = $this->wpdb->queries[0]['query'];
        $this->assertStringContainsString('ORDER BY created_at DESC', $recorded_query);
        $this->assertStringContainsString('LIMIT', $recorded_query);
    }

    public function testGetHitsBySeverityFiltersBySeverity(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'rule_key' => 'checkout_failure_spike', 'severity' => 'critical'],
        ];

        $since = time() - 3600;
        $hits  = $this->repo->getHitsBySeverity('critical', $since);

        $this->assertCount(1, $hits);
        $recorded_query = $this->wpdb->queries[0]['query'];
        $this->assertStringContainsString("severity", $recorded_query);
        $this->assertStringContainsString('critical', $recorded_query);
        $this->assertStringContainsString('created_at', $recorded_query);
    }

    public function testGetRecentHitsReturnsEmptyArrayWhenNoResults(): void
    {
        $GLOBALS['wpdb_get_results'] = [];

        $hits = $this->repo->getRecentHits(10);

        $this->assertSame([], $hits);
    }

    public function testIncrementHitInsertsWhenNoExistingRow(): void
    {
        // get_row returns null (no existing row) → inserts new.
        $this->repo->incrementHit('slow_network_recorder_downgrade', 'dev-001', 'sess-001');

        // Expect a get_row query + insert query.
        $this->assertGreaterThanOrEqual(2, count($this->wpdb->queries));
    }
}
