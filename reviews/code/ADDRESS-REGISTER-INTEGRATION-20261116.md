# ADDRESS-REGISTER-INTEGRATION-20261116 — independent final integration review

## Verdict

**CHANGES_REQUESTED — remove the undocumented internal object-ID search before commit, PR, and full CI.**

I authored none of the production or test changes in this candidate. I reviewed the complete uncommitted diff from base `7c6b8e14c0a5c8553a73320d12970107cb1debe5`, prepared candidate source `303869e2e66ac8cd6fdefb761eddf747bd952eb95599668dc5609784fbf4f929`, and executable source `a6694713491a9fc7f75fa28ee69879ceb2e4eebbb7864021faa4e92e1092faef` from package `20261116T064413Z-5d4b5d68bb`.

The merged feature otherwise remains compatible with current main: shared routes and navigation retain canonical `objects.read`, mutable page assets use content-derived versions and immutable responses, the unused preloader is absent, feedback return routing remains valid, domain facts come from effective/current authorities, and preference history remains actor-isolated, append-only, and v1-compatible.

## Standards axis

No blocking documented-standard violation was found. SQL remains in the named read adapter, the screen does not write domain facts, presentation preferences have one explicit application seam, and the focused architecture check passes all eight rules plus the global HTTP authorization qualification.

Non-blocking maintainability observations:

- `MariaDbAddressRegister` combines query construction, filtering, technical detail enrichment, process projection, completion facts, and personnel composition. This is a divergent-change risk, although it is still a bounded read adapter.
- Authorization is checked in the controller, read adapter, and preference callback. The defense is valid, but the duplicated formulations should eventually converge on one narrow access service to avoid drift.
- Column keys and metadata recur across catalogue, legacy validation, projection, and presentation. A future column change will require coordinated edits across several modules.

These observations do not block this bounded integration and do not justify broad refactoring in the correction.

## Spec axis and findings

### P2 — query matches an internal identifier outside A3

`app/InstallationProcess/MariaDbAddressRegister.php:29` includes `CAST(m.id AS CHAR) LIKE :q` in the search predicate. `ADDRESS-REGISTER-001` A3 defines search over address, registration number, factory number, and entrance only; the UI placeholder names the same public fields. A numeric fragment can therefore include an otherwise unrelated object solely because its internal database ID matches, changing totals, page membership, and combined stage-filter results.

Remove the object-ID predicate and add a regression in the address-register HTTP/read test proving that a value present only in `m.id` produces no match. Preserve literal escaping and the four contracted effective fields.

The separate Spec pass also questioned whether hidden-column positions survive reload. I do not treat that as a defect under the current contract: A11 deliberately defines the exact v2 state as `revision`, ordered `visible`, and `layoutVersion`, and the implementation preserves the order of every displayed column across reload and a new browser. A hidden column has no displayed position; adding a separate full-order field would violate A11's closed exact schema without a new owner decision.

No other missing, partial, or extra behavior was found. The full 22-column projection, current applied installer/control-engineer authority, effective edits, verified technical snapshot identity, corrected completion facts, registration-first adaptive layout, v1 projection, v2 history/CAS, and failure behavior match A1–A11.

## Evidence

Inspected focused evidence outside the checkout:

- address HTTP/native lineage, shared navigation, browser, feedback HTTP, and feedback browser: five PASS;
- preference isolation/CAS/validation/persistence/history: PASS;
- mutable-asset compatibility: genuine RED for unversioned `shlz.css`, followed by GREEN after `AddressRegisterAssetBundle` versions `shlz.css` and `navigation.js` and removes `preloader.js`;
- verification planner: 18 PASS;
- architecture guard: 59 PASS;
- `make architecture-check`: exit 0, eight rules and HTTP qualification PASS;
- isolated root Compose startup/restart: PASS;
- retrospective test/spec review: PASS with no open P1/P2 test-coverage finding.

The focused evidence predates the final review document but covers the reviewed executable correction. `git diff --check` is clean. No local full `make test` or `make verify` was run.

## Limits and next gate

This verdict covers the bounded address-register integration diff against `7c6b8e14`, including shared registrations, policy mappings, historical delivery records, and the asset compatibility correction. Production is unchanged.

Full exact-source GitHub CI is mandatory and remains pending/UNKNOWN. It should run only after the P2 correction receives a focused GREEN and the candidate is rebound to its new exact source. This review does not authorize merge of the current source and does not convert the historical production-first Gate 3 gap into preimplementation approval.

