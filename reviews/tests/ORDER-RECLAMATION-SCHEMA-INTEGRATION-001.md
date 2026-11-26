# ORDER-RECLAMATION-SCHEMA-INTEGRATION-001 — Gate 3 test review

- Verdict: **CHANGES_REQUESTED**
- Scope: only the new schema integration correction in `tests/Runtime/order_reclamation_schema_001_test.php` and `openspec/changes/simplify-order-local-preview/verification-schema-input.json`
- Snapshot base: `f935c748577510a4a50d877db51ecca067ee6c48`
- Snapshot patch digest: `84e7989e7b2c7cd2360711e7f69908f4e9e973f5788b8e2f14b18231ea13061f`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T223735Z-7e9ce5dcdb/package.json`
- Package candidate source: `5df94d2785743f930b03c25a2210da2fbc8f24b3524698acadd936bfaea44184`
- Package executable source: `5b26b2c4ad47df5f7e2b6b988b5fe1b74e0adc3547a82297a38f2f178f1c1256`
- Immutable review checkout HEAD: `f935c748577510a4a50d877db51ecca067ee6c48`
- Test author: root
- Reviewer: `/root/schema_gate3_reviewer` (independent; authored neither the reviewed test nor implementation)

This review covers the bounded retrospective Gate 3 correction only. It does not approve the broader order-local-preview implementation. That accumulated preview remains subject to its planned full retrospective Gate 3 and independent final review.

## Findings

### G3-SCHEMA-01 — High — schema 43 deep-readiness rejection is not exercised

Location: `tests/Runtime/order_reclamation_schema_001_test.php`, case `deep readiness requires external number`.

The normative addenda require deep readiness to detect incompatibility in both schema 42 and schema 43. The test creates a valid current schema and removes the schema-42 `external_document_number` column, proving only the schema-42 half. It never corrupts or removes the reclamation table and calls `MariaDbRuntimeReadiness::assertDeepReady`, so a correction that wires schema 42 into readiness but omits schema 43 can satisfy this readiness assertion. Add an independent schema-43 readiness case that starts from canonical schema 43, makes the reclamation schema incompatible (or absent), and expects the public deep-readiness seam to return `SCHEMA_NOT_READY` without writes.

### G3-SCHEMA-02 — High — append-only FK rejection covers only the case relationship

Location: `tests/Runtime/order_reclamation_schema_001_test.php`, case `reclamation rejects cascading history deletion`.

`RECLAMATION-DELAY-001` requires both the installation-case and actor relationships to use `ON UPDATE RESTRICT` and `ON DELETE RESTRICT`. The test replaces only the case FK with `CASCADE`. It therefore permits an implementation to validate that one FK while accepting cascading actor mutation/deletion. Cover the actor FK independently as well, and cover the required update and delete rules so either relationship becoming cascading is rejected through `isCompleteCompatible` and `apply` with no repair or other writes.

## Evidence assessment

The prepared record `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795646213575070000-f145c73b021f4597862bf8479e6da022.json` is bound to candidate source `5df94d2785743f930b03c25a2210da2fbc8f24b3524698acadd936bfaea44184`, executable source `5b26b2c4ad47df5f7e2b6b988b5fe1b74e0adc3547a82297a38f2f178f1c1256`, and the exact test command. The harness classifies the evidence outcome as `INTENDED_RED`; the underlying command exits 1 with command verdict `REGRESSION_FAILURE` before the schema consumer correction. The assertions fail through missing recovery 42/43 profiles and the known FK-rule compatibility gap rather than through a wrapper-only sentinel, so the RED is genuine for the behavior it covers.

The remaining cases correctly exercise additive schema 42 preservation, canonical migration to frontier 43, replay with unchanged state, wrong-width conflict with no writes, and exact recovery profile table/AUTO_INCREMENT inventories. The verification input maps the correction to the canonical migration, deep-readiness, and backup/restore seam. Runtime recovery end-to-end consumers are mapped in the full delivery task and must be checked separately before final release; this Gate 3 review neither waives nor claims that downstream obligation.

## Verdict

**CHANGES_REQUESTED** until G3-SCHEMA-01 and G3-SCHEMA-02 are covered in the bounded schema integration test and new genuine RED evidence is prepared for the corrected test source.

---

## Rereview — corrected test delta

- Verdict: **APPROVED**
- Corrected snapshot base: `f935c748577510a4a50d877db51ecca067ee6c48`
- Corrected snapshot patch digest: `506e31584641f090ff77c03b1fbec413d79701881e20f801ab2454fa84159f0a`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T224103Z-0dbab7932c/package.json`
- Package candidate source: `b119dd72a43295d37d8f3058e933bcccdbc6425124f785040f656ad57926ea9d`
- Package executable source: `6f923d636d2a7ac9fd927a4c738dfead1d31699053f36a2a005733220e17c1c2`
- Corrected test SHA-256: `bb00a07e000faf551a0ac4e4bb623acb6de6b2859bbaca758d13f667042ce752`
- Immutable review checkout HEAD: `f935c748577510a4a50d877db51ecca067ee6c48`
- Test author: root
- Reviewer: `/root/schema_gate3_reviewer` (independent; authored neither the reviewed test nor implementation)

### Prior finding dispositions

- **G3-SCHEMA-01 — fixed.** After an explicit current-schema success baseline, the test removes schema 42 and expects `SCHEMA_NOT_READY`, restores schema 42 through `AssignmentOrderOriginalExternalNumberSchemaMigration::apply`, and proves readiness succeeds again. It then independently removes schema 43, expects `SCHEMA_NOT_READY`, restores schema 43 through `EquipmentReclamationSchemaMigration::apply`, proves readiness succeeds again, and finally removes the reclamation number uniqueness guarantee and expects `SCHEMA_NOT_READY`. This covers absent and materially malformed schema 43 without conflating those probes with the schema-42 failure.
- **G3-SCHEMA-02 — fixed.** Four isolated fixtures now cover installation-case and actor relationships crossed with `ON UPDATE CASCADE` and `ON DELETE CASCADE`. Each fixture first proves the canonical schema and replay are compatible and write-free, changes only one referential action while leaving the other `RESTRICT`, expects `isCompleteCompatible` to reject it, and proves `apply` returns `SCHEMA_MIGRATION_CONFLICT` without changing the resulting incompatible state.

### Corrected RED and delta assessment

The corrected test is byte-identical in the immutable checkout and the current main worktree. The prepared record `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795646419708848000-a69ca003709f4297bd076b194f0a006a.json` binds the exact test command to candidate source `b119dd72a43295d37d8f3058e933bcccdbc6425124f785040f656ad57926ea9d` and executable source `6f923d636d2a7ac9fd927a4c738dfead1d31699053f36a2a005733220e17c1c2`. The harness classifies the evidence outcome as `INTENDED_RED`; the underlying command exits 1 with command verdict `REGRESSION_FAILURE`. The output reaches the established schema cases, passes the wrong-width fail-closed case, and reports the expected missing compatibility/recovery/FK behavior; it contains no setup exception. Although the old implementation stops the sequential readiness case at its first missing correction, the explicit success assertions before and after each restoration make the corrected test structure deterministic once the prerequisite correction is supplied.

No new blocker is present in the delta. The bounded schema integration Gate 3 test matrix is **APPROVED** for implementation. This approval remains limited to the schema correction. It does not approve the full preview or waive the separately mapped runtime recovery end-to-end consumers, exact-source CI, full retrospective Gate 3, or independent final review required before release.
