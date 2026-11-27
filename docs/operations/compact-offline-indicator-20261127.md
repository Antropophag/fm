# Компактный статус офлайн-готовности — 27.11.2026

## Простыми словами
По отзыву владельца большой блок заменяется малозаметным индикатором. Кнопка установки удаляется: установкой управляет браузер. Исправляется препятствие загрузке манифеста приложения.

## Контракт OFFLINE-INDICATOR-001
Публичная граница: HTML Стройконтроля + preparation UI, HTTP CSP и браузерный manifest discovery.
1. По уточнению владельца индикатор — только маленькая точка9px рядом со счётчиком объектов, в визуальном языке существующего fm2-local-sync. Без видимой текстовой подписи, кнопок, стрелки и раскрытия. Подготовка синяя, готовность зелёная, частичная готовность янтарная, ошибка красная. Доступное имя и обычный title различают состояния и содержат точные количества; готовность не подменяет подтверждение отправки операций.
2. Никакого раскрытия или дополнительного UI при нажатии/фокусе. Статус доступен assistive technology через aria-live и текстовое имя. Дополнительная кнопка повтора удалена по требованию минимализма; обычная перезагрузка страницы повторяет существующую подготовку. Прежние данные, очередь и алгоритм подготовки не меняются.
3. В приложении нет кнопки установки и перехвата beforeinstallprompt с preventDefault; браузер управляет установкой самостоятельно.
4. Политика страницы разрешает манифест только собственного origin. Chromium получает действительный манифест и подходящие иконки без manifest CSP violations; внешний manifest по-прежнему блокируется. Остальные CSP directives, authentication, права, worker scope и кеширование не расширяются.
5. Сохраняются существующие A1–A9 RELIABLE-FIELD-OFFLINE-001, очереди/повторы/фото/actor fences. Данные и схема не меняются. Hardware установка на личном устройстве остаётся UNKNOWN до фактической проверки.

## Доставка
Base bfe88ccdc505f953c11f9c34371f7447b1f63380 (домашняя сборка). Новое поручение пользователя supersedes прежнюю очередь только для этой коррекции. Root: контракт/регрессии; отдельный sol/low executor: реализация; независимый sol/low reviewer: planner-required reviews. Никакого изменения main, корпоративного окружения или домашней БД. Локально bounded checks; full make test/verify запрещены. CI/deploy не считаются завершёнными без evidence.

RED: `php tests/Yii2/offline_indicator_001_test.php` воспроизвёл блокировку манифеста и присутствие лишней кнопки;6/6 сценариев RED. Evidence `/var/folders/yc/548th18156s39y3kx0xc05tc0000gn/T/offline-indicator-b4d840e36883/results.json`. Публичный live CSP control: `/tmp/fmonitor-pwa-diagnosis.json`, `/tmp/fmonitor-pwa-csp-control.json`. Planner выбрал CRITICAL и gate3/final из-за затронутой границы, выполняем оба.

## Локальный preview по последнему поручению
Пользователь потребовал сначала показать вёрстку и запретил PR publication до согласования. Реализация UI выполнена отдельным `/root/indicator_executor` gpt-5.6-sol/low; root — contract/tests и local preview. Изменены только construction-control.php, field-offline-preparation.js, field-offline.css. CSP correction пока НЕ реализована. Gate3/final review/fullCI отложены, не approved.

Страница `http://127.0.0.1:8097/pilot/construction-control` открыта в отдельном Chrome window; пользователь переключил viewport на iPhone16 393×852. Native screenshot подтвердил строку «5 объектов / Готово без сети», отсутствие install UI и большого блока. Mock data/readiness только в external preview `/tmp/fm-offline-preview`, сервер `php -S 127.0.0.1:8097 /tmp/fm-offline-preview/router.php`; никаких real data mutations. Preview состояния: `?state=ready`, `?state=preparing`, `?state=partial`, `?state=error`.

Focused UI4/4 PASS (320/390/800/1366px, native disclosure/retry/no install intercept); CSP2/2 остаются ожидаемо RED. Evidence `/var/folders/yc/548th18156s39y3kx0xc05tc0000gn/T/offline-indicator-a117480bace6`. Detector [] clean; syntax/diff checks PASS. Первый визуальный pass выявил52px высоту на800px, исправлено до36px и подтверждено. Прежние preparation/review snapshots retained `/tmp/fm-offline-gate3-source-v3`; reviewer package был blocked отсутствием оформленного harness evidence, это НЕ approval. Следующий шаг: дождаться отзыва пользователя по вёрстке. PR/CI/commit/push/deploy не выполнялись.

Последний отзыв владельца: раскрытие отвергнуто, ориентир — минимальная индикация синхронизации чек-листов/фото. Обновляется только локальный preview, публикация по-прежнему запрещена до согласования.

RED уточнённой минимальной индикации:4 UI сценария поймали прежний36px disclosure вместо9px точки. Evidence `/var/folders/yc/548th18156s39y3kx0xc05tc0000gn/T/offline-indicator-4bd1d52caca9`. CSP2 остаются отложенным исправлением до окончания visual preview.

Второй макет: только пассивная9px точка рядом со счётчиком; native Chrome393×852 проверен и оставлен открытым на8097. Раскрытие, подпись, кнопки удалены. Исполнитель indicator_executor (sol/low); root обновил контракт/проверки. Пауза до согласования; CSP/гейты/CI/publication по-прежнему не завершены.

