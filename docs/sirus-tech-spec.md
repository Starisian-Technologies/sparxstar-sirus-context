---
product_id: sirus
name: "Sirus Context Engine"
status: review
version: "3.0.0"
owner: "@MaximillianGroup"
last_reviewed: "2026-07-01"
spec_id_prefix: "SIRUS"
---

# Sirus Context Engine — Technical Specification

## REQ-001 — Identity

**Product:** Sirus Context Engine
**Repo:** `Starisian-Technologies/sparxstar-sirus-context`
**Deployed as:** WordPress mu-plugin (`mu-plugins/sparxstar-sirus-context/`)
**Platform position:** `Ouroboros → Helios → Sirus → Sky → Mehns → Dheghom`
**Namespace:** `Starisian\Sparxstar\Sirus\`

Sirus is the context engine. It establishes environment before identity is proven, before authentication runs, before any application logic executes. Every downstream layer depends on the context it produces.

---

## REQ-002 — Role boundary

Sirus **produces context**. It does not make authorization decisions, enforce governance, verify pulses, or persist application state.

| Responsibility | Owner |
|---|---|
| Agreement evaluation (proceed/deny) | Helios |
| KV revocation | Helios |
| Pulse **verification** | Helios (Sirus generates; Helios verifies) |
| Governance policy enforcement | Mehns |
| Persistence | Dheghom |
| Draft accumulation | Sky |
| Shared infrastructure types | Ouroboros |

**Hard rules — non-negotiable:**

- `declare(strict_types=1)` in every file
- `ContextEngine::current()` returns a valid `SirusContext` or throws `ContextBootException` — never null, never partial
- `ContextBootException` must never be caught and swallowed
- `device_id` is always server-issued — never derived from JS fingerprint alone
- `ContextPulse` never contains identity claims
- IP addresses stored with last octet zeroed (`192.168.1.0`)
- `StarUserEnv` method signatures are frozen permanently
- MUST NEVER call `wp_set_auth_cookie()` or issue JWTs
- MUST NEVER query Dheghom or any external plugin directly

---

## REQ-003 — Platform citations

- Sirus Context Engine Spec v3.0 (`docs/specs/Sirus_Context_Engine_Spec_v3.0.docx.pdf`)
- SPARXSTAR Platform Integrity Map v1.0 (`docs/specs/Sparxstar_Platform_Integrity_Map_v1.0.docx.pdf`)
- PAM-002: Platform Integrity Architecture Memo
- GATE-AUDIT-PAM003: CI gate audit findings

---

## REQ-004 — Architecture

### Boot sequence

Sirus loads as a mu-plugin via `mu-plugins/00-sparxstar-loader.php`. It executes before any standard plugin, theme, or authentication hook.

```
mu-plugins/00-sparxstar-loader.php
  → SirusPlugin::boot()
    → ContextEngine::current()          returns SirusContext or throws ContextBootException
      → IdentityResolver::resolve()     five-tier identity resolution
      → DeviceContinuity::resolve()     server-issued device_id; fingerprint pipeline
      → TrustResolver::buildFromDevice() credential-level base + drift/session deductions
      → TrustEngine::compute()          frozen algorithm → trust_score [0.0, 1.0]
      → EnvironmentResolver::resolve()  client-first → EnvironmentRecord
      → AuthorityResolver::aggregate()  multi-authority aggregation
      → ConsentManager::resolve()       user→site→deny cascade
      → ContextCache::set()             TTL eviction
