# PRODUCTION-MAKE-ENV-001 — independent Gate 3 review

- Date: `2026-11-16`
- Reviewer: separately tasked agent `/root/independent_review`
- Reviewer independence: did not author the contract, OpenSpec artifacts, tests, or implementation
- Prepared reviewer package: `20261116T070310Z-b825c6e74b`, SHA-256 `48e1475fea89f17eb3f39c585d6af2d0764772a8a54393274fd54eadba54a1fb`
- Contract SHA-256: `9c5100c063a0ea068f59d8e3e9c6b8cb95e7c0d915dbce3560e8bfd2a9b128dc`
- Test SHA-256 values: env `1dc747f18359887b7481633fb4250ea417494d01ee039ec197a8a7eb9f04c1bb`; lifecycle `d3162841598265452c37b4c16268fd94e46ffc8c2cd70abec35883997c86b2e4`; live `f6e8bbd35b6ec4d6cdb145fa7f518fa3d4c59e5a42cd406b8c2f6d9f53c42f81`; dependencies `c56c08bd2409e5402355b266524becbdcb096ded906f9e79b0f3163ae785ab8d`
- RED evidence: four prepared `INTENDED_RED` records inspected; each reaches the missing public production seam
- Verdict: **CHANGES_REQUESTED**

## Findings

### T1 — BLOCKING: restore does not require a new database secret

`tests/Deployment/production_make_live_001_test.py:113-118` constructs the restore target with `td=dict(d)` and changes only project, HTTP, and state paths. It therefore supplies the source `FMONITOR_DB_PASSWORD` and `FMONITOR_MIGRATION_DB_PASSWORD` to the target, then accepts a successful restore without proving that the target receives newly generated database credentials. This leaves A09's explicit requirement to regenerate the target DB secret untested and could approve an implementation that clones source database credentials.

Create distinct target DB and migration secrets before restore, assert restore succeeds with them, and inspect the restored runtime/receipt sufficiently to prove the target uses those credentials while the original session/identity keys and owner login remain valid.

### T2 — BLOCKING: concurrency and ownership isolation have no executable oracle

A02 requires per-project serialization and zero mutation on lock contention; A03/A05 require foreign or partial projects never be adopted and lifecycle commands to affect only the recorded project. The tests only exercise sequential replay (`production_make_live_001_test.py:93`) and invalid configuration/symlink rejection. They never overlap two commands, present an existing valid-looking foreign Compose project/volume, or prove that a command cannot touch a neighboring production project.

Add a deterministic overlap test through the public `manage`/Make seam that holds the first operation after lock acquisition, proves the contender fails without Docker/resource mutation, and verifies a separately created neighboring/foreign project remains byte-for-state unchanged. Cover partial first-install identity rather than only an invalid `fm2-local-*` name.

### T3 — BLOCKING: backup and update lifecycle assertions cover only failure fragments

`production_make_live_001_test.py:98-109` backs up an already stopped deployment, so it does not verify A07's required capture/stop/confirm/resume behavior for a running writer set or the explicit stopped/resumed outcome after backup failure. Lines 134-151 cover a migration failure, but there is no successful changed-image update, atomic installed-identity transition, old backup/identity preservation, unchanged secrets, same-source rejection/replay, or recovery from the in-progress/failed receipt. Consequently an implementation could pass while stopping the wrong services, failing to resume the prior set, or never completing a valid update.

Exercise backup once with a mixed running service set and once with an injected recovery failure; assert exact service restoration and stated failure outcome. Add a successful uniquely labelled image update and assert pre-interruption acquisition, backup, migration ordering, new receipt/health, preserved secrets/data, and safe same-source replay. Retain the existing failed-migration stopped-writer assertions.

### T4 — BLOCKING: dependency fallback misses required failure and primary-path controls

