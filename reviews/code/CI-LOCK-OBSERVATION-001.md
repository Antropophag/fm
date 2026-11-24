# CI-LOCK-OBSERVATION-001 — independent final review

- Reviewer: `/root/gate3_review` (independent; authored none of the regression, cadence implementation, production code, or evidence).
- Review date: 2026-11-24.
- Origin: the sole regression in corrected GitHub CI run `45411089443` after complete failed-job inventory and analysis.
- Base: `de1c7b91c49493a37ef445d86440551a7625a2b4`.
- Exact reviewed source: `610828d9bedf0407ec7159fe39aab2b8e57ce10e2830b5f5279e8a15e0b5fd4b`; executable source `4df7df16fe7bd85c0ad269bd575ca09bc4e559404ad387077f8765eb82d030b8`.
- Final reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T145232Z-cb105607ba/package.json`, SHA-256 `a698f2a85a66ddd49105e628b40d2ea26c5358d6ccc2de9193179d5197be1cd5`.
- Required-context SHA-256: `5de31d409fa31a7c2dd3dfb2d20e1b18ae141eb265a831e33adbfb098184b4b4`.
- Verification-plan SHA-256: `efe63ca873266c18ff420706ca118d0ce1f56b1a3467f4abfe9d1734b885b263`.
- Gate 3 review: `reviews/tests/CI-LOCK-OBSERVATION-001.md`, SHA-256 `e6d92cf325946f83c7e6a2774c969c1f6293fe1ac86c6cd613b7f28bf16c9ad1`.
- Test authorship: root authored the deterministic pre-ENTER regression and all assertions; separate `auth_inventory` executor changed only the cleanup observer cadence from 10 ms to 200 ms.
- Verdict: **APPROVED**.

## Change assessment

The implementation matches the reviewed correction exactly:

- cleanup mode performs one independent pre-ENTER `INNODB_TRX` read and proves the worker has no transaction before the controlled command;
- the real worker then enters the existing cleanup seam and blocks on the parent-held task row;
- only the cleanup observer sleep changes from 10 ms to 200 ms;
- seed-mode processlist polling remains 10 ms;
- the original 15-second cleanup deadline remains unchanged.

The cadence is above MariaDB 11.4.7’s >100 ms information-schema transaction-cache refresh interval. It prevents the observer itself from indefinitely refreshing the timestamp of a stale pre-wait snapshot while leaving the actual lock, transaction and fixture behavior untouched.

## Preserved oracles

The complete mapped test remains intact and GREEN. It still requires:

- exact worker and parent blocker connection identities;
- a non-null wait start and `SERIALIZABLE` requesting transaction;
- the exact process-task table and `FOR UPDATE` lock seam;
- no terminal worker result before lock release;
- bounded successful completion only after parent commit;
- clean worker exit/stderr and usable parent/admin connections;
- exact seed-mode processlist stability and negative field mutations;
- exception cleanup through TERM/KILL/reap;
- every schema, literal byte/collation, mutation-sensitivity, conflict/no-DML, append-only, ownership, idempotency, foreign-row preservation and final cleanup assertion elsewhere in the file.

No production code, MariaDB setting, lock acquisition, fixture data, cleanup target, admission rule or business behavior changed.

## Evidence

- Mapped full test: `1795531884207464000-b9be2fb821a446d390e3884b2b22e5ba`, GREEN in 22.9 seconds at the exact reviewed source.
- Verification governance: `1795531884207484000-397679afdf3d4a1a83c6956a1ab6cd08`, GREEN.
- Runtime storage regression: `1795531884207476000-72e2533279aa43c2b63ec98cf6c98df5`, GREEN.

The earlier exact-source intended RED `1795531541232668000-425a40d8af764f41a85853504ccf3f5c` failed precisely the cleanup lock-wait observation with the primed cache and 10 ms cadence. The causal RED-to-GREEN transition changes only the reviewed cadence.

## Final verdict

**APPROVED** for exact source `610828d9bedf0407ec7159fe39aab2b8e57ce10e2830b5f5279e8a15e0b5fd4b`.

The correction is ready for lifecycle rebind, publication and a fresh exact-source GitHub CI run. This review does not claim that future CI run GREEN and does not authorize merge or deployment by itself.
