# Текущая цель — полная доставка распоряжений и рекламаций

## Активное поручение26.11.2026
Владелец снял паузу локального показа и явно разрешил все необходимые коммиты, PR/CI corrections, штатный merge и deployment merged-версии на домашний стенд. Цель активна через create_goal. Рабочий delivery worktree: `/Users/antropophag/code/fmonitor-2-simplify-order-local-preview`; navigation read-only. Весь накопленный WIP, включая migrations42/43, входит в поставку. Приведённые ниже паузы — сохранённая история, не текущий запрет. Актуальные source/PR/CI получать через harness state; review records и delivery record сохраняют evidence. Полная локальная suite запрещена; независимые reviews и exact-source CI обязательны.

Поручение владельца 25.11.2026: сначала быстро реализовать и показать на локальном стенде компактный норматив и новый процесс распоряжения, до гейтов и полного CI. Это task-specific разрешение на локальный preview; merge, production deploy и GREEN не следуют из него.

Scope и критерии: [ORDER-LOCAL-PREVIEW-001](../../specs/ORDER-LOCAL-PREVIEW-001.md). Root — scope/tests и стенд; отдельный sol/low order_preview_executor — реализация. Только bounded checks. Гейты, независимое final review, матрица полного CI и окончательное обновление соседних контрактов отложены до обратной связи владельца, не одобрены заочно. Предыдущая очередь сохранена в openspec/changes/simplify-order-local-preview/previous-delivery-goal.md.

Локальный показ выполнен: [delivery record](order-local-preview-20261125.md). Сейчас пауза до отзыва владельца; не начинать CI/merge автоматически. Стенд8094 работает.

Получена обратная связь: дату начала работ выбирать до формирования PDF и переносить в загрузку оригинала. Выполняется bounded correction того же preview; полная интеграция всё ещё не разрешена автоматически.

Уточнение даты начала реализовано и проверено HTTP/PDF + actual browser; снова пауза до ручного отзыва. История проверки — в delivery record.

Новый отзыв владельца: сгруппировать управление составом, шаблоном и оригиналом. Выполняется presentation correction на8094 в том же worktree; root критерии/QA, отдельный executor UI.

Группировка экрана завершена и проверена desktop/393px, dirty/save state и реальное скачивание нового PDF. Стенд8094 оставлен для ручного просмотра; пауза до отзыва.

По отзыву владельца удалён нормативный блок из шаблона распоряжения; PDF проверен, образецФН312 на8094 переформирован. Пауза до ручного отзыва.

В шаблон добавлена отдельная колонка фактической даты начала после плановой; выбранная дата26.08 сохранена в актуальном образцеФН312. Проверки PDF/HTML/HTTP passed, пауза до отзыва.

Исправлено форматирование PDF: центр/middle в таблице, пустой официальный номер над приложением, служебныйФН вfooter, естественный поток4→5. ФН312 переформирован и положен вDownloads, пауза до отзыва.

Уточнено единое физическое положениеФН вfooter всех страниц, провереноbbox/render; обновлённый PDF вDownloads. Пауза до отзыва.

Новый отзыв owner: компактная форма на один экран13–15дюймов, без гигантского заголовка. Root scope/QA, отдельный order_preview_executor CSS/view; толькоlocalpreview.

Компактная форма готова: проверены1366×768,1440×900 и1366×650 с3 монтажниками; вся форма видна. Mobile393px сохранён. Стенд8094 открыт для отзыва; пауза.

Новый отзыв owner: компактно переоформить загрузку оригинала, убрать дубли ФИО, применитьdesign skill. Root критерии/QA, order_preview_executor view/CSS; тот жеlocalpreview.

Компактная загрузка/исправление оригинала готова: ФИО один раз, состав слева и файл/реквизиты справа. Проверены1366×650,1440×900 и393×852; owner original сохранён без submit. Стенд8094 открыт, пауза до отзыва.

Новый отзыв: сломана вёрстка history ФН312. Root воспроизвёл48px icon-column mismatch; bounded presentation correction original-history.php/CSS выполняет отдельный order_preview_executor. Толькоlocalpreview, данные редакций не меняются.

История оригинала исправлена и проверена1366×650/393×852, focusedHTTP PASS. Стенд8094 оставлен наhistoryФН312; пауза до отзыва.

Owner уточнил: вhistory нужен штатный форматный элемент файла. Выполняется bounded замена ссылки на публичный SHLZ File Row; root scope/QA, отдельный executor view/CSS.

