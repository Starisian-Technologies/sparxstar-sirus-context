<?php

/**
 * SirusNetworkSettingsPage - Network-level access control for Sirus observability data.
 *
 * Super-admins use this page to:
 *   1. Set a network-wide default: whether subsites may access Sirus data.
 *   2. Configure which WordPress roles on a subsite can view the dashboard.
 *   3. Override settings per individual subsite.
 *
 * Settings are stored as site options (wp_sitemeta) so they are shared
 * across the entire network and inaccessible to individual site admins.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\admin;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers and renders the Sirus Network Settings page under Network Admin.
 * Only accessible to super-admins.
 */
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
     * Registers the network admin menu and the save handler.
     */
    public function __construct()
    {
        add_action('network_admin_menu', [ $this, 'add_network_menu' ]);
        add_action('network_admin_edit_sirus_network_settings', [ $this, 'handle_save' ]);
    }

    /**
     * Adds the Sirus entry to the Network Admin menu.
     */
    public function add_network_menu(): void
    {
        add_menu_page(
            esc_html__('Sirus Observability', 'sparxstar-sirus'),
            esc_html__('Sirus', 'sparxstar-sirus'),
            'manage_network_options',
            'sirus-network-settings',
            [ $this, 'render' ],
            'dashicons-chart-line',
            30
        );
    }

    /**
     * Returns the current default access configuration.
     *
     * @return array{enabled: bool, roles: string[]}
     */
    public static function getDefaultAccess(): array
    {
        $raw = get_site_option(self::OPTION_DEFAULT, null);

        if (! is_array($raw)) {
            return [
                'enabled' => false,
                'roles'   => [ 'administrator' ],
            ];
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'roles'   => self::sanitize_roles((array) ($raw['roles'] ?? [ 'administrator' ])),
        ];
    }

    /**
     * Returns the per-site override for a given blog ID, falling back to the default.
     *
     * @param int $blog_id Target blog identifier.
     * @return array{enabled: bool, roles: string[]}
     */
    public static function getSiteAccess(int $blog_id): array
    {
        $overrides = get_site_option(self::OPTION_SITES, []);
        $overrides = is_array($overrides) ? $overrides : [];

        if (isset($overrides[ $blog_id ]) && is_array($overrides[ $blog_id ])) {
            return [
                'enabled' => (bool) ($overrides[ $blog_id ]['enabled'] ?? false),
                'roles'   => self::sanitize_roles((array) ($overrides[ $blog_id ]['roles'] ?? [ 'administrator' ])),
            ];
        }

        return self::getDefaultAccess();
    }

    /**
     * Determines whether a given user can view Sirus data on a specific site.
     *
     * Super-admins always have access. Regular users are checked against the
     * site-level access config (enabled flag + allowed roles).
     *
     * @param int $user_id WordPress user ID.
     * @param int $blog_id Target blog ID.
     * @return bool
     */
    public static function userCanViewOnSite(int $user_id, int $blog_id): bool
    {
        if (is_super_admin($user_id)) {
            return true;
        }

        $access = self::getSiteAccess($blog_id);

        if (! $access['enabled']) {
            return false;
        }

        // Check user roles on the target blog.
        if (is_multisite()) {
            switch_to_blog($blog_id);
        }

        $user     = get_userdata($user_id);
        $can_view = false;

        if ($user instanceof \WP_User) {
            $user_roles = (array) ($user->roles ?? []);
            $can_view   = (bool) array_intersect($user_roles, $access['roles']);
        }

        if (is_multisite()) {
            restore_current_blog();
        }

        return $can_view;
    }

    /**
     * Handles the network admin form POST for saving Sirus network settings.
     */
    public function handle_save(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        if (! current_user_can('manage_network_options')) {
            wp_die(
                esc_html__('You do not have permission to manage network options.', 'sparxstar-sirus'),
                esc_html__('Permission Denied', 'sparxstar-sirus'),
                [ 'response' => 403 ]
            );
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $default_enabled = isset($_POST['sirus_default_enabled']) && sanitize_text_field(wp_unslash((string) $_POST['sirus_default_enabled'])) === '1';
        $default_roles   = isset($_POST['sirus_default_roles'])   && is_array($_POST['sirus_default_roles'])
            ? self::sanitize_roles(array_map('sanitize_text_field', array_map('wp_unslash', (array) $_POST['sirus_default_roles'])))
            : [ 'administrator' ];

        update_site_option(
            self::OPTION_DEFAULT,
            [
                'enabled' => $default_enabled,
                'roles'   => $default_roles,
            ]
        );

        $raw_overrides = isset($_POST['sirus_site']) && is_array($_POST['sirus_site'])
            ? (array) $_POST['sirus_site']
            : [];
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        $overrides = [];

        foreach ($raw_overrides as $blog_id => $site_data) {
            $blog_id = (int) $blog_id;
            if ($blog_id <= 0) {
                continue;
            }

            $site_data = is_array($site_data) ? $site_data : [];
            $enabled   = isset($site_data['enabled']) && sanitize_text_field(wp_unslash((string) $site_data['enabled'])) === '1';
            $roles_raw = isset($site_data['roles'])   && is_array($site_data['roles'])
                ? $site_data['roles']
                : [ 'administrator' ];
            $roles_sanitized = self::sanitize_roles(
                array_map('sanitize_text_field', array_map('wp_unslash', (array) $roles_raw))
            );

            $overrides[ $blog_id ] = [
                'enabled' => $enabled,
                'roles'   => $roles_sanitized,
            ];
        }

        update_site_option(self::OPTION_SITES, $overrides);

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'    => 'sirus-network-settings',
                    'updated' => '1',
                ],
                network_admin_url('admin.php')
            )
        );
        exit;
    }

    /**
     * Renders the network settings page HTML.
     */
    public function render(): void
    {
        if (! current_user_can('manage_network_options')) {
            wp_die(esc_html__('Access denied.', 'sparxstar-sirus'), '', [ 'response' => 403 ]);
        }

        $default   = self::getDefaultAccess();
        $sites     = is_multisite() ? get_sites([ 'number' => 200 ]) : [];
        $overrides = get_site_option(self::OPTION_SITES, []);
        $overrides = is_array($overrides) ? $overrides : [];

        ob_start();
        ?>
		<div class="wrap">
			<h1><?php esc_html_e('Sirus Observability — Network Settings', 'sparxstar-sirus'); ?></h1>

			<?php if (isset($_GET['updated']) && sanitize_text_field(wp_unslash((string) ($_GET['updated'] ?? ''))) === '1') : // phpcs:ignore WordPress.Security.NonceVerification.Recommended?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Settings saved.', 'sparxstar-sirus'); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field(self::NONCE_ACTION); ?>
				<input type="hidden" name="action" value="sirus_network_settings" />

				<h2><?php esc_html_e('Default Access (applies to all subsites unless overridden)', 'sparxstar-sirus'); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Enable Sirus Dashboard by Default', 'sparxstar-sirus'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="sirus_default_enabled" value="1" <?php checked($default['enabled'], true); ?> />
								<?php esc_html_e('Allow subsites to view the Sirus Observability dashboard', 'sparxstar-sirus'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Default Allowed Roles', 'sparxstar-sirus'); ?></th>
						<td>
							<?php foreach (self::SELECTABLE_ROLES as $role) : ?>
								<label style="display:block;">
									<input
										type="checkbox"
										name="sirus_default_roles[]"
										value="<?php echo esc_attr($role); ?>"
										<?php checked(in_array($role, $default['roles'], true), true); ?>
									/>
									<?php echo esc_html(ucfirst($role)); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">
								<?php esc_html_e('Which roles on a subsite may view the Sirus dashboard.', 'sparxstar-sirus'); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php if ($sites !== []) : ?>
					<h2><?php esc_html_e('Per-Site Overrides', 'sparxstar-sirus'); ?></h2>
					<p class="description">
						<?php esc_html_e('Leave a site unconfigured to use the default settings above.', 'sparxstar-sirus'); ?>
					</p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e('Site', 'sparxstar-sirus'); ?></th>
								<th><?php esc_html_e('Enable', 'sparxstar-sirus'); ?></th>
								<th><?php esc_html_e('Allowed Roles', 'sparxstar-sirus'); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
                        foreach ($sites as $site) :
                            $blog_id     = (int) $site->blog_id;
                            $site_access = isset($overrides[ $blog_id ]) && is_array($overrides[ $blog_id ])
                                ? [
                                    'enabled' => (bool) ($overrides[ $blog_id ]['enabled'] ?? false),
                                    'roles'   => self::sanitize_roles((array) ($overrides[ $blog_id ]['roles'] ?? [])),
                                ]
                                : null;
                            $site_enabled = $site_access !== null ? $site_access['enabled'] : $default['enabled'];
                            $site_roles   = $site_access !== null ? $site_access['roles'] : $default['roles'];
                            ?>
							<tr>
								<td>
									<?php
                                    $blog_details = get_blog_details($blog_id);
                            $blog_name            = ($blog_details && isset($blog_details->blogname)) ? (string) $blog_details->blogname : (string) $blog_id;
                            $blog_url             = ($blog_details && isset($blog_details->siteurl)) ? (string) $blog_details->siteurl : '';
                            ?>
									<strong><?php echo esc_html($blog_name); ?></strong>
									<br><small><?php echo esc_html($blog_url); ?></small>
								</td>
								<td>
									<input
										type="checkbox"
										name="sirus_site[<?php echo esc_attr((string) $blog_id); ?>][enabled]"
										value="1"
										<?php checked($site_enabled, true); ?>
									/>
								</td>
								<td>
									<?php foreach (self::SELECTABLE_ROLES as $role) : ?>
										<label style="display:inline-block; margin-right:8px;">
											<input
												type="checkbox"
												name="sirus_site[<?php echo esc_attr((string) $blog_id); ?>][roles][]"
												value="<?php echo esc_attr($role); ?>"
												<?php checked(in_array($role, $site_roles, true), true); ?>
											/>
											<?php echo esc_html(ucfirst($role)); ?>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php submit_button(esc_html__('Save Network Settings', 'sparxstar-sirus')); ?>
			</form>
		</div>
		<?php
        echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Filters a role list to only include roles present in SELECTABLE_ROLES.
     *
     * @param string[] $roles Raw role array.
     * @return string[]
     */
    private static function sanitize_roles(array $roles): array
    {
        $valid = [];
        foreach ($roles as $role) {
            $role = sanitize_text_field((string) $role);
            if (in_array($role, self::SELECTABLE_ROLES, true)) {
                $valid[] = $role;
            }
        }
        return $valid !== [] ? $valid : [ 'administrator' ];
    }
}
