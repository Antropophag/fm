# ADDRESS-REGISTER-IMPORT-001 — independent final Yii image dependency delta review v3

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the production correction, executable test, contract, CI triage, or GREEN evidence
- Published base: `06e73000f325ceff154b036523d18ecbf9c4985e`
- Gate 5 package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T225310Z-d99e84aa28/package.json`
- Candidate source: `f9f1b93bac6e88ea546fabab936a163bbbe1c574545e2eac5a180a7363e25c5e`
- Executable source: `9d8a566959f716896e11e38284b69327ff761ef614ca79082a1fe8ad68e05c50`
- Snapshot patch SHA-256: `ae250c6bbd40e9ad08e3f4df8b7b4e66fc5e204d0685daf65c772bce1dc79d16`
- Gate 3: `reviews/tests/ADDRESS-REGISTER-IMPORT-001-v15.md` — `APPROVED`
- Prior full final review: `reviews/code/ADDRESS-REGISTER-IMPORT-001-v2.md` — `APPROVED` for the importer source published as the base commit
- Verdict: **APPROVED**

## Findings

No blocking or non-blocking findings.

## Delta review

The only production change from published commit `06e73000` is in `deploy/yii2/Dockerfile`:

- `libzip-dev` is added to the existing `apt-get install --no-install-recommends` layer;
- `zip` is added to the existing `docker-php-ext-install` invocation.

This is the standard official PHP image build path for the already required `ext-zip`. It changes no application, parser, schema, migration, persistence, authentication, provider, report, PDF renderer, entrypoint, user, port, package version, Composer requirement, or lockfile behavior. Apt cleanup and the existing nonroot runtime remain intact.

The executable e2e test is byte-identical to the published base: SHA-256 `4b3f205d94567b69aa2d7e92063764123984249cdad49359761315ef55a18a75`. It still builds the real standalone image, runs locked production Composer installation plus `composer check-platform-reqs --no-dev`, launches the finished image with `--network=none`, rejects legacy packaged dependencies, verifies the owned logo digest, invokes the production TCPDF renderer, requires a complete PDF, and finds registration/installer/engineer text in the rendered streams. No assertion, platform requirement, or runtime path was weakened.

The remaining changed tracked files are factual delivery metadata only:

- `docs/operations/address-register-import-delivery-20261118.md` records the complete first-CI failure inventory and corrective lifecycle;
- `docs/operations/current-delivery-goal.md` records the active image correction and next exact-source CI;
- `openspec/changes/address-register-import/tasks.md` records the same factual status.

The supplementary verification input and Gate 3 v15 review bind the existing A11 runtime dependency obligation and do not add or relax product semantics.

## CI failure inventory

GitHub run `44626585565` on base commit `06e73000` is fully inspected. Fast, unit, governance, integration1, and integration2 are GREEN. The complete failed-job inventory is:

- `e2e` job `135922029884`: one `REGRESSION_FAILURE`, `tests/Yii2/yii2_preopening_package_001_test.py`, caused by missing `ext-zip` in `deploy/yii2/Dockerfile`;
- aggregate `verify` job `135941967477`: downstream failure caused solely by e2e, with no independent regression.

The retained complete run log and triage inventory identify no second failure. All other source-owned PHP image consumers were audited and already install `libzip-dev` and build `zip`, so no adjacent Dockerfile correction is missing.

## Exact correction evidence

The unchanged command

`python3 tests/Yii2/yii2_preopening_package_001_test.py`

is exact-source `GREEN` in the Gate 5 package. The real image now passes Composer's platform check and the complete network-isolated native PDF rendering assertions. The record is `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795128746047331000-9f78fbef39024644956b9cd58927a934.json`.

`git diff --check` passes. No full local suite, live provider/FIAS request, real import, deployment, merge, or production action was performed for this review.

## Scope and remaining gate

This approval covers the Dockerfile correction, factual delivery metadata, supplementary verification input, Gate 3 v15 record, and this final delta review at candidate source `f9f1b93bac6e88ea546fabab936a163bbbe1c574545e2eac5a180a7363e25c5e`. The prior v2 approval continues to cover the unchanged importer application/spec/test implementation from the published base.

The first CI run demonstrates the defect and is not GREEN for the corrected source. A new exact-source GitHub CI run after correction commit/push remains mandatory. This review does not authorize merge, main publication, deployment, real paid calls, dev/prod import, or live provider/FIAS acceptance. Any further code, executable-test, Docker, dependency, schema, configuration, or behavioral spec change requires renewed independent review.
