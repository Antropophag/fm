# PRODUCTION-MAKE-ENV-001 — independent implementation review

- Date: `2026-11-16`
- Reviewer: separately tasked agent `/root/independent_review`
- Base: `7c6b8e14c0a5c8553a73320d12970107cb1debe5`
- Reviewed implementation: `3aea5fee63338a0b35986e9e6c8f022de8006b6a`
- Scope: A01-A13 production Make/environment, image, lifecycle, recovery, release, and dependency fallback implementation
- Independence: reviewer authored neither implementation nor root contract/tests
- Evidence boundary: first combined live acceptance failed before provisioning; final source-bound GREEN package and full exact-source CI were not available
- Verdict: **CHANGES_REQUESTED**

## Findings

### D1 — BLOCKING: published Composer vendor is unreadable by runtime UID 10001

`deploy/dependencies/install:37` creates the vendor stage with `mktemp -d`, which is mode `0700` under the root image-build user. `publish()` at lines 50-59 renames that directory to `vendor` without changing ownership or modes. The final Docker image switches to `USER 10001:10001`, so PHP cannot traverse the directory or load `vendor/autoload.php`. The combined live acceptance reached this real failure before provisioning.

Before verification/publication, normalize the complete staged vendor tree to safe runtime-readable directory/file modes and ownership. Preserve the existing atomic prior-vendor behavior and reject links or unsafe node types rather than broadening permissions blindly.

### D2 — BLOCKING: build mode can label uncommitted source as an exact revision

`deploy/production/manage:190` checks `git status --porcelain --untracked-files=no`, deliberately excluding untracked files. The Docker build context copies `app`, `bin`, `config`, `public`, and `deploy`; untracked files under those paths are not excluded by `.dockerignore` and therefore enter the image while its OCI label and receipt claim `FMONITOR_SOURCE_REVISION`. `env_init()` also selects the reusable tag `fmonitor2-runtime:$revision` at line 65 although A02 requires a unique build tag.

Reject every source difference that can enter the build context, including untracked files, while allowing only explicitly ignored paths. Generate and retain a unique immutable image tag for each source build, then verify the resulting label and image ID. Add a bounded regression proving an untracked file in a copied source directory prevents the Docker build.

### D3 — BLOCKING: lifecycle commands do not verify recorded resource ownership

`ensure_unowned_or_owned()` validates the receipt's recorded database/state/secrets volume and network fingerprints, but only `deploy()` calls it. `installed()` at `deploy/production/manage:291`, used by stop/start/status/logs/backup/update, checks project/config/source identity but never those Docker resource fingerprints. If same-named resources are deleted and recreated while the private receipt remains, lifecycle commands can stop, start, inspect, migrate, or back up a foreign replacement project.

Centralize strict receipt/resource validation and require it before every installed lifecycle operation. Read-only status/logs may remain unlocked, but they must reject identity drift; every mutating operation must validate after acquiring the project lock and before its first Docker effect.

### D4 — BLOCKING: update can back up the new image while recording the old identity

`update_cmd()` reads `old_image` and `old_image_id`, then calls `image` before `backup_core` (`deploy/production/recovery.sh:117-123`). A build or local acquisition may replace the same mutable tag. `backup_core` then runs `$old_image`, which now resolves to the new image, while passing the old source and image ID into the backup manifest. This breaks A07/A08's exact installed-image backup and may execute new recovery code against the old schema.

Run the backup by a verified immutable old image ID, and reject/update references whose acquisition would destroy the only verified old identity. Build-mode unique tags reduce this risk but do not cover mutable local tags; validate both old and new IDs explicitly.

### D5 — MAJOR: status can return success with unhealthy enabled jobs

`status_cmd()` at `deploy/production/manage:296` runs `dc ps` and `health()`. `health()` checks PHP and web only. Docker Compose `ps` normally exits zero even when a listed container is unhealthy, so an unhealthy or absent enabled worker/scheduler can still yield a successful `make status`. Only the disabled-jobs branch has an explicit assertion.

When jobs are enabled, require both job services to be present, running, and healthy and return nonzero otherwise. Keep the current explicit `jobs: disabled` behavior when disabled.

