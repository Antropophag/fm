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
