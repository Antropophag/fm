# Test review: FLEXIBLE-ADDRESS-SEARCH-001

- Reviewer: independent agent `/root/search_review`
- Test author: root agent; this reviewer authored neither specification, tests, nor production
- Reviewed source: base `701617e03d97abe5529207f7fa1995f3996e27e6` plus reconstructible snapshot `/tmp/fm-flexible-address-evidence/gate3-review-source/source.patch`, SHA-256 `314950275f6e846d58e13cee20cca84888b80bac3670e6c336bb88007513500a`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T203048Z-980155b61d/package.json`, candidate source `6f65d8001533570f557b4d14d91aa4bf50497635c672ed4a6be63d288b781834`
- Agreed review scope / prior findings disposition: first complete Gate 3 review of A1-A8, all four root-authored RED consumers, permissions, pagination/totals, strict numeric matching, offline behavior, and saved financial-history preservation. No prior findings.
- Specification: `specs/FLEXIBLE-ADDRESS-SEARCH-001.md`, SHA-256 `49ecae3f4ceb2f9ae3060113c4cc50ee738b0771dbaa5790feb3ab6c3acfcf9e`
- Public seams: the listed server GET routes, shared PHP matcher, saved-calculation grouping, and saved-object offline filter
- Verdict: `CHANGES_REQUESTED`

## Findings

1. **HIGH — A7 is not tested at the real offline boundary.** `tests/AddressSearch/offline_search.cjs` loads Fuse and `field-offline-address-search.js` directly and runs the generic corpus. It never creates the actually saved object/calculation snapshot consumed by the offline UI, never invokes the saved-object filtering seam, and never proves device/actor scope. It also cannot catch either explicit A7 failure mode: substituting a current entrance into an older snapshot that lacked one, or retaining only a matching child instead of the complete saved calculation group. An implementation can pass this test while the offline screen searches a different projection, expands scope, or rewrites snapshot meaning.

2. **HIGH — A5's existing search fields are asserted only through a synthetic `extra` array, not their real owners.** The corpus covers registration/factory strings, but no integration assertion proves registration number, factory number, internal ID, declaration details, or saved-calculation employee name/tab number still work after each reader/controller replaces its current search. The saved-calculation addition tests only address queries. A shared address integration that drops one of these established per-section haystacks would satisfy the current RED suite.

3. **HIGH — authorization and actor-scope preservation lack a sensitive adversarial witness.** The server-list fixture does not place a matching object outside the actor's permitted set and assert its exclusion. The construction-control `ownership=mine` check is negative only: it proves object 4513 is absent, but has no matching owned positive row in the same request, so an empty/broken mine query passes. Add a simultaneous authorized match and unauthorized match with otherwise identical searchable data, and assert the authorized result remains while the unauthorized result stays absent for every owner whose SQL predicates are changed.

4. **MEDIUM — the complete public seam matrix and filtered aggregates are incomplete.** The HTTP loop covers `/pilot/objects`, `/pilot/address-register`, `/pilot/completion-register`, and `/pilot/otiz/objects`, but the contract separately names `/pilot/otiz`; only the legacy register's direct PHP call is exercised. The economy reader asserts rows and total but does not assert its filtered `summary`, despite A6 explicitly requiring related filtered totals. A regression that filters page rows correctly while leaving economy totals over the unfiltered portfolio would pass.

5. **MEDIUM — the retained RED evidence is incomplete for independent diagnosis.** The package references four harness records with exit `255` and `INTENDED_RED`, but every record has `stdout: null` and `stderr: null`. `/tmp/fm-flexible-address-evidence/lists-red.log` independently retains only the server-list failure at the expected first address assertion. There is no retained bounded failure output proving the shared matcher failed because the core is absent, construction control failed at its structured search, or saved calculation failed at address grouping rather than setup or an unrelated earlier assertion. Gate 3 requires the captured RED result, not only an outcome label.

The address grammar corpus itself is strong and independently literal: it covers normalization, word order, marker aliases, numeric street names, strict house/corpus/building/entrance distinctions, letters, fractions, unknown entrance, conflicts, typo behavior, literal metacharacters, no-result queries, and PHP/JS parity. The 10,000-record assertion rejects hidden candidate truncation and records time/memory. The server-list fixture usefully checks pre-pagination selection, page 2, effective edits without reindexing, deterministic IDs, and read-only facts. The saved-calculation test preserves the exact projection before/after search and retains both grouping views. These strengths do not close the five gaps above.

## Reproduced RED evidence

The prepared package binds all four commands to candidate source `6f65d8001533570f557b4d14d91aa4bf50497635c672ed4a6be63d288b781834` and records exit `255` / `INTENDED_RED`. The available bounded transcript for:

```text
php tests/Yii2/yii2_flexible_address_search_001_test.php
```

fails at the intended first address-list assertion: expected IDs `500001..500050`, actual empty list. Fixture setup therefore reached the real reader. The other three commands need retained bounded transcripts as described in finding 5.

## Reviewed hashes

```text
d79ef1d860bf78b2705aeff09571518be5fb0a488461a7069d4dfd8989f94ea0  tests/AddressSearch/corpus.json
68044990e3c5c8ff8995541104ce7b7c5aa14fe6eb0356b5d02568ab8bc62bc3  tests/AddressSearch/flexible_address_search_001_test.php
afc4deca9d746dd44aad0eab884fc5074b6c1afced869d0cdd1594966d32c231  tests/AddressSearch/offline_search.cjs
ca1412a6dd8a70fb1ff8bb98267195d02c56e81c221f4d39c68e70ec09479990  tests/Yii2/yii2_flexible_address_search_001_test.php
3a7b0eb899811e7fb31598f60c25289b8dccb3a4ebf06a9506e0ca1652b8fe2e  tests/Yii2/yii2_flexible_control_search_001_test.php
fe106a23b7d5f5503c2cd25999f549dc90699bb672be828937ebc68c35bae658  tests/Yii2/yii2_otiz_settlement_v2_001_test.php
f04ac2cfbb86d4f1843b693a461205041cee975c8ee43d4e894d600471cf1b41  tools/verification/suites.tsv
```

## Required changes

Return to Gate 2 and add real-boundary tests for offline saved scope/snapshot/group behavior; legacy non-address fields at each affected owner; positive/negative actor scope; `/pilot/otiz` plus filtered economy summary; and bounded failure transcripts for all four intended RED commands. Recompute the plan/package for the changed test source and request a fresh independent Gate 3 review before implementation.

## Gate 3 correction rereview — 2026-11-27

- Reviewer: independent agent `/root/search_review`; still independent of specification, tests, and production
- Reviewed source: base `701617e03d97abe5529207f7fa1995f3996e27e6` plus reconstructible snapshot `/tmp/fm-flexible-address-evidence/gate3-v2-source/source.patch`, SHA-256 `7377fd8c84b3b2d6ecb880b611db33bbb67ed82d0c0f53f2ada260f137f68231`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T203904Z-dc1683b2c2/package.json`
- Candidate source: `b7ceb9f81390ca76befedd701c496d5488080690c41d6c49ce4ea528b1018beb`
- Rereview scope: the corrected delta and complete disposition of findings 1-5; the unchanged grammar corpus and original strengths remain accepted
- Verdict: `APPROVED`

