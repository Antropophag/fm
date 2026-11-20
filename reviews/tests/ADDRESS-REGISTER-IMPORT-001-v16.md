# ADDRESS-REGISTER-IMPORT-001 — independent lookup/refresh corrective Gate 3 review v16

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, design, tests, fixtures, production implementation, or RED evidence
- Production base: `99232d9c263b0dc21fa1f2ab6a892e7e05f0f3a1`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T235613Z-98ba986482/package.json`
- Candidate source: `b914a45147935c53fca449306b986a11304e2e4e0c75d170b50f11316ce718ad`
- Executable source: `b98e031b86e1852d365ec7bb98ca38428dca6bcec5f79be721749d07bf98c85f`
- Snapshot patch SHA-256: `cfcc9e23ed0989029a5586e519fcbfdc4a5a883f0ee52bc638f4be27d48a201c`
- Verdict: **CHANGES_REQUESTED**

## Findings

1. **BLOCKING — explicit refresh is not specified or tested deterministically when immutable cache versions share the same timestamp.** Production CLI clocks use `gmdate('c')`, which supplies second precision; application timestamps are consequently stored with `.000000` microseconds. Cache selection orders completed versions by `captured_at_utc DESC, entry_id DESC`, and `entry_id` is a random UUID. A fresh entry and its explicit refresh can therefore legitimately have identical timestamps, leaving random UUID order to decide which version is “latest.”

   The new economy and concurrency scenarios advance the test clock from `18:00:00` to `18:00:01` before refresh. They prove the different-entry behavior only when timestamps differ. An implementation can pass by reading the ordinary top completed row and comparing its `entry_id` with the initially observed row; with equal timestamps it may select the old row, silently reuse the pre-refresh evidence, make an unnecessary second paid refresh, or fail to let a competing package resume from the newly completed version.

   This is material to the proposed correction because the contract explicitly requires an explicit refresh to create/use a new immutable version and requires a competitor to resume from newly completed evidence. **Correction:** define selection independently of timestamp/UUID ordering and add a same-clock regression. The store seam should query for a completed row whose immutable `entry_id` differs from the initially observed entry (or use another deterministic causal identity), rather than retrieve an arbitrary timestamp-tied top row and compare afterward. Keep the clock unchanged, or force old and new rows to the same `captured_at_utc`, and prove: one paid refresh, the new response/version is used, the old row remains immutable, a competitor pays zero and resumes from that completion, and no random `entry_id` ordering changes the result.

No other blocking or non-blocking findings.

## Bounded identity lookup assessment

The A04 correction is otherwise coherent and adequately exercised. `findMany(objects)` owns a requested-only lookup whose requests and matched rows live in private spools; in-memory state is limited to domain-separated digest indexes, offsets, and at most two distinct witnesses per input. A single keyset scan of the same effective/manual/source projection is bounded to pages of at most 256 and exact comparisons resolve digest candidates. This changes read cost without adding a durable projection, schema, uniqueness rule, or apply-time trust.

The two lookup phases are correctly separated. Pre-paid validation uses a fresh view before provider work; preparation builds a new view after provider work. A manual identity edit performed inside the provider call must be visible to preparation. Each preview/resume constructs and discards its own views. Apply retains its existing current lookup, locks, revision checks, immutable bindings, and transaction authority; a late two-row native collision must fail apply and roll back all published counters/facts.

The grouped identity test preserves the semantic frontier: binary registration equality, manual tombstones, immutable source binding, source/hash match routes, object-ID deduplication, ambiguity on two witnesses, provider/attempt invariants, complete 32-field reports, and unchanged business/history state. The deterministic Handler-read assertions compare 20 requested objects before and after 2,674 unrelated rows, then 40 objects, and require additive rather than source-times-catalog work. The exact RED (`1166/322041/644041`) reaches all semantic guards before failing the cost oracle.

## Refresh assessment apart from the timestamp tie

The fresh-cache regressions correctly expose the current bug: explicit refresh without permission is blocked; budget zero must return `REQUEST_BUDGET_EXCEEDED`; one permitted refresh must reserve exactly one attempt and create a new immutable row; ordinary subsequent preview/resume must reuse evidence without payment. Existing stale-refresh and unknown-outcome/retry coverage remains intact.

The held-provider two-worker scenario is the right public concurrency seam. It requires the first refresh to hold the durable claim, the competitor to pay zero while observing `CACHE_BUSY`, and later resume from the completed version. It preserves single-flight, immutable version history, cumulative budgets, and no silent fallback after unknown outcomes. Subject to finding 1, the scope is sufficient.

## RED and lifecycle evidence

The prepared package binds three commands to the exact source and records all three as truthful `INTENDED_RED`:

- identity: lookup work remains multiplicative;
- economy: fresh refresh with cap zero incorrectly returns ready and permitted refresh spends zero attempts;
- concurrency: explicit refresh never reaches the held provider.

The records are retained in `/Users/antropophag/.local/share/fmonitor-2/address-register-import/lookup-refresh-gate3-records.json`. No production correction is present in the reviewed source.

The second full CI run timed out integration2 only after the capacity regression completed GREEN; it contains zero `REGRESSION_FAILURE` markers, and aggregate verify/quality failures are downstream. That evidence identifies the performance symptom but is not final GREEN. The original ten importer tests, capacity/restore, architecture checks, and a fresh exact-source full CI remain mandatory after correction.

Prior final code approval remains scoped to its exact source and does not approve this correction. No production code edit, test edit, full local suite, live/network/provider request, real import, commit, push, merge, or deployment was performed by the reviewer.
