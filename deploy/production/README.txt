FMonitor: установка нового корпоративного окружения

Требуются Docker Engine, Compose v2, Make и bash. PHP, Node.js и Composer на
хосте не нужны. Git нужен при сборке из исходного checkout. Готовый release
содержит images.tar, release.json, SHA256SUMS, Makefile и каталог deploy.

1. Сверьте applicationPlatform/databasePlatform в release.json с сервером.
   Не используйте пакет другой архитектуры без подготовки подходящих образов.
2. Выполните make env-init. Команда проверит release checksums, создаст .env
   с правами600 и новыми внутренними секретами. Повтор сохраняет существующий
   файл. В исходном checkout выбирается сборка, в release — загрузка images.tar.
3. В .env задайте FMONITOR_INITIAL_OWNER_EMAIL (адрес @shlz.ru), домен в
   FMONITOR_TRUSTED_REQUEST_HOST и HTTPS URL в FMONITOR_PUBLIC_BASE_URL.
   Стартовый пароль находится в FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD.
4. Настройте корпоративный HTTPS reverse proxy на127.0.0.1:8093 (либо порт
   FMONITOR_HTTP_PORT). Передавайте исходный Host, разрешите тело запроса32МБ.
   Сертификат и DNS настраиваются на стороне инфраструктуры.
5. Выполните make deploy, затем make status. Войдите по указанному HTTPS URL
   со стартовой учёткой. Схема создаётся в новой БД; старые базы не импортируются.

FMONITOR_JOBS_ENABLED=false позволяет начать без внешних интеграций. Для
включения общего worker/scheduler заполните Bitrix, ERP и SMTP в том же .env,
задайте true и выполните make update. Отдельные runtime JSON/secrets команда
подготовит сама. Включение jobs разрешает их штатную работу с внешними системами.
Стартовый пароль применяется только при создании owner; дальнейшие изменения
его значения в .env не сбрасывают пароль существующего пользователя.

Команды эксплуатации:
  make status                 состояние текущего окружения
  make logs                   текущие журналы
  make stop / make start      остановка/старт без удаления данных
  make backup                 согласованная private копия БД и файлов
  make update                 backup старого образа, миграции, проверка нового
  make restore BACKUP=/abs/... восстановление исключительно в новый пустой project

Для другого пути конфигурации добавьте ENV_FILE=/absolute/private/runtime.env.
Файл .env и backup содержат секреты: не помещайте их в Git, общие логи или release.
Для restore возьмите restore.env из backup, сохраните исходные session/identity
keys, задайте новый project/порт/state directory и новые DB/root passwords.
Существующее окружение восстановление не перезаписывает. Подробный протокол и
примеры proxy: docs/operations/production-make-env.md в исходном репозитории.

Сборка сначала использует Composer, затем при ошибке — прямые архивы тех же
пакетов с проверкой SHA256. Если недоступны и прямые источники/apt/npm/base images,
перенесите готовый release: при его установке сборка и Composer не требуются.
