<?php

/**
 * EnvironmentRecord - Immutable client-first environment snapshot with privacy invariants.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\helpers\IpAnonymizer;

/**
 * Represents the request environment captured for the current device context.
 */
final readonly class EnvironmentRecord
{
    public string $environment_id;

    public string $browser_name;

    public string $browser_version;

    public string $os;

    public string $os_version;

    public string $device_type;

    public string $device_brand;

    public string $device_model;

    public string $network_effective_type;

    public string $ip_address;

    /**
     * Region-level location payload safe for transport and storage.
     *
     * @var array<string, mixed>
     */
    public array $location;

    public string $time_zone;

    public bool $is_bot;

    public int $captured_at;

    /**
     * @param string               $environment_id         Stable identifier for the captured environment.
     * @param string               $browser_name           Client-first browser name.
     * @param string               $browser_version        Browser version when available.
     * @param string               $os                     Client-first operating system name.
     * @param string               $os_version             Operating system version when available.
     * @param string               $device_type            Device class (desktop/tablet/smartphone/etc).
     * @param string               $device_brand           Device brand when available.
     * @param string               $device_model           Device model when available.
     * @param string               $network_effective_type Effective network type signal.
     * @param string               $ip_address             Raw or anonymized IP; anonymized at construction.
     * @param array<string, mixed> $location               Region-level location payload only.
     * @param string               $time_zone              Client time zone string.
     * @param bool                 $is_bot                 Whether the environment appears to be automated.
     * @param int                  $captured_at            Unix timestamp when captured.
     */
    public function __construct(
        string $environment_id,
        string $browser_name,
        string $browser_version,
        string $os,
        string $os_version,
        string $device_type,
        string $device_brand,
        string $device_model,
        string $network_effective_type,
        string $ip_address,
        array $location,
        string $time_zone,
        bool $is_bot,
        int $captured_at,
    ) {
        $this->environment_id         = $this->sanitizeString($environment_id);
        $this->browser_name           = $this->sanitizeString($browser_name, 'unknown');
        $this->browser_version        = $this->sanitizeString($browser_version);
        $this->os                     = $this->sanitizeString($os, 'unknown');
        $this->os_version             = $this->sanitizeString($os_version);
        $this->device_type            = $this->sanitizeString($device_type, 'unknown');
        $this->device_brand           = $this->sanitizeString($device_brand);
        $this->device_model           = $this->sanitizeString($device_model);
        $this->network_effective_type = $this->sanitizeNetworkType($network_effective_type);
        $this->ip_address             = IpAnonymizer::anonymize($ip_address);
        $this->location               = $this->normalizeLocation($location);
        $this->time_zone              = $this->sanitizeString($time_zone);
        $this->is_bot                 = $is_bot;
        $this->captured_at            = $captured_at > 0 ? $captured_at : time();
    }

    /**
     * Returns a flat array representation compatible with legacy accessors.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'environment_id'         => $this->environment_id,
            'browser_name'           => $this->browser_name,
            'browser_version'        => $this->browser_version,
            'os'                     => $this->os,
            'os_version'             => $this->os_version,
            'device_type'            => $this->device_type,
            'device_brand'           => $this->device_brand,
            'device_model'           => $this->device_model,
            'network_effective_type' => $this->network_effective_type,
            'ip_address'             => $this->ip_address,
            'location'               => $this->location,
            'time_zone'              => $this->time_zone,
            'is_bot'                 => $this->is_bot,
            'captured_at'            => $this->captured_at,
        ];
    }

    /**
     * @param string $value Raw string input.
     * @param string $default Fallback value when the sanitized result is empty.
     */
    private function sanitizeString(string $value, string $default = ''): string
    {
        $sanitized = sanitize_text_field($value);

        return $sanitized !== '' ? $sanitized : $default;
    }

    /**
     * @param string $networkType Raw effective type.
     */
    private function sanitizeNetworkType(string $networkType): string
    {
        $sanitized = strtolower($this->sanitizeString($networkType, 'unknown'));
        $allowed   = [ 'unknown', 'slow-2g', '2g', '3g', '4g', '5g', 'wifi', 'ethernet', 'offline', 'cli' ];

        return in_array($sanitized, $allowed, true) ? $sanitized : 'unknown';
    }

    /**
     * Removes exact coordinates and any unexpected fields from the location payload.
     *
     * @param array<string, mixed> $location Raw location payload.
     * @return array<string, mixed>
     */
    private function normalizeLocation(array $location): array
    {
        $normalized = [];

        foreach ([ 'country', 'region' ] as $key) {
            if (! isset($location[$key]) || ! is_scalar($location[$key])) {
                continue;
            }

            $value = sanitize_text_field((string) $location[$key]);
            if ($value !== '') {
                $normalized[$key] = $value;
            }
        }

        foreach ([ 'approx_lat', 'approx_lng' ] as $key) {
            if (! isset($location[$key]) || ! is_numeric($location[$key])) {
                continue;
            }

            $normalized[$key] = round((float) $location[$key], 2);
        }

        return $normalized;
    }
}
