# Замечания Микрюковой — поставка на домашний dev

## Состояние

В работе, не поставлено. Change `mikryukova-feedback`, ветка `codex/mikryukova-feedback`, база `cbb2a65654dd8d7bb4fbfca508dc9e22e78d488a`. Полное независимое final review APPROVED после одного возврата с двумя R5 findings; PR, exact-source full GitHub CI и live deployment ещё не завершены. Импортёр, корпоративный fm.shlz.ru и отправка писем вне задачи.

Контракт: [MIKRYUKOVA-FEEDBACK-001](../../specs/MIKRYUKOVA-FEEDBACK-001.md). Решения владельца, scope, текущая очередь и разрешение на прямой согласованный пересчёт учебных данных — [current-delivery-goal](current-delivery-goal.md). Работа ведётся только в dedicated worktree; navigation checkout read-only.

## Авторы и review

Root — нормативный контракт, OpenSpec, verification mapping и регрессии/адаптация fixtures. Отдельные gpt-5.6-sol/low runtime authors: inputs_executor (R1/R2, первоначальный R5), payment_executor/payment_finish (R3/R4, schema/recovery), advisory_executor (пять advisory исправлений, два соседних дефекта, полная квалификация R5), inspect_payment_inputs (ограниченная presentation correction view/CSS). Независимый independent_review не писал tests/runtime.

Gate3: [inputs](../../reviews/tests/MIKRYUKOVA-FEEDBACK-INPUTS-001.md), [payments](../../reviews/tests/MIKRYUKOVA-FEEDBACK-PAYMENTS-001.md), [demo](../../reviews/tests/MIKRYUKOVA-FEEDBACK-DEMO-001.md), [advisory regressions](../../reviews/tests/MIKRYUKOVA-FEEDBACK-ADVISORY-001.md), [demo hardening](../../reviews/tests/MIKRYUKOVA-FEEDBACK-DEMO-HARDENING-001.md). Возвраты соответственно2/2/1/0/1, причины и source snapshots в записях. Финальный verdict и exact source — [code review](../../reviews/code/MIKRYUKOVA-FEEDBACK-001.md).

## Проверки

Только bounded local; full local make test/make verify не запускались. Профильные проверки покрывают delivered workforce, stops, включительный норматив/ПТО/справку, real card HTTP, PDF/XLSX, hold/resume/partial allocations/reversal, сохранение/перераспределение резервов, concurrency/replay, schema drift и recovery. Матрица и фактические результаты — [inventory](../../openspec/changes/mikryukova-feedback/inventory.md). Initial architecture check нашёл SQL в maintenance facade; исправление — штатный MariaDb owner, без ослабления правил.

Существующие object-card/OTIZ browser tests проверены. Дополнительный payment hold/resume HTTP fixture снят на1440/390; один batch presentation fixes и один финальный контрольный проход. Артефакты и полные логи вне Git: `~/.local/share/fmonitor-2/mikryukova-feedback/`. Повторные targeted runs вызваны конкретными исправлениями/новыми findings, не повторением неизменного GREEN ради UNKNOWN.

## Домашний контур

Read-only проверка24.11.2026 21:18UTC: checkout HEAD совпадает с базой, clean, php/db healthy. Создан private SQL snapshot для изолированной числовой репетиции; реальная домашняя база не менялась. Local database `fm2_mikryukova_home_preview_20261125`, prefix `fm2_`, collation `utf8mb4_unicode_ci`. Snapshot SHA256 `9570a1e91c4aa1713638bab7a187e26f6b168b9b14e860be6a176b2736312149`, gzip9540075bytes. Это analysis snapshot; перед live deploy требуется свежий согласованный backup DB/state/source.

При обычном SQL restore движок добавил поддерживающий FK index, отсутствующий в исходном SHOW CREATE; только в локальной копии восстановлен exact исходный index set. Известный historical registry14/15 receipt gap уже описан в [поставке13.11](actual-start-from-assignment-order-20261113.md): registry populated, receipt empty. Его не ремонтируем и не выдаём за GREEN. Для домашнего dev применяется прежний scoped route: только новая additive migration41 через canonical owner под migration lock, preservation проверенных прежних tables/data; fresh/repeat полный catalogue проверяется отдельно в isolated fixtures/CI.

До поставки остаются: R5 qualification и isolated rehearsal завершены; freeze final candidate, independent final review, GitHub full exact-source CI, backup и reviewed-source deployment, точный preview/apply с авторизованной перестановкой дат246/147354 и проверкой денежных балансов. Никакое UNKNOWN не считается допуском к merge.
