# PECL-DIRECT-FALLBACK-001 — independent Gate 3 review

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`
- Base: `29e4fd90e8bad26802e20a9ecb5393f28eb6bfc5`
- Candidate source: `752fa1eb42834d233f9949946d533a89a57c5731db4b684684476b26837a969d`
- Reviewer package: `20261116T122150Z-e81068d951`, SHA-256 `1110f7a65a4106233a199cb21156fe51019d382d6ff6bf384fd1c435e3551eaa`
- Verification plan SHA-256: `fb8ca9f62ce521e55adea3839ed8f6acfef43963d3c2bc6a549459fe041e7582`
- Contract SHA-256: `e6521df2b8b37baf12dcb945351c9ab3f740576578597ad57b8aa28fdcb3ac12`
- Unit test SHA-256: `adf3c629486cf57becb22b2b9f8769e56cb0b591d0f1c8ed249e94939b8a29a1`
- Live test SHA-256: `568ce81c44614b92928bf96fc47ddaeac96e1b05f6876358318b873781ca4b4a`
- Harness Gate 3 record: `CHANGES_REQUESTED`, recorded `2026-11-16T12:23:27Z`
- Verdict: **CHANGES_REQUESTED**

The reviewer authored neither the contract/tests nor the future implementation. Both mapped commands have fresh exact-source `INTENDED_RED` records and no mapped test is missing. The private user log and the isolated baseline establish the same boundary: native PHP extensions and Microsoft ODBC prerequisites complete, then PECL metadata/download access fails. The proposed seam correctly remains inside the image build and does not involve database credentials or a live service.

## Findings

### T1 — BLOCKING: the unit oracle authorizes an arbitrary archive digest instead of the reviewed Microsoft pin

The contract fixes version `5.13.0`, commit `fd6d71d987dddcea413d9fc94234191cb82e4a28`, and SHA-256 `7ea3eeef0d615c884628285a647f9cb29db4e206be2cd5b418c10360085cf04a`; it also says reviewed pins are never automatically replaced. `pecl_direct_fallback_001_test.py:50-62` creates a synthetic tarball, computes its new digest, and supplies that digest to every successful installer call. The invalid-input matrix rejects malformed values and version `5.13.1`, but never rejects a different well-formed 40-hex commit or 64-hex digest. An implementation that accepts any caller-selected archive/digest pair therefore passes the entire unit suite, despite the no-upgrade/fixed-source contract.

Bind the production installer to the exact reviewed version/commit/digest from the repository pin source and add zero-effect cases for valid-looking wrong commit and digest. The unit build fixture may use a dedicated test transport/hash seam, but it must not redefine the approved production identity. Alternatively, if arbitrary externally reviewed pins are intentionally supported, revise the contract's fixed-source/no-upgrade language before implementation and add an explicit authority boundary; the current candidate says the opposite.

### T2 — MAJOR: primary success can pass without proving both exact modules and PDO registration

The `php` shim at lines 37 and 99-103 returns success for every command unless `VERIFY_FAIL` is set. Tests only require that some PHP invocation occurs. Likewise, the enable assertion checks ordering but not that `docker-php-ext-enable` receives both `sqlsrv` and `pdo_sqlsrv`. A primary-path implementation that enables one module and executes `php -r 'exit(0);'` passes. The real Docker case proves the fallback path's two versions, function, and PDO registration, but deliberately blocks PECL and cannot close the primary branch.

Make the shims inspect exact argv: permanent enable must name both modules once, and the PHP verification expression must require both loaded versions equal `5.13.0`, `sqlsrv_connect`, and PDO `sqlsrv`. For `VERIFY_FAIL`, require nonzero and prove the permanent enable step is not treated as successful publication. Retain the existing full-CI normal-runtime check as broader evidence rather than the only executable primary oracle.

## Confirmed properties

The candidate otherwise maps the reported symptom well. It requires PECL first with a bounded wrapper, direct mode skips PECL, the fallback URL is restricted to the official Microsoft codeload path, TLS bypass is forbidden, connect/total timeouts are present, and download/hash/build failures do not reach enabling. Both extension directories must build/install from one extracted tree, temporary work is removed on every tested outcome, primary success avoids GitHub/source compilation, fallback emits a visible marker, and runtime Docker integration uses repository pins rather than hidden latest selection.

The real Docker acceptance adds an uncached probe, maps `pecl.php.net` to loopback, requires fallback output, compiles against PHP 8.5, and verifies both exact extension versions, the SQLSRV entrypoint, and PDO registration under `--network none`. Cleanup targets only its random image tag.

## Gate consequence

Gate 3 is **CHANGES_REQUESTED** for T1 and T2. Correct the tests and preserve fresh exact-source intended RED evidence before implementation. No full local suite or live production access is required for rereview.

---

## Gate 3 rereview — corrected authority and verification oracles

- Date: `2026-11-16`
- Candidate source: `71344b89f7964096c237b88bac8ef62fb28e27b8652d42002ca85d25793b96a7`
- Reviewer package: `20261116T122708Z-34508e7bb3`, SHA-256 `1d33adf1c1f9c15d63260f4a98a9901c3d936f6bf9f00976824aee006b4bb9c8`
- Verification plan SHA-256: `52c610bc47e66cb803b3aff759af2bb1cc417e9f880b490f1c5809f02c8470b9`
- Contract SHA-256: `3288906e8c230af9765d0c7827701822f7be7ae9659218998b824427712ab69d`
- Unit test SHA-256: `a20fbf91909621962dc05266fd72cd8aff63978cab8718ae3fcd7ad27959bb6b`
- Live test SHA-256: `568ce81c44614b92928bf96fc47ddaeac96e1b05f6876358318b873781ca4b4a`
- Evidence: two fresh exact-source `INTENDED_RED` records; no missing mapped tests
- Harness Gate 3 record: `APPROVED`, recorded `2026-11-16T12:27:37Z`
- Verdict: **APPROVED**

### T1 disposition — resolved

The contract now states the actual trust boundary: `tools/delivery/dependencies.env` and the reviewed generated Dockerfile own the production tuple; `install-sqlsrv` is a deterministic integrity/build mechanism over explicitly trusted inputs rather than a second authorization catalogue. This avoids duplicate hardcoded pins while retaining reviewed upgrades through the generated-source workflow.

The runtime integration test asserts the canonical values are exactly version `5.13.0`, Microsoft commit `fd6d71d987dddcea413d9fc94234191cb82e4a28`, and SHA-256 `7ea3eeef0d615c884628285a647f9cb29db4e206be2cd5b418c10360085cf04a`. It requires the template placeholders and rendered literal arguments for all three, rejects `ARG`/`ENV` override seams, and proves the real renderer `--check` rejects an isolated rendered commit mutation. Synthetic archives remain appropriate for unit integrity mechanics; the real Docker test consumes the official tuple and archive.

### T2 disposition — resolved

The command shim now requires `docker-php-ext-enable` to receive exactly `sqlsrv` and `pdo_sqlsrv`. It inspects the PHP `-r` verification program for both extension names, loaded/version checks, `sqlsrv_connect`, PDO driver enumeration, and the expected `5.13.0` value. Primary success explicitly requires both enable arguments; verification failure propagates on primary and fallback branches. The real fallback container executes the same behavior against compiled modules, while existing full CI retains the real primary-runtime qualification.

### Approval boundary

No Gate 3 findings remain. The corrected contract and tests are **APPROVED** for separate minimal implementation. Expectations remain frozen; focused GREEN, actual blocked-PECL Docker acceptance, independent final review, and exact-source full GitHub CI are still required. This verdict does not authorize live deployment or a driver-version change.
