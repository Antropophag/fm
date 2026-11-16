# Independent Gate 3 test review — PRODUCTION-HANDOFF-001

- Date: 2026-11-16
- Reviewer: independently tasked agent `/root/reviewer`; authored none of the reviewed specification, tests, fixture, or production code.
- Fixed base: `42df3e1a93a9ac619057bb35ff8b7f54d714681d`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T174313Z-02c85cd3b0/package.json`
- Captured source: `/Users/antropophag/.local/share/fmonitor-2/simplify-production-handoff/gate3-source/source.patch`, SHA-256 `a852a7717a27a651978445eec71acb15528e7d4c3e39c10d4524d04009215b1c`
- Verification plan SHA-256: `ad4207a64ec0bf62ff613561180ccbcdaa374383d97514f06fc01c2414359117`
- Verdict: **CHANGES_REQUESTED**

This is a preimplementation review of the root-authored contract and executable
RED tests. The captured patch contains the contract/tests and no production
implementation; its failures are therefore expected and do not imply a Gate 4
assessment.

## Findings

### G3-1 — HIGH — the integration test permits fail-fast qualification

`specs/PRODUCTION-HANDOFF-001.md` H04 requires qualification to collect all
configured integration outcomes. In
`tests/Deployment/production_handoff_001_test.py:119-132`, each failure scenario
asserts only the selected stage's `FAILED` line. An implementation may stop at
ERP failure without reporting workforce/documents, or stop at workforce failure
without reporting documents, and still pass every scenario.

Correction: for each independently failed stage, require one outcome line for
every configured stage and its exact expected `OK`/`FAILED` value. Keep the
existing no-writer and secret-suppression assertions.

### G3-2 — HIGH — the declared public ERP command is not executed

H05 defines the public seam as
`php bin/yii erp-equipment-facts-sync/probe --interactive=0`, including exact
success/failure exit behavior and safe output. The PHP test directly invokes
`NativeErpEquipmentFactsDelivery::probe()` and then only searches controller
source for `actionProbe` and `->probe()` at lines 26-27. The Docker fixture
simulates this command rather than running the controller. A controller that
returns success on a failed/malformed result, exposes exception details, or
composes a fake/wrong transport can satisfy the current tests.

Correction: add a deterministic executable controller/console witness around
the constructor-injected adapter for success, malformed response, and thrown
exception. Assert command exit status, safe bounded status output, absence of
the exception/credential canaries, and production composition with the real SQL
Server transport. No live ERP credentials are required.

### G3-3 — MEDIUM — two explicit observable/recovery facts are only partially asserted

H04 requires jobs-disabled qualification to report `SKIPPED`; the test checks
only absence of sync calls. H03 requires the backup's effective recovery
identity; the test checks `restore.env` but does not inspect the fixture-generated
`runtime/manifest.json`. Silence on jobs-disabled and a stale runtime manifest
can therefore pass.

Correction: require the three jobs-disabled `SKIPPED` lines, and assert the
backup runtime manifest contains the same installed source, image reference,
and image ID independently asserted in `restore.env`.

## Assessment

The remaining matrix is well chosen and bounded. The Make/Git test uses a real
temporary repository and an external Docker process fixture; it covers automatic
clean HEAD selection, obsolete env metadata, installed-versus-candidate
operations, repeat behavior, changed-source backup ordering, failed-update
blocking, optional documents, dirty-source rejection, secret suppression, and
no legacy/scheduler call. Existing adjacent tests retain pull/local/archive
identity and production env/lifecycle coverage. The callable delivery test has
independent expected SQL, parameters, bounds, read-only option, malformed shapes,
empty-catalogue success, and exception redaction. The tests are isolated from
Docker, databases, networks, live credentials, and production state.

## Verification evidence

The captured source was restored over the fixed base and the two focused tests
were run. The deployment test produced six intended product failures: obsolete
source metadata still rejected, integration failures accepted, config-only
update rejected, and Git-B update/failure behavior absent. The ERP test failed
at the intended missing public `probe()` assertion. `git diff --check` and
`bash -n deploy/production/manage deploy/production/recovery.sh` passed.

Reviewed artifact hashes:

```text
46d182c33ba46ce40f8109b03d4267da9385bb62a7dbb742199606858d1481d0  specs/PRODUCTION-HANDOFF-001.md
4c4c1e470018c5be62941f8dc5259e1f6dfb87fbd85c9899ec0c27ae90e3e014  tests/Deployment/production_handoff_001_test.py
222f8920739bf4aabf5fb2c7da00b424ab894901ab9d1c21ecc956af0cb01779  tests/InstallationProcess/erp_equipment_facts_probe_001_test.php
02b34761a70b73cf9f440ef123960318b58beed07afd644ef8e95da02dd31628  tests/Support/production_handoff_docker.py
```

## Gate decision

Gate 3 is **CHANGES_REQUESTED**. Correct G3-1 through G3-3, capture fresh
intended RED bound to the corrected source, and submit the complete test delta
for independent rereview. Gate 4 remains blocked; implementation, CI, PR, merge,
and deployment remain `UNKNOWN`.

---

## Corrected Gate 3 rereview — 2026-11-16

- Corrected package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T174815Z-a25677adc8/package.json`
- Corrected exact source: `f8728cce7311a78c702a4738616c96c1100ee54bafc9ee43af8bd5f3f38691d4`
- Corrected snapshot: `/Users/antropophag/.local/share/fmonitor-2/simplify-production-handoff/gate3-source-v2/source.patch`, SHA-256 `19a5d97b11a881c328afc817d8e396c2febb034a3bb7f6cb62e2fca65545d04e`
- Corrected verification-plan SHA-256: `ecc26d4a9d86bb87926e65601dd402299208a49eb18e42eaf0d73590d527c76b`
- Verdict: **APPROVED**

