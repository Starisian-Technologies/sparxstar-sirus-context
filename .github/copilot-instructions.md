# Copilot Review Instructions

## Reference repositories (read via MCP)

Before reviewing any PR, read these repos:
- ADR Registry: Starisian-Technologies/sparxstar-architecture-governance-registry
- Product Specs: Starisian-Technologies/sparxstar-product-specification-registry
- Coding Standards: Starisian-Technologies/starisian-technologies-coding-standards
- Enforcement Workflows: Starisian-Technologies/sparxstar-code-conformance
- Contracts: Starisian-Technologies/sparxstar-contracts-registry
- Claude PR Review: Starisian-Technologies/sparxstar-claude-pr-review

## Review checklist

Flag any PR that:
- Contradicts an ADR or invariant
- Assumes an answer to an open question (OQ in OPEN state)
- Violates a coding standard
- Changes a contract interface without updating `docs/contracts/README.md`
- Changes behavior that contradicts the product spec
- Adds code with no spec backing it
- Adds pulse verification logic to Sirus (Sirus generates; Helios verifies — this boundary is absolute)
- Redefines `ContextBootException`, `ContextPulse`, `AgreementResult`, `ValidationHelper`, or `VerificationResult` locally (all are owned by Ouroboros)
- Changes `StarUserEnv` method signatures (frozen permanent contract)
- Stores IP addresses without last-octet zeroing
- Stores telemetry in post meta (prohibited — §23)

You are a reviewer, not the authority. Flag and explain. The owner decides.

---

## Repo-specific context

### What this repository is

Sirus is the context engine. Before identity is established, before authentication runs,
before any application logic executes — Sirus establishes the environment.

> Who is present. On what device. In what environment. Under what authority.

Sirus **produces context**. It does not make authorization decisions (Helios).
It does not enforce governance (Mehns). It does not verify pulses (Helios verifies; Sirus generates).

### Platform position

```
Ouroboros → Helios → Sirus → Sky → Mehns → Dheghom
```

### Ouroboros canonical namespaces

- Platform constants: `Starisian\Sparxstar\Infrastructure\Constants\Platform`
- DTOs: `Starisian\Sparxstar\Infrastructure\DTOs\{ContextPulse, TrustLevelPrimitive, ...}`
- Utils: `Starisian\Sparxstar\Infrastructure\Utils\{ContextPulseSigningMaterial, PulseGenerator, ...}`
- Exceptions: `Starisian\Sparxstar\Infrastructure\Exceptions\ContextBootException`
- Never use `\Signing\`, `\Primitives\`, or any other namespace — they do not exist

### Hard rules

- `declare(strict_types=1)` in every file
- Namespace: `Starisian\Sparxstar\Sirus\`
- Deployed as WordPress mu-plugin — cannot be deactivated
- MUST NEVER call `wp_set_auth_cookie()` or issue JWTs
- MUST NEVER query Dheghom or any external plugin directly
- `ContextEngine::current()` returns valid `SirusContext` or throws `ContextBootException` — never null, never partial
- `ContextBootException` MUST NEVER be caught and swallowed
- `device_id` is ALWAYS server-issued — never JS fingerprint alone
- IP addresses stored with last octet zeroed: `192.168.1.0`
- `ContextPulse` NEVER contains identity claims
- `StarUserEnv` signatures are FROZEN — must never change

### Trust score algorithm (frozen)

```
base = 1.0
device drifting:   -0.3
geo mismatch:      -0.2
new session:       -0.1
recent failures:   -0.3
clamped to [0.0, 1.0]
```
