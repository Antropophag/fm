# PRODUCTION-MAKE-ENV-001

## Простыми словами

Оператор создаёт `.env`, заполняет адрес сервера/свою учётку/внешние токены и выполняет `make deploy`. Система разворачивает новую БД и приложение; все дальнейшие команды сохраняют данные. Если обычная установка PHP-пакетов недоступна, сборка пробует закреплённые прямые архивы. Готовые Docker images можно перенести архивом без сборки в корпоративной сети.

## Scope and authorization

Owner16.11.2026 явно разрешил реализацию всего описанного deployment workflow с одним `.env`. Root пишет спецификацию/тесты; implementation — отдельные sol/low executors; sensitive credentials/durability требуют Gate3+final по planner. Existing source main7c6b8e14 includes PR294; parallel work/production preserved. Никаких фактических production writes, migrations/imports внешних БД или реальной отправки email в delivery.

## Public seam and acceptance

Актор — оператор с доступом Docker. `make env-init|deploy|status|logs|stop|start|backup|update|restore|release`, `ENV_FILE=/absolute/private/runtime.env` (default `.env`). Низкоуровневый эквивалент — `bash deploy/production/manage <verb> --env-file <path>`. Paths для backup/restore/release передаются явными Make variables и аргументами; точный формат документируется. `check-config` — read-only проверка с кодом0/ненулевым и именами ошибочных ключей без значений. `is-production` позволяет root Make dispatch logs без изменения local logs. Никакие environment values не выполняются как shell/code.

### A01 Single environment

`env-init` создаёт regular `.env` mode0600 через no-clobber publication; повтор возвращает успех/сообщение о сохранении либо безопасный отказ, bytes/key values не меняются. Symlink, directory, nonprivate existing config отклоняются. Генерируются независимые случайные >=32-byte hex DB passwords/cookie/identity/HMAC keys и bootstrap password; внешние credentials/email/domain требуют заполнения. Один файл содержит все операторские secrets; из него автоматически создаются private runtime files, без ручного JSON. `.env` и архивы с данными не входят Git/image/release. Config supports unquoted or paired single/double quoted literal values including dollar, backtick, #, = and spaces; no expansion/interpolation/source/eval. Duplicate/unknown keys, CRLF ambiguity/control chars/invalid identifiers/placeholder required values fail closed before Docker mutations. Shell-inherited FMONITOR values cannot replace file values. Host prerequisites Docker/Compose v2/Make/bash + common core utilities; PHP/Node/Composer/Python are not host prerequisites. Git нужен лишь при source build из checkout.

### A02 Production identity and modes

Production project names `fm2-prod-[a-z0-9][a-z0-9-]*`; default `fm2-prod-fmonitor`. Reject local/foreign project names. Runtime env production; HTTPS public URL, exact trusted hostname and scheme from same config (loopback HTTP allowance only explicit isolated test config). Database/runtime users safe identifiers; runtime and migration users distinct. Image modes `build|pull|local`, explicit `FMONITOR_RUNTIME_IMAGE` and 40-hex source revision. `build` uses current clean source at revision and unique image tag; `pull` fetches digest-pinned image; `local` never pulls/builds, optional verified image archive load (`FMONITOR_IMAGE_ARCHIVE`, обязательный внешний `FMONITOR_IMAGE_ARCHIVE_SHA256`; проверяется даже при уже существующем image). All modes check actual image identity/source label; mismatch refuses before DB changes. Persist installed source/image identity under private deployment state; unknown existing project cannot be adopted. Concurrency serialized for each configured project; lock contention does not mutate resources.

### A03 Empty install and safe replay

Deploy: preflight -> acquire image -> DB -> DML-only account public provisioning -> storage/secret prepare -> canonical schema migrations -> deep runtime check -> initial admin -> web/php and enabled jobs -> health. Never legacy import/demo bootstrap. Initial admin uses env email/password via existing canonical owner, bootstrap secret only one-shot service. Exact repeat is safe; durable initialized deployment marker allows subsequent deploy/start after owner changes password/adds users without resetting credentials or re-provisioning. Partial/foreign identity must not be silently adopted; after incomplete first install only canonical exact replay is allowed. Same installed image deploy replay does not run schema mutation with writers active; changed image requires update. Marker binds source/project/database/image and is published only after successful checks. Persistent data and session keys are preserved.

### A04 Optional integrations and privileges

`FMONITOR_JOBS_ENABLED=false` default: no Bitrix/ERP/SMTP credentials required, no workers/scheduler/external IO. `true`: current combined jobs runtime requires complete Bitrix/ERP/SMTP configuration validated before startup; documentation explicitly states bundle semantics. Worker gets necessary tokens/SMTP/ERP; scheduler gets only needed config, never bootstrap/password-reset authority. DB migrations use separate credential; web runtime stays DML-only. Setup creates config files automatically from env with mode0600 and runtime UID10001. No fake credentials to make a disabled integration look healthy.

### A05 Status lifecycle and local compatibility

`status` reports exact selected project/live/ready and enabled jobs health; disabled jobs displayed as disabled, not failure/success observation. Failed readiness exits nonzero. `stop` and `start` only affect own recorded project; stop never removes volumes; start uses installed image and does not migrate/reprovision/import. Logs route to production only when selected env declares production, otherwise preserve original local behavior. No reset/prune/down --volumes in production public interface. Restart policies/graceful stops/log rotation configured; DB/FPM not host-published; HTTP binds loopback behind external proxy.

