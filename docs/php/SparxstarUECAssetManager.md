# SparxstarUECAssetManager

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECAssetManager`

## Description

Modern asset loader for Sparxstar User Environment Check plugin.
Features:
- Always loads bundled/minified production assets
- Vendor scripts (FingerprintJS, DeviceDetector) are bundled via Rollup
- Run `pnpm run build` after updating vendor dependencies
- Admin Mode: Optional panel scripts for settings UI
@version 4.0.0

## Methods

### `enqueue_frontend()`

Modern asset loader for Sparxstar User Environment Check plugin.
Features:
- Always loads bundled/minified production assets
- Vendor scripts (FingerprintJS, DeviceDetector) are bundled via Rollup
- Run `pnpm run build` after updating vendor dependencies
- Admin Mode: Optional panel scripts for settings UI
@version 4.0.0
/
final class SparxstarUECAssetManager
{
    private const VERSION     = '4.0.0';

    private const TEXT_DOMAIN = 'sparxstar-user-environment-check';

    // --- Bootstrap Handle ---
    private const HANDLE_BOOTSTRAP = 'sparxstar-uec-bootstrap';

    // --- Style Handles ---
    private const STYLE_HANDLE       = 'sparxstar-user-environment-check-styles';

    private const ADMIN_STYLE_HANDLE = 'sparxstar-uec-admin';

    public static function init(): void
    {
        add_action('wp_enqueue_scripts', self::enqueue_frontend(...));
        add_action('admin_enqueue_scripts', self::enqueue_admin(...));
    }

    /**
Load frontend scripts - always uses production bundle.
Vendor dependencies are bundled via Rollup build process.

### `enqueue_frontend_styles()`

Enqueue frontend stylesheet - always uses production minified CSS.

### `enqueue_admin()`

Admin screen loader.
- Lightweight, NO heavy collectors
- Provides UI consistency in UEC settings page

### `get_localization_data()`

Gathers all necessary server-side data to be passed to client-side scripts.
@return array The data to be localized.

