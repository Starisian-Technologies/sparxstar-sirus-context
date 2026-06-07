# Gate Audit — sparxstar-sirus-context (post PR #88, Ouroboros v2.0.0 / PAM-003)

**Scope:** report only, **zero fixes**. Audits the three quality gates so CI can be
made trustworthy. Produced after PR #88 merged (`c30506d`): the resolver-token auth
and the `^2.0` Ouroboros pin are in `main`; the PHPCS `src/` debt is unchanged and
intentionally left for this audit to characterise.

**Method:** static analysis of the committed gate configuration and the local source
/ test tree. No code was modified. Tests are evaluated against spec
(PAM-002, PAM-003, Ouroboros v2.0.0 `shared-test-vectors.json`) — **never against the
current code**.

**Authority:** naming/convention recommendations in §A.2 are governed by
**SPARXSTAR-Engineering-Standards-v1.0 §6.2** — PascalCase classes, `Sparxstar` prefix for
platform classes, file name matches class name (i.e. PSR-4-style). This is the same standard
that authorises the S-03 naming migration.

**Blocked inputs (cannot be resolved from this container):**

| Input | Status | Impact |
|---|---|---|
| Ouroboros v2.0.0 `shared-test-vectors.json` | **unavailable** — lives in the private `sparxstar-ouroboros-integrity` repo; this session's proxy returns `repository not authorized`, registries are network-blocked | Final verdict on the 10 Ouroboros-coupled tests cannot be issued — only the structural triage below |
| `PAM-003.md` | **not a file in this repo** — referenced only (in `PAM-002.md`, `TRACKER.md`, `copilot-instructions.md`, and `src/core/*`) | PAM-003 acceptance criteria for pulse/trust tests must be supplied to finalise §C |

Where a verdict depends on a blocked input it is marked **⛔ BLOCKED — needs vectors/PAM-003**.

---

## A. PHPCS ruleset audit

### A.0 Structural problem: three divergent rulesets, two of them committed and active

| File | Used by | Scope | `testVersion` | Distinct rules |
|---|---|---|---|---|
| `phpcs.xml` | `composer phpcs`, `composer comments:check` | **`.`** (whole tree minus excludes) | **8.1–8.4** | `NormalizedArrays`, `ignore-annotations`, excludes `wp-assets/*` & `build/*`, `WordPressVIPMinimum.JS.*` excluded |
| `phpcs.xml.dist` | `composer lint` (`--report=full src/`) | **`src/*`** only | **8.2-** | `PHPCompatibilityParagonie*`, explicit `installed_paths`, `WordPress.Files.FileName` with `strict_class_file_names=false`, `Generic.Arrays.DisallowLongArraySyntax` |
| `phpcbf.xml.dist` | `composer lint:fix`, `composer phpcbf` | `.` minus excludes | — | formatting subset only (no Modernize/Universal/PHPCompatibility/VariableAnalysis) |

**Findings:**

1. **Two committed rulesets disagree and are both live.** `composer phpcs` (386 violations in
   CI) and `composer lint` (310) scan different paths with different rules — this *is* the
   386-vs-310 discrepancy seen on PR #88. **REVIEW → consolidate to one canonical ruleset**
   (recommend `phpcs.xml.dist` as the committed baseline; delete or thin `phpcs.xml`).
2. **`phpcs.xml.dist` is self-contradictory.** Its own `<description>` says *"To override
   locally, create a `phpcs.xml` file (gitignored)"* — but `phpcs.xml` **is committed and is
   what `composer phpcs` runs.** Either gitignore `phpcs.xml` (make it a true local override)
   or delete it and fold any wanted rules into the `.dist`. **REVIEW.**
3. **`testVersion` mismatch.** `phpcs.xml`=`8.1-8.4`, `phpcs.xml.dist`=`8.2-`,
   `composer.json` platform=`8.2.29`. The 8.1 lower bound contradicts the 8.2 floor and makes
   PHPCompatibility flag 8.2-only syntax as errors. **REVIEW → set both to `8.2-`.**
4. **`<arg name="ignore-annotations"/>` in `phpcs.xml`.** Inline `// phpcs:ignore` /
   `phpcs:disable` are **disabled**, so legitimate, reviewed false positives cannot be waived
   in-line. Combined with the hard-fail gate this guarantees friction. **REVIEW → drop
   `ignore-annotations`** (keep waivers reviewable in code) **unless** the team wants a
   zero-waiver policy, in which case document it.