## Independent correction review — 2026-11-16

### Superseding verdict

**APPROVED — proceed to commit, PR, and mandatory full exact-source GitHub CI.** This verdict supersedes the initial `CHANGES_REQUESTED` disposition while preserving the finding and its correction history. I authored neither the bounded production correction nor its regression.

Reviewed package: `20261116T064758Z-f671439ff0`; candidate/exact source `5e7435fafb9664287d5bca2893817fde685c2b9c79ae462b892b55502d5e5478`; executable source `ce7440c3db206516038de9546995bb580258ca58aa3031daf345e611dea5aedc`.

### P2 disposition

Fixed. `MariaDbAddressRegister` now searches only the four A3 fields: effective address, entrance, registration number, and factory number. The correction removes only `CAST(m.id AS CHAR) LIKE :q`; literal escaping, stage filtering, deterministic ordering, pagination, and page-local enrichment are unchanged.

The new public-reader oracle creates mirror-only object `999001` whose address, entrance, registration number, and factory number do not contain `999001`, then requires `read(..., '999001')` to return no rows. The pre-correction run fails with that exact unwanted object and exit 255. The corrected exact executable source passes both the complete address HTTP/native-lineage test and the browser test. This is direct RED/GREEN evidence for the reviewed predicate change.

### Remaining gates

No blocking Standards or Spec findings remain at this scope. The earlier non-blocking maintainability observations remain future refactoring notes and are not correction scope.

Full exact-source GitHub CI is still mandatory and UNKNOWN. Merge is approved only after CI is GREEN for the committed source derived from this corrected candidate. Production remains unchanged, and the prohibited local full suite was not run.

## First-CI correction review — 2026-11-16

### Review scope and inventory

I independently reviewed the complete failure inventory from PR run `43819674368` and the bounded correction package `20261116T071429Z-69e6c0acf4`, based on commit `817b49f89b24f126e3d98c10440ed04690072cee`. Package candidate/exact source is `d61fccd482ea369f571b5478a6de8ed0487606c196e2e4aa5ff16256f84e06cd`; executable source is `fbc58141123d361b3c33f61c635e374e843548cf9071a2fdb64d1c31690244a1`.

The first full run completed every job. Fast, unit, and both integration shards passed. The only regression failures were the e2e sidebar-state oracle and the governance registered-focused-bootstrap oracle; `verify` failed only as their aggregate. I authored neither correction.

### Independent test/spec verdict

**APPROVED.** The sidebar correction strengthens the stale assertion rather than loosening it: it replaces an exact count of 11 with the complete ordered 12-route href sequence, including `/pilot/address-register`, while retaining the 48px target, single-row geometry, horizontal-scroll reachability, no-JS, state persistence, CSP, and document-overflow checks.

The bootstrap correction permits `consumer ownership frontier` only when `plan.consumer_expansions` contains an entry for the exact `TARGET` verifier. Each matching expansion must execute exactly `['php', TARGET]`, so it deduplicates onto the registered wrapped command rather than introducing a second route. The test still requires one command, the existing command ID and purpose, exact `run-in-profile browser --with-services php TARGET` wrapper, MariaDB service, local execution priority, acceptance and changed-test rationales, and rejects every rationale outside the closed semantic/consumer set. Generic diagnostic commands and ordinary integration controls retain their prior restrictions.

These expectations match `ADDRESS-REGISTER-001` shared-navigation integration and `REGISTERED-FOCUSED-BOOTSTRAP-001` RFB001-E. No fixture, runtime, verification policy, CI selection, or admission behavior changed.

### Focused evidence

- corrected sidebar browser: PASS;
- registered focused bootstrap: 8 tests PASS in 184.483 seconds;
- change-verification planner: 18 tests PASS;
- isolated root Compose startup/delivery/restart: PASS;
- `git diff --check`: clean.

The first CI failures are the RED evidence for these adjacent oracle corrections. The focused runs exercise the corrected expectations and their surrounding invariants.

### Independent final correction verdict

**APPROVED — push the test-only correction and run the next mandatory exact-head full CI.** No product, persistence, authorization, deployment, or UI finding is introduced or reopened. All earlier address-register findings remain fixed.

This approval does not make the failed first run GREEN and does not authorize merge without a new full exact-source CI result. The next run must complete GREEN at the corrected committed head; any failure requires the repository's complete-inventory triage again. The prohibited local full suite was not run.
