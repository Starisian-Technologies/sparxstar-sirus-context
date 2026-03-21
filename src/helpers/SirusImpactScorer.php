<?php

/**
 * SirusImpactScorer - Computes numeric impact scores for Sirus events.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Computes integer impact scores and maps them to severity labels.
 */
final class SirusImpactScorer
{
    public const SEVERITY_LOW      = 'low';
    public const SEVERITY_MEDIUM   = 'medium';
    public const SEVERITY_HIGH     = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    private const CHECKOUT_WEIGHT     = 10;
    private const SLOW_NETWORK_WEIGHT = 5;

    private const SLOW_NETWORKS = ['slow-2g', '2g', 'slow-3g'];

    /**
     * Computes an integer impact score for an event + optional cluster context.
     *
     * Formula:
     *   impact_score = (error_count * affected_sessions)
     *                + (CHECKOUT_WEIGHT * 10 if url contains /checkout)
     *                + (SLOW_NETWORK_WEIGHT * 5 if network is slow)
     *
     * For single events (no cluster data), error_count = 1, affected_sessions = 1.
     *
     * @param array<string, mixed> $event   Event data.
     * @param array<string, mixed> $context Optional aggregated cluster context.
     * @return int
     */
    public function score(array $event, array $context = []): int
    {
        $error_count       = isset($context['error_count']) ? (int) $context['error_count'] : 1;
        $affected_sessions = isset($context['affected_sessions']) ? (int) $context['affected_sessions'] : 1;

        $score = $error_count * $affected_sessions;

        $url = (string) ($event['url'] ?? '');
        if (str_contains($url, '/checkout')) {
            $score += self::CHECKOUT_WEIGHT * 10;
        }

        $network = $this->extractNetwork($event);
        if (in_array($network, self::SLOW_NETWORKS, true)) {
            $score += self::SLOW_NETWORK_WEIGHT * 5;
        }

        return $score;
    }

    /**
     * Maps a numeric score to a severity label.
     *
     * 0–9 = low, 10–24 = medium, 25–49 = high, 50+ = critical
     */
    public function severityFromScore(int $score): string
    {
        return match (true) {
            $score >= 50 => self::SEVERITY_CRITICAL,
            $score >= 25 => self::SEVERITY_HIGH,
            $score >= 10 => self::SEVERITY_MEDIUM,
            default      => self::SEVERITY_LOW,
        };
    }

    /**
     * Extracts network from the event, checking denormalized column then context_json.
     *
     * @param array<string, mixed> $event
     */
    private function extractNetwork(array $event): string
    {
        if (isset($event['network']) && (string) $event['network'] !== '') {
            return (string) $event['network'];
        }

        $raw = (string) ($event['context_json'] ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return (string) ($decoded['network'] ?? '');
            }
        }

        return '';
    }
}
