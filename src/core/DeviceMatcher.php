<?php

/**
 * DeviceMatcher - Fingerprint scoring and three-way classification for device continuity.
 *
 * Implements spec §14.3. Scores are mapped to a MatchResult via classify():
 *   STRONG_MATCH — score >= STRONG_MATCH_THRESHOLD (0.8): restore device normally.
 *   WEAK_MATCH   — score >= WEAK_MATCH_THRESHOLD   (0.6): restore device, flag STEP_UP_REQUIRED.
 *   NO_MATCH     — score <  WEAK_MATCH_THRESHOLD   (0.6): register as new device.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Scores the similarity between two fingerprint hashes and classifies the result.
 *
 * Because the fingerprint_hash is an opaque SHA-256 hex string, full equality is
 * the only byte-level comparison available. Semantic similarity scoring (partial
 * match, component-by-component) is applied to the structured component map when
 * provided by the client.
 */
final class DeviceMatcher
{
    /**
     * Minimum score to classify as STRONG_MATCH (same device, normal restore).
     * A score equal to or above this threshold means the device environment is
     * sufficiently identical to proceed without any additional verification.
     */
    public const STRONG_MATCH_THRESHOLD = 0.8;

    /**
     * Minimum score to classify as WEAK_MATCH (same device, step-up required).
     * Scores between WEAK_MATCH_THRESHOLD (inclusive) and STRONG_MATCH_THRESHOLD
     * (exclusive) indicate meaningful environment change that warrants a step-up
     * challenge before fully restoring the session.
     * Scores below this threshold classify as NO_MATCH (new device registration).
     */
    public const WEAK_MATCH_THRESHOLD = 0.6;

    /**
     * Component weights used when scoring a structured fingerprint map.
     * Higher weight = more significant signal for device continuity.
     *
     * Keys are snake_case and match the server-canonical field names sent from
     * the JS collector (PHP is authoritative for naming; snake_case throughout).
     *
     * @var array<string, float>
     */
    private const COMPONENT_WEIGHTS = [
        'canvas_hash'          => 0.30,
        'screen'               => 0.20,
        'timezone'             => 0.15,
        'platform'             => 0.15,
        'languages'            => 0.10,
        'color_depth'          => 0.05,
        'hardware_concurrency' => 0.05,
    ];

    /**
     * Classifies a similarity score as STRONG_MATCH, WEAK_MATCH, or NO_MATCH.
     *
     * This is the canonical decision function used by DeviceContinuity::resolveDevice()
     * to branch on the outcome of fingerprint comparison.
     *
     * @param float $score Similarity score in [0.0, 1.0].
     */
    public static function classify(float $score): MatchResult
    {
        if ($score >= self::STRONG_MATCH_THRESHOLD) {
            return MatchResult::STRONG_MATCH;
        }

        if ($score >= self::WEAK_MATCH_THRESHOLD) {
            return MatchResult::WEAK_MATCH;
        }

        return MatchResult::NO_MATCH;
    }

    /**
     * Scores the similarity between two opaque fingerprint hashes (SHA-256 hex strings).
     *
     * For opaque hashes, only exact equality is tested. Returns 1.0 for identical hashes,
     * 0.0 for any difference. When component-level scoring is needed, use scoreComponents().
     *
     * @param string $stored  The stored fingerprint hash.
     * @param string $current The candidate fingerprint hash from the current request.
     * @return float 1.0 if identical, 0.0 otherwise.
     */
    public function scoreHash(string $stored, string $current): float
    {
        if ($stored === '' || $current === '') {
            return 0.0;
        }

        return hash_equals($stored, $current) ? 1.0 : 0.0;
    }

    /**
     * Scores the similarity between two structured component maps.
     *
     * Each component that is present and equal in both maps contributes its weighted
     * value to the total score. Components absent from either map are skipped (neutral).
     * The result is normalised to [0.0, 1.0] against the sum of present weights.
     *
     * @param array<string, mixed> $stored  Component map from the device record.
     * @param array<string, mixed> $current Component map from the current request.
     * @return float Similarity score in [0.0, 1.0].
     */
    public function scoreComponents(array $stored, array $current): float
    {
        if (empty($stored) || empty($current)) {
            return 0.0;
        }

        $matched_weight = 0.0;
        $total_weight   = 0.0;

        foreach (self::COMPONENT_WEIGHTS as $key => $weight) {
            if (! array_key_exists($key, $stored) || ! array_key_exists($key, $current)) {
                continue;
            }
            $total_weight += $weight;
            if ($stored[$key] === $current[$key]) {
                $matched_weight += $weight;
            }
        }

        if ($total_weight <= 0.0) {
            return 0.0;
        }

        return $matched_weight / $total_weight;
    }
}
