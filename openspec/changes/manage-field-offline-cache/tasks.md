## 1. Contract and RED

- [x] 1.1 Зафиксировать совмещённый scope,7дней владельца, budget/reserve, protection и отсутствие server migrations.
- [x] 1.2 Подготовить verification-input и прочитать обязательный planner plan до нового RED.
- [x] 1.3 Root: дополнить пять UI coverage findings и написать public worker/browser RED для R1–R7; сохранить полные доказательства вне Git.
- [x] 1.4 Получить независимый Gate3 для полного candidate.

## 2. Implementation

- [x] 2.1 Отдельный sol/low executor реализует worker cache owner, budget/reserve, substitution metadata/TTL и safe retention.
- [x] 2.2 Реализовать общий offline стройконтроль/поиск/готовность, сохранить actor fences и pending данные.
- [x] 2.3 Удалить только closeout action и выровнять общие фото.

## 3. Verification and delivery

- [x] 3.1 Bounded focused plan, actual service worker+IndexedDB restart/expiry/quota/generation tests; фото/UUID/wire побайтно сохранены; responsive screenshots проверены.
- [x] 3.2 Независимый final review полного candidate и исправлений.
- [ ] 3.3 Один exact-source GitHub CI через выбранный planner consumer; локальный full suite запрещён.
- [ ] 3.4 Reviewable PR/source и честный handoff. Merge/deploy не запрошены. Hardware UNKNOWN отдельно от browser evidence.

Состояние на момент reviewable source: реализация и локальная квалификация завершены; независимый final review APPROVED. Пункты3.3–3.4 завершаются публикацией этого точного commit и результатом его GitHub CI; ссылка и результат фиксируются в PR/handoff. Архивирование, merge и deploy не входят в поручение.
