# Runtime combined access log — 2026-11-24

Owner requested Ilyas's additional `access_log /dev/stdout combined;` directly
under safe_json and approved preserving safe-only logging for `/pilot/activate`.
Authorized route: GitHub PR, green exact-source CI, merge, then tested main to
corporate GitLab (whose main pipeline deploys production).

Scope is a bounded runtime logging correction; activation privacy is sensitive.
Root owns the contract and regression; separate sol/low executor owns nginx;
independent sol/low reviewer owns planner-required Gate 3 and final review.
Base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`.
Worktree: `fmonitor-2-nginx-combined-access-log`.
Contract: `specs/ACTIVATION-PROXY-LOG-001.md`, including 2026-11-24 addendum.
Input: `docs/operations/nginx-combined-access-log-verification-input.json`.

Earlier isolated probe of the unqualified one-line change confirmed three
synthetic secret leaks (query, Referer, User-Agent). Safe-only activation override
passed the existing runtime probe. Evidence remains outside Git at
`/var/folders/yc/548th18156s39y3kx0xc05tc0000gn/T/fm-nginx-combined-probe-ky9rgr4g`
and `.../fm-nginx-combined-safe-probe-4l10nj00`.

Verification: real nginx syntax and HTTP logging regression, bounded planner
checks, independent reviews and one full exact-source GitHub CI. No local full
make test/verify. No application/data/schema/authentication implementation changes.
Artifact authors: `/root` (specification/regression/delivery record),
`/root/nginx_log_executor` (nginx implementation), `/root/nginx_log_review`
(independent Gate 3 and final review).

Gate 3 APPROVED without findings:
`reviews/tests/NGINX-COMBINED-ACCESS-LOG-001.md`. The direct RED and a subsequent
harness-captured RED both show only the missing ordinary combined record/count;
the repeat was required to retain source/environment-bound reviewer evidence.
Signed record: `1795508571644528000-9b8de42dc35f4139b58120a4d14d9aad`.
Bounded `pilot_jobs_compose_001_test.py`, `change_verification_001_test.py`
(18 tests), and `architecture_guard_001_test.py` (59 tests) passed.
Logs: `/tmp/fm-nginx-combined-20261124/`.
Harness preparation was corrected for JSON evidence and matching image environment;
a preparation racing temporary architecture fixtures was retried on the frozen
restored snapshot. These were packaging failures, not test failures or approvals.

Final review: `reviews/code/NGINX-COMBINED-ACCESS-LOG-001.md` owns its exact
source and verification result. GitHub PR/CI and GitLab deployment outcomes remain
external live evidence; this pre-publication record does not claim them GREEN.
