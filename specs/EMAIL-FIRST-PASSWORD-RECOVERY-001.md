# EMAIL-FIRST-PASSWORD-RECOVERY-001

## Простыми словами

Администратор заранее добавляет рабочий email. Первый пароль пользователь задаёт на портале; восстановление забытого пароля выполняется письмом без участия администратора. Существующие пароли, роли и история сохраняются.

Статус: контракт root; RED и независимый Gate 3 ещё не выполнены. Источник требований — диалог владельца, включая явное поручение реализации. Этот контракт заменяет invitation/activation части YII2-USER-ACCESS-001 и YII2-USER-INVITATION-UX-001, а также выбор шага входа YII2-AUTH-001. Прочие RBAC/session guarantees наследуются.

## Purpose

Позволяет заранее допущенным пользователям задать первый пароль непосредственно на портале и восстановить забытый пароль через рабочую почту без приглашений и участия администратора.

## ADDED Requirements

### Requirement: Предварительное предоставление доступа

Система SHALL разрешать добавление пользователя только активному администратору с точным `access.administer`. Email SHALL нормализоваться trim/lowercase и принадлежать домену `@shlz.ru`; имя и пароль наследуют ограничения `YII2-USER-ACCESS-001`. Новая запись SHALL иметь состояние «Ожидает первого входа», отсутствующий пароль и обычную базовую роль. Дополнительные роли SHALL назначаться существующей защищённой командой. Создание SHALL атомарно сохранять identity, credentials, базовую роль и audit с администратором/временем; оно SHALL NOT выпускать приглашение или отправлять письмо. Полномочия администратора SHALL повторно проверяться при записи.

#### Scenario: Добавление и конкурентный повтор
- **WHEN** администратор добавляет ` New.Person@SHLZ.RU ` с допустимым ФИО, включая два конкурентных запроса
- **THEN** существует ровно одна запись `new.person@shlz.ru` без пароля, одно событие создания и нет приглашений/писем; дубликат получает понятную ошибку без дополнительных фактов

#### Scenario: Отказ без предоставления доступа
- **WHEN** запрос выполняет гость, пользователь без права, администратор после отзыва права либо переданы внешний email/некорректное ФИО
- **THEN** доступ не создаётся; гость направляется на вход, отсутствие права даёт 403, некорректные поля показываются с ошибкой и сохранением безопасных значений формы

#### Scenario: Блокировка до первого входа
- **WHEN** уполномоченный администратор блокирует, а затем разблокирует пользователя без пароля
- **THEN** блокировка SHALL запрещать первый пароль; разблокировка SHALL возвращать ожидание первого пароля, не создавать hash и не давать сессию; обе операции SHALL повышать session version и сохранять отдельные audit facts с прежней защитой последнего администратора

### Requirement: Выбор шага входа по email

После ввода email портал SHALL показывать форму первого пароля только для заранее внесённой разрешённой записи без пароля, форму обычного входа — для разрешённой записи с паролем. Для отсутствующего доменного email SHALL отображаться «Для предоставления доступа создайте заявку в ИТСМ». Некорректный/внешний email SHALL получать ошибку домена. Заблокированный/отключённый пользователь SHALL NOT получать возможность задания пароля, восстановления доступа или входа. Чтение формы SHALL NOT создавать пользователя или изменять роли/пароли. Email-различие в первом входе — явное продуктовое поведение; имя, роли и другие данные чужой записи до аутентификации SHALL NOT отображаться.

#### Scenario: Известный адрес без пароля
- **WHEN** пользователь вводит адрес заранее добавленной разрешённой записи без пароля
- **THEN** портал предлагает новый пароль и его повтор без письма, приглашения, ручного секретного кода или обращения к администратору

#### Scenario: Неизвестный адрес и существующий пароль
- **WHEN** введён отсутствующий адрес либо адрес разрешённой записи с паролем
- **THEN** в первом случае показана подсказка ИТСМ без создания записи, во втором — пароль для обычного входа и действие «Забыли пароль?»

