# Текущая цель — clone, .env, make deploy

Owner16.11.2026 потребовал простой первый запуск: git clone из корпоративного GitLab, копирование готового `.env` в корень, одна команда `make deploy`. WSL и Make уже есть. GitHub остаётся местом разработки/PR/CI, после проверок в GitLab переносится только main. Это bounded correction существующего production Make workflow: приватные managed directories и безопасное сужение прав собственного env; domain/schema/legacy/Excel-import не меняются. Root пишет контракт/tests, отдельный sol/low executor код, независимый reviewer Gate3/final по planner. Full local suite запрещён. Новые private trial/production handoffs и Downloads обновляются после source delivery; существующие production/данные/соседние worktrees не меняются. Для публикации используем GitHub PR/CI и owner-directed tested-main handoff; автоматический admission UNKNOWN не переопределяется.

Worktree `fmonitor-2-simple-clone-env-make-deploy`, base main1e4b6a61, контракт `PRODUCTION-MAKE-QUICKSTART-001`. Предыдущая история ниже сохранена.

---

# Текущая цель — production Make и единый environment

Поручение владельца16.11.2026 в текущей беседе: реализовать согласованный пакет чистого production-развёртывания через Make; все ключи, токены и первоначальная учётка находятся в одном приватном `.env`. При недоступности Composer repositories автоматически скачивать закреплённые пакеты напрямую с проверкой digest. Существующие базы не переносить. Это разрешение на реализацию, isolated acceptance, PR/push; фактическая выкладка на корпоративный сервер, отправка почты, изменения живого production и merge не входят.

Worktree `fmonitor-2-production-make-env-deploy`, change `production-make-env-deploy`, контракт `PRODUCTION-MAKE-ENV-001`. Исходная база — main7c6b8e14 после PR294; в эту ветку также включён main6cf725fc после завершения параллельного PR295; соседние worktrees и параллельная сессия сохраняются. Root — спецификация/tests; отдельные gpt-5.6-sol/low executors — deployment и dependency installer; независимый reviewer — Gate3/final. План выбирает lane/reviews. Проверки локально bounded, полный matrix только exact-source GitHub CI. Секреты и полные журналы вне Git. UNKNOWN не означает GREEN.

Предыдущая очередь сохранена ниже; её production-first/merge authorization не переносится на эту задачу.

---

# Текущая цель — адресный перечень через PR/CI до main

Owner16.11.2026 явно поручил проверить/дописать тесты, провести изменения черезPR и полныйCI и влить вmain. Работа в отдельном worktree address-register-integration на main7c6b8e14, содержащем mergedPR294. Объединены все original/repaired/adaptive address-register commits с сохранением авторства и историй. Production менять не требуется. Root specs/tests/integration; independent retrospective test/final review; требуемыегейты и полныйexact-sourceCI теперь обязательны, прежние owner-deferred записи ниже остаются историей. Full local make test/verify запрещён: только focused checks. Разрешеныpush/PR/merge после review/CI; чужиеworktrees/WIP не менять.

PR295 открыт, first full CI43819674368 разобран полностью: только2 regression failures (sidebar old11-link oracle; focused-bootstrap rationale oracle missing consumer frontier), verify aggregate failed accordingly. Root bounded test-only corrections preserve runtime/policy; independent correction reviews and next exact-source fullCI mandatory before merge. Active repair input: openspec/changes/address-register-personal-columns/ci-repair-verification-input.json, base817b49f8.

---

# Текущая цель — все колонки, регномер первым, адаптивная ширина

Owner15.11.2026 просит первым регномер, показать все колонки, убрать настройку пикселей, сделать адаптив и проверить ячейку нескольких монтажников. Desktop приоритет; телефон не требует отдельной полировки. Dedicated worktree address-register-adaptive-columns на aeaca219, включает сохранённое восстановление послеPR294. Root scope/spec/tests; отдельный sol/low implementation; independent final review. Production-first authorization сохраняется, full local suite запрещён, полныйGitCI позже. Legacy preferences history сохраняется, новая v2 presentation не переписывает файлы, domain data не меняется.