```

Because Sirus is deployed as a must-use plugin, WordPress never fires `register_activation_hook()`
or `register_deactivation_hook()` for it. Schema creation and cron scheduling therefore run from
`SirusPlugin::bootSchemaAndCron()` on an early `init` hook instead of an activation hook:
`SirusDatabase::maybe_upgrade_schema()` does a single cheap `get_option()` read of a stored schema
version and only runs `dbDelta()` on a version mismatch, and cron registration reuses the existing
idempotent `schedule_cron()` (`wp_next_scheduled()` guarded) pattern already used by
`ClientTelemetry`/`SirusEventAggregator`. `SirusPlugin::onActivation()`/`onDeactivation()` and the
`sparxstar-sirus-context.php` lifecycle hook registrations have been removed as unreferenced.

The legacy `sparxstar-user-environment-check.php` entry point is still shipped alongside
`sparxstar-sirus-context.php` (removal is a separately-scheduled item blocked on a stabilization
window — see OQ-005) but is now a guarded no-op: it returns immediately if `SIRUS_VERSION` is
already defined, so "Sirus wins" whenever both are present instead of behavior depending on mu-plugin
load order. It is excluded from distribution builds via `.distignore`.

### Trust score algorithm (frozen)

```
base = 1.0
device drifting:   -0.3
geo mismatch:      -0.2
new session:       -0.1
recent failures:   -0.3
clamped to [0.0, 1.0]
```

`trust_level` is carried as the Ouroboros `TrustLevelPrimitive` enum (PAM-003 Decision 7):
`NORMAL`, `STEP_UP_REQUIRED` (pre-flagged anomaly), `LOCKED` (administratively locked — most
severe). This supersedes the older `NORMAL`/`ELEVATED`/`CRITICAL` score-band language; see
OQ-002's resolved `TrustLevelPrimitive` drift item below.

Step-up policy (`StepUpPolicy`, frozen, first match wins):

1. `trust_level === LOCKED` → step-up always required, unconditionally (most severe; checked
   before the pre-flagged case below so it can never fall through to a less restrictive branch).
2. `trust_level === STEP_UP_REQUIRED` → step-up always required (pre-flagged context).
3. `ResourceSensitivity::LEVEL_3` → step-up always required.
4. `ResourceSensitivity::LEVEL_2` → step-up required when `trust_score < 0.7`.
5. `ResourceSensitivity::LEVEL_1` → no step-up required.

Sirus only *handles* `LOCKED` correctly if it is ever received on a pulse — this repository does
not decide when to emit it; that is a separate, larger governance decision not yet made.

### CLI context

When `PHP_SAPI === 'cli'`:

```
identity_id  = "SYSTEM"
trust_score  = 1.0
trust_level  = "NORMAL"
authority_id = "GLOBAL"
device_id    = "CLI"
```

### Environment resolution (client-first)

Client-submitted signals take precedence over server-side UA parsing. Matomo DeviceDetector is fallback/enrichment only. `EnvironmentRecord` is built with:

- IP anonymization (last-octet zeroed)
- Region-level location only (no exact coordinates without grant)
- Network filtering
- `captured_at` metadata

### Pulse generation

Sirus signs pulses via HMAC-SHA256 using Ouroboros `ContextPulseSigningMaterial::build()`. Pulse fields include the four PAM-002-P2 fields: `behavior_flags`, `geo_zone`, `network_effective_type`, `session_duration`. Sirus never verifies pulses at runtime — that is Helios. The HMAC signing key is read exclusively from the `SPARXSTAR_PULSE_SIGNING_KEY` PHP constant (renamed from its old `SIRUS_`-prefixed name to match Helios's side of the shared secret — the two names never matched, so every pulse previously failed Helios's signature verification).

### Pulse TTL strategy (provisional pending field testing)

`PulseGenerator::resolveTtl(ResourceSensitivity $sensitivity): int` maps resource sensitivity to a working-default TTL, configurable via the `sparxstar_sirus_pulse_ttl_seconds` filter (receives `$ttl, $sensitivity, $default`) rather than being hardcoded:

| Sensitivity | Default TTL |
|---|---|
| `LEVEL_1` | 120s |
| `LEVEL_2` | 60s |
| `LEVEL_3` | 30s |

At `LEVEL_1` only, a low-connectivity network (`EnvironmentResolver::getNetworkEffectiveType()` returning `slow-2g`/`2g`/`slow-3g`) extends the TTL to a flat 600s (10 minutes) cap, applied after the filter. `LEVEL_2`/`LEVEL_3` are never extended for connectivity. `PulseGenerator::PULSE_TTL` (60s) remains the fallback default when `generate()` is called with no explicit TTL. `SirusRESTController::handle_generate_pulse()` is the production call site: it resolves the TTL via `resolveTtl()` before calling `generate()`. The specific second values above are provisional pending field testing and may change.

---

## REQ-005 — Data model

### SirusContext (primary output)

| Field | Type | Notes |
|---|---|---|
| `identity_id` | `?string` | Resolved identity; `"SYSTEM"` for CLI; null if unresolved |
| `device_id` | `string` | Server-issued; never JS-only |
| `trust_score` | `float` | [0.0, 1.0] |
| `trust_level` | `TrustLevelPrimitive` | Enum: NORMAL / STEP_UP_REQUIRED / LOCKED |
| `authority_id` | `?string` | Governance scope; `"GLOBAL"` for CLI; null if unresolved |
| `environment` | `EnvironmentRecord` | Client-first environment record |
| `consent` | `ConsentRecord` | Three-level cascade result |
| `capabilities` | `array` | Named capabilities from `CapabilityEngine` |

### EnvironmentRecord

| Field | Type | Notes |
|---|---|---|
| `browser_name` | `?string` | Client-submitted or Matomo fallback |
| `os` | `?string` | Client-submitted or Matomo fallback |
| `device_type` | `?string` | Client-submitted or Matomo fallback |
| `network_effective_type` | `?string` | Client-submitted |
| `ip_address` | `string` | Last octet zeroed |
| `geo_zone` | `?string` | Region-level only |
| `is_bot` | `bool` | |
| `time_zone` | `?string` | Client-submitted |
| `captured_at` | `int` | Unix timestamp |

### ContextPulse (owned by Ouroboros)

15 fields per PAM-002 §3.3. Includes: `pulse_id`, `issued_at`, `expires_at`, `trust_level`, `trust_score`, `geo_zone`, `network_effective_type`, `session_duration`, `behavior_flags`, HMAC signature. Never contains `identity_id`.

---

## REQ-006 — API surface

### REST endpoints (Spec §18–19)

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/sparxstar/v1/device` | Register or resolve a device record |
| `GET` | `/sparxstar/v1/context` | Current `SirusContext`; optional `device_id` must match |
| `POST` | `/sparxstar/v1/pulse` | Issue signed `ContextPulse`; HttpOnly/SameSite=Strict cookie + `{pulse_id, expires_at, trust_level}` body |
| `GET` | `/sparxstar/v1/identity` | Resolve current identity tier |
| `GET` | `/sparxstar/v1/session` | Current session status |
| `POST` | `/sparxstar/v1/client-report` | Telemetry/error ingestion; never stored in post meta |