### D6 — MAJOR: an existing backup root is not required to be private or regular

Validation canonicalizes only the parent of `FMONITOR_BACKUP_ROOT`. `backup_core()` line 47 uses `mkdir -p -m 700`, which follows an existing symlink and does not verify an existing directory's owner or mode. Backups can therefore be published through a symlink or beneath a nonprivate operator-selected root despite A07's private `0700` destination boundary.

Create the root with no-clobber semantics when absent; when present, reject symlinks, non-directories, foreign ownership, and any mode other than `0700`. Validate the chosen destination against that canonical private root before stopping writers.

### D7 — MAJOR: release does not bind the database image or document architecture

`release_cmd()` records the application image ID but represents MariaDB only as mutable tag `mariadb:11.4.7-noble` (`deploy/production/recovery.sh:215-223`). Deployment verifies only the application image identity after archive load. Neither release metadata nor operator documentation records the image platform/architecture. A10 requires exact application and database image identifiers and documented architecture.

Inspect and record the exact database image ID/digest and platform alongside the application identity, verify both after loading and before resource creation, and document the compatible architecture in the release artifact/operator flow.

### D8 — MAJOR: receipt publication failure can leave writers running in an unresolved update

The guarded update failure block in `deploy/production/recovery.sh:125-130` covers `provision` and `start_services`, but `receipt installed` at line 131 is outside it. If installed-environment or receipt publication fails after services start, `set -e` exits with writers running and the prior `updating` marker still present. A08 requires every failed update to leave writers stopped and retain an explicit failed/in-progress state. `deploy()` has the analogous edge after first-start success, leaving writers active with an initializing receipt.

Include final durable receipt publication in the success transition. On publication failure, stop the selected writers and retain/publish the failure marker without claiming installed. Preserve exact replay for the initial deployment case.

### D9 — BLOCKING: Bash conditional context suppresses update step failures

`update_cmd()` uses `if ! provision || ! start_services; then` at `deploy/production/recovery.sh:125`. Bash disables `errexit` for commands in a function whose invocation is tested by `!`, `if`, or an `||` list. `provision()` in `deploy/production/manage:231-240` is a sequence of unchecked commands. A failed provisioning, state preparation, integration staging, migration, or first runtime check can therefore be followed by a later successful command and make `provision` return success. On an already-current schema, for example, an injected migration failure can be masked by the succeeding deep check; update may start services and publish `installed`.

Make every ordered provisioning and start step propagate failure explicitly (`&&` chain or `|| return`) and keep the update error handler outside any context that weakens function-internal failure semantics. Add focused failure injection at each material boundary, especially migration followed by a potentially successful deep check, and require stopped writers plus a failed marker.

## Confirmed implementation properties

The strict environment parser does not source or evaluate values; image labels/IDs are checked; the receipt binds project/config/source and core identity; runtime and migration database credentials are separated in Compose; production HTTP is loopback-only; public application services inherit UID 10001; restart, graceful-stop, and log limits are present. Backup uses the canonical RuntimeRecovery owner with data-only schema-v39 manifests. The new public recovery `verify` reuses canonical manifest/tar preflight before target DB creation. Direct dependency fallback binds the exact Composer inputs and package set to reviewed HTTPS archive URLs and SHA-256 values, retains Composer plugin/autoload/platform processing, and preserves an existing vendor on ordinary failure. Release excludes the operator environment and runtime data.

These properties do not offset D1-D9. The first live run is evidence for D1, not a complete acceptance result; remaining live behavior and exact-source CI are UNKNOWN.

## Review consequence

Implementation review for `3aea5fee` is **CHANGES_REQUESTED**. Correct D1-D9 as one coherent candidate, run the planner-selected bounded checks including the full disposable live acceptance, and prepare a fresh source-bound reviewer package. Do not interpret this record as approval of the in-progress working tree or as a request for the prohibited full local suite.

---

## Final Gate 5 rereview — corrected implementation

