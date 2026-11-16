# Production deployment through Make

Нужны Bash, Make, Docker Engine и Compose v2. PHP, Composer, Node и Python на
сервере не требуются. Все секреты находятся в одном regular-файле mode `0600`.

```sh
make env-init ENV_FILE=/absolute/private/runtime.env
# заполнить replace_me и выбрать image mode
make deploy ENV_FILE=/absolute/private/runtime.env
make status ENV_FILE=/absolute/private/runtime.env
```

`env-init` без Docker определяет текущий Git revision, выбирает source build,
создаёт уникальный image tag и private state/backup directories под `$HOME`.
Оператору остаётся заполнить public hostname, owner email и требуемые внешние
credentials. Если Git недоступен, source initialization отказывает; для
air-gapped сервера используйте environment из проверенного release package.

`FMONITOR_IMAGE_MODE=build` собирает чистый checkout exact revision, `pull`
требует digest-pinned registry reference, `local` использует уже загруженный
image. Для переноса archive укажите абсолютные `FMONITOR_IMAGE_ARCHIVE` и
`FMONITOR_IMAGE_ARCHIVE_SHA256`. Ни один режим не переключается на другой молча.

Jobs выключены по умолчанию. `FMONITOR_JOBS_ENABLED=true` включает worker и
scheduler единым bundle и требует полную конфигурацию Bitrix, ERP и SMTP.

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

Restore допустим только для другого, ещё не существующего `fm2-prod-*` project с
пустыми volumes. Он сохраняет cookie/identity keys исходного backup, использует
новые DB credentials target и отказывает до создания Docker resources при
повреждённом bundle.

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
