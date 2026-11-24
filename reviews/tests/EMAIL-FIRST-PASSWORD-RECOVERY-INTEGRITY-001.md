# EMAIL-FIRST-PASSWORD-RECOVERY-INTEGRITY-001 — bounded Gate 3 test review

- Reviewer: `/root/gate3_review` (independent reviewer; authored none of the reviewed integrity specification, tests, implementation, or RED evidence).
- Review date: 2026-11-24.
- Scope: newly discovered recovery-integrity test delta only; no runtime implementation review. The main Gate 3 and schema-matrix decisions remain separate.
- Reviewed exact candidate source: `67d461c0c9cff7270d5bf83287d30c0a397f4437d01d674f02d8db5ede9c697e`; executable source `41674ce0af1355a3d438635cd1d8efd1ef7274c9be9c9b52cc0db79a7a8fbf6f`.
- Prepared package: `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T121409Z-ac9d8eddf7/package.json`, SHA-256 `a57d253efe6b14676a21d5eef174439eb7e8782054ed8cca96ac0d0fde3973fb`.
- Required-context SHA-256: `ce569524e4a17e2706cb01f66baee6d573681bb68c1cdc868b1b7920d52715c9`.
- Verification-plan SHA-256: `f98c7ff8ab450ff61ebebaeaacd1e895d2d82d75b3cf667b94d96fc016c8c34f`.
- Normative specification: `specs/EMAIL-FIRST-PASSWORD-RECOVERY-001.md`, SHA-256 `d9ef8f3f447dc1a3677ee17bfaf69587dd3a6b4e131109daa5de25d3a7375509`.
- Executable test: `tests/Yii2/password_recovery_integrity_001_test.php`, SHA-256 `2370a9b1061a558ecad8a6fa8019c17699807d5952edb914d5e3d4f9b758213a`.
- Verdict: **APPROVED**.

## Coverage assessment

The test supplies independent, reachable oracles for all eleven reported implementation gaps:

1. It alters the enum only to preserve historical representability, then calls the active public `YiiUserAccess` facade and requires `invite`, `reissue`, and `activate` to return `invalid`, `invitation` to return null, and every call to leave the complete observed fact snapshot unchanged. Seeded historical users, credentials, invitation hashes and usable expiries ensure the retired methods cannot pass merely because no legacy row exists.
2. It constructs a production-style owner without an injected clock, fixes that owner’s actual MariaDB session time to `2030-01-02T03:04:05Z`, invokes the public recovery request, and requires stored creation and expiry to be that single DB UTC instant and exactly +1800 seconds.
3. It provisions two active identities, issues real grants, swaps one valid ciphertext into the other identity, and requires zero sender calls. Separate valid grants then tamper stored key ID, token digest, or request ID one at a time and again require zero sender calls. These cases independently prove the authenticated envelope binds request, user, email, generation and key ID and that decrypted token bytes are checked against the stored digest before transport.
4. It mutates a real password-recovery outbox intent to payload version 2, routes it through the real queue and `JobHandlerRuntime`, and requires a permanent outcome with no fake-network call. The explicit queue registry keeps default admission closed while admitting only the tested outbox job family.
5. It starts the real HTTP runtime with a valid recovery key, key ID and trusted origin but no SMTP configuration; forgot-password must return recovery-only 503 while ordinary login remains available.

The fake sender is invoked only as an observation port and no real mail or production action occurs. The aggregate oracle lets every counterexample execute before the final failure, providing a complete failure inventory rather than stopping at the first missing guard.

## RED evidence assessment

Record `1795522360778113000-b749391f425148929580a91aa690a971` is bound to exact candidate `67d461c0c9cff7270d5bf83287d30c0a397f4437d01d674f02d8db5ede9c697e`. It reaches the active facade, public recovery owner, DB clock, fake sender boundary, real outbox/job handler, and real HTTP runtime, then reports all eleven expected counterexamples. The failures are missing integrity behavior rather than fixture/setup failures. The test uses isolated fixture identities and synthetic secrets; no live SMTP is configured.

## Verdict

**APPROVED**

No test-design blocker was found. The approved delta requires the active facade retirement, DB-session UTC clock, authenticated envelope and digest checks, exact recovery template version, and SMTP-aware HTTP readiness described above. Implementation corrections may proceed under the separate executor package. This verdict does not approve implementation code, final source, CI, publication, merge, deployment, or live email.
