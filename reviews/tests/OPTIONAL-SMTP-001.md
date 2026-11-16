# OPTIONAL-SMTP-001 — independent Gate 3 review

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`
- Base: `274dfc64275dfe1f31f1e6cf085c99f273db60b4`
- Candidate source: `185d22e29c98cb1395e3577007872f096bcf78148a4413a85192d6d812f37311`
- Reviewer package: `20261116T144311Z-dee5059df1`, SHA-256 `d701a3289b8bcd12f1ff32524f55e84c5c5afcbe7476cdb0a59c6014ad05ca9a`
- Verification plan SHA-256: `f089e02e32658aa56b3bfc3edc3b64ac207646e031897ba6b56afd419b867ba4`
- Contract SHA-256: `2c87882b65a085c0d301dd70649a1e69c1930185cc38ce50d80c40872f1e95f9`
- Harness Gate 3 record: `APPROVED`, recorded `2026-11-16T14:43:55Z`
- Verdict: **APPROVED**

The reviewer authored neither the root contract/tests nor the future separate-executor implementation. This correction uses its own base/input and does not reopen or overwrite the earlier RUNTIME-HELP review. All four mapped commands have exact-source `INTENDED_RED` records and no mapped test is missing.

## Configuration state model

The contract defines one closed presence rule over exactly `FMONITOR_SMTP_HOST`, `USERNAME`, `PASSWORD`, and `FROM_ADDRESS`:

- all absent, empty, or whitespace-only means mail disabled;
- any nonblank control value means SMTP is configured and the complete existing validation applies;
- metadata defaults alone never enable mail;
- non-string control values fail closed.

No new flag or environment key is introduced. Existing port bounds, TLS-only encryption, peer verification, username/from equality, sender email, public HTTPS URL, timeout, and production test-recipient restrictions remain owned by `SmtpConfiguration`.

The production CLI test exercises blank and whitespace-disabled states, complete SMTP, every one/two/three-key partial subset, a whitespace password within an otherwise complete set, missing Bitrix/ERP prerequisites, and unchanged jobs=false behavior. It invokes public `check-config`, requires zero Docker effects through the existing seam, and proves synthetic mail/ERP values are absent from diagnostics.

## Jobs and durability coverage

The public configuration/handler test covers absent/metadata/blank states, all 15 nonempty control-key masks, non-string controls, disabled sender rejection, complete configuration, unchanged fail-closed queued outbox handling before DB/recipient/transport access, and independent Bitrix/ERP handler paths.

The disposable MariaDB/CLI test exercises actual `health` and `scheduler` commands with blank, complete, partial, and invalid-TLS configurations. It proves:

- blank SMTP retains true process/queue health;
- complete SMTP health validates without connecting to SMTP;
- partial/invalid SMTP fails before new scheduling;
- workforce, document links, equipment facts, utilization, and heartbeat scheduling remain active without mail;
- disabled mail adds neither weekly generator nor outbox dispatch jobs and makes no delivery attempt;
- a Monday tick with optional email collaborators absent remains healthy;
- re-enable schedules one existing pending intent without delivery.

Before those transitions the test creates real terminal `outbox.dispatch` and `weekly-fkr-report.generate` jobs through the durable queue, one dead and one completed, and snapshots their full job and event rows. Blank scheduling, the deterministic Monday tick, and re-enable must preserve this history exactly. A pending outbox intent is independently snapshotted and no outbox attempt event may appear. This distinguishes “no new mail work while disabled” from deletion or rewriting of historical facts.

## Compose and adjacent CI coverage

The Compose test renders both `deploy/production/compose.yaml` and `deploy/runtime/compose.yaml` as JSON without starting resources. For blank and complete SMTP it requires both jobs-worker and jobs-scheduler to receive the same SMTP metadata plus production runtime/public URL values. This closes the prior worker-only propagation gap and preserves configured administrative mail.

The address-register browser delta is test-only. After native Cancel it waits for the exact saved visible-column sequence before retaining the original equality assertion and subsequent draft-restoration checks. It narrows the known asynchronous race without changing expected UI behavior.

## RED evidence

- production config `1794840158782296000-b5ffad2f088340a499b261fada8d3684`, command blob `536680f6d347664d9a095540ee0d03d7c7f90d327eb924e1061b9aa91d5a3c39`
- presence/handler `1794840166929168000-8e021dd8bc74461db2c2f5affbe49c1f`, command blob `45db07306dfbf796ed511e2f38aec47c0bde4b8b0e74854dce4a893c459887cb`
- real CLI/disposable DB `1794840174981615000-68927a1285754bb38eb3697423387516`, command blob `0949a9e9fd353669393383f9ff60c43bd04cb088e6fb702fbf8266c44b721ad9`
- production/runtime Compose `1794840183270322000-e3e7d010eff64649bc8acf42b6fce969`, command blob `6029cac86062ddd9bb293abcbf737b63958b09146fd03a1a7f7a6426d1744270`

The failures are the absent optional-presence seam, mandatory SMTP validation in blank mode, non-optional scheduler collaborators, and missing scheduler SMTP environment. They are implementation REDs rather than live-network or fixture setup failures.

## Approval boundary

No Gate 3 findings remain. The complete OPTIONAL-SMTP contract/tests and the precise address-browser wait are **APPROVED** for separate minimal implementation. No transport may execute in scheduler/tests, no queue history may be deleted or replayed, and configured sender/TLS validation must not weaken. Focused GREEN, disposable DB GREEN, independent final review, and exact-source CI remain required. Corporate Bitrix/ERP behavior remains `UNKNOWN` until observed on the workstation; this review authorizes no live call, email, database reset, or production change.

---

## Final test-delta disposition

- Date: `2026-11-16`
- Exact candidate source: `96313cec95642154fd6bf8cd9a68a4cefb5b30f089a0017cee27e1fc76de892e`
- Executable source: `d0f980c6ea965205fa33c9bc694b10a9875d99cd0eced604937e5a1279796eca`
- Reviewer package: `20261116T145136Z-1a135dd1e9`, SHA-256 `0750636cf518c3aed5e00575d1580fd9a25de116842ca457bf86c4c4ac419f98`
- Verification plan SHA-256: `1d83f4a0066c7894323675074033426f66424a9d20aaf6cd8df47cabceee7d94`
- Harness final record: `APPROVED`, recorded `2026-11-16T14:52:13Z`
- Verdict: **APPROVED**

During focused GREEN, the CLI fixture initially expected `installer-utilization.capture` on the first arbitrary scheduler tick. The unchanged scheduler only creates that job at exactly 03:17 Europe/Moscow after a completed same-day `workforce.sync`; the fixture supplied neither condition. Removing that single expected immediate kind is an oracle correction. The exact expected disabled-mail jobs remain workforce sync, Bitrix document links, and ERP equipment facts. Every SMTP absence, historical job/event, pending intent, zero-attempt, deterministic Monday, and re-enable assertion remains unchanged.

The original four exact-source intended RED records remain linked above. Attempts to encode them through newer `--historical-red`/`--test-delta` fields were rejected because the retained v1 records have no acceptance ID, command ID, or purpose. This review records the linkage explicitly and does not fabricate metadata or alter policy.

All four current acceptance commands are source-bound GREEN:

- production config `1794840651207129000-3a53d054c118462080ea054928920e3c`, command blob `536680f6d347664d9a095540ee0d03d7c7f90d327eb924e1061b9aa91d5a3c39`
- presence/handler `1794840651207307000-48a9c58ebabf4e4ab4c1bb24cd59cf86`, command blob `45db07306dfbf796ed511e2f38aec47c0bde4b8b0e74854dce4a893c459887cb`
- real CLI/disposable DB `1794840659928869000-e305a8a2961b454eb8027e0bbb83a9de`, command blob `61b96c6507b86171371b13e0d0a30f44b9416d68d850618c76d4975992970bb7`
- production/runtime Compose `1794840651214296000-d789fe5021304810bbd5c2ab01e214e8`, command blob `6029cac86062ddd9bb293abcbf737b63958b09146fd03a1a7f7a6426d1744270`

The other focused obligations completed GREEN; their summary retains the initial superseded CLI fixture failure and the final receipt records the corrected pass. No test expectation was weakened beyond removing the factually ineligible utilization job.
