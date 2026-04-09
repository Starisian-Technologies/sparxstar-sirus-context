<?php

/**
 * DeviceMatcher - Fingerprint scoring and similarity thresholds for device continuity.
 *
 * Defines the numerical thresholds used by DeviceContinuity when deciding whether
 * a new fingerprint belongs to the same device. A score of 1.0 is a perfect match;
 * a score below DRIFT_THRESHOLD indicates meaningful device change.
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
     * Minimum similarity score for the device to be considered the SAME device without drift.
     * Scores >= EXACT_THRESHOLD → identical fingerprint, no drift recorded.
     */
    public const EXACT_THRESHOLD = 1.0;

    /**
     * Boundary score between a drifting device and a new device.
     *
     * - Scores >= DRIFT_THRESHOLD and < EXACT_THRESHOLD → same device, drift recorded.
     * - Scores <  DRIFT_THRESHOLD                       → new device registration.
     *
     * There is intentionally a single boundary constant because the upper bound of
     * "new device" and the lower bound of "drift" are the same point on the scale.
     */
    public const DRIFT_THRESHOLD = 0.6;

    /**
     * Component weights used when scoring a structured fingerprint map.
     * Higher weight = more significant signal for device continuity.
     *
     * @var array<string, float>
     */
    private const COMPONENT_WEIGHTS = [
        'canvas_hash'    => 0.30,
        'screen'         => 0.20,
        'timezone'       => 0.15,
        'platform'       => 0.15,
        'languages'      => 0.10,
        'color_depth'    => 0.05,
        'hardware_conc'  => 0.05,
    ];

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

    /**
     * Returns true if the given score indicates an exact fingerprint match.
     *
     * @param float $score Similarity score from scoreHash() or scoreComponents().
     */
    public function isExactMatch(float $score): bool
    {
        return $score >= self::EXACT_THRESHOLD;
    }

    /**
     * Returns true if the given score indicates drift on an otherwise verified device.
     *
     * @param float $score Similarity score.
     */
    public function isDrift(float $score): bool
    {
        return $score >= self::DRIFT_THRESHOLD && $score < self::EXACT_THRESHOLD;
    }

    /**
     * Returns true if the given score indicates a new (unrecognised) device.
     *
     * @param float $score Similarity score.
     */
    public function isNewDevice(float $score): bool
    {
        return $score < self::DRIFT_THRESHOLD;
    }
}
