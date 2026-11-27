# Gate 3 test review — offline/checklist UI and object general photos

- Reviewer: independent `/root/delivery_reviewer` (authored none of the reviewed specifications, tests, or implementation)
- Review stage: Gate 3 only
- Base: `838d935af4da76d93eb7900b1416b8eec457f13a`
- Reconstructible source: `/tmp/fm-main-gate3-source/source.patch`
- Patch SHA-256: `b65bf89a88b3c8805e03f0ddca296696b4d15c8b56feceb703be50d76e1ef565`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T153605Z-1082218ead/package.json`
- Contracts: `docs/operations/compact-offline-indicator-20261127.md`, `specs/OBJECT-GENERAL-PHOTOS-001.md`

## Verdict

**CHANGES_REQUESTED**

The three exact-source RED records are valid intended failures: general HTTP is rejected with 422 at the new section-0 seam, the browser has no general controls, and the manifest is blocked while the already implemented presentation checks pass. The test set is deterministic and uses public HTTP/browser seams, but it is not yet an acceptable complete executable specification for the sensitive slice.

## Findings

1. **HIGH — The browser cap scenario contradicts the normative same-bucket hash deduplication rule.** `specs/OBJECT-GENERAL-PHOTOS-001.md` A2 says hash dedupe applies within the current bucket. `tests/Support/object_general_photos_browser.cjs:35-38` uploads the same bytes ten times to general bucket 0 under different filenames and requires nine additional blobs plus an over-cap rejection. A conforming implementation may deduplicate those identical bytes, so this test would reject correct behavior or force incorrect behavior. Generate distinct valid image bytes/hashes for each cap slot, and add a separate assertion that an identical hash in general is deduplicated/rejected without consuming another slot while the same hash remains allowed once in section 1.

2. **HIGH — The quota-recovery fixture re-enables failure after reload, so the later cap scenario cannot reach its asserted state.** `tests/Support/object_general_photos_browser.cjs:9-13` sets `globalThis.generalQuota=true` in an init script on every document. Line 23 disables it only in the current document; line 29 reloads, which runs the init script and restores `true`. The uploads at lines 35-38 will therefore abort as quota failures instead of filling the cap, regardless of the implementation. Persist the fixture's recovery switch across reload (for example with a session-scoped flag read by the init script and set before retry), while keeping the first transaction-abort assertion intact.

3. **HIGH — Cross-object mutation isolation is not exercised.** A2 requires that a cross-object photo command cannot move or affect a photo. `tests/Yii2/object_general_photos_001_test.php:18` checks only a cross-object GET, while all upload/revoke commands use object 4512. Add POST attempts against another object for both a section-0 upload and revoke (including a foreign `photoId`), assert the specified denial/rejection, and assert unchanged operation/photo/history rows.

4. **MEDIUM — The new section-0 audit/receipt contract is mostly assumed rather than asserted.** A2 explicitly inherits actor, device timestamp, immutable payload, byte hash, revision, and confirmed receipt semantics. `tests/Yii2/object_general_photos_001_test.php:13-39` checks revision counts and operation kinds, but never verifies the persisted actor/device/time/hash/payload or the response/receipt identity for section 0. Existing section-photo tests are useful regression consumers, but they do not prove that the newly admitted section-0 path preserves these fields. Assert the new upload and revoke operation/photo rows and returned receipt/projection fields, including the exact client operation ID, actor/device attribution, timestamps, hash/payload, section 0, and accepted revision.

5. **MEDIUM — A4's general-photo removal path and multi-select behavior are not covered in the browser.** `tests/Support/object_general_photos_browser.cjs:17` only checks that `multiple` exists, and lines 20-42 enqueue single files; it never selects multiple images in one action. It also never exercises queued general-photo discard or accepted general-photo revoke/reload, although A4 requires gallery return and local queued delete/revoke to follow the existing rules. Add browser assertions for one multi-file selection, confirmation before discarding an unattempted general photo, inability to discard an attempted one, and an authorized accepted general-photo revoke disappearing from the general strip/gallery after reload while history remains server-side.

6. **MEDIUM — Authorization and rejection coverage does not prove the full section-0 boundary.** `tests/Yii2/object_general_photos_001_test.php:22-25` covers malformed section IDs and one logged-in reader, but does not exercise unauthenticated command handling, invalid CSRF, invalid/corrupt file data on section 0, closed/non-working case admission, or revoke of a missing/already revoked/foreign photo with no appended facts. A1 explicitly preserves rights, CSRF, file validity, and no-fact rejection; A2 requires revoke to target a valid photo. Add bounded section-0 cases for these paths, with reason/status and unchanged-history assertions. Where a shared existing consumer is intentionally relied on, bind it explicitly in the verification input/plan and demonstrate that it reaches section 0 rather than only ordinary sections.

7. **LOW — Accessible-name acceptance is only structural.** `tests/Support/object_general_photos_browser.cjs:16-17` finds the controls and capture/multiple attributes, but A4 requires full accessible labels for “Снять”, “Выбрать”, and “Все фото”. Assert their accessible names (and disabled state under a denied-permission fixture) at the rendered browser seam.

## Accepted aspects

- RED provenance is exact-source and failures occur for the intended missing behavior, not setup failure.
- The manifest consumer checks all affected route policies, same-origin discovery, valid icons, cross-origin blocking, and preservation of other CSP directives.
- The compact/offline consumer checks all four readiness states, exact truthful counts, passive accessible indicator behavior, native install prompt non-interception, responsive layout, recovery-only queue exposure, sync color separation, pending-state truthfulness, confirmation before local discard, and attempted-operation protection.
- The general-photo browser consumer uses a real service worker/IndexedDB path and covers quota rollback, retry, operation-ID stability, offline reload, automatic replay, projection isolation, responsive layout, gallery inclusion, and section-strip exclusion.
- Profile registry additions map the three new consumers to integration/browser lanes; existing consumers were adapted to automatic synchronization without deleting their core replay/recovery assertions.

No local full suite was run.

## Root correction disposition — awaiting independent re-review
1. FIXED: cap uses11 distinct valid PNG hashes; HTTP separately proves same-hash general dedupe and same bytes allowed insection1.
2. FIXED: quota recovery flag persists in sessionStorage across reload.
3. FIXED: existing ID POST against another working object conflicts; independent foreign upload stays isolated; foreign/missing/current-revoked/cross-object revoke rejects without facts.
4. FIXED: upload/revoke operation identity, device, actor, deviceTime, revision, payload/hash/bytes/name and receipt projection/serverReceivedAt are asserted.
5. FIXED: browser multi-file selection, local discard confirmation, attempted-operation protection, accepted revoke/reload/gallery count and persisted upload+revoke history.
6. FIXED: guest, reader, invalidCSRF, corruptfile, closedcase, missing/foreign/alreadyrevoked no-fact matrix; controller status expectations corrected to rejected403.
7. FIXED: exact input accessible names and reader-disabled UI assertions.
Prior source snapshot retained; corrected candidate requires independent re-review, not approved by this disposition.

## Second review / root complete-matrix rebuild
Independent v2 CHANGES_REQUESTED retained `/tmp/fm-main-gate3-v2-verdict.md`: ambiguous cross-object revoke reused accepted ID while actor lacks formal revoke assignment forothercase. FIXED: freshID now proves403 ACCESS_DENIED; same authorized object + changed revoke reason proves409 OPERATION_PAYLOAD_CONFLICT; authorized upload target-change also asserts exact conflict code. Spec explicitly preserves auth-before-replay priority. No application authorization expansion.

Complete matrix rebuilt: A1 upload/negativeID/photo-only0/guest/reader/CSRF/corrupt/closed/no-facts; A2 exact audit/receipt/hash/dedupe/separatecap/uploadtargetreplay/revokepayloadreplay/missingforeignrevokedtargets/history; A3 noitemprogress/nosectioncompletion; A4 accessible camera/multiselect/gallery/read-only/queueddiscardconfirm/attemptguard/acceptedrevoke/reload/320–1366; A5 atomicquota rollback+download+retry/offline-reload/sameID autoaccept/localbucketcap. All map to two focused public consumers. CSP/compact/readiness/sync14states covered separately. Root re-read full tests against owner/spec and actual public admission ordering before this third dispatch.

Full removed-selector consumer search before re-review also reconciled preparation reload recovery (no deleted retrybutton), filtered total from existing footer, mobile single readiness row visibility. Corresponding assertions retain counts/scope cancellation/filter semantics; no runtime behavior added and no failure bypass. These consumer deltas are included in the complete third review candidate.

# Gate 3 third review — offline/checklist UI and object general photos

- Reviewer: independent `/root/delivery_reviewer` (authored none of the reviewed specifications, tests, fixtures, or implementation)
- Review stage: Gate 3 only
- Base: `838d935af4da76d93eb7900b1416b8eec457f13a`
- Reconstructible source: `/tmp/fm-main-gate3-v3-source/source.patch`
- Patch SHA-256: `7c2a8300da9a7c931ead1d8058ae18cfb89f13212f449a84d05ba2ba42cdeca5`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T155133Z-7d848f9186/package.json`
- Contracts: `docs/operations/compact-offline-indicator-20261127.md`, `specs/OBJECT-GENERAL-PHOTOS-001.md`

