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


## Owner clarification and CI failure triage

Owner now requests real Bitrix/ERP without mail; blank-all-four SMTP credentials
are the chosen interface, no new flag. OPTIONAL-SMTP-001 owns this bounded
configuration correction. Root tests; separate executor implementation; fresh
independent planner-required reviews. Admin SMTP unchanged; trial jobs enabled.

Full CI43880163739 at274dfc64 failed solely on e2e
yii2_address_register_browser_001_test.php: cancel leaves saved view. All other
categories passed; verify133356838658 and Quality Graph133357430245 are derived
failures. Complete inventory and logs retained privately. Unchanged base browser
test/runtime reproduced locally: native dialog close restoration occurs after
click promise resolves. Root test correction awaits exact saved columns; all
assertions and runtime remain unchanged. No same-source retry or full local suite.

## Optional SMTP qualification checkpoint

Separate executor completed SMTP presence/configuration, scheduler composition and
both Compose environment propagation; sender, queue and schema stayed unchanged.
Independent Gate3/final APPROVED in OPTIONAL-SMTP-001 review records. Selected
19 local checks plus adjacent production env/browser checks and actual
make architecture-check passed. The first CLI run exposed a root test oracle
mistake: utilization is only due03:17 Moscow after completed workforce sync.
One expected kind was corrected with independent test-delta approval; original
failure and RED retained. Four final mapped acceptance records are exact-source
GREEN. Initial help/actual fresh Docker acceptance remains linked above. No full
local suite or real external mail/integration call ran. Candidate is ready for
one new exact-source fullCI, then owner-authorized tested-main GitLab publication;
private handoff changes are staged outside Git and await the merged SHA.
