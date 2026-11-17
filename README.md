# FM

Портал управления монтажными работами АО «ЩЛЗ»: объекты, монтажники, ход работ и расчёт ОТиЗ.

## Требования

- Linux x86_64, Git, Make и Bash;
- Docker Engine с Docker Compose v2 и доступ пользователя к Docker;
- DNS и TLS для `fm.shlz.ru`;
- сетевой доступ к настроенным интеграциям (ERP SQL Server, Битрикс, SMTP) и источникам зависимостей для сборки;
- отдельное место и регламент резервного копирования базы данных и файлов.

## Развёртывание

```bash
git clone http://10.178.69.56/ts.grishin/fm.git
cd fm
```

Поместите переданный по защищённому каналу файл `.env` в корень репозитория. Если файл называется `fm-production.env`, переименуйте его:

```bash
mv fm-production.env .env
make deploy
make status
```

Сборка, миграции базы данных и создание первого администратора выполняются автоматически по переданному `.env`. При включённых в `.env` фоновых задачах деплой проверяет доступ к ERP и запускает настроенные синхронизации с Битрикс. Для первого входа используйте значения `FMONITOR_INITIAL_OWNER_EMAIL` и `FMONITOR_BOOTSTRAP_SUPERADMIN_PASSWORD` из него. Старые объекты не переносятся.

Для внешнего HTTPS-прокси на том же сервере направьте `fm.shlz.ru` на `http://127.0.0.1:8093`, передавайте `Host: fm.shlz.ru` и `X-Forwarded-Proto: https`, установите лимит запроса `28 MB` для загрузки PDF размером до 20 МБ.

Обновление не требует правки версий в переданном `.env`:

```bash
git pull --ff-only
make update
```

Основные команды:

```bash
make status
make logs
make backup
make stop
make start
```

Подробнее: [production Make runbook](docs/operations/production-make-env.md) и [настройка среды разработки](docs/development-setup.md).

<details>
<summary>Локальный quickstart</summary>

Нужны Git, Make, `curl`, Docker и Docker Compose v2. Создайте отдельный уникальный project с именем `fm2-local-*`, замените все `replace_me` и укажите email владельца в домене `@shlz.ru`:

```bash
cp .env.example .env
make up
```

После запуска портал доступен на <http://127.0.0.1:8093/>.

</details>
