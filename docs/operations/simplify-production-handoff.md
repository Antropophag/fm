# Stable production handoff

Owner authorization 2026-11-16: implement quickly the agreed stable env + automatic
source updates and initial integration qualification. Empty objects expected; legacy
import excluded and Excel loader deferred. No deployment/merge/live-secret changes.
Base: 42df3e1a93a9ac619057bb35ff8b7f54d714681d.
Root authors contract/tests; separate gpt-5.6-sol/low executor production code;
independent gpt-5.6-sol/low reviewer performs planner-required Gate3/final.
Compact bounded correction of existing production handoff, sensitive delivery/recovery
semantics. No schema migration, HTTP/auth policy, architecture boundary or new domain
writer. Runtime packaging includes the probe via existing COPY app/config.
Spec: specs/PRODUCTION-HANDOFF-001.md. No full local make test/verify; focused local
checks and one selected exact-source GitHub CI. Status/evidence appended below.

## Candidate and verification

Root wrote contract and executable tests. `/root/executor` authored production
Make orchestration/recovery/operator text; `/root/erp_executor` authored the three
ERP probe PHP files. `/root/reviewer` is independent of all artifact authors.
Gate3 first returned three test-completeness findings, then approved corrected
source. A later scalar malformed-response correction received explicitly
retrospective independent test-delta approval; history is retained in the review.

Focused results: Make handoff5/5, existing production env/lifecycle, quickstart16/16,
optional SMTP, public ERP probe/controller and existing native transport PASS.
Existing ERP contract/owner-boundary/read checks PASS on a unique disposable MariaDB
project; setup and teardown exit0. Executor's initial source-scope attempt against
absent localhost23306 was a fixture-precondition failure, not GREEN. Governance
planner18/18 and architecture59/59 PASS. No full local suite, live integrations,
SMTP sends, actual deployment or secret edits. Raw logs/evidence remain outside Git
at ~/.local/share/fmonitor-2/simplify-production-handoff/.

Remaining at this checkpoint: independent final candidate review, exact-source CI
and PR publication. Merge/live deployment are not authorized by this task.

Final-review correction: F1–F3 (installed DB tag drift, build-to-pinned recovery
metadata, rewrite-failure cleanup/resume) were reproduced through five additional
public Make regressions, independently approved, and corrected by the deployment
executor. All10 handoff cases now PASS; no prior findings waived. Source-scope check
also PASS after supplying an isolated disposable DB; original precondition failure
is retained above. Existing live quickstart teardown now removes its own receipt's
automatically selected image instead of the obsolete operator input tag; this CI
consumer adjustment does not change application behavior.

Recovery mode follow-up: the complete build/pull/local matrix exposed a pull-mode
regression in the first F2 correction. Root added pinned pull/local backup witnesses
(pull intended RED, local GREEN), independent reviewer approved the delta, executor
preserved old installed pull/local mode while resolving old build to local. All
three targeted mode cases PASS. No compatibility expectation was weakened.

Final review APPROVED for code commit13efd65a; F1–F3 closed. Full12-case handoff and
public probe GREEN on exact source. Actual clean clone/copied-env/deploy/login/
repeat/stop-start with receipt-owned image cleanup PASS; isolated jobs Compose
startup/restart PASS. Local canonical full suite was not run. Final metadata commit
adds only this delivery update and the independent approval record; application,
operator and test bytes remain those reviewed. Exact-source GitHub CI follows.