### Findings disposition

1. **Resolved.** The existing `reliable_field_offline_browser` now exercises the actual saved-object shell after network loss. It proves a saved owned tail object is found by natural and typo queries, an identical foreign object never materializes, and an older saved row with no entrance neither borrows a current/neighbor entrance nor disappears when entrance is omitted. The browser transcript records exactly one intended failing scenario at the new flexible-search assertion while the other 24 scenarios pass, so the RED is isolated from the established offline behavior.

2. **Resolved.** Real owners now retain their established fields: object/address lists cover registration and factory numbers with internal-ID behavior kept only where already supported; completion covers registration and declaration text; economy covers registration; construction control covers its existing number fields; saved calculations cover employee name and tab number. The saved-calculation assertions also require the matching parent to retain both allocation children, preventing child-only group truncation.

3. **Resolved.** Construction control now gives the owned and foreign rows the same searchable address and requires `ownership=mine` to return only owned object `4512`. The globally authorized readers separately require user `9401` to remain denied; these lists do not have a per-row ownership predicate, so no invented row-level permission contract is required.

4. **Resolved.** The HTTP seam matrix now includes the `/pilot/otiz` alias. The economy fixture gives one matching and one nonmatching object an independently literal 52,000,000-cent fund and requires the filtered summary to equal exactly 52,000,000 cents, catching an unfiltered aggregate while retaining row/total/page checks.

