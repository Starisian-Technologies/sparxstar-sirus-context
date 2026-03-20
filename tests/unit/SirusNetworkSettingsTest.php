<?php

/**
 * Tests for SirusNetworkSettingsPage access control logic.
 *
 * Tests the static helper methods that determine whether a user can
 * view Sirus data on a given site.
 *
 * @package Starisian\Sparxstar\Sirus\Tests\Unit
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\Tests\Unit;

use Starisian\Sparxstar\Sirus\admin\SirusNetworkSettingsPage;

/**
 * Validates SirusNetworkSettingsPage access-control helpers.
 */
final class SirusNetworkSettingsTest extends SirusTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wp_site_options'] = [];
        $GLOBALS['__wp_users']      = [];
        $GLOBALS['__is_multisite']  = false;
    }

    // ── getDefaultAccess ──────────────────────────────────────────────────────

    /**
     * When no site option exists, defaults to disabled with administrator only.
     */
    public function testGetDefaultAccessFallsBackToSafeDefaults(): void
    {
        $access = SirusNetworkSettingsPage::getDefaultAccess();

        $this->assertFalse($access['enabled']);
        $this->assertSame(['administrator'], $access['roles']);
    }

    /**
     * Stored default is returned correctly.
     */
    public function testGetDefaultAccessReturnsStoredValue(): void
    {
        update_site_option(
            SirusNetworkSettingsPage::OPTION_DEFAULT,
            ['enabled' => true, 'roles' => ['administrator', 'editor']]
        );

        $access = SirusNetworkSettingsPage::getDefaultAccess();

        $this->assertTrue($access['enabled']);
        $this->assertContains('editor', $access['roles']);
    }

    // ── getSiteAccess ─────────────────────────────────────────────────────────

    /**
     * When no per-site override exists, falls back to the network default.
     */
    public function testGetSiteAccessFallsBackToDefault(): void
    {
        update_site_option(
            SirusNetworkSettingsPage::OPTION_DEFAULT,
            ['enabled' => true, 'roles' => ['administrator']]
        );

        $access = SirusNetworkSettingsPage::getSiteAccess(2);

        $this->assertTrue($access['enabled']);
    }

    /**
     * A per-site override takes precedence over the network default.
     */
    public function testGetSiteAccessOverrideTakesPrecedence(): void
    {
        update_site_option(
            SirusNetworkSettingsPage::OPTION_DEFAULT,
            ['enabled' => false, 'roles' => ['administrator']]
        );

        update_site_option(
            SirusNetworkSettingsPage::OPTION_SITES,
            [5 => ['enabled' => true, 'roles' => ['editor']]]
        );

        $access = SirusNetworkSettingsPage::getSiteAccess(5);

        $this->assertTrue($access['enabled']);
        $this->assertSame(['editor'], $access['roles']);
    }

    /**
     * A site without an override still uses the network default.
     */
    public function testGetSiteAccessNonOverridedSiteUsesDefault(): void
    {
        update_site_option(
            SirusNetworkSettingsPage::OPTION_SITES,
            [5 => ['enabled' => true, 'roles' => ['editor']]]
        );

        update_site_option(
            SirusNetworkSettingsPage::OPTION_DEFAULT,
            ['enabled' => false, 'roles' => ['administrator']]
        );

        // Blog 9 has no override → uses default.
        $access = SirusNetworkSettingsPage::getSiteAccess(9);

        $this->assertFalse($access['enabled']);
    }

    // ── userCanViewOnSite ─────────────────────────────────────────────────────

    /**
     * Super-admins can always view data regardless of site settings.
     */
    public function testSuperAdminCanAlwaysView(): void
    {
        // OPTION_DEFAULT disabled — super-admin should still pass.
        update_site_option(
            SirusNetworkSettingsPage::OPTION_DEFAULT,
            ['enabled' => false, 'roles' => ['administrator']]
        );

        // is_super_admin() shim returns true for any user.
        $this->assertTrue(SirusNetworkSettingsPage::userCanViewOnSite(1, 1));
    }

    /**
     * A regular user on a site with access disabled is denied.
     */
    public function testRegularUserDeniedWhenSiteDisabled(): void
    {
        // Override is_super_admin to return false for user 99.
        $GLOBALS['__is_super_admin_map'] = [99 => false];

        update_site_option(
            SirusNetworkSettingsPage::OPTION_DEFAULT,
            ['enabled' => false, 'roles' => ['administrator']]
        );

        // No WP_User registered → get_userdata() returns false → cannot view.
        $this->assertFalse(SirusNetworkSettingsPage::userCanViewOnSite(99, 1));
    }

    // ── Role sanitisation ─────────────────────────────────────────────────────

    /**
     * Invalid roles are stripped; if none remain, 'administrator' is used.
     */
    public function testInvalidRolesAreStrippedWithAdminFallback(): void
    {
        update_site_option(
            SirusNetworkSettingsPage::OPTION_DEFAULT,
            ['enabled' => true, 'roles' => ['hacker', 'superuser', 'invalid_role']]
        );

        $access = SirusNetworkSettingsPage::getDefaultAccess();

        // All invalid roles stripped → fallback to ['administrator'].
        $this->assertSame(['administrator'], $access['roles']);
    }

    /**
     * Valid roles are preserved, invalid ones removed.
     */
    public function testMixedRolesFiltersInvalid(): void
    {
        update_site_option(
            SirusNetworkSettingsPage::OPTION_DEFAULT,
            ['enabled' => true, 'roles' => ['editor', 'hacker', 'author']]
        );

        $access = SirusNetworkSettingsPage::getDefaultAccess();

        $this->assertContains('editor', $access['roles']);
        $this->assertContains('author', $access['roles']);
        $this->assertNotContains('hacker', $access['roles']);
    }
}
