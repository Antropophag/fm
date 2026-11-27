# Домашняя сборка надёжной оффлайн-работы —27.11.2026

Владелец разрешил любые необходимые тесты на домашнем fmonitor.antropophag.ru. Сборка сохраняет действующий address-register/schema44 runtime `f6f67aeb533f8c7b688a8d904eb7a54b688bafe9` и добавляет независимо проверенный feature `838d935af4da76d93eb7900b1416b8eec457f13a`. Ожидаются ровно два этих родителя, без новых бизнес-изменений. Корпоративный периметр, main/PR merge, реальные imports/paid providers и downgrade не входят.

Рабочая копия: `/Users/antropophag/code/fmonitor-2-reliable-field-offline-home-release`; navigation read-only. Полная прежняя домашняя цель сохранена в `openspec/changes/reliable-field-offline/home-previous-delivery-goal.md`, feature goal — в родительском commit. Предыдущие подготовленные home worktrees/ветки остаются нетронутыми.

Контракт `specs/RELIABLE-FIELD-OFFLINE-001.md`, основной verification-input и отдельный read-order RED lineage сохранены. Root проверяет точное сохранение address/feature paths и сборку; независимый sol/low reviewer проверяет весь candidate и новый operator binding. Тяжёлые browser consumers выполняются последовательно; локальный полный make test/verify запрещён. Source/CI читать через harness state; обе новые exact-source CI обязательны до выкладки.

Домашний runtime не остановлен и не изменён. Перед deploy обязательны новый review binding с точными parents/CI/helper hashes, свежий read-only preflight, reviewed schema44 operator, backup и сохранение всех таблиц/истории. Hardware Android/FortiClient/iPhone остаётся UNKNOWN. Impeccable обновляется отдельной задачей.

Независимый final assembly review APPROVED: source4a8a1778, package20261127T130845Z-faa6f51a02;14/14 focused GREEN,140 address +69 feature paths сохранены точно. Initial missing dependencies исправлены locked Composer install без source/lock changes, failed setup evidence сохранено. Следующий шаг — exact two-parent commit/home CI и новый operator binding; live contour по-прежнему не менялся.
