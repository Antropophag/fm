## Context

Мотивация и согласованный scope: [proposal.md](proposal.md). Контракт: [ADDRESS-REGISTER-IMPORT-001](specs/installation/address-register-import/spec.md).

Worktree создан navigation helper на `08ca8b18` (2026-11-18); первоначальный аудит выполнялся на `50a2fb3f` (2026-09-26). Нельзя выдавать старый аудит за live schema. В актуальном source уже существуют `ObjectDetailsEditApplication`, `MariaDbObjectDetailsEditStore`, `MariaDbEffectiveObjectDetails` и редактирование части реквизитов. Источником текущих значений могут быть manual overrides, а не только `fm_maintable`.

`MariaDbYiiObjectQueue` читает дела, legacy-проекцию и ограничивает допуск migration provenance / техническим snapshot. Одной вставки объекта недостаточно для видимости. `PilotCaseImporter` требует даты и предназначен для другого bootstrap-контракта. Нельзя ослабить его глобально или пометить новые объекты историческими cutover-объектами ради обхода фильтра.

Root подготовил executable spec/test candidate; первый независимый Gate 3 вернул CHANGES_REQUESTED (`reviews/tests/ADDRESS-REGISTER-IMPORT-001-v1.md`). Реализация не начата. Source/live readiness frontier — v39, dev содержит 309 объектов, prod пока пуст. Обследование и RED evidence находятся в private delivery home. Прежние задачи Docker/deploy и navigation checkout не меняются. Новые требования экономии ниже имеют приоритет над прежним решением выполнять новые запросы для каждого preview; перед новым RED root синхронизирует executable spec и тесты.

## Goals / Non-Goals

**Goals:**
- Один сценарий от XLSX через проверяемый пакет до актуальных объектов каталога; атомарность, одинаковое чтение ручных и импортированных реквизитов.
- Разделение медленной подготовки с ДаДатой и короткой транзакции публикации.
- Сохранение исходных сведений отдельно от исправленных адресов и изменяемой текущей проекции.

**Non-Goals:**
- Новые экраны импорта/редактирования, автоматические назначения/открытие/инспекции.
- Восстановление старой процессной истории или перенос идентификаторов dev в prod.
- Универсальный движок arbitrary Excel, словарные догадки, изменение payroll/премиальных расчётов.

## Decisions

### 1. Владелец и единый mutation seam

`InstallationProcess` владеет данными и `AddressRegisterImportApplication` (preview/apply/resume). Yii console выполняет parsing и rendering; XLSX и HTTP — адаптеры. Разбор файла и нормализация возвращают типизированный source package, не SQL. Persistence остаётся в модуле, а не в CLI/cron/HTTP. rapid-pilot не получает writer.

Импорт и существующий редактор должны использовать общую внутреннюю операцию изменения реквизитов, effective read-model и протокол блокировки. Нельзя просто вызвать нынешний `edit` для каждой строки: store сам открывает/закрывает транзакцию и разрушит атомарность пакета. Нужно выделить транзакционно совместимую внутреннюю операцию, сохранив public contract редактора и его проверки полномочий. CLI имеет отдельный проверяемый операторский контекст; не имперсонирует пользователя произвольным `--actor`.

### 2. Пакет и CLI

Предлагаемая форма (точную argv-грамматику и exit codes root закрепляет в executable spec до Gate 2):

```text
yii address-register/preview --file=<path> --year=2027 --contract=<id> --target=dev
yii address-register/preview --file=<path> --year=2027 --contract=<id> --target=dev --overwrite --reason=<text>
yii address-register/apply --package=<id> --preview-sha256=<sha> --operation=<uuid> --target=dev
yii address-register/resume --package=<id> --target=dev
yii address-register/report --package=<id> --target=dev
```

`report` только повторно выгружает сохранённый результат. Секреты читаются из private runtime configuration. Fingerprint включает bytes hash, parser/mapping версии, год, договор, режим, причину, provider evidence hashes, target identity и expected revisions. Применение не добавляет `--overwrite` задним числом: режим входит в preview. Проверяемый операторский principal и его отображаемое имя сохраняются независимо от данных файла.

До provider calls выполняется бесплатный `estimate` либо preview в offline-режиме по умолчанию: локальный разбор, доступная идентификация, проверка кэша и расчёт недостающих запросов. Платный preview/resume требует `--allow-paid-calls --max-paid-requests=N`; default N=0. `--refresh-addresses` явно запрашивает новую версию проверки и подчиняется тому же лимиту. Точные аргументы export/import переносимого address evidence и shared budget root фиксирует в executable spec до RED. Старый обязательный target-preview для prod сохраняется, но при пригодном импортированном кэше не требует новых обращений к ДаДате.

