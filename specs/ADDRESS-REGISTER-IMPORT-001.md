# ADDRESS-REGISTER-IMPORT-001

## Простыми словами

Оператор загружает адресный перечень через CLI. Во время preview ДаДата проверяет дома, оператор видит изменения, apply публикует весь пакет одной транзакцией. Обычный режим только дополняет неизвестное; overwrite заменяет непустыми значениями, сохраняя историю. Пустые даты допустимы в каталоге. Документы и процессные факты не меняются.

Версия 1.2 (capacity correction 2026-11-19), включая подтверждённое уточнение минимизации расходов 2026-11-18: подтверждены в беседе и поручением «Погнали». Полный нормативный набор требований и сценариев включён по ссылке: [OpenSpec](../openspec/changes/address-register-import/specs/installation/address-register-import/spec.md). Этот документ уточняет наблюдаемые seams и матрицу проверок, не отменяя требований OpenSpec.

## Public seams

CLI `php bin/fmonitor2-address-register.php <estimate|preview|apply|resume|report|export-evidence|import-evidence>` и эквивалентные Yii `address-register/<action>` используют одного application owner. Аргументы только `--name=value`, кроме bare `--overwrite`; повторы и неизвестные аргументы отвергаются. Preview: `--file`, `--year` (2000..2100), `--contract`, `--target` (dev/prod), optional overwrite/reason. Estimate не выполняет платных запросов. Preview/resume по умолчанию используют только пригодный кэш; новые обращения требуют `--allow-paid-calls --max-paid-requests=N`. Явный `--refresh-addresses` также требует бюджета. Лимит учитывает все потенциально оплаченные попытки операции и не сбрасывается при resume. Apply: `--package` UUID, `--preview-sha256` 64 lowercase hex, `--operation` UUID, `--target`. Resume/report: package/target. Export-evidence: destination/target; import-evidence: file/target. CLI переносит подписанный JSON без секретов; HMAC-key берётся из FMONITOR_IMPORT_EVIDENCE_SECRET. Resume принимает allow-paid-calls/max-paid-requests/retry-unknown/budget-reason; смена лимита требует непустой причины. `--reason` обязателен и непуст только при overwrite. Вывод JSON в stdout (русские summary/messages внутри), подробный CSV/JSON через report в настроенный private root. Неинтерактивная команда не спрашивает ввод; preview hash является явным подтверждением просмотренного отчёта.

Public composition seam для deterministic integration tests и CLI:

```php
ProductionAddressRegisterImportFactory::create(
    mysqli $db, string $processPrefix, string $legacyPrefix,
    string $target, int $operatorUserId, AddressRegisterAddressProvider $provider,
    callable $clock, array $configuration = []
): AddressRegisterImportApplication
// AddressRegisterAddressProvider::clean(string $address): array
$owner->estimate(string $path, int $year, string $contract): array;
$owner->preview(string $path, int $year, string $contract, bool $overwrite=false, string $reason='', array $options=[]): array;
$owner->apply(string $packageId, string $previewSha256, string $operationId): array;
$owner->resume(string $packageId, array $options=[]): array;
$owner->report(string $packageId): array;
$owner->exportReport(string $packageId, string $privateDirectory): array;
$owner->exportAddressEvidence(): array;
$owner->importAddressEvidence(array $signedBundle): array;
```

Provider port возвращает один raw clean/address object, бросает безопасное исключение при transport failure. Production endpoint фиксирован `https://cleaner.dadata.ru/api/v1/clean/address`; произвольный URL через argv/env не поддерживается. Тестовый fake задаётся только в application composition, не доступен через production env.

