# SirusNetworkSettingsPage

**Namespace:** `Starisian\Sparxstar\Sirus\admin`

**Full Class Name:** `Starisian\Sparxstar\Sirus\admin\SirusNetworkSettingsPage`

## Description

SirusNetworkSettingsPage - Network-level access control for Sirus observability data.
Super-admins use this page to:
  1. Set a network-wide default: whether subsites may access Sirus data.
  2. Configure which WordPress roles on a subsite can view the dashboard.
  3. Override settings per individual subsite.
Settings are stored as site options (wp_sitemeta) so they are shared
across the entire network and inaccessible to individual site admins.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
Registers and renders the Sirus Network Settings page under Network Admin.
Only accessible to super-admins.

## Methods

### `__construct()`

SirusNetworkSettingsPage - Network-level access control for Sirus observability data.
Super-admins use this page to:
  1. Set a network-wide default: whether subsites may access Sirus data.
  2. Configure which WordPress roles on a subsite can view the dashboard.
  3. Override settings per individual subsite.
Settings are stored as site options (wp_sitemeta) so they are shared
across the entire network and inaccessible to individual site admins.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
Registers and renders the Sirus Network Settings page under Network Admin.
Only accessible to super-admins.
/
final class SirusNetworkSettingsPage
{
    /** Site option key for the network-wide default access config. */
    public const OPTION_DEFAULT = 'sirus_network_default_access';

    /** Site option key for per-site overrides (array keyed by blog_id). */
    public const OPTION_SITES = 'sirus_network_site_overrides';

    /** Nonce action used for saving settings. */
    private const NONCE_ACTION = 'sirus_network_settings_save';

    /** Available roles for the dropdown. */
    private const SELECTABLE_ROLES = [
        'administrator',
        'editor',
        'author',
        'contributor',
        'subscriber',
    ];

    /**
Registers the network admin menu and the save handler.

### `add_network_menu()`

Adds the Sirus entry to the Network Admin menu.

### `getDefaultAccess()`

Returns the current default access configuration.
@return array{enabled: bool, roles: string[]}

### `getSiteAccess(int $blog_id)`

Returns the per-site override for a given blog ID, falling back to the default.
@param int $blog_id Target blog identifier.
@return array{enabled: bool, roles: string[]}

### `userCanViewOnSite(int $user_id, int $blog_id)`

Determines whether a given user can view Sirus data on a specific site.
Super-admins always have access. Regular users are checked against the
site-level access config (enabled flag + allowed roles).
@param int $user_id WordPress user ID.
@param int $blog_id Target blog ID.
@return bool

### `handle_save()`

Handles the network admin form POST for saving Sirus network settings.

### `render()`

Renders the network settings page HTML.

### `sanitize_roles(array $roles)`

Filters a role list to only include roles present in SELECTABLE_ROLES.
@param string[] $roles Raw role array.
@return string[]

