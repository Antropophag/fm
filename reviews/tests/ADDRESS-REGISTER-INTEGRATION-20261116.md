# ADDRESS-REGISTER-INTEGRATION-20261116 — retrospective test/spec review

## Review identity

- Reviewer: independent `integration_test_review`; authored no production code or tests in this candidate.
- Review type: retrospective Gate 3 test/spec coverage review for the already-production address register integration.
- Base: `7c6b8e14c0a5c8553a73320d12970107cb1debe5`.
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T064231Z-5d06af1f3b/package.json`.
- Package candidate/exact source: `22ffcac359d028c8b6d03137419fdcc413ed7a945945425a50b7f83e92e1d6a2`.
- Package executable source: `a54e3df5beb8857e7046df3fb0ebfaf4cbd7ece470423f66bacb0b826e4c59e9`.
- Contract: `specs/ADDRESS-REGISTER-001.md`, SHA-256 `5183de6b7137ad350ebc94064bc4fe86198e5fea7d36a31cf67e4f1847076f52`.

This is not preimplementation approval. The address register was delivered production-first and the integration tests were reviewed afterward. The delivery harness rejected a Gate 3 role package because qualifying preimplementation RED was not available for every test. That historical fact remains explicit and is not converted into GREEN by this review.

## Verdict

**PASS — retrospective test/spec coverage is sufficient for PR and exact-source CI.**

No open P1 or P2 test/spec finding remains in the reviewed candidate. This verdict approves the coverage and expectations of the bounded address-register integration candidate. It does not approve merge independently of the required final review and full exact-source GitHub CI, which remain pending/UNKNOWN at this review point.

## Acceptance coverage

- **A1 authorization and transport:** `tests/Yii2/yii2_address_register_001_test.php` covers guest redirect and return URL, authenticated reader, inactive and no-permission actors, permission revocation, GET/HEAD behavior, method rejection on both routes, native CSRF, server-owned actor identity, no-store responses, and safe 503 behavior without exposing corrupt private bytes.
- **A2 read projection:** the HTTP/database test now asserts one exact row containing every projected field, including effective address/registration/comment/floors, entrance/factory values, all technical snapshot values, planned-date precedence and fallback, canonical actual start/finish, corrected PTO/declaration dates, status/progress, control engineer, installers, and absent values. Native applied composition and pending replacement precedence remain exercised through the public owners.
- **A3 query and pagination:** malformed scalar/query/page inputs, literal `%`, `_`, and `\\`, stage membership and projected stage values, 50-row bounds, exact address/entrance/id ordering across the page boundary, rendered filter-preserving pager links, and browser search reset/preservation are observed.
- **A5R/A9/A10 presentation:** the browser flow checks all 22 defaults with registration number first, responsive widths, contained single viewport, drawer behavior, column search/reorder/reset/cancel/save, sticky registration/header, full 1/2/5-person structured crews, long identifiers, empty configuration, mobile safety, and persisted personal order across reload and a new browser context.
- **A5R failure states:** intercepted 422, 503, and network failures keep the drawer modal and retain the draft/preview, show an error without a success notice, and permit a real retry. A stale-tab 409 remains independently covered.
- **A6/A7/A11 preferences:** the seam test covers exact v2 shape, actor isolation, authorization, validation, byte preservation, immutable history, replay, stale revisions, a real two-process first-save race, permissions, v1 read projection and first explicit v2 append. The added table matrix rejects malformed/unknown v1 and v2 schemas on both read and save without writes, while filesystem cases cover oversized, directory, symlink, actor-path, lock-path, and snapshot-path failures without modifying external targets.
- **Shared registrations:** `tests/Yii2/yii2_main_navigation_001_test.php` now owns exact route, label, order, group membership, icon, active state, permission removal, and content-derived asset versions. The address HTTP test inventories the mutable assets actually emitted by the page and checks versioned immutable responses. `.quality-graph/verification-policy.json` maps the address owner, asset controller, navigation, factory, route/assets configuration, and icon, while existing feedback and web-owner capabilities retain their own verifiers and point to the address consumer.

## Evidence inspected

- `tests/AddressRegister/column_preferences_001_test.php`, SHA-256 `5bef2b8e8400988aab3a1a5715f312b7622781fd9acd04adf56a6df56640e9f5`.
- `tests/Yii2/yii2_address_register_001_test.php`, SHA-256 `ddad5f3ec9037d581a6b44898beb4d81246a4b903a128da208d72e974e1df84b`.
- `tests/Yii2/yii2_address_register_browser_001_test.php`, SHA-256 `07c2081e31f433ab174afecb91f8d7e2473446c1d193398b7539f038bb639456`.
- `tests/Yii2/address_register_browser.mjs`, SHA-256 `e2e3178a24f0e5f78885dcb7eef131839a8b2f1b251bab6f28ee439e46f80b51`.
- `tests/Yii2/yii2_main_navigation_001_test.php`, SHA-256 `5871e348ebe40045e73eebb2edf1fb3e9cf0df00fd4c618dfd6b300bc55990bf`.
- `.quality-graph/verification-policy.json`, SHA-256 `9278766a9dc21bb21b2ce827851d1f682a68e389e74c5cd0c32a4903115ece90`.

Private focused evidence at `~/.local/share/fmonitor-2/address-register-integration-20261116/` records an intended RED for the actual mutable-asset inventory (`shlz.css` lacked a version) followed by a focused five-test PASS after the bounded asset-bundle correction: address HTTP/read projection, main navigation, address browser, feedback HTTP, and feedback browser. The reviewer inspected this evidence but did not rerun the suites. The RED is evidence for that bounded compatibility correction only; it is not represented as preimplementation RED for the historical feature.

Full local `make test` / `make verify` was not run, in accordance with the owner decision. Full exact-source GitHub CI and the separate independent final review remain mandatory before merge.

## Search-contract correction re-review

A later independent code review found that A3 limits `q` to the four public fields address, registration number, factory number, and entrance, while the reader additionally matched the internal `fm_maintable.id`. The bounded correction was reviewed against package `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T064758Z-f671439ff0/package.json`, candidate/exact source `5e7435fafb9664287d5bca2893817fde685c2b9c79ae462b892b55502d5e5478`, executable source `ce7440c3db206516038de9546995bb580258ca58aa3031daf345e611dea5aedc`.

The new public-reader assertion creates mirror-only object `999001`, whose visible searchable fields contain no `999001`, and requires `read(9101, '999001')` to return no rows. Private evidence `search-red.log` records that assertion failing against the previous reader because the object ID matched. The implementation delta removes only `CAST(m.id AS CHAR) LIKE :q`; the SQL retains effective address, effective entrance, effective registration number, and effective factory number as its four `LIKE` predicates, with the existing literal escaping unchanged. The existing `UNSTARTED`, `%`, `_`, and `\\` cases remain in the same test and continue to prove visible-field search and literal wildcard/backslash handling.

Private evidence `search-green.log` records PASS for both the HTTP/read-projection and browser suites at source digest `ce7440c3db206516038de9546995bb580258ca58aa3031daf345e611dea5aedc`, equal to the prepared package executable source. No other runtime or test behavior changed in this correction.

**Delta verdict: PASS.** The oracle is specific to the contracted exclusion, demonstrated RED against the prior behavior, and the correction narrows search exactly to A3's four fields without weakening the literal-search coverage. The earlier retrospective PASS remains in force, subject to the same pending full exact-source CI and separate final review requirements.
