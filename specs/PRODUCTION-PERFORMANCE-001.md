# PRODUCTION-PERFORMANCE-001

## Простыми словами

Исправляем все F01–F12 аудита 14 ноября: страницы не повторяют одну работу сотни раз, тяжёлое чтение не задерживает другую вкладку, фото показываются миниатюрами, а скрытые формы не раздувают страницу. Суммы, права, оригиналы и append-only история сохраняются. Владелец 15 ноября разрешил production-first с последующим прохождением гейтов/CI до merge.

## Public seams and inherited invariants

Существующие Yii GET/HEAD, формы и сообщения service worker. Финансовые/кадровые команды, CSRF, user/cache isolation, audit, expected revisions и stable operation/request IDs наследуют текущие contracts. Оптимизация чтения не даёт права писать и не кеширует approval для следующей команды. Все неизвестные данные сохраняют прежнее fail-closed поведение. Runtime derivatives не являются доказательством и могут быть восстановлены из оригинала; миграции бизнес-БД и изменение оригиналов не входят.

## Acceptance

### A01 — F01 repeated admission

Для одного HTTP GET реестра выплат каждый объект проверяется максимум один раз независимо от числа строк, с сохранением первого blocking reason, порядка и всех сумм. Следующий HTTP GET выполняет свежую проверку. Финансовые команды MUST проверять текущий допуск в своей транзакции, без этого request memo. Пример: rows [A,B], [A,B], [B,C] вызывают reader A/B/C по одному разу; blocked B блокирует соответствующие rows как прежде.

### A02 — F02 bounded prefetch

init + начальный pageshow + online burst MUST не дублировать pending/in-flight URL. Service worker SHALL иметь максимум два спекулятивных сетевых запроса одновременно; страницы в очереди обслуживаются после foreground navigation. pagehide/новый scope отменяет ожидающие warmups. Повторная попытка после ошибки возможна. Logout/user switch/generation invalidates queue, а запоздалый ответ старого пользователя не попадает в кеш нового. Существующая изоляция документов и проверка authenticated response сохраняются. Пример: 50 URL дважды → не более 50 warmup fetch при одном поколении, max active=2.

### A03 — F03 protected photo thumbnail

GET/HEAD существующего оригинала остаётся побайтово прежним. Отдельный authenticated thumbnail representation имеет тот же object/photo access/revocation guard, Cache-Control private/no-store и не раскрывает storage path. Максимальная сторона 640px, без upscale, с сохранением пропорций и видимой ориентации оригинала (включая EXIF1–8 повороты/отражения в JPEG/PNG/WebP); JPEG quality82, PNG/WebP поддерживаются через GD. Недопустимый/слишком большой для bounded decode raster даёт безопасный отказ, а не публичный original fallback. Derivative atomically cached privately по source digest и renderer version; original SHA/bytes неизменны. На странице img получает thumbnail и lazy/async + размеры; скрытая галерея не запрашивает original, открытие изображения ведёт к original. Один ресурс не создаётся повторно текущим render cycle. Фото локальной очереди используют прежний Blob до подтверждения.

### A04 — F04 read-only session handoff

Выбранные read-only routes: `/pilot/otiz/payments`, `/pilot/installers`, `/pilot/installers/{tabId}`, `/pilot/objects/{id}`. На GET и поддерживаемых этими routes HEAD после authentication/authorization и подготовки identity/CSRF файловая сессия SHALL закрываться до тяжёлого чтения. Дальнейший render не открывает её заново. Login/logout/POST/flash/recovery остаются штатными writers. Нельзя вернуть успех при ошибке session persistence. Два read-only запроса с одной cookie не сериализуются на длительности backend read; token в отданной форме остаётся действительным для последующего разрешённого POST.

### A05 — F05 dismissed card

Карточка существующего delivered dismissed монтажника возвращает 200 и его настоящий статус/историю. employed и missing_from_delivery не пропадают из-за фильтра кадрового статуса. Неизвестный ID остаётся 404; правила авторизации прежние.

### A06 — F06 batch current status

