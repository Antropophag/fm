# OPTIONAL-SMTP-001 — independent final review

- Date: `2026-11-16`
- Reviewer: `/root/independent_review`
- Base: `274dfc64275dfe1f31f1e6cf085c99f273db60b4`
- Exact candidate source: `96313cec95642154fd6bf8cd9a68a4cefb5b30f089a0017cee27e1fc76de892e`
- Executable source: `d0f980c6ea965205fa33c9bc694b10a9875d99cd0eced604937e5a1279796eca`
- Reviewer package: `20261116T145136Z-1a135dd1e9`, SHA-256 `0750636cf518c3aed5e00575d1580fd9a25de116842ca457bf86c4c4ac419f98`
- Verification plan SHA-256: `1d83f4a0066c7894323675074033426f66424a9d20aaf6cd8df47cabceee7d94`
- Harness final record: `APPROVED`, recorded `2026-11-16T14:52:13Z`
- Verdict: **APPROVED**

The reviewer authored neither the root contract/tests nor the separate executor implementation. Gate 3 first identified missing historical-job and scheduler-environment coverage; the complete corrected candidate was approved before implementation. Final code and the one-line unit-oracle correction were independently reviewed.

## Implementation assessment

`SmtpConfiguration::isConfigured()` adds a side-effect-free presence seam over exactly HOST, USERNAME, PASSWORD, and FROM_ADDRESS. Absent, empty, or whitespace-only controls do not enable mail. Any nonblank control marks SMTP configured; non-string controls fail with the existing safe configuration error. The existing `fromEnvironment()` factory and all sender, TLS, HTTPS, peer, timeout, test-recipient, and production restrictions remain unchanged.

Production `check-config` continues to require complete Bitrix/ERP settings when jobs are enabled. SMTP controls are accepted only all blank or all four present; the full set still passes through existing non-placeholder requirements. Jobs=false behavior is unchanged.

At CLI composition, scheduler mode validates complete configured SMTP before opening the jobs database. Production health does the same, while blank SMTP needs no sender configuration. Disabled SMTP supplies neither weekly-report scheduling nor outbox-dispatch scheduling collaborator. Workforce, Bitrix document links, ERP facts, installer utilization, and heartbeat collaborators remain active. Re-enabling SMTP restores the existing weekly/outbox collaborators without replaying historical jobs or executing transport in the scheduler.

Both production and local runtime Compose configurations now pass the same blank or configured SMTP metadata, runtime environment, and public URL to jobs-scheduler as to jobs-worker. This preserves configured administrative mail and lets the scheduler make the same validated presence decision.

No transport, queue owner, schema, retry, outbox history, sender, or domain writer changed. Already queued outbox work retains the existing `OUTBOX_TRANSPORT_UNCONFIGURED` result before database/recipient/SMTP access when credentials are blank. The address-register modification is test-only and waits for the exact saved column sequence after asynchronous Cancel restoration.

## Evidence

The formal package contains four authoritative exact-source GREEN records with no missing mapped tests:

- config `1794840651207129000-3a53d054c118462080ea054928920e3c`
- presence/handler `1794840651207307000-48a9c58ebabf4e4ab4c1bb24cd59cf86`
- real CLI/disposable DB `1794840659928869000-e305a8a2961b454eb8027e0bbb83a9de`
- Compose rendering `1794840651214296000-d789fe5021304810bbd5c2ab01e214e8`

Together they prove all blank/whitespace/partial/complete states, non-string rejection, unchanged configured validation, no secret diagnostics, health and scheduler behavior, non-mail scheduling, no new email work while disabled, exact preservation of dead/completed mail jobs and events, pending-intent preservation, zero attempts, deterministic Monday behavior, one dispatch on re-enable, and worker/scheduler propagation in both Compose files. No SMTP, Bitrix, ERP, or production network call occurs.

The full focused set completed GREEN after the approved utilization-scheduler oracle correction. The initial failed CLI receipt is retained in the evidence summary and is superseded only by the corrected source-bound GREEN, not erased or relabeled.

## Final boundary

Gate 5 is **APPROVED** for the exact candidate and executable source above. No code findings remain. Corporate Bitrix/ERP results remain `UNKNOWN` until observed on the workstation; no email or live integration call was authorized or performed. Exact-source CI/publication and the missing automatic admission adapter remain separate pending states and are not represented as GREEN. Existing data, users, queue history, and installed state must be preserved through the reviewed update workflow; no database reset is authorized.
