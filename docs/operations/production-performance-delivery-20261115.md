# Production performance — delivery 2026-11-15

## Authorization and scope

Owner: «Исправь все прямо на проде, потом прогони правки по гейтам через наш CI до мерджа в мейн». Explicit authorization covers production deployment followed by formal gates/CI/PR/merge. It does not authorize deleting facts, changing financial rules or broad filesystem cleanup. Full local make test/verify remains forbidden.

All F01–F12 of performance-audit-20261114 are mapped to PRODUCTION-PERFORMANCE-001 A01–A12; no finding silently deferred. Base main: `0a7fea127a6129f63d370333b23ce31f80d047bd`. Dedicated worktree `fmonitor-2-fix-prod-performance-20261115`. Source/PR/CI are resolved by harness state, not this historical text.

## Authorship

- Root: contract/OpenSpec, verification registration/ownership metadata, independent test expectations and fixture adaptations, integration and deployment tooling.
- `/root/perf_backend`, gpt-5.6-sol/low: A01/A04–A07 product implementation.
- `/root/perf_checklist`, gpt-5.6-sol/low: A02/A03/A11, GD dependency closure.
- `/root/perf_ui`, gpt-5.6-sol/low: A08–A10/A12 product implementation.
- Independent predeploy/formal reviewers remain separate from these authors. No self-approval is inferred.

## Delivery order

Owner explicitly requested production-first. Before deployment: bounded local checks and independent full-candidate review. Formal Gate3/final approval and exact-source full GitHub CI follow production delivery; this is a declared retrospective sequence, not a claim that preimplementation Gate3 occurred. All required gates remain PENDING until records exist. Initial planner lane CRITICAL, required reviews gate3 + final; no policy weakening is used to obtain an ordinary lane.

## Evidence outside Git

Primary directory: `~/.local/share/fmonitor-2/fix-prod-performance-20261115/`.
- `baseline.json`: fresh host/PHP hashes, container identities and readiness. Two previously divergent dashboard files are out of patch scope and must survive.
- `register-red.log`, `sw-red.log`, `thumbnail-red.log`: initial root tests before implementation. `browser-baseline.log`: real browser intended RED at calendar date-window bound. Missing dependency/setup failures are not RED evidence.
- `backend-focused.log`: native PHP session lock, narrow reads and object card GREEN; old malformed-application assertion exposed a masking defect, corrected by executor without changing expectation. Exact existing utilization stage-one rerun GREEN.
- `media-auth-focused.log`: real photo original/thumbnail authorization, photo browser, offline operations/replay, signed-original browser, session fault and month session regressions GREEN on recorded source.
- `sw-stale-navigation.log`: late authenticated response after logout reproduced cache resurrection; executor added epoch/sequence commit guards. Expanded event protocol now GREEN.
- UI compatibility logs preserve every failed intermediate source. Old per-row editor-count and calendar-window expectations are migrated to the new public contracts while retaining recipient/context/auth/focus/overflow assertions. Actual import-mode, invitation-recovery and modal/context defects are fixed in product code, not by weakening tests.
- GD prerequisite image `fmonitor2-runtime:perf-gd-base-20261115` built on server from the running image ID, not from a secret-bearing container commit. This is a prepared artifact only; no running service has yet been switched by this delivery.

## Current checkpoint

Implementation and focused compatibility verification are in progress. Production rollout, formal approvals, CI and merge are NOT YET complete. Future observations append below; previous failed evidence is retained.

## Review correction checkpoint (before production)

Independent `/root/perf_review` returned CHANGES_REQUIRED for incomplete acceptance mapping and missing adversarial evidence. Existing full OTIZ/calendar/user/stage-one browser/HTTP regressions are now mapped. Root added bounded scoped SQL and unrelated malformed evidence, missing/invalid workforce status, two real HTTP workers sharing one held session, before-send no-reopen checks, calendar keyboard/late response/network fallback, user counts/page/exact-editor denial, asset content/dependency perturbation, and oversized image decode refusal. Initial added tests exposed test-fixture assumptions (raw original does not itself mark actual start, payments HEAD is not an existing route, keyboard activation uses the existing button contract) and one real product defect: directory rendering reopened the session for logout CSRF. Backend executor corrected all selected shell callers to use tokens captured before handoff; concurrency regression then passed. Failures are retained in review-gap logs.

Production runtime audit additionally found independently older web/jobs source bytes. Rollout is therefore limited to the PHP service with a persistent per-service image override in the existing home Compose file, plus nginx configuration reload. Other container IDs and sources remain unchanged. Both rollback and candidate images are built from captured actual PHP source; no container commit/secrets capture. Rollout script review required stronger Running/health assertions, preservation of every non-conflicting rollback path, structured original+rollback errors, and public HTTP probes. Authenticated live thumbnail/performance confirmation remains a distinct mandatory stage; service switch alone is not delivery success.

