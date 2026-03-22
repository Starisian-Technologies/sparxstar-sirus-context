<?php

/**
 * SirusRateLimit - Transient-based fixed-window rate limiter.
 *
 * Limits event ingestion to RATE_LIMIT_MAX events per device per hour.
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
    private const RATE_LIMIT_WINDOW = 3600; // 1 hour
    private const RATE_LIMIT_MAX    = 200;  // events per device per hour
    private const KEY_PREFIX        = 'sirus_rl_';

    /**
     * Returns true if the request should be allowed, false if rate-limited.
     * Uses a fixed-window strategy via WP transients.
     *
     * @param string $device_id The device identifier.
     * @return bool
     */
    public function allow(string $device_id): bool
    {
        $key  = self::KEY_PREFIX . md5($device_id);
        $data = get_transient($key);

        if ($data === false) {
            set_transient($key, ['count' => 1, 'window_start' => time()], self::RATE_LIMIT_WINDOW + 60);
            return true;
        }

        $data  = is_array($data) ? $data : ['count' => 0, 'window_start' => time()];
        $start = (int) ($data['window_start'] ?? time());
        $count = (int) ($data['count'] ?? 0);

        if ((time() - $start) >= self::RATE_LIMIT_WINDOW) {
            // Window has expired — reset and allow. Extra 60 seconds TTL guards against
            // clock drift and ensures WP's transient GC doesn't prune mid-window.
            set_transient($key, ['count' => 1, 'window_start' => time()], self::RATE_LIMIT_WINDOW + 60);
            return true;
        }

        if ($count >= self::RATE_LIMIT_MAX) {
            return false;
        }

        $data['count'] = $count + 1;
        set_transient($key, $data, self::RATE_LIMIT_WINDOW + 60);
        return true;
    }
}
