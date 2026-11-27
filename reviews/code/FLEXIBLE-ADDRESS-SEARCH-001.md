# Final code review: FLEXIBLE-ADDRESS-SEARCH-001

- Reviewer: independent agent `/root/search_final_review`; authored neither specification, tests, nor production
- Reviewed package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T212424Z-383be9858c/package.json`
- Exact candidate source: `26b86371688e6055cc8d5c31382333e2ddf6e0d1351757a4380e83417563d013`
- Executable source: `8c33db6cedde06a002123aa9798dfe5560fb038f58c9f68012ec99e5832cdeff`
- Reconstructible snapshot patch: `/tmp/fm-flexible-address-evidence/final-review-source/source.patch`, SHA-256 `21feb282ab0fb9f80d2fa57396a118f74aa6f9cd0e5e68c83e6707c5983c6814`
- Restored snapshot: `/tmp/fm-flexible-address-evidence/final-review-restored`
- Contract: `specs/FLEXIBLE-ADDRESS-SEARCH-001.md`
- Verdict: `CHANGES_REQUESTED`

## Findings

1. **HIGH — the retained `ObjectRegister` searches stale legacy address/entrance values after an effective edit.** A6 requires that an updated effective address or entrance be searchable on the next request without reindexing. `MariaDbObjectRegister::matchingIds()` reads only `fm_maintable.ordadr_address`, `entrance`, and `regnumber` (`app/Otiz/MariaDbObjectRegister.php:72-77`), while the other active readers overlay `fm2_object_detail_edits`. The same class also builds its result rows from legacy address fields in `MariaDbObjectRegisterQuery::base()`. Consequently, after an edit such as `Берёзовая 12 / entrance 3` to `Лесная 9 / entrance 7`, this public `ObjectRegister` seam will continue matching the old address and will not match the new one. The focused test invokes this seam for initial normalized matching, but its effective-edit assertion covers only `MariaDbAddressRegister` (`tests/Yii2/yii2_flexible_address_search_001_test.php:31-32`), so the defect is not caught.

2. **WITHDRAWN after counterevidence — the `ObjectRegister` financial summary is explicitly global.** `specs/OTIZ-OBJECT-REGISTER-PAGING-001.md` requires global economics totals to retain their meaning independently of query, state, sort, and page; its “Общая сводка” scenario expressly requires the values to remain unchanged when the register is filtered. `tests/Otiz/object_register_paging_001_test.php` independently asserts the same complete summary for the first page, third page, and `state=blocked`. Therefore the unfiltered streaming fold at `app/Otiz/MariaDbObjectRegister.php:63-66` is established behavior, not an A6 defect. Changing it would alter financial presentation semantics outside this change's scope. In FLEXIBLE-ADDRESS-SEARCH-001, “related filtered totals” applies to totals whose owning seam already defines them as filtered, such as `MariaDbOtizPortfolioEconomy`; it does not convert an explicitly global summary. Clarify A6 accordingly to remove this cross-contract ambiguity.

## Assessment of the remaining candidate

The shared PHP and JavaScript grammar implementations are materially equivalent and satisfy the reviewed corpus: normalization, street-type discrimination, optional `№`, marked and positional components, strict numeric comparison, Cyrillic typo tolerance, compound names, literal metacharacters, and deterministic source order. The implementation does not fuzzy-match numeric components. The added A1 grammar implementation is confined to the matchers and matches the separately approved test delta.

The current mapped evidence is internally consistent: all five records are GREEN at exact candidate and executable source, including the actual offline shell's 25 scenarios and the saved-calculation grouping seam. The architecture check passes, including the required Pilot HTTP global-call qualification. The six earlier container materialization failures are adequately explained by the ignored asset location; the final nonignored `public/third-party/fuse` asset is present, licensed, versioned through `AssetVersion`, served same-origin, and included in the offline shell generation. The retained early refresh timeout remains UNKNOWN; three subsequent stable 25/25 runs mean it is not evidence of a reproducible candidate failure, and it is not classified as pre-existing.

ACL predicates remain ahead of filtering in the list owners reviewed, and matched IDs are converted to integers before SQL interpolation. Search remains read-only and the evidence covers unchanged facts/history, actor scope, strict numeric near-misses, pagination beyond the first page, saved group preservation, and offline snapshot scope. The 10,000-record benchmark records bounded local time and memory. These strengths do not close the remaining effective-address A6 failure in the separately retained `ObjectRegister` seam.

## Gate 3 lineage

The historical Gate 3 approval at source `b7ceb9f81390ca76befedd701c496d5488080690c41d6c49ce4ea528b1018beb` and the later clarified A1 test-delta approval at source `0a84a3593f54f1bc4e50f2cac754f3b73b70ff278fe80fa27469bca5d3612bcc` remain valid lineage. Their true RED records are preserved; this review does not claim a new RED at the final candidate. Findings 1-4 from the first Gate 3 review remain resolved, and finding 5 remains withdrawn as reviewer error. The final candidate cannot receive code approval until finding 1 is corrected and independently verified. Finding 2 is withdrawn on the normative and executable counterevidence above.

## Required disposition

Update `MariaDbObjectRegister`/`MariaDbObjectRegisterQuery` so its matcher and displayed rows use effective address, entrance, and registration values. Add a focused regression witness for that behavior, clarify A6 to preserve the explicitly global `ObjectRegister` economics summary while requiring already-filtered summaries to follow their result selection, prepare a new exact-source package, and request final rereview. Preserve the existing Gate 3 history rather than relabeling the final GREEN suite as new RED evidence.
## Final corrected code review — 2026-11-28

- Reviewer: independent agent `/root/search_final_review`; authored neither specification, tests, nor production
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T215634Z-492d36a179/package.json`
- Exact candidate source: `176546ebd5219a860891514af532cf4a1b7e8053547bc616ce0414da73d2ede3`
- Executable source: `d3bc3f502a5a81e1448e1616417e962090d50f6e3dece42a6686ae221d725adc`
- Reconstructible snapshot patch: `/tmp/fm-flexible-address-evidence/final-corrected-source/source.patch`, SHA-256 `7017164cde8084a848887b0cbbc0694cda21732486d40637f779bcf626556c92`
- Restored snapshot: `/tmp/fm-flexible-address-evidence/final-corrected-restored`
- Review scope: full candidate, with focused rereview of the four runtime files changed since rejected source `26b86371688e6055cc8d5c31382333e2ddf6e0d1351757a4380e83417563d013`
- Verdict: `APPROVED`

