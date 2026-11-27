# Домашняя сборка — единый поиск адресов и подъездов

Владелец разрешил автономно довести PR314 до main и обновить домашний стенд. PR314 merged; проверенный head d04983f1d789706ebac6571b2b9185e7b17eeb4e, полный exact-source CI46077589859 SUCCESS. Эта сборка сохраняет домашнюю адресную базу/schema44 и добавляет поиск без изменения фактов, прав и финансовой логики. Точные родители, в порядке: `80dabd52a1845b5b2eaf213bd730a9c860ddf905`, `b8eee46c4648d6c5d6669a631d4727b316bfa4a2`. Дерево main проверено равным проверенному PR head.

Рабочая копия `/Users/antropophag/code/fmonitor-2-flexible-address-search-home44`; navigation read-only. Root отвечает за контракт/проверки, отдельный sol/low executor — за композицию Composer inputs; независимый sol/low reviewer — за точные overlap blobs и финальные assembly/operator approvals. Только bounded local checks; full suite — один exact-source CI. До выкладки обязательны сохранение всех родительских путей/каталога/тестов, независимое review, свежий preflight, backup четырёх остановленных writers, schema44 replay без новых миграций и сверка всех таблиц/истории. После первого запуска candidate writer разрешён только app rollback с сохранением актуальных данных. Live пока не менялся. Прежние цели обоих родителей ниже сохранены полностью.

## История main

# Текущая цель — flexible address search

Поручение владельца 2026-11-27: подобрать готовое решение и интегрировать свободный поиск адреса во всех действующих разделах, включая подъезд. `игорная 10 1 1` означает дом 10, корпус 1, подъезд 1. Scope: поиск и его проекции; без изменения адресных фактов, финансовых расчётов, прав или финансовой логики. Владелец дополнительно поручил автономно довести PR до merge в main и обновить домашний стенд; ориентироваться на качественный общий поиск и best practices, а не подгонять правила под примеры.

Работа ведётся в выделенном worktree `fmonitor-2-flexible-address-search`, base `701617e03d97abe5529207f7fa1995f3996e27e6`. Root — спецификация/тесты; отдельный gpt-5.6-sol/low executor — реализация; независимый gpt-5.6-sol/low reviewer — planner-required review. Локально только bounded focused checks; полный `make test`/`make verify` запрещён. CI — выбранный planner consumer для точного source. Разрешены PR, normal protected merge и домашний deploy после проверок с сохранением данных. Предыдущие поручения ниже сохранены как история.

## Предыдущая цель — offline UI и общие фото объекта

27.11.2026 owner явно поручил оформить ВСЕ изменения текущей сессии до merge в main и выложить на домашний стенд. Прежний запрет PR/пауза local preview СНЯТЫ; обязательные независимые reviews, CI и запрет full local make test/verify сохраняются. Scope: компактные truthful offline/sync statuses, удаление custom install/manual sync UI, same-origin manifest CSP fix, общие фото текущего объекта.

Зависимость PR312 (838d935a) имеет exact-source CI SUCCESS; отдельный address import PR307 не входит в текущий PR и не merged по умолчанию. Home schema44/address runtime сохраняется при отдельной совместимой home assembly после merge нашей ветки. Текущий delivery worktree `/Users/antropophag/code/fmonitor-2-offline-minimal-main`, branch codex/offline-minimal-main, base838d935a. Session delta перенесён cleanly; home-based preview/WIP сохранён в прежней копии и `/tmp/fm-offline-home-wip-transfer`.

# Локальный preview — общие фото текущего объекта

Owner27.11.2026 поручил общие фото текущего лифта, кнопки снизу у «Все фото»; scope уточнён ответом «К текущему объекту целиком». Change `add-object-general-photos`, контракт OBJECT-GENERAL-PHOTOS-001. Root specs/tests/preview; отдельные sol/low executors, reviews/CI pending по текущему local-preview разрешению. Не публиковать PR до согласования. Предыдущий WIP/цели сохраняются ниже.

# Локальный визуальный preview — до согласования

