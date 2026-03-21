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

    public function testMatchingSignalReturnsShouldApplyTrue(): void
    {
        $matches = $this->engine->evaluate(
            [SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR],
            ['event_type' => 'network_issue']
        );

        $this->assertCount(1, $matches);
        $this->assertTrue($matches[0]['should_apply']);
        $this->assertSame('slow_network_recorder_downgrade', $matches[0]['rule_key']);
    }

    public function testMatchingSignalContainsCorrectFields(): void
    {
        $matches = $this->engine->evaluate(
            [SirusSignalEvaluator::SIGNAL_CHECKOUT_FAILURE],
            ['event_type' => 'api_error', 'url' => '/checkout']
        );

        $this->assertCount(1, $matches);
        $match = $matches[0];
        $this->assertArrayHasKey('rule_key', $match);
        $this->assertArrayHasKey('signal_key', $match);
        $this->assertArrayHasKey('severity', $match);
        $this->assertArrayHasKey('action_key', $match);
        $this->assertArrayHasKey('response_mode', $match);
        $this->assertArrayHasKey('should_apply', $match);
        $this->assertArrayHasKey('admin_note', $match);
        $this->assertSame('checkout_failure_spike', $match['rule_key']);
        $this->assertSame('critical', $match['severity']);
    }

    public function testNoMatchingSignalReturnsEmptyArray(): void
    {
        $matches = $this->engine->evaluate(
            ['some_unknown_signal'],
            ['event_type' => 'session_start']
        );

        $this->assertSame([], $matches);
    }

    public function testAllFourRulesAreMatchable(): void
    {
        $all_signals = [
            SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR,
            SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK,
            SirusSignalEvaluator::SIGNAL_CHECKOUT_FAILURE,
            SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION,
        ];

        $matches = $this->engine->evaluate($all_signals, []);

        $this->assertCount(4, $matches, 'All 4 rules should match when all signals are present');

        $rule_keys = array_column($matches, 'rule_key');
        $this->assertContains('slow_network_recorder_downgrade', $rule_keys);
        $this->assertContains('safari_feature_break', $rule_keys);
        $this->assertContains('checkout_failure_spike', $rule_keys);
        $this->assertContains('unstable_device_session', $rule_keys);
    }

    public function testSafariRuleHasSafeModeResponse(): void
    {
        $matches = $this->engine->evaluate(
            [SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK],
            ['event_type' => 'js_error', 'browser' => 'Safari']
        );

        $this->assertCount(1, $matches);
        $this->assertSame('safe_mode', $matches[0]['response_mode']);
    }

    public function testUnstableSessionRuleLightweightMode(): void
    {
        $matches = $this->engine->evaluate(
            [SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION],
            ['event_type' => 'session_end']
        );

        $this->assertCount(1, $matches);
        $this->assertSame('lightweight', $matches[0]['response_mode']);
        $this->assertSame('medium', $matches[0]['severity']);
    }

    public function testRuleConfigHasFourRules(): void
    {
        $rules = SirusRuleConfig::getRules();
        $this->assertCount(4, $rules);
    }
}