Read-only seams адаптеров: `AddressRegisterWorkbook::read(path, year, contract)` возвращает `objects` (row/fields/raw), `excluded` count и `errors`; `AddressRegisterAddressValidation::validate(source, response)` возвращает `accepted` boolean, `reasonCode` nullable, `address`/`houseFiasId` при успехе. `AddressRegisterDadataProvider` реализует provider port; constructor принимает key/secret и optional callable transport `(url, headers, body, array policy): array{status:int,body:string}`. Policy содержит connectTimeout=5,totalTimeout=15,maxResponseBytes=1048576,verifyTls=true,followRedirects=false. Один clean = одна HTTP-попытка; retries выполняет application owner после резервирования бюджета, не скрытый transport. `AddressRegisterProviderFailure` имеет публичные reasonCode/retryable/outcomeUnknown и constructor с этими аргументами; transport exception даёт PROVIDER_OUTCOME_UNKNOWN, malformed/oversize response — PROVIDER_RESPONSE_INVALID, 401/403 — PROVIDER_AUTH_FAILED, 429 — PROVIDER_RATE_LIMIT, 5xx — PROVIDER_UNAVAILABLE. Только 429/5xx retryable; exception не включает headers/body/ключи. CLI не открывает transport override. Factory и CLI используют эти же правила.

`configuration`: `evidenceSecret` (>=32 bytes для export/import HMAC-SHA256), `cacheTtlDays` default90. `options`: `allowPaidCalls` defaultfalse, `maxPaidRequests` default0 (0..100000), `refreshAddresses` defaultfalse, `retryUnknown` defaultfalse, `budgetReason` для явного расширения бюджета/resume неизвестной попытки. Invalid types/unknown keys отвергаются до provider. Результат estimate: status=estimated, cost={uniqueAddresses,cacheHits,cacheMisses,cacheStale,estimatedRequests,estimatedRubles}; preview без разрешения и без кэша status=blocked/reasonCode=PAID_CALLS_NOT_ALLOWED, сохраняет возобновляемый package. При разрешении с недостаточным cap — blocked/REQUEST_BUDGET_EXCEEDED без превышения. Attempts считаются в cost.paidAttempts и cost.remainingBudget. Повтор ready package через resume использует сохранённый отчёт и не списывает.

Export/import evidence: signedBundle={version:1,provider:"dadata-clean-address",entries:[...],signature:<hex HMAC>}, подпись canonical payload без signature. Entries содержат request/response/time/version hashes, но не ключи, budget/target object IDs; validation signature выполняется раньше записей. Недоверенное evidence -> rejected/EVIDENCE_INVALID, повреждённые поля/будущие даты также отвергаются целиком. Export возвращает signedBundle; import возвращает status=imported. Актуальный cached response заново проверяется текущей quality policy.

`report` возвращает сохранённый статус package (ready/rejected/blocked/applied/failed/unknown), не меняет его. `exportReport` возвращает status=exported,applicationStatus=<сохранённый>,files={json,csv}; при ошибке status=failed,reasonCode=REPORT_EXPORT_FAILED,applicationStatus=<сохранённый>. Каталог/outcome при этом неизменны. CLI report применяет exportReport к настроенному private root; export success exit0, failure70. Private root обязан быть существующим обычным каталогом 0700 вне checkout/webroot, без symlink; создаваемые output files 0600, atomic rename. CSV заголовки русские, опасные начальные =,+,-,@,TAB,CR (включая после whitespace) обезвреживаются, JSON сохраняет реальные данные. Путь чужого файла не перезаписывается.

Return shape: `status` (`estimated`, `ready`, `blocked`, `rejected`, `applied`, `replayed`, `failed`, `unknown`, `exported`, `imported`), `reasonCode` nullable, `packageId` nullable, `previewSha256` nullable, `summary` (selected, excluded, created, updated, unchanged, filled, replaced, warnings, errors), `rows` и `errors`. Row detail содержит row/registrationNumber/objectId/field/action/old/input/value/reasonCode/message/nextAction. Preview считает предполагаемые изменения; apply/report после commit — фактические. Report дополнительно возвращает исходные нормализованные объекты в `objects`, с `fields` и `raw`, и provider evidence. Errors — массив структур с row/field/reasonCode/message/nextAction; ошибки независимых валидируемых строк собираются целиком.

CLI exit: 0 estimated/ready/applied/replayed/report/exported/imported; 2 blocked (PAID_CALLS_NOT_ALLOWED/REQUEST_BUDGET_EXCEEDED) и 2 rejected data/address/identity; 64 configuration/argv; 69 database/provider unavailable; 70 confirmed persistence/export failure; 75 stale preview, operation conflict или unknown commit; 77 unauthorized; 78 schema unavailable. Результат export failure отдельно сохраняет applied outcome и не утверждает rollback.