## Verdict

**APPROVED**

No unresolved Gate 3 findings.

The corrected specification makes authorization-before-replay explicit without widening rights. The tests now distinguish the two relevant cases correctly: a fresh foreign revoke is denied with exact `ACCESS_DENIED`, while an authorized accepted operation ID reused with a changed payload is an exact `OPERATION_PAYLOAD_CONFLICT`; the authorized upload target-change has the same exact conflict oracle. Every rejected path retains an unchanged-facts assertion.

The complete A1–A5 matrix is observable at public HTTP/browser seams: section-0 admission and exclusivity, rights/CSRF/file/case rejection, object isolation, immutable audit and receipt data, replay and hash dedupe, independent caps, append-only revoke history, progress isolation, accessible responsive controls, multi-select, gallery and strip separation, quota rollback and recovery, durable reload, same-ID automatic synchronization, confirmation-gated local discard, attempted-operation protection, accepted revoke/reload behavior, and read-only disabled controls.

The three fresh exact-source records are valid intended RED evidence:

- HTTP fails at the missing section-0 admission with 422 `OPERATION_REJECTED`.
- Browser flow fails because the general-photo controls are absent.
- Manifest checks fail because same-origin manifest CSP is absent/blocked, while all implemented compact readiness, accessibility, native-install non-interception, checklist-minimal, recovery, and truthful sync-state scenarios pass.

