# PECL-DIRECT-FALLBACK-001 — independent final review

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`
- Base: `29e4fd90e8bad26802e20a9ecb5393f28eb6bfc5`
- Reviewed HEAD: `3ec7976ad30978e948d7c15a75fd9a4d525aedc3`
- Exact candidate source: `38b834371d18cb50e65e0627e58fed9f9534236139d1662f9b27c0996e1e814b`
- Executable source: `8a8b1b5bf10417625611355dea09c2578d61ba065046621e2e81a5fa1475a779`
- Reviewer package: `20261116T123337Z-b61807cfba`, SHA-256 `63bc9db254669b9b6df18198075c54a436d69c5f8a690ebc12055c30ded595bd`
- Verification plan SHA-256: `f3a4bb86c1da67906c3e48bf327463a6fe1ac10cfb7545670c3a3a566be735ef`
- Harness final record: `APPROVED`, recorded `2026-11-16T12:33:56Z`
- Verdict: **APPROVED**

The reviewer authored neither the root contract/tests nor the separate executor implementation. Gate 3 first returned two oracle/authority findings, then approved the corrected contract and tests. Final code inspection found no implementation defects.

## Implementation assessment

The change is limited to the reported image-build failure boundary. The generated runtime Dockerfile replaces the direct `pecl install` line with one repository-owned installer and passes literal reviewed pins from `tools/delivery/dependencies.env`. No runtime environment or build-argument override is introduced, no driver version changes, and application/database behavior is untouched.

The installer:

- validates complete, unique version/revision/digest/mode inputs before effects;
- attempts exact `sqlsrv-5.13.0` and `pdo_sqlsrv-5.13.0` through PECL under a bounded timeout;
- skips all source-network work on primary success;
- on primary failure or direct mode, prints the safe `SQLSRV_SOURCE_FALLBACK` marker;
- downloads only the Microsoft `msphpsql` codeload HTTPS commit URL with HTTPS-only original/redirect protocols, bounded retry/connect/total timing, and no TLS bypass;
- checks the reviewed SHA-256 before extraction or compilation;
- validates the required source tree and both upstream version macros;
- builds and installs both extensions from the same verified tree, replacing any partial primary outcome rather than accepting a mixed installation;
- enables both modules together and rejects unless both runtime versions match, `sqlsrv_connect` exists, and PDO advertises `sqlsrv`;
- cleans the private workspace on success, failure, and handled signals.

The renderer key grammar change from letters/underscore to letters/digits/underscore matches the existing dependency-manifest parser and is necessary for `SQLSRV_SOURCE_SHA256`. It does not broaden value syntax. Generated-source checks bind the template and rendered Dockerfile to the reviewed version, commit, and digest.

## Exact-source evidence

The formal package contains two authoritative source-bound GREEN records with no missing mapped tests:

- `python3 tests/Deployment/pecl_direct_fallback_001_test.py` — record `1794832314125115000-8c52a5c6f00e4163bfd69d221426f9f8`, command blob `2d3a5f79d2b4eaf354296354911f20f104ef8c85348156eef6cbc37db3e37421`
- `python3 tests/Deployment/pecl_direct_fallback_live_001_test.py` — record `1794832304928917000-52f52a0646ee4f0c86d9fc5c578fe3d9`, command blob `568ce81c44614b92928bf96fc47ddaeac96e1b05f6876358318b873781ca4b4a`

The live test uses actual PHP 8.5 Docker with `pecl.php.net` mapped to loopback, forces an uncached installer layer, observes the fallback marker, compiles from the official reviewed archive, and under `--network none` proves both exact extension versions, the SQLSRV entrypoint, and PDO registration. It uses no SQL Server, ERP, mail, production credentials, or live service.

Supporting planned checks are GREEN:

- change verification `1794832404624953000-e3748d68a42e4cdab6cd98ccd0ded0d6`
- verification CI contract `1794832404624954000-e477cd47c34b4c0da68ad425697f288d`
- generated dependency render check `1794832404624998000-9fd553723bde4d6ab114f7c3c88a6c73`
- architecture guard `1794832443563352000-f87b87f81233471eb3ec10f7bd33968b`

The private baseline with PECL mapped to loopback remains a real nonzero reproduction of the user's endpoint-unavailable failure class. No claim is made about the corporate firewall's exact DNS/proxy/routing cause.

## Final boundary

Gate 5 is **APPROVED** for the exact candidate and executable source above. No code findings remain. Full exact-source GitHub CI and tested-main publication are still pending; the known missing automatic admission adapter remains `UNKNOWN`. This verdict does not authorize live production deployment or represent those pending states as GREEN.
