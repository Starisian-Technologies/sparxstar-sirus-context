# UECCompatibilityShim

**Namespace:** `Starisian\Sparxstar\Sirus\integrations`

**Full Class Name:** `Starisian\Sparxstar\Sirus\integrations\UECCompatibilityShim`

## Description

UECCompatibilityShim - Registers backward-compatibility aliases for the old namespace.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\integrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
Ensures that code referencing the old Starisian\SparxstarUEC namespace continues
to work after the Sirus context engine is introduced.
StarUserEnv.php stays in the old namespace and delegates to ContextEngine.

## Methods

### `register()`

UECCompatibilityShim - Registers backward-compatibility aliases for the old namespace.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\integrations;

if (! defined('ABSPATH')) {
    exit;
}

/**
Ensures that code referencing the old Starisian\SparxstarUEC namespace continues
to work after the Sirus context engine is introduced.
StarUserEnv.php stays in the old namespace and delegates to ContextEngine.
/
final class UECCompatibilityShim
{
    /**
Registers any necessary class aliases for backward compatibility.
Should be called early in the plugins_loaded action.

