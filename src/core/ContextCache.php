<?php

/**
 * ContextCache - Request-level in-memory cache for the current SirusContext.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Static, request-scoped cache holding the resolved SirusContext for the current request.
 * No instantiation required; all access is via static methods.
 */
final class ContextCache
{
    /** @var SirusContext|null */
    private static ?SirusContext $context = null;

    /** Prevent instantiation. */
    private function __construct()
    {
    }

    /**
     * Returns the cached SirusContext, or null if none has been set.
     */
    public static function get(): ?SirusContext
    {
        return self::$context;
    }

    /**
     * Stores the given SirusContext in the request-level cache.
     *
     * @param SirusContext $context The context to cache.
     */
    public static function set(SirusContext $context): void
    {
        self::$context = $context;
    }

    /**
     * Clears the cached context, forcing re-resolution on the next call.
     */
    public static function clear(): void
    {
        self::$context = null;
    }
}
