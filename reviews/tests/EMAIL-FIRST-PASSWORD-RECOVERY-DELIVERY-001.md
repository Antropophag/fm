# EMAIL-FIRST-PASSWORD-RECOVERY-DELIVERY-001 — bounded Gate 3 test review

- Reviewer: `/root/gate3_review` (independent reviewer; authored none of the reviewed specification, tests, implementation, or RED evidence).
- Review date: 2026-11-24.
- Scope: combined correction matrix for the eight findings discovered during advance final-review inspection. Prior main, schema, integrity, and qualification Gate 3 records remain unchanged. This is not an implementation review.
- Reviewed exact candidate source: `8611acf955998f50f9cacbe58857639bd089903d7bc85b02cb9064e2eec2cff5`; executable source `4f90c35baa7bceddb728d8bb71cde47fa776f3466bf7e3809c721c550a17164d`.
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T124702Z-76aec84fa4/package.json`, SHA-256 `6ff584736326fd4a211f8096b8475291cb5a39b7a77adfc8573569725a034095`.
- Required-context SHA-256: `5de31d409fa31a7c2dd3dfb2d20e1b18ae141eb265a831e33adbfb098184b4b4`.
- Verification-plan SHA-256: `e7608ca07dca3f71c6d07f83d350da48720284e088f10045a52850e335073233`.
- Normative specification: `specs/EMAIL-FIRST-PASSWORD-RECOVERY-001.md`, SHA-256 `95a857e7cf529e9ab403a8a1ed85c3b61408031c9b0a6b26486421dd3e8fe587`.
- Combined behavioral test: `tests/Yii2/password_recovery_delivery_safety_001_test.php`, SHA-256 `83517a3a13e87de26998ff31758f7127e0f746ec2e03a43a43679d23499aad0a`.
- Deployment test: `tests/Deployment/password_recovery_configuration_001_test.py`, SHA-256 `0f3d083fa677be2688f4fab53ca968b7e8fd48fa7fb53e987d6eec96a9d6faa1`.
- Verdict: **CHANGES_REQUESTED**.

## Findings disposition

1. **Covered — same-session request identity and form replay.** The test submits a real HTTP form, snapshots all facts, replays the same POST and requires neutral 200 with no changes. It then performs a new GET in the same cookie jar, requires a different request ID, and submits a second eligible email that must create the second grant. This independently enforces both same-form idempotency and later-request availability.

2. **Covered — durable delivery claim, overlap, and uncertain settlement.** Two independent owner/DB connections overlap at the real sender callback; the nested delivery must not invoke its sender. A trigger rejects the final delivered settlement after the fake transport accepted the message; the row must remain durable `unknown` with no encrypted envelope and replay must not send. A sender exception after entry has the same terminal no-resend requirement. The callback remains outside a DB transaction, preserving the accepted architecture boundary.

3. **Covered — real plain-text newlines.** The captured message must contain a real newline immediately before the HTTPS link and must contain no literal backslash-`n` sequence.

4. **Not applicable as a code finding; behavior already passes.** The combined test observes both the real forgot-password GET form and POST confirmation and requires `Cache-Control: no-store` and `Referrer-Policy: no-referrer`. Those assertions are absent from the failure inventory, confirming the global Yii response pipeline already supplies the required headers. No duplicate controller-specific change is warranted. Retain these assertions as regression protection.

5. **Covered — pending-password directory language.** A real pending identity is rendered through the administrator directory. The page must show “Ожидает первого входа” and, after stripping markup and normalizing case, contain no invitation wording.

6. **Open — deployment secret isolation is checked only for `deploy/production/compose.yaml`, leaving the active runtime Compose contour unqualified.** `password_recovery_configuration_001_test.py:40-51` renders only the production file. The reviewed source also changes `deploy/runtime/compose.yaml`, where the shared `runtime-environment` currently sends the recovery key and SMTP password to prepare, migrate, startup-check, and jobs-scheduler. An implementation can fix the production file and leave the same overexposure in the runtime contour while this test turns green. Render both repository-owned Compose files with all relevant profiles and require key/keyId/origin plus SMTP only in the approved consumers for each: PHP receives recovery configuration needed for HTTP readiness; jobs-worker receives recovery configuration and SMTP; prepare, migrate, startup-check, jobs-scheduler, web, DB, staging and other services receive neither recovery key nor SMTP password. Keep diagnostic redaction assertions for both renders.

7. **Open (partially covered) — malformed client identities must create bounded quota facts, but oversized email attempts may disappear without failing the test.** The client matrix snapshots attempt count and requires exactly three new bounded rows. The email matrix only requires neutral results, no exception, and no grant; it never requires the three malformed/oversized email calls to add quota-attempt rows. Although the final loop constrains rows that happen to exist, an implementation that simply returns `accepted` for every email longer than 254 without recording an attempt passes, contrary to the appended requirement for a bounded quota representation and the anti-flood policy’s independence from identity existence. Snapshot attempt count before the email loop, require exactly one new attempt per distinct request ID, assert every stored email representation is at most 254 characters and cannot collide across the three test inputs, and retain the no-grant/no-storage-error assertions. The representation may be a test-owned deterministic bounded value; it must preserve request idempotency and quota accounting without creating a fictitious identity.

8. **Covered — strict HTTPS origin.** The test rejects path, query, fragment and userinfo variants, accepts a host, optional port and a trailing slash, and requires canonical removal of only the trailing slash.

## RED evidence assessment

Record `1795524395414050000-208984c7cf0142c39f418c94d28d3e1b` is bound to exact candidate `8611acf955998f50f9cacbe58857639bd089903d7bc85b02cb9064e2eec2cff5`. It reaches the real HTTP/session flow, independent owner connections, sender boundary, settlement fault, administrator directory, public request command and recovery configuration. It aggregates 18 actual failures. The privacy-header checks are notably absent from that list, providing direct evidence for disposition 4. The failures are missing behavior rather than setup faults.

Record `1795524395419655000-058361995f684d4eab0f67b239aa5c4e` reaches the real production `manage` parser and real Docker Compose render, then fails on secret exposure to `jobs-scheduler`. It is a credible RED for the production contour, but supplies no evidence for the unrendered runtime contour.

## Required changes

- Extend the deployment test to render and qualify both production and runtime Compose contours across all relevant profiles and all nonconsumer services.
- Require each malformed/oversized email command to create a distinct, bounded quota-attempt fact while remaining neutral and grant-free.
- Preserve all resolved behavioral cases, including the already-passing global privacy-header assertions.
- Capture fresh exact-source RED, regenerate the bounded package, and request delivery-safety Gate 3 rereview before implementation correction.

The seven behavioral findings are otherwise covered, with finding 4 correctly classified as already satisfied. Implementation correction remains blocked on the two test gaps above. No implementation code, final source, CI, publication, merge, deployment, or live email is approved by this record.

---

## Delivery-safety correction rereview — 2026-11-24

- Reviewer independence: unchanged; `/root/gate3_review` authored none of the corrected specification, tests, implementation, or evidence.
- Refreshed package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T125639Z-a64eb10c26/package.json`, SHA-256 `80fce782934c4ba372656cc9bc6bd5259ff68fd8f08622415d268abde5fcfa39`.
- Exact candidate source: `23f9f586dd68438eca18291e92997a2ff1448fad7d67cc4e678dfa106b182668`; executable source `779292aa9d4e2c86b5f30e41cccd01b0915d1f041a4a95291198203e5ac628cc`.
- Required-context SHA-256: `5de31d409fa31a7c2dd3dfb2d20e1b18ae141eb265a831e33adbfb098184b4b4`.
- Verification-plan SHA-256: `70ba9d7f7aa92de1d834e271a1943645497f760e0bd460405c14863f1e5fb2b5`.
- Corrected behavioral test SHA-256: `ed4af083abc81994a35025a4b7f103d317951a398c0acf04c80750dfd4580b07`.
- Corrected deployment test SHA-256: `c8e27f6657ae9d659e8c502d41c89ff0a43c5f86433880a6facc2a41c5795ee9`.
- Verdict: **APPROVED**.

