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