Вhistory использован штатный SHLZ File Row; подтверждено1366×650/393×852. Стенд открыт, пауза до отзыва.

Owner просит понятные подписи: реальный автор загрузки вместо ID, явное действие исправления. Root HTTP regression/scope; отдельный executor query enrichment/view. Толькоpreview.

Owner оспорил сам workflow исправления оригинала, ожидает новое распоряжение взамен. Семантические изменения/переименование ссылки приостановлены до уточнения обработки ошибочного скана; независимая правка имени загрузившего продолжается.

Owner разрешил заменять ошибочный скан. Выполняется разделение scan-only replacement и NEW_ORDER, защита неизменных реквизитов; root regression/spec, отдельный executor implementation. Всё ещёlocalpreview.

Имя загрузившего и scan-only replacement готовы: protected реквизиты, явное новое распоряжение, focusedHTTP/architecture/syntax passed, browser1366×650/393×852. HistoryФН312 открыта; selection сейчасpendingФН314. Пауза до отзыва; гейты/CI отложены.

Новый owner scope: по Desktop screenshot22.52.33 исправить стык actions/pending оригинала в Documents; убрать принятие оригинала из пользовательского языка (только загрузка), пройти весь пользовательский UI/справку на лишнюю техническую диагностику. Диагностика допустима вadmin; meaningful ошибки/несохранённая работа остаются. Root scope/QA, executor implementation, отдельный sol/low read-only copy audit. Localpreview, прежний deferCI.

Очистка usercopy/отступов иadminизоляция диагностики готовы; help обновлён. FocusedHTTP/access/syntax/visualcontracts passed; nativeAX текстыподтвердил, финальнаяпиксельнаяQA ограниченаустаревшимcompositor/noWindowsAvailable. Стенд8094 сохранён, CI/review по-прежнемуотложены; пауза доотзыва.

Прямой вход «Новое распоряжение» из Documents готов; при pending — «Продолжить подготовку». Root bounded navigation fix, render smoke3states/lint/diff passed. Пауза до отзыва.

Новый owner screenshot23.28.56: переработать Сроки и готовность, устранить смешение план/факт и висящую кнопку справок. Root scope/visual assessment/mechanical QA, отдельный executor view/scopedCSS. Толькоpresentation/localpreview.

Вкладка Сроки и готовность переработана: План/Факт, отдельный перенос с кнопкой, группыготовности. Nativebrowser1366×768/393×852, render3states, detector/lint/diff passed. Новоеотдельноеокно8094 открыто; пауза доотзыва.

26.11.2026: активная задача — рекламации оборудования, номер+дни, несколько наобъект. Owner явно подтвердил сумму дней СВЕРХ даты справки, иначе сверхнорматива. Spec RECLAMATION-DELAY-001; root tests/scope, отдельные sol/low storageUI иfinance исполнители. RED unit/owner записаны; service403 прервал выполнение, owner resume/продолжи возобновил. Старые расчёты не переписываются. Тотжеlocalpreview; Gate3/final review/fullCI доpublication остаютсяотложенными.

Рекламации реализованы и показаны на8094: несколько номеров/дней, сумма поверх справки/норматива, финансовые snapshots сохранены. Focused ledger/HTTP/math/native inputs/freshness/workbook/training checks PASS; advisory review без подтверждённых блокеров, native desktop/393px проверен. Миграция43 применена только локальной копии. Пауза до отзыва; formal Gate3/final/CI и прежний harness protected-owner blocker остаются незавершёнными.

## Новое поручение владельца — полная доставка в новой сессии
26.11.2026 владелец поручил оформить все накопленные правки этой сессии в PR, провести через CI до merge и выложить на домашний стенд, установить цель и работать автономно до завершения. Исполнение начать в новой сессии; текущая готовит только handoff. Прежняя пауза до разрешения CI/merge снята, но обязательные Gate3/final review, защита ветки, корректное ownership и запрет локальных полных make test/verify сохраняются.
Продолжать существующее worktree `/Users/antropophag/code/fmonitor-2-simplify-order-local-preview`, branch `codex/simplify-order-local-preview`, сохранить весь tracked/untracked WIP. Детальный переходной контекст: `/Users/antropophag/.local/share/fmonitor-2/order-local-preview-20261125/handoff-next-session.md`. Новая сессия должна создать goal и довести PR/CI/merge/home deployment до проверенного результата без повторного общего запроса разрешения.