Новый owner scope: минимализм чек-листа, убрать routine saved-actions портянку, объяснить номер заказа над процентами. Контракт CHECKLIST-MINIMAL-001 в текущем delivery record. Root tests/preview, отдельный executor implementation; остаёмся local preview без PR.

Последний отзыв: компактная группа справа «Доступно оффлайн: N объектов» + точка рядом. Count из фактической offline preparation, без раскрытия/кнопок. Цвета: подготовка синий, ready зелёный, partial янтарный, error красный.

27.11.2026 пользователь явно поручил сначала сверстать локально и открыть в браузере; НЕ начинать публикацию PR до согласования вёрстки. Это task-specific preview authorization; formal gates/CI остаются отложенными, не approved. Scope/status ниже сохраняются.

# Текущая цель — компактная офлайн-индикация

Поручение владельца27.11.2026: уменьшить индикатор, убрать кнопку установки, исправить установку через браузер. Контракт и authorship: [delivery record](compact-offline-indicator-20261127.md). Worktree `/Users/antropophag/code/fmonitor-2-compact-offline-indicator`; base bfe88ccd. Root spec/tests, отдельный executor, независимые planner-required reviews. Только bounded local checks, full suite через exact-source CI. Прежняя цель сохранена ниже как история.

---

# Домашняя сборка надёжной оффлайн-работы —27.11.2026

Владелец разрешил любые необходимые тесты на домашнем fmonitor.antropophag.ru. Сборка сохраняет действующий address-register/schema44 runtime `f6f67aeb533f8c7b688a8d904eb7a54b688bafe9` и добавляет независимо проверенный feature `838d935af4da76d93eb7900b1416b8eec457f13a`. Ожидаются ровно два этих родителя, без новых бизнес-изменений. Корпоративный периметр, main/PR merge, реальные imports/paid providers и downgrade не входят.

Рабочая копия: `/Users/antropophag/code/fmonitor-2-reliable-field-offline-home-release`; navigation read-only. Полная прежняя домашняя цель сохранена в `openspec/changes/reliable-field-offline/home-previous-delivery-goal.md`, feature goal — в родительском commit. Предыдущие подготовленные home worktrees/ветки остаются нетронутыми.

Контракт `specs/RELIABLE-FIELD-OFFLINE-001.md`, основной verification-input и отдельный read-order RED lineage сохранены. Root проверяет точное сохранение address/feature paths и сборку; независимый sol/low reviewer проверяет весь candidate и новый operator binding. Тяжёлые browser consumers выполняются последовательно; локальный полный make test/verify запрещён. Source/CI читать через harness state; обе новые exact-source CI обязательны до выкладки.

Домашний runtime не остановлен и не изменён. Перед deploy обязательны новый review binding с точными parents/CI/helper hashes, свежий read-only preflight, reviewed schema44 operator, backup и сохранение всех таблиц/истории. Hardware Android/FortiClient/iPhone остаётся UNKNOWN. Impeccable обновляется отдельной задачей.

Независимый final assembly review APPROVED: source4a8a1778, package20261127T130845Z-faa6f51a02;14/14 focused GREEN,140 address +69 feature paths сохранены точно. Initial missing dependencies исправлены locked Composer install без source/lock changes, failed setup evidence сохранено. Следующий шаг — exact two-parent commit/home CI и новый operator binding; live contour по-прежнему не менялся.


## История домашнего стенда

# Домашняя сборка v2 — компактный офлайн-статус и общие фото

Owner27.11 разрешил текущий scope до merge main, затем home deploy. Эта отдельная композиция сохраняет live bfe88ccdc505f953c11f9c34371f7447b1f63380/schema44/address runtime и добавляет reviewed feature cc1ad09b3175585d008cf8c20ea7d859ba0cccf0. Worktree `/Users/antropophag/code/fmonitor-2-offline-minimal-home44`, branch codex/offline-minimal-home44-release-v2. Ровно два родителя [live bfe88ccd, feature cc1ad09b], без новых бизнес-изменений; root composition, independent sol/low final review. Full local suite запрещён; bounded preservation/catalog/focused checks и exact-source full CI обязательны.

