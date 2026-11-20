# ADDRESS-REGISTER-IMPORT-001 — independent Gate 3 review v3

- Date: `2026-11-18`
- Reviewer: separately assigned `/root/gate3_review` (`gpt-5.6-sol / low`)
- Authorship: reviewer authored none of the specification, OpenSpec artifacts, tests, fixtures, or RED evidence; this review is the only authored artifact
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T190609Z-1e61c8e307/package.json`
- Candidate source: `1c0e0f0db6253826f51de0a1b7c769a33ecfdb53d4159a9356563d06552e4465`
- Snapshot: base `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`, patch `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261118T190609Z-1e61c8e307/snapshot/source.patch`, SHA-256 `b22ca765349a9ad4daad519e7de7a57901e841b12e80d127fe6fc1f753027d26`
- Evidence: ten mapped focused commands recorded `INTENDED_RED` on the exact candidate source; no live DaData request was made
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Review

The v3 candidate closes all four findings from
`reviews/tests/ADDRESS-REGISTER-IMPORT-001-v2.md` while retaining the previously
reviewed A01–A12 matrix.

The manual tombstone now originates at the public `ObjectDetailsEditCommand`
seam. The test proves the exact manual actor and request, append-only event,
old/new values, effective null, default-import preservation, explicit overwrite
restoration, and zero extra events on replay. The normative contract explicitly
distinguishes restoring the same known registration number from accepting a
contradictory nonempty identity.

Null-date behavior is now inspected through the queue, card application, HTTP
card DOM, risk filter, and calendar. The expectations require null planned and
actual dates, an unopened object, the existing “Требуется распоряжение” state,
visible “Неизвестно” values, absence of a fabricated cutover/risk result, and no
new missing-schedule status.

Report coverage now fixes literal preview counters and provenance, a complete
field row, retained provider response and hashes, private modes, formula-safe
CSV, preview/applied phase and object identity, append-only overwrite history,
and secret scanning for evidence, DB and provider credentials. The real CLI
adapter cases cover successful estimate/preview/resume/apply/report plus exit
families 2, 64, 69, 70, 75, 77 and 78 with external transport functions disabled.

The parser test now creates an actual highly compressible OOXML member at the
128 MiB expanded limit and one byte above it, and separately forges central
directory metadata to require rejection before inflation. This complements the
existing compressed-size, entry, row, column and cell limits and the OOXML
security corpus.

OpenSpec tasks 1.3–1.5 and 2.1–2.5 now truthfully record completed planning and
RED authoring. Task 2.6 remains pending until this verdict is incorporated, and
all implementation, GREEN, final-review, CI and live-provider work remains
unchecked.

The earlier v1/v2 corrections remain present: independent parser/provider
fixtures, exact provider policy and budget ownership, offline estimate/cache/
signed evidence behavior, two-process creation and paid-call races, late-fault
whole-business rollback, real post-commit response loss and replay, identity and
authorization matrices, canonical v40 schema/readiness, and public backup/
restore. Expected values are fixed by the contract and synthetic fixtures rather
than by future production helpers.

## Verification evidence

The prepared package records all ten mapped commands as `INTENDED_RED`, exit
`255`, against candidate source
`1c0e0f0db6253826f51de0a1b7c769a33ecfdb53d4159a9356563d06552e4465`.
Each stops at an absent production seam, which is the intended missing behavior
at Gate 2 rather than a fixture/setup failure. PHP syntax validation passed for
all ten tests and the three address-register support fixtures. `git diff --check`
passed. The verification plan selects `CRITICAL` with required reviews `gate3`
and `final`, and the package reports no missing tests. No full local suite and no
live provider call were run.

Gate 3 is **APPROVED** for the exact prepared snapshot. This approval authorizes
implementation against these reviewed expectations; it is not GREEN evidence,
final code review, CI approval, publication, or live DaData authorization.