Поставлено15.11 22:46МСК:regnumberfirst/all22/autowidth/full multiline crews, currentimage `fmonitor2-runtime:address-register-adaptive-20261115-092f3fa944e9`. [Delivery](address-register-adaptive-20261115.md). Scopedchecks/finalreview PASS,1043otherfiles retained from latest parallel image; fullCI deferred. After v2 saves, do not code-only rollback to v1reader; use forward-compatible recovery and preserve all history.

---

# Текущая цель — восстановить адресный перечень после параллельной выкладки

Owner15.11.2026 сообщил404 и попросил проверить параллельныйPR. Подтверждено: production shared web/assets/asset-controller bytes точно равныPR2944cdd3d5 и потеряли регистрации address-register; остальные16runtime files сохранены. Новый dedicated worktree restore-address-register-after-perf объединяет publishedPR294 + два reviewed address commits локально, не меняет чужой worktree/ветку/PR. Root bounded correction author; independent final review до точечного восстановления3shared files. Старые разрешения production-first сохраняются; данные/settings не менять. Full local suite запрещён.

Восстановлено15.11 22:16МСК:3shared registrations returned, immutable derived PHP image persisted,1047other files preserved, focused HTTP/browser and independent review PASS. [Recovery record](address-register-recovery-20261115.md). PublishedPR294 still requires preservation of these additive routes/assets before future rollout; its worktree/WIP is untouched.

---

# Текущая цель — исправить весь production performance audit

Поручение владельца 15.11.2026: «Исправь все прямо на проде, потом прогони правки по гейтам через наш CI до мерджа в мейн». Это явное разрешение на scoped production deployment, последующее прохождение гейтов, PR/push/merge после exact-source CI. Scope — все F01–F12 аудита performance-audit-20261114, без очистки данных/истории или изменения финансовой допустимости.

Worktree: `fmonitor-2-fix-prod-performance-20261115`; change `fix-prod-performance`; контракт `PRODUCTION-PERFORMANCE-001`. Root — scope/spec/tests/интеграция и выкладка; отдельные gpt-5.6-sol/low executors — backend, checklist, UI; независимый reviewer не является автором. До каждой выкладки — focused checks, полный список файлов, CAS по production hashes, private backup/rollback и независимое ревью. Обычная очередность формальных gates owner-deferred до production delivery; ретроспективный Gate3 честно обозначается, не выдаётся за preimplementation approval. После поставки — обязательные reviews и один полный exact-source GitHub CI; при failure сначала полный inventory, затем corrections. Full local make test/verify запрещён.

Primary logs/captures вне Git: `~/.local/share/fmonitor-2/fix-prod-performance-20261115/`. Navigation checkout read-only. Соседние worktrees/WIP и два ранее отличавшихся runtime-файла dashboard сохранить; не включать их в поставку без нового фактического основания. Точное source/PR/CI — harness state; UNKNOWN не означает GREEN.

Предыдущая очередь сохранена ниже как история.

---

# Текущая цель — PR292 и PR293 до merge в main

Поручение владельца13 ноября2026: оформить последние production-first правки и довести обе ветки до merge. Это явное разрешение на PR/push/merge после независимого ревью и полного exact-source CI; новый production deployment/reset данных не входит. Работа ведётся в отдельных worktrees persistent-month-session и actual-start-from-assignment-order. Navigation checkout read-only.

PR292 — месячная сессия, PR293 — начало по приложению распоряжения. Первые CI полностью разобраны: PR292 один устаревший SameSite Strict тест; PR29360 regressions преимущественно через общий fixture без appendixStartDate, старый ручной open, точные schema/asset inventories. Root адаптирует tests, sol/low executor исправил совместимость composite UUID1–5/PDF reasons, independent review сохраняется. Полный локальный make test/verify запрещён; используются focused checks и существующий GitHub full consumer. Исторический production-first/deferred статус ниже не означает актуальный CI GREEN.

---

