# Independent final review — PRODUCTION-HANDOFF-001

- Date: 2026-11-16
- Reviewer: independently tasked agent `/root/reviewer`; authored none of the specification, tests, fixtures, or production implementation.
- Fixed base: `42df3e1a93a9ac619057bb35ff8b7f54d714681d`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T175540Z-87bad262d9/package.json`
- Exact source: `f1a26b48c91916a3c2699b1bc1d0e8119d37d79e4f546c63ad1479a1bf01f898`
- Frozen snapshot: `/Users/antropophag/.local/share/fmonitor-2/simplify-production-handoff/final-source/source.patch`, SHA-256 `6dc1e2764bb54bbb811e9be052f35c68cdc6f2e1c1712e61217ff1d677cdf820`
- Verification-plan SHA-256: `5dcf7a1a5374071b216004c51f96e793f2724157cd79738e69f0b43aba30ed46`
- Verdict: **CHANGES_REQUESTED**

## Findings

### F1 — HIGH — installed database image identity is not enforced for release or source update

`deploy/production/recovery.sh:248-251` handles an installed build-mode release
with `installed; image verify`. `resolve_build_identity()` rehydrates only the
application source, image, ID, and platform. A normal source-build `.env` keeps
database ID/platform empty because image discovery never rewrites operator env.
Consequently `image verify` accepts whatever image currently occupies the mutable
`FMONITOR_DATABASE_IMAGE` tag, and `release_cmd()` packages it even when its ID
differs from the installed receipt. This violates H02 and the inherited exact
release-image contract.

The same gap exists at `recovery.sh:153-164`: source update calls `image` before
verifying the receipt's installed database image identity. An externally replaced
database tag can become the candidate and later recreate the DB service, while the
old receipt's database ID was never checked. Status/start/backup correctly use
`installed_image`; release/update must retain equivalent database exactness without
mistaking the new application candidate for the installed application.

Correction: verify the current database reference, ID, and platform against the
installed receipt before release packaging and before update candidate acquisition
or backup. Package only receipt-exact installed images. Add a process-fixture case
that changes the DB tag's observed ID after install and requires release and update
to fail before save, backup, provisioning, or writer effects.

### F2 — HIGH — build-to-pinned update writes stale recovery identity

`deploy/production/recovery.sh:77` rewrites private `restore.env` only when the
*candidate* `FMONITOR_IMAGE_MODE` is `build`. During an installed automatic-build
to pull/local update, current mode is pinned, so the rewrite is skipped. The copied
`installed.env` contains the original operator bytes, whose source/image metadata
is intentionally stale because the automatic build identity was never written
back. The resulting backup therefore cannot identify the actual old installed
source/image/IDs, contrary to H03.

Correction: decide resolution from the installed/backup identity rather than the
candidate mode, or safely resolve the old backup's recovery fields from its receipt
on every applicable update. Preserve pinned old installations unchanged. Add a
build-to-pull/local update witness that inspects the old backup's source, image,
application ID/platform, and database identity.

### F3 — MEDIUM — recovery-config rewrite failures bypass backup cleanup and writer recovery

At `deploy/production/recovery.sh:96-98`, failures from `awk`, `chmod`, or `mv`
return immediately after writers have been stopped and the private stage created.
These new exits bypass the surrounding cleanup/resume handling: a manual backup can
leave its previously running writers stopped, the incomplete stage remains, and no
required safe `stopped`/`resumed` outcome is reported. The inherited backup contract
requires a nonzero failure with explicit writer outcome, and update backup failure
must not publish a partial bundle.

Correction: route all three rewrite/publication failures through the same stage
cleanup and conditional writer-resume path used by adjacent backup failures, with a
safe outcome message. Add a deterministic helper-failure regression proving no
bundle/stage publication and correct manual-backup resume behavior; update mode must
remain stopped.

## Conformance assessment

No other blocker was found. Build deploy/update derives a clean exact Git HEAD,
uses collision-resistant non-overwriting candidate tags, preserves env bytes, and
rehydrates installed application identity for ordinary operations. Repeat deploy
and same-source update behavior is bounded; failed update retains its backup and
marker and blocks start/status. Integration qualification runs after schema,
secrets, and owner preparation but before writers and the installed receipt; it
collects all ERP/workforce/document outcomes, suppresses command streams, supports
jobs-disabled and optional-document `SKIPPED`, and invokes no SMTP, scheduler,
outbox, or legacy import.

The ERP command composes the real SQL Server transport, performs one bounded
read-only `SELECT 1`, rejects malformed shapes and adapter exceptions without
private details, and returns safe JSON plus a matching exit code. Login and query
timeouts are bounded. The canonical workforce/document writers remain unchanged.
Pull/local acquisition, env parsing, locks, ownership, and secret boundaries are
preserved.

## Evidence inspected

The package binds GREEN focused records for the handoff Make/Git test and ERP probe
test to exact source `f1a26b48...`. Adjacent production env, lifecycle, quickstart,
optional-SMTP, native transport, ERP contract/read/owner, verification-plan, and
architecture checks were reported GREEN. The corrected source-scope DB check used
its own random-port disposable MariaDB and passed setup/test/teardown `[0,0,0]`;
the earlier localhost `23306` precondition failure is not counted as product
evidence. Full local `make test`/`make verify` was not run, per owner policy. Exact
source CI remains `UNKNOWN`.

## Decision

Final review is **CHANGES_REQUESTED** on F1-F3. Correct the complete set, add the
bounded regressions, rerun mapped focused checks, and prepare a new immutable source
for delta rereview. CI, PR readiness, merge, and deployment remain `UNKNOWN`.

---

## Final correction rereview — 2026-11-16

- Corrected commit: `13efd65a62c908f5c26a20ac502e49fe984bc2c2`
- Corrected package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T181342Z-31a4ccef12/package.json`
- Exact source: `7c50701de27c42de7702ee7865b943adf70a320712fef906ec040e83d5366a0e`
- Executable source: `503c86761c14bbf83ad54bd870de00cf3cdd653f0b647bf8c6430bbeb1ae210a`
- Verification-plan SHA-256: `a750365b8b647a98807d48608a39adc863d8ae26757c41c747cc51b97c2cdce5`
- Frozen source: base commit `13efd65a62c908f5c26a20ac502e49fe984bc2c2` plus empty patch SHA-256 `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`
- Verdict: **APPROVED**

