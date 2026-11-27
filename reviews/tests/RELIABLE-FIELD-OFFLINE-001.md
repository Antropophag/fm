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
