# Independent final product code/spec review — offline minimal UI and object general photos

- Reviewer: independent `/root/delivery_reviewer` (authored none of the reviewed production code, specifications, or tests)
- Review stage: final product code/spec review before commit/PR/CI
- Base: `838d935af4da76d93eb7900b1416b8eec457f13a`
- Reconstructible source: `/tmp/fm-main-final-v2-source/source.patch`
- Patch SHA-256: `6689a0f2793713185f55c3a66b3b1fdf25371ef09101609d74c7872f5e03f925`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T163620Z-152b6ffec2/package.json`
- Contracts: `docs/operations/compact-offline-indicator-20261127.md`, `specs/OBJECT-GENERAL-PHOTOS-001.md`, inherited `specs/RELIABLE-FIELD-OFFLINE-001.md`

## Verdict

**APPROVED**

No unresolved product, specification, implementation, security, history, replay, accessibility, or maintainability findings in the frozen source.

## Review conclusions

- Section 0 is admitted only for `photo_uploaded` and `photo_revoked`; every other checklist command retains the established section boundary. Existing authorization, CSRF, case-state, file validation, replay, conflict, dedupe, cap, immutable audit, protected read, and reasoned-revoke paths remain shared and enforced.
- General photos are isolated by installation case and bucket, keep append-only upload/revoke history, preserve exact receipts and actor/device attribution, do not satisfy section-photo requirements, and do not change checklist progress.
- The UI uses the existing durable photo writer and actor fence. It provides camera/multi-select controls, a separate object-photo strip, shared gallery inclusion, responsive accessible labels, quota recovery, offline restart, automatic same-ID sync, accepted-photo revoke, and read-only disabled controls without a parallel cache or anonymous identity.
- The A6 Store correction is appropriately narrow: only a queued, unattempted section-0 upload uses single-node logical discard. It rejects attempted or frozen-wire roots and transitive descendants atomically, deletes exactly the selected blob, preserves every unrelated row/byte/wire field, and splices only direct successor predecessor links. Established non-general discard behavior is unchanged.
- Checklist minimalism removes routine queue walls and manual sync while retaining automatic retry triggers, truthful one-line state, visible sync color, full accessible/title context, actionable conflict/auth/access/storage recovery, unsaved-photo recovery, exact discard confirmation, and no false synchronized state.
- Internal section 0 is presented consistently as “Фото объекта”; it no longer leaks “Раздел 0” in actionable recovery cards.
- Offline preparation renders the confirmed prepared count independently of list filters, keeps all four truthful states, has no disclosure/retry/install control, and preserves reload-based preparation recovery and cancellation fences.
- PWA installation is delegated consistently to the browser. Same-origin `manifest-src` is added on the Yii response boundary while external manifests remain blocked and all other CSP directives, worker scope, authentication, caching, and fallback response policy remain constrained.
- Help and normative text now agree on automatic sync, browser-native installation, object-general-photo limits/history, and local photo cancellation.
- Existing consumer updates follow surviving public seams: automatic retries replace the removed sync button, reload replaces the removed preparation retry control, filtered totals use the rendered footer, and mobile layout allows the compact readiness row. The reviewed changes retain their original replay, timeout, cancellation, filter, count, security, history, and no-fact assertions.

## Review findings disposition

1. The inherited PWA contract contradiction was removed; browser-native installation and absence of an in-app install action are now consistent.
2. The checklist banner now carries full nonempty `aria-label`/`title` context for initial loading, all connection states, and initialization failure while keeping the owner-approved short visible copy. Auth recovery explicitly presents “Нужен вход” with “Войдите снова. Данные сохранены” accessibility context.
3. Section-0 general-photo recovery text now uses “Фото объекта”, with regression coverage for conflict/auth/storage presentations.

## Verification reviewed

- The initial bounded inventory contained 15 consumers: 11 GREEN, three fully inventoried regression failures, and one drift-marked UNKNOWN architecture result. No failure was hidden or treated as green.
- Every initial failure was corrected without weakening its substantive oracle: the 25-scenario offline browser, server filtering, and public cutover/CSP/hash consumer.
- The final frozen correction inventory is **7/7 GREEN** in `/tmp/fm-main-corrected-results.json`: the three acceptance consumers, full offline browser **25/25**, construction-control filtering, public production cutover, and architecture check. The final architecture record is `1795797441809190000-44e47cf2bcde4b9dbcd31aa62019b7fa`.
- The three core acceptance records prove HTTP/history/isolation, actual browser A4–A6 behavior, and compact/native-manifest/checklist-minimal behavior on exact source.
- A separate native actual-general-browser diagnostic passed with record `1795797472925823000-668f3ab423d745a6aef359528bb04331`. Screenshots at 320, 393, and 1366 pixels show the controls fitting one row and the general strip remaining separate. The visible error toast is the deliberate quota-failure recovery fixture, not a routine operation panel.
- The source remained frozen during the completed checks. No canonical full `make test`/`make verify` suite was run locally, consistent with the owner decision.

## Remaining delivery gates

This approval covers the reviewed frozen product source. It is not evidence of an open PR, exact-source GitHub CI success, merge, home assembly, deployment, hardware camera/FortiClient/iPhone behavior, or live smoke success. The planner-selected full exact-source CI, normal PR/merge workflow, separately reviewed home binding/assembly, fresh live preflight, and post-cutover smoke remain required.
