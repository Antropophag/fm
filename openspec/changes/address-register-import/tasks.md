## 1. Обследование и Gate 1

- [x] 1.1 В выделенном worktree прочитать текущую цель, выполнить harness state и создать собственный delivery context после поручения на реализацию; проверить, что прежние задачи и navigation checkout не изменены. Evidence: private `~/.local/share/fmonitor-2/address-register-import/session.json`; root package `~/.local/share/fmonitor-2/delivery-harness/packages/20261118T180732Z-88659ab41c/package.json`. После уточнения расходов binding необходимо обновить.
- [x] 1.2 Выполнить read-only census source/dev/prod: schema frontier, идентификаторы, дубли, заполненность, ручные overrides, видимость объектов и процессные связи. Evidence: private `~/.local/share/fmonitor-2/address-register-import/dev-source-identity-census-20261118.json`; live readiness v39, dev 309 объектов, prod 0, исходные сведения не скопированы в Git. Строковые пересечения требуют сверки с проверенными адресами, не считаются доказанными дублями.
- [x] 1.3 Зафиксировать characterization существующего редактора, effective values, NULL dates, admission очереди, bootstrap cases, технических/финансовых проекций; результат — перечень текущих public outcomes и regression consumers без ослабления процессных gates.
- [x] 1.4 Root синхронизировать существующий `specs/ADDRESS-REGISTER-IMPORT-001.md` с уточнением экономии: offline estimate, межпакетный кэш/TTL, signed evidence dev → prod, общий долговечный лимит попыток и запрет автоматического повтора неопределённого исхода; закрепить полный CLI/exit codes, mapping и independent examples. Синхронизация выполнена: explicit signatures/statuses/report rows, v40 schema/113 tables/60 AUTO_INCREMENT (после capacity correction), A01–A12. Старый подход оплаты каждого preview отменён.
- [x] 1.5 Зафиксировать матрицу совместимости схемы/операторского principal/runtime XLSX и HTTP dependencies, backup/restore, отчётов и versioned mapping; подтвердить, что каждый неизвестный технический пункт design закрыт evidence либо явно блокирует соответствующий шаг.

## 2. Verification plan, RED и независимый Gate 3

- [x] 2.1 Создать `verification-input.json`, выполнить harness prepare по `tools/delivery/change-verification.md`, прочитать executable Quality Graph plan; подтвердить покрытие всех изменяемых границ и отсутствие unresolved obligations до Gate 2, sensitive route с gate3/final выбирает planner.
- [x] 2.2 Root написать синтетические XLSX fixtures и тесты public CLI для структуры, фильтров/годов, пустых дат, строковых ID, двух паспортов, словарей, денежных сумм, malformed input и bounded parsing; сохранить RED по отсутствующему поведению, не по ошибке setup.
- [x] 2.3 Root дополнить тесты provider boundary и закрыть findings v1: подтверждённый дом/отказы/компоненты, точные retries, timeout с возможным списанием, durable cap, отсутствие расходов до локальной валидации, межпакетный кэш/TTL/negative cache, concurrent request dedup, resume и подписанный перенос evidence dev → prod; проверить ноль повторных paid calls при overwrite/повторе/prod-cache, отсутствие live ключа в тестах.
- [x] 2.4 Root написать интеграционные тесты реальной изолированной БД для дозаполнения, overwrite ручных значений, пустых ячеек/tombstones, конфликтов идентичности, all-or-nothing, rollback аудита, replay/commit-unknown, race и stale preview; expected результаты задать независимо от production helpers.
- [x] 2.5 Root покрыть наблюдаемые каталог/карточку/поиск/календарь без дат, общую историю ручных правок и импортов, неизменность документов/назначений/фактических сроков и отчёты; подтвердить RED на public seams.
- [x] 2.6 Получить новый независимый verdict после исправлений v1 и четырёх findings `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v2.md`; полный candidate и A01–A12 matrix пересобраны, подготовить свежий plan и RED evidence; подготовить полный реконструируемый source package для независимого gpt-5.6-sol / low reviewer, не автора тестов, и получить новый Gate 3 verdict до реализации. Первый verdict CHANGES_REQUESTED не заменяется самопроверкой.

## 3. Реализация единого вертикального сценария

