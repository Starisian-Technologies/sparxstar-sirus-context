<?php

/**
 * Tests for SirusSignalEvaluator.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;

final class SirusSignalEvaluatorTest extends SirusTestCase
{
    private SirusSignalEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new SirusSignalEvaluator();
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    /**
     * Returns just the 'type' values from the normalized signals array.
     *
     * @param array<int, array{type: string, severity: string, source: string, timestamp: int}> $signals
     * @return string[]
     */
    private function signalTypes(array $signals): array
    {
        return array_column($signals, 'type');
    }

    // ─── schema validation ────────────────────────────────────────────────────

    public function testEachSignalHasRequiredKeys(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type' => 'js_error',
            'url'        => '/some-page',
        ]);

        $this->assertNotEmpty($signals);
        foreach ($signals as $signal) {
            $this->assertArrayHasKey('type', $signal);
            $this->assertArrayHasKey('severity', $signal);
            $this->assertArrayHasKey('source', $signal);
            $this->assertArrayHasKey('timestamp', $signal);
            $this->assertIsString($signal['type']);
            $this->assertIsString($signal['severity']);
            $this->assertSame('sirus_signal_evaluator', $signal['source']);
            $this->assertIsInt($signal['timestamp']);
        }
    }

    // ─── signal detection ─────────────────────────────────────────────────────

    public function testJsErrorEmitsRepeatedJsError(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type' => 'js_error',
            'url'        => '/some-page',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR, $this->signalTypes($signals));
    }

    public function testApiErrorOnCheckoutUrlEmitsCheckoutFailure(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type' => 'api_error',
            'url'        => '/checkout',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_CHECKOUT_FAILURE, $this->signalTypes($signals));
    }

    public function testJsErrorOnCheckoutUrlEmitsCheckoutFailure(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type' => 'js_error',
            'url'        => '/checkout/confirm',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_CHECKOUT_FAILURE, $this->signalTypes($signals));
    }

    public function testNetworkIssueWithSlowNetworkEmitsSlowNetworkError(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type' => 'network_issue',
            'network'    => '2g',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR, $this->signalTypes($signals));
    }

    public function testJsErrorWithSlow2gFromContextJsonEmitsSlowNetworkError(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type'   => 'js_error',
            'context_json' => json_encode(['network' => 'slow-2g', 'browser' => 'Chrome']),
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR, $this->signalTypes($signals));
    }

    public function testJsErrorWithSafariBrowserEmitsSafariFeatureBreak(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type' => 'js_error',
            'browser'    => 'Safari',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK, $this->signalTypes($signals));
    }

    public function testJsErrorWithSafariInContextJsonEmitsSafariFeatureBreak(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type'   => 'js_error',
            'context_json' => json_encode(['browser' => 'Safari']),
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK, $this->signalTypes($signals));
    }

    public function testSessionEndEmitsUnstableSession(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type' => 'session_end',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION, $this->signalTypes($signals));
    }

    public function testSessionStartEmitsUnstableSession(): void
    {
        $signals = $this->evaluator->getSignals([
            'event_type' => 'session_start',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION, $this->signalTypes($signals));
    }

    public function testNoDuplicateSignalsWhenMultipleConditionsMatch(): void
    {
        // js_error on /checkout with Safari on 2g → repeated_js_error, checkout_failure,
        // slow_network_high_error_rate, safari_feature_break — no duplicates.
        $signals = $this->evaluator->getSignals([
            'event_type' => 'js_error',
            'url'        => '/checkout',
            'browser'    => 'Safari',
            'network'    => '2g',
        ]);

        $types = $this->signalTypes($signals);
        $this->assertSame(array_unique($types), $types, 'getSignals must return deduplicated signal types');
    }

    public function testNonErrorEventTypeProducesNoErrorSignals(): void
    {
        // page_ready is a genuine non-error event type; it should never trigger
        // error-class signals such as repeated_js_error or safari_feature_break.
        $signals = $this->evaluator->getSignals([
            'event_type' => 'page_ready',
            'url'        => '/page',
            'browser'    => 'Chrome',
            'network'    => '4g',
        ]);

        $types = $this->signalTypes($signals);
        $this->assertNotContains(SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR, $types);
        $this->assertNotContains(SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK, $types);
    }

    public function testEmptyEventReturnsEmptySignals(): void
    {
        $signals = $this->evaluator->getSignals([]);
        $this->assertSame([], $signals);
    }
}
