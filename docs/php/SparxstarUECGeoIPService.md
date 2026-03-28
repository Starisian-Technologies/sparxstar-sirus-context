# SparxstarUECGeoIPService

**Namespace:** `Starisian\SparxstarUEC\services`

**Full Class Name:** `Starisian\SparxstarUEC\services\SparxstarUECGeoIPService`

## Methods

### `lookup(string $ip_address)`

Service for performing GeoIP lookups.
Privacy rules (non-negotiable per spec §H):
- Location output is limited to country + region only (approx_lat / approx_lng).
- Exact coordinates (city-level or finer) are never stored unless a callback
  on the sparxstar_env_geolocation_lookup filter explicitly reintroduces them.
- The sparxstar_env_geolocation_lookup filter receives region-level,
  privacy-sanitized data produced by this service; custom callbacks may further
  restrict or, if they deliberately choose, widen this data.
/

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
Look up an IP address and return its geographic information.
Output is deliberately limited to region-level data. City-level fields
(exact city name, postal code, precise coordinates) are stripped before
the result is returned or cached.
Supports both ipinfo.io (API) and MaxMind GeoIP2 (local database).
@param string $ip_address The IP to look up.
@return array|null The location data or null if lookup fails.

### `to_region_level(array $raw)`

Filter: sparxstar_env_geolocation_lookup
Allows an external provider or grant mechanism to supply richer
location data. The default contract remains region-level only.
Any override must handle consent verification independently.
@param array $location_data Region-level location data.
@param string $ip_address The (non-anonymized) IP being resolved.
/
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
Strips all fields below region level, returning only the allowed subset.
Allowed fields:
  country     — two-letter country code or full name
  region      — state / province / subdivision name
  approx_lat  — latitude rounded to 1 decimal place (~11 km precision)
  approx_lng  — longitude rounded to 1 decimal place (~11 km precision)
@param array $raw Raw location data from the provider.
@return array Region-level location data.

### `lookup_ipinfo(string $ip_address)`

Perform lookup using ipinfo.io API.
@param string $ip_address The IP to look up.
@return array|null Raw location data or null.

### `lookup_maxmind(string $ip_address)`

Perform lookup using MaxMind GeoIP2 local database.
@param string $ip_address The IP to look up.
@return array|null Raw location data or null.

