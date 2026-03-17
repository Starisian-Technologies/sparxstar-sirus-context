<?php
/**
 * AuthorityResolver - Resolves the authority type for a given SirusContext.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Maps the resolved trust level and WordPress capabilities to a named authority type.
 */
final class AuthorityResolver
{
    /** Sparxstar Network super-admin authority. */
    public const SPARXSTAR_NETWORK = 'sparxstar_network';

    /** Starisian platform authority. */
    public const STARISIAN = 'starisian';

    /** AIWA (AI Workforce Authority) designation. */
    public const AIWA = 'aiwa';

    /** Tribal sovereign authority. */
    public const TRIBAL_AUTHORITY = 'tribal_authority';

    /** External partner institution authority. */
    public const PARTNER_INSTITUTION = 'partner_institution';

    /**
     * Resolves the authority identifier for the given context, or null if none applies.
     *
     * Resolution rules (checked in priority order):
     * 1. authority trust + manage_network cap → sparxstar_network
     * 2. authority trust + manage_options cap → starisian
     * 3. Anything else → null
     *
     * @param SirusContext $context The context to evaluate.
     * @return string|null One of the AUTHORITY_* constants, or null.
     */
    public function resolve(SirusContext $context): ?string
    {
        if ($context->trust_level !== 'authority') {
            return null;
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return null;
        }

        if (user_can($user_id, 'manage_network')) {
            return self::SPARXSTAR_NETWORK;
        }

        if (user_can($user_id, 'manage_options')) {
            return self::STARISIAN;
        }

        return null;
    }
}