Уточнение позиции: owner требует размещать индикатор справа, как синхронизацию чек-листа. Это supersedes прежнюю привязку точки к счётчику. Root выполняет bounded presentation correction одного CSS правила (допустимый single-author route по AGENTS); mobile count слева, точка у правого края, desktop уже справа. Без новой функциональности, гейты/publication всё ещё отложены.

Owner просит компактно объяснить счётчик. Root выполняет bounded copy correction: «В списке: N объектов» в прежней строке; count остаётся filtered list total, не числом offline-prepared документов. Без изменения высоты/позиции индикатора, только локальный preview; PR до согласования запрещён.

Уточнение owner: счётчик относится к OFFLINE, не filtered list. Предыдущая подпись «В списке» ошибочна и superseded. В той же строке слева «Без сети: N объектов» только после ready, при preparing/partial «Без сети: completed из total» по реальному preparation state; до known total — «Без сети: подготовка…»; при ошибке «Подготовка не удалась» без ложного обнуления сохранённых данных. Точка справа сохраняется. Root — bounded correction существующей индикации и regression; список/фильтры не определяют offline count. Preview only, запрет PR сохраняется.

Счётчик offline подключён к summary(state,completed,total), list total удалён из status. RED `/tmp/fm-offline-count-red.log`;4 UI scenarios GREEN с независимым empty filtered list и2 prepared objects; CSP2 ожидаемо RED. Native Chrome393px подтвердил «Без сети:5 объектов» и точку справа. Root bounded author, PHP/JS syntax/diff PASS. Preview открыт, ожидание отзыва; публикация не выполнялась.

Последнее уточнение owner: подпись «Доступно оффлайн: N объектов»; текст и точка рядом единой группой справа (supersedes разнос по краям). Состояния цвета остаются: blue preparing, green ready, amber partial, red error. Root bounded presentation/copy correction; никаких новых действий или раскрытия. Только local preview, PR до согласования запрещён.

Текущий preview: «Доступно оффлайн:5 объектов» +9px dot в одной правой группе gap8px. Native Chrome393px подтверждён; PHP/JS syntax/diff PASS, focused UI4/4 PASS на320/390/800/1366px, CSP2 остаются ожидаемо RED. Макет открыт на8097, PR/CI/deploy не выполнялись.

## CHECKLIST-MINIMAL-001 — уточнение локального preview
Owner согласовал вид offline списка и попросил минимализм чек-листа: убрать портянку сохранённых действий. Номер над процентами — `orderNumber` из object profile, ссылка на техдокументы при их наличии; это объяснено пользователю, его значение/ссылка сохраняются.
Обычные queued/sending/retryable_error/uncertain операции не создают отдельный panel/list/details над чек-листом. Чекбоксы, локальные фото и короткий sync-banner сохраняют правду; никакого «Синхронизировано» с pending. Отдельные карточки допускаются только для требующих решения состояний (conflict/rejected/auth_required/access_denied/storage_recovery или storageError). Empty panel скрыт без margin. Обычные успешные отметки/фото не создают повторяющиеся success toasts; ошибки сохраняются.
Удаление ещё не отправленного queued/unattempted фото переносится на× у его thumbnail с прежним explicit confirmation и session.discard(id). Attempted/sending/uncertain не получают эту кнопку. Сохранённая серверная история и runtime/data/API не меняются. Unsaved memory photos/retry/download/confirmation остаются видимыми. Dead routine-details CSS удаляется. Root specs/tests/preview, отдельный executor — implementation; gates/CI/publication deferred до согласования нового preview.

Owner дополнительно заметил отсутствие цветной точки у «Синхронизировано». Добавляется8px aria-hidden dot рядом с sync copy. Цвет по фактическому banner state: ok green; loading/sending blue; pending/offline/retryable/uncertain amber; auth_required/access_denied/storage_recovery/conflict/error red. Unknown не green. Без новой анимации; existing sending pulse respects prefers-reduced-motion.

Owner после подтверждения automatic sync явно потребовал убрать кнопку ручной синхронизации. Удаляются data-sync-now и его UI handler; automatic online/pageshow/visibility/timed retries остаются неизменными. Recovery несохранённого фото и конфликтов — отдельные действия, сохраняются.

Owner уточнил: любой sync status должен умещаться в ОДНУ строку на iPhone. Все состояния включая loading/init failure/unknown используют короткий UI copy, без raw error.message. Проверка320/393px: высота banner<=24px, видимая точка, отсутствие horizontal overflow; full error context остаётся в accessibility/title и actionable recovery.

## Полная delivery authorization
Owner явно снял preview pause: оформить всё до merge main, затем home deploy. Авторизация включает необходимые коммиты/PR/CI corrections/штатный merge и домашнюю выкладку, при mandatory independent reviews/exact CI. Scope не включает отдельный PR307 address import; home schema44 надо сохранить отдельной reviewed assembly. CHECKLIST-MINIMAL UI5 tests PASS (including14syncstates320/393, photo discard confirmation); `/tmp/fm-checklist-minimal-final.log`; CSP2 всё ещё RED до исправления. Старые browser consumers data-sync-now/queue-details надо привести к новым public automatic flows доpublication.
