<?php

/**
 * EnvironmentResolver - Resolves browser, OS, and network environment via Matomo DeviceDetector.
 *
 * This class owns environment detection for the Sirus context engine.
 * It wraps Matomo DeviceDetector when available, and falls back to
 * lightweight server-side heuristics when the library is not installed.
 *
 * StarUserEnv::get_browser_name(), get_os(), get_device_type(), and
 * get_network_effective_type() all route through this resolver.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\services;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves the browser, OS, device type, and network effective type for the current request.
 *
 * When Matomo DeviceDetector is available (matomo/device-detector), it is used for
 * accurate UA parsing. Without it, a lightweight regex-based fallback is used.
 * Consumers receive the same output shape regardless of which path is taken.
 */
final class EnvironmentResolver
{
    /**
     * Resolved environment data, keyed by environment field name.
     *
     * @var array<string, string>|null
     */
    private ?array $resolved = null;

    /**
     * Resolves the full environment record for the current User-Agent.
     *
     * Result is memoised within the request; subsequent calls return the
     * same array without re-parsing. The returned array always contains
     * all four environment fields.
     *
     * @return array{
     *     browser_name: string,
     *     os: string,
     *     device_type: string,
     *     network_effective_type: string
     * }
     */
    public function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']))
            : '';

        $this->resolved = class_exists('DeviceDetector\DeviceDetector')
            ? $this->resolveWithDetector($raw_ua)
            : $this->resolveWithFallback($raw_ua);

        // Network effective type is not available server-side; set to 'unknown'.
        // Overridden downstream by client-reported signal via REST event.
        $this->resolved['network_effective_type'] = $this->resolveNetworkType();

        return $this->resolved;
    }

    /**
     * Returns just the browser name.
     *
     * @return string Browser name, e.g. "Chrome", or "unknown".
     */
    public function getBrowserName(): string
    {
        return $this->resolve()['browser_name'];
    }

    /**
     * Returns just the OS name.
     *
     * @return string OS name, e.g. "Android", or "unknown".
     */
    public function getOs(): string
    {
        return $this->resolve()['os'];
    }

    /**
     * Returns just the device type.
     *
     * @return string Device type, e.g. "smartphone", "desktop", or "unknown".
     */
    public function getDeviceType(): string
    {
        return $this->resolve()['device_type'];
    }

    /**
     * Returns the network effective type.
     *
     * Server-side this is always "unknown" unless overridden by a client signal.
     *
     * @return string Network type, e.g. "4g", "3g", "2g", "slow-2g", or "unknown".
     */
    public function getNetworkEffectiveType(): string
    {
        return $this->resolve()['network_effective_type'];
    }

    /**
     * Resolves environment using Matomo DeviceDetector.
     *
     * @param string $ua Raw User-Agent string.
     * @return array{browser_name: string, os: string, device_type: string, network_effective_type: string}
     */
    private function resolveWithDetector(string $ua): array
    {
        try {
            $dd = new \DeviceDetector\DeviceDetector($ua);
            $dd->parse();

            $browser_info = $dd->getClient();
            $os_info      = $dd->getOs();

            $browser_name = '';
            if (is_array($browser_info) && isset($browser_info['name']) && is_string($browser_info['name'])) {
                $browser_name = $browser_info['name'];
            }

            $os = '';
            if (is_array($os_info) && isset($os_info['name']) && is_string($os_info['name'])) {
                $os = $os_info['name'];
            }

            $device_type = $dd->isSmartphone() ? 'smartphone'
                : ($dd->isTablet()    ? 'tablet'
                : ($dd->isDesktop()   ? 'desktop'
                : ($dd->isBot()       ? 'bot'
                : 'unknown')));

            return [
                'browser_name'           => $browser_name !== '' ? $browser_name : 'unknown',
                'os'                     => $os !== '' ? $os : 'unknown',
                'device_type'            => $device_type,
                'network_effective_type' => 'unknown',
            ];
        } catch (\Throwable) {
            return $this->resolveWithFallback($ua);
        }
    }

    /**
     * Lightweight regex-based fallback when Matomo DeviceDetector is not installed.
     *
     * @param string $ua Raw User-Agent string.
     * @return array{browser_name: string, os: string, device_type: string, network_effective_type: string}
     */
    private function resolveWithFallback(string $ua): array
    {
        $browser_name = 'unknown';
        $os           = 'unknown';
        $device_type  = 'unknown';

        if ($ua === '') {
            return ['browser_name' => $browser_name, 'os' => $os, 'device_type' => $device_type] + ['network_effective_type' => 'unknown'];
        }

        // Browser detection (most-specific first).
        $browser_patterns = [
            'Edg'     => 'Microsoft Edge',
            'OPR'     => 'Opera',
            'Opera'   => 'Opera',
            'Chrome'  => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari'  => 'Safari',
            'MSIE'    => 'Internet Explorer',
            'Trident' => 'Internet Explorer',
        ];
        foreach ($browser_patterns as $token => $name) {
            if (str_contains($ua, $token)) {
                $browser_name = $name;
                break;
            }
        }

        // OS detection.
        $os_patterns = [
            'Android'     => 'Android',
            'iPhone'      => 'iOS',
            'iPad'        => 'iOS',
            'Windows NT'  => 'Windows',
            'Macintosh'   => 'macOS',
            'Linux'       => 'Linux',
        ];
        foreach ($os_patterns as $token => $name) {
            if (str_contains($ua, $token)) {
                $os = $name;
                break;
            }
        }

        // Device type.
        if (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone')) {
            $device_type = 'smartphone';
        } elseif (str_contains($ua, 'Tablet') || str_contains($ua, 'iPad')) {
            $device_type = 'tablet';
        } elseif ($os !== 'unknown') {
            $device_type = 'desktop';
        }

        return ['browser_name' => $browser_name, 'os' => $os, 'device_type' => $device_type] + ['network_effective_type' => 'unknown'];
    }

    /**
     * Returns the network effective type.
     *
     * Server-side detection is not possible; returns 'unknown'.
     * Client-reported values are set via the SirusEventController.
     *
     * @return string Always 'unknown' server-side.
     */
    private function resolveNetworkType(): string
    {
        /**
         * Filter: sparxstar_env_network_effective_type
         * Allow overriding the network type from an external signal or test.
         *
         * @param string $type Default 'unknown'.
         */
        return (string) apply_filters('sparxstar_env_network_effective_type', 'unknown');
    }
}
