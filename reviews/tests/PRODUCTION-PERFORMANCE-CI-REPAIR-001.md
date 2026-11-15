# PRODUCTION-PERFORMANCE-CI-REPAIR-001 — retrospective Gate 3 review

Reviewer: `/root/perf_review` (independent; authored neither product source nor root-owned tests/environment changes)

Verdict: **APPROVED for retrospective test/spec adequacy**.

This review follows the first full GitHub CI run and does not claim a new preimplementation Gate 3 for the complete repair set. The original run failures are retained regression evidence. The visible assigned-role label has its own genuine intended RED, and the photo-orientation correction retains its separately approved Gate 3 package. Environment/setup failures are not treated as product RED.

## Binding

- Candidate commit: `dfd53529bbd15cbb4c1532d249e2ba220b661612`
- Candidate source: `6aa17d6b14f82a6f776d3fb225378e7e69f693f293ded6005131ccb5fcad9e76`
- Executable source: `18ee54ff3dcac7018a3adb5463db7d1d0b1b7f5ceba2bbaa4b3ad471bfec4df3`
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261115T192608Z-90df1048ca/package.json`
- Package SHA-256: `87c373910cf3a1966ff7d2063934428bdfb550cbd2f1f5a12dec1ee4bc4afa9d`
- Verification plan SHA-256: `751a01694833813c7c779c9b6299a64a7e4867be7035a9da2120785822931b70`
- Evidence-record aggregate SHA-256: `b7f2cdb958a586b5a603597d0ff78a2d0142fc6026d5fe8c074aa1f0486c386c`

The package contains 16/16 GREEN records, all bound to the candidate and executable source above. The snapshot patch is empty because the full reviewed candidate is committed at the package base; its empty SHA-256 is `e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`.

## Failure inventory and adaptations

The first CI run `43740284088` completed with eight failed jobs and fifteen named failed tests; the aggregate failure was also recorded. The complete failed-job inventory and every `REGRESSION_FAILURE` were collected before correction. Inventory SHA-256 values:

- Job inventory: `28ff59726840f9106ac167c3e96236fff5a4bfc0d7ae595f1f4d7c62b00d28f1`
- Regression inventory: `a77d0536cbd505a8b2c07f01e6940ce1c8d822ebda82f479cdc16cc2e7614595`

The reviewed test adaptations preserve their original oracles while following the new public seams. Financial tests still assert exact action, CSRF, expected revision, operation ID, object/recipient identity, preview recovery, rejection behavior and unchanged financial facts; they now use the shared editor and protected no-JS editor instead of removed per-row forms. Identity tests move role, legacy-link and secret/non-disclosure assertions to the exact authenticated `no-store` user editor while retaining authorization, denial, CSRF, replay and no-write checks. Navigation and icon tests retain the pinned geometry and public-route matrix while attributing the audited role-remove SVG to its relocated editor. Asset tests now execute the content-derived version function directly rather than checking a stale source spelling. Runtime/cutover tests retain exact public cache, MIME, HEAD, runtime identity and source-boundary expectations.

Focused-profile policy changes only assign the already required browser/MariaDB/container environment to registered Yii tests. The clean-worktree bootstrap still proves the registered focused path from a committed candidate. ZipArchive/GD additions affect disposable verification images, not production dependencies or business behavior.

## Reviewer finding

The full source review found one additional UI regression: detachable role chips had an accessible remove label but no visible assigned-role name. Root added an exact DOM assertion binding role ID, detach action, visible label and matching aria-label. The pre-fix run is a genuine `INTENDED_RED` at the public editor seam; evidence metadata SHA-256 is `80a29fe9b26f9db62a68a344aa10c9e4ed7b847a24bf1782527574d1c514341e`. The separate UI executor restored the escaped visible span, and the bound user-access check is GREEN.

No regression expectation was weakened to obtain GREEN. No remaining test/spec finding blocks the correction. A new full exact-source GitHub CI run is still required; this focused review does not claim that future matrix GREEN.
