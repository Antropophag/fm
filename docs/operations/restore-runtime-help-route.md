# Runtime help route — 2026-11-16

Owner's deployed clean workstation returned {ok:false,reason:NOT_FOUND} from the
Instruction link. Root reproduced production UrlManager parsing: help/, help/index.html,
help/guide.css returnfalse while pilot/objects resolves. The HTML/CSS/JS/screenshots
already exist in public/help and are copied into the image. No DB/import failure.
Trial jobs are disabled intentionally; integration activation is not part of this fix.

Contract RUNTIME-HELP-001. Root spec/tests; separate sol/low executor controller/routes;
independent reviews per planner. Narrow public read correction, current host/security
policy retained. User has an installed database now; subsequent handoff uses make update,
not first-install replacement/reset. Secrets and evidence stay outside Git.


## Qualification checkpoint

Gate3 approved without findings after real Yii HTTP and actual healthy Docker
installation both reproduced help404. Separate executor changed only HelpController
and assets routes. Root strengthened the symlink regression to replace an allowed
screenshot in a private exact source fixture, avoiding the weaker unknown-name404
oracle and any mutation of repository files. Existing guide content is unchanged.

Final HTTP5-case GREEN1794836861848321000-985959852e7449369e2b2ff643f44fb8;
fresh-clone Docker guide/all-linked-assets/login/replay/stop-start GREEN
1794837013368785000-af3a40233be647f5a5788d0b197eac5a. All3 other planned checks
passed: jobs Compose, change verification and architecture guard. No full local
suite ran. Independent final review approved13:52:31Z on candidatea5b97c45 with no
code findings. Only review/delivery notes follow that source; exact-source fullCI
and tested-main GitLab publication remain next. Existing automatic I1 admission
adapter stays UNKNOWN. No live production or external integration activity.
