# SirusRuleConfig

**Namespace:** `Starisian\Sparxstar\Sirus\helpers`

**Full Class Name:** `Starisian\Sparxstar\Sirus\helpers\SirusRuleConfig`

## Description

SirusRuleConfig - Hard-coded starter mitigation rules.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Provides the static rule set used by SirusMitigationRuleEngine.
Each rule maps a signal key to an action and response mode.

## Methods

### `getRules()`

SirusRuleConfig - Hard-coded starter mitigation rules.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
Provides the static rule set used by SirusMitigationRuleEngine.
Each rule maps a signal key to an action and response mode.
/
final class SirusRuleConfig
{
    /**
Returns all configured mitigation rules.
@return array<int, array<string, mixed>>