### Сохранение входных границ при потоковой обработке

CLI/application принимают исходный XLSX только из локального обычного файла: URL/нефайловые streams и специальные файлы отклоняются до их открытия/чтения, provider не вызывается. Размер и тип открытого файла проверяются перед созданием снимка. Потоковый reader подписанного evidence обязан соблюдать строгий JSON: фактический NUL после завершённого документа и любой иной непустой хвост не являются EOF; такой файл, как и недоверенная подпись, даёт CLI exit2/status=rejected/reasonCode=EVIDENCE_INVALID без stderr и без новых cache rows. При unknown COMMIT сохраняется полный рассмотренный preview-отчёт (summary, все32 rows, objects, providerEvidence), явно помеченный unknown; недоступность DB после ACK-loss не разрешает усечь ответ.

## Авторизация и среда

Проверяемый configured operator user ID задаётся только deployment env `FMONITOR_IMPORT_OPERATOR_USER_ID`, не argv/Excel. Пользователь активный, активирован, с `objects.read` и `objects.details.edit` в действующей роли ФКР/руководителя. Для CLI дополнительно обязателен configured `FMONITOR_IMPORT_DB_PRINCIPAL`, точно совпадающий с `CURRENT_USER()`; веб principal не получает это значение/authority. Target из `FMONITOR_IMPORT_TARGET` должен совпасть с argv и package. Existing FMONITOR_DB_* и prefixes используются без default target. ДаДата: `FMONITOR_DADATA_API_KEY`, `FMONITOR_DADATA_SECRET_KEY`; источник/отчёты: `FMONITOR_IMPORT_STORAGE_ROOT` вне webroot/Git, 0700/0600. Factory seam проверяет actor/target/schema и не предоставляет обхода permission checks. Replay требует действующих полномочий.

## Формат, mapping и значения

Профиль v1: лист «Адресный перечень», шапка 5–6, строки объектов с 7; заголовки проверяются после нормализации whitespace/case, допустимы только явные варианты известной опечатки «Материл». A — ordinal provenance, B–H — year/regnumber/unom/sourceArea/sourceDistrict/address/entrance. I–O — existingFloors/existingWeight/existingPitType/existingPitMaterial/existingCabinDoor/existingShaftDoor/existingSpeed. P–V — floors/weight/pittype/pitmaterial/cabinDoor/shaftDoor/speed планируемого оборудования; W–X — frequencyDoor/frequencyMain. Y — designPeriodRaw (сохраняет точность месяца); Z — installationDays; AA — equipmentRequestDate; AB–AC — plannedStartDate/plannedFinishDate; AD–AG — designCost/replacementCost/discountedDesignCost/discountedReplacementCost. Contract из параметра проверяется против шапки; отсутствие даты договора не фабрикуется.

Обязательные значения новой строки: year, regnumber, unom, address, entrance. Остальные допускают unknown. Строки иных лет исключаются; строки объектов распознаются по заполненному B или A с данными C:H, чтобы malformed row не исчезала молча. Итоги и примечания после таблицы не считаются объектами. Пустая AH допустима, непустая неизвестная колонка блокирует. Для поддержанного header проверять все колонки, включая пустые в data.

Строковые идентификаторы trim, UTF-8, без NUL/control, регномер/UNOM <=120 bytes, entrance <=80 bytes. Числовая ячейка ID должна быть целой и точно представимой (<=15 десятичных цифр); строки сохраняют нули/буквы. Даты: Excel serial с учетом epoch 1900/1904, либо валидные DD.MM.YYYY/ISO YYYY-MM-DD, без времени/нулевых дат. Если обе даты есть, finish >= start. Даты project/request не подменяют installation dates.

Числа: integer floors 1..200, weight 1..100000, days 1..3660; speed положительная <=20, decimal округление half-up до 2 знаков, trailing zeros несущественны. Money >=0, <=999999999999.99, decimal half-up 2, без binary float; ноль настоящий. Bool только да/нет (case-insensitive), 1/0; отсутствие = unknown. Словари включают все исследованные значения: шахта глухая, железо-сетчатая, комбинированная, каркасно-приставная; материал железобетон, кирпич, металлокаркас и сетка/стекло, железобетон и металл, кирпич и металл; двери автоматические/ручные. Raw и canonical хранятся отдельно. Общая current проекция содержит планируемые технические сведения; existing сохраняется отдельным набором. Никакой прямой записи premium operands.

