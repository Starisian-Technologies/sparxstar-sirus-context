SPARXSTAR Sirus Context --- Agent Instructions
============================================

Who reads this file
-------------------

AI agents operating in this repository. Read this alongside `copilot-instructions.md`.

Platform position
-----------------

```
Ouroboros → Helios → Sirus → Sky → Mehns → Dheghom
```

Sirus is the platform kernel. It loads first as a mu-plugin. Everything else depends on the context it produces. It receives nothing from downstream layers.

The following files provide the full tech specs for the project:

-   the [full technical specs](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sirus_Context_Engine_Spec_v3.0)
-   The [Platform Integrity Map](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sparxstar_Platform_Integrity_Map_v1.0)
-   The [SPARXSTAR Platform Overview](https://github.com/Starisian-Technologies/sparxstar-sirus-context/edit/main/README.md#:~:text=Sparxstar_Platform_Overview_v1.0.-,docx,-.pdf)

The UEC migration
-----------------

`sparxstar-user-environment-check` is in production on a live site. Sirus replaces it transparently via the `StarUserEnv` shim.

Migration strategy: Transparent Proxy.

-   Sirus boots and populates its environment record on every request.
-   `StarUserEnv` proxies all calls to Sirus internals.
-   No calling code changes.
-   For the first 30 days post-deployment, fail-closed rules apply only to new `/sparxstar/` and `/aiwa/` endpoints.
-   After stabilisation, fail-closed rules extend to all governed paths.

Do not break `StarUserEnv`. Its signatures are a permanent contract.

Why the client is the source of truth for environment
-----------------------------------------------------

Server-side User-Agent parsing is unreliable. Matomo DeviceDetector is used for UA parsing but the client-side signals (`visitorId`, canvas hash, screen resolution, timezone) are the primary fingerprint inputs. `visitorId` is client-side derived, probabilistic, used for drift detection only. It is NOT a security primitive. The server-issued `device_id` is the real persistent identity.

Why Sirus is a mu-plugin
------------------------

Sirus must establish context before any standard plugin, theme, or authentication hook can execute. A regular plugin can be deactivated. A mu-plugin cannot. The boot order is enforced by the loader: `mu-plugins/00-sparxstar-loader.php` requires Sirus first.

Why ContextBootException must never be swallowed
------------------------------------------------

If `ContextEngine::current()` throws and the exception is caught and ignored, every downstream decision operates on undefined state: Helios agreement uses an undefined `device_id`, Triple Binding in Dheghom uses an undefined session, governance evaluation uses an undefined authority. There is no recovery from missing context. There is only halt.

Cross-repo dependency check
---------------------------

Before implementing any validation logic, search Ouroboros for `ValidationHelper`. Before implementing any exception, check Ouroboros exceptions. `ContextBootException` is defined in Ouroboros --- do not re
