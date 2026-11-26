# Локальный показ процесса распоряжения

Контракт: specs/ORDER-LOCAL-PREVIEW-001.md. Owner preview-first, 25.11.2026. Новые дата и номер заменяют прежнее решение без номера 1С ДО.

## Capabilities
- `order-local-preview`: согласованный процесс распоряжения, сохранённый PDF, номера, замена скана и компактные рабочие экраны.
- `equipment-reclamation`: добавочные дни рекламаций, финансовые основания и сохранение истории.

## Impact
Полная интеграция всего накопленного preview; схемы42/43, recovery/readiness и downstream consumers. Внешние первичные данные и секреты вне Git. Домашний deployment только merged-версии с backup.
