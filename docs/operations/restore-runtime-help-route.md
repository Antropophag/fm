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
