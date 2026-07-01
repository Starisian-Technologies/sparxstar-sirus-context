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

### Trust score algorithm (frozen)

```
base = 1.0
device drifting:   -0.3
geo mismatch:      -0.2
new session:       -0.1
recent failures:   -0.3
clamped to [0.0, 1.0]
```

Trust score maps to trust level:

| Score | Level |
|---|---|
| ≥ 0.7 | NORMAL |
| > 0.0 | ELEVATED |
| = 0.0 | CRITICAL |

Step-up policy: Level 3 always; Level 2 (step-up required) when `trust_score < 0.7`.

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

Sirus signs pulses via HMAC-SHA256 using Ouroboros `ContextPulseSigningMaterial::build()`. Pulse fields include the four PAM-002-P2 fields: `behavior_flags`, `geo_zone`, `network_effective_type`, `session_duration`. Sirus never verifies pulses at runtime — that is Helios.

---

## REQ-005 — Data model

### SirusContext (primary output)

| Field | Type | Notes |
|---|---|---|
| `identity_id` | `?string` | Resolved identity; `"SYSTEM"` for CLI; null if unresolved |
| `device_id` | `string` | Server-issued; never JS-only |
| `trust_score` | `float` | [0.0, 1.0] |
| `trust_level` | `TrustLevelPrimitive` | Enum: NORMAL / ELEVATED / CRITICAL |
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

### OQ-001 — Ouroboros v2.0 availability

`sparxstar-ouroboros-integrity` v2.0.0 `shared-test-vectors.json` is blocked by a `repository not authorized` CI gate error. Ten Ouroboros-coupled tests remain at 🟡 until this resolves.

---

## REQ-008 — Dependencies

### Runtime

| Package | Version | Purpose |
|---|---|---|
| `starisian/sparxstar-ouroboros-integrity` | `^2.0` | Shared contracts and infrastructure types |
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
- **OQ-007** — `TrustLevelPrimitive` enum drift between Sirus consumers and Ouroboros v2.0.0 (commit `3529cb67`): Sirus calls `TrustLevelPrimitive::ELEVATED` (in `PulseGenerator::deriveBehaviorFlags()`), `TrustLevelPrimitive::from('ELEVATED')` (via `ContextEngine` passing Helios-supplied strings), and `TrustLevelPrimitive::from('anonymous')` (in `NetworkContextBroker::verifyToken()`), but CI reports `ValueError` for both backing values at runtime — meaning they are absent from the installed enum. This is a live-path defect: any pulse generated for an elevated-trust context throws at runtime, not just in tests. **Owner ruling required:** (a) if `ELEVATED`/`anonymous` exist in Ouroboros at a newer tag, the fix is a version-bump in `composer.lock`; (b) if they were never shipped, Ouroboros must add them or Sirus must be rewritten to use the cases that do exist. The 94 test errors currently labelled S-09 are the observable surface of this defect. Deferral requires ratification of this OQ by the owner; until ratified, the gate is open.

---

## Changelog

| Version | Date | Notes |
|---|---|---|
| 3.0.0 | 2026-07-01 | Initial governance spec submission; reflects S-07 implementation state |
| — | 2026-06-12 | TRACKER.md last updated; S-07 merged to main |
| — | 2026-06-09 | `/context` `device_id` mismatch enforcement added |
