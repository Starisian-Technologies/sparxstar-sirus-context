<?php

/**
 * Tests for SirusMitigationActionRepository.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;

final class SirusMitigationActionRepositoryTest extends SirusTestCase
{
    private \wpdb $wpdb;
    private SirusMitigationActionRepository $repo;

    protected function setUp(): void
    {
        $GLOBALS['wpdb']             = new \wpdb();
        $GLOBALS['wpdb_get_results'] = [];

        $this->wpdb = $GLOBALS['wpdb'];
        $this->repo = new SirusMitigationActionRepository($this->wpdb);
    }

    public function testInsertRecordsQuery(): void
    {
        $id = $this->repo->insert([
            'action_key'    => 'enable_lightweight_recorder',
            'device_id'     => 'dev-001',
            'session_id'    => 'sess-001',
            'response_mode' => 'degraded',
            'expires_at'    => time() + 86400,
        ]);

        $this->assertSame(1, $id);
        $this->assertCount(1, $this->wpdb->queries);

        $query = $this->wpdb->queries[0];
        $this->assertSame('wp_sirus_mitigation_actions', $query['table']);
        $this->assertSame('enable_lightweight_recorder', $query['data']['action_key']);
        $this->assertSame('degraded', $query['data']['response_mode']);
        $this->assertSame('active', $query['data']['status']);
    }

    public function testInsertDefaultsStatusToActive(): void
    {
        $this->repo->insert([
            'action_key'    => 'test_action',
            'device_id'     => 'dev-002',
            'session_id'    => '',
            'response_mode' => 'normal',
        ]);

        $query = $this->wpdb->queries[0];
        $this->assertSame('active', $query['data']['status']);
        $this->assertSame(1, $query['data']['site_id']);
    }

    public function testGetActiveForDeviceUsesStatusActiveFilter(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'enable_lightweight_recorder', 'status' => 'active'],
        ];

        $actions = $this->repo->getActiveForDevice('dev-001');

        $this->assertCount(1, $actions);
        $recorded_query = $this->wpdb->queries[0]['query'];
        $this->assertStringContainsString('device_id', $recorded_query);
        $this->assertStringContainsString('active', $recorded_query);
    }

    public function testGetActiveForSessionUsesStatusActiveFilter(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 2, 'action_key' => 'suggest_lightweight_mode', 'status' => 'active'],
        ];

        $actions = $this->repo->getActiveForSession('sess-001');

        $this->assertCount(1, $actions);
        $recorded_query = $this->wpdb->queries[0]['query'];
        $this->assertStringContainsString('session_id', $recorded_query);
        $this->assertStringContainsString('active', $recorded_query);
    }

    public function testExpireActionIssuesUpdateQuery(): void
    {
        $this->repo->expireAction(42);

        $this->assertCount(1, $this->wpdb->queries);
        $recorded_query = $this->wpdb->queries[0]['query'];
        $this->assertStringContainsString('UPDATE', $recorded_query);
        $this->assertStringContainsString('expired', $recorded_query);
        $this->assertStringContainsString('42', $recorded_query);
    }

    public function testGetActiveForDeviceReturnsEmptyWhenNoResults(): void
    {
        $GLOBALS['wpdb_get_results'] = [];

        $actions = $this->repo->getActiveForDevice('dev-unknown');

        $this->assertSame([], $actions);
    }
}
