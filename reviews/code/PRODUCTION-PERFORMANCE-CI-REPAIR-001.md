# PRODUCTION-PERFORMANCE-CI-REPAIR-001 — independent final and predeploy review

Reviewer: `/root/perf_review` (independent; no product or test authorship)

Verdict: **APPROVED for implementation, focused exact-source verification and scoped predeploy rollout**.

This approval does not claim the next full GitHub CI run, PR admission or merge.

## Binding

- Candidate commit: `dfd53529bbd15cbb4c1532d249e2ba220b661612`
- Candidate source: `6aa17d6b14f82a6f776d3fb225378e7e69f693f293ded6005131ccb5fcad9e76`
- Executable source: `18ee54ff3dcac7018a3adb5463db7d1d0b1b7f5ceba2bbaa4b3ad471bfec4df3`
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261115T192608Z-90df1048ca/package.json`
- Verification plan SHA-256: `751a01694833813c7c779c9b6299a64a7e4867be7035a9da2120785822931b70`
- Evidence-record aggregate SHA-256: `b7f2cdb958a586b5a603597d0ff78a2d0142fc6026d5fe8c074aa1f0486c386c`

The formal package contains 16/16 GREEN exact-source records. They cover photo orientation/cache behavior; production runtime browser and nginx/FPM cutover; canonical integration runtime; sensitive offline policy; development setup and registered focused bootstrap; legacy identity link; navigation and pinned icons; operational dashboard; OTIZ decision preview, filters and v2 acceptance; SHLZ selects; sidebar state; and user-access edges.

## Implementation findings and closure

No unresolved finding remains. The six UI/runtime corrections are bounded:

1. CSS versions now use the exact CSS content digest; JavaScript versions continue to include dependency digests.
2. Fixed public assets with the existing one-year policy are explicitly immutable, while mutable CSS/JS still require an exact content version.
3. OTIZ recovery forms are populated server-side and remain usable without JavaScript; enhancement safely converts an already-open nonmodal recovery dialog to `showModal()` without double-open failure.
4. Standalone no-JS financial editors expose the same preview actions while retaining the exact POST command fields.
5. Decision editor headings identify the exact person and object in both recovery and enhanced flows.
6. On-demand user-editor role chips retain their audited remove icon and now show the escaped assigned-role name beside the exact action.

Finance, authorization, no-JS fallback, cache freshness and exact-source oracles remain intact. The previously approved orientation implementation and production verification are unchanged.

## Scoped deployment

The rebuilt deployment artifact is allowlisted to exactly these six product files:

- `app/YiiRuntime/Assets/AssetVersion.php`
- `app/YiiRuntime/Assets/otiz.js`
- `app/YiiRuntime/Controllers/PilotAssetController.php`
- `app/YiiRuntime/Views/otiz-calculation-v2.php`
- `app/YiiRuntime/Views/otiz-editor.php`
- `app/YiiRuntime/Views/user-editor.php`

Deployment binding:

- Patch: `b8dad3c0e8680841ea8d76756319bef526ef456dd05ba075b40e0fec32746a40`
- Candidate image: `sha256:da524d95b25bb3a48843fa0588e6a25789a9b4c7bf00e021fe0dd23401ffbe12`
- Rollback image: `sha256:754a8db214788b85d64f70c768640507fe48324218c1048688f8ecb588e9d03a`
- Image manifest SHA-256: `8a5b31302ec9621d055d2b9021ce2fa659d3f497c3735eec84adae1ac3d79c02`

Every deployed file hash matches committed candidate `dfd53529`. Fresh CAS binds the current host, actual PHP runtime, containers and current orientation/GD image. Candidate and rollback images layer captured actual runtime over that image, preserving vendor, GD and the orientation repair. The persistent PHP override requires the exact captured current image prefix and preserves every other Compose byte. Host/runtime verification, unrelated service-ID checks, canonical startup attestation, readiness/health wait, nginx reload, public probes, restoration of nonconflicting paths and structured rollback remain the previously approved flow. No-action rollout/rollback generation and Python syntax checks passed during review.

Explicit predeploy approval is granted for this bound six-file PHP-only switch. Success still requires post-rollout runtime hashes, health and service-ID verification plus live authenticated/no-JS financial recovery, exact user role label/action, fixed-asset cache and orientation smoke checks. The next full GitHub CI matrix remains a separate required admission step.

## Post-approval preservation finding and verified recovery

The six-file rollout completed and its intended UI checks passed, but the deployment approval above missed a material parallel-work preservation defect. Production already contained separately reviewed address-register additions outside this branch. The original performance rollout replaced three shared route/asset files and removed their registrations; another agent restored them. The later six-file rollout then replaced the complete shared `PilotAssetController.php` from this branch and again removed the two address-register CSS/JS map entries. File-level CAS proved that the expected whole file was installed, but it did not prove preservation of additive foreign semantics inside that allowlisted file. These were defects in this delivery, not in the parallel agent or its unfinished work.

Recovery was separately reviewed before execution. It was an external overlay over the captured actual production controller hash `5a33d718…`, adding only `address-register.js` and `address-register.css` while preserving the performance immutable-cache expression and every other byte. No commit or unfinished adaptive-column source from the foreign branch was imported into this PR. A disposable coexistence checkout used completed foreign commit `aeaca219cbe842b0c176c30e4e32da5a78d38ab7`, the committed performance product files and the two-entry overlay; the existing real HTTP/native and SHLZ browser suites were GREEN at source `7819c9c341640a921e6d5aa584ad6e090e8cb096bfd6238a59a995f7ed6be0ae`.

The recovery deployment bound:

- Patch: `01ba464936f4a552a7359f968689823272f3ad2de93bb982b60763d6cca43450`
- Candidate image: `sha256:87dc46ab98c36e657309c27d14956009dfffd7b56a6d03bc2881a949d6b3c504`
- Rollback image: `sha256:7af407e0f255f3919024bab155aa653f9a0147f26597027fe08f557483863e04`

Candidate and rollback images each enumerate exactly 1,050 runtime files including composer manifests. Offline image proof shows the candidate differs from the captured baseline only at `PilotAssetController.php`; rollback differs nowhere. Rollout rechecked the complete runtime key set and hashes, exact container IDs/images, host hashes and Compose override, then required complete post-switch equality with the expected candidate. It completed at `2026-11-15T19:39:19Z`; only PHP was recreated, nginx reloaded, PHP/web/jobs/database were healthy, gateway running, and unrelated service IDs and private state were preserved.

Live recovery evidence is VERIFIED: address-register guest 303 and authenticated 200; emitted content-versioned CSS/JS GET and HEAD 200 with correct MIME, `nosniff` and immutable cache; bare/wrong versions non-immutable; filter/settings drawers and column search worked; cancel/reload preserved preferences; no write request or JavaScript error occurred. Repeated CI-repair smoke verified five visible role labels, six financial dialogs, calendar fragments, asset caching, session concurrency and the protected oriented thumbnail. The six-photo native-orientation comparison remained verified with unchanged original SHA and guest denial. A no-JS invalid-money preview returned 422 with exact fields/operation identity and calculation header/history unchanged; no save command was issued.

Evidence SHA-256:

- Preservation live JSON: `092cf5ca229db515a04501485c306193af3a52cd9ab8927111c45411fc648f8a`
- Post-preservation CI-repair smoke: `597b0cd16d78c8e7a3e837745d1085747a747ee723f443d3bf7057907c664018`
- Orientation recheck: `43e0da19fe96a803b870a5434a1f4a07786fbff9a9b44e559b3c611072879a05`
- No-JS financial preview: `7ce5ec3290a4ee0dd4ec0b54bde228fe3e94043c8a8891196a9eabfa99df6a72`
- Recovery rollout receipt: `c303b013f1e051cf3677cef761da017680254ebf7edd6a41d85f5a7765b59b76`

The recovery is independently approved and verified. Future deployments touching a shared production file must apply the reviewed semantic delta over a fresh capture of actual runtime and prove the complete candidate and rollback trees; whole-file replacement from this branch is not approved when production contains separately owned additions. The foreign adaptive work remains outside this delivery and must not be imported or published here.