The live test predownloads archives itself (`production_make_live_001_test.py:54-60`) and tests only an offline installation from valid cache after one forced Composer failure. `production_dependencies_001_test.py:14-35` validates manifest shape but never runs the installer against a corrupt cached archive, missing archive download, TLS/hash mismatch, platform failure, partial vendor publication, or a successful primary Composer path that proves fallback was not invoked. These are explicit A11/A12 acceptance requirements.

Add bounded installer-level cases for: primary success with an unreachable/failing fallback sentinel; a missing cache populated by the expected exact HTTPS URL; corrupt existing cache rejected without replacement; downloaded SHA/size mismatch; offline Composer platform/plugin failure; and preservation or atomic replacement of an existing vendor directory on failure and idempotent repeat. Keep credentials out of captured output.

### T5 — MAJOR: strict environment and mode matrices are materially incomplete

`production_make_env_001_test.py` proves one quoted adversarial value, missing/inherited value rejection, several semantic invalid values, duplicate/unknown keys, mode `0644`, and symlink rejection. It does not cover paired single/double quoted parsing independently, unquoted `#`, `=` and spaces, CRLF/control characters/invalid identifiers/placeholders, directory/FIFO targets, no-clobber publication under contention, or an existing private non-template file. No test covers `build` and `pull` image modes, digest/source-label mismatch before DB mutation, local archive verification, or unknown installed-image identity. A01/A02 allow no ambiguity at these boundaries.

Extend the no-Docker tests with a table of exact accepted literal bytes and fail-closed input/file-type cases, including concurrent `env-init` publication. Add fake-Docker boundary traces for all three image modes that prove identity mismatch and invalid archive/digest fail before DB commands, while the valid command ordering reaches the expected acquisition seam.

## Confirmed properties

The prepared evidence contains four real, source-bound intended RED executions rather than setup-only claims. The tests already establish useful oracles for secret non-printing, shell-literal non-execution, inherited-env exclusion, disabled integration configuration, Make target presence, loopback/logging static compose constraints, clean Docker install and owner login, deploy/start replay data preservation, schema-v39 backup, source collision rejection, corrupt-backup no-container behavior, failed-update writer shutdown, exact lock/manifest correspondence, real offline Composer plugin/autoload/platform success, and release scanning for the bootstrap secret. The existing full-CI browser test may remain the PDF/product-flow oracle; it need not be duplicated here.

The live test generates unique project names and guards destructive cleanup with the random suffix before `down --volumes`. This review did not run the owner-prohibited full local suite and does not claim implementation GREEN or corporate-server behavior.

## Gate consequence

Gate 3 is **CHANGES_REQUESTED**. Address T1-T4 before implementation and preserve fresh exact RED records for changed tests. T5 may be split into bounded no-Docker cases, but its fail-closed configuration and image-identity boundaries must be executable before this contract is approved for implementation.

---

