<?php

/**
 * Tests for NetworkContextBroker – signed cross-domain context tokens.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\core\SirusContext;

/**
 * Validates token generation and verification in NetworkContextBroker.
 */
final class NetworkContextBrokerTest extends TestCase
{
    /** @var NetworkContextBroker */
    private NetworkContextBroker $broker;

    /** @var SirusContext */
    private SirusContext $context;

    protected function setUp(): void
    {
        $this->broker  = new NetworkContextBroker();
        $this->context = new SirusContext(
            context_id:     'ctx-test-1',
            environment_id: 'env-abc',
            network_id:     '1',
            site_id:        '1',
            device_id:      'dev-uuid-test',
            session_id:     'sess-test',
            identity_id:    null,
            authority_id:   'sparxstar',
            role_set:       [],
            capabilities:   ['read', 'publish'],
            trust_level:    'user',
            trust_score:    1.0,
            issued_at:      time(),
            expires:        time() + 300,
        );
    }

    /**
     * generateToken() returns a non-empty string.
     */
    public function testGenerateTokenReturnsNonEmptyString(): void
    {
        $token = $this->broker->generateToken($this->context);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    /**
     * Token contains exactly one dot separator (payload.signature).
     */
    public function testGeneratedTokenHasOneDotSeparator(): void
    {
        $token = $this->broker->generateToken($this->context);

        $this->assertSame(1, substr_count($token, '.'));
    }

    /**
     * verifyToken() returns a SirusContext for a freshly generated (valid) token.
     */
    public function testVerifyTokenReturnsContextForValidToken(): void
    {
        $token  = $this->broker->generateToken($this->context);
        $result = $this->broker->verifyToken($token);

        $this->assertInstanceOf(SirusContext::class, $result);
    }

    /**
     * The verified context preserves key portable payload fields.
     */
    public function testVerifyTokenPreservesPayloadFields(): void
    {
        $token  = $this->broker->generateToken($this->context);
        $result = $this->broker->verifyToken($token);

        $this->assertNotNull($result);
        $this->assertSame('ctx-test-1', $result->context_id);
        $this->assertSame('dev-uuid-test', $result->device_id);
        $this->assertSame('sparxstar', $result->authority_id);
        $this->assertSame(['read', 'publish'], $result->capabilities);
    }

    /**
     * identity_id is never included in the token payload (privacy rule).
     */
    public function testVerifyTokenDoesNotRestoreIdentityId(): void
    {
        $ctx_with_identity = new SirusContext(
            context_id:     'ctx-identity-test',
            environment_id: 'env',
            network_id:     '1',
            site_id:        '1',
            device_id:      'dev',
            session_id:     'sess',
            identity_id:    'secret-user-42', // must not survive round-trip
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    'user',
            trust_score:    1.0,
            issued_at:      time(),
            expires:        time() + 300,
        );

        $token  = $this->broker->generateToken($ctx_with_identity);
        $result = $this->broker->verifyToken($token);

        $this->assertNotNull($result);
        $this->assertNull($result->identity_id);
    }

    /**
     * verifyToken() returns null for a tampered token (signature mismatch).
     */
    public function testVerifyTokenReturnsNullForTamperedSignature(): void
    {
        $token  = $this->broker->generateToken($this->context);
        $parts  = explode('.', $token, 2);
        // Append a character to corrupt the signature.
        $tampered = $parts[0] . '.' . $parts[1] . 'X';

        $result = $this->broker->verifyToken($tampered);

        $this->assertNull($result);
    }

    /**
     * verifyToken() returns null for a tampered payload.
     */
    public function testVerifyTokenReturnsNullForTamperedPayload(): void
    {
        $token  = $this->broker->generateToken($this->context);
        $parts  = explode('.', $token, 2);
        // Append a character to corrupt the payload.
        $tampered = $parts[0] . 'X.' . $parts[1];

        $result = $this->broker->verifyToken($tampered);

        $this->assertNull($result);
    }

    /**
     * verifyToken() returns null for a completely invalid string.
     */
    public function testVerifyTokenReturnsNullForGarbage(): void
    {
        $this->assertNull($this->broker->verifyToken(''));
        $this->assertNull($this->broker->verifyToken('notavalidtoken'));
        $this->assertNull($this->broker->verifyToken('aaa.bbb.ccc'));
    }

    /**
     * verifyToken() returns null when the token's exp has passed.
     *
     * We manually construct a token with a past exp to verify the rejection path,
     * since generateToken() always issues a fresh 30-second token.
     */
    public function testVerifyTokenReturnsNullForExpiredToken(): void
    {
        // Manually build a token payload with exp in the past and a valid signature.
        $payload = [
            'ctxv' => SirusContext::CONTEXT_VERSION,
            'ctx'  => 'ctx-expired',
            'env'  => 'env',
            'net'  => '1',
            'site' => '1',
            'dev'  => 'dev',
            'auth' => null,
            'caps' => [],
            'iat'  => 1000,
            'exp'  => 1001, // well in the past
            'nbf'  => 1000,
        ];

        $json        = (string) json_encode($payload, JSON_THROW_ON_ERROR);
        $payload_b64 = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $signature   = hash_hmac('sha256', $payload_b64, wp_salt('auth'), true);
        $sig_b64     = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $token  = $payload_b64 . '.' . $sig_b64;
        $result = $this->broker->verifyToken($token);

        $this->assertNull($result, 'An expired token must not be accepted.');
    }
}
