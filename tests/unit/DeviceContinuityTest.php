<?php

/**
 * Tests for DeviceContinuity – the drift-tolerance device resolution service.
 *
 * Uses a hand-rolled in-memory DeviceRepository stub so we can test
 * DeviceContinuity logic without a database.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\DeviceRecord;
use Starisian\Sparxstar\Sirus\core\DeviceRepositoryInterface;

/**
 * Validates the Drift Tolerance model in DeviceContinuity.
 *
 * Decision matrix under test:
 *   device_id + correct secret + same fingerprint  → touch last_seen, return existing
 *   device_id + correct secret + drifted fingerprint → update hash, increment drift_score
 *   device_id + wrong secret                        → fall through to fingerprint lookup
 *   fingerprint match (no device_id)                → touch last_seen, return existing
 *   no match anywhere                               → register new device
 */
final class DeviceContinuityTest extends SirusTestCase
{
    private const GOOD_SECRET = 'aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899';

    /** @var array<string, DeviceRecord> keyed by device_id */
    public array $store = [];

    /** @var array<string, DeviceRecord> keyed by fingerprint_hash */
    public array $fpStore = [];

    /** @var array<string, int> drift increments by device_id */
    public array $driftIncrements = [];

    /** @var array<string> device_ids whose last_seen was touched */
    public array $lastSeenTouched = [];

    /** @var array<string, string> new fingerprint_hash per device_id */
    public array $updatedFingerprints = [];

    /** @var DeviceRepositoryInterface */
    private DeviceRepositoryInterface $repo;
    private DeviceContinuity $continuity;

