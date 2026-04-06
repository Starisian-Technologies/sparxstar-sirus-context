SPARXSTAR Sirus Context --- Developer Instructions
================================================

What this is
------------

Sirus is the context engine of the SPARXSTAR platform. It runs before identity, authentication, and application logic. It replaces the `sparxstar-user-environment-check` (UEC) plugin via a transparent compatibility shim.

Prerequisites
-------------

-   PHP 8.2+
-   WordPress 6.8+
-   Composer 2.x
-   Redis (required --- persistent object cache for trust state)
-   Matomo DeviceDetector (bundled via Composer, committed to `vendor/`)

Setup
-----

This plugin loads as a mu-plugin via the platform loader. Do not activate it as a standard plugin.

bash

```
# Verify the loader file exists
ls mu-plugins/00-sparxstar-loader.php
# Sirus must be the first require_once in that file

# Install dependencies
composer install
# Note: vendor/ is committed. Do not gitignore it.
# Matomo DeviceDetector is committed --- no runtime fetch.
```

Confirm Redis is available. Without persistent object cache, trust cache is disabled and the system falls back to per-request pulse verification. Transients must not be used for trust state.

UEC compatibility
-----------------

If the site currently runs `sparxstar-user-environment-check`:

1.  Deploy Sirus first. Verify `StarUserEnv` methods return correct values.
2.  Keep UEC active for 30 days alongside Sirus (parallel shim mode).
3.  After 30 days, deactivate UEC. `StarUserEnv` continues via Sirus.

`StarUserEnv` method signatures are frozen. If they return wrong values, fix the Sirus internals --- never change the method signatures.

Running tests
-------------

bash

```
composer test           # full suite
composer run lint       # PHPCS PSR-12
composer run analyze    # PHPStan Level 5
composer run test:unit  # PHPUnit
```

Branching rules
---------------

-   No direct commits to `main`
-   PHPStan Level 5 must pass
-   All tests must pass
