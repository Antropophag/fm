# EMAIL-FIRST-PASSWORD-RECOVERY-QUALIFICATION-001 — bounded Gate 3 test review

- Reviewer: `/root/gate3_review` (independent reviewer; authored none of the reviewed test delta, implementation, or evidence).
- Review date: 2026-11-24.
- Scope: accepted-password normal-login qualification and two historical/public regression fixture corrections only. Prior main, schema, and integrity Gate 3 approvals remain separate and unchanged. This is not an implementation review.
- Reviewed exact candidate source: `6a9945f1989aef7a68a5ac9e8f0a08e2e12bc094211ab4dec382a56d0307b3d0`; executable source `b6102fd987723540bd45fbc72611513c432875f61ab0fec11eceac228858ebe0`.
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T124034Z-f5b2994612/package.json`, SHA-256 `6ad82cd3b9ba8bb09ff1d486cbcc24600ebd5044cefbe137c0807d2c9af30d9e`.
- Required-context SHA-256: `90dd5682b84218d0d1487d5ea2de1fb9b43f2bdb6fa1a76681fbd9486b7498df`.
- Verification-plan SHA-256: `dc67f64b84b7210aa485ef10437d74f37c4f6579246471df48782921c6305314`.
- Repair input: `openspec/changes/email-first-password-recovery/verification-repair-input.json`, SHA-256 `3d1d64f2e5bbbaf1b4a8c6161e27bdaa8bd89f19e822bb218b0c98c54a470627`.
- Reviewed tests: `yii2_activation_password_20261029_test.php` SHA-256 `fa8a569fde6a3505a368447b5919440fb1a244f29dd89caa3d264af5251b3777`; `runtime_recovery_forward_update_001_test.php` SHA-256 `6d4d8919a1f74e10daa16d3f77b934b917791cd43f8eaa2bd3dea5ab79ca44f9`; `yii2_user_access_001_test.php` SHA-256 `7adf720114d9e6b230e1c068b9ce062d82d7430d6c3e7e64aafe9311f91c4c2b`.
- Verdict: **APPROVED**.

## Accepted-password login qualification

The password-policy test keeps the independent reject matrix for missing character groups, invalid UTF-8, email local-part inclusion, length, and confirmation mismatch. It retains the real HTTP first-password form, exact 10/200 field bounds, visible composition help, Argon2id verification, exact byte preservation, no silent trimming, single-use behavior, and existing-hash login.

The corrected accepted matrix now uses four materially different valid values: ASCII punctuation/digit zero, Unicode, exactly 200 Unicode characters (`Aa1!` plus 196 Cyrillic characters), and leading/trailing spaces. For each value, the public owner first accepts and stores the password, the test independently verifies its hash, and a fresh cookie jar must complete ordinary login with the exact same bytes. The spaced case additionally proves the trimmed value does not verify and retains its separate exact-spaced login witness. This closes the previous asymmetry where acceptance was tested for every valid composition but normal login was tested only for selected ASCII/spaced values.

## Historical recovery fixture assessment

The version-22 correction does not alter the pinned historical runtime source or its production recovery implementation. It edits only the extracted historical test fixture before invoking that historical fixture’s public backup seam, inserting one valid Argon2id credential for the already active fixture identity. This makes the historical data coherent with current V40 preflight rather than bypassing preflight.

The regression remains strong:

- historical sources stay pinned to exact commits and images, with literal OCI revision checks;
- historical schema versions, table counts, auto-increment counts, deferred markers, deleted-high-ID frontiers, and old-image self-restore remain literal;
- the seeded credential is present before backup and therefore belongs to the complete `beforeRows` snapshot; the current migration and runtime prepare must preserve its exact hash and every other historical row, sequence value, state byte, and mode;
- only current expectations advance to schema 40 with 109 tables and 61 auto-increment entries;
- the old image must still reject the current bundle with zero target DB/state mutation.

The fixture therefore preserves the historical backup/restore contract while supplying valid source data required by the new additive migration. It does not replace, loosen, or regenerate historical manifest evidence.

## Public user-access marker

Restoring the `PASS: YII2-USER-ACCESS-001 preserved user role/status invariants and rollback` marker changes reporting only. The underlying test continues to exercise public provision/first-password/login, role and status history, session invalidation, direct and HTTP authorization, last-superadministrator protection, replay behavior, transaction rollback, and safe 503 handling. The typed browser-profile execution is exact-source GREEN.

## Evidence assessment

- Record `1795523767479051000-d75096538d674602973e1d5305c60637` is an exact-source intended RED. The owner has already accepted and hashed the valid 200-character Unicode password; ordinary login returns 200 instead of the required 303. The failure is at the real normal-login seam and isolates the byte-length rejection rather than setup or password-policy failure.
- Record `1795523767503343000-0ded64d4f4be48ae9d802f7891d5fec6` is exact-source GREEN for both pinned historical forward-update rehearsals and prints both expected PASS markers.
- Record `1795523997304538000-5f96ba9f6ad64f4d90086e00c94712b8` is exact-source GREEN through the planner-selected browser profile for the user-access regression.

## Verdict

**APPROVED**

No test-design or regression-weakening blocker was found. The bounded login correction may proceed under its separate executor package. This verdict does not approve implementation code, final source, remaining inventory/UNKNOWN obligations, final review, CI, publication, merge, deployment, or live email.
