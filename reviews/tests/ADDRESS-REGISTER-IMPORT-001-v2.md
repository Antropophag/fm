# ADDRESS-REGISTER-IMPORT-001 — independent Gate 3 review v2

- Date: `2026-11-18`
- Reviewer: separately assigned `/root/gate3_review` (`gpt-5.6-sol / low`)
- Authorship: reviewer authored none of the specification, OpenSpec artifacts, tests, fixtures, or RED evidence; this review is the only authored artifact
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T185549Z-57d28578ae/package.json`
- Candidate source: `92784b6b043f4465b5bb6b42eba17175046b17c2e671d93518cfd30fd8f9f122`
- Snapshot: base `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T185549Z-57d28578ae/snapshot/source.patch`, SHA-256 `ea0b706769df2f7bf5656bda3f5a841fbf1c86fd36b129161b8d7cd650832b6e`
- Evidence: ten mapped focused commands recorded `INTENDED_RED` on the candidate source; no live DaData request was made
- Verdict: **CHANGES_REQUESTED**

## Findings (prioritized)

### 1. The manual-tombstone acceptance case bypasses the required public seam

Blocking. `address_register_import_integration_001_test.php:58-60` creates the alleged manual tombstone by directly updating `fm2_object_detail_edits.values_json` and incrementing its revision. It does not use `ObjectDetailsEditApplication`, create the required append-only manual event/request/provenance, or prove that the existing editor can express a deliberate clear distinct from unknown. A production implementation may fail to support manual clearing entirely, or may lose its actor/history semantics, while this test still passes because the fixture manufactures the storage shape.

Create the tombstone through the existing public manual-edit command and assert its exact request/event/current projection before preview. Then prove default import retains it with `KEPT_EXISTING`, overwrite replaces it with the import actor/reason/source in the next event, and replay adds no history. Do not encode the production JSON representation in the test setup.

### 2. A05 and A10 still claim broader observable behavior than their tests inspect

Blocking. The A05 integration checks only that a card returns 200 and contains the registration number, plus absence of a calendar event (`address_register_import_integration_001_test.php:39-40,63`). It does not assert the null-date presentation, absence of overdue/false opening/status, queue ordering/filtering, or summary behavior required by A05. A UI that renders a fabricated date or “overdue” marker can pass.

The report test checks one counter, source-object count, file modes, a Russian CSV header, formula neutralization and one weight history (`address_register_import_reports_001_test.php:5-10`). It does not assert the required complete row schema and values (source sheet/row, environment/mode, registration/object ID, old/input/final, reason code, Russian message and next action), the complete selected/excluded/created/updated/unchanged/filled/replaced/warnings/errors counter set, provider evidence/provenance hashes, or that preview and committed exports contain the correct planned versus actual values. It checks only the fixture evidence secret, while the contract forbids provider and DB secrets throughout reports/logs. The valid CLI path likewise covers only success, wrong principal, missing overwrite reason and missing schema; the specified rejected/provider/unknown/export exit mappings remain unexercised.

Add public queue/card/calendar assertions for the null-date meanings and false-state exclusions. Compare complete independently expected preview/applied CSV and JSON rows and summaries, including all provenance, message and next-action fields; seed recognizable provider/DB/evidence secrets and scan every output/log. Exercise representative CLI exits 2, 69, 70 and 75 at their public seam, including post-commit export failure.

### 3. The parser bound matrix omits the 128 MiB expanded-content limit

Blocking. The normative parser contract fixes both a 20 MiB compressed limit and a 128 MiB total expanded limit (`specs/ADDRESS-REGISTER-IMPORT-001.md:58`). The expanded v2 parser test covers compressed bytes, ZIP entry count, row, column and cell limits, but never constructs a small compressed archive whose expanded entries exceed 128 MiB (`address_register_import_parser_001_test.php:42-46`). A parser that checks only archive size and entry count, then expands an arbitrarily large highly compressible payload, passes the current suite.

Add an independently generated highly compressible OOXML archive at the expanded boundary and one byte over, asserting `INPUT_LIMIT_EXCEEDED` before XML materialization or catalog/provider activity. Keep the fixture bounded on disk while making the decompressed total observable from ZIP metadata/stream accounting.

### 4. The OpenSpec task state contradicts the prepared Gate 3 package

Blocking lifecycle inconsistency. `openspec/changes/address-register-import/tasks.md:6-16` still marks contract synchronization, verification-input/prepare, fixture/test creation, provider/economy tests, integration tests, and closure of the v1 findings as incomplete. Several entries describe work that is visibly present in this exact package, and task 1.4 still says signatures and exit mappings will be clarified “before tests” although the tests and normative signatures now exist. Only obtaining the v2 verdict itself should remain pending at this stage.

Update completed planning/test tasks truthfully and revise stale future-tense text. Leave implementation and later verification tasks unchecked. The lifecycle record must describe this candidate rather than the pre-v2 state.

## v1 disposition

The v2 delta materially addresses the principal mechanics behind v1 findings 1, 2, 4, 6 and much of 7: it adds two-process creation and cache races, real post-commit response loss, full-table late-fault comparison, canonical v40 schema/inventory checks, real public backup/restore, explicit provider transport policy and application-owned budgeted retries, identity ambiguity, capability denial, zero/false behavior and append-only history checks. Finding 3 is partially fixed by the report/CLI suite but remains open at the completeness described above. Finding 5 is substantially expanded but remains open for the missing expanded-content bound. Finding 7 remains open specifically for the side-channel tombstone and incomplete null-date public outcomes.

## RED evidence

The package records all ten mapped commands as `INTENDED_RED` against source `92784b6b043f4465b5bb6b42eba17175046b17c2e671d93518cfd30fd8f9f122`. Syntax validation of all ten tests and three support fixtures passed, and `git diff --check` passed. The RED commands stop at absent production seams, so the new downstream race/fault/export/restore assertions have not yet executed; this is valid missing-behavior RED but does not compensate for the sensitivity gaps above. No full local suite and no live provider call were run.

Gate 3 remains **CHANGES_REQUESTED**. Correct the four findings, prepare a new exact snapshot with focused RED evidence, and request another independent review before implementation.
