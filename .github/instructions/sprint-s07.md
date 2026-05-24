SPARXSTAR Sirus — Sprint S-07 Copilot Instructions
==================================================

Spec Completeness & Helios Integration Readiness
------------------------------------------------

Read this alongside `copilot-instructions.md` and the Sirus Context Engine Spec v3.0.
Every task below is grounded in a specific spec section. Do not invent scope.

Authoritative sources (in this order)
--------------------------------------

Ground every decision in these, not in any automated PR review:

1. `docs/specs/` — Sirus Context Engine Spec v3.0, Platform Integrity Map, Platform Overview.
2. Root specs — `PAM-002.md`, `SPARXSTAR_PAM-001_Consolidated.pdf`.
3. Instruction files — `.github/instructions/copilot-instructions.md` and `AGENTS.md` (repo root).

Two principles from `AGENTS.md` / `.github/instructions.md` govern this sprint and
override convenience:

- **The client is the authoritative source of truth for environment.** The backend
  MUST NOT guess OS / browser / device by parsing User-Agent strings. Matomo
  DeviceDetector is enrichment/fallback only; client-submitted signals are primary.
- **Isolate responsibilities.** REST routing lives only in the REST controller (no
  SQL there); DB access in a DB class; external calls in a service class; caching in
  the cache helper. Sanitize/validate every external input; inject dependencies.

Goal of this sprint
--------------------

Sirus claims spec v3.0 alignment, but three things in the spec's code-generation
order (§24) were never built, and several built components have no tests. S-07
makes Sirus a complete, integration-ready context **producer** for the edge:
finish the REST surface Helios consumes (the four missing endpoints are mandated by
Spec §19 — this is spec completion, not new scope), prove pulses round-trip, add the
EnvironmentRecord DTO with privacy enforced at construction, and close the
test debt S-02 left open.


Hard ownership rule for this sprint (read first)
------------------------------------------------

Sirus **generates** pulses. Helios **verifies** them. This is settled by **PAM-002
§10.2 / §3.6**: *"PulseGenerator in Sirus must populate all fields. PulseVerifier in
Helios must validate all fields."* (PAM-002 §10.1 records Helios PR #18 shipping its
`PulseVerifier`.) Do **NOT** add pulse verification runtime to this repository. The
canonical six-check `PulseVerifier` and the `VerificationResult` enum (Spec §13) are
**owned downstream (Helios) with shared types in Ouroboros** — import, never
redefine. Sirus's only verification obligation is a **round-trip test** proving its
generated pulses validate against the canonical signing material.

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
- **Separation of concerns** (`.github/instructions.md` Principle I): routing and
  `WP_REST_Request`/`WP_REST_Response` handling stay in `SirusRESTController`. No SQL
  and no business logic in the controller — delegate to the existing services
  (`PulseGenerator`, `IdentityResolver`, `ClientTelemetry`, repositories). Sanitize
  and validate every payload field; use permission callbacks; inject collaborators
  via the constructor rather than instantiating them inline.

Tests: extend `tests/integration/RestApiTest.php` to cover all six endpoints
(success + permission-denied + malformed input).

---

Task 2 (P0) — Pulse generate↔verify round-trip (Spec §13)
---------------------------------------------------------

- Add `tests/unit/PulseRoundTripTest.php`. Sign a pulse through `PulseGenerator`,
  then assert it verifies against the canonical signing material
  `ContextPulseSigningMaterial::build()` (**PAM-002 §3.5**) with correct
  HMAC-SHA256, and that tampering, expiry, future-skew, malformed `device_id`,
  out-of-enum `trust_level`, and out-of-bounds `trust_score` each fail. These six
  checks mirror Spec §13 but are exercised as a **test**, not Sirus runtime code.
- Assert the pulse carries the full **canonical field set (PAM-002 §3.3)** and that
  the restored P2 fields validate per PAM-002 §10.2: `behavior_flags` is an array,
  `geo_zone` is a non-empty string, `network_effective_type` is a valid enum value,
  `session_duration` is a non-negative int. (`PulseGenerator` already populates these.)
- Import `VerificationResult` from `Starisian\Sparxstar\Infrastructure\...` when
  Ouroboros exports it. If not yet published, gate that assertion and rely on
  raw HMAC comparison for now. **Never define `VerificationResult` in Sirus.**

---

Task 3 (P1) — EnvironmentRecord DTO with privacy at construction (Spec §7, §23)
-------------------------------------------------------------------------------

`EnvironmentResolver::resolve()` returns a flat 4-field array. Spec §7 requires a
rich DTO and §23 requires privacy invariants enforced at the boundary.

**Client-first rule (non-negotiable — `AGENTS.md`, `.github/instructions.md`):** the
DTO is populated from **client-submitted signals** (the `visitorId`, screen
resolution, timezone, Network Information API fields, and the client's own
OS/browser/device reports). The server MUST NOT derive `browser_name`, `os`, or
`device_type` by parsing the User-Agent. Matomo DeviceDetector is a **fallback/
enrichment only** when a client signal is absent, never the authority. Server-issued
values remain authoritative only for `ip_address` (anonymized) and `device_id`.

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
- Have `EnvironmentResolver` build the `EnvironmentRecord` from client signals first,
  Matomo only as fallback. Keep the existing flat string accessors (`getBrowserName()`,
  etc.) as thin wrappers so nothing breaks.
- Do **not** change the frozen `StarUserEnv` public signatures (§23).

Tests:
- `tests/unit/EnvironmentRecordTest.php` — privacy invariants asserted at
  construction (full IP rejected/zeroed, exact coords stripped, no PII at lower tiers).
- `tests/unit/EnvironmentResolverTest.php` — client signals take precedence over
  the UA-derived fallback; Matomo/regex only fills gaps; network type filtered.

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
- All six Spec §18–19 endpoints registered, each with integration coverage in
  `tests/integration/RestApiTest.php` for the success path, permission-denied,
  and malformed input (no endpoint merges without all three).
- No verification runtime added to Sirus; `VerificationResult`, if used, imported from Ouroboros.
- `EnvironmentRecord` enforces privacy at construction; `StarUserEnv` signatures unchanged.
- `copilot-instructions.md` PulseVerifier ownership contradiction resolved by
  **removing `PulseVerifier` from the "What this repository owns" list** (line ~51)
  and keeping the "Helios verifies / do not put verification logic here" rule
  (line ~71) as the single source of truth.
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
