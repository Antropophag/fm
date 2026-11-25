# EMAIL-FIRST-PASSWORD-RECOVERY-001 — independent final code review

- Reviewer: `/root/gate3_review` (independent final reviewer; authored none of the specification, tests, application implementation, schema implementation, or retained evidence).
- Review date: 2026-11-24.
- Base: `5e37074db0b9d6528e660d92c13cc93024d4ea28`.
- Exact reviewed candidate source: `e4a91efbe0f69267e00a1dc00658f4c42b785c7997b97053b1bd8028bcd75ddb`; executable source `b7d96db2f838e048148ba0f7f3aff2a7db8cf439ae5ea6efb0f21eb982151c5d`.
- Final reviewer package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T131218Z-51ead078ae/package.json`, SHA-256 `efdd337d4203ee0e25f72e72afd4064c575b4f0664edb7a81d5e75929e89f405`.
- Required-context SHA-256: `ce569524e4a17e2706cb01f66baee6d573681bb68c1cdc868b1b7920d52715c9`.
- Verification-plan SHA-256: `fdc247943bdc93cdecaafacbfef254bbcec85d2f2680350af6edf47ecbf98990`.
- Normative specification: `specs/EMAIL-FIRST-PASSWORD-RECOVERY-001.md`, SHA-256 `f7d333bc921d7d194cdf33d7f8a0e3bbad8d9d887d935f474649a50d24e8228f`.
- Authorship: root authored specifications and executable tests; separate gpt-5.6-sol/low executors authored application/runtime and canonical-schema changes; this reviewer remained independent throughout Gate 3 and final review.
- Verdict: **APPROVED**.

## Final behavior assessment

The candidate implements the reviewed email-first model through one active `YiiUserAccess` owner:

- administrators with exact current authority provision a normalized corporate email, pending-password identity, null credential, default role and audit atomically, with concurrent duplicate and permission-revocation serialization;
- pending identities set one policy-compliant first password through the portal without invitation, email, auto-login, role changes or overwrite;
- ordinary login selects initial/password/absent behavior by the stored identity and accepts every password composition the owner can create, including exact 200-character Unicode and untrimmed values;
- forgot-password returns a neutral response for eligible and ineligible identities, records bounded attempts for malformed inputs, applies atomic cooldown/email/IP quotas, rotates session request identities while preserving same-form replay, and creates grants/outbox only for active identities with passwords;
- recovery grants use DB UTC, exact 30-minute expiry, SHA-256 token lookup and XChaCha20-Poly1305 envelopes authenticated by request ID, user, normalized email, session generation and key ID;
- reset is single-use and transactionally changes the hash, increments session generation, revokes all outstanding grants, closes old sessions, preserves roles and never logs in automatically;
- delivery rechecks eligibility, durably claims `unknown` and erases the envelope before network entry, prevents overlapping/replayed sends, restores the same envelope only for a known transient result after fresh eligibility, and leaves transport exceptions or settlement failures terminal unknown without automatic resend;
- active Yii invitation methods and endpoints cannot create, reveal, reissue or activate invitations; historical enum values, rows, adapters and pinned migration/recovery evidence remain representable but are not active writers.

## Schema, migration and retention assessment

V40 remains additive. It preserves the five-value enum representation, existing IDs, roles, hashes, block states, invitation rows, legacy links and append-only role/status history. Inconsistent active/waiting credential data fails closed. Known partial prefixes resume without dropping or rewriting existing facts, identities, credentials, ciphertext, delivery state or sequence gaps.

The implementation validates the full physical manifest: table engine/collation, every column type/null/default/extra/collation, ordered indexes with uniqueness and prefix parts, and FK target/actions. The independent literal manifest and eleven mutation cases are GREEN.

Backup/restore uses the exact V40 inventory of 109 tables and 61 auto-increment entries. The real recovery round trip preserves all three new tables byte-for-byte, including ciphertext and unknown delivery state, plus next values 800/900. Historical V22/V23 images and manifests remain pinned; a valid synthetic credential is added to historical fixture data before its public backup so current preflight can migrate it without weakening historical row, hash, sequence, state or old-tool rejection assertions.

## HTTP, jobs, SMTP, environment and logging assessment

The active routes enforce POST/CSRF/method boundaries and safe 400/403/405/410/503 outcomes. Forgot and reset responses are no-store/no-referrer through the real response pipeline; reset GET/HEAD does not consume a grant. Reset pages use local assets only, and exact nginx locations keep token query/header/body material out of access/error diagnostics while retaining ordinary upstream diagnostics.

The jobs handler accepts only the exact email/password-recovery/version-1 intent, uses the generic outbox/lease settlement path, and maps unknown SMTP acceptance to terminal delivery uncertainty. Existing weekly email behavior remains covered.

All three supported Compose contours are qualified with all profiles. Recovery key/key ID reach PHP and jobs-worker only. SMTP reaches PHP, jobs-worker and jobs-scheduler, whose existing configuration check enables outbox/weekly scheduling. Prepare, migrate, startup-check, local integration, web, DB and staging services do not receive those secrets. The production operator and canonical local-runtime environment parser accept the new private variables without printing them. Trusted recovery configuration accepts an HTTPS host with optional port and rejects userinfo, path, query and fragment.

## Advance final-review findings disposition

1. **Fixed:** same submitted recovery form remains neutral/idempotent; a later same-session GET receives a new command identity and can issue another eligible request.
2. **Fixed:** delivery is durably claimed before transport, overlapping owners send once, and accepted/unknown/failing settlement cannot automatically resend.
3. **Fixed:** plain-text instructions contain real newlines.
4. **Not applicable as a defect:** the global response pipeline already supplied no-store/no-referrer to forgot GET/POST; explicit HTTP assertions retain this guarantee.
5. **Fixed:** pending-password directory rows show first-login status without invitation-expiry language.
6. **Fixed after dependency correction:** recovery secrets and SMTP are scoped to their actual consumers; jobs-scheduler correctly retains SMTP because it gates outbox and weekly scheduling.
7. **Fixed:** oversized or malformed email/client inputs produce neutral bounded quota-attempt facts without DB errors or grants.
8. **Fixed:** public base configuration is a strict HTTPS origin.

No additional code, schema, HTTP, jobs, SMTP, environment, logging or retention finding remains.

## Gate 3 lineage

The final tests retain the complete independent review lineage:

- `EMAIL-FIRST-PASSWORD-RECOVERY-001.md`: initial CHANGES_REQUESTED followed by APPROVED after six complete corrections;
- `EMAIL-FIRST-PASSWORD-RECOVERY-SCHEMA-001.md`: implementation-derived manifest and partial-preservation findings corrected through a complete literal/populated-prefix matrix, then APPROVED;
- `EMAIL-FIRST-PASSWORD-RECOVERY-INTEGRITY-001.md`: retired facade, DB clock, authenticated envelope, exact intent version and SMTP readiness APPROVED;
- `EMAIL-FIRST-PASSWORD-RECOVERY-QUALIFICATION-001.md`: accepted Unicode login and historical forward-update fixture APPROVED;
- `EMAIL-FIRST-PASSWORD-RECOVERY-DELIVERY-001.md`: request lifecycle, durable delivery, text/UI/input/origin and three-contour dependency corrections APPROVED after the scheduler dependency correction.

No prior RED was silently reclassified. Fixture changes seed only data required for coherent current migration, preserve existing hashes/history, or update current-frontier expectations while leaving historical version classes, source hashes and legacy adapter oracles intact.

## Verification evidence

All 12 mapped final checks in the reviewer package are exact-source GREEN:

- production recovery configuration across three contours;
- V40 migration/history and strict schema/partial resume;
- real jobs backup/restore;
- first-password HTTP and browser flow;
- recovery application, delivery safety, production edges and integrity;
- provision-versus-revocation race;
- 10–200 Unicode password policy and ordinary login.

`make architecture-check`, including the PilotHttp global qualification, is GREEN at record `1795525798514114000-d03c7a6c4635467eabb6871c593eff74`. Additional exact-source GREEN evidence covers unchanged optional-SMTP behavior, the local-runtime/ERP environment parser, generated dependency consistency, and the typed browser-profile legacy-link flow.

The broader bounded inventory and triage are retained externally. The unregistered historical `OtizBrowserFixture` diagnostic exposed an unrelated old OTIZ form timeout; its experimental edit was reverted byte-for-byte, it is outside canonical suites/current Yii, and no GREEN is claimed for it. The repository-mandated full local suite was not run.

## Final verdict

**APPROVED** for exact candidate source `e4a91efbe0f69267e00a1dc00658f4c42b785c7997b97053b1bd8028bcd75ddb`.

The candidate is ready for the remaining delivery steps: identity verification, commit/PR preparation and the planner-selected exact-source GitHub CI. This review does not claim CI, publication, merge, deployment, production migration, live SMTP delivery or operational secret readiness; those remain pending/UNKNOWN until separately evidenced.