Пакет неизменяем после завершения подготовки; refresh создаёт новую ревизию и отчёт. Изменение файла, параметров или целевой БД требует нового preview. Отчёт без блокирующих ошибок допускает apply; предупреждения о сохранении старых значений не блокируют.

### 3. Идентичность и модель хранения

Аддитивная схема; literal версии/имена constraint выбираются после live/source frontier inspection. Сущности:

| Сущность | Содержание |
|---|---|
| Import package / attempts | Хеш источника, parser version, год/договор, target, режим, оператор, подготовка, ошибки, итог, operation fingerprint |
| Source rows | Исходные значения, лист/строка, нормализованный payload, source identity, результат/связь с объектом |
| Address evidence / accepted versions | Raw и canonical address, house FIAS ID, provider quality/time/hash, source address hash, причины решения |
| Object register facts | UNOM, реквизиты программы/договора, ссылки на source, устойчивый внутренний object ID |
| Equipment snapshots | Два набора: existing и planned-replacement; исходная точность/предварительность, словари и provenance |
| Contract planning / costs | Плановые даты, сроки проектирования, длительность, заявка и четыре стоимости; precision и currency |
| Detail revisions / change events | Общая актуальная проекция и append-only old/new/source/actor/reason; ручное очищение отделено от unknown |

Новый ID назначается БД через согласованный namespace, без `MAX(id)+1`, без регномера/UNOM/Excel-row как PK. Уникальные identity reservations и блокировки предотвращают гонку создания. Исторические дубли не исправляются миграцией молча: preflight блокирует неоднозначные совпадения. У существующего объекта неизвестный UNOM дополняется только при совпадении проверенного дома/подъезда. Регномер остаётся строкой. Смена договора/года — не повод создать второй физический лифт; membership/provenance программы отделены от его идентичности.

### 4. Mapping источника

Позиции описывают проверенный профиль; parsing проверяет также заголовки, а не слепо доверяет буквам.

| Колонки | Семантика |
|---|---|
| A | Только происхождение/порядок, не object ID |
| B–H | Год, регномер, UNOM, исходные округ/район, адрес, подъезд |
| I–O | Исключены из новых реквизитов; остаются только в raw |
| P–V | Те же отдельные параметры планируемого нового лифта |
| W–X | Частотные преобразователи дверей и главного привода нового лифта |
| Y | Период проектирования с точностью исходного текста, без фиктивного дня начала |
| Z–AC | Длительность монтажа, дата заявки, плановые начало/окончание |
| AD–AG | Исключены из новых реквизитов; остаются только в raw |

Установочные характеристики не подтверждаются как фактические до проектирования. Старый одиночный technical snapshot не переписывается новым набором. В новой карточке effective текущие общие поля должны иметь явно документированную семантику планируемого устанавливаемого оборудования; исходный existing набор сохраняется отдельно и доступен в import report. Перед Gate 2 зафиксировать mapping к существующим payroll/classification потребителям так, чтобы импорт не подменил финансовые входы.

Регистровые варианты словарей нормализуются явно. Для исходного `0.70999999999999897` правило точности скорости даёт `0.71`, при этом raw сохраняется; формальная точность всех чисел закрепляется в fixture matrix. XLSX формулы не исполняются, внешние ссылки/макросы не разрешаются. Формулы в строках объектов, не предусмотренные профилем, блокируют вход; итоговые SUBTOTAL не служат источником сумм. ZIP/XML ограничения по bytes/entries/rows предотвращают бесконтрольное потребление памяти. До Gate 2 фиксируются лимиты, покрывающие исследованный перечень.

### 5. ДаДата и качество

Используется серверный clean/address adapter с проверкой TLS, bounded timeout/retry, без логирования ключей. Suggest не используется для массовой автоматической загрузки. Параметры качества сверить с актуальной официальной документацией при подготовке fixtures: house-level identity, actuality, qc/qc_house, неразобранные части, регион/дом/корпус/строение. Нельзя принять ближайший дом или только улицу. Связь с UNOM проверяется как отдельная согласованность исходной и сохранённой идентичности; ответ ДаДаты сам по себе не доказывает UNOM.