Machine-readable contract: `docs/contracts/sirus-api-contract.v1.json`
Seed payloads: `docs/contracts/sirus-api-seed.v1.json`

### Public PHP surface (frozen)

See `PUBLIC_API.md` for the full list of hooks, filters, and classes consumed by other repos.

**Stable filters:**

```
sparxstar_env_cache_handler
sparxstar_env_cache_ttl
sparxstar_env_geolocation_ttl
sparxstar_env_geolocation_lookup
sparxstar_env_retention_days
sparxstar_sirus_device_ttl_days
sparxstar_sirus_pulse_ttl_seconds
```

**StarUserEnv facade (frozen — signatures never change):**

```php
StarUserEnv::get_browser_name()
StarUserEnv::get_os()
StarUserEnv::get_device_type()
StarUserEnv::get_network_effective_type()
StarUserEnv::get_ip_address()
StarUserEnv::get_location()
```

---

## REQ-007 — Seams

### Upstream (consumes)

- `sparxstar-ouroboros-integrity` — `ContextBootException`, `ContextPulse`, `ContextPulseSigningMaterial`, `TrustLevelPrimitive`; pending: `AgreementResult`, `ValidationHelper`, `VerificationResult`
- Helios (via `IdentityResolver`) — five-tier identity resolution
- WordPress core — `wpdb`, auth hooks, option/meta storage

### Downstream (produces)

- `SirusContext` — consumed by Helios, Mehns, Sky, Dheghom
- `ContextPulse` — consumed by Helios for verification
- REST surface — consumed by Helios, Dheghom, Sky, compatibility callers
- `StarUserEnv` facade — consumed by all existing WordPress site code

### Stub-drift CI check