### Requirement: Однократная установка первого пароля

Команда первого пароля SHALL повторно проверять существование, разрешённый статус и отсутствие hash внутри атомарной записи. Она SHALL сохранять стойкий hash, время установки и одно событие первого пароля без самого пароля. Совпадающий пароль и подтверждение SHALL удовлетворять действующей политике: 10..200 Unicode-символов, корректный UTF-8, хотя бы одна строчная и заглавная латинская буква, цифра и ASCII спецсимвол, без email local-part без учёта регистра при его длине от 4 bytes. После успеха SHALL предлагаться обычный вход новым паролем с сохранённым безопасным локальным return URL. Она SHALL NOT принимать роль/user ID из формы как полномочие, менять чужой email, создавать пользователя, заменять установленный hash или снимать блокировку.

#### Scenario: Две формы первого пароля
- **WHEN** две независимые browser session одновременно задают разные допустимые пароли одной ожидающей записи
- **THEN** только одна операция устанавливает пароль и создаёт событие; вторая сообщает, что пароль уже задан, и предлагает вход/восстановление, не перезаписывая hash

#### Scenario: Блокировка или ошибка формы
- **WHEN** запись заблокирована после открытия формы, пароль недопустим, CSRF недействителен или email подменён на запись с паролем
- **THEN** пароль, роли и статус остаются прежними; ошибка пароля допускает исправление, invalid CSRF даёт 400

### Requirement: Самостоятельный запрос восстановления

Пользователь SHALL иметь возможность ввести email и выбрать «Забыли пароль?» без входа в систему. Для разрешённой активной записи с установленным паролем система SHALL атомарно создать recovery-запрос и намерение отправить инструкцию на сохранённый email этой записи. Ответ SHALL говорить «Если для этого email доступно восстановление, вы получите письмо с инструкцией» для существующего, отсутствующего, ожидающего первого пароля и заблокированного адреса. В последних трёх случаях письмо и recovery-разрешение SHALL NOT создаваться. Запрос SHALL NOT сбрасывать текущий пароль, закрывать сессии, разблокировать пользователя или менять роли.

#### Scenario: Письмо восстановления
- **WHEN** пользователь с забытым паролем отправляет допустимый запрос
- **THEN** после commit система отправляет письмо с инструкцией, одноразовой ссылкой на портал, сроком действия 30 минут и пояснением игнорировать незапрошенное письмо; пароль в письмо не включается

#### Scenario: Отсутствие доступа
- **WHEN** восстановление запрошено для неизвестного, заблокированного или ещё не задавшего пароль пользователя
- **THEN** возвращается тот же нейтральный ответ без recovery-разрешения/письма, изменения identity или ослабления блокировки

#### Scenario: Защита от потока запросов
- **WHEN** повторный запрос поступает менее чем через 60 секунд либо превышены 3 письма за скользящие 15 минут на normalized email или 30 запросов за 15 минут на доверенно определённый адрес клиента
- **THEN** новые письма/разрешения не создаются, сохраняется нейтральный ответ и существующие credentials/сессии не блокируются; лимиты применяются атомарно и не зависят от наличия записи

#### Scenario: Повтор формы и новый запрос
- **WHEN** повторно отправлена та же форма запроса либо пользователь позже создаёт новый допустимый запрос
- **THEN** повтор той же формы SHALL не создавать второе письмо/разрешение; новый запрос в пределах лимита SHALL сохранять пригодность ранее доставленной неиспользованной ссылки до expiry или успешной смены пароля

### Requirement: Одноразовое применение восстановления

Ссылка SHALL быть непредсказуемой, ограниченной одной identity/email и текущей версией credentials, с истечением ровно через 30 минут после создания запроса. Открытие ссылки SHALL только показывать форму и не расходовать её. Установка нового пароля SHALL требовать действующую ссылку, разрешённый статус, CSRF и соблюдение общей политики пароля. Успешная транзакция SHALL заменить hash, увеличить session version, погасить все незавершённые recovery-разрешения этой identity и сохранить событие смены пароля без секретов. После успеха SHALL предлагаться обычный вход без автоматического входа. Права SHALL оставаться прежними.

