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


## Generated Compose correction

Run43890591476 at74c8d393 failed setup in e2e133380492432 and Integration
133380492485/133380492527: runtime Compose drifted from its canonical template.
All three complete logs retained; no regression tests ran in those jobs. Root
cancelled the remaining running categories to avoid waiting on a known failed
candidate; cancelled categories are incomplete, never GREEN. The runtime Compose
file has no generated header; its template ownership was missed during local
qualification. Separate executor copied the reviewed11 scheduler environment
lines into tools/delivery/compose.runtime.yaml.in. Rendering preserves every
previously reviewed artifact byte-for-byte and the dependency check now passes.
No runtime/spec/test semantics changed. Independent final delta review and a new
exact-source fullCI are required before publication.


## Calendar browser readiness correction

Full run43892093550 at7277f54c completed with all categories GREEN except e2e.
Sole regression was production_performance_browser_001_test.php, waitForURL
after keyboard Enter following Back. verify/Quality Graph failures are derived;
complete logs/inventory retained. Test and calendar runtime match green base
e4642a77; previous run43880163739 passed this test in5.214s. Exact unchanged
fixture passed three isolated attempts. A deterministic private witness held the
Back fragment: URL already restored while old keyboard day remained focused;
release replaced DOM, detached that element and left focus on BODY.

Root bounded test-only correction records initial selected date and waits for
that selected DOM state after Back before focusing the keyboard date. Existing
keyboard, navigation, stale-response, fallback and readonly assertions remain.
No sleep, larger timeout, runtime or policy change. Root authors this established
test correction; independent final review per ordinary compact planner route.
Input calendar-browser-readiness-verification-input.json, base7277f54c. Prior
SMTP/help reviews remain; a new exact-source fullCI is required before merge.
