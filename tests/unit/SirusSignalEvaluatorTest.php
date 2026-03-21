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

    public function testJsErrorEmitsRepeatedJsError(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'js_error',
            'url'        => '/some-page',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR, $signals);
    }

    public function testApiErrorOnCheckoutUrlEmitsCheckoutFailure(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'api_error',
            'url'        => '/checkout',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_CHECKOUT_FAILURE, $signals);
    }

    public function testJsErrorOnCheckoutUrlEmitsCheckoutFailure(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'js_error',
            'url'        => '/checkout/confirm',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_CHECKOUT_FAILURE, $signals);
    }

    public function testNetworkIssueWithSlowNetworkEmitsSlowNetworkError(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'network_issue',
            'network'    => '2g',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR, $signals);
    }

    public function testJsErrorWithSlow2gFromContextJsonEmitsSlowNetworkError(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type'   => 'js_error',
            'context_json' => json_encode(['network' => 'slow-2g', 'browser' => 'Chrome']),
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR, $signals);
    }

    public function testJsErrorWithSafariBrowserEmitsSafariFeatureBreak(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'js_error',
            'browser'    => 'Safari',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK, $signals);
    }

    public function testJsErrorWithSafariInContextJsonEmitsSafariFeatureBreak(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type'   => 'js_error',
            'context_json' => json_encode(['browser' => 'Safari']),
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK, $signals);
    }

    public function testSessionEndEmitsUnstableSession(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'session_end',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION, $signals);
    }

    public function testSessionStartEmitsUnstableSession(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'session_start',
        ]);

        $this->assertContains(SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION, $signals);
    }

    public function testNoDuplicateSignalsWhenMultipleConditionsMatch(): void
    {
        // js_error on /checkout with Safari on 2g → repeated_js_error, checkout_failure,
        // slow_network_high_error_rate, safari_feature_break — no duplicates.
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'js_error',
            'url'        => '/checkout',
            'browser'    => 'Safari',
            'network'    => '2g',
        ]);

        $this->assertSame(array_unique($signals), $signals, 'detectSignals must return deduplicated signals');
    }

    public function testNonErrorEventTypeProducesNoErrorSignals(): void
    {
        $signals = $this->evaluator->detectSignals([
            'event_type' => 'api_error',
            'url'        => '/page',
            'browser'    => 'Chrome',
            'network'    => '4g',
        ]);

        $this->assertNotContains(SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR, $signals);
        $this->assertNotContains(SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK, $signals);
    }
}