## Gate 3 rereview v2 — corrected candidate

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`, unchanged and independent
- Prior candidate source: `676f9fb187f46e07d725f68e8039421edec47d48faa70139e895d06227d6f24e`
- Reviewed candidate source: `34289bd412eb20b8cedf134d14509cf1b966de8139719de13b8c6b99289e5470`
- Prepared package: `20261116T071118Z-95c3013c0d`
- Corrected test SHA-256 values: env `1d58154b7c855823a754c746e3ea9d7dd78812284edad24c3186adca0f3b5986`; lifecycle `b42aa51770959c4e0dcfae4c16ab198f350c78161a7e4ef2edba48479bb4f110`; live `914e2c5875548bf5a80eb67bb803f6123d827364c3c5c0ddce2b92b31b42aa81`; dependencies unchanged `c56c08bd2409e5402355b266524becbdcb096ded906f9e79b0f3163ae785ab8d`
- Evidence: four fresh `INTENDED_RED` records bind the exact corrected candidate and command blobs
- Verdict: **CHANGES_REQUESTED**

### Resolved dispositions

- **T1 resolved.** `production_make_live_001_test.py:194-203` gives the restore target distinct runtime and migration DB passwords, proves the runtime received the target password by hash, preserves the source identity key, owner authentication, and durable evidence.
- **T2 partially resolved.** Lines 125-138 now reject an existing foreign production volume without changing its inspection state and prove exact retry after an interrupted initial start. Lines 163-178 establish a deterministic held-backup overlap and require the contending `start` to fail.
- **T3 partially resolved.** Lines 152-162 cover running-backup restoration and an injected backup error with an explicit outcome. Lines 219-234 exercise a successful changed-image update and prove new receipt identity, unchanged configured secrets, retained data/login, source-project isolation, health, and replay. The failed-migration stopped-writer case remains.
- **T4 resolved.** Lines 53-114 cover real cached offline fallback and repeat, retained vendor on corrupt cache and bad download, platform failure, primary success without fallback transport, and exact allowlisted URL download through a rejecting transport fixture.
- **T5 resolved.** The env test now covers all literal forms, malformed identifiers/quotes, control bytes, CRLF, placeholders, directory/FIFO/private existing targets, and concurrent no-clobber initialization. The lifecycle test covers build/pull/local acquisition, wrong source label before DB effects, and selected archive tamper before load.

### R1 — BLOCKING: the contender can mutate the project and the test still passes

`tests/Deployment/production_make_live_001_test.py:172` captures container state immediately before the contending `start`, but that `before` value is never asserted. The test releases the held backup at line 176 and only checks the final running set after the first backup has resumed services. An implementation that correctly rejects the contender only after it has started or recreated services can therefore be restored to the expected final set by the first operation and pass. This does not prove A02's requirement that lock contention performs no resource mutation.

While the first backup remains held, capture a complete stable project fingerprint before the contender, run the contender, and compare the same fingerprint before releasing the first operation. Include container IDs/status and volume/network identity; a Docker spy or event capture may be used to prove no mutating command occurred. Keep the final restoration assertion.

### R2 — BLOCKING: successful update still does not prove mandatory backup and stopped-writer ordering

`tests/Deployment/production_make_live_001_test.py:219-234` proves the visible result of a successful changed-image update, but it does not assert that update produced and retained a pre-migration backup of the old state/image, nor that writer services stayed stopped across migration and receipt publication. An implementation that migrates live and omits the A08 backup can satisfy every current assertion. The separate failed-migration case proves writers are stopped after failure, not during a successful migration.

Instrument the successful update at the public Docker boundary. Require a completed old-source backup before the migration command, prove web/php and enabled jobs are stopped when migration runs, and prove writers restart only after migration/check succeeds. Inspect the retained backup metadata for old source/image identity and current data, then retain the existing new-receipt/data/secrets/source-isolation assertions.

### Rereview consequence

Gate 3 remains **CHANGES_REQUESTED** for R1 and R2 only. The original T1, T4, and T5 findings are closed; T2 and T3 are narrowed to the two exact missing oracles above. Preserve fresh source-bound RED evidence after those test changes. No implementation or full local suite is required for the next rereview.

---

## Gate 3 rereview v3 — final preimplementation matrix

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`, independent of specification, tests, and future implementation
- Prior candidate source: `34289bd412eb20b8cedf134d14509cf1b966de8139719de13b8c6b99289e5470`
- Reviewed candidate source: `71b9767bec2449382948fc0f0dbdbba1b0f6ffa0918077b0b5abafb4c043888f`
- Prepared package: `20261116T071453Z-4b7bfe7d19`, SHA-256 `33e31ec8b695ea7e08d26c57a13006f1548a054b15b8beaa99fe8218b14455aa`
- Corrected live test SHA-256: `df63083f589c258bb5eb81e2b2484e5125ba292daec796d7f8da90e6d19fe6c8`
- Acceptance matrix SHA-256: `207d7df37da63151f9354dcd2b3bb336a8b9bab25781b31ed88f53467ba21177`
- Evidence: all four prepared commands have fresh exact-source `INTENDED_RED` records at candidate `71b9767b`; unchanged tests remain bound to the same candidate and executable source
- Verdict: **APPROVED**

