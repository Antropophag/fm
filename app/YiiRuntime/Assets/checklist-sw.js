"use strict";

const META_CACHE = "fmonitor2-field-meta";
const GENERATION_KEY = "/pilot/__field-generation__";
const GENERATION_HISTORY_KEY = "/pilot/__field-generation-history__";
const BUILD_GENERATION = "__FMONITOR_WORKER_GENERATION__";
const IDENTITY_KEY = "/pilot/__field-identity__";
const LOGIN_KEY = "/pilot/__field-login-proof__";
const LOGIN_PENDING_KEY = "/pilot/__field-login-pending__";
const ASSET_MANIFEST = "/pilot/assets/field-shell.json";
const OFFLINE = "/pilot/construction-control/offline";
const STATIC_ASSETS = [
  OFFLINE,
  "/pilot/assets/pwa-icon-192.png",
  "/pilot/assets/pwa-icon-512.png",
  "/pilot/assets/fonts/golos-text-cyrillic-400-normal.woff2",
  "/pilot/assets/fonts/golos-text-cyrillic-500-normal.woff2",
  "/pilot/assets/fonts/golos-text-cyrillic-600-normal.woff2",
  "/pilot/assets/fonts/golos-text-latin-400-normal.woff2",
  "/pilot/assets/fonts/golos-text-latin-500-normal.woff2",
  "/pilot/assets/fonts/golos-text-latin-600-normal.woff2",
  "/pilot/assets/favicon.svg",
  "/pilot/assets/manifest.webmanifest",
  "/pilot/assets/shlz-icons/search.svg",
  "/pilot/assets/shlz-icons/calendar-interface.svg",
  "/pilot/assets/shlz-icons/logout.svg",
  "/pilot/assets/shlz-icons/delivery-box.svg",
  "/pilot/assets/shlz-icons/folder-file-open.svg",
  "/pilot/assets/shlz-icons/chevron-left-duo.svg",
  "/pilot/assets/shlz-icons/chevron-right-duo.svg",
  "/pilot/assets/shlz-icons/arrow-left-md.svg",
  "/pilot/assets/shlz-icons/arrow-right-md.svg",
];
const warmups = new Map();
let epoch = 0;

const checklistPath = (path) =>
  /^\/pilot\/(?:objects|construction-control\/objects)\/[1-9][0-9]*\/checklist$/.test(
    path,
  );
const queuePath = (path) =>
  path === "/pilot/construction-control" || path === OFFLINE;

self.addEventListener("install", (event) => {
  event.waitUntil(installShell());
});

self.addEventListener("activate", (event) => {
  event.waitUntil(notifyUpdate());
});

self.addEventListener("message", (event) => {
  const data = event.data || {};
  if (data.type === "FIELD_RUNTIME_INFO") {
    event.ports?.[0]?.postMessage({
      protocol: 3,
      generation: BUILD_GENERATION,
    });
    return;
  }
  if (data.type === "FIELD_LOGOUT") {
    event.waitUntil(
      tombstone().then(() => event.ports?.[0]?.postMessage({ ok: true })),
    );
    return;
  }
  if (data.type === "FIELD_ESTABLISH_ACTOR") {
    event.waitUntil(establishActor(data, event.ports?.[0]));
    return;
  }
  if (data.type === "FIELD_CHECK_ACTOR") {
    event.waitUntil(
      identity().then((value) =>
        event.ports?.[0]?.postMessage({ ok: true, identity: value }),
      ),
    );
    return;
  }
  if (data.type === "FIELD_ACTOR_CONFIRMED") {
    event.waitUntil(confirmActor(data, event.ports?.[0]));
    return;
  }
  if (data.type === "CONSUME_LOGIN_CONFIRM") {
    event.waitUntil(returnLoginProof(data, event.ports?.[0]));
    return;
  }
  if (data.type === "CHECK_CHECKLIST_CACHE") {
    event.waitUntil(checkDocument(data, event.ports?.[0]));
    return;
  }
  if (data.type === "CACHE_CHECKLIST") {
    event.waitUntil(cacheChecklist(data, event.ports?.[0]));
  }
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (request.method === "POST" && url.pathname === "/pilot/logout") {
    event.respondWith(logoutRequest(request));
    return;
  }
  if (
    request.method === "POST" &&
    ["/pilot/login", "/pilot/otiz/login"].includes(url.pathname)
  ) {
    event.respondWith(loginRequest(request));
    return;
  }
  if (request.method !== "GET") return;
  if (
    request.mode === "navigate" &&
    (checklistPath(url.pathname) || queuePath(url.pathname))
  ) {
    event.respondWith(navigate(request, url));
    return;
  }
  if (url.pathname.startsWith("/pilot/assets/"))
    event.respondWith(asset(request));
});

