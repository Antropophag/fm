# Поставка повторяемого импорта адресного перечня

Работа выполняется в `codex/address-register-import`, выделенный worktree
`fmonitor-2-address-register-import`, base `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`.
Контракт: [ADDRESS-REGISTER-IMPORT-001](../../specs/ADDRESS-REGISTER-IMPORT-001.md).
[Инструкция оператора](address-register-import.md),
[lifecycle](../../openspec/changes/address-register-import/tasks.md),
[граница владельца](../adr/0005-address-register-import-owner.md).

Инструмент реализован; интеграционная проверка, независимый final review и CI
на момент этой записи продолжаются. Реальный импорт на dev/prod и обращения
в ДаДату не выполнялись. Использование платного аккаунта ожидает отдельного
подтверждения владельцем разрешения Ильи. Merge/main push и deployment не входят
в эту поставку инструмента.

Исходный private XLSX проверен локально: 2668 строк на 2027 год, 4666 строк других
лет исключены, ошибок поддерживаемого формата нет; повторная проверка после
исправлений парсера прошла при `memory_limit=128M`. Эти числа не подтверждают
качество адресов ФИАС: live-проверок адресов было ноль. Исходный файл, строки,
доступы и primary evidence остаются вне Git.

Автор спецификации и тестов — `/root`. Реализация — отдельные
`gpt-5.6-sol / low`: `/root/import_executor` (schema/runtime/CLI/composition),
`/root/import_adapters_executor` (parser/provider initial, application/dependencies),
`/root/shared_details_executor` (shared mutation, adapter corrections, persistence).
`/root/import_test_delta_review` не участвовал в написании кода или тестов.
Root выполнил интеграционный аудит; авторство реализации не подменялось.

Gate 3: v1 и v2 вернули соответственно 7 и 4 findings; v3 APPROVED.
[v1](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v1.md),
[v2](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v2.md),
[v3](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v3.md),
[v4 test delta](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v4.md),
[v5 final test delta](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v5.md).
Оба test-delta review — APPROVED без findings; final code/architecture review
остается отдельным обязательным этапом.
Усиленные regressions проверяются на отдельном восстановленном исходном
baseline: это честно отмеченное ретроспективное RED, а не история фактического
порядка написания кода. Exact snapshots, hashes и команды находятся в review records.

Повторные focused проверки вызваны исправлениями конкретных границ:
cache/budget/unknown outcome, identity/locking, canonical address, filesystem,
Yii routing и schema readiness. Первый disposable restore не дошёл до assertions:
после добавления ext-zip устарели hashes dependency manifest; manifest исправлен.
Первый architecture check выявил CLI SQL, прямое создание persistence adapter
и новый public seam; SQL/composition исправлены, один seam зарегистрирован
с ADR для независимого review. Полный локальный `make test`/`make verify`
не запускался согласно решению владельца.

Начало этапа исполнения по executor package: `2026-11-18T19:09:24Z`.
Длительность всей задачи, tokens и денежная стоимость агентской работы
не выводятся из предположений. Расходы ДаДаты в этой реализации — ноль
реальных вызовов; проверки используют подставной provider.

Primary evidence: `~/.local/share/fmonitor-2/address-register-import/` и
`~/.local/share/fmonitor-2/delivery-harness/`. Используется только task-owned
disposable Compose project `fm2-disposable-address-import`; рабочие контуры
и соседние проекты Docker не изменяются.

Коррекция19.11: [final review v1](../../reviews/code/ADDRESS-REGISTER-IMPORT-001-v1.md) — CHANGES_REQUESTED. Все10 mapped tests на source76939643 и architecture прошли, но последующий bounded actual-size synthetic probe выявил OOM256 MiB и DB packet16 MiB; также CLI неверно классифицирует unknown commit. Эти findings остаются открытыми; root обновил streaming/chunk contract и regressions перед corrective Gate3. [Test-only v6](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v6.md) одобрил fixture mount, не код. Повторные проверки вызываются этими конкретными исправлениями, не отсутствием live-доступа.


## Продолжение 19 ноября — проверка ёмкости и входных границ

