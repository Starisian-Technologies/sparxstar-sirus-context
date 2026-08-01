<?php
/**
 * Tests focused on the main plugin bootstrap file.
 *
 * @package Starisian\SparxstarUEC\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\SparxstarUEC\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Validates constants and hook registrations defined in the bootstrap file.
 */
final class PluginBootstrapTest extends TestCase
{
    /**
     * Load the plugin bootstrap once before running assertions.
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2) . '/sparxstar-user-environment-check.php';
    }

    /**
     * Ensure the bootstrap defines the expected plugin constants.
     */
    public function testPluginConstantsAreDefined(): void
    {
        $this->assertTrue(defined('SPX_ENV_CHECK_PLUGIN_FILE'));
        $this->assertTrue(defined('SPX_ENV_CHECK_PLUGIN_PATH'));
        $this->assertTrue(defined('SPX_ENV_CHECK_VERSION'));
        $bootstrap = file_get_contents(dirname(__DIR__, 2) . '/sparxstar-user-environment-check.php');
        $this->assertNotFalse($bootstrap);
        $this->assertMatchesRegularExpression("/define\('SPX_ENV_CHECK_VERSION',\\s*'0\\.9\\.6'\\s*\\);/", $bootstrap);
        $this->assertTrue(defined('SPX_ENV_CHECK_TEXT_DOMAIN'));
        $this->assertTrue(defined('SPX_ENV_CHECK_DB_TABLE_NAME'));
    }

    /**
     * B-3: sparxstar-user-environment-check.php is a no-op once
     * sparxstar-sirus-context.php has loaded (it defines SIRUS_VERSION) —
     * "Sirus wins". This test suite's shared bootstrap (tests/bootstrap-unit.php)
     * always defines SIRUS_VERSION up front, so the legacy file's guard fires
     * before it reaches its activation/deactivation hook registrations, and
     * they must NOT be registered.
     */
    public function testActivationHooksAreNotRegisteredWhenSirusIsLoaded(): void
    {
        $this->assertTrue(defined('SIRUS_VERSION'), 'Test bootstrap is expected to pre-define SIRUS_VERSION.');
        $this->assertArrayNotHasKey('callback', $GLOBALS['registered_activation_hook'] ?? []);
        $this->assertArrayNotHasKey('callback', $GLOBALS['registered_deactivation_hook'] ?? []);
    }
}
