<?php

declare(strict_types=1);

/**
 * Service for performing GeoIP lookups.
 *
 * Privacy rules (non-negotiable per spec §H):
 * - Location output is limited to country + region only (approx_lat / approx_lng).
 * - Exact coordinates (city-level or finer) are never stored unless a callback
 *   on the sparxstar_env_geolocation_lookup filter explicitly reintroduces them.
 * - The sparxstar_env_geolocation_lookup filter receives region-level,
 *   privacy-sanitized data produced by this service; custom callbacks may further
 *   restrict or, if they deliberately choose, widen this data.
 */

namespace Starisian\SparxstarUEC\services;

if (! defined('ABSPATH')) {
    exit;
}

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Starisian\SparxstarUEC\helpers\StarLogger;

final class SparxstarUECGeoIPService
{
    /**
     * Look up an IP address and return its geographic information.
     *
     * Output is deliberately limited to region-level data. City-level fields
     * (exact city name, postal code, precise coordinates) are stripped before
     * the result is returned or cached.
     *
     * Supports both ipinfo.io (API) and MaxMind GeoIP2 (local database).
     *
     * @param string $ip_address The IP to look up.
     * @return array|null The location data or null if lookup fails.
     */
    public function lookup(string $ip_address): ?array
    {
        try {
            // Validate IP address
            if (! filter_var($ip_address, FILTER_VALIDATE_IP)) {
                return null;
            }

            // Get provider selection from settings
            $provider = get_option('sparxstar_uec_geoip_provider', 'none');

            if ($provider === 'none') {
                return null;
            }

            // Check cache first
            $transient_key = 'sparxstar_geoip_' . md5($ip_address);
            $cached_data   = get_transient($transient_key);
            if ($cached_data !== false && is_array($cached_data)) {
                return $cached_data;
            }

            // Route to appropriate provider
            $raw_data = null;
            if ($provider === 'ipinfo') {
                $raw_data = $this->lookup_ipinfo($ip_address);
            } elseif ($provider === 'maxmind') {
                $raw_data = $this->lookup_maxmind($ip_address);
            }

            if (! is_array($raw_data) || $raw_data === []) {
                return null;
            }

            // Enforce region-level privacy — strip city/postal/precise coords.
            $location_data = $this->to_region_level($raw_data);

            /**
             * Filter: sparxstar_env_geolocation_lookup
             *
             * Allows an external provider or grant mechanism to supply richer
             * location data. The default contract remains region-level only.
             * Any override must handle consent verification independently.
             *
             * @param array $location_data Region-level location data.
             * @param string $ip_address The (non-anonymized) IP being resolved.
             */
            $location_data = (array) apply_filters('sparxstar_env_geolocation_lookup', $location_data, $ip_address);

            // Cache the result for the configured TTL (default 24 hours).
            $ttl = (int) apply_filters('sparxstar_env_geolocation_ttl', DAY_IN_SECONDS);
            set_transient($transient_key, $location_data, $ttl);

            return $location_data;
        } catch (\Throwable $throwable) {
            StarLogger::log('SparxstarUECGeoIPService', $throwable);
            return null;
        }
    }

    /**
     * Strips all fields below region level, returning only the allowed subset.
     *
     * Allowed fields:
     *   country     — two-letter country code or full name
     *   region      — state / province / subdivision name
     *   approx_lat  — latitude rounded to 1 decimal place (~11 km precision)
     *   approx_lng  — longitude rounded to 1 decimal place (~11 km precision)
     *
     * @param array $raw Raw location data from the provider.
     * @return array Region-level location data.
     */
    private function to_region_level(array $raw): array
    {
        return [
            'country'    => sanitize_text_field((string) ($raw['country'] ?? '')),
            'region'     => sanitize_text_field((string) ($raw['region'] ?? '')),
            'approx_lat' => isset($raw['latitude']) ? round((float) $raw['latitude'], 1) : null,
            'approx_lng' => isset($raw['longitude']) ? round((float) $raw['longitude'], 1) : null,
        ];
    }

    /**
     * Perform lookup using ipinfo.io API.
     *
     * @param string $ip_address The IP to look up.
     * @return array|null Raw location data or null.
     */
    private function lookup_ipinfo(string $ip_address): ?array
    {
        try {
            $api_key = get_option('sparxstar_uec_ipinfo_api_key', '');

            if (empty($api_key)) {
                return null;
            }

            $url      = sprintf('https://ipinfo.io/%s?token=%s', $ip_address, $api_key);
            $response = wp_remote_get($url);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (! is_array($data)) {
                return null;
            }

            return [
                'country'   => sanitize_text_field($data['country'] ?? ''),
                'region'    => sanitize_text_field($data['region'] ?? ''),
                'latitude'  => isset($data['loc']) ? (float) explode(',', (string) $data['loc'])[0] : null,
                'longitude' => isset($data['loc']) ? (float) explode(',', (string) $data['loc'])[1] : null,
            ];
        } catch (\Throwable $throwable) {
            StarLogger::log('SparxstarUECGeoIPService', $throwable);
            return null;
        }
    }

    /**
     * Perform lookup using MaxMind GeoIP2 local database.
     *
     * @param string $ip_address The IP to look up.
     * @return array|null Raw location data or null.
     */
    private function lookup_maxmind(string $ip_address): ?array
    {
        try {
            $db_path = get_option('sparxstar_uec_maxmind_db_path', '');

            if (empty($db_path) || ! file_exists($db_path)) {
                return null;
            }

            if (! class_exists(\GeoIp2\Database\Reader::class)) {
                StarLogger::warning(
                    'SparxstarUECGeoIPService',
                    'MaxMind GeoIP2 library not found. Run: composer require geoip2/geoip2',
                    [ 'method' => 'lookup_maxmind' ]
                );
                return null;
            }

            $reader = new Reader($db_path);
            $record = $reader->city($ip_address);

            return [
                'country'   => sanitize_text_field($record->country->name ?? ''),
                'region'    => sanitize_text_field($record->mostSpecificSubdivision->name ?? ''),
                'latitude'  => $record->location->latitude  ?? null,
                'longitude' => $record->location->longitude ?? null,
            ];
        } catch (\Throwable $throwable) {
            if (class_exists(AddressNotFoundException::class) && $throwable instanceof AddressNotFoundException) {
                return null;
            }

            StarLogger::log('SparxstarUECGeoIPService', $throwable);
            return null;
        }
    }
}
