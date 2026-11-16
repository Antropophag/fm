# Production deployment through Make

Нужны Bash, Make, Docker Engine и Compose v2. PHP, Composer, Node и Python на
сервере не требуются. Все секреты находятся в одном regular-файле mode `0600`.

```sh
make env-init ENV_FILE=/absolute/private/runtime.env
# заполнить hostname и owner email; integrations — только если включены jobs
make deploy ENV_FILE=/absolute/private/runtime.env
make status ENV_FILE=/absolute/private/runtime.env
```

`env-init` без Docker определяет текущий Git revision, выбирает source build,
создаёт уникальный image tag и private state/backup directories под `$HOME`.
Оператору остаётся заполнить public hostname, owner email и требуемые внешние
credentials. Если Git недоступен, source initialization отказывает; для
air-gapped сервера используйте environment из проверенного release package.
Чистота source checkout проверяется при `make deploy`, перед сборкой image.

`FMONITOR_IMAGE_MODE=build` собирает чистый checkout exact revision, `pull`
требует digest-pinned registry reference, `local` использует уже загруженный
image. Для переноса archive укажите абсолютные `FMONITOR_IMAGE_ARCHIVE` и
`FMONITOR_IMAGE_ARCHIVE_SHA256`. Ни один режим не переключается на другой молча.
`FMONITOR_APPLICATION_IMAGE_ID` может оставаться пустым только для source build
или non-archive acquisition: затем workflow проверяет и фиксирует фактический ID.
Release/archive задаёт exact application/database image IDs и обе platform.
Workflow требует одинаковую платформу images; соответствие платформе
Docker-сервера оператор проверяет до установки командой
`docker info --format '{{.OSType}}/{{.Architecture}}'` и по `release.json`:
`x86_64` соответствует `amd64`, `aarch64` — `arm64`.

Jobs выключены по умолчанию. `FMONITOR_JOBS_ENABLED=true` включает worker и
scheduler единым bundle и требует полную конфигурацию Bitrix, ERP и SMTP.
Это разрешает реальные синхронизации и отправку почты сразу после запуска.
Нужны workforce webhook `https://host/rest/<id>/<token>/`, непустой JSON-массив
department IDs, ERP host/database/user/password и SMTP host/user/password/from.
Document webhook того же формата необязателен, но задаётся только вместе с
положительным document root ID.

## Сеть и TLS

Единственный входящий endpoint — `127.0.0.1:FMONITOR_HTTP_PORT`; MariaDB и
PHP-FPM на host не публикуются. `FMONITOR_TRUSTED_REQUEST_HOST` содержит exact
hostname (и нестандартный port) без scheme; authority в
`FMONITOR_PUBLIC_BASE_URL=https://...` должна совпадать.

Source build требует egress к registry/base images и источникам
apt/npm/Microsoft/PHP dependency archives. Release installation этого egress для
сборки не требует. Jobs=true требует DNS/CA и egress к Bitrix HTTPS, ERP
endpoint/DB port и SMTP host/port.

HTTP runtime слушает только loopback. Внешний TLS proxy должен передавать
исходный `Host` и `X-Forwarded-Proto: https`, принимать body больше 21 MiB и не
записывать query string activation links. Например, Caddy передаёт запросы так:

```caddyfile
fmonitor.example.org {
    request_body { max_size 25MB }
    log { output discard }
    reverse_proxy 127.0.0.1:8093 {
        header_up Host {host}
        header_up X-Forwarded-Proto https
    }
}
```

Повседневные команды сохраняют volumes:

```sh
make logs  ENV_FILE=/absolute/private/runtime.env
make stop  ENV_FILE=/absolute/private/runtime.env
make start ENV_FILE=/absolute/private/runtime.env
```

Backup останавливает только writers выбранного project и вызывает публичный
`fmonitor2-runtime-recovery.php` из установленного image. Destination должен быть
новым абсолютным путём вне checkout:

```sh
make backup ENV_FILE=/absolute/private/runtime.env BACKUP=/absolute/private/backups/point-1
```

Update сначала получает и проверяет новый image, затем создаёт backup старого
source при остановленных writers и применяет только forward migrations. Ошибка
оставляет writers остановленными; автоматического destructive rollback нет.
Изменение jobs/integration configuration также применяется только через
`make update`; обычные `deploy`, `start`, `status` и `logs` отклоняют drift
установленного environment. Bootstrap password при update не создаёт и не
сбрасывает owner account.

```sh
make update ENV_FILE=/absolute/private/runtime.env
```

Перед source update получите чистый reviewed checkout нового commit, обновите
`FMONITOR_SOURCE_REVISION` и задайте новый, ещё не существующий runtime image tag.
Для `pull` задайте новый digest-pinned reference; для `local` заранее загрузите
проверенный image/archive и обновите exact identity fields. Mutable tag нельзя
перезаписывать как update.

Restore допустим только для другого, ещё не существующего `fm2-prod-*` project с
пустыми volumes. Он сохраняет cookie/identity keys исходного backup, использует
новые DB credentials target и отказывает до создания Docker resources при
повреждённом bundle.

Создайте отдельный target environment. Из backup `restore.env` без изменений
перенесите `FMONITOR_SESSION_INSTANCE`, cookie validation key и identity key;
source/image identity должна соответствовать bundle. Задайте новые project,
HTTP port, deployment state directory и runtime/root DB passwords. Source project
не останавливается и не изменяется; target containers/network/volumes должны
отсутствовать.

```sh
make restore ENV_FILE=/absolute/private/restore.env BACKUP=/absolute/private/backups/point-1
```

Air-gap package содержит application и MariaDB images, deployment files и
checksums, но не `.env`, данные или runtime logs:

```sh
make release ENV_FILE=/absolute/private/runtime.env OUTPUT=/absolute/private/releases/release-1
```

Команды сериализованы на project. Production workflow не предоставляет reset,
prune или `down --volumes`.

Пакет готовых образов привязан к платформе: `release.json` хранит точные ID
приложения и MariaDB, а также `applicationPlatform`/`databasePlatform`
(например, `linux/amd64` или `linux/arm64`). Перед передачей сисадмину
согласуйте архитектуру сервера. Пакет для другой архитектуры нужно собрать
на соответствующей платформе; исходный build также требует доступа к
базовым образам, apt/npm и Microsoft repository. Fallback PHP-пакетов
устраняет зависимость от доступности Composer repositories, но не заменяет
все источники сборки. Перенос готовых images исключает сборку на целевом сервере.