    protected function setUp(): void
    {
        $this->store           = [];
        $this->fpStore         = [];
        $this->driftIncrements = [];
        $this->lastSeenTouched = [];
        $this->updatedFingerprints = [];

        // Build a stub DeviceRepository using anonymous class implementing the interface.
        $self       = $this;
        $this->repo = new class ($self) implements DeviceRepositoryInterface {
            /** @phpstan-ignore-next-line */
            public function __construct(private readonly DeviceContinuityTest $t) {}

            public function findByDeviceId(string $device_id): ?DeviceRecord
            {
                return $this->t->store[$device_id] ?? null;
            }

            public function findByFingerprintHash(string $fingerprint_hash): ?DeviceRecord
            {
                return $this->t->fpStore[$fingerprint_hash] ?? null;
            }

            public function save(DeviceRecord $record): bool
            {
                $this->t->store[$record->device_id]           = $record;
                $this->t->fpStore[$record->fingerprint_hash]  = $record;
                return true;
            }

            public function updateLastSeen(string $device_id): void
            {
                $this->t->lastSeenTouched[] = $device_id;
            }

            public function updateFingerprintHash(string $device_id, string $fingerprint_hash): void
            {
                $this->t->updatedFingerprints[$device_id] = $fingerprint_hash;
            }

            public function incrementDriftScore(string $device_id): void
            {
                $this->t->driftIncrements[$device_id] = ($this->t->driftIncrements[$device_id] ?? 0) + 1;
            }
        };

        $this->continuity = new DeviceContinuity($this->repo);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function seedRecord(
        string $device_id,
        string $fingerprint_hash,
        string $secret = self::GOOD_SECRET,
        int $drift_score = 0,
        int $last_seen_offset = 0,
        string $trust_level = 'device'
    ): DeviceRecord {
        $record = new DeviceRecord(
            device_id:        $device_id,
            device_secret:    $secret,
            fingerprint_hash: $fingerprint_hash,
            environment_json: '{}',
            first_seen:       time() - 3600,
            last_seen:        time() + $last_seen_offset,
            trust_level:      $trust_level,
            drift_score:      $drift_score,
        );
        $this->store[$device_id]          = $record;
        $this->fpStore[$fingerprint_hash] = $record;
        return $record;
    }

    // ── Path 1a: hard-anchor match, no drift ─────────────────────────────────

    /**
     * When device_id + secret match and fingerprint is unchanged, updateLastSeen is called.
     */
    public function testNoDriftTouchesLastSeen(): void
    {
        $this->seedRecord('dev-1', 'fp-original');

        $result = $this->continuity->resolveDevice('dev-1', self::GOOD_SECRET, 'fp-original', []);

        $this->assertSame('dev-1', $result->device_id);
        $this->assertContains('dev-1', $this->lastSeenTouched);
        $this->assertArrayNotHasKey('dev-1', $this->driftIncrements);
    }

    // ── Path 1b: hard-anchor match, fingerprint drift ────────────────────────

    /**
     * When device_id + secret match but fingerprint changed, the new WEAK_MATCH
     * path updates the fingerprint, increments drift_score, and flags trust_level
     * as STEP_UP_REQUIRED (in-memory only — the stored credential level is unchanged).
     */
    public function testDriftOnVerifiedDeviceUpdatesFingerprintAndIncrementsDriftScore(): void
    {
        $this->seedRecord('dev-2', 'fp-old');

        $result = $this->continuity->resolveDevice('dev-2', self::GOOD_SECRET, 'fp-new', []);

        $this->assertSame('dev-2', $result->device_id);
        $this->assertSame('fp-new', $result->fingerprint_hash);
        $this->assertSame(1, $result->drift_score);
        $this->assertSame('fp-new', $this->updatedFingerprints['dev-2']);
        $this->assertSame(1, $this->driftIncrements['dev-2']);
    }

    /**
     * Verified device with changed fingerprint → trust_level is STEP_UP_REQUIRED.
     *
     * The returned in-memory DeviceRecord carries STEP_UP_REQUIRED so that
     * ContextEngine::buildFromDevice() propagates it to the SirusContext trust_level,
     * triggering StepUpPolicy. The stored DB record's trust_level is NOT changed.
     */
    public function testChangedFingerprintOnVerifiedDeviceSetsStepUpRequired(): void
    {
        $this->seedRecord('dev-step', 'fp-original', trust_level: 'anonymous');

        $result = $this->continuity->resolveDevice('dev-step', self::GOOD_SECRET, 'fp-changed', []);

        $this->assertSame('dev-step', $result->device_id);
        $this->assertSame('STEP_UP_REQUIRED', $result->trust_level);
        $this->assertSame('fp-changed', $result->fingerprint_hash);
        $this->assertSame(1, $result->drift_score);
    }

    /**
     * Verified device with SAME fingerprint → trust_level is NOT STEP_UP_REQUIRED
     * (the stored trust_level is preserved as-is).
     */
    public function testSameFingerprintOnVerifiedDevicePreservesStoredTrustLevel(): void
    {
        $this->seedRecord('dev-ok', 'fp-same', trust_level: 'user');

        $result = $this->continuity->resolveDevice('dev-ok', self::GOOD_SECRET, 'fp-same', []);

        $this->assertSame('dev-ok', $result->device_id);
        $this->assertSame('user', $result->trust_level);
    }

    /**
     * Drift does NOT create a new device — the device_id is preserved.
     */
    public function testDriftDoesNotCreateNewDevice(): void
    {
        $this->seedRecord('dev-3', 'fp-old');

        $result = $this->continuity->resolveDevice('dev-3', self::GOOD_SECRET, 'fp-new', []);

        $this->assertSame('dev-3', $result->device_id);
        // Store should NOT contain a new entry keyed to 'fp-new' as a new device_id.
        // The fingerprint map is updated by updateFingerprintHash, not save().
        $this->assertArrayNotHasKey('fp-new', $this->store);
    }

    /**
     * Drift_score accumulates across multiple drift events.
     */
    public function testDriftScoreAccumulates(): void
    {
        $this->seedRecord('dev-4', 'fp-v1', drift_score: 3);

        $result = $this->continuity->resolveDevice('dev-4', self::GOOD_SECRET, 'fp-v2', []);

        $this->assertSame(4, $result->drift_score);
    }

    // ── Path 1c: wrong secret → fall through ─────────────────────────────────

    /**
     * When device_id is present but the secret is wrong, device_id claim is not trusted.
     * Falls through to fingerprint lookup.
     */
    public function testWrongSecretFallsThroughToFingerprintLookup(): void
    {
        // Plant a device with the correct fingerprint but wrong secret attempt.
        $this->seedRecord('dev-5', 'fp-5');

        // fp-5 is also in fpStore, so a fingerprint lookup would succeed.
        $result = $this->continuity->resolveDevice('dev-5', 'wrong-secret-here', 'fp-5', []);

        // Must have resolved via fingerprint path (lastSeenTouched by findByFingerprintHash path)
        // and must NOT have triggered drift (no secret verification).
        $this->assertSame('dev-5', $result->device_id);
        $this->assertArrayNotHasKey('dev-5', $this->driftIncrements);
        $this->assertArrayNotHasKey('dev-5', $this->updatedFingerprints);
    }

    /**
     * When device_id + wrong secret + fingerprint not in store → new device registered.
     */
    public function testWrongSecretAndUnknownFingerprintRegistersNewDevice(): void
    {
        $this->seedRecord('dev-6', 'fp-6');

        $result = $this->continuity->resolveDevice('dev-6', 'wrong-secret', 'fp-unknown', []);

        // A brand-new device was created (different device_id).
        $this->assertNotSame('dev-6', $result->device_id);
        $this->assertNotEmpty($result->device_secret);
        $this->assertSame(0, $result->drift_score);
    }

    // ── Path 2: fingerprint-only lookup ──────────────────────────────────────

    /**
     * Without a device_id, fingerprint match resolves an existing device.
     */
    public function testFingerprintMatchWithoutDeviceIdResolvesDevice(): void
    {
        $this->seedRecord('dev-7', 'fp-7');

        $result = $this->continuity->resolveDevice('', '', 'fp-7', []);

        $this->assertSame('dev-7', $result->device_id);
        $this->assertContains('dev-7', $this->lastSeenTouched);
    }

    // ── Path 3: new device registration ──────────────────────────────────────

    /**
     * When no existing record matches, a new device is registered with a server-generated
     * device_id, device_secret, and drift_score = 0.
     */
    public function testFirstVisitRegistersNewDevice(): void
    {
        $result = $this->continuity->resolveDevice('', '', 'fp-brand-new', []);

        // New UUID was generated.
        $uuid_regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        $this->assertTrue((bool) preg_match($uuid_regex, $result->device_id));

        // Secret is a 64-char hex string.
        $this->assertSame(64, strlen($result->device_secret));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result->device_secret);

        // Starts at zero drift.
        $this->assertSame(0, $result->drift_score);

        // Trust level starts as anonymous.
        $this->assertSame('anonymous', $result->trust_level);
    }

