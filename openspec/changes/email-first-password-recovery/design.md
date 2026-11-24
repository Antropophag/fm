## Context

Мотивация и решения владельца — в [proposal.md](proposal.md). Матрица поведения — в [delta spec](specs/identity/email-first-password-recovery/spec.md). Это изменение чувствительной семантики доступа, а не bounded bug fix.

Текущие `AuthController`/`MariaDbYiiLocalIdentityStore` допускают вход только active identity с password hash. `YiiUserAccess` объединяет приглашение, активацию, управление ролями и блокировкой. Схема хранит invited/pending_invitation, credentials, invitations и session_version. Нельзя просто показать форму пароля вместо ссылки: старые endpoints, миграция, блокировка и конкурентные записи тоже должны перейти на новый контракт.

Общий outbox хранит только несекретные данные. `Jobs/SmtpTransport` сейчас проверяет `WeeklyFkrRecipientEligibility`; его непосредственное использование для восстановления закроет путь другим ролям. Настройки SMTP существуют, но пригодность live среды не проверена.

## Goals / Non-Goals

**Goals:**
- Один владелец изменений identity/credentials и проверок доступа; тонкие HTTP adapters.
- Одна связная доставка жизненного цикла пароля: подготовленный администратором пользователь → первый пароль → последующее восстановление.
- Изоляция recovery-разрешений от обычной сессии и старых приглашений; сохранение пользовательских ID и истории.

**Non-Goals:**
- Не вводить SSO, MFA, общую mail-платформу или новый RBAC.
- Не менять бизнес-процессы, обычную политику пароля, weekly FKR eligibility или глобальные outbox guarantees.
- Не использовать `rapid-pilot/` как writer. Старые adapters — только oracle/fixtures; активные Yii routes не должны их загружать.

## Decisions

### Один application owner

Владельцем остаётся IdentityAccess, публичным фасадом — `YiiUserAccess`, с командами предварительного создания (`provision`), первого пароля (`setInitialPassword`), запроса восстановления (`requestPasswordReset`) и завершения (`resetPassword`). Внутренние persistence-компоненты разделяются по этим операциям, но не становятся самостоятельными HTTP writers. Нормативный контракт перед Gate 2: `specs/EMAIL-FIRST-PASSWORD-RECOVERY-001.md`, согласованный с delta spec.

Предлагаемые HTTP seams: существующий `/pilot/login` с выбором шага; POST `/pilot/first-password`; GET/POST `/pilot/forgot-password`; GET/HEAD/POST `/pilot/reset-password`; POST `/pilot/admin/users/create`. Существующие login aliases используют тот же owner. Старая активация показывает страницу перехода, а старые invitation mutations отклоняются без побочных эффектов. Legacy owner provisioning, импорт и прямые CLI writers должны быть включены в inventory перехода, чтобы не продолжать выпускать invited-состояния в новом runtime.

Роли и полномочия проверяются текущим canonical RBAC внутри транзакции. Controller не пишет SQL. IdentityAccess разрешены существующая DB connection, clock/entropy/hash abstractions и порт append outbox intent. Jobs получает только bounded recipient/reset projection через application seam; не меняет роли/credentials напрямую. Architecture-check должен подтвердить эти направления, отсутствие новых доменных writers в HTTP и отсутствие rapid-pilot dependency.

Альтернатива с одной веткой в `AuthController` отвергнута: она не защищает повторный POST, конкурентный первый вход и остальные entrypoints.

### Состояния и атомарность

Вводится явное `pending_password`; null hash не даёт права обходить blocked/status0. Новая запись получает pending_password/status1. Первое сохранение блокирует строку identity, проверяет состояние/hash и атомарно создаёт hash+activation fact. Обычный вход применяется после установки пароля, без дополнительного механизма auto-login.

Блокировка допускается и для ожидающего первого пароля пользователя. Разблокировка такой записи возвращает pending_password, а записи с hash — active; обе операции повышают session_version и сохраняют audit. Это исключает случайную активацию null-hash записи. Last-superadministrator protection наследуется.

