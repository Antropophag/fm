# Independent final review — MIKRYUKOVA-FEEDBACK-001

## Return 1 — CHANGES_REQUESTED

Reviewer `/root/independent_review` (gpt-5.6-sol/low), не автор tests/runtime. Полный candidate R1–R5 просмотрен, все14 mapped acceptances exact-source GREEN. Package `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T215128Z-af631d58f8/package.json`; candidate `5767ab8ce242c6bc0e10fc54bbec28a775eaa4475b71db9e7e0598ea993c9e66`; executable `7164c0f03bdc572b7c817227319b7644d385bc374856604f1f8579ed00f599ed`. Snapshot `~/.local/share/fmonitor-2/mikryukova-feedback/final-candidate-v2`.

1. HIGH, OPEN: R5 graph qualification должна требовать ровно один accepted calculation, before gross equality saved right↔active claim и наличие каждого matching frontier. Текущая проверка допускает второй internally balanced accepted graph, несовпадение claim gross и отсутствие frontier. Предварительное предположение о duplicate active right внутри одного calculation отозвано: текущий v37 generated unique key это запрещает. Cross-calculation сложный граф остаётся за пределами согласованного one-accepted maintenance.
2. HIGH, OPEN: authorization tuple не полностью блокируется внутри apply transaction. Конкурентный отзыв role/assignment/permission может завершиться после проверки и до writes. Нужны deterministic locks всех используемых auth rows, повторная проверка, revocation race и отказ FORBIDDEN без maintenance writes. Receipt replay не отменяет актуальную авторизацию.

Других findings по полному production/test/schema/recovery/money/UI candidate нет. Root добавляет regression delta, separate executor исправляет только R5, затем required independent delta Gate3/final. Этот verdict не разрешает CI/deploy как final-approved.


## Correction candidate

Оба findings исправлены отдельным advisory_executor только в MariaDbTrainingOtizRecalculation.php; root test delta independently Gate3 APPROVED (см. demo-hardening review). Расширенный официальный R5 test, lint и snapshot_publication GREEN. Finding1 FIXED: exactly-one accepted, saved/claim before gross, mandatory frontier. Finding2 FIXED: locked auth tuple и post-lock authorization до receipt/writes, revocation race FORBIDDEN. Новая frozen final review pending; unchanged R1–R4 квалифицированы14GREEN предыдущего full snapshot.


## Corrected final — APPROVED

Reviewer `/root/independent_review`, не автор tests/runtime, одобрил полный corrected candidate без новых findings. Package `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T220158Z-2e46b8a5a8/package.json`, candidate `b2e802df39bdc04efc1c83bd7b5ffbe8eb2ebf8e98c0a6ff8926ab61a50ba413`, executable `610903039ed5d05fc958115eba072a479fdd50747db1f1a17a6698d6afb075b8`, patch `5fe79d83b00b7b7ef1c6b09781fc53decc316c7e2c1deecda72bd423e8c4ab16`. Exact-source R5 GREEN record `1795557574176961000-1c44b83d4cfd4d778ad5e142907af660`. Prior14GREEN применимы к неизменённым R1–R4 и R5 surfaces; byte diff только MariaDbTrainingOtizRecalculation и его root regression.

Оба final findings FIXED, новых material correctness/money/history/recovery/concurrency findings нет. R1–R5 contract behavior, schema/recovery, authorization, audit, payment allocations/reversals, UI/PDF/XLSX, CLI boundary и authorship separation APPROVED. Это code review, не evidence CI/deploy. После reviewed snapshot изменены только финальная review-запись, tasks и delivery status documentation; runtime/tests не меняются.
