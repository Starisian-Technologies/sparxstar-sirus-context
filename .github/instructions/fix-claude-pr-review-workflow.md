GitHub Copilot Task — Fix the Claude PR Review reusable workflow
===============================================================

> **Target repository:** `Starisian-Technologies/sparxstar-anthropic-workflow`
> **File to change:** `.github/workflows/claude-pr-review.yml` (the reusable
> `workflow_call` invoked by each repo's `claude-pr-review.yml`).
> This task does **not** touch `sparxstar-sirus-context` — that repo only calls
> the reusable workflow.

Why this task exists
--------------------

The reusable review workflow *intends* to ground each review in the repo's specs,
but on real repos it silently feeds the model almost none of them, primes it with
another repo's rules, and contains a shell-injection sink. A recent review passed a
PR while flagging a spec-mandated change as "scope creep" — because the spec that
mandated it was never loaded. Fix the workflow so reviews are grounded, fail loudly
when context is missing, and are safe against untrusted PR metadata.

Do not change the `workflow_call` public interface in a breaking way (existing
callers pass only `secrets.ANTHROPIC_API_KEY`). New `inputs` must be optional with
defaults that preserve current behavior.

---

Bug 1 — The spec loader cannot read the actual specs
----------------------------------------------------

The "Load spec context" step uses `for f in *.md docs/*.md specs/*.md` and `cat`s
matches. On real repos the binding specs are **PDFs** in `docs/specs/` (e.g.
`Sirus_Context_Engine_Spec_v3.0.docx.pdf`) and there is no top-level `specs/` dir,
so none match — and `cat` on a PDF emits binary even if it did match. Result: the
canonical spec (the one with the REST endpoint list, prohibitions, etc.) never
reaches the model.

Required fix:
- Convert PDFs to text before inclusion. Add `poppler-utils` and run `pdftotext`:
  ```bash
  sudo apt-get update && sudo apt-get install -y poppler-utils
  ```
  For each PDF found under the spec dirs, emit `pdftotext -layout "$pdf" -` into the
  context with a `--- FILE: <path> ---` header.
- Discover markdown **recursively** (`find ... -name '*.md'`), not with a one-level glob.
- Make the spec locations configurable via optional `workflow_call` inputs with
  defaults, so the workflow works across repos:
  - `spec-dirs` (default: `docs docs/specs .github/instructions`)
  - `spec-files` (default: `AGENTS.md .github/copilot-instructions.md .github/instructions/copilot-instructions.md .github/instructions.md`)
  - `pdf-spec-glob` (default: `docs/specs/*.pdf`)
- Keep excluding `README.md`/`CHANGELOG.md` from the markdown sweep, but still load
  the explicit `spec-files` even if they live under `.github/`.

Bug 2 — Wrong path for the instructions file
--------------------------------------------

The step checks only `.github/copilot-instructions.md`. Real repos keep it at
`.github/instructions/copilot-instructions.md` (and also have `.github/instructions.md`).
The `[ -f ]` guard fails silently and the boundary/ownership rules never load.
Fix: load **all** of the `spec-files` defaults above; check each, and record which
were found vs missing.

Bug 3 — Failures are silent
---------------------------

When an expected file is absent the loader skips it and the review proceeds on
near-empty context, then emits a confident `PASS`. Fix:
- After loading, print a **manifest** to the job log: every file included and its
  byte count (and "MISSING" for each expected file not found).
- Add an optional `required-specs` input (newline/space list). If any required spec
  is missing or `spec_context.txt` is empty, `echo "::error::..."` and `exit 1` so the
  job fails visibly instead of reviewing blind.
- Pass the manifest into the prompt so the model knows what it could and could not
  verify.

Bug 4 — The injected checklist is the wrong repo's rules
--------------------------------------------------------

The inlined `PLATFORM-WIDE RULES` block is mostly Helios/Mḗh₁n̥s/Dheghom/Ouroboros
internals (`SieveKernel`, `AuditLedger` genesis hash, `GovernanceTokenSigningMaterial`,
`STARISIAN_PACKAGE_TOKEN`, FingerprintJS vendoring, …) that don't apply to most repos,
and it buries the few that do. It also contains `"No test files … unless explicitly
requested"`, which mis-flags legitimate test PRs.

Required fix:
- Move the rules out of the inlined heredoc. Load them from a repo-supplied file via
  an optional input `review-rules-file` (default: `.github/review-rules.md`).
- If that file is absent, fall back to a **minimal** platform-wide default (strict_types,
  no `error_log()`, no `SELECT *`, proprietary license, no local redefinition of
  Ouroboros-owned types, no `identity_id` in `ContextPulse`). Keep the default short.
- Each repo then owns its own conformance checklist; the reusable workflow stops
  hardcoding one repo's rules for all.

Bug 5 — Shell injection via PR title (security)
-----------------------------------------------

`PR_TITLE="${{ github.event.pull_request.title }}"` interpolates an attacker-controlled
PR title directly into the run script at YAML-render time, in a job with
`pull-requests: write`. A crafted title can break out of the assignment.

Required fix:
- Never inline `${{ github.event.* }}` untrusted fields inside `run:` scripts. Pass
  them through `env:` on the step and reference the shell variable quoted:
  ```yaml
  - name: Run Claude review
    env:
      ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
      PR_TITLE: ${{ github.event.pull_request.title }}
      PR_NUMBER: ${{ github.event.pull_request.number }}
      REPO: ${{ github.repository }}
    run: |
      ...
      # use "$PR_TITLE", "$PR_NUMBER", "$REPO" — never the ${{ }} form here
  ```
- Audit the other steps for the same pattern (the `gh pr diff "${{ ... number }}"`
  call should also use the env var).

Bug 6 — Reasoning discipline in the prompt
------------------------------------------

The free-form template lets the model emit ungrounded findings and pressures it to
manufacture filler when nothing is wrong. Update the prompt instructions to:
- **Require a citation per finding.** Every VIOLATION/WARNING must reference the
  specific loaded spec section, file, or platform rule it is grounded in. *If it
  cannot cite a loaded source, it must not raise the finding.*
- **Allow an empty result.** State explicitly that "No issues found" is a complete,
  acceptable review; do not invent warnings.
- **Respect what was loaded.** Include the manifest; instruct the model to mark a
  concern "unverified — spec not in context" rather than guessing when the relevant
  spec was not loaded.
- **Switch rubric for docs/planning PRs.** If the changed files are only
  `*.md` / `docs/**` / `.github/instructions/**`, review for spec/doc accuracy and
  internal consistency — do not apply code heuristics (test-coverage thresholds,
  "scope creep", etc.).

Bug 7 — Minor hardening (do if quick)
-------------------------------------

- The model id is pinned to an old snapshot (`claude-sonnet-4-20250514`). Bump to a
  current Sonnet and a current `anthropic-version`, or expose the model as an input.
- The 80KB diff cut is a raw byte slice. Prefer truncating on a file/hunk boundary so
  the model isn't handed a half-line; keep the existing truncation note.

---

Acceptance criteria
-------------------

- On a repo whose specs are PDFs under `docs/specs/`, the job log shows a manifest
  listing those specs converted via `pdftotext` and loaded with non-zero byte counts.
- `.github/instructions/copilot-instructions.md` and `.github/instructions.md` are
  loaded when present.
- If a `required-specs` entry is missing (or context is empty), the job **fails** with
  a clear `::error::`, instead of posting a review.
- A PR whose title contains shell metacharacters (e.g. `` `id` `` or `$(touch x)`)
  cannot alter job execution — verify the title only ever appears as a quoted env var.
- Conformance rules come from the repo's `review-rules-file` (or the minimal default),
  not a hardcoded foreign list.
- Every finding in the posted review cites a loaded spec section/file/rule; a clean PR
  yields "No issues found" with no filler warnings.
- The `workflow_call` interface still works for existing callers passing only
  `ANTHROPIC_API_KEY` (all new inputs optional with safe defaults).

Suggested validation
---------------------

Run the workflow (or `act`) against two fixture PRs:
1. A docs-only PR that completes a spec-mandated item — confirm it is **not** flagged
   as scope creep and the doc rubric is used.
2. A PR with a hostile title and a real `strict_types` omission — confirm no injection,
   and the omission is reported with a rule citation.
