# HeliosClient

**Namespace:** `Starisian\Sparxstar\Sirus\integrations`

**Full Class Name:** `Starisian\Sparxstar\Sirus\integrations\HeliosClient`

## Methods

### `__construct(private string $base_url = '')`

HeliosClient - Integration client for the Helios trust resolution service.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\integrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
Communicates with the Helios trust-resolution REST endpoint.
Caches successful responses in the WordPress object cache to reduce
repeated remote calls within the same request lifecycle.
/
final readonly class HeliosClient
{
    /** WordPress object cache group. */
    private const CACHE_GROUP = 'sparxstar_sirus';

    /** Seconds to cache a successful Helios response. */
    private const CACHE_TTL = 120;

    /** Helios REST endpoint path. */
    private const ENDPOINT = '/wp-json/helios/v1/trust/resolve';

    /**
@param string $base_url Base URL of the Helios service (including scheme, no trailing slash).