### R1 disposition — resolved

`tests/Deployment/production_make_live_001_test.py:172-180` now fingerprints container IDs and `.State` before and after the rejected contender while the first backup remains held. The contender uses a dedicated Docker wrapper, and the test rejects every mutating Docker operation (`run`, `up`, `start`, `stop`, `restart`, `down`, `rm`, `load`, `pull`, or `build`) before releasing the first operation. The prior false-GREEN path, in which the contender mutated resources and the first operation later restored the final state, is closed. Final prior-service restoration remains asserted after release.

### R2 disposition — resolved

`tests/Deployment/production_make_live_001_test.py:225-262` wraps the successful update's real Docker boundary. At migration invocation the observer requires no web/php/worker/scheduler writer to be running and requires a newly published canonical manifest naming the old source. The retained trace then enforces new-image acquisition before recovery backup and backup before migration. The test also retains the new-source receipt, health, replay, unchanged secrets, durable evidence/login, and source-project isolation assertions. This closes the omitted-backup and live-migration false-GREEN paths.

### Complete matrix assessment

The rebuilt `docs/operations/production-make-env-delivery.md` maps A01-A13 to the four new public-seam tests and the existing full-CI browser oracle without claiming implementation or CI GREEN. The mapping agrees with the reviewed assertions and keeps live deployment, real mail, corporate TLS/DNS, and merge outside this Gate 3 verdict. The intended REDs reach the absent production seam and are not setup failures.

No further preimplementation findings remain. The prior T1-T5 and R1-R2 findings stay in this record as history and are superseded only for the exact candidate above.

### Approval boundary

Gate 3 is **APPROVED** for minimal implementation against these frozen contract and test bytes. This authorizes neither changes to the expectations nor production deployment, merge, final review, or a claim of GREEN. Implementation must remain separately authored; focused checks, independent final review, and the planner-selected exact-source full GitHub CI remain required. The owner-prohibited full local suite was not run.

---

## Gate 3 fixture-delta review — Yii bootstrap and early cleanup

- Date: `2026-11-16`
- Baseline: approved Gate 3 test candidate described above
- Reviewed file: `tests/Deployment/production_make_live_001_test.py`, SHA-256 `cc5944776612afd2413de6b7b96a80ef57640ec7b50b6ecf9442b33bdcd25e80`
- Scope: two fixture-only changes; no contract or expected product behavior change
- Verdict: **APPROVED**

The dependency assertion now loads `vendor/autoload.php` and then the canonical `vendor/yiisoft/yii2/Yii.php` bootstrap before checking `Yii` and `TCPDF`. This matches `tools/delivery/setup-composer.sh:36-43`, `deploy/dependencies/verify.php`, and the repository's canonical integration tests: Yii's global bootstrap class is deliberately loaded from `Yii.php`, while Composer supplies the dependency graph. The correction removes a fixture-caused false failure without weakening the offline Composer/plugin/autoload/platform assertions.

The new `atexit` cleanup removes only tags recorded in `extra_images` and `owned_image`. Every such tag is generated with the test's random token; a caller-supplied `FMONITOR_PRODUCTION_TEST_IMAGE` is never assigned to `owned_image`. Cleanup uses `docker image rm` without force, suppresses only cleanup output, and does not target containers, volumes, projects, shared tag patterns, or digests. A duplicate best-effort removal after the normal `finally` is harmless and closes only the early dependency-phase failure leak.

No findings. The prior Gate 3 approval remains valid for this corrected test fixture. This delta approval is not final implementation review and does not claim the live or full suite has run.

---

## Gate 3 A09/A10 delta — canonical read-only recovery preflight

