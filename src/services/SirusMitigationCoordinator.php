<?php

/**
 * SirusMitigationCoordinator - Full processing pipeline for Sirus adaptive responses.
 *
 * Orchestrates: signal detection → rule evaluation → scoring → persistence.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\services;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;
use Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer;
use Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine;
use Starisian\Sparxstar\Sirus\helpers\SirusRuleConfig;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;

/**
 * Coordinates the full adaptive-response pipeline for a single event.
 */
final class SirusMitigationCoordinator
{
    /** Response mode priority (higher index = higher priority). */
    private const MODE_PRIORITY = ['normal', 'lite', 'degraded'];

    /** Minimum confidence score to fire a directive. */
    public const MIN_CONFIDENCE = 0.6;

    /** Minimum event samples required before firing a 'degraded' directive. */
    public const MIN_SAMPLE_FOR_DEGRADED = 3;

    /** Default directive TTL in seconds if rule doesn't specify. */
    public const DEFAULT_TTL = 300;

    /** Transient key prefix for cached active directives. */
    private const DIRECTIVE_CACHE_PREFIX = 'sirus_dir_';

    /** Kill switch option name. */
    public const KILL_SWITCH_OPTION = 'sirus_mitigation_enabled';

    public function __construct(
        private readonly SirusSignalEvaluator $evaluator,
        private readonly SirusImpactScorer $scorer,
        private readonly SirusMitigationRuleEngine $ruleEngine,
        private readonly SirusRuleHitRepository $ruleHitRepo,
        private readonly SirusMitigationActionRepository $actionRepo,
    ) {}

    /**
     * Full processing pipeline: signals → rules → score → store hit → store action → invalidate cache.
     *
     * @param array<string, mixed> $event Persisted event data.
     */
    public function processEvent(array $event): void
    {
        if (! $this->isMitigationEnabled()) {
            return;
        }

        $signals = $this->evaluator->detectSignals($event);

        if ($signals === []) {
            return;
        }

        $match = $this->ruleEngine->evaluate($signals);

        if ($match === null) {
            return;
        }

        $score    = $this->scorer->score($event);
        $severity = $this->scorer->severityFromScore($score);

        $this->ruleHitRepo->insert([
            'rule_key'   => $match['rule_key'],
            'signal_key' => $match['signal_key'],
            'device_id'  => (string) ($event['device_id'] ?? ''),
            'session_id' => (string) ($event['session_id'] ?? ''),
            'severity'   => $severity,
            'action_key' => $match['action_key'],
        ]);

        $this->actionRepo->insert([
            'action_key'    => $match['action_key'],
            'device_id'     => (string) ($event['device_id'] ?? ''),
            'session_id'    => (string) ($event['session_id'] ?? ''),
            'response_mode' => $match['response_mode'],
            'expires_at'    => time() + (int) ($match['ttl'] ?? self::DEFAULT_TTL),
        ]);

        // Invalidate the cached directive for this device+session so it is recomputed fresh.
        $device_id  = (string) ($event['device_id'] ?? '');
        $session_id = (string) ($event['session_id'] ?? '');
        if ($device_id !== '') {
            delete_transient(self::DIRECTIVE_CACHE_PREFIX . md5($device_id . '|' . $session_id));
        }
    }

