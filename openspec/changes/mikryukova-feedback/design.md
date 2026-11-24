## Context

См. proposal.md и specs/MIKRYUKOVA-FEEDBACK-001.md. Основание — подтверждённое интервью владельца. Текущий markPaid оплачивает расчёт целиком; payment fact не имеет связи с отдельными obligations. Владелец дополнительно подтвердил: исходные плановые workdatestart/plan_finish_date являются договорными.

## Goals / Non-Goals

**Goals:** согласованный public owner от карточки/расчёта до реальной частичной выплаты; точные суммы, история, защита конкуренции и понятные UI/PDF/XLSX.

**Non-Goals:** импортёр Excel и его ветка, корпоративный production, внешние сообщения, перенос прежней учебной финансовой семантики. Нет новых writers в rapid-pilot.

## Decisions

- ObjectDetails остаётся владельцем редактируемого stops; source JSON и ручной overlay с revision/audit. Договорные даты читаются из существующих исходных workdatestart/plan_finish_date по явному решению владельца, без дублирующих полей. Pure ContractualReplacementPeriod::fromContractDates / allowanceFinish вычисляет включительный календарный интервал. NativePremiumInputs обеспечивает evidence и текущую справку; все финансовые projections используют stops.
- Workforce query применяет delivered к набору списка и сводке; UI не выводит отсутствие закрепления из нуля currentWorks.
- MariaDbOtizSettlementV2 остаётся единственным владельцем hold/resume/pay/reverse. Public holdObjectPayments/resumeObjectPayments принимают actor/object/expectedRevision/reason/operationId. objectPaymentHold возвращает objectId/revision/held/reason (начальный revision0); paymentPlan возвращает eligibleCents/heldCents/paidCents/remainingCents и objects с objectId/remainingCents/paidCents/held.
- Добавить append-only fm2_otiz_object_payment_hold_events с уникальными object/revision и fm2_otiz_payment_allocations с payment/obligation/amount. Новая версия схемы41, manifest/readiness/recovery и current-schema fixtures обновляются согласованно. Это необходимая новая структура, а не совместимость прежних учебных данных.
- Общая блокировка settlement_locks по отсортированным objectId сериализует hold/resume/pay. Затем obligations по id. markPaid сохраняет API, paidCents ответа — сумма конкретного batch; payment header=sum allocations. Сторно исключает только allocation выбранного header. All-held => PAYMENT_HELD без фактов. Replay существующим operation owner. Объект обязан существовать. Повтор текущего hold/resume состояния с верной revision может быть no_change; другая причина held состояния — новый versioned факт.
- Admission сохраняет прежний fail-closed контракт для расчёта, не становится обходом проверки composition. Hold влияет на выплату, не на начисление/accept. Резервная выплата тоже подчиняется object hold. Уволенный исходный получатель допускается только явным reserve-release решением. UI default redistribution исключает неразрешённые личные резервы; нет получателей — нет нового черновика.
- UI использует существующие shlz формы/диалоги; новая графическая система не вводится. Payment-mode workbook отражает eligible remainder, report/history остаются объяснимыми; реестры и ledger читают allocations. Источники и даты видны в карточке/документе/расчёте.

## Risks / Trade-offs

- Двойная выплата/неверное сторно → root regression двух объектов, повтор, конкурентные соединения, subset reversal и SQL балансы.
- Demo rewrite затрагивает rights/frontiers/payments/reserves → preview полного dependency graph, backup, одна controlled apply транзакция, числовой post-check; не заменять проекцию без связанных сумм.
- Новая схема → canonical fresh/repeat, migration/readiness/recovery inventories, отдельный bounded restore check.
- Некорректные исходные плановые/договорные даты → явные issues вместо выдуманного норматива. Справка и adjusted finish не подменяют исходный договорный интервал.
- Параллельные серверные изменения → read-only hash inventory до поставки, сохранить/согласовать отличия, не перезаписывать checkout целиком.

## Migration Plan

Root specs/tests → independent Gate3 → separate sol/low runtime authors → focused GREEN → independent final → exact-source GitHub CI/PR → backup и reviewed deployment только home dev → preview/backfill/recalculate → read-only user flow/balances. Rollback использует сохранённые source/state/database с согласованной версией, без потери новых фактов. Корпоративный GitLab push не выполнять: он запускает production.

## Учебный пересчёт после read-only inventory

На home обнаружено309объектов,17расчётов: один accepted/paid id4 (44 checklist rights,5обязательств,1payment),16drafts, без accepted reserve-release/reversal/replacement. Все17344saved entitlements имеют rightKey/basisBp/cumulativeGrossCents/grossCents; все741recipient имеют weight. Owner подтвердил точечную перестановку договорных дат246/147354.

TrainingOtizRecalculation(Connection,prefix,legacyPrefix,configuredPublicBaseUrl,clock)::preview(actor)/apply(actor,previewHash,operationId) — отдельный CLI-only maintenance seam, без HTTP. Точный configured URL https://fmonitor.antropophag.ru обязателен; CLI берёт его из конфигурации, а не из флага вместо конфигурации. Actor должен иметь otiz.manage; backup/deploy выполняет оператор отдельно до apply.

