# SPARXSTAR Sirus — Implementation Tracker

> **Scoreboard:** Does the implementation fully represent the spec layer it claims to align to?

This document tracks every component defined in **Sirus Context Engine Spec v3.0** against its build state, assigns it to a sprint, and surfaces what work remains. Update this file as sprints close.

---

## Legend

| Symbol | Meaning |
|---|---|
| ✅ | Built, tested, merged |
| 🟡 | Built, not yet tested |
| 🔲 | Specified, not yet built |
| ⏳ | Blocked on external dependency |
| 🗑️ | Scheduled for removal (replaced by upstream) |

---

## Scoreboard — Spec v3.0 Components

### Core Engine

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `ContextEngine::current()` | `src/core/ContextEngine.php` | ✅ | S-01 | Throws `ContextBootException`, never null/partial |
| CLI system context | `src/core/ContextEngine.php` | ✅ | S-01 | `SYSTEM`/`GLOBAL`/`CLI` path |
| `SirusContext` DTO | `src/core/SirusContext.php` | ✅ | S-01 | Includes `trust_score` field |
| `ContextCache` | `src/core/ContextCache.php` | ✅ | S-01 | Cache + TTL eviction |
| `ContextBootException` | `src/exceptions/ContextBootException.php` | 🟡 | S-01 | **PROVISIONAL** — replace with Ouroboros import |
| `ContextPulse` DTO | `src/dto/ContextPulse.php` | 🟡 | S-01 | **PROVISIONAL** — replace with Ouroboros import |

### Trust and Security

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `TrustEngine` | `src/core/TrustEngine.php` | ✅ | S-01/S-02 | Frozen algorithm; 18 unit tests in `TrustEngineTest` |
| `TrustResolver` | `src/core/TrustResolver.php` | ✅ | S-01/S-02 | Credential-level base + drift/session deductions; 15 unit tests in `TrustResolverTest` |
| `StepUpPolicy` | `src/core/StepUpPolicy.php` | ✅ | S-01/S-02 | Frozen policy; operates on `ContextPulse` + `ResourceSensitivity`; 15 unit tests |
| `PulseGenerator` | `src/core/PulseGenerator.php` | ✅ | S-01/S-02 | HMAC-SHA256 only; 20 unit tests in `PulseGeneratorTest`; `$now`/`$ttlSeconds` explicit params |

### Device and Identity

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `DeviceContinuity` | `src/core/DeviceContinuity.php` | ✅ | S-01 | Two-stage pipeline: `resolveDevice()` + `evaluateContinuity()` |
| `DeviceMatcher` | `src/core/DeviceMatcher.php` | 🟡 | S-01 | EXACT=1.0 / DRIFT=0.6 thresholds; missing unit tests |
| `DeviceRecord` DTO | `src/core/DeviceRecord.php` | ✅ | S-01 | |
| `DeviceRepository` | `src/core/DeviceRepository.php` | ✅ | S-01 | |
| `IdentityResolver` | `src/core/IdentityResolver.php` | ✅ | S-01 | Five-tier resolution via Helios |
| `AuthorityResolver` | `src/core/AuthorityResolver.php` | ✅ | S-01 | Multi-authority aggregation |

### Environment and Network

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `EnvironmentResolver` | `src/services/EnvironmentResolver.php` | 🟡 | S-01 | Matomo DeviceDetector; missing unit tests |
| `NetworkContextBroker` | `src/core/NetworkContextBroker.php` | ✅ | S-01 | `issueToken(context, secret)` / `verifyToken(token, secret)` — explicit secret; portable |

### Consent and Compliance

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `ConsentManager` | `src/core/ConsentManager.php` | 🟡 | S-01 | Three-level cascade (user→site→deny); purpose consent; append-only history; unit tests pending S-02 |

### Compatibility

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `StarUserEnv` facade | `src/StarUserEnv.php` | ✅ | S-00 | **FROZEN** — UEC backward compat; signatures must never change |
| `UECCompatibilityShim` | `src/integrations/UECCompatibilityShim.php` | ✅ | S-00 | Namespace alias bridge |

---

## Scoreboard — Test Coverage

| Test File | Component | Status | Sprint | Tests |
|---|---|---|---|---|
| `ContextEngineTest.php` | `ContextEngine` | ✅ | S-01 | 6 |
| `ContextCacheTest.php` | `ContextCache` | ✅ | S-01 | — |
| `SirusContextTest.php` | `SirusContext` | ✅ | S-01 | — |
| `NetworkContextBrokerTest.php` | `NetworkContextBroker` | ✅ | S-01 | 10 tests: issue/verify round-trip, tamper detection, wrong secret, expired |
| `IdentityResolverTest.php` | `IdentityResolver` | ✅ | S-01 | — |
| `DeviceContinuityTest.php` | `DeviceContinuity` | ✅ | S-01 | — |
| `DeviceRecordTest.php` | `DeviceRecord` | ✅ | S-01 | — |
| `TrustEngineTest.php` | `TrustEngine` | ✅ | **S-02** | 18 |
| `PulseGeneratorTest.php` | `PulseGenerator` | ✅ | **S-02** | 20 |
| `TrustResolverTest.php` | `TrustResolver` | ✅ | **S-02** | 15 |
| `EnvironmentResolverTest.php` | `EnvironmentResolver` | 🔲 | S-02 | — |
| `DeviceMatcherTest.php` | `DeviceMatcher` | 🔲 | S-02 | — |
| `ConsentManagerTest.php` | `ConsentManager` | 🔲 | S-02 | — |
| `StepUpPolicyTest.php` | `StepUpPolicy` | ✅ | **S-02** | 15 |
| `ContextBootExceptionTest.php` | `ContextBootException` | 🔲 | S-02 | — |
| `ContextPulseTest.php` | `ContextPulse` | 🔲 | S-02 | — |

