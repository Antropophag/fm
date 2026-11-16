# PRODUCTION-MAKE-QUICKSTART-001 — independent Gate 3 review

- Date: `2026-11-16`
- Reviewer: separately tasked `/root/independent_review`
- Base: `1e4b6a61f444d7cf2a808eebc8fdf9f5aba9c711`
- Candidate source: `d22aceb2a11b5ed39e823c3f06587c1cc88cbdba2442c432c4ce43eb44949522`
- Reviewer package: `20261116T104605Z-e69fba7dd1`, SHA-256 `711d760a422a777dafd4136fbdf3de7bf86fa3308b1bcbff68ed6a2f1c470d3e`
- Verification plan SHA-256: `c7fb432853644af84aedfd02fea026988cc436b936381beb0cb0f4ad1c988818`
- Contract SHA-256: `a98df310c6884f459daa705ea824dc0fffb451377e783815d616eae52a9e702a`
- Unit test SHA-256: `e5da60989676302e64dba1617ee07357331726ed2fb66ea30e1d37dc06d3ed6a`
- Live test SHA-256: `a59885c895a4f5bfa84fb850c29030139e29289b8b96d0753f5a2933037de340`
- Harness Gate 3 record: `CHANGES_REQUESTED`, recorded `2026-11-16T10:47:50Z`
- Verdict: **CHANGES_REQUESTED**

The reviewer authored neither the contract/tests nor the future implementation. The package contains two exact-source `INTENDED_RED` records and no missing mapped tests. The unit RED reaches eleven cases with six intended failures against the absent quickstart behavior; the live RED reaches the real fresh-clone/default-Make path and fails on strict root-env permissions before Docker rather than on unrelated setup.

## Findings

### T1 — BLOCKING: the live clone test performs the manual checkout the contract removes

`tests/Deployment/production_make_quickstart_live_001_test.py:50-52` reads the source revision, clones, and then explicitly runs `git checkout --detach <revision>`. Q03 and the user workflow require an ordinary clone of corporate GitLab main to need no manual checkout while the prepared handoff names that published main. The test can pass if the cloned repository's default branch/HEAD is stale or points elsewhere because it repairs the clone before copying `.env`.

Use a normal isolated clone whose advertised default HEAD is `main`, assert its initial `HEAD`, branch/ref, and tree already equal the prepared source, and do not run checkout/reset. Local clone transport is acceptable for bounded acceptance, but avoid `--shared` if the goal is to qualify a self-contained transferred checkout; at minimum run connectivity/object verification and prove the checkout remains valid without the source worktree.

### T2 — BLOCKING: unchanged bytes do not prove unchanged file identity or close the chmod swap

Q01 requires deploy to verify unchanged bytes **and file identity** around the only authorized mutation. The unit and live tests save `read_bytes()` and compare content after deploy, but never retain or compare `st_dev`, `st_ino`, link count, owner, or file type. An implementation that replaces `.env` with a new same-byte file and mode `0600` would pass. There is also no deterministic case where the pathname is swapped after the initial safety check or during the chmod boundary; hardlink/symlink cases are only present before invocation.

Record the original regular file's device/inode/UID/link-count and assert the same identity after successful deploy and replay. Add a chmod-boundary shim that replaces or relinks the pathname after the chmod attempt while preserving bytes, and require rejection before parse/Docker with the foreign files and their modes unchanged. This makes the contract's anti-TOCTOU promise executable rather than editorial.

### T3 — MAJOR: ownership and existing managed-leaf rejection are incomplete

Q02 requires HOME itself and every existing managed component to be real, owned by the current UID, and mode `0700`. `test_invalid_values_and_home_do_not_create_managed_hierarchy` covers relative and symlink HOME but not foreign-owned HOME. The stat shim covers foreign ownership for `.env` and the managed root, while `test_unsafe_managed_leaf_is_not_followed` covers only symlink `state`/`backups`. A foreign-owned, public-mode, or regular-file managed leaf could be adopted by a defective implementation without failing this suite.

