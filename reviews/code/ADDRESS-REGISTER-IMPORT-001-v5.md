# ADDRESS-REGISTER-IMPORT-001 — independent final code/architecture review v5

- Date: `2026-11-26`
- Reviewer: separately assigned `/root/fields21_review`; reviewer authored none of the reviewed specification, tests, fixtures, policy, production implementation, merge resolutions, or verification evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T232824Z-5f78379cd3/package.json`
- Candidate source: `433687cddd1ee0cdc11866f5c19b59f5120d7d312e9bc095f00031d70a9bda0e`
- Executable source: `e53e02f1584d9bfaa68c7a7b120b7e4f77b986b24804ae6c1f189ba65c5ac309`
- Snapshot: base `dcce5b9f64e5b340a1e1afbfb48103e945d38335`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T232824Z-5f78379cd3/snapshot/source.patch`, SHA-256 `5cb044ff6537251c229bcd726334536f9e58ab79f9907a3d8f6858c7c1909df8`
- Verification plan SHA-256: `a523a1e563ec04cc833a0b381ab56f1376e4e0741bcad96b1fd1257bc8b19cc5`
- Gate 3: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v22.md` — `APPROVED`
- Merge lineage: history-preserving merge of `origin/main` `f935c748577510a4a50d877db51ecca067ee6c48` into root checkpoint `dcce5b9f64e5b340a1e1afbfb48103e945d38335`; published PR baseline remains `dfb6927fb6f3624c007f895d45357f73358c1367`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Production review

`AddressRegisterWorkbook` now maps exactly B–H/P–AC in the specified order. It retains the full A–AG header and raw cells, so I–O/AD–AG remain evidence while their values no longer enter numeric/reference validation, canonical fields, mutations, counters or new reports. Existing bounds, formula rejection, required identity fields, date rules, Z duration and planned-field validation remain unchanged.

`AddressRegisterImportApplication` identifies the current payload by the exact 21-field key sequence before resume/apply. Applied historical packages remain readable and replayable; unapplied historical 32-field packages fail before store mutation with `failed/PREVIEW_STALE`, preserving CLI exit 75. Current blocked/unknown packages retain their existing retry and budget behavior. The compatibility check inspects durable source payloads rather than report presentation, and the regression proves package bytes, old JSON/CSV, facts, provider count and history remain unchanged through rejection, recovery, replay and export.

The schema composition is additive and ordered: main email recovery remains migration 40, OTIZ payment control remains 41, and address import becomes 42. Recovery schemas extend v39→v40→v41→v42 without replacing predecessor families, yielding 118 tables and 64 AUTO_INCREMENT sequences. Populated password/OTIZ rows, DDL and four allocator gaps survive migration, replay, public backup/restore and restored importer replay. Runtime readiness and canonical consumers point at the v42 frontier.

The effective-detail merge retains main's `stops` projection and the importer event-derived provenance. Registry/card/queue/readiness tests preserve both branches. No new state-writing seam, provider access, paid behavior, secret handling or authorization bypass was introduced.

## Supplemental exact-byte test and policy review

The previously private reconciliation is included in this snapshot and is approved. The 28 conflict-resolved frontier consumers plus three upstream current-frontier updates retain their prior assertions while advancing only the combined canonical version/inventory to 42/118/64. Historical profiles remain pinned where appropriate; current profiles use v42. `PreopeningFixture` deliberately migrates through v39, creates complete credentials, then advances through current v42, preserving main's email-first invariant rather than bypassing it.

The verification policy retains main ownership for `RuntimeRecoverySchemaV40`, adds main's v41 owner, assigns the importer only `RuntimeRecoverySchemaV42`, and preserves the shared `MariaDbObjectDetailsMutation` owner. Generated dependency and verification inventory checks are GREEN.

Post-Gate-3 test qualifications are suitable and do not weaken expectations: the remaining two-row/six-row report cardinalities are corrected to 21 fields; historical outcomes materialize `Traversable` payloads portably; comparisons normalize presentation containers while separately requiring exact persisted package-row bytes; and real CLI resume/apply of old pending packages explicitly require exit 75. The implementation's `failed/PREVIEW_STALE` response satisfies that unchanged public mapping.

## Verification evidence

All ten mapped importer commands are exact-source GREEN on candidate `433687cddd1ee0cdc11866f5c19b59f5120d7d312e9bc095f00031d70a9bda0e`, including parser, reports, identity, schema, concurrency/economy, full 2,668-object/667-address execution, 19,994 boundary, populated predecessor recovery and replay under 256 MiB PHP memory and 16 MiB MariaDB packet limits.

Eleven adjacent checks are GREEN on the immediately preceding candidate: architecture, current schema frontier, production canonical runner, email migration/schema, OTIZ hold behavior, effective editing, object card/queue, dependency rendering and verification inventory. The retained lineage record shows that only three importer test files changed afterward; production, policy, frontier tests and all adjacent command inputs remained byte-identical. The final ten-test exact-source run includes those three qualified files.

`git diff --cached --check` passed on the restored snapshot. PHP syntax checks passed for the changed importer application/workbook/schema, recovery v42 and effective-details production files. No full local suite, paid/live provider request, real import, source edit, commit, push, merge completion or deployment was performed by the reviewer.

Full exact-source GitHub CI is still pending and remains required before publication/merge. This approval is the independent final code/architecture and supplemental reconciliation decision for the snapshot above; it is not CI GREEN and does not authorize merge, deployment or live imports.