---

## Scoreboard — Ouroboros Migration

The following provisional types must be removed when `sparxstar-ouroboros-integrity` ships.

| Provisional file | Canonical owner | Migration status |
|---|---|---|
| `src/exceptions/ContextBootException.php` | `sparxstar-ouroboros-integrity` | ⏳ Waiting for Ouroboros |
| `src/dto/ContextPulse.php` | `sparxstar-ouroboros-integrity` | ⏳ Waiting for Ouroboros |

**Hard rule (enforced at Ouroboros merge):**

> Remove both provisional files. Import the Ouroboros-owned types directly. Do not maintain two copies.

Ouroboros must own and export:
- `ContextPulse` DTO
- `ContextBootException`
- `GovernanceToken` DTO
- `AgreementResult` enum
- `ValidationHelper`
- All shared cross-repo enums

---

## Scoreboard — UEC Legacy Code

Legacy `sparxstar-user-environment-check` files remain in the codebase during the migration window. Scheduled for removal once all call sites are confirmed migrated.

| File | Replacement | Status | Sprint |
|---|---|---|---|
| `src/SparxstarUserEnvironmentCheck.php` | `src/SirusPlugin.php` | 🗑️ | S-03 |
| `src/core/SparxstarUECAssetManager.php` | `src/core/ContextEngine.php` | 🗑️ | S-03 |
| `src/core/SparxstarUECDatabase.php` | `src/core/SirusDatabase.php` | 🗑️ | S-03 |
| `src/core/SparxstarUECInstaller.php` | `src/core/SirusDatabase.php` | 🗑️ | S-03 |
| `src/core/SparxstarUECKernel.php` | `src/SirusPlugin.php` | 🗑️ | S-03 |
| `src/core/SparxstarUECSnapshotRepository.php` | `src/core/SirusEventRepository.php` | 🗑️ | S-03 |
| `src/cron/SparxstarUECScheduler.php` | `src/SirusPlugin.php` (cron hooks) | 🗑️ | S-03 |
| `src/includes/SparxstarUECCacheHelper.php` | `src/core/ContextCache.php` | 🗑️ | S-03 |
| `src/includes/SparxstarUECSessionManager.php` | `src/core/DeviceContinuity.php` | 🗑️ | S-03 |
| `src/services/SparxstarUECGeoIPService.php` | `src/services/EnvironmentResolver.php` | 🗑️ | S-03 |
| `src/api/SparxstarUECRESTController.php` | `src/api/SirusRESTController.php` | 🗑️ | S-03 |
| `src/admin/SparxstarUECAdmin.php` | `src/admin/SirusDashboardPage.php` | 🗑️ | S-03 |

---

## Sprint Plan

### S-00 — Foundation (Complete)

> UEC compatibility layer established. StarUserEnv facade frozen.

- [x] `StarUserEnv` facade (frozen public API)
- [x] `UECCompatibilityShim` (namespace aliasing)
- [x] `SirusPlugin`, `SirusDatabase`, `SirusEventRepository`
- [x] `IpAnonymizer` (last-octet zeroing enforced)

---

### S-01 — Spec v3.0 Alignment (Complete — this PR)

> All components from Sirus Context Engine Spec v3.0 built. Behavior locked, not just structure.

- [x] `ContextEngine::current()` — deterministic, throws `ContextBootException`, never null
- [x] CLI system context path (`SYSTEM`/`GLOBAL`/`CLI`)
- [x] `SirusContext` DTO — `trust_score` field added
- [x] `TrustEngine` — frozen algorithm (base 1.0, deductions clamped to [0.0, 1.0])
- [x] `TrustResolver` — credential-level base + drift/session deductions for `buildFromDevice()`
- [x] `PulseGenerator` — HMAC-SHA256, no identity in pulse, key from constant only
- [x] `ContextPulse` DTO — immutable, provisional Ouroboros mirror
- [x] `ContextBootException` — provisional Ouroboros mirror
- [x] `EnvironmentResolver` — Matomo DeviceDetector + regex fallback + Throwable guard
- [x] `DeviceMatcher` — EXACT=1.0, DRIFT=0.6, single boundary constant
- [x] `ConsentManager` — three-level cascade (user meta → site option → deny), purpose consent, append-only history
- [x] `StepUpPolicy` — uses `ContextPulse` + `ResourceSensitivity` enum; `isRequired()`/`getRequiredLevel()` frozen boundary; 15 tests in `StepUpPolicyTest`
- [x] `NetworkContextBroker` — `issueToken(context, secret)` / `verifyToken(token, secret)` — explicit secret; `tl`/`ts` round-trip; absent `ts` derived from `tl`; 10 tests
- [x] README.md — full spec alignment documentation
- [x] PUBLIC_API.md — public surface document for cross-repo consumers

