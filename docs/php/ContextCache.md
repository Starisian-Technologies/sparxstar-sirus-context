# ContextCache

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\ContextCache`

## Description

ContextCache - Request-level in-memory cache for the current SirusContext.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Static, request-scoped cache holding the resolved SirusContext for the current request.
No instantiation required; all access is via static methods.

## Properties

### `$context`

ContextCache - Request-level in-memory cache for the current SirusContext.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Static, request-scoped cache holding the resolved SirusContext for the current request.
No instantiation required; all access is via static methods.
/
final class ContextCache
{
    /** @var SirusContext|null

## Methods

### `__construct()`

ContextCache - Request-level in-memory cache for the current SirusContext.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Static, request-scoped cache holding the resolved SirusContext for the current request.
No instantiation required; all access is via static methods.
/
final class ContextCache
{
    /** @var SirusContext|null */
    private static ?SirusContext $context = null;

    /** Prevent instantiation.

### `get()`

Returns the cached SirusContext, or null if none has been set.

### `set(SirusContext $context)`

Stores the given SirusContext in the request-level cache.
@param SirusContext $context The context to cache.

### `clear()`

Clears the cached context, forcing re-resolution on the next call.

