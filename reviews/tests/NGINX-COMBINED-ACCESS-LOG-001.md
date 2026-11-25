# Test review: NGINX-COMBINED-ACCESS-LOG-001

- Reviewer: independent Codex reviewer (`/root/nginx_log_review`; authored neither specification nor test)
- Test author: root delivery agent
- Reviewed source: base `08ca8b18c2ce6678af9d6c3eb6e44edf5860ba56` plus restored snapshot `/tmp/fm-nginx-combined-20261124/gate3-restored`; retained patch SHA-256 `3d24d31670e5db154b17a6dab3be4f7f5f533fd93a419a19858b742a55e93f53` (manifest `/tmp/fm-nginx-combined-20261124/gate3-snapshot/manifest.json`, file SHA-256 `0907851548298f851212d3d4bcd931fe2c09ff6992657116026e923020f6976b`)
- Agreed review scope / prior findings disposition (for rereview): first Gate 3 review of the 2026-11-24 runtime combined-log contract addendum and its complete test delta; no prior findings
- Specification: `specs/ACTIVATION-PROXY-LOG-001.md`, section “Runtime combined logging — owner decision 2026-11-24”
- Public seam: real nginx syntax and HTTP requests through both `deploy/yii2/nginx.conf` and `deploy/runtime/nginx.conf`, observing container stdout, stderr, response status and response body
- Red command and intended failure: `python3 tests/Runtime/activation_proxy_log_001_test.py`; signed harness record `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/records/1795508571644528000-9b8de42dc35f4139b58120a4d14d9aad.json`, exit 1, with exactly `runtime: ordinary combined access record for security collection` and `runtime: exact access count without activation duplicates`; existing activation privacy, Yii parity, diagnostics and isolation assertions passed
- Planner: lane `CRITICAL`; required reviews `gate3`, `final`; prepared reviewer package `/Users/antropophag/.local/share/fmonitor-2/delivery-harness/packages/20261124T082350Z-d6813669ed/package.json`
- Verdict: `APPROVED`

## Findings

None.

The addendum is traceable to the single existing acceptance and states the runtime/Yii distinction, the ordinary-request combined fields, exact activation exception, and unchanged adjacent behavior. The test exercises the deployed nginx seam rather than configuration text alone. Its expected combined line is independently fixed to the standard observable shape and to known non-secret Referer/User-Agent fixtures. Exact stdout counts require one runtime combined record while detecting combined duplicates for either activation request; JSON parsing separately preserves one safe record for every request. Random query, Referer, User-Agent and body tokens remain checked in both output streams, so the sensitive exception cannot pass by hiding only one input channel.

The test remains deterministic and isolated: it uses a named existing/built runtime base image without a hidden pull, unique containers and loopback ports, readonly candidate configs, explicit ownership labels, and bounded cleanup that preserves unrelated containers. The signed RED is caused solely by the missing runtime combined record and count. The other prepared bounded checks also passed: `pilot_jobs_compose_001_test.py`, `change_verification_001_test.py`, and `architecture_guard_001_test.py`. No full local suite was run.

## Required changes

None.
