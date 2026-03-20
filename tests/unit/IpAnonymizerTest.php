<?php

/**
 * Tests for IpAnonymizer – the IP privacy helper.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\Sirus\helpers\IpAnonymizer;

/**
 * Validates IP anonymization for IPv4 and IPv6 addresses.
 */
final class IpAnonymizerTest extends TestCase
{
    /**
     * IPv4: last octet is zeroed.
     */
    public function testAnonymizeIPv4ZerosLastOctet(): void
    {
        $this->assertSame('192.168.1.0', IpAnonymizer::anonymize('192.168.1.42'));
        $this->assertSame('10.0.0.0', IpAnonymizer::anonymize('10.0.0.1'));
        $this->assertSame('255.255.255.0', IpAnonymizer::anonymize('255.255.255.255'));
    }

    /**
     * IPv4 already ending in .0 stays the same.
     */
    public function testAnonymizeIPv4AlreadyZeroed(): void
    {
        $this->assertSame('192.168.1.0', IpAnonymizer::anonymize('192.168.1.0'));
    }

    /**
     * IPv4-mapped IPv6 addresses are handled as IPv4.
     */
    public function testAnonymizeIPv4MappedIPv6(): void
    {
        $result = IpAnonymizer::anonymize('::ffff:192.168.1.42');
        $this->assertSame('192.168.1.0', $result);
    }

    /**
     * Invalid input returns an empty string.
     */
    public function testAnonymizeInvalidReturnsEmpty(): void
    {
        $this->assertSame('', IpAnonymizer::anonymize('not-an-ip'));
        $this->assertSame('', IpAnonymizer::anonymize(''));
        $this->assertSame('', IpAnonymizer::anonymize('999.999.999.999'));
    }

    /**
     * IPv6: result is a valid IPv6 string (non-empty).
     */
    public function testAnonymizeIPv6ReturnsValidString(): void
    {
        $result = IpAnonymizer::anonymize('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
        $this->assertNotEmpty($result);
        $this->assertTrue(
            (bool) filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6),
            "Expected a valid IPv6 address, got: {$result}"
        );
    }

    /**
     * IPv6: two different addresses in the same /48 subnet produce the same result.
     */
    public function testAnonymizeIPv6SameSubnetSameResult(): void
    {
        $a = IpAnonymizer::anonymize('2001:db8::1');
        $b = IpAnonymizer::anonymize('2001:db8::2');

        $this->assertSame($a, $b, 'Two addresses in the same /48 must anonymize identically.');
    }

    /**
     * IPv6: two addresses in different /48 subnets produce different results.
     */
    public function testAnonymizeIPv6DifferentSubnetDifferentResult(): void
    {
        $a = IpAnonymizer::anonymize('2001:db8:1::1');
        $b = IpAnonymizer::anonymize('2001:db8:2::1');

        $this->assertNotSame($a, $b, 'Two addresses in different /48 subnets must not anonymize identically.');
    }
}
