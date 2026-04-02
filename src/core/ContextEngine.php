<?php

/**
 * ContextEngine - Builds and caches the SirusContext for the current request.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Responsible for assembling a fully resolved SirusContext.
 * Reads the device cookie from the superglobal only in this class.
 */
final class ContextEngine
{
    /**
     * Returns a standardized context payload array for the current request.
     *
     * This is the single canonical public output method for external consumers.
     * Output shape is fixed — no optional keys, no dynamic structure drift.
     *
     * @return array{
     *     context_id: string,
     *     environment_id: string,
     *     network_id: string,
     *     site_id: string,
     *     device_id: string,
     *     session_id: string,
     *     identity_id: string|null,
     *     authority_id: string|null,
     *     trust_level: string,
     *     issued_at: int,
     *     expires: int
     * }
     */
    public static function getContext(): array
    {
        $ctx = self::current();
        return [
            'context_id'     => $ctx->context_id,
            'environment_id' => $ctx->environment_id,
            'network_id'     => $ctx->network_id,
            'site_id'        => $ctx->site_id,
            'device_id'      => $ctx->device_id,
            'session_id'     => $ctx->session_id,
            'identity_id'    => $ctx->identity_id,
            'authority_id'   => $ctx->authority_id,
            'issued_at'      => $ctx->issued_at,
            'expires'        => $ctx->expires,
        ];
    }

    /**
     * Returns the SirusContext for the current request, building it once and caching.
     *
     * Expired contexts are evicted from the cache and rebuilt transparently so that
     * stale authority / capability data is never served.
     */
    public static function current(): SirusContext
    {
        $cached = ContextCache::get();

        // Evict expired context so downstream code always receives a fresh one.
        if ($cached !== null && $cached->isExpired()) {
            ContextCache::clear();
            $cached = null;
        }

        if ($cached !== null) {
            return $cached;
        }

        $context = self::build();
        ContextCache::set($context);
        return $context;
    }

    /**
     * Builds a SirusContext from an already-resolved DeviceRecord, primes the
     * request-level cache, and returns it.
     *
     * Call this immediately after resolving a device in the REST controller so
     * that every subsequent call to ContextEngine::current() within the same PHP
     * request is guaranteed to reference the same device. Without this explicit
     * binding the cookie-based build() path might resolve a different device_id.
     *
     * @param DeviceRecord $device The resolved and persisted device record.
     * @return SirusContext A fully initialized context seeded with the device.
     *
     * @throws \RuntimeException If the supplied device has an empty device_id.
     */
    public static function buildFromDevice(DeviceRecord $device): SirusContext
    {
        if ($device->device_id === '') {
            throw new \RuntimeException(
                '[Sirus] Hard fail: device context is missing. buildFromDevice() requires a resolved device_id.'
            );
        }

        $context_id     = wp_generate_uuid4();
        $environment_id = hash('sha256', (string) get_bloginfo('url'));
        $network_id     = function_exists('get_current_network_id')
            ? (string) get_current_network_id()
            : '1';
        $site_id    = (string) get_current_blog_id();
        $session_id = (session_status() === PHP_SESSION_ACTIVE && session_id() !== '')
            ? session_id()
            : wp_generate_uuid4();

        $issued_at = time();
        $expires   = $issued_at + 300;

        $context = new SirusContext(
            context_id:     $context_id,
            environment_id: $environment_id,
            network_id:     $network_id,
            site_id:        $site_id,
            device_id:      $device->device_id,
            session_id:     $session_id,
            identity_id:    null,
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    $device->trust_level,
            issued_at:      $issued_at,
            expires:        $expires,
        );

        // Prime the request-level cache so ContextEngine::current() returns the
        // same context instance throughout this request.
        ContextCache::set($context);

        return $context;
    }

    /**
     * Assembles a fresh SirusContext from the current WordPress/request environment.
     */
    public static function build(): SirusContext
    {
        $context_id     = wp_generate_uuid4();
        $environment_id = hash('sha256', (string) get_bloginfo('url'));
        $network_id     = function_exists('get_current_network_id')
            ? (string) get_current_network_id()
            : '1';
        $site_id = (string) get_current_blog_id();

        // Cookie access is intentionally isolated to this class.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_cookie = isset($_COOKIE['spx_device_id']) ? sanitize_text_field(
            wp_unslash((string) $_COOKIE['spx_device_id'])
        ) : '';
        // Validate UUID v4 format to prevent arbitrary strings from reaching the DB.
        $device_id = (is_string($raw_cookie) && wp_is_uuid($raw_cookie, 4))
            ? $raw_cookie
            : wp_generate_uuid4();

        $session_id = (session_status() === PHP_SESSION_ACTIVE && session_id() !== '')
            ? session_id()
            : wp_generate_uuid4();

        $trust_level  = 'anonymous';
        $identity_id  = null;
        $authority_id = null;
        $role_set     = [];
        $capabilities = [];
        $issued_at    = time();
        $expires      = $issued_at + 300;

        return new SirusContext(
            context_id:     $context_id,
            environment_id: $environment_id,
            network_id:     $network_id,
            site_id:        $site_id,
            device_id:      $device_id,
            session_id:     $session_id,
            identity_id:    $identity_id,
            authority_id:   $authority_id,
            role_set:       $role_set,
            capabilities:   $capabilities,
            trust_level:    $trust_level,
            issued_at:      $issued_at,
            expires:        $expires,
        );
    }
}
