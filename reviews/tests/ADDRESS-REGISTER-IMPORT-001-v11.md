# ADDRESS-REGISTER-IMPORT-001 — independent identity-delta Gate 3 review v11

- Date: `2026-11-19`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, tests, fixtures, production implementation, or evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T201617Z-b1db6a2978/package.json`
- Base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Candidate source: `537d2faafd7f52c8d637708771919643d2cb94f5d99d541bb6068cf49f7c0ee4`
- Executable source: `e4110638ae702301580454f3b344171dd337878672ad491a87f16aac5aa611fc`
- Snapshot patch: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T201617Z-b1db6a2978/snapshot/source.patch`, SHA-256 `0996fe79ea6bae2fe9b170534e00bd358879c9e40edede2822dd0e76c49a611d`
- Prior reviews: full corrective Gate 3 v8 `APPROVED`; supplementary input-boundary Gate 3 v10 `APPROVED`
- Verdict: **CHANGES_REQUESTED** for the identity test delta

## Findings

1. **BLOCKING — the promised cached repeat has no no-provider-call assertion.** `tests/InstallationProcess/address_register_import_identity_001_test.php` records the provider count before the reordered overwrite and proves that overwrite reuses the shared address cache. It then performs the non-overwrite repeat and checks `ready`, zero created/updated, two unchanged, and zero warnings, but never compares the provider count after that repeat. The identity-delta scenario and the established paid-call contract require repeat of the two shared-entrance objects to remain offline. An implementation that unnecessarily refreshes or calls the provider only on this repeat would pass the submitted test. **Correction:** retain the count after the overwrite, run the repeat, and assert the provider count is still identical. Keep the existing unchanged summary assertion.

No other blocking or non-blocking findings.

## Identity model assessment

The clarified identity rule is consistent with the original OpenSpec. Primary matching is registration-based and requires consistent UNOM, entrance, and verified house; the contract never made `UNOM+entrance` a unique lift identity. Distinct registrations sharing one entrance and verified house are separate lifts. Removing the uncontracted source-location uniqueness restriction therefore restores supported input rather than weakening the documented conflict guards.

The private offline aggregate is relevant bounded evidence: the actual source contains 2,668 selected objects, no duplicate registrations, no duplicate full identities, and hundreds of shared `UNOM+entrance` groups comprising multiple objects with consistent raw addresses. It performs no provider, database, or live request. No private source rows or identifiers are reproduced in this review.

The positive synthetic case otherwise has suitable reachability. It uses two distinct registrations with the same UNOM, entrance, address, and verified house; expects one fake provider call, a ready preview with all 64 field rows, two applied objects with distinct database IDs, exact catalog growth, same-operation replay without writes, stable identities after row reorder, independent overwritten weights, and a subsequent unchanged result.

The negative guards are also suitable. An exact duplicate requires stable `DUPLICATE_SOURCE_OBJECT` before provider access and without facts. The same registration with a contradictory entrance requires `IDENTITY_CONFLICT` before provider access and without facts, including overwrite. Existing ambiguous database match, verified-house/entrance conflict, allocator collision, and manual nonempty current-identity tests remain intact.

The 19,994-row capacity fixture keeps unique UNOM values and is unaffected. It remains a capacity boundary rather than the evidence for changing identity semantics.

## Evidence and lifecycle

The prepared package maps `php tests/InstallationProcess/address_register_import_identity_001_test.php` and records exact-source `INTENDED_RED`: the unchanged identity prevalidation rejects the new valid shared-entrance positive case. This is truthful RED before the identity correction, not implementation GREEN. PHP syntax and `git diff --check` pass; the package base and patch digest match its manifest.

Full corrective Gate 3 v8, supplementary v10, and the original full final-review/CI matrix remain in force. The eventual identity correction must make this test GREEN and rerun the planner-selected complete exact-source verification; the current RED package does not approve production code.

No full local suite, live/provider request, actual import, deployment, commit, code edit, or test edit was performed.