Initial PR CI46035652358/home CI46036642158 сохранены как неуспешные. Устранены только два устаревших test oracles: allowlist3 новых focused profiles и счётчики списка в прежнем header вместо footer. Полный failure inventory сохранён вне checkout; main governance автоматически cancelled с потерянным GitHub log blob, UNKNOWN не выдано за GREEN. App/runtime bytes v2 совпадают с исходной independently approved assembly 11aa5b6da5c7de672b09fceb2a1c6eee09d57f59; новые immutable CI обязательны.

Feature PR313 должен быть merged до staging/cutover. Wrapper проверяет точный main merge commit, равенство его tree с reviewed feature, обе успешные CI, сохранение schema44 и новые operator bindings. PR307 не merged. Корпоративный контур, реальные imports/paid providers и downgrade вне scope. До successful review/preflight/backup стенд не меняется. Физические iPhone/camera/VPN UNKNOWN.

## Feature delivery history

# Полная доставка — offline UI и общие фото объекта

27.11.2026 owner явно поручил оформить ВСЕ изменения текущей сессии до merge в main и выложить на домашний стенд. Прежний запрет PR/пауза local preview СНЯТЫ; обязательные независимые reviews, CI и запрет full local make test/verify сохраняются. Scope: компактные truthful offline/sync statuses, удаление custom install/manual sync UI, same-origin manifest CSP fix, общие фото текущего объекта.

Зависимость PR312 (838d935a) имеет exact-source CI SUCCESS; отдельный address import PR307 не входит в текущий PR и не merged по умолчанию. Home schema44/address runtime сохраняется при отдельной совместимой home assembly после merge нашей ветки. Текущий delivery worktree `/Users/antropophag/code/fmonitor-2-offline-minimal-main`, branch codex/offline-minimal-main, base838d935a. Session delta перенесён cleanly; home-based preview/WIP сохранён в прежней копии и `/tmp/fm-offline-home-wip-transfer`.

# Локальный preview — общие фото текущего объекта

Owner27.11.2026 поручил общие фото текущего лифта, кнопки снизу у «Все фото»; scope уточнён ответом «К текущему объекту целиком». Change `add-object-general-photos`, контракт OBJECT-GENERAL-PHOTOS-001. Root specs/tests/preview; отдельные sol/low executors, reviews/CI pending по текущему local-preview разрешению. Не публиковать PR до согласования. Предыдущий WIP/цели сохраняются ниже.

# Локальный визуальный preview — до согласования

Новый owner scope: минимализм чек-листа, убрать routine saved-actions портянку, объяснить номер заказа над процентами. Контракт CHECKLIST-MINIMAL-001 в текущем delivery record. Root tests/preview, отдельный executor implementation; остаёмся local preview без PR.

Последний отзыв: компактная группа справа «Доступно оффлайн: N объектов» + точка рядом. Count из фактической offline preparation, без раскрытия/кнопок. Цвета: подготовка синий, ready зелёный, partial янтарный, error красный.

27.11.2026 пользователь явно поручил сначала сверстать локально и открыть в браузере; НЕ начинать публикацию PR до согласования вёрстки. Это task-specific preview authorization; formal gates/CI остаются отложенными, не approved. Scope/status ниже сохраняются.

# Текущая цель — компактная офлайн-индикация

Поручение владельца27.11.2026: уменьшить индикатор, убрать кнопку установки, исправить установку через браузер. Контракт и authorship: [delivery record](compact-offline-indicator-20261127.md). Worktree `/Users/antropophag/code/fmonitor-2-compact-offline-indicator`; base bfe88ccd. Root spec/tests, отдельный executor, независимые planner-required reviews. Только bounded local checks, full suite через exact-source CI. Прежняя цель сохранена ниже как история.

---

# Домашняя сборка надёжной оффлайн-работы —27.11.2026

