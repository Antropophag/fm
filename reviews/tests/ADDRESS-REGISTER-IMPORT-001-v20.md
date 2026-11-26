# ADDRESS-REGISTER-IMPORT-001 — independent 21-field corrective Gate 3 review v20

- Date: `2026-11-26`
- Reviewer: separately assigned `/root/fields21_review`; reviewer authored none of the specification, OpenSpec artifacts, tests, fixtures, production implementation, or RED evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T223941Z-3b2a4cf33c/package.json`
- Candidate source: `7c030ea875655baa4218bbe76b999c09e8b3d626cf38851ac86e2daa9d5da9a7`
- Executable source: `2257924236c3c9cd2525bef7531d9ff25bbca3b7b99da992b4e11d3df0f4f80d`
- Snapshot: base `dfb6927fb6f3624c007f895d45357f73358c1367`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T223941Z-3b2a4cf33c/snapshot/source.patch`, SHA-256 `de4b443af3e2db43875b5eee8cb6164d4bd1a68fba224a7f743ddf3e8702cce0`
- Verification plan SHA-256: `605dc2c98114f7eb2a31c85e7bc68474b25abfa5a695dc89d5ee29481f84c50b`
- Verdict: **CHANGES_REQUESTED**

## Findings

1. **MAJOR — the old pending-package regression proves rejection but not the required successful re-preview recovery.** A13 requires an unapplied 32-field package to require a new preview, and the owner matrix explicitly calls for old pending packages to reject and re-preview. `tests/InstallationProcess/address_register_import_reports_001_test.php:101-112` creates historical `ready` and `blocked` packages and correctly requires `PREVIEW_STALE` from `resume` and `apply`, but it never follows either rejection with a fresh public `preview` and `apply`. A regression that permanently strands the operator after `PREVIEW_STALE`, or that leaks excluded values while recovering, would pass.

   **Correction:** after each old pending rejection, create a fresh preview from the retained source through the public seam and apply it. Require exactly 21 rows, stable object identity, preservation of the 11 legacy values and prior history, and no mutation/report row for excluded I–O/AD–AG values. Prove the new operation succeeds without rewriting the old package/report.

2. **MODERATE — historical CSV compatibility is not observed.** A13 requires read/export of a saved historical report to preserve its prior 32 field rows. The same test proves `report()` has 32 rows and compares the exported JSON byte-for-structure with the saved result, but `:112` only checks that CSV export returns `exported`; it never reads the CSV. An implementation that silently emits only the new 21 rows or drops the 11 legacy field names/values from historical CSV would pass.

   **Correction:** read the exported historical CSV and require 32 data rows in the preserved order, including the 11 legacy field names and their saved values. Keep the existing JSON/report immutability checks.

No other blocking or non-blocking findings.

## Assessment

The A13 contract otherwise states the owner decision precisely: new parsing, projection and reports contain B–H/P–AC only; excluded cells remain in raw source evidence; existing values and append-only history remain readable; Z remains installation duration; later AB/AC enrich the same object and case; replay adds no facts; and the 2,668/19,994 capacity cardinalities are updated without changing the 256 MiB/16 MiB bounds.

The parser and report tests use public preview/apply/report/export seams for acceptance behavior. Direct mutation/store/operation writes are clearly confined to constructing historical fixtures that the new public parser cannot create. This is maintainable enough for Gate 3, although a dedicated historical-package fixture helper would reduce persistence coupling.

## RED evidence and lifecycle

The package records six preserved tests GREEN and four intended RED results. Parser fails on the current 32-field mapping, reports fails on 28 rather than 19 filled fields, restore fails on 74,704 rather than 50,692 filled fields, and identity fails on 640 rather than 420 report rows. These are intended missing-behavior failures rather than fixture/setup failures. Assertions after each first failure, including the new A13 historical/recovery path and downstream capacity/restore checks, honestly remain unexecuted and are **UNKNOWN**, not GREEN.

`git diff --cached --check` passed on the restored snapshot. PHP syntax checks passed for the changed parser, reports, identity and restore tests. No full local suite, live/provider request, paid call, real import, production edit, commit, push, merge, deployment, or main-reconciliation review was performed.

This verdict is limited to the narrow 21-field snapshot above. Current `origin/main` schema v40/v41 reconciliation and the proposed address-import v42/118-table/64-AUTO_INCREMENT delta are outside this package and require their own root-authored specification/test delta and independent review before final freeze.
