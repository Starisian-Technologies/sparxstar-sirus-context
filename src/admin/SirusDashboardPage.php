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
use Starisian\Sparxstar\Sirus\helpers\SirusRuleConfig;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

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
     * @param SirusEventRepository        $repository  Events DAL.
     * @param SirusPriorityScorer         $scorer      Priority scoring helper.
     * @param SirusRuleHitRepository      $ruleHitRepo Rule hits DAL.
     * @param SirusMitigationCoordinator  $coordinator Mitigation coordinator.
     */
    public function __construct(
        private readonly SirusEventRepository $repository,
        private readonly SirusPriorityScorer $scorer,
        private readonly SirusRuleHitRepository $ruleHitRepo,
        private readonly SirusMitigationCoordinator $coordinator,
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
        $top_urls        = $this->repository->getTopFailingUrls($error_since, self::TOP_URLS_LIMIT);
        $scored_urls     = $this->scorer->scoreRows($top_urls);

        $errors_by_device  = $this->repository->getErrorCountsByColumn('device_type', $error_since);
        $errors_by_browser = $this->repository->getErrorCountsByColumn('browser', $error_since);

        $recent_rule_hits    = $this->ruleHitRepo->getRecentHits(10);
        $slow_network_count  = $this->repository->getSlowNetworkErrorCount($error_since);
        $mobile_error_count  = $this->repository->getMobileErrorCount($error_since);

        $mitigation_enabled = (bool) get_option(\Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator::KILL_SWITCH_OPTION, true);
        if (defined('SIRUS_DISABLE_MITIGATION') && (bool) constant('SIRUS_DISABLE_MITIGATION')) {
            $mitigation_enabled = false;
        }

        ob_start();
        ?>
        <div class="wrap sirus-dashboard">
            <h1><?php esc_html_e('Sirus Observability Dashboard', 'sparxstar-sirus'); ?></h1>

            <div class="sirus-panels" style="display:flex; gap:20px; flex-wrap:wrap; margin-top:20px;">

                <!-- Panel 0: Mitigation Status -->
                <div class="sirus-panel" style="flex:1; min-width:280px; background:#fff; border:1px solid #ddd; border-radius:4px; padding:16px;">
                    <h2 style="margin-top:0; font-size:1.1em;">
                        <?php esc_html_e('Mitigation Status', 'sparxstar-sirus'); ?>
                    </h2>
                    <?php if (! $mitigation_enabled) : ?>
                        <p style="color:#d63638; font-weight:bold;">
                            <?php esc_html_e('⚠ Mitigation Disabled', 'sparxstar-sirus'); ?>
                        </p>
                        <p class="description">
                            <?php esc_html_e('Enable via sirus_mitigation_enabled option or remove SIRUS_DISABLE_MITIGATION constant.', 'sparxstar-sirus'); ?>
                        </p>
                    <?php elseif ($recent_rule_hits !== []) : ?>
                        <?php
                        $latest   = $recent_rule_hits[0];
                        // Derive mode from SirusRuleConfig — single source of truth.
                        $rule_mode_map = [];
                        foreach (SirusRuleConfig::getRules() as $rule) {
                            $rule_mode_map[(string) $rule['rule_key']] = (string) $rule['mode'];
                        }
                        $active_mode = $rule_mode_map[(string) ($latest['rule_key'] ?? '')] ?? 'normal';
                        ?>
                        <table class="widefat" style="margin-bottom:0;">
                            <tbody>
                                <tr>
                                    <th style="width:45%;"><?php esc_html_e('Active Mode', 'sparxstar-sirus'); ?></th>
                                    <td><strong><?php echo esc_html($active_mode); ?></strong></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Reason', 'sparxstar-sirus'); ?></th>
                                    <td><?php echo esc_html((string) ($latest['rule_key'] ?? '')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Severity', 'sparxstar-sirus'); ?></th>
                                    <td><?php echo esc_html((string) ($latest['severity'] ?? '')); ?></td>
                                </tr>
                                <tr>
                                    <th><?php esc_html_e('Last Triggered', 'sparxstar-sirus'); ?></th>
                                    <td><?php echo esc_html(wp_date('H:i', (int) ($latest['created_at'] ?? 0))); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p style="color:#46b450; font-weight:bold;">
                            <?php esc_html_e('✓ Normal — no active mitigation.', 'sparxstar-sirus'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Panel 1: Critical Rule Hits -->
                <div class="sirus-panel" style="flex:2; min-width:300px; background:#fff; border:1px solid #ddd; border-radius:4px; padding:16px;">
                    <h2 style="margin-top:0; font-size:1.1em;">
                        <?php esc_html_e('Critical Rule Hits', 'sparxstar-sirus'); ?>
                    </h2>
                    <?php if ($recent_rule_hits === []) : ?>
                        <p><?php esc_html_e('No rule hits recorded.', 'sparxstar-sirus'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Rule', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Severity', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Action', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Device', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Session', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Hit Count', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('First Seen', 'sparxstar-sirus'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_rule_hits as $hit) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html((string) ($hit['rule_key'] ?? '')); ?></code></td>
                                        <td><?php echo esc_html((string) ($hit['severity'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($hit['action_key'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($hit['device_id'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($hit['session_id'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($hit['hit_count'] ?? 0)); ?></td>
                                        <td><?php echo esc_html(wp_date('Y-m-d H:i', (int) ($hit['created_at'] ?? 0))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Panel 2: Recommended Actions -->
                <div class="sirus-panel" style="flex:2; min-width:300px; background:#fff; border:1px solid #ddd; border-radius:4px; padding:16px;">
                    <h2 style="margin-top:0; font-size:1.1em;">
                        <?php esc_html_e('Recommended Actions', 'sparxstar-sirus'); ?>
                    </h2>
                    <?php
                    $action_hits = $this->ruleHitRepo->getRecentHits(5);
                    if ($action_hits === []) :
                    ?>
                        <p><?php esc_html_e('No active recommendations.', 'sparxstar-sirus'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Rule Key', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Action Key', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Severity', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Status', 'sparxstar-sirus'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($action_hits as $hit) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html((string) ($hit['rule_key'] ?? '')); ?></code></td>
                                        <td><?php echo esc_html((string) ($hit['action_key'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($hit['severity'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($hit['status'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Panel 3: Environmental Friction -->
                <div class="sirus-panel" style="flex:2; min-width:300px; background:#fff; border:1px solid #ddd; border-radius:4px; padding:16px;">
                    <h2 style="margin-top:0; font-size:1.1em;">
                        <?php esc_html_e('Environmental Friction', 'sparxstar-sirus'); ?>
                    </h2>
                    <table class="widefat striped" style="margin-bottom:12px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Signal', 'sparxstar-sirus'); ?></th>
                                <th><?php esc_html_e('Error Count', 'sparxstar-sirus'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php esc_html_e('Slow Network Failures', 'sparxstar-sirus'); ?></td>
                                <td><?php echo esc_html((string) $slow_network_count); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Mobile-Only Failures', 'sparxstar-sirus'); ?></td>
                                <td><?php echo esc_html((string) $mobile_error_count); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php if ($errors_by_browser !== []) : ?>
                        <h3 style="font-size:0.95em;"><?php esc_html_e('Browser-Specific Failures', 'sparxstar-sirus'); ?></h3>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Browser', 'sparxstar-sirus'); ?></th>
                                    <th><?php esc_html_e('Errors', 'sparxstar-sirus'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($errors_by_browser as $browser => $count) : ?>
                                    <tr>
                                        <td><?php echo esc_html($browser); ?></td>
                                        <td><?php echo esc_html((string) $count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

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
}
