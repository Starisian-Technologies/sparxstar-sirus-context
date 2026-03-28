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
     * Evaluates signals and returns the single highest-priority matching rule, or null.
     *
     * @param string[] $signals Array of signal keys from SirusSignalEvaluator.
     * @return array<string, mixed>|null The winning rule array, or null if no match.
     */
    public function evaluate(array $signals): ?array
    {
        $matches = [];

        foreach (SirusRuleConfig::getRules() as $rule) {
            if (! in_array($rule['signal_key'], $signals, true)) {
                continue;
            }

            $matches[] = $rule;
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (array $a, array $b): int => (int) $b['priority'] <=> (int) $a['priority']);

        return $matches[0];
    }
}
