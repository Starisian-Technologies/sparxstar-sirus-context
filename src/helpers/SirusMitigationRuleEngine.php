<?php

/**
 * SirusMitigationRuleEngine - Evaluates signals against configured rules.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Matches detected signals against the configured rule set.
 * Returns only rules that match. No DB writes here.
 */
final class SirusMitigationRuleEngine
{
    /**
     * Evaluates an array of detected signals against the configured rule set.
     *
     * @param string[]             $signals Array of signal keys from SirusSignalEvaluator.
     * @param array<string, mixed> $event   The triggering event.
     * @param array<string, mixed> $context Optional cluster context (error_count, affected_sessions, etc.).
     * @return array<int, array<string, mixed>> Each element: rule_key, signal_key, severity, action_key, response_mode, should_apply, admin_note.
     */
    public function evaluate(array $signals, array $event, array $context = []): array
    {
        unset($event, $context); // Only signals determine match; event/context reserved for future enrichment.

        $matches = [];

        foreach (SirusRuleConfig::getRules() as $rule) {
            if (! in_array($rule['signal_key'], $signals, true)) {
                continue;
            }

            $matches[] = [
                'rule_key'      => $rule['rule_key'],
                'signal_key'    => $rule['signal_key'],
                'severity'      => $rule['severity'],
                'action_key'    => $rule['action_key'],
                'response_mode' => $rule['response_mode'],
                'should_apply'  => true,
                'admin_note'    => $rule['admin_note'] ?? '',
            ];
        }

        return $matches;
    }
}
