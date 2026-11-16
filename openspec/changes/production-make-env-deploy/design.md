## Context

Fresh main7c6b8e14 includes PR294. Existing Make is deliberately local and cannot be reused as production validator. Existing RuntimeRecovery supports CURRENT_SCHEMA_VERSION39; historical v23 runbook is obsolete for current acceptance. Canonical migration/provision owners retain DB writes.

## Goals / Non-Goals

Goals: implement PRODUCTION-MAKE-ENV-001 A01–A13; one `.env`, simple Make, exact source images, verified fallback, repeatable recovery.
Non-Goals: live deployment, merge, legacy/business migration, changing domain rules, in-place restore.

## Decisions

- `deploy/production/manage` owns host orchestration; root Make aliases simple verbs, `logs` dispatches based on safe production declaration to preserve local path. Strict separate config parser, no shell source. `ENV_FILE` chooses one operator file.
- Separate production compose preserves existing local runtime; mount state volume at parent directory for empty-target canonical restore. App DB credentials only DML, provisioning/migration separate one-shot. Lock/installed receipt protect source/project identities and replay.
- Files needed by runtime are mechanically generated from env; bootstrap owner only before successful installation receipt. No reuse of misleading local resume semantics in production.
- Current public RuntimeRecovery handles backup+restore including canonical schema and data. Wrapper captures exact image/config metadata and protects target ownership. No full DDL dump roundtrip.
- Jobs is one explicit opt-in bundle because current runtime couples Bitrix/ERP/SMTP; disabled bundle has no required credentials. No external probes that send email.
- Production dependency installer attempts Composer first, then verified archives and offline Composer using temporary lock dist locations. Lock versions and allowlisted plugin/autoload retained. SHA256 manifest accompanies original lock. Image archive includes DB and application to bypass all build-network requirements on final server.
- New implementation paths under deploy; production Dockerfile is generated from tools/delivery/Dockerfile.runtime.in. Root-authored black-box tests registered in suites.tsv; no changes to CI admission policy. Schema frontier remains39, no new tables. Backup/restore regression covers current owner; business photos/order/history remain unchanged.

## Risks / Trade-offs

- Corporate network may block direct archive host too → fail with clear reason; deploy release image archive.
- Secret literals/Compose expansion → parser never evaluates, exports allowlisted literal values and disables implicit env-file loading; tests include dollar/backtick/quotes.
- Partial update → stopped writers + retained old backup + failure receipt; no speculative automatic rollback.
- No knowledge of actual corporate DNS/CA → reverse proxy example + documented operator handoff, isolated acceptance cannot claim corporate deployment.

## Migration Plan

New production installation uses fresh project/volumes. Existing local environment untouched. Production update uses backup and forward canonical migrations. Restore creates a different empty project, retains source. Package does not merge/deploy itself.
