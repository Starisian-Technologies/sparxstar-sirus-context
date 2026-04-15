<?php

/**
 * DeviceContinuity - Resolves or registers device identity across requests.
 *
 * Drift Tolerance Model (v1.2):
 *
 * Two signals govern device resolution:
 *
 *   Hard Anchor  — device_id + device_secret (server-issued pair)
 *   Soft Signal  — fingerprint_hash (probabilistic, may change)
 *
 * Decision matrix:
 *   device_id match + secret match + fingerprint match  → touch last_seen, return
 *   device_id match + secret match + fingerprint DRIFT  → accept as same device,
 *                                                          update fingerprint + increment drift_score
 *   device_id match + secret MISMATCH                  → do NOT trust device_id claim,
 *                                                          fall through to fingerprint lookup
 *   fingerprint match (no device_id or unverified)     → touch last_seen, return
 *   no match                                            → register new device
 *
 * Treating a fingerprint mismatch as "new device" would log users out whenever
 * they update their browser or switch from Wi-Fi to cellular. The device_secret
 * is the authentication gate; the fingerprint is only evidence of environment change.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Manages device continuity by resolving existing records or registering new devices.
 */
final class DeviceContinuity
{
    /**
     * @param DeviceRepositoryInterface $repository The device persistence layer.
     */
    public function __construct(private readonly DeviceRepositoryInterface $repository)
    {
    }

    /**
     * Resolves a device by ID/secret pair or fingerprint hash, or registers a new one.
     *
     * @param string $device_id The device UUID from the client's localStorage.
     * @param string $device_secret The device secret from the client's localStorage.
     * @param string $fingerprint_hash SHA-256 hash of the client fingerprint (server-derived).
     * @param array $environment_data Raw environment data from the client.
     */
    public function resolveDevice(
        string $device_id,
        string $device_secret,
        string $fingerprint_hash,
        array $environment_data
    ): DeviceRecord {
        $matcher = new DeviceMatcher();

        // ── Path 1: Hard-anchor lookup — device_id + secret ───────────────────
        if ($device_id !== '') {
            $existing = $this->repository->findByDeviceId($device_id);

            if ($existing !== null && $existing->isActive() && $existing->verifySecret($device_secret)) {
                if (
                    $fingerprint_hash !== '' && $existing->fingerprint_hash !== '' && $existing->fingerprint_hash !== $fingerprint_hash
                ) {
                    // Fingerprint changed on a verified (secret-confirmed) device.
                    // The hard anchor proves this is the same device, so we never
                    // register a new device here. Classify the change:
                    //   STRONG_MATCH  — impossible (hashes differ, so score = 0.0)
                    //   WEAK_MATCH    — impossible with opaque SHA-256 hashes (score is 0 or 1)
                    //   NO_MATCH      — score = 0.0; but since device is verified, treat as WEAK
                    //                   (environment changed, step-up required, not a new device).
                    $score = $matcher->scoreHash($existing->fingerprint_hash, $fingerprint_hash);
                    $match = DeviceMatcher::classify($score);

                    if ($match === MatchResult::STRONG_MATCH) {
                        // Hash comparison returned 1.0 — this branch should not be reached
                        // because the outer condition already checked inequality.
                        // Touch last_seen and return existing record.
                        $this->repository->updateLastSeen($device_id);
                        return $existing;
                    }

                    // WEAK_MATCH or NO_MATCH on a verified device → restore + flag STEP_UP_REQUIRED.
                    // The stored record's trust_level is NOT persisted as STEP_UP_REQUIRED;
                    // the flag is carried in the in-memory DeviceRecord only, so the DB
                    // retains the actual credential level for future requests.
                    $this->repository->updateFingerprintHash($device_id, $fingerprint_hash);
                    $this->repository->incrementDriftScore($device_id);

                    return new DeviceRecord(
                        device_id:        $existing->device_id,
                        device_secret:    $existing->device_secret,
                        fingerprint_hash: $fingerprint_hash,
                        environment_json: $existing->environment_json,
                        first_seen:       $existing->first_seen,
                        last_seen:        time(),
                        trust_level:      StepUpPolicy::TRUST_LEVEL_STEP_UP_REQUIRED,
                        drift_score:      $existing->drift_score + 1,
                    );
                }

                // No fingerprint change — STRONG_MATCH, touch last_seen and return.
                $this->repository->updateLastSeen($device_id);
                return $existing;
            }
            // If secret mismatch or expired: fall through to fingerprint lookup.
            // Do NOT register a new device based on an unverified device_id claim.
        }

        // ── Path 2: Soft-signal lookup — fingerprint hash only ─────────────────
        if ($fingerprint_hash !== '') {
            $by_fp = $this->repository->findByFingerprintHash($fingerprint_hash);
            if ($by_fp !== null) {
                // Exact hash match → STRONG_MATCH.
                $match = DeviceMatcher::classify(
                    $matcher->scoreHash($by_fp->fingerprint_hash, $fingerprint_hash)
                );

                if ($match === MatchResult::STRONG_MATCH) {
                    $this->repository->updateLastSeen($by_fp->device_id);
                    return $by_fp;
                }

                // WEAK_MATCH or NO_MATCH with only a soft signal → cannot confidently
                // recover the device without the hard anchor. Fall through to registration.
            }
        }

        // ── Path 3: First visit (NO_MATCH) — register brand-new device ─────────
        return $this->registerDevice($fingerprint_hash, $environment_data);
    }

