# Code review: NGINX-COMBINED-ACCESS-LOG-001

- Reviewer: independent Codex reviewer (`/root/nginx_log_review`; authored neither implementation nor specification/test)
- Implementation author: `/root/nginx_log_executor` (two directives in `deploy/runtime/nginx.conf`); `/root` authored the specification, regression and delivery/evidence records
- Reviewed source: base `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56` plus restored snapshot `/tmp/fm-nginx-combined-20261124/final-restored`; retained patch SHA-256 `f324bb95715f7c36da1dfa6066dcc9dd3a754e10a1e962a7652641884355bcb7` (manifest `/tmp/fm-nginx-combined-20261124/final-snapshot/manifest.json`, file SHA-256 `c2f7ac77371c57b7345560d26509c428c21cf8d341edfa0c1f1ea34b2aab174e`)
- Reviewed artifact hashes: `deploy/runtime/nginx.conf` SHA-256 `53c005dd2a880daee626221a5cd22ab17aa971848bfb953a9de6d0cab882fbac`; `specs/ACTIVATION-PROXY-LOG-001.md` SHA-256 `20c08ac3de3cd4b3adb38e627db9a78e68e8e53e24484f7da1b6870ae12f2a3d`; `tests/Runtime/activation_proxy_log_001_test.py` SHA-256 `57f73704b0ac66c2e9844e59d24df8d500db8c7c52ca2ad52330d786f1f67069`
- Agreed review scope / prior findings disposition (for rereview): first final review of the complete runtime combined-access-log candidate; Gate 3 had no findings; specification and test are unchanged since Gate 3
- Specification: `specs/ACTIVATION-PROXY-LOG-001.md`, including “Runtime combined logging — owner decision 2026-11-24”
- Approved test review: `reviews/tests/NGINX-COMBINED-ACCESS-LOG-001.md`, `APPROVED`
- Verification commands: signed GREEN `python3 tests/Runtime/activation_proxy_log_001_test.py`, record `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795508744775023000-2b4749700a484346a195953c0b82a8b5.json`; prepared bounded checks `python3 tests/Deployment/pilot_jobs_compose_001_test.py`, `python3 tests/Verification/change_verification_001_test.py` (18 tests), and `python3 tests/Verification/architecture_guard_001_test.py` (59 tests), all PASS in `/tmp/fm-nginx-combined-20261124/`
- Planner/package: lane `CRITICAL`, required reviews `gate3`, `final`; `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T082623Z-6378e88f26/package.json`; candidate source `22208eb9686d81572f2327e12cfed50497f304715c9c22eed7fa070048e5cc24`, executable source `8537a5a5a05f4dc6c914c9a0ebbd5188721e350b31600e0c6250a91843fadaea`
- Verdict: `APPROVED`

## Findings

None.

The runtime configuration adds `access_log /dev/stdout combined;` immediately after the existing safe JSON log as authorized. The exact `location = /pilot/activate` adds its own `access_log /dev/stdout safe_json;`; nginx access-log inheritance therefore gives this sensitive location only the declared safe format, while ordinary runtime routes retain both parent logs. The Yii configuration remains unchanged. FastCGI routing, request forwarding, timeouts, response behavior, error-log suppression for activation, and ordinary upstream diagnostics are untouched.

The signed real-nginx acceptance is GREEN on the prepared exact source. It verifies syntax for both configurations; one safe JSON record for each request; exactly one standard combined record for the ordinary runtime request; no combined activation duplicates; absence of generated query, Referer, User-Agent and body secrets from stdout and stderr; retained ordinary upstream diagnostics; and setup/cleanup isolation. This is a direct RED-to-GREEN transition with unchanged approved expectations. The implementation introduces no application, database, schema, authorization, persistence, recovery, dependency or CI-policy change.

The candidate is approved for the next delivery stages. The planner-required full exact-source GitHub `make test`, PR result, merge, GitLab publication and deployment remain external pending evidence and are not claimed GREEN by this review.

## Required changes

None.
