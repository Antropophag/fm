# ADDRESS-REGISTER-IMPORT-001 — independent final code and architecture review v4

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, executable tests, fixtures, production implementation, or verification evidence
- Original base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Published correction base: `99232d9c263b0dc21fa1f2ab6a892e7e05f0f3a1`
- Gate 5 package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261120T005944Z-1e8f2c8a13/package.json`
- Candidate source: `7f1a9de93d203b9cbdb34851b7d681369f85b973a7de8a971b3f082cfe7314ff`
- Executable source: `62abb816f9e198c11b03834f6b007fe7e84e854fac08e878b41dd86b419a3758`
- Snapshot patch SHA-256: `5e797617186f1cd59ad417adb779473ce33e75b9be46f8654f10dc976f329def`
- Prior final reviews: v2 full importer and v3 Yii image correction — `APPROVED` for their exact sources
- Corrective Gate 3 reviews: v17 lookup/refresh and v19 unknown-budget — `APPROVED`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Prior final-review continuity

The v2/v3 dispositions remain valid on this source. F1 unknown COMMIT still returns the complete reviewed report as `unknown/COMMIT_OUTCOME_UNKNOWN` with CLI exit `75`, and fresh-process operation replay performs no second commit or duplicate mutation. F2 remains closed by bounded workbook/report/evidence processing, immutable payload generations, 256 MiB application memory, 16 MiB MariaDB packet, full 2,668/667 apply/export/backup/restore/replay, and the 19,994-row/639,808-field boundary. R1 remains closed: a late apply fault rolls back catalog/detail/audit/outcome publication and reports zero created, updated, filled, and replaced facts.

Input boundaries, shared-entrance identity, reference normalization, v40 prefix/frontier semantics, strict evidence JSON, the standalone Yii `ext-zip` image correction, authorization, paid-attempt ordering, and append-only history are unchanged except where strengthened below. No previously approved negative guard was removed or relaxed.

## Bounded identity lookup

Preview identity resolution no longer executes one full catalog query per source object. Each pre-paid and post-provider phase builds its own requested-only snapshot. Source requests and complete matching witnesses live in private temporary spools; memory contains domain-separated registration/identity digests, offsets, and at most two distinct object witnesses per input. Two witnesses remain ambiguity and block the package.

The store owns the SQL projection and keyset iterator. It reads the same effective manual registration/entrance/UNOM, immutable source registration/hash, address, house identity, and object ID as the prior query, ordered by legacy object ID in pages of at most 256. `AddressRegisterIdentityLookup` is a pure spool/index consumer with no SQL ownership. Exact PHP comparisons retain binary/case-sensitive registration and source-hash behavior, manual NULL tombstones, object-ID deduplication across match routes, and request ordinal identity.

Prevalidation and post-provider preparation create independent fresh views. A manual edit made during provider work is visible to preparation. No preview lookup enters apply: the existing transaction still performs current `find`, locks, revision checks, immutable source binding checks, and atomic mutation. A late native ambiguity is rejected with zero published counters and unchanged facts.

The public cost regression reaches all semantic controls and then measures additive Handler work across 20 and 40 requested rows after adding 2,674 unrelated catalog rows. The exact-source identity test is GREEN. More materially, the complete 2,668/667 plus 19,994 capacity/restore command now completes in `246.14748741697986` seconds, down from the prior exact full run's `1368.3641031250008` seconds, while preserving the same resource bounds and assertions. The correction therefore closes the observed integration timeout cause rather than only satisfying a synthetic counter.

## Causal cache versions and refresh

The still-unpublished v40 cache family adds `entry_version BIGINT UNSIGNED NOT NULL` with no default or AUTO_INCREMENT, a positive check, and unique `(request_hash, entry_version)`. The frontier remains 113 tables / 60 AUTO_INCREMENT families. Exact schema expectations, SQL enforcement, both migration seams, missing-sibling preflight, drift rejection, prefix25 behavior, backup/restore, and readiness are GREEN.

Versions are allocated monotonically per request hash under a short database-and-prefix-scoped advisory lock. Provider claim creation and atomic signed-evidence import share that allocator. The allocator lock is released before provider transport and on failure; the existing unique inflight key continues to serialize only the same cache request. An unrelated request hash can allocate and complete while another provider is deliberately held.

Completed cache selection uses capture time and causal version, never random UUID order. Same-clock tests pin the old row to the maximum valid v4 UUID, change the response, and require the new payload through explicit refresh, default preview, ready resume, competing-package resume, export/import, and target preview. Old rows remain immutable. Signed export preserves same-clock causal order; target import assigns local versions atomically in authenticated order. A late import trigger rolls back the whole set, releases the allocator, and succeeds exactly on retry without provider access.

Fresh explicit refresh requires paid permission and positive cumulative cap. One permitted refresh reserves one attempt and creates one immutable version. A competitor pays zero while the claim is held, then resumes from the completed result. Unknown refresh never falls back to older fresh evidence and requires explicit retry permission, reason, and sufficient cumulative budget.

## Durable unknown and exhausted budget

Provider-unknown, transient budget refusal, and valid retry now have separate state transitions. Provider-unknown persists the package report, consumed attempt, and owned pending cache row. If explicit retry has no remaining cap, the caller receives transient `blocked/REQUEST_BUDGET_EXCEEDED` with the retained paid count, while the durable package/report remains `unknown/PROVIDER_OUTCOME_UNKNOWN`.

The budget denial does not mutate or delete the pending row, allocate another version, call the provider, or reuse older completed evidence. A later budget-only resume without `retryUnknown` remains unknown and performs no call. A valid explicit retry completes the same entry ID/version/request hash, clears its lock fields, and consumes exactly the authorized additional attempt. Both uncached and older-fresh-cache paths compare the full pending row byte-for-byte and exact request-hash row count across denials. Economy and concurrency are exact-source GREEN.

## Architecture and standards

The implementation preserves one public application owner and the existing MariaDB store boundary. SQL remains inside store/persistence modules; the new lookup helper owns only bounded in-process indexing. Screens, CLI/Yii adapters, parser, provider, and cron do not own domain facts. Apply continues through the shared object-detail mutation seam and append-only audit history.

Authorization and local validation precede paid work. Attempts are reserved durably before transport, retry caps remain cumulative, unknown outcomes are never retried silently, and provider credentials are absent from reports/logs. The parser, signed evidence, private report files, chunk integrity, schema readiness, recovery, prefix compatibility, and nonroot runtime/image behavior remain as approved in v2/v3.

The initial final-v5 architecture check correctly rejected SQL in `AddressRegisterIdentityLookup`. The final source moves the unchanged keyset iterator into `MariaDbAddressRegisterImportStore`; no baseline exception or coverage weakening was added. `make architecture-check` is exact-source GREEN.

## Verification evidence

All ten mandatory importer commands are exact-source `GREEN` in the Gate 5 package:

- CLI, concurrency, economy, identity, parser, provider, integration, reports, schema, and real runtime backup/restore/capacity.

The capacity command completes the full 2,668-object/667-address flow, 85,376 rows, apply/export, real backup/restore, replay and cache reuse, followed by the 19,994-object boundary and row-20,001 rejection, under 256 MiB and 16 MiB packet limits. Its duration is `246.14748741697986` seconds.

Six adjacent commands are exact-source `GREEN`: `make architecture-check`, shared object-details MariaDB editing, production schema frontier, Yii object card, Yii object queue, and generated dependency rendering. `git diff --check` and PHP syntax checks pass. No canonical full local `make test` or `make verify` was run, consistent with the owner decision.

## Source scope and remaining gates

This verdict covers production code, executable specification/tests, schema/recovery changes, OpenSpec design, fixtures, and verification inputs in candidate source `7f1a9de93d203b9cbdb34851b7d681369f85b973a7de8a971b3f082cfe7314ff`, plus this review record.

After evidence, only factual completion metadata may be added to:

- `docs/operations/address-register-import-delivery-20261118.md`;
- `docs/operations/current-delivery-goal.md`;
- `openspec/changes/address-register-import/tasks.md`;
- this review file.

No code, executable test, fixture, specification, design, schema, verification input, generated input, or configuration may change without invalidating this verdict and requiring renewed exact-source review. Root's saved precommit digests must match all other reviewed files.

The prior GitHub run timed out on the published predecessor and is not GREEN for this source. Fresh exact-source full CI remains mandatory before publication or merge. Live DaData/FIAS behavior, paid requests, and dev/prod import remain `UNKNOWN` and unapproved. This review does not authorize merge, main publication, deployment, real import, paid provider calls, or production changes.