`bin/check-ouroboros-stub-drift.php` (composer script `check:ouroboros-drift`) reflects the real
installed `starisian/sparxstar-ouroboros-integrity` package and verifies that the primitives Sirus
actually consumes from it (`TrustLevelPrimitive` cases, `ContextPulse` constructor parameters,
`Platform` constants, `ContextPulseSigningMaterial::build()`, `ContextBootException`) still match
the shape Sirus's code assumes. It runs as a dedicated CI job (`.github/workflows/test.yml`,
`ouroboros-stub-drift`) after the main `php-tests` job. This is the automated version of the manual
process that would have caught the historical drift documented in
`docs/DRAFT-OQ-016-trustlevelprimitive-drift.md` before it reached 94 CI failures.

### OQ-001 — Ouroboros v2.0 availability

`sparxstar-ouroboros-integrity` v2.0.0 `shared-test-vectors.json` is blocked by a `repository not authorized` CI gate error. Ten Ouroboros-coupled tests remain at 🟡 until this resolves.

---

## REQ-008 — Dependencies

### Runtime

| Package | Version | Purpose |
|---|---|---|
| `starisian/sparxstar-ouroboros-integrity` | `^3.0` | Shared contracts and infrastructure types |
| `geoip2/geoip2` | `^3.2` | GeoIP resolution |

### Optional

| Package | Purpose |
|---|---|
| `matomo/device-detector` | Server-side UA parsing (fallback only; not required for client-first path) |

### Dev

PHPUnit `^11.5.50`, PHPStan `^2.1` (currently Level 5 → target Level 7 via S-05), PHPCS with WordPress + VIP sniffs, PSR-12 base.

---

## REQ-009 — Security and privacy

