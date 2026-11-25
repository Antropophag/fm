# ORDER-LOCAL-PREVIEW-001 — complete retrospective Gate 3

- Final test/spec verdict: **APPROVED**
- Scope: complete accumulated order-local-preview and equipment-reclamation candidate under `ORDER-LOCAL-PREVIEW-001` and `RECLAMATION-DELAY-001`
- Reviewer: `/root/schema_gate3_reviewer`, independent; authored none of the reviewed specifications, tests, fixtures, or production implementation
- Root author: specifications, primary acceptance tests, bounded owner-authorized corrections, fixture-lineage acceptance, and review-correction test
- Consumer author: `/root/schema_consumer_inventory` (sol/low), existing schema/recovery/browser/test-consumer closure and fixture updates
- Other production executors recorded by the delivery record: `/root/order_preview_executor`, `/root/reclamation_finance_seams`, and `/root/schema_integration_executor`, all separate from this reviewer

This is an honest retrospective Gate 3. The original preview was implemented and shown under the owner's explicit preview-first authorization with formal gates deferred. This record does not represent that accumulated implementation as having received preimplementation approval. The later schema integration correction did receive independent preimplementation Gate 3 and remains recorded separately in [`ORDER-RECLAMATION-SCHEMA-INTEGRATION-001.md`](ORDER-RECLAMATION-SCHEMA-INTEGRATION-001.md).

## Frozen full-candidate binding and baseline

- Full package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T230717Z-8df9f6ec0b/package.json`
- Snapshot base: `f935c748577510a4a50d877db51ecca067ee6c48`
- Snapshot patch SHA-256: `c9f15784061706207fb69cb48214c8bf8064535116a0b5af5032f6b87f659631`
- Candidate source: `b514b7e6cb61ae9740f617f28b60b73e1b362713c1bace4bd3b57c9364d9e981`
- Executable source: `db2fe604419be391b9324811847ea7cc61305c9e3b68e6c5a197e188c0d4e7b7`
- Result: 15/15 mapped acceptance commands `GREEN`, all bound to that exact source

The baseline covers the real reclamation ledger/HTTP seam, PDF and HTML rendering, workbook explanation, full order template/download/upload/history/replay flow, metadata boundaries, schema 42/43 compatibility, production schema frontier, pure reclamation deadline and monetary penalty math, native financial inputs and immutable snapshots, runtime jobs recovery, current recovery, forward-update recovery, object-card presentation, and administrative isolation of OTIZ diagnostics.

The source/test audit also inspected the historical external RED and QA records for template date, actual-start column, PDF normative removal and formatting, grouped/compact layout, original history and author, scan-only replacement, user-copy/admin isolation, reclamation owner/resolver/finance integration, lineage closure, and the macOS safe-error environment gap. No weakening of the established replay, stale-target, unopened-case, authorization, append-only, monetary, recovery, or immutable-snapshot assertions was found.

## Initial retrospective review — CHANGES_REQUESTED

The complete frozen candidate had one test/spec finding.

### FG3-01 — Medium — direct Documents-tab new-order flow lacked durable acceptance coverage

The normative contract requires:

- accepted original with no pending order and `canSelect` → primary `Новое распоряжение` to the established selection flow;
- pending order and `canSelect` → `Продолжить подготовку` instead of creating a second order;
- those actions to depend on selection authority without granting original read/correct/upload controls;
- actors without selection authority to see neither action.

Production already implemented these states, and a historical one-off render smoke existed, but no permanent acceptance test covered the complete state/permission matrix. The 15-command baseline was therefore real and green but insensitive to this named owner correction. Initial verdict: **CHANGES_REQUESTED**.

Private source audit record: `/Users/antropophag/.local/share/fmonitor-2/order-local-preview-20261125/full-gate3-findings.md`.

## Correction Gate 3 and dispositions

- Correction package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261125T231520Z-ca50ab2de8/package.json`
- Snapshot base: `f935c748577510a4a50d877db51ecca067ee6c48`
- Snapshot patch SHA-256: `360e42954511f0900a1b74a77dbffb8c4c3c7ca28994f8df2414544d6bf2c2c7`
- Candidate source: `bc93b28977f352ab870e24f1e8dcfac58dfac74905ad05816303212a0b2bb5bb`
- Executable source: `d1913613476660eedb7413568bb49f6cc4e7653ea80c6e8945fe975c598d481a`
- Reviewed test: `tests/Yii2/order_review_corrections_001_test.php`
- Test SHA-256: `053e21a079e897e031342ad01bc7e3cc7e11bd6a2e64aea40750e5a1fb7c4fe1`

### FG3-01 — fixed

The correction test covers six real authenticated object-card GET states: accepted and pending orders across owner, selector-only manager, and no-selector manager permissions. It requires the exact existing selection href, mutual exclusion of `Новое распоряжение` and `Продолжить подготовку`, absence of original controls for the selector-only actor, absence of selection actions after revocation, and complete before/after business-fact equality for every GET. The corrected fixture uses existing manager user 97 / process role 7 and toggles only the exact permissions under review.

The packaged run reports `PASS Documents accepted/pending and independent permissions`. Because the production CTA behavior was already correct and the finding concerned missing durable coverage, no fabricated behavioral RED was required.

### FCR-1 regression matrix — approved for implementation

The independent code review identified a separate production defect: unavailable or corrupt reclamation evidence could be translated to 403 instead of dependency failure 503. The same root-authored correction test exercises the real card seam with a healthy control and three independent axes:

- missing reclamation registry;
- corrupt stored normalized-number hash;
- deadline overflow from valid stored facts.

Each case restores its fixture, proves no business facts or reclamation rows change, and rejects raw technical codes in the response. The final access-denial case separately preserves genuine 403 semantics.

The exact evidence record `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795648294510098000-20e4c103f8644471b61e50681d7fccb5.json` has harness outcome `INTENDED_RED` and command verdict `REGRESSION_FAILURE`: missing and corrupt evidence return 403 instead of expected 503, while deadline overflow already returns 503. The CTA matrix passes in the same run. This is genuine behavior-specific RED with no setup failure.

Private correction review: `/Users/antropophag/.local/share/fmonitor-2/order-local-preview-20261125/correction-gate3-review.md`.

## Final verdict

The complete retrospective Gate 3 test/spec verdict is **APPROVED**:

- FG3-01, the sole test/spec finding, is closed by complete durable state/permission coverage;
- the full 15-command exact-source baseline remains valid and retained;
- the new FCR-1 regression matrix is independently approved and genuinely RED for the remaining production defect;
- schema preimplementation Gate 3 remains independently documented rather than being absorbed or recharacterized here.

This approval authorizes the separate executor to implement only the reviewed FCR-1 correction with the approved test kept byte-identical. It is not implementation GREEN and does not approve final release. The production fix, scoped correction GREEN, independent final code review, exact-source Linux CI, PR admission, merge, and deployment all remain pending.
