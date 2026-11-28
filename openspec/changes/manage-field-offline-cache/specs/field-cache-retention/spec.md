## Purpose

Обеспечить длительную оффлайн-работу инженера с ограниченным воспроизводимым кэшем, доступными объектами подмены и сохранностью всех ещё не отправленных данных.

## ADDED Requirements

### Requirement: Bounded reproducible cache

Приложение SHALL соблюдать FIELD-CACHE-RETENTION-001 R1:100МиБ воспроизводимых responses и резерв200МиБ при доступном storage estimate, без ложной готовности.

#### Scenario: Capacity exhausted
- **WHEN** новый чек-лист не помещается после безопасной очистки
- **THEN** онлайн-чтение остаётся доступным, подготовка показывает partial и причину, прежние защищённые данные сохраняются

### Requirement: Discoverable substitution objects

Приложение SHALL объединять все свои подготовленные объекты с успешно открытыми онлайн объектами подмены на7дней по R2/R3, с поиском и стандартным стройконтролем.

#### Scenario: Replace a colleague
- **WHEN** инженер с действующим read-доступом открыл чужой чек-лист с сетью и позже отключился
- **THEN** этот объект доступен в том же списке и поиске после restart; offline GET не продлевает срок

### Requirement: Protected pending data and bounded generations

Очистка SHALL выполнять R4/R5 и никогда не удалять/изменять unresolved операции/Blob; unknown protection запрещает удаление.

#### Scenario: Expiry with a queued photograph
- **WHEN** срок подмены закончился, но фотография ещё не принята сервером
- **THEN** объект и bytes сохраняются с пояснением pending; повтор maintenance не меняет UUID/wire/payload

#### Scenario: Successful and failed application updates
- **WHEN** новое complete поколение установлено и старые клиенты закрыты
- **THEN** ненужные старые копии очищаются только после сохранения необходимых документов; failed install оставляет прежний рабочий shell

### Requirement: Actor and permission fences

Приложение SHALL выполнять R6/R7: отозванный/чужой доступ не раскрывает cached data, server replay остаётся авторизованным и идемпотентным.

#### Scenario: Permission revoked while work is pending
- **WHEN** fresh checklist GET возвращает403
- **THEN** offline показ этого объекта блокируется, а unresolved операции и фото сохраняются для разрешения после подключения
