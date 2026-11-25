# Адресный CLI21: готовый кандидат для нового CI —26.11.2026

Gate3v22 и independent final code/architecture reviewv5 APPROVED без findings.
Exact-source importer10/10 GREEN,11 adjacent/architecture GREEN с проверенной
lineage неизменных production/adjacent bytes. Full2668/667 и19994 boundary,
public restore v42/118 tables/64 AI прошли при256MiB/16MiB;232.341s.
Main f935c748 включён без переписывания истории; root spec/tests, separate
sol/low production executor. Следующий шаг — corporate-identity merge commit
в task branch, push PR307 и новый exact-source full GitHubCI.
CI этого кандидата пока не GREEN. После запуска/завершения точный результат
читать через harness state и PR307; live CI не выводится из этой записи.
Реальные imports/paid calls/PR merge/main push/deployment запрещены.
История ниже сохранена.

---

# Текущая коррекция —21 поле адресного CLI,26.11.2026

Owner поручил продолжить worktree address-register-import / PR307 до merge-ready:
исключить I–O/AD–AG из новых реквизитов/field reports, оставить B–H/P–AC и Z.
Полный XLSX/raw, старые данные/история сохраняются;2668 объектов2027 без дат,
даты позже повторным импортом без дублей. Root spec/tests, separate sol/low
production executor, planner-required independent reviews и новый exact CI.
Baseline dfb6927f/reviewv4/CI44650539950 не проверяют эту дельту.
Gate3v22 APPROVED; separate executor реализует код. Main f935c748 требует
сохранить migrations40/41 и добавить address42 (118 tables/64 AUTO_INCREMENT);
root объединяет tests/docs/policy, финальные checks/review/CI ещё впереди.
Реальные импорты, paid calls, merge/main push/deployment запрещены.
Evidence: ~/.local/share/fmonitor-2/address-register-import/fields21/.
История ниже сохранена.

---

# Адресный импортёр: финальная коррекция одобрена — 20.11.2026

Frozen source7f1a9de9: original10/10 и adjacent6/6 GREEN; полный capacity/restore
246.147s при256MiB/16MiB против прежних1368.364s. Gate3v17/v19 и независимый
final code/architecture reviewv4 APPROVED без findings. SQL остаётся в Store;
lookup, causal cache versions и durable unknown recovery проверены. Следующий
шаг — correction commit/push в PR307 и один полный exact-source GitHubCI.
До его завершения общийCI не GREEN. Paid clean/address0, FIAS UNKNOWN;
merge/main push/deploy/dev-prod imports запрещены. История ниже сохранена.

---

# Заключительная проверка после SQL ownership fix — 20.11.2026

Candidatec64eafef прошёл10/10 с capacity/restore280.942s (прежде1368.364s),
но architecture выявил SQL в helper. Separate executor перенёс тот же SQL/keyset
в MariaDB Store; helper теперь pure spool/index, baseline не менялся. Новый
source заморожен для architecture/adjacent, full10, independent final и fullCI.
Gate3v17/v19 APPROVED; paid/live/merge/deploy запреты сохраняются. История ниже.

---

# Финальная проверка lookup/cache/recovery correction — 20.11.2026

Gate3v17 иv19 APPROVED. Separate store/application executors завершили bounded
identity lookup, causal cache entry_version и сохранение unknown claim при отказе
retry budget. Focused schema/identity/concurrency/economy GREEN; последний budget
probe не повторяет provider без retryUnknown. Candidate заморожен для original10
с capacity/restore, architecture/adjacent, independent final review и нового CI.
PR307/head99232d9c — опубликованная предыдущая версия; новый WIP ещё не опубликован.
Реальных clean/address0; paid/live/merge/deploy запреты остаются. История ниже.

---

# Unknown outcome после отказа бюджета — 20.11.2026

