# Independent final review — PRODUCTION-HANDOFF-001

- Date: 2026-11-16
- Reviewer: independently tasked agent `/root/reviewer`; authored none of the specification, tests, fixtures, or production implementation.
- Fixed base: `42df3e1a93a9ac619057bb35ff8b7f54d714681d`
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261116T175540Z-87bad262d9/package.json`
- Exact source: `f1a26b48c91916a3c2699b1bc1d0e8119d37d79e4f546c63ad1479a1bf01f898`
- Frozen snapshot: `/Users/antropophag/.local/share/fmonitor-2/simplify-production-handoff/final-source/source.patch`, SHA-256 `6dc1e2764bb54bbb811e9be052f35c68cdc6f2e1c1712e61217ff1d677cdf820`
- Verification-plan SHA-256: `5dcf7a1a5374071b216004c51f96e793f2724157cd79738e69f0b43aba30ed46`
- Verdict: **CHANGES_REQUESTED**

## Findings

### F1 — HIGH — installed database image identity is not enforced for release or source update

`deploy/production/recovery.sh:248-251` handles an installed build-mode release
with `installed; image verify`. `resolve_build_identity()` rehydrates only the
application source, image, ID, and platform. A normal source-build `.env` keeps
database ID/platform empty because image discovery never rewrites operator env.
Consequently `image verify` accepts whatever image currently occupies the mutable
`FMONITOR_DATABASE_IMAGE` tag, and `release_cmd()` packages it even when its ID
differs from the installed receipt. This violates H02 and the inherited exact
release-image contract.

The same gap exists at `recovery.sh:153-164`: source update calls `image` before
verifying the receipt's installed database image identity. An externally replaced
database tag can become the candidate and later recreate the DB service, while the
old receipt's database ID was never checked. Status/start/backup correctly use
`installed_image`; release/update must retain equivalent database exactness without
mistaking the new application candidate for the installed application.

Correction: verify the current database reference, ID, and platform against the
installed receipt before release packaging and before update candidate acquisition
or backup. Package only receipt-exact installed images. Add a process-fixture case
that changes the DB tag's observed ID after install and requires release and update
to fail before save, backup, provisioning, or writer effects.

### F2 — HIGH — build-to-pinned update writes stale recovery identity

`deploy/production/recovery.sh:77` rewrites private `restore.env` only when the
*candidate* `FMONITOR_IMAGE_MODE` is `build`. During an installed automatic-build
to pull/local update, current mode is pinned, so the rewrite is skipped. The copied
`installed.env` contains the original operator bytes, whose source/image metadata
is intentionally stale because the automatic build identity was never written
back. The resulting backup therefore cannot identify the actual old installed
source/image/IDs, contrary to H03.

Correction: decide resolution from the installed/backup identity rather than the
candidate mode, or safely resolve the old backup's recovery fields from its receipt
on every applicable update. Preserve pinned old installations unchanged. Add a
build-to-pull/local update witness that inspects the old backup's source, image,
application ID/platform, and database identity.

### F3 — MEDIUM — recovery-config rewrite failures bypass backup cleanup and writer recovery

At `deploy/production/recovery.sh:96-98`, failures from `awk`, `chmod`, or `mv`
return immediately after writers have been stopped and the private stage created.
These new exits bypass the surrounding cleanup/resume handling: a manual backup can
leave its previously running writers stopped, the incomplete stage remains, and no
required safe `stopped`/`resumed` outcome is reported. The inherited backup contract
requires a nonzero failure with explicit writer outcome, and update backup failure
must not publish a partial bundle.

Correction: route all three rewrite/publication failures through the same stage
cleanup and conditional writer-resume path used by adjacent backup failures, with a
safe outcome message. Add a deterministic helper-failure regression proving no
bundle/stage publication and correct manual-backup resume behavior; update mode must
remain stopped.

## Conformance assessment

No other blocker was found. Build deploy/update derives a clean exact Git HEAD,
uses collision-resistant non-overwriting candidate tags, preserves env bytes, and
rehydrates installed application identity for ordinary operations. Repeat deploy
and same-source update behavior is bounded; failed update retains its backup and
marker and blocks start/status. Integration qualification runs after schema,
secrets, and owner preparation but before writers and the installed receipt; it
collects all ERP/workforce/document outcomes, suppresses command streams, supports
jobs-disabled and optional-document `SKIPPED`, and invokes no SMTP, scheduler,
outbox, or legacy import.

The ERP command composes the real SQL Server transport, performs one bounded
read-only `SELECT 1`, rejects malformed shapes and adapter exceptions without
private details, and returns safe JSON plus a matching exit code. Login and query
timeouts are bounded. The canonical workforce/document writers remain unchanged.
Pull/local acquisition, env parsing, locks, ownership, and secret boundaries are
preserved.

## Evidence inspected

The package binds GREEN focused records for the handoff Make/Git test and ERP probe
test to exact source `f1a26b48...`. Adjacent production env, lifecycle, quickstart,
optional-SMTP, native transport, ERP contract/read/owner, verification-plan, and
architecture checks were reported GREEN. The corrected source-scope DB check used
its own random-port disposable MariaDB and passed setup/test/teardown `[0,0,0]`;
the earlier localhost `23306` precondition failure is not counted as product
evidence. Full local `make test`/`make verify` was not run, per owner policy. Exact
source CI remains `UNKNOWN`.

## Decision

Final review is **CHANGES_REQUESTED** on F1-F3. Correct the complete set, add the
bounded regressions, rerun mapped focused checks, and prepare a new immutable source
for delta rereview. CI, PR readiness, merge, and deployment remain `UNKNOWN`.