#### Scenario: Успешная смена
- **WHEN** пользователь открывает полученную ссылку и сохраняет новый допустимый пароль с подтверждением
- **THEN** новый пароль позволяет войти, прежний пароль и прежние сессии больше не принимаются, роли и блокировки не расширяются, повторное применение ссылки не меняет ничего

#### Scenario: Открытие почтовым сканером
- **WHEN** ссылка открыта GET/HEAD до пользователя почтовым сканером
- **THEN** пароль/сессии/разрешение не меняются, пользователь всё ещё может завершить восстановление

#### Scenario: Просрочка и повтор
- **WHEN** применена ссылка в момент expiry или позже, поддельная/использованная ссылка, ссылка после смены email/credentials/session version либо для заблокированной записи
- **THEN** показана общая ошибка недействительной ссылки с возможностью запросить восстановление заново; hash/roles/status остаются прежними

#### Scenario: Конкурентные смена пароля и блокировка
- **WHEN** одновременно применяются две действующие recovery-ссылки либо выполняется блокировка пользователя
- **THEN** максимум одна смена пароля проходит на прежней версии; блокировка не снимается, а её commit закрывает дальнейший вход; не появляется частичных изменений credentials/audit

### Requirement: Доставка и секреты восстановления

Письма SHALL доставляться только после commit на сохранённый получательский адрес с повторной проверкой пригодности запроса перед отправкой. Recovery-ссылка SHALL строиться из доверенной конфигурации HTTPS origin, а не пользовательского Host/Forwarded. Raw recovery token SHALL NOT сохраняться в открытом виде в DB, outbox, audit, логах, telemetry или operator output. Страницы восстановления SHALL не кэшироваться, не загружать сторонние ресурсы и не передавать токен через Referer. Неопределённый результат SMTP SHALL NOT называться доставкой; plaintext secrets SHALL оставаться вне диагностики. Отказ SMTP SHALL NOT затрагивать действующий пароль или обычный вход.

#### Scenario: Отказ и повтор доставки
- **WHEN** доставка временно не проходит или SMTP acceptance неизвестен
- **THEN** сохраняется безопасный результат доставки, UI не утверждает «письмо доставлено», worker не выполняет повторно смену пароля; повтор использует тот же запрос/срок, не выпускает новое разрешение, а неизвестная доставка не повторяется автоматически

#### Scenario: Смена состояния до отправки
- **WHEN** пользователь заблокирован, email/версия credentials изменены или срок ссылки истёк до dispatch
- **THEN** сообщение не отправляется, запрос не может восстановить доступ, history остаётся доступной без токена

### Requirement: Переход без приглашений и потери истории

Новый runtime SHALL прекратить создание/перевыпуск/активацию приглашений через все опубликованные входы. Старые ссылки SHALL показывать, что способ входа изменился, и вести к обычному вводу email без изменения credentials. Миграция SHALL сохранить пользователей, ID, роли, password hashes, блокировки, legacy identity links и append-only историю. Разрешённые invited/pending_invitation записи с null hash SHALL перейти в ожидание первого пароля; активные записи с hash SHALL сохранить обычный вход. Несогласованные данные SHALL останавливать миграцию с безопасной диагностикой, без угадывания статуса и массовой активации. Повтор миграции SHALL не дублировать историю.

#### Scenario: Действующая и ожидающая записи
- **WHEN** мигрируют активный пользователь с hash, ожидающий приглашение без hash и заблокированный пользователь
- **THEN** первый входит прежним паролем, второй задаёт первый пароль на портале, третий остаётся заблокирован; старые invitation rows и остальные исторические факты сохранены

