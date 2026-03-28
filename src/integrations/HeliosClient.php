<?php

/**
 * HeliosClient - Integration client for the Helios trust resolution service.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\integrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Communicates with the Helios trust-resolution REST endpoint.
 * Caches successful responses in the WordPress object cache to reduce
 * repeated remote calls within the same request lifecycle.
 */
final readonly class HeliosClient
{
    /** WordPress object cache group. */
    private const CACHE_GROUP = 'sparxstar_sirus';

    /** Seconds to cache a successful Helios response. */
    private const CACHE_TTL = 120;

    /** Helios REST endpoint path. */
    private const ENDPOINT = '/wp-json/helios/v1/trust/resolve';

    /**
     * @param string $base_url Base URL of the Helios service (including scheme, no trailing slash).
     */
    public function __construct(private string $base_url = '')
    {
    }

    /**
     * Resolves trust information for the given device and session via Helios.
     *
     * Returns an array with keys: identity_id, trust_level, verification_status,
     * authority_memberships, capabilities – or null on failure.
     *
     * @param string $device_id Device UUID.
     * @param string $session_id Session identifier.
     * @param string|null $identity_claim Optional identity claim to pass to Helios.
     * @return array<string, mixed>|null
     */
    public function resolve(
        string $device_id,
        string $session_id,
        ?string $identity_claim = null
    ): ?array {
        $cache_key = sprintf('helios_trust:%s:%s', $device_id, $session_id);
        $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        // If no explicit base_url is configured, Helios is not available.
        if ($this->base_url === '') {
            return null;
        }

        $base_url = $this->base_url;

        $url = rtrim($base_url, '/') . self::ENDPOINT;

        $body = wp_json_encode(
            [
                'device_id'      => $device_id,
                'session_id'     => $session_id,
                'identity_claim' => $identity_claim,
            ]
        );

        if ($body === false) {
            return null;
        }

        $response = wp_remote_post(
            $url,
            [
                'headers'     => [ 'Content-Type' => 'application/json' ],
                'body'        => $body,
                'timeout'     => 5,
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ((int) $status !== 200) {
            return null;
        }

        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return null;
        }

        // Normalise the expected keys.
        $result = [
            'identity_id'           => isset($data['identity_id']) ? (string) $data['identity_id'] : null,
            'trust_level'           => isset($data['trust_level']) ? (string) $data['trust_level'] : 'anonymous',
            'verification_status'   => isset($data['verification_status']) ? (string) $data['verification_status'] : 'unverified',
            'authority_memberships' => isset($data['authority_memberships']) && is_array($data['authority_memberships'])
                ? $data['authority_memberships']
                : [],
            'capabilities' => isset($data['capabilities']) && is_array($data['capabilities'])
                ? $data['capabilities']
                : [],
        ];

        wp_cache_set($cache_key, $result, self::CACHE_GROUP, self::CACHE_TTL);

        return $result;
    }
}
