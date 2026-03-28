<?php

/**
 * SirusRuleConfig - Hard-coded starter mitigation rules.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Provides the static rule set used by SirusMitigationRuleEngine.
 * Each rule maps a signal key to an action and response mode.
 */
final class SirusRuleConfig
{
    /**
     * Returns all configured mitigation rules.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRules(): array
    {
        return [
            [
                'rule_key'      => 'high_js_error_rate',
                'signal_key'    => SirusSignalEvaluator::SIGNAL_REPEATED_JS_ERROR,
                'mode'          => 'lite',
                'priority'      => 80,
                'confidence'    => 0.75,
                'reason'        => 'high_js_error_rate',
                'ttl'           => 300,
                'admin_note'    => 'Repeated JS errors detected; downgrading to lite mode.',
                // DB compatibility aliases.
                'action_key'    => 'high_js_error_rate',
                'response_mode' => 'lite',
                'severity'      => 'high',
            ],
            [
                'rule_key'      => 'network_failure_spike',
                'signal_key'    => SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR,
                'mode'          => 'degraded',
                'priority'      => 100,
                'confidence'    => 0.82,
                'reason'        => 'network_failure_spike',
                'ttl'           => 300,
                'admin_note'    => 'Network failure spike on slow connection; enabling degraded mode.',
                // DB compatibility aliases.
                'action_key'    => 'network_failure_spike',
                'response_mode' => 'degraded',
                'severity'      => 'high',
            ],
            [
                'rule_key'      => 'unstable_device_session',
                'signal_key'    => SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION,
                'mode'          => 'lite',
                'priority'      => 60,
                'confidence'    => 0.70,
                'reason'        => 'unstable_device_session',
                'ttl'           => 300,
                'admin_note'    => 'Unstable device session detected; switching to lite mode.',
                // DB compatibility aliases.
                'action_key'    => 'unstable_device_session',
                'response_mode' => 'lite',
                'severity'      => 'high',
            ],
        ];
    }
}
