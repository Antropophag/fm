# OFFLINE-CONSTRUCTION-CHECKLIST-UI-001

Owner request 2026-11-28: test offline navigation and retain the ordinary construction-control interface; remove the checklist closeout link while retaining its 15% explanatory layout; center general-photo button labels.

## Scope extension — owner 2026-11-28

Owner explicitly included cache budget/reserve, expiring substitution objects and safe cleanup in this pass. FIELD-CACHE-RETENTION-001 supersedes the storage exclusions below; UI criteria remain. Seven days explicitly confirmed. Previous Gate3 findings on UI coverage are retained and corrected in the combined candidate.

## Bounded scope and acceptance

This is an ordinary presentation correction of RELIABLE-FIELD-OFFLINE-001 A2/A9 and OBJECT-GENERAL-PHOTOS-001. The public `/pilot/construction-control` route, offline alias and PWA start retain the construction-control title, familiar search/filter toolbar, responsive table/rows and own-object checklist links when the server is unreachable. Offline data remains the existing prepared own-object set, independent of the last online filter/page; do not cache arbitrary authenticated HTML or include other engineers' objects. Use the existing construction-control styling/components. A quiet offline explanation states that only saved own objects are available. Online-only controls must be absent or disabled with an explanation; do not invent activity, shipment or engineer facts missing from prepared records. Existing saved records remain usable without migration.

A1: real service-worker navigation with networking disabled opens construction-control layout, never the separate heading “Объекты без сети”; reload/new tab and checklist return stay functional. Flexible search over all prepared records, entrance, completed display/filter and accurate shown/available counts work locally. Empty/no-match states are distinct; URL query is honored on offline navigation. Reconnection plus ordinary reload returns server content.

A2: no changes to IndexedDB schema, operation identities, queue/replay, worker actor/epoch fencing, login/logout, cache admission, server authorization, domain writers or prepared-set ownership. Closed/other actor shows no old private rows. Only existing ready/stale/refreshing records are displayed, completed rows remain read-only. Server-only fields are explicitly unavailable. No new persistent projection or migration. New shell dependencies must be in the existing versioned asset closure.

A3: checklist retains its document-closeout section and exact explanation that the remaining 15% use PTO act/declaration, but has no closeout navigation button/link. Section-photo and general-photo ownership, pick/camera accessible names, disabled state, focus and input behavior stay unchanged.

A4: general-object camera/file labels are visually centered (text center within 3 CSS pixels of control center) at 320/390/800/1366px, with aligned controls and no horizontal overflow. Validate actual browser geometry and screenshots. Gallery count stays inline. Section photo controls remain intact.

## Impact and delivery

No schema/frontier/import/backup/restore/domain change. Sensitive offline mechanisms are intentionally untouched; any required change to those reopens scope and planner review selection. Root owns this record and executable tests; separate sol/low executor implements, independent sol/low final reviewer verifies the full candidate. Full local make test/verify is forbidden; bounded local tests plus planner-selected exact-source GitHub CI. Current request authorizes implementation/testing, not a new home deployment or merge. Previous task history and other worktrees are preserved.

Evidence lives outside Git at `/Users/antropophag/.local/share/fmonitor-2/offline-construction-checklist-ui`. Live-home reproduction uses an isolated browser profile and read-only business navigation, with offline emulation; no production checklist facts are created.