# Текущая цель — фактическое начало по подписанному распоряжению

Поручение владельца 13 ноября 2026: фактическое начало берётся из графы «Дата начала работ» приложения к распоряжению; принятый подписанный оригинал автоматически открывает работы без отдельного ввода даты стройконтролем. Владелец планирует очистку и повторную загрузку объектов; backfill прежних дат не нужен, очистка ещё не выполняется.

Рабочий worktree: `fmonitor-2-actual-start-from-assignment-order`. [Подтверждённые решения и исследование](actual-start-from-assignment-order-20261113.md). Владелец подтвердил: **открыть сразу**, включая будущую дату из приложения. Все продуктовые развилки закрыты; реализуется `openspec/changes/actual-start-from-assignment-order`, контракт `specs/ACTUAL-START-FROM-ORDER-001.md`. Реализация и независимое final review завершены; scoped production deployment выполнен13 ноября. Новая schema39 добавлена без изменения105 прежних таблиц,22 source files подтверждены по hashes; внешний ready/login200 и все5 healthchecked сервисов healthy. База не очищалась. В Git фиксируется delivery checkpoint; formal Gate3/fullCI/automated admission остаются owner-deferred/UNKNOWN.

Действует заданный владельцем в текущей беседе режим production-first с отложенной обычной последовательностью гейтов; root пишет контракт/tests, отдельный sol/low executor реализует, независимый final review и focused checks сохраняются. Полный локальный `make test`/`make verify` запрещён. Старые worktrees, данные и история сохраняются.

Предыдущая очередь ниже — история, не scope текущего поручения.

---

# Текущая цель — месячная сессия и вход по внешним ссылкам

Owner13 ноября2026 запросил исправить потерю входа по external link и сделать session lifetime месяц (30суток). Worktree `fmonitor-2-persistent-month-session`, контракт `specs/MONTH-SESSION-001.md`, [delivery record](month-session-20261113.md). Scope только session config и регрессии; production-first режим текущей беседы, focused checks и independent final review. Gate3/fullCI owner-deferred; full local make test/verify запрещён. Старые session files, keys, users и прежние production patches сохраняются. Поставка выполнена13.11.2026 16:49МСК: один config-файл, independent final review APPROVED, focused browser/session-failure GREEN, live cookie30дней/Lax/Secure/HttpOnly и health200. Новые параметры старой cookie применяются при следующем входе. Gate3/fullCI остаются отложенными.

Предыдущая цель ниже сохранена как история.

---

# Предыдущая цель — провести production-изменения через Git и CI

Поручение владельца 2026-11-10: собрать все фактически работающие production-изменения в Git, дополнить тесты, пройти независимые ревью и полный exact-source GitHub CI. Предыдущий режим экстренной поставки не освобождает этот интеграционный candidate от проверок.

Рабочая ветка/worktree: `codex/reconcile-production-20261110`, `fmonitor-2-reconcile-production-20261110`. Base: `60ed34ac178d1a85b3f21797981a5d8ba35da39c` (`origin/main`). Production читается только для сверки; изменение кода, миграции, restart/deploy и business-data writes на сервере в это поручение не входят. Локальные WIP и соседние worktrees сохраняются. Root задаёт контракт/тесты, независимые sol/low agents выполняют требуемые роли; импорт сохраняет фактическое прежнее авторство.

Контракт: [PRODUCTION-RECONCILIATION-20261110](../../specs/PRODUCTION-RECONCILIATION-20261110.md). Primary capture/logs находятся вне Git: `~/.local/share/fmonitor-2/reconcile-production-20261110/`. Полный локальный `make test`/`make verify` запрещён. Локально — bounded focused checks, полный matrix — существующий Quality Graph consumer. UNKNOWN не означает GREEN/допуск.