### Findings disposition

1. **Fixed.** `MariaDbObjectRegister::matchingIds()` now reads effective address, entrance, and registration expressions over the edit projection. `MariaDbObjectRegisterQuery::base()` uses the same effective expressions for displayed row metadata. The raw-page registration order uses the effective registration expression before LIMIT/OFFSET, so paging and display agree with matching. The all-five-owner regression is GREEN and proves the new address/entrance and registration find only object 500060, displays the exact effective values, removes the stale registration from search, and puts `BASE-REG` then `CORRECTED-TAIL` before the remaining `REG-*` rows under ascending pre-page sort.

2. **Withdrawn, preserved correctly.** `OTIZ-OBJECT-REGISTER-PAGING-001` defines the old `ObjectRegister` economics summary as global independently of filters and pages. The correction leaves that fold unchanged. Both the new explicit invariant and the established `object_register_paging_001_test.php` are GREEN, including its global financial formula and authorization assertions. No financial semantics changed.

### Numeric street-name correction

The PHP and JavaScript parsers now use the same general rule: an unmarked numeric token is an address component only when it occurs after the last nonnumeric, non-component street-name token, or when the query contains no street-name token. Leading numbers in `8 Марта`, `9 Января`, `1905 года`, and `50 лет Октября` therefore remain part of the street name without a dictionary. Numeric-only queries and marked house/corpus/building/entrance components keep their prior interpretation. The 30-record shared PHP/JS corpus is GREEN, including the negative exact-entrance case, and the 10,000-record benchmark remains bounded at 0.073 seconds and 10 MiB in the retained run.