Bounded input: 20 MiB compressed, 128 MiB суммарно expanded, 2048 zip entries, индекс строки worksheet <=20000 (19994 строки объектов с7), 64 колонки, 32768 символов в одной ячейке; превышение `INPUT_LIMIT_EXCEEDED`, без каталога. Не извлекать ZIP на файловую систему, не загружать внешние ссылки/DTD. Формулы в выбранных data rows не вычисляются и отклоняются; формулы в footer игнорируются, контрольные суммы считаются из строк.

## Идентичность лифта и общая площадка

Пара UNOM+подъезд не является уникальным идентификатором лифта. Разные регномера при одном UNOM, подъезде и подтверждённом доме обозначают разные объекты; пакет таких строк допускается, provider evidence переиспользуется. Повтор полного source identity в одном срезе даёт DUPLICATE_SOURCE_OBJECT; повтор регномера с противоречащими UNOM/подъездом/домом даёт IDENTITY_CONFLICT. Неоднозначное совпадение регномера в БД и несовпадение текущих непустых идентификационных реквизитов по-прежнему блокируют весь пакет, включая overwrite. Перестановка строк и replay не создают дублей. Это уточнение source identity из OpenSpec, а не разрешение объединять объекты по адресу.

## Согласованная нормализация справочников

Все уже принимаемые parser варианты регистра, пробелов и разделителей для `pittype`/`pitmaterial` должны доходить до application и сохраняться одним соответствующим кодом общего редактора. Raw R/S остаётся неизменным. Канонические коды: Глухая40, Железо-сетчатая201, Комбинированная202, Каркасно-приставная203; Железобетон41, Железобетон и металл72, Кирпич86, Кирпич и металл112, Металлокаркас и стекло123, Металлокаркас и сетка124. Равнозначное написание при повторном импорте даёт unchanged, без новых detail events или provider attempts. Нормализация не расширяет словарь исходного профиля: неизвестные значения и legacy-коды7/9 вместо допустимого названия по-прежнему отклоняются parser как VALUE_INVALID до provider.

## ДаДата acceptance

