# RUNTIME-HELP-001 — independent Gate 3 review

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`
- Base: `e4642a772856df9af0c37c47dc18d2ab9a1d0240`
- Candidate source: `1e07eb2824b98f54d8a9ed0e8d3d13ec7b626173267a94f872ff417eed1a74c6`
- Reviewer package: `20261116T134207Z-752bae6a59`, SHA-256 `a613f5e89ae49dde8d94d5375663f5a2c668505715f56ffe75dbab9ecba32dca`
- Verification plan SHA-256: `8668535c930e99c165a3c0a54ff209e70e9f67ed248c159fcfdc08cba3577b09`
- Contract SHA-256: `df1542e6e089816ff0d111debe2e1110ca995fbd5d9678315682a234326b7cc1`
- Focused HTTP test SHA-256: `a58bb3631342c272fa9c1434f27de0ac94840df8bdd8f073d5f42ffcc8d883b5`
- Production-container test SHA-256: `a63b6a8d489e0e48dde56fbed4a6ca0aa19807e83fb946e75676476f2ef2e5c2`
- Harness Gate 3 record: `APPROVED`, recorded `2026-11-16T13:42:29Z`
- Verdict: **APPROVED**

The reviewer authored neither the contract/tests nor the future implementation. Both mapped tests have exact-source `INTENDED_RED` records and no mapped test is missing.

## Symptom and seam

The deployed trial is healthy and authenticated application routes resolve, while `/help/` returns HTTP 404/`NOT_FOUND`. The focused fixture independently confirms `/help/`, `/help/index.html`, `guide.css`, and the existing page assets are absent from strict Yii routing even though their committed files are present. The correction therefore belongs in the Yii URL manager/controller/response seam, not Nginx, filesystem publication, authentication, database, or guide content.

The intended implementation remains narrow: a help controller may inherit `PilotController` to retain exact trusted-host validation, use GET/HEAD verb filtering, and serve only fixed committed guide files selected through explicit route rules. No arbitrary root/path parameter or static-server bypass is required.

## Acceptance coverage

The focused test starts the real Yii HTTP entrypoint without a database and verifies:

- `/help`, `/help/`, and `/help/index.html` return the exact existing HTML bytes;
- HEAD returns the GET content length and no body;
- HTML, CSS, JavaScript, WebP, and shared SHLZ CSS use the expected MIME/body;
- every `/help/` asset linked by the guide is reachable;
- CSP, `nosniff`, and no-session-cookie behavior are preserved;
- an unexpected Host is rejected;
- unsupported methods do not serve content;
- missing files, source/markdown/env paths, plain/encoded traversal, nested traversal, and double-encoded screenshot escape paths return 404;
- a random reviewer-owned symlink in the screenshot directory cannot disclose an outside sentinel and is removed in `finally`.

The production acceptance extends the existing fresh-main clone and plain-Make workflow. After a real healthy Docker deployment and owner login, it fetches the exact guide HTML and every linked `/help/` asset through the production Nginx/PHP containers, while retaining replay, stop/start, session/data, receipt, jobs-disabled, and guarded cleanup assertions.

## RED evidence

- Focused real-Yii HTTP: `1794836207447499000-da39a0b6e14040a29ed6241ae5886bbb`, command blob `a58bb3631342c272fa9c1434f27de0ac94840df8bdd8f073d5f42ffcc8d883b5`
- Fresh-clone production containers: `1794836299329850000-4c90792c1c2344fd9f0fb7578e0d4fe4`, command blob `a63b6a8d489e0e48dde56fbed4a6ca0aa19807e83fb946e75676476f2ef2e5c2`

The live RED deploys successfully and fails specifically when GET `/help/` returns 404. It is not a Docker, login, dependency, or setup failure.

## Approval boundary

No Gate 3 findings remain. The contract and tests are **APPROVED** for separate minimal implementation. Guide HTML/CSS/JS/images must remain byte-identical; existing trusted-host, security headers, authentication, CSRF, CSP, and static asset routes must not be weakened. Focused GREEN, the real production-container GREEN, independent final review, and exact-source CI remain required. An already installed trial must receive this through `make update` with new reviewed source/image identity; ordinary `deploy` must not bypass update semantics.