- Date: `2026-11-16`
- Candidate source: `794f1a194a99ac57eb0d8ab4c6970af7ec16a689eb1637a42a1f68f3c38ed94b`
- Prepared package: `20261116T073617Z-ef2900b373`, SHA-256 `7881a7da2e01d94ba574ecfe7fb22dd86960233950ded34243da114e6ab8da2b`
- Contract SHA-256: `8cc22dcdbf8232f19d319cda32289651a0249a81be3f51626e78008c52697f48`
- Canonical preflight test SHA-256: `2ed1246a3a104f00c683b30404a49e048be7bad77acf83c500f83978e6bf0c73`
- Live acceptance SHA-256: `9bb47980d881bc92a228996dc5f8ba5342d40958dfcfba45936568b1a3a382ed`
- Exact RED: `php tests/Runtime/production_recovery_preflight_001_test.php` reaches the missing public `verify` command and fails its valid-bundle expectation; package record is source-bound to this candidate
- Verdict: **APPROVED**

The A09 clarification makes the required boundary explicit: the public `verify` command must reuse RuntimeRecovery's canonical preflight and finish without a database connection, extraction, or write. It preserves the existing restore semantics and closes the previously reported ordering gap rather than adding unrelated behavior.

`production_recovery_preflight_001_test.php` supplies a valid canonical v39 fixture with an unreachable database endpoint, so success proves verification does not connect to DB. It then independently rejects source mismatch; wrong schema, table catalogue, auto-increment, size, deferred set, and top-level shape; and traversal, symlink, hardlink, duplicate-entry, and unsafe-mode tar archives after recomputing their payload hashes. It also asserts source manifest stability and absence of target state/secrets. The tar fixture produces structurally valid checksummed ustar inputs, so these are semantic preflight cases rather than malformed-test setup.

The live acceptance adds the missing host-orchestration proof: a schema-v38 manifest and outer private-manifest hash are made self-consistent, restore must fail, and the unique target must have no containers, database/state/secrets volumes, or default network. This prevents the hash-only helper from satisfying A09 while deferring canonical rejection until after `dc up db`.

The A10 increment runs the generated release Makefile's `env-init` and checks local image mode, exact source/image defaults, archive existence, and archive digest before deleting the generated private `.env` and retaining the existing package secret-exclusion checks. It tests the operator artifact without weakening release isolation.

No findings. Gate 3 remains **APPROVED** for the frozen expectations, including this A09/A10 delta. Minimal separate implementation may expose the shared read-only canonical preflight and place it before target resource creation. Final implementation review, focused GREEN, and exact-source CI remain outside this approval.

---

## Gate 3 correction-oracle delta — D2, D6, D7, D8

- Date: `2026-11-16`
- Implementation baseline under correction: `3aea5fee63338a0b35986e9e6c8f022de8006b6a`
- Environment test SHA-256: `74048085b861e97010a98ee115cba31ec7e11d3323ed871ca5121031db6cf5b4`
- Lifecycle test SHA-256: `c87451d6bddd00dcc06b20dc9b40f728f9118f0143cc95873631a1540a6ed499`
- Live test SHA-256: `243d8d630c0d848fe66aeb647ddd8b54c7a77ade557fd03cc1c0c3d54492ad55`
- Retained RED index SHA-256: `4d83f72ea25a05f4b9ddbf0601a173cfbb0c2c269fd7b5774df7fc53fc69029b`
- Verdict: **APPROVED** for the test delta only

The environment test now rejects an existing mode-`0755` backup directory, a symlink to it, and a regular file used as the backup root. An absent private path remains accepted by the existing valid configuration. This directly covers D6's fail-closed type/mode boundary before Docker or writer effects.

The lifecycle fake now models that a build-mode image does not exist before the build command. Its Git fixture exposes an untracked `app/` source file unless the implementation uses the old `--untracked-files=no` escape. The new case requires rejection before any labelled Docker build. This is a precise D2 oracle and retains build/pull/local acquisition and wrong-label assertions.