Долговечный кэш отдельно от package keyed по provider + версии нормализации запроса + региональному контексту + точному каноническому запросу. Нормализация кэш-ключа меняет только представление whitespace/case; не удаляет корпус/строение/территорию и не объединяет разные адреса по одному UNOM. До первого достоверного ФИАС ID кэш не объединяет похожие улицы. Кэш хранит исходный запрос, raw response, capturedAt, hash, качество и версию правил. Сохраняется и неподтверждённый ответ, чтобы неизменившаяся ошибка не порождала повторную оплату; он никогда не допускает каталог. Transport failures не являются address evidence.

TTL по умолчанию 90 дней, настраиваемый. Это выбранная эксплуатационная политика проекта, не гарантия актуальности реестра. При истечении TTL нет скрытого платного refresh: offline estimate сообщает число устаревших записей, новое получение разрешается только в рамках явного бюджета. Изменение адресной строки, версии нормализации или явно запрошенный refresh создаёт новую проверку. Изменение правил acceptance позволяет повторно оценить сохранённый raw response локально, если его данных достаточно. `--overwrite` не означает refresh. Все версии evidence сохраняются append-only.

Для переноса dev → prod предусмотрен приватный подписанный bundle только address evidence, без DB object IDs, данных пользователей и provider credentials. Подпись проверяется настроенным доверенным ключом окружения, а не хешем, приложенным к недоверенному файлу. Получатель проверяет целостность, источник доверия, версии, исходный request hash, возраст и повторно применяет текущую quality policy. Target preview заново проверяет собственную БД; перенос кэша не переносит разрешение apply и не заменяет идентификацию. Нет live связи БД dev и prod. Без пригодного кэша prod явно показывает дополнительные расходы и не платит автоматически.

Каждая потенциально оплачиваемая HTTP-попытка предварительно резервирует единицу в долговечном бюджете операции. Resume сохраняет прежний расход; расширение лимита — отдельное явное действие с аудитом. Один cache key обслуживает не более одного in-flight запроса в окружении, конкурирующие previews ожидают результат либо возвращают занятость без дублирования оплаты. При неопределённом исходе timeout/connection loss попытка считается потенциально оплаченной, получает `PROVIDER_OUTCOME_UNKNOWN` и не повторяется автоматически. Явный retry расходует новую единицу. Retry 429/5xx допустим только в пределах общего лимита попыток; отсутствие средств/401/403 не ретраится. Ошибка квоты/сети сохраняется как provider failure, не как «адрес неверен».

Estimate показывает число строк, уникальных запросов, cache hits/stale/misses, базовую оценку новых запросов и верхнюю границу попыток по cap. Цена в рублях рассчитывается по явно указанной цене за адрес (исходно 0.20 ₽, дата тарифа сохраняется), это оценка, не обещание неизменной цены или подтверждённого списания. Фактический баланс общего аккаунта может меняться другими приложениями; отчёт не приписывает всю разницу нашему импорту. Постоянные тесты/CI используют fake provider и не принимают live credentials.

На первом наборе 2027 ожидается 667 уникальных запросов вместо 2668 строк лифтов: 133.40 ₽ по цене 0.20 ₽ без повторов. Повторный preview/apply и перенос на prod должны использовать те же пригодные ответы, а не удваивать эту оценку. Точная оценка зависит от локальных ошибок и доступного кэша.

Для неоднозначности оператор получает строку и причину; исправляет новый экземпляр перечня либо provider/source data, после чего создаёт новый preview. Прямое принятие непроверенного дома флагом overwrite не поддерживается. Исходник всегда сохраняется неизменно. Отдельный универсальный формат файла исправлений в v1 не вводится.

### 6. Атомарность и конкурентность

Подготовка сохраняет технические журналы и provider responses вне транзакции каталога. До apply всё выбранное множество проверяется, строится список мутаций и expected revisions. В apply блокируются identity keys и объекты в стабильном порядке, повторно проверяются права, schema, fingerprint и зависимости preview, затем одной транзакцией создаются/меняются каталог, технические дела, accepted evidence, история и durable operation outcome. Никаких HTTP-вызовов и exports внутри этой транзакции.

Стандартный field conflict без overwrite — warning. Identity conflict, неподтверждённый адрес, невалидное непустое поле, schema drift или stale preview — блокировка всего пакета. Не допускается частичный commit по N строк. Resume относится к подготовке и восстановлению outcome. При неизвестном commit — reconciliation по operation ID, без слепой повторной записи. Потеря внешнего отчёта после commit не делает применение неуспешным; report восстанавливается из durable результата.