### Full-candidate assessment

The conclusions from the first full review remain otherwise unchanged. The shared matcher enforces strict numeric components, literal metacharacters, street-type discrimination, compound-name normalization, bounded Cyrillic typo tolerance, deterministic source order, and no fuzzy identifiers. Server owners filter authorized candidate sets before count and pagination; matched IDs are integer-normalized before SQL use. Effective edits are read on the next search without a persistent index. Saved-calculation search preserves whole groups and projections. Offline search uses only the actor/device-scoped saved snapshot and does not invent a missing entrance.

Fuse remains version-pinned and licensed, served same-origin from the nonignored `public/third-party/fuse` path, versioned through `AssetVersion`, and included in the offline shell generation. The packaging correction preserves identical vendor bytes. Search remains read-only; focused evidence covers unchanged facts/history, ACL boundaries, filtered economy totals, global legacy economics, strict numeric near-matches, matches beyond page one, and exact display values. No external address service, daemon, or persistent search projection was introduced.

All five mapped acceptance records are GREEN at the exact candidate and executable source, including the real offline shell's 25/25 scenarios. The established `ObjectRegister` paging/global-economics regression is also GREEN at the same source. The architecture check passes all eight rules and the required Pilot HTTP global-call qualification. The earlier 38 native checks and six corrected container consumers remain retained evidence; original setup failures were preserved rather than relabeled. The early isolated browser refresh timeout remains UNKNOWN and was not called pre-existing; it has not reproduced in the stable full offline runs and supplies no reproducible candidate defect.

### Gate 3 and RED lineage

The original Gate 3 approval, the clarified A1 delta approval, and correction Gate 3 approval at source `b9af69ebadf509e723654c459b05701779c200f319d9e51ffb0bd6bdf223b321` remain valid lineage at this final candidate. Their historical true RED records are preserved. The final GREEN records demonstrate implementation closure; this review makes no new RED claim. The first Gate 3 review's findings 1-4 remain resolved and finding 5 remains withdrawn as reviewer error.

No findings. Final code review is `APPROVED` for exact candidate source `176546ebd5219a860891514af532cf4a1b7e8053547bc616ce0414da73d2ede3`. This is code/test approval, not exact-source CI admission or deployment-operator approval. Subsequent tracked additions limited to this exact review artifact and task-completion metadata do not alter the reviewed runtime/test source; any other source or test change requires a new bound review.

### Reviewed correction hashes

```text
d69d121c9605bbe44516b38afdd588bd2fbf0fcbcbd344050364afa11e64d443  app/AddressRegister/Search.php
67d74df4cfd4f0ad401f6471eccc2025069a9d1ddfc405907724a53db72e7aa6  app/YiiRuntime/Assets/field-offline-address-search.js
5bc5056e94f20eb9b3507e85fcc19226b1c7fa3a752272f8097839b03bebabf9  app/Otiz/MariaDbObjectRegister.php
4d753f714e743205891e3a20c7d38facb7d764a848b41f7a954ccc386f170669  app/Otiz/MariaDbObjectRegisterQuery.php
1ef972eb7963137983024ed31825ffe229003c04f6013a97a1508b522bffff82  specs/FLEXIBLE-ADDRESS-SEARCH-001.md
1401a79444cb1f9088c72c2d28fb7a5cca3fe8375a231b543fea1629eb070e58  tests/AddressSearch/corpus.json
a1f3f03e41c42556056a1a9085ab2d4f7d5110fe8ac3e9899e0eafedf5ced7a2  tests/Yii2/yii2_flexible_address_search_001_test.php
```