- [x] 3.1 Отдельному gpt-5.6-sol / low executor реализовать аддитивные canonical migrations и readiness/inventory для package/source/evidence/identity/register fields/history; проверить upgrade predecessor, повтор миграции, несовместимую схему, сохранность существующих данных и restore inventory focused checks.
- [x] 3.2 Реализовать поддерживаемый XLSX-профиль, явный год/договор, нормализацию и два набора технических сведений; проверить parser/mapping tests, отсутствие выполнения формул и сохранение исходной точности/значений.
- [x] 3.3 Реализовать ДаДата adapter, межпакетный кэш/resume/quality, offline estimate, durable budget и подписанный перенос адресного evidence; пройти reviewed fake-provider tests включая prod cache reuse и timeout outcome без повторной оплаты по умолчанию. Каталог во время подготовки не меняется.
- [x] 3.4 Реализовать общую внутреннюю операцию изменения реквизитов для импорта/редактора, effective projection, field provenance и tombstones; проверить дозаполнение/overwrite и сохранение существующего public manual-edit contract.
- [x] 3.5 Реализовать preview/apply с доверенным операторским доступом, stable identity allocation, одной транзакцией всего пакета, expected revisions и durable operation outcome; пройти атомарность, replay, race, stale preview и commit-unknown тесты.
- [x] 3.6 Реализовать явное включение новых объектов в каталог/карточку без дат и без ложного migration provenance; проверить видимость, роли, поиск, календарь, пустые даты и неизменность процессных gates.
- [x] 3.7 Добавить CLI adapter, русскую сводку, CSV/JSON и повторную выгрузку отчётов; проверить old/input/final values, счётчики, source row, объяснения/next action, CSV escaping, режим файла и отсутствие секретов.
- [x] 3.8 Обновить runtime dependency/configuration и операторский runbook с preview/apply/resume/report, overwrite, rollback/commit-unknown и примером dev/prod; проверить команды на disposable окружении без применения к production.

## 4. Интеграционная проверка и review

- [x] 4.1 Выполнить выбранные bounded focused checks, architecture-check и при изменении `app/PilotHttp` HTTP qualification; сохранить команды/полные приватные логи и итог без локального `make test`/`make verify`.
- [ ] 4.2 После отдельного подтверждения владельцем разрешения Ильи на расходы выполнить offline estimate и controlled dev acceptance с явным cap: 2668 исходных строк / 667 UNOM, mapping/суммы/адреса/null dates/replay. Зафиксировать число paid attempts и доказать отсутствие повторных вызовов при повторе; любой blocked row останавливает весь каталог. До разрешения — только fake provider, без копирования legacy keys на стенды.
- [x] 4.3 Подготовить полный candidate и независимый final review gpt-5.6-sol / low, не автора implementation; проверить acceptance coverage, schema/readiness/restore и соседние потоки, получить APPROVED только после закрытия findings.
- [ ] 4.4 После проверки corporate author/committer identity и наличия corporate remote создать согласованный commit, провести один exact-source CI по planner route; при сбое сначала собрать полный failed-job/REGRESSION_FAILURE inventory, затем исправлять, сохраняя disposition всех отказов.

## 5. Передача и Done

- [ ] 5.1 Свести delivery record с actual authors, spec/tests/review/CI, focused evidence, elapsed/rework и remaining UNKNOWN; проверить, что planning complete не выдан за реализацию или GREEN.
- [ ] 5.2 Подготовить prod runbook, проверить signed address evidence из dev и target-specific preview без повторных paid calls при пригодном кэше; сверить backup/restore, расходы и stale conflicts, передать пакет оператору. Production apply не выполнять автоматически в рамках реализации инструмента.
- [ ] 5.3 Подтвердить Done инструмента: один поддерживаемый формат, интегрированная ДаДата, каталог без дат, fill-only/overwrite, all-or-nothing/replay, понятные логи, независимые reviews и выбранный CI; затем архивировать change по отдельному поручению, сохранив deployment status отдельно.

Состояние Gate 2: тесты написаны root, source зафиксирован prepared packages; intended RED означает отсутствие реализации, не GREEN. Независимые Gate 3 v1/v2 дали CHANGES_REQUESTED; исправления предъявляются v3. Implementation 3.x и проверка поведения после реализации остаются невыполненными. Реальные provider вызовы запрещены до отдельного разрешения владельца.

Gate 3 v3 APPROVED: reviews/tests/ADDRESS-REGISTER-IMPORT-001-v3.md, exact source1c0e0f0d/snapshot b22ca765. Root тесты/контракт одобрены; GREEN, final review и CI ещё не выполнены.

