# ADDRESS-REGISTER-IMPORT-001 — independent home44 Gate 3 review v23

- Date: `2026-11-26`
- Reviewer: separately assigned `/root/fields21_review`; reviewer authored none of the reviewed contract, tests, fixtures, production implementation, operational route, or RED evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261126T071025Z-32cb30107e/package.json`
- Candidate source: `d2455ecaff30197f7a38e20d63784eb63f57e5677d340442bbc17fa2f3dd26d8`
- Executable source: `f806086bcadc14432ae8ae28891b9ff11ae8b2d3336ea0914bdfd0680a29ec44`
- Snapshot: base `31761d3429b8c7f573c0e328a95a5f3fa9eebe20`, patch `/Users/antropophag/.local/share/fmonitor-2/address-register-import/dev-deploy-20261126/gate3-source/source.patch`, SHA-256 `726cb327b9f86c8c8c0228d732c3c7847c6ae5712dce149f876f42a5471662d6`
- Verification plan SHA-256: `a2f97183e2c64ddc27ef71087ee4586d4475ad20bbbbe29aca8e0f61f77d2f26`
- Verdict: **APPROVED**

## Gate 3 findings

No blocking or non-blocking findings.

## Operational finding outside Gate 3

1. **DEPLOYMENT-BLOCKING DOCUMENTATION — the private command blueprint incorrectly requires a merged GitHub commit.** `scoped44-command-blueprint.md` says both “merged, reviewed” and `TARGET='<merged-reviewed-github-commit>'`. The current owner authorization permits home deployment after reviews and exact-source CI but explicitly does not authorize PR merge or main push. Following that prose would either stop an otherwise authorized deployment or pressure an unauthorized merge.

   **Correction before deployment:** bind `TARGET` to the exact reviewed, committed PR307 source whose exact-source CI is GREEN, without requiring or implying PR merge. Retain the clean-tree, revision-label, build-ID and image-ID checks. This private operational-document correction does not change the reviewed A15 contract/tests or Gate 3 verdict, but home deployment must not proceed from the current blueprint text.

## Contract and test assessment

A15 correctly supersedes the earlier candidate frontier without rewriting its history. Current main owners remain `AssignmentOrderOriginalExternalNumberSchemaMigration` at 42 and `EquipmentReclamationSchemaMigration` at 43; address import becomes the additive owner at 44. The combined recovery contract is exactly 119 tables and 65 AUTO_INCREMENT families. Existing 21-field mapping, identity, report, budget and capacity behavior remains unchanged.

The schema regression independently requires the complete canonical sequence 1..44 and the exact owners at 40–44. It constructs the full v43 predecessor, populates password/OTIZ plus the main42 original-start row and main43 reclamation row, advances every relevant allocator, then compares exact predecessor rows, DDL and allocator positions after direct owner44 application and replay. The external document number, nonzero reclamation delay and all previously reviewed sentinels are observable rather than inferred from table membership.

The synthetic home compatibility case is suitable. It creates an assignment-order identity row, advances its next ID to 314, deletes v14 receipts to reproduce the historical gap, and snapshots every old table before invoking only `AddressRegisterImportSchemaMigration::apply`. Exact predecessor-table equality after apply/replay proves address44 neither fabricates receipts nor mutates the registry row or allocator. It does not claim the generic full catalogue is safe on that state.

The restore regression points at `RuntimeRecoverySchemaV44`, requires 119/65, includes the populated main42/43 families in the recovery inventory, and preserves the complete binary-safe table snapshot through public backup/restore and importer replay. Existing 2,668/667 and 19,994 capacity/restore coverage remains present downstream.

`AddressRegisterPredecessorFixture` remains independent of production manifests. The retained private main43 probe runs the expanded fixture against exact upstream main43, completes full catalogue replay, and reports `PASS main43 populated predecessor qualification`; this establishes fixture reachability without claiming address44 GREEN.

## Home deployment boundary

The read-only home preflight shows a clean detached main43 release with healthy services and a historical v14 receipt gap: 99 registry rows, next ID 314, zero receipts. A15 correctly requires a fresh private backup, preservation of the existing env/secrets/state and running service set, app-only cutover, no provider/SMTP/import traffic, and post-cutover health/readiness and schema witnesses.

The reviewed contract permits only scoped owner44 execution under the canonical database lock. The inspected private `home-scoped-44.php` route (SHA-256 `fc214f9da1a9bdb07399843d0d13d821d4b21925c964e14316bb92ff280a02eb`) maps its sole locked owner to catalogue version 44, checks the exact absent/completed seven-table frontier for apply/replay, proves registry count/hash/next-ID and receipt zero before and after, and explicitly reports migration14 as not invoked and not claimed GREEN. Its lock/preflight/state-preservation design has no finding. That operational script is private evidence rather than part of this source snapshot; its final exact release bytes and execution remain subject to final review, exact-source CI, fresh backup and deployment verification. The separate blueprint wording finding above must be resolved first.

## RED evidence and lifecycle

Both mapped commands are exact-source `INTENDED_RED`. The schema test fails first because canonical owner 44 is absent; the restore test fails first because `RuntimeRecoverySchemaV44` is absent. These failures are caused by the missing reviewed production integration, not broken setup. Assertions after those first failures remain **UNKNOWN**, not GREEN, until the separate production executor implements the slice.

The verification input maps only the two changed public seams, selects `CRITICAL`, requires Gate 3 and final review, and retains full exact-source CI. The planned production paths cover catalogue/schema ownership, recovery/readiness, policy and the current contract. Root-owned frontier consumer reconciliation must be reviewed on the final merged bytes.

`git diff --cached --check` passed on the restored snapshot. PHP syntax checks passed for the changed schema test, restore test and predecessor fixture. No production/test edit, full local suite, paid/live request, actual import, commit, push, PR merge, main push, corporate deployment or home mutation was performed by the reviewer.

This approval authorizes the separately assigned production main43→address44 integration. It does not itself approve the final implementation, exact CI, or home deployment execution.
