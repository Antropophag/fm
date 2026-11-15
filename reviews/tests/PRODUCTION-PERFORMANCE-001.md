# PRODUCTION-PERFORMANCE-001 — retrospective Gate 3 test/spec review

Reviewer: `/root/perf_review` (independent; authored neither product source nor root-owned contract/tests)

Verdict: **APPROVED for retrospective Gate 3 test/spec adequacy**.

This is deliberately retrospective because the owner authorized production-first delivery. It is not preimplementation Gate 3 approval. Genuine historical assertion RED exists only for A01, A02, A03, A09 and the later A02 stale-navigation race. The other acceptance criteria have retrospective executable coverage; setup failures, including missing GD/ZipArchive in intermediate focused images, are not RED evidence.

## Binding

- Base: `0a7fea127a6129f63d370333b23ce31f80d047bd`
- Candidate source: `da7067fe354da7232eb327b8f891afddd8063fc775c1ab235e41cb386412e20e`
- Executable source: `1f1530d2f13eac69425ea75d0f931c8ac155a6fbda32bc3174c2be2d6e16913d`
- Snapshot patch SHA-256: `732293874238e44ded51cfe2d5d34d93b61777011ebad331db299cd70ead5d44`
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261115T170944Z-de63286580/package.json`
- Verification plan SHA-256: `ba14129dfc48226854c0b3ebb788a0f3b386b600cedc82d1f2c3d18319217511`
- Evidence-record aggregate SHA-256: `e3777551924f65b6ee9119ee11eb7c584944e92638aecb07612b0e912afe7021`

The package contains 21/21 GREEN mapped records, all bound to the candidate and executable source above. Architecture verification reported all eight rules GREEN. The repository ban on local full `make test` / `make verify` was observed; full exact-source GitHub CI remains pending.

## Review result

The executable mapping now covers A01–A12 at their public seams and sensitive adjacencies: request-local financial admission with command freshness and malformed projection fail-closed behavior; bounded service-worker concurrency, cancellation, logout/user isolation and stale-response exclusion; protected thumbnails, exact originals, revocation, local Blob previews and bounded decode; native session-lock release, two same-cookie concurrent HTTP workers, captured CSRF and persistence failures; dismissed/missing workforce identities, bounded status/scoped utilization SQL and malformed relevant versus unrelated evidence; shared financial editor context and unchanged POST facts; bounded calendar navigation, keyboard/history/stale-response/fallback behavior; paginated user filtering and protected exact editors; upload progress and stable retry identity; and content-derived asset/dependency freshness including every raw mutable page emission and offline install cache bypass.

The initial review found incomplete mappings and missing adversarial assertions. Root mapped the existing OTIZ, calendar, user, photo and installer regressions and added focused tests for scoped SQL, invalid workforce status, session concurrency, calendar races/fallback, editor security, asset dependency perturbation and oversized rasters. The tests exposed a real render-time session reopen, corrected by capturing CSRF before handoff. Later test adaptations retained the financial behavior oracles: the shared editor assertion still proves one shared form and exact A/B entrypoints, while XLSX and financial facts remain unchanged. The disposable focused profile gained ZipArchive only after the preserved XLSX oracle reached that environment dependency.

Historical RED hashes:

- A01 register: `388d52771db3acd1481eb8ae26610749741b8fd18bff4f2c213fd8713e075e9e`
- A02 service worker: `83278ba32ecbce86c25aa6e7e8698455332cd2b01e2bc9a8ce213a06deab3b25`
- A03 thumbnail: `cd61c55f237ec1d987788a35460a975c181b197478b0eba05a5b0ff041d8013c`
- A09 browser: `9a49923006a9de2912cf267f74179dc25d78c07379b683970359ca9cc44f2dc6`
- A02 stale navigation: `a1be30bfb82a8f273aeb934d217c430b4f8a696639dd594ef4cdace0df69cfcf`

No regression expectation was weakened to manufacture GREEN. No remaining test/spec finding blocks the retrospective Gate 3. GitHub CI, production rollout and live authorized verification are separate requirements and are not claimed here.
