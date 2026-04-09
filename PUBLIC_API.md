# Sirus — Public API Surface

> This document is the authoritative list of every interface element from this repository that is consumed or depended upon by other Starisian platform repositories (Helios, Dheghom, Sky, Mehns, Ouroboros, and site-level plugins).
>
> **Keep this file current.** If you add a public constant, filter, hook, REST endpoint, or class, register it here.

---

## Frozen Contracts (never change without a platform-wide migration)

### `StarUserEnv` Facade — `src/StarUserEnv.php`

All six methods are **frozen**. Their signatures MUST NOT change. These are the only public interface from the old `sparxstar-user-environment-check` plugin and are consumed by every site-level plugin, theme, and integration that referenced UEC.

```php
namespace Starisian\SparxstarUEC;

StarUserEnv::get_browser_name(): string
StarUserEnv::get_os(): string
StarUserEnv::get_device_type(): string
StarUserEnv::get_network_effective_type(): string
StarUserEnv::get_ip_address(): string
StarUserEnv::get_location(): array
```

---

## Primary Entry Points

### `ContextEngine` — `src/core/ContextEngine.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

ContextEngine::current(): SirusContext     // Throws ContextBootException on failure. Never null.
ContextEngine::buildFromDevice(DeviceRecord $device): SirusContext
ContextEngine::build(): SirusContext
```

**Contract:**
- `current()` is the primary accessor. All downstream code calls this.
- Throws `ContextBootException` (never returns null, never returns partial).
- CLI SAPI path: returns fixed system context (`SYSTEM`/`GLOBAL`/`CLI`).
- Caches via `ContextCache` for the duration of the request.

---

### `SirusContext` DTO — `src/core/SirusContext.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

// Constructor (all readonly):
new SirusContext(
    string  $context_id,
    string  $environment_id,
    string  $network_id,
    string  $site_id,
    string  $device_id,
    string  $session_id,
    ?string $identity_id,
    ?string $authority_id,
    array   $role_set,
    array   $capabilities,
    string  $trust_level,
    float   $trust_score,     // [0.0, 1.0] — from TrustEngine / TrustResolver
    int     $issued_at,
    int     $expires,
)

// Portable payload (for cross-domain handoff):
SirusContext::toPortablePayload(): array  // Keys: ctx, env, net, site, dev, auth, caps, tl, ts, iat, exp
```

**Portable payload field map:**

| Key | Property | Type |
|---|---|---|
| `ctx` | `context_id` | string |
| `env` | `environment_id` | string |
| `net` | `network_id` | string |
| `site` | `site_id` | string |
| `dev` | `device_id` | string |
| `auth` | `authority_id` | string\|null |
| `caps` | `capabilities` | string[] |
| `tl` | `trust_level` | string |
| `ts` | `trust_score` | float (4 d.p.) |
| `iat` | `issued_at` | int (Unix) |
| `exp` | `expires` | int (Unix) |

> **Note:** `identity_id` is NOT included in the portable payload. It must be resolved independently by each receiving service.

---

## Core Services

### `TrustEngine` — `src/core/TrustEngine.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

TrustEngine::compute(array $signals): array  // returns {trust_score: float, trust_level: string}
TrustEngine::scoreToLevel(float $score): string
```

**Frozen algorithm (MUST NOT change without spec update):**

| Signal key | Deduction |
|---|---|
| `device_drifting` (bool) | −0.3 |
| `geo_mismatch` (bool) | −0.2 |
| `new_session` (bool) | −0.1 |
| `recent_failures` (bool) | −0.3 |

Base = 1.0. Result clamped to [0.0, 1.0].

**Level mapping (frozen):**

| Score | Level |
|---|---|
| ≥ 0.7 | `NORMAL` |
| > 0.0 | `ELEVATED` |
| = 0.0 | `CRITICAL` |

**Public constants (consumed by TrustResolver):**

```php
TrustEngine::DEDUCTION_DEVICE_DRIFTING  // 0.3
TrustEngine::DEDUCTION_GEO_MISMATCH     // 0.2
TrustEngine::DEDUCTION_NEW_SESSION      // 0.1
TrustEngine::DEDUCTION_RECENT_FAILURES  // 0.3
TrustEngine::LEVEL_NORMAL               // 'NORMAL'
TrustEngine::LEVEL_ELEVATED             // 'ELEVATED'
TrustEngine::LEVEL_CRITICAL             // 'CRITICAL'
```

---

### `TrustResolver` — `src/core/TrustResolver.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

