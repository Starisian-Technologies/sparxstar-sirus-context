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
     * Returns the SirusContext for the current request, building it once and caching.
     */
    public static function current(): SirusContext
    {
        $cached = ContextCache::get();
        if ($cached !== null) {
            return $cached;
        }

        $context = self::build();
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
        $device_id = ($raw_cookie !== '') ? $raw_cookie : wp_generate_uuid4();

        $session_id = (session_status() === PHP_SESSION_ACTIVE && session_id() !== '')
            ? session_id()
            : wp_generate_uuid4();

        $trust_level    = 'anonymous';
        $identity_id    = null;
        $authority_id   = null;
        $role_set       = [];
        $capabilities   = [];
        $issued_at      = time();
        $expires        = $issued_at + 300;

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
