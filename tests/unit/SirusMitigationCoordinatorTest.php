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
        $GLOBALS['transients']       = [];
        $GLOBALS['wp_options']       = [];

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

    // ─── processEvent ────────────────────────────────────────────────────────

    public function testProcessEventInsertsForMatchingSignal(): void
    {
        // js_error on 2g → SIGNAL_REPEATED_JS_ERROR + SIGNAL_SLOW_NETWORK_ERROR
        // → network_failure_spike wins (priority 100)
        $event = [
            'event_type' => 'js_error',
            'url'        => '/page',
            'browser'    => 'Chrome',
            'network'    => '2g',
            'device_id'  => 'dev-001',
            'session_id' => 'sess-001',
            'timestamp'  => time(),
        ];

        $this->coordinator->processEvent($event);

        $insert_queries = array_filter(
            $this->wpdb->queries,
            static fn(array $q) => isset($q['table'])
        );

        $this->assertGreaterThan(0, count($insert_queries));
    }

    public function testProcessEventWithNoSignalsDoesNotInsert(): void
    {
        $event = [
            'event_type' => 'capability_failure',
            'url'        => '/page',
            'browser'    => 'Chrome',
            'network'    => '4g',
            'device_id'  => 'dev-002',
            'session_id' => 'sess-002',
        ];

        $this->coordinator->processEvent($event);

        $insert_queries = array_filter(
            $this->wpdb->queries,
            static fn(array $q) => isset($q['table'])
        );

        $this->assertCount(0, $insert_queries);
    }

    public function testProcessEventInvalidatesTransientCache(): void
    {
        $device_id = 'dev-cache-test';
        $cache_key = 'sirus_dir_' . md5($device_id);
        $GLOBALS['transients'][$cache_key] = ['mode' => 'lite', 'ttl' => 300, 'reason' => 'x', 'confidence' => 0.75];

        $event = [
            'event_type' => 'js_error',
            'url'        => '/page',
            'network'    => '2g',
            'device_id'  => $device_id,
            'session_id' => '',
            'timestamp'  => time(),
        ];

        $this->coordinator->processEvent($event);

        $this->assertArrayNotHasKey($cache_key, $GLOBALS['transients']);
    }

    public function testProcessEventSkippedWhenKillSwitchOff(): void
    {
        $GLOBALS['wp_options'][1][SirusMitigationCoordinator::KILL_SWITCH_OPTION] = false;

        $event = [
            'event_type' => 'js_error',
            'url'        => '/page',
            'network'    => '2g',
            'device_id'  => 'dev-003',
            'session_id' => '',
        ];

        $this->coordinator->processEvent($event);

        $insert_queries = array_filter(
            $this->wpdb->queries,
            static fn(array $q) => isset($q['table'])
        );

        $this->assertCount(0, $insert_queries);
    }

    // ─── getDirective ────────────────────────────────────────────────────────

    public function testGetDirectiveReturnsNullWhenNoActiveActions(): void
    {
        $GLOBALS['wpdb_get_results'] = [];

        $result = $this->coordinator->getDirective('dev-unknown');

        $this->assertNull($result);
    }

    public function testGetDirectiveReturnsNullWhenKillSwitchOff(): void
    {
        $GLOBALS['wp_options'][1][SirusMitigationCoordinator::KILL_SWITCH_OPTION] = false;
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'high_js_error_rate', 'response_mode' => 'lite', 'status' => 'active'],
        ];

        $result = $this->coordinator->getDirective('dev-001');

        $this->assertNull($result);
    }

    public function testGetDirectiveReturnsLockedStructureForLiteMode(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'high_js_error_rate', 'response_mode' => 'lite', 'status' => 'active'],
        ];

        $result = $this->coordinator->getDirective('dev-001');

        $this->assertNotNull($result);
        $this->assertIsArray($result);
        $this->assertSame('lite', $result['mode']);
        $this->assertArrayHasKey('ttl', $result);
        $this->assertArrayHasKey('reason', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertSame('high_js_error_rate', $result['reason']);
        $this->assertSame(0.75, $result['confidence']);
        $this->assertSame(SirusMitigationCoordinator::DEFAULT_TTL, $result['ttl']);
    }

    public function testGetDirectiveReturnsNullForDegradedWithInsufficientSamples(): void
    {
        // Degraded requires MIN_SAMPLE_FOR_DEGRADED (3) actions; only 2 provided.
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'network_failure_spike', 'response_mode' => 'degraded', 'status' => 'active'],
            ['id' => 2, 'action_key' => 'network_failure_spike', 'response_mode' => 'degraded', 'status' => 'active'],
        ];

        $result = $this->coordinator->getDirective('dev-001');

        $this->assertNull($result);
    }

    public function testGetDirectiveReturnsDegradedWhenSampleThresholdMet(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'network_failure_spike', 'response_mode' => 'degraded', 'status' => 'active'],
            ['id' => 2, 'action_key' => 'network_failure_spike', 'response_mode' => 'degraded', 'status' => 'active'],
            ['id' => 3, 'action_key' => 'network_failure_spike', 'response_mode' => 'degraded', 'status' => 'active'],
        ];

        $result = $this->coordinator->getDirective('dev-001');

        $this->assertNotNull($result);
        $this->assertSame('degraded', $result['mode']);
        $this->assertSame(0.82, $result['confidence']);
        $this->assertSame('network_failure_spike', $result['reason']);
    }

    public function testGetDirectiveUsesTransientCacheOnSecondCall(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'high_js_error_rate', 'response_mode' => 'lite', 'status' => 'active'],
        ];

        $first  = $this->coordinator->getDirective('dev-cache');
        // Clear DB results to prove the second call reads from transient.
        $GLOBALS['wpdb_get_results'] = [];
        $second = $this->coordinator->getDirective('dev-cache');

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
    }

    public function testGetDirectiveTtlUsesExpiresAtWhenSet(): void
    {
        $expires_at = time() + 150;
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'high_js_error_rate', 'response_mode' => 'lite', 'status' => 'active', 'expires_at' => $expires_at],
        ];

        $result = $this->coordinator->getDirective('dev-ttl');

        $this->assertNotNull($result);
        // TTL should be approximately 150 seconds (max(0, expires_at - time())).
        $this->assertGreaterThan(140, $result['ttl']);
        $this->assertLessThanOrEqual(150, $result['ttl']);
    }

    // ─── normalizeMode (tested via getDirective) ──────────────────────────────

    public function testNormalizeModeMapsSafeModeToDegradedViaDirective(): void
    {
        // safe_mode is normalized to degraded before the priority lookup,
        // so 3 safe_mode actions will be selected and normalized correctly.
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'network_failure_spike', 'response_mode' => 'safe_mode', 'status' => 'active'],
            ['id' => 2, 'action_key' => 'network_failure_spike', 'response_mode' => 'safe_mode', 'status' => 'active'],
            ['id' => 3, 'action_key' => 'network_failure_spike', 'response_mode' => 'safe_mode', 'status' => 'active'],
        ];

        $result = $this->coordinator->getDirective('dev-safe');

        // safe_mode → normalized to degraded; sample gate met (3 >= 3).
        $this->assertNotNull($result);
        $this->assertSame('degraded', $result['mode']);
    }

    public function testNormalizeModeMapLightweightToLiteViaGetResponseMode(): void
    {
        // lightweight is normalized to lite before the priority lookup.
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'high_js_error_rate', 'response_mode' => 'lightweight', 'status' => 'active'],
        ];

        $mode = $this->coordinator->getResponseMode('dev-light');

        $this->assertSame('lite', $mode);
    }

    // ─── deprecated wrappers ─────────────────────────────────────────────────

    public function testGetResponseModeReturnsLiteWhenDirectiveIsLite(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'high_js_error_rate', 'response_mode' => 'lite', 'status' => 'active'],
        ];

        $mode = $this->coordinator->getResponseMode('dev-001');

        $this->assertSame('lite', $mode);
    }

    public function testGetResponseModeDefaultsToNormalWhenNoActions(): void
    {
        $GLOBALS['wpdb_get_results'] = [];

        $mode = $this->coordinator->getResponseMode('dev-unknown');

        $this->assertSame('normal', $mode);
    }

    public function testGetClientDirectivesReturnsLiteFlags(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'high_js_error_rate', 'response_mode' => 'lite', 'status' => 'active'],
        ];

        $directives = $this->coordinator->getClientDirectives('dev-001');

        $this->assertSame('lite', $directives['response_mode']);
        $this->assertFalse($directives['flags']['disable_waveform']);
        $this->assertTrue($directives['flags']['disable_animations']);
        $this->assertTrue($directives['flags']['reduce_polling']);
    }

    public function testGetClientDirectivesReturnsDegradedFlagsWithSufficientSamples(): void
    {
        $GLOBALS['wpdb_get_results'] = [
            ['id' => 1, 'action_key' => 'network_failure_spike', 'response_mode' => 'degraded', 'status' => 'active'],
            ['id' => 2, 'action_key' => 'network_failure_spike', 'response_mode' => 'degraded', 'status' => 'active'],
            ['id' => 3, 'action_key' => 'network_failure_spike', 'response_mode' => 'degraded', 'status' => 'active'],
        ];

        $directives = $this->coordinator->getClientDirectives('dev-001');

        $this->assertSame('degraded', $directives['response_mode']);
        $this->assertTrue($directives['flags']['disable_waveform']);
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
        $this->assertSame([], $directives['actions']);
    }
}
