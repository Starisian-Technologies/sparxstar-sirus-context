SPARXSTAR Sirus — Sprint S-07 Copilot Instructions
==================================================

Spec Completeness & Helios Integration Readiness
------------------------------------------------

Read this alongside `copilot-instructions.md` and the Sirus Context Engine Spec v3.0.
Every task below is grounded in a specific spec section. Do not invent scope.

Goal of this sprint
--------------------

Sirus claims spec v3.0 alignment, but three things in the spec's code-generation
order (§24) were never built, and several built components have no tests. S-07
makes Sirus a complete, integration-ready context **producer** for the edge:
finish the REST surface Helios consumes, prove pulses round-trip, add the
EnvironmentRecord DTO with privacy enforced at construction, and close the
test debt S-02 left open.

Hard ownership rule for this sprint (read first)
------------------------------------------------

Sirus **generates** pulses. Helios **verifies** them. Do **NOT** add pulse
verification runtime to this repository (see `copilot-instructions.md`, "What
this repository does NOT own"). The canonical six-check `PulseVerifier` and the
`VerificationResult` enum (Spec §13) are **shared types owned by Ouroboros** —
import them, never redefine them. Sirus's only verification obligation is a
**round-trip test** proving its generated pulses validate against the canonical
Ouroboros signing material.

> Note: `copilot-instructions.md` currently contradicts itself — line ~51 lists
> `PulseVerifier` under "What this repository owns" while line ~71 says verification
> does not live here. Resolve this in S-07: remove `PulseVerifier` from the "owns"
> list. The "Helios verifies" rule is correct.

---

Task 1 (P0) — Complete the REST surface (Spec §18–19)
-----------------------------------------------------

`src/api/SirusRESTController.php` registers only `/device` and `/context`. Add the
remaining four endpoints from Spec §19. Match the existing registration style in
that file (namespace `sparxstar/v1`, permission callbacks, sanitized args).

- `POST /sparxstar/v1/pulse` (Spec §18) — request a fresh signed `ContextPulse`.
  Request: `{ device_id, resource_sensitivity, request_id }`.
  Response: signed `ContextPulse` as an **HttpOnly, SameSite=Strict** cookie,
  plus body metadata `{ pulse_id, expires_at, trust_level }`. This is for Helios
  origin-side / server-to-server re-verification — **not** for the browser JS
  client. Delegate signing to the existing `PulseGenerator`.
- `GET /sparxstar/v1/identity` — resolve current identity tier via `IdentityResolver`.
- `GET /sparxstar/v1/session` — session status.
- `POST /sparxstar/v1/client-report` — telemetry/error reporting via `ClientTelemetry`.

Constraints:
- Never expose Helios REST endpoints to client JS (§23).
- Client telemetry must never be stored in WordPress post meta (§23).
- The pulse must never carry `identity_id` (§23) — `PulseGenerator` already enforces this; do not add identity to the endpoint payload.

Tests: extend `tests/integration/RestApiTest.php` to cover all six endpoints
(success + permission-denied + malformed input).

---

Task 2 (P0) — Pulse generate↔verify round-trip (Spec §13)
---------------------------------------------------------

- Add `tests/unit/PulseRoundTripTest.php`. Sign a pulse through `PulseGenerator`,
  then assert it verifies against the canonical Ouroboros
  `ContextPulseSigningMaterial::build()` output (correct HMAC-SHA256), and that
  tampering, expiry, future-skew, malformed `device_id`, out-of-enum
  `trust_level`, and out-of-bounds `trust_score` each fail. These six checks
  mirror Spec §13 but are exercised as a **test**, not Sirus runtime code.
- Import `VerificationResult` from `Starisian\Sparxstar\Infrastructure\...` when
  Ouroboros exports it. If not yet published, gate that assertion and rely on
  raw HMAC comparison for now. **Never define `VerificationResult` in Sirus.**

---

Task 3 (P1) — EnvironmentRecord DTO with privacy at construction (Spec §7, §23)
-------------------------------------------------------------------------------

`EnvironmentResolver::resolve()` returns a flat 4-field array. Spec §7 requires a
rich DTO and §23 requires privacy invariants enforced at the boundary.

- Build `src/core/EnvironmentRecord.php` — `final`, `declare(strict_types=1)`,
  readonly constructor with the §7 fields: `environment_id`, `browser_name`,
  `browser_version`, `os`, `os_version`, `device_type`, `device_brand`,
  `device_model`, `network_effective_type`, `ip_address`, `location` (array),
  `time_zone`, `is_bot`, `captured_at`.
- Enforce at construction:
  - IP stored with last octet zeroed (`192.168.1.0`) — reuse `IpAnonymizer`.
  - `location` is region-level only (country, region, approx_lat, approx_lng);
    never exact coordinates without an explicit per-session grant.
  - At anonymous / device tiers, no PII captured or inferred.
- Have `EnvironmentResolver` return an `EnvironmentRecord`. Keep the existing flat
  string accessors (`getBrowserName()`, etc.) as thin wrappers so nothing breaks.
- Do **not** change the frozen `StarUserEnv` public signatures (§23).

Tests: `tests/unit/EnvironmentResolverTest.php` (UA parse, fallback regex, network
filter) and `tests/unit/EnvironmentRecordTest.php` (privacy invariants asserted at
construction — full IP rejected/zeroed, exact coords stripped).

---

Task 4 (P1) — Close S-02 test debt for built components
-------------------------------------------------------

Each is already built in `src/` but has no test. Extend `SirusTestCase`,
PHPUnit ^11.5.

- `tests/unit/AuthorityResolverTest.php` — multi-authority aggregation; the
  most-restrictive outcome wins on conflict (§17).
- `tests/unit/CapabilityEngineTest.php` — `resolve(SirusContext): array` named
  capability issuance from context.
- `tests/unit/ClientTelemetryTest.php` — report/aggregation/pruning; assert
  telemetry is never written to post meta (§23).

---

Definition of done
------------------

- `composer run test` and `composer run test:unit` pass — no failures, no deprecations.
- `composer run analyze` clean at the configured PHPStan level (S-05 is mid-migration to Level 6; do not regress).
- `composer run lint` clean (PHPCS PSR-12).
- All six Spec §19 endpoints registered and integration-tested.
- No verification runtime added to Sirus; `VerificationResult`, if used, imported from Ouroboros.
- `EnvironmentRecord` enforces privacy at construction; `StarUserEnv` signatures unchanged.
- `copilot-instructions.md` PulseVerifier ownership contradiction resolved.
- `TRACKER.md` S-07 rows flipped 🔲 → 🟡 → ✅ as work lands.

Non-negotiable guardrails (from §23 and `copilot-instructions.md`)
------------------------------------------------------------------

- `declare(strict_types=1)` in every file; namespace `Starisian\Sparxstar\Sirus\`.
- `ContextEngine::current()` returns a valid `SirusContext` or throws
  `ContextBootException` — never null, never partial; never swallow that exception.
- `device_id` is always server-issued.
- `ContextPulse` never contains identity claims; never use it as a source of `identity_id`.
- Never call `wp_set_auth_cookie()`, issue JWTs, or query Dheghom/external plugins.
- Never redefine Ouroboros-owned types (`ContextPulse`, `ContextBootException`,
  `VerificationResult`, `AgreementResult`, `ValidationHelper`).
- `?->` wherever a nullable is consumed.
