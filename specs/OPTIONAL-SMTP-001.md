# OPTIONAL-SMTP-001 — Optional SMTP in integration rehearsal

## Простыми словами

Пустые SMTP-реквизиты отключают почту, не отключая Bitrix/ERP. Никакого нового
флага: пользователь копирует готовый env и выполняет make update. Инструкция
остаётся в scope RUNTIME-HELP-001.

## Contract

Actor: deployment operator via private env and existing Make/Jobs CLI.
S01: Exactly HOST, USERNAME, PASSWORD, FROM_ADDRESS under FMONITOR_SMTP_ control
presence. All absent, empty or whitespace-only means disabled. Metadata such as
port/name/TLS defaults do not enable mail. Any nonblank credential requires the
existing complete SmtpConfiguration validation. Non-string values fail closed.
No new environment keys, weakened TLS, public URL or recipient validation.

S02: Production manage check-config accepts jobs=true with valid Bitrix/ERP and
all four SMTP credentials blank. Every partial credential subset fails before
Docker, without printing values. Four present credentials retain existing
validation; jobs=false remains unchanged. Real integration secrets stay private.

S03: With SMTP disabled, production Jobs health reports actual process/queue
health without requiring mail credentials. Scheduler continues workforce,
document links, equipment facts, utilization and heartbeats, but does not schedule
weekly mail generation or new outbox dispatch. Configured SMTP is fully validated
before scheduler ticks; partial/invalid settings fail with no new jobs/intents.
Production and runtime Compose pass the same existing SMTP configuration to
worker and scheduler; blank/full values are preserved. Complete SMTP preserves
existing scheduling and health behavior, including
scheduling pending intents after re-enable. No transport executes in scheduler.

S04: Already queued outbox.dispatch retains existing permanent
OUTBOX_TRANSPORT_UNCONFIGURED result before DB/recipient/SMTP access when SMTP
is blank. No delivered fact or attempt is fabricated. Existing pending intents
and historical jobs are preserved. Existing queued weekly generators may create
pending intents; they cannot send without configured SMTP. No automatic replay
of failed historical jobs is added. Normal ERP/document handler paths stay usable.

S05: Trial private env enables jobs and copies only real Bitrix/ERP settings from
the existing production handoff, blanks all four SMTP credentials, preserves
local URLs/ports/DB/identity/bootstrap secrets and disables legacy import. Admin
env retains SMTP. Neither secret file enters Git. Rehearsal uses installed-state
make update; no DB reset. Actual corporate integration results remain UNKNOWN
until observed on workstation; no live calls or emails during local verification.

## Adjacent boundaries and exclusions

No domain writer, schema, HTTP authorization, transport, backup format or queue
state change. Existing SMTP tests remain mandatory. CLI integration fixture uses
owned disposable database and synthetic values; scheduler never invokes network.
A test-only CI correction waits for the exact saved address columns after native
async dialog close, retaining all existing assertions. Help runtime remains as
reviewed. No Excel importer or legacy data migration is introduced.
