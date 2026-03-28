# CapabilityEngine

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\CapabilityEngine`

## Description

CapabilityEngine - Resolves the capability set for a given SirusContext.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Maps trust levels to a canonical set of capability strings.
Results are filterable via sparxstar_sirus_capabilities.

## Methods

### `resolve(SirusContext $context)`

CapabilityEngine - Resolves the capability set for a given SirusContext.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Maps trust levels to a canonical set of capability strings.
Results are filterable via sparxstar_sirus_capabilities.
/
final class CapabilityEngine
{
    /** @var array<string, list<string>> Capabilities granted per trust level. */
    private const BASE_CAPABILITIES = [
        'anonymous'   => [ 'read_context' ],
        'device'      => [ 'read_context', 'submit_environment' ],
        'contributor' => [ 'read_context', 'submit_environment', 'submit_content' ],
        'user'        => [ 'read_context', 'submit_environment', 'submit_content', 'read_profile' ],
        'authority'   => [
            'read_context',
            'submit_environment',
            'submit_content',
            'read_profile',
            'manage_context',
            'resolve_authority',
        ],
    ];

    /**
Returns the capability array for the context's trust level.
Applies the sparxstar_sirus_capabilities filter before returning.
@param SirusContext $context The context to resolve capabilities for.
@return list<string>

