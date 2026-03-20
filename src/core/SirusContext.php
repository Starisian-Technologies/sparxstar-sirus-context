<?php
/**
 * SirusContext - The main Context Data Transfer Object.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Immutable value object representing the full context for a single request.
 */
final class SirusContext
{
    public const CONTEXT_VERSION = 1;

    /**
     * Constructs a new SirusContext.
     *
     * @param string      $context_id     Unique identifier for this context instance.
     * @param string      $environment_id Hashed identifier for the site environment.
     * @param string      $network_id     Multisite network identifier.
     * @param string      $site_id        Blog/site identifier.
     * @param string      $device_id      Device continuity identifier.
     * @param string      $session_id     Session identifier.
     * @param string|null $identity_id    Authenticated identity, or null.
     * @param string|null $authority_id   Resolved authority type, or null.
     * @param array       $role_set       WordPress roles associated with the context.
     * @param array       $capabilities   Resolved capability strings.
     * @param string      $trust_level    One of: anonymous, device, contributor, user, authority.
     * @param int         $issued_at      Unix timestamp when the context was issued.
     * @param int         $expires        Unix timestamp when the context expires.
     */
    public function __construct(
        public readonly string  $context_id,
        public readonly string  $environment_id,
        public readonly string  $network_id,
        public readonly string  $site_id,
        public readonly string  $device_id,
        public readonly string  $session_id,
        public readonly ?string $identity_id,
        public readonly ?string $authority_id,
        public readonly array   $role_set,
        public readonly array   $capabilities,
        public readonly string  $trust_level,
        public readonly int     $issued_at,
        public readonly int     $expires,
    ) {}

    /**
     * Returns true if the context has passed its expiry timestamp.
     * An expires value of 0 is treated as "never expires" (backward-compatible).
     */
    public function isExpired(): bool
    {
        return $this->expires > 0 && time() >= $this->expires;
    }

    /**
     * Returns true if the given capability string is in the resolved capabilities set.
     *
     * @param string $capability The capability string to check.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * Returns true if the resolved authority_id matches the given authority string.
     *
     * @param string $authority The authority identifier to compare against.
     */
    public function hasAuthority(string $authority): bool
    {
        return $this->authority_id === $authority;
    }

    /**
     * Returns a portable payload array, deliberately excluding identity_id.
     * Safe to transmit cross-domain.
     *
     * @return array<string, mixed>
     */
    public function toPortablePayload(): array
    {
        return [
            'ctxv' => self::CONTEXT_VERSION,
            'ctx'  => $this->context_id,
            'env'  => $this->environment_id,
            'net'  => $this->network_id,
            'site' => $this->site_id,
            'dev'  => $this->device_id,
            'auth' => $this->authority_id,
            'caps' => $this->capabilities,
            'iat'  => $this->issued_at,
            'exp'  => $this->expires,
        ];
    }
}
