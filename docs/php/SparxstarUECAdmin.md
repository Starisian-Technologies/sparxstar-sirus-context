# SparxstarUECAdmin

**Namespace:** `Starisian\SparxstarUEC\admin`

**Full Class Name:** `Starisian\SparxstarUEC\admin\SparxstarUECAdmin`

## Methods

### `add_admin_menu()`

SPARXSTAR Sirus — Context Engine — Admin Settings (Minimal, Stable)
Version 2.3: Fetch snapshots by User ID instead of browser session/fingerprint.
/

declare(strict_types=1);

namespace Starisian\SparxstarUEC\admin;

if (! defined('ABSPATH')) {
    exit;
}

use Throwable;
use Starisian\SparxstarUEC\StarUserEnv;
use Starisian\SparxstarUEC\helpers\StarLogger;
use Starisian\SparxstarUEC\core\SparxstarUECSnapshotRepository;

final class SparxstarUECAdmin
{
    private const OPTION_KEY_PROVIDER     = 'sparxstar_uec_geoip_provider';
    private const OPTION_KEY_IPINFO_KEY   = 'sparxstar_uec_ipinfo_api_key';
    private const OPTION_KEY_MAXMIND_PATH = 'sparxstar_uec_maxmind_db_path';
    private const PAGE_SLUG               = 'sparxstar-uec-settings';

    public function __construct()
    {
        if (is_admin()) {
            add_action('admin_menu', $this->add_admin_menu(...));
            add_action('admin_init', $this->register_settings(...));
            add_action('admin_notices', $this->admin_notices(...));
        }
    }

    /**
Register the options menu.

### `register_settings()`

Register settings and fields.

### `render_settings_page()`

Settings page output.

### `render_provider_field()`

Provider selection dropdown.

### `render_ipinfo_key_field()`

ipinfo.io API key input.

### `render_maxmind_path_field()`

MaxMind database path input.

### `render_snapshot_viewer_section()`

Snapshot viewer: now fetches by logged-in User ID.

### `admin_notices()`

Warning if GeoIP configuration is incomplete.

