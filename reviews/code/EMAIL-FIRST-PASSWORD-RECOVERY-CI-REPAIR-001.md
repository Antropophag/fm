# EMAIL-FIRST-PASSWORD-RECOVERY-CI-REPAIR-001 — independent final correction review

- Reviewer: `/root/gate3_review` (independent; authored none of the correction specification, tests, implementation, or evidence).
- Review date: 2026-11-24.
- Origin: complete failure inventory from GitHub CI run `45401564714`: four failed jobs and 15 `REGRESSION_FAILURE` entries, all inspected before correction.
- Base commit: `2bc3fcea`.
- Exact reviewed source: `3593aeb452a91fd621bad0af1656e58b76ed129d53060073a3d7b8eb890f6321`; executable source `e0ff8b06acfd2497ff81fba2938d4828ee8db6582d839e4083ce3ab64b17810e`.
- Final reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T140538Z-0d07279cb4/package.json`, SHA-256 `e82632d859c7c5b9410f5e9076df38fe75211d25119d57d731a951b7948eb49a`.
- Required-context SHA-256: `90dd5682b84218d0d1487d5ea2de1fb9b43f2bdb6fa1a76681fbd9486b7498df`.
- Verification-plan SHA-256: `270f6aa401998a8254c741212d59982ef1a60bef8910df49ff8988dfcb204ed8`.
- Gate 3 record: `reviews/tests/EMAIL-FIRST-PASSWORD-RECOVERY-CI-REPAIR-001.md`, SHA-256 `8ae979fac30c848801abcd29a14a8447b415a21734f82b362ffb54e83481aacb`.
- Corrected application seam: `app/YiiRuntime/Controllers/AuthController.php`, SHA-256 `da0f1d274ef558013fe13c576568e0739fbf1305491b1dfa6491b3a61f97cd26`.
- Verdict: **APPROVED**.

## Application correction

The successful-login path now returns a safe destination rather than a boolean. After credential/rate-limit validation it:

1. creates the Yii identity session;
2. captures and validates the saved local return URL;
3. resets the stored return URL while the session remains open;
4. closes the session durably;
5. only then records successful authentication and clears prior failed attempts;
6. redirects using the captured safe destination without reopening the session.

A late session write failure therefore interrupts before `succeed()`, preserving the two existing failed-attempt facts. Wrong-password and rate-limit paths still append failure facts and render the neutral error. Normal and Unicode password login remain unchanged. The ordering is limited to the inherited session durability guarantee and introduces no new authorization or authentication policy.

## Complete 15-consumer disposition

All 15 consumers from the CI failure inventory are exact-source GREEN:

1. `original_ready_queue_manual_test.php` — synthetic active identities receive only missing valid credentials before current migration; queue behavior remains unchanged.
2. `production_make_live_001_test.py` — current backup manifest expectation advances to V40; the complete production lifecycle passes.
3. `assignment_order_original_attempt_audit_schema_001_test.php` — only missing active credentials are seeded before full-catalog replay; original audit/fail-closed assertions remain.
4. `classification_provenance_schema_001_test.php` — ordinary current repeat advances from 39 to 40; race, physical manifest and zero-mutation conflict assertions remain literal.
5. `object_detail_snapshot_schema_001_test.php` — full current runner output adds V40; object-detail manifests, partial recovery and DDL-denial checks remain.
6. `workforce_canonical_runner_001_test.php` — current table list adds the three password tables in exact binary order; all historical/current catalogue and prefix assertions pass.
7. `settlement_owner_001_test.php` — only current terminal frontier changes to 40; settlement ownership facts remain unchanged.
8. `settlement_v2_concurrency_001_test.php` — only current terminal frontier changes to 40; financial concurrency assertions remain unchanged.
9. `settlement_v2_db_001_test.php` — only current terminal frontier changes to 40; exact financial/database assertions remain unchanged.
10. `object_details_editing_restore_roundtrip_001_test.php` — current recovery body uses V40 while pinned archived historical bodies remain unchanged; row/sequence/state preservation passes.
11. `production_runtime_browser_001_test.php` — fixture setup completes valid credentials before V40; real production browser behavior remains unchanged.
12. `runtime_readiness_load_001_test.php` — current canonical readiness frontier advances to 40; attestation/database mismatch and fail-closed checks remain.
13. `yii2_production_web_cutover_001_test.php` — route contract adds current auth routes, protected retired invite GET behavior and exact current asset hash; cutover route/security checks pass.
14. `yii2_otiz_concurrency_001_test.php` — only current full-catalog fixture coherence changes; OTIZ concurrency facts remain.
15. `yii2_session_failure_001_test.php` — late write/destroy failures, secret/cookie/redirect safety, failed-attempt preservation and normal session lifecycle are GREEN through the typed browser profile.

No historical recovery class, pinned source hash, legacy adapter writer oracle, financial expectation, concurrency outcome, append-only history, or old-tool refusal was relaxed. Credential fixture changes insert only missing hashes for synthetic active identities and preserve existing IDs, emails, roles, history and hashes.

## Evidence

The Gate 5 package binds GREEN records for the changed workforce literal and session-durability acceptance:

- `1795528959497924000-623a794e25c740eda9b7dfcc8a3a4e73` — workforce canonical runner;
- `1795528959497867000-e33728dc7132459ea434b923e7a0f175` — typed session-failure flow.

The retained exact-source inventory `email-password-ci-repair-green-results.json` contains the remaining consumers. Production lifecycle record `1795528969242506000-3e092c4feba54d128e7eec79dbb6a0c8` is GREEN. Architecture plus mandatory PilotHttp global qualification is GREEN at `1795529069506674000-09fa89b542e14c6eb251294c04ba14f8`.

No full local suite, production migration, deployment, live email or destructive action was performed.

## Final verdict

**APPROVED** for exact correction source `3593aeb452a91fd621bad0af1656e58b76ed129d53060073a3d7b8eb890f6321`.

The CI-repair candidate is ready for publication to the existing PR and a fresh exact-source GitHub CI run. This review does not claim that future CI run GREEN and does not authorize merge or deployment by itself.
