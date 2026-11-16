# RUNTIME-HELP-001

## Простыми словами

Пункт «Инструкция» открывает уже существующее руководство в новом Docker-окружении.
Содержание и оформление не меняются. Исправляются его HTTP-маршруты.

## Contract

H01: public GET /help/, /help and /help/index.html return200 with the exact existing
public/help/index.html bytes and text/html; HEAD returns200 with matching content
length and no body. A session is not required for these existing public help assets.
Use the existing Yii HTTP entry, URL manager and response lifecycle; no Nginx bypass
of trusted-host, raw PHP exposure, alternate bootstrap or domain state changes.

H02: GET/HEAD /help/guide.css, /help/guide.js and every /help/screenshots/*.webp
referenced by the page return200, exact committed bytes and correct MIME types.
The existing /pilot/assets/shlz.css dependency continues to work. Missing or unknown
files, other extensions/source/markdown files, nested traversal and symlink escape
must return404. Resolve only the fixed public/help files and permitted screenshot
filenames under that directory; never take an arbitrary filesystem root from input.

H03: preserve shared security headers, exact trusted-host rejection, HEAD behavior
and existing static allowlist. Unsupported methods must reject without writes.
Do not weaken authentication/CSRF/CSP or enable autoindex. The guide HTML contains
no external/inline scripts requiring any CSP expansion. Instruction content/images
remain exactly as packaged; no new data/permission/schema/financial behavior.

H04: focused HTTP regression exercises the real Yii route/controller/response path;
production fresh-clone Make acceptance fetches /help/ and every linked help asset
through actual Nginx/PHP containers after login. Existing login, app asset and
installation flow stay valid. No public route returns files outside the guide root.
Full local suite prohibited; focused local checks and exact-source CI required.
