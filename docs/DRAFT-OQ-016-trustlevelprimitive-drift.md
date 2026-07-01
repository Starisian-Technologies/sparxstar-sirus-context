# DRAFT OQ-016 — TrustLevelPrimitive Enum Drift (Sirus ↔ Ouroboros v2.0.0)

> **Status:** DRAFT — not yet ratified. Owner must assign the real OQ number
> (currently placeholder OQ-016, assuming OQ-001 through OQ-015 are occupied in the
> governance registry) and enter it there. Until ratified in the registry, this document
> is not deferral authority.

---

## Classification

- **Type:** Cross-repo contract break
- **Severity:** Live-path defect (not test-only)
- **Repos affected:** `sparxstar-sirus-context` (consumer), `sparxstar-ouroboros-integrity` (publisher)
- **Observed:** 2026-07-01, CI on PR #118 (`claude/tender-babbage-y2p8us`)

---

## Observed failure

CI runs on `sparxstar-sirus-context` at Ouroboros pin `3529cb67` (v2.0.0, tagged 2026-06-07)
report 94 errors, all `ValueError` from PHP enum resolution:

```
ValueError: "ELEVATED" is not a valid backing value for enum
  Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive

ValueError: "anonymous" is not a valid backing value for enum
  Starisian\Sparxstar\Infrastructure\DTOs\TrustLevelPrimitive
```

---

## Live-path call sites in Sirus (not test-only)

| File | Line | Call | Trigger condition |
|---|---|---|---|
| `src/core/PulseGenerator.php` | 142 | `TrustLevelPrimitive::ELEVATED` (case constant) | Any pulse generated for an elevated-trust context |
| `src/core/ContextEngine.php` | 206 | `TrustLevelPrimitive::from($trust_level)` where `$trust_level` comes from Helios response | Any context resolution where Helios returns `"ELEVATED"` as the trust level |
| `src/core/NetworkContextBroker.php` | 136 | `TrustLevelPrimitive::from('anonymous')` | Any cross-domain token verification for a pre-v2 token without a `tl` field |

The `PulseGenerator` and `NetworkContextBroker` call sites throw `ValueError` at runtime,
not just in the test suite. No try/catch wraps these calls.

---

## What needs to be determined

**Run this command against the Ouroboros repo to answer the pin-vs-contract question:**

```bash
gh api "repos/Starisian-Technologies/sparxstar-ouroboros-integrity/contents/src/DTOs/TrustLevelPrimitive.php?ref=3529cb67de4e53cfa9570eb96fc09d7a6d00cb92" \
  --jq '.content' | base64 -d | grep -iE "enum |case "
```

---

## Resolution paths (owner selects one)

### Path A — Version bump (if `ELEVATED`/`anonymous` exist on a newer Ouroboros tag)

Update `composer.lock` in `sparxstar-sirus-context` to pin to the tag that exports those
cases. Verify by re-running CI. No Sirus source changes required.

### Path B — Ouroboros contract addition (if the cases were never shipped)

File a change request against `sparxstar-ouroboros-integrity` to add `ELEVATED` and
`anonymous` as backing values to `TrustLevelPrimitive`. Sirus cannot fix this unilaterally —
the enum is defined in Ouroboros and Sirus is a consumer. Block Sirus's S-09 work on
Ouroboros publishing the updated enum.

### Path C — Sirus rewrite (if the cases are intentionally absent by design)

If Ouroboros deliberately removed these cases in v2.0, the correct fix is to rewrite the
three Sirus call sites above to use the cases that do exist. `PulseGenerator::deriveBehaviorFlags()`
and the `NetworkContextBroker` fallback must be updated. This is non-trivial and requires
spec alignment with Helios (which sends `"ELEVATED"` strings that Sirus passes to `::from()`).

---

## Current gate status

**Failing. Unratified. Blocking.**

The 94 CI errors are the observable surface. The gate may not be closed as "no action needed"
or deferred until this OQ is ratified by the owner in the governance registry with a resolution
path selected.

---

## Owner ruling

> [ ] Path A — version bump. Target tag: ___________
>
> [ ] Path B — Ouroboros contract addition. Filed as: ___________
>
> [ ] Path C — Sirus rewrite. Spec alignment required with: ___________
>
> Ratified by: ___________ Date: ___________
