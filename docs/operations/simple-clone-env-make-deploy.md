# Clone/env/Make usability correction — 2026-11-16

Owner explicitly requests git clone -> copy root .env -> make deploy, with no manual
private-directory or chmod/ENV_FILE setup. Existing Windows/WSL/Make; GitHub remains
development and CI, GitLab receives only tested main. Base1e4b6a61. This is a bounded
correction of existing production operator usability, with sensitive permission
handling kept in planner-required review. Root authors spec/tests; separate sol/low
deployment_executor implementation; independent reviewer authored neither.

Contract PRODUCTION-MAKE-QUICKSTART-001; verification input beside this record.
Scope excludes business/schema, legacy import, Excel importer, production runtime
changes, CI/admission-policy changes and automatic broad repository synchronization.
No full local suite. Private evidence/handoffs outside Git. Initial stage: contract
and tests; implementation not started, no GREEN/final approval/CI claimed yet.


## Implementation checkpoint

Gate3 first returned T1–T3: avoid target checkout repair in the real clone oracle,
verify descriptor/file identity and chmod-time replacement, and complete unsafe
HOME/project/leaf matrices. Root addressed the full inventory; both mapped tests
again demonstrated intended RED on existing behavior. Revised Gate3 APPROVED at
source3c91c4c4 (review record retains prior rejection). Separate deployment_executor
implemented the four scoped operator files. New14-case quickstart, existing env
and lifecycle checks plus Bash syntax passed. Root removed one redundant spy-stdout
assertion: actual Docker trace remains the oracle; the original quiet prerequisite
behavior is retained. No secret bytes, domains, schemas or service data changed.

Actual fresh-clone Docker qualification, final independent review and exact-source
full GitHub CI remain pending at this checkpoint. No production deployment.
