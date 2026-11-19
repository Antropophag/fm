# ADDRESS-REGISTER-IMPORT-001 — independent final code and architecture review v2

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, executable tests, fixtures, production implementation, or verification evidence
- Reviewed base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Gate 5 package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T215010Z-fea646471c/package.json`
- Candidate source: `e8463fe0564630d8d0aa15206764903ebaeead0962f3a8078ce7d0639d3d0859`
- Executable source: `9927100193ffa1a895c0f30132445a742b575b34a68fee6f68d492613f1aebad`
- Snapshot patch: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T215010Z-fea646471c/snapshot/source.patch`, SHA-256 `9738f51f43db17c727a3c018282cb4edab11dcde21a08195a675ff74ba14b24e`
- Previous final review: `reviews/code/ADDRESS-REGISTER-IMPORT-001-v1.md` — `CHANGES_REQUESTED`
- Corrective Gate 3 reviews: v8 full capacity correction, v10 input boundaries, v12 shared-entrance identity, v13 reference normalization, v14 prefix/frontier — all `APPROVED`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Original final-review dispositions

### F1 — unknown COMMIT returned the wrong CLI outcome

**Resolved.** The unchanged public CLI drives the real factory, application, and MariaDB store through a test-only MySQL wire proxy. The proxy forwards the real `COMMIT`, receives the successful server ACK, and suppresses only delivery of that ACK to the client. The application records one commit attempt, tries durable operation reconciliation, and returns the complete reviewed preview report as `unknown/COMMIT_OUTCOME_UNKNOWN` when durability cannot be confirmed. CLI maps that result to exit `75`, not database-unavailable `69`.

The unknown result retains the exact summary, all 32 rows, normalized source object, provider evidence, provenance, indexes, values, and list order. A fresh process with the same operation ID reads the durable applied outcome as replay, performs no second commit, and creates no duplicate business facts. Authorization is rechecked before replay.

### F2 — supported package exceeded 256 MiB and then MariaDB packet limits

**Resolved.** Workbook scanning, payload spools, preview hashing, evidence handling, package persistence, report serialization, apply/replay, and restore use bounded streams or immutable chunk sequences. The v40 payload family stores arrays in append-only generations with bounded item/chunk size, compact manifests, count/chunk/digest metadata, SQL checksum/JSON/count constraints, and an atomic published pointer. Hydration validates manifest shape, package/generation/kind, exact ordinal continuity, per-chunk count/checksum/size, total count, absence of extra chunks, and the complete framed digest. Old generations are not deleted.

The exact runtime test completes under the shipped PHP `256M` limit and ordinary MariaDB `max_allowed_packet=16777216`. It prepares and applies 2,668 objects across 667 fake addresses, preserves 85,376 field rows, exports complete CSV and JSON, replays without duplicate facts, performs real backup/restore with table row hashes, and reuses restored cache without provider calls. It then prepares the exact 19,994-object worksheet boundary with 639,808 field rows and rejects row 20,001 before a provider attempt. The only 512 MiB child independently decodes an already emitted JSON artifact; application execution remains at 256 MiB.

### R1 — failed apply reported preview mutations as published facts

**Resolved.** Apply owns catalog creation, shared detail mutation, source binding, audit events, operation outcome, applied payload generation, and package publication in one transaction. A late audit fault rolls all of these back. The failure report sets created, updated, filled, and replaced to zero and persists the failed package outcome without presenting preview mutations as published facts. Existing catalog/history/preparation state remains unchanged.

## Corrective delta dispositions

- **Input boundaries:** nonlocal XLSX wrappers and non-regular files are rejected before open/read or provider work. The evidence reader treats literal NUL and any non-whitespace tail as invalid JSON. Trailing-NUL and invalid-signature CLI imports return exactly exit `2`, `rejected/EVIDENCE_INVALID`, empty stderr, and add no cache rows.
- **Identity:** registration remains the source identity while UNOM, entrance, and verified house must be consistent. Different registrations may share one entrance and evidence while retaining distinct IDs. Exact duplicates return `DUPLICATE_SOURCE_OBJECT`; contradictory repeated registration returns `IDENTITY_CONFLICT` before provider/attempt/facts. Apply, replay, reorder, overwrite, and ordinary repeat preserve IDs, catalog cardinality, history, provider-call count, and durable attempt count.
- **Reference normalization:** all parser-accepted case/space/separator forms for the existing four shaft and six material labels map to the established shared-editor codes. Stored display values are canonical; original R/S values remain immutable in provenance. Canonical-spelling repeat is unchanged with no events, provider calls, or attempts. Unknown words and numeric legacy codes remain `VALUE_INVALID` at the parser boundary.
- **Prefix/frontier:** v40 contains exactly seven address-import families and current recovery is 113 tables / 60 AUTO_INCREMENT families. Two independent 25-byte prefixes can coexist. Fresh v39→v40, compatible six-table missing-chunks recovery, strict readiness, and repeat no-DDL behavior pass. The chunks FK uses a deterministic bounded physical name while exact target and `RESTRICT/RESTRICT` semantics remain enforced; constraint names are not public contract.
- **Certificate frontier correction:** the final test-only delta adds exactly the seven v40 address-import suffixes to the independent maximal-prefix physical inventory. The earlier complete actual inventory showed seven additions and no removals. The corrected exact-source certificate check is GREEN; no production behavior changed in that delta.

