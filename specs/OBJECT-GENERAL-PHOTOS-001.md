# OBJECT-GENERAL-PHOTOS-001

## Простыми словами
В чек-листе можно снять или выбрать общие фотографии текущего лифта. Кнопки находятся снизу рядом с «Все фото». Общие снимки сохраняются и отправляются так же, как снимки разделов, но не заменяют обязательное фото конкретного раздела.

## A1 — Public seam and ownership
Авторизованный пользователь с существующим правом загрузки фото в открытое монтажное дело SHALL загрузить общее фото через существующий POST `/pilot/objects/{id}/checklist/photos`: photo_uploaded, sectionId=0. Scope — только installation case текущего объекта, не весь заказ. Projection/read выдаёт sectionId=0 и существующие защищённые view/thumbnail URLs. Права/CSRF/валидность файла сохраняются; отрицательные/неизвестные sectionId и нецелочисленный0 отклоняются без фактов. Только photo_uploaded/photo_revoked могут использовать0; item/section/installer commands по0 отвергаются.

## A2 — History, limits, replay
Общие фото наследуют журнал, actor/device timestamps, immutable clientOperationId/payload, byte hash, ревизию и подтверждённые receipts RELIABLE-FIELD-OFFLINE-001. Идентичный повтор не создаёт второе фото/операцию; после обычной проверки прав изменение target/payload с тем жеID — conflict. Проверка прав предшествует replay и не раскрывает факт чужой операции при отказе доступа. Limit10 active general photos отдельно от10 каждого раздела; dedupe hash действует в текущем bucket. Например те же bytes допустимы один раз в general и один раз вsection1;11-е general отклонено,section1 остаётся доступен. Отзыв общего фото требует существующих прав/причины, добавляет photo_revoked; upload/history не удаляются. Cross-object photo read/command не переносит фото.

## A3 — Progress isolation
Общие фото SHALL не менять checklist progress и не удовлетворять section completion. После выполнения всех5 items раздела2 без section2 photo завершение раздела отклоняется даже при general photo. После добавления section2 photo обычный workflow сохраняется. section_completed0 всегда отклоняется.

## A4 — Local first UI
Снизу чек-листа компактная группа «Фото объекта»: «Снять», «Выбрать», «Все фото» с полными accessible labels. Capture input предлагает камеру; select допускает несколько image файлов. Disabled permissions сохраняются. Три действия доступны320/393/1366px без horizontal overflow. Общие фото показываются в собственном strip и входят в общую галерею/счётчик; section strips их не показывают. Gallery return и local queued delete/revoke используют прежние правила.

## A5 — Offline and recovery
Общие фото используют тот же durable IndexedDB blob+operation transaction, actor fence, cap, recovery при quota, pending preview и автоматический sync. Offline enqueue сохраняет sectionId0, рестарт/возврат сети отправляет тот же operation ID; receipt сохраняет0 и local bytes освобождаются только по прежним правилам. Unsaved general photo сохраняет retry/download/discard memory recovery в general block. Никакого нового private cache/anonymous identity или отдельного writer.

## A6 — Remove one queued general photo safely
Удаление queued, unattempted общего photo_uploaded SHALL удалять только выбранную локальную операцию/Blob, сохраняя ID, bytes/hash, payload и порядок остальных действий. Связь непосредственного следующего действия с удаляемой фотографией заменяется её прежним predecessor (либо null); это только локальная сериализация, не изменение wire. Root с attempted/frozen wire либо любой зависимый attempted/frozen wire отклоняет удаление атомарно без изменений. Actor/epoch fence проверяется внутри прежней transaction. Остальные типы/разделы сохраняют установленную семантику discard; section0 фото не является бизнес-предпосылкой других действий.

## Delivery
Root specs/tests, separate sol/low executor implementation, independent reviews обязательны перед publication. После явного поручения владельца о полной доставке Gate3/final/exact-source CI обязательны перед merge и домашней выкладкой; прежняя local-preview пауза снята. Full local make test/verify запрещены. Нет новых tables/migrations, внешних adapters, backfill или изменения imports. Backups сохраняют existing photo/operation tables и private media. Browser hardware camera/FortiClient остаётся UNKNOWN без ручного исполнения. Соседний WIP минимализма/установки сохраняется.