### 7. Каталог, ручные правки и процессные границы

Добавить явный admission source для native объектов из перечня в обычную очередь, без поддельного migration/cutover provenance. Создать при необходимости пустое техническое дело в той же транзакции. Null dates поддержать в списке/карточке, поиске, сортировке, календаре и сводках; отсутствие даты не превращается в overdue или calendar event. Не добавлять специальный статус «график не задан».

Общая ревизия сериализует будущие и существующие ручные правки с импортом. По умолчанию ручной tombstone защищён; overwrite может заменить его непустым входом. Не менять immutable snapshots распоряжений, интервалы назначений, фактические сроки, deadline certificates и процессную историю. Даже overwrite планового реквизита не переписывает отдельный документ переноса срока и не обходит его приоритет в действующей процессной проекции.

### 8. Проверки и границы delivery

Root переносит стабильный контракт в `specs/ADDRESS-REGISTER-IMPORT-001.md` с public CLI grammar, status/exit code table, независимыми fixtures и полной acceptance matrix до Gate 2. Это не фиксация GREEN и не замена OpenSpec executable tests. Работа затрагивает schema/durability/overwrite: требуется planner route с Gate 3 и final review, executor и reviewers — отдельные gpt-5.6-sol / low, без исключения авторства.

Перед RED: выделенный delivery binding, harness state/prepare и verification-input; unresolved Quality Graph obligations блокируют тестовую стадию. Затрагиваются catalogue read-model, manual-edit/replay/concurrency, schema inventories/readiness, runtime XLSX/HTTP dependencies, backup/restore, permission provisioning и architecture-check. Если меняется `app/PilotHttp`, обязательна HTTP qualification. Retired rapid-pilot launch checks не возвращать в gates; shared characterization contracts сохранить. Локально только bounded checks, полная матрица — один exact-source CI после реализации.

## Risks / Trade-offs

- Атомарность большого файла → длительная DB-транзакция ограничивается заранее подготовленным DML; измерить на 2668 объектах и полном размере профиля на dev, не подменять требование частичными commit.
- Универсальный overwrite → непустые реквизиты могут заменить ручные; обязательные reviewed preview, причина и old/new история делают действие проверяемым.
- Заполнение только legacy fields → ручной override скроет импорт; использовать единую effective projection.
- Несогласованность технических характеристик и классификации → две версии паспорта и явная граница финансовых потребителей.
- Отказ одного адреса останавливает пакет → сохранять полную диагностику и успешно выполненные проверки для resume.
- Timeout после обработки на стороне провайдера → потенциальное списание учитывается, автоматического повторения такой попытки нет; возобновление требует явного решения в пределах бюджета.
- Кэш устарел или переносимое evidence подменено → блокировка автоматического принятия, локальный отчёт и явное обновление, без молчаливого запроса/оплаты.
- Новые поля/secret wiring недоступны на стенде → UNKNOWN, без обещания production-ready и без fallback к частичной загрузке.

## Migration Plan

1. Read-only сверка source schema и dev/prod: версии, unique IDs, manual overrides, existing objects, объёмы. Первичные доказательства приватно вне Git.
2. Аддитивные canonical migrations и backfill только доказуемых связей; никаких migrations из HTTP/импорта. Обновить readiness, restore inventory и операторскую документацию.
3. Настроить операторский principal, secret injection ДаДаты, private source/report storage и runtime dependency. Существующие записи не переписывать при включении функции.
4. Проверить preview/apply/replay/resume/overwrite/concurrency и rollback полного пакета на dev; затем приватный срез 2027 (контроли 2668 объектов / 667 UNOM до сверки с существующей БД).
5. После acceptance на dev и независимых проверок подготовить backup/restore evidence и новый preview prod. Фактическая загрузка — отдельный операторский этап, не следствие создания proposal.
6. При необходимости отката до commit — rollback транзакции. После commit — остановка импортов и разбор по истории, без массового удаления объектов, на которых уже началась работа. Rollback приложения только после проверки обратной совместимости additive schema; восстановление backup — по согласованному runbook.

## Open Questions

Source/live readiness frontier v39 и отсутствие ext-zip на стендах подтверждены. Требования к runtime principal, кэшу/переносимому evidence и лимиту попыток должны войти в обновлённые executable spec/tests и prepared plan. Независимые findings первого Gate 3 остаются открытыми. Владелец запросит разрешение Ильи на платные обращения; до подтверждения разрешения продолжается только разработка/проверки без live paid calls, ключи не копируются на стенды и не сохраняются в Git.