5. **Withdrawn as reviewer error.** The first review checked nonexistent inline `stdout`/`stderr` fields. Each harness record actually retains valid `stdout_path` and `stderr_path` files. Both the original records and this fresh package contain the intended failure output. The fresh five-command evidence shows: missing common matcher; actual offline shell failing only its new flexible-search assertion; server lists returning no normalized address matches; construction control returning no structured match; and saved calculation returning zero address groups after its database profile became healthy.

### Rereview assessment

The expectations remain independent of the planned implementation. IDs, actor scope, 52,000,000-cent aggregate, exact rows, group child count, and snapshot mutation are fixture-owned literals rather than values computed by the future matcher. The corrected tests reject post-page filtering, unfiltered totals, changed legacy field haystacks, unauthorized entry, offline scope expansion, invented snapshot entrance, partial calculation groups, numeric near matches, and write-side effects. The fixtures use isolated databases/browser storage and preserve deterministic ordering and cleanup.

Reviewed corrected hashes:

```text
b81bf42c7242f3b0b7f2f3a546a32a31101217cd0c7bddf939fb329c581b6395  tests/Yii2/reliable_field_offline_browser.cjs
52022d7dfb602e761336b7eb40ab77b91c50a9c5e15228b2ce7cb76829d08da2  tests/Yii2/reliable_field_offline_browser_001_test.php
7d9f17309cc194eb0f7a7f83d02375085ec86cc1d2dd59d2b5a13af43fbf24eb  tests/Yii2/yii2_flexible_address_search_001_test.php
c5ae15830fa549c0bef9590f4c4f494cb53e557244c54c016be21c4c4e5ef200  tests/Yii2/yii2_flexible_control_search_001_test.php
59c6003d8c0bf28fb56a468da77c84dc0a2f0862c0b2ff5cf11c653d5fe2548d  tests/Yii2/yii2_otiz_settlement_v2_001_test.php
```

Gate 3 is `APPROVED` for exact candidate source `b7ceb9f81390ca76befedd701c496d5488080690c41d6c49ce4ea528b1018beb`. Gate 4 may proceed against these expectations. Any later specification or test change requires plan recomputation and the planner-selected independent review cycle.
## Gate 3 A1 grammar-delta review — 2026-11-28

