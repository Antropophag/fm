# ADDRESS-REGISTER-IMPORT-001 — independent Gate 3 test-delta review v4

- Date: `2026-11-18`
- Reviewer: separately assigned `/root/import_test_delta_review` (`gpt-5.6-sol / low`)
- Actual test author: root; the reviewer authored none of the specification, OpenSpec artifacts, tests, fixtures, implementation, or RED evidence
- Scope: retrospective regression-test-only delta reconstructed on the pre-implementation baseline; this review does not claim that implementation work had not begun elsewhere
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T194030Z-107590361c/package.json`
- Candidate source: `5680f37aa148f06b92314d613d99fe3ccb7aff79c7887641c74a225be1b371d0`
- Snapshot: base `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T194030Z-107590361c/snapshot/source.patch`, SHA-256 `3711714c246c324744102c5a11b6aa7794ac5b65d31d8aabef0867f2f4c1550e`
- Restored review checkout: `/Users/antropophag/.local/share/fmonitor-2/address-register-import/test-delta-review`
- Prior approved source: `1c0e0f0db6253826f51de0a1b7c769a33ecfdb53d4159a9356563d06552e4465`, reviewed in `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v3.md`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Delta review

The reconstructed candidate changes only five previously approved test files: parser, provider, integration, schema, and economy. The file hashes match the supplied test-delta context. The delta adds assertions for behavior already required by the approved normative contract and does not add a new product outcome.

The parser additions fix the public 32-field B:AG mapping, reject a numeric value whose non-date style cannot justify date conversion, and reject truncated XML plus duplicate row or cell elements instead of silently losing data. These expectations are independent of parser internals and exercise the public workbook seam.

The provider additions require successful validation to return the canonical address and FIAS house identity, and require exact typed failure classifications for authentication, rate limiting, server failures, invalid HTTP statuses, malformed responses, and oversized responses. Retryability and unknown-outcome expectations follow the contract's budget owner boundary: the provider performs one attempt and exposes enough typed information for the application owner to decide whether a paid retry is permitted.

The integration additions require identical re-imported values to remain unchanged without false warnings and require the provider's canonical address to become the current value while the original spreadsheet spelling remains in raw provenance. The resume fixture correctly changes from house 11 to fresh house 13: evidence for valid house 11 is retained even though its earlier mixed batch was rejected, so reusing it would bypass the intended provider-failure path.

The schema additions cover semantic drift in type/nullability, uniqueness, and referential integrity, and prove `assertCompatible` is read-only. The final case intentionally combines an incompatible existing table with a missing sixth table and proves `apply` performs complete preflight before creating the missing table. This closes the gap where matching names alone could be mistaken for schema compatibility or partial DDL could precede rejection.

The economy additions require a reason for budget expansion and retry after an unknown paid outcome, reject duplicate source identity before provider spend, enforce importer authorization on evidence export/import without cache mutation, and require explicit paid refresh to create a new cache version while preserving prior evidence. The same response is deliberately allowed as a new immutable evidence version, and subsequent preview reuses it offline.

The previously approved full A01–A12 matrix remains present. These additions strengthen exact conformance at parser, provider, persistence, authorization, schema-readiness, and paid-call boundaries without weakening prior assertions or relying on mutable production implementation as the oracle.

## Verification evidence

The snapshot manifest and patch digest were checked, and the restored checkout is based on the recorded commit. `git diff --check` passed. PHP syntax validation passed for all five changed tests, and their SHA-256 values exactly match `/Users/antropophag/.local/share/fmonitor-2/address-register-import/test-delta-context.json`.

All ten mapped focused tests have retained `INTENDED_RED` records with exit `255` against exact candidate source `5680f37aa148f06b92314d613d99fe3ccb7aff79c7887641c74a225be1b371d0`. This is retrospective regression evidence on the reconstructed pre-implementation baseline, not a chronology claim about parallel implementation work. The verification plan selects `CRITICAL` with required reviews `gate3` and `final`. No full local suite and no live provider call were run.

Gate 3 is **APPROVED** for this exact reconstructed test snapshot. The approval covers the test-only delta plus the preserved v3 matrix; it is not GREEN evidence, final implementation review, CI approval, publication, or authorization for a live DaData request.
