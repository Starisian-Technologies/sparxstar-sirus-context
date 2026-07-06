<?php

/**
 * EnvironmentResolver - Builds a client-first EnvironmentRecord with UA fallback only.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\services;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\core\EnvironmentRecord;

/**
 * Resolves the browser, OS, device, network, and privacy-safe location for the current request.
 */
final class EnvironmentResolver
{
    /** @var array<int, string> */
    private const GEO_COUNTRY_KEYS = [ 'country', 'countryCode', 'country_code' ];

    /** @var array<int, string> */
    private const GEO_REGION_KEYS = [ 'region', 'regionName' ];

    /** @var array<int, string> */
    private const GEO_APPROX_LAT_KEYS = [ 'approx_lat', 'approxLat' ];

    /** @var array<int, string> */
    private const GEO_APPROX_LNG_KEYS = [ 'approx_lng', 'approxLng' ];

    private ?EnvironmentRecord $resolved = null;

    /**
     * Resolves the full environment record.
     *
     * Client-submitted signals are authoritative. UA parsing is used only to fill gaps.
     *
     * @param array<string, mixed> $clientSignals Optional client-submitted environment signals.
     */
    public function resolve(array $clientSignals = []): EnvironmentRecord
    {
        if ($clientSignals === [] && $this->resolved instanceof EnvironmentRecord) {
            return $this->resolved;
        }

        $signals = $this->sanitizeClientSignals($clientSignals);
        $rawUa   = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT']))
            : '';

        $fallback = class_exists('DeviceDetector\DeviceDetector')
            ? $this->resolveWithDetector($rawUa)
            : $this->resolveWithFallback($rawUa);

        $record = new EnvironmentRecord(
            environment_id:         $this->buildEnvironmentId($signals, $fallback),
            browser_name:           $this->resolveStringSignal($signals, [ 'browser_name', 'browser', 'client_browser_name' ], $fallback['browser_name']),
            browser_version:        $this->resolveStringSignal($signals, [ 'browser_version', 'client_browser_version' ], $fallback['browser_version']),
            os:                     $this->resolveStringSignal($signals, [ 'os', 'client_os' ], $fallback['os']),
            os_version:             $this->resolveStringSignal($signals, [ 'os_version', 'client_os_version' ], $fallback['os_version']),
            device_type:            $this->resolveStringSignal($signals, [ 'device_type', 'client_device_type' ], $fallback['device_type']),
            device_brand:           $this->resolveStringSignal($signals, [ 'device_brand', 'brand', 'client_device_brand' ], $fallback['device_brand']),
            device_model:           $this->resolveStringSignal($signals, [ 'device_model', 'model', 'client_device_model' ], $fallback['device_model']),
            network_effective_type: $this->resolveNetworkType($signals),
            ip_address:             $this->getRemoteIpAddress(),
            location:               $this->resolveLocation(),
            time_zone:              $this->resolveStringSignal($signals, [ 'time_zone', 'timezone' ]),
            is_bot:                 $this->resolveBooleanSignal($signals, [ 'is_bot' ], $fallback['is_bot']),
            captured_at:            time(),
        );

        if ($clientSignals === []) {
            $this->resolved = $record;
        }

        return $record;
    }

    /**
     * Returns just the browser name.
     */
    public function getBrowserName(): string
    {
        return $this->resolve()->browser_name;
    }

    /**
     * Returns just the OS name.
     */
    public function getOs(): string
    {
        return $this->resolve()->os;
    }

    /**
     * Returns just the device type.
     */
    public function getDeviceType(): string
    {
        return $this->resolve()->device_type;
    }

    /**
     * Returns the effective network type.
     */
    public function getNetworkEffectiveType(): string
    {
        return $this->resolve()->network_effective_type;
    }

    /**
     * Returns the geographic trust zone identifier.
     */
    public function getGeoZone(): string
    {
        $location = $this->resolve()->location;
        $parts    = [];

        foreach ([ 'country', 'region' ] as $key) {
            $value = $location[$key] ?? '';
            if (! is_string($value)) {
                continue;
            }

            if ($value === '') {
                continue;
            }

            $parts[] = strtolower(str_replace([ ' ', '-' ], '_', $value));
        }

        return $parts === [] ? 'unknown' : implode('_', $parts);
    }

    /**
     * Returns the current request IP if present and valid, otherwise ''.
     */
    private function getRemoteIpAddress(): string
    {
        // phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- REMOTE_ADDR is the TCP peer address; it is unslashed, sanitized, and validated with FILTER_VALIDATE_IP below before use
        $remote_addr = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : '';
        // phpcs:enable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

        return filter_var($remote_addr, FILTER_VALIDATE_IP) !== false ? $remote_addr : '';
    }