    /**
     * Creates and persists a brand-new DeviceRecord.
     * Both device_id and device_secret are always server-generated.
     *
     * @param string $fingerprint_hash SHA-256 hash of the client fingerprint.
     * @param array $environment_data Raw environment data from the client.
     */
    public function registerDevice(string $fingerprint_hash, array $environment_data): DeviceRecord
    {
        $now     = time();
        $encoded = wp_json_encode($environment_data);
        if ($encoded === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[Sirus DeviceContinuity] wp_json_encode failed for environment_data; storing empty object.');
            $encoded = '{}';
        }

        // device_secret: 32 random bytes → 64-char hex. Server never exposes raw bytes.
        $device_secret = bin2hex(random_bytes(32));

        $record = new DeviceRecord(
            device_id:        wp_generate_uuid4(),
            device_secret:    $device_secret,
            fingerprint_hash: $fingerprint_hash,
            environment_json: $encoded,
            first_seen:       $now,
            last_seen:        $now,
            trust_level:      'anonymous',
            drift_score:      0,
        );

        $this->repository->save($record);

        return $record;
    }

    /**
     * Fractional reduction in continuity_score applied per recorded drift event.
     * At 0.05 per event the score reaches 0.0 after 20 distinct drift events,
     * making high-drift devices easily identifiable while preserving a usable
     * score for moderate environment changes (e.g., Wi-Fi → cellular).
     */
    private const DRIFT_PENALTY_PER_EVENT = 0.05;

    /**
     * Evaluates the continuity state for a resolved DeviceRecord.
     *
     * This is the analysis stage of the two-stage device pipeline:
     *   Stage 1 — resolveDevice()     : fingerprint/ID → DeviceRecord (boundary resolution)
     *   Stage 2 — evaluateContinuity(): DeviceRecord   → continuity state (derived analysis)
     *
     * Per spec §A, this method produces the canonical device-state output consumed by
     * ContextEngine. Output shape is fixed — no optional keys, no dynamic structure.
     *
     * @param DeviceRecord $device A fully resolved DeviceRecord.
     * @return array{device_hash: string, continuity_score: float, risk_flags: array<int, string>}
     *
     * @throws \RuntimeException If the device context is missing (empty device_id or fingerprint_hash).
     */
    public function evaluateContinuity(DeviceRecord $device): array
    {
        if ($device->device_id === '') {
            throw new \RuntimeException(
                '[Sirus] Hard fail: device context is missing. A resolved device_id is required.'
            );
        }

        if ($device->fingerprint_hash === '') {
            throw new \RuntimeException(
                '[Sirus] Hard fail: invalid device hash. A non-empty fingerprint_hash is required.'
            );
        }

        // continuity_score: 1.0 for a stable device, reduced by DRIFT_PENALTY_PER_EVENT per drift event,
        // floored at 0.0 so extremely drifted devices do not produce negative scores.
        $continuity_score = max(0.0, 1.0 - ($device->drift_score * self::DRIFT_PENALTY_PER_EVENT));

        // risk_flags: advisory markers — never used for enforcement.
        $risk_flags = [];

        if ($device->drift_score >= 5) {
            $risk_flags[] = 'high_drift';
        }

        if (! $device->isActive()) {
            $risk_flags[] = 'inactive_device';
        }

        if ($device->trust_level === 'anonymous' && $device->drift_score > 0) {
            $risk_flags[] = 'anonymous_with_drift';
        }

        return [
            'device_hash'      => $device->fingerprint_hash,
            'continuity_score' => $continuity_score,
            'risk_flags'       => $risk_flags,
        ];
    }
}
