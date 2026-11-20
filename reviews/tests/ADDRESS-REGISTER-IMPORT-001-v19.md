# ADDRESS-REGISTER-IMPORT-001 — independent unknown-budget corrective Gate 3 review v19

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, tests, fixtures, production correction, or RED evidence
- Production base: `99232d9c263b0dc21fa1f2ab6a892e7e05f0f3a1`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261120T002223Z-4159e73a34/package.json`
- Candidate source: `35176cad753c0fc3d738bfe336730356ff35216ba5ca483fc39aef4d01d28690`
- Executable source: `c06dddc5477c190fb9af8b5b332de35ed5913d1554035e2880609ce5c0995899`
- Snapshot patch SHA-256: `249235728bf2743ef521c5078ba06b38155dd0639c590c89b53ca63be0909599`
- Previous corrective review: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v18.md` — `CHANGES_REQUESTED`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Resolution of v18 finding

**Resolved — both unknown-budget paths now prove durable claim identity, not only package status.** Immediately after provider-unknown, the test selects the owned cache row and captures its complete bytes, including `entry_id`, `entry_version`, `request_hash`, `lock_owner`, `lock_key`, request/response placeholders, timestamp, provider/rules metadata, and accepted flag. It also records the request-hash row count.

After the exhausted explicit retry, the test reads that same entry ID and requires the full row to equal the original pending row and the count to remain unchanged. It then performs a budget-only resume without `retryUnknown`, re-reads the same row, and again requires byte equality and unchanged count. Deleting, reconstructing, reallocating, re-owning, settling, or otherwise mutating the claim cannot pass.

The later valid explicit retry must complete the same `entry_id`, `entry_version`, and `request_hash`, clear `lock_owner/lock_key`, set the accepted result, and preserve the same request-hash row count. A newly allocated replacement claim or extra version fails. These assertions are repeated for both an uncached unknown and a refresh unknown with older completed fresh evidence.

## State-machine assessment

The contract now distinguishes three outcomes precisely:

- provider-unknown is durable uncertainty with an owned pending cache row and a consumed attempt;
- insufficient cumulative cap is a transient `blocked/REQUEST_BUDGET_EXCEEDED` response before transport and is not a provider outcome;
- valid explicit retry requires paid permission, `retryUnknown=true`, a nonempty reason, and enough cumulative cap to complete the retained claim.

Budget denial must not overwrite the package's durable `unknown/PROVIDER_OUTCOME_UNKNOWN` report, remove or mutate the pending claim, create another cache version, call the provider, or reuse an older fresh entry. A later budget-only resume remains unknown even with a larger cap and reason. The valid retry then performs exactly the authorized additional attempt and recovers normally.

The paired matrix is complete. The uncached path detects accidental new provider work after claim deletion. The fresh-cache path detects silent fallback to older accepted evidence. Existing call/attempt/budget assertions, durable report assertions, same-clock causal versions, immutable history, default/resume behavior, signed transfer, and valid recovery remain in place.

## Relationship to v17 implementation

The implemented Gate 3 v17 correction remains independently covered: requested-only identity lookup, separate pre/post-provider views, apply-time authority, causal per-request `entry_version`, strict schema/drift rules, allocator lock scope, same-clock refresh selection, single-flight concurrency, unrelated-hash progress, signed transfer, and atomic import rollback/retry.

Identity, concurrency, and schema are GREEN on that implementation. Economy was GREEN after the owned-claim stale guard correction before this new state-machine regression. The only new production correction authorized by v19 is the unknown-budget transition; it must not alter the approved lookup/version semantics.

The small prepared-result fixture correction normalizes `entry_version` to the actual integer shape before comparison. It adds no product rule, bypass, or production behavior and allows the test to reach the intended durable-claim boundary.

## RED evidence and lifecycle

The prepared package maps `php tests/InstallationProcess/address_register_import_economy_001_test.php` and records exact-source `INTENDED_RED`. The current implementation deletes the uncached pending claim during the exhausted retry: the expected original rows/counts are observed as `null/null/0/1`. This directly proves the reviewed failure instead of inferring it from a later provider call.

The exact record is `/Users/antropophag/.local/share/fmonitor-2/address-register-import/unknown-budget-v19-final-record.json`. Because the test stops at the first uncached claim assertion, the later fresh-cache and successful-completion assertions honestly await implementation and are not claimed GREEN.

This approval authorizes a separately assigned application executor to implement the bounded state transition. The original ten importer tests, full capacity/restore, adjacent/architecture checks, independent final review, and a fresh exact-source full CI remain mandatory. Prior final approval remains scoped to its source.

No production code edit, test edit, full local suite, live/network/provider request, real import, commit, push, merge, or deployment was performed by the reviewer.
