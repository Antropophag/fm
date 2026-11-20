# ADDRESS-REGISTER-IMPORT-001 — independent lookup/refresh corrective Gate 3 review v17

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, design, tests, fixtures, production implementation, or RED evidence
- Production base: `99232d9c263b0dc21fa1f2ab6a892e7e05f0f3a1`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261120T000612Z-46fb4571c9/package.json`
- Candidate source: `3701d8e0cd07ea6cb255cfded57782a4c6193507eb6211882fffb0e547e33c8a`
- Executable source: `15fe561761c20d96bc51f9adbe219113e5ce112db34c695442f3142e5209944c`
- Snapshot patch SHA-256: `cb06bbec2376e4a60987cd4d9251fe8776716e6fb82fad0f8ce53e189ae68df5`
- Previous corrective review: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v16.md` — `CHANGES_REQUESTED`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Resolution of v16 finding

**Resolved — same-clock cache ordering now has a causal key independent of timestamp and UUID.** The unpublished v40 cache family adds `entry_version BIGINT UNSIGNED NOT NULL` with no default or AUTO_INCREMENT, `CHECK(entry_version > 0)`, and `UNIQUE(request_hash, entry_version)`. Versions increase within each retained request-hash history. Completed selection orders by actual capture time and causal version; opaque random entry IDs no longer determine freshness.

The same-clock regressions keep the exact clock unchanged, pin the old entry to the maximum valid v4 UUID, change the provider payload, and require the new payload from the explicit refresh, default preview, ready resume, competing-package resume, and signed target transfer. They require the old row to remain byte-identical and all same-clock rows to have increasing versions. A timestamp-plus-random-UUID implementation cannot pass.

## Schema and allocator review

The version column is part of the existing unpublished cache table, so the canonical frontier remains 113 tables and 60 AUTO_INCREMENT families. The independent literal schema oracle checks exact column order/type/null/default/extra, retained collation/engine and existing fields, exact primary/one-inflight/request-time keys, the ordered unique request-version key, JSON checks, and the positive version check. SQL enforcement rejects zero, negative, and duplicate per-request versions.

Drift probes cover wrong width, nullable version, a default, missing/nonunique/reordered unique key, missing positive check, and weakened positive check. Both `apply` and `assertCompatible` must reject every drift, including with a missing sibling, without repair or sibling creation. Existing chunk/schema/prefix assertions remain intact.

Provider claims and atomic signed-evidence import share a short database-and-prefix-scoped allocator lock. Version allocation is local to a request hash and occurs under that lock; the lock is released before provider transport and in every failure path. The existing `one_inflight` key continues to own provider single-flight. Gaps after discarded claims are allowed, while retained entries remain strictly monotone and immutable.

The concurrent regression holds one provider after its claim, completes an unrelated request hash before releasing the first provider, and requires the unrelated sequence to start at version 1 with one independent attempt. This proves allocator coordination is not held across network transport and does not serialize unrelated provider work. The competing same-hash refresh pays zero while the claim is held and later resumes from the new same-clock version.

Signed export preserves authenticated entry order for equal capture times. Target import allocates target-local versions in that order inside one transaction, so source object IDs or source-local version numbers are not transferred. A trigger faults a later version insert; the entire import must roll back to the exact prior cache state, release the allocator lock, and succeed completely on retry without provider calls.

## Refresh semantics review

The grouped economy test covers the full explicit-refresh vector. No paid permission returns `PAID_CALLS_NOT_ALLOWED`; budget zero returns `REQUEST_BUDGET_EXCEEDED` without a call; an allowed refresh reserves exactly one attempt and publishes one immutable new version; subsequent default preview and ready resume use it for free. An unknown refresh remains unknown on ordinary resume, never falls back to the old fresh row, and requires explicit retry permission, reason, and cumulative budget before a recovered version is published.

Historical rows are compared against the pre-refresh snapshot, catalog/detail facts remain unchanged, provider/attempt counts are exact, and signed transfer selects the newest changed payload under the same timestamp. Existing stale-refresh, negative cache, TTL, evidence authentication, and retry bounds remain unchanged.

## Bounded identity lookup review

The A04 design remains approved as reviewed in v16. Requested objects and relevant full rows are spooled privately; memory holds domain-separated digest indexes, offsets, and at most two distinct witnesses. The store scans the same effective/manual/source projection once through keyset pages of at most 256 and performs exact binary comparisons for candidates. Pre-paid validation and post-provider preparation construct independent fresh views, while apply retains current transactional find/lock/revision/identity authority.

The public regression preserves manual tombstones and immutable bindings, observes a manual identity change during provider work, rejects a late native ambiguity at apply with zero published counters, exercises binary case sensitivity, and measures additive SQL Handler work for 20/40 source rows across 2,674 unrelated catalog rows. Complete reports and provider/attempt/business/history invariants remain asserted.

## RED evidence and lifecycle

The prepared package binds four commands to the exact candidate and records all four as truthful `INTENDED_RED`:

- identity fails last on the measured multiplicative work oracle after semantic guards;
- economy fails on current fresh-refresh permission/budget/call behavior;
- concurrency fails because current refresh never reaches the held provider;
- schema fails at the absent exact version column before downstream schema assertions.

The records are retained in `/Users/antropophag/.local/share/fmonitor-2/address-register-import/lookup-refresh-v17-final-records.json`. Because production is unchanged, downstream same-clock, allocator, transfer, rollback, drift, and bounded-work assertions honestly await implementation and are not claimed GREEN.

This approval authorizes separately assigned store/application executors to implement the reviewed correction. The original ten importer tests, full capacity/restore, architecture/adjacent checks, independent final review, and a fresh exact-source full CI remain mandatory. Prior final approval remains scoped to its source.

No production code edit, test edit, full local suite, live/network/provider request, real import, commit, push, merge, or deployment was performed by the reviewer.