The live release increment requires a canonical database image ID plus compatible explicit application/database platforms. It exercises the generated release `env-init`, then changes the image choice and proves a repeated invocation preserves every byte of the private operator configuration. These assertions cover D7 and the pre-existing A01 repeat/no-clobber rule without introducing registry publication or multi-architecture requirements.

The update publication fixture marks the point at which new web/php writers are started, then makes `mv` fail. It requires nonzero update, all writers stopped, and `start` blocked by unresolved state. Backup and the earlier updating receipt remain able to publish before the injection. The case therefore targets D8's final receipt failure rather than an earlier setup failure; failure to publish a secondary failed marker is tolerated as long as the durable updating state blocks restart.

Two real reconstructed-baseline REDs were inspected. The environment test fails exactly because `3aea5fee` accepts the unsafe backup root (log SHA-256 `5224b9f068540a371958e7c1aab8fff70f7612dc3010c6a0c54fbecbc0428264`). The lifecycle test fails exactly because the old ignored-untracked check reaches Docker build (log SHA-256 `0842426d4e798f1dd9c9b7ed43a12d8707fd5af0fce3993bbb8f4fefa0f60408`). The Docker-dependent D7/D8 cases remain pending the corrected full live run and are not claimed RED or GREEN here.

No findings in this delta. All prior expectations remain. This approval does not approve the correction implementation, close D1-D9, or replace final source-bound live evidence and code rereview.

---

## Final fixture-delta and GREEN evidence disposition

- Date: `2026-11-16`
- Reviewed HEAD: `cbcc04dbb4283db5c9e1e4161b03330cc97523d9`
- Exact candidate source: `82720bf8c7482679f193dc1d7a2cd209127a0f6054b72c172ee806dbe1667e4a`
- Reviewer package: `20261116T090737Z-9cd6a4c843`
- Verdict: **APPROVED**

The narrow fixture corrections reviewed in principle during implementation are formally approved on the frozen source:

- canonical Yii bootstrap is loaded after Composer autoload;
- cleanup targets only random test-owned image tags;
- the expected application image ID distinguishes same-label wrong images from the exact release image;
- `urllib.parse` is module-scoped, avoiding a function-local `urllib` binding;
- backup failure/hold injection matches adjacent Docker argv structurally and proves boundary reach with markers;
- stopped-backup acceptance remains unchanged while Bash3 empty-array behavior is corrected in production code.

No assertion was weakened. Earlier fixture failures (`urllib` scope and recovery command matching) and production/runtime failures (Bash3 empty-array behavior, nested read-only mount, and Docker Desktop bind ownership) remain recorded in delivery history as failures, not GREEN.

The five exact-source GREEN records are:

- environment `1794819783571220000-3499668a43914939ac0e2efc9666d120`, command blob `74048085b861e97010a98ee115cba31ec7e11d3323ed871ca5121031db6cf5b4`
- lifecycle `1794819783566494000-baf7729467d542009c863833d460b6f2`, command blob `150ff774dd4e09c4c6f62e155e285b02f6b09a56c65ea13969e954b6c4de4420`
- dependencies `1794819783578518000-87fa83b8ef77455c9d6e9ee832d854b9`, command blob `c56c08bd2409e5402355b266524becbdcb096ded906f9e79b0f3163ae785ab8d`
- canonical recovery preflight `1794819783582351000-7e9b075a67fd49cc9a4fd00ceecaddbc`, command blob `2ed1246a3a104f00c683b30404a49e048be7bad77acf83c500f83978e6bf0c73`
- complete live acceptance `1794819773831147000-c99ca0ae0d4742aea7f94937e71fe9b9`, command blob `0f6f09d48aba2e89d7499f37af4408ebffdf6e9cfa76c2378536a6ee4425c29f`

Gate 3 expectations and all correction oracles are satisfied by the frozen implementation. This tests verdict supports final code approval but does not claim full CI, merge readiness, or live corporate deployment.
