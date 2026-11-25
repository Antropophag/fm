# Импорт адресного перечня

Команда `php bin/fmonitor2-address-register.php` поддерживает один профиль XLSX
«Адресный перечень». До работы должны быть выполнены canonical migrations v40,
настроены отдельный DB principal импорта, активный оператор ФКР и приватный каталог
`FMONITOR_IMPORT_STORAGE_ROOT` с режимом `0700` вне webroot и checkout.

Обязательная конфигурация процесса: существующие `FMONITOR_DB_HOST`,
`FMONITOR_DB_PORT`, `FMONITOR_DB_NAME`, `FMONITOR_DB_USER`,
`FMONITOR_DB_PASSWORD`, `FMONITOR_PROCESS_TABLE_PREFIX` и
`FMONITOR_LEGACY_TABLE_PREFIX`; дополнительно
`FMONITOR_IMPORT_OPERATOR_USER_ID`, `FMONITOR_IMPORT_DB_PRINCIPAL`,
`FMONITOR_IMPORT_TARGET`, `FMONITOR_IMPORT_STORAGE_ROOT` и общий для доверенных
dev/prod `FMONITOR_IMPORT_EVIDENCE_SECRET` длиной не меньше 32 bytes. Для новых
проверок адресов задаются серверные `FMONITOR_DADATA_API_KEY` и
`FMONITOR_DADATA_SECRET_KEY`. Значение `FMONITOR_IMPORT_DB_PRINCIPAL` должно
точно совпадать с `CURRENT_USER()` подключения; target в env и команде совпадают.

Сначала выполните бесплатную оценку:

```sh
php bin/fmonitor2-address-register.php estimate --file=/private/address.xlsx --year=2027 --contract=CONTRACT-1 --target=dev
```

Preview по умолчанию использует только пригодный кэш. Новые проверки ДаДата
разрешаются явным общим лимитом попыток:

```sh
php bin/fmonitor2-address-register.php preview --file=/private/address.xlsx --year=2027 --contract=CONTRACT-1 --target=dev --allow-paid-calls --max-paid-requests=700
```

Сохраните `packageId` и `previewSha256` из JSON. Apply публикует весь пакет одной
транзакцией и требует новый UUID операции:

```sh
php bin/fmonitor2-address-register.php apply --package=PACKAGE_UUID --preview-sha256=SHA256 --operation=OPERATION_UUID --target=dev
```

Для замены непустых текущих значений создайте новый preview с `--overwrite` и
непустым `--reason=...`. Пустые входные значения не очищают каталог. При
`PREVIEW_STALE` создайте новый preview; при неизвестном исходе не повторяйте
операцию с другим UUID, а сначала выполните report с прежним package. Для сверки по operation повторите
apply с теми же package, preview-sha256 и operation: подтверждённый commit
вернёт replay без повторных изменений.

`resume` продолжает подготовку. Увеличение бюджета требует `--budget-reason`.
`report` повторно выгружает приватные JSON/CSV. Для переноса уже оплаченного
address evidence dev → prod используйте `export-evidence` и `import-evidence`;
bundle подписывается `FMONITOR_IMPORT_EVIDENCE_SECRET`, не содержит credentials
или object IDs, а prod всё равно выполняет собственный preview БД.

Production apply выполняет оператор после backup, target-specific preview и
проверки отчёта. Само наличие пакета или кэша не является разрешением на apply.

Явное обновление устаревшего evidence и возобновление неизвестной оплаченной
попытки выполняются только с новым лимитом и причиной:

```sh
php bin/fmonitor2-address-register.php preview --file=/private/address.xlsx --year=2027 --contract=CONTRACT-1 --target=dev --refresh-addresses --allow-paid-calls --max-paid-requests=700
php bin/fmonitor2-address-register.php resume --package=PACKAGE_UUID --target=dev --retry-unknown --allow-paid-calls --max-paid-requests=701 --budget-reason='Повтор после неизвестного ответа'
```

Перенос кэша использует новый путь в приватном каталоге и не перезаписывает
существующий файл:

```sh
php bin/fmonitor2-address-register.php export-evidence --destination=/private/address-evidence.json --target=dev
php bin/fmonitor2-address-register.php import-evidence --file=/private/address-evidence.json --target=prod
php bin/fmonitor2-address-register.php preview --file=/private/address.xlsx --year=2027 --contract=CONTRACT-1 --target=prod
```

Exit `0` означает сохранённый успешный outcome; `2` — блокировку бюджета или
отклонённые данные; `64` — ошибку аргументов/config; `69` — недоступную БД или
provider; `70` — ошибку подтверждённого сохранения/экспорта; `75` — stale,
operation conflict или неизвестный commit; `77` — отказ полномочий; `78` —
несовместимую схему. При неизвестном commit (`75`) используйте report и повтор apply с теми же
package, preview-sha256 и operation; не создавайте новый UUID вслепую.
При PREVIEW_STALE нужен новый preview; OPERATION_CONFLICT требует сверки
параметров исходной операции, а не повторного использования её UUID. При `70` после успешного
apply каталог не откатывается: исправьте приватный каталог и повторите report.

Все действия доступны также как `php bin/fmonitor2-yii.php
address-register/<action> ...`; Yii передаёт ту же строгую argv-грамматику одному
CLI/application owner.

Инструмент рассчитан на штатные PHP256 MiB и MariaDB packet16 MiB.
Поддержанный предел — строка worksheet20000 включительно (19994 объекта
в строках7–20000). Отчёт сохраняет все21 импортируемое поле на объект, включая пустой вход
SKIPPED_EMPTY. Поднятие лимитов, усечение отчёта и удаление истории не являются
способами восстановления. Для подтверждённого rollback счётчики опубликованных
created/updated/filled/replaced равны0.

Разрешение на реальные clean/address запросы пока не получено; примеры платного
preview выше показывают синтаксис и сами по себе не разрешают расход. Проверки
инструмента выполняются с fake provider; реальные адреса ещё не проверены.

Разные регномера при общем UNOM, подъезде и подтверждённом доме импортируются
как самостоятельные лифты. UNOM и подъезд сохраняются как в источнике.
Точный повтор source identity блокирует пакет с DUPLICATE_SOURCE_OBJECT;
противоречащий регномер/текущая идентичность — с IDENTITY_CONFLICT.

## Профиль21 поля —26.11.2026

Используйте прежний полный XLSX, не удаляя и не сдвигая колонки. Загружаются
B–H и P–AC; I–O (старое оборудование) и AD–AG (стоимости) остаются только
в исходном evidence, без новых реквизитов и строк отчёта изменений. Ранее
сохранённые значения, история и отчёты доступны без удаления. Старый ещё
не применённый32-field пакет требует нового preview (PREVIEW_STALE).

Перечень2668 объектов2027 допускает пустые плановые даты AB/AC; Z остаётся
длительностью монтажа. Даты можно позже заполнить тем же форматом и обычным
повторным импортом: существующие объекты и дела сохранят идентичность.
Никакая дата не выводится из года, длительности или проектного периода.
Реальные импорты и платные вызовы в текущей задаче не разрешены.

После согласования с main f935c748 актуальный canonical frontier —42:
40 остаётся восстановлением пароля,41 — контролем выплат ОТиЗ,42 — адресным
импортом. Backup/restore содержит118 таблиц и64 AUTO_INCREMENT. Это описание
проверяемого кода; применение миграций к dev/prod этой задачей не разрешено.
