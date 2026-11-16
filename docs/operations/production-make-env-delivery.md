# Production Make/env delivery — 2026-11-16

Owner authorization: implement agreed clean deployment package, one private env with bootstrap account/secrets and automatic Composer/direct-archive fallback. No live deployment/legacy transfer/real mail or merge. Dedicated worktree based on main7c6b8e14 including merged PR294; neighboring worktrees untouched.

Authors: root specification/OpenSpec/tests/integration; separate sol/low deployment_executor, recovery_executor and dependency_executor implementation; independent_review authored neither tests nor implementation.

Contract: `specs/PRODUCTION-MAKE-ENV-001.md`; lifecycle: `openspec/changes/production-make-env-deploy`. Planner selects CRITICAL, Gate3 and final, full exact-source CI. No full local suite.

## Complete acceptance matrix rebuild after two Gate3 returns

Root rebuilt the complete matrix before next dispatch; prior findings are retained in reviews/tests/PRODUCTION-MAKE-ENV-001.md.

| Contract | Public executable evidence | Coverage |
|---|---|---|
| A01 | production_make_env_001 | independent generated secrets; no-clobber/repeat/concurrent init; literal quotes/dollar/backticks/hash/spaces; inherited-env exclusion; unknown/duplicate/control/CRLF/placeholder; file type/mode |
| A02 | env + lifecycle + live | project identity; build/pull/local acquisition; source-label/archive-checksum rejection before DB; existing foreign volume preserved; held backup contenders cannot invoke mutations or alter container states |
| A03 | live | failed first-start/exact replay; fresh owner login; repeated deploy/session/data preservation |
| A04 | env + live | disabled jobs require no credentials or background services; enabling without full config fails |
| A05 | lifecycle + live | Make seam; missing env zero Docker; stop/start; health; neighbor/source identity preserved; local paths untouched |
| A06 | live + existing CI production browser | loaded upload/post PHP settings; existing protected PDF/user journey remains in full CI |
| A07 | live | running/stopped backup; exact prior service restoration; injected failure; current v39 complete canonical bundle; no-clobber destinations |
| A08 | live | successful labelled-image update; process observer verifies old-source backup exists and writers stopped before migration; acquisition/backup/migration ordering; new receipt/secrets/data; failed migration stays stopped/start denied |
| A09 | live | new target DB/root secrets verified in runtime; identity key preserved; owner login/state roundtrip; source/collision/corruption preflight |
| A10 | live | release directory/images/tooling excludes env/secrets; archive preflight covered by lifecycle |
| A11/A12 | dependencies + live | exact manifest set/hash/source; primary-success no downloader; forced primary failure and real offline plugin/autoload; cache repeat/corrupt/platform failure; exact direct URL fixture/download corruption; atomic prior vendor preservation |
| A13 | all + CI | only random disposable resources; cleanup guards; no live transport; private evidence; independent gates/full CI |

Residual R1 fixed: held operation now compares stable container IDs/State before/after the contender and records/rejects any mutating Docker command from contender before releasing lock.
Residual R2 fixed: success-update Docker observer checks a newly published canonical old-source backup and absence of all writer services at migration invocation; trace asserts image acquisition precedes backup and backup precedes migration. Final new receipt/data/secret checks retained.

## Evidence and status

Private evidence root: `~/.local/share/fmonitor-2/production-make-env-deploy/`. Intended RED after each meaningful root test correction is recorded by harness; repetitions are source changes, not same-source retries. Gate3 review has two CHANGES_REQUESTED iterations; implementation has not started at this checkpoint. Source-bound final review/CI/PR are pending, not GREEN.

## Implementation checkpoint

Gate3 approved after two complete-candidate returns. Narrow fixture delta (canonical Yii bootstrap and owned-image cleanup) and A09 read-only canonical verify/A10 release-defaults delta independently approved. Implementation authors above; root corrected public tests only. Docker dependency image build succeeded; root executed automatic fallback/primary success/corrupt-cache/download/platform preservation cases inside network-disabled container (private dependency-phase.log), all GREEN. Fast config/lifecycle and architecture/generated-dependency checks GREEN. Combined clean runtime/backup/restore acceptance, independent final review and CI still pending.

Readonly canonical verify adds no schema/table/domain fact; it exposes existing preflight before target resources. Planner consumer closure expands across recovery/application; local checks remain bounded, broader matrix belongs to exact-source CI.

## First combined run and complete correction inventory

