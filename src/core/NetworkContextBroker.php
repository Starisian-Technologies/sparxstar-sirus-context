<?php
/**
 * NetworkContextBroker - Generates and verifies signed cross-domain context tokens.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Creates and verifies short-lived signed tokens that carry a portable SirusContext
 * payload across network boundaries without exposing the identity_id.
 */
final class NetworkContextBroker
{
    /** Short-lived token TTL in seconds. */
    private const TOKEN_TTL = 30;

    /**
     * Generates a signed base64url-encoded context token.
     *
     * Format: base64url(json_payload) . '.' . base64url(hmac_signature)
     *
     * @param SirusContext $context The context to encode into the token.
     * @return string The signed token string.
     */
    public function generateToken(SirusContext $context): string
    {
        $now     = time();
        $payload = $context->toPortablePayload();
        $payload['nbf'] = $now;
        $payload['exp'] = $now + self::TOKEN_TTL;

        $json        = (string) wp_json_encode($payload);
        $payload_b64 = $this->base64url_encode($json);
        $signature   = hash_hmac('sha256', $payload_b64, wp_salt('auth'), true);
        $sig_b64     = $this->base64url_encode($signature);

        return $payload_b64 . '.' . $sig_b64;
    }

    /**
     * Verifies a signed token and returns a reconstructed SirusContext, or null on failure.
     *
     * @param string $token The token string to verify.
     * @return SirusContext|null The reconstructed context, or null if invalid/expired.
     */
    public function verifyToken(string $token): ?SirusContext
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload_b64, $sig_b64] = $parts;

        $expected_sig = hash_hmac('sha256', $payload_b64, wp_salt('auth'), true);
        $provided_sig = $this->base64url_decode($sig_b64);

        // Guard against empty decoded signature before constant-time comparison.
        if ($provided_sig === '' || ! hash_equals($expected_sig, $provided_sig)) {
            return null;
        }

        $json = $this->base64url_decode($payload_b64);
        if ($json === '') {
            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }

        $now = time();
        if (! isset($data['exp']) || (int) $data['exp'] <= $now) {
            return null;
        }

        if (isset($data['nbf']) && (int) $data['nbf'] > $now) {
            return null;
        }

        return new SirusContext(
            context_id:     (string) ($data['ctx']  ?? ''),
            environment_id: (string) ($data['env']  ?? ''),
            network_id:     (string) ($data['net']  ?? ''),
            site_id:        (string) ($data['site'] ?? ''),
            device_id:      (string) ($data['dev']  ?? ''),
            session_id:     '',
            identity_id:    null,
            authority_id:   isset($data['auth']) ? (string) $data['auth'] : null,
            role_set:       [],
            capabilities:   isset($data['caps']) && is_array($data['caps'])
                                ? array_map('strval', $data['caps'])
                                : [],
            trust_level:    'anonymous',
            issued_at:      (int) ($data['iat'] ?? 0),
            expires:        (int) ($data['exp'] ?? 0),
        );
    }

    /**
     * Encodes a binary string using base64url (RFC 4648 §5).
     *
     * @param string $data Raw bytes or plain string to encode.
     */
    private function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodes a base64url-encoded string back to raw bytes.
     *
     * @param string $data Base64url-encoded string.
     */
    private function base64url_decode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return ($decoded === false) ? '' : $decoded;
    }
}
