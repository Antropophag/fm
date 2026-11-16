## Why

Оператору нужна установка FMonitor в пустую БД через один приватный `.env` и Make, без ручного provisioning и настройки нескольких secret files. Корпоративная сеть может блокировать Composer repositories, поэтому сборка должна иметь проверяемый запасной путь загрузки exact-lock пакетов.

## What Changes

- Production команды env-init/deploy/status/logs/stop/start/backup/update/restore/release; существующий local quickstart сохраняется.
- Генерация внутренних секретов, literal parsing environment, initial-owner только при первом создании и явное включение jobs.
- Изолированный production Compose, готовые образы/pull/local archive и явная source build.
- Composer-first install с verified direct archives fallback и штатным autoload/plugin/platform check.
- Current-schema backup/restore через существующего application owner; restore только в новый пустой project.
- Инструкция, reverse-proxy example и focused/disposable acceptance.

## Capabilities

### New Capabilities
- `operations/production-make-deployment`: единый операторский интерфейс production installation и maintenance.

### Modified Capabilities

Нет изменений business behavior.

## Impact

Актор — deployment operator с правом Docker и чтения приватного environment. Публичный seam — Make/production manage и dependency installer; oracle — согласованный владельцем сценарий и canonical migration/provision/recovery owners. Затронуты Make, deployment packaging/templates, docs и executable tests. Не входят live deployment, legacy import, actual email sends, автоматический merge, in-place destructive restore, policy bypass и чужие worktrees.
