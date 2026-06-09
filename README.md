
![SPARXSTAR Banners-17](https://github.com/user-attachments/assets/c9dca401-8c52-412e-8d26-6367a7c3913e)

# SPARXSTAR Sirus — Context Engine

> **Does this implementation fully represent the spec layer it claims to align to?**
> Yes. See [Spec Alignment](#spec-alignment) below.

Technical specifications:

- [Sirus Context Engine Spec v3.0](docs/specs/Sirus_Context_Engine_Spec_v3.0.docx.pdf)
- [Platform Integrity Map](docs/specs/Sparxstar_Platform_Integrity_Map_v1.0.docx%20(3).pdf)
- [SPARXSTAR Platform Overview](docs/specs/Sparxstar_Platform_Overview_v1.0.docx.pdf)
- [Public API Surface](PUBLIC_API.md) — all hooks, filters, REST endpoints, and classes consumed by other repos
- [Sirus API Contract](docs/contracts/sirus-api-contract.v1.json) and [seed fixture](docs/contracts/sirus-api-seed.v1.json) — machine-readable REST contract for downstream repos
- [Implementation Tracker](TRACKER.md) — sprint scoreboard and remaining work

---

## What Sirus Is

Sirus is the **context engine**. Before identity is established, before authentication runs, before any application logic executes — Sirus establishes the environment.

> Who is present. On what device. In what environment. Under what authority.

Sirus **produces context**. It does not make authorization decisions (that is Helios). It does not enforce governance (that is Mehns).

### Platform position

```
Ouroboros → Helios → Sirus → Sky → Mehns → Dheghom
```

Sirus is deployed as a WordPress **mu-plugin** — it loads first and cannot be deactivated.

---

## Spec Alignment

The table below answers whether each component in the Sirus Context Engine Spec v3.0 is implemented in this repository.

| Spec Component | File | Status |
|---|---|---|
| `ContextEngine` — `current()` accessor, CLI system context | `src/core/ContextEngine.php` | ✅ Implemented |
| `SirusContext` DTO — primary output consumed by all downstream layers | `src/core/SirusContext.php` | ✅ Implemented |
| `ContextPulse` DTO — signed pulse (never contains identity claims) | `Starisian\Sparxstar\Infrastructure\DTOs\ContextPulse` | ✅ Imported from Ouroboros |
| `PulseGenerator` — HMAC-SHA256 pulse signing | `src/core/PulseGenerator.php` | ✅ Implemented + tested |
| `TrustEngine` — frozen trust score algorithm | `src/core/TrustEngine.php` | ✅ Implemented + tested |
| `TrustResolver` — credential-level base score + drift/session deductions | `src/core/TrustResolver.php` | ✅ Implemented + tested |
| `DeviceContinuity` — server-issued `device_id`, fingerprint, session recovery | `src/core/DeviceContinuity.php` | ✅ Implemented |
| `DeviceMatcher` — fingerprint scoring thresholds | `src/core/DeviceMatcher.php` | ✅ Implemented |
| `EnvironmentResolver` / `EnvironmentRecord` — client-first environment record with Matomo fallback only | `src/services/EnvironmentResolver.php`, `src/core/EnvironmentRecord.php` | ✅ Implemented + tested |
| `IdentityResolver` — five-tier resolution via Helios | `src/core/IdentityResolver.php` | ✅ Implemented |
| `AuthorityResolver` — governance scope, multi-authority aggregation | `src/core/AuthorityResolver.php` | ✅ Implemented |
| `ConsentManager` — cascade: user meta → site authority default → deny | `src/core/ConsentManager.php` | ✅ Implemented |
| `StepUpPolicy` — Level 3 always; Level 2 when `trust_score < 0.7` | `src/core/StepUpPolicy.php` | ✅ Implemented |
| `NetworkContextBroker` — cross-domain handoff with `tl`/`ts` payload | `src/core/NetworkContextBroker.php` | ✅ Implemented |
| `ContextBootException` — boot failure signal | `Starisian\Sparxstar\Infrastructure\Exceptions\ContextBootException` | ✅ Imported from Ouroboros |
| `StarUserEnv` — frozen public facade (UEC compatibility) | `src/StarUserEnv.php` | ✅ Implemented — signatures frozen |

### What this repository does NOT own

| Responsibility | Owned by |
|---|---|
| Agreement evaluation (proceed / deny) | **Helios** |
| KV revocation | **Helios** |
| Pulse **verification** | **Helios** — Sirus *generates*, Helios *verifies* |
| Governance policy | **Mehns** |
| Persistence | **Dheghom** |
| Draft accumulation | **Sky** |

---

## Ouroboros Dependency Status

Sirus now imports canonical shared contracts from `sparxstar-ouroboros-integrity`; it no longer carries provisional mirrors for `ContextPulse` or `ContextBootException`.

| Shared contract | Canonical namespace | Sirus responsibility |
|---|---|---|
| `ContextPulse` | `Starisian\Sparxstar\Infrastructure\DTOs` | Generate and sign only; never verify at runtime |
| `ContextBootException` | `Starisian\Sparxstar\Infrastructure\Exceptions` | Throw/rethrow on context boot failure; never swallow |
| `ContextPulseSigningMaterial` | `Starisian\Sparxstar\Infrastructure\Utils` | Build canonical signing material for generated pulses |

Remaining imports to reconcile when available in Ouroboros: `AgreementResult`, `ValidationHelper`, and `VerificationResult`.

---

## Build-Out Plan

1. **Dependency gate** — restore installability of `sparxstar-ouroboros-integrity` from the configured registry or VCS source, then rerun `composer install`, `composer run smoke:api-contract`, `composer run test`, and `composer run analyze` in CI.
2. **S-07 validation closeout** — convert remaining tracker rows marked validation-pending to complete only after the full PHPUnit suite passes against installed dependencies.
3. **PHPStan level migration** — finish S-05 by raising the configured analysis level one step at a time, with no new baseline entries for Sirus-owned code.
4. **UEC stabilization exit** — after the 30-day production window, execute S-03: audit live `Starisian\SparxstarUEC\` call sites, then remove legacy UEC classes and compatibility exclusions without changing `StarUserEnv` signatures.
5. **Cross-repo handoff checks** — confirm Helios and Dheghom consume the same Ouroboros version and that Helios remains the only runtime pulse verifier.

---

## Hard Rules

| Rule | Enforcement |
|---|---|
| `declare(strict_types=1)` in every file | Required |
| Namespace: `Starisian\Sparxstar\Sirus\` | Required |
| `ContextEngine::current()` returns valid `SirusContext` or throws `ContextBootException` — never null, never partial | Enforced in `ContextEngine` |
| `ContextBootException` MUST NEVER be caught and swallowed | Enforced by convention — no silent catch blocks |
| `device_id` is ALWAYS server-issued — never JS fingerprint alone | Enforced in `DeviceContinuity` |
| IP addresses stored with last octet zeroed: `192.168.1.0` | Enforced in `IpAnonymizer` |
| `ContextPulse` NEVER contains identity claims | Enforced by Ouroboros DTO shape and `PulseGenerator` |
| MUST NEVER call `wp_set_auth_cookie()` or issue JWTs | Enforced by review |
| MUST NEVER query Dheghom or any external plugin directly | Enforced by review |
| `StarUserEnv` signatures are FROZEN — must never change | Enforced by review |

---

## Trust Score Algorithm (Frozen)

The trust score algorithm is frozen and MUST NOT be changed without a formal spec update.

```
base            =  1.0
device drifting = -0.3
geo mismatch    = -0.2
new session     = -0.1
recent failures = -0.3
result clamped to [0.0, 1.0]
```

Score → Level mapping (used by `StepUpPolicy`):

| Score | Level |
|---|---|
| `>= 0.7` | `NORMAL` — no step-up required for Level 2 resources |
| `> 0.0 and < 0.7` | `ELEVATED` — step-up required for Level 2 resources |
| `= 0.0` | `CRITICAL` |

---

## StepUpPolicy (Frozen)

| Resource sensitivity | Condition | Step-up required? |
|---|---|---|
| Level 3 | Always | ✅ Yes |
| Level 2 | `trust_score < 0.7` | ✅ Yes |
| Level 2 | `trust_score >= 0.7` | ❌ No |
| Level 1 | Any | ❌ No |

`StepUpPolicy` **recommends**. Helios **enforces**.

---

## CLI Context

When `PHP_SAPI === 'cli'`, `ContextEngine::current()` returns a fixed system context:

```
identity_id  = "SYSTEM"
trust_score  = 1.0
trust_level  = "NORMAL"
authority_id = "GLOBAL"
device_id    = "CLI"
```

---

## UEC Compatibility (StarUserEnv)

The original `sparxstar-user-environment-check` plugin is in production. Sirus replaces it transparently. The `StarUserEnv` facade is frozen and must never change:

```php
StarUserEnv::get_browser_name()           // → EnvironmentResolver → browser_name
StarUserEnv::get_os()                     // → EnvironmentResolver → os
StarUserEnv::get_device_type()            // → EnvironmentResolver → device_type
StarUserEnv::get_network_effective_type() // → EnvironmentResolver → network_effective_type
StarUserEnv::get_ip_address()             // → IpAnonymizer → last octet zeroed
StarUserEnv::get_location()               // → GeoIP → location or null
```

---

## ContextEngine Usage

```php
// Returns a valid SirusContext or throws ContextBootException.
// NEVER returns null. NEVER returns partial context.
$ctx = \Starisian\Sparxstar\Sirus\core\ContextEngine::current();

// Array output for REST / external consumers.
$payload = \Starisian\Sparxstar\Sirus\core\ContextEngine::getContext();

// After a REST device resolution — binds the resolved device for the request.
$ctx = \Starisian\Sparxstar\Sirus\core\ContextEngine::buildFromDevice($device_record);
```

---

## PulseGenerator Usage

```php
// Requires SIRUS_PULSE_SIGNING_KEY constant in wp-config.php (min 32 bytes).
define( 'SIRUS_PULSE_SIGNING_KEY', 'your-32-char-minimum-key' );

$generator = new \Starisian\Sparxstar\Sirus\core\PulseGenerator();
$pulse     = $generator->generate(ContextEngine::current());

// $pulse is a ContextPulse DTO — safe to transmit to Helios.
// It contains: pulse_id, context_id, device_id, session_id, site_id,
//              network_id, trust_score, trust_level, issued_at, expires, sig.
// It does NOT contain identity_id.
```

---

## ConsentManager Usage

`getTechnicalConsent()` resolves via a three-level cascade (privacy-first):

1. **Individual user meta** — highest priority; set by the user
2. **Site authority default** — set by the site admin via `setSiteConsentDefault()`; allows per-site privacy postures (e.g., a sovereign band site can enforce `STATE_DENIED` for all users)
3. **System hard default** — `STATE_DENIED` (never `granted` by default)

```php
$consent = new \Starisian\Sparxstar\Sirus\core\ConsentManager();

// Technical consent — resolved via cascade.
$state = $consent->getTechnicalConsent($user_id); // 'granted' | 'denied' | 'pending'
$consent->setTechnicalConsent($user_id, ConsentManager::STATE_GRANTED);

// Site authority default (called by site admin UI, not end users).
$consent->setSiteConsentDefault(ConsentManager::STATE_DENIED, $blog_id);
$default = $consent->getSiteConsentDefault($blog_id); // 'denied'

// Purpose-level consent.
$consent->setPurposeConsent($user_id, 'analytics', ConsentManager::STATE_DENIED);
$map = $consent->getPurposeConsent($user_id); // ['analytics' => 'denied']

// Append-only history (never modified).
$history = $consent->getHistory($user_id);
```

---

## TrustEngine Usage

```php
$engine = new \Starisian\Sparxstar\Sirus\core\TrustEngine();

$result = $engine->compute([
    'device_drifting' => true,   // -0.3
    'geo_mismatch'    => false,
    'new_session'     => true,   // -0.1
    'recent_failures' => false,
]);
// $result = ['trust_score' => 0.6, 'trust_level' => 'ELEVATED']
```

For building a context from a `DeviceRecord`, use `TrustResolver` — it derives the trust score from the credential level (`elder`→0.95, `contributor`→0.90, `user`→0.85, `device`→0.70, `anonymous`→0.50) before applying the same frozen deductions:

```php
$score = \Starisian\Sparxstar\Sirus\core\TrustResolver::evaluate($device_record);
// $score = float in [0.0, 1.0]
```

`ContextEngine::buildFromDevice()` calls this automatically. Do not call it directly unless building a context outside `ContextEngine`.

---

## Geolocation (Optional)

Sirus does not provide geolocation itself. Hook the filter to plug in a provider:

```php
add_filter( 'sparxstar_env_geolocation_lookup', function( $location, $ip ) {
    return my_geo_service_lookup($ip); // Must return ['country' => 'US', ...] or null
}, 10, 2 );
```

Without a provider, `StarUserEnv::get_location()` returns `null`.

---

## Available Filters (Stable — Do Not Remove)

| Filter | Purpose |
|---|---|
| `sparxstar_env_cache_handler` | Switch cache backend |
| `sparxstar_env_cache_ttl` | Set cache duration |
| `sparxstar_env_geolocation_ttl` | Set geolocation cache duration |
| `sparxstar_env_geolocation_lookup` | Custom geolocation provider |
| `sparxstar_env_retention_days` | Snapshot retention window |
| `sparxstar_sirus_device_ttl_days` | Device record TTL (default 90 days) |

---

## Build and Test

```bash
composer install
composer test           # Full suite: lint + analyze + unit tests
composer run lint       # PHPCS PSR-12 + WordPress VIP
composer run analyze    # PHPStan Level 5
composer run test:unit  # PHPUnit only
```

### Dependencies from sparxstar-ouroboros-integrity

The following are imported from, or reserved for, Ouroboros. Do not redefine them in Sirus:

- `ContextBootException`
- `ContextPulse` DTO
- `ContextPulseSigningMaterial`
- `AgreementResult` enum (pending local usage)
- `ValidationHelper` (pending local usage)
- `VerificationResult` enum (Helios verification path only)

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    SIRUS CONTEXT ENGINE                     │
├──────────────┬──────────────┬───────────────┬──────────────┤
│ ContextEngine│  TrustEngine │ PulseGenerator│EnvironmentR. │
│  current()   │  compute()   │  generate()   │  resolve()   │
│  build()     │  (frozen)    │  (sign only)  │  (UA parse)  │
│  buildFrom   │              │               │              │
│  Device()    │              │               │              │
├──────────────┴──────────────┴───────────────┴──────────────┤
│              SirusContext DTO (trust_score included)        │
├──────────────┬──────────────┬───────────────┬──────────────┤
│DeviceCont.   │ConsentManager│ StepUpPolicy  │ DeviceMatcher│
│server-issued │ tech+purpose │ recommends    │ DRIFT=0.6    │
│ device_id    │ append-only  │ Helios enf.   │ EXACT=1.0    │
├──────────────┴──────────────┴───────────────┴──────────────┤
│ IdentityResolver  │ AuthorityResolver │ NetworkContextBroker│
│ (Helios only)     │ (authority type)  │ (cross-domain token)│
├──────────────────────────────────────────────────────────────┤
│             StarUserEnv  ← FROZEN (UEC compatibility)       │
└─────────────────────────────────────────────────────────────┘
          ↓ generates pulses         ↑ does NOT verify
          ↓ passes context        HELIOS verifies pulses
          ↓ recommends step-up   HELIOS decides proceed/deny
```

---

## Installation

1. Place the plugin in `/wp-content/mu-plugins/` (mu-plugin — cannot be deactivated)
2. Requires **WordPress 6.8+** and **PHP 8.2+**
3. Loads automatically — no activation step
4. Add `SIRUS_PULSE_SIGNING_KEY` to `wp-config.php` (required for `PulseGenerator`)

---

## Compatibility

- Fully **WordPress Multisite** compatible (network-aware from boot)
- Optimized for **PHP-FPM**
- Safe behind **Cloudflare → Nginx → Varnish → Apache**
- Consumes `sparxstar-helios-trust` for identity context

---

## License

Proprietary. All rights reserved.
Commercial usage requires written consent from **Starisian Technologies / MaximillianGroup**.

---

## Credits

Developed by **Max Barrett** and **Starisian Technologies**.
Built to power scalable digital tools and creative ecosystems across West Africa and beyond.


---
