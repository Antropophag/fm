## Context

Базовый worker — единственный writer checklist/shell CacheStorage. Его поколения сейчас сохраняются без ограничения. Field store v3 владеет durable queue/Blob; его схема и writers остаются прежними. Домашний sample6:68–110КиБ на чек-лист, около1МиБ shell; это размер, не performance гарантия. Root UI RED сохранён; Gate3 вернул пять полнотных замечаний, которые включены в обновлённые тесты.

## Goals / Non-Goals

**Goals:** FIELD-CACHE-RETENTION-001 R1–R7 и прежние три UI-исправления в одном reviewable candidate. Budget100МиБ/резерв200МиБ, срок подмены7дней (явно подтверждено владельцем). Собственные и visiting snapshots без серверных/domain writes.

**Non-Goals:** серверные миграции, изменение прав/назначения, prefetch всех заказов/фото, чистка pending журналов, фоновые browser sync guarantees, корпоративный/домашний deploy.

## Decisions

- Capacity/admission/discovery/cleanup принадлежат worker. Можно выделить глубокий helper; если отдельный worker script, его URL обязан быть exact-versioned и войти в generation/install closure и VM harness. Никакого unversioned network dependency при cold offline startup.
- Нужные реквизиты подмены сериализуются в реально авторизованный checklist HTML (base64 JSON data attribute или эквивалент), проверяются на actor/object/canonical path. Deadline берётся из worker clock только при успешном foreground network fetch. Данные own берутся из существующего стабильного manifest; отправитель подтверждает actor/epoch/build. Подмена не требует нового server endpoint.
- Cache bytes и время/посещение хранятся с реально сохранённой response либо восстанавливаются сканированием; metadata не может объявить отсутствующий document готовым. Cache.put и metadata failure учитываются раздельно, crash recovery не доверяет отдельному счётчику.
- Durable queue читается только readonly для protection. Все неизвестные/nonterminal операции и несопоставленные blobs — fail-closed. Защита распространяется на все users/generations при глобальной очистке. Ни retention, ни лист не вызывают domain mutations.
- Admission сериализован у owner; generation/actor fences сохраняются вокруг каждого await. Cleanup учитывает live clients и повторно проверяет pending до удаления. Изменение storage writer/lease semantics не входит.
- Известные старые клиенты сохраняют свои assets. Неизвестный live client откладывает pruning; текущий/предыдущий complete поколения удерживаются минимум. Следующая подготовка/успешный foreground visit запускает bounded maintenance. Полные сканы только при reconciliation/maintenance, не на каждом asset request.
- Оффлайн UI сохраняет standard control classes/table/search; не кэширует произвольную authenticated all-list HTML. Поля отсутствующие в сохранённых данных явно unavailable; лишние действия отключены/отсутствуют. Все offline records объединены по objectId, own выше substitution.
- Архитектурные проверки затрагивают native Yii assets/views и worker boundaries. rapid-pilot остаётся без изменений. Server schema/frontier, imports, backup/restore не меняются: persistent browser schema upgrade не нужен.

## Risks / Trade-offs

- Browser quota estimate приблизительна; резерв сообщается как подтверждённый только при валидном estimate, actual quota errors остаются обработанными.
- Старые открытые вкладки/защищённые данные могут временно превышать цель; безопасный отказ новых downloads предпочтительнее потери данных. Указать причину partial.
- Deadline основан на часах устройства, не является защитой от злонамеренной правки local storage/clock или заменой серверной авторизации.
- Недоступность IndexedDB запрещает cleanup; готовность не притворяется полной. Объект с pending сохраняется за пределами TTL до успешной синхронизации/разрешения; revoked отображение блокируется, bytes сохраняются.
- Потребуются изменения старых worker fixtures только для нового public dependency/message contract; не ослаблять устоявшиеся privacy/durability assertions ради GREEN.

### Public worker protocol for executable acceptance

Существующие actor/epoch/generation сохраняются. `FIELD_SET_OWN_OBJECTS` принимает полный стабильный `{userId,epoch,generation,setVersion,items}` от клиента стройконтроля и отвечает `{ok}`. `FIELD_CACHE_STATUS` и `FIELD_CACHE_MAINTAIN` возвращают `{ok,items,budgetBytes,reserveBytes,cacheBytes,storageVerified}` для точного actor; записи содержат прежние реквизиты объекта и `{source:'own'|'substitution',expiresAt,protected}`. Invalid identity/source получает `ok:false` без items и эффектов. `FIELD_CLIENT_GENERATION` сообщает известное опубликованное поколение действительно живого client; неизвестные клиенты продолжают защищать старые поколения.

Серверные реквизиты реального авторизованного чек-листа передаются в `data-offline-cache` как base64 UTF-8 JSON `{userId,objectId,address,entrance,registrationNumber,completed,checklistUrl}`. Worker проверяет actor авторизованного HTML и канонический URL документа до добавления foreground visit в доступный список. Legacy документы без атрибута остаются допустимыми собственными подготовленными снимками, но не становятся бессрочными подменами. Сообщение клиента само по себе не создаёт новое время визита.

`expiresAt` — Unix epoch milliseconds. `FIELD_CLIENT_GENERATION` — специальное сообщение о живой вкладке: известное опубликованное старое поколение допустимо только для действительно живого same-origin client; это не разрешает stale-generation admission/cleanup.


## Старые namespace кэша

Учёт физических bytes включает сохранённые `fmonitor2-checklist-shell-*` и `fmonitor2-checklist-doc-*-<userId>`, поскольку runtime поддерживает их при upgrade. Они не являются бесплатным исключением из100МиБ. Live/unknown старый клиент или UNKNOWN protection запрещает destructive pruning; при превышении бюджета новые копии ограничиваются. После закрытия старых клиентов подтверждённые own/protected документы переносятся в совместимое поколение с проверкой bytes/hash и обязательной metadata revision до удаления источника. Неподтверждённые старые non-own документы не становятся записью подмены. Сбой copy/verification/metadata/fence сохраняет исходные документы и необходимые shell resources. Все legacy mutations используют прежний общий cache lock и revision.


## Initial document и изоляция прежнего actor

`FIELD_ESTABLISH_ACTOR` получает browser-authenticated source Client. Его первый canonical-checklist bootstrap — единственное дополнение к контролируемой navigation qualification: identity ещё отсутствует, source live/same-origin/same-object, refetch authenticated и metadata valid. Все document writers используют общий managed budget/proof/revision owner; actor confirmation и offline readiness имеют разные результаты. Late bootstrap повторно читает identity/epoch/build и не восстанавливает logout. Повторная проверка actor не продлевает TTL.

Для v2 protection разбирает canonical scope read-only и проверяет согласованность optional duplicated fields; неизвестное не превращается в пустую очередь. Чужие legacy caches сохраняются до активного возвращения actor, без попытки объявить их clean из-за отсутствия локального каталога. Это уточняет прежний план обхода foreign users; bytes продолжают учитываться.

Initial bootstrap использует `WindowClient` с frameType `top-level` или `auxiliary`; другие типы отвергаются. Auxiliary является отдельным browsing context без parent, согласно [Get Frame Type](https://w3c.github.io/ServiceWorker/spec/service_worker/#get-frame-type). Lifecycle передаёт уже полученную runtime generation; generic actor confirmation без cache admission остаётся совместимым.