async function installShell() {
  const manifestResponse = await fetch(ASSET_MANIFEST, { cache: "no-store" });
  if (!manifestResponse.ok) throw new Error("Shell manifest unavailable");
  const manifest = await manifestResponse.clone().json();
  if (!Array.isArray(manifest.assets) || manifest.assets.length === 0) {
    throw new Error("Shell manifest invalid");
  }
  const generation = String(manifest.generation || "");
  if (generation !== BUILD_GENERATION || !/^[a-f0-9]{64}$/.test(generation))
    throw new Error("Shell generation invalid");
  const cache = await caches.open(`fmonitor2-field-shell-${BUILD_GENERATION}`);
  await cache.put(ASSET_MANIFEST, manifestResponse);
  for (const url of STATIC_ASSETS) {
    const exact = `${url}${url.includes("?") ? "&" : "?"}build=${BUILD_GENERATION}`;
    const response = await fetch(exact, { cache: "no-store" });
    if (!response.ok)
      throw new Error(`Mandatory shell asset unavailable: ${url}`);
    await cache.put(url, response);
  }
  await cache.addAll(manifest.assets);
}

async function notifyUpdate() {
  const meta = await caches.open(META_CACHE);
  const retained = await meta.match(GENERATION_HISTORY_KEY);
  let history = [];
  try {
    history = retained ? await retained.json() : [];
  } catch {}
  history = [...new Set([...history, BUILD_GENERATION])];
  await meta.put(GENERATION_KEY, new Response(BUILD_GENERATION));
  await meta.put(GENERATION_HISTORY_KEY, new Response(JSON.stringify(history)));
  const clients = await self.clients.matchAll({ includeUncontrolled: true });
  for (const client of clients) {
    client.postMessage({
      type: "FIELD_UPDATE_READY",
      generation: BUILD_GENERATION,
    });
  }
}

function safeChecklist(value) {
  try {
    const url = new URL(value, self.location.origin);
    return url.origin === self.location.origin && checklistPath(url.pathname)
      ? url
      : null;
  } catch {
    return null;
  }
}

async function activeGeneration() {
  const response = await (await caches.open(META_CACHE)).match(GENERATION_KEY);
  return response ? await response.text() : null;
}

async function shellCache() {
  return caches.open(`fmonitor2-field-shell-${BUILD_GENERATION}`);
}

async function documentCache(userId) {
  return caches.open(`fmonitor2-field-doc-${BUILD_GENERATION}-${userId}`);
}

async function identity() {
  const response = await (await caches.open(META_CACHE)).match(IDENTITY_KEY);
  if (!response) {
    for (const name of await caches.keys()) {
      if (!name.startsWith("fmonitor2-checklist-shell-")) continue;
      const legacy = await (
        await caches.open(name)
      ).match("/pilot/__offline-active-user__");
      if (legacy)
        return {
          userId: Number(await legacy.text()),
          epoch: 0,
          tombstone: false,
        };
    }
    return null;
  }
  try {
    return await response.json();
  } catch {
    return null;
  }
}

async function writeIdentity(value) {
  await (
    await caches.open(META_CACHE)
  ).put(
    IDENTITY_KEY,
    new Response(JSON.stringify(value), {
      headers: { "Content-Type": "application/json" },
    }),
  );
}

async function authenticated(response) {
  if (
    !response.ok ||
    !(response.headers.get("content-type") || "").includes("text/html")
  )
    return null;
  const text = await response.clone().text();
  const match = text.match(
    /\sdata-(?:authenticated-user-id|user-id)="([1-9][0-9]*)"/,
  );
  return match ? Number(match[1]) : null;
}

