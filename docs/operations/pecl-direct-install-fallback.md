# PECL installation blocker — 2026-11-16

User log (private Downloads/логи.txt, lines1107–1111) proves missing PECL metadata
and a timed out HTTPS download of pdo_sqlsrv5.13.0. apt/ODBC/native extensions
precede and succeed. Root reproduced the same endpoint-unavailable class with:
`docker run --rm --add-host pecl.php.net:127.0.0.1 php:8.5-fpm-bookworm sh -c
'timeout 30 pecl install sqlsrv-5.13.0 pdo_sqlsrv-5.13.0'` (nonzero, no releases).
No claim about the corporate firewall's exact rule/DNS/proxy root cause.

Minimal seam is the failing extension acquisition command. Prior Composer fallback
cannot cover PECL. Official GitHub v5.13.0 tag resolves to fd6d71d987dddcea413d9fc94234191cb82e4a28;
source archive SHA256 independently obtained and checked. Root owns contract/tests;
separate sol/low deployment_executor code, independent reviewer owns neither.
Source-level fallback preserves architecture portability and exact5.13.0 ABI checks.
No version bump or runtime business behavior change. Known I1 automatic admission
adapter remains UNKNOWN; no policy/enforcement change. Full local suite banned.
Private evidence: ~/.local/share/fmonitor-2/pecl-direct-install-fallback/.


## Reviewed implementation and focused acceptance

Gate3 initially returned T1/T2. Root clarified production pin authority and strengthened
both-module/runtime verification expectations; independent rereview APPROVED at
71344b89. Separate deployment_executor implemented the installer, canonical pins,
Docker template/generated consumer and dependency docs. One necessary renderer fix
extends placeholder names to digits, matching the existing pin parser; this resolves
SQLSRV_SOURCE_SHA256 substitution and is covered by real renderer/drift tests.
Root additionally asserted strict HTTPS protocols through curl redirects.

Actual blocked-PECL Docker build and network-free module inspection passed:
1794832304928917000-52f52a0646ee4f0c86d9fc5c578fe3d9 GREEN.
Nine public-installer cases passed:
1794832314125115000-8c52a5c6f00e4163bfd69d221426f9f8 GREEN.
All4 other planned checks passed (change verification, verification CI contract,
generated source, architecture guard). No full local suite or production changes.
Final reviewer recorded APPROVED12:33:56Z on source38b83437 with no code findings.
The corresponding review record retains exact complete hashes and evidence IDs.
Only this delivery note and final review documentation are added after approval.
Full exact-source GitHub CI and tested-main GitLab transfer remain the publication
steps; known I1 automatic admission stays UNKNOWN, not silently overridden.


## CI oracle correction

First fullCI43868057851 reported one regression: existing ERP console test searched
for an inline pdo_sqlsrv literal in Dockerfile after installation had moved to the
reviewed helper. Root missed this adjacent consumer during local selection. The
complete failed-job inventory is unit plus verify aggregate; the complete regression
inventory contains only tests/Yii2/yii2_erp_equipment_facts_console_001_test.php.
Remaining e2e and integration jobs were cancelled to start the corrected full run
sooner; their results remain UNKNOWN, never GREEN. All failed logs are retained.

Root bounded test-only correction checks helper COPY, pinned invocation, and both
drivers enabled in the actual installer; all ERP behavioral assertions are retained.
Runtime/template/pins remain byte-identical to41727536. Exact oracle RED retained;
first local corrected attempt lacked vendor/autoload.php, then exact composer.lock
installation supplied the fixture and the complete PHP test passed. Governance and
architecture also passed. Independent final correction approval recorded12:51:17Z.
Correction input declares ordinary compact maintenance; planner requires final only.
New exact-source fullCI required before main/GitLab publication.
