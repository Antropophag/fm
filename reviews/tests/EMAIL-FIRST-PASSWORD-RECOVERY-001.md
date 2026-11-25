# EMAIL-FIRST-PASSWORD-RECOVERY-001 — Gate 3 test review

- Reviewer: `/root/gate3_review` (independent reviewer; authored none of the reviewed specification, OpenSpec artifacts, tests, implementation, or evidence).
- Review date: 2026-11-24.
- Reviewed source: base `5e37074db0b9d6528e660d92c13cc93024d4ea28`; exact candidate source `afc4964b1c8a63ae334725dbf5338b71d2c49cfc6aa33dc6f18571903c97a9ee`; executable source `5852b51bf92a12ecd5dd5af14f0b7b441340c1959c052daf5f7e60bbfa7914eb`.
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T112341Z-a5b9fa50cc/package.json`, SHA-256 `796d4f17dd2da77b279f1b22ccf03643269664e0253874de7ffa6faef4f50897`.
- Required context: SHA-256 `ed0179c8ccd0b8d1683abf43f669f4588aa1bcd453797bbe27d1117707147def`.
- Verification plan: SHA-256 `c276469278f295c2fd33e15f8f750f478563db2a88bf146997c3ba0e4b26a018`; lane `CRITICAL`; required reviews `gate3`, `final`.
- Normative specification: `specs/EMAIL-FIRST-PASSWORD-RECOVERY-001.md`, SHA-256 `d8f7b0e947256af90a0a29be6cdec616fdc1201c1c818f90eca681cafa0963d4`.
- Test hashes: `email_first_password_001_test.php` `41f8049b08a74a4d66a9b7c10e8557b27ff31af5ef755553368c4f1a1ffcd960`; `password_recovery_001_test.php` `78d91d321e8156426901fea097a3778ecc46cbe28eabfcce5a26be9047712b14`; `password_recovery_edges_001_test.php` `c26589ee51317f12ffad785383ce4bd5a9bed50182b21614863297864743ff7c`; `email_password_migration_001_test.php` `3a3e5663bfc8ddcf69ba06582687714c9e6ecd9054018826dd347922581ee097`; `email_password_browser_001_test.php` `9e47c8d164cea755d15933d9c8756c8a521e1557eee0be4c8939a6929e76b672`.
- Scope: complete root-authored contract, OpenSpec artifacts/inventory, new tests and changed adjacent regression tests. Production implementation has not started; source schema remains 39. The owner-accepted first-password ownership model and dated 10..200 Unicode-character/four-group password policy were reviewed as fixed decisions, not reopened.
- Verdict: **CHANGES_REQUESTED**.

## Findings

1. **HIGH — blocked recovery admission is not tested, so an implementation may issue a grant and outbox intent for a blocked account.** The normative “Отсутствие доступа” scenario requires identical neutral output and no recovery permission/mail for unknown, blocked, and pending-password identities. `tests/Yii2/password_recovery_001_test.php:22-23` covers unknown/external and pending-password requests. The blocked cases at `:36-37` first issue a valid request while the user is active and only then block before reset or delivery. They prove invalidation after issuance, but never call `requestPasswordReset` for an already blocked identity and never compare its HTTP response with the eligible/unknown response. Add owner and real-HTTP witnesses that block an identity before the request, assert the exact neutral response, and independently observe no recovery request, password event, or outbox intent (while allowing the required quota-attempt fact).

2. **HIGH — the pre-dispatch eligibility matrix covers block only; email/version/expiry invalidation can still leak a usable recovery message.** The contract requires dispatch to recheck block, stored email, credential/session version, and expiry before invoking the network sender. `tests/Yii2/password_recovery_001_test.php:37` proves only block suppression. `tests/Yii2/password_recovery_edges_001_test.php:21` changes email and checks `passwordReset`, then restores the email without attempting delivery; no test changes credentials/session version before delivery or advances the clock to the exact expiry boundary before delivery. Add isolated grants for each invalidation cause, mutate one condition after commit and before `deliverPasswordReset`/the production job handler, assert the sender is never called, the safe terminal outcome/history is recorded without a token, and replay cannot send later. Include the exact `t+1800s` dispatch boundary.

3. **HIGH — migration preservation is asserted for IDs, hashes, blocking, and invitation rows, but not for roles or legacy identity links, and restore is only an inventory count.** `tests/IdentityAccess/email_password_migration_001_test.php:11-25` seeds no role assignment, role history, status history, or `fm2_legacy_identity_links`; therefore the explicit requirement to preserve roles, legacy links, and append-only history can regress while the test stays green. Its restore oracle calls `RuntimeRecoverySchemaV40::tables()`/`autoIncrement()` and checks counts, but never backs up and restores rows from the three new password tables, including encrypted grant metadata and delivery state. Seed representative pre-v40 roles/links/history and compare byte-for-byte after upgrade and replay. Add a bounded actual backup/restore round trip, or extend the existing recovery test with rows in every new table and assert exact restored contents and sequence behavior.

4. **HIGH — the required authorization recheck at provision commit has no race witness.** The contract explicitly requires `access.administer` to be rechecked at the write. `tests/Yii2/yii2_user_access_edges_001_test.php:42` proves a revoke committed before a later owner call is observed, and the duplicate provision race proves uniqueness, but neither overlaps permission revocation with provision. An implementation that authorizes once before opening its transaction and commits after revocation would pass. Add a deterministic barrier/lock race between permission revocation and `provision`; once revocation commits, provision must return `access_denied` with no identity, credential, default-role, audit, invitation, or outbox facts. Keep the existing last-superadministrator protection independent of this case.

5. **MEDIUM — the active delivery-goal rewrite discards the prior queue from its canonical location instead of preserving it there.** The candidate replaces almost the entire `docs/operations/current-delivery-goal.md` and moves prior text to `previous-delivery-goal.md`. The constitution says the original path owns the current queue and pause state and requires preservation of history/working stand. The root has already identified this frozen-snapshot defect: prepend the new goal while retaining the full prior queue at the canonical path, then refresh the exact-source package. The archival copy may remain supporting history but cannot be the sole retained queue.

6. **HIGH — no executable test proves the required recovery key/origin reach the web runtime and SMTP reaches the jobs runtime through production deployment wiring.** The contract names `FMONITOR_PASSWORD_RECOVERY_KEY`, `FMONITOR_PASSWORD_RECOVERY_KEY_ID`, `FMONITOR_PUBLIC_BASE_URL`, and existing SMTP settings as runtime prerequisites, and the OpenSpec design/tasks require readiness and deploy wiring. The current tests inject these values directly into `UserAccessFixture` or the test process; they do not exercise `deploy/production/manage` allowlisting or Compose service propagation. Read-only inspection shows the manage allowlist omits the new recovery key/key ID and Compose currently passes SMTP only to jobs, so a locally green implementation could deploy with recovery unavailable in web or delivery unavailable in jobs. Add a bounded deployment-configuration test that exercises the production render/manage seam with synthetic values and asserts key, key ID, and trusted origin are present only in the required web/jobs services, SMTP reaches the jobs sender, secrets are absent from rendered diagnostics/output, and missing required values produce the specified recovery-only readiness failure without breaking ordinary login.

## Coverage assessment

The matrix otherwise has strong independent oracles for normalized provision, first-password single use, dated password composition, ordinary login/hash preservation, role and status invariants, last-superadministrator concurrency, request idempotency, email/IP limits, exact expiry on reset, reset races, session invalidation, transaction rollback, trusted origin, no-store/no-referrer, token redaction, SMTP transient/unknown outcomes, production outbox routing, old invitation retirement, schema frontier, browser keyboard submission, and narrow layout. Changed weekly-mail, ordinary-auth, migration inventory, restore inventory, import, and proxy-log consumers are represented in the planner obligations.

The supplied additional baseline records are useful characterization evidence: ordinary auth `1795519446643496000-99d4ee0657ec4820a82a837cafc98724`, weekly delivery `1795519446676000000-08049d0a031e45bc90947fa5cb5d3eee`, and weekly unknown-delivery `1795519446618223000-b4e669879dbb4c549865b56b27b5bc3a` are GREEN at the unchanged snapshot. Initial-owner record `1795519446654295000-67e253846b0c472fa308a136a2a5abd8` is correctly excluded from baseline approval because the root-authored frontier expects schema 40 while production remains at 39.

## RED evidence assessment

All five retained feature records are bound to exact candidate `afc4964b1c8a63ae334725dbf5338b71d2c49cfc6aa33dc6f18571903c97a9ee` and executable source `5852b51bf92a12ecd5dd5af14f0b7b441340c1959c052daf5f7e60bbfa7914eb`. They reach real public seams and fail for the expected absent behavior: admin create UI, forgot/reset route (404), canonical migration 40 versus actual 39, and browser create-form absence. These are credible intended REDs rather than setup failures. They do not compensate for the missing assertions above; the corrected matrix needs fresh source-bound RED records and a refreshed reviewer package.

## Required changes

- Add blocked-at-request owner/HTTP coverage with independent no-grant/no-mail observations.
- Complete dispatch-time revalidation for changed email, credential/session version, and exact expiry, including production job replay.
- Seed and assert preservation of roles, legacy identity links, and append-only history; exercise actual restore contents for all new tables.
- Add a deterministic provision-versus-permission-revocation race.
- Add a bounded production deployment/readiness wiring oracle for recovery key, key ID, trusted origin, and jobs SMTP propagation/redaction.
- Restore the prior queue at `docs/operations/current-delivery-goal.md`, regenerate the exact-source plan/package, capture fresh intended RED, and request bounded Gate 3 rereview.

Gate 4 remains blocked. No implementation, production action, full local suite, final review, CI, publication, merge, or deployment is approved by this record.

---

## Gate 3 correction rereview — 2026-11-24

- Reviewer independence: unchanged; `/root/gate3_review` authored none of the corrected specification, OpenSpec artifacts, tests, implementation, or evidence.
- Refreshed package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T113247Z-28db226d4a/package.json`, SHA-256 `378e36fa85021b88d8ed9233d92ce320ac253955b7db52fcf7bbd04ce724c752`.
- Exact corrected candidate source: `d60bc1f00a835b9b2ee525123835bdc6a2e81f10b8e7e05c21987362275de6d5`; executable source `4d211e2a36e498e84e788815df504fb05edde70e7cb5d864f6da4e437a4be073`.
- Required-context SHA-256: `2b1a6b4cc2b68256519ba9d9debd07ccc52a791b35aaa2cf9687db746e00f8fa`.
- Verification-plan SHA-256: `4a91ad583115e8cc11ccf271a0c7fadd208169d544a2faff2195d80c6182c345`; lane remains `CRITICAL`, required reviews remain `gate3`, `final`.
- Corrected specification SHA-256: `7f59f48a9a2a8da3bcd6aeee03a0f402bab038c82a15bab1d4f7fe24d1f320b3`.
- Corrected/new test hashes: recovery `af3f9b128e0eb73e298da7ff85e99dad015ebac4b1c3a2999f3fbc8669587372`; recovery edges `62944a6411c15f37751f160b6ba0358ea62ab2051009a38b268d466a01d4b8ec`; provision authorization race `cd0e58d45741038cf630b7ebfb6a5f3d84f7d25360b0dbfebaa39673a6155a1c`; migration `9d1a8b3a2517a2406dcdababf625cd2dcd2a99b96d22bb96b796e2210c4c7e98`; actual recovery round trip `b53b713166de6570f6763b121247cf25e56abadd19447532cd0b548e712c3a0e`; production recovery configuration `6292c85fee44dd151fb960d64215bdf59c706c4edc4da0c596b01b45b10be3a5`.
- Verdict: **APPROVED**.

