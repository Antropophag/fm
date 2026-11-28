# FIELD-CACHE-RETENTION-001

## Основание и граница

28.11.2026 владелец явно расширил UI-исправления на бюджет браузерного кэша с резервом под фото, все свои автоматически, открытые для подмены в том же списке с ограничением срока, очистку старых копий без удаления неотправленных действий/фото. Заменяет противоречащие retention/discovery пункты RELIABLE-FIELD-OFFLINE-001; остальные гарантии сохраняются. Включает OFFLINE-CONSTRUCTION-CHECKLIST-UI-001 A1–A4 из docs/operations/offline-construction-checklist-ui-20261128.md; прежнее исключение storage из его scope заменено этим контрактом.

## R1 — Байтовый бюджет и резерв

Воспроизводимые offline HTML и published shell assets всех поколений SHALL учитываться по фактическим bytes response body, не по числу объектов или Content-Length. Целевой суммарный бюджет100*1024*1024 bytes. При доступной достоверной navigator.storage.estimate новый/обновлённый кэш SHALL оставлять минимум200*1024*1024 bytes между quota и usage (замена учитывает размер старой копии). Измерение — оценка, не гарантия ОС. При отсутствии/ошибке estimate действует100МиБ лимит, UI явно не утверждает подтверждённый резерв. Persistence denied не равняется отказу работы, но предупреждение о риске очистки сохраняется. При нехватке места никакого false-ready: worker возвращает различимый отказ CACHE_BUDGET_EXHAUSTED; подготовка показывает сохранённые X из Y и причину. Онлайн-документ продолжает открываться даже при невозможности его сохранить. Существующие защищённые данные могут превышать бюджет: их нельзя удалять ради цифры; новые записи при этом ограничиваются.

## R2 — Свои и подмена

Полный стабильный own manifest остаётся прежним серверным owner; ownership=all/query/page не расширяют автоматический набор. Только успешное авторизованное foreground GET канонического чек-листа может добавить/обновить подмену. Серверно сформированные реквизиты должны соответствовать actor/object/url; клиентская строка/запрос не является подтверждением чтения. Background warmup, cached/offline GET, повторный запуск и открытие списка не продлевают срок. Срок подмены7*24часов от последнего успешного foreground network GET. Политика срока — retention, не расширение серверных прав. Свой объект имеет приоритет и не дублируется. Пока есть unresolved операции/Blob, истёкший объект сохраняется и доступен текущему actor с явным состоянием «Есть несинхронизированные изменения»; после terminal receipt может быть удалён. Закрытые/неразрешённые для записи чек-листы сохраняют read-only поведение.

## R3 — Общий список

Список без сети объединяет подтверждённые cached own records и актуальные cached substitution records; direct URL, reload, новая вкладка и полный browser restart дают одинаковую доступность. Физически отсутствующий HTML никогда не считается готовым. Свои и подмена различимы ненавязчивой меткой; поиск по адресу/подъезду/регномеру работает по объединению. Срок виден справочно. Не показывать чужую учётную запись, неподтверждённую запись, expired без pending, revoked. URL query/completed применяются локально; итог «Показано X из Y» точен (Y — весь доступный объединённый набор, X — после фильтров); завершённые по умолчанию скрыты, reset сбрасывает query/completed. Пустое хранилище и пустой поиск различаются. Онлайн reload возвращает обычный серверный раздел.

## R4 — Безопасная очистка

CacheStorage cleanup SHALL никогда не менять/удалять IndexedDB operations, photoBlobs, operation UUID/wire/payload, receipts, scopeState или device/actor identity. Любой статус кроме accepted/discarded/superseded считается unresolved, включая неизвестный статус, conflict/rejected/auth_required/access_denied/uncertain/sending/retryable. Наличие Blob защищает объект даже при несогласованном terminal status. Невозможность прочитать/однозначно сопоставить pending данные SHALL запрещать destructive cleanup, не означать пустую очередь. Перед удалением переоценить защиту; открытые checklist clients защищают свои документы. Потеря actor/epoch/generation прекращает stale cleanup/публикацию. При краше индекс восстанавливается по реально сохранённым bytes; потеря/несовпадение metadata не даёт права угадывать отсутствие pending.

Порядок освобождения: expired/отозванные и исчезнувшие из актуального own набора ненужные копии; старые дубли поколений после подтверждённого переноса; затем наименее недавно открытые unprotected substitutions. Нельзя вытеснять текущие own ради подмены. При невозможности освободить место отказать новой подготовке, сохранив старое. Ошибка Cache.put/QuotaExceeded не разрушает последнюю пригодную копию и не добавляет готовую запись. Повтор maintenance идемпотентен; параллельные admissions не превышают доступный бюджет из-за взаимной гонки. Domain/server facts не создаются.