Root сохранил весь WIP и исправил два finding corrective Gate3 v7:
валидные boundary identities и независимый точный schema oracle для chunks.
[Gate3 v8](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v8.md) — APPROVED.
Реализация коррекции: отдельные sol/low `import_application_capacity`,
`import_store_capacity`, `import_parser_capacity`; root production-код не писал.
Независимый reviewer `import_gate3_v8_review` не автор кода/спецификации/тестов.

На замороженном source `10d68f7de74b75ef9069b162f2136395ac51d6d5e10ad704ec5d3192d50dd6e8`
все10 mapped tests GREEN. Большой runtime regression занял1394.18s:
2668 объектов/667 fake адресов,85376 field rows, apply/export/реальный backup/restore/
replay, затем19994 объекта/639808 field rows при256 MiB и packet16 MiB.
Architecture8 rules, HTTP qualification, shared editor, card/queue, schema frontier
и dependency-render checks GREEN. Это evidence данного baseline, не перенос GREEN
на последующий source. Полные записи: private `capacity-frozen-records.json` и
`capacity-frozen-adjacent-records.json` в evidence home.

Root выявил два регресса новых streaming adapters: nonlocal source open и
принятие trailing NUL как EOF в signed evidence. Групповой test delta прошёл
[v9 CHANGES_REQUESTED](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v9.md)
(точность error oracle и полнота unknown payload) и
[v10 APPROVED](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v10.md).
Отдельный application executor исправил обе границы; новые focused assertions GREEN.
Дополнительный input-boundaries verification package не отменяет полную матрицу
основного `verification-input.json` для final review и CI.

Повторный полный capacity regression нужен из-за изменённого source после этих
конкретных findings. Старый pre-keyset capacity run отменён как superseded/UNKNOWN;
его контейнер и image удалены адресно, общий disposable DB сохранён.
Streaming parser отдельно проверил исходный XLSX:2668/4666/0 errors при256M;
это по-прежнему не ФИАС-проверка. Все live clean/address calls —0.

Текущие source/PR/CI читать через harness state; формальные verdict — в review
records. Данная запись фиксирует авторство и причины повторных проверок и не
подменяет exact-source evidence. Merge/main push/deploy/live imports запрещены
текущим поручением; полный локальный make test/make verify не запускался.


Бесплатный aggregate audit исходного XLSX выявил293 группы с общими UNOM и
подъездом (604 объекта); в каждой группе исходный адрес одинаков, регномера
разные. Повторов регномера или полного identity нет. Эти агрегаты не содержат
строк, адресов или ФИАС-проверок; private evidence — source-identity-aggregate-
20261119.json и source-location-aggregate-20261119.json. Нормативный OpenSpec
не задаёт уникальность UNOM+подъезд; прежний prevalidate вводил лишний запрет.
Capacity fixture с различными UNOM сохранён: его не ослабляли ради GREEN.

Отдельный identity delta прошёл [v11 CHANGES_REQUESTED](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v11.md)
(неполный repeat-call oracle), затем [v12 APPROVED](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v12.md)
после проверки всей цепочки create/apply/replay/overwrite/repeat и calls/attempts/
IDs/history. Прежние отрицательные identity checks сохранены; точный duplicate
получает уже предусмотренный OpenSpec код DUPLICATE_SOURCE_OBJECT.
Root test/spec; application executor исправляет production. Final повтор на
source78cbecca дал9 GREEN и был адресно прерван как superseded на capacity,
поскольку найден новый identity defect. INTERRUPTED не считается GREEN.
Контейнер/image этого запуска удалены, остальные Docker-ресурсы не тронуты.


[Gate3 v13](../../reviews/tests/ADDRESS-REGISTER-IMPORT-001-v13.md) — APPROVED
для parser/catalogue reference-normalization delta. Локальный actual-source
zero-budget preview ранее обнаружил точное сопоставление `глухая`/`Глухая`;
отдельный executor согласовал уже разрешённую нормализацию без расширения словаря.
Полный synthetic matrix покрывает все4 типа шахты/6 материалов, literal codes/
display/raw, повтор без событий/вызовов/попыток и неизвестные значения до provider.
Общий ручной редактор также прошёл focused check.