### A06 Runtime package

Production image loads `deploy/runtime/php.ini` as actual PHP config; upload/post limits support20MiB PDF plus multipart overhead, nginx limit compatible. Image source label/build identity recorded. Secrets excluded build context; helper scripts required by production copied into image. Existing local compose/Make behavior not silently changed. Public proxy example passes Host and HTTPS configuration, supports upload limits; no secret query logging for activation links. Public services non-root application UID10001, persistent paths outside public document root.

### A07 Backup

`make backup` observes installed exact image/source and records running writer services. Stops scheduler, worker, web/php, confirms stopped, then calls `bin/fmonitor2-runtime-recovery.php backup` from installed image using current canonical schema (v39 at base), data-only dump + state and hashes. Destination outside checkout, mode0700/files0600, unique no-clobber; secrets/config retained separately privately as needed to restore exact keys. Outputs only safe path/status. On success restore previous running services (or keep stopped for update); on error report nonzero with explicit stopped/resumed outcome. Failed/incomplete backup never authorizes update. Current schema/jobs/history/files included, no SQL downgrade and no new alternative schema dump owner.

### A08 Update and failure

`make update` requires initialized own project and new source/image. It acquires/checks new image before interruption, backs up old image/state, keeps writers stopped, prepares current paths/migrates with new image, recreates selected services, verifies health, atomically records new installed identity. On failure no automatic destructive restore, no old runtime restart against unknown migrated schema; preserve backup/old image/markers, leave writers stopped and print safe recovery guidance. Marker records failed/in-progress state so later deploy/start cannot bypass unresolved update. Same source replay cannot duplicate business facts. No changes to secret values on update.

### A09 Restore empty target

`make restore BACKUP=<absolute bundle>` is explicit recovery only. Validate hashes/source/image/config before any target data effect; requires a NEW production project with absent containers/network/volumes, different from source manifest. Existing target rejected with no writes. Calls existing RuntimeRecovery restore with migration credential to create canonical schema/import exact data and restore AUTO_INCREMENT/state; parent volume mount keeps target state root absent initially. Restore binds original session identity/keys from private backup configuration, replaces only target infrastructure identifiers; regenerates target DB secret, then deep check/start web and explicit jobs setting. Never restore into source or overwrite existing data. Missing/corrupt bundle or wrong source/image fails; invalid backup must not create DB resources. Source remains untouched.

### A10 Release transfer

`make release` creates a no-clobber package with application+DB images, source identity, required deployment files and manifest/hashes, excluding `.env`, runtime state, backup/private logs. Import/local mode verifies package checksum and image identity; supports air-gapped runtime start with already transferred images (no Composer/npm/apt at deploy). Architecture and exact image identifiers documented. Generation does not publish to registry or change running services.

### A11 Composer-first fallback

`bash deploy/dependencies/install --project <root> --manifest <json> --composer <executable> --mode auto|composer|direct` (defaults: current project, packaged manifest/Composer, auto); `--check-manifest` validates exact input without network/install. `--archive-dir <dir>` uses verified cached `<sha256>.zip` files (missing downloads; present corrupt file fails without replacement). Production build installer first attempts normal pinned Composer install --no-dev --prefer-dist with allowlisted Yii plugin and lock. If unavailable, automatically download every production package from reviewed direct HTTPS archive URL pinned to lock reference and SHA256 manifest. Manifest covers exactly lock prod packages/name/version/reference; original composer.json/lock remain unchanged. Version/lock drift, missing hash, TLS/hash error or corrupt ZIP fails closed. Fallback may use private lock copy with ONLY dist location changed to verified local files; Composer performs installation/autoload/plugins/platform check offline. Never guess latest, disable TLS, manually invent autoload, ignore platform requirements, or silently drop packages. Partial vendor is not published as success; second install idempotent. Bounded download timeouts/retries; log no auth tokens. Direct download is fallback for blocked Composer repositories, not a promise that GitHub/all external hosts are reachable.

### A12 Dependency evidence

Verify normal path does not invoke fallback when successful; forced repository failure uses exact direct paths; corrupt archive/missing manifest/lock mismatch/platform failure return nonzero. Verify real offline Composer plugin install, Yii/TCPDF autoload and InstalledVersions equivalent to lock; original lock bytes stable. Build production artifact verifies loaded PHP settings and dependencies. Fallback downloads exact archives; fully offline server uses release images.

### A13 Delivery acceptance and no live effects

Root tests cover CLI parse/atomic env/no secrets, orchestration stop/fail/replay ordering through Docker executable boundary, actual clean Docker install/restart/owner-login/PDF upload and backup/restore isolated roundtrip, forced dependency fallback and SHA failures. Full suite only CI; local tests bounded. No external integration sends during acceptance. Record independent reviews, exact source/CI, limitations (no corporate server access/real TLS proxy assertion without evidence).

## Excluded changes

Business contracts, automatic legacy loading, direct SQL domain updates, unattended password/key rotation, automatic in-place disaster rollback, backup retention/offsite service provisioning, corporate DNS/certificate issuance and modifications to running production are outside scope. Deployment includes instructions/commands for operator scheduling/copy, without claiming an RPO/RTO already achieved.
