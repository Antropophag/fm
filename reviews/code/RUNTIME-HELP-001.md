# RUNTIME-HELP-001 — independent final review

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`
- Base: `e4642a772856df9af0c37c47dc18d2ab9a1d0240`
- Reviewed HEAD: `8b333809c2e2621de91c232f005ad3f8472b1461`
- Exact candidate source: `a5b97c45d1e6d9b27ed7513787b8ba7c83fd7467dd9afcce9cd971862effa178`
- Executable source: `7cc5c534701f9f48bf755efc7a320432dd7061120941e1b59a3aee9ab93a77bb`
- Reviewer package: `20261116T135210Z-fdf0d35e9c`, SHA-256 `bc9aba9993634c0ef6e43f7ce1259d8cb4af5530d5c57205d2421445b863109e`
- Verification plan SHA-256: `407c7e570b4b98226235adb52d38cfd4515e4cfadd5ca4b72f7a880b884ae9ea`
- Harness final record: `APPROVED`, recorded `2026-11-16T13:52:31Z`
- Verdict: **APPROVED**

The reviewer authored neither the root contract/tests nor the separate executor implementation. Gate 3 approved the complete route/security expectations before implementation. Final code and the strengthened allowed-filename symlink oracle were reviewed independently.

## Implementation assessment

The correction is limited to two runtime files:

- `HelpController` inherits `PilotController`, retaining the exact trusted-host check and normal Yii request/response lifecycle.
- `config/yii/assets.php` adds explicit GET/HEAD rules for the three page aliases, guide CSS/JavaScript, and the fixed committed screenshot names.

The controller has a second fixed filename-to-MIME map. It derives the guide root internally, resolves candidates under that root, requires a regular non-symlink candidate whose real path stays below the root, reads exact committed bytes, and returns a raw Yii response with explicit MIME, length, public one-hour cache, `nosniff`, and same-origin resource policy. Unsupported or absent paths cannot select an arbitrary filesystem root. No Nginx/static bypass, raw PHP entrypoint, directory listing, authentication change, CSRF exception, CSP expansion, guide edit, database access, or domain behavior was introduced.

The existing shared response hook still closes sessions, removes powered-by disclosure, applies CSP/referrer/frame/permissions/opener headers, and strips bodies for HEAD while retaining the GET content length. The guide remains public as it was designed; no session cookie is created to view it.

## Exact-source evidence

The formal package contains two authoritative GREEN records with no missing mapped tests:

- `python3 tests/Yii2/runtime_help_001_test.py` — record `1794836861848321000-985959852e7449369e2b2ff643f44fb8`, command blob `da0db5fedd2673dff1331410cd8e099bf42d2ee34a29c57f6d3ffdc509560813`
- `python3 tests/Deployment/production_make_quickstart_live_001_test.py` — record `1794837013368785000-af3a40233be647f5a5788d0b197eac5a`, command blob `a63b6a8d489e0e48dde56fbed4a6ca0aa19807e83fb946e75676476f2ef2e5c2`

The focused fixture uses exact private copies of application/config/public source and shared readonly vendor dependencies. It exercises aliases, exact bytes, MIME, GET/HEAD length/body, CSP and security headers, no cookie, wrong Host, unsupported methods, unknown/source/traversal paths, every linked guide asset, and shared SHLZ CSS. Its symlink case replaces the actually allowlisted `01-login.webp` only in the private fixture, verifies an outside sentinel remains inaccessible with 404, and restores fixture bytes in `finally`; repository source is never mutated.

The production acceptance performs the real fresh-main clone, source image build, clean deployment, owner login, and then fetches exact guide HTML plus every linked help asset through production Nginx/PHP containers. Its prior installation/replay/stop-start/session/data/receipt/jobs-disabled and guarded cleanup assertions remain.

Supporting planned checks are GREEN:

- jobs Compose `1794836870633331000-0257625bd7d54ed78351afce3128165e`
- change verification `1794836928121235000-2403c13133dd45e79b11fa4e8f6a5745`
- architecture guard `1794836966192592000-121af25fedce4d098045c59e774b4a81`

The original focused RED `1794836207447499000-da39a0b6e14040a29ed6241ae5886bbb` and live RED `1794836299329850000-4c90792c1c2344fd9f0fb7578e0d4fe4` remain the exact preimplementation evidence: healthy Yii/production deployments returned 404 specifically for the missing help route.

## Final boundary

Gate 5 is **APPROVED** for the exact candidate and executable source above. No implementation findings remain. Exact-source CI and tested-main publication remain pending, and the missing I1 automatic admission adapter remains `UNKNOWN`; none is represented as GREEN or bypassed. An already installed trial must receive the new reviewed source/image through `make update`, preserving its data, users, keys, and normal update safeguards. This review does not authorize an unreviewed live production change.