Gate3v17 реализован отдельными executors; lookup/concurrency/schema GREEN, economy
GREEN после owned-inflight stale-guard correction. Cost probe устранён:0 fullscan
reads вместо107040 для20/2674. Root подтвердил дополнительный budget edge:
исчерпанный retry стирает unknown, следующий resume без retryUnknown может принять
старый cache или повторить запрос. Парный regression uncached/fresh-cache и
independent Gate3 до correction; затем full10/capacity/architecture/final/CI.
PR307/head99232d9c остаётся последним опубликованным; новый WIP не опубликован.
Paid/live/merge/deploy запрещены; история ниже сохранена.

---

# Коррекция стоимости lookup и refresh — 20.11.2026

PR307/head99232d9c: второй CI44632946488 отменил integration2 по45минутному
лимиту после capacityGREEN1711.971s; хвост integration не завершён. Остальные
категории GREEN. Полный inventory разобран: regression failures0, aggregate
verify/Quality Graph downstream failures. Root воспроизвёл O(source*catalog)
identity scans коротким public preview probe и пишет cost/freshness regressions.
Также подтверждён no-op explicit refresh при свежем кэше. Gate3v16 запросил
same-clock proof; candidatev17 добавляет immutable entry_version в cache v40
без изменения frontier113/60. Gate3v17 APPROVED; отдельные store/application
executors реализуют согласованный delta, затем focused/final/новый exact CI.
Лимиты/coverage не повышать/не сокращать. Paid/live/merge/deploy запреты сохраняются.

---

# CI-коррекция Yii image — 20.11.2026

PR307 / commit06e73000: полный CI44626585565 завершён. Обе integration shards,
fast/unit/governance GREEN; единственный regression — отсутствующий ext-zip
в standalone deploy/yii2/Dockerfile, verify упал вследствие e2e. Полный inventory
разобран. Gate3v15 APPROVED, отдельный executor исправил только зависимости
этого image; bounded e2e GREEN, независимый final deltav3 APPROVED. Следующий
шаг — correction commit/push и новый exact-source CI.
Importer/application/schema bytes не меняются. Ограничения paid/live/merge/deploy
сохраняются. История ниже сохранена.

---

# Адресный CLI-импортёр: final review пройден — 20.11.2026

Выделенный worktree address-register-import: corrective Gate3v8/v10/v12/v13/v14
и независимый final code/architecture reviewv2 APPROVED. Frozen sourcee8463fe:
10/10 mappedGREEN,6/6 adjacentGREEN, полный2668/667 capacity/restore/replay и
19994-object boundary при256MiB/16MiB. Prefix25/frontier113/60 проверены.
Следующий шаг — corporate-identity commit/PR и полный exact-source GitHubCI;
CI на момент этой записи pending. Source/PR/CI читать через harness/PR.
Реальных clean/address0, разрешения на расходы нет, FIAS UNKNOWN. Merge/main
push/deploy/dev-prod imports не выполнять. История ниже сохранена.

---

# Текущий этап адресного импорта — 19.11.2026

В выделенном address-register-import worktree продолжается поручение владельца
довести CLI до проверенного состояния. Streaming/chunks correction прошла Gate3v8
и полный synthetic capacity/restore baseline; input-boundary delta — Gate3v10.
Бесплатный аудит исходного файла выявил293 общие пары UNOM+подъезд с разными
регномерами. Это604 самостоятельных лифта, а не дубли полного identity.
Gate3v12 подтвердил отдельный identity regression без отмены конфликтных guards;
отдельный executor исправил лишнюю location uniqueness. Gate3v13 и отдельная
реализация согласовали parser/catalogue normalization. Реальный исходник прошёл
локальный zero-budget preview до PAID_CALLS_NOT_ALLOWED без provider/apply.
Gate3v14 подтвердил коррекцию canonical prefix25 и точные v40/frontier113
oracles. Текущий шаг: separate schema fix,16 adjacent checks, затем основной
полный verification-input, frozen checks, независимый final review и CI.
История и actual authors — в [delivery record](address-register-import-delivery-20261118.md).
Source/PR/CI читать через harness state. Реальных clean/address запросов0;
разрешения на расходы нет. Merge/main push/deploy/dev-prod imports не выполнять.

---

# Коррекция текущей ветки — ёмкость адресного импорта, 19.11.2026