5. **`phpcbf.xml.dist` fixes a strict subset of what `phpcs.xml` checks.** After `lint:fix`,
   `composer phpcs` will still report Modernize/Universal/PHPCompatibility/VariableAnalysis
   findings that have no autofixer. **KEEP** (correct by design) — but set expectations: the
   "386 auto-fixable" PHPCBF banner refers to whitespace/format only; the residue is manual.

### A.1 Sniff-by-sniff (top-level refs; keep / exclude / review)

| Rule ref (in `phpcs.xml` / `.dist`) | Verdict | Rationale |
|---|---|---|
| `WordPress-Core` | **KEEP** | Baseline formatting/security; the bulk of the auto-fixable 386 (tabs, paren spacing) come from here and PHPCBF clears them. |
| `WordPress-Extra` | **KEEP w/ carve-outs** | Pulls in `WordPress.Files.FileName` and `WordPress.NamingConventions.*` — see §A.2 PSR-4 conflicts. |
| `WordPress-Docs` | **REVIEW** | Strict docblock sniffs on a namespaced/typed PHP 8.2 codebase produce high-volume, low-value noise; consider scoping to public API only. |
| `WordPressVIPMinimum` | **REVIEW** | VIP platform rules on a self-hosted network plugin flag things that don't apply (e.g. restricted functions); `phpcbf.xml.dist` already excludes several. Decide if VIP is a real target. |
| `PHPCompatibility` / `WP` / `Paragonie*` | **KEEP**, fix `testVersion` | Valuable; only correct once `testVersion` is unified to `8.2-` (finding A.0.3). |
| `Modernize`, `Universal`, `NormalizedArrays` (PHPCSExtra) | **KEEP** | Aligns with the modern-PHP direction; mostly non-fixable → manual residue. |
| `VariableAnalysis.CodeAnalysis.VariableAnalysis` | **KEEP** | Catches real dead/undeclared vars. |
| `WordPress.NamingConventions.PrefixAllGlobals` (sparxstar/star) | **KEEP** | Correct prefixes; namespaced symbols are exempt so low conflict. |
| `WordPress.WP.I18n` (text_domain sparxstar) | **KEEP** | Correct. Note plugin header text-domain must match `sparxstar`. |
| `WordPress.Files.FileName` | **EXCLUDE / relax** | **PSR-4 conflict — see §A.2.** Relaxed in `.dist`, *not* relaxed in `phpcs.xml`. |
| `Generic.Arrays.DisallowLongArraySyntax` (.dist only) | **KEEP** | Enforces short arrays; consistent with WP-Core. |

### A.2 PSR-4 ↔ WPCS conflicts (the structural source of much of the debt)

