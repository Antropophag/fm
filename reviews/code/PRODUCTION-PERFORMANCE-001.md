# PRODUCTION-PERFORMANCE-001 — independent final and predeploy review

Reviewer: `/root/perf_review` (independent; no product or test authorship)

Verdict: **APPROVED for implementation, focused exact-source verification and predeploy rollout**.

The approval binds only to the source and rollout artifacts below. It does not claim production delivery, authorized live interaction GREEN, full GitHub CI GREEN, PR admission or merge.

## Binding

- Base: `0a7fea127a6129f63d370333b23ce31f80d047bd`
- Candidate source: `da7067fe354da7232eb327b8f891afddd8063fc775c1ab235e41cb386412e20e`
- Executable source: `1f1530d2f13eac69425ea75d0f931c8ac155a6fbda32bc3174c2be2d6e16913d`
- Snapshot patch SHA-256: `732293874238e44ded51cfe2d5d34d93b61777011ebad331db299cd70ead5d44`
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261115T170944Z-de63286580/package.json`
- Verification plan SHA-256: `ba14129dfc48226854c0b3ebb788a0f3b386b600cedc82d1f2c3d18319217511`
- Final package metadata SHA-256: `f14d9f51b62114415bbe530c9740671c7d52347966f93c99bda003e53b68536f`
- Evidence-record aggregate SHA-256: `e3777551924f65b6ee9119ee11eb7c584944e92638aecb07612b0e912afe7021`
- Prepared image manifest SHA-256: `771789816b2fe0aecbd6ffc193d4479158ee9b42915fec894f9dd8b365ebe5dc`

The formal package contains 21/21 GREEN mapped records on the exact candidate and executable source. Architecture verification is GREEN for all eight rules, and `git diff --check` is clean.

## Findings and dispositions

No unresolved implementation or rollout finding remains.

1. Scoped utilization originally treated malformed structure in every current application as globally relevant. It now globally fails closed only when a roster is not safe enough to disprove membership; a complete unrelated roster may be excluded, while related malformed structure still fails closed. Exact adversarial SQL/projection tests cover both sides.
2. Malformed or incomplete OTIZ register projections originally skipped admission and could appear payable. Strict JSON/list/positive-integer validation now produces the safe unknown blocking reason without changing stored money.
3. User-directory query validation was caught by an infrastructure wrapper and returned 500. Yii HTTP exceptions now retain their public 400/404 behavior.
4. Raw mutable assets remained at unversioned or fixed-version page seams. All page emissions now use content-derived `AssetVersion`, including the early sidebar and checklist script; the service-worker generation fetches its internal shell keys with HTTP-cache bypass.
5. Session concurrency testing exposed views reopening the released file session to obtain CSRF. Selected controllers now capture identity/token before release and pass them through rendering; two real same-cookie HTTP workers prove the second request completes while the first backend read is held.

The full source inventory also checked authenticated thumbnail/original access and private derivative caching, service-worker identity/cache sequencing, upload retry IDs, calendar fragments, shared financial/user editors, read projection bounds, GD runtime dependencies and generated dependency sources. No monetary, authorization, original-byte, append-only history or business-schema migration change was introduced.

The production rollout is narrowly PHP-only because the running web/jobs containers contain older independent bytes that must remain untouched. The reviewed scripts preserve exact host/runtime CAS, create rollback from the actual PHP runtime, pin image IDs, preserve unrelated container IDs and restore every nonconflicting path. Startup uses the canonical deep read-only runtime qualification and writes only the private build-bound readiness attestation before health wait and nginx reload. Failure recovery records original and rollback errors; a post-rollout live failure uses the same reviewed recovery function. Rollout reports `ROLLED_OUT_PENDING_LIVE_VERIFICATION` until the authorized photo and interaction matrix succeeds.

This is explicit predeploy approval for the bound scoped rollout and its reviewed rollback. Production success still requires the live authorized thumbnail/performance matrix and source/hash verification. After delivery, the existing exact-source full GitHub CI must run; every failed job and `REGRESSION_FAILURE` must be inventoried before correction. Until those stages complete, production and CI remain UNKNOWN rather than GREEN.