- Date: `2026-11-16`
- Base: `6cf725fcf10b865613c6f91d1db225fb29542266`
- Reviewed HEAD: `cbcc04dbb4283db5c9e1e4161b03330cc97523d9`
- Exact candidate source: `82720bf8c7482679f193dc1d7a2cd209127a0f6054b72c172ee806dbe1667e4a`
- Reviewer package: `20261116T090737Z-9cd6a4c843`
- Verification plan SHA-256: `04ad94b7fab41de37880de27ed42f0826a6b157e63f68f94fb2c2c7055a4dba2`
- Reviewer: `/root/independent_review`; implementation authors: separate deployment, recovery, and dependency executors
- Harness review record: final `APPROVED`, recorded `2026-11-16T09:08:20Z`
- Verdict: **APPROVED**

### D1-D9 and D7-R dispositions

- **D1 closed:** staged Composer vendor is made runtime-readable before atomic publication; the final image and live acceptance load it as UID10001.
- **D2 closed:** build mode requires the exact HEAD with all untracked source absent, uses a random unique tag, and refuses a pre-existing tag before build.
- **D3 closed:** every installed lifecycle seam validates the receipt-bound database/state/secrets volume and network identities before observation or mutation.
- **D4 closed:** update verifies and executes recovery through the stored immutable old image ID while retaining the old human reference in its manifest.
- **D5 closed:** enabled jobs must each be present, running, and healthy for status success; disabled jobs remain explicitly disabled.
- **D6 closed:** deployment state and backup roots require private operator ownership/mode, reject links/files/public directories, and backup destinations are new direct children of the canonical private root.
- **D7 and D7-R closed:** release records and checksum-binds exact application/database IDs and equal platforms, seeds both identities into the release environment, and verifies the loaded application ID, database ID, and platforms before DB effects. Release tooling comes from the verified immutable application image.
- **D8 closed:** deploy/update include final receipt publication in the guarded success transition; publication failure stops all writers and leaves an unresolved marker that blocks start.
- **D9 closed:** provisioning, integration, migration, health, receipt, backup, and recovery helper steps propagate failures explicitly rather than relying on conditional-context `errexit`.

The later Bash3 stopped-backup correction safely handles an empty writer set without changing running-writer restoration. Restore keeps the host bundle read-only, performs outer validation, copies fixed members into a container-owned private overlay, drops to UID/GID10001 for canonical verify/restore, and uses a minimal writable credential tmpfs. The separate target environment mount, password cleanup, deep readiness, service start, and installed receipt behavior were reviewed and exercised by the final live run.

### Exact-source evidence

All package evidence below is `GREEN`, has no missing tests, and binds source `82720bf8c7482679f193dc1d7a2cd209127a0f6054b72c172ee806dbe1667e4a`:

- `python3 tests/Deployment/production_make_env_001_test.py` — record `1794819783571220000-3499668a43914939ac0e2efc9666d120`
- `python3 tests/Deployment/production_make_lifecycle_001_test.py` — record `1794819783566494000-baf7729467d542009c863833d460b6f2`
- `python3 tests/Deployment/production_dependencies_001_test.py` — record `1794819783578518000-87fa83b8ef77455c9d6e9ee832d854b9`
- `php tests/Runtime/production_recovery_preflight_001_test.php` — record `1794819783582351000-7e9b075a67fd49cc9a4fd00ceecaddbc`
- `python3 tests/Deployment/production_make_live_001_test.py` — record `1794819773831147000-c99ca0ae0d4742aea7f94937e71fe9b9`

The live record covers the real offline dependency fallback, empty v39 deployment, owner login, exact replay, stop/start and durable state, running/stopped/error backup, contention exclusion, corrupt and semantically invalid restore rejection before target resources, empty-target restore and login, release identities/defaults, successful update ordering/data/secrets, receipt-publication failure, and failed-migration writer shutdown.

Historical failed live runs and both earlier `CHANGES_REQUESTED` decisions remain authoritative diagnostics and are not reclassified as GREEN.

### Final boundary

Gate 5 is **APPROVED** for the exact candidate above. No code-review findings remain. Three broad historical recovery/canonical-integration consumers and the planner-selected full matrix remain assigned to exact-source GitHub CI; CI is still `UNKNOWN` and this verdict does not authorize merge or production deployment. The owner-prohibited full local suite was not run.