Historical intended RED is established for A01/A02/A03/A09 and the later stale-navigation race. Other criteria receive retrospective executable verification, never invented preimplementation RED. Exact final review package/evidence follow source freeze; deployment, full CI and merge remain pending at this checkpoint.

## Complete source review correction checkpoint

Reviewer completed the full frozen-source inventory with four findings: unrelated known-roster/malformed-engineer global rejection, malformed register JSON failing open, user query errors converted to500, and unversioned hardcoded page asset emissions. Separate executors corrected all four; root added exact regressions and a full server-view emission inventory. The SW generation owner now seeds internal unversioned shell keys with HTTP-cache bypass (`no-store`); the event test observes each install request. Current mutable page emissions are versioned including early sidebar scripts.

First canonical mapped run is retained in `canonical-round-1`:15 GREEN, one genuine old per-recipient editor assertion requiring migration to the shared-editor contract, and five UNKNOWN because the source changed during corrective work (their test exits were0). None of those UNKNOWN records is accepted as exact-source evidence. The assertion now proves one shared form plus both exact recipient entrypoints; financial POST facts/rejection assertions remain unchanged. A complete source freeze and new mapped verification are required before reviewer approval.

Second corrected frozen mapped run:20/21 GREEN; one root test adaptation incorrectly required exactly one entrypoint per financial recipient although both grouping axes plus chooser deliberately expose several. Corrected to require each exact pair entrypoint and exactly one shared form, matching the original semantic expectation. Reviewer found one fixed-version checklist script missed by the first no-query-only source inventory; root rebuilt the full raw mutable URL inventory across runtime PHP/config including query strings and found only that emission. Executor now uses the content-derived version there; inventory rejects both unversioned and hardcoded-version URLs. This foreseeable repeat is recorded; prior evidence remains in canonical-round-2.

Focused environment correction: after the OTIZ HTML assertion was fixed, its preserved XLSX oracle reached an unavailable `ZipArchive` extension in the focused browser image (SETUP failure, not product RED). Root added libzip/zip to that disposable image and the CI extension manifest, without changing the financial test oracle or production dependencies. No full local suite is run.

## Production checkpoint — 2026-11-15 20:11 MSK

Independent retrospective Gate3 and final/predeploy reviews are APPROVED in `reviews/tests/PRODUCTION-PERFORMANCE-001.md` and `reviews/code/PRODUCTION-PERFORMANCE-001.md`. The final formal package `20261115T170944Z-de63286580` binds21/21 GREEN mapped checks to candidate `da7067fe354da7232eb327b8f891afddd8063fc775c1ab235e41cb386412e20e` (executable `1f1530d2f13eac69425ea75d0f931c8ac155a6fbda32bc3174c2be2d6e16913d`); architecture8/8 GREEN. Subsequent source additions are these factual delivery notes and lifecycle/review records only.

Scoped rollout completed17:11:49UTC:50 runtime/config/composer files, patch aggregate `03dfff5dd17961ea72972a4285ba4bb7640d89feb3f99616dbcad4d0a8019f9b`, image `sha256:722f50897544d0608abb707cac95eed279e00f46f75d19e0c931a9ad9399a7d4`. Only PHP was recreated; existing nginx reloaded, other container IDs preserved. Canonical runtime startup check refreshed the build-bound private readiness attestation; no migration or business writes. PHP/web/jobs/database healthy, gateway running. Actual prior PHP source is preserved in rollback image `fmonitor2-runtime:perf-rollback-20261115-03dfff5dd179`; original host files and Compose override are privately backed up.

Authorized live checks are VERIFIED: unchanged login session; users<=25 and actual editor dialog; calendar<=42 and fragment navigation; immutable exact-version asset HEAD; real protected GD thumbnail640×360 (19,133 bytes) from the same2,215,859-byte original with unchanged SHA; guest thumbnail denied; dismissed installer15755 now200. Parallel roles completed69ms while payments completed582ms. The repeat page audit covered38 responses, all200 with zero JS errors. Desktop/mobile interaction inventory covered50 actions with no failed interaction or JS error; unavailable actions remain explicitly recorded rather than counted as exercised.

Same-session page comparison: payments TTFB3,727→616ms; users HTML305,818→61,756bytes and DOM4,179→631; calendar HTML330,708→127,068bytes and DOM4,002→1,554; calculation16 dialogs97→6. Calendar/users server TTFB did not materially improve; their gain is bounded HTML/DOM and incremental navigation. These are live samples, not guaranteed latency percentiles. Primary JSON/logs/screenshots stay outside Git.

GitHub full exact-source CI/PR/merge remain the next required integration stage at this commit checkpoint. Their actual outcome is recorded in the PR/run and external delivery journal; this historical checkpoint does not claim future GREEN.
