# FINAL CODE + UI finish review — ADDRESS-REGISTER-001

## Verdict

**APPROVED. UI disposition: ship.**

Independent final review of corrected candidate source `27e2bd39cbeb3f6cc67a0c81e94eb4bfa109689183ca7977378e7ca455315d4b` / executable source `1f787e26446c136971420ea62af6858918ad868deb538b07fd87b51f7a9410b3`. I authored none of the candidate code or tests. The corrected candidate resolves both findings from the first review without weakening the architecture ratchet or changing the established visual system. Focused HTTP/browser verification and the canonical architecture check pass on the corrected candidate.

The first review of source `e0d906c095867f2c3c2d6008ca5619e8cd430d24cd5990984edfc8a10205c06a` / executable source `f2a06860196f158247153bcef88c84f40f2a03264ee611c4a9481d56b2ff39a7` was **CHANGES_REQUESTED** for nine SQL-ownership violations and mobile action/navigation overlap. Both findings and their corrections remain recorded below.

Formal preimplementation Gate 3 and full exact-source CI were owner-deferred and remain **UNKNOWN**, not GREEN. This final-review approval does not convert those deferred checks into formal admission.

## Contract coverage

- **A1 authorization/HTTP:** covered in the implementation and focused HTTP evidence: canonical `objects.read` is checked server-side for GET and preference POST; actor identity comes from Yii identity; native CSRF, guest return, 403, HEAD, 405 and `no-store` paths are exercised. No client-controlled actor identifier is accepted.
- **A2–A3 projections and bounded reads:** the candidate produces one paginated row per legacy object, limits pages to 50, escapes wildcard search input, rejects malformed filters and batches page enrichment. Focused HTTP evidence covers effective edits, canonical dates/status, current applied installer/control assignments, pending-composition exclusion and stage buckets. The read path does not expose arbitrary legacy columns or write process facts. The correction places the MariaDB reader under the registered `InstallationProcess` persistence owner and composes its queue projection through `InstallationProcessFactory`; the canonical architecture check now passes.
- **A4–A5 columns/UI:** the closed catalogue, confirmed default set, address-first invariant, 80–640 px width bounds, contained wide desktop table, sticky desktop header/address, filtering, pagination, draft/cancel/reset/save behavior, keyboard move controls, Escape and focus restoration are present. Long values wrap and retain a title. The corrected mobile editor keeps its sticky action footer above the fixed shell navigation and hides the feedback FAB while the panel is open. Browser assertions cover save/reset/cancel geometry at 390 px.
- **A6–A7 preferences:** the file owner enforces canonical authorization, isolated integer actor directories, exact closed-state validation, revision CAS, immutable version files, per-user locking, restrictive permissions, bounded snapshots, symlink/nonregular rejection, atomic temporary-file publication with flush/fsync/rename, no-op replay and fail-closed corrupt state. Focused seam and two-browser evidence covers isolation, persistence, races, stale conflict, cancellation and reload.
- **A8 delivery:** root-authored contract/tests and separate backend/UI executors preserve authorship separation. Focused preference, corrected combined HTTP/browser, planner and canonical architecture checks are GREEN on their recorded candidates. Formal Gate 3/full CI remain deferred, and no full-CI/admission conclusion follows from this review.

## Visual/interaction evidence

Inspected `qa/desktop.png`, `qa/desktop-columns.png`, `qa/mobile.png` and `qa/mobile-columns.png` at original resolution. The desktop result fits the existing FMonitor Operate shell: clear hierarchy, dense Excel-like table, contained horizontal scrolling, readable column widths, pinned identity/header behavior, and restrained shlz-ui/Golos styling. The settings panel exposes selection, order and numeric width without hiding the table context. Search, stage filtering, count and pagination are easy to scan.

At 390 px, the toolbar stacks cleanly, the table remains contained, and the horizontal data model is preserved rather than collapsed into cards. Keyboard opening, Escape closing, focus return, cancel/reset/save, two-session persistence and stale-conflict messaging are exercised by the browser run. The corrected `qa-corrected/mobile-columns.png` shows the complete `Сохранить`, `По умолчанию` and `Отмена` action stack above the fixed bottom navigation. The feedback FAB is hidden while editing, removing the competing control from the action zone. Exact browser geometry assertions confirm each action is inside the viewport and ends above the navigation.

The design detector reported no findings. Direct inspection of the corrected captures now agrees: the original mobile overlap is resolved without disturbing the desktop surface.

## Findings

1. **P1 — RESOLVED: the original read owner failed the mandatory SQL-ownership architecture ratchet.** Original `app/AddressRegister/MariaDbAddressRegister.php:28`, `:33`, `:36`, `:45`, `:63`, `:65`, `:72`, `:74`, and `:77` contained SQL outside a registered persistence owner; `architecture-final.log` reported nine new `sql_ownership` violations. **Correction reviewed:** the reader moved to `app/InstallationProcess/MariaDbAddressRegister.php`, receives its queue projection as a constructor dependency, and is composed through the public `InstallationProcessFactory::addressRegister()` seam used by the controller/tests. No architecture baseline or checker exception was added. `architecture-corrected.log` reports `ARCHITECTURE CHECK PASSED (8 rules)`.

2. **P2 — RESOLVED: mobile bottom navigation obscured the original column editor actions.** Original `qa/mobile-columns.png` showed the fixed navigation covering most of `Сохранить`, with the feedback control competing in the same zone. **Correction reviewed:** `app/YiiRuntime/Assets/address-register.css:44-58` adds shell-consistent bottom clearance and a sticky action footer above `64px + safe-area`, and hides the FAB only while the editor is open. `tests/Yii2/address_register_browser.mjs:35` checks save/reset/cancel bounding boxes against the navigation. The corrected browser run passes and `qa-corrected/mobile-columns.png` visually confirms the result.

No open code, contract, interaction or visual-finish findings remain in the reviewed candidate.

## Verification limits

Reviewed the complete original candidate plus the correction delta, contract/OpenSpec design, corrected role package and reconstructible snapshot, changed factory/controller/reader/tests/styles, focused logs and all four corrected screenshots. `corrected-focused.log` reports the combined HTTP/read-projection and browser checks PASS with exact executable source `1f787e26446c136971420ea62af6858918ad868deb538b07fd87b51f7a9410b3`. `architecture-corrected.log` reports the global HTTP qualification PASS and `ARCHITECTURE CHECK PASSED (8 rules)`. Earlier preference and planner evidence remains GREEN; `design-detector.json` remains empty.

The focused logs record their own source digests and are useful behavioral evidence, but no full exact-source GitHub CI result is present. Formal Gate 3 and full CI remain owner-deferred/UNKNOWN. I did not run the prohibited local full suite, deploy, edit implementation/tests, or infer production health/admission from the focused checks.
