# ADDRESS-REGISTER-ADAPTIVE-20261115 — independent final review

## Verdict

**CHANGES_REQUESTED — fix long registration-number containment, rebuild the exact candidate, and recapture the affected table state.** I authored none of the runtime, specification, or test changes. I reviewed the candidate based on `aeaca219cbe842b0c176c30e4e32da5a78d38ab7`, exact executable source `d23b322955be21e7540d449c077502c33e7a350bd9b929bb7cb05b350213fec6`, the final browser captures, and a fresh focused run of the three address-register checks. Apart from the blocking containment issue below, the change preserves the existing SHLZ visual system and introduces no new theme, token set, or competing interaction model.

## Contract

A9–A11 are satisfied. `registrationNumber` is the first catalogue/default column, remains mandatory and sticky, and links to the same object route as the address. Defaults and valid legacy v1 records project all 22 columns without rewriting legacy history. A first explicit save over legacy appends revision + 1 in the exact v2 shape; identical v2 saves are no-ops; stale saves retain CAS rejection.

The public v2 state is exactly `revision`, `visible`, and `layoutVersion: 2`. New POSTs and persisted snapshots contain no widths. Legacy records remain closed-schema, address-first, unique-known-column, complete-width-map and integer 80..640 validated; malformed, unknown, and extra-field snapshots fail closed. Authorization, CSRF, actor isolation, append-only publication, lock discipline, and 409/422/503 behavior remain intact.

The data projection now returns current installer names as structured arrays. The view escapes each name and renders one `li` per installer; null remains `—`. No comma parsing is used, so punctuation inside a real name cannot split the crew.

## Visual review

I inspected `desktop.png`, `desktop-installers.png`, `desktop-columns.png`, `desktop-filters.png`, `mobile.png`, and `mobile-columns.png` at their original sizes. Desktop has one table scrollport, a sticky header and registration column, accessible header/footer controls, and responsive type-based widths. Address, installer, engineer, and comment columns grow more than identifiers, numbers, and dates. The abbreviated technical headings remain understandable with full labels in `title`; “Грузопод.” fits without colliding with adjacent headers.

The 1-, 2-, and 5-installer rows show every complete name on a separate line, including the long hyphenated Cyrillic name. Rows grow naturally and no name is clipped, ellipsized, or hidden. The columns drawer removes all pixel controls while retaining search, visibility, ordering, reset, cancel, save, keyboard focus, and fixed-registration affordances. Filter and column drawers continue to use the public SHLZ drawer/select behavior.

`desktop-installers.png` is displaced roughly one scrollbar gutter at the page origin after the table is horizontally scrolled. This is a capture artifact: the registration and installer columns are both fully visible, all target names can be assessed, and the browser check proves the installer cell is inside the table scrollport. It does not require recapture. The mobile captures meet the explicitly basic narrow-safety requirement; desktop remains the polished priority.

## Findings

### Blocking — admitted registration numbers can paint over adjacent cells

`address-register.css:22` caps the registration column at 11rem and applies `white-space: nowrap`, `overflow-wrap: normal`, and `word-break: normal`, without clipping or another containment rule. The effective object model admits registration numbers up to 120 bytes (`ObjectDetailsFieldRegistry.php:34`), so an unbroken valid value can visibly spill across the address cell and obscure its content. The current visual fixture covers only short `REG-451201` values and therefore cannot prove containment at the actual model boundary.

Keep ordinary identifiers on one line when they fit, but provide a bounded wrap/break behavior for long valid identifiers. Add a browser assertion using a long admitted registration number that its rendered box remains inside the registration cell and does not overlap the address cell, then recapture the affected desktop table state and rerun the focused exact-source checks.

### Deployment requirement — rollback must account for v2 preference snapshots

The prior runtime recognizes only exact v1 `revision,visible,widths` snapshots. Once a user saves v2, rolling back only the seven runtime files makes that actor's latest snapshot fail closed and the register return 503. The rollout/rollback procedure therefore must keep the pre-rollout preference tree and preserve any post-rollout v2 history separately. A rollback must either use a forward-compatible rollback image or atomically restore the pre-rollout preference view while retaining the v2 files for later recovery; it must not discard or rewrite those append-only records. This is a material rollback condition, not a reason to weaken the closed-schema reader.

