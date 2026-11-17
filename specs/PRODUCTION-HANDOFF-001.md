# PRODUCTION-HANDOFF-001 — stable env and verified integrations

## Простыми словами
Администратор клонирует проект, копирует готовый production `.env` и вызывает
`make deploy`. Обновление: `git pull --ff-only && make update`, без правки env.
Пустой список объектов нормален. Legacy import и будущий Excel loader не входят.
ERP проверяется реальным ограниченным read-only запросом, кадровый Bitrix и
настроенный Bitrix Disk проходят первую штатную синхронизацию до успеха deploy.

## Scope and inherited contract
Bounded correction of PRODUCTION-MAKE-ENV-001 / PRODUCTION-MAKE-QUICKSTART-001.
Their secret parsing, locks, storage ownership, exact image verification, backup,
failure and isolated restore guarantees remain. This supersedes manual source/image
fields for source-build installs only. No schema/domain policy/HTTP changes.

## H01 Stable operator environment
For source-checkout `build` deploy/update derive exact clean Git HEAD automatically,
ignoring obsolete source/image metadata in an existing handoff env. New env-init
must not require operator-maintained revision/image values. Original env bytes,
secrets and permissions remain stable (existing root-copy chmod exception retained).
No shell evaluation or secret output. Dirty tracked/untracked source rejects before
build or database effects. Pull/local/archive inputs remain explicitly pinned and
retain all source label, digest, image ID and platform checks; never infer latest.

## H02 Installed versus candidate version
First deploy records actual source/image/ID. Same-source repeat deploy/update reuses
installed image; no duplicate bootstrap, migration or first-sync writes. A changed
source is applied only by update, after backup of old source with writers stopped.
After git pull but before update, status/start/stop/logs/backup use installed identity,
not checkout HEAD. Release of an installed source installation uses installed exact
images. Candidate tags cannot overwrite an old image. Config-only update on the same
source may reuse the verified image. Existing explicit build receipts remain usable.
Operations remain serialized by project lock. No env content rewrite on any path.

## H03 Backup and failure compatibility
Backup private restore.env must identify effective installed source/image/IDs despite
stale or automatic user metadata; keep original user-config hash validation separate
from the resolved recovery config hash. Restore retains pinned local/image identity
and new-target-only semantics, including old bundles. Update failure retains backup,
old image and failed marker, leaves writers stopped and returns nonzero. Status and
start cannot bypass failed updates. Failed initial deploy remains safely retryable.

## H04 Integration qualification
When jobs=true, after schema and secret preparation and before starting writers and
publishing installed receipt, deploy/update must execute: ERP connectivity probe,
canonical workforce sync, and canonical document-link sync iff URL/root configured.
Each configured integration reports `INTEGRATION_CHECK <erp|workforce|documents>
<OK|FAILED>`; absent document config reports SKIPPED. Jobs=false reports SKIPPED and
makes no external check. Collect all integration outcomes, nonzero if any fails.
Do not forward raw stdout/stderr/errors/URLs/tokens from integrations. No SMTP test
send, outbox dispatch, scheduler tick or legacy import as part of qualification.
Use the real existing application owners for workforce/doc writes, no direct SQL
mutation/new sync writer. A successful empty catalogue is valid. Generic status and
health remain read-only and do not start network syncs. Repeat installed deploy does
not repeat writes; changed deployment is requalified.

## H05 ERP public probe
`php bin/yii erp-equipment-facts-sync/probe --interactive=0` uses configured read-only
SQL Server credentials and always performs one bounded SELECT 1 even with no local
objects. It does not enumerate ERP objects, query local domain rows, import objects,
publish equipment facts or mutate either database. Connection/query timeouts bounded
by configured timeout. Exit0 and safe status completed only for the expected response;
connection/query/shape errors produce failed status/nonzero without exception details.
The expected response is exactly one row with the sole key `available`, whose value
is either integer `1` or string `"1"` (PDO SQL Server may return either). No loose
numeric coercion: booleans, floats, null, other strings, missing/extra columns and
multiple rows fail. Both representations must succeed through the public CLI.
Public callable transport seam `NativeErpEquipmentFactsDelivery::probe(): array` (constructor-injected transport)
allows a deterministic external-adapter witness. The production controller composes
this seam with the real SQL Server transport, not a test flag/fake adapter.

## H06 Verification and adjacent flows
Root-authored subprocess tests exercise Make with an external Docker process fixture:
clean Git A -> deploy -> Git B -> status A -> update B -> replay, same env bytes;
backup resolved config; failed update; dirty source; pinned modes; jobs-disabled;
independent integration failures and secret suppression. PHP callable probe checks
success, exception, malformed response, bounded read-only query and no local object
dependency. Existing production env/lifecycle/quickstart/optional-SMTP checks remain.
CI performs existing real Docker installation/backup/restore checks on exact source.
No live production or credential changes; no real Bitrix/ERP access needed for tests.

## H07 Resume failed first deployment after a source correction
For build-mode `make deploy`, an exact `initializing` receipt may advance to a
different clean Git HEAD. Verify existing project/installation identity, recorded
resource identities and database image identity before acquiring the new image.
Build a uniquely tagged candidate and verify its source/image/platform before
atomically replacing the initializing receipt; never overwrite the old image.
Build/validation failures preserve the old receipt. Resume provisioning/bootstrap
and integration qualification with the candidate without deleting volumes or data,
resetting credentials, changing env bytes or rerunning legacy import. A later
qualification failure leaves the candidate initializing for a same-source retry.
Installed receipts retain their installed image on deploy and use update for source
changes. Pinned pull/local modes and failed/updating receipts never acquire this
source-advance behavior. Identity/resource/database drift and dirty source reject
before build or provisioning. No schema change is introduced by this correction.