Committed implementation checkpoint3aea5fee. Four new fast checks source-bound GREEN; architecture and OpenSpec validation GREEN. First live run stopped at initial account provisioning because root-owned vendor stage0700 was unreadable to runtimeUID10001 (harness record1794815029923419000-625cc4e4cc40408f99bfa0caa12fee10). No user data or production target affected; isolated resources cleaned. Dependency author fixed publication permissions and proved non-root autoload in network-none container.

Independent code review CHANGES_REQUESTED D1–D9 retained in reviews/code/PRODUCTION-MAKE-ENV-001.md. Corrections address full source/tag identity, all-resource ownership validation, old immutable image backup, jobs health, private backup roots, app+DB release identity/platform, receipt-failure writer shutdown and explicit Bash failure propagation. Root added D2/D6 executable regressions (confirmed intended RED on isolated3aea+test delta), plus release replay/identity and post-start publication failure assertions; independent test-delta APPROVED. Corrected combined run and final review remain pending.

## Complete matrix rebuild after second code-review return

Root re-audited A01–A13 and all D1–D9 dispositions for2ba3be89. D1–D6/D8/D9 are independently closed by code inspection; local fast checks GREEN. Remaining D7-R: expected application image ID was recorded but not consumed; new root oracle checks supported expected ID, same-revision/wrong-ID zero DB effect, matching-ID reaches controlled DB boundary, and release env auto-seeding. App/DB platform equality is explicit. Full second live created v39 schema and initial owner/replayed idempotently, PHP healthy; web health used single-quoted literal Host variable and failed400. Actual own-container comparison expanded Host200/literal400 establishes cause; captured record1794816371045047000-6adf3176879e41918219105814c7435d. Corrected web health and D7-R are one coherent next candidate; old failures retained, no full local suite or live stand touched.


## Integrated upstream and actual workflow corrections

Preserved parallel PR295 by merging main6cf725fc into95ad3372; only the current-goal history conflicted, resolved with both task histories retained. Final diff/review base is6cf725fc. After D7-R/web-health correction, root fixed two test harness defects (urllib local binding; backup injection matching now compares actual adjacent argv) with independent review approval in principle. Fault/hold bodies were separately exercised against actual Docker recovery argv; prior unsuccessful runs are retained as failures.

Combined run on95ad3372 passed real offline dependencies, clean v39/owner login, replay, sessions, running backup, injected backup failure and concurrent-command exclusion. Stopped backup then exposed Bash3.2 empty-array nounset at recovery.sh16; record1794818472697622000-c7521da86a714f388c85f7bd3daf71c7 is the established executable RED. Recovery author owns the bounded compatibility fix; root did not alter its oracle. Full subsequent workflow remains pending at this checkpoint.

Focused source-bound config/lifecycle/dependency/canonical-preflight plus architecture guard, jobs Compose, verification planner and generated-source checks passed. make architecture-check exited0 with existing hotspot advisories; harness labels that output UNKNOWN, which is retained. Historical recovery and canonical-integration consumer closure is assigned to the full exact-source CI rather than repeated costly local builds; the new current-v39 live acceptance supplies the local changed-boundary evidence. No full local suite, production deployment or external transport was run.


On0b4e4f61 the stopped-backup Bash3 correction passed together with all earlier backup and lock cases. Restore preflight then failed before DB creation because Docker cannot create a nested target.env mountpoint inside the read-only backup-directory bind. Record1794818823674696000-d7918d5d024c42e7bd37617bedbe5599 is retained as RED. Recovery executor separates the target environment mount path; the unchanged full restore oracle remains authoritative.


On7a2b5a63 the outer bundle verifier passed but canonical verify returned BUNDLE_INVALID (record1794819063814415000-7924d8be28674850803ad3def7c54dbd). Recovery executor measured the Docker Desktop bind root as uid/gid0:0 mode0700 while its files were10001:10001 mode0600; canonical preflight correctly requires the directory itself to belong to10001. A private diagnostic confirmed container-owned overlay staging directory/files10001 and public BUNDLE_VERIFIED without weakening preflight. The proposed flow retains readonly host bundle, copies into ephemeral disk-backed container staging, drops privileges with existing setpriv, and uses a small writable credential tmpfs because canonical RuntimeStorage readiness requires writable private storage. Full restore/login acceptance remains pending until the corrected run completes.


Recovery executor subsequently completed the unchanged full focused live acceptance with exit0 after the container-owned staging correction: real empty deploy/replay/login/restart/v39 backup/empty restore, release, successful update and injected update failures. Synthetic project containers were removed. Independent reviewer approved the trust/privilege transition in principle; original backup remains readonly, canonical verification is unchanged, SQL/tar processing occurs only after dropping to10001. Root is freezing the corrected source for harness-bound acceptance and final review; CI/PR remain pending at this checkpoint.