По [официальной документации clean/address](https://dadata.ru/api/clean/address/) на 2026-11-18: принимать только qc=0, qc_house=2, fias_level=8, fias_actuality_state=0, непустые result/house, валидный UUID house_fias_id, пустые unparsed_parts и отсутствие flat/room. Для московского профиля region_iso_code=RU-MOW. Проверить номер/корпус/строение против явно извлечённых из source house tokens, не только quality flags. Неясный разбор исходного номера блокируется. Примечание о диапазоне подъездов отделяется, территориальные уточнения сохраняются. Адресный текст сокращений может измениться; идентичность дома не меняется молча.

Transport: connect <=5 s, total <=15 s, response <=1 MiB, TLS verify обязателен. До каждого вызова долговечно резервируется единица бюджета. Retry 429/5xx bounded (максимум 3 attempts) и ограничен общим cap; 401/403 не ретраятся. Timeout/потеря соединения после отправки без достоверного результата даёт `PROVIDER_OUTCOME_UNKNOWN`: возможное списание учитывается, автоматического retry нет. Provider failure `PROVIDER_UNAVAILABLE`; неправильный address результат `ADDRESS_UNVERIFIED`. Межпакетный кэш действует по точному request/context/version hash, default TTL90 дней. Новый preview, overwrite и resume не обновляют пригодный ответ. Просроченные/отрицательные результаты показываются без автоматической оплаты. Signed evidence переносится dev → prod с проверкой доверенной подписи/версий/request hash/срока и повторной локальной quality validation; target preview повторяет только сверку БД. Файл кэша не содержит credentials или target object IDs. Отсутствие street в подтверждённом доме Зеленограда само по себе не ошибка.

## Матрица приемки

| ID | Наблюдаемый результат |
|---|---|
| A01 | CLI argv/permission/target/schema reject до изменения каталога и вызовов provider |
| A02 | XLSX parser: headers/years/hidden rows/strings/limits/formulas, два паспорта, dates/decimals/raw |
| A03 | Provider: accepted house, quality rejects, component mismatch, network/auth/429, dedup/resume |
| A04 | Identity: новые ID, совпадение, неизвестный UNOM, duplicate/ambiguous/contradictory rows, reorder |
| A05 | Unknown dates видимы в queue/card/calendar без ложных открытий, статусов и обязательного графика |
| A06 | Fill-only/unchanged/warning/empty/zero/false/manual tombstone; согласованная адресная версия |
| A07 | Overwrite: reason + preview, manual values replaced, empty preserved, process snapshots untouched |
| A08 | Atomic all-or-nothing включая audit fault и ошибку последней строки |
| A09 | Stale preview/target mismatch/race/idempotence/commit unknown, не больше одного нового объекта |
| A10 | Полные русские CSV/JSON reports/counters/provenance, no secrets, export retry без reapply |
| A11 | Canonical migration/readiness/schema/restore/runtime deps, no DDL из importer |
| A12 | Offline estimate, prevalidation без оплаты, межпакетный/negative cache, TTL, signed evidence dev → prod, общий durable cap/retry/resume, неопределённое списание и concurrent request dedup |

Автор спецификации/будущих тестов root. Executor и independent reviewers gpt-5.6-sol/low, исключений авторства нет. Full local test запрещён, planner-required Gate 3/final и exact-source CI обязательны. Live ДаДата/dev/prod acceptance фиксируются отдельно от deterministic tests.

## Уточнение расходов и состояние тестов

Платные вызовы на найденных legacy-ключах ожидают отдельного сообщения владельца о разрешении Ильи; чтение баланса не является разрешением расходовать деньги. До этого разрешены только fake-provider tests и offline checks; ключи не копируются на стенды/Git.

Нормативные требования экономии и сценарии — в OpenSpec разделах «Минимизация расходов и явный бюджет обращений» и «Перенос адресных проверок с dev на prod». Прежний test candidate и verification input требуют дополнения A12 и исправления семи findings Gate3 v1; до нового полного RED/review реализация не допускается. Transport/composition signatures, budget/evidence/report outcomes уточнены выше; изменения покрываются новым root test candidate для независимого Gate 3.

## Persistence и восстановление

Canonical migration v40 (source frontier v39) добавляет fm2_address_import_packages (исходные bytes/raw rows, параметры, durable preview/failure/report и бюджет), fm2_address_import_cache (versioned provider evidence), fm2_address_import_attempts (durable paid attempts/unknown outcomes), fm2_address_import_objects (однозначная связь/дополнительные реквизиты объекта), fm2_address_import_operations (commit outcome/idempotency), fm2_address_import_ids (DB-owned AUTO_INCREMENT allocation) и fm2_address_import_payload_chunks (неизменяемые ограниченные части источника/отчёта). Всего добавляются ровно семь таблиц; single-flight lock реализуется без дополнительной persisted table. Только fm2_address_import_ids имеет AUTO_INCREMENT, остальные новые identity keys — UUID/hash/object ID; current recovery oracle: 113 таблиц и 60 AUTO_INCREMENT. Все transactional families InnoDB, UTF-8, JSON валидируется, unique identities/operation keys enforced. Public schema seam AddressRegisterImportSchemaMigration::apply(mysqli,prefix) и ::assertCompatible(mysqli,prefix); apply additive/idempotent, readiness ничего не исправляет. Определение literal columns/indexes/constraints — в migration и независимых schema expectations; никакой mutable legacy history при upgrade не меняется.

Объекты/связи/accepted address versions в objects, их текущие реквизиты и общие detail events, operation outcome — единая apply-транзакция. Package/cache/attempt preparation остаётся отдельно для диагностики отказов. Allocation reserve rows бизнес-транзакции откатываются вместе с objects, допустимы только gaps AUTO_INCREMENT. Актуальные native IDs не совпадают с Excel ordinal и не получаются SELECT MAX+1. Все существующие IDs учитываются allocator при миграции/выделении, новые colliding identities не применяются.

RuntimeRecoverySchemaV40 и RuntimeRecovery включают новые семейства/auto sequences и version40. Public backup/restore обязан сохранить исходные payloads, cache, оплаченные attempts, operations, object links и append-only историю; после restore offline preview использует пригодный кэш без новой оплаты. Runtime требует ext-zip/XML/curl. Обновляются Docker dependency template/generated Dockerfile, readiness и независимый current-schema oracle. Existing tests остальных миграций не удаляются и не ослабляются.

## Уточнение наблюдаемых отчётов и ручной очистки

Для уже связанного import source ручное очищение текущего regnumber не уничтожает immutable source→object identity. При неизменных прежнем регномере источника, UNOM/доме/подъезде ordinary import сохраняет этот tombstone; overwrite может восстановить тот же source regnumber. Противоречащий непустой текущий идентификатор по-прежнему блокирует пакет. Новые объекты с неизвестной идентичностью не подбираются по догадке.

Отчёт включает source={sha256,sheet},target,mode (fill_missing/overwrite),phase (preview/applied),providerEvidence=[{requestHash,responseHash,capturedAt,provider,response}]. У каждой field row есть sheet,row,target,mode,registrationNumber,objectId,field,action,old,input,value,reasonCode,message,nextAction; preview objectId=null для нового объекта, applied содержит созданный ID. FILLED success reasonCode=null, message preview «Будет заполнено пустое поле.», applied «Поле заполнено.»; nextAction preview «Проверьте изменения и примените пакет.», applied «Действий не требуется.». Контрактные количества считаются по32 исходным полям B:AG: default synthetic row содержит28 непустых полей, поэтому summary={selected:1,excluded:0,created:1,updated:0,unchanged:0,filled:28,replaced:0,warnings:0,errors:0}. Contract membership и provider metadata не увеличивают filled/replaced. JSON сохраняет исходные значения, CSV их безопасное представление. Private output root проверяется на стадии export, ошибка экспорта CLI report=70/REPORT_EXPORT_FAILED, даже если каталог уже применён.

## Capacity correction 2026-11-19 — обязательное уточнение реализации

Final code review v1 и private synthetic probes2668/667 опровергли пригодность eager report representation: OOM256 MiB при canonical JSON, затем packet>16 MiB при диагностических512 MiB. Поднятие memory_limit/max_allowed_packet не является исправлением. Поддержанный пакет и верхняя граница должны работать на штатных256/16 MiB; локальные/CI проверки не вызывают реальный provider.

В v40 седьмая таблица `fm2_address_import_payload_chunks` хранит package_id/generation_id/kind/ordinal/item_count/payload_json/payload_sha256/created_at_utc. PK первых четырёх колонок, FK package RESTRICT, JSON/checksum/size validation и append-only lifecycle. Новый AUTO_INCREMENT не вводится. Целевой chunk256 KiB/128 элементов, hard cap4 MiB (любой SQL-параметр ограничен). Package source/report и operation outcome содержат компактные manifests; неподтверждённые поколения не видны через published pointer, старые поколения не удаляются. Publication applied chunks и outcome входит в ту же transaction, что каталог/аудит. Current recovery —113 tables/60 AUTO_INCREMENT, седьмое семейство также входит в строгую readiness и dump/restore.

Точный SQL-контракт chunks (порядок колонок): `package_id CHAR(36)`, `generation_id CHAR(36)`, `kind VARCHAR(32)`, `ordinal INT UNSIGNED`, `item_count SMALLINT UNSIGNED`, `payload_json LONGTEXT`, `payload_sha256 CHAR(64)`, `created_at_utc DATETIME(6)`. Все NOT NULL, без DEFAULT и EXTRA/AUTO_INCREMENT; строковые колонки utf8mb4_bin, engine InnoDB, table collation utf8mb4_bin. Единственный индекс — PRIMARY(package_id,generation_id,kind,ordinal), без prefix lengths; FK package_id → packages(package_id), ON UPDATE RESTRICT / ON DELETE RESTRICT. Независимые CHECK: `JSON_VALID(payload_json)`, `OCTET_LENGTH(payload_json) <= 4194304`, `payload_sha256 = SHA2(payload_json,256)`, `JSON_TYPE(payload_json) = 'ARRAY'`, `item_count BETWEEN 1 AND 128`, `JSON_LENGTH(payload_json) = item_count`. Проверяется точный смысл каждого ограничения, а не наличие подстроки в ослабленном выражении. `apply` и `assertCompatible` отвергают любую несовместимую существующую chunks-table до создания недостающих siblings и без ремонта DDL. Имена CHECK/FK не являются публичным контрактом.

`AddressRegisterWorkbook::scan(path, year, contract, onObject, onError): array{selected:int,excluded:int,errors:int}` выдаёт объекты/ошибки callback-ами по мере чтения, без матрицы ячеек. Существующий `read` сохраняет результат objects/excluded/errors как collecting adapter. XML читается из ZIP stream; безопасный приватный spool декодированных shared strings допустим, произвольные ZIP entry paths на диск не извлекаются. Невалидный источник целиком блокирует provider calls. Индексы строк7..20000 дают19994 записи, строка20001 отвергается. Источник разбора и file hash относятся к одному неизменяемому снимку.

Top-level application returns остаются array. `rows`, `objects`, `providerEvidence` (и `entries` экспортированного bundle) могут быть неизменяемыми повторно итерируемыми `IteratorAggregate & Countable & ArrayAccess` sequences; values/index/order те же. Для маленького потребителя допустима явная материализация; production hash, CLI, CSV/JSON export, apply/replay/restore никогда не материализуют весь пакет. JSON на внешней границе содержит обычные полные массивы без ссылок вместо данных. `AddressRegisterResultWriter::write($stream, mixed $result): void` выдаёт тот же JSON ограниченными фрагментами, контролирует неполную запись; чувствительные данные/секреты в ошибки не попадают. Подписанный cache import проверяет весь неизменяемый payload до cache writes и также не требует одного гигантского JSON/SQL-параметра.

Preview SHA protocol `address-register-preview-v2` последовательно учитывает компактный header и canonical элементы с однозначным type/ordinal/length framing; разбиение на chunks не влияет на hash. Opaque previewSha256 остаётся64 lowercase hex. Нельзя изменить payload после публикации pointer; пропуск/повреждение части выявляется по count/digest и не выдаётся за успех.

Отчёт содержит все32 поля B:AG на строку, включая `SKIPPED_EMPTY` с old/input/final для пустого входа. Они не увеличивают filled/replaced/warnings и не очищают БД. Для default synthetic row по-прежнему filled28; всего32 field rows. Подготовка2668/667 даёт85376 field rows,667 fake calls при первом разрешённом preview и0 при повторе. Real-scale regression проходит apply/report/export, backup/restore и same-operation replay при256 MiB и16 MiB DB packet. Boundary preparation19994 valid rows с пригодным кэшем содержит639808 field rows; дополнительная строка блокируется до любых provider attempts.

CLI commit-loss regression вызывает неизменённый CLI/factory/application/store через тестовый TCP proxy, который доставляет COMMIT серверу, получает успешное подтверждение и закрывает соединение до передачи ACK клиенту; CLI сохраняет unknown/COMMIT_OUTCOME_UNKNOWN, exit75 и один commit. Обычный следующий CLI вызов с тем же operation ID читает replay без повторных фактов. Fault injection остаётся только в тестовой сетевой fixture; production hooks/env/argv не добавляются.

Отчёт после подтверждённого rollback apply считает created/updated/filled/replaced равными0; подготовленные строки не выдаются за опубликованные факты. В capacity test только независимый JSON oracle уже выгруженного файла может использовать отдельный процесс с большей памятью; приложение, parser/hash/store/apply/export/restore остаются в исходном256 MiB и не повышают лимит.

### A11: canonical prefix compatibility

v40 preserves the established canonical prefix range through25 bytes. Both fresh
v39→v40 and a compatible six-table partial v40 family must reach all seven
families without modifying existing table DDL. Multiple prefixes may coexist in
one database. Constraint identifiers are implementation details; FK semantics
remain exact RESTRICT. Repeat apply/assertCompatible perform no rename or repair.

### A04: bounded read-only identity lookup work

Supported package preparation must not rescan the full existing catalog for each
source object. Each read-only identity phase performs catalog work additive in
catalog and source size, with bounded pages and requested-match payloads spooled
outside RAM. Retain only requested keys/offsets and at most two distinct identity
witnesses per source row in memory; two witnesses mean an ambiguous identity and
still block the whole package. Effective manual registration/entrance/UNOM, NULL
tombstones, immutable source registration/hash, exact binary equality and object-ID
deduplication across match routes retain their existing meaning.

Pre-paid validation and post-provider preparation use separate fresh lookup views.
Each new preview/resume builds its own view. A manual edit during provider work
must be visible to preparation; a later native collision must block apply. No
preview lookup survives into the apply transaction: current find/revision/locking
and identity validation remain authoritative. No new domain uniqueness rule,
durable shadow projection, schema change or importer DDL is introduced.

The deterministic public-seam regression compares SQL handler read work for20
source objects before/after2674 unrelated catalog rows and then40 source objects:
added catalog work <=24000 handler reads, doubled-source added work <=2500.
It also asserts complete32-field reports and unchanged business facts/provider
calls/attempts for cached preparation. Full2668/667 and19994-object capacity,
restore/replay,256MiB/16MiB and existing concurrency checks remain mandatory.

### A12: explicit refresh of still-fresh evidence

An explicit refresh applies even when an accepted cache entry is within90 days.
Provider access requires paid permission and a positive remaining durable cap;
absent a newly completed shared version, budget0 blocks without a provider attempt. One permitted refresh reserves one attempt and creates
a new immutable evidence version, preserving older rows. Default subsequent
preview and ready-package resume reuse the new evidence without payment.

A concurrent refresh claim remains single-flight: a competing package pays0 while
the claim is owned and may resume from the newly completed evidence. When a
completion appears between initial observation and claim, its different immutable
entry identity may satisfy that refresh; the same pre-existing entry may not.
Unknown refresh outcome never silently falls back to older fresh evidence or
retries; explicit retry permission/reason and cumulative budget remain required.

Same-clock ordering is deterministic. The unpublished v40 cache table adds
`entry_version BIGINT UNSIGNED NOT NULL` immediately after request_hash, with no
default and no AUTO_INCREMENT, `CHECK(entry_version > 0)`, and ordered
`UNIQUE KEY request_version(request_hash,entry_version)`. Existing columns,
one_inflight unique key, request_time index and JSON checks remain exact. The
frontier stays113 tables/60 AUTO_INCREMENT. Drift or missing version rejects
both apply/assertCompatible before creating siblings; no DDL repair.

Entry versions are immutable, monotonically increasing among retained entries
for each request hash, allocated under a short database+prefix-scoped lock shared
by provider claims and atomic signed evidence imports. Release the allocator lock
before provider transport and on every failure; transport still uses one_inflight.
Cache selection retains inflight priority and orders completed entries by actual
captured_at then entry_version. No fictitious captured times or edits to old rows.
Signed export preserves capture/version order for equal-time entries; target import
assigns local versions in that authenticated order, with no source object IDs.
A late import fault rolls back the entire version set and releases its lock.

Explicit retry of an unknown refresh also must not reuse the unchanged original
fresh cache as if the unknown request had been resolved. Same-clock regressions
pin the old UUID to the largest valid v4 value, change the provider payload, and
require the new payload in refresh/default/resume/signed-transfer results, one
reserved refresh attempt, immutable history and zero calls by the competitor.

The held-provider regression also completes a different request hash before the
first provider is released, proving allocator coordination does not span transport;
its version sequence starts independently and it reserves exactly one own attempt.

An explicit retry of an unknown provider outcome with no remaining attempt budget
returns transient blocked/REQUEST_BUDGET_EXCEEDED, retaining paidAttempts and the
durable unknown package/report/claim. A budget denial is not a provider outcome
and must not clear uncertainty. Subsequent report and resume without retryUnknown
remain unknown/PROVIDER_OUTCOME_UNKNOWN even if the caller increases budget with
a reason. They neither call the provider nor fall back to old fresh evidence.
A later explicit retry with permission/reason/sufficient cap may recover normally.
This applies both with and without an older completed cache entry.

The retained unknown claim is the same row, not a reconstructed replacement:
entry_id, entry_version, lock_owner, lock_key and other pending-row bytes remain
unchanged across budget denial and budget-only resume; no extra cache version is
created. A valid explicit retry completes that same entry/version and clears its
locks. Completed older evidence remains immutable.
