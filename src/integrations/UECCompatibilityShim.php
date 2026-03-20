<?php
/**
 * UECCompatibilityShim - Registers backward-compatibility aliases for the old namespace.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\integrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ensures that code referencing the old Starisian\SparxstarUEC namespace continues
 * to work after the Sirus context engine is introduced.
 * StarUserEnv.php stays in the old namespace and delegates to ContextEngine.
 */
final class UECCompatibilityShim
{
    /**
     * Registers any necessary class aliases for backward compatibility.
     * Should be called early in the plugins_loaded action.
     */
    public static function register(): void
    {
        // StarUserEnv remains in its original namespace and directly imports ContextEngine,
        // so no class_alias calls are required at this time.
        // This method is intentionally left as a stable extension point for future shims.
    }
}