---

### S-02 — Test Coverage for S-01 Components (In Progress)

> Every S-01 component built without a unit test needs one. PHPUnit ^11.5.50, extends `SirusTestCase`.

- [x] `TrustEngineTest` — 18 tests: frozen algorithm, all signal combos, clamping to [0.0, 1.0], level mapping
- [x] `PulseGeneratorTest` — 20 tests: key validation, pulse fields, no identity_id, TTL = issued_at + default, explicit `$now`/`$ttlSeconds` honoured, sig is 64-char hex
- [x] `TrustResolverTest` — 15 tests: all credential bases, drift deduction, new-session deduction, combined, clamping
- [ ] `ContextPulseTest` — DTO immutability, field access, no identity_id field present
- [ ] `ContextBootExceptionTest` — extends `\RuntimeException`, message passthrough
- [ ] `EnvironmentResolverTest` — UA parsing (browser/OS/device), fallback regex path, network filter
- [ ] `DeviceMatcherTest` — score = 1.0 (EXACT), score = 0.6 (DRIFT), score < 0.6 (no match), component weights
- [ ] `ConsentManagerTest` — get/set technical consent, cascade order, purpose consent map, append-only history
- [ ] `StepUpPolicyTest` — ✅ COMPLETE (committed above)

**Acceptance criteria:** `composer run test:unit` passes with no failures or deprecations.

---

### S-03 — UEC Legacy Removal (After Ouroboros Ships or After Stabilisation Window)

> Remove all `SparxstarUEC*` files. Confirm no production call sites reference old namespace directly.

**Prerequisite:** 30-day stabilisation window post S-01 deployment closed.

- [ ] Audit all active site call sites for `Starisian\SparxstarUEC\` namespace references
- [ ] Remove all 12 legacy UEC files listed in the UEC Legacy Scoreboard above
- [ ] Remove `UECCompatibilityShim` (no longer needed)
- [ ] Remove `src/admin/SparxstarUECAdmin.php`
- [ ] Update `phpcs.xml` and `phpstan.neon.dist` to drop UEC exclusions
- [ ] Confirm `composer run test` passes after removals

---

### S-04 — Ouroboros Integration (Blocked — Ouroboros Not Shipped)

> Replace provisional mirrors with Ouroboros package imports.

**Prerequisite:** `sparxstar-ouroboros-integrity` package published to Packagist or private registry.

- [ ] Add `sparxstar-ouroboros-integrity` to `composer.json` `require`
- [ ] Delete `src/exceptions/ContextBootException.php` (provisional)
- [ ] Delete `src/dto/ContextPulse.php` (provisional)
- [ ] Update all import statements to use Ouroboros namespace
- [ ] Import `AgreementResult` enum from Ouroboros (remove any local copy)
- [ ] Import `ValidationHelper` from Ouroboros (remove any local copy)
- [ ] Run `composer run test` to confirm no regressions
- [ ] Confirm Helios and Dheghom are updated to the same Ouroboros version

---

### S-05 — PHPStan Level Increase (Ongoing Quality)

> Target PHPStan Level 7 across the entire `src/` tree.

- [ ] Resolve all Level 6 findings in `src/core/`
- [ ] Resolve all Level 6 findings in `src/services/`
- [ ] Resolve all Level 6 findings in `src/helpers/`
- [ ] Resolve all Level 6 findings in `src/api/`
- [ ] Update `phpstan.neon.dist` to `level: 6`, confirm clean
- [ ] Repeat for Level 7

---

### S-06 — Observability and Telemetry Hardening (Future)

> Signal pipeline completeness and cross-layer tracing.

- [ ] Confirm `SirusSignalEvaluator` covers all signal types defined in spec
- [ ] Add structured logging to `TrustEngine` (score deltas, reason codes)
- [ ] Add structured logging to `PulseGenerator` (pulse_id, issued_at, expiry)
- [ ] Add `ConsentManager` audit log integration with `SirusEventRepository`
- [ ] Expose `GET /sirus/v1/context` REST endpoint for debug/admin
- [ ] Add admin UI panel for live trust score and consent status per device

---

## How to Use This Tracker

1. **On sprint start** — move items from `🔲` to a named sprint column and assign owners.
2. **On component build** — change `🔲 → 🟡` and add the file reference.
3. **On test pass** — change `🟡 → ✅`.
4. **On Ouroboros ship** — execute S-04 and flip provisional items to ✅.
5. **On UEC removal** — execute S-03 and remove the UEC Legacy rows from this tracker.

---

*Last updated: 2026-04-09 | Spec version: Sirus Context Engine Spec v3.0*
