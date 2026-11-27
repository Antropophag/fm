## Context
См. proposal.md и OBJECT-GENERAL-PHOTOS-001. Владелец подтвердил current object; общий order scope исключён. Existing photo tables содержат unsigned non-null section_id без CHECK/FK на1..7.

## Goals / Non-Goals
Использовать прежний public mutation seam и offline transaction без отдельного writer. Не менять обязанности фото разделов, права, счёт прогресса, миграции, imports или lifecycle истории.

## Decisions
- sectionId0 — общий bucket объекта, допустимый только для upload/revoke. Nullable требовал бы schema migrations и неоднозначных numeric consumers без пользы.0 не добавляется в ITEMS/шаблон.
- Persistence owner остаётся MariaDbYiiChecklist accept/storePhoto. Existing locks, replay comparison, per-bucket cap/dedupe и projection уже сохраняют0; меняется только admission к photo branches.
- UI переиспользует upload/photo rendering/recovery helpers с явным target sectionId0; general container не входит в sections, управляющие progress/auto-completion. Gallery агрегирует все фотографии. Shared photo helper вместо копирования нового workflow.
- Offline store/sync численно сравнивают sectionId, поддерживают0; root regression подтвердит cap/receipt и offline reconnect. Runtime private bytes/actor fences не меняются.
- Архитектура: HTTP controller по-прежнему adapter, штатный application owner пишет факты; rapid-pilot не меняется. Boundary/architecture checks и focused HTTP/browser обязательны перед publication.

## Risks / Trade-offs
- Разрешить0 всем операциям → regression запрещает section/item commands0.
- Потерять связь локальных general bytes с receipt → реальный offline enqueue/reconnect проверяется с точнымID и projection.
- General photo ошибочно завершает раздел → независимый public HTTP сценарий.
- UI раздувается → три кратких действия внизу, скриншоты320/393/1366.

## Migration Plan
Schema unchanged; existing snapshots/backups/media включают general rows. После появления0 нельзя откатывать runtime к writer, который не принимает их revoke/replay; deployment не выполняется в preview. Formal Gate3/final/fullCI остаются pending, actual source через harness. Root author/tests; отдельный executor; текущий WIP не переписывается.

## Discard correction discovered by real browser regression
Existing discard treats serialization predecessors as full causal dependencies and deletes all descendants. For independent general photos0 this would delete unrelated pictures. Keep the actor-guarded transaction and fail-closed attempted/frozen-wire checks; splice only a queued/unattempted general photo0 from the ordering chain, preserve all other nodes/bytes, and leave non-general discard unchanged. This is A6, now independently reviewed before implementation.
