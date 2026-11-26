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

---

## CI test-consumer delta review against `09db5402`

- Source/test verdict: **APPROVED**
- Admission status: **NOT GREEN — exact-source CI evidence pending**
- Snapshot base: `09db5402252f29328436674c48e467c52fb0edab`
- Snapshot patch SHA-256: `755b3f58ecc867543812f222236bcec241840e67fecbff72394037c1907f45a8`
- Prepared root-context package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261126T002204Z-4c4d60e41c/package.json`
- Candidate source: `9aaceeb4aae992716aacb0cc9afb422702f2dbc8c9099d07554130d1ad5260bf`
- Executable source: `dd22ede9d18f2f033122e3e0625067b86b5e5ba1a9425ba465b70a2b05e26764`
- Test authors: root for the two unit corrections, governance correction, and five end-to-end/browser corrections; separate integration consumer agents for the remaining fixture/oracle updates
- Reviewer: `/root/schema_gate3_reviewer`, independent; authored none of this delta

The prepared reviewer route correctly refused to produce review admission because the package contains no exact-source harness records for the 37 mapped acceptance consumers. This review therefore assesses the frozen source/test delta only. It does not convert raw local output into harness GREEN and does not treat the prior CI failure run as approval.

### Findings

No findings.

### Test-delta assessment

The complete 37-consumer inventory and diagnoses under `/Users/antropophag/.local/share/fmonitor-2/order-local-preview-20261125/ci-45721698081` were inspected against the frozen diff. The changes are bounded consumer closure for already-reviewed preview behavior:

- canonical schema expectations advance to exact frontier 43 and add the reclamation table without replacing exact catalogues with counts or permissive membership checks;
- template fixtures now supply the required chosen start date and assert the expanded exact persisted-artifact metadata;
- scan-replacement fixtures remove retired client fields, preserve immutable signed date/number, use distinct replacement bytes, and retain replay/history/access assertions;
- ordinary user-copy expectations remove technical IDs, reason codes, and sync diagnostics while retaining immutable facts in lower-level projections, exports, or admin seams;
- object-card state tests retain permission denial, exact href, read-only, pending/applied separation, and no-reopen assertions while adopting the approved compact wording;
- renderer HTML remains an exact literal oracle with independently updated byte length and SHA-256, escaping, projection, and active-content checks intact;
- asset cutover tests retain exact source-byte hashes and HTTP security/cache checks rather than weakening to presence checks;
- the shipped-consumer governance test adds `order_reclamation_schema_001_test.php` in exact canonical order and preserves the exact-list, missing, ambiguous, and unregistered fail-closed cases;
- visual ownership replaces a brittle immediate-sibling regex with ordered structural assertions proving the picker is inside the form, the form ends before the public modal, and the dialog retains its explicit installer binding. Existing responsive ownership, global-shell, and no-ellipsis checks remain;
- browser flows now use the current external-number/start-date and scan-replacement contract, compare the downloaded current original to the distinct replacement bytes, and preserve navigation and immutable-fact assertions.

No removed assertion was found to conceal a production defect or materially weaken its original boundary. The root-owned final browser v4 logs and the independently assigned integration logs report the corrected consumers passing, but these raw bounded checks are diagnostic support only.

### Safe-error retained invariant

`tests/Runtime/yii2_safe_error_correlation_001_test.php` is strengthened rather than loosened. The stale expectation of one signed intent is replaced by the exact two append-only identities: initial and correction request IDs, modes, identical signed start, and identical external number. The test still requires two revisions, zero applications, null actual start, safe 503 envelope, privacy, correlation behavior, and genuine 403/503 distinctions elsewhere in the suite. The macOS `/dev/full` limitation remains a local environment gap; the Linux safe-error consumer is mandatory and has not been declared GREEN by this review.

### Production CSS delta

The sole production change narrows `.fm2-original-workflow h2` to the two intended workflow headings. This prevents unrelated calendar headings from inheriting compact workflow typography. It changes no business state, permissions, or layout ownership, and the existing browser geometry assertion remains bounded at `<=20` rather than being relaxed.

### Evidence route and verdict

The repository-authored `verification-ci-input` mapping of all 37 failed consumers should remain unchanged. A scoped CSS/browser/root-acceptance package may supplement the frozen source review, but it must not replace, reduce, or remap the 37 mandatory consumers. The correct route is:

1. retain this **APPROVED** independent source/test review and the earlier full-review history;
2. run the existing exact-source Linux CI plan for the frozen candidate;
3. require all 37 mapped consumers, including `yii2_safe_error_correlation_001_test.php`, to produce exact-source GREEN evidence before admission;
4. treat any missing exact-source result as `UNKNOWN`, not GREEN.

Final delta verdict: **APPROVED for source/test adequacy; NOT GREEN for CI or admission**. This review is independent of, and does not substitute for, the mandatory exact-source CI result and final source approval.

---

## Historical witness pin correction — Gate 3

- Verdict: **APPROVED**
- Findings: none
- Comparison point / snapshot base: `a6c6049e570147a91ef1d0e3c3017ff19573a059`
- Snapshot patch SHA-256: `b19ca07489285c8d74dadc9b3425f0325b195128b8497f5082d3b83e7ccab6f7`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261126T004807Z-1c056380a3/package.json`
- Candidate source: `a1910c3fd38a902dabccbad3a10e9a27aa73d7f585fd65aeef32f68ff0d335e6`
- Executable source: `4f1f9ba792b25ef361d8238a52148c67df3392f4cd46386cd96ed4947b062e8e`
- Test author: root, declared bounded test-consumer correction
- Reviewer: `/root/schema_gate3_reviewer`, independent

The executable test delta changes only the fixed historical commit used by `tests/Verification/verification_delivery_deduplication_001_test.py` from `5dc2c8852d465885c33160faee1fd2ec0e4ee50f` to `a6c6049e570147a91ef1d0e3c3017ff19573a059`.

The new witness is valid and non-circular:

- `git show a6c6049e…:tests/Yii2/yii2_preopening_browser_001_test.php` has SHA-256 `e6751ceb4d7df714d7c8978ca9513889701a9fb15c598d02e02ce98061d35197`, exactly matching the current canonical file;
- the retired `5dc2c885…` witness has different SHA-256 `946cc1cb82f3f106fe9cbce6dac6ebb0c4af550bed790ec33dd92bff29cf139d`, explaining the second-CI governance failure;
- the oracle reads a named full historical commit through `git show`, never dynamic `HEAD`, merge-base, or current-worktree content as its expected value;
- full-byte equality remains mandatory;
- the suite inventory must still contain the canonical test exactly once;
- active verification mappings must still reference the canonical test;
- the retired wrapper must remain absent from inventory, mappings, and filesystem;
- all CI reuse/admission, mismatch, failure/cancellation, transport-UNKNOWN, correction-disposition, and narrow-cosmetic-classifier negative tests remain unchanged.

The focused harness record `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795654085997140000-36056d71c594426c85481bd432d86c8b.json` is exact-source `GREEN` and runs all ten governance cases. The second exact-source CI run `45728527305` is retained as evidence that every application unit, E2E, and integration consumer—including Linux safe-error—passed; its only direct regression was this stale historical witness, with overall verify failure propagated from it.

The CI verification input adds this governance test as the 38th mandatory consumer while preserving the prior 37. Gate 3 approves the test correction and mapping. It does not declare the candidate admitted: a third exact-source full CI run must execute the complete matrix, including all 38 mapped consumers, and must succeed without bypass or remapping.
