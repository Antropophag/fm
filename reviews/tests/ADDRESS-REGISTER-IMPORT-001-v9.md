# ADDRESS-REGISTER-IMPORT-001 — independent supplementary Gate 3 review v9

- Date: `2026-11-19`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, tests, fixtures, production implementation, or evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T195953Z-5627381c77/package.json`
- Base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Candidate source: `254b6f3cfef8fcebd69973bc2197d6c0109c241674e72b46cad94d32016286cb`
- Executable source: `07b3f0f99615915cb6459f82ab5906cb762a7ae589bc094ccc569a3dac98d020`
- Snapshot patch: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T195953Z-5627381c77/snapshot/source.patch`, SHA-256 `7835c84ae94c365f192860335c991aa5fa5bca80dd76eda0fa13fb649f0b48bf`
- Previous full corrective review: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v8.md` — `APPROVED`
- Verdict: **CHANGES_REQUESTED** for the supplementary delta

## Findings

1. **BLOCKING — malformed signed evidence can satisfy the new test with the wrong public outcome.** `tests/InstallationProcess/address_register_import_reports_001_test.php:59` records a failure only when the CLI returns exit `0`; any nonzero result passes this part of the grouped assertion. The established contract already requires malformed/untrusted evidence to return `status=rejected`, `reasonCode=EVIDENCE_INVALID`, which maps to CLI exit `2`. An implementation that rejects trailing NUL as generic configuration failure `64`, database/provider failure `69`, schema failure `78`, or another unrelated error would therefore turn the test GREEN while violating the public contract. The helper also proves stdout is JSON, but it does not constrain that JSON's status or reason. **Correction:** assert the exact tuple `[2, 'rejected', 'EVIDENCE_INVALID', '']` for the trailing-NUL CLI call, while retaining the before/after cache assertion. If aggregation of all boundary symptoms is required, append a descriptive failure for every tuple mismatch instead of hard-stopping before the final grouped assertion.

2. **BLOCKING — the “full unknown report” oracle verifies cardinality, not the preserved report contents.** `tests/InstallationProcess/address_register_import_reports_001_test.php:52` checks exit/status/reason, summary, and only `count=32/1/1` for `rows`, `objects`, and `providerEvidence`. The new contract requires the complete reviewed preview report to survive ACK loss with the same values, indexes, and order. A result containing 32 empty or fabricated rows, one wrong source object, and one wrong evidence item would pass. This is the exact risk introduced by the new streaming adapters: retaining container lengths does not prove retained payload. **Correction:** materialize the small single-object sequences through the existing test helper and compare the unknown result's complete `rows`, `objects`, and `providerEvidence` to the reviewed `$faultPreview` values, preserving order and every field. Keep the exact summary and exit/status/reason assertions.

No other blocking or non-blocking findings.

## Assessment of the supplementary scope

The contract clarification is coherent with the already approved import contract. XLSX input is a local regular-file boundary, so a URL/non-file stream must be rejected before `open` or `stat` and before provider activity. The test-only registered URL wrapper is a suitable public-application probe: every wrapper access is observable, it performs no network request, and the test requires a rejected result with zero provider calls.

Treating a literal NUL as EOF is incompatible with strict JSON. The real CLI import path and signed exported bundle are the right boundary; checking cache state before and after prevents accepting a malformed prefix and publishing its entries. Subject to finding 1, this is a suitable regression test and requires no production fault hook.

The real MySQL wire proxy remains a suitable unknown-COMMIT probe. It forwards the actual `COMMIT`, consumes the server ACK, suppresses only delivery of that ACK, requires exit `75` with `unknown/COMMIT_OUTCOME_UNKNOWN`, and then proves fresh-process reconciliation performs no second commit or duplicate business write. Subject to finding 2, extending this scenario to the complete report is correctly scoped.

## Evidence and lifecycle

The package maps only `php tests/InstallationProcess/address_register_import_reports_001_test.php` for this supplementary delta and records it as `INTENDED_RED` against the exact candidate/executable sources. The grouped failure truthfully exposes unchanged production behavior; it is not implementation GREEN. PHP syntax and `git diff --check` pass.

The separately supplied frozen baseline evidence records all ten full importer tests GREEN, including the 2,668/667 and 19,994-row boundaries, backup/restore, replay, schema, and concurrency, plus architecture and six adjacent checks. That evidence belongs to the prior baseline source and does not prove the current supplementary assertions. The original full verification input remains the authority for final review and CI.

Full corrective Gate 3 v8 remains valid for its exact reviewed snapshot. This supplementary `CHANGES_REQUESTED` verdict is limited to the two incomplete new oracles above. Production fixes have not started and are not reviewed here.

No full local suite, live/provider request, import, deployment, commit, or production edit was performed.