### Prior findings disposition

1. **Fixed.** `password_recovery_001_test.php` now issues an owner request against an identity already blocked and independently asserts no grant, password event, or outbox mutation. Its real HTTP matrix submits eligible, blocked, and unknown identities through separate anonymous sessions, requires the exact neutral text/status, compares rendered neutral content, and proves blocked/unknown requests do not create domain facts while leaving quota-attempt persistence available.

2. **Fixed.** `password_recovery_edges_001_test.php` now creates separate committed grants and invalidates email, session version, or the exact `created+1800s` expiry before dispatch. Every case requires a permanent safe outcome, zero sender calls, and terminal replay suppression after any reversible state is restored. Existing block suppression, production outbox/job replay, corrupted envelope rejection, and token-persistence checks remain. The added parallel request case also proves the remaining email quota/cooldown slot is admitted atomically.

3. **Fixed.** `email_password_migration_001_test.php` now seeds role definition, permission, membership, role event, status event, and legacy identity link, snapshots each complete row set, and requires byte-for-byte preservation after upgrade and replay. `runtime_jobs_recovery_001_test.php` now seeds all three new tables, including encrypted bytes and unknown delivery state, carries them through the public backup/restore workflow, compares exact restored rows, and proves preserved next values with deliberate `800`/`900` sequence gaps.

