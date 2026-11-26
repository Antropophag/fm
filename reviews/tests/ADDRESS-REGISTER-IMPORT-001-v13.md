# ADDRESS-REGISTER-IMPORT-001 — independent reference-normalization Gate 3 review v13

- Date: `2026-11-19`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, tests, fixtures, production implementation, or evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T205404Z-e0ad1be514/package.json`
- Base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Candidate source: `a52e534608280912530a87c2415ca0335c37e7e5d9b3be663ec4e933e2264de2`
- Executable source: `b99f9e7175ed4dbf22ec06d1e7597186ef31b431b27b9d0d9c0720f24bcee75a`
- Snapshot patch: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T205404Z-e0ad1be514/snapshot/source.patch`, SHA-256 `0445a67774d92b0bce5d8ac297fb9887c46141a3dd2e1150f8e65cc82450be99`
- Prior Gate 3 verdicts: full corrective v8, input-boundary v10, and identity v12 — `APPROVED`
- Verdict: **APPROVED** for the reference-normalization test/spec delta

## Findings

No blocking or non-blocking findings.

## Contract and test assessment

The delta reconciles two existing public boundaries rather than adding vocabulary. The workbook parser already accepts equivalent case, whitespace, and separator forms for shaft type and shaft material; the application must map those accepted forms to the established shared-editor codes while preserving the original R/S bytes in source provenance. Numeric legacy codes remain editor/storage representations and are not accepted as XLSX labels.

The literal expectations are independent of production catalogue constants. The six synthetic rows collectively cover all four supported shaft labels and all six supported material labels, including lower/upper/mixed case, surrounding and internal whitespace, hyphen/space variants, and optional conjunction separators. The asserted code/display mapping is complete: shaft `40/201/202/203` and material `41/72/86/112/123/124` with their canonical Russian displays.

Fixture reachability is explicit. `AddressRegisterWorkbook::read` must first return six objects and no errors, proving that the tested spellings are already inside the parser contract. The application preview must then be ready with six selected/created objects, 168 filled values, no warnings/errors, all 192 field rows, one shared fake-provider call, and literal code plus original raw values for every object. The current exception therefore identifies the application/catalogue seam rather than parser rejection or fixture failure.

Apply must create exactly six objects and store the expected code/display pair for each. A second workbook uses canonical display spellings for the same objects; preview and apply must report six unchanged objects with no created, updated, filled, or replaced facts, no new detail events, no new objects, no provider call, and only the original single durable provider attempt. The report for the first package must still contain every original noncanonical R/S value, proving immutable raw provenance.

The negative frontier remains closed. Both fields are tested with a numeric legacy code and an unknown word: R=`7`, unknown shaft; S=`9`, unknown material. Every case must return `rejected/VALUE_INVALID` before provider access or durable attempt reservation, without catalog growth or detail history. This prevents normalization from broadening the source dictionary while allowing equivalent spelling of supported labels.

## Evidence and lifecycle

The prepared package maps `php tests/InstallationProcess/address_register_import_integration_001_test.php` and records exact-source `INTENDED_RED`. The parser assertions pass, after which unchanged production throws `InvalidArgumentException` while converting the accepted lowercase shaft label through the exact-match catalogue. Later application/apply/repeat assertions honestly await the separately authored production correction. Earlier integration controls remain unchanged and were GREEN on the prior baseline; they are not relabeled GREEN for this source.

The private actual-source preflight is bounded local evidence only: it uses zero provider calls and no live/import/apply operation, and reaches the same adapter mismatch after parsing. No address or row data from that source is reproduced here.

PHP syntax and `git diff --check` pass. The package base and snapshot digest match its manifest. I inspected the complete grouped test addition, literal mapping coverage, parser/application separation, persistence and repeat invariants, negative ordering, specification alignment, and exact RED evidence.

Full corrective Gate 3 v8, input-boundary v10, identity v12, and the original ten-test final-review/CI matrix remain in force. This approval permits a separate application/reference executor to implement the reviewed normalization correction; it does not approve production implementation or replace exact-source final verification.

No full local suite, live/network/provider request, actual import, deployment, commit, code edit, or test edit was performed.
