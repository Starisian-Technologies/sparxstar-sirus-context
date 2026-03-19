<?php

/**
 * Base test case for SPARXSTAR Sirus unit tests.
 *
 * Provides a polyfill for assertMatchesRegularExpression() so tests run
 * correctly under both the project-required PHPUnit ^11.5 and older global
 * installs (PHPUnit < 9.1) that may be present in development environments.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use PHPUnit\Framework\TestCase;

abstract class SirusTestCase extends TestCase
{
    /**
     * Polyfill: assertMatchesRegularExpression was introduced in PHPUnit 9.1.
     * This override is a no-op under PHPUnit 9.1+ (same behaviour) and
     * provides backward compatibility for PHPUnit < 9.1.
     */
    public static function assertMatchesRegularExpression(
        string $pattern,
        string $string,
        string $message = ''
    ): void {
        if (is_callable([parent::class, 'assertMatchesRegularExpression'])) {
            parent::assertMatchesRegularExpression($pattern, $string, $message);
            return;
        }

        // PHPUnit < 9.1 fallback — identical semantics.
        $result = preg_match($pattern, $string);

        if ($result === false) {
            $error = preg_last_error_msg();
            static::fail("Invalid regular expression pattern '$pattern': $error");
        }

        static::assertTrue(
            $result === 1,
            $message !== '' ? $message : "Failed asserting that '$string' matches pattern '$pattern'."
        );
    }
}
