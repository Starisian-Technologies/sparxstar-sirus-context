# SparxstarUECAssetManager

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECAssetManager`

## Description

Asset loader for SPARXSTAR Sirus — Context Engine.
Version 4.0.1: Fixed REST API namespace and JS localization keys.

## Methods

### `enqueue_frontend()`

Asset loader for SPARXSTAR Sirus — Context Engine.
Version 4.0.1: Fixed REST API namespace and JS localization keys.
/
final class SparxstarUECAssetManager
{
    private const VERSION = '4.0.1';

    private const TEXT_DOMAIN = 'sparxstar-user-environment-check';

    // --- Bootstrap Handle ---
    private const HANDLE_BOOTSTRAP = 'sparxstar-uec-bootstrap';

    // --- Style Handles ---
    private const STYLE_HANDLE = 'sparxstar-user-environment-check-styles';

    private const ADMIN_STYLE_HANDLE = 'sparxstar-uec-admin';

    public static function init(): void
    {
        add_action('wp_enqueue_scripts', self::enqueue_frontend(...));
        add_action('admin_enqueue_scripts', self::enqueue_admin(...));
    }

    /**
Load frontend scripts - always uses production bundle.

### `enqueue_frontend_styles()`

Enqueue frontend stylesheet.

### `enqueue_admin()`

Admin screen loader.

### `get_localization_data()`

Gathers all necessary server-side data.
FIXED: Matches JS keys and Controller Namespace.
@return array The data to be localized.

