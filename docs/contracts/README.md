# Sirus Contracts

This directory contains the machine-readable API contracts produced by the Sirus Context Engine.
These files are synced to `Contracts/sirus/` in the contracts registry on every merge to main
that changes this directory.

## sirus-api-contract.v1.json

**Type:** OpenAPI 3.1 contract
**Stability:** `s07-integration-ready`
**Owner:** `Starisian\Sparxstar\Sirus`

Defines all six REST endpoints under `/wp-json/sparxstar/v1/` that Helios, Dheghom, Sky,
and compatibility callers consume:

| Endpoint | Method | Description |
|---|---|---|
| `/device` | POST | Register or resolve a device record |
| `/context` | GET | Current `SirusContext`; optional `device_id` must match |
| `/pulse` | POST | Issue a signed `ContextPulse`; returns HttpOnly cookie + `{pulse_id, expires_at, trust_level}` |
| `/identity` | GET | Resolve current identity tier |
| `/session` | GET | Current session status |
| `/client-report` | POST | Telemetry/error ingestion; never stored in post meta |

Security scheme: `wpRestNonce` (WordPress REST API nonce, `X-WP-Nonce` header).

## sirus-api-seed.v1.json

**Type:** Seed payload fixture
**Stability:** `s07-integration-ready`

Canonical request/response payloads for all six endpoints. Used by downstream repos for:
- Integration test assertions (known-good fixture)
- Smoke test baselines
- Mock server setup

Seed payloads are derived from the spec — not from current code output — so they remain
stable as the implementation evolves toward the spec.