async function loginRequest(request) {
  const cache = await caches.open(META_CACHE);
  let passwordAttempt = false;
  try {
    const form = await request.clone().formData();
    passwordAttempt = typeof form.get("password") === "string";
  } catch {}
  const identityBefore = await identity();
  let response;
  try {
    response = await fetch(request);
  } catch (error) {
    await cache.delete(LOGIN_PENDING_KEY);
    throw error;
  }
  const userId = await authenticated(response);
  const identityAfter = await identity();
  const epochUnchanged =
    Number(identityAfter?.epoch || 0) === Number(identityBefore?.epoch || 0);
  if (passwordAttempt && userId && epochUnchanged) {
    const proof = {
      userId,
      nonce: crypto.randomUUID(),
      epoch: Number(identityBefore?.epoch || 0),
      confirmedAt: Date.now(),
      expiresAt: Date.now() + 120000,
    };
    await cache.put(
      LOGIN_KEY,
      new Response(JSON.stringify(proof), {
        headers: { "Content-Type": "application/json" },
      }),
    );
    await cache.delete(LOGIN_PENDING_KEY);
  } else if (
    passwordAttempt &&
    epochUnchanged &&
    (response.type === "opaqueredirect" ||
      (response.status >= 300 && response.status < 400))
  ) {
    const pending = {
      nonce: crypto.randomUUID(),
      epoch: Number(identityBefore?.epoch || 0),
      expiresAt: Date.now() + 30000,
    };
    await cache.put(
      LOGIN_PENDING_KEY,
      new Response(JSON.stringify(pending), {
        headers: { "Content-Type": "application/json" },
      }),
    );
  } else {
    await cache.delete(LOGIN_PENDING_KEY);
  }
  return response;
}

async function returnLoginProof(data, port) {
  const cache = await caches.open(META_CACHE);
  const response = await cache.match(LOGIN_KEY);
  const current = await identity();
  let proof = null;
  if (response) {
    try {
      const value = await response.json();
      if (
        value.expiresAt >= Date.now() &&
        Number(value.userId) === Number(data.userId) &&
        Number(value.epoch ?? current?.epoch ?? 0) ===
          Number(current?.epoch || 0)
      )
        proof = value;
    } catch {}
  }
  if (!proof) {
    const pendingResponse = await cache.match(LOGIN_PENDING_KEY);
    let pending = null;
    try {
      pending = pendingResponse ? await pendingResponse.json() : null;
    } catch {}
    if (
      pending?.expiresAt >= Date.now() &&
      Number(pending.epoch) === Number(current?.epoch || 0)
    ) {
      try {
        const target = new URL(data.url || "", self.location.origin);
        if (
          target.origin !== self.location.origin ||
          !target.pathname.startsWith("/pilot/")
        )
          throw new Error("Invalid login landing");
        const protectedResponse = await fetch(target.href, {
          credentials: "include",
          cache: "no-store",
          headers: { Accept: "text/html" },
          signal: AbortSignal.timeout ? AbortSignal.timeout(15000) : undefined,
        });
        const verifiedUser = await authenticated(protectedResponse);
        if (Number(verifiedUser) !== Number(data.userId)) {
          await cache.delete(LOGIN_PENDING_KEY);
          throw new Error("Login actor mismatch");
        }
        proof = {
          userId: Number(data.userId),
          nonce: pending.nonce,
          epoch: Number(pending.epoch),
          confirmedAt: Date.now(),
          expiresAt: Date.now() + 120000,
        };
        await cache.put(
          LOGIN_KEY,
          new Response(JSON.stringify(proof), {
            headers: { "Content-Type": "application/json" },
          }),
        );
        await cache.delete(LOGIN_PENDING_KEY);
      } catch {}
    } else if (pendingResponse) {
      await cache.delete(LOGIN_PENDING_KEY);
    }
  }
  port?.postMessage({ confirmed: Boolean(proof), proof });
}

async function confirmActor(data, port) {
  const cache = await caches.open(META_CACHE);
  const current = await identity();
  const proofResponse = await cache.match(LOGIN_KEY);
  let proof = null;
  try {
    proof = proofResponse ? await proofResponse.json() : null;
  } catch {}
  const valid =
    proof &&
    proof.expiresAt >= Date.now() &&
    Number(proof.userId) === Number(data.userId) &&
    proof.nonce === data.nonce &&
    Number(proof.epoch ?? current?.epoch ?? 0) ===
      Number(current?.epoch || 0) &&
    Number.isInteger(Number(data.epoch)) &&
    Number(data.epoch) >= Number(current?.epoch || 0);
  if (!valid) {
    const exact =
      !current?.tombstone &&
      Number(current?.userId) === Number(data.userId) &&
      Number(current?.epoch) === Number(data.epoch);
    port?.postMessage({ ok: exact });
    return;
  }
  epoch = Math.max(epoch, Number(current?.epoch || 0), Number(data.epoch));
  await writeIdentity({ userId: Number(data.userId), epoch, tombstone: false });
  await cache.delete(LOGIN_KEY);
  port?.postMessage({ ok: true });
}