### Open findings disposition

6. **Fixed.** The deployment test now renders both `deploy/production/compose.yaml` and `deploy/runtime/compose.yaml` with every profile enabled. In both contours it requires recovery key, key ID, trusted origin and SMTP credentials in PHP and jobs-worker. It aggregates all other services and fails with their contour-qualified names if either the recovery key or SMTP password appears. This covers prepare, migrate, startup-check, jobs-scheduler, local-integration, staging, web, DB, and any future rendered nonconsumer. Operator and Compose diagnostics retain their secret-redaction assertions.

7. **Fixed.** The behavioral test snapshots quota-attempt count before three distinct malformed/oversized email commands and requires exactly three new attempt facts, while independently requiring neutral accepted results, no storage exception, no recovery grant, and canonical maximum lengths for every stored email/client representation. Distinct request IDs preserve idempotency identity even when a bounded representation is conservative. The client boundary retains its own exact three-attempt count.

All other dispositions from the first review remain accepted, including finding 4 as already satisfied by the global `WebResponse` pipeline and proven by real forgot-password GET and POST headers.

### Downstream current-frontier test delta

The additional current-consumer changes are valid and do not weaken historical or business assertions:

- Thirteen schema/recovery consumers advance only the full current catalogue/result to V40, 109 tables, 61 auto-increment entries, and the three additive password tables. Historical recovery classes, source hashes, component versions, financial facts, incompatibility cases, row snapshots, sequence checks, and replay assertions remain literal.
- The active Yii legacy-link acceptance replaces retired invitation issuance/activation with public provision, role assignment, login email step and first-password submission. It preserves linking-before-password behavior, null credential before first password, local Argon2id credential isolation from legacy secrets, successful normal login, authorization, CSRF/content/body bounds, atomic rollback and safe-error assertions.
- `PreopeningFixture` now deliberately migrates through V39, creates its planned active identities and valid credentials, then completes V40 before tested flows. The paging, queue-lineage and address-register fixtures seed only missing active credentials using synthetic Argon2id values before the full current migration; existing IDs, emails, roles, history and hashes are untouched. Their bounded consumer/browser checks are GREEN.
- The experimental `OtizBrowserFixture` edit was reverted byte-for-byte. Its separately retained historical OTIZ-form diagnostic is neither a canonical-suite failure nor claimed GREEN, and no unrelated OTIZ behavior was changed.

