<?php

/**
 * SirusRateLimit - Transient-based fixed-window rate limiter.
 *
 * Limits event ingestion to RATE_LIMIT_MAX events per dimension per hour.
 * Two dimensions are enforced: device_id (always) and IP subnet (when provided).
 * A request is blocked if EITHER dimension is at or over the limit.
 * Counters for both dimensions are only incremented when both pass.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Fixed-window rate limiter backed by WP transients.
 * One instance per request; stateless across requests.
 */
final class SirusRateLimit
{
    private const RATE_LIMIT_WINDOW = 3600;

    // 1 hour
    private const RATE_LIMIT_MAX = 200;

    // events per dimension per hour
    private const KEY_PREFIX = 'sirus_rl_';

    /**
     * Extra TTL added beyond the window to guard against clock drift and
     * ensure WP's transient GC does not prune entries mid-window.
     */
    private const RATE_LIMIT_GRACE_PERIOD = 60; // seconds

    /**
     * Returns true if the request should be allowed, false if rate-limited.
     *
     * Enforces two independent dimensions:
     * - `device_id` (always checked)
     * - IP subnet (checked only when `$ip_subnet` is non-empty)
     *
     * A request is blocked immediately if EITHER dimension is at the limit.
     * Both counters are incremented only when both dimensions pass, preventing
     * over-counting when one dimension blocks.
     *
     * @param string $device_id The device identifier (client-supplied).
     * @param string $ip_subnet Anonymous IP subnet from IpAnonymizer::ipSubnet() (optional).
     * @return bool True when the request is within both limits.
     */
    public function allow(string $device_id, string $ip_subnet = ''): bool
    {
        $device_key = self::KEY_PREFIX . md5('device:' . $device_id);
        $ip_key     = $ip_subnet !== '' ? self::KEY_PREFIX . md5('ip:' . $ip_subnet) : '';

        // Phase 1: check both dimensions without committing any counter updates.
        if ($this->isAtLimit($device_key)) {
            return false;
        }

        if ($ip_key !== '' && $this->isAtLimit($ip_key)) {
            return false;
        }

        // Phase 2: both passed — record the hit on each active dimension.
        $this->recordHit($device_key);
        if ($ip_key !== '') {
            $this->recordHit($ip_key);
        }

        return true;
    }

    /**
     * Returns true if the counter for this key is currently at or over the limit.
     * Does not modify any stored value.
     *
     * @param string $key Fully-formed transient key.
     */
    private function isAtLimit(string $key): bool
    {
        $data = get_transient($key);

        if ($data === false) {
            return false;
        }

        $data = is_array($data) ? $data : [
            'count'        => 0,
            'window_start' => time(),
        ];
        $start = (int) ($data['window_start'] ?? time());
        $count = (int) ($data['count'] ?? 0);

        if ((time() - $start) >= self::RATE_LIMIT_WINDOW) {
            // Window has expired — not at limit.
            return false;
        }

        return $count >= self::RATE_LIMIT_MAX;
    }

    /**
     * Increments the hit counter for the given key.
     * Resets the window when the previous one has expired.
     *
     * @param string $key Fully-formed transient key.
     */
    private function recordHit(string $key): void
    {
        $data = get_transient($key);

        if ($data === false) {
            set_transient(
                $key,
                [
                    'count'        => 1,
                    'window_start' => time(),
                ],
                self::RATE_LIMIT_WINDOW + self::RATE_LIMIT_GRACE_PERIOD
            );
            return;
        }

        $data = is_array($data) ? $data : [
            'count'        => 0,
            'window_start' => time(),
        ];
        $start = (int) ($data['window_start'] ?? time());
        $count = (int) ($data['count'] ?? 0);

        if ((time() - $start) >= self::RATE_LIMIT_WINDOW) {
            // Window expired — reset.
            set_transient(
                $key,
                [
                    'count'        => 1,
                    'window_start' => time(),
                ],
                self::RATE_LIMIT_WINDOW + self::RATE_LIMIT_GRACE_PERIOD
            );
            return;
        }

        $data['count'] = $count + 1;
        set_transient($key, $data, self::RATE_LIMIT_WINDOW + self::RATE_LIMIT_GRACE_PERIOD);
    }
}
