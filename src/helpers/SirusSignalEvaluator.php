<?php

/**
 * SirusSignalEvaluator - Transforms raw event data into normalized signal keys.
 *
 * Pure function class — no persistence, no side effects.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Detects observability signals from a single sanitized event array.
 */
final class SirusSignalEvaluator
{
    public const SIGNAL_REPEATED_JS_ERROR = 'repeated_js_error';

    public const SIGNAL_CHECKOUT_FAILURE = 'checkout_failure';

    public const SIGNAL_SLOW_NETWORK_ERROR = 'slow_network_high_error_rate';

    public const SIGNAL_SAFARI_FEATURE_BREAK = 'safari_feature_break';

    public const SIGNAL_UNSTABLE_SESSION = 'unstable_device_session';

    private const SLOW_NETWORKS = [ 'slow-2g', '2g', 'slow-3g' ];

    /**
     * Transforms a single raw event array into normalized signal keys.
     *
     * @param array<string, mixed> $event Sanitized event row.
     * @return string[] Deduplicated array of signal keys detected from this event.
     */
    public function detectSignals(array $event): array
    {
        $signals    = [];
        $event_type = (string) ($event['event_type'] ?? '');
        $url        = (string) ($event['url'] ?? '');
        $browser    = $this->extractBrowser($event);
        $network    = $this->extractNetwork($event);

        if ($event_type === 'js_error') {
            $signals[] = self::SIGNAL_REPEATED_JS_ERROR;
        }

        if (
            ($event_type === 'api_error' || $event_type === 'js_error') && str_contains($url, '/checkout')
        ) {
            $signals[] = self::SIGNAL_CHECKOUT_FAILURE;
        }

        if (
            ($event_type === 'network_issue' || $event_type === 'js_error') && in_array($network, self::SLOW_NETWORKS, true)
        ) {
            $signals[] = self::SIGNAL_SLOW_NETWORK_ERROR;
        }

        if ($browser === 'Safari' && $event_type === 'js_error') {
            $signals[] = self::SIGNAL_SAFARI_FEATURE_BREAK;
        }

        if ($event_type === 'session_end' || $event_type === 'session_start') {
            $signals[] = self::SIGNAL_UNSTABLE_SESSION;
        }

        return array_values(array_unique($signals));
    }

    /**
     * Extracts the browser value from the event, with context_json fallback.
     *
     * @param array<string, mixed> $event
     */
    private function extractBrowser(array $event): string
    {
        if (isset($event['browser']) && (string) $event['browser'] !== '') {
            return (string) $event['browser'];
        }

        $context = $this->decodeContextJson($event);
        return (string) ($context['browser'] ?? '');
    }

    /**
     * Extracts the network value from the event, with context_json fallback.
     *
     * @param array<string, mixed> $event
     */
    private function extractNetwork(array $event): string
    {
        if (isset($event['network']) && (string) $event['network'] !== '') {
            return (string) $event['network'];
        }

        $context = $this->decodeContextJson($event);
        return (string) ($context['network'] ?? '');
    }

    /**
     * Decodes the context_json field if present.
     *
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function decodeContextJson(array $event): array
    {
        $raw = (string) ($event['context_json'] ?? '');
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