Карточка объекта получает статусы всех уникальных tabId одним ограниченным чтением workforce_catalog, без проекции всех назначений и итогов каталога. Дубликаты не добавляют SQL. Отсутствующий/невалидный обязательный статус сохраняет прежний безопасный отказ.

### A07 — F07 scoped utilization

Card(tabId) и compact(tabIds) SHALL ограничивать каталог и связанные assignments запрошенными идентичностями, с пустым результатом без общего сканирования для пустого списка. Related originals читаются пакетно, без одного запроса на assignment. Текущие/предстоящие/исторические назначения и sorting остаются прежними; relevant malformed evidence fail-closed, доказанно нерелевантные SQL-excluded rows не блокируют scoped card. Общий forecast/observation сохраняет прежний полный контур проверки.

### A08 — F08 bounded financial editors

Редакторы решения и удержания переиспользуются: максимум один dialog каждого типа независимо от количества объектов/получателей. Остальные confirm/chooser dialogs имеют фиксированное число по типам. Поля/action/CSRF/revision/version/request ID относятся к выбранной паре; открытие B после A не переносит значения A. Предпросмотр и ошибка восстанавливаются только в своём контексте, неизвестный результат не выдаётся за успех; keyboard/Escape/opener focus/scroll остаются рабочими. На initial HTML отсутствуют N однотипных финансовых forms.

### A09 — F09 bounded calendar

Initial calendar renders не более42 date columns с отдельной agenda выбранной даты; остальной допустимый прежний диапазон доступен навигацией окна/прямой ссылкой. Default window начинается не позже текущей даты; selected date видима. GET date fallback/HEAD/validation/authorized scope сохраняются. JS day navigation обновляет agenda/window/URL без полной document navigation; Back/Forward, keyboard и stale-response cancellation корректны; сетевой отказ оставляет рабочий GET fallback. Данные за пределами визуального окна не теряются и не подменяются.

### A10 — F10 bounded user administration

GET users q/status/role/page ищет во всём допустимом каталоге до pagination, initial не более25 пользователей и без повторения role/legacy-link форм для каждого. One authenticated on-demand user editor сохраняет текущие role/status/legacy-link contracts, superadmin guards и CSRF. Сервер строит editor для exact user с no-store; запрет и unknown user не выдают данные. Counts отражают весь каталог/отбор, не страницу. Invite flow работает; no-JS editor/fallback доступен. После POST пользователь может вернуться к целевой строке/редактору без произвольного redirect.

### A11 — F11 upload feedback

PDF/photo показывает передачу bytes/percent при lengthComputable и отдельный этап ожидания server receipt. Success только по существующему validated accepted/replayed receipt. Network/timeout/abort после начала передачи означает unknown/retryable, сохраняет точный requestId/clientOperationId и Blob/metadata для повтора. Прогресс не включается в immutable operation fingerprint. Повтор после lost response не создаёт второй факт; confirmed invalid intent/change следует прежним правилам ID. Поддерживаются offline photo queue и desktop/mobile live region.

### A12 — F12 public asset freshness

Все изменяемые публичные CSS/JS получают content-derived version при выдаче; одинаковые bytes дают одинаковую версию, изменённые bytes — новую. Только exact matching version получает long immutable cache; unversioned/mismatched URL не маскируется immutable. Module dependencies получают согласованное обновление через существующий public asset boundary. Удаляется регистрация preloader без markup. Private HTML/photo/documents остаются nonpublic/no-store; allowlist, MIME, HEAD, CSP и offline generation сохранены. Offline shell uses generation-scoped Cache Storage; install MUST bypass HTTP cache with `cache: no-store`, so unversioned internal shell keys cannot seed an old body into a new generation. Новый runtime воспроизводимо включает GD; требуется реальный thumbnail probe перед production success.

## Verification and delivery

Root-owned focused executable tests плюс существующие HTTP/browser/offline/auth/financial/photo regressions покрывают A01–A12. RED evidence и owner-deferred sequence записываются честно. Production CAS/rollback сохраняет unrelated source. После выкладки live repeat audit без business writes, independent final and planner-required review, exact-source CI then merge. До окончательного GREEN все непроверенные обязательства остаются UNKNOWN.
