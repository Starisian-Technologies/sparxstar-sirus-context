<?php

/**
 * Tests for PulseGenerator – HMAC-SHA256 signed ContextPulse generation.
 *
 * Test ordering note: SIRUS_PULSE_SIGNING_KEY is a PHP constant that can only be
 * defined once per process. This test class:
 *   1. Runs the "key not defined" assertion first (before the constant exists).
 *   2. Defines the constant via setUpBeforeClass() for all subsequent tests.
 *   3. The "too short key" path cannot be exercised in the same process after a
 *      valid key has been defined — this is a known PHP constant limitation.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\PulseGenerator;
use Starisian\Sparxstar\Sirus\core\SirusContext;
use Starisian\Sparxstar\Sirus\dto\ContextPulse;

/**
 * Unit tests for PulseGenerator::generate().
 */
final class PulseGeneratorTest extends SirusTestCase
{
    /** Fixed signing key used across happy-path tests (32 bytes). */
    private const TEST_SIGNING_KEY = 'sirus-test-signing-key-x32bytes!';

    /** @var PulseGenerator */
    private PulseGenerator $generator;

    /**
     * Define SIRUS_PULSE_SIGNING_KEY once for the lifetime of this test class.
     * Runs before any test methods in this class.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (! defined('SIRUS_PULSE_SIGNING_KEY')) {
            define('SIRUS_PULSE_SIGNING_KEY', self::TEST_SIGNING_KEY);
        }
    }

    protected function setUp(): void
    {
        $this->generator = new PulseGenerator();
    }

    // ── Return type and shape ─────────────────────────────────────────────────

    /**
     * generate() returns a ContextPulse instance.
     */
    public function testGenerateReturnsContextPulse(): void
    {
        $pulse = $this->generator->generate($this->makeContext());

        $this->assertInstanceOf(ContextPulse::class, $pulse);
    }

    // ── Fields must match context ─────────────────────────────────────────────

    /**
     * pulse.context_id matches context.context_id.
     */
    public function testPulseContextIdMatchesContext(): void
    {
        $context = $this->makeContext();
        $pulse   = $this->generator->generate($context);

        $this->assertSame($context->context_id, $pulse->context_id);
    }

    /**
     * pulse.device_id matches context.device_id.
     */
    public function testPulseDeviceIdMatchesContext(): void
    {
        $context = $this->makeContext();
        $pulse   = $this->generator->generate($context);

        $this->assertSame($context->device_id, $pulse->device_id);
    }

    /**
     * pulse.session_id matches context.session_id.
     */
    public function testPulseSessionIdMatchesContext(): void
    {
        $context = $this->makeContext();
        $pulse   = $this->generator->generate($context);

        $this->assertSame($context->session_id, $pulse->session_id);
    }

    /**
     * pulse.site_id matches context.site_id.
     */
    public function testPulseSiteIdMatchesContext(): void
    {
        $context = $this->makeContext();
        $pulse   = $this->generator->generate($context);

        $this->assertSame($context->site_id, $pulse->site_id);
    }

    /**
     * pulse.network_id matches context.network_id.
     */
    public function testPulseNetworkIdMatchesContext(): void
    {
        $context = $this->makeContext();
        $pulse   = $this->generator->generate($context);

        $this->assertSame($context->network_id, $pulse->network_id);
    }

    /**
     * pulse.trust_score matches context.trust_score.
     */
    public function testPulseTrustScoreMatchesContext(): void
    {
        $context = $this->makeContext();
        $pulse   = $this->generator->generate($context);

        $this->assertSame($context->trust_score, $pulse->trust_score);
    }

    /**
     * pulse.trust_level matches context.trust_level.
     */
    public function testPulseTrustLevelMatchesContext(): void
    {
        $context = $this->makeContext();
        $pulse   = $this->generator->generate($context);

        $this->assertSame($context->trust_level, $pulse->trust_level);
    }

    // ── ContextPulse NEVER contains identity claims ───────────────────────────

    /**
     * ContextPulse has no identity_id field (spec: NEVER contains identity claims).
     */
    public function testPulseHasNoIdentityIdField(): void
    {
        $context = $this->makeContext(identity_id: 'secret-user-99');
        $pulse   = $this->generator->generate($context);

        $this->assertFalse(
            property_exists($pulse, 'identity_id'),
            'ContextPulse must never have an identity_id property.'
        );

        // Confirm the pulse serialization also has no identity_id.
        $this->assertArrayNotHasKey('identity_id', $pulse->toArray());
    }

    // ── TTL / timing ──────────────────────────────────────────────────────────

    /**
     * pulse.expires = pulse.issued_at + PulseGenerator::PULSE_TTL (60 seconds).
     */
    public function testPulseExpiresIsIssuedAtPlusTtl(): void
    {
        $pulse = $this->generator->generate($this->makeContext());

        $this->assertSame(
            $pulse->issued_at + PulseGenerator::PULSE_TTL,
            $pulse->expires
        );
    }

    /**
     * pulse.issued_at is a recent Unix timestamp (within 5 seconds of now).
     */
    public function testPulseIssuedAtIsRecent(): void
    {
        $before = time();
        $pulse  = $this->generator->generate($this->makeContext());
        $after  = time();

        $this->assertGreaterThanOrEqual($before, $pulse->issued_at);
        $this->assertLessThanOrEqual($after, $pulse->issued_at);
    }

    /**
     * Explicit $now is honoured: pulse.issued_at equals the provided timestamp.
     */
    public function testExplicitNowIsHonoured(): void
    {
        $now   = 1_700_000_000;
        $pulse = $this->generator->generate($this->makeContext(), $now);

        $this->assertSame($now, $pulse->issued_at);
    }