    /**
     * Returns the single active directive for a device, or null.
     *
     * Pipeline:
     * 1. Kill switch → null
     * 2. Transient cache → return cached (TTL-enforcement prevents oscillation)
     * 3. Fetch active actions from DB → pick highest-priority mode
     * 4. Confidence gate → null if below MIN_CONFIDENCE
     * 5. Sample gate → null if degraded but insufficient traffic sample
     * 6. Cache + return directive
     *
     * @return array{mode: string, ttl: int, reason: string, confidence: float}|null
     */
    public function getDirective(string $deviceId, string $sessionId = ''): ?array
    {
        if (! $this->isMitigationEnabled()) {
            return null;
        }

        // TTL gate: return cached directive if not expired.
        // Include sessionId so that different sessions on the same device never share
        // a directive that may reflect the other session's session-scoped actions.
        $cache_key = self::DIRECTIVE_CACHE_PREFIX . md5($deviceId . '|' . $sessionId);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        // Load active DB actions for this device.
        $device_actions  = $this->actionRepo->getActiveForDevice($deviceId);
        $session_actions = $sessionId !== ''
            ? $this->actionRepo->getActiveForSession($sessionId)
            : [];

        $all_actions = array_merge($device_actions, $session_actions);

        if ($all_actions === []) {
            return null;
        }

        // Pick the highest-priority action; normalize modes before comparison so
        // legacy values (safe_mode, lightweight) map to the locked 3-mode set.
        $winning_action = null;
        $highest        = -1;
        foreach ($all_actions as $action) {
            $mode     = $this->normalizeMode((string) ($action['response_mode'] ?? 'normal'));
            $priority = array_search($mode, self::MODE_PRIORITY, true);
            if ($priority !== false && (int) $priority > $highest) {
                $highest        = (int) $priority;
                $winning_action = $action;
            }
        }

        if ($winning_action === null) {
            return null;
        }

        // Map legacy response_mode → locked 3-mode contract.
        $mode       = $this->normalizeMode((string) ($winning_action['response_mode'] ?? 'normal'));
        $action_key = (string) ($winning_action['action_key'] ?? '');
        $expires_at = isset($winning_action['expires_at']) ? (int) $winning_action['expires_at'] : 0;
        $ttl        = $expires_at > 0 ? max(0, $expires_at - time()) : self::DEFAULT_TTL;

        // Determine confidence from matching rule config.
        $confidence = $this->getConfidenceForActionKey($action_key);

        // Confidence gate.
        if ($confidence < self::MIN_CONFIDENCE) {
            return null;
        }

        // Sample gate: degraded mode requires a minimum number of *degraded* actions to fire.
        // Counting only degraded actions prevents a mix of normal/lite actions from
        // padding the count and incorrectly satisfying the threshold.
        if ($mode === 'degraded') {
            $degraded_actions = array_filter(
                $all_actions,
                fn(array $a) => $this->normalizeMode((string) ($a['response_mode'] ?? 'normal')) === 'degraded'
            );
            if (count($degraded_actions) < self::MIN_SAMPLE_FOR_DEGRADED) {
                return null;
            }
        }

        $directive = [
            'mode'       => $mode,
            'ttl'        => $ttl,
            'reason'     => $action_key,
            'confidence' => $confidence,
        ];

        // Cache directive for TTL duration to prevent oscillation.
        if ($ttl > 0) {
            set_transient($cache_key, $directive, $ttl);
        }

        return $directive;
    }

    /**
     * @deprecated Use getDirective() for the locked single-directive contract.
     */
    public function getResponseMode(string $deviceId, string $sessionId = ''): string
    {
        $directive = $this->getDirective($deviceId, $sessionId);
        return $directive !== null ? $directive['mode'] : 'normal';
    }

    /**
     * @deprecated Use getDirective() for the locked single-directive contract.
     *
     * @return array{response_mode: string, actions: string[], flags: array<string, bool>}
     */
    public function getClientDirectives(string $deviceId, string $sessionId = ''): array
    {
        $directive = $this->getDirective($deviceId, $sessionId);
        if ($directive === null) {
            return [
                'response_mode' => 'normal',
                'actions'       => [],
                'flags'         => [
                    'disable_waveform'   => false,
                    'disable_animations' => false,
                    'reduce_polling'     => false,
                ],
            ];
        }
        $mode = $directive['mode'];
        return [
            'response_mode' => $mode,
            'actions'       => [$directive['reason']],
            'flags'         => [
                'disable_waveform'   => in_array($mode, ['degraded'], true),
                'disable_animations' => in_array($mode, ['degraded', 'lite'], true),
                'reduce_polling'     => in_array($mode, ['degraded', 'lite'], true),
            ],
        ];
    }

    /**
     * Checks the global kill switch.
     */
    private function isMitigationEnabled(): bool
    {
        if (defined('SIRUS_DISABLE_MITIGATION') && (bool) constant('SIRUS_DISABLE_MITIGATION')) {
            return false;
        }
        return (bool) get_option(self::KILL_SWITCH_OPTION, true);
    }

    /**
     * Maps legacy/extended mode names to the locked 3-mode contract.
     */
    private function normalizeMode(string $mode): string
    {
        return match ($mode) {
            'degraded', 'safe_mode' => 'degraded',
            'lightweight', 'lite'   => 'lite',
            default                 => 'normal',
        };
    }

    /**
     * Looks up confidence for an action_key in the rule config.
     */
    private function getConfidenceForActionKey(string $actionKey): float
    {
        foreach (SirusRuleConfig::getRules() as $rule) {
            if (($rule['action_key'] ?? '') === $actionKey || ($rule['rule_key'] ?? '') === $actionKey) {
                return (float) ($rule['confidence'] ?? self::MIN_CONFIDENCE);
            }
        }
        return self::MIN_CONFIDENCE;
    }
}
