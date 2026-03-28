# SirusImpactScorer

**Namespace:** `Starisian\Sparxstar\Sirus\helpers`

**Full Class Name:** `Starisian\Sparxstar\Sirus\helpers\SirusImpactScorer`

## Description

SirusImpactScorer - Computes numeric impact scores for Sirus events.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Computes integer impact scores and maps them to severity labels.

## Methods

### `score(array $event, array $context = [])`

SirusImpactScorer - Computes numeric impact scores for Sirus events.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Computes integer impact scores and maps them to severity labels.
/
final class SirusImpactScorer
{
    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    private const CHECKOUT_WEIGHT = 10;

    private const SLOW_NETWORK_WEIGHT = 5;

    private const SLOW_NETWORKS = [ 'slow-2g', '2g', 'slow-3g' ];

    /**
Computes an integer impact score for an event + optional cluster context.
Formula:
  impact_score = (error_count * affected_sessions)
               + (CHECKOUT_WEIGHT * 10 if url contains /checkout)
               + (SLOW_NETWORK_WEIGHT * 5 if network is slow)
For single events (no cluster data), error_count = 1, affected_sessions = 1.
@param array<string, mixed> $event Event data.
@param array<string, mixed> $context Optional aggregated cluster context.

### `severityFromScore(int $score)`

Maps a numeric score to a severity label.
0–9 = low, 10–24 = medium, 25–49 = high, 50+ = critical

### `extractNetwork(array $event)`

Extracts network from the event, checking denormalized column then context_json.
@param array<string, mixed> $event