4. **Fixed.** `provision_authorization_race_001_test.php` uses a parent transaction to hold the default-role row and an uncommitted permission revoke, starts the real public owner command in an independent DB process, and observes an actual `INNODB_LOCK_WAITS` relationship before committing the revoke. It then requires `access_denied` and independently checks absence of identity, credential, provision event, invitation, and outbox facts. This deterministically rejects authorize-once-before-wait implementations while retaining the separate last-superadministrator cases.

5. **Fixed.** `docs/operations/current-delivery-goal.md` now prepends the new active goal and retains the full prior queue and pause/history text below a clear separator at the canonical path. The archival copy remains supporting evidence rather than the sole queue record.

6. **Fixed.** `password_recovery_configuration_001_test.py` exercises the real production `manage env-init`/`check-config` seam and a read-only real Compose render with synthetic values. It requires recovery key, key ID, and trusted public origin in PHP and jobs, SMTP in the jobs worker, excludes the recovery secret from unrelated web/database services and operator diagnostics, and proves omitted optional recovery configuration does not reject ordinary deployment. The existing HTTP edge test independently proves missing recovery configuration preserves normal login and makes recovery visibly unavailable with safe `503` output.

### Complete corrected-matrix assessment

No new blocking finding was found. The corrected tests retain the previously accepted provision/first-password, password-policy, RBAC/status, invitation retirement, recovery/reset, transport, browser, logging, import, schema, weekly-mail, and ordinary-auth coverage. Each prior blind spot now has a direct behavioral oracle rather than a source-text assertion. The owner-accepted first-password ownership decision remains unchanged and no email-verification requirement has been introduced.

### Fresh RED evidence

Eight exact-source records are included for candidate `d60bc1f00a835b9b2ee525123835bdc6a2e81f10b8e7e05c21987362275de6d5`: initial/password HTTP, provision authorization race, recovery, recovery edges, migration, actual runtime recovery, browser, and production configuration. The new deployment record exits at the real operator parser rejection `PRODUCTION_CONFIG_INVALID: FMONITOR_PASSWORD_RECOVERY_KEY`. The recovery-round-trip record reaches the real canonical migration and observes schema 39 instead of required 40 before any host dump-tool dependency. The authorization-race record presently stops at the absent public create seam; its deterministic DB lock/wait assertions are structurally complete for execution once that first missing behavior exists. The other records continue to fail at their expected absent routes/UI/schema. These are credible source-bound intended REDs; no production service, live SMTP, deployment, or destructive action was performed.

### Correction verdict

**APPROVED**

Gate 4 implementation may proceed under the prepared separate-executor package. This approval is limited to the corrected specification/test matrix at exact candidate source `d60bc1f00a835b9b2ee525123835bdc6a2e81f10b8e7e05c21987362275de6d5`; it does not approve implementation code, final source, CI, publication, merge, deployment, or live email.
