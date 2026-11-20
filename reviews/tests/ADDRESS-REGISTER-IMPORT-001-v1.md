# ADDRESS-REGISTER-IMPORT-001 — independent Gate 3 review v1

- Date: `2026-11-18`
- Reviewer: separately assigned agent `/root/gate3_review` (`gpt-5.6-sol / low`)
- Authorship: reviewer authored none of the specification, OpenSpec artifacts, tests, fixture, or RED evidence; this review record is the only authored artifact
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T181240Z-6beb5df542/package.json`
- Candidate source: `13fae3bc696256d7d5524236a37de4f92b1f9cf1d36bf593d64413b6024f6c88`
- Snapshot: base `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T181240Z-6beb5df542/snapshot/source.patch`, SHA-256 `71c44b9bc2a639b2be19f9dcbcbc05e2a51d77265bb540e1b177ea117459c86c`
- Scope: normative spec, OpenSpec proposal/design/tasks, four executable tests, and `AddressRegisterWorkbookFixture`
- Verdict: **CHANGES_REQUESTED**

## Findings (prioritized)

### 1. A09 has no executable concurrency or unknown-commit coverage

Blocking. The contract requires two concurrent packages to create at most one object and requires reconciliation of a lost/unknown commit without a blind reapply (`specs/ADDRESS-REGISTER-IMPORT-001.md:69-70`; OpenSpec scenarios at `spec.md:128-138`). The integration test performs only sequential replay, sequential operation-fingerprint conflict, a sequential manual edit after preview, and a target mismatch (`address_register_import_integration_001_test.php:36-52`). It never runs two workers against the same identity, injects a lost response/uncertain commit, observes `OUTCOME_UNKNOWN`, or proves exact operation/business-event/object inventories after reconciliation. An implementation with no identity reservation, duplicate concurrent objects, or blind reapplication after an uncertain commit could pass.

Add deterministic worker/barrier races for same-object creation and stale apply, plus commit-response loss/reconciliation fault cases. Assert exact winner/loser results and complete object, package, operation, accepted-address, detail-revision, event, and audit inventories before and after replay.

### 2. A08's atomicity oracle checks one value, not the whole package transaction

Blocking. The “last bad row” case fails during preview and then calls `apply` without asserting its status/reason (`address_register_import_integration_001_test.php:49`); it does not exercise a failure after earlier rows have begun publication. The audit-trigger case checks only the effective `weight` value (`:54-57`). It does not prove rollback of a newly created object, technical case, identity reservation, source linkage, accepted address/version, package operation outcome, detail revision/events, or audit rows, and it does not establish that durable failure evidence is kept outside the rolled-back business transaction. The protected-table snapshot covers only four process tables and is not recaptured around the fault.

Inject a late apply fault after at least one new row and one update have written each transactional family, then compare exact pre/post inventories for every required atomic fact. Separately assert the durable rejected/failed attempt evidence and that retry cannot mistake the failed attempt for a committed outcome.

### 3. A10 and the public report/CLI contract are mostly untested and partly ambiguous

Blocking. The sole report assertions cover one replaced weight row (`address_register_import_integration_001_test.php:46`). There is no CSV or JSON artifact check, Russian message/next-action check, counters/provenance/source objects/provider evidence check, formula-injection escaping, secret redaction across artifacts, private-root modes, preview-versus-applied distinction, or post-commit export failure and report-only retry. The CLI test exercises only invalid invocations with all configuration removed and requires every case to collapse to `CONFIGURATION_INVALID`/64 (`address_register_import_cli_001_test.php:6-9`); it never reaches any valid command or verifies the specified exit mapping, overwrite reason/preview confirmation, configured operator/DB principal, target, schema, or “reject before provider” ordering.

The normative return shape lists statuses `ready`, `rejected`, `applied`, `replayed`, `failed`, and `unknown`, while separately saying exit 0 may mean “report” and exit 70 may represent export failure after an applied outcome (`specs/ADDRESS-REGISTER-IMPORT-001.md:32-34`). It does not define the report/export-failure response status and reason precisely enough for an independent oracle.

Resolve that response contract, then execute all four CLI actions through valid and rejected configurations. Inspect exact stdout/stderr, exits, durable result, CSV/JSON bytes and permissions, redaction, formula safety, counters, provenance, export-failure behavior, and report retry without reapplication.

### 4. A11 has no schema, migration, readiness, dependency, backup, or recovery test

Blocking. The integration fixture happens to run against an available current test schema, but no assertion covers canonical additive migration, schema inventory/frontier, missing/incompatible schema returning `SCHEMA_UNAVAILABLE` without importer DDL, runtime XLSX/HTTP dependencies, readiness, backup/restore, or forward recovery. This does not satisfy A11 (`specs/ADDRESS-REGISTER-IMPORT-001.md:72`) or OpenSpec task 3.5/4.5. A solution that works only on the prebuilt fixture database, omits production migration/catalogue entries, or loses packages/evidence during restore could pass all four tests.

Add focused migration/inventory/readiness tests and an upgrade/backup-restore round trip that proves packages, operations, source rows, address evidence, revisions and audit history survive. Exercise absent and incompatible schema explicitly and prove no DDL is issued by the importer.

### 5. Parser coverage is far below the bounded and security-sensitive A02 contract

Blocking. The parser test covers one literal workbook and seven small mutations (`address_register_import_parser_001_test.php:8-25`). It omits 1900/1904 date epochs and Excel serials, exact 15-digit ID boundary and numeric rejection, control/NUL/UTF-8 and byte limits, all numeric/bool/dictionary boundaries, two equipment passports in full, design-period precision, malformed-row recognition, footer/formula distinction, sheet/header variants, unknown columns across the full supported range, shared strings, external relationships/DTD, and every compressed/expanded/entry/row/column/cell limit. `file_put_contents('not a workbook')` is not a ZIP/XML adversarial corpus. Plausible zip bombs, external entity/relationship reads, truncation, floating-point money errors, and silent malformed-row loss could pass.

Create independent bounded fixtures for each boundary and one-over rejection, both workbook epochs and storage types, security payloads, footer formulas, precise decimals/dates/IDs, and the full column/dictionary mapping. Assert exact reason, row/field, and no catalog/provider side effect where applicable.

### 6. Provider acceptance and retry assertions permit materially wrong behavior

Blocking. The provider test accepts any retry count `<=3` for 429/500 (`address_register_import_provider_001_test.php:12`), so no retry at all passes. It does not test timeout/transport exceptions, retry success, bounded backoff, response-size cap, malformed/non-array/multiple results, missing `result`, `room`, complex house/corpus/structure tokens, ambiguous source parsing, territorial/entrance annotations, or Zelenograd's street exception. The injected callable exposes only a single total timeout integer, so connect timeout, TLS verification, and response streaming bounds are not observable. It checks secret substrings only in exception messages, not logs/reports.

Add exact attempts for retryable versus non-retryable failures, retry-then-success, transport/timeout/oversize/malformed cases, a table of house component identities and ambiguity, and observable production transport policy. Verify saved provider evidence and every externalized error/report remain secret-free.

### 7. Fill-only, overwrite, history, identity, and authorization assertions are incomplete

Blocking. One weight edit demonstrates manual overwrite, but the test never creates a manual tombstone, zero or false current value; never proves ordinary import preserves those states; and never verifies append-only event fields for actor, reason, source, old/new values, package and report hash. It checks four protected process tables, omitting factual dates, deadline certificates, payroll operands, accepted address consistency, and other named immutable snapshots. Identity coverage lacks unknown-UNOM fill, ambiguous existing matches, contradictory duplicates/reordering, and verified house/entrance matching. Authorization checks only one reader ID and does not exercise inactive/unactivated users, either required capability independently, role expiry, configured `CURRENT_USER()` principal mismatch, or replay authorization.

Add field-table cases for unknown/zero/false/manual tombstone in default and overwrite modes; exact append-only history and address-version assertions; the full identity ambiguity matrix; all permission/principal/target/schema preconditions with provider-call and mutation inventories; and protected process/payroll/factual-date snapshots around both ordinary and overwrite apply.

## RED evidence

The prepared package records all four focused commands as `INTENDED_RED`, exit `255`, on exact candidate source `13fae3bc696256d7d5524236a37de4f92b1f9cf1d36bf593d64413b6024f6c88`. Each currently stops at its first absent production seam (`bin/fmonitor2-address-register.php`, `AddressRegisterWorkbook`, `AddressRegisterAddressValidation`, or `ProductionAddressRegisterImportFactory`). That is an intended missing-behavior cause rather than a setup failure, but it does not execute or validate the downstream assertions discussed above. No full local suite was run.

Gate 3 is **CHANGES_REQUESTED**. Implementation is not authorized against this test candidate; the complete acceptance matrix needs executable sensitivity and a fresh prepared Gate 3 review.
