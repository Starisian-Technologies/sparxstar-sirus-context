<?php

/**
 * Tests for SirusMitigationRuleEngine.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine;
use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;
use Starisian\Sparxstar\Sirus\helpers\SirusRuleConfig;

final class SirusMitigationRuleEngineTest extends SirusTestCase
{
    private SirusMitigationRuleEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SirusMitigationRuleEngine();
    }

    public function testNoSignalsReturnsNull(): void
    {
        $result = $this->engine->evaluate([]);

        $this->assertNull($result);
    }

    public function testUnknownSignalReturnsNull(): void
    {
        $result = $this->engine->evaluate(['some_unknown_signal']);

        $this->assertNull($result);
    }

    public function testSingleMatchingSignalReturnsSingleRuleArray(): void
    {
        $result = $this->engine->evaluate([SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR]);

        $this->assertIsArray($result);
        $this->assertSame('network_failure_spike', $result['rule_key']);
    }

    public function testReturnedRuleHasRequiredFields(): void
    {
        $result = $this->engine->evaluate([SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rule_key', $result);
        $this->assertArrayHasKey('signal_key', $result);
        $this->assertArrayHasKey('mode', $result);
        $this->assertArrayHasKey('priority', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertArrayHasKey('reason', $result);
    }

    public function testHigherPriorityRuleWinsWhenMultipleSignalsMatch(): void
    {
        // SIGNAL_SLOW_NETWORK_ERROR → network_failure_spike (priority 100)
        // SIGNAL_REPEATED_JS_ERROR  → high_js_error_rate    (priority 80)
        $result = $this->engine->evaluate([
            SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR,
            SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR,
        ]);

        $this->assertIsArray($result);
        $this->assertSame('network_failure_spike', $result['rule_key']);
        $this->assertSame(100, $result['priority']);
    }

    public function testNetworkFailureSpikeHasDegradedMode(): void
    {
        $result = $this->engine->evaluate([SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR]);

        $this->assertIsArray($result);
        $this->assertSame('degraded', $result['mode']);
        $this->assertSame(0.82, $result['confidence']);
    }

    public function testHighJsErrorRateHasLiteMode(): void
    {
        $result = $this->engine->evaluate([SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR]);

        $this->assertIsArray($result);
        $this->assertSame('lite', $result['mode']);
        $this->assertSame(0.75, $result['confidence']);
    }

    public function testUnstableSessionHasLiteMode(): void
    {
        $result = $this->engine->evaluate([SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION]);

        $this->assertIsArray($result);
        $this->assertSame('lite', $result['mode']);
        $this->assertSame(0.70, $result['confidence']);
        $this->assertSame(60, $result['priority']);
    }

    public function testRuleConfigHasThreeRules(): void
    {
        $rules = SirusRuleConfig::getRules();
        $this->assertCount(3, $rules);
    }

    public function testAllThreeRulesAreMatchable(): void
    {
        $all_signals = [
            SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR,
            SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR,
            SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION,
        ];

        // With all signals, the highest-priority rule (network_failure_spike, 100) wins.
        $result = $this->engine->evaluate($all_signals);

        $this->assertIsArray($result);
        $this->assertSame('network_failure_spike', $result['rule_key']);
    }

    public function testRuleHasDbCompatibilityAliases(): void
    {
        $result = $this->engine->evaluate([SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('action_key', $result);
        $this->assertArrayHasKey('response_mode', $result);
        $this->assertSame($result['rule_key'], $result['action_key']);
        $this->assertSame($result['mode'], $result['response_mode']);
    }
}