    // ── evaluateContinuity ──────────────────────────────────────────────────────

    /**
     * evaluateContinuity() returns fixed output shape for a valid record.
     */
    public function testEvaluateContinuityReturnsFixedSchema(): void
    {
        $record = $this->seedRecord('dev-ctx', 'fp-ctx-hash');

        $ctx = $this->continuity->evaluateContinuity($record);

        $this->assertArrayHasKey('device_hash', $ctx);
        $this->assertArrayHasKey('continuity_score', $ctx);
        $this->assertArrayHasKey('risk_flags', $ctx);
        $this->assertSame('fp-ctx-hash', $ctx['device_hash']);
        $this->assertIsFloat($ctx['continuity_score']);
        $this->assertIsArray($ctx['risk_flags']);
    }

    /**
     * evaluateContinuity() throws when device_id is empty.
     */
    public function testEvaluateContinuityThrowsOnEmptyDeviceId(): void
    {
        $this->expectException(\RuntimeException::class);

        $record = new DeviceRecord(
            device_id:        '',
            device_secret:    'secret',
            fingerprint_hash: 'fp-hash',
            environment_json: '{}',
            first_seen:       time(),
            last_seen:        time(),
            trust_level:      'anonymous',
            drift_score:      0,
        );

        $this->continuity->evaluateContinuity($record);
    }

    /**
     * evaluateContinuity() throws when fingerprint_hash is empty.
     */
    public function testEvaluateContinuityThrowsOnEmptyFingerprintHash(): void
    {
        $this->expectException(\RuntimeException::class);

        $record = new DeviceRecord(
            device_id:        'valid-device-id',
            device_secret:    'secret',
            fingerprint_hash: '',
            environment_json: '{}',
            first_seen:       time(),
            last_seen:        time(),
            trust_level:      'anonymous',
            drift_score:      0,
        );

        $this->continuity->evaluateContinuity($record);
    }

    /**
     * continuity_score decreases with each drift event and is floored at 0.0.
     */
    public function testContinuityScoreDecreasesWithDrift(): void
    {
        $record_no_drift = $this->seedRecord('dev-nd', 'fp-nd', drift_score: 0);
        $record_with_drift = $this->seedRecord('dev-wd', 'fp-wd', drift_score: 5);
        $record_max_drift = new DeviceRecord(
            device_id:        'dev-max',
            device_secret:    self::GOOD_SECRET,
            fingerprint_hash: 'fp-max',
            environment_json: '{}',
            first_seen:       time(),
            last_seen:        time(),
            trust_level:      'anonymous',
            drift_score:      100,
        );

        $ctx_no_drift   = $this->continuity->evaluateContinuity($record_no_drift);
        $ctx_with_drift = $this->continuity->evaluateContinuity($record_with_drift);
        $ctx_max_drift  = $this->continuity->evaluateContinuity($record_max_drift);

        // Score formula: max(0.0, 1.0 - (drift_score * DRIFT_PENALTY_PER_EVENT))
        // where DRIFT_PENALTY_PER_EVENT = 0.05
        // drift_score=0  → 1.0 - (0  * 0.05) = 1.0
        // drift_score=5  → 1.0 - (5  * 0.05) = 0.75
        // drift_score=100 → max(0.0, 1.0 - (100 * 0.05)) = max(0.0, -4.0) = 0.0
        $this->assertSame(1.0, $ctx_no_drift['continuity_score']);
        $this->assertSame(0.75, $ctx_with_drift['continuity_score']);
        $this->assertSame(0.0, $ctx_max_drift['continuity_score']);
    }
}