Сверка1000runtime-файлов закрыта;26focused проверок и независимые ретроспективное тестовое/итоговое ревью пройдены. Legacy Gate3 preimplementation packager для уже реализованного all-GREEN candidate не применяется автоматически; ограничение и исторические RED зафиксированы в delivery record. PR291 создан; первый полный run42778997280 завершился с2architecture и16test failures. Полная инвентаризация собрана. Активный correction input: openspec/changes/reconcile-production-20261110/ci-repair-verification-input.json, base ed1b6cdb3805920b95ee35787d28fb40862a7997. Исправления прошли отдельные Gate3/final и focused проверки; отправляется обновление PR291 для нового exact-source полного CI.

Предыдущая очередь сохранена ниже как история и не определяет scope нового поручения.

---

# Текущая цель — обратная связь ОТИЗ к 30.10.2026 10:00 МСК

Поручение владельца 29.10.2026: реализовать замечания ОТИЗ и поставить на production
короткими порциями «код → независимое ревью → прод». Обычная последовательность
гейтов отложена явным решением владельца; независимое ревью перед выкладкой и
адресные проверки сохраняются. Другие доработки идут в других сессиях.

Рабочий каталог: `fmonitor-2-otiz-production-20261029`. [Решения и ход поставки](otiz-feedback-2026-10-29.md).
Первая UI-порция проверена независимым reviewer и выложена 29.10 в19:06МСК.
Финансовая порция поставлена19:54МСК: сохранённые доли, последующее распределение, месячные итоги XLSX и отчётные фильтры. Production read verification пройдена; ожидать обратной связи владельца.
Primary evidence/rollback/logs вне Git: `~/.local/share/fmonitor-2/otiz-production-20261029/`.
Уточнение интерфейса ОТИЗ29.10 23:50МСК поставлено: фильтры в dropdown, размер страницы под таблицей, решения по выплате без GET-перехода. [Проверки и ограничения](otiz-filters-decisions-20261029.md).
30.10 00:04МСК исправлен предпросмотр решения по числовому ID монтажника и ограничен его объектами. [Поставка и live-проверка расчёта14](otiz-decision-preview-20261030.md).
30.10 08:09МСК поставлены комментарии ФКР ко всем объектам: отдельное поле карточки, колонка списка, история и защита одновременного редактирования. [Поставка и проверки](object-comments-20261030.md).
30.10 08:30МСК удалена колонка «Следующий шаг», разнесены даты плана в таблице объектов. [Поставка](object-table-spacing-20261030.md).
30.10 09:07МСК поставлены компактный комментарий под паспортом, справки под плановым окончанием, пагинация ОТИЗ слева, количество монтажников в экономике ОТИЗ. [Поставка](compact-card-and-otiz-20261030.md). В отдельной ветке поставлено корпоративное оформление XLSX. В работе ERP join59740.25 и решения по паре объект+монтажник.
30.10 15:16:09МСК решения ОТиЗ по паре объект+монтажник [проверены и поставлены](otiz-object-decisions-20261030.md) через bounded migration38 route; canonical full-run v14 receipt-history conflict остаётся отдельным долгом, fullCI GREEN не заявлен.
Перед каждым изменением production проверять исходные hashes для защиты параллельных сессий.
WIP №257 и остальные worktrees сохранить. Полные локальные make test/verify запрещены.

Предыдущая очередь ниже сохранена как история и не является активным поручением.

---

# Текущая цель — завершить PR #286

Поручение владельца 2026-10-01: сравнить текущий production с PR #286, включить отсутствующие изменения, разобрать все падения CI и довести PR до merge. Это поручение разрешает обновление ветки PR и merge после обязательных проверок и независимого review; production deployment, миграции и изменение данных не запрошены.

Рабочая ветка: `codex/reconcile-production-20260928`. Исходный candidate: `97884878631303d46cb4cf31ab83231517e266e9`. Полная сверка и логи хранятся вне Git в `/Users/antropophag/.local/share/fmonitor-2/pr286-20261001/`. Контракт и продолжение истории: [production reconciliation](production-reconciliation-2026-09-28.md).

Текущий WIP №257 и соседние worktrees сохраняются. Root задаёт scope/spec/tests; отдельный executor реализует новые исправления, независимый reviewer проверяет planner-required gates. Полный локальный `make test`/`make verify` запрещён. Используются bounded focused проверки и exact-source GitHub CI. UNKNOWN не означает approval.

