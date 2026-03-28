# SirusMitigationCoordinator

**Namespace:** `Starisian\Sparxstar\Sirus\services`

**Full Class Name:** `Starisian\Sparxstar\Sirus\services\SirusMitigationCoordinator`

## Methods

### `processEvent(array $event)`

SirusMitigationCoordinator - Full processing pipeline for Sirus adaptive responses.
Orchestrates: signal detection → rule evaluation → scoring → persistence.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\services;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\helpers\SirusRuleConfig;
use Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer;
use Starisian\Sparxstar\Sirus\core\SirusRuleHitRepository;
use Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator;
use Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine;
use Starisian\Sparxstar\Sirus\core\SirusMitigationActionRepository;

/**
Coordinates the full adaptive-response pipeline for a single event.
/
final readonly class SirusMitigationCoordinator
{
    /** Response mode priority (higher index = higher priority). */
    private const MODE_PRIORITY = [ 'normal', 'lite', 'degraded' ];

    /** Minimum confidence score to fire a directive. */
    public const MIN_CONFIDENCE = 0.6;

    /** Minimum event samples required before firing a 'degraded' directive. */
    public const MIN_SAMPLE_FOR_DEGRADED = 3;

    /** Default directive TTL in seconds if rule doesn't specify. */
    public const DEFAULT_TTL = 300;

    /** Transient key prefix for cached active directives. */
    private const DIRECTIVE_CACHE_PREFIX = 'sirus_dir_';

    /** Kill switch option name. */
    public const KILL_SWITCH_OPTION = 'sirus_mitigation_enabled';

    public function __construct(
        private SirusSignalEvaluator $evaluator,
        private SirusImpactScorer $scorer,
        private SirusMitigationRuleEngine $ruleEngine,
        private SirusRuleHitRepository $ruleHitRepo,
        private SirusMitigationActionRepository $actionRepo,
    ) {
    }

    /**
Full processing pipeline: signals → rules → score → store hit → store action → invalidate cache.
@param array<string, mixed> $event Persisted event data.

### `getDirective(string $deviceId, string $sessionId = '')`

Returns the single active directive for a device, or null.
Pipeline:
1. Kill switch → null
2. Transient cache → return cached (TTL-enforcement prevents oscillation)
3. Fetch active actions from DB → pick highest-priority mode
4. Confidence gate → null if below MIN_CONFIDENCE
5. Sample gate → null if degraded but insufficient traffic sample
6. Cache + return directive
@return array{mode: string, ttl: int, reason: string, confidence: float}|null

### `getResponseMode(string $deviceId, string $sessionId = '')`

@deprecated Use getDirective() for the locked single-directive contract.

### `getClientDirectives(string $deviceId, string $sessionId = '')`

@deprecated Use getDirective() for the locked single-directive contract.
@return array{response_mode: string, actions: string[], flags: array<string, bool>}

### `isMitigationEnabled()`

Checks the global kill switch.

### `normalizeMode(string $mode)`

Maps legacy/extended mode names to the locked 3-mode contract.

### `getConfidenceForActionKey(string $actionKey)`

Looks up confidence for an action_key in the rule config.

