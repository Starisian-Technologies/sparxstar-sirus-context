# Sirus — Role and Boundary

## Owns

- `ContextEngine` — `current()` accessor; CLI system context boot path
- `SirusContext` DTO — the primary context object consumed by every downstream layer
- `TrustEngine` — frozen trust score algorithm (base 1.0, deductions clamped to [0.0, 1.0])
- `TrustResolver` — credential-level base score + drift/session deductions
- `StepUpPolicy` — Level 3 always; Level 2 when `trust_score < 0.7`
- `PulseGenerator` — HMAC-SHA256 pulse signing via Ouroboros `ContextPulseSigningMaterial`
- `DeviceContinuity` — server-issued `device_id`, fingerprint pipeline, session recovery
- `DeviceMatcher` — fingerprint scoring thresholds (STRONG ≥ 0.8, WEAK ≥ 0.6)
- `DeviceRecord` / `DeviceRepository` — device record persistence
- `EnvironmentResolver` / `EnvironmentRecord` — client-first environment record; Matomo is fallback only
- `IdentityResolver` — five-tier identity resolution
- `AuthorityResolver` — multi-authority aggregation
- `ConsentManager` — user→site→deny cascade; purpose consent; append-only audit history
- `NetworkContextBroker` — cross-domain handoff token (`tl`/`ts` payload)
- `CapabilityEngine` — named capability issuance from context
- `ClientTelemetry` — telemetry report ingestion and aggregation
- `StarUserEnv` facade — frozen public surface (UEC backward compatibility); signatures are permanent
- `UECCompatibilityShim` — namespace alias bridge for legacy callers
- Signal/mitigation subsystem: `SirusSignalEvaluator`, `SirusMitigationCoordinator`, `SirusMitigationRuleEngine`, `SirusImpactScorer`, `SirusPriorityScorer`, `SirusRateLimit`, `SirusEventAggregator`, `SirusEventRepository`, `SirusRuleHitRepository`, `SirusMitigationActionRepository`
- REST surface (Spec §18–19): `/sparxstar/v1/device`, `/context`, `/pulse`, `/identity`, `/session`, `/client-report`
- API contract files: `docs/contracts/sirus-api-contract.v1.json`, `docs/contracts/sirus-api-seed.v1.json`

## Does not own

- **Pulse verification** — Sirus generates; Helios verifies. No verification runtime belongs here.
- **Agreement evaluation** (proceed/deny) — Helios
- **KV revocation** — Helios
- **Governance policy enforcement** — Mehns
- **Persistence layer** — Dheghom
- **Draft accumulation** — Sky
- `ContextBootException` — canonical owner is Ouroboros (`Starisian\Sparxstar\Infrastructure\Exceptions`); import only
- `ContextPulse` DTO — canonical owner is Ouroboros (`Starisian\Sparxstar\Infrastructure\DTOs`); import only
- `ContextPulseSigningMaterial` — canonical owner is Ouroboros; consumed for signing material, not redefined
- `VerificationResult`, `AgreementResult`, `ValidationHelper` — Ouroboros; import when available; never redefine

## Contracts produced

- `docs/contracts/sirus-api-contract.v1.json` — OpenAPI 3.1 machine-readable REST contract for all six `sparxstar/v1` endpoints; consumed by Helios, Dheghom, Sky, and compatibility callers
- `docs/contracts/sirus-api-seed.v1.json` — canonical seed payloads for downstream integration tests and smoke tests

## Consumed by

- **Helios** — consumes `SirusContext`, verifies `ContextPulse` signatures, calls `/pulse` and `/identity`
- **Dheghom** — depends on `device_id` and `trust_level` established by Sirus for Triple Binding
- **Sky** — receives context via `StarUserEnv` facade and direct `ContextEngine::current()`
- **Mehns** — evaluates governance against `authority_id` and `trust_level` from `SirusContext`
- Any WordPress plugin or theme that calls `StarUserEnv::*()` — frozen facade, never changes