The earlier cap/hash contradiction and quota-reset fixture defect remain fixed. The added existing-consumer updates use surviving public UI: page reload replaces the removed preparation retry control while preserving bounded cancellation/recovery assertions; filtered totals come from the rendered list summary; and the mobile consumer permits exactly the compact readiness line before the toolbar. These changes do not weaken the underlying count, filter-reset, scope-cancellation, responsive-layout, or queue-state assertions.

This approval is Gate 3 test approval only. Implementation, focused GREEN evidence, exact-source CI, and the independent final code review remain separate required gates. No repository source was edited and no local full suite was run.

## A6 correction Gate3 and GREEN — 2026-11-27

Real browser found general first-photo discard cascaded through all ten queued blobs. Root extended A6/tests; separate `/root/indicator_executor` implemented Store splice. First independent correction review CHANGES_REQUESTED for transitive/root guards and full preservation oracle; both fixed in v2. Independent `/root/delivery_reviewer` APPROVED `/tmp/fm-main-discard-gate3-v2-verdict.md`, snapshot `/tmp/fm-main-discard-gate3-v2-source`, patch SHA2920f754a8eec7b2a6ea0fb7b70812b727881b6efbb94b64a371e89c7ae5722e, package20261127T162147Z-f5829c9ef4. Actor epoch and attempted/frozen-wire depth0/1/3 use public Store; full rows/metadata/order and actual Blob bytes compared. Valid RED0!=9 retained in external correction-results.

Implemented acceptance3/3 GREEN `/tmp/fm-main-green-results.json`: HTTP1795796620703070000, browser1795796638083582000, compact1795796661850840000. Safe single discard now passes along with offline restart/replay/revoke, cap/isolation/recovery and compact statuses.

Bounded cutover consumer first reported8 stale expected values: six normal WebResponse manifest CSP headers plus checklist.js and generated worker hashes. Root updates only those reviewed asset/header expectations, preserving fallback error CSP, MIME/cache/security/routing/DB isolation assertions. Initial failure record1795796635819039000 retained. Full local suite remains prohibited; bounded direct consumers locally, full matrix in exact-source CI.

## Final focused correction inventory

Root disposition and complete failure inventory: `/tmp/fm-main-final-dispositions.md`; initial15 records `/tmp/fm-main-focused-results.json` retain11GREEN,3REGRESSION_FAILURE,1source-driftUNKNOWN. Final corrected source7/7GREEN `/tmp/fm-main-corrected-results.json`, including browser25/25, filtering, cutover and full architecture qualification. Native general-photo browser diagnostic PASS1795797472925823000, actual320/393/1366 screenshots retained outsidecheckout; root inspected320/1366, compact row and separate general strip fit. Latest CUA window unavailable; earlier authorized local preview remains delivered. Final independent code review in `reviews/code/OFFLINE-MINIMAL-GENERAL-PHOTOS-001.md`; external source patch6689a0f2793713185f55c3a66b3b1fdf25371ef09101609d74c7872f5e03f925. Full exact-source CI and home delivery remain pending at this checkpoint.
