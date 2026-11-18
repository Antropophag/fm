# PRODUCTION-RUNNER-REPAIR-001

## Простыми словами

Обновление существующей установки можно передать GitLab Runner без смены
владельца общего каталога блокировок. Старый ручной запуск и Runner используют
одну блокировку. Ошибки блокировки называют причину, а не переменную проекта.
В GitHub увеличивается время E2E и сохраняются логи неудачных сборок.

## Scope and authority

Owner18.11.2026 authorizes correcting the runner handover and confirmed code-side
CI failures. This is a bounded sensitive correction of PRODUCTION-MAKE-ENV-001 A02
and QUALITY-GRAPH-CURRENT-CI-001. Root authors contract/tests; separate executors
implement; independent Gate3/final. Full local verification remains prohibited.
Existing operator Docker authority permits a narrowly confined one-shot helper;
no sudo, host permission/ownership changes, new credentials, service, privileged
container, host root mount, data migration or stale-lock reclamation.

## Acceptance

### A01 Existing namespace and operator handover

The public Make/production manage commands retain atomic acquisition of
`/tmp/fmonitor2-production-locks/${COMPOSE_PROJECT_NAME}.lock`. Same-owner operation
and fresh install remain supported without a helper image. When the root is a
regular mode0700 directory owned by another UID, an already installed deployment
may use its verified, immutable, locally available database image to acquire and
release the exact same lock via Docker. No new lock namespace or per-user bypass.
The helper runs as the numeric directory owner/group, with no network, read-only
container root, all capabilities dropped and no-new-privileges, no pull and only
the fixed lock root bind-mounted writable. No application credentials are passed.

### A02 Identity, rejection and diagnostics

Reject symlink/non-directory/wrong-mode roots as `PRODUCTION_LOCK_INVALID`.
An unavailable/unverified installed image or unsupported helper operation fails
closed with `PRODUCTION_LOCK_HELPER_UNAVAILABLE` before application mutation.
Validate the mounted root against the captured host identity (inode, owner, group,
mode; device where meaningful) before touching it, detecting source substitution.
State/env/receipt privacy and installed project/image verification remain in force.
An inaccessible state directory is not repaired by this feature. Neither status
nor a successful config check is advertised as proof of update permission.

### A03 Contention and release

An existing project lock, including stale locks, files and symlinks, blocks with
exit75 and an explicit busy diagnostic. Do not remove or reclaim it. Native old
holder blocks helper contender; helper holder blocks old native mkdir. Different
projects remain independent. Release only a lock acquired by this operation,
checking holder token and directory identity for helper cleanup. Failed acquisition
never schedules cleanup. Cleanup failure leaves the lock and reports a diagnostic;
it must not silently report an entirely successful operation. Trusted Docker/root
operators are outside the adversarial filesystem model; no claim of protection
against an administrator deliberately removing a live lock.

### A04 Lifecycle preservation

Successful handover follows existing update/backup/start/stop behavior, preserves
env bytes, database identity and data, and releases its lock on success or failure.
No build, pull, application exec/up/stop or backup runs when acquisition fails.
The constrained helper itself and read-only Docker inspections are permitted.

### C01 Bounded full E2E

The existing single blocking E2E category gets a 30-minute job budget including
setup. Keep exact-head checkout, category command, full-mode condition, teardown,
aggregation and all test coverage. Renderer owns this setting; regeneration
restores it after drift. No allow-failure, skip, retry loop or second CI consumer.

### C02 Complete build evidence

Only in the E2E CI job, an executable Docker boundary records full stdout/stderr
of actual `docker build`, `docker buildx build` and `docker compose ... build`
operations before individual tests truncate diagnostics. Preserve exit status,
stdout/stderr separation and normal non-build behavior. Force plain progress for
these builds, overriding quiet progress only; do not alter build inputs or invoke
the command twice. Unique private files support concurrent builds; successful
build logs are discarded, failed/interrupted build logs remain in distinct
`<unique>.stdout.log` and `<unique>.stderr.log` files (mode0600). Do not log argv,
environment, config files, Docker exec/run output or application data. E2E runs
with synthetic test configuration and no production secrets. Upload only that
dedicated log directory on failure/cancellation, using a pinned artifact action,
7-day retention, missing-files ignore. Preserve failures even if capture fails.

## Evidence and non-goals

Job5615 proves shell checkout/status/pull succeeded and update failed with
COMPOSE_PROJECT_NAME; actual server lock metadata remains unobserved. Isolated
public-CLI reproduction proves the ownership/mode failure pattern.
GitHub44284790954 attempt1 timed out after77/78 passed; attempt2 has seven runtime
image-build failures. Microsoft HTTP429 was observed in setup; the exact failed
image request is unknown because raw logs were lost. Network retry/Dockerfile
dependency changes are excluded. No business routes/schema/import semantics change.

Before delivery, additionally qualify the unchanged emitted helper shell in a
disposable Linux Docker volume with real numeric UID separation and both native
mkdir/helper contention directions. Adapter tests alone do not qualify Docker's
bind/UID behavior. Docker Desktop can translate inode/UID; identity mismatch must
fail closed there. No success is claimed for remote/translated filesystem contexts.
