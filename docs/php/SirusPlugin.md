# SirusPlugin

**Namespace:** `Starisian\Sparxstar\Sirus`

**Full Class Name:** `Starisian\Sparxstar\Sirus\SirusPlugin`

## Description

SirusPlugin - Plugin bootstrap and hook registration for the Sirus Context Engine.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\core\SirusDatabase;
use Starisian\Sparxstar\Sirus\core\ClientTelemetry;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\DeviceRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusRateLimit;
use Starisian\Sparxstar\Sirus\api\SirusRESTController;
use Starisian\Sparxstar\Sirus\admin\SirusDashboardPage;
use Starisian\Sparxstar\Sirus\api\SirusEventController;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\core\SirusEventAggregator;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusPriorityScorer;
use Starisian\Sparxstar\Sirus\api\SirusDirectiveController;
use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;
use Starisian\Sparxstar\Sirus\admin\SirusNetworkSettingsPage;
use Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;
use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;

/**
Singleton orchestrator for the Sirus Context Engine plugin.
Registers all WordPress hooks and bootstraps the service layer.

## Properties

### `$instance`

SirusPlugin - Plugin bootstrap and hook registration for the Sirus Context Engine.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\core\SirusDatabase;
use Starisian\Sparxstar\Sirus\core\ClientTelemetry;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\DeviceRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusRateLimit;
use Starisian\Sparxstar\Sirus\api\SirusRESTController;
use Starisian\Sparxstar\Sirus\admin\SirusDashboardPage;
use Starisian\Sparxstar\Sirus\api\SirusEventController;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\core\SirusEventAggregator;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusPriorityScorer;
use Starisian\Sparxstar\Sirus\api\SirusDirectiveController;
use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;
use Starisian\Sparxstar\Sirus\admin\SirusNetworkSettingsPage;
use Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;
use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;

/**
Singleton orchestrator for the Sirus Context Engine plugin.
Registers all WordPress hooks and bootstraps the service layer.
/
final class SirusPlugin
{
    /** @var SirusPlugin|null

## Methods

### `getInstance()`

SirusPlugin - Plugin bootstrap and hook registration for the Sirus Context Engine.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\core\ContextEngine;
use Starisian\Sparxstar\Sirus\core\SirusDatabase;
use Starisian\Sparxstar\Sirus\core\ClientTelemetry;
use Starisian\Sparxstar\Sirus\core\DeviceContinuity;
use Starisian\Sparxstar\Sirus\core\DeviceRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusRateLimit;
use Starisian\Sparxstar\Sirus\api\SirusRESTController;
use Starisian\Sparxstar\Sirus\admin\SirusDashboardPage;
use Starisian\Sparxstar\Sirus\api\SirusEventController;
use Starisian\Sparxstar\Sirus\core\NetworkContextBroker;
use Starisian\Sparxstar\Sirus\core\SirusEventAggregator;
use Starisian\Sparxstar\Sirus\core\SirusEventRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusPriorityScorer;
use Starisian\Sparxstar\Sirus\api\SirusDirectiveController;
use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;
use Starisian\Sparxstar\Sirus\admin\SirusNetworkSettingsPage;
use Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine;
use Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator;
use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;

/**
Singleton orchestrator for the Sirus Context Engine plugin.
Registers all WordPress hooks and bootstraps the service layer.
/
final class SirusPlugin
{
    /** @var SirusPlugin|null */
    private static ?SirusPlugin $instance = null;

    /**
Returns the single SirusPlugin instance, creating it if necessary.

### `__construct()`

Private constructor – use getInstance().

### `registerHooks()`

Registers WordPress hooks used by this plugin.

### `initAdminPages()`

Initialises admin and network-admin pages.
Called on plugins_loaded — the standard WP bootstrap hook. Construction
is gated by is_admin() so admin page classes and their repository/service
dependencies are only instantiated during admin HTTP requests (including
network admin). Frontend, WP-Cron, and WP-CLI requests are excluded.
plugins_loaded fires before init, admin_menu, and network_admin_menu, so
constructor-registered hook callbacks are captured correctly. If any
downstream logic specifically requires init, it should use
add_action('init', ...) inside the relevant class.

### `registerRestRoutes()`

Instantiates and registers the Sirus REST routes.

### `enqueueAssets()`

Enqueues front-end assets and injects the current context token.
The script file is only enqueued when the asset exists on disk.

### `runTelemetryPrune()`

Runs the daily telemetry pruning job.
Removes:
  - raw client error reports older than 60 days (sparxstar_client_reports)
  - sirus_events older than the retention window (default 30 days)

### `addCronSchedules(array $schedules)`

Adds the custom 'every_5_minutes' cron schedule.
@param array<string, array<string, mixed>> $schedules Existing cron schedules.
@return array<string, array<string, mixed>>

### `runEventAggregation()`

Runs the 5-minute event aggregation compilation job.

### `onActivation()`

Activation hook: ensures all Sirus database tables exist and schedules cron.

### `onDeactivation()`

Deactivation hook: removes scheduled cron events for this plugin.

