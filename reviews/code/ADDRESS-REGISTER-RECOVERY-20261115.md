# ADDRESS-REGISTER recovery final review — 2026-11-15

Verdict: **APPROVED** for the bounded three-file production recovery only.

Reviewer: independent `/root/register_recovery_review`; no authorship of the recovery, original address-register implementation, tests, or deployment procedure.

Reviewed source:

- commit: `dc482209c12cde512c9001dc0a6529b020bc9903`
- prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261115T191302Z-af07a8ecd3/package.json`
- package candidate source: `e7eee1fe44784b1793691c9cd72762ebedb62f7ff31d63e534193d4d9bb17861`
- executable source: `c31cc5a7015e267630a2bf885546e028678c7ad61ffadc1b6f180ab91eb25edc`
- reconstructible snapshot: base commit above plus `snapshot/source.patch`, SHA-256 `83df7361c7f66003db8ac897860aab4e8fc06fbbb08f1540d60b8ee5003b8b21`

Reviewed runtime file hashes:

- `config/yii/web.php`: `e9306b8bcd203fe25f54a17717da12bf9c0fbea5faa9a490a66bae47f2d6a655`
- `config/yii/assets.php`: `432a9caf3e43ed36bc9cbc3fb5dfd08c8805ae6ab1efad7ca49546a5c69b37b8`
- `app/YiiRuntime/Controllers/PilotAssetController.php`: `70720884cd16b5e9450161083d1cd80c3ac1edd77129c07cb36d6bc91ed82bb1`

## Findings

No blocking or non-blocking findings in the agreed recovery scope.

The diff from published performance commit `4cdd3d5182204f9d05a6162c390ac27651a40295` is limited to the three declared runtime files. It restores the four address-register route keys, the JavaScript and CSS route allowlist entries, and the JavaScript and CSS asset-map entries. Comparison with the captured production-before files shows the same bounded additions. Existing performance, calendar, session, cache, completion-register, icon, and other asset registrations remain byte-for-byte intact outside those additions. `git diff --check` is clean.

The restored explicit method routes retain the original authorization and CSRF ownership in `AddressRegisterController`; the compatibility route keys do not bypass controller admission. The existing HTTP regression exercises guest `303`, authenticated `200`, denied `403`, native CSRF rejection, private per-account preferences, JavaScript/CSS `200`, adjacent object queue behavior, and read-only projections. The browser regression exercises the SHLZ drawer/filter/preference flow and reports no asset failures or business/schema writes. No test expectation changed in this recovery.

## Verification evidence

- `php tests/Yii2/yii2_address_register_001_test.php && php tests/Yii2/yii2_address_register_browser_001_test.php` — PASS in the real Yii/browser profile, exit `0`, executable source `c31cc5a7015e267630a2bf885546e028678c7ad61ffadc1b6f180ab91eb25edc`, retained in `~/.local/share/fmonitor-2/address-register-outage-20261115/focused.log`.
- `php tests/InstallationProcess/pilot_http_auth_001_global_calls_test.php` — PASS: complete global-call qualification.
- `php -l` on each of the three reviewed runtime files — PASS.
- Canonical local `make test` / `make verify` was not run, as required by the owner decision. Full exact-source CI remains deferred/UNKNOWN and is not represented as GREEN by this approval.

This verdict does not approve other pending PR 294 work, WIP corrections, deployment execution, production state, or the deferred full CI matrix. The proposed production operation must retain its stated CAS checks against the active container, override, and file hashes; reuse the current production PHP image and existing state/secrets volumes; replace only these three reviewed files; recreate only PHP; and reload nginx. Any source-byte change requires renewed review.
