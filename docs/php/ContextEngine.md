# ContextEngine

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\ContextEngine`

## Description

ContextEngine - Builds and caches the SirusContext for the current request.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Responsible for assembling a fully resolved SirusContext.
Reads the device cookie from the superglobal only in this class.

## Methods

### `current()`

ContextEngine - Builds and caches the SirusContext for the current request.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Responsible for assembling a fully resolved SirusContext.
Reads the device cookie from the superglobal only in this class.
/
final class ContextEngine
{
    /**
Returns the SirusContext for the current request, building it once and caching.
Expired contexts are evicted from the cache and rebuilt transparently so that
stale authority / capability data is never served.

### `buildFromDevice(DeviceRecord $device)`

Builds a SirusContext from an already-resolved DeviceRecord, primes the
request-level cache, and returns it.
Call this immediately after resolving a device in the REST controller so
that every subsequent call to ContextEngine::current() within the same PHP
request is guaranteed to reference the same device. Without this explicit
binding the cookie-based build() path might resolve a different device_id.
@param DeviceRecord $device The resolved and persisted device record.
@return SirusContext A fully initialized context seeded with the device.

### `build()`

Assembles a fresh SirusContext from the current WordPress/request environment.