Preview не пишет в sourceDB: stages stops=floors и ровно разрешённую date correction в памяти, использует общие NativePremiumNorms/ContractualReplacementPeriod/PremiumCalculationV2 и pure projection seam денежного владельца. Не вызывать отдельный mysqli reader после незакоммиченных Yii writes. Сохраняются exact saved right/recipient identity и weights, меняются только подтверждённые входами суммы/производные даты; новые работы в оплаченный расчёт не добавляются. Drafts не получают claims/obligations/payments. Текущий простой accepted graph обновляется атомарно с obligation/payment allocation/frontier балансами. Неизвестный/более сложный dependency graph — unresolved и отказ apply, а не незаметный частичный пересчёт. Это ограничение обслуживает проверенный текущий учебный inventory, не ограничивает обычный R4 payment lifecycle.

SourceFingerprint охватывает source operands, calculation JSON и зависимые денежные rows. PreviewHash детерминирован, не включает время вызова. Apply проверяет целевой контур/actor, сериализацию и sourceHash под блокировками; изменённые входы=>PREVIEW_STALE; unresolved=>TRAINING_RECALCULATION_BLOCKED. Receipt в existing v2_operations: повтор actor/op+hash возвращает прежний результат даже после изменения source; другой hash конфликтует. Один transaction включает backfill/revisions/audit, date correction, calculation/claims/frontiers/obligations/payment/details и receipt. Root test: real canonical accepted paid right28 и draft right29 с ручным резервом; новый work29 не поглощается accepted root; stop9→7 и новый норматив дают accepted1170000 вместо1183000 и draft585000+reserve585000; failure trigger и concurrent replay проверяют атомарность.

### R5 preview schema

Preview: objects[{objectId,oldStops,newStops}], dateCorrections[{objectId,regnumber,beforeStart,beforeFinish,afterStart,afterFinish}], calculations[{calculationId,status,beforeTotalCents,afterTotalCents,beforeReservedCents,afterReservedCents,rights[{rightKey,beforeGrossCents,afterGrossCents}],obligations[{employeeId,beforeAmountCents,afterAmountCents}],payments[{paymentId,beforeAmountCents,afterAmountCents}]}], sourceFingerprint, previewHash, unresolved. Дополнительные exact ids/diagnostics допустимы. Before/after — не скрытые мутации. PreviewHash excludes invocation clock. Apply validates actor/target independently of prior preview and rejects changed monetary dependency bytes. Unsupported reversal/accepted-release/replacement/ambiguous multiple payment graph is explicit unresolved. Unrelated user/original-document/work rows and original object columns outside the approved correction survive byte-identically.


### Полная квалификация R5 после advisory review

Применимый home graph — один accepted/fully-paid расчёт и произвольное число draft без durable money; один legacy payment может иметь полностью пустую новую allocation table. Preview квалифицирует точное соответствие saved rights/claims/frontiers, positive recipient/obligation identities и header totals; неоднозначные/частичные allocations, duplicate/missing obligations, replacements/releases/reversals/multiple payments, malformed inputs явно unresolved. Для empty legacy allocations apply создаёт точные связи с прежними payment/obligation IDs; для complete — обновляет их. Draft никогда не пишет claims/frontiers. Итоговые баланс/identity/cardinality проверяются до receipt.

Apply блокирует всю используемую source/dependency выборку в детерминированном порядке на том же соединении до повторного preview/hash; sourceFingerprint включает active/inactive deductions, текущие PTO/correction и certificate evidence, а также денежные зависимости. Текущие workforce/admission не заменяют сохранённые права/веса; если их читает projection, они также входят в snapshot. Disabled user/inactive role отвергаются. CLI использует действительную конфигурацию URL и canonical UUID, preview не пишет.

Все объекты получают stops, даже без монтажного дела. Для существующих дел создаётся нормальный object-detail history event; для объектов без дела нельзя выдумывать case ID (schema требует NOT NULL): полный before/after inventory сохраняется в aggregate maintenance receipt `appliedPreview`. Расчёты повышают revision/updated_at и получают event `training_recalculated`. Повтор не добавляет событий. Сохраняются точные идентификаторы прав/долей и существующие ручные решения. Current PTO correction/certificate вычисляются по общему контракту и актуальному источнику; saved report date используется только при отсутствии PTO. Это уточнение реализации уже согласованного атомарного пересчёта, не расширение пользовательского scope.


### Final-review closure R5

Qualified graph SHALL иметь ровно один accepted расчёт. Saved accepted right ↔ active claim ↔ frontier проверяются по точной идентичности, единственности и before gross; отсутствующий frontier не считается нулём. В apply после начала транзакции блокируется полная authorization tuple actor/role assignments/roles/permissions и повторно проверяется otiz.manage. Отзыв роли сериализуется: если revocation committed прежде получения lock, apply возвращает FORBIDDEN без maintenance writes. Receipt replay также требует текущей авторизации; отозванное право не обходится сохранённым результатом.
