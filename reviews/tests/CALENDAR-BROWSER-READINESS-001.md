# CALENDAR-BROWSER-READINESS-001 — retrospective independent test-delta review

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`
- Base: `7277f54c7779bd93f63d93344b09ea8340dc111b`
- Exact candidate source: `1f46365cc1d805647b96489aa45b88f00e51956374cb770a50b2a94198c3406c`
- Executable source: `60284ce070b50956713242bdecae07110f80b17edf164ee986ae9ea612633016`
- Reviewer package: `20261116T153241Z-26e7227245`, SHA-256 `1d991bce61b3cdedcfe16fb2c32aebef1cf608bd44bdf53fc2633d229160db79`
- Verification plan SHA-256: `4b2be8b05107f08cb252d185fc93ac06a023ff51d7701357dfe9342df43e758d`
- Review character: retrospective test-only repair; not preimplementation Gate 3
- Verdict: **APPROVED**

## Historical trigger and diagnosis

Full CI run `43892093550` completed with one product-test failure: the production performance browser test read a calendar locator after Back navigation had restored the URL but before the asynchronous fragment replacement had restored the selected date in the current DOM. All other product categories were GREEN; Verify and Quality Graph failures were derived from that single e2e failure.

The private witness captures the precise race. Before Back, the selected element was connected. After the URL changed and the fragment update ran, the previously captured node was disconnected and its parent was BODY, proving that URL readiness and an old locator were not sufficient evidence of the new calendar state. The unchanged old fixture also passed repeatedly outside the unlucky timing window and had prior CI GREEN, consistent with a readiness race rather than a product regression.

The test-only correction records the initial selected date before mutation. After `page.goBack()` and the existing URL assertion, it waits until the **current document** has exactly one selected date whose `data-calendar-date` equals the initial value. The original bounded-grid assertion then runs, followed by the unchanged keyboard flow.

No timeout, sleep, retry, relaxed equality, runtime hook, or product code was added. Existing zero-navigation, selected-date reconciliation, keyboard activation, late-response ordering, fragment-failure fallback, bounded user directory, OTIZ shared dialogs, no-JS fallbacks, page-error, and viewport assertions remain.

## Evidence

The formal package contains the bound A09 browser acceptance:

- `tools/delivery/run-in-profile browser --with-services php tests/Yii2/production_performance_browser_001_test.php`
- command ID `acceptance:production_performance_browser_001_test`
- acceptance `A09`, purpose `acceptance`
- GREEN record `1794843133352254000-fa0c9ef3c8844981b641054ef42bf826`
- command blob `75028f31f3d7d8c8bbeb4c754197a851f0956e8f5143dffcb45f9d833d45d7d2`

The direct native fixture also completed GREEN. Supporting source-bound records are jobs Compose `1794842982506919000-4e9b589ad694456b86c6d3c9f197159f` and change verification `1794842983487244000-d6bcf431bdfe42228a05e8597dea40aa`.

## Approval boundary

The test delta is **APPROVED**. The planner's CRITICAL/Gate 3 ceremony is caused by adding the delivery-history verification input, not by a product, runtime, admission, or policy semantic change. This review does not invent a current intended RED or describe itself as preimplementation approval. The historical CI RED and empirical witness are the correction provenance. Automatic admission remains `UNKNOWN`; owner-authorized publication still requires the ordinary full-CI result and does not bypass policy.