async function establishActor(data, port) {
  const current = await identity();
  if (current?.tombstone || Number(data.epoch) < Number(current?.epoch || 0)) {
    port?.postMessage({ ok: false });
    return;
  }
  try {
    const target = new URL(data.url || "", self.location.origin);
    if (
      target.origin !== self.location.origin ||
      !target.pathname.startsWith("/pilot/")
    )
      throw new Error();
    const response = await fetch(target.href, {
      credentials: "include",
      cache: "no-store",
      headers: { Accept: "text/html" },
      signal: AbortSignal.timeout ? AbortSignal.timeout(15000) : undefined,
    });
    if (Number(await authenticated(response)) !== Number(data.userId))
      throw new Error();
    epoch = Math.max(epoch, Number(current?.epoch || 0), Number(data.epoch));
    await writeIdentity({
      userId: Number(data.userId),
      epoch,
      tombstone: false,
    });
    if (checklistPath(target.pathname)) {
      await (
        await documentCache(data.userId)
      ).put(target.pathname, await cacheableResponse(response));
    }
    port?.postMessage({ ok: true });
  } catch {
    port?.postMessage({ ok: false });
  }
}

async function tombstone() {
  const current = await identity();
  epoch = Math.max(epoch, Number(current?.epoch || 0)) + 1;
  for (const controller of warmups.values()) controller.abort();
  warmups.clear();
  await writeIdentity({
    userId: current?.userId || null,
    epoch,
    tombstone: true,
  });
  await (await caches.open(META_CACHE)).delete(LOGIN_KEY);
  await (await caches.open(META_CACHE)).delete(LOGIN_PENDING_KEY);
}

async function logoutRequest(request) {
  await tombstone();
  return fetch(request);
}

async function cacheChecklist(data, port) {
  const url = safeChecklist(data.url);
  if (!url) {
    port?.postMessage({ ok: false, message: "Некорректный адрес чек-листа." });
    return;
  }
  const durableIdentity = await identity();
  epoch = Math.max(epoch, Number(durableIdentity?.epoch || 0));
  const captured = epoch;
  const controller = new AbortController();
  warmups.set(url.href, controller);
  try {
    const result = await refresh(
      url,
      controller.signal,
      captured,
      Number(data.userId),
    );
    port?.postMessage({ ok: true, ...result });
  } catch (error) {
    port?.postMessage({
      ok: false,
      message: error?.message || "Не удалось сохранить объект.",
    });
  } finally {
    warmups.delete(url.href);
  }
}

async function refresh(url, signal, captured, expectedUserId) {
  const response = await fetch(url.href, {
    credentials: "include",
    cache: "no-store",
    signal: combined(signal, 15000),
  });
  const userId = await authenticated(response);
  const current = await identity();
  if (
    !userId ||
    userId !== expectedUserId ||
    captured !== epoch ||
    current?.tombstone
  ) {
    throw new Error("Сессия изменилась. Войдите снова.");
  }
  if (current?.userId && Number(current.userId) !== userId) {
    throw new Error("Учётная запись изменилась. Войдите снова.");
  }
  if (!current)
    await writeIdentity({ userId, epoch: captured, tombstone: false });
  const contentHash = await responseHash(response);
  if (captured !== epoch) throw new DOMException("Cancelled", "AbortError");
  await (
    await documentCache(userId)
  ).put(url.pathname, await cacheableResponse(response));
  return { contentHash, generation: BUILD_GENERATION };
}

async function checkDocument(data, port) {
  const url = safeChecklist(data.url);
  const current = await identity();
  let response = null;
  if (
    url &&
    current &&
    !current.tombstone &&
    Number(current.userId) === Number(data.userId)
  ) {
    response = await (await documentCache(current.userId)).match(url.pathname);
    if (!response)
      response = await promotePreviousDocument(url.pathname, current.userId);
  }
  const contentHash = response ? await responseHash(response) : null;
  port?.postMessage({
    ok:
      Boolean(response) &&
      (!data.contentHash || data.contentHash === contentHash),
    contentHash,
  });
}

async function navigate(request, url) {
  const captured = epoch;
  try {
    const response = await racedFetch(request, 3000);
    if (
      response.status === 401 ||
      response.status === 403 ||
      response.redirected
    )
      return response;
    if (response.status < 500) {
      const userId = await authenticated(response);
      const current = await identity();
      if (
        userId &&
        !current?.tombstone &&
        (!current?.userId || Number(current.userId) === userId)
      ) {
        if (!current)
          await writeIdentity({ userId, epoch: captured, tombstone: false });
        if (captured === epoch && checklistPath(url.pathname)) {
          await (
            await documentCache(userId)
          ).put(url.pathname, await cacheableResponse(response));
        }
      }
      return response;
    }
  } catch {}
  const current = await identity();
  if (current?.tombstone || !current?.userId) {
    return queuePath(url.pathname)
      ? offlineShell()
      : new Response("Войдите снова, чтобы открыть сохранённый чек-лист.", {
          status: 503,
        });
  }
  if (queuePath(url.pathname)) return offlineShell();
  const currentCache = await documentCache(current.userId);
  const cached = await currentCache.match(url.pathname);
  if (cached) return cached;
  const promoted = await promotePreviousDocument(url.pathname, current.userId);
  return (
    promoted ||
    new Response("Этот чек-лист ещё не сохранён на устройстве.", {
      status: 503,
    })
  );
}