Reset фиксирует user ID, normalized email, session/credential generation и срок. Запрос восстановления не меняет credentials или generation. Несколько разрешённых запросов могут сосуществовать в пределах лимита: повторный запрос не обесценивает уже доставленное письмо. Успешный reset атомарно меняет hash, увеличивает session_version и погашает остальные разрешения. Блокировка и смена email также инвалидируют generation. Согласованный порядок блокировок: identity → credentials → recovery grants → audit/outbox. Две смены на прежней generation не могут пройти.

Простой перевод в active после unblock без проверки hash и использование invitation-token для recovery отвергнуты: они смешивают новые сценарии с прежней активацией.

### Ссылка и доставка восстановления

Письмо содержит ссылку на HTTPS портал с 32 случайными bytes в base64url, сроком 30 минут. Авторизация применения хранится как digest токена; первый вход обходится без такого токена. Старые invitation tokens никогда не принимаются как reset.

Для durable отправки создаётся отдельная защищённая delivery envelope в IdentityAccess: токен зашифрован authenticated encryption (libsodium), с key ID и associated data для request/user/generation. Ключ — отдельный внешний runtime secret, не credential DB и не session key. В generic outbox сохраняются только request ID/template/recipient identity, без URL, токена или ciphertext. Это сохраняет ограничение опубликованного transactional-outbox контракта. Шифртекст хранится лишь на срок разрешения/необходимой отправки; его удаление не удаляет audit/receipt. Отсутствие библиотеки/ключа блокирует recovery readiness безопасной ошибкой, а не включает plaintext fallback.

Dispatcher читает owner projection, повторно проверяет текущие статус/email/generation/expiry, раскрывает envelope в памяти и вызывает транспорт вне DB transaction. SMTP-ошибки/логи не включают тело письма. После SMTP acceptance статус отражает именно acceptance, не гарантированное попадание во входящие. Неизвестный ACK фиксируется как UNKNOWN_DELIVERY без автоматического повторения; новый пользовательский запрос разрешён в рамках лимита. При явной временной ошибке повтор использует тот же токен и исходный expiry. Exactly-once доставка не обещается.

Из текущего SMTP извлекается узкий wire sender или общий низкоуровневый порт, сохраняя отдельные recipient policies: weekly FKR — прежний, recovery — IdentityAccess. Переиспользуются SMTP TLS/config и queue/outbox lifecycle. Выключенная/непригодная SMTP-конфигурация не ломает первый и обычный вход, но recovery endpoint возвращает безопасную недоступность одинаково для всех email.

Альтернатива raw token в outbox нарушает secret boundary; синхронная отправка внутри password transaction удерживает блокировки и не решает неоднозначность SMTP.

### Защита форм и наблюдаемость

CSRF и защищённые Yii sessions наследуются; до входа показывается только введённый email, без ФИО/ролей. Все password forms допускают исправление без повторного вывода пароля. Новый UI использует существующие публичные shlz-ui exports и не меняет общий shell.

Reset pages используют no-store, Referrer-Policy: no-referrer, локальные assets. Access/proxy/application logs редактируют token/query на этих маршрутах; токен не попадает в analytics, error context, redirects или flash. GET/HEAD не погашают grant, чтобы почтовый сканер не расходовал ссылку. POST повторно проверяет grant под lock. Recovery request имеет session-bound request ID для дедупликации повторной отправки формы, общий нейтральный ответ и атомарные лимиты из spec. IP берётся только из доверенного runtime источника, не произвольных forwarded headers.

Audit разделяет административное создание, установку первого пароля, recovery request, password reset и delivery result. Неуспехи не создают credential/role facts; безопасные события защиты от перебора допустимы. Unknown/nonexistent emails не создают фиктивных identity facts. Матрица rate-limit tests использует управляемые clock и источник client address.

