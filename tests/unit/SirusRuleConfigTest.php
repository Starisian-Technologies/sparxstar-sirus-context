<?php

/**
 * Tests for SirusRuleConfig – hard-coded starter mitigation rules.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\helpers\SirusRuleConfig;
use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;

/**
 * Unit tests for SirusRuleConfig::getRules().
 *
 * The rule set is spec-frozen. Any unintended change to signal keys, modes,
 * priorities, or confidence values will be caught here.
 */
final class SirusRuleConfigTest extends SirusTestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $rules;

    protected function setUp(): void
    {
        $this->rules = SirusRuleConfig::getRules();
    }

    // ── Structure ─────────────────────────────────────────────────────────────

    /**
     * getRules() must return exactly three rules.
     */
    public function testRuleCountIsThree(): void
    {
        $this->assertCount(3, $this->rules);
    }

    /**
     * Every rule must contain all required keys.
     */
    public function testAllRulesHaveRequiredKeys(): void
    {
        $required = [
            'rule_key',
            'signal_key',
            'mode',
            'priority',
            'confidence',
            'reason',
            'ttl',
            'admin_note',
            'action_key',
            'response_mode',
            'severity',
        ];

        foreach ($this->rules as $i => $rule) {
            foreach ($required as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $rule,
                    "Rule [{$i}] is missing required key '{$key}'."
                );
            }
        }
    }

    /**
     * TTL must be a positive integer in every rule.
     */
    public function testAllRulesHavePositiveTtl(): void
    {
        foreach ($this->rules as $i => $rule) {
            $this->assertIsInt($rule['ttl'], "Rule [{$i}] ttl must be an integer.");
            $this->assertGreaterThan(0, $rule['ttl'], "Rule [{$i}] ttl must be positive.");
        }
    }

    /**
     * Confidence must be a float in (0, 1] for every rule.
     */
    public function testAllRulesHaveValidConfidence(): void
    {
        foreach ($this->rules as $i => $rule) {
            $this->assertIsFloat($rule['confidence'], "Rule [{$i}] confidence must be a float.");
            $this->assertGreaterThan(0.0, $rule['confidence'], "Rule [{$i}] confidence must be > 0.");
            $this->assertLessThanOrEqual(1.0, $rule['confidence'], "Rule [{$i}] confidence must be <= 1.");
        }
    }

    /**
     * response_mode must be one of the locked set: normal, lite, degraded.
     */
    public function testAllRulesHaveValidResponseMode(): void
    {
        $valid = ['normal', 'lite', 'degraded'];
        foreach ($this->rules as $i => $rule) {
            $this->assertContains(
                $rule['response_mode'],
                $valid,
                "Rule [{$i}] response_mode '{$rule['response_mode']}' is not in the locked mode set."
            );
        }
    }

    /**
     * mode must equal response_mode for every rule (alias consistency).
     */
    public function testModeAliasMatchesResponseMode(): void
    {
        foreach ($this->rules as $i => $rule) {
            $this->assertSame(
                $rule['mode'],
                $rule['response_mode'],
                "Rule [{$i}] 'mode' must equal 'response_mode'."
            );
        }
    }

    /**
     * rule_key must equal action_key for every rule (alias consistency).
     */
    public function testRuleKeyAliasMatchesActionKey(): void
    {
        foreach ($this->rules as $i => $rule) {
            $this->assertSame(
                $rule['rule_key'],
                $rule['action_key'],
                "Rule [{$i}] 'rule_key' must equal 'action_key'."
            );
        }
    }

    // ── Specific rules ────────────────────────────────────────────────────────

    /**
     * 'high_js_error_rate' rule must be present with the correct signal.
     */
    public function testHighJsErrorRateRuleExists(): void
    {
        $rule = $this->findRule('high_js_error_rate');
        $this->assertNotNull($rule, "Rule 'high_js_error_rate' not found.");
        $this->assertSame(SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR, $rule['signal_key']);
        $this->assertSame('lite', $rule['response_mode']);
    }

    /**
     * 'network_failure_spike' rule must be present with the correct signal and highest priority.
     */
    public function testNetworkFailureSpikeRuleExists(): void
    {
        $rule = $this->findRule('network_failure_spike');
        $this->assertNotNull($rule, "Rule 'network_failure_spike' not found.");
        $this->assertSame(SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR, $rule['signal_key']);
        $this->assertSame('degraded', $rule['response_mode']);
        $this->assertSame(100, $rule['priority']);
    }

    /**
     * 'unstable_device_session' rule must be present with the correct signal.
     */
    public function testUnstableDeviceSessionRuleExists(): void
    {
        $rule = $this->findRule('unstable_device_session');
        $this->assertNotNull($rule, "Rule 'unstable_device_session' not found.");
        $this->assertSame(SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION, $rule['signal_key']);
        $this->assertSame('lite', $rule['response_mode']);
    }

    /**
     * Priority ordering: network_failure_spike (100) > high_js_error_rate (80)
     * > unstable_device_session (60).
     */
    public function testPriorityOrderIsCorrect(): void
    {
        $network   = $this->findRule('network_failure_spike');
        $js        = $this->findRule('high_js_error_rate');
        $unstable  = $this->findRule('unstable_device_session');

        $this->assertNotNull($network);
        $this->assertNotNull($js);
        $this->assertNotNull($unstable);

        $this->assertGreaterThan($js['priority'], $network['priority']);
        $this->assertGreaterThan($unstable['priority'], $js['priority']);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Finds a rule by its rule_key, or returns null if not found.
     *
     * @return array<string, mixed>|null
     */
    private function findRule(string $rule_key): ?array
    {
        foreach ($this->rules as $rule) {
            if (($rule['rule_key'] ?? '') === $rule_key) {
                return $rule;
            }
        }
        return null;
    }
}