Реализация 3.1–3.8 выполнена отдельными sol/low executors и заморожена для интеграционной проверки. Их bounded checks прошли; единый root verification snapshot, реальное disposable backup/restore, final review и exact-source CI ещё не завершены. Gate 3 v4 одобрил первый тестовый delta на восстановленном pre-implementation baseline; последующие уточнения (неопределённый ответ, canonical reimport, файловая граница, nonempty manual identity, ID collision, Yii и runtime readiness) предъявляются отдельным полным test-delta candidate. Это ретроспективная проверка усиленных regressions, не утверждение, что реализация ещё отсутствует в рабочей ветке.

Gate 3 final test delta v5 APPROVED без findings: reviews/tests/ADDRESS-REGISTER-IMPORT-001-v5.md, exact test-only source ba1e6e26 / snapshot4cfe68e5; все10 mapped test hashes совпадают с implementation candidate. Final code/architecture approval отдельно, ещё не получено.

## 6. Коррекция полного пакета после final review v1

- [x] 6.1 Root обновить нормативный capacity/streaming contract, семитабличный v40 frontier113/60 и executable tests; сохранить предыдущие GREEN/RED и два BLOCKING findings.
- [x] 6.2 Root добавить точный CLI unknown-commit/replay regression и real-scale/runtime/packet/boundary/restore proof; независимый corrective Gate3 до изменения реализации.
- [x] 6.3 Отдельные sol/low executors реализуют ограниченный parser, immutable payload generations, streaming hash/read/render и точный exit75, сохранив все прежние условия.
- [ ] 6.4 Выполнить bounded checks на замороженном полном candidate, включая большой пакет и restore; закрыть каждое finding v1 независимым final review, затем один exact-source GitHubCI.

Статус 19.11.2026: финальное ревью кода v1 CHANGES_REQUESTED с двумя блокирующими findings. 10 малых mapped tests и architecture GREEN не подтверждают пригодность большого пакета: private probes2668/667 воспроизводят OOM256M и DB packet16M. Пункты3.1/3.2/3.5/3.7/3.8 вновь открыты для необходимой реализации ёмкости, остальные подтверждённые семантики сохраняются. Supplementary test-only review v6 одобрил fixture mount; он не является code approval. Реальных вызовов/загрузок/выкладки/commit/CI ещё нет.

Corrective Gate 3 v8 APPROVED: reviews/tests/ADDRESS-REGISTER-IMPORT-001-v8.md, source d31e86f03a3bb5f1384b4e55bdcb12ebdf195d5e63227bc6d0b5d55328950955. Root исправил boundary identities и независимый точный chunks-schema oracle. 6 GREEN + 4 INTENDED_RED; ранние schema/restore failures не подтверждают downstream assertions. F1/F2/R1 открыты до implementation/final review. Реализация6.3 передана отдельным sol/low agents import_application_capacity, import_store_capacity, import_parser_capacity.

Frozen capacity candidate10d68f7d: все10 mapped tests GREEN, включая2668/667 restore/replay и19994 boundary; architecture и adjacent checks GREEN. Root обнаружил регрессии новых streaming adapters: nonlocal source open и trailing-NUL evidence accepted/cache write. Дополнительный test-only Gate3 пакет input-boundaries-verification-input.json проверяет этот единый delta; полный исходный verification-input.json остаётся обязательным для final review/CI. До verdict production frozen.

Supplementary Gate3 v9 вернул два oracle findings; v10 APPROVED после точного evidence rejection и полного canonical unknown-report comparison. Отдельный application executor исправил local-file guard и NUL/EOF handling; reports/CLI/economy GREEN. Production implementation6.3 завершена; root возвращает binding на полный verification-input.json для current-source verification/final review/CI. Реальные платные запросы и dev/prod import остаются вне разрешения.

Identity correction: actual-source aggregate выявил293 shared locations/604objects без повторных регномеров. Новый business regression отделён от capacity fixture. Gate3v11 CHANGES_REQUESTED (repeat calls), v12 APPROVED после пересборки полной flow matrix. 3.5/6.3 временно переоткрыты до separate-executor correction; все прежние конфликтные проверки сохраняются. Final candidate78cbecca:9GREEN + capacityINTERRUPTED/superseded, не общийGREEN.

Identity correction после Gate3v12 реализована отдельным application executor и прошла expanded focused test. Actual-source zero-budget preview выявил case-sensitive catalogue lookup; Gate3v13 APPROVED для полной reference/raw/repeat/negative matrix, отдельный executor исправил ObjectDetailsReferenceCatalogue. Исходный XLSX прошёл локальный application preflight при256M:2668 selected/4666 excluded/errors0,667 estimatedrequests, blocked PAID_CALLS_NOT_ALLOWED, provider0/applyfalse/businessunchanged. Это не ФИАС и не target-specific dev/prod acceptance. 3.5/6.3 завершены; полная current-source матрица/final review/CI остаются отдельными gates.

