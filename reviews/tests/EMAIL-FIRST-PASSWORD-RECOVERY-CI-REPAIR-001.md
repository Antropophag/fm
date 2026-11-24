# EMAIL-FIRST-PASSWORD-RECOVERY-CI-REPAIR-001 — bounded Gate 3 test review

- Reviewer: `/root/gate3_review` (independent reviewer; authored none of the reviewed specification/test delta, application correction, or evidence).
- Review date: 2026-11-24.
- Scope: corrections derived from complete CI run `45401564714`: 15 regression failures across four failed jobs, fully inventoried and analyzed before edits. This is a test/spec Gate 3 review, not implementation approval.
- Base commit: `2bc3fcea` (`Replace invitations with email-first passwords and recovery`).
- Exact reviewed candidate source: `a77ce675f14b34c727ee476eb4f6c303238fd842bfb53076e3ae4ad780c398ef`; executable source `b6ad244560a5ee89559e9eaaa207c45a001fee20c5bc2a42f83d054c3e4547c0`.
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T135923Z-3f0282e6a5/package.json`, SHA-256 `56cfe9ee2da57eeb10fd30b7cbe979f70bdff383d4b5ff198e7c06b20bf89b4b`.
- Required-context SHA-256: `90dd5682b84218d0d1487d5ea2de1fb9b43f2bdb6fa1a76681fbd9486b7498df`.
- Verification-plan SHA-256: `45a1fd89e99814643def834e0e45bf0cb8a3ca03fe5c6e0cc1a789bf4e7ea615`.
- Corrected specification SHA-256: `5c88600b0c7b87fc63e561feacf94cbd9510a47e4de6ec2cdd3fdcf5eab88e67`.
- Session regression: `tests/Yii2/yii2_session_failure_001_test.php`, unchanged command blob `3c641c51f73e67b6ff904e50d90a9400c32909496d84bf21d0f7a92ceff6847b`.
- Corrected workforce literal test SHA-256: `c211da806d361fbc9dc43e438a001c8f67451bb5ca7732d7f046d857046afa7e`.
- Verdict: **APPROVED**.

## Session durability regression

The existing session-failure test is an independent, reachable oracle for inherited `YII2-AUTH-001` behavior. It performs two failed password attempts, then injects a native late session-write failure during a correct login. The HTTP response must fail safely without cookie/redirect/secret disclosure, and the two failed-attempt facts must remain. The current implementation returns the safe failure but has already cleared those facts because `authenticate()` calls `succeed()` before durable session close.

The appended normative rule is coherent with the existing behavior: capture and reset the safe return URL while the session is open, complete login/session mutation, close the session durably, then clear failed attempts. A close failure must skip `succeed()`, and code must not reopen the session afterward. This preserves rate-limit history without changing normal successful-login behavior.

Record `1795528728338881000-567e2633bb514193803a0e42e16c5270` is exact-source intended RED through the planner-selected browser profile and MariaDB service. It fails only at `failed attempts survive late session write failure` with expected 2 versus actual 0; setup and earlier safe-response assertions pass.

## Current-frontier and fixture corrections

The root-owned test/helper delta corrects stale consumers without weakening their original oracles:

- full current catalogue results advance from V39 to V40, and current backup manifests advance to 40/109/61;
- exact current table inventories add the three password tables in binary-sorted order; the initially discovered workforce mismatch was order-only, then corrected and rerun GREEN;
- partial fixtures seed only missing credentials for active synthetic identities before V40 preflight, using synthetic Argon2id hashes; existing IDs, emails, roles, history and existing hashes are preserved;
- `PreopeningFixture` deliberately applies through V39, creates its planned active identities/credentials, then completes V40, so preflight remains strict rather than being bypassed;
- current `runtime_recovery_001_test.php` uses V40/109 for current-image callers, while the outer forward-update test still archives and executes pinned historical source bodies and manifests;
- cutover contract updates retain real access ordering: unauthenticated GET of the retired admin invite route is protected and redirects to login, while authorized retired mutation behavior remains covered elsewhere; new auth routes, method fallbacks and the exact current CSS hash are literal;
- historical schema classes/source hashes, legacy adapters, financial calculations, concurrency races, append-only facts, recovery refusal, old-tool rejection and data/sequence comparisons remain unchanged.

The 13 unchanged corrected consumer blobs reached GREEN at source `8483a871...` and remain explicit mandatory consumers. The sole subsequently changed consumer, `workforce_canonical_runner_001_test.php`, is exact-source GREEN at record `1795528728338490000-b0ebc055907a49ee9330e1daa48ff93e`. All 15 commands remain mandatory for exact-source post-implementation verification and CI; prior GREEN is not represented as final-source approval.

## Complete CI triage assessment

The corrections correspond to the complete four-job/15-failure inventory rather than a sampled first failure. No production migration, live system, global Docker cleanup or full local suite was used. The application regression is isolated to the session ordering rule; the remaining failures are stale current-frontier literals or incoherent synthetic active-user fixtures exposed by the stricter V40 preflight.

No test-design blocker remains. The proposed application correction is bounded to successful login ordering and must retain the unchanged wrong-password, rate-limit, safe return URL, cookie, session destroy and infrastructure-failure assertions.

## Verdict

**APPROVED** for exact test/spec candidate `a77ce675f14b34c727ee476eb4f6c303238fd842bfb53076e3ae4ad780c398ef`.

The separate executor may implement the reviewed session ordering correction. Final verification must run all 15 mandatory consumers at the resulting exact source before final review/CI repair publication. This verdict does not approve implementation code, final source, CI, merge, deployment or live operations.