TrustResolver::evaluate(DeviceRecord $device): float  // [0.0, 1.0]
```

Derives trust score from `DeviceRecord::$trust_level` as a base, then applies TrustEngine deductions for drift and new sessions. Used exclusively by `ContextEngine::buildFromDevice()`.

**Credential base scores (frozen):**

| Level | Base |
|---|---|
| `elder` | 0.95 |
| `contributor` | 0.90 |
| `user` | 0.85 |
| `device` | 0.70 |
| `anonymous` / other | 0.50 |

---

### `PulseGenerator` — `src/core/PulseGenerator.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

PulseGenerator::generate(SirusContext $context, int $now = 0): ContextPulse
```

**Requirements:**
- PHP constant `SIRUS_PULSE_SIGNING_KEY` must be defined and ≥ 32 bytes. Throws `\RuntimeException` otherwise.
- Signing algorithm: HMAC-SHA256.
- `ContextPulse` NEVER contains `identity_id`.
- TTL: 60 seconds from `$now`.

---

### `StepUpPolicy` — `src/core/StepUpPolicy.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

StepUpPolicy::requiresStepUp(SirusContext $context, int $auth_level): bool
```

**Frozen policy:**

| Level | Condition |
|---|---|
| 3 | Always requires step-up |
| 2 | Requires step-up when `trust_score < 0.7` |
| ≤ 1 | Never requires step-up |

Returns recommendation only. **Helios enforces.**

---

### `ConsentManager` — `src/core/ConsentManager.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

// Technical consent (three-level cascade: user meta → site option → STATE_DENIED)
ConsentManager::getTechnicalConsent(int $user_id): string          // STATE_GRANTED | STATE_DENIED
ConsentManager::setTechnicalConsent(int $user_id, string $state): bool

// Site authority default (set by site admin)
ConsentManager::getSiteConsentDefault(int $blog_id = 0): string
ConsentManager::setSiteConsentDefault(string $state, int $blog_id = 0): bool

// Purpose-level consent
ConsentManager::getPurposeConsent(int $user_id): array             // purpose_key → STATE_*
ConsentManager::setPurposeConsent(int $user_id, string $purpose_key, string $state): bool

// Append-only history
ConsentManager::getHistory(int $user_id): array
```

**State constants:**

```php
ConsentManager::STATE_GRANTED  // 'granted'
ConsentManager::STATE_DENIED   // 'denied'
ConsentManager::STATE_PENDING  // 'pending'
```

**Cascade order for `getTechnicalConsent()`:**
1. Individual user meta (`sirus_technical_consent`) — highest priority
2. Site option (`sirus_technical_consent_default`) — authority default
3. System hard default: `STATE_DENIED` — privacy-first

---

### `NetworkContextBroker` — `src/core/NetworkContextBroker.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

NetworkContextBroker::issueToken(SirusContext $context): string          // base64url-encoded signed token
NetworkContextBroker::verifyToken(string $token, string $secret): ?SirusContext
```

**Token payload field map:** Same as `SirusContext::toPortablePayload()` above (minus `identity_id`). `ts` field added in v1.0 — absent `ts` is derived from `tl` for backward compatibility.

---

### `DeviceContinuity` — `src/core/DeviceContinuity.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

