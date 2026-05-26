<?php

/**
 * Tests for EnvironmentRecord privacy invariants.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\core\EnvironmentRecord;

final class EnvironmentRecordTest extends SirusTestCase
{
    public function testIpAddressIsAnonymizedAtConstruction(): void
    {
        $record = $this->makeRecord(ip_address: '192.168.1.44');

        $this->assertSame('192.168.1.0', $record->ip_address);
    }

    public function testLocationRetainsOnlyRegionLevelFields(): void
    {
        $record = $this->makeRecord(
            location: [
                'country'    => 'GH',
                'region'     => 'Greater Accra',
                'approx_lat' => 5.6037,
                'approx_lng' => -0.1870,
                'lat'        => 5.60371234,
                'lng'        => -0.18701234,
                'email'      => 'private@example.com',
            ]
        );

        $this->assertSame(
            [
                'country'    => 'GH',
                'region'     => 'Greater Accra',
                'approx_lat' => 5.6,
                'approx_lng' => -0.19,
            ],
            $record->location
        );
    }

    public function testInvalidNetworkTypeFallsBackToUnknown(): void
    {
        $record = $this->makeRecord(network_effective_type: 'satellite-laser');

        $this->assertSame('unknown', $record->network_effective_type);
    }

    /**
     * @param array<string, mixed> $location
     */
    private function makeRecord(
        string $ip_address = '203.0.113.19',
        array $location = [],
        string $network_effective_type = '4g'
    ): EnvironmentRecord {
        return new EnvironmentRecord(
            environment_id:         'env-1',
            browser_name:           'Chrome',
            browser_version:        '123.0',
            os:                     'Android',
            os_version:             '14',
            device_type:            'smartphone',
            device_brand:           'Pixel',
            device_model:           '8',
            network_effective_type: $network_effective_type,
            ip_address:             $ip_address,
            location:               $location,
            time_zone:              'Africa/Accra',
            is_bot:                 false,
            captured_at:            1_700_000_000,
        );
    }
}