### Fresh evidence

Record `1795524971138727000-2eefcc29e00b4e209a517accd71374cb` is exact-source intended RED for the complete behavioral matrix. Its aggregate inventory now includes the exact malformed-email quota-attempt failure alongside session rotation, delivery overlap/uncertainty, plain text, pending-directory, client bounds and strict-origin failures. Privacy headers continue to pass.

Record `1795524971135877000-396fdf8b87f846afa844623fada32939` is exact-source intended RED across both Compose contours and all profiles. It reports concrete nonconsumer exposures in production and runtime services rather than stopping at the first service.

The external bounded consumer inventories report the retained current-frontier and fixture consumers GREEN except the explicitly reverted, unregistered OTIZ diagnostic described above. No full local suite, production action, live email, or destructive migration was run.

### Rereview verdict

**APPROVED**

The complete correction test matrix for findings 1–8 is approved at exact candidate source `23f9f586dd68438eca18291e92997a2ff1448fad7d67cc4e678dfa106b182668`. Implementation correction may proceed under its separate executor package. This verdict does not approve implementation code, final source, final review, CI, publication, merge, deployment, or live email.

---

## F6 dependency correction — 2026-11-24

The preceding pass incorrectly classified `jobs-scheduler` as an SMTP nonconsumer. Read-only dependency inspection confirms `JobsRuntimeCommand` uses `SmtpConfiguration::isConfigured`/`fromEnvironment` to enable `OutboxDispatchScheduler` and the weekly scheduler. Removing SMTP from that service would silently disable recovery dispatch and existing weekly email scheduling. The repository’s existing optional-SMTP Compose regression correctly treats scheduler SMTP as required.

Correct F6 boundary:

- recovery key and key ID: PHP and jobs-worker only;
- trusted public origin: PHP and jobs-worker, plus any already-established scheduler need only if independently required by its existing weekly contract;
- SMTP tuple/password: PHP, jobs-worker, and jobs-scheduler;
- prepare, migrate, startup-check, local-integration, web, DB and other services receive neither recovery key nor SMTP password.

The current deployment test fails `jobs-scheduler` merely for containing the SMTP password, so its approved implementation target would regress existing jobs behavior. The F6 disposition and overall rereview verdict above are therefore superseded.

