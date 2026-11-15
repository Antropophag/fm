# PRODUCTION-PERFORMANCE-ORIENTATION-001 — independent final, predeploy and live review

Reviewer: `/root/perf_review` (independent; no product or test authorship)

Verdict: **APPROVED**.

## Binding

- Base: `4cdd3d5182204f9d05a6162c390ac27651a40295`
- Final candidate source: `ff8eb5863a0ececac45cc9911414b6bda0c404b4b951fbe2193fd6361e4292fc`
- Executable source: `8ee3b8d3ffa3a244532fa22273ed893a4c8ed4ea85c9636d42e58cc6f9fd33fc`
- Reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261115T184454Z-a9bc07f979/package.json`
- Package SHA-256: `02ac33af0037c96edb0e5fcb8cb6727e32a3851714900098fc8fe3f224254e10`
- Snapshot patch SHA-256: `9eeba8c20788ff5c0a1a4e536a4290557628516edc378e4f8cf000c13b85cef8`
- Verification plan SHA-256: `6c9e2a40de64685bf73d1f64a5985b5e3998a5468cfaad51f39d681a1c33ee33`

## Implementation and focused evidence

No unresolved finding remains. The one-file product correction parses bounded JPEG APP1, PNG eXIf and WebP EXIF containers; validates every segment, chunk and TIFF IFD offset; supports II/MM byte order and tag iteration; and safely defaults invalid or absent metadata to orientation 1. Post-resize flip/rotation implements EXIF 1–8, swaps advertised geometry for orientations 5–8, and expands the target memory allowance for simultaneous resize and rotation buffers. Renderer key `gd-640-jpeg82-orientation-v2` invalidates v1 derivatives. Original evidence is read-only.

The final package contains 2/2 GREEN exact-source checks: all 48 orientation/cache cases and the unchanged thumbnail contract. Protected HTTP/browser adjacency is also GREEN at executable source `8ee3b8d3…`, covering authorization, revocation, exact original bytes and browser thumbnail behavior.

## Deployment and live verification

The independently reviewed rollout was allowlisted to `app/InspectionEvidence/InspectionPhotoThumbnail.php` only:

- Patch: `f22cec5d3eae39fd370af5aa44f318c8d5a5f983af5d6c09aec93398c3561484`
- Candidate image: `sha256:88c2b81404e41b50b8a7778a6e859669081310218a99b5bc6fbb6cae1d842702`
- Rollback image: `sha256:a2c6473359beb06142d4736b816b8e883a5242701fc89c053cbad7051587bbcf`
- Image manifest SHA-256: `b31caa4beabecee15161516894d3016765c6c4727fc6f747a527ab55ff60d56f`

Fresh post-outage host/runtime/container CAS protected the switch. The PHP override replaced only the exact captured prior image prefix and preserved all other Compose bytes and service IDs. Canonical startup qualification, build-bound readiness attestation, health wait, nginx reload, public probes and structured rollback remained in force.

Rollout completed at `2026-11-15T18:47:02Z`: one file, PHP only; PHP, web, jobs and database were healthy and gateway running. Live verification compared six protected derivatives with native browser EXIF-oriented originals. Four orientation-6 photos rendered upright at `360×640` or `480×640`; two orientation-1 photos retained their expected geometry. Every maximum side was 640, mean RGB pixel error ranged from 2.005 to 3.561, responses remained `private, no-store`, object 544 original SHA was unchanged, guest access was denied, and readiness returned 200.

Primary evidence:

- Orientation live JSON SHA-256: `43e0da19fe96a803b870a5434a1f4a07786fbff9a9b44e559b3c611072879a05`
- Protected adjacency log SHA-256: `1015df3c07c970d2afd96d19769b2322a0f59217d85d39265c6959cf921ef584`
- Rollout receipt SHA-256: `b61cb106b68bb03e32de5f3ba3263fcbcfcc3bb139656311ff2aebda10e151cc`

The orientation correction is approved as implemented and verified in production. Full branch CI and its separately triaged corrections remain governed by their own exact-source evidence; this review does not convert unrelated CI state to GREEN.
