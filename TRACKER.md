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
| `SirusContext` DTO | `src/core/SirusContext.php` | ✅ | S-01 | Includes `trust_score`; `trust_level` typed as `TrustLevelPrimitive` enum |
| `ContextCache` | `src/core/ContextCache.php` | ✅ | S-01 | Cache + TTL eviction |
| `ContextBootException` | `packages/sparxstar-ouroboros-integrity/src/Exceptions/ContextBootException.php` | ✅ | S-04 | Migrated to Ouroboros CO-001 — `Starisian\Sparxstar\Infrastructure\Exceptions` |
| `ContextPulse` DTO | `packages/sparxstar-ouroboros-integrity/src/DTOs/ContextPulse.php` | ✅ | S-04 | Migrated to Ouroboros CO-001 — `Starisian\Sparxstar\Infrastructure\DTOs` |

### Trust and Security

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `TrustEngine` | `src/core/TrustEngine.php` | ✅ | S-01/S-02 | Frozen algorithm; 18 unit tests in `TrustEngineTest` |
| `TrustResolver` | `src/core/TrustResolver.php` | ✅ | S-01/S-02 | Credential-level base + drift/session deductions; 15 unit tests in `TrustResolverTest` |
| `StepUpPolicy` | `src/core/StepUpPolicy.php` | ✅ | S-01/S-02 | Frozen policy; `requiresStepUp()` + `TRUST_LEVEL_STEP_UP_REQUIRED` pre-flag check; 17 unit tests |
| `PulseGenerator` | `src/core/PulseGenerator.php` | ✅ | S-01/S-02 | HMAC-SHA256 only; consumes enum-backed `SirusContext::trust_level`; PAM-002-P2 fields wired (`behavior_flags`, `geo_zone`, `network_effective_type`, `session_duration`); 20 unit tests in `PulseGeneratorTest`; `$now`/`$ttlSeconds` explicit params |

### Device and Identity

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `DeviceContinuity` | `src/core/DeviceContinuity.php` | ✅ | S-01 | Two-stage pipeline: `resolveDevice()` + `evaluateContinuity()` |
| `DeviceMatcher` | `src/core/DeviceMatcher.php` | ✅ | S-01 | spec §14.3: `STRONG_MATCH_THRESHOLD=0.8` / `WEAK_MATCH_THRESHOLD=0.6`; `MatchResult` enum; `classify()`; 22 unit tests |
| `DeviceRecord` DTO | `src/core/DeviceRecord.php` | ✅ | S-01 | |
| `DeviceRepository` | `src/core/DeviceRepository.php` | ✅ | S-01 | |
| `IdentityResolver` | `src/core/IdentityResolver.php` | ✅ | S-01 | Five-tier resolution via Helios |
| `AuthorityResolver` | `src/core/AuthorityResolver.php` | ✅ | S-01 | Multi-authority aggregation |

### Environment and Network

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `EnvironmentResolver` | `src/services/EnvironmentResolver.php` | ✅ | S-07 | Client-first `EnvironmentRecord` builder with UA fallback only; compatibility accessors retained |
| `EnvironmentRecord` DTO | `src/core/EnvironmentRecord.php` | ✅ | **S-07** | Built with IP anonymization, region-level location, network filtering, and capture metadata |
| `NetworkContextBroker` | `src/core/NetworkContextBroker.php` | ✅ | S-01 | `issueToken(context, secret)` / `verifyToken(token, secret)` — explicit secret; portable |

### Consent and Compliance

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `ConsentManager` | `src/core/ConsentManager.php` | ✅ | S-01 | Three-level cascade (user→site→deny); purpose consent; append-only history; 16 tests ✅ |

### Compatibility

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `StarUserEnv` facade | `src/StarUserEnv.php` | ✅ | S-00 | **FROZEN** — UEC backward compat; signatures must never change |
| `UECCompatibilityShim` | `src/integrations/UECCompatibilityShim.php` | ✅ | S-00 | Namespace alias bridge |

### Pulse Verification Contract (Spec §13)

