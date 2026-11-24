# Полная матрица после второго Gate3 возврата

R1/R2 рассматриваются как ограниченный первый implementation пакет; R3/R4 и demo имеют отдельные проверки до начала соответствующего кода. Финальное review охватывает весь change.

| Требование | Oracle / проверка | Статус |
|---|---|---|
| delivered catalogue, summary/found, search, 2 pages, assigned/no current, object identity | mikryukova_directory | RED |
| stop integer independent floors, inclusive/leap/year/invalid dates | contractual_replacement_period | RED |
| native7stop/12floor exact58500000, actual10 + contract20 =>29, PTO/report, contract endpoints, certificate override | mikryukova_inputs | RED |
| unchanged unopened eligibility | forDate empty + scoped card missing-start message | RED |
| real stops HTTP, auth/stale/invalid no writes, audit/replay/readback | mikryukova_feedback_http | RED |
| card period scoped20days, contract01/20, actual unknown, normative basis, no adjusted2099 | mikryukova_feedback_http | RED |
| PDF contract01/20, actual10,20days, normative basis | mikryukova_order_pdf | RED |
| XLSX exact norm/original/actual/derived/certificate02Oct and certificate basis | mikryukova_workbook | RED |
| current certificate corrected leaf and original opening inherited contract | excel_inputs_001, original_start_001 existing plan consumers | pending focused GREEN after inputs change |
| all finance consumers require stops | native/ObjectEconomy/register/portfolio focused checks | pending GREEN |
| hold/resume RBAC/revision/replay/history/object existence | object_payment_hold | RED planned |
| partial two-object pay, same/new-op repeat, resume, subset reverse, header allocation conservation, across calcs | object_payment_hold | RED planned |
| independent payment race and hold serialization | object_payment_hold_concurrency | RED planned |
| real hold HTTP, current payment export subset, labels | object_payment_hold_http | root tests pending |
| original peer reserve eligibility + explicit dismissed source payout | reserved_shares + new reserve cases | root delta pending |
| schema41 fresh/repeat, manifests, recovery, current fixture frontier | current-schema checks | root delta pending |
| home-only demo preview/apply replay/balance/deny foreign target | mikryukova_demo_recalculate | root tests pending |
| browser desktop/narrow keyboard labels and data flows | focused user flow | pending |
| review entire candidate / exact-source CI / backup/home deploy | delivery tasks | pending |

Gate3 return1 findings1–4 retained in agent review; return2:1/2 fixed;3 unopened interpretation clarified without changing inherited admission;4 exact surface-scoped values/basis added. No findings dismissed by dropping user scope. Excel importer explicitly excluded.

## R3/R4 matrix recheck after second Gate3 return

All prior five findings rechecked against four root tests + exact-source GREEN inherited reserve race/settlement DB. Scope now covers reserve peer hold/current status, auth/stale/payload conflict/no-write, two drafts consume once, explicit dismissed source, acceptance-time current status, reserve payment under object hold; HTTP controls and worked deductions; hold+resume exact immutable audit; distinct-actor pay/pay and hold/pay races; subset ledger linkage, waiting register, portfolio, monthly summary including two batches and subset reversal; payment XLSX remainder and export neutrality. Second return was missing exact resumed event tuple; now pinned with current read state/reason and full frozen clock. DemoR5 remains separately pending, not silently approved.


## Итог focused-проверок и визуальная проверка 25.11

R1–R4 целевые domain/HTTP/PDF/XLSX, schema drift, payment race, reserve race, базовый settlement DB, builder/native admission/rights-flow/corrections integration/upgrade, current canonical migration/recovery preflight и historical forward recovery прошли. Два соседних дефекта исправлены отдельным executor: calculation-wide remaining в portfolio и избыточное чтение hold на draft page. Root исправил только устаревшие fixture inputs: source floors9 для POST12; literal current schema41; signed actual start01.05 в regression Kss1/0.5/0, чтобы договорный интервал оставался валиден. Default InspectionFixture actual start02.09 сохранён.

Desktop/mobile: object-card presentation browser GREEN; OTIZ filters/decisions browser GREEN. Дополнительно изолированный real HTTP fixture payment hold/resume снят на1440/390. Первый визуальный проход показал формы до заголовка; /root/inspect_payment_inputs поправил только view/pilot.css по existing shlz. Финальный второй проход — читаемая секция управления выплатой после заголовка, подписанные основания, partial status/remaining, без viewport overflow. На этом визуальная шлифовка завершена. Артефакты outside repo: /Users/antropophag/.local/share/fmonitor-2/mikryukova-feedback/payments-{held,resumed}-{1440,390}.png и visual-payment-flow-final.log.

Home read-only повторно: HEADcbb2a656, clean, php/db healthy. Private DB snapshot restored into isolated local fm2_mikryukova_home_preview_20261125 for numeric rehearsal; no live writes. Dump restore required exact source DB default collation and removal of one engine-auto-added FK supporting index absent from source DDL. Registry14/15 receipt history remains the previously documented home gap (docs/operations/actual-start-from-assignment-order-20261113.md): existing registry is populated and receipt empty. Preserve it; scoped additive migration41 follows the existing canonical owner + migration-lock route without replaying historical backfill or claiming its GREEN. R5 numerical rehearsal and finalreview/CI/deploy remain pending.


## R5 frozen implementation / домашняя копия

Официальный расширенный mikryukova_demo_recalculate GREEN; snapshot_publication, architecture guard59/59, payment-hold neighbor и lint GREEN. Реальная isolated home-copy:309objects/17calculations/unresolved0; apply и exact replay завершились, stops309, audit events17, accepted4 revision3, total=paid66625000коп. Claims/frontiers/obligations/payment headers/allocations сходятся в66625000коп. Изменились16drafts, сумма paid4 не изменилась. Это локальная репетиция, не live rewrite.

Memory: исходный full-source JSON fingerprint превысил default128M; streaming fingerprint убрал большую промежуточную строку. Whole-copy apply18.26s, maxRSS323436544bytes, reportedpeak375048128bytes; разовая CLI запускается с явным memory_limit512M, обычная web конфигурация не меняется. Полные private evidence в apply-home-copy.php / home-copy-preview.json и соседних логах. Public facade не содержитSQL, persistence в MariaDbTrainingOtizRecalculation. Final independent review/CI/deploy pending.