Gate3v14 APPROVED for prefix/frontier correction:9GREEN+7INTENDED_RED, all
RED converge on generated FK65bytes at canonical prefix25. Root authored exact
v40/frontier113 oracles and two-prefix/partial-recovery regression; separate store
executor owns the short FK identifier correction. Historical frontier assertions
and prefix-dependent original-maintenance mappings remain unchanged. Final full
importer matrix and adjacent frontier checks must pass after this correction.

Final local verification20.11.2026: full10/10 GREEN on source
e8463fe0564630d8d0aa15206764903ebaeead0962f3a8078ce7d0639d3d0859;
capacity/restore1368.364s under256MiB/16MiB, adjacent6/6GREEN. Independent
final code/architecture reviewv2 APPROVED closesF1/F2/R1 and corrective deltas.
Only factual delivery metadata and the review were added after evidence; code/tests
remain byte-identical. Tasks4.4/6.4 remain pending exact-source GitHubCI at commit.
4.2 and live parts of5.2 remain unperformed: no paid authorization, provider0,
no dev/prod import, FIAS UNKNOWN. Commit/PR/CI allowed; merge/deploy prohibited.

First full CI run44626585565 on commit06e73000: both integrations (including
capacity), fast/unit/governance GREEN; sole e2e regression is missing ext-zip
in standalone deploy/yii2 image. Full failure inventory and aggregate disposition
recorded before correction. Gate3v15 APPROVED; separate executor owns the
Dockerfile-only correction, followed by bounded e2e/final delta/next-source CI.

Yii dependency correction: existing real package e2e GREEN; independent final
delta reviewv3 APPROVED, production scope only deploy/yii2/Dockerfile. Corrective
commit and new full exact-sourceCI follow; first run failure is retained, not
relabelledGREEN. Importer code/tests and all prior acceptance proofs unchanged.

## 7. CI-duration and refresh corrective slice

- [x] 7.1 Root collect complete CI44632946488 failure inventory, isolate lookup
  scaling with a short public probe, and write bounded-work/freshness/refresh regressions.
- [x] 7.2 Independent Gate3 approve the complete correction before production edits.
- [x] 7.3 Separate store/application executors implement requested-only fresh lookup
  snapshots and explicit fresh-cache refresh, preserving apply/live semantics.
- [ ] 7.4 Run focused full importer/capacity and adjacent checks, obtain final
  independent approval, commit/push and complete exact-source full GitHubCI.

Second CI: all categories except integration2 completed GREEN; integration2 hit
45minute job cap after capacityGREEN1711.971s, leaving later integration tail
incomplete. No REGRESSION_FAILURE. verify and synthetic Quality Graph summary
failed downstream. Complete inventory inspected; no blind same-source retry.

Gate3v16 requested same-clock cache proof. v17 candidate adds exact immutable
entry_version metadata (no AI/frontier change), max-old-UUID same-clock payload
checks, signed transfer ordering and late atomic import rollback. All production
remains unchanged pending independent approval.

Gate3v17 APPROVED with no findings. Store/application sol/low executors now own
the reviewed implementation files; root owns all spec/test changes. Full frozen
verification and independent final approval remain pending for this correction.

Gate17 implementation focused closure: identity/concurrency/schema GREEN; economy
required owned-inflight staleness guard correction, then GREEN. Additional bounded
public probe exposed exhausted explicit retry budget erasing durable unknown; root
adds paired uncached/fresh-cache regression before that correction. No paid calls.

Gate3v18 requested direct durable-claim proof; v19 candidate preserves full claim
bytes/version count through denials and completes the identical entry/version on
valid retry, for both uncached and fresh-cache unknown outcomes.

Gate3v19 APPROVED; separate application executor now returns transient exhausted
retry rejection without deleting the unknown claim or replacing its durable report.
Full paired economy test and tight public unknown-budget probe GREEN. Candidate
now freezes for original10/capacity/adjacent checks and independent final review.

Final corrective source7f1a9de9: original10/10 and adjacent6/6GREEN, full
capacity/restore246.147s under unchanged256MiB/16MiB bounds. Independent final
code/architecture reviewv4 APPROVED without findings, including lookup, causal
cache versions, unknown-budget retention and SQL ownership correction. Only
factual metadata added after evidence; code/tests remain byte-identical. Tasks
4.4/6.4/7.4 await correction commit and full exact-sourceCI. Paid/live acceptance
remains unperformed; no merge/main push/deployment/dev-prod import authorized.