Владелец разрешил любые необходимые тесты на домашнем fmonitor.antropophag.ru. Сборка сохраняет действующий address-register/schema44 runtime `f6f67aeb533f8c7b688a8d904eb7a54b688bafe9` и добавляет независимо проверенный feature `838d935af4da76d93eb7900b1416b8eec457f13a`. Ожидаются ровно два этих родителя, без новых бизнес-изменений. Корпоративный периметр, main/PR merge, реальные imports/paid providers и downgrade не входят.

Рабочая копия: `/Users/antropophag/code/fmonitor-2-reliable-field-offline-home-release`; navigation read-only. Полная прежняя домашняя цель сохранена в `openspec/changes/reliable-field-offline/home-previous-delivery-goal.md`, feature goal — в родительском commit. Предыдущие подготовленные home worktrees/ветки остаются нетронутыми.

Контракт `specs/RELIABLE-FIELD-OFFLINE-001.md`, основной verification-input и отдельный read-order RED lineage сохранены. Root проверяет точное сохранение address/feature paths и сборку; независимый sol/low reviewer проверяет весь candidate и новый operator binding. Тяжёлые browser consumers выполняются последовательно; локальный полный make test/verify запрещён. Source/CI читать через harness state; обе новые exact-source CI обязательны до выкладки.

Домашний runtime не остановлен и не изменён. Перед deploy обязательны новый review binding с точными parents/CI/helper hashes, свежий read-only preflight, reviewed schema44 operator, backup и сохранение всех таблиц/истории. Hardware Android/FortiClient/iPhone остаётся UNKNOWN. Impeccable обновляется отдельной задачей.

Независимый final assembly review APPROVED: source4a8a1778, package20261127T130845Z-faa6f51a02;14/14 focused GREEN,140 address +69 feature paths сохранены точно. Initial missing dependencies исправлены locked Composer install без source/lock changes, failed setup evidence сохранено. Следующий шаг — exact two-parent commit/home CI и новый operator binding; live contour по-прежнему не менялся.

## Prior live assembly history

# Домашняя сборка надёжной оффлайн-работы —27.11.2026

Владелец разрешил любые необходимые тесты на домашнем fmonitor.antropophag.ru. Сборка сохраняет действующий address-register/schema44 runtime `f6f67aeb533f8c7b688a8d904eb7a54b688bafe9` и добавляет независимо проверенный feature `838d935af4da76d93eb7900b1416b8eec457f13a`. Ожидаются ровно два этих родителя, без новых бизнес-изменений. Корпоративный периметр, main/PR merge, реальные imports/paid providers и downgrade не входят.

Рабочая копия: `/Users/antropophag/code/fmonitor-2-reliable-field-offline-home-release`; navigation read-only. Полная прежняя домашняя цель сохранена в `openspec/changes/reliable-field-offline/home-previous-delivery-goal.md`, feature goal — в родительском commit. Предыдущие подготовленные home worktrees/ветки остаются нетронутыми.

Контракт `specs/RELIABLE-FIELD-OFFLINE-001.md`, основной verification-input и отдельный read-order RED lineage сохранены. Root проверяет точное сохранение address/feature paths и сборку; независимый sol/low reviewer проверяет весь candidate и новый operator binding. Тяжёлые browser consumers выполняются последовательно; локальный полный make test/verify запрещён. Source/CI читать через harness state; обе новые exact-source CI обязательны до выкладки.

Домашний runtime не остановлен и не изменён. Перед deploy обязательны новый review binding с точными parents/CI/helper hashes, свежий read-only preflight, reviewed schema44 operator, backup и сохранение всех таблиц/истории. Hardware Android/FortiClient/iPhone остаётся UNKNOWN. Impeccable обновляется отдельной задачей.

Независимый final assembly review APPROVED: source4a8a1778, package20261127T130845Z-faa6f51a02;14/14 focused GREEN,140 address +69 feature paths сохранены точно. Initial missing dependencies исправлены locked Composer install без source/lock changes, failed setup evidence сохранено. Следующий шаг — exact two-parent commit/home CI и новый operator binding; live contour по-прежнему не менялся.