В `fmonitor-2-address-register-import` final review v1 вернул CHANGES_REQUESTED:
COMMIT_OUTCOME_UNKNOWN должен иметь CLI exit75; пакет2668/667 падает при256 MiB
и затем превышает MariaDB packet16 MiB. Малые10 tests/architecture GREEN не
означают готовность загрузки. Root уточнил contract/tests: ограниченные streams,
неизменяемые payload chunks (седьмое семейство v40, frontier113/60), полный report,
реальный размер/верхняя граница/restore и точный commit-loss CLI regression.
Повышение лимитов и усечение отчётов не принимаются. Дополнительно failed apply
должен показывать ноль опубликованных изменений. Исправление реализации — после
независимого corrective Gate3, отдельными sol/low executors; следующий final/CI
обязателен. Реальные ДаДата/dev/prod import/deploy по-прежнему запрещены до
соответствующих отдельных разрешений. История предыдущего этапа ниже сохранена.

---

# Текущая задача этой ветки — повторяемый импорт адресного перечня, 18.11.2026

Владелец поручил реализовать согласованный CLI-инструмент импорта XLSX в отдельном
worktree `fmonitor-2-address-register-import`, branch `codex/address-register-import`,
base `08ca8b18`. Контракт `ADDRESS-REGISTER-IMPORT-001`, lifecycle
`openspec/changes/address-register-import/`. Root — спецификация и тесты;
отдельные gpt-5.6-sol/low executors — реализация, независимые Gate 3 и final review.
Gate 3 v3 APPROVED; дополнительные regression assertions после аудита реализации
требуют независимого delta review. Реализация и focused проверки продолжаются.

ДаДата clean/address выбрана для инструмента. Реальные запросы запрещены до
отдельного подтверждения владельцем разрешения Ильи; пока только fake provider.
Dev/prod import, merge/main push и deployment не выполнять. Локально только
bounded checks на task-owned disposable DB; полный exact-source CI — GitHub.
Исторические разрешения других поставок ниже на эту ветку не переносятся.
Текущие source/PR/CI читать через harness state; private evidence вне Git.

---

# Текущая задача — GitLab Runner для обновления production, 18.11.2026

---

# Текущая цель — замечания Микрюковой, 24.11.2026

Владелец после подробного интервью подтвердил «Делай»: единый пакет по specs/MIKRYUKOVA-FEEDBACK-001.md, change mikryukova-feedback. Исправить справочник, считать премию по остановкам и нормативу договорного интервала от фактического начала, сделать решения/удержания понятными, добавить приостановку объекта и частичные выплаты. Импортёр Excel явно вне scope: находится в другой ветке.

Разрешена поставка только на домашний dev fmonitor.antropophag.ru после проверок и backup. Учебные объекты: разово stops=floors; все учебные расчёты и связанные суммы можно обновить согласованно без разработки исторической совместимости. Корпоративный fm.shlz.ru не изменять, письма не отправлять. Существующие несведённые server changes сохранять.

Root — specs/tests; отдельные gpt-5.6-sol/low исполнители — runtime; независимые Gate3/final. Только focused local, полный exact-source CI в GitHub; full local make test/verify запрещён. PR/CI/source через harness state. Пользовательское разрешение на demo rewrite не отменяет обычные проверки.

Предыдущая очередь сохранена в openspec/changes/mikryukova-feedback/previous-delivery-goal.md; не возобновлять её в этом change.

Owner уточнение: исходные плановые даты являются договорными. Для учебного объекта246/147354 явно разрешено поменять ошибочную пару 18.10–01.09.2026 на 01.09–18.10.2026, только home dev и в общем preview/backfill.

Owner continuation25.11.2026: автономно завершить проверки и штатный GitHub merge PR310, затем обновить home dev, затем создать в почте ЧЕРНОВИК ответа Микрюковой со скриншотами и простым описанием исправлений без технических деталей. Письмо НЕ ОТПРАВЛЯТЬ. Разрешение на merge не отменяет exact-source CI, независимый review и branch protections.