### 8. Исправление ёмкости пакета — 19.11.2026

Final review v1 доказал, что удержание/сортировка полного отчёта и единый SQL-параметр не обслуживают даже реальный объём 2668 объектов. Ограничения среды сохраняются: PHP256 MiB, обычный MariaDB packet16 MiB. В v40 добавляется `fm2_address_import_payload_chunks`, без AUTO_INCREMENT; current recovery113/60. Это реализация исходного scope, а не новый пользовательский режим.

Пакет хранит компактные manifest/pointer, а неизменяемые поколения содержат source objects, report objects/rows и provider evidence. Один chunk имеет целевой размер256 KiB, не более128 элементов и жёсткий размер4 MiB; все SQL-параметры остаются ограниченными. Ключ: package/generation/kind/ordinal, число элементов и SHA256 проверяются. FK на package — RESTRICT, не CASCADE; опубликованные и прежние поколения не переписываются и не удаляются. Частичное поколение невидимо до атомарного переключения pointer. В apply новые строки результата, mutation history, operation manifest и pointer фиксируются той же транзакцией каталога. Не добавлять очистку orphan/history в этот scope.

XLSX сканируется построчно, без полной матрицы ячеек. XML читается непосредственно из ZIP stream; локальный runtime PHP8.5 поддерживает `XMLReader::fromStream`. Приватный 0600 spool декодированных shared strings допустим для ограниченного random access, произвольное извлечение архивных путей запрещено. Сохраняется полный неизменённый снимок входа либо эквивалентные source chunks; parser/hash используют один снимок. Все локальные проверки заканчиваются до первой provider-попытки. Предел — индекс строки20000 включительно, то есть19994 записи в строках7..20000; строка20001 отвергается.

Top-level PHP outcome остаётся array; rows/objects/providerEvidence могут быть неизменяемыми повторно итерируемыми Countable/ArrayAccess sequences. Публичные JSON-массивы, порядок, значения и все строки отчёта остаются прежними; production CLI/export используют потоковый writer. Малые тестовые потребители явно материализуют sequences только для сравнения. Empty source cells получают SKIPPED_EMPTY, без увеличения filled/replaced.

Preview hash protocol v2 вычисляется по компактному header и каноническим элементам в публичном порядке с однозначным framing type/ordinal/length. Границы chunks не влияют на digest. Полный массив отчёта или SQL packet не создаётся даже ради хеша, replay, report или CSV/JSON. Подписанный address bundle также обрабатывается ограниченными элементами; подпись проверяется до cache writes, immutable snapshot исключает замену между проверкой и записью.

Regression:2668/667 fake addresses проходят preview, apply, report/export, replay и реальный backup/restore при256 MiB и обычном packet;19994-record boundary проходит offline preparation/полный потоковый отчёт, следующая строка блокируется до provider. Полнота проверяется самостоятельным подсчётом всех32 field rows, а не только счётчиком manifest. Повреждённый/неполный chunk никогда не публикуется как успешный отчёт.

### Canonical prefix compatibility correction

The seventh v40 table keeps the established canonical prefix through25 bytes.
MariaDB auto-generated FK naming exceeds its64-byte identifier limit for chunks,
so use a short deterministic prefix/table-derived constraint identifier; constraint
names are not public schema semantics. Existing compatible automatic names remain
accepted without rename/ALTER. Preflight still validates every existing family
before creating a missing sibling; a compatible six-table interrupted migration
can create chunks and complete. Root tests cover two prefixes in one database,
exact schema, fresh/repeat/recovery and all adjacent current-frontier consumers.

### Read-only identity lookup cost and fresh-cache refresh correction

CI44632946488 exhausted the45minute integration job after capacity itself passed
in1711.971s. A tight public preview probe isolated O(source*catalog) repeated scans:
2674 unrelated rows give5352/10704/107040 full-scan reads for1/2/20 source rows.
The larger regression observes1166/322041/644041 total handler reads for small
catalog20, populated catalog20, populated catalog40. Backup/restore is not in this
probe; repeated identity resolution is the measured cause. No limits/coverage rise
or reduction is accepted as the correction.

