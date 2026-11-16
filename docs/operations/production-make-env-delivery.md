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
