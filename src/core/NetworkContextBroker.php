<?php

/**
 * NetworkContextBroker - Issues and verifies signed cross-domain context tokens.
 *
 * The signing secret is passed explicitly to both issueToken() and verifyToken()
 * so that the class is portable across PHP origin, TypeScript edge workers, and
 * sovereign minimal deployments that do not have access to WordPress functions.
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
 *
 * Token format: base64url(json_payload) . '.' . base64url(hmac_signature)
 *
 * The caller is responsible for supplying the signing secret so that:
 * - The implementation has no implicit dependency on wp_salt() or any WP function.
 * - The same logic can run identically on origin (PHP), edge (TypeScript), and
 *   sovereign / air-gapped deployments.
 * - Test vectors remain deterministic: same inputs → identical signatures.
 */
final class NetworkContextBroker
{
    /** Short-lived token TTL in seconds. */
    private const TOKEN_TTL = 30;

    /**
     * Issues a signed base64url-encoded context token.
     *
     * @param SirusContext $context The context to encode into the token.
     * @param string       $secret  HMAC-SHA256 signing secret. Must not be empty.
     *                              In WordPress contexts pass `wp_salt('auth')`.
     * @return string The signed token string.
     */
    public function issueToken(SirusContext $context, string $secret): string
    {
        if (trim($secret) === '') {
            throw new \InvalidArgumentException('[Sirus NetworkContextBroker] Signing secret must not be empty.');
        }

        $now            = time();
        $payload        = $context->toPortablePayload();
        $payload['nbf'] = $now;
        $payload['exp'] = $now + self::TOKEN_TTL;

        $json        = json_encode($payload);
        if ($json === false) {
            throw new \RuntimeException('[Sirus NetworkContextBroker] Failed to encode token payload as JSON.');
        }
        $payload_b64 = $this->base64url_encode($json);
        $signature   = hash_hmac('sha256', $payload_b64, $secret, true);
        $sig_b64     = $this->base64url_encode($signature);

        return $payload_b64 . '.' . $sig_b64;
    }

    /**
     * Verifies a signed token and returns a reconstructed SirusContext, or null on failure.
     *
     * @param string $token  The token string to verify.
     * @param string $secret HMAC-SHA256 signing secret. Must match the secret used in issueToken().
     *                       In WordPress contexts pass `wp_salt('auth')`.
     * @return SirusContext|null The reconstructed context, or null if invalid/expired.
     */
    public function verifyToken(string $token, string $secret): ?SirusContext
    {
        if (trim($secret) === '') {
            return null;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payload_b64, $sig_b64] = $parts;

        $expected_sig = hash_hmac('sha256', $payload_b64, $secret, true);
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
        if (! isset($data['exp']) || (int) $data['exp'] < $now) {
            return null;
        }

        if (isset($data['nbf']) && (int) $data['nbf'] > $now) {
            return null;
        }

        // Fail closed: token is invalid if the primary context identifiers are absent.
        $context_id = (string) ($data['ctx'] ?? '');
        $device_id  = (string) ($data['dev'] ?? '');

        if ($context_id === '' || $device_id === '') {
            return null;
        }

        // Least-privilege fallback: when tl/ts are absent (pre-v2 token), default to
        // 'anonymous' rather than reconstructing a higher-trust context from partial data.
        $trust_level = isset($data['tl']) ? (string) $data['tl'] : 'anonymous';
        $trust_score = max(0.0, min(1.0, isset($data['ts'])
            ? (float) $data['ts']
            : self::trustScoreFromLevel($trust_level)
        ));

        return new SirusContext(
            context_id:     $context_id,
            environment_id: (string) ($data['env'] ?? ''),
            network_id:     (string) ($data['net'] ?? ''),
            site_id:        (string) ($data['site'] ?? ''),
            device_id:      $device_id,
            session_id:     '',
            identity_id:    null,
            authority_id:   isset($data['auth']) ? (string) $data['auth'] : null,
            role_set:       [],
            capabilities:   isset($data['caps']) && is_array($data['caps'])
                                ? array_map(strval(...), $data['caps'])
                                : [],
            trust_level:    $trust_level,
            trust_score:    $trust_score,
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

    /**
     * Derives a logical trust score from a trust_level string.
     *
     * Used as a backward-compatible fallback for tokens that predate the `ts`
     * field. Maps the credential level to a float that aligns with TrustResolver
     * credential base scores.
     *
     * @param string $trust_level Trust level string from the portable payload.
     * @return float Derived trust score in [0.0, 1.0].
     */
    private static function trustScoreFromLevel(string $trust_level): float
    {
        return match (strtolower($trust_level)) {
            'elder'       => 0.95,
            'contributor' => 0.90,
            'user', 'normal' => 0.85,
            'device'      => 0.70,
            'elevated'    => 0.60,
            'critical'    => 0.10,
            default       => 0.50,
        };
    }
}