> **Ownership ruling:** Sirus **generates** pulses; Helios **verifies** them (`.github/instructions/copilot-instructions.md`: "Do not put verification logic here"). The canonical six-check contract and `VerificationResult` enum are **shared types owned by Ouroboros** — Sirus must not implement runtime verification. Sirus's only obligation is to prove its generated pulses round-trip against the canonical Ouroboros signing material.

| Component | Owner | Status | Sprint | Notes |
|---|---|---|---|---|
| `PulseGenerator` (signing side) | Sirus | ✅ | S-01/S-02 | Signs via Ouroboros `ContextPulseSigningMaterial::build()` |
| `VerificationResult` enum | Ouroboros | ⏳ | S-07 | Shared type — import from Ouroboros, never redefine in Sirus |
| `PulseVerifier` six-check | Helios / Ouroboros | ⏳ | S-07 | **Not built in Sirus by design.** S-07 adds a generate↔verify round-trip *test* only |

### API, Capability, and Telemetry

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| REST `/device` | `src/api/SirusRESTController.php` | ✅ | S-01 | Registered |
| REST `/context` | `src/api/SirusRESTController.php` | ✅ | S-01 | Registered |
| REST `/pulse` | `src/api/SirusRESTController.php` | ✅ | **S-07** | Registered with HttpOnly/SameSite=Strict pulse cookie + metadata body; integration-tested in `RestApiTest` |
| REST `/identity` | `src/api/SirusRESTController.php` | ✅ | **S-07** | Registered; delegates to `IdentityResolver`; integration-tested |
| REST `/session` | `src/api/SirusRESTController.php` | ✅ | **S-07** | Registered; Sirus-native session check (not `session_status()`); integration-tested |
| REST `/client-report` | `src/api/SirusRESTController.php` | ✅ | **S-07** | Registered; delegates to `ClientTelemetry`; integration-tested |
| `CapabilityEngine` | `src/core/CapabilityEngine.php` | ✅ | S-07 | `resolve(SirusContext): array`; `CapabilityEngineTest` added |
| `AuthorityResolver` | `src/core/AuthorityResolver.php` | ✅ | S-01/S-07 | Built in S-01; `AuthorityResolverTest` added in S-07 |
| `ClientTelemetry` | `src/core/ClientTelemetry.php` | ✅ | S-07 | `ClientTelemetryTest` added |

### Event / Mitigation / Signal Subsystem (Observability — Spec §6)

> The runtime signal-evaluation and mitigation pipeline that backs the trust signals `TrustEngine` consumes. Built earlier; comprehensive unit coverage landed via PRs #80/#81 ("12 previously uncovered classes"). Tracked here so the subsystem is visible against the S-06 observability theme.

| Component | File | Status | Sprint | Notes |
|---|---|---|---|---|
| `SirusSignalEvaluator` | `src/helpers/SirusSignalEvaluator.php` | ✅ | S-06 | `SirusSignalEvaluatorTest` |
| `SirusMitigationCoordinator` | `src/services/SirusMitigationCoordinator.php` | ✅ | S-06 | `SirusMitigationCoordinatorTest` |
| `SirusMitigationRuleEngine` | `src/helpers/SirusMitigationRuleEngine.php` | ✅ | S-06 | `SirusMitigationRuleEngineTest` |
| `SirusRuleConfig` | `src/helpers/SirusRuleConfig.php` | ✅ | S-06 | `SirusRuleConfigTest` |
| `SirusImpactScorer` | `src/helpers/SirusImpactScorer.php` | ✅ | S-06 | `SirusImpactScorerTest` |
| `SirusPriorityScorer` | `src/helpers/SirusPriorityScorer.php` | ✅ | S-06 | `SirusPriorityScorerTest` |
| `SirusRateLimit` | `src/helpers/SirusRateLimit.php` | ✅ | S-06 | `SirusRateLimitTest`; REMOTE_ADDR validated before rate-limit keying |
| `SirusEventAggregator` | `src/core/SirusEventAggregator.php` | ✅ | S-06 | `SirusEventAggregatorTest` |
| `SirusEventRepository` | `src/core/SirusEventRepository.php` | ✅ | S-06 | `SirusEventRepositoryTest` |
| `SirusRuleHitRepository` | `src/core/SirusRuleHitRepository.php` | ✅ | S-06 | `SirusRuleHitRepositoryTest` |
| `SirusMitigationActionRepository` | `src/core/SirusMitigationActionRepository.php` | ✅ | S-06 | `SirusMitigationActionRepositoryTest` |
| `SirusEventController` (REST) | `src/api/SirusEventController.php` | ✅ | S-06 | `SirusEventControllerTest` |
| `SirusDirectiveController` (REST) | `src/api/SirusDirectiveController.php` | ✅ | S-06 | `SirusDirectiveControllerTest` |
| `SirusDeviceParser` | `src/services/SirusDeviceParser.php` | ✅ | S-06 | `SirusDeviceParserTest`; Matomo wrapper, empty output without optional dep |
| `HeliosClient` | `src/integrations/HeliosClient.php` | ✅ | S-06 | `HeliosClientTest` |
| `SirusNetworkSettingsPage` | `src/admin/SirusNetworkSettingsPage.php` | ✅ | S-06 | `SirusNetworkSettingsTest` |