The autoloader (`composer.json`) is **PSR-4**: `Starisian\SparxstarUEC\` and
`Starisian\Sparxstar\Sirus\` → `src/`. Every class file is therefore **StudlyCase**
(`src/core/PulseGenerator.php`, `src/helpers/IpAnonymizer.php`, …). WPCS expects WordPress
file/naming conventions. The specific collisions:

1. **`WordPress.Files.FileName`** wants `class-pulsegenerator.php` (hyphenated lowercase).
   PSR-4 requires `PulseGenerator.php`. `phpcs.xml.dist` sets `strict_class_file_names=false`
   (disables the *class-matches-file* check) **but not** the
   `NotHyphenatedLowercase`/`InvalidClassFileName` parts; **`phpcs.xml` does not relax it at
   all** → every src class file trips it under `composer phpcs`. **Recommend: exclude
   `WordPress.Files.FileName` entirely** in the canonical ruleset (incompatible with PSR-4).
   Per **Engineering Standards §6.2** the PSR-4 convention (PascalCase, file matches class) is
   the mandated standard, so this sniff is excluded by policy, not merely by preference.
2. **`WordPress.NamingConventions.ValidVariableName` (snake_case).** Confirmed in CI logs
   (`$actionKey → $action_key`). Modern typed code commonly uses camelCase locals; WPCS
   mandates snake_case. **REVIEW — pick one convention and apply repo-wide;** if camelCase is
   the house style, exclude this sniff; if snake_case, it's legitimate debt.
3. **Namespaces vs `PrefixAllGlobals`.** Low conflict — namespaced symbols are exempt; only
   truly global symbols need the `sparxstar`/`star` prefix. **No change.**

> Net: a large fraction of the 386/310 is **convention collision (FileName + camelCase)**,
> not latent bugs. Decide PSR-4-vs-WPCS once; the count drops sharply after that plus PHPCBF.

---

## B. Pre-commit gate (husky / lint-staged) audit

**Finding: there is no pre-commit gate of any kind.**

| Mechanism checked | Result |
|---|---|
| `.husky/` directory | absent |
| `lint-staged` / `husky` / `simple-git-hooks` / `pre-commit` keys in `package.json` | none |
| `.lintstagedrc*`, `.pre-commit-config.yaml`, `captainhook`, `grumphp` | none |
| `composer.json` hook tooling | none |
| `.git/hooks/` | only `*.sample` (inactive) |

**Implications:**

- **The "phpcbf exit-code bug" (Helios) is N/A here — there is no hook to contain it.** For
  the record, that bug is: a `pre-commit` running `phpcbf` treats PHPCBF's **exit code 1
  ("fixed some violations")** as failure, blocking commits even after a successful auto-fix
  (PHPCBF returns `0` = nothing to fix, `1` = fixed, `2` = errors remaining; only `2` is a
  real failure). **If a husky/lint-staged hook is added later, it must treat `phpcbf` exit
  `1` as success** (e.g. `phpcbf || [ $? -eq 1 ]`).
- The absence is itself a gate finding: nothing stops a developer committing PHPCS-violating
  PHP locally — which is how 386 violations accumulated despite a hard CI gate. **REVIEW →
  consider a lint-staged `phpcbf` (with the exit-code guard) + `phpcs` check**, but only after
  §A consolidation, or the hook will inherit the dual-ruleset confusion.

---

## C. Test-file audit

Tests classified **valid / outdated→rewrite-to-spec / wrong→delete**, decided by spec
(PAM-002, PAM-003, Ouroboros v2.0.0 vectors) — not by current code. 51 test files
(49 unit + 1 integration + `SirusTestCase` base). Every test maps to a real class or a real
behaviour — **no `wrong→delete` from a missing-class standpoint.** The decisive axis is
**Ouroboros coupling**: 10 tests import the `Starisian\Sparxstar\Infrastructure\*` namespace
(confirmed external — *not* defined in `src/`, i.e. the Ouroboros package), so their contract
is owned by Ouroboros v2.0.0 / PAM-003.

### C.1 ⛔ Ouroboros-coupled — verdict BLOCKED (needs v2.0.0 vectors + PAM-003)

These consume Ouroboros DTOs/utils (`ContextPulse`, `TrustLevelPrimitive`,
`ContextPulseSigningMaterial`, `Platform`) and must be re-derived from
`shared-test-vectors.json`, **rewritten to spec, not to code**:

| Test | Couples to | Note |
|---|---|---|
| `PulseRoundTripTest` | `PulseGenerator` + Ouroboros `ContextPulse`/`ContextPulseSigningMaterial`/`TrustLevelPrimitive` | **Highest priority.** Round-trip issuance↔verify; directly exercises the PulseGenerator migration (see §C.4). Must assert against v2.0.0 vectors. |
| `PulseGeneratorTest` | `PulseGenerator::generate()`, `Platform::PULSE_VERSION_CURRENT` | Asserts local issuance shape; if issuance delegates to Ouroboros, this is **outdated→rewrite**. |
| `SirusContextTest` | Ouroboros DTOs | Pulse/trust fields → vector-driven. |
| `AuthorityResolverTest`, `CapabilityEngineTest`, `StepUpPolicyTest`, `TrustResolverTest` | trust-level primitives / `token_version` | Trust model is PAM-003 territory. |
| `ContextEngineTest`, `ContextCacheTest`, `IdentityResolverTest`, `NetworkContextBrokerTest` | context→pulse assembly | Re-verify field set against PAM-003. |

> **Cannot finalise valid-vs-rewrite without the vectors.** Provide
> `shared-test-vectors.json` (and PAM-003 acceptance) to complete this section.

### C.2 Likely-valid (spec-stable; confirm no incidental pulse coupling)

`DeviceMatcherTest`, `DeviceContinuityTest`, `DeviceRecordTest`, `SirusDeviceParserTest`,
`IpAnonymizerTest`, `EnvironmentRecordTest`, `EnvironmentResolverTest`, `ConsentManagerTest`,
`ClientTelemetryTest`, `StarLoggerTest`, `SirusRateLimitTest`, `SirusRuleConfigTest`,
`Sirus*RepositoryTest` (Event / RuleHit / MitigationAction), `SirusEventAggregatorTest`,
`SirusImpactScorerTest`, `SirusPriorityScorerTest`, `SirusSignalEvaluatorTest`,
`SirusMitigationCoordinatorTest`, `SirusMitigationRuleEngineTest`,
`SirusDatabaseEventsTableTest` (→ `SirusDatabase`), `SirusNetworkSettingsTest`
(→ `SirusNetworkSettingsPage`), `SparxstarUEC*Test` (API/CacheHelper/GeoIPService/
SessionManager/InstallerMultisite), `StarUserEnv*Test`, `UECCompatibilityShimTest`,
`HeliosClientTest`, `SirusEventControllerTest`, `SirusDirectiveControllerTest`.
→ **VALID pending a content read**; not Ouroboros-contract-bound.

### C.3 Smoke / structural

| Test | Verdict | Note |
|---|---|---|
| `PluginBootstrapTest` | **VALID** | Asserts plugin constants (`SPX_ENV_CHECK_*`) + activation/deactivation hooks registered. Not a missing-class case. |
| `SirusRESTControllerTest`, `RestApiTest` (integration) | **REVIEW** | Reference pulse issuance via `new PulseGenerator()`; re-verify once §C.4 migration lands. |
| `SirusTestCase` | base class (not a test) | Shared harness; keep. |

### C.4 PulseGenerator collision (the flagged migration — NOT resolved here)

- **No namespace/autoload collision.** Sirus's `Starisian\Sparxstar\Sirus\core\PulseGenerator`
  is a distinct FQCN from Ouroboros's class — `composer install` succeeded on every PR #88
  job with no redeclaration fatal. So at the **namespace and autoload levels there is no
  conflict.**
- **PHPStan level: unverifiable in CI** — PHPStan is masked behind the PHPCS hard-fail
  (`composer run test` short-circuits), so any type/contract drift between Sirus's local
  generator and Ouroboros's `PulseGenerator::generate()` does **not** currently surface.
- **Semantic duplication (the real item).** Canonical pulse *issuance* moved into Ouroboros
  (`PulseGenerator::generate()`), yet Sirus still issues locally. The generator is
  instantiated once at the composition root (`src/SirusPlugin.php:138`, `new PulseGenerator()`)
  and **constructor-injected** into consumers — e.g. `SirusRESTController`
  (`src/api/SirusRESTController.php:47`, `private readonly PulseGenerator $pulse_generator`),
  which receives it via DI rather than instantiating it. (Test setups instantiate it directly.)
  Signing-material *format* already delegates to Ouroboros
  (`ContextPulseSigningMaterial::build()`, CO-001, per the class docblock) — issuance is the
  un-migrated half.
- **Left for Max's delegation migration (spec'd item).** `PulseRoundTripTest` /
  `PulseGeneratorTest` are the canaries that will confirm the migration once rewritten to the
  v2.0.0 vectors.

---

## D. Summary & required inputs

**Actionable now (no spec needed):**
- A.0.1/2 consolidate to one ruleset; fix the contradictory `.dist` description.
- A.0.3 unify `testVersion` to `8.2-`.
- A.0.4 reconsider `ignore-annotations`.
- A.2.1 exclude `WordPress.Files.FileName` (PSR-4 incompatible).
- A.2.2 decide camelCase vs snake_case repo-wide.
- B add a lint-staged `phpcbf` hook **with the exit-code-1 guard** (after A consolidation).

**Blocked — need inputs to finalise:**
- Ouroboros v2.0.0 `shared-test-vectors.json` → finalise §C.1 verdicts.
- PAM-003 acceptance criteria → trust/pulse test rewrites.

> **TRACKER.md refresh remains a separate follow-up** — it records *verified* state only, so
> it should land after the gate is consolidated and CI is trustworthy (i.e. after §A/§B are
> applied and §C is unblocked by the vectors).
