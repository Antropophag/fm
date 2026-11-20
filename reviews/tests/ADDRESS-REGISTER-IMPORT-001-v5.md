# ADDRESS-REGISTER-IMPORT-001 — independent Gate 3 final test-delta review v5

- Date: `2026-11-18`
- Reviewer: separately assigned `/root/import_test_delta_review` (`gpt-5.6-sol / low`)
- Actual test author: root; production implementation was authored separately; the reviewer authored none of the specification, OpenSpec artifacts, tests, fixtures, implementation, or RED evidence
- Scope: final retrospective regression-test-only delta reconstructed on the pre-implementation baseline; this review does not claim that implementation work had not begun elsewhere
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T201925Z-6ac2b43974/package.json`
- Candidate source: `ba1e6e267988cbcae3967339c868967b858af8a98f57399901afd4acff2f98da`
- Snapshot: base `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T201925Z-6ac2b43974/snapshot/source.patch`, SHA-256 `4cfe68e5fd2103568b879ed7703eb3ae9a68a195d44c9be5fe74db3fbe2655d4`
- Restored review checkout: `/Users/antropophag/.local/share/fmonitor-2/address-register-import/test-delta-review`
- Prior approved source: `5680f37aa148f06b92314d613d99fe3ccb7aff79c7887641c74a225be1b371d0`, reviewed in `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v4.md`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Delta review

Relative to the approved v4 snapshot, the final candidate changes five test files: integration, economy, reports, identity, and runtime restore. The exact ten-test hashes match `/Users/antropophag/.local/share/fmonitor-2/address-register-import/test-delta-v5-final-hashes.json`. The additions cover behavior already required by the normative A01–A12 contract and do not introduce a new product outcome.

The integration addition proves that a source spelling already normalized to the provider's canonical address can be imported again as the same unchanged object, without a false identity conflict, warning, update, creation, or provider call. It complements the v4 canonical-persistence assertion and verifies both sides of the required raw/canonical separation through the public application seam.

The economy addition separates two permissions after an unknown paid outcome. An earlier invalid retry request cannot erase the durable `PROVIDER_OUTCOME_UNKNOWN` state, and increasing the budget with a reason still cannot retry unless `retryUnknown` is explicitly authorized. The test preserves the provider call count until both permissions are present, so it catches accidental spend as well as state loss.

The report additions reject an invalid traversal-shaped package ID, a valid but unknown package ID, a target mismatch, and a private-root path with a symlink ancestor before creating files. They assert the destination remains empty and the traversal target remains absent. The real Yii console estimate and cached preview are also required to match the dedicated CLI outcomes with transport functions disabled, closing the public adapter parity gap without a live provider.

The identity additions exercise two sensitive cases through public behavior. A native writer occupying the allocator's next candidate ID must remain untouched while one import apply allocates a distinct ID. A stable imported source whose regnumber or entrance was changed to a new nonempty manual value through `ObjectDetailsEditCommand` must be rejected in both ordinary and overwrite preview; the test restores each value through the same public editor seam. This preserves external authorship, append-only editing, and the contract's distinction between a manual clear and a conflicting nonempty identity.

The runtime addition removes one import-family table from a real prepared runtime and proves deep readiness returns `SCHEMA_NOT_READY` without creating, renaming, or repairing any table. It verifies that the address-import family is part of the operational readiness frontier and that readiness remains read-only.

The previously approved parser, provider, schema, concurrency, CLI, recovery, reports, identity, integration, and economy expectations remain present. The final delta strengthens sensitivity at money, identity, filesystem, schema-readiness, and adapter boundaries while retaining deterministic fake-provider and isolated database/filesystem fixtures. Expected results come from the normative contract and synthetic setup rather than mutable implementation helpers.

## Verification evidence

The snapshot manifest and patch digest were checked, and the restored candidate is based on the recorded commit. The v4 snapshot was independently restored and compared with v5 to isolate the five-file delta. `git diff --check` passed. PHP syntax validation passed for all ten mapped tests, and every test file's SHA-256 matches the supplied final hash inventory.

All ten mapped focused tests have retained `INTENDED_RED` records with exit `255` against exact candidate source `ba1e6e267988cbcae3967339c868967b858af8a98f57399901afd4acff2f98da`. This is retrospective regression evidence on the reconstructed pre-implementation baseline, not a chronology claim about parallel implementation work. The verification plan selects `CRITICAL` with required reviews `gate3` and `final`. No full local suite and no live provider call were run.

Gate 3 is **APPROVED** for this exact final reconstructed test snapshot. This approval covers the v5 test delta plus the preserved approved matrix; it does not approve production code or architecture and is not GREEN evidence, final code review, CI approval, publication, or authorization for a live DaData request.
