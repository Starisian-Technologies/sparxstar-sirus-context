# SPARXSTAR — sparxstar-sirus-context
# Copilot Instructions

## What This Repository Is

Sirus is the context engine of the SPARXSTAR platform. It runs before
identity is established, before authentication runs, before any application
logic executes. It determines what is happening right now: who is present,
on what device, in what environment, under what authority.

Sirus produces context. It does not make authorization decisions (Helios).
It does not enforce governance (Mehns).

The following files provide the full tech specs for the project:

- the [full technical specs](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sirus_Context_Engine_Spec_v3.0)
- The [Platform Integrity Map](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sparxstar_Platform_Integrity_Map_v1.0)
- The [SPARXSTAR Platform Overview](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sparxstar_Platform_Overview_v1.0.-,docx,-.pdf)

## What This Repository Owns

- ContextEngine — context creation and current() accessor
- SirusContext DTO — the primary output of the context engine
- ContextPulse generation and signing (PulseGenerator)
- PulseVerifier — six-check canonical verification contract
- TrustEngine — trust state and trust score computation
- DeviceContinuity — server-issued device_id, fingerprint, session recovery
- DeviceMatcher — fingerprint scoring thresholds
- EnvironmentResolver — browser, OS, network, location via Matomo
- IdentityResolver — five-tier identity resolution
- AuthorityResolver — governance scope, multi-authority aggregation
- ConsentManager — technical consent, purpose consent, consent history
- PulseGenerator — signed ContextPulse for Helios consumption
- StepUpPolicy — step-up logic (Level 2 trust < 0.7, Level 3 always)
- NetworkContextBroker — cross-domain handoff
- UEC Compatibility Shim — StarUserEnv frozen public interface

## What This Repository Does NOT Own

- Agreement evaluation (proceed/deny) — that is Helios
- KV revocation reads/writes — that is Helios
- Governance policy evaluation — that is Mehns
- Structured field persistence — that is Dheghom
- Draft accumulation — that is Sky
- Pulse VERIFICATION — Sirus generates and signs.
  Helios verifies. Do not put verification logic here.

## Hard Rules

- declare(strict_types=1) in every file
- Namespace: Starisian\Sparxstar\Sirus\
- Sirus must be deployed as a WordPress mu-plugin
- Sirus MUST NEVER call wp_set_auth_cookie() or issue JWTs
- Sirus MUST NEVER query Dheghom or any external plugin directly
- ContextEngine::current() must return a valid SirusContext or throw
  ContextBootException — never return null, never return partial context
- device_id is ALWAYS server-issued — never derived from JS fingerprint alone
- IP addresses stored with last octet zeroed: 192.168.1.0
- The ContextPulse NEVER contains identity claims — device state and
  trust signal only
- PHPStan Level 5 must pass — use ?-> everywhere nullable is consumed
- UEC shim signatures are frozen and must never change

## Trust Score Algorithm (frozen)

  base = 1.0
  device drifting:     -0.3
  geo mismatch:        -0.2
  new session:         -0.1
  recent failures:     -0.3
  clamped to [0.0, 1.0]

## CLI Context (when PHP_SAPI === "cli")

  identity_id  = "SYSTEM"
  trust_score  = 1.0
  trust_level  = "NORMAL"
  authority_id = "GLOBAL"
  device_id    = "CLI"

## Dependencies

This repository depends on sparxstar-ouroboros-integrity for:
- ContextBootException
- ContextPulse DTO
- AgreementResult enum
- ValidationHelper
Never redefine these. Use the Ouroboros package.

## When Uncertain

If you are unsure whether something belongs in Sirus or Helios:
Sirus PRODUCES context. Helios EVALUATES whether a request may proceed.
Sirus never makes a yes/no decision about a request. Helios does.