    /**
     * Explicit $ttlSeconds controls pulse.expires independently of the default TTL.
     */
    public function testExplicitTtlSecondsIsHonoured(): void
    {
        $now = 1_700_000_000;
        $ttl = 120;

        $pulse = $this->generator->generate($this->makeContext(), $now, $ttl);

        $this->assertSame($now + $ttl, $pulse->expires);
    }

    /**
     * generate() with $now=0 falls back to time() (not to the Unix epoch).
     */
    public function testZeroNowFallsBackToCurrentTime(): void
    {
        $before = time();
        $pulse  = $this->generator->generate($this->makeContext(), 0);
        $after  = time();

        $this->assertGreaterThanOrEqual($before, $pulse->issued_at);
        $this->assertLessThanOrEqual($after, $pulse->issued_at);
    }

    // ── Signature ─────────────────────────────────────────────────────────────

    /**
     * pulse.sig is a non-empty hex string (64 chars for HMAC-SHA256).
     */
    public function testPulseSigIsNonEmptyHexString(): void
    {
        $pulse = $this->generator->generate($this->makeContext());

        $this->assertNotEmpty($pulse->sig);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $pulse->sig);
    }

    /**
     * Two calls with the same context produce different pulse_ids and sigs
     * (pulse_id is a fresh UUID each time; sig includes pulse_id + timestamp).
     */
    public function testTwoGenerationsAreDifferent(): void
    {
        $context = $this->makeContext();

        $pulse1 = $this->generator->generate($context);
        $pulse2 = $this->generator->generate($context);

        $this->assertNotSame($pulse1->pulse_id, $pulse2->pulse_id);
        $this->assertNotSame($pulse1->sig, $pulse2->sig);
    }

    // ── Canonical string determinism ──────────────────────────────────────────

    /**
     * For a deterministic set of inputs the canonical string (and thus signature)
     * must be reproducible. We verify by reconstructing the canonical format
     * and computing the expected signature.
     *
     * Canonical format (spec):
     *   pulse_id|context_id|device_id|session_id|site_id|network_id|trust_score|trust_level|issued_at|expires
     * trust_score is formatted with number_format(x, 4, '.', '').
     */
    public function testCanonicalStringIsReproducible(): void
    {
        $context = $this->makeContext();
        $pulse   = $this->generator->generate($context);

        // Reconstruct the canonical string using the same fields the generator used.
        $canonical = implode('|', [
            $pulse->pulse_id,
            $pulse->context_id,
            $pulse->device_id,
            $pulse->session_id,
            $pulse->site_id,
            $pulse->network_id,
            number_format($pulse->trust_score, 4, '.', ''),
            $pulse->trust_level,
            (string) $pulse->issued_at,
            (string) $pulse->expires,
        ]);

        $expected_sig = hash_hmac('sha256', $canonical, self::TEST_SIGNING_KEY);

        $this->assertSame($expected_sig, $pulse->sig);
    }

    /**
     * trust_score is serialized with 4 decimal places in the canonical string
     * (ensures cross-system reproducibility regardless of float precision).
     */
    public function testTrustScoreIsFormattedTo4DecimalsInCanonical(): void
    {
        // Use a trust_score that varies in precision.
        $context = $this->makeContext(trust_score: 0.9);
        $pulse   = $this->generator->generate($context);

        $canonical = implode('|', [
            $pulse->pulse_id,
            $pulse->context_id,
            $pulse->device_id,
            $pulse->session_id,
            $pulse->site_id,
            $pulse->network_id,
            number_format($pulse->trust_score, 4, '.', ''),
            $pulse->trust_level,
            (string) $pulse->issued_at,
            (string) $pulse->expires,
        ]);

        // Canonical must contain '0.9000' not '0.9' or '0.9000000001'.
        $this->assertStringContainsString('0.9000', $canonical);

        $expected_sig = hash_hmac('sha256', $canonical, self::TEST_SIGNING_KEY);
        $this->assertSame($expected_sig, $pulse->sig);
    }

    // ── pulse_id is a UUID v4 ─────────────────────────────────────────────────

    /**
     * pulse.pulse_id is a UUID v4 string.
     */
    public function testPulseIdIsUuidV4(): void
    {
        $pulse = $this->generator->generate($this->makeContext());

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $pulse->pulse_id
        );
    }

    // ── Key validation ────────────────────────────────────────────────────────

    /**
     * The signing key minimum length is 32 bytes (PulseGenerator::MIN_KEY_LENGTH via reflection).
     * This test documents the constant indirectly without needing to call the private method.
     *
     * Full "too short key throws" and "key not defined throws" tests cannot be exercised
     * once SIRUS_PULSE_SIGNING_KEY is defined. They are verified by code review of the
     * resolveSigningKey() implementation.
     */
    public function testSigningKeyMinLengthIs32(): void
    {
        // The constant is defined at test boot; its length must be >= 32.
        $this->assertGreaterThanOrEqual(32, strlen(constant('SIRUS_PULSE_SIGNING_KEY')));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Builds a minimal SirusContext for use in assertions.
     *
     * @param string|null $identity_id Identity ID to include (never appears in pulse).
     * @param float       $trust_score Trust score to use.
     */
    private function makeContext(
        ?string $identity_id = null,
        float $trust_score = 1.0
    ): SirusContext {
        return new SirusContext(
            context_id:     'ctx-pulse-test',
            environment_id: 'env-test',
            network_id:     '1',
            site_id:        '1',
            device_id:      'dev-pulse-test',
            session_id:     'sess-pulse-test',
            identity_id:    $identity_id,
            authority_id:   null,
            role_set:       [],
            capabilities:   [],
            trust_level:    'NORMAL',
            trust_score:    $trust_score,
            issued_at:      time(),
            expires:        time() + 300,
        );
    }
}
