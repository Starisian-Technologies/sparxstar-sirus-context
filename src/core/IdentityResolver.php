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
 * Returns the Helios identity context or null when Helios is unavailable.
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
     * Returns null when no Helios client is configured or Helios is unreachable.
     * Sirus itself never derives a trust level or identity from WordPress internals.
     *
     * @param SirusContext $context The context being evaluated.
     * @return array<string, mixed>|null Helios identity payload, or null.
     */
    public function resolve(SirusContext $context): ?array
    {
        if (! $this->helios_client instanceof HeliosClientInterface) {
            return null;
        }

        return $this->helios_client->getIdentityContext(
            $context->device_id,
            $context->session_id,
            $context->identity_id
        );
    }
}