Предыдущая цель ниже приостановлена этим поручением; её история и рабочий checkout сохраняются.

---

# Текущая цель — bounded local Docker growth

Поручение владельца 2026-09-26: после фактического заполнения диска Docker-артефактами реализовать change `bound-local-docker-growth` и довести candidate до PR-ready. Scope: только локальные focused/disposable delivery workflow, storage guard, project-owned image/cache retention, teardown доказанно ephemeral Compose resources, диагностика и проверки безопасности.

Контракт: [LOCAL-DOCKER-STORAGE-BUDGET-001](../../specs/LOCAL-DOCKER-STORAGE-BUDGET-001.md). Lifecycle: [bound-local-docker-growth](../../openspec/changes/bound-local-docker-growth/). Root пишет scope/spec/tests; отдельный gpt-5.6-sol/low executor реализует; независимые gpt-5.6-sol/low reviewers решают planner-required Gates 3/5. Локально только bounded focused checks; full `make test`/`make verify` запрещён. Exact-source GitHub CI выполняется один раз.

Не входят №157 `compact-task-context-manifest`, завершённый hotfix №258/PR №282, параллельный отдельный worktree/PR №276 `reconcile-completed-installation-state`, application/product behavior, production deployment, изменение CI admission, автоматическая правка Docker Desktop settings, глобальный prune, удаление persistent stand/foreign resources и угадывание владельца исторических anonymous volumes. Предыдущие и параллельные цели сохранены в Git history и не включаются в этот candidate.

Текущее состояние, PR и CI читаются только через `python3 tools/delivery/harness.py state`. Merge/deploy/settings не выполнять.


---

# Parallel address-register delivery history

# Текущая цель этой ветки — адресный перечень с персональными колонками

Поручение владельца 13.11.2026: спроектировать и поставить на production отдельный адресный перечень для пользователей широких Excel-таблиц; каждый пользователь настраивает свои колонки, интерфейс использует shlz-ui. Владелец явно задал production-first с последующим Git pipeline. Worktree `fmonitor-2-address-register-personal-columns`; независимые parallel sessions/production patches сохраняются. Root пишет контракт/tests, отдельный sol/low executor реализует, independent final review и bounded focused checks сохраняются. Полный local make test/verify запрещён, формальная последовательность Gates/CI отложена владельцем и не объявляется GREEN. Очистка базы/смена прав не входят в scope.

Поставка выполнена14.11.2026 00:18МСК:15runtime files, independent final APPROVED, focused HTTP/browser/preferences и architecture GREEN,309objects/7pages read-only qualification, external health200. [Delivery record](address-register-20261114.md). Ожидать обратной связи владельца; полный Git pipeline остаётся следующим этапом, не GREEN.

По дополнительному поручению владельца постоянный маршрут production сохранён в [production-access.md](production-access.md). Предыдущая очередь ниже — история, не scope этой ветки.



---

# Текущая коррекция — вёрстка адресного перечня на штатных shlz-ui компонентах

Owner14.11.2026 отверг поставленный UI: серый самодельный блок настроек, прилипание к краям, два вертикальных скролла, недостаточное использование shlz-ui. Это bounded presentation correction действующего контракта, root автор regression/tests/implementation по разрешённому ordinary bounded fix route; independent final review обязателен. Продолжаем существующий delivery worktree, base e52457801ab38c16995567f6aee9c78077acd7e5, production-first authorization сохраняется. Данные/права/preferences storage не меняются. Прежнее визуальное APPROVED опровергнуто владельцем и не принимается как доказательство этой коррекции.

Исправление поставлено14.11.2026 00:45МСК:4runtime files, publicSHLZ drawers/select, white single-scroll viewport, preserved preferences; fresh independent APPROVED и focused browser/architecture PASS. [Коррекция](address-register-ui-repair-20261114.md). Ждать пользовательской проверки; fullCI остаётся следующим этапом.