## Standards and architecture review

The implementation keeps one public application owner for estimate, preview, resume, apply, report, and signed evidence. CLI and Yii route to that owner; screens and adapters do not own domain facts. Catalog writes use the shared object-detail mutation seam and append-only audit history. Existing effective-detail readers, queue, card, runtime readiness, recovery, and migration catalogue consume the same canonical state.

Authorization requires an active, activated operator with both read and edit capabilities in an allowed role. CLI additionally checks the configured database principal and exact target. Validation and authorization precede provider work. Paid attempts are reserved durably before transport, cumulative caps survive resume, retryable responses are bounded, uncertain transport is not retried silently, and cache single-flight behavior prevents duplicate calls.

The parser snapshots one local regular XLSX, enforces compressed/expanded/entry/row/column/cell bounds, rejects formulas in data rows and unsafe XML/relationships, streams worksheet content, preserves raw values, and performs local errors before spend. DaData composition fixes endpoint and policy, limits response bytes and time, verifies TLS, follows no redirects, classifies failures without leaking credentials, and accepts only the specified verified-house response.

Signed evidence authenticates the canonical complete envelope before application writes, validates request and response hashes, version/context, timestamp/TTL, and current address-quality rules, then imports atomically. Reports use private exact-0700 roots outside the checkout/webroot, reject symlink paths, publish exact-0600 files through exclusive partials and atomic rename, neutralize CSV formula prefixes, and omit secrets.

Migration v40 is additive and idempotent, preflights every existing family before creating a missing sibling, rejects column/index/FK/check/engine/collation drift, and never repairs incompatible DDL in readiness. Recovery and generated container dependencies include the new schema and required PHP extensions. No memory/packet-limit increase, report truncation, history deletion, mutable published generation, production fault hook, or autonomous live-provider fallback was introduced.

## Specification review

The final code implements the public seams, source profile, mapping, value normalization, address quality, economy and evidence rules, authorization, identity, fill/overwrite semantics, idempotency, atomicity, reporting, schema, capacity, and recovery requirements in `ADDRESS-REGISTER-IMPORT-001` and its OpenSpec delta. The approved Gate 3 corrections strengthen executable coverage without changing the owner-approved behavior or weakening the original negative guards.

No actual source data is committed. Private aggregate evidence motivated the shared-entrance correction but does not define the contract and contains no provider validation. The 19,994-row synthetic capacity fixture remains identity-valid independently of that evidence.

## Verification evidence

All ten mandatory importer commands are exact-source `GREEN` in the Gate 5 package:

- CLI, concurrency, economy, identity, parser, provider, integration, reports, schema, and real runtime backup/restore.

The restore/capacity command took `1368.3641031250008` seconds and covers the full 2,668/667 and 19,994 scenarios described above. The other nine tests include real commit-loss reconciliation, failed-apply rollback counters, strict evidence JSON, shared-entrance identity, reference normalization, exact schema drift, prefix recovery, auth, budgets, retries, and concurrency.

Six adjacent checks are exact-source `GREEN`: `make architecture-check`, shared object-details MariaDB editing, production schema frontier, Yii object card, Yii object queue, and generated dependency rendering. The prefix/frontier matrix has 15 GREEN records on the production-fixed source; its sole stale certificate expected-inventory failure was fully inventoried as exactly seven missing expected suffixes and no removed tables. The corrected certificate test is separately exact-source GREEN on the final candidate.

`git diff --check` passes. No canonical full local `make test` or `make verify` was run, in accordance with the owner decision. No live provider, FIAS, dev/prod import, deployment, or external write was performed.

## Remaining gates and source scope

This verdict covers the code, executable specifications/tests, schema/recovery frontier, generated dependency inputs, and configuration in candidate source `e8463fe0564630d8d0aa15206764903ebaeead0962f3a8078ce7d0639d3d0859`, plus this review record. The owner-authorized post-evidence edits to `docs/operations/address-register-import-delivery-20261118.md`, `docs/operations/current-delivery-goal.md`, and `openspec/changes/address-register-import/tasks.md` may record factual completion metadata only. Those metadata edits and this review file do not alter the reviewed behavior. Any other code, spec, executable-test, fixture, schema, generated-input, or configuration change invalidates this verdict and requires renewed exact-source review.

GitHub exact-source CI is still `UNKNOWN` and remains mandatory before publication. Live DaData/FIAS behavior and server-side adapters are also `UNKNOWN`; they are not approval or GREEN. This review does not authorize real paid requests, dev/prod import, merge, main publication, deployment, or production changes. Proceed to commit/PR and the planner-required exact-source CI; merge/deploy remain separately gated.
