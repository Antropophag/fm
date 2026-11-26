# ADDRESS-REGISTER-IMPORT-001 — independent 21-field/main-reconciliation Gate 3 review v22

- Date: `2026-11-26`
- Reviewer: separately assigned `/root/fields21_review`; reviewer authored none of the specification, OpenSpec artifacts, tests, fixtures, production implementation, or RED evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T225439Z-f8fe238919/package.json`
- Candidate source: `1f7cdf45264c7e9403254e45dc79b756e887419f3931519cf8a187d1d5ae164a`
- Executable source: `7f7efa65b596f2cea23503119374e77b4fa4d24e4ecfeda629189fd492a76d75`
- Snapshot: base `dfb6927fb6f3624c007f895d45357f73358c1367`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T225439Z-f8fe238919/snapshot/source.patch`, SHA-256 `8de6355e1da9230499bba4db3bb2b42a49e412565e995cc940860dfc38c970ba`
- Verification plan SHA-256: `afc18825d039f722bdf2452d4ebe3744eb166e25ffa8c7ecb0ecee69d15a9d1b`
- Previous review: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v21.md` — `CHANGES_REQUESTED`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Resolution of v21 finding

**Resolved — populated v40/v41 predecessor data and allocator positions are now observed across migration and recovery.** `tests/Support/AddressRegisterPredecessorFixture.php` independently constructs valid rows in all five predecessor families: password events, recovery requests, recovery attempts, OTIZ hold events and payment allocations. It creates the required identity, credential, calculation, obligation and payment parents; preserves a literal binary token hash, microsecond timestamps and a nonzero `12345`-cent allocation; and advances the four AUTO_INCREMENT values to distinct gaps `920001` through `920004`.

The schema regression snapshots exact ordered rows, `SHOW CREATE TABLE` bytes and next allocator values before address v42. It compares the complete snapshot after first migration and after replay, in addition to the complete predecessor-table DDL/row snapshot. Truncation, DDL rewrite, allocator reset or predecessor mutation cannot pass.

The public recovery regression seeds the same fixture before backup, includes it in the 118-table binary-safe inventory, and requires exact rows, DDL and next IDs after restore and again after address-operation replay. `serialize`-based row hashing correctly preserves binary token bytes rather than assuming JSON-safe UTF-8.

The retained private setup probe executes this fixture against exact `origin/main` source `f935c748577510a4a50d877db51ecca067ee6c48`, reaches schema 41 in a disposable database, populates all five families/four sequence gaps and confirms an unchanged baseline replay. It is valid fixture qualification, not production GREEN.

## Complete Gate 3 assessment

The approved A13 matrix requires exactly B–H/P–AC in new parsing, projection and reports; preserves I–O/AD–AG only in raw source evidence; prevents excluded mutations in fill and overwrite; retains existing values, append-only history and historical 32-row JSON/CSV reports; rejects old pending 32-field packages and proves successful fresh 21-field recovery; preserves Z as duration; enriches later AB/AC on the same object/case; and proves replay without duplicate facts. Exact 2,668/667 and 19,994 cardinalities remain bounded by 256 MiB/16 MiB.

The approved A14 matrix fixes the combined canonical sequence at email 40, OTIZ 41 and additive address 42; requires order 1..42, populated predecessor preservation and replay; fixes recovery at 118 tables/64 AUTO_INCREMENT sequences; preserves the populated password/OTIZ/address union through public backup/restore; and observes upstream `stops` together with import-source provenance.

The main-frontier conflict resolutions remain private and outside this snapshot. Their exact merged bytes, including all 28 frontier consumers, three upstream current-frontier updates and final verification policy, require the declared supplemental independent review after the history-preserving main merge. This approval authorizes the separately assigned production executor for the reviewed 21-field and v42 production behavior; it does not approve unseen reconciliation bytes, final implementation, CI, publication, merge or deployment.

## RED evidence and verification

The rebuilt package records five preserved tests GREEN and five intended RED results. Schema fails at the absent canonical migration 42; restore fails at the absent `RuntimeRecoverySchemaV42`; parser, reports and identity fail on current 32-field cardinalities. Each failure is causally tied to missing reviewed behavior. Assertions after the first failure, including the corrected historical recovery/CSV and downstream v42/restore cases, honestly remain **UNKNOWN**, not GREEN, until implementation.

`git diff --cached --check` passed on the restored snapshot. PHP syntax checks passed for the predecessor fixture and changed schema/restore tests. No full local suite, live/provider request, paid call, real import, production edit, commit, push, merge, deployment, or review of the private main-frontier resolutions was performed.
