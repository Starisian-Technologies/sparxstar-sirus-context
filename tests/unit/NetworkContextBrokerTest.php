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
 * Validates token issuance and verification in NetworkContextBroker.
 */
final class NetworkContextBrokerTest extends TestCase
{
    /** Fixed test secret used across all happy-path tests (portable, no wp_salt dependency). */
    private const TEST_SECRET = 'sirus-ncb-test-secret-32bytes!!!';

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
     * issueToken() returns a non-empty string.
     */
    public function testIssueTokenReturnsNonEmptyString(): void
    {
        $token = $this->broker->issueToken($this->context, self::TEST_SECRET);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    /**
     * Token contains exactly one dot separator (payload.signature).
     */
    public function testIssuedTokenHasOneDotSeparator(): void
    {
        $token = $this->broker->issueToken($this->context, self::TEST_SECRET);

        $this->assertSame(1, substr_count($token, '.'));
    }

    /**
     * verifyToken() returns a SirusContext for a freshly issued (valid) token.
     */
    public function testVerifyTokenReturnsContextForValidToken(): void
    {
        $token  = $this->broker->issueToken($this->context, self::TEST_SECRET);
        $result = $this->broker->verifyToken($token, self::TEST_SECRET);

        $this->assertInstanceOf(SirusContext::class, $result);
    }

    /**
     * The verified context preserves key portable payload fields.
     */
    public function testVerifyTokenPreservesPayloadFields(): void
    {
        $token  = $this->broker->issueToken($this->context, self::TEST_SECRET);
        $result = $this->broker->verifyToken($token, self::TEST_SECRET);

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

        $token  = $this->broker->issueToken($ctx_with_identity, self::TEST_SECRET);
        $result = $this->broker->verifyToken($token, self::TEST_SECRET);

        $this->assertNotNull($result);
        $this->assertNull($result->identity_id);
    }

    /**
     * verifyToken() returns null for a tampered token (signature mismatch).
     */
    public function testVerifyTokenReturnsNullForTamperedSignature(): void
    {
        $token  = $this->broker->issueToken($this->context, self::TEST_SECRET);
        $parts  = explode('.', $token, 2);
        // Append a character to corrupt the signature.
        $tampered = $parts[0] . '.' . $parts[1] . 'X';

        $result = $this->broker->verifyToken($tampered, self::TEST_SECRET);

        $this->assertNull($result);
    }

    /**
     * verifyToken() returns null for a tampered payload.
     */
    public function testVerifyTokenReturnsNullForTamperedPayload(): void
    {
        $token  = $this->broker->issueToken($this->context, self::TEST_SECRET);
        $parts  = explode('.', $token, 2);
        // Append a character to corrupt the payload.
        $tampered = $parts[0] . 'X.' . $parts[1];

        $result = $this->broker->verifyToken($tampered, self::TEST_SECRET);

        $this->assertNull($result);
    }

    /**
     * verifyToken() returns null for a completely invalid string.
     */
    public function testVerifyTokenReturnsNullForGarbage(): void
    {
        $this->assertNull($this->broker->verifyToken('', self::TEST_SECRET));
        $this->assertNull($this->broker->verifyToken('notavalidtoken', self::TEST_SECRET));
        $this->assertNull($this->broker->verifyToken('aaa.bbb.ccc', self::TEST_SECRET));
    }

    /**
     * verifyToken() returns null when a different secret is used for verification.
     * This ensures tokens are secret-scoped: a token issued with secret A cannot
     * be verified with secret B.
     */
    public function testVerifyTokenReturnsNullForWrongSecret(): void
    {
        $token = $this->broker->issueToken($this->context, self::TEST_SECRET);

        $result = $this->broker->verifyToken($token, 'a-completely-different-secret!!');

        $this->assertNull($result);
    }

    /**
     * verifyToken() returns null when the token's exp has passed.
     *
     * We manually construct a token with a past exp to verify the rejection path,
     * since issueToken() always issues a fresh 30-second token.
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
        $signature   = hash_hmac('sha256', $payload_b64, self::TEST_SECRET, true);
        $sig_b64     = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $token  = $payload_b64 . '.' . $sig_b64;
        $result = $this->broker->verifyToken($token, self::TEST_SECRET);

        $this->assertNull($result, 'An expired token must not be accepted.');
    }

    /**
     * issueToken() throws InvalidArgumentException for an empty secret.
     */
    public function testIssueTokenThrowsForEmptySecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->broker->issueToken($this->context, '');
    }

    /**
     * issueToken() throws InvalidArgumentException for a whitespace-only secret.
     */
    public function testIssueTokenThrowsForWhitespaceOnlySecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->broker->issueToken($this->context, '   ');
    }

    /**
     * verifyToken() returns null when the secret is empty (fail-closed).
     */
    public function testVerifyTokenReturnsNullForEmptySecret(): void
    {
        $token = $this->broker->issueToken($this->context, self::TEST_SECRET);
        $this->assertNull($this->broker->verifyToken($token, ''));
    }

    /**
     * verifyToken() returns null when the secret is whitespace only (fail-closed).
     */
    public function testVerifyTokenReturnsNullForWhitespaceOnlySecret(): void
    {
        $token = $this->broker->issueToken($this->context, self::TEST_SECRET);
        $this->assertNull($this->broker->verifyToken($token, '   '));
    }
}