Reviewer independence is unchanged. No finding was waived.

### Finding resolution

- **F1 fixed.** Update now verifies the current database reference, ID, and
  platform against the installed receipt before candidate image acquisition or
  backup effects. Installed build release uses `installed_image` and the receipt's
  exact application ID before creating a stage or calling `docker save`. Independent
  update and release regressions mutate the DB tag and require rejection before
  build, pull, save, runtime backup, writer, migration, or receipt effects.
- **F2 fixed.** Recovery config always receives effective receipt source,
  application identity/platform, and database identity/platform. Its mode comes
  from the old installed private config: old build becomes `local`, while old
  digest-pull and local modes remain pull and local. This handles build-to-pinned
  candidate transitions without removing pinned pull restore portability. The
  build, pull, and local public regressions all inspect their recovery result.
- **F3 fixed.** Rewrite, permission, and publication failures remove the private
  stage and use the same `keep_stopped` policy as the surrounding backup flow.
  Manual backup restores its exact prior writer set and reports `resumed`; update
  leaves writers stopped, publishes no stage/bundle, avoids migration, preserves
  the installed receipt, and reports `stopped`.

The live quickstart cleanup change is also sound. Automatic source builds ignore
the obsolete input image tag, so teardown now reads the disposable project's
private installed receipt, verifies the random project and expected source, and
removes that receipt-owned image. It cannot select a neighboring installation.

### Final evidence and limits

The package binds all twelve Make/Git handoff cases and the ERP controller/probe
test GREEN to the corrected exact source. The handoff record is
`1794852727082466000-5620f0eccedd4ce096e6edb47086146a.json`; the ERP record is
`1794852795827009000-c813397b7b1144b0997679ec895613c3.json`. Adjacent env,
lifecycle, quickstart, optional-SMTP, ERP contract/read/owner/source checks,
planner, architecture, syntax, and diff checks are reported GREEN. The source-scope
check used an isolated random-port disposable MariaDB and passed setup/test/teardown.

The two additional bounded real-Docker checks completed GREEN before this record
was finalized. Fresh clone + copied env + `make deploy` covered login, replay,
stop/start, and actual receipt-owned image cleanup; harness record
`1794852739339008000-540a7d76b4e0413696abf1ed1b9efc7f.json`. The isolated
`pilot_jobs_compose` check covered jobs delivery and restart. Neither check touched
a live server or real external integration. All planner local obligations are now
covered. Exact-source GitHub CI remains `UNKNOWN` and is not implied by this
approval. The owner prohibition on local full `make test`/`make verify` was
respected.

### Final decision

Independent final review is **APPROVED** for commit `13efd65a` and exact source
`7c50701de...`. F1-F3 are closed and no new code finding remains. Publication,
PR readiness, merge, and deployment still require the selected exact-source CI;
this review grants none of them by itself.

---

## Final SQL-ownership delta review — 2026-11-16

