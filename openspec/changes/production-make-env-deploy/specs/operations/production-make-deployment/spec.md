## Purpose

Обеспечить оператору воспроизводимое production-развёртывание FMonitor с нуля, одним приватным файлом настроек и командами Make, включая сборку при ограниченной сети и сохранение новых данных.

## ADDED Requirements

### Requirement: Single environment deployment
Система SHALL выполнять A01–A06 контракта PRODUCTION-MAKE-ENV-001 через Make без изменения существующих данных или раскрытия secrets.

#### Scenario: Empty install and replay
- **WHEN** оператор заполняет `.env` и выполняет `make deploy` дважды
- **THEN** новая схема и owner создаются один раз, второй запуск сохраняет данные/учётки и проверяет готовность.

#### Scenario: Invalid or foreign configuration
- **WHEN** файл некорректен, содержит duplicate/unknown keys или project чужой
- **THEN** операция отклоняется до Docker mutations, значения secrets не выводятся.

### Requirement: Verified dependency fallback
Система MUST выполнять A11–A12: Composer-first и direct exact-lock archives fallback с проверкой SHA256, plugins/autoload/platform.

#### Scenario: Repository unavailable
- **WHEN** обычная установка Composer не проходит из-за недоступности repository
- **THEN** installer использует проверенные прямые архивы тех же версий и штатную offline Composer installation.

#### Scenario: Archive integrity mismatch
- **WHEN** архив или lock не соответствует manifest
- **THEN** installer завершается ошибкой и не публикует частичный vendor.

### Requirement: Durable maintenance and release
Система SHALL выполнять A07–A10 и A13: exact-source backup/update, restore исключительно в новый empty project и перенос готовых images без секретов.

#### Scenario: Update failure
- **WHEN** после успешного backup миграция/health нового image неуспешна
- **THEN** writers остаются остановленными, backup/старый image сохраняются, успех не объявляется.

#### Scenario: Restore collision
- **WHEN** target project уже существует либо bundle повреждён
- **THEN** restore отклоняется до изменения данных, source project остаётся неизменным.
