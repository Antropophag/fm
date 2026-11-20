# ADDRESS-REGISTER-IMPORT-001 — independent supplementary Gate 3 review v10

- Date: `2026-11-19`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the specification, tests, fixtures, production implementation, or evidence
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T200419Z-a8ca5ab241/package.json`
- Base: `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56`
- Candidate source: `6b47f9a3fd1f7a8a6da2b2b4fd4d0a216fe4d23a7f79f1ab821d3c9c312e6f07`
- Executable source: `0f32152ce9b8f2d70a5d909309ebf6cbc060ebeedff6f1248686e596bbe9b68d`
- Snapshot patch: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T200419Z-a8ca5ab241/snapshot/source.patch`, SHA-256 `10bd7b8bd9f4c4ca66d527b306e86c5fb8ec9d7f3fb60ddf8a331e4de98aebe1`
- Previous supplementary review: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v9.md` — `CHANGES_REQUESTED`
- Verdict: **APPROVED** for the supplementary test/spec delta

## Findings

No blocking or non-blocking findings.

## Resolution of v9 findings

1. **Resolved — invalid signed evidence now requires the exact public outcome.** The grouped test invokes the real CLI for both a valid signed envelope followed by a literal NUL/trailing bytes and a syntactically valid envelope with an invalid signature. Each case must return exactly exit `2`, `status=rejected`, `reasonCode=EVIDENCE_INVALID`, and empty stderr. The test compares cache-row count before and after each invocation. A generic configuration, database, schema, provider, or success result can no longer satisfy the oracle. Failures are accumulated rather than stopping the remaining boundary probes.

2. **Resolved — unknown COMMIT now compares the complete canonical reviewed report.** The test recursively materializes the small `Traversable` sequences, recursively canonicalizes associative maps while leaving list order intact, and compares the entire CLI result against the reviewed `$faultPreview` with only `status=unknown` and `reasonCode=COMMIT_OUTCOME_UNKNOWN` replaced. This covers every summary value, all 32 field rows, the complete normalized source object, provider evidence, provenance, indexes, values, and order. Cardinality-only or substituted payloads cannot pass. The proxy still proves exactly one actual commit and fresh-process reconciliation without a second commit or duplicate business facts.

## Supplementary scope assessment

The local-source boundary is tested through the public application seam with a test-only URL wrapper whose `open` and `stat` accesses are counted. The expected rejection, zero wrapper accesses, and zero provider calls jointly require URL/non-file input to be classified before source I/O or paid work. The wrapper performs no network request and adds no production hook.

The strict-JSON cases exercise the real CLI, real signed export shape, real evidence reader, and real cache application. A NUL after a complete document is treated as a nonempty JSON tail rather than EOF; an invalid signature supplies a GREEN control for the same stable rejection contract. The test requires both rejection classification and no new cache rows.

The unknown-COMMIT case remains a faithful ACK-loss regression. The test-only MySQL wire proxy forwards the real `COMMIT`, receives the successful server ACK, withholds only that ACK from the unchanged client, records one commit, and allows a fresh process to reconcile the same operation. Complete report comparison now protects the new streaming result adapters as well as exit/replay behavior.

## Evidence and lifecycle

The prepared package maps the single grouped command `php tests/InstallationProcess/address_register_import_reports_001_test.php` and records an exact-source `INTENDED_RED`. The unchanged production currently performs nonlocal wrapper I/O, accepts the trailing-NUL envelope as imported, and writes cache rows; those failures truthfully reach the final grouped assertion. The invalid-signature control and complete unknown-report comparison already pass. This is RED evidence for the next implementation step, not production GREEN.

PHP syntax and `git diff --check` pass. The package base and snapshot digest match its manifest. I inspected the full v9-to-v10 delta, exact public outcome mapping, recursive canonical comparison, list-order preservation, fixture reachability, and package evidence.

Full corrective Gate 3 v8 remains the authority for the complete importer spec/test baseline. Its ten-test requirements and the original final-review/CI matrix are unchanged and must be rerun against the eventual implementation. Frozen prior GREEN evidence does not close this new RED delta.

No full local suite, live/provider request, import, deployment, commit, code edit, or test edit was performed. Supplementary Gate 3 is **APPROVED** for this exact test/spec snapshot only; production implementation, final readiness, CI, publication, deployment, and live DaData use remain unapproved.
