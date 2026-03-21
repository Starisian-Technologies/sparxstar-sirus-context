<?php

/**
 * Tests for SirusMitigationCoordinator.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;
use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;
use Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer;
use Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;

final class SirusMitigationCoordinatorTest extends SirusTestCase
{
    private \wpdb $wpdb;
    private SirusMitigationCoordinator $coordinator;
    private SirusRuleHitRepository $ruleHitRepo;
    private SirusMitigationActionRepository $actionRepo;

    protected function setUp(): void
    {
        $GLOBALS['wpdb']             = new \wpdb();
        $GLOBALS['wpdb_get_results'] = [];

        $this->wpdb        = $GLOBALS['wpdb'];
        $this->ruleHitRepo = new SirusRuleHitRepository($this->wpdb);
        $this->actionRepo  = new SirusMitigationActionRepository($this->wpdb);

        $this->coordinator = new SirusMitigationCoordinator(
            new SirusSignalEvaluator(),
            new SirusImpactScorer(),
            new SirusMitigationRuleEngine(),
            $this->ruleHitRepo,
            $this->actionRepo
        );
    }

    public function testProcessEventCallsEvaluatorRuleEngineAndRepositories(): void
    {
        // A js_error on /checkout with Safari on 2g will trigger multiple rules.
        $event = [
            'event_type' => 'js_error',
            'url'        => '/checkout',
            'browser'    => 'Safari',
            'network'    => '2g',
            'device_id'  => 'dev-001',
            'session_id' => 'sess-001',
            'timestamp'  => time(),
        ];

        $this->coordinator->processEvent($event);

        // At least one rule hit and one action should have been inserted.
        $insert_queries = array_filter(
            $this->wpdb->queries,
            static fn(array $q) => isset($q['table'])
        );

        $this->assertGreaterThan(0, count($insert_queries));
    }

    public function testProcessEventWithNoSignalsDoesNotInsert(): void
    {
        // session_end → SIGNAL_UNSTABLE_SESSION → unstable_device_session rule hit
        // but we test a non-matching event type here.
        $event = [
            'event_type' => 'capability_failure',
            'url'        => '/page',
            'browser'    => 'Chrome',
            'network'    => '4g',
            'device_id'  => 'dev-002',
            'session_id' => 'sess-002',
        ];

        $this->coordinator->processEvent($event);

        // capability_failure does not produce any signal → no inserts.
        $insert_queries = array_filter(
            $this->wpdb->queries,
            static fn(array $q) => isset($q['table'])
        );

        $this->assertCount(0, $insert_queries);
    }

    public function testGetResponseModePriorityOrder(): void
    {
        // safe_mode > degraded > lightweight > normal
        // Seed the stub to return actions with various response_modes.
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'a', 'response_mode' => 'lightweight', 'status' => 'active'],
            ['id' => 2, 'action_key' => 'b', 'response_mode' => 'degraded', 'status' => 'active'],
        ];

        $mode = $this->coordinator->getResponseMode('dev-001');

        $this->assertSame('degraded', $mode);
    }

    public function testGetResponseModeSafeModeBeatsAll(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'a', 'response_mode' => 'degraded', 'status' => 'active'],
            ['id' => 2, 'action_key' => 'b', 'response_mode' => 'safe_mode', 'status' => 'active'],
        ];

        $mode = $this->coordinator->getResponseMode('dev-001');

        $this->assertSame('safe_mode', $mode);
    }

    public function testGetResponseModeDefaultsToNormalWhenNoActions(): void
    {
        $GLOBALS['wpdb_get_results'] = [];

        $mode = $this->coordinator->getResponseMode('dev-unknown');

        $this->assertSame('normal', $mode);
    }

    public function testGetClientDirectivesReturnsDegradedFlags(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'enable_lightweight_recorder', 'response_mode' => 'degraded', 'status' => 'active'],
        ];

        $directives = $this->coordinator->getClientDirectives('dev-001');

        $this->assertSame('degraded', $directives['response_mode']);
        $this->assertTrue($directives['flags']['disable_waveform']);
        $this->assertTrue($directives['flags']['disable_animations']);
        $this->assertTrue($directives['flags']['reduce_polling']);
    }

    public function testGetClientDirectivesReturnsSafeModeFlags(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'disable_problem_feature', 'response_mode' => 'safe_mode', 'status' => 'active'],
        ];

        $directives = $this->coordinator->getClientDirectives('dev-002');

        $this->assertSame('safe_mode', $directives['response_mode']);
        $this->assertTrue($directives['flags']['disable_waveform']);
        $this->assertTrue($directives['flags']['disable_animations']);
        $this->assertFalse($directives['flags']['reduce_polling']);
    }

    public function testGetClientDirectivesLightweightFlags(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'suggest_lightweight_mode', 'response_mode' => 'lightweight', 'status' => 'active'],
        ];

        $directives = $this->coordinator->getClientDirectives('dev-003');

        $this->assertSame('lightweight', $directives['response_mode']);
        $this->assertFalse($directives['flags']['disable_waveform']);
        $this->assertTrue($directives['flags']['disable_animations']);
        $this->assertTrue($directives['flags']['reduce_polling']);
    }

    public function testGetClientDirectivesNormalModeAllFlagsFalse(): void
    {
        $GLOBALS['wpdb_get_results'] = [];

        $directives = $this->coordinator->getClientDirectives('dev-004');

        $this->assertSame('normal', $directives['response_mode']);
        $this->assertFalse($directives['flags']['disable_waveform']);
        $this->assertFalse($directives['flags']['disable_animations']);
        $this->assertFalse($directives['flags']['reduce_polling']);
    }

    public function testGetClientDirectivesIncludesActionKeys(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'enable_lightweight_recorder', 'response_mode' => 'degraded', 'status' => 'active'],
            ['id' => 2, 'action_key' => 'admin_alert_checkout', 'response_mode' => 'normal', 'status' => 'active'],
        ];

        $directives = $this->coordinator->getClientDirectives('dev-005');

        $this->assertContains('enable_lightweight_recorder', $directives['actions']);
        $this->assertContains('admin_alert_checkout', $directives['actions']);
    }
}
