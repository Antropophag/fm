# PRODUCTION-MAKE-QUICKSTART-001 — independent final review

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`
- Base: `1e4b6a61f444d7cf2a808eebc8fdf9f5aba9c711`
- Reviewed HEAD: `9a022931d100676bab4b50b4366b60c68b519c0c`
- Exact candidate source: `95738b0868ee7c075e050d2d4c1f7c5c9f9f254ef30cfa06e513d3d22b10eca1`
- Executable source: `dc0a3eff5828da247c5685409622e2a7b1b190bfcd7b623115eed835ddf07c03`
- Reviewer package: `20261116T111531Z-837c81446e`
- Package SHA-256: `20edc838737b9910b44d7595ef82580d8ada77cc61ce2f65f149bfe1c2dd6cc4`
- Verification plan SHA-256: `b85b34d9563d786d85548d675ba5a4646e3999bee4c5037beb61d3281ffd6f15`
- Harness final record: `APPROVED`, recorded `2026-11-16T11:17:45Z`
- Verdict: **APPROVED**

The reviewer authored neither the root contract/tests nor the separate executor implementation. Gate 3 first returned three test gaps, then approved the complete corrected expectations. Final implementation review found and closed two additional issues: root env parsing is descriptor-snapshot bound through parse and post-parse identity verification, and deploy-time permission narrowing is limited to the canonical repository-root `.env`; explicit external env files retain strict mode validation.

## Final implementation assessment

The implementation preserves the established production lifecycle and adds only the approved quickstart seam:

- default `make deploy` may narrow an operator-owned, regular, single-link repository-root `.env` from copied mode to `0600` without replacing it or changing bytes;
- parsing uses bytes captured from the validated descriptor, with digest and device/inode/UID/link-count/mode checks before and after parsing;
- symlink, hardlink, nonregular, foreign-owned, unfixable, and late-replaced env paths reject before Docker;
- the exact `auto` aliases resolve in memory to an owned per-project HOME hierarchy, created only by deploy as mode `0700` components;
- readonly validation creates nothing, other lifecycle verbs require the hierarchy, and explicit absolute paths retain their previous strict behavior;
- env-init emits portable `auto` values without creating directories;
- project separation, existing state, operation locks, source/image identity, secrets, jobs behavior, backup/update/restore, and no-legacy-import behavior remain unchanged.

The ordinary-clone live test uses a separate bare upstream advertising `main`, then a non-shared `file://` clone with no checkout repair. It copies `.env` mode `0644` to the root and executes plain Make, qualifying the requested GitLab-style workflow rather than a local worktree shortcut.

## Evidence

The final package contains two authoritative exact-source GREEN records with no missing mapped tests:

- `python3 tests/Deployment/production_make_quickstart_live_001_test.py` — record `1794827593055057000-2631f4c0a42a4163a54a014866b125a6`, command blob `20062199c86e6ea4f414a286117600c12ce620c1d7c4df75fb559779dfdb860d`
- `python3 tests/Deployment/production_make_quickstart_001_test.py` — record `1794827689138931000-8a61dfdae91d499ab8ed37bb232de2dc`, command blob `33b2977fbcfea0ac4d1bad114236bcd130926c4455d75b2442ed805df45ca3d8`

The live record covers ordinary clone/main identity, actual source build, copied root env permission narrowing with stable identity/bytes, absent managed hierarchy creation, owner login, deploy replay, stop/start, session/data preservation, jobs disabled, receipt hash/source, and guarded cleanup. The unit record covers 16 cases including file races, unsafe types/ownership/modes, readonly behavior, absolute compatibility, literals, two projects, and concurrent locking.

Three other planned local checks are GREEN:

- pilot jobs Compose `1794827594339161000-59b50004b5ef466985f9e54b2af37c86`
- change verification `1794827594351346000-b155411794e64134a68560f346db8484`
- architecture guard `1794827594339423000-1e62c1478c2c4c39aa6613afd2bc0fd8`

An immediately preceding 16-case command exited successfully but its harness record was marked `UNKNOWN`/stale because a concurrent governance fixture changed the source fingerprint. It is retained honestly and is not used as GREEN; the settled-source rerun above is authoritative. The reconstructed `6aa85302` correction RED contains exactly the late-replacement and explicit-external-env failures, with the other 14 cases passing.

## Final boundary

Gate 5 is **APPROVED** for the exact candidate and executable source above. No implementation findings remain. Full exact-source GitHub CI is still pending, and the known missing I1 automatic admission adapter remains `UNKNOWN`; neither condition is represented as GREEN or bypassed. This review does not authorize production deployment or merge by itself.
