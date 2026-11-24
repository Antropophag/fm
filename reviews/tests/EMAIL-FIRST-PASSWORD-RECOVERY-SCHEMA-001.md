# EMAIL-FIRST-PASSWORD-RECOVERY-SCHEMA-001 — bounded Gate 3 test review

- Reviewer: `/root/gate3_review` (independent reviewer; authored none of the reviewed schema specification, tests, implementation, or RED evidence).
- Review date: 2026-11-24.
- Scope: schema-qualification test delta only. The previously approved full matrix at candidate `d60bc1f00a835b9b2ee525123835bdc6a2e81f10b8e7e05c21987362275de6d5` and its six closed findings were not reopened. This is not an implementation review.
- Reviewed exact candidate source: `c6ddd215a46b0463aaaae5d4a8d4e55e514147d38371d157d1ebf6f7ef97c951`; executable source `eb2e1bd6ba3b05fc3f0b1aacfc941e82cca2907a7f114b6965da29daf31f8665`.
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T114811Z-0b0c6b73e9/package.json`, SHA-256 `21518b38eb361589006b2cc672b53281aaae7aecd54edd99585a3fbf9f191767`.
- Required-context SHA-256: `ce569524e4a17e2706cb01f66baee6d573681bb68c1cdc868b1b7920d52715c9`.
- Verification-plan SHA-256: `d77bbaa2fc7e309b48d2f29452307cda0b7671ed3b2c78a1ee45aa3e2580d299`.
- Normative specification SHA-256: `505e6bd6ef2d8528eef812275093aa3db8a260b87d66183c1fb1a0f458d159b2`.
- New schema test: `tests/IdentityAccess/email_password_schema_001_test.php`, SHA-256 `66f12e3037744a56bc517c900d41301b2b65f509372fbb1e80978c4ce74f13f7`.
- Reconciled regressions: `identity_access_schema_001_test.php` SHA-256 `a84607ad0e677f5efc84b51ed304d924a2b8549e40e9b20835357abb23a31034`; `production_migration_runner_001_test.php` SHA-256 `46fc55bf977c6b324741a850ab488499f3064eadeb40a2872df96e15394d5909`.
- Verdict: **CHANGES_REQUESTED**.

## Findings

1. **HIGH — the accepted schema baseline is implementation-derived, so the test does not independently enforce the complete semantic fingerprint required by the contract.** `tests/IdentityAccess/email_password_schema_001_test.php:8` first accepts whatever the current implementation calls ready. Each mutation then captures that implementation-created table with `SHOW CREATE TABLE` at `:25`, changes one selected property, and restores the captured DDL at `:32`. The only literal complete structure asserted is the five-value enum. There is no test-owned manifest for all three new tables covering every column’s exact type, unsigned/length, nullability, default, extra, charset/collation, table engine/collation, every index’s name/order/uniqueness/type, and the recovery-request FK’s referenced table/columns and ON DELETE/UPDATE actions. An implementation with a wrong unmutated property—for example `encrypted_token` width/nullability, `key_id` collation, attempt-request uniqueness, a missing secondary index component, or the wrong FK target/action—can be the accepted baseline and pass all ten mutations. Define a literal independent semantic manifest, query `information_schema` into comparable tuples, and require exact equality before running the mutation matrix. Keep the mutations as useful proof that both `isReady` and `apply` reject drift without repair.

2. **HIGH — interrupted-upgrade coverage proves only one table subset, while the promised known-partial recovery has multiple reachable DDL-prefix states.** The partial fixture at `email_password_schema_001_test.php:35-41` starts with the additive enum and exactly `fm2_password_events`, whose DDL is copied from the current implementation. A crash across MariaDB’s auto-committing DDL can also leave additive enum with no new tables or with events plus requests before attempts; interruption can occur around the waiting-identity credential insertion/state normalization as well. The test neither enumerates those reachable prefixes nor independently defines the expected partial table. An implementation special-cased to the one tested subset can pass while refusing another normal retry. Build separate test-owned fixtures for every reachable ordered table prefix (zero, events, events+requests, all tables with unfinished identity projection), seed invited/pending representations and facts appropriate to each stage, and require one public rerun to finish V40 without loss, followed by a no-op replay. Use the literal manifest from finding 1 rather than copying `SHOW CREATE TABLE` from the candidate implementation.

## Regression reconciliation assessment

The two existing-test updates are valid and should be retained:

- `identity_access_schema_001_test.php` preserves the historical literal manifest while adding a current-manifest switch for the additive five-value enum. Replacing the active/null-hash fixture with an Argon2id hash makes the populated successor fixture consistent with V40 preflight rather than weakening it. Current catalogue expectations correctly advance to V40.
- `production_migration_runner_001_test.php` adds exactly the three V40 tables to the successor inventory, changes only the current `fm2_pilot_users.activation_state` expectation to the additive enum, and advances current replay/recovery results to 40. The historical underlying contract literals remain untouched.

## RED evidence assessment

Record `1795520866019311000-2ff82de927b24d46843b0a0f12db3cbf` is bound to exact candidate `c6ddd215a46b0463aaaae5d4a8d4e55e514147d38371d157d1ebf6f7ef97c951` and exits at the final aggregate oracle with all 22 expected misses: additive enum, readiness and migration rejection for ten mutations, and partial resume. It reaches the public `isReady`/`apply`/canonical migration seams and is a credible intended RED rather than setup failure. Its breadth demonstrates the present implementation gap, but it cannot establish correctness for schema properties and partial states absent from the test body.

## Required changes

- Add a complete test-owned literal semantic manifest for all three V40 tables and exact equality against `information_schema`, including FK target/actions and index uniqueness/type/order.
- Enumerate every reachable ordered partial V40 prefix and unfinished identity-projection state, using test-owned DDL, then require successful completion and stable replay for each.
- Preserve the ten drift mutations and both valid frontier-test reconciliations.
- Capture fresh exact-source RED, regenerate the bounded reviewer package, and request schema-only Gate 3 rereview before the paused schema executor resumes.

The prior main Gate 3 approval remains intact for its exact source. Schema implementation/resumption is blocked by this bounded verdict; unrelated already-authorized executor work, final review, CI, publication, merge, deployment, and live email are outside this record.

---

## Schema correction rereview — pass 2 — 2026-11-24

- Reviewer independence: unchanged; `/root/gate3_review` authored none of the corrected schema contract, tests, implementation, or evidence.
- Refreshed package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T120551Z-486ea7774d/package.json`, SHA-256 `48b621961d6f48dd237356d3a664431ccf721fcd993c647517dea784abac0ca2`.
- Exact candidate source: `b64258460a4636a8d0d2dfa244d16b7d16863b94148238a06d6efff8850c01e0`; executable source `185caba42eb3bf019777f435b38e673959f58771664f55653b31061f4da8fa5f`.
- Required-context SHA-256: `ce569524e4a17e2706cb01f66baee6d573681bb68c1cdc868b1b7920d52715c9`.
- Verification-plan SHA-256: `f5d3090cc733ae4c95b3c02bb96a9ed7d1af4bfbbdf2aee5613c0e282342a393`.
- Independent schema contract: `tests/Support/PasswordSchemaContract.php`, SHA-256 `e6ab918b935f00879d3d9391feb666df614159789430431e20250c4dfce86763`.
- Corrected schema test: SHA-256 `e26c0edd2d08c4740d449ac14787be2d61770008d5e9f791f30236833c94a44a`.
- Bounded fixture corrections: recovery edges SHA-256 `6c2a062f0506a3e73acd69ea6f2b3ff42c07989a58f28dcf3a86fb861c684178`; provision authorization race SHA-256 `09a9b91c6d80d837b5905b98a40c6615996b4bc38cf2838367c40db029e049b0`.
- Verdict: **CHANGES_REQUESTED**.