---

## Scoreboard — Test Coverage

| Test File | Component | Status | Sprint | Tests |
|---|---|---|---|---|
| `ContextEngineTest.php` | `ContextEngine` | ✅ | S-01 | 6 |
| `ContextCacheTest.php` | `ContextCache` | ✅ | S-01 | — |
| `SirusContextTest.php` | `SirusContext` | ✅ | S-01 | — |
| `NetworkContextBrokerTest.php` | `NetworkContextBroker` | ✅ | S-01 | 10 tests: issue/verify round-trip, tamper detection, wrong secret, expired |
| `IdentityResolverTest.php` | `IdentityResolver` | ✅ | S-01 | — |
| `DeviceContinuityTest.php` | `DeviceContinuity` | ✅ | S-01 | +2 STEP_UP_REQUIRED tests |
| `DeviceRecordTest.php` | `DeviceRecord` | ✅ | S-01 | — |
| `TrustEngineTest.php` | `TrustEngine` | ✅ | **S-02** | 18 |
| `PulseGeneratorTest.php` | `PulseGenerator` | ✅ | **S-02** | 20 |
| `TrustResolverTest.php` | `TrustResolver` | ✅ | **S-02** | 15 |
| `EnvironmentResolverTest.php` | `EnvironmentResolver` | ✅ | **S-07** | Client signals take precedence; UA/Matomo is fallback only; network filter; EnvironmentRecord output |
| `DeviceMatcherTest.php` | `DeviceMatcher` | ✅ | **S-02** | 22 |
| `ConsentManagerTest.php` | `ConsentManager` | ✅ | S-02 | 16 tests — cascade order, privacy-first hard default, anonymous user, invalid meta, multisite isolation, history, purpose consent |
| `StepUpPolicyTest.php` | `StepUpPolicy` | ✅ | **S-02** | 17 |
| `AuthorityResolverTest.php` | `AuthorityResolver` | ✅ | **S-07** | Authority trust paths and precedence coverage |
| `CapabilityEngineTest.php` | `CapabilityEngine` | ✅ | **S-07** | Named capability issuance + filter coverage |
| `ClientTelemetryTest.php` | `ClientTelemetry` | ✅ | **S-07** | Report/aggregation/pruning, no post-meta storage assertions |
| `PulseRoundTripTest.php` | `PulseGenerator` ↔ Ouroboros | ✅ | **S-07** | Generate→verify against canonical Ouroboros signing material |
| `EnvironmentRecordTest.php` | `EnvironmentRecord` | ✅ | **S-07** | Privacy invariants at construction |
| `RestApiTest.php` (integration) | `SirusRESTController` | ✅ | **S-07** | Covers all six REST endpoints (§18–19) |
| ~~`ContextBootExceptionTest.php`~~ | `ContextBootException` | 🗑️ | — | **Removed from Sirus scope** — type owned by Ouroboros since S-04 |
| ~~`ContextPulseTest.php`~~ | `ContextPulse` | 🗑️ | — | **Removed from Sirus scope** — type owned by Ouroboros since S-04 |

