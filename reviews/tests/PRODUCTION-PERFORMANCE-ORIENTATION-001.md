# PRODUCTION-PERFORMANCE-ORIENTATION-001 — independent Gate 3 review

Reviewer: `/root/perf_review` (independent; no product or test authorship)

Verdict: **APPROVED**.

This is a bounded correction of established A03 behavior: protected thumbnails must preserve the browser-visible EXIF orientation while original evidence remains byte-identical. Real production inspection found orientation 6 in four of six sampled originals, and the deployed v1 renderer produced sideways derivatives.

## Binding

- Base: `4cdd3d5182204f9d05a6162c390ac27651a40295`
- Gate 3 source: `5ffde814fb5578a9580a5c3cfa85335a21764e25a46b9ec1f9ddf899e67f1816`
- Gate 3 executable source: `37032d4a00b267796333f705da1fe1cf25678d7578274bd60658c7843550b62e`
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261115T183733Z-c60da25bb1/package.json`
- Package SHA-256: `7be07b962d6b6f4d8461b4a82bdaaf30e4901990309660aefb19ad6ad44f850f`
- Snapshot patch SHA-256: `7a3d657c8aa36944da33e940e99bbfd45affb5004b2d97481f81353f40f89872`
- Verification plan SHA-256: `395c60abf3e9e651e4f1a5dd7f85c9e3fb4eca03bb623380916deb82efcd787c`

## Review result

The root-owned regression independently constructs JPEG, PNG and WebP originals for all EXIF orientations 1–8 in both little-endian and big-endian TIFF: 48 cases. An unrelated ImageDescription entry precedes Orientation, so the implementation must locate the tag rather than rely on a fixed offset. Corner-color permutations and display dimensions independently specify every rotation/reflection; orientations 5–8 swap width and height.

Every case seeds the exact existing `gd-640-jpeg82-v1` cache key with a valid un-oriented derivative and metadata. The expected implementation must bypass those bytes without overwriting the legacy cache, preserve the original bytes exactly, and cache the corrected derivative deterministically. The package contains a genuine `INTENDED_RED` at JPEG little-endian orientation 2 and the existing thumbnail contract GREEN on the same source and executable environment. No setup failure is treated as RED.

The initial review required explicit v1-cache invalidation and TIFF byte-order/tag-position coverage. Both findings are closed. No remaining test/spec finding blocks implementation by the separate checklist executor.
