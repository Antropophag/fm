# ADDRESS-REGISTER-IMPORT-001 — independent corrective Gate 3 review v8

- Date: `2026-11-19`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, OpenSpec artifacts, tests, fixtures, production implementation, or evidence
- Corrective test/spec author: root; production implementation is unchanged from final review v1
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T191825Z-691d49e174/package.json`
- Base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Candidate source: `d31e86f03a3bb5f1384b4e55bdcb12ebdf195d5e63227bc6d0b5d55328950955`
- Executable source: `758ad018a3557ad25c50c0c0f2112f303d6a206d8ce237aceae1f1ea5dcd00f6`
- Snapshot patch: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T191825Z-691d49e174/snapshot/source.patch`, SHA-256 `c5650f1489b99ed378b8ec2210ad7adf948db10231ca5d97ee33f0ded5b8ed33`
- Previous review: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v7.md` — `CHANGES_REQUESTED`
- Verdict: **APPROVED** for corrective Gate 3

## Findings

No blocking or non-blocking findings.

## Resolution of v7 findings

1. **Resolved — the 19,994-row boundary fixture now has valid distinct source identities.** `tests/Runtime/address_register_import_restore_001_test.php` assigns each row a distinct registration number and UNOM while retaining one shared address and entrance. This is consistent with the established `regnumber|unom|entrance` identity rule, retains the offline one-address cache scenario, requires all 19,994 objects and 639,808 field rows, and keeps row 20,001 on a genuinely uncached address with the before-provider assertion. The private public-seam probe first proves the corrected small fixture is accepted and cached, then reaches `MariaDbAddressRegisterImportStore::savePackage` with 19,994 rows and fails with `MySQL server has gone away`; it no longer fails identity validation. That probe is useful RED characterization of the existing packet defect, not production GREEN.

2. **Resolved — the payload-chunk family now has independent exact schema expectations.** The contract states the complete ordered column/type/null/default tuple, binary UTF-8 collation, sole four-column primary key, restrictive package foreign key, six independent semantic checks, and absence of AUTO_INCREMENT. `tests/InstallationProcess/address_register_import_schema_001_test.php` derives none of these expectations from production DDL. It also exercises the constraints with accepted and rejected inserts and probes type, nullability, defaults, signedness, AUTO_INCREMENT, primary-key composition/order, extra indexes, collation, foreign-key absence/rules, engine, missing checks, and weakened checks. Every chunk drift is submitted independently to both `assertCompatible` and `apply`, including with a missing sibling, and the complete DDL inventory must remain unchanged. Recovery inventory and the 113-table/60-AUTO_INCREMENT frontier remain tied to the seventh family.

The private literal-DDL rehearsal confirms that the independent oracle and drift mechanics can execute against a conforming synthetic table. It deliberately bypasses production migration/readiness seams, so it is not evidence that the future implementation satisfies them.

## Prior final-review findings and corrective coverage

- **F1, unknown commit outcome:** the real CLI/factory/application/store test remains suitable. Its wire proxy forwards one real `COMMIT`, consumes the server success, suppresses only the ACK, and requires exit `75`, `unknown`/`COMMIT_OUTCOME_UNKNOWN`, followed by fresh-process reconciliation with no duplicate facts or second commit.
- **F2, 256 MiB / 16 MiB capacity:** the tests require the supported 2,668-row/667-address flow and the exact 19,994-row boundary to preserve complete reports through apply/export and backup/restore without raising application or MariaDB limits. The corrected large private probe reaches the current persistence packet failure and therefore supports the intended RED lineage; it does not satisfy the unexecuted downstream assertions.
- **R1, failed-apply publication:** the late-fault test requires zero published created/updated/filled/replaced counters and rollback of catalog, audit, and preparation payload facts.

These are adequate executable requirements for the next implementation stage. Final-review findings F1/F2/R1 remain open until separately authored production corrections make the exact tests pass and receive the required independent final review.

## Contract and test assessment

The corrective design preserves the public CLI/application seams, authorization, paid-call ordering, identity checks, append-only history, replay, complete ordinary JSON/CSV reports, and backup/restore behavior. The v40 change is bounded to immutable payload chunks and compact published manifests. Publication of applied chunks and outcome remains in the catalog/audit transaction; incomplete generations are invisible, old generations remain immutable, and no memory/packet-limit increase, report truncation, history deletion, or production fault hook is allowed.

The six preserved GREEN tests cover unaffected behavior. The four `INTENDED_RED` results truthfully expose unchanged production gaps: failed-apply counters, absent payload schema/inventory, real CLI exit `69` instead of `75`, and eager persistence/report capacity. Because schema and restore tests currently stop at the absent seventh family, downstream literal schema, drift, full-scale apply/export, and restore assertions are still unexecuted and **UNKNOWN**, not GREEN. That is appropriate before production correction and must be closed by implementation evidence.

## Verification

The prepared package binds ten mapped commands to the exact candidate and executable sources: six `GREEN` and four `INTENDED_RED`. The snapshot base and patch digest match its manifest. `git diff --check` passes, and the changed schema, restore, and reports PHP tests pass syntax checks. I inspected the complete v7-to-v8 delta, the contract/OpenSpec alignment, exact test assertions, package records, and the supplied private SQL/boundary probes.

No full local suite, live/provider request, source import, deployment, commit, or production change was performed. Corrective Gate 3 is **APPROVED** for this exact spec/test snapshot; this approval does not approve production implementation, final readiness, CI, publication, deployment, or live DaData use.
