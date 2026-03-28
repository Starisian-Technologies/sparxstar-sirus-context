# SparxstarUECInstaller

**Namespace:** `Starisian\SparxstarUEC\core`

**Full Class Name:** `Starisian\SparxstarUEC\core\SparxstarUECInstaller`

## Description

Handles plugin lifecycle events across single and multisite contexts.

## Methods

### `spx_uec_activate($network_wide)`

Handles plugin lifecycle events across single and multisite contexts.
/
class SparxstarUECInstaller
{
    /**
Run activation tasks respecting network-wide installs.
@param bool|mixed $network_wide Flag provided by WordPress when activated network-wide.

### `spx_uec_deactivate($network_wide)`

Run deactivation cleanup without removing data.
@param bool|mixed $network_wide Flag provided by WordPress when deactivated network-wide.

### `spx_uec_initialize_new_site(\WP_Site|int $new_site)`

Initialise the plugin for a newly created site in a network.
@param \WP_Site|int $new_site Newly created site object or blog ID supplied by WordPress.

### `activate_site(\wpdb $wpdb)`

Perform activation logic for the current site only.
@param \wpdb $wpdb Database adapter from the current blog context.

### `deactivate_site()`

Clear scheduled events for the current site only.

### `seed_defaults()`

Register default options for the current site if they do not exist.

