# IdentityResolver

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\IdentityResolver`

## Methods

### `__construct(private ?HeliosClient $helios_client = null)`

IdentityResolver - Resolves a trust level for the current SirusContext.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

use Starisian\Sparxstar\Sirus\integrations\HeliosClient;

/**
Determines the trust level for a resolved SirusContext by inspecting
device presence, WordPress authentication, capabilities, and optionally
an external Helios trust resolution service.
Trust levels (ascending): anonymous → device → contributor → user → authority
/
final readonly class IdentityResolver
{
    /** @var array<string, int> Numeric weight for each trust level (for comparison). */
    private const TRUST_WEIGHTS = [
        'anonymous'   => 0,
        'device'      => 1,
        'contributor' => 2,
        'user'        => 3,
        'authority'   => 4,
    ];

    /**
@param HeliosClient|null $helios_client Optional Helios integration for external trust resolution.

### `resolve(SirusContext $context)`

Resolves and returns the highest applicable trust level string for the context.
@param SirusContext $context The context being evaluated.
@return string One of: anonymous, device, contributor, user, authority.

### `escalate(string $current, string $candidate)`

Returns the higher trust level of the two provided values.
Ignores unknown levels (treats them as 'anonymous') and logs a warning.
@param string $current The currently resolved trust level.
@param string $candidate The candidate trust level to compare.

