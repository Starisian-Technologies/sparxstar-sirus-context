<?php
/**
 * IdentityResolver - Resolves a trust level for the current SirusContext.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\integrations\HeliosClient;

/**
 * Determines the trust level for a resolved SirusContext by inspecting
 * device presence, WordPress authentication, capabilities, and optionally
 * an external Helios trust resolution service.
 *
 * Trust levels (ascending): anonymous → device → contributor → user → authority
 */
final class IdentityResolver
{
    /** @var array<string, int> Numeric weight for each trust level (for comparison). */
    private const TRUST_WEIGHTS = [
        'anonymous'   => 0,
        'device'      => 1,
        'contributor' => 2,
        'user'        => 3,
        'authority'   => 4,
    ];

    /**
     * @param HeliosClient|null $helios_client Optional Helios integration for external trust resolution.
     */
    public function __construct(private readonly ?HeliosClient $helios_client = null) {}

    /**
     * Resolves and returns the highest applicable trust level string for the context.
     *
     * @param SirusContext $context The context being evaluated.
     * @return string One of: anonymous, device, contributor, user, authority.
     */
    public function resolve(SirusContext $context): string
    {
        $trust_level = 'anonymous';

        if ($context->device_id !== '') {
            $trust_level = $this->escalate($trust_level, 'device');
        }

        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $trust_level = $this->escalate($trust_level, 'user');

            if (user_can($user_id, 'manage_options')) {
                $trust_level = $this->escalate($trust_level, 'authority');
            }
        }

        if ($this->helios_client !== null) {
            $helios = $this->helios_client->resolve(
                $context->device_id,
                $context->session_id,
                $context->identity_id
            );
            if (is_array($helios) && isset($helios['trust_level']) && is_string($helios['trust_level'])) {
                $trust_level = $this->escalate($trust_level, $helios['trust_level']);
            }
        }

        return $trust_level;
    }

    /**
     * Returns the higher trust level of the two provided values.
     *
     * @param string $current  The currently resolved trust level.
     * @param string $candidate The candidate trust level to compare.
     */
    private function escalate(string $current, string $candidate): string
    {
        $current_weight   = self::TRUST_WEIGHTS[$current]   ?? 0;
        $candidate_weight = self::TRUST_WEIGHTS[$candidate] ?? 0;

        return $candidate_weight > $current_weight ? $candidate : $current;
    }
}