Store findMany(sourceObjects) returns a requested-only AddressRegisterIdentityLookup;
find(ordinal,fields) preserves request identity even when caller skips duplicates.
Requests and relevant full rows live in private temp spools; memory contains
domain-separated digest indexes, offsets and <=2 distinct witnesses per input.
Scan the same left-joined effective/manual/source projection using keyset pages
<=256; first page must preserve the full existing ID range. Exact comparisons
verify digest candidates. Prevalidate and preparation each create/discard their
own lookup; preparation builds after provider work. Apply retains current find,
locks and revision checks unchanged.

A new freshness regression also exposed explicit refresh being discarded in favor
of the initially observed fresh cache row. During refresh, completed-cache reuse
must distinguish a newly completed immutable entry from that initial entry.
Retain existing default reuse, cap reservation, held-claim single-flight, unknown
outcome and explicit retry semantics. Root owns all regressions; separate store
and application executors implement after Gate3v16; independent final review follows.

Gate3v16 found same-clock ordering incomplete: random UUID tie-break can select
old evidence even after a refresh. The complete v17 correction adds immutable
per-request entry_version to the still-unpublished v40 cache table, positive CHECK
and UNIQUE(request_hash,entry_version), no AI/table-count change. Allocation uses
a short DB+prefix-scoped advisory lock shared by claimCache and the existing atomic
importCache transaction; release before transport/in finally. Choose completed
cache by captured_at/entry_version; export same-clock entries in that order so
signed target import retains causal order with target-local versions. Old UUIDs
remain opaque IDs, old evidence/timestamps immutable. Same-clock max-old-UUID
tests and late import rollback prove this across refresh, default, resume and
transfer. Explicit retryUnknown must force past unchanged old fresh evidence too.

SQL ownership closure: MariaDbAddressRegisterImportStore owns the exact paged
candidate SQL generator. AddressRegisterIdentityLookup receives source objects
and an iterable of candidate rows, synchronously builds its immutable spools/index,
and contains no database connection, prefix, table or SQL knowledge. Semantics,
256-row bound and public findMany/ordinal lookup interface are unchanged.

## Delta26.11.2026:21 импортируемое поле

A13 заменяет прежнее решение о загрузке existing/costs. Parser проецирует только
B–H/P–AC, полная XLSX шапка/raw сохраняются. Registry/effective history не сужаются:
ранее сохранённые11 полей читаются как прежде. Pending старые32-field packages
требуют нового preview, immutable applied reports/replay сохраняются.
Root — contract/tests; отдельный sol/low executor — production; planner выбирает
Gate3/final и full CI. Исторический dfb6927f/reviewv4/CI44650539950 — baseline,
не проверка этой дельты. Проверяются parser, apply/overwrite, legacy retention,
JSON/CSV, null dates→fill, replay, capacity/restore, architecture/adjacent.
DDL/frontier/runtime dependencies/permissions не меняются; schema и restore
проверяют совместимость. UI и финансовые вычисления не затронуты. Используется
тот же application mutation seam; без live adapters/импортов/платных вызовов.

## Main reconciliation26.11.2026

Merge origin/main f935c748 required for PR307 mergeability. Preserve main email40
and OTIZ41, append address42; combined recovery118 tables/64 AUTO_INCREMENT.
A14 supersedes older v40/frontier113/60 references for the combined candidate.
Preserve upstream stops plus importer provenance, shared registry/card/readiness
and all verification consumers. Root resolves tests/spec/docs; separate executor
resolves production/policy. Gate3 then focused combined-schema/restore/adjacent,
final independent review and exact CI; no main push, real migration or deploy.

## Home dev integration after main43
Owner26.11 authorized home deployment. Preserve main2ea60f1d migrations42/43
and append address44 (119 tables/65 AI). A15 owns upgraded acceptance; root
seeds nonempty external number/reclamation plus predecessor facts/AI and retains
full restore. Separate executor resolves production; root resolves frontier
tests/policy, fresh Gate3/final/CI before deployment. Preserve home state/WIP
and backups, use existing home compose route; no actual import/paid calls,
PR merge/main push or corporate deploy. No unrelated feature redesign.


26.11.2026 owner Moscow/rapid-preparation delta: normative A16 in
`specs/ADDRESS-REGISTER-IMPORT-001.md`. Preserve original address/cache identity,
transmit Moscow context, support source house grammar, accumulate quality errors
without weakening acceptance or resetting budget. No schema changes. Paid preview
authorized up to667 cumulative attempts; no partial or full apply until12367 resolved.