### Prior findings disposition

1. **Fixed.** `PasswordSchemaContract` independently pins every column’s normalized type, nullability, default, extra, charset and collation; table engine/collation; every index’s name, uniqueness, ordered columns, type and prefix lengths; and the FK name, columns, target, delete rule and update rule. It also owns literal DDL. The schema test compares the complete actual `information_schema` projection with those literals before mutation and restores mutated tables only from the test-owned DDL. The additional truncated unique-token index mutation proves prefix-length sensitivity. No accepted baseline is now derived from production definitions or `SHOW CREATE TABLE`.

2. **Open (partially fixed).** The correction now enumerates all four reachable ordered DDL prefixes: zero tables, events only, events plus requests, and all three tables. It combines an invited row with a pending-password row, requires completion to V40, exact final manifests, normalized waiting states, and stable no-event replay. However, every pre-existing recovery table is empty. The normative promise and the prior required change include completion “без потери фактов”; an implementation may drop and recreate every compatible partial table, then produce the exact final manifest and pass every current assertion. Seed a distinguishable valid row in every table that exists for each prefix—event for prefixes 1–3, request plus its ciphertext/hash/timestamps/state for prefixes 2–3, attempt for prefix 3—snapshot those bytes and auto-increment next values, run the public migration, and require exact preservation afterward and after replay. Also compare the two identity and credential rows, not only their projected activation states, so partial completion cannot silently replace identity facts.

### Bounded fixture corrections

Both bundled corrections are valid and introduce no blocker:

- Passing the explicit `['outbox.dispatch'=>[1]]` registry to `MariaDbJobQueue` keeps the production default closed while enabling only the job family exercised by the focused recovery dispatcher test. The exact-source record `1795521808911787000-c46e5eaff8414fc5b43ecc857ab5e395` is GREEN and the existing network, replay, secret, and no-write assertions remain.
- Raising the `INNODB_LOCK_WAITS` observation interval from 20 ms to 200 ms accounts for MariaDB metadata snapshot refresh without weakening the required observed lock relationship or any final no-write assertion. Record `1795521808919931000-e8657005c80a43caa82ceac8c4a9333e` is exact-source GREEN.

### Fresh RED assessment

Record `1795521808929617000-07fa9be2ef204c08bd635d681affdc3d` is bound to candidate `b64258460a4636a8d0d2dfa244d16b7d16863b94148238a06d6efff8850c01e0`. It reaches the public readiness/apply/canonical migration seams and aggregates 27 expected missing guarantees: additive enum, readiness and apply rejection across eleven semantic mutations, and recovery of all four partial prefixes. This is a strong, credible intended RED. It cannot expose destructive replacement of compatible partial tables because those fixtures contain no recovery facts.

### Pass-2 verdict

**CHANGES_REQUESTED**

The sole remaining blocker is preservation of facts and sequence state in every pre-existing partial table, plus exact identity/credential preservation during resumed projection. Retain the independent manifest, complete prefix enumeration, mutation matrix, and both accepted fixture corrections. Capture fresh exact-source RED and request one bounded schema rereview. The prior main Gate 3 approval remains intact; schema code corrections remain paused.

---

## Complete schema-matrix rereview — pass 3 — 2026-11-24

- Reviewer independence: unchanged; `/root/gate3_review` authored none of the rebuilt schema contract, tests, implementation, or evidence.
- Package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T121409Z-ac9d8eddf7/package.json`, SHA-256 `a57d253efe6b14676a21d5eef174439eb7e8782054ed8cca96ac0d0fde3973fb`.
- Exact candidate source: `67d461c0c9cff7270d5bf83287d30c0a397f4437d01d674f02d8db5ede9c697e`; executable source `41674ce0af1355a3d438635cd1d8efd1ef7274c9be9c9b52cc0db79a7a8fbf6f`.
- Required-context SHA-256: `ce569524e4a17e2706cb01f66baee6d573681bb68c1cdc868b1b7920d52715c9`.
- Verification-plan SHA-256: `f98c7ff8ab450ff61ebebaeaacd1e895d2d82d75b3cf667b94d96fc016c8c34f`.
- Rebuilt schema test SHA-256: `a93615e2952a775a7c481e8c0044da52278bc88a9947e44de3e40180838e2ed0`; independent schema contract remains `e6ab918b935f00879d3d9391feb666df614159789430431e20250c4dfce86763`.
- Rebuild inventory: `openspec/changes/email-first-password-recovery/schema-rebuild.md`, SHA-256 `16755c5452bde203e85e7ba5638796c61f2f17b5690b880b768f4c19e615c8f4`.
- Verdict: **APPROVED**.

### Complete findings disposition

1. **S1 remains fixed.** The independent literal metadata/DDL contract still covers every column, table, index and FK semantic, and the eleven-mutation matrix retains readiness/apply rejection and no-repair assertions.

2. **S2 fixed.** Each ordered prefix now contains complete identity fixtures: invited `601`, pending-password `602`, and active `603` with generation 7 and a valid preserved Argon2id credential. Prefixes with events seed exact event ID 23 and next value 800. Prefixes with requests seed a request bound to user 603/generation 7 with a real XChaCha20-Poly1305 ciphertext, SHA-256 token digest, key ID, normalized email, exact 30-minute expiry, and transient delivery state. The full prefix also seeds accepted attempt ID 31 and next value 900. Before public completion the test snapshots all identity rows, credential rows, recovery facts and applicable next values; afterward it permits only invited-to-pending-password projection and requires every other byte and sequence value unchanged. The same comparisons are repeated after canonical no-op replay. A drop/recreate, rewrite, credential replacement, or sequence reset can no longer pass.

### Fresh RED assessment

Record `1795522360814090000-3d20f58b5ff3444bb8a3b42637e06d42` is bound to exact candidate `67d461c0c9cff7270d5bf83287d30c0a397f4437d01d674f02d8db5ede9c697e`. It reaches the complete independent manifest, eleven mutation cases, and all four populated prefix fixtures, then aggregates the expected 27 missing guarantees. The current implementation fails before any destructive production action; all data is isolated test data. This is a credible complete intended RED.

### Schema verdict

**APPROVED**

The bounded schema test matrix is approved for the exact source above. Schema implementation may resume under its separate executor package. This does not approve implementation code, final source, CI, publication, merge, deployment, or live email. The prior main Gate 3 approval remains intact.