async function promotePreviousDocument(path, userId) {
  const names = await caches.keys();
  const candidates = [];
  for (const name of names) {
    if (
      !(name.includes("checklist-doc-") || name.includes("field-doc-")) ||
      !name.endsWith(`-${userId}`)
    )
      continue;
    const response = await (await caches.open(name)).match(path);
    if (!response) continue;
    const timestamp =
      Date.parse(
        response.headers.get("X-FMonitor-Cached-At") ||
          response.headers.get("Date") ||
          "",
      ) || 0;
    candidates.push({ name, response, timestamp });
  }
  candidates.sort(
    (left, right) =>
      right.timestamp - left.timestamp || right.name.localeCompare(left.name),
  );
  for (const candidate of candidates) {
    const response = candidate.response;
    const html = await compatibleHtml(await response.text());
    const promoted = new Response(html, {
      status: 200,
      headers: { "Content-Type": "text/html; charset=UTF-8" },
    });
    await (await documentCache(userId)).put(path, promoted.clone());
    return promoted;
  }
  return null;
}

async function cacheableResponse(response) {
  const headers = new Headers(response.headers);
  headers.set("X-FMonitor-Cached-At", new Date().toISOString());
  return new Response(await response.clone().arrayBuffer(), {
    status: response.status,
    statusText: response.statusText,
    headers,
  });
}

async function compatibleHtml(html) {
  const manifest = await (await shellCache()).match(ASSET_MANIFEST);
  const assets = manifest ? (await manifest.json()).assets : [];
  const byName = new Map(
    assets.map((url) => [
      new URL(url, self.location.origin).pathname.split("/").pop(),
      url,
    ]),
  );
  const ordered = [
    "field-offline-store.js",
    "field-offline-lifecycle.js",
    "field-offline-sync.js",
    "field-offline-photo.js",
    "field-offline.js",
  ];
  const scripts = ordered
    .map((name) => byName.get(name))
    .filter(Boolean)
    .map((url) => `<script src="${url}" defer></script>`)
    .join("");
  const checklist = byName.get("checklist.js");
  let result = html.replace(
    /<script[^>]+src="\/pilot\/assets\/checklist\.js(?:\?v=[^"]+)?"[^>]*><\/script>/,
    `${scripts}<script src="${checklist}" defer></script>`,
  );
  for (const [name, url] of byName) {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    result = result.replace(
      new RegExp(`/pilot/assets/${escaped}(?:\\?v=[^"']+)?`, "g"),
      url,
    );
  }
  return result;
}

async function offlineShell() {
  return (
    (await (await shellCache()).match(OFFLINE)) ||
    new Response("Сначала подключитесь и подготовьте объекты.", { status: 503 })
  );
}

async function asset(request) {
  const current = await (await shellCache()).match(request);
  if (current) return current;
  const url = new URL(request.url);
  const cached = url.searchParams.has("v") ? await caches.match(request) : null;
  if (cached) return cached;
  return fetch(request);
}

async function responseHash(response) {
  const bytes = await response.clone().arrayBuffer();
  const digest = await crypto.subtle.digest("SHA-256", bytes);
  return Array.from(new Uint8Array(digest), (value) =>
    value.toString(16).padStart(2, "0"),
  ).join("");
}

function racedFetch(request, milliseconds) {
  const controller = new AbortController();
  let timer;
  const deadline = new Promise((_, reject) => {
    timer = setTimeout(() => {
      controller.abort();
      reject(new DOMException("Timed out", "TimeoutError"));
    }, milliseconds);
  });
  return Promise.race([
    fetch(request, { signal: controller.signal }),
    deadline,
  ]).finally(() => clearTimeout(timer));
}

function combined(signal, milliseconds) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), milliseconds);
  signal?.addEventListener("abort", () => controller.abort(), { once: true });
  controller.signal.addEventListener("abort", () => clearTimeout(timer), {
    once: true,
  });
  return controller.signal;
}
