## Context

См. proposal и PRODUCTION-PERFORMANCE-001. Измерения находятся вне checkout; все F01–F12 входят в candidate. Пользователь явно изменил порядок delivery на production-first, последующие gates/CI/merge. Правила root-authored tests и отдельного executor/reviewer сохраняются.

## Goals / Non-Goals

Три разделённых implementation участка: backend reads; checklist/media/upload; UI DOM/assets. Никакой записи бизнес-фактов из read endpoints, миграции бизнес-схемы, изменения admission policy, public cache приватного контента или rewrite framework.

## Decisions

- Admission memo живёт только в presentation read request, не в financial owner. Narrow employment-status query и scoped utilization вместо полного directory read.
- Явный read-session handoff только после AccessControl и подготовки identity/CSRF, не на session writers. Не отключать persistence fault handling.
- Service worker владеет настоящим network queue<=2, user/generation isolation и invalidation. Инициатор coalesces lifecycle events; cancellation не означает успешную запись offline operation.
- Thumbnail service использует GD с bounded raster dimensions (не более40MP и configurable memory-safe bound), maximum640px/JPEG82; private deterministic derivative и неизменный original. Без thumbnail runtime readiness rollout не завершается. GD добавляется в owning Docker templates и generated consumers, не через произвольную shared shell команду. На production сначала готовится совместимый extension/image artifact, затем scoped application patch.
- Переиспользуемые финансовые editor forms; календарь42days и server-rendered partial representation; users25rows плюс authenticated editor. Старые URL и no-JS сценарии остаются.
- Public asset version через owning asset seam; statics delivery может оставаться PHP при exact version/conditional headers, чтобы не расширять filesystem allowlist. Framework migration/CDN не нужны для закрытия F12.
- Root пишет независимые fixtures/assertions; executors не редактируют tests/specs. Существующие тесты, привязанные к старому размеру DOM/периоду/inline forms, root переносит к новым observable contracts, не удаляя auth/history assertions.

## Risks / Trade-offs

- GD decode/memory → ограничение dimensions, safe unavailable, private atomic derivative, оригинал доступен прежним путём.
- Session reopen/flash loss → allowlisted pure reads и отдельный concurrent real-session test; token continuity и fault tests.
- SW поздние ответы чужой сессии → epoch guard и abort invalidation, no shared document cache.
- UI context leak → exact IDs/revisions, recovery/preview tests, focus/no-JS checks.
- Параллельный production drift → compare-and-swap каждого пути и backup по месту; unrelated dashboard source сохраняется.

## Migration Plan

Без бизнес-миграций. Root фиксирует source hashes и artifacts; focused checks + independent review; extension prerequisite/thumbnail readiness; scoped host/runtime atomic file replacement с rollback и graceful PHP reload; live read-only scenarios. После production formal reviews/CI и merge. Полный локальный suite запрещён. При CIfailure сперва complete failure inventory.
