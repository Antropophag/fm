# ADDRESS-REGISTER-IMPORT-001 — independent prefix/frontier Gate 3 review v14

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, tests, fixtures, production implementation, or evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T212029Z-f1132d0077/package.json`
- Base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Candidate source: `3745c7100c9347c516b017e8e74ca49f4186142c6efab1c720c28dcf17fedb8e`
- Executable source: `8fe9493d4584542cd7517a38eedd12571a551df2bfd7fb3f999b9395c348c52e`
- Snapshot patch: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T212029Z-f1132d0077/snapshot/source.patch`, SHA-256 `07a4a56bd862e0ffd8dc1c66fcf9fcde98c473193b4b93bdf4766ed233c0b357`
- Prior Gate 3 verdicts: full corrective v8, input-boundary v10, identity v12, reference-normalization v13 — `APPROVED`
- Verdict: **APPROVED** for the prefix/frontier test/spec delta

## Findings

No blocking or non-blocking findings.

## Contract and test assessment

The new A11 paragraph preserves the established canonical prefix range through 25 bytes while keeping schema semantics exact. A fresh v39 predecessor and a compatible six-table partial v40 family must both reach the complete seven-table address-import family. Multiple prefixes may coexist in one database. Repeat `apply` and `assertCompatible` must perform no rename, repair, or other DDL. Constraint identifiers remain implementation details; the package foreign key still requires the exact package target and `ON UPDATE/DELETE RESTRICT` semantics.

The focused address-import schema tail is sufficient and independent. It uses two distinct 25-byte prefixes in the same database, runs the real canonical v39 predecessor and v40 catalogue, requires exit `0` and schema version `40`, verifies all seven prefixed tables, runs strict compatibility, snapshots the complete database DDL, and proves repeat apply changes nothing. It then removes only the chunks table, requires additive recovery, verifies every pre-existing table DDL byte-for-byte, and re-runs strict compatibility. This covers the observed interrupted six-table state without accepting drift or silently repairing existing definitions.

The original exact chunks schema oracle remains intact: ordered columns/types/null/defaults, binary collation, sole four-column primary key, package foreign-key target and RESTRICT actions, six checks, no AUTO_INCREMENT, SQL constraint enforcement, drift rejection through both public migration seams, and no sibling creation before full preflight. The prefix delta does not weaken any of these assertions and intentionally does not make a physical foreign-key name public.

The measured production defect is precise. With a 25-byte prefix, MariaDB derives `prefix + fm2_address_import_payload_chunks + _ibfk_1`, a 65-byte identifier, and canonical migration exits `70` after creating the compatible first six tables. An explicit deterministic short hash-derived foreign-key name can correct that identifier boundary without changing columns, target, actions, indexes, checks, table inventory, or readiness semantics.

## Frontier consumers

The neighboring changes update stale current-frontier expectations from v39/106 tables to v40/113 tables and 60 AUTO_INCREMENT families. They add the seven address-import tables where exact current inventories are asserted and include v40 in applied-version lists. Historical version-specific counts and contracts remain unchanged.

The workforce canonical runner retains the ATTEMPT-AUDIT v0.4 physical-name policy: only the two previously overlong maintenance suffixes use the established deterministic shortened mapping, and prefix-derived foreign keys remain bounded by the existing hash policy. The delta updates its current frontier and adds the seven v40 tables; it does not rename established short tables, broaden accepted drift, change grants, or alter workforce semantics.

## Evidence and lifecycle

The prepared package binds all 16 scoped commands to the exact candidate and executable sources. Nine are `GREEN`: ERP equipment facts, inspection photo content index, inspection planning, object detail snapshot, OTIZ settlement schema, production migration runner, OTIZ v2 upgrade, OTIZ v2 concurrency, and runtime readiness load. These confirm corrected v40 literals and unaffected semantics on consumers that do not exercise the failing maximal-prefix path.

Seven are truthful `INTENDED_RED`: classification provenance, deadline transfer certificate, inspection evidence, installation completion, identity access, workforce canonical runner, and address-register schema. Each reaches a 25-byte canonical-prefix assertion and receives exit `70`; the inspection-evidence test first passes its formerly stale frontier expectation before reaching the same later prefix failure. The complete failed-command inventory contains no unrelated unresolved failure.

PHP syntax and `git diff --check` pass. The package base and snapshot digest match its manifest. I inspected the A11 contract, full address-import schema tail, all neighboring frontier changes, ATTEMPT-AUDIT prefix mapping, all 16 exact records, and the corrected evidence disposition.

Full corrective Gate 3 v8 and the v10/v12/v13 deltas remain in force. This 16-test delta does not replace the original ten-test final matrix or the required final review of F1/F2/R1, capacity, restore, identity, input boundaries, and reference normalization. This approval permits a separate schema executor to implement the reviewed identifier correction; it does not approve production implementation or overall readiness.

No full local suite, live/network/provider request, actual import, deployment, commit, code edit, or test edit was performed.
