## Why
Стройконтролю нужны общие фотографии текущего объекта, которым не подходит ни один раздел чек-листа. Владелец27.11.2026 подтвердил привязку к текущему лифту и компактные кнопки рядом с «Все фото».

## What Changes
- Съёмка/выбор общих фото объекта снизу чек-листа.
- Общие фото используют сохранение на устройстве, автоматическую отправку, галерею и восстановление существующего photo workflow.
- Общие фото не закрывают требования фото разделов и не меняют прогресс.
- Public seam: существующие checklist photo/operation HTTP endpoints текущего объекта; actor — пользователь с существующими правами загрузки/отзыва фото.
- Non-goals: общие фото всех лифтов заказа, изменение выплат/документов/схемы прав, публикация PR до visual approval.

## Capabilities
### New Capabilities
- `inspection/object-general-photos`: фото текущего объекта без привязки к разделу.
### Modified Capabilities
Нет: существующие требования фото раздела сохраняются.

## Impact
Native checklist mutation/projection, Yii view/client, offline store/photo flow, focused HTTP/browser regressions. Существующий WIP минимализма сохраняется. Root contract/tests, отдельные sol/low executors; preview authorization сохраняет deferred gates честно.
