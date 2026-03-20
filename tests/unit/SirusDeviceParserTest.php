<?php

/**
 * Tests for SirusDeviceParser – server-side UA parsing wrapper.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\Sirus\services\SirusDeviceParser;

/**
 * Validates that SirusDeviceParser returns the correct structure and
 * degrades gracefully when Matomo DeviceDetector is not installed.
 */
final class SirusDeviceParserTest extends TestCase
{
    /**
     * Returns the expected array key names.
     *
     * @return string[]
     */
    private function expectedKeys(): array
    {
        return [
            'browser',
            'browser_version',
            'os',
            'os_version',
            'device_type',
            'brand',
            'model',
            'is_bot',
        ];
    }

    /**
     * parse() always returns an array with the full expected structure.
     */
    public function testParseAlwaysReturnsExpectedStructure(): void
    {
        $parser = new SirusDeviceParser();
        $result = $parser->parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

        foreach ($this->expectedKeys() as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    /**
     * parse() with an empty string returns the empty structure (all empty strings, is_bot false).
     */
    public function testParseEmptyStringReturnsEmptyStructure(): void
    {
        $parser = new SirusDeviceParser();
        $result = $parser->parse('');

        $this->assertSame('', $result['browser']);
        $this->assertSame('', $result['os']);
        $this->assertSame('', $result['device_type']);
        $this->assertFalse($result['is_bot']);
    }

    /**
     * is_bot is always a bool, never a string or null.
     */
    public function testIsBotIsAlwaysBool(): void
    {
        $parser = new SirusDeviceParser();
        $result = $parser->parse('Googlebot/2.1 (+http://www.google.com/bot.html)');

        $this->assertIsBool($result['is_bot']);
    }

    /**
     * When Matomo library is absent, parse() returns the empty structure
     * rather than throwing an exception.
     *
     * In CI the library is not installed, so this test also confirms graceful
     * degradation in that environment.
     */
    public function testParseDegradeGracefullyWhenLibraryAbsent(): void
    {
        if (class_exists(\DeviceDetector\DeviceDetector::class)) {
            $this->markTestSkipped('Matomo DeviceDetector is installed; graceful-degradation path not exercised.');
        }

        $parser = new SirusDeviceParser();
        $result = $parser->parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)');

        $this->assertIsArray($result);
        foreach ($this->expectedKeys() as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }
}