- Reviewer: independent agent `/root/search_final_review`; authored neither specification, tests, nor production
- Prior Gate 3: `APPROVED` at candidate source `b7ceb9f81390ca76befedd701c496d5488080690c41d6c49ce4ea528b1018beb`; findings 1–4 remain resolved and finding 5 remains withdrawn as reviewer error
- Reviewed package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T211331Z-5bbfd7025e/package.json`
- Reviewed candidate source: `0a84a3593f54f1bc4e50f2cac754f3b73b70ff278fe80fa27469bca5d3612bcc`
- Reconstructible snapshot patch: `/tmp/fm-flexible-address-evidence/packaging-source/source.patch`, SHA-256 `4b41dae5277acc82150c4d61e1eea54fb46dbd59f890c0818a2ff4818be72c53`
- Restored snapshot: `/tmp/fm-flexible-address-evidence/packaging-restored`; reviewed specification and corpus match the frozen worktree bytes
- Review scope: only the clarified A1 delta: optional `№` before numeric components, a separately stored entrance containing `под. №2`, and explicit street-type discrimination when the record type is known while permitting name matching when the record type is absent
- Verdict: `APPROVED`

### Assessment

The new corpus expectations are independent and discriminating. Record 26 (`Полевая, 6`, entrance `2`) has no recorded street type; record 28 (`проспект Полевая, д6`, entrance `2`) has a known conflicting type. The untyped query `полевая 6 п2` must return both records, while `ул. Полевая д6 п2` must return only record 26. This simultaneously rejects treating a missing record type as a mismatch and ignoring a known conflicting type. Expected IDs are fixture-owned literals and are not derived from the matcher.

Record 27 stores the entrance itself as `под. №2`; query `кольцевая д8 п2` requires the same structured interpretation across the separately supplied entrance field. Queries `д№12к2п№3` and `березовая д12 корп№2 под№3` cover compact and word-marker forms of the optional number sign. Together these cases directly exercise each added A1 sentence without relaxing the existing exact-number or street-name expectations.

The retained core record is a valid intended RED at the exact clarified rule. It exits 255 at `ул. Полевая д6 п2`, expected `[26]`, actual `[]`, via `tests/AddressSearch/flexible_address_search_001_test.php`. Because this case follows the untyped query and the stored-entrance case in corpus order, those preceding new expectations already pass on the frozen implementation. The record retains bounded output through `stdout_path` and `stderr_path`; setup reached the public `Search::ids` seam.

The same package records the four mapped actual-boundary consumers GREEN: real offline shell 25/25, server lists/ACL/summary, construction control, and saved-calculation grouping/projection. Their executable source is identical to the core RED record. These results preserve the previously approved boundary coverage while isolating the newly clarified grammar gap. Remaining focused consumers and exact-source CI are lifecycle obligations for implementation/final review, not blockers to approving this test delta.

No test expectation computes its answer from production behavior, widens authorization, mutates facts, or weakens a prior assertion. No findings.

### Reviewed hashes

```text
2fb17948b2a15e0323b6e96c22e625520562ee71f6e101f949537ddb19571adc  specs/FLEXIBLE-ADDRESS-SEARCH-001.md
2634dc4c1eb4dddb6ba3f43f2d0a63180d1c6bc937e336fb952272b5662bcac3  tests/AddressSearch/corpus.json
a3c9f14c889b6fd113ce5faf23c9945308b00e1e75ae5dd9761150bcb1d786a6  tests/AddressSearch/flexible_address_search_001_test.php
c8e27cfe14e81577e4707470c1060e905587eab06804cf822f02335384b689ba  tests/AddressSearch/offline_search.cjs
fa75d16e850a0c2348363db4b3240aeb6f11d74a39f40cecf756af7f767ba4ad  tests/Yii2/yii2_flexible_address_search_001_test.php
c5ae15830fa549c0bef9590f4c4f494cb53e557244c54c016be21c4c4e5ef200  tests/Yii2/yii2_flexible_control_search_001_test.php
59c6003d8c0bf28fb56a468da77c84dc0a2f0862c0b2ff5cf11c653d5fe2548d  tests/Yii2/yii2_otiz_settlement_v2_001_test.php
```

Gate 3 is `APPROVED` for this A1 grammar test delta at exact candidate source `0a84a3593f54f1bc4e50f2cac754f3b73b70ff278fe80fa27469bca5d3612bcc`. This is not a final production-code approval.
## Correction Gate 3 review — 2026-11-28

- Reviewer: independent agent `/root/search_final_review`; authored neither specification, tests, nor production
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261127T214500Z-4143d75b1a/package.json`
- Candidate source: `b9af69ebadf509e723654c459b05701779c200f319d9e51ffb0bd6bdf223b321`
- Executable source: `bf63409c63c8f753bc247fd5229e24256a1389efb5a70c886dbc30c8461e9003`
- Reconstructible patch: `/tmp/fm-flexible-address-evidence/correction-gate3-source/source.patch`, SHA-256 `e4566af756a77f4032c4f16a84a40f8c0cc0951f52a58fae66bf3ca7280f473a`
- Restored snapshot: `/tmp/fm-flexible-address-evidence/correction-gate3-restored`; specification, corpus, and server integration test match the frozen worktree bytes
- Scope: final-review finding 1 correction tests, final-review finding 2 withdrawal/clarification, and the independently reproduced general A2 numeric-name gap
- Verdict: `APPROVED`

### Effective-field correction expectations

The server test applies one literal effective edit to object 500060: address `ул. Лесная, д. 9`, entrance `7`, and registration `CORRECTED-TAIL`, replacing the old `Берёзовая` address and `REG-500060`. It then runs the same assertions through all five owners already in scope: address register, object queue, completion register, portfolio economy, and retained `ObjectRegister`.

