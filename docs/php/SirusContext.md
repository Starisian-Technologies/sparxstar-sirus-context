# SirusContext

**Namespace:** `Starisian\Sparxstar\Sirus\core`

**Full Class Name:** `Starisian\Sparxstar\Sirus\core\SirusContext`

## Methods

### `isExpired()`

SirusContext - The main Context Data Transfer Object.
@package Starisian\Sparxstar\Sirus
/

declare(strict_types=1);

namespace Starisian\Sparxstar\Sirus\core;

if (! defined('ABSPATH')) {
    exit;
}

/**
Immutable value object representing the full context for a single request.
/
final readonly class SirusContext
{
    public const CONTEXT_VERSION = 1;

    /**
Constructs a new SirusContext.
@param string $context_id Unique identifier for this context instance.
@param string $environment_id Hashed identifier for the site environment.
@param string $network_id Multisite network identifier.
@param string $site_id Blog/site identifier.
@param string $device_id Device continuity identifier.
@param string $session_id Session identifier.
@param string|null $identity_id Authenticated identity, or null.
@param string|null $authority_id Resolved authority type, or null.
@param array $role_set WordPress roles associated with the context.
@param array $capabilities Resolved capability strings.
@param string $trust_level One of: anonymous, device, contributor, user, authority.
@param int $issued_at Unix timestamp when the context was issued.
@param int $expires Unix timestamp when the context expires.
/
    public function __construct(
        public string $context_id,
        public string $environment_id,
        public string $network_id,
        public string $site_id,
        public string $device_id,
        public string $session_id,
        public ?string $identity_id,
        public ?string $authority_id,
        public array $role_set,
        public array $capabilities,
        public string $trust_level,
        public int $issued_at,
        public int $expires,
    ) {
    }

    /**
Returns true if the context has passed its expiry timestamp.
An expires value of 0 is treated as "never expires" (backward-compatible).

### `hasCapability(string $capability)`

Returns true if the given capability string is in the resolved capabilities set.
@param string $capability The capability string to check.

### `hasAuthority(string $authority)`

Returns true if the resolved authority_id matches the given authority string.
@param string $authority The authority identifier to compare against.

### `toPortablePayload()`

Returns a portable payload array, deliberately excluding identity_id.
Safe to transmit cross-domain.
@return array<string, mixed>

