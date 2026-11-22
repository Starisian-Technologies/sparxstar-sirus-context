# Coding agent review rules — Auto-synced from registry@bd13b51
# DO NOT EDIT
# Coding agent review and merge rules

Owner instructions, 2026-11-21. These rules supersede older instructions asking
for human approval, Copilot reviews or native Codex review requests.

Reviewers are Amazon Q (automatic) plus the central review service. Do not request
Copilot; the account budget does not cover it. Do not use native `@codex review`;
use the central service.

A repository is migrated only if `AGENTS.md` has the central-review pointer and
the request-review relay workflow is on its default branch. Otherwise rely on
Amazon Q until migration. A legacy Claude missing-key failure is known and is
not a merge blocker under this owner ruling; unrelated CI failures are not
waived. Never add AI provider keys to consumer repositories.

Open PRs as drafts. Complete the work and get CI green before marking ready for
review. Draft-to-ready triggers the central cost tier; do not request reviews on
drafts or unfinished work.

- Docs, CI config, test-only changes and small behavior-neutral refactors need
  the automatic Claude review only.
- Runtime behavior changes also need central Codex review.
- Auth/2FA, payments/billing, secrets/credentials, data migrations, licence
  changes and data deletion need all providers.

Use standalone `/review codex` or `/review all` only for the applicable tier if
needed. Post once, never on every push. One successful review per provider/PR
is enough. Allow one re-review only when behavior changes after review; typo and
test fixes do not require another paid review. The service caps paid attempts at
two per provider/PR and enforces configured daily per-provider budgets.

A review counts only when an App-authored Central review comment appears at the
current head commit. A dispatch, skipped job, daily cap or billing error is not a
review. Never claim one happened or describe an older-head review as current.

If no result appears within two hours, inspect the run, fix the request error
and re-request once. If it still fails, or a provider is out of budget, state in
the PR which review did not happen and why. Then merge on green once real
findings are fixed and review threads resolved. No human approval is required.
Do not use the unavailability rule to ignore real findings or other CI failures.

The central service reads declared specs, referenced ADRs/invariants, contracts
and base-commit agent rules at pinned registry tags; do not train a separate
model or substitute stale guidance for current governing documents.

Canonical implementation/setup guide:
[Central review guide](https://github.com/Starisian-Technologies/sparxstar-claude-pr-review/blob/main/docs/central-review.md)

Pilot: `sparxstar-woocommerce-stripe-billing`. Migrate other private repositories
one at a time, disabling each legacy reviewer only after its first successful
central review. Until that evidence exists, call a rollout a pilot, not complete.
