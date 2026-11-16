# PRODUCTION-MAKE-QUICKSTART-001

Q03 manual source/image handoff metadata is superseded for build installs by
[PRODUCTION-HANDOFF-001](PRODUCTION-HANDOFF-001.md). The same env survives Git updates.

Bounded correction of the established one-env production Make operator workflow.
Normative delta to PRODUCTION-MAKE-ENV-001; existing identity, health, secrecy,
backup/update/recovery and no-legacy-import contracts remain unchanged.

## Q01 Copied env, one command
A clean checkout with a prepared regular operator-owned single-link root `.env`
can start via `make deploy` without ENV_FILE, chmod, sudo, env-init or pre-created
private state directories. Deploy narrows copied file permissions to0600 and
verifies exact unchanged bytes and file identity before parsing/Docker. Symlinks,
hardlinks, nonregular files and foreign ownership reject before chmod/Docker;
unfixable permissions reject before Docker with a WSL/Linux-filesystem hint.
No shell evaluation, secret output, password regeneration or content rewrite.
Other verbs and check-config keep strict readonly env validation (0644 rejected).
Permission narrowing is limited to the repository-root `.env`; an explicit path
to another env retains strict0600 validation. Root env parsing uses bytes captured
from the validated descriptor, with pathname identity/content rechecked after
capture/parsing; late same-byte replacement must fail before Docker.

## Q02 Managed paths
The exact literal `auto` is accepted only for FMONITOR_DEPLOY_STATE_DIR and
FMONITOR_BACKUP_ROOT. Resolve in memory under canonical operator HOME to
`$HOME/.fmonitor2-production/<validated COMPOSE_PROJECT_NAME>/state` and `/backups`.
Only deploy creates absent managed components, one at a time, mode0700 and current
UID, after scalar config validation. Existing symlink/file/foreign/public roots
reject unchanged; never recursively chmod/chown/adopt existing paths. HOME must
be absolute, real, owned and a directory; no interpolation of arbitrary values.
Check-config may validate an absent auto hierarchy without creating anything;
other lifecycle commands require their existing owned hierarchy. Explicit absolute
paths retain current strict validation; their parent directories are never auto-created/adopted.

## Q03 Identity and replay
Env-init emits portable auto paths and does not create managed state directories.
Original env bytes remain the receipt config hash source. Two projects get distinct
managed paths. Repeated deploy preserves keys, account and data; concurrent setup
must not loosen ownership or bypass the existing project operation lock. Source
revision/image identity remain explicit/exact (no mutable latest or hidden pull).
Prepared handoffs name the exact published source; a fresh clone needs no manual
checkout command while main matches that handoff.

## Q04 Failure and actual qualification
Missing/malformed env, invalid project, unsafe existing path, hardlink/symlink and
unfixable mode fail without Docker effects or unrelated chmod/chown. No secret
values in diagnostics. Validate actual fresh-checkout + root env0644 + absent
managed HOME hierarchy + plain make deploy, real initial login, repeat deploy and
stop/start on a disposable Docker project. Env content and persistent marker remain.
No external integration sends or legacy import; no existing runtime is touched.
Focused local checks and one exact-source full GitHub CI, independent required
reviews. Do not rerun GREEN solely for the known missing admission adapter.

## Delivery and operator scope
Update source and standalone release operator instructions to the same three-step
flow. Publish through GitHub tests and then only main in corporate GitLab. Private
production and workstation env values are preserved except the two path settings
and the exact delivered source/image metadata. Trial stays loopback HTTP/jobs=false;
production retains HTTPS/integration config. No new host PHP/Python dependency.
