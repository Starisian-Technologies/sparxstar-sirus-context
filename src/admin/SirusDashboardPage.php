<?php

/**
 * SirusDashboardPage - Sirus Observability admin dashboard for site admins.
 *
 * Displays:
 *   A. Active Users — count of sessions active in the last 15 minutes.
 *   B. Errors (last hour) — grouped by device type and browser.
 *   C. Top Failing URLs — with impact score and priority.
 *
 * Access is gated by SirusNetworkSettingsPage::userCanViewOnSite().
 * Super-admins always have access.
 *
 * @package Starisian\Sparxstar\Sirus
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\admin;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusPriorityScorer;

/**
 * Registers and renders the site-level Sirus Observability dashboard.
 */
final class SirusDashboardPage
{
    private const MENU_SLUG      = 'sirus-dashboard';
    private const ACTIVE_WINDOW  = 900;  // 15 minutes in seconds.
    private const ERROR_WINDOW   = 3600; // 1 hour in seconds.
    private const TOP_URLS_LIMIT = 10;

    /**
     * @param SirusEventRepository $repository Events DAL.
     * @param SirusPriorityScorer  $scorer     Priority scoring helper.
     */
    public function __construct(
        private readonly SirusEventRepository $repository,
        private readonly SirusPriorityScorer $scorer,
    ) {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    /**
     * Adds the Sirus entry to the site admin menu.
     */
    public function add_admin_menu(): void
    {
        $user_id = get_current_user_id();
        $blog_id = get_current_blog_id();

        if (! SirusNetworkSettingsPage::userCanViewOnSite($user_id, $blog_id)) {
            return;
        }

        add_menu_page(
            esc_html__('Sirus Observability', 'sparxstar-sirus'),
            esc_html__('Sirus', 'sparxstar-sirus'),
            'read',
            self::MENU_SLUG,
            [$this, 'render'],
            'dashicons-chart-line',
            30
        );
    }

    /**
     * Renders the dashboard page HTML.
     */
    public function render(): void
    {
        $user_id = get_current_user_id();
        $blog_id = get_current_blog_id();

        if (! SirusNetworkSettingsPage::userCanViewOnSite($user_id, $blog_id)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'sparxstar-sirus'), '', ['response' => 403]);
        }

        $now         = time();
        $error_since = $now - self::ERROR_WINDOW;

        $active_sessions = $this->repository->getActiveSessions(self::ACTIVE_WINDOW);
        $recent_errors   = $this->repository->getRecentErrors($error_since);
        $top_urls        = $this->repository->getTopFailingUrls($error_since, self::TOP_URLS_LIMIT);
        $scored_urls     = $this->scorer->scoreRows($top_urls);

        $errors_by_device  = $this->group_errors_by_context($recent_errors, 'device_type');
        $errors_by_browser = $this->group_errors_by_context($recent_errors, 'browser');

        ob_start();
        ?>
        <div class="wrap sirus-dashboard">
            <h1><?php esc_html_e('Sirus Observability Dashboard', 'sparxstar-sirus'); ?></h1>

            <div class="sirus-panels" style="display:flex; gap:20px; flex-wrap:wrap; margin-top:20px;">

                <!-- Panel A: Active Users -->
                <div class="sirus-panel" style="flex:1; min-width:220px; background:#fff; border:1px solid #ddd; border-radius:4px; padding:16px;">
                    <h2 style="margin-top:0; font-size:1.1em;">
                        <?php esc_html_e('Active Users', 'sparxstar-sirus'); ?>
                    </h2>
                    <p style="font-size:2em; font-weight:bold; margin:8px 0;">
                        <?php echo esc_html((string) $active_sessions); ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Sessions active in the last 15 minutes.', 'sparxstar-sirus'); ?>
                    </p>
                </div>

                <!-- Panel B: Errors by Device -->
                <div class="sirus-panel" style="flex:2; min-width:300px; background:#fff; border:1px solid #ddd; border-radius:4px; padding:16px;">
                    <h2 style="margin-top:0; font-size:1.1em;">
                        <?php esc_html_e('Errors (Last Hour)', 'sparxstar-sirus'); ?>
                    </h2>

                    <?php if ($errors_by_device === []) : ?>
                        <p><?php esc_html_e('No errors recorded in the last hour.', 'sparxstar-sirus'); ?></p>
                    <?php else : ?>
                        <h3 style="font-size:0.95em;"><?php esc_html_e('By Device Type', 'sparxstar-sirus'); ?></h3>
                        <table class="widefat striped" style="margin-bottom:12px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Device', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Errors', 'sparxstar-sirus'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($errors_by_device as $label => $count) : ?>
                                    <tr>
                                        <td><?php echo esc_html($label); ?></td>
                                        <td><?php echo esc_html((string) $count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <h3 style="font-size:0.95em;"><?php esc_html_e('By Browser', 'sparxstar-sirus'); ?></h3>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Browser', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Errors', 'sparxstar-sirus'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($errors_by_browser as $label => $count) : ?>
                                    <tr>
                                        <td><?php echo esc_html($label); ?></td>
                                        <td><?php echo esc_html((string) $count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Panel C: Top Failing URLs -->
                <div class="sirus-panel" style="flex:2; min-width:300px; background:#fff; border:1px solid #ddd; border-radius:4px; padding:16px;">
                    <h2 style="margin-top:0; font-size:1.1em;">
                        <?php esc_html_e('Top Failing URLs', 'sparxstar-sirus'); ?>
                    </h2>

                    <?php if ($scored_urls === []) : ?>
                        <p><?php esc_html_e('No failing URLs recorded in the last hour.', 'sparxstar-sirus'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('URL', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Errors', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Sessions', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Priority', 'sparxstar-sirus'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scored_urls as $row) :
                                    $priority      = (string) ($row['priority'] ?? SirusPriorityScorer::PRIORITY_LOW);
                                    $badge_color   = match ($priority) {
                                        SirusPriorityScorer::PRIORITY_HIGH   => '#d63638',
                                        SirusPriorityScorer::PRIORITY_MEDIUM => '#dba617',
                                        default                               => '#1d2327',
                                    };
                                    ?>
                                    <tr>
                                        <td><code><?php echo esc_html((string) ($row['url'] ?? '')); ?></code></td>
                                        <td><?php echo esc_html((string) ($row['error_count'] ?? 0)); ?></td>
                                        <td><?php echo esc_html((string) ($row['affected_sessions'] ?? 0)); ?></td>
                                        <td>
                                            <span style="color:<?php echo esc_attr($badge_color); ?>; font-weight:bold;">
                                                <?php echo esc_html($priority); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php
        echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Groups recent error rows by a context JSON field value.
     *
     * @param array<int, array<string, mixed>> $errors       Rows from SirusEventRepository::getRecentErrors.
     * @param string                           $context_key  Key within context_json (e.g. 'browser', 'device_type').
     * @return array<string, int>
     */
    private function group_errors_by_context(array $errors, string $context_key): array
    {
        $groups = [];

        foreach ($errors as $row) {
            $context_json = (string) ($row['context_json'] ?? '{}');
            $context      = json_decode($context_json, true);
            $context      = is_array($context) ? $context : [];
            $label        = (string) ($context[$context_key] ?? 'Unknown');

            $groups[$label] = ($groups[$label] ?? 0) + 1;
        }

        arsort($groups);

        return $groups;
    }
}
