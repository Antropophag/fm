# ADDRESS-REGISTER-IMPORT-001 — independent unknown-budget corrective Gate 3 review v18

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, tests, fixtures, production correction, or RED evidence
- Production base: `99232d9c263b0dc21fa1f2ab6a892e7e05f0f3a1` plus the separately implemented Gate 3 v17 lookup/refresh correction and owned-claim stale exemption
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261120T001812Z-f316848a27/package.json`
- Candidate source: `000110ef013c9bba263b86b4ed0f402f71295656632875536516cbdd00e79b52`
- Executable source: `2747c37a602a07669645f122a28a87920d552648bd72a1d6b1ebfeb0d41dcb21`
- Snapshot patch SHA-256: `0faae5926c9411e9a417d759e8a2ee87e897aa9ab5449fc502eac1da8bcd26ad`
- Verdict: **CHANGES_REQUESTED**

## Findings

1. **BLOCKING — the regressions do not observe the durable claim that the contract explicitly requires budget denial to preserve.** Both paired scenarios assert the transient `blocked/REQUEST_BUDGET_EXCEEDED` response, retained paid count, durable `unknown/PROVIDER_OUTCOME_UNKNOWN` report, no call/fallback on a later budget-only resume, and eventual success of a valid explicit retry. Neither scenario reads `fm2_address_import_cache.lock_owner/lock_key`, entry ID, or entry version after the unknown outcome and after each denied resume.

   An implementation can satisfy every submitted assertion by guarding on durable package status while still deleting the unknown claim during the exhausted retry. The later valid explicit retry may simply create a new claim and succeed. That would pass the test while violating the new normative sentence that the unknown package/report/**claim** remains durable, and it would weaken same-hash single-flight ownership between the denied and valid retries.

   **Correction:** in both the no-older-cache and old-fresh-cache paths, capture the unknown claim's immutable `entry_id`, `entry_version`, `request_hash`, `lock_owner`, and `lock_key` immediately after the provider-unknown result. Assert the same row and ownership remain unchanged after the exhausted explicit retry and after the budget-only/no-`retryUnknown` resume. Assert no extra claim/version row appears. Then perform the valid explicit retry and prove that same retained claim is completed (same entry ID/version, lock fields cleared, changed response published), rather than replaced by a newly allocated claim. Keep all existing package/report/call/attempt/budget assertions.

No other blocking or non-blocking findings.

## State-machine assessment

The proposed behavior is otherwise coherent. A provider-unknown result is a durable uncertainty, while an exhausted cap is a transient admission refusal before transport. `REQUEST_BUDGET_EXCEEDED` must therefore be returned to the current caller without overwriting the package's durable unknown report, settling uncertainty, deleting its claim, or treating an older completed cache row as resolution.

A subsequent resume that only increases budget with a reason but omits `retryUnknown` must still return the durable unknown outcome without provider access or cache fallback. Only a later resume with paid permission, `retryUnknown=true`, a nonempty reason, and sufficient cumulative cap may perform the next attempt and recover. The paid-attempt count remains cumulative throughout.

The paired placement is appropriate. The first path has no older completed evidence and exposes accidental uncached provider re-entry. The second begins with an older fresh completed version and exposes silent fallback. Both run before their previously GREEN valid-recovery assertions, so the new failure is causally isolated without weakening the established success path.

## Relationship to v17

The Gate 3 v17 lookup and causal cache-version design remains suitable. Identity, concurrency, and schema are GREEN on the implemented source; economy returned GREEN after the separate owned-claim stale guard correction before this new budget-state regression was added. The same-clock version ordering, allocator lock, import rollback, requested-only identity lookup, and existing stale/refresh coverage are not changed by this bounded test/spec delta.

The current production failure is truthful: exhausted explicit retry returns the requested transient blocked response but persists that blocked state over unknown, deletes the claim, and a later budget-only resume reaches ready with an additional provider call. The exact RED reports calls increasing from 4 to 5.

## Evidence and lifecycle

The prepared package maps `php tests/InstallationProcess/address_register_import_economy_001_test.php` and records exact-source `INTENDED_RED`. The first no-older-cache pair fails at the new matrix; the later old-fresh pair and post-implementation assertions honestly remain unexecuted. A separate private probe confirms the old-fresh fallback symptom, but it is not a substitute for executable coverage.

The exact RED record is `/Users/antropophag/.local/share/fmonitor-2/address-register-import/unknown-budget-gate3-record.json`. The pre-regression economy GREEN record after the owned-claim stale correction is retained separately and does not close this delta.

No production budget-state correction is present. After the test gap is corrected and independently approved, a separate executor may implement the state transition. The original ten importer tests, capacity/restore, adjacent/architecture checks, independent final review, and fresh exact-source full CI remain mandatory.

No production code edit, test edit, full local suite, live/network/provider request, real import, commit, push, merge, or deployment was performed by the reviewer.