## R5 — Поколения и совместимость

После успешной установки хранить текущий и один предыдущий complete shell плюс поколения реально открытых старых клиентов. Если поколение живого клиента неизвестно, откладывать удаление потенциально используемых им ресурсов. После закрытия таких вкладок следующий maintenance очищает ненужные поколения; failed/partial install не меняет доступный shell. До удаления старого doc cache переносить необходимые own/substitution/protected документы в совместимое текущее поколение, проверяя успешное сохранение. Если перенос/проверка не удались, сохранять исходную копию и сообщать ограничение. Обновление старого v2/v3 профиля не сбрасывает очереди/Blob; собственные ранее подготовленные записи доступны после upgrade. Неизвестные устаревшие не-own документы не должны бессрочно появляться в списке подмены без свежего network GET.

## R6 — Доступ и сообщения

Все metadata/list/maintenance worker сообщения проверяют same-origin client, положительный actorId, epoch и generation против подтверждённого worker actor; stale/чужие/invalid сообщения не раскрывают данные и не выполняют очистку. Успешный network GET другого actor не смешивается с исходным actor. Полученные401/403 и подтверждённый login redirect для конкретного чек-листа закрывают его offline discovery/direct access; сохранённые unresolved bytes остаются для повторной авторизации/разрешения. Fresh authorized network GET может восстановить доступ. Logout/account switch закрывают прежние данные немедленно; они физически сохраняются, не превращаясь в данные нового пользователя. TTL не является серверным разрешением отправки: каждый replay использует прежнюю fresh auth/context validation.

## R7 — Смежные гарантии и проверка

Сохраняются worker warmup concurrency2, foreground priority, asset version closure, exact replay и multi-tab queue serialization. Бюджет относится к воспроизводимому кэшу; pending фото ограничены существующими photo limits, их bytes не удаляются cleanup. Серверные фото/галерея не начинают автоматически скачиваться. Физический iPhone/Android/FortiClient performance остаётся UNKNOWN без отдельного device test. Acceptance: UI widths320/390/800/1366; public worker VM quota/TTL/identity/generation tests; real Chromium service worker+IndexedDB cleanup/restart/substitution/queued-Blob checks; существующие durability/replay/browser contracts. Full suite только выбранный GitHub CI, не локально.

## Уточнения проверки до RED

- Приоритет: fresh canonical401/403/login redirect закрывает показ даже pending объекта; исключение TTL для pending сохраняет доступ только без такого наблюдённого отказа. Bytes не удаляются в обоих случаях.
- Qualifying visit — top-level canonical same-origin checklist document navigation (`mode=navigate`, `destination=document`) с успешным реальным network response и валидными embedded actor/object metadata. Обычный fetch, prefetch/warmup или cached navigation срок не продлевает.
- Projected bytes = сумма physical response bodies во всех retained complete shell/doc caches минус доказанный заменяемый entry плюс новый entry. Дубли в разных caches считаются отдельно. Origin-wide estimate.usage отдельно ограничивает reserve; не прибавлять к нему те же bytes второй раз.
- При unreadable protection запрещены eviction, migration и удаление source cache; admission разрешён только без cleanup, если помещается и безопасно сохраняется, иначе indeterminate/partial.
- Complete generation — только успешно опубликованный полный install. Текущий+предыдущий complete и положительно известные live поколения сохраняются; unknown/uncontrolled live client запрещает потенциально затрагивающий его pruning. Required document переносится copy+verify до удаления source; ошибка metadata write сохраняет source.


## Уточнение cold bootstrap и legacy queue