The fresh exact-source focused run passed:

- `php tests/AddressRegister/column_preferences_001_test.php`
- `php tests/Yii2/yii2_address_register_001_test.php`
- `php tests/Yii2/yii2_address_register_browser_001_test.php`

The run reported source digest `d23b322955be21e7540d449c077502c33e7a350bd9b929bb7cb05b350213fec6`. Supplied architecture evidence passes all eight rules and the global HTTP authorization qualification; the detector inventory is empty.

The planned exact image/source/Compose CAS and derivation from the actual current image containing recovered routes appropriately protect the code boundary, subject to the preference rollback requirement above.

## Limits

This review covers only the seven runtime files named in the bound package and the reviewed exact executable source. It does not approve changes in parallel worktrees or PRs, preference rewrites, business-data writes, deployment outside the stated seven-file derived-image plan, or admission based on UNKNOWN evidence.

Formal Gate 3 and full exact-source GitHub CI are owner-deferred and remain unapproved/UNKNOWN. I did not run the prohibited local full `make test` or `make verify`. Production-first owner authorization and the deployment guardrails are recorded context, not substitutes for those deferred gates.

## Independent correction review — 2026-11-15

### Correction verdict

**APPROVED — ship the corrected bounded candidate.** This verdict supersedes the initial `CHANGES_REQUESTED` disposition while preserving its findings and their audit trail. I authored none of the corrections. I reviewed exact source `7bb4118050b4dc85d798d0554c6d72868cff16c83b7a5ebc696e96a4894567ea` and executable source `e2f6313eea8930427c53c2d4bef6c0b63123e5c2e25885951f46e85f10f76b57` from package `20261115T194414Z-aa952481c1`.

### Long registration-number disposition

Fixed. The registration column keeps its bounded adaptive width but now uses normal whitespace and `overflow-wrap: anywhere`. The regression fixture persists a literal 120-character unbroken registration number, confirms the full value is rendered, and uses DOM Range client rectangles to prove every painted text fragment remains inside its own table cell. This directly detects the glyph spill identified in the initial review. The corrected browser run is GREEN at executable source `e2f6313eea8930427c53c2d4bef6c0b63123e5c2e25885951f46e85f10f76b57`.

### Rollout disposition

Fixed in the prepared rollout procedure. I inspected `rollout.py` without executing it. It stops the web container before replacing the PHP image or exposing the v2 reader, verifies the candidate image, complete runtime hashes, host hashes, readiness, health, unrelated service identities, and Compose CAS while traffic remains stopped, and permits v1 rollback only inside that unexposed interval. It sets `exposed=True` before restarting web traffic. Any failure from that point retains the candidate image and persistent preference state and reports `POST_EXPOSURE_VERIFICATION_FAILED_FORWARD_REPAIR_REQUIRED`; it cannot automatically downgrade to the v1 reader after a v2 save may have been admitted. The pre-cutover preference archive is additional recovery evidence and is not used to rewrite or discard accepted v2 history.

The wrapper and generated remote Python compile successfully. This approval covers the reviewed control flow; no production deployment had occurred at review time.

### Corrected visual review

I inspected all six refreshed `qa-corrected` captures at original size. The desktop table, installer state, filters, column drawer, and basic mobile states preserve the earlier approved visual behavior. The 1-, 2-, and 5-installer examples remain complete and line-separated. The installer capture now starts at window origin before capture; its table-scroll position still exposes the intended installer column without invalidating the surrounding page state. No new visual regression or design-system divergence is present.

### Remaining limits

No code, UI, persistence, security, compatibility, or prepared-rollout findings remain at the reviewed scope. Formal Gate 3 and full exact-source GitHub CI remain owner-deferred and UNKNOWN; this correction verdict does not convert either into approval or GREEN. The local full suite remains prohibited and was not run.
