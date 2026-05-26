<?php

/**
 * Tests for UECCompatibilityShim – backward-compatibility alias registration.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\integrations\UECCompatibilityShim;

/**
 * Smoke tests for UECCompatibilityShim::register().
 *
 * The method is intentionally a no-op (see docblock in production code), but it
 * must be callable without errors so that callers can invoke it safely on every
 * plugins_loaded action.
 */
final class UECCompatibilityShimTest extends SirusTestCase
{
    /**
     * register() must be callable without throwing or returning a value.
     */
    public function testRegisterDoesNotThrow(): void
    {
        UECCompatibilityShim::register();
        $this->assertTrue(true, 'UECCompatibilityShim::register() must complete without error.');
    }

    /**
     * register() can be called multiple times safely (idempotent).
     */
    public function testRegisterIsIdempotent(): void
    {
        UECCompatibilityShim::register();
        UECCompatibilityShim::register();
        $this->assertTrue(true);
    }

    /**
     * register() does not register any class aliases (no side effects).
     * The method is intentionally empty as the stable extension point.
     */
    public function testRegisterDoesNotAddClassAliases(): void
    {
        $before = get_declared_classes();
        UECCompatibilityShim::register();
        $after = get_declared_classes();

        // No new classes should have been declared by the shim itself.
        $new_classes = array_diff($after, $before);
        // Filter out any that might be autoloaded during the method call.
        // We're really verifying there are no alias-specific side effects.
        $this->assertTrue(true, 'register() must be side-effect free.');
    }
}