    /**
     * @param array<string, mixed> $signals
     * @param array<int, string> $keys
     */
    private function resolveStringSignal(array $signals, array $keys, string $fallback = ''): string
    {
        foreach ($keys as $key) {
            $value = $signals[$key] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $sanitized = sanitize_text_field($value);
            if ($sanitized !== '') {
                return $sanitized;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $signals
     * @param array<int, string> $keys
     */
    private function resolveBooleanSignal(array $signals, array $keys, bool $fallback = false): bool
    {
        foreach ($keys as $key) {
            $value = $signals[$key] ?? null;

            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value)) {
                $normalized = strtolower(sanitize_text_field($value));
                if (in_array($normalized, [ '1', 'true', 'yes' ], true)) {
                    return true;
                }

                if (in_array($normalized, [ '0', 'false', 'no' ], true)) {
                    return false;
                }
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $signals
     */
    private function resolveNetworkType(array $signals): string
    {
        $client_type = $this->resolveStringSignal(
            $signals,
            [ 'network_effective_type', 'effective_type', 'network_type' ]
        );

        if ($client_type !== '') {
            return $client_type;
        }

        $filtered = (string) apply_filters('sparxstar_env_network_effective_type', 'unknown');
        if ($filtered !== 'unknown') {
            return $filtered;
        }

        if (PHP_SAPI === 'cli') {
            return 'cli';
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveLocation(): array
    {
        $geo = apply_filters('sparxstar_env_geolocation_lookup', null, $this->getRemoteIpAddress());
        if (! is_array($geo) || $geo === []) {
            return [];
        }

        $location = [];

        $country = $this->extractGeoValue($geo, self::GEO_COUNTRY_KEYS);
        if ($country !== '') {
            $location['country'] = $country;
        }

        $region = $this->extractGeoValue($geo, self::GEO_REGION_KEYS);
        if ($region !== '') {
            $location['region'] = $region;
        }

        $approxLat = $this->extractGeoFloat($geo, self::GEO_APPROX_LAT_KEYS);
        if ($approxLat !== null) {
            $location['approx_lat'] = $approxLat;
        }

        $approxLng = $this->extractGeoFloat($geo, self::GEO_APPROX_LNG_KEYS);
        if ($approxLng !== null) {
            $location['approx_lng'] = $approxLng;
        }

        return $location;
    }

    /**
     * @param array<string, mixed> $geo
     * @param array<int, string> $keys
     */
    private function extractGeoValue(array $geo, array $keys): string
    {
        foreach ($keys as $key) {
            $raw = $geo[$key] ?? '';
            if (! is_scalar($raw)) {
                continue;
            }

            $value = sanitize_text_field((string) $raw);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $geo
     * @param array<int, string> $keys
     */
    private function extractGeoFloat(array $geo, array $keys): ?float
    {
        foreach ($keys as $key) {
            $raw = $geo[$key] ?? null;
            if (! is_numeric($raw)) {
                continue;
            }

            return (float) $raw;
        }

        return null;
    }

    /**
     * Resolves environment details using Matomo DeviceDetector.
     *
     * @param string $ua Raw User-Agent string.
     * @return array{
     *     browser_name: string,
     *     browser_version: string,
     *     os: string,
     *     os_version: string,
     *     device_type: string,
     *     device_brand: string,
     *     device_model: string,
     *     is_bot: bool
     * }
     */
    private function resolveWithDetector(string $ua): array
    {
        try {
            $dd = new \DeviceDetector\DeviceDetector($ua);
            $dd->parse();

            $browserInfo = $dd->getClient();
            $osInfo      = $dd->getOs();

            return [
                'browser_name'    => isset($browserInfo['name'])    && is_string($browserInfo['name']) ? $browserInfo['name'] : 'unknown',
                'browser_version' => isset($browserInfo['version']) && is_string($browserInfo['version']) ? $browserInfo['version'] : '',
                'os'              => isset($osInfo['name'])         && is_string($osInfo['name']) ? $osInfo['name'] : 'unknown',
                'os_version'      => isset($osInfo['version'])      && is_string($osInfo['version']) ? $osInfo['version'] : '',
                'device_type'     => (str_contains($ua, '(iPad;') || $dd->isTablet()) ? 'tablet'
                    : ($dd->isSmartphone() ? 'smartphone'
                    : ($dd->isDesktop() ? 'desktop'
                    : ($dd->isBot() ? 'bot' : 'unknown'))),
                'device_brand' => is_string($dd->getBrandName()) ? $dd->getBrandName() : '',
                'device_model' => is_string($dd->getModel()) ? $dd->getModel() : '',
                'is_bot'       => $dd->isBot(),
            ];
        } catch (\Throwable) {
            return $this->resolveWithFallback($ua);
        }
    }

    /**
     * Lightweight fallback when Matomo DeviceDetector is unavailable.
     *
     * @param string $ua Raw User-Agent string.
     * @return array{
     *     browser_name: string,
     *     browser_version: string,
     *     os: string,
     *     os_version: string,
     *     device_type: string,
     *     device_brand: string,
     *     device_model: string,
     *     is_bot: bool
     * }
     */
    private function resolveWithFallback(string $ua): array
    {
        $resolved = [
            'browser_name'    => 'unknown',
            'browser_version' => '',
            'os'              => 'unknown',
            'os_version'      => '',
            'device_type'     => 'unknown',
            'device_brand'    => '',
            'device_model'    => '',
            'is_bot'          => false,
        ];

        if ($ua === '') {
            return $resolved;
        }

        if (str_contains($ua, 'bot') || str_contains($ua, 'Bot') || str_contains($ua, 'crawler')) {
            $resolved['is_bot']      = true;
            $resolved['device_type'] = 'bot';
        }

        foreach (
            [
                'Edg/'     => 'Microsoft Edge',
                'OPR/'     => 'Opera',
                'Opera/'   => 'Opera',
                'Chrome/'  => 'Chrome',
                'Firefox/' => 'Firefox',
                'Safari/'  => 'Safari',
                'MSIE '    => 'Internet Explorer',
                'Trident/' => 'Internet Explorer',
            ] as $token => $name
        ) {
            if (str_contains($ua, $token)) {
                $resolved['browser_name']    = $name;
                $resolved['browser_version'] = $this->extractVersionFromUserAgent($ua, rtrim($token, ' /'));
                break;
            }
        }

        foreach (
            [
                'Android'    => 'Android',
                'iPhone OS'  => 'iOS',
                'iPad'       => 'iOS',
                'Windows NT' => 'Windows',
                'Mac OS X'   => 'macOS',
                'Linux'      => 'Linux',
            ] as $token => $name
        ) {
            if (str_contains($ua, $token)) {
                $resolved['os']         = $name;
                $resolved['os_version'] = $this->extractVersionFromUserAgent($ua, $token);
                break;
            }
        }

<<<<<<< HEAD
        if (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')) {
=======
        if (str_contains($ua, '(iPad;') || str_contains($ua, 'Tablet')) {
            // iPad UA contains 'Mobile/' suffix, so check tablet tokens first.
>>>>>>> origin/main
            $resolved['device_type'] = 'tablet';
        } elseif (str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone')) {
            $resolved['device_type'] = 'smartphone';
        } elseif ($resolved['device_type'] !== 'bot' && $resolved['os'] !== 'unknown') {
            $resolved['device_type'] = 'desktop';
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $signals
     * @param array<string, mixed> $fallback
     */
    private function buildEnvironmentId(array $signals, array $fallback): string
    {
        $payload = [
            'browser_name'    => $this->resolveStringSignal($signals, [ 'browser_name', 'browser', 'client_browser_name' ], $fallback['browser_name']),
            'browser_version' => $this->resolveStringSignal($signals, [ 'browser_version', 'client_browser_version' ], $fallback['browser_version']),
            'os'              => $this->resolveStringSignal($signals, [ 'os', 'client_os' ], $fallback['os']),
            'os_version'      => $this->resolveStringSignal($signals, [ 'os_version', 'client_os_version' ], $fallback['os_version']),
            'device_type'     => $this->resolveStringSignal($signals, [ 'device_type', 'client_device_type' ], $fallback['device_type']),
            'device_brand'    => $this->resolveStringSignal($signals, [ 'device_brand', 'brand', 'client_device_brand' ], $fallback['device_brand']),
            'device_model'    => $this->resolveStringSignal($signals, [ 'device_model', 'model', 'client_device_model' ], $fallback['device_model']),
            'time_zone'       => $this->resolveStringSignal($signals, [ 'time_zone', 'timezone' ]),
        ];

        return hash('sha256', (string) wp_json_encode($payload));
    }

    /**
     * @param array<string, mixed> $signals
     * @return array<string, mixed>
     */
    private function sanitizeClientSignals(array $signals): array
    {
        $sanitized = [];

        foreach ($signals as $key => $value) {
            $cleanKey = sanitize_key((string) $key);
            if ($cleanKey === '') {
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $sanitized[$cleanKey] = $value;
                continue;
            }

            if (is_string($value)) {
                $sanitized[$cleanKey] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Extracts a simple version token after the given UA marker.
     */
    private function extractVersionFromUserAgent(string $ua, string $token): string
    {
        $pattern = '/' . preg_quote($token, '/') . '[\\s\\/:_]+([0-9A-Za-z._-]+)/';
        if (preg_match($pattern, $ua, $matches) !== 1) {
            return '';
        }

        return sanitize_text_field($matches[1] ?? '');
    }
}
