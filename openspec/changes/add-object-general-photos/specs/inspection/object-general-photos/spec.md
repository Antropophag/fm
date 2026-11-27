## Purpose
Позволить стройконтролю сохранять общие фотографии текущего объекта монтажа и использовать их без сети независимо от фотографий конкретных разделов чек-листа.

## ADDED Requirements
### Requirement: General photos belong to current object
Система SHALL реализовать A1–A3 OBJECT-GENERAL-PHOTOS-001: существующие права, public photo seam, идемпотентность, журнал, отдельный limit и отсутствие влияния на выполнение разделов.
#### Scenario: General photo does not complete section
- **WHEN** инженер загрузил общее фото объекта и выполнил все пункты раздела без фото этого раздела
- **THEN** общее фото доступно в галерее объекта, а завершение раздела отклонено без новых фактов
### Requirement: Compact offline general photo workflow
Система SHALL реализовать A4–A6 OBJECT-GENERAL-PHOTOS-001: компактные camera/file controls у «Все фото», local durable queue, recovery и автоматическую отправку.
#### Scenario: Capture without network
- **WHEN** инженер выбирает общее фото без сети
- **THEN** снимок виден в general strip и общей галерее, сохранён локально и автоматически отправляется с тем жеID при возврате сети

### Requirement: General-photo deletion preserves other queued work
Система SHALL удалять только выбранное неотправленное общее фото согласно A6 OBJECT-GENERAL-PHOTOS-001, сохраняя остальные bytes/IDs и actor/wire guards.
#### Scenario: Delete first of several queued general photos
- **WHEN** пользователь подтверждает удаление первого из нескольких неотправленных общих фото
- **THEN** удалён только выбранный снимок, остальные доступны и сохраняют порядок отправки