### Corrected verdict

**CHANGES_REQUESTED**

Revise both Compose-contour matrices to distinguish recovery-key consumers from SMTP consumers, retain the existing optional-SMTP scheduler regression, capture fresh exact-source RED, and request bounded rereview. Other findings and downstream fixture dispositions remain approved.

---

## F6 dependency correction rereview — 2026-11-24

- Reviewer independence: unchanged; `/root/gate3_review` authored none of the corrected specification, tests, implementation, or evidence.
- Package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T130611Z-1009adf496/package.json`, SHA-256 `548c3c933ef5ce8923636a04d346a76afaf8e6c3b5409dd9aefbd4275e586b32`.
- Exact candidate source: `5208cc855b1fe2193b9b7f3700bb591815b87eceb13d6d4901abbcb89661db0b`; executable source `d175c7cd792889c3b8f37c6ccc4f7aa23a1570b0fca1223c1fbe1371348ba213`.
- Required-context SHA-256: `5de31d409fa31a7c2dd3dfb2d20e1b18ae141eb265a831e33adbfb098184b4b4`.
- Verification-plan SHA-256: `81a867459c2f5735580d46611abd6ab9bf44252651af4a941050325990271135`.
- Corrected deployment test SHA-256: `693b70e298547880d0a80428a9102153d858927c274ca130f2ce61db9dcd9ea2`.
- Strengthened behavioral test SHA-256: `08bf6a5e5f9c1a698557b178af6515c86bbf41cd29fc95f0d7339dd35b503710`.
- Corrected specification SHA-256: `f7d333bc921d7d194cdf33d7f8a0e3bbad8d9d887d935f474649a50d24e8228f`.
- Verdict: **APPROVED**.

### Corrected consumer matrix

The test now renders all three supported contours with all profiles:

- `deploy/production/compose.yaml`;
- `deploy/runtime/compose.yaml`;
- `tools/delivery/compose.runtime.yaml.in`.

For every rendered service it applies two independent allowlists:

- recovery key/key ID and trusted public origin must be present in PHP and jobs-worker, and the key must be absent elsewhere;
- the complete SMTP tuple must be present in PHP, jobs-worker, and jobs-scheduler, and the SMTP password must be absent elsewhere.

This preserves the scheduler’s actual `SmtpConfiguration` dependency for outbox and weekly scheduling while removing secrets only from genuine nonconsumers such as prepare, migrate, startup-check, local-integration, web and DB. No new flag or jobs redesign is introduced. The operator output and Compose stderr redaction assertions remain.

The existing `erp_equipment_facts_runtime_001_test.py` is a valid independent public parser oracle for `.env.example`: it fails with the two new recovery variables present, and the retained one-variable diagnostic shows removing only those keys changes exit 64 to success. Adding those exact keys to `tools/delivery/local-runtime-env` is therefore observable without weakening its existing ERP/environment allowlist checks.

### Durable-claim strengthening

The delivery-safety test now reads the recovery request through the fixture’s independent DB connection from inside the sender callback and requires `delivery_state=unknown` with `encrypted_token=NULL` before transport can fail or another owner can enter. This proves the no-resend claim is durable and externally visible before network activity, rather than merely an in-memory flag on the delivering owner. The exact-source record is GREEN after the separately authored implementation correction.

### Evidence

- `1795525550025218000-625693b7351e4760b534602987d92a1b`: intended RED enumerating missing scheduler SMTP in both published Compose contours and missing PHP/worker recovery plus PHP SMTP wiring in the canonical local template.
- `1795525550013358000-45590113df2f4d81bb9793d4467180cd`: intended RED at the real local-runtime environment validator for the new `.env.example` keys.
- `1795525550050241000-39b64f9473f04cd7ad84b32b508c3d8a`: exact-source GREEN for the strengthened delivery-safety matrix, including independent durable-claim observation.

### Final bounded verdict

**APPROVED**

The corrected F6 dependency model and its three-contour/parser tests are approved at exact source `5208cc855b1fe2193b9b7f3700bb591815b87eceb13d6d4901abbcb89661db0b`. All other delivery-safety findings and downstream fixture dispositions remain approved. Implementation correction may proceed under the separate executor package; this record does not approve final source, final review, CI, publication, merge, deployment, or live email.
