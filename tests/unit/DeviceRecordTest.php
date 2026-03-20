<?php

/**
 * Tests for the DeviceRecord Data Transfer Object.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\Sirus\core\DeviceRecord;

/**
 * Validates the DeviceRecord DTO properties and activity window logic.
 */
final class DeviceRecordTest extends TestCase
{
    /** A known-good secret for tests. */
    private const GOOD_SECRET = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    /**
     * Helper to create a DeviceRecord with a given last_seen timestamp.
     *
     * @param int $last_seen Unix timestamp for last_seen.
     */
    private function makeRecord(int $last_seen, int $drift_score = 0): DeviceRecord
    {
        return new DeviceRecord(
            device_id:        'dev-uuid-1234',
            device_secret:    self::GOOD_SECRET,
            fingerprint_hash: 'abc123hash',
            environment_json: '{"ua":"Mozilla/5.0"}',
            first_seen:       $last_seen - 3600,
            last_seen:        $last_seen,
            trust_level:      'device',
            drift_score:      $drift_score,
        );
    }

    /**
     * Constructor properties are accessible and correctly typed.
     */
    public function testConstructorPropertyAccess(): void
    {
        $record = $this->makeRecord(1000);

        $this->assertSame('dev-uuid-1234', $record->device_id);
        $this->assertSame(self::GOOD_SECRET, $record->device_secret);
        $this->assertSame('abc123hash', $record->fingerprint_hash);
        $this->assertSame('{"ua":"Mozilla/5.0"}', $record->environment_json);
        $this->assertSame(1000 - 3600, $record->first_seen);
        $this->assertSame(1000, $record->last_seen);
        $this->assertSame('device', $record->trust_level);
        $this->assertSame(0, $record->drift_score);
    }

    /**
     * drift_score is set correctly from the constructor.
     */
    public function testDriftScoreIsSetFromConstructor(): void
    {
        $record = $this->makeRecord(time(), 5);

        $this->assertSame(5, $record->drift_score);
    }

    /**
     * A device seen one second ago is still active.
     */
    public function testIsActiveReturnsTrueForRecentDevice(): void
    {
        $record = $this->makeRecord(time() - 1);

        $this->assertTrue($record->isActive());
    }

    /**
     * A device whose last_seen is more than 90 days ago is inactive.
     */
    public function testIsActiveReturnsFalseForStaleDevice(): void
    {
        $ninety_one_days_ago = time() - (91 * DAY_IN_SECONDS);
        $record              = $this->makeRecord($ninety_one_days_ago);

        $this->assertFalse($record->isActive());
    }

    /**
     * A device seen exactly at the boundary (90 days * DAY_IN_SECONDS) is inactive.
     * The boundary is exclusive: (time - last_seen) must be < TTL.
     */
    public function testIsActiveReturnsFalseAtExactTtlBoundary(): void
    {
        $exactly_ninety_days = time() - (90 * DAY_IN_SECONDS);
        $record              = $this->makeRecord($exactly_ninety_days);

        $this->assertFalse($record->isActive());
    }

    /**
     * A device seen one second before the 90-day boundary is still active.
     */
    public function testIsActiveReturnsTrueJustBeforeTtlBoundary(): void
    {
        $just_before = time() - (90 * DAY_IN_SECONDS) + 1;
        $record      = $this->makeRecord($just_before);

        $this->assertTrue($record->isActive());
    }

    // ── verifySecret() ────────────────────────────────────────────────────────

    /**
     * verifySecret() returns true for a matching secret.
     */
    public function testVerifySecretReturnsTrueForCorrectSecret(): void
    {
        $record = $this->makeRecord(time());

        $this->assertTrue($record->verifySecret(self::GOOD_SECRET));
    }

    /**
     * verifySecret() returns false for a wrong secret.
     */
    public function testVerifySecretReturnsFalseForWrongSecret(): void
    {
        $record = $this->makeRecord(time());

        $this->assertFalse($record->verifySecret('totally-wrong-secret'));
    }

    /**
     * verifySecret() returns false for an empty string.
     */
    public function testVerifySecretReturnsFalseForEmptyString(): void
    {
        $record = $this->makeRecord(time());

        $this->assertFalse($record->verifySecret(''));
    }
}
