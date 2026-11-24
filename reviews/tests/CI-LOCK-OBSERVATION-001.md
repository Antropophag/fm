# CI-LOCK-OBSERVATION-001 — bounded Gate 3 test review

- Reviewer: `/root/gate3_review` (independent reviewer; authored none of the regression, planned correction, production code, or evidence).
- Review date: 2026-11-24.
- Origin: corrected GitHub CI run `45411089443`, with complete failed-job and regression inventory collected before edits. Exactly one regression remained in `assignment_order_original_database_setup_001_test.php` at the cleanup lock-wait observation.
- Base: `de1c7b91c49493a37ef445d86440551a7625a2b4`.
- Exact reviewed test candidate: `1415c740b6aff1549c87634b97cd03f9bc8723e296ece7fad45cc857a571bb29`; executable source `f5e0a3db8a4a86f91933f451cfbb022069467dca2b3f75000499a812351b1b5a`.
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T144707Z-3ab4d555b2/package.json`, SHA-256 `9cf2e4ee87c3dddb8a15c491bea76becc6f5f7881358f0c0eed1304f25bdf19e`.
- Required-context SHA-256: `5de31d409fa31a7c2dd3dfb2d20e1b18ae141eb265a831e33adbfb098184b4b4`.
- Verification-plan SHA-256: `aec0c841eb6b79987763d14a35339986b5441029c5da2180af19b9a9fc687e6f`.
- Regression test SHA-256: `12e148caaebd372b208782d4773d1bbe8250886e737fdf07e3f518309067d381`.
- Verification input SHA-256: `5d31e45c17de20c6e95d00b7ae4b8ede28dbda301913353552076c2d98bc6a01`.
- Planner classification: sensitive test-qualification boundary; Gate 3 and final review required.
- Verdict: **APPROVED**.

## Diagnosis and regression reachability

The test and its direct dependencies were byte-identical to the preceding passing commit, while CI failed only the cleanup observer’s inability to see the lock relation. The controlled diagnostic distinguishes the worker/lock behavior from metadata observation:

- an unprimed observer passes at both 10 ms and 200 ms;
- a legitimate pre-ENTER `INNODB_TRX` read confirms the worker has no transaction yet;
- after ENTER, the primed observer fails with 10 ms polling at the original exact lock assertion;
- changing only that polling cadence to 200 ms makes the complete test pass.

This matches MariaDB 11.4.7’s transaction information-schema cache: refresh requires more than 100 ms since the previous read, and each read updates the last-read timestamp. A 10 ms loop can therefore preserve the pre-wait snapshot for the whole deadline even while the real worker is blocked.

Record `1795531541232668000-425a40d8af764f41a85853504ccf3f5c` is exact-source intended RED. It reaches the real cleanup worker and fails only at `Cleanup exact worker lock-wait relation is independently observed`; setup, worker identity, controlled ENTER and initial snapshot assertions pass.

## Oracle preservation

The root-authored regression adds only the deterministic pre-ENTER metadata snapshot for cleanup mode. The planned executor change is limited to the observer loop’s sleep from 10 ms to 200 ms. It preserves:

- the original 15-second deadline;
- the exact requesting worker connection ID and exact parent blocker ID;
- non-null `trx_wait_started`;
- `SERIALIZABLE` isolation;
- the exact process-task table and `FOR UPDATE` query predicates;
- the no-early-terminal-result window;
- successful worker completion only after parent lock release;
- worker exit/stderr and surviving parent/admin connection checks;
- independent seed-mode processlist observation;
- every schema, row, byte/collation, mutation-sensitivity, zero-DML conflict, append-only, cleanup idempotency, foreign-row preservation and final removal oracle in the rest of the test;
- exceptional TERM/KILL/reap cleanup behavior.

No production code, MariaDB setting, actual transaction/lock behavior, fixture ownership rule, admission policy or business expectation changes.

## Verdict

**APPROVED** for exact regression source `1415c740b6aff1549c87634b97cd03f9bc8723e296ece7fad45cc857a571bb29`.

The separate executor may apply the single 200 ms observer-cadence correction. The complete test must then pass at the resulting exact source and receive independent final review before publication. This verdict does not approve implementation code, CI, merge or deployment.