Extend the existing stat shim to HOME, project directory, `state`, and `backups`. For each leaf, independently cover mode `0755`, regular file, symlink, and simulated foreign ownership; require zero Docker calls and exact preservation. Retain the explicit-absolute compatibility case.

## Confirmed properties

The delta is otherwise well bounded. The unit test copies the actual root Makefile and production CLI, calls default `make deploy`, proves only deploy may narrow `.env`, checks literal non-execution and secret non-output, distinguishes readonly check-config and other verbs, preserves explicit absolute paths, rejects unsafe project/HOME/root types, separates two projects, preserves an existing state sentinel, and exercises project-lock contention before a second Docker call. The live case uses a unique project/image/port, generated secrets, jobs disabled, real source build, owner login, replay, stop/start, session/data preservation, receipt hash/source assertions, and guarded cleanup. It performs no integration sends or legacy import.

The scope keeps source/image identity explicit and changes only the two `auto` aliases plus deploy-time handling of a prepared root `.env`; it does not weaken existing absolute-path or lifecycle contracts.

## Gate consequence

Gate 3 is **CHANGES_REQUESTED** for T1-T3. Correct the tests only, retain fresh exact-source intended RED records, and request another independent Gate 3 review before implementation. No full local suite or Docker rerun is required for the rereview beyond the bounded mapped evidence.

---

## Gate 3 rereview — complete corrected candidate

- Date: `2026-11-16`
- Candidate source: `3c91c4c45ef3eae2e10018b46fb239ce366b5c226fe87b9ea677a8b163940c47`
- Reviewer package: `20261116T105258Z-9456e7b462`, SHA-256 `215585568ddcfdc98d6afe0710cf94bb2b578b6d7970e6cfbcf27550d9c30628`
- Verification plan SHA-256: `9b7c80fd0eaa412c77b8bc338b170bc38ff0676302a3cec9a0a869f5f33b0660`
- Unit test SHA-256: `9b5d44a5997c9e09e160ee258c878bdb37705c066af08bc57ddc9f578fecb30b`
- Live test SHA-256: `20062199c86e6ea4f414a286117600c12ce620c1d7c4df75fb559779dfdb860d`
- Fresh evidence: two exact-source `INTENDED_RED` records, no missing mapped tests
- Harness Gate 3 record: `APPROVED`, recorded `2026-11-16T10:54:17Z`
- Verdict: **APPROVED**

### T1 disposition — resolved

The live test prepares a separate bare upstream whose advertised `HEAD` is `main` at the candidate revision, then performs an ordinary `file://` clone. The target uses neither `--shared` nor checkout/reset repair and independently asserts the initial symbolic branch and exact `HEAD`. This exercises the same default-branch property required of the corporate GitLab handoff while remaining isolated from external services.

### T2 disposition — resolved

Successful unit and live paths now bind the original bytes to `st_dev`, `st_ino`, `st_uid`, and `st_nlink` before deploy and after replay. Two deterministic chmod-boundary cases replace the pathname on the first permission operation with either an unrelated symlink or a new same-byte regular file. Both require explicit boundary reach, rejection before Docker, preservation of the original inode/bytes, and no mode/content change to the unrelated victim. An implementation must therefore bind permission narrowing to the originally opened file and detect pathname identity drift.

### T3 disposition — resolved

The matrix now simulates foreign ownership for HOME, the managed root, project, state, and backups. Existing project and leaf paths independently cover public mode, symlink, and regular-file forms, with inode/mode/owner preservation and no writes through the outside target. Relative/symlink HOME, invalid scalars, explicit absolute compatibility, and zero-Docker rejection remain covered.

### Approval boundary

No Gate 3 findings remain. The contract and corrected tests are **APPROVED** for separate minimal implementation. Expectations must remain frozen; implementation still requires focused GREEN, the real isolated live qualification, independent final review, and exact-source full GitHub CI. This verdict does not authorize production deployment or merge.
