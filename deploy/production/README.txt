FMonitor: установка нового корпоративного окружения

Требуются Docker Engine с Linux containers, Compose v2, Make и bash. PHP, Node.js
и Composer на host не нужны. Git нужен только для source build. Release содержит
images.tar, release.json, SHA256SUMS, Makefile и deploy/production.

До установки release выполните
`docker info --format '{{.OSType}}/{{.Architecture}}'` и сравните результат с
applicationPlatform/databasePlatform из release.json (`x86_64` соответствует
`amd64`, `aarch64` — `arm64`). Workflow проверяет одинаковую
платформу application и MariaDB images и exact IDs; соответствие серверу
оператор подтверждает до запуска.

Если сисадмин получил подготовленный `.env` вместе с source checkout, маршрут
состоит из трёх действий: clone corporate repository, copy файла в корень как
`.env`, затем `make deploy`. Обычная копия mode0644 будет безопасно сужена до0600
без изменения bytes; `auto` создаст project-scoped private state/backups под
`$HOME`. Ручные `sudo`, `chmod` и `ENV_FILE` не нужны. Небезопасный env или
существующий path отклоняется до Docker.
Автоматическая правка mode относится только к root `.env`; явно выбранный внешний
`ENV_FILE` должен заранее быть private mode0600 и workflow его не изменяет.

1. Если подготовленного `.env` нет, выполните `make env-init`. В release команда
   проверит SHA256SUMS и заполнит
   local/archive identities без Git. В source checkout она запишет текущий HEAD,
   build mode и новый уникальный tag; чистоту checkout команда проверит при
   `make deploy`. Новый `.env` имеет mode0600; повтор его не изменяет.
2. Заполните `FMONITOR_INITIAL_OWNER_EMAIL` адресом @shlz.ru. Начальный пароль
   уже создан в `FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD`; он нужен для первого
   входа. Задайте exact hostname без scheme в
   FMONITOR_TRUSTED_REQUEST_HOST и HTTPS URL с той же authority в
   FMONITOR_PUBLIC_BASE_URL. FMONITOR_TRUSTED_REQUEST_SCHEME=https оставьте.
3. Настройте DNS/certificate и HTTPS reverse proxy на
   127.0.0.1:FMONITOR_HTTP_PORT (default8093). Передавайте исходный Host и
   X-Forwarded-Proto:https, разрешите request body не менее25MiB и не пишите
   query string activation links. MariaDB и PHP-FPM на host не публикуются.
4. Выполните `make deploy`, затем `make status`. Войдите по public URL. Создаётся
   новая БД; старые базы не импортируются.

Сеть: source build требует egress к registry/base images и источникам
apt/npm/Microsoft/PHP dependency archives. Release не требует этих источников
сборки. Jobs=true требует DNS/CA и egress к настроенным Bitrix HTTPS, ERP
endpoint/DB port и SMTP host/port.

Jobs выключены по умолчанию. Для общего worker/scheduler заполните:
- FMONITOR_BITRIX_WEBHOOK_URL: https://host/rest/<id>/<token>/
- FMONITOR_BITRIX_DEPARTMENT_IDS_JSON: непустой JSON-массив положительных ID
- необязательный document webhook того же формата только вместе с положительным
  FMONITOR_BITRIX_ORDER_DOCUMENT_ROOT_ID
- ERP host/database/user/password и HMAC key
- SMTP host/port/encryption/user/password/from address/from name
Задайте FMONITOR_JOBS_ENABLED=true и выполните `make update`. Это разрешает
реальные синхронизации и отправку почты; runtime secret files создаются
автоматически.

Команды:
  make status                 readiness и enabled jobs health
  make logs                   журналы выбранного project
  make stop / make start      lifecycle без удаления volumes
  make backup                 private согласованная копия DB и state
  make update                 backup, forward migrations, проверка кандидата
  make restore BACKUP=/abs/... restore только в новый пустой project

Для другого config path добавляйте ENV_FILE=/absolute/private/runtime.env.
`.env` и backup содержат секреты: не помещайте их в Git, release или общие логи.

Update:
- source build: получите чистый reviewed checkout нового commit, обновите
  FMONITOR_SOURCE_REVISION и задайте новый несуществующий runtime image tag;
- pull: задайте новый digest-pinned image reference;
- local/archive: загрузите проверенный candidate и обновите exact image
  ID/platform/archive fields.
После изменения jobs/integration config также используйте `make update`.
Изменение bootstrap password не сбрасывает пароль существующего owner.

Restore:
1. Создайте отдельный target `.env` из backup `restore.env`.
2. Сохраните exact FMONITOR_SESSION_INSTANCE, FMONITOR_YII_COOKIE_VALIDATION_KEY,
   FMONITOR_YII_IDENTITY_KEY и bundle source/image identity.
3. Задайте новый fm2-prod-* project, свободный HTTP port, новый deployment state
   directory и новые runtime/root DB passwords. Если target публикуется через
   другой hostname/URL, trusted host и public URL должны точно соответствовать
   запланированному reverse proxy; при прежней внешней authority менять их только
   из-за нового локального port не требуется.
4. Выполните `make restore BACKUP=/absolute/private/backup`.
Target containers/network/volumes должны отсутствовать. Source project остаётся
запущенным и не изменяется; in-place restore не поддерживается.

Source build сначала использует Composer, затем закреплённые архивы с SHA256.
Если недоступны также base images/apt/npm/Microsoft/archive endpoints, соберите
release на подходящей платформе и перенесите готовые images.

Передача: release с SHA256SUMS, согласованные архитектура/домен/порт proxy; `.env`
отдельно по защищённому каналу. Данные лежат в Docker volumes; metadata/backup —
в FMONITOR_DEPLOY_STATE_DIR/FMONITOR_BACKUP_ROOT. Нужен свободный диск под images,
данные, backup и временную копию restore. Расписание, срок хранения и вынос backup
на отдельный носитель настраивает сисадмин; автоматического удаления копий нет.
