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
    /**
     * Helper to create a DeviceRecord with a given last_seen timestamp.
     *
     * @param int $last_seen Unix timestamp for last_seen.
     */
    private function makeRecord(int $last_seen): DeviceRecord
    {
        return new DeviceRecord(
            device_id:        'dev-uuid-1234',
            fingerprint_hash: 'abc123hash',
            environment_json: '{"ua":"Mozilla/5.0"}',
            first_seen:       $last_seen - 3600,
            last_seen:        $last_seen,
            trust_level:      'device',
        );
    }

    /**
     * Constructor properties are accessible and correctly typed.
     */
    public function testConstructorPropertyAccess(): void
    {
        $record = $this->makeRecord(1000);

        $this->assertSame('dev-uuid-1234', $record->device_id);
        $this->assertSame('abc123hash', $record->fingerprint_hash);
        $this->assertSame('{"ua":"Mozilla/5.0"}', $record->environment_json);
        $this->assertSame(1000 - 3600, $record->first_seen);
        $this->assertSame(1000, $record->last_seen);
        $this->assertSame('device', $record->trust_level);
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
}