#### Scenario: Старый интерфейс или endpoint
- **WHEN** вызывается сохранённая форма выпуска/перевыпуска/активации приглашения после перехода
- **THEN** новый invitation или пароль через старый механизм не создаётся; UI администратора предлагает «Добавить пользователя» и больше не показывает секретные коды/ссылки приглашения

### Requirement: Границы формы и отказоустойчивость

Все команды SHALL использовать POST, текущий CSRF, ограничения scalar/body и защищённое хранение сессий Yii. Неправильный HTTP method SHALL давать 405, invalid CSRF — 400, infrastructure failure — безопасный 503 без SQL/секретов. GET/HEAD SHALL не менять доменные факты. Обычные login/logout, возврат на безопасный локальный экран, проверка текущих прав и блокировка сессий SHALL сохранять текущие гарантии. Отказ транзакции SHALL откатывать весь набор связанных фактов; сбой доставки HTTP/session после commit SHALL не подтверждать ложный успех и не давать возможности перезаписи первого пароля.

#### Scenario: Прямой запрос и сбой записи
- **WHEN** команда вызвана без формы с поддельными actor/role fields либо запись credentials/audit/outbox завершается ошибкой
- **THEN** полномочия не расширяются, частичные domain facts отсутствуют, безопасная ошибка не содержит password/token/hash; после неизвестного клиенту commit первый пароль всё равно нельзя установить второй раз

## Public application and HTTP contract

Один владелец — `YiiUserAccess`. Публичные команды:

| Команда | Результат |
| --- | --- |
| `provision(actorId,email,fullName)` | `created` с userId; `invalid`/`access_denied` без фактов |
| `setInitialPassword(email,password,confirmation)` | `activated`; `invalid_password` с безопасной причиной; `invalid` для неподходящей identity |
| `requestPasswordReset(email,requestId,clientAddress)` | `accepted` для всех eligible/ineligible/rate-limited requests без выдачи token; infrastructure failure throws |
| `resetPassword(token,password,confirmation)` | `changed`; `invalid_password`; `invalid` |
| `passwordReset(token)` | `[email => normalizedEmail]` только для пригодного grant, иначе null; read не меняет grant |
| `deliverPasswordReset(requestId,sender)` | safe transport outcome; sender получает `to,subject,html,text` только в памяти; callback вызывается после commit и повторной eligibility проверки |

HTTP: GET/HEAD/POST `/pilot/login` (и существующий `/pilot/otiz/login`), POST `/pilot/first-password`, GET/HEAD/POST `/pilot/forgot-password`, GET/HEAD/POST `/pilot/reset-password`, POST `/pilot/admin/users/create`. Общие формы используют `email`, `password`, `passwordConfirmation`, `_csrf`; reset POST — ещё `token`, request POST — session-bound `requestId`. Неподдерживаемые методы новых маршрутов дают 405 через route fallback, а не случайный 404.

`/pilot/login` email step возвращает 200; ожидающая identity получает form action `/pilot/first-password`; известная active identity — обычную password form. Первый пароль success: 200 с «Пароль установлен» и ссылкой `/pilot/login`, без auto-login; invalid_password: 200 с role=alert и формой; неподходящее состояние: 200 с нейтральной ошибкой и входом/recovery. Forgot GET: 200 form; POST: 200 нейтральный результат. Reset GET: 200 form либо generic invalid link; POST success: 200 «Пароль изменён» и обычный вход; invalid password/link: 200 actionable error. Admin create success/invalid возвращает 303 `/pilot/admin/users` с notice/error и сохранёнными несекретными полями; access_denied403. Старые invite/reissue POST:410 без mutation; `/pilot/activate` GET/HEAD/POST:200 страница перехода без формы пароля (POST всё ещё требует CSRF). Normal mutation GET405, invalid CSRF400, инфраструктура503.

