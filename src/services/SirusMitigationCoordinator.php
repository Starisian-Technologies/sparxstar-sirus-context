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
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;

/**
 * Coordinates the full adaptive-response pipeline for a single event.
 */
final class SirusMitigationCoordinator
{
    /** Response mode priority (higher index = higher priority). */
    private const MODE_PRIORITY = ['normal', 'lightweight', 'degraded', 'safe_mode'];

    public function __construct(
        private readonly SirusSignalEvaluator $evaluator,
        private readonly SirusImpactScorer $scorer,
        private readonly SirusMitigationRuleEngine $ruleEngine,
        private readonly SirusRuleHitRepository $ruleHitRepo,
        private readonly SirusMitigationActionRepository $actionRepo,
    ) {}

    /**
     * Full processing pipeline: signals → rules → score → store hits → store actions.
     *
     * @param array<string, mixed> $event Persisted event data.
     */
    public function processEvent(array $event): void
    {
        $signals = $this->evaluator->detectSignals($event);

        if ($signals === []) {
            return;
        }

        $matches = $this->ruleEngine->evaluate($signals, $event);

        foreach ($matches as $match) {
            if (! $match['should_apply']) {
                continue;
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
                'expires_at'    => time() + 86400,
            ]);
        }
    }

    /**
     * Returns the highest-priority active response_mode for a device/session.
     *
     * Priority: safe_mode > degraded > lightweight > normal
     */
    public function getResponseMode(string $deviceId, string $sessionId = ''): string
    {
        $device_actions  = $this->actionRepo->getActiveForDevice($deviceId);
        $session_actions = $sessionId !== ''
            ? $this->actionRepo->getActiveForSession($sessionId)
            : [];

        $all_actions = array_merge($device_actions, $session_actions);

        $highest_priority = 0;
        $highest_mode     = 'normal';

        foreach ($all_actions as $action) {
            $mode     = (string) ($action['response_mode'] ?? 'normal');
            $priority = array_search($mode, self::MODE_PRIORITY, true);

            if ($priority === false) {
                continue;
            }

            if ($priority > $highest_priority) {
                $highest_priority = (int) $priority;
                $highest_mode     = $mode;
            }
        }

        return $highest_mode;
    }

    /**
     * Returns full client directive payload for a device/session.
     *
     * @return array{response_mode: string, actions: string[], flags: array<string, bool>}
     */
    public function getClientDirectives(string $deviceId, string $sessionId = ''): array
    {
        $response_mode = $this->getResponseMode($deviceId, $sessionId);

        $device_actions  = $this->actionRepo->getActiveForDevice($deviceId);
        $session_actions = $sessionId !== ''
            ? $this->actionRepo->getActiveForSession($sessionId)
            : [];

        $all_actions = array_merge($device_actions, $session_actions);
        $action_keys = [];

        foreach ($all_actions as $action) {
            $key = (string) ($action['action_key'] ?? '');
            if ($key !== '') {
                $action_keys[] = $key;
            }
        }

        $action_keys = array_values(array_unique($action_keys));

        $flags = [
            'disable_waveform'   => in_array($response_mode, ['degraded', 'safe_mode'], true),
            'disable_animations' => in_array($response_mode, ['degraded', 'safe_mode', 'lightweight'], true),
            'reduce_polling'     => in_array($response_mode, ['degraded', 'lightweight'], true),
        ];

        return [
            'response_mode' => $response_mode,
            'actions'       => $action_keys,
            'flags'         => $flags,
        ];
    }
}
