# ADDRESS-REGISTER-IMPORT-001 — independent final home44 code/architecture review v6

- Date: `2026-11-26`
- Reviewer: separately assigned `/root/fields21_review`; reviewer authored none of the reviewed contract, tests, fixtures, production implementation, conflict resolutions, policy, operational helpers, driver or verification evidence
- Actual authorship: root authored A15/specification/tests/frontier-policy reconciliation and the post-Gate-3 UI assertion adaptation; separately assigned `/root/fields21_production` authored the address-import production reconciliation
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261126T073130Z-f2ee056773/package.json`
- Candidate source: `9821c8e6afa40aa6dbb43ef6a2ed4cf10af362de4c75e69bc4a889f9f96977d0`
- Executable source: `ecdb8b252807eeb45a31b5cc4f445ad5a7a2ba5800de9d2cecd458836299e273`
- Snapshot: base `07de0f338f109ff1ac592c9fcbefe7af05e0f6ae`, patch `/Users/antropophag/.local/share/fmonitor-2/address-register-import/dev-deploy-20261126/final-source/source.patch`, SHA-256 `211d70a86a2bb9ca13f65c6e5f358799654ad2313b6acc6e3574bee9a15d0661`
- Verification plan SHA-256: `a216a87c6ca000a872bad06aa243892f1016a71c2435bff6fcc6136c4eabf580`
- Gate 3: `reviews/tests/address-register-import-001-review-v23.md` — `APPROVED`
- Operational reviews: private `operational-review-v2.md` and `driver-review-v2.md` — `APPROVED`
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Production and architecture review

The integration preserves current main owners byte-for-purpose: `AssignmentOrderOriginalExternalNumberSchemaMigration` remains canonical 42, `EquipmentReclamationSchemaMigration` remains 43, and address import is appended at 44. `AddressRegisterImportSchemaMigration` changes only its reported owner version. The catalogue remains contiguous through 44 and keeps email40/OTIZ41 unchanged.

Recovery composition is coherent. V42 retains the V41 inventory because owner42 modifies the existing original-start family without adding a table or allocator. V43 adds exactly the reclamation table and allocator. V44 adds the seven address-import families and its allocator, producing the required 119 tables and 65 AUTO_INCREMENT entries. Runtime recovery, manifest validation, restore, allocator restoration and deep readiness consistently use V44. The verification policy moves V42/V43 to their upstream owners and leaves only V44 with address import.

The implementation preserves the complete 21-field behavior approved in v22/v5. No parser, application, identity, budget, provider, history or reporting semantics changed in this integration. Old pending 32-field packages still fail closed; applied historical outcomes remain replayable. The home-compatible path uses direct owner44 only, so it does not invoke or fabricate historical migration14 receipts.

The populated predecessor fixture includes password/OTIZ facts, the main42 nonempty external document number, the main43 reclamation with nonzero delay, valid parents and allocator gaps. Exact row, DDL and next-ID snapshots survive owner44 application and replay. The synthetic home state additionally preserves the unreceipted assignment-order registry row and next ID314. Full public backup/restore preserves the same predecessor union, address payloads, histories and replay state.

Inherited main `2ea60f1d2f0a9b152cd05a6731ea2f3a808bfc10` feature bytes are outside the address implementation authorship. The reviewed conflict resolutions preserve those owners, current UI/card behavior, recovery frontier, policy and consumer expectations rather than replacing them with the earlier v42 address candidate.

## Supplemental Gate 3 closure

The only executable acceptance change after the focused source was the object-card label assertion in `address_register_import_integration_001_test.php`. The previous assertion looked for the stale labels `Плановое начало`/`Плановое завершение` and `Неизвестно`. Current main intentionally renders the accepted plan section `readiness-plan-title` with labels `Начало`/`Завершение` and value `Не указано`.

The revised XPath narrows observation to that plan section and changes presentation literals only. It retains the substantive requirements: both public dates are null, the case remains unopened, no actual dates/opening facts appear, the object stays in the expected queue, null dates are not overdue or fabricated cutover data, and no new “График не задан” state appears. The exact final-source integration test is GREEN. I approve this root-authored test delta as the required Gate 3 closure; it does not weaken A13/A15 or require another production change.

## Verification evidence

The exact final source has GREEN results for the changed integration, schema and full restore commands. The schema run proves canonical 1..44, populated v43→v44 preservation, the home receipt-gap/registry witness, prefix25, partial-family recovery, schema drift rejection and replay. The restore run covers the 2,668-object/667-address flow, 19,994 boundary, 256 MiB PHP memory, 16 MiB MariaDB packet, 119/65 inventory, populated predecessor backup/restore and operation replay.

Nine other importer commands and fourteen adjacent checks are retained GREEN from the immediately preceding focused source. The lineage record shows only the integration test plus documentation/proposal changed afterward; production, the other importer tests, all adjacent tests and their inputs remain byte-identical. Adjacent GREEN covers main42/43 behavior, architecture, current frontier, object details/card/queue, email schema/migration, OTIZ payment hold, production canonical runner, dependency rendering and verification inventory.

The earlier integration failure was completely inventoried and was a stale UI literal, not a product regression. Its corrected exact-source rerun is GREEN. No unresolved `REGRESSION_FAILURE` remains in the supplied focused inventory.

`git diff --cached --check` passed on the restored snapshot. PHP syntax checks passed for address migration44, recovery V44 and the changed integration test. The canonical full local suite was not run, consistent with the owner ban. Full exact-source GitHub CI on the new committed head remains required and is **UNKNOWN** until it completes.

## Operational disposition

The scoped owner44 helper is approved at SHA-256 `c983856a32ec237839db664383aab9de386a2a5f50dc73f732b4cb5905152a26`; the old-table inventory helper is approved at `a8e1f581ad7ef0efa7f06334613d1224b53c6b5fe0fd75280d0ccc6a5c886ffb`. Their disposable probe proves apply/replay, exact old-table preservation, receipt zero and registry next ID314.

The home driver v1 secret-retention finding is resolved. Driver SHA-256 `f29c19a11a019be2018652996a82fa1c5f08e549af8b0b3417beadb51984e03e` uses an in-memory sensitive capture for container/image inspection and expanded Compose configuration. The sentinel qualification `c07ead7f2de71ad292b80ab22f482ada8a093f4a4917e51afc0561b92d85ee87` is PASS; the additional root probe exercises actual project/config parsing and failure capture without retaining sentinel bytes in logs. The no-merge blueprint targets the reviewed CI-GREEN PR307 branch head with the required main ancestor.

Home deployment remains authorized only after the exact committed source receives GREEN CI. It must retain the frozen helper/driver hashes, verify the fresh backup and all old-table witnesses, run scoped owner44 under the canonical lock, preserve db/gateway and the four-service set, and perform no actual import, paid provider request or SMTP test. This review does not authorize PR merge, main push, corporate deployment or any live import.

No source/test/production edit, remote action, backup, migration, container operation, import, provider request, email, commit, push, merge or deployment was performed by the reviewer. This approval is the independent final code/architecture decision for the exact snapshot above; it is not CI GREEN.
