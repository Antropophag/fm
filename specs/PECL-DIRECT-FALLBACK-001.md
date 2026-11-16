# PECL-DIRECT-FALLBACK-001

## Простыми словами

При недоступности PECL make deploy продолжает установку тех же драйверов SQL Server
из официального исходного архива Microsoft. Ручные изменения Dockerfile не нужны.

## Contract

P01: public installer `bash deploy/dependencies/install-sqlsrv --version VERSION
--source-revision COMMIT --source-sha256 SHA256 [--mode auto|direct]` defaults auto.
The runtime Docker template and generated file call it with approved dependency pins.
`tools/delivery/dependencies.env` and the reviewed generated Dockerfile own the
production version/commit/hash tuple. The installer is an integrity/build mechanism
for explicitly trusted inputs, not an authorization service; its generic input
validation does not duplicate those pins. No runtime environment/build-argument
operator override of the production tuple is introduced.
Auto first attempts both exact PECL packages; a bounded primary failure/timeout
falls back and prints `SQLSRV_SOURCE_FALLBACK` without secrets. Primary success does not contact GitHub or compile source again.
Direct skips PECL. Version/commit/digest/mode and unknown or duplicate options
reject before network, installation or enabling extensions.

P02: fallback fetches only HTTPS codeload.github.com/microsoft/msphpsql/tar.gz/COMMIT,
with bounded connect/total duration and retries, no TLS bypass. A fresh temporary
workspace is cleaned on success/failure. Verify SHA256 before extraction or build;
failed downloads or mismatches fail closed, without enabling modules. Official
v5.13.0 commit fd6d71d987dddcea413d9fc94234191cb82e4a28 has archive SHA256
7ea3eeef0d615c884628285a647f9cb29db4e206be2cd5b418c10360085cf04a.

P03: both drivers compile from that same verified tree with upstream shared sources.
Partial primary installation cannot yield mixed/unverified success: fallback builds
and installs both drivers. Any extraction/build/install error propagates. Enable both
only after successful installation; then PHP must load both at exactly VERSION and
PDO must advertise sqlsrv. Wrong/missing versions or failed loading must reject.
Runtime versions remain5.13.0; no ERP credentials, production access or data changes.

P04: actual PHP8.5 Docker build with pecl.php.net deliberately unreachable must
compile and load both5.13.0 modules via fallback; no DB/server/network integration
credentials are needed. Normal runtime image is also exercised by existing fullCI.
Use bounded local checks only. Archive digest and source/version pins are reviewed
inputs, never automatically replaced after mismatch. Existing Composer fallback,
Make interface, source/image identity and clean database semantics stay unchanged.

Scope is build installation only. No migration/schema/financial/history changes,
no live service deployment, no image deletion outside task-owned temporary builds,
no authentication or application behavior changes. Docker build layer failure prevents
publishing an incomplete image. Standalone installer is intended for disposable build
layers, not concurrent mutation of a live PHP installation.
