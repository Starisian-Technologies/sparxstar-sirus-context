<?php

/**
 * Tests for EnvironmentResolver client-first resolution.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\services\EnvironmentResolver;

final class EnvironmentResolverTest extends SirusTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.42';
        $GLOBALS['registered_filters'] = [];
    }

    public function testClientSignalsTakePrecedenceOverUaFallback(): void
    {
        $resolver = new EnvironmentResolver();
        $record   = $resolver->resolve(
            [
                'browser_name'           => 'Firefox',
                'browser_version'        => '126.0',
                'os'                     => 'Android',
                'os_version'             => '15',
                'device_type'            => 'smartphone',
                'device_brand'           => 'Samsung',
                'device_model'           => 'S24',
                'network_effective_type' => '3g',
                'timezone'               => 'Africa/Accra',
            ]
        );

        $this->assertSame('Firefox', $record->browser_name);
        $this->assertSame('126.0', $record->browser_version);
        $this->assertSame('Android', $record->os);
        $this->assertSame('15', $record->os_version);
        $this->assertSame('smartphone', $record->device_type);
        $this->assertSame('Samsung', $record->device_brand);
        $this->assertSame('S24', $record->device_model);
        $this->assertSame('3g', $record->network_effective_type);
        $this->assertSame('Africa/Accra', $record->time_zone);
    }

    public function testUaFallbackFillsMissingClientSignals(): void
    {
        $resolver = new EnvironmentResolver();
        $record   = $resolver->resolve(
            [
                'browser_name' => 'Firefox',
            ]
        );

        $this->assertSame('Firefox', $record->browser_name);
        $this->assertSame('Windows', $record->os);
        $this->assertSame('desktop', $record->device_type);
    }

    public function testNetworkTypeFallsBackToFilterWhenClientSignalMissing(): void
    {
        add_filter(
            'sparxstar_env_network_effective_type',
            static fn (string $type): string => $type === 'unknown' ? 'wifi' : $type
        );

        $resolver = new EnvironmentResolver();
        $record   = $resolver->resolve();

        $this->assertSame('wifi', $record->network_effective_type);
    }

    public function testGeolocationFilterProducesRegionLevelLocation(): void
    {
        add_filter(
            'sparxstar_env_geolocation_lookup',
            static function ($value, string $ip): array {
                unset($value);

                return [
                    'country'    => 'GH',
                    'region'     => 'Greater Accra',
                    'approx_lat' => 5.6037,
                    'approx_lng' => -0.1870,
                    'ip'         => $ip,
                    'lat'        => 5.60371234,
                    'lng'        => -0.18701234,
                ];
            },
            10,
            2
        );

        $resolver = new EnvironmentResolver();
        $record   = $resolver->resolve();

        $this->assertSame(
            [
                'country'    => 'GH',
                'region'     => 'Greater Accra',
                'approx_lat' => 5.6,
                'approx_lng' => -0.19,
            ],
            $record->location
        );
        $this->assertSame('gh_greater_accra', $resolver->getGeoZone());
    }
}
