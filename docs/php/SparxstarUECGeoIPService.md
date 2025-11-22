# SparxstarUECGeoIPService

**Namespace:** `Starisian\SparxstarUEC\services`

**Full Class Name:** `Starisian\SparxstarUEC\services\SparxstarUECGeoIPService`

## Methods

### `lookup(string $ip_address)`

Service for performing GeoIP lookups.
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
Supports both ipinfo.io (API) and MaxMind GeoIP2 (local database).
@param string $ip_address The IP to look up.
@return array|null The location data or null if lookup fails.

### `lookup_ipinfo(string $ip_address)`

Perform lookup using ipinfo.io API.
@param string $ip_address The IP to look up.
@return array|null Location data or null.

### `lookup_maxmind(string $ip_address)`

Perform lookup using MaxMind GeoIP2 local database.
@param string $ip_address The IP to look up.
@return array|null Location data or null.