---

## Scoreboard — Ouroboros Migration

The following provisional types must be removed when `sparxstar-ouroboros-integrity` ships.

| Provisional file | Canonical owner | Migration status |
|---|---|---|
| `src/exceptions/ContextBootException.php` | `sparxstar-ouroboros-integrity` | ✅ Migrated — Ouroboros CO-001 |
| `src/dto/ContextPulse.php` | `sparxstar-ouroboros-integrity` | ✅ Migrated — Ouroboros CO-001 |

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
- [x] `DeviceMatcher` — spec §14.3: `STRONG_MATCH_THRESHOLD=0.8` / `WEAK_MATCH_THRESHOLD=0.6`; `MatchResult` enum (STRONG/WEAK/NO); `classify()` static method; `hardware_concurrency` key (not `hardware_conc`)
- [x] `ConsentManager` — three-level cascade (user meta → site option → deny), purpose consent, append-only history
- [x] `StepUpPolicy` — uses `ContextPulse` + `ResourceSensitivity` enum; `requiresStepUp()`/`getRequiredLevel()` frozen boundary; `TRUST_LEVEL_STEP_UP_REQUIRED` pre-flag; 17 tests in `StepUpPolicyTest`
- [x] `NetworkContextBroker` — `issueToken(context, secret)` / `verifyToken(token, secret)` — explicit secret; `tl`/`ts` round-trip; absent `ts` derived from `tl`; 10 tests
- [x] README.md — full spec alignment documentation
- [x] PUBLIC_API.md — public surface document for cross-repo consumers

---

### S-02 — Test Coverage for S-01 Components (Complete)

> Every S-01 component built without a unit test needs one. PHPUnit ^11.5.50, extends `SirusTestCase`.

- [x] `TrustEngineTest` — 18 tests: frozen algorithm, all signal combos, clamping to [0.0, 1.0], level mapping
- [x] `PulseGeneratorTest` — 20 tests: key validation, pulse fields, no identity_id, TTL = issued_at + default, explicit `$now`/`$ttlSeconds` honoured, sig is 64-char hex
- [x] `TrustResolverTest` — 15 tests: all credential bases, drift deduction, new-session deduction, combined, clamping
- [x] ~~`ContextPulseTest`~~ — **removed from scope**; `ContextPulse` is owned by Ouroboros since S-04
- [x] ~~`ContextBootExceptionTest`~~ — **removed from scope**; `ContextBootException` is owned by Ouroboros since S-04
- [x] `EnvironmentResolverTest` — **delivered in S-07** (client-first signals, UA/regex fallback, network filter)
- [x] `DeviceMatcherTest` — ✅ COMPLETE (22 tests: classify() three-way branching, STRONG/WEAK/NO_MATCH cases, boundary at 0.8 and 0.6, scoreHash, scoreComponents, hardware_concurrency key validation)
- [x] `ConsentManagerTest` — cascade order (user→site→deny), privacy-first hard default, anonymous user skip, invalid meta fallthrough, multisite isolation, history append-only, purpose consent (16 tests ✅)
- [x] `StepUpPolicyTest` — ✅ COMPLETE (17 tests — includes STEP_UP_REQUIRED trust level pre-flag)

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

### S-04 — Ouroboros Integration (Complete — this PR)

> Replace provisional mirrors with Ouroboros package imports.

**Prerequisite:** `sparxstar-ouroboros-integrity` package published to Packagist or private registry.

- [x] Add `sparxstar-ouroboros-integrity` to `composer.json` `require`
- [x] Delete `src/exceptions/ContextBootException.php` (provisional)
- [x] Delete `src/dto/ContextPulse.php` (provisional)
- [x] Update all import statements to use Ouroboros namespace
- [ ] Import `AgreementResult` enum from Ouroboros (remove any local copy)
- [ ] Import `ValidationHelper` from Ouroboros (remove any local copy)
- [x] Run `composer run test` to confirm no regressions
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