Reviewer independence is unchanged. The corrected snapshot contains only the
root-authored contract/test candidate; no production implementation was reviewed
or approved here.

### Finding disposition

- **G3-1 fixed.** Every independent ERP, workforce, and documents failure now
  requires all three configured outcome lines, with the selected stage `FAILED`
  and the other two `OK`. The repair run requires all three `OK`. A fail-fast
  implementation can no longer satisfy the test.
- **G3-2 fixed.** The new subprocess fixture executes unchanged bytes of the real
  `ErpEquipmentFactsSyncController::actionProbe()` while binding deterministic
  stand-ins at its production configuration and SQL-transport class boundaries.
  Success, malformed response, connection failure, and query failure assert the
  real controller exit, safe JSON status, empty stderr, credential/error
  redaction, exact ERP config, one bounded read-only `SELECT 1`, and composition
  through the production transport FQCN into the real native delivery. It uses no
  live service, credential, database, controller fork, or test flag.
- **G3-3 fixed.** Jobs-disabled deploy requires exact `SKIPPED` output for ERP,
  workforce, and documents. Backup verification independently compares the
  runtime manifest's source, image reference, and image ID with the installed
  identity and private `restore.env`.

No new finding was identified. The helper creates a private random temporary
tree, copies only the unchanged controller source needed to preserve its normal
relative Composer include, captures both process streams, and removes its owned
artifacts in `finally`. Expected query/config/output values are independently
declared by the test. The expanded assertions remain deterministic and bounded.

### Corrected RED evidence

The prepared package contains two accepted `INTENDED_RED` records bound to exact
source `f8728cce7311a78c702a4738616c96c1100ee54bafc9ee43af8bd5f3f38691d4`:

```text
python3 tests/Deployment/production_handoff_001_test.py
record: 1794851223716377000-b4327b7b63d24cebae26a394a80fe2e9.json
command blob: 984930a351abc6e4514ad3fe96e4db2d046f80b6bde85654b19eb1530fea4b2c

php tests/InstallationProcess/erp_equipment_facts_probe_001_test.php
record: 1794851238641453000-f0c3043f3d8547e797b119e0f4bb1788.json
command blob: cb95ef1f546d05e09ca3f5c69c1d25b8f0a4c5192942ecb65b3c2a23b0003dec
```

Independent syntax checks passed for both PHP files and Python compilation
passed for the deployment test and Docker fixture. The RED failures remain
sensitive to missing production behavior rather than fixture setup.

### Rereview decision

Gate 3 is **APPROVED** for the corrected exact source above. Gate 4 may proceed
against this reviewed contract/test package. Any expectation or test change
requires fresh source binding and independent delta review. Production GREEN,
final review, CI, PR, merge, and deployment remain `UNKNOWN`.

---

## Retrospective test-correction review — malformed rows and recovery identity

- Date: 2026-11-16
- Review scope: test-only delta after Gate 4 exposed an uncaught adapter `TypeError`, plus completion of the already-contracted backup identity oracle and specification supersession pointers.
- Verdict: **APPROVED**

This is explicitly retrospective for the malformed-row correction. It does not
rewrite the preceding preimplementation approval or represent the new scalar
cases as original RED evidence.

The ERP probe matrix now includes `[1]` and `['invalid']`. These values are valid
transport return types at the callable boundary but invalid response rows under
H05, so the required result is the existing safe `failed` receipt. The retained
RED log demonstrates that `[1]` previously escaped as an actual `array_keys()`
`TypeError`, rather than failing for fixture setup. The expectation is derived
from H05's malformed-response fail-closed rule and does not prescribe the later
`is_array` implementation correction. The focused PHP test now passes with the
correction present.

The deployment test additionally requires the resolved old application
platform, database image ID, and database platform in private `restore.env`.
Together with the already-approved source, application image reference/ID, and
runtime manifest checks, this closes H03's effective installed recovery identity
without adding behavior. Values come from independent fixture image inspection,
not from the implementation's rewrite logic.

The pointers in `PRODUCTION-MAKE-ENV-001` and
`PRODUCTION-MAKE-QUICKSTART-001` accurately narrow supersession to source-build
version selection and initial qualification. They preserve pinned pull/local,
release, recovery, identity, secrecy, and adjacent guarantees. No normative
expectation was dropped.

Reviewed hashes:

