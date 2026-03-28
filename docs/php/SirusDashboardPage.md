# SirusDashboardPage

**Namespace:** `Starisian\Sparxstar\Sirus\admin`

**Full Class Name:** `Starisian\Sparxstar\Sirus\admin\SirusDashboardPage`

## Description

SirusDashboardPage - Sirus Observability admin dashboard for site admins.
Displays:
  A. Active Users — count of sessions active in the last 15 minutes.
  B. Errors (last hour) — grouped by device type and browser.
  C. Top Failing URLs — with impact score and priority.
Access is gated by SirusNetworkSettingsPage::userCanViewOnSite().
Super-admins always have access.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\admin;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\helpers\SirusRuleConfig;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusPriorityScorer;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

/**
Registers and renders the site-level Sirus Observability dashboard.

## Methods

### `add_admin_menu()`

SirusDashboardPage - Sirus Observability admin dashboard for site admins.
Displays:
  A. Active Users — count of sessions active in the last 15 minutes.
  B. Errors (last hour) — grouped by device type and browser.
  C. Top Failing URLs — with impact score and priority.
Access is gated by SirusNetworkSettingsPage::userCanViewOnSite().
Super-admins always have access.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\admin;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\helpers\SirusRuleConfig;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusPriorityScorer;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;

/**
Registers and renders the site-level Sirus Observability dashboard.
/
final class SirusDashboardPage
{
    private const MENU_SLUG      = 'sirus-dashboard';
    private const ACTIVE_WINDOW  = 900;  // 15 minutes in seconds.
    private const ERROR_WINDOW   = 3600; // 1 hour in seconds.
    private const TOP_URLS_LIMIT = 10;

    /**
@param SirusEventRepository $repository Events DAL.
@param SirusPriorityScorer $scorer Priority scoring helper.
@param SirusRuleHitRepository $ruleHitRepo Rule hits DAL.
@param SirusMitigationCoordinator $coordinator Mitigation coordinator.
/
    public function __construct(
        private readonly SirusEventRepository $repository,
        private readonly SirusPriorityScorer $scorer,
        private readonly SirusRuleHitRepository $ruleHitRepo,
        private readonly SirusMitigationCoordinator $coordinator,
    ) {
        add_action('admin_menu', [ $this, 'add_admin_menu' ]);
    }

    /**
Adds the Sirus entry to the site admin menu.

### `render()`

Renders the dashboard page HTML.