- **Trust score** — frozen algorithm; deductions clamped to [0.0, 1.0]; no identity in pulse
- **Credential-tier base scores** (`TrustResolver::CREDENTIAL_BASE`) — `anonymous` 0.50 < `device` 0.70 < `user` 0.85 < `contributor` 0.90 < `authority` 0.95. Every real `CredentialTier` case must resolve above `DEFAULT_BASE` (0.50, `anonymous`'s own value) so a valid tier can never silently score worse than an anonymous device by falling through to the default.
- **IP anonymization** — last octet zeroed at `EnvironmentRecord` construction; enforced at construction, not caller-side
- **Telemetry** — `ClientTelemetry` reports never stored in post meta (§23); aggregated only
- **Cookies** — pulse cookie is HttpOnly + SameSite=Strict
- **SQL** — all queries use `$wpdb->prepare()` with `%d`/`%s` placeholders; no interpolation (enforced by PHPCS `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`)
- **Sessions** — PHP sessions used intentionally for device continuity on standard WP hosting; excluded from VIP session sniff by policy
- **Nonces** — REST endpoints use WP REST nonce (`wpRestNonce`); declared in OpenAPI contract
- **`device_id`** — always server-issued; JS-side `visitorId` is probabilistic drift-detection input only, not a security primitive

---

## REQ-010 — Current state

### Sprint status (as of 2026-07-01)

| Sprint | Theme | Status |
|---|---|---|
| S-00 | Foundation | ✅ Complete |
| S-01 | Spec v3.0 alignment | ✅ Complete |
| S-02 | Test coverage for S-01 | ✅ Complete |
| S-03 | UEC legacy removal | 🔲 Pending 30-day stabilization window |
| S-04 | Ouroboros integration | ✅ Complete (3 imports pending Ouroboros v2.0) |
| S-05 | PHPStan level increase | 🟡 In progress (Level 5 → target 7) |
| S-06 | Observability hardening | 🟡 In progress |
| S-07 | Spec completeness + Helios readiness | ✅ Implementation merged; 🟡 validation pending CI gate |
| **S-08** | **Make CI trustworthy** | **🔲 Next sprint** |
| S-09 | Ouroboros v2.0 alignment | 🔲 Planned (after S-08) |

### AC-001 — PHPStan 0 errors at Level 5

Achieved. `phpstan-baseline.neon` is empty for Sirus-owned code.

### AC-002 — PHPCS 0 errors at PSR-12 + WordPress security

Achieved as of S-01/S-02 pass. CI gate reliability is S-08 scope.

### AC-003 — All 6 REST endpoints registered and integration-tested

Achieved in S-07. `tests/integration/RestApiTest.php` covers all six endpoints.

---

## OQ-002 — Open items

- **OQ-001** (above) — Ouroboros v2.0 `repository not authorized` CI block
- **OQ-002** — PAM-003 acceptance criteria file is referenced across multiple files but not present in this repo; needs to be supplied or referenced to a canonical location
- **OQ-003** — `geo_zone` format not yet locked (PAM-002-O3); must be locked before PAM-002-P3 ships
- **OQ-004** — `VerificationResult`, `AgreementResult`, `ValidationHelper` imports deferred pending Ouroboros v2.0 publication
- **OQ-005** — S-03 UEC legacy removal gated on 30-day production stabilization window post S-01 deployment; 12 legacy `SparxstarUEC*` files remain in codebase
- **OQ-006** — Engineering Standards §6.2 citation in GATE-AUDIT-PAM003 references "GraphQL Resolver Rules" — citation needs correction or the actual PSR-4 naming standard document needs to be supplied
- **[PENDING OQ NUMBER FROM GOVERNANCE REGISTRY]** — `TrustLevelPrimitive` enum drift (`docs/DRAFT-OQ-016-trustlevelprimitive-drift.md`): historically, Sirus called `TrustLevelPrimitive::ELEVATED`/`::from('ELEVATED')`/`::from('anonymous')`, none of which exist on the current `TrustLevelPrimitive` enum (`NORMAL`, `STEP_UP_REQUIRED`, `LOCKED`). **Status: the live call sites now use the correct cases** (`PulseGenerator::deriveBehaviorFlags()` checks `STEP_UP_REQUIRED`; `StepUpPolicy` handles `NORMAL`/`STEP_UP_REQUIRED`/`LOCKED` fail-closed per the spec-conformance pass that added the missing `LOCKED` branch). The `bin/check-ouroboros-stub-drift.php` CI check (see above) now guards against this class of drift recurring silently. This entry is left as a historical record; the DRAFT-OQ-016 document itself should still be formally ratified/closed by the owner in the governance registry.
- **OQ-009** — `CredentialTier` not exported by `sparxstar-ouroboros-integrity` v3.0.0 (commit `b7edd0d5`). Provisional definition lives at `src/Infrastructure/DTOs/CredentialTier.php` under the shared `Starisian\Sparxstar\Infrastructure\DTOs` namespace. Remove provisional file and the `"Starisian\\Sparxstar\\Infrastructure\\"` autoload entry in `composer.json` once Ouroboros publishes the type. Blocking 108 CI errors on `claude/tender-babbage-y2p8us` until provisional fix was applied 2026-07-06.

---

## Changelog

| Version | Date | Notes |
|---|---|---|
| 3.0.2 | 2026-08-01 | Spec-conformance audit fixes: renamed the old `SIRUS_`-prefixed signing-key constant to `SPARXSTAR_PULSE_SIGNING_KEY` (matches Helios); schema creation/cron scheduling moved from the never-fired activation hook to a boot-time idempotent check (`SirusDatabase::maybe_upgrade_schema()`); legacy `sparxstar-user-environment-check.php` is now a guarded no-op when Sirus is loaded; `TrustResolver::CREDENTIAL_BASE` fixed (removed dead `elder` entry, added missing `authority` entry scored above `user`); `StepUpPolicy` now fails closed on `LOCKED` (checked before `STEP_UP_REQUIRED`); `PulseGenerator::resolveTtl()` implements the sensitivity-driven pulse TTL strategy (§ Pulse TTL strategy); added `bin/check-ouroboros-stub-drift.php` CI check. |
| 3.0.1 | 2026-07-06 | Requires `sparxstar-ouroboros-integrity` ≥ v3.0.0 (introduces `CredentialTier` enum and two-field trust/credential split). Sirus is first platform repo on Ouroboros 3.x; Helios, Sky, Mehns, Dheghom tracking separately. |
| 3.0.0 | 2026-07-01 | Initial governance spec submission; reflects S-07 implementation state |
| — | 2026-06-12 | TRACKER.md last updated; S-07 merged to main |
| — | 2026-06-09 | `/context` `device_id` mismatch enforcement added |
