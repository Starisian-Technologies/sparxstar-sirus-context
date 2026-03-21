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
                'rule_key'      => 'slow_network_recorder_downgrade',
                'signal_key'    => SirusSignalEvaluator::SIGNAL_SLOW_NETWORK_ERROR,
                'action_key'    => 'enable_lightweight_recorder',
                'response_mode' => 'degraded',
                'severity'      => 'high',
                'admin_note'    => 'Slow network detected; downgrading recorder to lightweight mode.',
            ],
            [
                'rule_key'      => 'safari_feature_break',
                'signal_key'    => SirusSignalEvaluator::SIGNAL_SAFARI_FEATURE_BREAK,
                'action_key'    => 'disable_problem_feature',
                'response_mode' => 'safe_mode',
                'severity'      => 'high',
                'admin_note'    => 'Likely Safari compatibility issue.',
            ],
            [
                'rule_key'      => 'checkout_failure_spike',
                'signal_key'    => SirusSignalEvaluator::SIGNAL_CHECKOUT_FAILURE,
                'action_key'    => 'admin_alert_checkout',
                'response_mode' => 'normal',
                'severity'      => 'critical',
                'admin_note'    => 'Checkout failure spike detected.',
            ],
            [
                'rule_key'      => 'unstable_device_session',
                'signal_key'    => SirusSignalEvaluator::SIGNAL_UNSTABLE_SESSION,
                'action_key'    => 'suggest_lightweight_mode',
                'response_mode' => 'lightweight',
                'severity'      => 'medium',
                'admin_note'    => 'Unstable device session pattern detected.',
            ],
        ];
    }
}
