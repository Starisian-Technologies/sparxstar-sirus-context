# SirusRateLimit

**Namespace:** `Starisian\Sparxstar\Sirus\helpers`

**Full Class Name:** `Starisian\Sparxstar\Sirus\helpers\SirusRateLimit`

## Description

SirusRateLimit - Transient-based fixed-window rate limiter.
Limits event ingestion to RATE_LIMIT_MAX events per dimension per hour.
Two dimensions are enforced: device_id (always) and IP subnet (when provided).
A request is blocked if EITHER dimension is at or over the limit.
Counters for both dimensions are only incremented when both pass.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Fixed-window rate limiter backed by WP transients.
One instance per request; stateless across requests.

## Methods

### `allow(string $device_id, string $ip_subnet = '')`

SirusRateLimit - Transient-based fixed-window rate limiter.
Limits event ingestion to RATE_LIMIT_MAX events per dimension per hour.
Two dimensions are enforced: device_id (always) and IP subnet (when provided).
A request is blocked if EITHER dimension is at or over the limit.
Counters for both dimensions are only incremented when both pass.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Fixed-window rate limiter backed by WP transients.
One instance per request; stateless across requests.
/
final class SirusRateLimit
{
    private const RATE_LIMIT_WINDOW = 3600;

    // 1 hour
    private const RATE_LIMIT_MAX = 200;

    // events per dimension per hour
    private const KEY_PREFIX = 'sirus_rl_';

    /**
Extra TTL added beyond the window to guard against clock drift and
ensure WP's transient GC does not prune entries mid-window.
/
    private const RATE_LIMIT_GRACE_PERIOD = 60; // seconds

    /**
Returns true if the request should be allowed, false if rate-limited.
Enforces two independent dimensions:
- `device_id` (always checked)
- IP subnet (checked only when `$ip_subnet` is non-empty)
A request is blocked immediately if EITHER dimension is at the limit.
Both counters are incremented only when both dimensions pass, preventing
over-counting when one dimension blocks.
@param string $device_id The device identifier (client-supplied).
@param string $ip_subnet Anonymous IP subnet from IpAnonymizer::ipSubnet() (optional).
@return bool True when the request is within both limits.

### `isAtLimit(string $key)`

Returns true if the counter for this key is currently at or over the limit.
Does not modify any stored value.
@param string $key Fully-formed transient key.

### `recordHit(string $key)`

Increments the hit counter for the given key.
Resets the window when the previous one has expired.
@param string $key Fully-formed transient key.