Рекомендации для recovery проверены по [OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html): одноразовый токен с ограниченным сроком, нейтральный ответ, отсутствие смены credentials до доказательства владения токеном, обычный вход после сброса. Первый вход по одному email — отдельное продуктовое решение и этим рекомендациям подтверждения личности не эквивалентен.

## Risks / Trade-offs

- [Захват первого пароля по известному email] → явно сохранить ограничение из диалога; не объявлять email подтверждённым. Владелец выбрал прямое назначение первого пароля после обсуждения риска. Скрытые SSO/письма/ИТСМ gates не добавлять.
- [Перечисление допущенных email первым шагом] → запрошенные разные экраны сохраняются, но без ФИО/ролей и с ограничением частоты. Нейтральное recovery-сообщение само по себе не устраняет этот продуктовый риск.
- [Утечка recovery URL в логах] → включить proxy/access log policy и тест искусственного token marker в полный acceptance scope; не ограничиваться PHP audit.
- [Сбой после commit до ответа] → owner гарантирует однократную установку; повтор не заменяет пароль, recovery остаётся доступным; UI не сообщает ложный успех.
- [Email-ошибка администратора] → получение recovery возможно только через сохранённый email; альтернативный адрес из формы не принимается.
- [Компрометация ключа envelope или backup] → отдельный секрет, limited TTL, metadata-only diagnostics, контроль наличия key ID при restore. При утрате ключа ожидающие запросы безопасно отзываются, не расшифровываются fallback-ом.

## Migration Plan

1. Root пишет нормативный контракт и inventory всех active identity writers, auth aliases, schema/readiness/backup consumers. Уточняет фактический frontier из source; номер миграции заранее не угадывается.
2. Добавить canonical migration для pending_password, recovery grants/envelopes и audit/rate-limit persistence; обновить schema fingerprint, fresh provisioning, upgrade, verification fixtures/table inventories, backup/restore manifest. Secrets остаются вне checkout. Архивные invitation tables сохраняются.
3. Preflight выявляет противоречивые status/hash/state без автоматического исправления. Разрешённые invited/pending_invitation с null hash переводятся в pending_password, active hashes и blocked остаются прежними. Все старые invitation links прекращают работу; существующие строки/события сохраняются, отзыв фиксируется без удаления истории. Повтор миграции идемпотентен.
4. Переключить Yii/admin/CLI/import writers, убрать invitation UI, согласовать нормативные auth/user-access contracts и продуктовые ссылки на приглашения. Исходные старые изменения/ревью не удалять.
5. Focused checks проверяют полный HTTP/browser flow, races/faults, upgrade/replay/restore и mail с fake transport/disposable SMTP capture. Live доставка без отдельного целевого адреса не выполняется; SMTP operational status остаётся UNKNOWN. Нужен один выбранный planner exact-source CI consumer, без локального полного make test/verify.
6. Перед реальным переключением отдельно проверить readiness ключа/SMTP/origin и redaction. В этой задаче planning не означает deploy. Обратный запуск старого writer после миграции запрещён: он возродит приглашения. При дефекте recovery можно временно закрыть его endpoint с явной недоступностью, сохраняя вход; восстановление данных — только согласованной процедурой, не destructive down migration.

## Open Questions

- Live SMTP, публичный HTTPS origin и управляемое хранение envelope secret требуют проверки в целевой среде; наличие переменных в примере не является доказательством. Это операционная готовность, не новая продуктовая развилка.
- URL ИТСМ не задан. До появления проверенного адреса показывать согласованный текст без выдуманной ссылки.

Планирование выполнено root; тесты, код, RED, независимые Gate 3/final reviews, CI и deployment ещё не выполнены. Новый binding будет сформирован только на этапе реализации для этой задачи, отдельно от прежнего Docker change. Root авторствует спецификации/тесты, отдельный gpt-5.6-sol/low — реализацию, независимый gpt-5.6-sol/low — planner-required reviews.
