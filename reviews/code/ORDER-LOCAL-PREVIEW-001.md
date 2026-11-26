# ORDER-LOCAL-PREVIEW-001 — independent final code review

## Verdict

**APPROVED** for the complete corrected candidate source `fa740f9b6165d97cfe86b985b1d2a03e052a78a6aa85b5408a2b249e7d10bb22`.

This is a code/spec approval for the reviewed source. Exact-source Linux CI, PR admission, merge, backup/migration deployment, and post-deploy verification remain pending and are not declared GREEN here.

## Independence and binding

The reviewer authored none of the specifications, tests, implementation, or correction.

- Base commit: `f935c748577510a4a50d877db51ecca067ee6c48`
- Full audited baseline source: `b514b7e6cb61ae9740f617f28b60b73e1b362713c1bace4bd3b57c9364d9e981`
- Full baseline snapshot: `/Users/antropophag/.local/share/fmonitor-2/order-local-preview-20261125/full-candidate-snapshot/source.patch`
- Full baseline snapshot SHA-256: `c9f15784061706207fb69cb48214c8bf8064535116a0b5af5032f6b87f659631`
- Full baseline Gate 5 package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T230717Z-8df9f6ec0b/package.json`
- Corrected complete snapshot: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T232610Z-36f3213985/snapshot/source.patch`
- Corrected snapshot SHA-256: `44b8e30610eb7e28583d0374e986aca6d3cbc8ca04ca0533dd0f71ecef396e96`
- Prepared correction Gate 5 package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T232610Z-36f3213985/package.json`
- Delta from the audited baseline: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T232610Z-36f3213985/delta.patch`

The final review reused the complete prior source audit and inspected the bounded delta, its permanent tests, specifications, verification mapping, finding dispositions, formal Gate 3 records, and exact-source evidence. The complete scope remains the order composition/template/original/history/replay flow, scan replacement, PDF and compact UI, copy/help, diagnostic authorization, reclamation money semantics, schemas 42/43, readiness, backup/restore/forward migration, consumer closure, and ownership policy.

## Prior finding dispositions

### FG3-01 — fixed

The prior retrospective Gate 3 finding required durable coverage for the Documents-tab action and permission states. `tests/Yii2/order_review_corrections_001_test.php` now covers six real authenticated GET combinations: accepted/pending order state across owner, selector-only, and selector-denied actors. It asserts the exact selection route, mutual exclusion of “Новое распоряжение” and “Продолжить подготовку”, no accidental original controls for the selector-only actor, hidden actions after permission revocation, and unchanged business facts for every GET.

The test is registered in the verification input and suite inventory. The complete retrospective Gate 3 and the bounded correction Gate 3 are APPROVED in `reviews/tests/ORDER-LOCAL-PREVIEW-001.md`.

### FCR-1 — fixed

The prior final code review found that missing or corrupt reclamation evidence escaped to the action-wide `DomainException` handler and was misclassified as HTTP 403.

`app/YiiRuntime/Controllers/ObjectCardController.php` now catches the reclamation read/deadline calculation boundary locally. It rethrows only `FORBIDDEN`, preserving genuine authorization denial as 403, and wraps every other domain evidence/storage failure as `RuntimeException`, reaching the existing safe 503 path. The change mirrors the adjacent deadline-certificate classification and does not modify reclamation facts, money calculation, schema, replay, or authorization.

The permanent regression exercises healthy read, missing table, corrupt normalized evidence, deadline overflow, and genuine permission denial. It requires 503 for unavailable/invalid evidence, 403 only for denied access, no raw technical codes, and byte-for-byte equality of business facts and reclamation rows across each GET probe.

## Evidence considered

The full baseline package records 15/15 mapped commands GREEN against source `b514b7e6...`, including real schema frontier, reclamation HTTP/storage, PDF and workbook, native money inputs, object-card presentation, diagnostic authorization, runtime jobs recovery, backup/restore, and forward updates from historical schemas 22 and 23.

The correction package records these commands GREEN against final source `fa740f9b...`:

- `php tests/Yii2/order_review_corrections_001_test.php`
- `php tests/Yii2/equipment_reclamation_001_test.php`
- `php tests/Yii2/yii2_object_card_presentation_001_test.php`

The unchanged full baseline evidence remains applicable to unchanged schema, math, replay, PDF, recovery, and consumer code. The correction evidence closes both reported findings and checks the adjacent presentation and reclamation seams. No local full `make test` or `make verify` was run, in accordance with the owner decision.

## Final findings

No open code, spec, security, append-only history, replay, money, schema, recovery, authorization, domain-seam, deployment-readiness, or ownership-policy finding remains in the reviewed candidate.

