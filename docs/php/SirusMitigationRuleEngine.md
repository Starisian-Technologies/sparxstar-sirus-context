# SirusMitigationRuleEngine

**Namespace:** `Starisian\Sparxstar\Sirus\helpers`

**Full Class Name:** `Starisian\Sparxstar\Sirus\helpers\SirusMitigationRuleEngine`

## Description

SirusMitigationRuleEngine - Evaluates signals against configured rules.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Matches detected signals against the configured rule set.
Returns only rules that match. No DB writes here.

## Methods

### `evaluate(array $signals)`

SirusMitigationRuleEngine - Evaluates signals against configured rules.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Matches detected signals against the configured rule set.
Returns only rules that match. No DB writes here.
/
final class SirusMitigationRuleEngine
{
    /**
Evaluates signals and returns the single highest-priority matching rule, or null.
@param string[] $signals Array of signal keys from SirusSignalEvaluator.
@return array<string, mixed>|null The winning rule array, or null if no match.

