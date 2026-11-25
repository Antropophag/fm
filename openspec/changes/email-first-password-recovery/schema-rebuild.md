# Complete schema-correction matrix rebuild

Root rebuilt the complete schema candidate after the second predictable coverage return, not a one-assertion patch. Previous source b642584...; independent review EMAIL-FIRST-PASSWORD-RECOVERY-SCHEMA-001 retains history. Main Gate3 remains approved; no schema correction implemented yet.

| Boundary | Complete independent oracle | Negative / replay / preservation |
| --- | --- | --- |
| Fresh v40 | PasswordSchemaContract literal metadata for every column/default/null/extra/charset/collation, engine, ordered index+uniqueness+prefix and FK target/actions | 11 real SQL mutations, readiness and migration rejection, no automatic repair |
| Historical enum | Exact five-label additive successor | runtime first-password writer admits only pending_password; legacy adapters retained as evidence |
| Partial prefix0 | Full pre-v40 identities601/602/603, null and valid existing credential | completion + repeated full canonical run, exact identity except waiting transition, byte-identical credentials |
| Partial prefix1 | Above + event23 and next-id800 | exact event bytes + next value after completion and replay |
| Partial prefix2 | Above + request UUID, matching user603/generation7, cryptographic encrypted bytes, hash, keyId, exact30min expiry, transient state | exact complete row and no removal/recreation/rewriting of facts |
| Partial prefix3 | Above + accepted attempt31 and next-id900 | exact event/request/attempt data and both sequence gaps through completion/replay |
| Existing-data conflict | Main migration test active+null hash, roles/permissions/membership/history/legacy links | conflict no guesses; full historical snapshots preserved on valid upgrade/replay |
| Actual backup/restore | runtime_jobs_recovery real public CLI, all3 new tables and sequences800/900 | byte-for-byte restored facts, opaque ciphertext, delivery state, readiness and subsequent sequence use |
| Existing schema consumers | identity_access_schema current-only successor adapter and valid activehash fixture; production_migration_runner exact current table list | historical literal contract sources remain unchanged |
| Test environment correctness | explicit outbox registry; observed InnoDB wait at200ms | queue admission staysclosed; authorization denial/no-write assertions unchanged |

No populated compatible recovery table may be dropped/recreated during resume. The new fixture uses test-owned literal DDL, not production definitions. All raw evidence remains external. S1 literal-completeness closed previously; S2 allprefixes plus populated-data/sequence preservation now addressed. Approval remains pending until new exact-source rereview.