Approval does not substitute for the planner-required exact-source Linux CI. The macOS `/dev/full` limitation remains an environment limitation and is not treated as GREEN.

## CI consumer-closure delta review — 26.11.2026

### Binding and independence

The reviewer authored none of this delta's production code, tests, fixtures, specifications, or evidence. This section is append-only and retains the complete approval above as the baseline review.

- Committed comparison point: `09db5402252f29328436674c48e467c52fb0edab`
- Frozen complete source: `9aaceeb4aae992716aacb0cc9afb422702f2dbc8c9099d07554130d1ad5260bf`
- Frozen executable source: `dd22ede9d18f2f033122e3e0625067b86b5e5ba1a9425ba465b70a2b05e26764`
- Snapshot: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261126T002204Z-4c4d60e41c/snapshot/source.patch`
- Snapshot SHA-256: `755b3f58ecc867543812f222236bcec241840e67fecbff72394037c1907f45a8`
- Root context package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261126T002204Z-4c4d60e41c/package.json`
- First exact-source CI run: `45721698081`; complete retained inventory under `/Users/antropophag/.local/share/fmonitor-2/order-local-preview-20261125/ci-45721698081/`

The first CI run completed with 37 `REGRESSION_FAILURE` entries across five test jobs and no overall GREEN. The complete failure inventory and per-shard diagnoses were inspected before this verdict. They identified stale current-schema inventories, stale renderer/date/metadata expectations, stale copy and DOM selectors, historical fixture inconsistencies, and test harness assumptions. They did not establish a new money, append-only history, replay, authorization, recovery, or domain-seam defect.

### Delta assessment

**APPROVED as a code/test delta, with CI admission still pending.** No open review finding was found in the frozen source.

The consumer corrections remain tied to the approved behavior rather than weakening it:

- schema/frontier fixtures now include the additive schema 42/43 columns and equipment-reclamation table while preserving exact table, constraint, migration, recovery, and prefix assertions;
- original/template fixtures use server-owned document dates, explicit start dates, external 1C numbers, scan-replacement metadata, distinct replacement bytes, and immutable correction dates while retaining replay, concurrency, access, no-write GET, exact-byte, and fail-closed assertions;
- PDF/HTML expectations assert the approved Plan/Fact date columns and centered cell geometry with exact bytes and hashes;
- OTIZ recovery expects safe Russian user copy and explicitly excludes the raw internal code while preserving HTTP 422, form values, request identity, revision, reopened dialog, and zero financial writes;
- object-identity tests scope the compact breadcrumb and context panels separately while preserving current/manual identity, escaping, missing-value, immutable PDF, authorization, and responsive checks;
- preopening and production browser journeys activate the Documents tab and use the current initial upload/scan replacement controls; they retain real visible interactions, retries, downloads, opening, permissions, accessibility, and responsive assertions;
- the sidebar audit keeps its immutable 29-entry baseline, pinned export digests, removal fingerprints, current SVG classification, and surviving `plus-alt-2` binding. Only the two explicitly documented retired order compositions stop requiring nonexistent per-file bindings;
- ancillary fixture changes replace stale literal expectations with exact current behavior and retain the original behavioral assertions.

The only production change is in `app/YiiRuntime/Assets/pilot.css`: the broad `.fm2-original-workflow h2` rule is narrowed to the two owned workflow heading positions. This restores SHLZ calendar heading geometry without changing order data, commands, permissions, or layout outside the original workflow. The final bounded browser logs record PASS for both the preopening journey and the production nginx/FPM journey.

### Evidence and limitation

Private focused logs and the final root browser checks support the correction, including final PASS logs for:

- `tests/Runtime/production_runtime_browser_001_test.php`
- `tests/Yii2/yii2_original_object_identity_001_test.php`
- `tests/Yii2/yii2_otiz_settlement_form_recovery_browser_001_test.php`
- `tests/Yii2/yii2_preopening_browser_001_test.php`
- `tests/Yii2/yii2_sidebar_state_icons_001_test.php`

The root package correctly contains no harness evidence and remains `NOT_REVIEWED`; reviewer preparation refused because all 37 mapped correction commands have not yet been recorded GREEN. The Linux-only safe-error consumer is also still pending. Therefore:

- this review does not declare the verification plan GREEN;
- the failed first CI run is not superseded by raw focused logs;
- PR admission, merge, and deployment remain blocked until one exact-source Linux CI run completes successfully and the full failure inventory is closed;
- any further code or test change requires review of that delta.

### Findings

No code/test-delta finding remains open. The verdict is conditional only on missing mandatory verification evidence, not on an identified source defect.
