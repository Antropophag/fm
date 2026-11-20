# ADDRESS-REGISTER-IMPORT-001 — independent identity-delta Gate 3 review v12

- Date: `2026-11-19`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, tests, fixtures, production implementation, or evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T202059Z-a2b6c5dcb2/package.json`
- Base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Candidate source: `75801ccbca79ab17d2a59e988cae284293824fcf504a3e9a05c696adfd62df0e`
- Executable source: `3bd3e542661673a587235498178c4cd6e1d9f67efce64bb23e256413d0aaef99`
- Snapshot patch: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T202059Z-a2b6c5dcb2/snapshot/source.patch`, SHA-256 `7ca10b58d1506dbb76dfc90bcdb928775b0fe4e474fb1756c55443593dc20900`
- Previous identity review: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v11.md` — `CHANGES_REQUESTED`
- Verdict: **APPROVED** for the identity test/spec delta

## Findings

No blocking or non-blocking findings.

## Resolution of v11 finding

**Resolved — the complete shared-entrance flow now proves cached execution.** The test fixes the expected provider count at baseline plus one after the first shared-address preview and checks that exact value after initial preview, apply, operation replay, reordered overwrite preview, overwrite apply, ordinary repeat preview, and ordinary repeat apply. It also records the durable attempt count before the scenario, requires exactly one new attempt for the one shared address, and requires the count to remain unchanged through every repeat and both negative cases. An unnecessary paid/provider call at any phase can no longer pass.

## Complete identity-delta assessment

The identity clarification matches the original OpenSpec: registration is the primary source identity and UNOM, entrance, and verified house must remain consistent, but `UNOM+entrance` is not unique across lifts. Distinct registrations sharing one verified location therefore remain two objects. This removes an undocumented source-location uniqueness restriction without weakening registration conflicts, database ambiguity, or current-identity protection.

The positive test reaches the complete public flow. Two registrations share one UNOM, entrance, address, and verified house; preview must be ready with one fake provider request, one durable attempt, and all 64 field rows. Apply must create exactly two catalog objects with distinct internal IDs. Same-operation replay must add no facts. Reordered overwrite must preserve both IDs and apply independent `630`/`1000` values. Ordinary repeat preview must report two unchanged objects, and repeat apply must report zero created, updated, filled, and replaced with two unchanged.

The repeat-apply oracle additionally requires stable catalog cardinality, stable IDs, unchanged detail-history count for both objects, no provider call, and no additional durable attempt. Together these assertions distinguish stable two-object identity from accidental merge, duplicate creation, repeated mutation, or hidden paid work.

The negative guards remain exact and execute before an uncached address could be sent to the provider. An exact duplicate requires `rejected/DUPLICATE_SOURCE_OBJECT`; the same registration with a contradictory entrance requires `rejected/IDENTITY_CONFLICT`, including overwrite. Both require unchanged provider count, unchanged business facts, and no durable attempt reservation. Existing verified-house/entrance conflicts, ambiguous database registration, allocator collision, manual nonempty current-identity conflicts, authorization ordering, reorder, and replay controls remain intact.

The private offline aggregate supports the need for this behavior without defining it: the actual source has no duplicate registrations or full identities and contains hundreds of multi-object `UNOM+entrance` groups with consistent addresses. No provider, database, or live request was used, and no private row values are reproduced here. The unique-UNOM 19,994-row capacity fixture remains unchanged and is not used to justify the identity rule.

## Evidence and lifecycle

The prepared package maps `php tests/InstallationProcess/address_register_import_identity_001_test.php` and records exact-source `INTENDED_RED`. Unchanged production still rejects the valid shared-entrance preview before the later assertions, which honestly await the separately authored application correction. Previously established controls were GREEN on the prior implementation baseline; they are not relabeled GREEN for this source.

PHP syntax and `git diff --check` pass. The package base and snapshot digest match its manifest. I inspected the complete v11-to-v12 delta, provider and attempt invariants, apply/replay/reorder/repeat mutations, negative ordering, specification alignment, and evidence disposition.

Full corrective Gate 3 v8, supplementary input-boundary Gate 3 v10, and the original full final-review/CI matrix remain in force. This approval authorizes the separately assigned application executor to implement the reviewed identity correction; it does not approve production implementation or replace exact-source final verification.

No full local suite, live/provider request, actual import, deployment, commit, code edit, or test edit was performed.
