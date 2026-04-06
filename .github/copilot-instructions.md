SPARXSTAR --- sparxstar-sirus-context
===================================

Copilot Instructions
====================

What this repository is
-----------------------

Sirus is the context engine. Before identity is established, before authentication runs, before any application logic executes --- Sirus establishes the environment. Who is present, on what device, in what environment, under what authority.

Sirus produces context. It does not make authorization decisions (Helios). It does not enforce governance (Mehns).

The following files provide the full tech specs for the project:

- the [full technical specs](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sirus_Context_Engine_Spec_v3.0)
- The [Platform Integrity Map](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sparxstar_Platform_Integrity_Map_v1.0)
- The [SPARXSTAR Platform Overview](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sparxstar_Platform_Overview_v1.0.-,docx,-.pdf)


Production context --- UEC compatibility
--------------------------------------

The original `sparxstar-user-environment-check` plugin is in production. Sirus replaces it via a namespace shim. The `StarUserEnv` facade signatures are **frozen and must never change**.

php

```
StarUserEnv::get_browser_name()           // → environment->browser_name
StarUserEnv::get_os()                     // → environment->os
StarUserEnv::get_device_type()            // → environment->device_type
StarUserEnv::get_network_effective_type() // → environment->network_effective_type
StarUserEnv::get_ip_address()             // → environment->ip_address
StarUserEnv::get_location()               // → environment->location
```

What this repository owns
-------------------------

-   `ContextEngine` --- `current()` accessor, CLI System Context
-   `SirusContext` DTO --- primary output consumed by all downstream layers
-   `ContextPulse` generation and HMAC-SHA256 signing (`PulseGenerator`)
-   `PulseVerifier` --- six-check canonical verification
-   `TrustEngine` --- trust state and score computation
-   `DeviceContinuity` --- server-issued `device_id`, fingerprint, session recovery
-   `DeviceMatcher` --- fingerprint scoring thresholds
-   `EnvironmentResolver` --- browser, OS, network via Matomo DeviceDetector
-   `IdentityResolver` --- five-tier resolution
-   `AuthorityResolver` --- governance scope, multi-authority aggregation
-   `ConsentManager` --- technical consent, purpose consent, history
-   `StepUpPolicy` --- Level 3 always, Level 2 when `trust_score < 0.7`
-   `NetworkContextBroker` --- cross-domain handoff
-   `StarUserEnv` --- frozen public facade (UEC compatibility)

What this repository does NOT own
---------------------------------

-   Agreement evaluation (proceed/deny) --- Helios
-   KV revocation --- Helios
-   Governance policy --- Mehns
-   Persistence --- Dheghom
-   Draft accumulation --- Sky
-   Pulse **verification** --- Sirus **generates**. Helios **verifies**. Do not put verification logic here.

Hard rules
----------

-   `declare(strict_types=1)` in every file
-   Namespace: `Starisian\Sparxstar\Sirus\`
-   Deployed as WordPress mu-plugin --- cannot be deactivated
-   MUST NEVER call `wp_set_auth_cookie()` or issue JWTs
-   MUST NEVER query Dheghom or any external plugin directly
-   `ContextEngine::current()` returns valid `SirusContext` or throws `ContextBootException` --- never null, never partial
-   `ContextBootException` MUST NEVER be caught and swallowed
-   `device_id` is ALWAYS server-issued --- never JS fingerprint alone
-   IP addresses stored with last octet zeroed: `192.168.1.0`
-   `ContextPulse` NEVER contains identity claims
-   `?->` required wherever nullable is consumed (PHPStan Level 5)
-   `StarUserEnv` signatures are FROZEN --- must never change

Trust score algorithm (frozen)
------------------------------

```
base = 1.0
device drifting:   -0.3
geo mismatch:      -0.2
new session:       -0.1
recent failures:   -0.3
clamped to [0.0, 1.0]
```

CLI context (when `PHP_SAPI === 'cli'`)
---------------------------------------

```
identity_id  = "SYSTEM"
trust_score  = 1.0
trust_level  = "NORMAL"
authority_id = "GLOBAL"
device_id    = "CLI"
```

Available filters (stable --- do not remove)
------------------------------------------

```
sparxstar_env_cache_handler       --- switch cache backend
sparxstar_env_cache_ttl           --- set cache duration
sparxstar_env_geolocation_ttl     --- set geolocation cache duration
sparxstar_env_geolocation_lookup  --- custom geolocation provider
sparxstar_env_retention_days      --- snapshot retention
sparxstar_sirus_device_ttl_days   --- device record TTL (default 90)
```

Build and test
--------------

bash

```
composer install
composer test           # full suite
composer run lint       # PHPCS PSR-12
composer run analyze    # PHPStan Level 5
composer run test:unit  # PHPUnit
```

Dependencies from sparxstar-ouroboros-integrity
-----------------------------------------------

-   `ContextBootException`
-   `ContextPulse` DTO
-   `AgreementResult` enum
-   `ValidationHelper`

**Never redefine these. Use the Ouroboros package.**

When uncertain
--------------

If you are making a yes/no decision about whether a request may proceed --- that is Helios, not Sirus. Sirus produces context. Helios decides.