Первый пароль и reset используют Argon2id как текущий контракт; успешный обычный login не меняет hash. Production clock — UTC database time. Для deterministic tests owner принимает callable `passwordClock` (UTC DateTimeImmutable) и `passwordRecoveryConfig` с внешним envelope key/keyId и HTTPS public base URL; config неизменяем после создания owner и не поступает из HTTP. Непригодная production recovery-конфигурация даёт 503 для всех recovery-запросов без влияния на initial/normal login. Параметры среды: `FMONITOR_PASSWORD_RECOVERY_KEY` (base64 32 bytes), `FMONITOR_PASSWORD_RECOVERY_KEY_ID` (bounded identifier), существующие SMTP и `FMONITOR_PUBLIC_BASE_URL`. Настоящие значения не коммитятся.

Request ID — UUID v4 с привязкой к browser session на HTTP границе, или уникальный command ID при прямом вызове owner. Same request ID с тем же normalized email идемпотентен; с другим email не выпускает нового разрешения. Лимиты 3/15m означают: первый, второй и третий допустимы с интервалом не менее60s; четвёртый подавляется; граница expiry t+1800s уже недействительна. Email/IP quota проверяется в одной атомарной операции. Без configured trusted proxy clientAddress берётся из REMOTE_ADDR. Fake Forwarded/X-Forwarded-For не обходит лимит.

Audit persistence (таблицы, имена колонок и схема обязаны войти в canonical migration contract до implementation): `fm2_password_events` хранит user_id, action (`user_provisioned`, `initial_password_set`, `password_reset_requested`, `password_reset`), actor_user_id nullable и occurred_at_utc без secret. `fm2_password_recovery_requests` хранит request_id, user_id, email_normalized, session_version, token_hash, encrypted_token, key_id, created_at_utc, expires_at_utc, used_at_utc/revoked_at_utc и delivery outcome; готовое письмо/URL не хранится. Таблица квот — `fm2_password_recovery_attempts`, отдельная от password login attempts; она хранит request ID, normalized email, bounded client identity и время, не создаёт фиктивных пользователей. Outbox payload: только requestId, template `password-recovery`, version1 и email channel. Событие намерения `password-recovery/v1/{requestId}`.

Initial bootstrap superadministrator с уже установленным password остаётся активным и не переводится в pending_password. Legacy engineer import создаёт pending_password + null credential; прямые retired PilotHttp adapters не становятся production routes. Старые данные invited/pending_invitation нормализуются новым migration, а исторические миграции остаются воспроизводимыми.

## Independent examples and acceptance map

- Admin9101 с exact access.administer, default user role9210; ` New.Person@SHLZ.RU ` → одна pending_password identity, одна role9210, null hash, ноль invitations/outbox.
- `Another password 2026!` и совпадающий повтор устанавливают первый password; повтор с `Replacement password 2026!` не заменяет hash.
- UTC2026-11-24T12:00:00Z request, expiry12:30:00Z: 12:29:59Z valid, 12:30:00Z invalid. Clock не берётся из request.
- Reset при session_version7 даёт8, предыдущие cookies и все grants версии7 недействительны; roles до/после идентичны.
- Tests группируются: HTTP/provision/initial/RBAC; recovery/application/transport/limits/concurrency; canonical migration/restore/inventory; browser/keyboard/narrow. Characterization сохраняет ordinary auth, roles/status, initial owner и weekly mail policies.

Уточнение baseline 2026-11-24: действующая политика изменена решением `docs/operations/control-search-password-20261029.md`; старое 14-byte описание YII2-USER-ACCESS-001 устарело. Новый контракт сохраняет 10..200 Unicode-символов/4 группы и точные bytes без trim. Вход обязан принимать успешно установленный по этой политике пароль, включая UTF-8; существующие hashes не переоцениваются по composition rules при login.

Для проверки production delivery composition `JobHandlerRuntime::handle(job,config,documentSync=null,emailSender=null)` принимает необязательный network sender; production передаёт null и использует SMTP. Sender не выбирается HTTP-полями/окружением пользователя. Это test port, не альтернативный writer. Recovery unknown outcome хранится терминально и не повторяется при replay worker.

## Runtime configuration and recovery persistence qualification

