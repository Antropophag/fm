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


## Final-review corrections

The initial committed implementation6aa85302 passed actual fresh-main-clone,
copied0644env, plain Make Docker installation and owner login (record
1794826918690878000-bd2f98cd476d4779abddabd01a972991). Preliminary independent
review raised Q1 (parse the captured bytes and reject late path replacement) and
Q2 (limit permission repair to repository-root.env). Separate executor corrected
both; root added two regressions and clarified Q01. The16-case suite against
6aa85302 fails exactly the two new cases; corrected source passes all16.
Evidence: private simple-clone-env-make-deploy/final-review-correction-red.log
and corrected-unit.log. Existing14 cases retained. Final exact-source Docker
qualification, independent final verdict and full GitHub CI still pending.


## Qualification checkpoint

Corrected implementation8711dab8 passed the real Docker fresh-main clone,
copied env, plain Make and login/replay/stop-start acceptance:
1794827593055057000-2631f4c0a42a4163a54a014866b125a6 GREEN.
The final16-case unit result is
1794827689138931000-8a61dfdae91d499ab8ed37bb232de2dc GREEN.
Its immediately preceding command passed but was marked STALE by a concurrent
governance fingerprint fixture; one settled-source repeat supplied bound evidence.
The3 other planned local checks passed: pilot jobs Compose, change verification,
and architecture guard. No full local suite ran. Test/implementation review Q1/Q2
closed both findings; docs-only9a022931 retains that disposition. Executable
source dc0a3eff5828da247c5685409622e2a7b1b190bfcd7b623115eed835ddf07c03.
Full CI and tested-main publication follow the independent final review. The
known missing I1 automatic admission adapter remains UNKNOWN; these local
results and owner-directed publication do not claim automatic admission.
