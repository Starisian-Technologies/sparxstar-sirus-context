# SirusSignalEvaluator

**Namespace:** `Starisian\Sparxstar\Sirus\helpers`

**Full Class Name:** `Starisian\Sparxstar\Sirus\helpers\SirusSignalEvaluator`

## Description

SirusSignalEvaluator - Transforms raw event data into normalized signal keys.
Pure function class — no persistence, no side effects.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Detects observability signals from a single sanitized event array.

## Methods

### `detectSignals(array $event)`

SirusSignalEvaluator - Transforms raw event data into normalized signal keys.
Pure function class — no persistence, no side effects.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Detects observability signals from a single sanitized event array.
/
final class SirusSignalEvaluator
{
    public const SIGNAL_REPEATED_JS_ERROR = 'repeated_js_error';

    public const SIGNAL_CHECKOUT_FAILURE = 'checkout_failure';

    public const SIGNAL_SLOW_NETWORK_ERROR = 'slow_network_high_error_rate';

    public const SIGNAL_SAFARI_FEATURE_BREAK = 'safari_feature_break';

    public const SIGNAL_UNSTABLE_SESSION = 'unstable_device_session';

    private const SLOW_NETWORKS = [ 'slow-2g', '2g', 'slow-3g' ];

    /**
Transforms a single raw event array into normalized signal keys.
@param array<string, mixed> $event Sanitized event row.
@return string[] Deduplicated array of signal keys detected from this event.

### `extractBrowser(array $event)`

Extracts the browser value from the event, with context_json fallback.
@param array<string, mixed> $event

### `extractNetwork(array $event)`

Extracts the network value from the event, with context_json fallback.
@param array<string, mixed> $event

### `decodeContextJson(array $event)`

Decodes the context_json field if present.
@param array<string, mixed> $event
@return array<string, mixed>

