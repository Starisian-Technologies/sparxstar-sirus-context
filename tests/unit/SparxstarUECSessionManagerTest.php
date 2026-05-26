<?php

/**
 * Tests for SparxstarUECSessionManager – PHP session wrapper with path lookup utility.
 *
 * NOTE: Session tests operate on $_SESSION directly in CLI mode, where
 * headers_sent() returns false and session_start() may or may not be available.
 * Tests are written to avoid relying on the actual PHP session backend and
 * instead focus on the pure logic in get_value_from_array() and lookup().
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\SparxstarUEC\includes\SparxstarUECSessionManager;

/**
 * Unit tests for SparxstarUECSessionManager.
 *
 * High-value coverage areas:
 * - get_value_from_array() – pure logic, no WP/session dependencies.
 * - lookup() – must return the $default parameter (stub for future functionality).
 * - get_session_id() – smoke test that method exists and returns a string.
 */
final class SparxstarUECSessionManagerTest extends SirusTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['fired_actions'] = [];
    }

    // ── get_value_from_array(): basic path lookup ─────────────────────────────

    /**
     * A single-level key must resolve correctly.
     */
    public function testGetValueFromArraySingleLevelKey(): void
    {
        $result = SparxstarUECSessionManager::get_value_from_array(['browser' => 'Chrome'], 'browser');
        $this->assertSame('Chrome', $result);
    }

    /**
     * A dot-separated two-level path must resolve to the nested value.
     */
    public function testGetValueFromArrayTwoLevelPath(): void
    {
        $array  = ['device' => ['type' => 'smartphone']];
        $result = SparxstarUECSessionManager::get_value_from_array($array, 'device.type');
        $this->assertSame('smartphone', $result);
    }

    /**
     * A three-level nested path must resolve correctly.
     */
    public function testGetValueFromArrayThreeLevelPath(): void
    {
        $array = ['a' => ['b' => ['c' => 'deep']]];
        $this->assertSame('deep', SparxstarUECSessionManager::get_value_from_array($array, 'a.b.c'));
    }

    /**
     * An absent top-level key must return the default value.
     */
    public function testGetValueFromArrayMissingTopLevelKeyReturnsDefault(): void
    {
        $result = SparxstarUECSessionManager::get_value_from_array([], 'missing_key', 'fallback');
        $this->assertSame('fallback', $result);
    }

    /**
     * An absent nested key must return the default value.
     */
    public function testGetValueFromArrayMissingNestedKeyReturnsDefault(): void
    {
        $array  = ['device' => ['os' => 'Android']];
        $result = SparxstarUECSessionManager::get_value_from_array($array, 'device.type', 'unknown');
        $this->assertSame('unknown', $result);
    }

    /**
     * Default parameter is null when not provided.
     */
    public function testGetValueFromArrayDefaultIsNullWhenNotProvided(): void
    {
        $result = SparxstarUECSessionManager::get_value_from_array(['a' => 1], 'nonexistent');
        $this->assertNull($result);
    }

    /**
     * An integer value must be returned as a string.
     */
    public function testGetValueFromArrayIntegerReturnedAsString(): void
    {
        $result = SparxstarUECSessionManager::get_value_from_array(['count' => 42], 'count');
        $this->assertSame('42', $result);
    }

    /**
     * A boolean false value must be returned as the string ''.
     */
    public function testGetValueFromArrayBooleanFalseReturnedAsString(): void
    {
        $result = SparxstarUECSessionManager::get_value_from_array(['flag' => false], 'flag');
        $this->assertSame('', $result);
    }

    /**
     * A boolean true value must be returned as the string '1'.
     */
    public function testGetValueFromArrayBooleanTrueReturnedAsString(): void
    {
        $result = SparxstarUECSessionManager::get_value_from_array(['flag' => true], 'flag');
        $this->assertSame('1', $result);
    }

    /**
     * When the resolved value is a non-scalar (e.g. an array), the default is returned.
     */
    public function testGetValueFromArrayNonScalarValueReturnsDefault(): void
    {
        $array  = ['meta' => ['nested' => ['too', 'deep']]];
        $result = SparxstarUECSessionManager::get_value_from_array($array, 'meta.nested', 'def');
        $this->assertSame('def', $result);
    }

    /**
     * An empty input array must return the default.
     */
    public function testGetValueFromArrayEmptyArrayReturnsDefault(): void
    {
        $result = SparxstarUECSessionManager::get_value_from_array([], 'a.b.c', 'empty');
        $this->assertSame('empty', $result);
    }

    // ── lookup() ─────────────────────────────────────────────────────────────

    /**
     * lookup() is a stub that always returns the provided $default value.
     */
    public function testLookupReturnsDefaultAlways(): void
    {
        $result = SparxstarUECSessionManager::lookup('key', 1, 'sess-abc', 'my_default');
        $this->assertSame('my_default', $result);
    }

    /**
     * lookup() returns null when no default is supplied.
     */
    public function testLookupReturnsNullWhenNoDefault(): void
    {
        $result = SparxstarUECSessionManager::lookup('key', null, null);
        $this->assertNull($result);
    }

    // ── get_session_id() ──────────────────────────────────────────────────────

    /**
     * get_session_id() must return a string (never throws).
     */
    public function testGetSessionIdReturnsString(): void
    {
        $result = SparxstarUECSessionManager::get_session_id();
        $this->assertIsString($result);
    }

    // ── get() ─────────────────────────────────────────────────────────────────

    /**
     * get() returns the default when the key is absent from the session.
     */
    public function testGetReturnsDefaultWhenKeyAbsent(): void
    {
        // Pre-set session namespace to empty to avoid relying on actual session.
        $_SESSION['sparxstar_uec_data'] = [];

        $result = SparxstarUECSessionManager::get('nonexistent_key', 'default_val');
        $this->assertSame('default_val', $result);
    }

    /**
     * set_all() stores multiple values, readable via get().
     */
    public function testSetAllStoresValues(): void
    {
        $_SESSION['sparxstar_uec_data'] = [];

        SparxstarUECSessionManager::set_all([
            'browser'     => 'Firefox',
            'device_type' => 'tablet',
        ]);

        $this->assertSame('Firefox', SparxstarUECSessionManager::get('browser'));
        $this->assertSame('tablet', SparxstarUECSessionManager::get('device_type'));
    }

    /**
     * set_all() with an empty array must be a no-op (does not throw).
     */
    public function testSetAllWithEmptyArrayIsNoOp(): void
    {
        SparxstarUECSessionManager::set_all([]);
        $this->assertTrue(true);
    }
}
