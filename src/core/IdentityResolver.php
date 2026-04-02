<?php

/**
 * IdentityResolver - Resolves identity context for the current SirusContext.
 *
 * Per spec §B: Sirus does NOT derive identity independently. All identity
 * resolution is delegated exclusively to Helios via HeliosClientInterface.
 * WordPress user functions (get_current_user_id, user_can, etc.) MUST NOT be
 * called here.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\integrations\HeliosClientInterface;

/**
 * Delegates identity resolution to Helios Trust.
 * Always returns a fixed-schema identity array; falls back to a null-safe
 * structure when Helios is unavailable so callers never receive null.
 */
final readonly class IdentityResolver
{
    /**
     * @param HeliosClientInterface|null $helios_client Helios integration (required for resolution).
     */
    public function __construct(private ?HeliosClientInterface $helios_client = null)
    {
    }

    /**
     * Resolves and returns the identity context from Helios for the given device/session.
     *
     * When Helios is unavailable or returns no data, a fixed null-safe fallback shape
     * is returned so callers always receive a consistent structure.
     * Sirus itself never derives a trust level or identity from WordPress internals.
     *
     * @param SirusContext $context The context being evaluated.
     * @return array{identity_id: string|null, verification_status: string, authority_memberships: array<int, string>, capabilities: array<int, string>}
     */
    public function resolve(SirusContext $context): array
    {
        /** @var array{identity_id: string|null, verification_status: string, authority_memberships: array<int, string>, capabilities: array<int, string>} $fallback */
        $fallback = [
            'identity_id'           => null,
            'verification_status'   => 'none',
            'authority_memberships' => [],
            'capabilities'          => [],
        ];

        if (! $this->helios_client instanceof HeliosClientInterface) {
            return $fallback;
        }

        $result = $this->helios_client->getIdentityContext(
            $context->device_id,
            $context->session_id,
            $context->identity_id
        );

        return is_array($result) ? $result : $fallback;
    }
}
