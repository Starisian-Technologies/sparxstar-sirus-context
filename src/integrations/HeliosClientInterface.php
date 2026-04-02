<?php

/**
 * HeliosClientInterface - Contract for Helios trust-resolution integration.
 *
 * All Sirus access to identity resolution, device binding validation, and trust
 * data MUST go through this interface. No direct imports of concrete Helios classes
 * are permitted outside of the DI wiring layer.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\integrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Defines the contract for communicating with the Sparxstar Helios Trust service.
 */
interface HeliosClientInterface
{
    /**
     * Resolves trust information for the given device and session via Helios.
     *
     * Returns an array with keys: identity_id, trust_level, verification_status,
     * authority_memberships, capabilities – or null on failure / unavailability.
     *
     * @param string $device_id   Device UUID.
     * @param string $session_id  Session identifier.
     * @param string|null $identity_claim Optional identity claim to pass to Helios.
     * @return array<string, mixed>|null
     */
    public function resolve(
        string $device_id,
        string $session_id,
        ?string $identity_claim = null
    ): ?array;

    /**
     * Returns the identity context for the given device and session from Helios.
     *
     * The identity context includes the resolved identity_id, verification status,
     * and any authority memberships Helios has determined for this device/session pair.
     * Sirus MUST consume identity exclusively via this method; it must not derive
     * identity independently using WordPress user functions.
     *
     * Returns null if Helios is unavailable or the identity cannot be resolved.
     *
     * @param string $device_id   Device UUID.
     * @param string $session_id  Session identifier.
     * @param string|null $identity_claim Optional identity hint passed from the client.
     * @return array{
     *     identity_id: string|null,
     *     trust_level: string,
     *     verification_status: string,
     *     authority_memberships: array<int, string>,
     *     capabilities: array<int, string>
     * }|null
     */
    public function getIdentityContext(
        string $device_id,
        string $session_id,
        ?string $identity_claim = null
    ): ?array;
}
