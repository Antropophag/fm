# ADDRESS-REGISTER-IMPORT-001 — independent 21-field/main-reconciliation Gate 3 review v21

- Date: `2026-11-26`
- Reviewer: separately assigned `/root/fields21_review`; reviewer authored none of the specification, OpenSpec artifacts, tests, fixtures, production implementation, or RED evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T224830Z-4ba936ca75/package.json`
- Candidate source: `99f3162cfeb653d7d52732e50b4f38d75640eb2f36504cf5bd11d8256a1659da`
- Executable source: `2287e555c75316847e90391fc4d56ffdcfdccbc2ff4a257dbbaec26d4424e317`
- Snapshot: base `dfb6927fb6f3624c007f895d45357f73358c1367`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T224830Z-4ba936ca75/snapshot/source.patch`, SHA-256 `b44dada2f2e7984d2e7591639b858c1768eed6de7fa480120b8933f873059b81`
- Verification plan SHA-256: `5303aa86b58032c4a90d74619c94719b0d416324bee05c9e25d9948937727604`
- Previous review: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v20.md` — `CHANGES_REQUESTED`
- Verdict: **CHANGES_REQUESTED**

## Finding

1. **MAJOR — A14 does not prove preservation and recovery of populated v40/v41 predecessor families.** The contract requires predecessor DDL and rows to remain unchanged and public backup/restore to preserve the union of password, OTIZ and address families, including sequences. `tests/InstallationProcess/address_register_import_schema_001_test.php:10-13` runs the v41 predecessor and snapshots every table, but it inserts only a legacy catalogue row; the new password-event/recovery and OTIZ hold/allocation families remain empty. `tests/Runtime/address_register_import_restore_001_test.php:29-39` likewise hashes and restores the 118-table inventory without first placing representative rows and non-default AUTO_INCREMENT gaps in the v40/v41 families.

   Empty-table DDL equality and inventory membership cannot catch a reconciliation that truncates predecessor rows, resets their allocator positions, or omits their populated contents from backup/restore. Such an implementation could pass every new A14 assertion while violating the explicit compatibility requirement.

   **Correction:** before address migration, seed valid representative password and OTIZ predecessor rows through bounded fixtures or literal independent setup, including rows in both AUTO_INCREMENT families and advanced next values. In the canonical migration regression, compare exact ordered rows, DDL and next AUTO_INCREMENT values before/after v42 and replay. In the public backup/restore regression, include populated password and OTIZ rows plus their sequence gaps in the pre-backup inventory and prove exact restored equality. Existing upstream tests may supply valid fixture shapes, but their independent execution does not replace observing the combined v42 and union-recovery boundary here.

No other blocking or non-blocking findings.

## Resolution of v20 findings

1. **Resolved — both old pending statuses now recover through the public seam.** For historical `ready` and `blocked` packages, the test first requires `PREVIEW_STALE` from resume/apply, then creates and applies a fresh 21-field preview. It requires stable identity, zero created/updated/filled/replaced facts, unchanged legacy values/history, unchanged old report and no provider call.

2. **Resolved — historical CSV compatibility is now complete.** Each old package export is read as CSV; the regression requires 32 data rows, exact saved field order and all 11 legacy field values, while retaining the complete JSON equality check.

## Remaining scope assessment

The 21-field A13 coverage is otherwise suitable: exact B–H/P–AC order, raw preservation of I–O/AD–AG, absence of new excluded requisites and field rows, Z duration, later AB/AC enrichment, stable identity/case, replay without duplicate facts, legacy values/history/report compatibility, and updated 2,668/19,994 capacity cardinalities.

A14 correctly fixes the intended combined frontier at email migration 40, OTIZ migration 41 and additive address migration 42; checks canonical order 1..42 and v41→v42 replay; requires 118 recovery tables and 64 AUTO_INCREMENT sequences; and observes upstream `stops` together with address-import provenance. The planned 28 conflict-resolved frontier consumers are not in this snapshot and require supplemental exact-byte review after reconciliation, as declared by the root author. This review does not approve those private bytes or the final merged source.

## RED evidence and verification

The package records five preserved tests GREEN and five intended RED results. Schema fails at the absent canonical migration 42; restore fails at the absent `RuntimeRecoverySchemaV42`; parser/reports/identity fail on current 32-field cardinalities. These failures are causally appropriate. Assertions after the first failure, including the corrected historical recovery/CSV cases and downstream A14 composition/restore cases, honestly remain **UNKNOWN**, not GREEN.

`git diff --cached --check` passed on the restored snapshot. PHP syntax checks passed for the changed reports, schema and restore tests. No full local suite, live/provider request, paid call, real import, production edit, commit, push, merge, deployment, or review of the private 28-consumer reconciliation was performed.
