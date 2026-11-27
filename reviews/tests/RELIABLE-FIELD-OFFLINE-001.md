# Test review: RELIABLE-FIELD-OFFLINE-001

- Reviewer: independent `/root/offline_gate_reviewer`, gpt-5.6-sol/low; authored none of this contract/tests.
- Specification/test author: root.
- Verdict: **APPROVED**, 2026-11-26T15:03:12Z; harness record-review completed.
- Exact candidate: `a02f0ce64fb7cbe98e3853972aa01d5c617ae3366ce12e7825a086b7fd877713`.
- Base: `2ea60f1d2f0a9b152cd05a6731ea2f3a808bfc10`.
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261126T150108Z-bf312f36cf/package.json`.
- Reconstructible snapshot: package `snapshot/source.patch`, SHA-256 `629e9c70092b7a818102116c943e7cf94311aeb8117cddd9050d0e6770ae03c1`.
- Restored reviewed source: `/Users/antropophag/.local/share/fmonitor-2/reliable-field-offline/gate3-source`.
- Required lane/reviews: planner CRITICAL, Gate3 and final.

## Evidence

- `tests/Yii2/reliable_field_offline_worker_001_test.mjs`: INTENDED_RED; `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795704881011872000-38df8595b1e542e089b5c085a8080e50.json`.
- `tests/Yii2/reliable_field_offline_001_test.php`: INTENDED_RED; `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795704881011863000-03809e4ccd6644e8ae0119638ba48227.json`.
- `tests/Yii2/yii2_checklist_operation_replay_001_test.php`: GREEN; `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795704882785372000-b8ce26a3778a4814b281f8a036126f4a.json`.
- `tests/Yii2/yii2_construction_control_mobile_list_browser_001_test.php`: GREEN; `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795704904000149000-91ab3ab83cae4d31944a945038ba6224.json`.
- `tests/Yii2/yii2_inspection_browser_001_test.php`: GREEN; `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795704915407930000-9914bf114e3241f887ff2223836e8bc1.json`.
- `tests/Yii2/reliable_field_offline_browser_001_test.php`: INTENDED_RED; `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795704881011850000-6f88b0b9abc742daa791d60de6bbee7e.json`.

## Independent verdict and findings

Findings: none. The reviewer verified package/plan/context hashes and the reconstructed source. A1–A9 have executable coverage at real HTTP/browser/IndexedDB/service-worker and independent server-fact seams: full mine-only53-object preparation including unvisited editable checklist, partial/churn recovery, durable ordering/replay and multi-tab sequencing, receipt abort, auth/VPN, photo preparation/cap/quota/export, legacy queues, explicit conflicts and private cache generation/logout safety. New tests show intended missing behavior; three neighboring regression controls remain GREEN. Physical Android/iPhone/FortiClient remains UNKNOWN, not a Gate3 blocker or evidence of hardware acceptance.

## Advisory iterations before formal review

The reviewer first identified15 design clarifications; root recorded the resolved identity/generation/transaction/replay/manifest/lease state machines in design. A subsequent14-item test preflight led to the complete failure matrix, canonical fixture associations, real reason codes and independent server assertions. The last3 preflight concerns were addressed: fresh photo-cap setup revisions, worker late-response test without forcing login interception, and bounded manifest-churn recovery. Root additionally tested first offline editing after list-only preparation. These were advisory preflights; there was one formal Gate3 verdict, APPROVED.

Earlier browser run with source drift/download-await error is excluded from gate evidence. Repeated focused RED runs followed concrete test/contract corrections and the explicit RED-new/GREEN-existing declaration needed by the harness. Full local suites were never run. Raw logs remain external and are not copied into Git.

## Gate4 authorship

Separate sol/low `/root/offline_core_executor` owns field-offline engine modules; `/root/offline_surface_executor` owns UI/server read adapter/SW integration. Root retains tests/specifications. No exceptions to independence or server append-only history.

## Incremental A8/A9 Gate3 — 2026-11-27

Verdict **APPROVED**, independent `/root/offline_gate_reviewer` (sol/low), no findings. Root authored all changed contract/test artifacts; AuthController remains unchanged in this reviewed RED candidate. Original approval/evidence above are preserved.

- Candidate `49422aa41292b42defbce200f77844207c3d6789c68cd51f2d94fe440a0f9703`; same base2ea60f1d.
- Package `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T064956Z-271dfdf3c2/package.json`.
- Snapshot patch SHA256 `4dc552e2e1acc3b911d63817a5422e3fe4f802b27660863b9902ce4201aea7d6`; restored source `/Users/antropophag/.local/share/fmonitor-2/reliable-field-offline/reauth-gate3-source-20261127`.
- Plan SHA256 `5da0a91fff7a90b4003f9368b3a75db700801beda1500aba10ff5f325854ccef`; required-context SHA256 `6bfbf14b0955aa3e322cd680b533799eba21e95fbe758c80b5e7e62c58065dd6`; context-manifest SHA256 `c8eff408fc8c74800861d880f45911a5dd680be3b1973472ad36017b5224a89b`.
- Exact six outcomes retained in external `reliable-field-offline/reauth-gate3-results.json`: HTTP/browser INTENDED_RED; worker/replay/mobile/inspection GREEN. HTTP fails precisely at missing explicit reauthentication GET200 versus303; browser fails measured A9 geometry/visible check and missing recovery link, while the other21 scenarios pass. Inspection preserves exactly11 server facts.

Approved correction: preserve ordinary authenticated GET redirect, expose exact scalar `reauth=1` using the existing form, and route every credential POST through unchanged CSRF/email/rate-limit/password/session/safe-return controls. Provide explicit recovery links without deleting local data. Restore incumbent visible checks/state classes, readable warning/hero geometry, compact routine queue, and photo affordances at320/390/800/1366. No new auth store/token, domain writer, UI redesign, or hardware claim. Final independent review remains required.

## Independent A3/A9 restored-status delta — 2026-11-27

APPROVED by independent `/root/offline_gate_reviewer` (gpt-5.6-sol/low); root authored tests, no reviewer implementation. Candidate `f621adc69931d333798e531c989733ca8b8665ce7799964b2b34c79d346c58e2`; package `20261127T071345Z-77910a3418`, snapshot patch `ca03a270eef7f6b66eddcbea43ab3d1f8b98e447511a216e1071c43b008a0324`, plan `81fb7397838cd1b2c745a6ac0dcb223ec0567cf6c6b85a46decd8f2ecf10afbd`. Base remains2ea60f1d. Findings none.

Public DOM observation exposed a false synchronized label after full offline browser restart with two durable pending actions. Root adds document-start observation with misleading navigator.onLine=true and actual offline transport. Exact RED source `d9fad6c5059149120b394642f3036f2bdfc0a3c0d313ba03c188edcebf5864b0`: record `1795763399738820000-d56cefba0fd44f49af285cca2ae2d8ba`, all23 cases executed,22GREEN and sole intended A9 false-synchronized failure. Current candidate differs only by same-turn measurement of the primary mobile-list row/rail; main browser test bytes unchanged. Mobile current GREEN record `1795763634378166000-86f9b9f683014f13bee9734a347e88a0`; thresholds unchanged, fonts settled before geometry. Earlier mobile GREEN record `1795763401003125000-f73aaedf0e2d47b7877d5ea6db693b85` retained. Full records are under external delivery-harness/records; package/snapshot under external delivery-harness/packages.

Approval is limited to this established A3/A9 correction and test stabilization; final code review and exact-source CI remain required. Prior broader reviews/RED lineage are preserved above.

## Independent A9 restored-status wrapping delta — 2026-11-27

APPROVED by independent `/root/offline_gate_reviewer` (sol/low), findings none. Candidate `be080fa88a0181be146a1df9ab9fdf73919321003176787cdd79d517617631a2`; package `20261127T072318Z-1582ab34b0`; patch `42dc161b8cfb0d4e394275687e0171adbd8e19e77ffd8aaaa7b900bd14891ffd`; plan `0f7736a5ba1d1c0869c1fe092ff1c3051cb9a8abf46390f17d4de73633264f45`. Prior candidate c96f6c completed20/20 selected checks and23/23 browser scenarios.

Root screenshot inspection observed a truthful but long pending status after full process restart. A copied isolated cached-profile public-DOM replay of that exact observed copy measured horizontal widths320→453,390→453,800→948,1366→1366 with nowrap. This is explicitly presentation-state replay evidence, not a fabricated end-to-end RED. External evidence: reliable-field-offline/restored-layout-replayed-state-red.json and probe-restored-geometry.cjs, alongside the original field-restored-checklist screenshots in the focused run artifacts. Root adds the same <=innerWidth+1 assertion already used by the first offline journey to the restart screenshot loop. No transport, persistence, state setup or limits changed. Reviewer explicitly accepted this bounded lineage without repeating all23 solely for CSS RED; corrected browser run remains required.

Approved correction: concise equivalent pending copy and constrained status wrapping, preserving accessibility and every distinct A3 state. Reviewer separately approved neutral English error/DOM marker renames for two architecture SQL-regex false positives, with no SQL/checker/baseline or protocol changes. These are not final Gate5 approval.

## Complete CI correction Gate3 — 2026-11-27

APPROVED by independent `/root/offline_gate_reviewer`, sol/low. Candidate `ff67bc428c72cee203f62c06b77a418639b6d7e6f7ed7159f61ae35463f63ba6`; prepared reviewer package `20261127T085911Z-63595e7bb2`, reconstructible snapshot retained externally. All11 failed CI consumers and complete failed-job logs of run45962318981 were inspected before correction. Root owns test/spec changes; implementation remains separate. Findings none after corrected A02/A12 label and real preparation-adapter scope/cancellation oracle.

The eight exact-source outcomes are four intended RED (current worker A02/A12 scheduler/install, first-generation notice, minimal help asset isolation, browser attempt adapter/accepted DOM token) and four GREEN controls (HTTP, replay, mobile queue, inspection). Main browser executed24 cases:22PASS and exactly two intended failures, missing common nonempty attempt scope and incumbent accepted DOM ok token. Controlled network drop and actual53-RPC preparation reached the intended seam. All other prior assertions remain.

- tests/Yii2/production_performance_sw_001_test.mjs: INTENDED_RED; `1795769703708656000-38902846fcac4ce5ad6179de785613ed.json`
- tests/Yii2/reliable_field_offline_worker_001_test.mjs: INTENDED_RED; `1795769708460942000-3472716c148b4fb7a4527bb9a0083f99.json`
- tests/Yii2/runtime_help_001_test.py: INTENDED_RED; `1795769712394975000-cc4fdda028f84f4aa7bcbb1f62df5e59.json`
- tests/Yii2/reliable_field_offline_001_test.php: GREEN; `1795769703708652000-18c1f3ee61ab461aba807486593b4c1b.json`
- tests/Yii2/yii2_checklist_operation_replay_001_test.php: GREEN; `1795769713933338000-eb53eed034c24227a26c21b8a46e7dd2.json`
- tests/Yii2/yii2_construction_control_mobile_list_browser_001_test.php: GREEN; `1795769734114866000-54752014cf7e4d3c91de44c5df094bbe.json`
- tests/Yii2/yii2_inspection_browser_001_test.php: GREEN; `1795769749063466000-df997e5aac9f4b33bc3613bac5132e1d.json`
- tests/Yii2/reliable_field_offline_browser_001_test.php: INTENDED_RED; `1795769703708646000-735df803a2714f79b903c01ecd0f2b0b.json`

Approved coherent implementation batch: individual-RPC scopes/cancellation, worker-global two-slot subscriber scheduler/coalescing/completion memo within attempt/fresh next attempt, foreground priority, actor/epoch/generation/logout/stale-finally fences; explicit no-store generation install; first/same-generation notice suppression; lazy shell-only asset dependencies; incumbent accepted DOM token. Golden fixture reconciliation may update only exact hashes for reviewed asset bytes and retain MIME/cache/security expectations; final source must include/review that delta. This is Gate3 approval, not final release approval.

## CI correction: failed same-generation install — 2026-11-27

Independent reviewer `/root/offline_gate_reviewer` (sol/low, neither test nor implementation author) APPROVED the bounded A8 regression and staging disposition. Candidate `d9118743869657bb098afe6e581fd2969580782e5aa6085658d208d470f651f6`; root package `20261127T092304Z-a26c5ee8e5`; exact intended RED record `1795771446750848000-45492596a032405d9a22f01398422fa6`, source drift false. The public worker install/fetch sequence proves a failed reinstall of the same generation must preserve offline status 200 and exact previously cached bytes. Before correction the offline fetch throws `unreachable`. Root authored the regression; core executor authors the correction. Install uses attempt-specific staging, failed staging alone is removed, and active complete same-generation bytes remain untouched. Staging is excluded from public asset fallback. No findings; implementation requires independent final review.

Bounded A8 follow-up: independent reviewer APPROVED two deterministic CacheStorage fault cases for candidate `b3e7d012960a5f6b40f39ccef57b882a183d9a17f8679929b2be7901862cd74f`, package `20261127T093805Z-650f3987cf`. Exact intended RED `1795772291767155000-57fd0a37a80643cf8758bd3e2032a55a` captures both cross-attempt staging deletion and old-worker access to a partially copied generation; source drift false, no setup failure. Root authored tests; executor correction must keep attempt-owned staging cleanup and durable complete-publication gating, preserving explicit legacy versions. The prior implementation precheck was CHANGES_REQUESTED for these two findings; neither was waived. Final implementation approval remains pending.

Staging reclamation follow-up: independent reviewer APPROVED candidate `a5273f2ce175a1da99bbde24443b3ee36a8cce4809626692acaa9b28ecc07513`, package `20261127T094643Z-3fffe58591`, exact intended RED `1795772805044594000-aaef3f1a54ee459b873d19cc109940fc` (source drift false). After two overlapping installs have settled and activation successfully validates/publishes the generation, one same-build staging cache incorrectly remains. The approved assertion requires zero, followed by unchanged exact active bytes. Cleanup is restricted to the successful activation publication boundary; installation retains attempt ownership and failed publication does not reclaim other attempts. Static review inventory was otherwise closed with no additional blockers.

Downstream consumer completion: independent reviewer APPROVED calendar fixture baseline restoration (explicit top reset after disclosure and before independent day selection; all original no-hash/scroll<80/date/ARIA/agenda assertions unchanged). External probe proves mobile y128 remains128 across AJAX selection; the previous fixture itself introduced that scroll. The sidebar host retry exposes a real standards RED `1795773109840984000-6d76909fd2544ff980569e77eef57b96`: new handwritten SVG in checklist.js is unclassified. Approved correction removes it in favor of an aria-hidden decorative checkmark with unchanged checked/unchecked and check-all semantics. Root preserves strict icon audit; surface executor owns implementation. Production-browser and sidebar consumers own inner provisioning and must run from the host; cutover requires the public shlz-ui export root. These setup failures and successful host retries remain external, never erased.

A4 abandoned-lease recovery: independent reviewer APPROVED candidate `f002bf7094607515ccb6ddf88bca17717ad061bfb683e260d03f39b8d3b2f886`, package `20261127T100158Z-a436b44e73`, exact intended RED `1795773735939288000-629253cdbbc348988eefca43df7d4a71` (source drift false). Full24 scenarios:23PASS, sole deterministic failure is automatic exact uncertain-photo recovery after a realistic foreign-owner lease expires. The fixture writes an existing lease before opening the reauthenticated checklist, preserves the12-second bound, observes the actual POST timestamp and forbids sending before lease expiry. Static diagnosis: failed acquire returned without scheduling any successor. Approved minimal correction schedules one bounded retry through the existing timer/scheduler and actor fences, with no dispatch before acquisition and cancellation on close. This closes established A4 bounded recovery; no test timeout was relaxed.