For every owner, the test requires both the structured effective address/entrance query and the effective registration query to return exactly object 500060; it separately requires displayed address and registration to equal the edited literals and the stale registration query to have total zero. These expectations reject a matcher-only patch that leaves displayed metadata stale, a display-only patch that searches legacy values, and dual old/new matching. The current server record reaches and passes the first four owners, then fails exactly at `legacy-register лесная д9 п7`, expected `[500060]`, actual `[]`. This is a valid intended RED for final-review finding 1.

The pre-page sorting witness is independently literal: object 451201 has `BASE-REG`, object 500060 has edited `CORRECTED-TAIL`, and all remaining fixture registrations begin with `REG-`. Requiring the first two IDs to be `[451201, 500060]` under ascending registration order proves the effective value participates before SQL paging while preserving the established sort. The final invariant assertion requires the retained `ObjectRegister` economics summary to remain identical with and without the effective registration query.

### Numeric street-name expectations

A2 already states that an unmarked numeric tail *after* a street name supplies house/corpus/entrance and that numbers inside street names remain part of the name. The new records `ул. 8 Марта, д. 10` and `улица 9 Января, дом 12` expose the old implementation's narrow `года/лет` special case without changing that rule. Queries `8 марта`, `8 марта 10 п1`, and `9 января д12 п2` require the leading number to remain a street-name token; `9 января 12 п1` must return zero, preserving exact entrance comparison. Expected IDs 29 and 30 are fixture-owned literals and are not computed from production behavior.

The core record exits 255 at the first new numeric-name assertion, `8 марта`, expected `[29]`, actual `[]`, through the public PHP matcher. Because offline parity is invoked only after the PHP corpus loop, this record does not falsely claim a separate JS RED; the executor must bring both implementations through the shared corpus. This is a valid intended RED for the existing general A2 requirement.

### Clarification and lineage

The A6 clarification correctly reconciles this contract with `OTIZ-OBJECT-REGISTER-PAGING-001`: totals and summaries already defined as filtered follow the filtered selection, while the old `ObjectRegister` economics summary retains its explicitly global financial meaning. The new invariant assertion prevents this search correction from changing that financial contract. Final-review finding 2 remains withdrawn.

The three other mapped actual-boundary records remain GREEN at the same exact source and executable source: the real offline shell completes 25/25, construction-control scope remains intact, and saved-calculation grouping/projection remains intact. Earlier Gate 3 approvals, their true RED evidence, the first review's resolved findings 1-4, and withdrawn finding 5 remain append-only history. This approval covers only the new correction test delta; it is not final production-code approval.

No expectations derive answers from the future matcher, weaken authorization or financial semantics, mutate facts, or relax prior assertions. No findings.

### Reviewed hashes

```text
1ef972eb7963137983024ed31825ffe229003c04f6013a97a1508b522bffff82  specs/FLEXIBLE-ADDRESS-SEARCH-001.md
1401a79444cb1f9088c72c2d28fb7a5cca3fe8375a231b543fea1629eb070e58  tests/AddressSearch/corpus.json
a3c9f14c889b6fd113ce5faf23c9945308b00e1e75ae5dd9761150bcb1d786a6  tests/AddressSearch/flexible_address_search_001_test.php
c8e27cfe14e81577e4707470c1060e905587eab06804cf822f02335384b689ba  tests/AddressSearch/offline_search.cjs
a1f3f03e41c42556056a1a9085ab2d4f7d5110fe8ac3e9899e0eafedf5ced7a2  tests/Yii2/yii2_flexible_address_search_001_test.php
52022d7dfb602e761336b7eb40ab77b91c50a9c5e15228b2ce7cb76829d08da2  tests/Yii2/reliable_field_offline_browser_001_test.php
c5ae15830fa549c0bef9590f4c4f494cb53e557244c54c016be21c4c4e5ef200  tests/Yii2/yii2_flexible_control_search_001_test.php
59c6003d8c0bf28fb56a468da77c84dc0a2f0862c0b2ff5cf11c653d5fe2548d  tests/Yii2/yii2_otiz_settlement_v2_001_test.php
```

Correction Gate 3 is `APPROVED` for exact candidate source `b9af69ebadf509e723654c459b05701779c200f319d9e51ffb0bd6bdf223b321`. Implementation may proceed against these expectations; any test/spec change requires a new bound review.