Production `manage check-config` SHALL принимать recovery key/keyId как данные из приватного env без печати секретов. Compose SHALL передавать одинаковые key/keyId/trusted HTTPS origin в php и jobs-worker; nginx/db их не получают. Существующие SMTP настройки доходят до worker; отсутствующие recovery-настройки не блокируют развёртывание обычного входа, recovery сообщает503. Ни test sender, ни подмена recipient не включаются в production HTTP.

V40 использует три новые таблицы и две новые последовательности: events (`id` auto), requests (`request_id` primary UUID, без auto), attempts (`id` auto). Audit и attempts имеют поля из public contract; requests дополнительно `delivery_state` с исходным pending. Backup/restore SHALL сохранять все bytes этих таблиц, включая ciphertext, keyId, expiry, terminal unknown delivery и sequence gaps. Утрата внешнего envelope key после restore не даёт plaintext fallback или отправки старой ссылки.

## Canonical schema qualification

Переход SHALL быть additive: canonical enum `pending_invitation,invited,pending_password,active,blocked` сохраняет представимость исторических/fixture значений. Штатный first-password writer допускает только pending_password; исторические значения не оживляют старую активацию. Известное промежуточное состояние additive enum + subset новых таблиц SHALL безопасно завершаться повторным запуском с нормализацией оставшихся ожиданий и без потери фактов.

Готовность и миграция SHALL проверять типы, nullable/default/extra, engine/collation колонок и таблиц, уникальность/состав индексов и FK semantics; совпадения имён недостаточно. Несовместимый fingerprint SHALL отвергаться до исправлений/DDL, а не автоматически ремонтироваться. Regression matrix вводит повреждения token width, audit type/nullability/sequence, delivery default, engine/table+column collation, unique index и FK.

## Recovery integrity qualification

Публичный активный YiiUserAccess SHALL отвергать invite/reissue/activate результатом invalid и invitation — null без фактов, даже когда исторические enum значения и invitation rows представимы. Исторические adapter classes остаются только oracle, а не доступным способом создания credentials нового runtime.

Encrypted envelope SHALL использовать libsodium authenticated encryption с associated data requestId/userId/email/sessionVersion/keyId; перед отправкой проверяются stored keyId и SHA-256 расшифрованного токена против token_hash. Перестановка ciphertext между корректными grants, изменение keyId/requestId/token digest SHALL не вызывать sender. Production clock SHALL читать UTC на той же DB connection; created_at и expiry основаны на одном снимке времени. Env-built recovery без готового SMTP SHALL возвращать503 для восстановления даже при правильных key/origin, сохраняя обычный/первый вход; injected test config не требует liveSMTP. Production dispatch SHALL принимать только channel=email/template=password-recovery/version1.

### Final qualification: delivery and input boundaries

Перед network send recovery owner SHALL durable-claim попытку: конкурентная доставка и сбой/неизвестный результат после вызова транспорта SHALL NOT автоматически отправлять письмо повторно. Неопределённый исход хранится как unknown без envelope; заведомо transient outcome может сохранить прежнюю envelope для retry с прежним token/expiry. Повтор одного HTTP POST остаётся нейтральным/idempotent, а новая форма в той же сессии получает новую identity команды. Forgot GET/POST имеют no-store и no-referrer. Pending directory не показывает устаревшую invitation validity.

Oversized/непригодные email и client identity на public recovery command SHALL возвращать нейтральный ответ с ограниченным представлением quota attempt без storage error; email, не представимый в canonical254-character поле, не допускает grant. Recovery config принимает только HTTPS origin (host и optional port; завершающий slash нормализуется), без userinfo/path/query/fragment. Recovery key/keyId передаются только PHP и jobs-worker. SMTP tuple передаётся PHP, jobs-worker и jobs-scheduler: scheduler проверяет SMTP для включения outbox/weekly scheduling. Prepare/migrate/startup-check/local-integration/web/db этих секретов не получают. Оба published Compose и canonical local-runtime template/env parser поддерживают recovery configuration.