```text
fcb260eaccdfacafe0bf177e58ed22301cee325d0649289152f7f106b4a1fd03  tests/InstallationProcess/erp_equipment_facts_probe_001_test.php
b722b055cde21e98e9fa798cd6496c774cfcbe3ae1a0ef30f33a8174d200a737  tests/Deployment/production_handoff_001_test.py
764c19e75169516bc5bed05993df9190b4a6dbc4112edd1ba33683a7af3ba065  specs/PRODUCTION-MAKE-ENV-001.md
9c974a1d0d6e5a90d6abfdfd6fb0febbf4ea37a70c3424dfa8f5cf214a59b21a  specs/PRODUCTION-MAKE-QUICKSTART-001.md
2a6b2191ce9863ccc49752557fd594777a5c2a3f689dc631b0f80cf4307457a8  probe-malformed-red.log
```

`php tests/InstallationProcess/erp_equipment_facts_probe_001_test.php` passed,
PHP/Python syntax checks passed, and `git diff --check` passed. The deployment
test's implementation GREEN and the complete candidate remain subject to the
forthcoming exact-source final review; this delta approval does not pre-approve
production code or CI.

---

## Gate 3 final-correction test delta — F1 through F3

- Date: 2026-11-16
- Scope: root-authored regression delta for final-review findings F1-F3; production source remained at the rejected final snapshot.
- Verdict: **APPROVED**

The five scenarios close the complete returned matrix without changing the
contract or an earlier expectation:

- Update and release use independent fresh installations, replace the mutable DB
  tag's observed ID, and require rejection before build/pull/save, backup/runtime
  run, writer stop/start, migration, or receipt change. Immutable DB-ID inspection
  support in the external Docker fixture prevents release from passing because of
  an unrelated fixture limitation.
- Build-to-local update compares the old receipt's source, application reference,
  application ID/platform, database reference/ID/platform, and the runtime
  manifest's source/application identity. This is sensitive to partial recovery
  metadata rewriting and preserves unchanged env bytes.
- Injected `awk` failure is owned entirely by the test PATH wrapper. Manual backup
  must remove its stage, restore the exact prior writer set, keep the installed
  receipt, and report `resumed`. Update backup must leave only DB running, publish
  neither stage nor bundle, preserve the installed receipt, avoid migration, and
  report `stopped`. A correction that always resumes or always leaves stopped
  cannot pass both cases.

The retained RED evidence is behavior-sensitive. Four scenarios fail against the
rejected production source in `final-correction-red-v2.log`: update accepts DB
drift, build-to-local emits stale source metadata, manual rewrite failure strands
writers, and update rewrite failure leaves a stage. After correcting the fixture's
immutable-ID support, `release-drift-red.log` independently shows release reaching
`docker save` with the drifted DB ID. All five failures occur at the intended
contract assertion.

Reviewed hashes:

```text
86ba8ff7f85c1ea76d305fda30943af0ea2537a6e28b4bbcc90ff6fdeebf7a21  tests/Deployment/production_handoff_001_test.py
574e58ed7216eeb372b9d7f7ac66eff73213eaeded1a1ab9281a8cd6f038b29a  tests/Support/production_handoff_docker.py
50b49504835beb6d386c27fe81c1b0ac63d70876f0f1cf6407c48bf04fb70d36  final-correction-red-v2.log
092c9abec8d6eb65c416a334b53276a9caeb74090103a226b8931605510b99e1  release-drift-red.log
```

Python compilation and `git diff --check` passed. Gate 3 approves this bounded
test delta for implementation. The earlier final-review findings remain open
until corrected production, complete focused GREEN, a new frozen source, and
independent final delta review.

---

## Gate 3 pinned-recovery mode delta

- Date: 2026-11-16
- Scope: regression for the F2 correction's installed-mode distinction.
- Verdict: **APPROVED**

The added public Make/Docker scenarios install pinned `pull` and `local`
handoffs independently, create a backup, and require private recovery config to
retain the installed mode, pinned image reference, exact source, and unchanged
operator env. The pull fixture crosses an external `docker pull` process boundary
and exposes a deterministic source-labelled immutable image; local setup creates
the same owned image without a pull. These cases distinguish the old installed
mode from the update candidate mode already covered by the build-to-local test.

The retained run is correctly behavior-sensitive: pinned local remains GREEN,
while pinned pull fails because the rejected correction rewrites it to `local`.
This prevents fixing automatic-build recovery by removing the inherited ability
of a pinned pull restore to fetch its digest image on a new target.

```text
9ec59b29a113105ca48f3d8a35260356c63c5af181c0a6867ab3e818ea7656e5  tests/Deployment/production_handoff_001_test.py
58151d9b1d1deda756c474ace11416c039d0548f97869bfe6ce7b38115f2345e  tests/Support/production_handoff_docker.py
b3baa0d28671d600869dea095bf0e9191f5bc26a201ec30065359f9cbae4c671  pinned-recovery-red.log
```

Python compilation and `git diff --check` passed. Gate 3 approves this test delta
for the narrow old-mode correction. Production and the earlier final findings
remain pending a complete GREEN frozen-source rereview.