Новый приватный `source-offline-preflight-green.log`: при256M исходный XLSX
дошёл через application до ожидаемого PAID_CALLS_NOT_ALLOWED при budget0:
2668 selected,4666 excluded,errors0,667 estimated unique requests,providerCalls0,
applyPerformed=false,businessUnchanged=true. Это task-owned local disposable DB,
не dev/prod. Реальные адреса не отправлялись provider; целевой live preview и
ФИАС-качество остаются UNKNOWN до отдельного разрешения владельца.


При проверке всех соседних frontier oracles найден один старый literal112/60
в deadline_transfer_certificate_schema_001_test.php. Root исправил его на уже
утверждённые113/60; production не менялась. Test-only preload recovery inventory
из точного reviewed v1 snapshot (ещё6 import families) воспроизводит RED
expected113/actual112, current schema проходит обычный путь. Этот однострочный
oracle delta предъявляется независимому test review вместе с final candidate.
Повтор capacity на source0d180aac адресно остановлен как superseded/INTERRUPTED
до изменения oracle; контейнер/image удалены. Перед новым длительным запуском
проверяется полный набор быстрых изменённых schema consumers.

## Prefix/frontier correction — 20.11.2026

Root audited the changed schema consumers before another capacity run. Twelve
bounded checks exposed stale current-frontier oracles and the same production
defect at the established25-byte prefix: MariaDB's generated chunks FK identifier
is65 bytes (error1059); the canonical command returns70 after creating six tables.
Root corrected literal v40/seven-family expectations, preserved historical-version
assertions and ATTEMPT-AUDIT prefix-dependent physical names, and added independent
two-prefix/no-DDL/partial-recovery coverage. Supplementary frontier-verification-input
maps16 bounded checks; inspection_evidence reaches this same prefix defect after
its old version assertion is corrected. Full mapped importer verification and
independent final approval remain pending. Production fix waits for Gate3v14;
actual authors remain root tests/spec and separate store executor production.

Gate3v14 APPROVED; separate store executor implemented only deterministic short
chunks FK naming. Full16 post-fix frontier checks reached15GREEN; the remaining
certificate test reached a previously hidden physical table-list oracle missing
exactly the seven importer families (no extra/missing non-import tables). Root
added these literal expectations, preserving exact equality; independent final
review includes this test-only delta. No production change was needed.

## Final local candidate and independent approval — 20.11.2026

- Reviewed source: `e8463fe0564630d8d0aa15206764903ebaeead0962f3a8078ce7d0639d3d0859`.
- Executable source: `9927100193ffa1a895c0f30132445a742b575b34a68fee6f68d492613f1aebad`.
- Gate5 package: private delivery-harness `20261119T215010Z-fea646471c`.
- Original mapped10/10 GREEN; capacity/backup/restore/replay and upper worksheet
  boundary completed in1368.364s at shipped PHP256MiB/MariaDB packet16MiB.
- Architecture, shared manual editor, current schema frontier, card, queue and
  dependency generation:6/6 GREEN. Prefix/frontier15GREEN plus exact corrected
  certificate inventoryGREEN. No local full make test/verify was run.
- Independent review `reviews/code/ADDRESS-REGISTER-IMPORT-001-v2.md`: APPROVED,
  findingsnone; originalF1/F2 and rootR1 resolved. Root authored specs/tests;
  application/store/parser executors authored production; reviewer authored only
  independent reviews. No authorship exception used.
- Post-evidence changes are only this record, current-goal, OpenSpec task status
  and final review. All other candidate file digests preserved before commit.
- Author/committer verified corporate Timofey Grishin <ts.grishin@shlz.ru>;
  corporate GitLab remote retained. Commit/PR/full exact-source GitHubCI next.
  CI is pending here; its authoritative result belongs to the published commit/PR.
- Real clean/address calls0; actual-source offline preflight only. Paid permission
  absent, FIAS UNKNOWN, live dev/prod acceptance unperformed. No merge/main push,
  deployment or real import authorized/performed.
