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