- При первом прямом открытии до активации worker первоначальный авторизованный network document подтверждается существующим `FIELD_ESTABLISH_ACTOR`. Только при отсутствующей worker identity, live same-origin canonical checklist client и совпадающем objectId в source/data URL допускается initial-visit proof после no-store refetch с валидными server metadata и qualified redirect. Это адаптер первоначального foreground document, не обычный background fetch. Повтор actor check, dashboard/foreign/noncanonical/mismatched source не создаёт и не продлевает подмену. Перед записью повторно проверяются actor/tombstone/epoch/active build; late reply не отменяет logout или смену actor/build.
- Bootstrap использует тот же managed admission/record/revision. Успешное установление actor может вернуть `{ok:true,cacheReady:false,reasonCode:'CACHE_BUDGET_EXHAUSTED'}`: онлайн-работа продолжается, но объект не считается сохранённым. Успешная initial checklist admission возвращает `cacheReady:true`; её срок тот же7дней и сохраняется через restart. Raw document Cache.put в actor bootstrap не допускается.
- У поддерживаемых v2 operations actor/device/object могут однозначно следовать из canonical трёхчастного `scope`. Если дублирующие userId/objectId/deviceInstallationId отсутствуют, protection читает mapping из валидного scope; если поля присутствуют, они должны точно согласовываться. Некорректный/противоречивый scope остаётся UNKNOWN. Derived mapping никогда не записывается обратно и не меняет UUID/wire/payload/status/Blob.
- Legacy bytes предыдущего actor физически сохраняются и учитываются, но недоступны текущему actor. Отсутствие принятого own/catalogue для чужого actor не доказывает ненужность его документов. Destructive legacy cleanup откладывается до возвращения этого actor и подтверждения его own/protection состояния.

- Initial bootstrap требует browser Client `type=window`, `frameType=top-level` либо `auxiliary` (отдельное окно/вкладка без родительского frame); nested/none/unknown и не-window источники не допускаются. Сверяется runtime generation из сообщения с активным build. ActorId/epoch должны быть корректными целыми, а live source повторно подтверждается перед admission.

## Уточнение прерванного обновления и совместимости

- Если admission уже заменил документ, а последующая проверка actor/build/cancellation отвергла результат, восстанавливается точная предыдущая копия. Удаление допустимо только при отсутствии предшественника. Новая более поздняя запись не перезаписывается rollback; ошибка восстановления не удаляет единственную пригодную копию. Queue/UUID/wire/Blob неизменны.
- Повторная promotion HTML между поколениями оставляет ровно один актуальный script каждого offline engine в порядке зависимостей; существующие теги с атрибутами, одинарными кавычками или whitespace нормализуются, посторонние scripts сохраняются.
- Runtime readiness не подтверждается раньше успешной публикации полного shell и его active generation; ошибки активации не маскируются успешным handshake.

## Полная матрица отказов managed admission

- Каждый успешный admission имеет единственного владельца, который завершает token commit либо условным rollback при false/throw/fence/cancel. Это относится к foreground, initial bootstrap, own warmup и legacy promotion. Optional maintenance выполняется после commit; её отказ не отменяет подтверждённую запись.
- Catalogue/denial confirmation и own-set publication являются составными metadata updates: частичный отказ не продлевает TTL, не меняет ownership/discovery и не снимает наблюдённый отказ доступа. Восстанавливается предыдущая запись; если восстановление невозможно, доступ закрывается и возвращается limited. Отказ основной записи denial использует независимый durable fallback, сохраняющий закрытие после worker restart; bytes pending сохраняются. Полная недоступность всех durable writes остаётся явно UNKNOWN, не обещанием сохранения состояния после restart.
- Обычный foreground HTML без data-offline-cache остаётся только online: не изменяет bytes, catalogue или срок. Legacy own snapshots обрабатываются только по подтверждённому own/access пути.
- CHECK_CHECKLIST_CACHE — read-only сообщение, проверяет same-origin source и точные actor/epoch/generation; не запускает promotion/admission. Клиент передаёт actor context.
- FIELD_CACHE_STATUS дополнительно возвращает actor-scoped неперсистентный pendingWriteCount: число незавершённых admission tokens без содержимого. После завершённого success/failure оно равно0; это operational diagnostic, не UI readiness.

## Восстановление ownership и выбывшие свои объекты

- При неполном восстановлении OWN/catalogue предыдущий подтверждённый per-user view сохраняется независимым durable fallback. После worker restart coherent readers используют его; status сообщает limited/CACHE_STATE_INDETERMINATE. Пока состояние не подтверждено заново, current/foreign maintenance не удаляет исходные bytes и не заменяет view старыми preparations. Fallback снимается только после полной успешной публикации OWN+catalogue. Если не работает и этот durable writer, сохранность после restart остаётся UNKNOWN; это не требует общей transaction journal для произвольных crash points.
- Чистый объект, выбывший из своего набора, остаётся учтённым как retired/expired до post-confirmation safe cleanup физических aliases. Удаление только строки каталога без возможности дальнейшей очистки bytes не допускается: переназначения не должны навсегда занимать бюджет. Pending/UNKNOWN защита и открытые документы продолжают блокировать удаление.
