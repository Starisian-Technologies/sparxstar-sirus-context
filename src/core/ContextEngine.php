<?php

/**
 * ContextEngine - Builds and caches the SirusContext for the current request.
 *
 * Hard rules:
 *   - current() returns a valid SirusContext or throws ContextBootException.
 *   - current() NEVER returns null and NEVER returns a partial context.
 *   - ContextBootException MUST NEVER be caught and swallowed by callers.
 *   - When PHP_SAPI === 'cli', a fixed CLI system context is returned.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\exceptions\ContextBootException;
use Starisian\Sparxstar\Sirus\core\TrustEngine;

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
     * CLI requests receive a fixed system context per spec:
     *   identity_id  = "SYSTEM"
     *   trust_score  = 1.0
     *   trust_level  = "NORMAL"
     *   authority_id = "GLOBAL"
     *   device_id    = "CLI"
     *
     * Expired contexts are evicted from the cache and rebuilt transparently so that
     * stale authority / capability data is never served.
     *
     * @return SirusContext A valid, fully-resolved context.
     * @throws ContextBootException If context cannot be established. MUST NOT be swallowed.
     */
    public static function current(): SirusContext
    {
        // CLI system context — always trust_score 1.0 with SYSTEM identity.
        if (PHP_SAPI === 'cli') {
            return self::buildCliContext();
        }

        try {
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
        } catch (ContextBootException $e) {
            // Re-throw: ContextBootException MUST NEVER be swallowed.
            throw $e;
        } catch (\Throwable $e) {
            throw new ContextBootException(
                '[Sirus] ContextBootException: context could not be established.',
                0,
                $e
            );
        }
    }

    /**
     * Builds the fixed CLI system context per spec.
     *
     * CLI context is never cached — it is always freshly constructed because CLI
     * requests are typically short-lived and do not share request-level state.
     *
     * @return SirusContext
     */
    private static function buildCliContext(): SirusContext
    {
        $issued_at = time();

        return new SirusContext(
            context_id:     'CLI-' . $issued_at,
            environment_id: 'cli',
            network_id:     '1',
            site_id:        '1',
            device_id:      'CLI',
            session_id:     'CLI',
            identity_id:    'SYSTEM',
            authority_id:   'GLOBAL',
            role_set:       [],
            capabilities:   [],
            trust_level:    'NORMAL',
            trust_score:    1.0,
            issued_at:      $issued_at,
            expires:        0,
        );
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

        $trust_score = TrustResolver::evaluate($device);

        // If DeviceContinuity flagged this record with STEP_UP_REQUIRED (fingerprint
        // changed on a verified device), propagate that flag directly to the context
        // rather than deriving a level from the numeric score alone.
        $trust_level = ($device->trust_level === StepUpPolicy::TRUST_LEVEL_STEP_UP_REQUIRED)
            ? StepUpPolicy::TRUST_LEVEL_STEP_UP_REQUIRED
            : (new TrustEngine())->scoreToLevel($trust_score);

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
            trust_level:    $trust_level,
            trust_score:    $trust_score,
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

        $trust_result = (new TrustEngine())->compute([]);
        $trust_level  = $trust_result['trust_level'];
        $trust_score  = $trust_result['trust_score'];
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
            trust_score:    $trust_score,
            issued_at:      $issued_at,
            expires:        $expires,
        );
    }
}