- Correction base: `e1356095af458fb1608cd5468698b77619aaba76`
- Corrected package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T183249Z-cadc42baf4/package.json`
- Exact source: `b5df0512cdddeaaeed91f4427b21f67684f97c5bdf5a38b27c27db283e79488b`
- Executable source: `a5878b6d6bab765c1febc422cd5b0637c563564257e2967ef76516ac83fa966a`
- Snapshot patch SHA-256: `e5424b14c6c8b36e803e55ba163ba53fba99b8373d71845cde9ba99d7fcbb802`
- Verification-plan SHA-256: `47db5303769f94330a33a0631984855a7c8a50022402cf61a6f1b7b4c77ae0a7`
- Verdict: **APPROVED**

CI run `43927062027` found one actual architecture failure on the prior pushed
head: the new literal probe SQL lived in `NativeErpEquipmentFactsDelivery` rather
than the established ERP SQL owner. The complete known fast-job inventory had no
`REGRESSION_FAILURE` entries; unit and governance jobs passed, while the remaining
integration/e2e jobs were still pending when this bounded source was approved.

The correction is exactly two production files and preserves behavior.
`MariaDbErpEquipmentFactsSource::probeQuery()` now owns the exact
`SELECT 1 AS available`; `NativeErpEquipmentFactsDelivery::probe()` calls that
owner with the same empty parameters, one-row bound, configured timeout, read-only
flag, response validation, exception handling, and safe receipt. No policy
allowlist or architecture exemption was added. The prior F1-F3 corrections and
their tests are byte-unchanged.

The package binds all twelve handoff cases and the probe/controller test GREEN to
the corrected source. Records:

```text
1794853873879043000-fc74ac9a81014a2b82b698c2a41c6519.json  handoff Make/Git matrix
1794853926121077000-2c6dda5ef9b84c8293440378fa5300a6.json  ERP probe/controller
```

The actual repository `make architecture-check` passed all eight rules, including
the required Pilot HTTP global-call qualification; its log SHA-256 is
`5e5d7b4f2c7542d50d9b09863a03fa7ca3919d726728c820c69de8396a3315bd`.
Both changed PHP files linted, the focused probe test passed, and `git diff --check`
passed. The architecture file-size advisories are pre-existing unrelated hotspots
and do not concern this delta.

No finding remains. Independent final delta review is **APPROVED** for exact source
`b5df0512...`. The still-running prior-head CI jobs must reach a complete inventory
before correction push, and replacement exact-source CI must pass before
publication; this approval does not convert pending or prior failed CI to GREEN.

---

## Final failed-initial retry delta review — 2026-11-16

- Package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T184234Z-876e3f1f6e/package.json`
- Exact source: `4dd2f3c829ba10848fbfbbddf316cdd4124d53248064824f054cf987fb075973`
- Executable source: `0da762f9547aa729c253e8952c5356f1ea7cbaac174d1934292cac4a95eb1cc9`
- Snapshot base: `e1356095af458fb1608cd5468698b77619aaba76`
- Snapshot patch SHA-256: `1f2dac269808407e84ef18ce85db9d942876f14ce7a45114a092fa779d5617c0`
- Verification-plan SHA-256: `39a39c76d584d69490265f5075e2b680e7df64c2c3c471b517acbda8043e4420`
- Verdict: **APPROVED**

The runtime delta after the approved SQL-owner correction is confined to
`deploy/production/manage`. Deploy still validates the receipt's project/resource
ownership and durable identity hash before entering retry logic, then verifies
the exact source, application reference, and application ID. Config-hash equality
is now required inside the `state=installed` branch, where config changes continue
to fail with `update`. A `state=initializing` receipt may proceed with corrected
integration config; `provision()` republishes the initializing receipt with the
new config hash before subsequent setup/qualification, and final success persists
the corrected env. Unknown/failed receipt states remain rejected.

The three public cases are complementary: corrected ERP credentials after a
failed initial qualification now succeed without secret output and preserve
identity/image; a changed Yii identity key remains rejected before Docker or
receipt effects; and the same ERP config change after successful installation
remains rejected before effects and requires update. This implements H03 safe
initial retry without weakening H02 installed identity or config-change policy.

The package binds fifteen handoff cases GREEN at record
`1794854421957099000-46ecf56c1f224d28a60447ce512625af.json` and the probe/controller
test GREEN at `1794854489326357000-33aa338dda0645b1a722eeb586464427.json`.
Shell/Python syntax, focused probe, and diff checks passed during review. The
approved SQL-ownership delta and F1-F3 fixes remain unchanged.

No finding remains. Independent final delta review is **APPROVED** for exact
source `4dd2f3c8...`. The prior CI run still had only Integration 2 pending at
review time; the known fast architecture failure belongs to the earlier head and
is corrected in this source. Complete prior-run inventory and replacement
exact-source CI remain required before publication.