DeviceContinuity::getDeviceContext(string $fingerprint_hash): DeviceRecord
```

**Contract:**
- `device_id` is ALWAYS server-issued. JS fingerprint is an input to `fingerprint_hash`, not a device identifier.
- Throws `\RuntimeException` if `fingerprint_hash` is empty.

---

### `IdentityResolver` — `src/core/IdentityResolver.php`

```php
namespace Starisian\Sparxstar\Sirus\core;

IdentityResolver::resolve(): array  // Never null. Returns FALLBACK_IDENTITY on Helios failure.
```

Returns: `{identity_id: string|null, verification_status: string, authority_memberships: string[], capabilities: string[]}`

---

## REST API Endpoints

### `sirus/v1` namespace

| Method | Route | Controller | Auth |
|---|---|---|---|
| `POST` | `/wp-json/sirus/v1/event` | `SirusEventController` | WP nonce required (`X-WP-Nonce` or `?_wpnonce`) |
| `GET` | `/wp-json/sirus/v1/directives` | `SirusDirectiveController` | WP nonce required |
| `GET` | `/wp-json/sirus/v1/directives/{device_id}` | `SirusDirectiveController` | WP nonce required |

### `sparxstar/v1` namespace (legacy UEC compat)

| Method | Route | Controller | Auth |
|---|---|---|---|
| `POST` | `/wp-json/sparxstar/v1/context` | `SirusRESTController` | WP nonce required |
| `GET` | `/wp-json/sparxstar/v1/context` | `SirusRESTController` | WP nonce required |

---

## WordPress Filters

All stable filters. Do not remove.

| Filter | Default | Where it is applied |
|---|---|---|
| `sparxstar_sirus_device_ttl_days` | `90` | `DeviceRecord` — device record TTL in days |
| `sparxstar_env_retention_days` | `30` | `SirusEventRepository` — event log retention |
| `sparxstar_sirus_capabilities` | `[]` | `CapabilityEngine` — capability set for a context |
| `sparxstar_env_geolocation_lookup` | `null` | `EnvironmentResolver` / `StarUserEnv` — custom geolocation provider |
| `sparxstar_env_geolocation_ttl` | `DAY_IN_SECONDS` | GeoIP service — geolocation cache duration |
| `sparxstar_env_network_effective_type` | `'unknown'` | `EnvironmentResolver` — override network type |

---

## WordPress Options (site-level)

| Option key | Owner | Purpose |
|---|---|---|
| `sirus_technical_consent_default` | `ConsentManager` | Site authority default for technical consent |
| `sirus_mitigation_enabled` | `SirusMitigationCoordinator` | Kill switch for mitigation system |

---

## Provisional Types (temporary — replace with Ouroboros imports)

| Class | Location | Notes |
|---|---|---|
| `ContextBootException` | `src/exceptions/ContextBootException.php` | Mirror of Ouroboros type. Remove when Ouroboros ships. |
| `ContextPulse` | `src/dto/ContextPulse.php` | Mirror of Ouroboros type. Remove when Ouroboros ships. |

---

## PHP Constants

Required at plugin load time:

| Constant | Required | Purpose |
|---|---|---|
| `SIRUS_PULSE_SIGNING_KEY` | Only when `PulseGenerator` is called | HMAC-SHA256 signing key. Minimum 32 bytes. |
| `ABSPATH` | Always | WordPress bootstrap guard. |
| `SIRUS_VERSION` | Auto-defined by entry point | Plugin version string. |
| `SIRUS_PLUGIN_PATH` | Auto-defined by entry point | Absolute path to plugin root. |

---

## What Sirus Does NOT Export

Do not expect these from this repository:

- Agreement evaluation (proceed/deny) → **Helios**
- KV-store revocation → **Helios**
- JWT issuance → **Helios**
- Governance policy evaluation → **Mehns**
- Structured field persistence → **Dheghom**
- Pulse **verification** — Sirus **generates**; Helios **verifies**
- `wp_set_auth_cookie()` calls → prohibited in this repo

---

*Last updated: 2026-04-09 | Spec version: Sirus Context Engine Spec v3.0*