### S-07 — Spec Completeness and Helios Integration Readiness (Complete except Ouroboros-blocked `VerificationResult` import)

> Close the spec components that exist in the codegen order (Spec §24) but were never built, complete the REST surface Helios consumes, and finish the test coverage S-02 left dangling. Theme: make Sirus a complete, integration-ready producer for the edge.

**Prerequisite for the verification items:** Ouroboros must export `VerificationResult` and the canonical `PulseVerifier` contract. If not yet published, item 1 ships the round-trip test against `ContextPulseSigningMaterial` only and the enum import is deferred.

**P0 — REST surface for Helios (Spec §18–19)**

- [x] Register `POST /sparxstar/v1/pulse` — request a fresh signed `ContextPulse` (HttpOnly cookie + `{ pulse_id, expires_at, trust_level }` body); server-to-server / Helios re-verification only, not browser-facing
- [x] Register `GET /sparxstar/v1/identity` — resolve current identity tier
- [x] Register `GET /sparxstar/v1/session` — session status
- [x] Register `POST /sparxstar/v1/client-report` — telemetry/error reporting (never stored in post meta, §23)
- [x] Integration tests in `tests/integration/RestApiTest.php` covering all six endpoints

**P0 — Pulse generate↔verify compatibility (Spec §13)**

- [x] Add `PulseRoundTripTest` — sign a pulse via `PulseGenerator`, confirm it verifies against the canonical Ouroboros signing material (tamper / expiry / malformed cases)
- [ ] Import `VerificationResult` from Ouroboros when available — **never redefine in Sirus**
- [x] **Do not** add runtime verification logic to Sirus (per `copilot-instructions.md`); contradiction **resolved** — `PulseVerifier` ownership claim removed from that file; it now reads only "Sirus generates, Helios verifies"

**P1 — EnvironmentRecord DTO (Spec §7, §23)**

- [x] Build `src/core/EnvironmentRecord.php` with all spec fields, privacy enforced at construction (IP last-octet zeroed, region-level location only, no exact coords without grant, `is_bot`, `time_zone`, brand/model/versions, `captured_at`)
- [x] **Client-first** (`AGENTS.md`): populate the record from client-submitted signals; never derive browser/OS/device by parsing User-Agent — Matomo is fallback/enrichment only
- [x] Have `EnvironmentResolver` return an `EnvironmentRecord`; keep the existing flat accessors as thin wrappers for backward compat
- [x] `EnvironmentResolverTest` + `EnvironmentRecordTest` (privacy invariants asserted at construction; client signals take precedence over UA fallback)

**P1 — Close S-02 test debt for built components**

- [x] `AuthorityResolverTest` — multi-authority aggregation, most-restrictive conflict outcome (§17)
- [x] `CapabilityEngineTest` — named capability issuance from context (§23 codegen)
- [x] `ClientTelemetryTest` — report/aggregation/pruning, no post-meta storage (§23)

**Acceptance criteria:**

- `composer run test` and `composer run test:unit` pass with no failures or deprecations
- `composer run analyze` clean at the configured PHPStan level (currently mid-migration to Level 6 per S-05)
- All six REST endpoints from Spec §18–19 are registered and integration-tested
- No verification runtime added to Sirus; `VerificationResult` (if used) is imported from Ouroboros
- `copilot-instructions.md` PulseVerifier ownership contradiction resolved

---

## How to Use This Tracker

1. **On sprint start** — move items from `🔲` to a named sprint column and assign owners.
2. **On component build** — change `🔲 → 🟡` and add the file reference.
3. **On test pass** — change `🟡 → ✅`.
4. **On Ouroboros ship** — execute S-04 and flip provisional items to ✅.
5. **On UEC removal** — execute S-03 and remove the UEC Legacy rows from this tracker.

---

*Last updated: 2026-06-07 | Spec version: Sirus Context Engine Spec v3.0 (PAM-002 normative overlay)*
