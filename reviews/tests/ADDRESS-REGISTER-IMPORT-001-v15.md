# ADDRESS-REGISTER-IMPORT-001 — independent Yii image dependency Gate 3 review v15

- Date: `2026-11-20`
- Reviewer: separately assigned `/root/import_gate3_v8_review`; reviewer authored none of the contract, executable test, production Dockerfile, CI result, or RED evidence
- Reviewed commit/base: `06e73000f325ceff154b036523d18ecbf9c4985e`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261119T223753Z-eb6abe4003/package.json`
- Candidate source: `7e44903902f2fee7922bf29f007ccc7c8141801f5d7148e45adf508106cfbc1b`
- Executable source: `2344541f938ec7214b78d0817a3712fb0a0e2455bcfd21197efef9c31e8b9cc1`
- Snapshot patch SHA-256: `94f487d7443b6fa065a8944d1b5d17b024b7d6a3e8a09687c101d5292592b421`
- Prior final review: `reviews/code/ADDRESS-REGISTER-IMPORT-001-v2.md` — `APPROVED` for its exact pre-CI source
- Verdict: **APPROVED** for the bounded test/contract delta

## Findings

No blocking or non-blocking findings.

## Scope and reachability

The supplementary input maps the existing `tests/Yii2/yii2_preopening_package_001_test.py` to the already approved A11 runtime-dependency obligation. It adds no product behavior, test bypass, ignored platform requirement, alternate renderer, or weaker acceptance. The planned production correction is limited to `deploy/yii2/Dockerfile`.

The unchanged test builds the real standalone Yii production image from that Dockerfile. During the image build, Composer installs the locked production dependencies and runs `composer check-platform-reqs --no-dev`; the current build fails there because root `composer.json` requires `ext-zip` and the image lacks it. This proves the fixture reaches the exact missing runtime dependency rather than a synthetic source check.

After a successful build, the same test runs the image with `--network=none`, invokes the production assignment-order PDF renderer, rejects packaged `rapid-pilot`/PilotHttp dependencies, verifies the owned logo digest, requires a complete PDF envelope and EOF, decompresses PDF streams, and finds the expected registration, installer, and engineer text. Adding zip support cannot make the later renderer assertions vacuous.

## Consumer inventory and correction boundary

The importer uses `ZipArchive`, and `ext-zip` is an explicit Composer platform requirement. The source-owned PHP image inventory was checked across the root image, runtime image, delivery templates/generated focused and verification images, and the standalone Yii image. Every other PHP consumer already installs `libzip-dev` and builds the `zip` extension. Only `deploy/yii2/Dockerfile` omits both.

The appropriate correction is therefore the ordinary official-PHP-image pattern in that single Dockerfile: install `libzip-dev` in the existing apt layer and add `zip` to the existing `docker-php-ext-install` invocation. Package versions, Composer requirements, lockfile, production code, parser behavior, PDF renderer, entrypoint, user, and networking remain unchanged. Ignoring the Composer platform requirement or removing `ext-zip` would violate the reviewed contract.

## RED and CI evidence

The prepared package records exact-source `INTENDED_RED` for:

`python3 tests/Yii2/yii2_preopening_package_001_test.py`

The real Docker build reaches Composer and reports only that root requires missing `ext-zip`. Root reproduced the same RED twice. The private record is `/Users/antropophag/.local/share/fmonitor-2/address-register-import/yii-image-gate3-red-record.json`; complete logs remain outside Git.

For GitHub run `44626585565`, the complete e2e failed-job inventory contains one `REGRESSION_FAILURE`, this same test. Fast, unit, governance, and integration1 are GREEN. Integration2 was still running when this Gate 3 review was requested, so the complete CI failed-job inventory remains pending and CI is not GREEN. No correction push may occur until integration2 completes and every failure is inspected as required by project policy.

## Lifecycle boundary

The prior v2 code approval remains limited to its exact reviewed source. This Gate 3 approval authorizes a separate executor to make the single reviewed Docker dependency correction. The corrected exact source must make the unchanged e2e test GREEN and receive an independent final delta review before push/publication.

This verdict does not approve CI, publication, merge, deployment, live provider/FIAS behavior, real import, or paid requests. No production code edit, test edit, full local suite, live/network provider request, commit, or push was performed by the reviewer.
