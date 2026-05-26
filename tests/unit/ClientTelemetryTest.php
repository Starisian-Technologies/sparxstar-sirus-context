<?php

/**
 * Tests for ClientTelemetry.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\ClientTelemetry;

final class ClientTelemetryTest extends SirusTestCase
{
    private \wpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new \wpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['wpdb_get_var'] = null;
        $GLOBALS['__current_blog_id'] = 1;
    }

    public function testRecordInsertsRawReportAndStats(): void
    {
        $telemetry = new ClientTelemetry($this->wpdb);
        $telemetry->record('js_error', 'Something broke', [ 'component' => 'collector' ], 'dev-1');

        $this->assertCount(2, $this->wpdb->queries);
        $this->assertSame('wp_sparxstar_client_reports', $this->wpdb->queries[0]['table']);
        $this->assertSame('wp_sparxstar_client_error_stats', $this->wpdb->queries[1]['table']);
        $this->assertSame('dev-1', $this->wpdb->queries[0]['data']['device_id']);
    }

    public function testRecordUpdatesExistingAggregateWhenHashAlreadyExists(): void
    {
        $GLOBALS['wpdb_get_var'] = 3;

        $telemetry = new ClientTelemetry($this->wpdb);
        $telemetry->record('js_error', 'Something broke', [], 'dev-2');

        $this->assertSame('wp_sparxstar_client_error_stats', $this->wpdb->queries[2]['table']);
        $this->assertSame(4, $this->wpdb->queries[2]['data']['count']);
    }

    public function testPruneDeletesOnlyFromRawReportsTable(): void
    {
        $telemetry = new ClientTelemetry($this->wpdb);
        $telemetry->prune();

        $query = $this->wpdb->queries[0]['query'] ?? '';
        $this->assertStringContainsString('wp_sparxstar_client_reports', $query);
        $this->assertStringNotContainsString('postmeta', $query);
    }
}
