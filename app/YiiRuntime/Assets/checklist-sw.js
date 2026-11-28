"use strict";
importScripts("__FMONITOR_CACHE_MODULE__");

const META_CACHE = "fmonitor2-field-meta";
const GENERATION_KEY = "/pilot/__field-generation__";
const GENERATION_HISTORY_KEY = "/pilot/__field-generation-history__";
const BUILD_GENERATION = "__FMONITOR_WORKER_GENERATION__";
const IDENTITY_KEY = "/pilot/__field-identity__";
const LOGIN_KEY = "/pilot/__field-login-proof__";
const LOGIN_PENDING_KEY = "/pilot/__field-login-pending__";
const ASSET_MANIFEST = "/pilot/assets/field-shell.json";
const OFFLINE = "/pilot/construction-control/offline";
const SHELL_STAGING_PREFIX = "fmonitor2-field-staging-";
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
const WARMUP_LIMIT = 2;
const warmups = new Map();
const warmupQueue = [];
const completedWarmups = new Map();
let activeWarmups = 0;
let foregroundNavigations = 0;
let epoch = 0;
const liveClientGenerations = new Map();
globalThis.FMonitorFieldCacheTarget = (userId) => `fmonitor2-field-doc-${BUILD_GENERATION}-${userId}`;
globalThis.FMonitorFieldCacheHash = responseHash;
globalThis.FMonitorFieldCacheFence = async expected => {
  const current=await identity();return Boolean(current&&!current.tombstone&&Number(current.userId)===Number(expected?.userId)&&Number(current.epoch)===Number(expected?.epoch)&&expected?.generation===BUILD_GENERATION&&(await activeGeneration())===BUILD_GENERATION);
};
globalThis.FMonitorFieldCachePromote=async response=>{const headers=new Headers(response.headers);headers.set("Content-Type","text/html; charset=UTF-8");return new Response(await compatibleHtml(await response.text()),{status:200,headers});};
globalThis.FMonitorFieldOpenObjectIds=async()=>new Set((await self.clients.matchAll({includeUncontrolled:true})).map(client=>Number(new URL(client.url).pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1])).filter(Number.isInteger));

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
  event.waitUntil(activateWorker());
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
  if (["FIELD_SET_OWN_OBJECTS", "FIELD_CACHE_STATUS", "FIELD_CACHE_MAINTAIN", "FIELD_CLIENT_GENERATION"].includes(data.type)) {
    event.waitUntil(managedCacheMessage(data, event.ports?.[0], event.source));
    return;
  }
  if (data.type === "FIELD_LOGOUT") {
    event.waitUntil(
      tombstone().then(() => event.ports?.[0]?.postMessage({ ok: true })),
    );
    return;
  }
  if (data.type === "FIELD_ESTABLISH_ACTOR") {
    event.waitUntil(establishActor(data, event.ports?.[0], event.source));
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
    event.waitUntil(checkDocument(data, event.ports?.[0],event.source));
    return;
  }
  if (data.type === "CACHE_CHECKLIST") {
    event.waitUntil(cacheChecklist(data, event.ports?.[0], event.source));
    return;
  }
  if (data.type === "CANCEL_CHECKLIST_WARMUPS") {
    event.waitUntil(cancelChecklistWarmups(data, event.ports?.[0], event.source));
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
    event.respondWith(foregroundNavigate(request, url));
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
  const attempt = crypto.randomUUID
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  const stagingName = `${SHELL_STAGING_PREFIX}${BUILD_GENERATION}-${attempt}`;
  const cache = await caches.open(stagingName);
  try {
    for (const url of STATIC_ASSETS) {
      const exact = `${url}${url.includes("?") ? "&" : "?"}build=${BUILD_GENERATION}`;
      const response = await fetch(exact, { cache: "no-store" });
      if (!response.ok)
        throw new Error(`Mandatory shell asset unavailable: ${url}`);
      await cache.put(url, response);
    }
    for (const raw of manifest.assets) {
      const url = new URL(raw, self.location.origin);
      if (url.origin !== self.location.origin || !url.pathname.startsWith("/pilot/assets/"))
        throw new Error("Shell manifest asset invalid");
      const response = await fetch(url.href, { cache: "no-store" });
      if (!response.ok)
        throw new Error(`Mandatory manifest asset unavailable: ${url.pathname}`);
      await cache.put(url.href, response);
    }
    await cache.put(ASSET_MANIFEST, manifestResponse);
    if (!(await completeShellCache(cache, manifest)))
      throw new Error("Staged shell validation failed");
    const targetName=`fmonitor2-field-shell-${BUILD_GENERATION}`,targetBytes=(await caches.keys()).includes(targetName)?await FMonitorFieldCache.cacheBytes(await caches.open(targetName)):0,growth=(await FMonitorFieldCache.cacheBytes(cache))-targetBytes;
    if(!(await FMonitorFieldCache.capacity(Math.max(0,growth))).ok)throw new Error("Shell cache budget exhausted");
  } catch (error) {
    await caches.delete(stagingName);
    throw error;
  }
}

async function activateWorker() {
  await publishInstalledShell();
  FMonitorFieldCache.invalidate();
  await notifyUpdate();
  await clearBuildStaging();
}

async function cacheManifest(cache) {
  try {
    const response = await cache.match(ASSET_MANIFEST);
    if (!response || !response.ok) return null;
    const manifest = await response.json();
    return manifest?.generation === BUILD_GENERATION &&
      Array.isArray(manifest.assets)
      ? manifest
      : null;
  } catch {
    return null;
  }
}

async function publishInstalledShell() {
  const cacheName = `fmonitor2-field-shell-${BUILD_GENERATION}`;
  const names = await caches.keys();
  if (names.includes(cacheName)) {
    const published = await caches.open(cacheName);
    const manifest = await cacheManifest(published);
    if (manifest && (await completeShellCache(published, manifest))) {
      return;
    }
  }
  for (const name of names) {
    if (!name.startsWith(`${SHELL_STAGING_PREFIX}${BUILD_GENERATION}-`))
      continue;
    const staging = await caches.open(name);
    const manifest = await cacheManifest(staging);
    if (!manifest || !(await completeShellCache(staging, manifest))) continue;
    await FMonitorFieldCache.exclusive(async()=>{
      FMonitorFieldCache.invalidate();
      const targetBytes=(await caches.keys()).includes(cacheName)?await FMonitorFieldCache.cacheBytes(await caches.open(cacheName)):0,growth=(await FMonitorFieldCache.cacheBytes(staging))-targetBytes;
      if(!(await FMonitorFieldCache.capacity(Math.max(0,growth))).ok)throw Error("Shell cache budget exhausted at publication");
      await promoteShellCache(staging, cacheName, manifest);
      FMonitorFieldCache.invalidate();
      await FMonitorFieldCache.markMutation();
    });
    return;
  }
  throw new Error("Complete staged shell unavailable");
}

async function clearBuildStaging() {
  for (const name of await caches.keys()) {
    if (name.startsWith(`${SHELL_STAGING_PREFIX}${BUILD_GENERATION}-`))
      await caches.delete(name);
  }
}

async function completeShellCache(cache, expectedManifest) {
  try {
    const stored = await cache.match(ASSET_MANIFEST);
    if (!stored || !stored.ok) return false;
    const manifest = await stored.json();
    if (
      manifest.generation !== BUILD_GENERATION ||
      JSON.stringify(manifest.assets) !== JSON.stringify(expectedManifest.assets)
    )
      return false;
    for (const url of STATIC_ASSETS) {
      const response = await cache.match(url);
      if (!response || !response.ok) return false;
    }
    for (const raw of expectedManifest.assets) {
      const response = await cache.match(new URL(raw, self.location.origin).href);
      if (!response || !response.ok) return false;
    }
    return true;
  } catch {
    return false;
  }
}

async function promoteShellCache(staging, cacheName, manifest) {
  await caches.delete(cacheName);
  const published = await caches.open(cacheName);
  try {
    for (const request of await staging.keys()) {
      const response = await staging.match(request);
      if (!response) throw new Error("Staged shell entry missing");
      await published.put(request, response);
    }
    if (!(await completeShellCache(published, manifest)))
      throw new Error("Published shell validation failed");
  } catch (error) {
    await caches.delete(cacheName);
    FMonitorFieldCache.invalidate();
    await FMonitorFieldCache.markMutation();
    throw error;
  }
}

async function notifyUpdate() {
  const meta = await caches.open(META_CACHE);
  const previous = await activeGeneration();
  const retained = await meta.match(GENERATION_HISTORY_KEY);
  let history = [];
  try {
    history = retained ? await retained.json() : [];
  } catch {}
  history = [...new Set([...history, BUILD_GENERATION])];
  await meta.put(GENERATION_KEY, new Response(BUILD_GENERATION));
  await meta.put(GENERATION_HISTORY_KEY, new Response(JSON.stringify(history)));
  if (!previous || previous === BUILD_GENERATION) return;
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

async function validManagedMessage(data, source) {
  let sourceUrl;
  try { sourceUrl = new URL(source?.url || ""); } catch { return false; }
  const current = await identity();
  if(current)epoch=Math.max(epoch,Number(current.epoch)||0);
  return sourceUrl.origin === self.location.origin && sourceUrl.pathname.startsWith("/pilot/") &&
    Number.isInteger(Number(data.userId)) && Number(data.userId) > 0 &&
    current && !current.tombstone && Number(current.userId) === Number(data.userId) &&
    Number(current.epoch) === Number(data.epoch) && data.generation === BUILD_GENERATION &&
    (await activeGeneration()) === BUILD_GENERATION;
}

async function managedCacheMessage(data, port, source) {
  const validationData=data.type==="FIELD_CLIENT_GENERATION"?{...data,generation:BUILD_GENERATION}:data;
  if (!(await validManagedMessage(validationData, source))) { port?.postMessage({ok:false}); return; }
  const userId=Number(data.userId);
  if(data.type==="FIELD_SET_OWN_OBJECTS"){
    const items=Array.isArray(data.items)?data.items.filter(validCatalogueItem):[];
    if(items.length!==data.items?.length||typeof data.setVersion!=="string"){port?.postMessage({ok:false});return;}
    if(!(await FMonitorFieldCache.setOwn(userId,data.setVersion,items,{userId,epoch:Number(data.epoch),generation:BUILD_GENERATION}))){port?.postMessage({ok:false});return;}await maintainManagedCache(userId,Number(data.epoch));if(!(await validManagedMessage(validationData,source))){port?.postMessage({ok:false});return;}port?.postMessage({ok:true});return;
  }
  if(data.type==="FIELD_CLIENT_GENERATION"){
    const names=await caches.keys(), generation=String(data.generation||"");
    if(!source?.id||!names.includes(`fmonitor2-field-shell-${generation}`)){port?.postMessage({ok:false});return;}
    liveClientGenerations.set(source.id,generation);await maintainManagedCache(userId,Number(data.epoch));if(!(await validManagedMessage(validationData,source))){port?.postMessage({ok:false});return;}port?.postMessage({ok:true});return;
  }
  if(data.type==="FIELD_CACHE_MAINTAIN")await maintainManagedCache(userId,Number(data.epoch));
  const value=await FMonitorFieldCache.status(userId,{userId,epoch:Number(data.epoch),generation:BUILD_GENERATION});if(!(await validManagedMessage(validationData,source))){port?.postMessage({ok:false});return;}port?.postMessage({ok:true,...value});
}

function validCatalogueItem(item){
  if(!item||!Number.isInteger(Number(item.objectId))||Number(item.objectId)<=0||Number(item.userId||0)<0)return false;
  try{const url=new URL(item.checklistUrl,self.location.origin);return url.origin===self.location.origin&&checklistPath(url.pathname)&&url.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1]===String(Number(item.objectId));}catch{return false;}
}

async function maintainManagedCache(userId,actorEpoch){
  return FMonitorFieldCache.exclusive(async()=>{
    const expected={userId,epoch:actorEpoch,generation:BUILD_GENERATION};
    if(!(await FMonitorFieldCacheFence(expected)))return {ok:false,limited:true};
    const meta=await caches.open(META_CACHE),retained=await meta.match(GENERATION_HISTORY_KEY);let history=[];
    try{history=retained?await retained.json():[];}catch{}
    const previous=[...history].reverse().find(value=>value!==BUILD_GENERATION)||null;
    const clients=await self.clients.matchAll({includeUncontrolled:true}),ids=new Set(clients.map(client=>client.id));
    const openObjectIds=new Set(clients.map(client=>Number(new URL(client.url).pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1])).filter(Number.isInteger));
    for(const id of [...liveClientGenerations.keys()])if(!ids.has(id))liveClientGenerations.delete(id);
    const unknownLive=clients.some(client=>!liveClientGenerations.has(client.id));
    const result=await FMonitorFieldCache.maintain(userId,BUILD_GENERATION,previous,new Set(liveClientGenerations.values()),unknownLive,openObjectIds,expected,true);
    if(unknownLive||result.limited||!(await FMonitorFieldCacheFence(expected)))return result;
    const keep=[previous,BUILD_GENERATION,...liveClientGenerations.values()].filter(Boolean),allowed=new Set(keep),names=await caches.keys();
    const foreignUsers=new Set(names.map(name=>Number(name.match(/^fmonitor2-field-doc-[a-f0-9]{64}-([1-9][0-9]*)$/)?.[1])).filter(id=>Number.isInteger(id)&&id!==userId));
    for(const foreignUser of foreignUsers)if(!(await FMonitorFieldCache.consolidateUser(foreignUser,allowed,expected,true)))return {...result,limited:true};
    const legacyUsers=new Set(names.map(name=>Number(name.match(/^fmonitor2-checklist-doc-.+-([1-9][0-9]*)$/)?.[1])).filter(Number.isInteger));
    if([...legacyUsers].some(legacyUser=>legacyUser!==userId))return {...result,limited:true};
    for(const legacyUser of legacyUsers)if(!(await FMonitorFieldCache.consolidateLegacyUser(legacyUser,openObjectIds,expected,true)))return {...result,limited:true};
    for(const name of await caches.keys())if(name.startsWith("fmonitor2-checklist-shell-")){
      if(!(await FMonitorFieldCacheFence(expected)))return {...result,limited:true};
      await caches.delete(name);FMonitorFieldCache.invalidate();await FMonitorFieldCache.markMutation();
    }
    for(const name of await caches.keys()){
      const generation=name.match(/^fmonitor2-field-shell-([a-f0-9]{64})$/)?.[1];
      if(generation&&!allowed.has(generation)){
        if(!(await FMonitorFieldCacheFence(expected)))return {...result,limited:true};
        await caches.delete(name);FMonitorFieldCache.invalidate();await FMonitorFieldCache.markMutation();
      }
    }
    if(await FMonitorFieldCacheFence(expected))await meta.put(GENERATION_HISTORY_KEY,new Response(JSON.stringify([previous,BUILD_GENERATION].filter(Boolean))));
    return result;
  });
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

async function liveBootstrapSource(source, objectId) {
  if (!(await liveActorSource(source))) return false;
  let sourceUrl;
  try { sourceUrl = new URL(source.url); } catch { return false; }
  return checklistPath(sourceUrl.pathname) && Number(sourceUrl.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1]) === objectId;
}

async function liveActorSource(source) {
  if (!source?.id || source.type !== "window" || !["top-level", "auxiliary"].includes(source.frameType)) return false;
  let sourceUrl;
  try { sourceUrl = new URL(source.url); } catch { return false; }
  if(sourceUrl.origin!==self.location.origin||!sourceUrl.pathname.startsWith("/pilot/"))return false;
  return (await self.clients.matchAll({includeUncontrolled:true})).some(client => client.id === source.id && client.url === source.url);
}

async function establishActor(data, port, source) {
  const current = await identity();
  const userId=Number(data.userId),actorEpoch=Number(data.epoch);
  if (!Number.isInteger(userId) || userId <= 0 || !Number.isInteger(actorEpoch) || actorEpoch < 0 || data.generation !== BUILD_GENERATION || current?.tombstone || actorEpoch < Number(current?.epoch || 0)) {
    port?.postMessage({ ok: false });
    return;
  }
  if(current){
    const exact=Number(current.userId)===userId&&Number(current.epoch)===actorEpoch;
    port?.postMessage({ok:exact,cacheReady:false,...(exact?{}:{reasonCode:"ACTOR_MISMATCH"})});
    return;
  }
  try {
    const target = new URL(data.url || "", self.location.origin);
    const objectId=Number(target.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1]);
    if (target.origin !== self.location.origin || !target.pathname.startsWith("/pilot/") || !(await liveActorSource(source))) throw new Error("Invalid actor source");
    const checklistBootstrap=checklistPath(target.pathname)&&Number.isInteger(objectId)&&await liveBootstrapSource(source,objectId);
    const response = await fetch(target.href, {
      credentials: "include",
      cache: "no-store",
      headers: { Accept: "text/html" },
      signal: AbortSignal.timeout ? AbortSignal.timeout(15000) : undefined,
    });
    const redirect=redirectKind(response,target);
    if(redirect==="login"||Number(await authenticated(response))!==userId)throw Error("Bootstrap authentication failed");
    if((await identity())!==null||(await activeGeneration())!==BUILD_GENERATION||!(await liveActorSource(source)))throw Error("Bootstrap context changed");
    const metadata=checklistBootstrap&&redirect!=="unrecognized"?await offlineMetadata(response,target,userId):null;
    if(checklistBootstrap&&(!metadata||metadata===false))throw Error("Bootstrap metadata invalid");
    epoch=Math.max(epoch,actorEpoch);await writeIdentity({userId,epoch:actorEpoch,tombstone:false});
    const after=await identity();if(after?.tombstone||Number(after?.userId)!==userId||Number(after?.epoch)!==actorEpoch||(await activeGeneration())!==BUILD_GENERATION)throw Error("Bootstrap context changed");
    if(!checklistBootstrap){port?.postMessage({ok:true,cacheReady:false});return;}
    const expected={userId,epoch:actorEpoch,generation:BUILD_GENERATION},proof={userId,objectId,source:"substitution",expiresAt:Date.now()+FMonitorFieldCache.TTL,confirmedAt:Date.now(),metadata};
    const admitted=await FMonitorFieldCache.admit(userId,target.pathname,await cacheableResponse(response),expected,proof);
    if(!admitted.ok){const actorStillValid=await FMonitorFieldCacheFence(expected);port?.postMessage({ok:actorStillValid&&admitted.reasonCode!=="CACHE_CONTEXT_STALE",cacheReady:false,reasonCode:admitted.reasonCode});return;}
    let committed=false,outcome={ok:true,cacheReady:false,reasonCode:"CACHE_METADATA_FAILED"};
    try{
      if(!(await liveBootstrapSource(source,objectId))||!(await FMonitorFieldCacheFence(expected))){outcome={ok:false,cacheReady:false,reasonCode:"CACHE_CONTEXT_STALE"};}
      else if(await FMonitorFieldCache.record(userId,metadata,true,expected)){
        if(!(await liveBootstrapSource(source,objectId))||!(await FMonitorFieldCacheFence(expected)))outcome={ok:false,cacheReady:false,reasonCode:"CACHE_CONTEXT_STALE"};
        else{await FMonitorFieldCache.commitWrite(admitted.writeId);committed=true;outcome={ok:true,cacheReady:true};}
      }
    }catch{}
    finally{if(!committed)await FMonitorFieldCache.deleteIfWrite(userId,target.pathname,admitted.writeId);}
    port?.postMessage(outcome);
  } catch {
    port?.postMessage({ ok: false });
  }
}

async function tombstone() {
  const current = await identity();
  epoch = Math.max(epoch, Number(current?.epoch || 0)) + 1;
  cancelAllWarmups("Сеанс завершён. Подготовка остановлена.");
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

function warmupText(value) {
  return typeof value === "string" && value.length > 0 && value.length <= 256
    ? value
    : null;
}

function warmupKey(userId, actorEpoch, generation, url) {
  return `${userId}\n${actorEpoch}\n${generation}\n${url.href}`;
}

function consumerKey(clientId, data, attempt) {
  return `${clientId}\n${data.userId}\n${data.epoch}\n${data.generation}\n${data.scope}\n${attempt}`;
}

function memoKey(ownerKey, entryKey) {
  return `${ownerKey}\n${entryKey}`;
}

async function warmupContextValid(userId, actorEpoch, generation) {
  if (generation !== BUILD_GENERATION) return false;
  const [current, active] = await Promise.all([identity(), activeGeneration()]);
  epoch = Math.max(epoch, Number(current?.epoch || 0));
  return Boolean(
    current &&
      !current.tombstone &&
      Number(current.userId) === Number(userId) &&
      Number(current.epoch) === Number(actorEpoch) &&
      Number(actorEpoch) === epoch &&
      active === BUILD_GENERATION,
  );
}

function finishWarmupSubscriber(subscriber, result, remember = false) {
  if (subscriber.done) return;
  subscriber.done = true;
  if (remember && result.ok) {
    completedWarmups.set(subscriber.memoKey, {
      clientId: subscriber.clientId,
      scope: subscriber.scope,
      attempt: subscriber.attempt,
      userId: subscriber.userId,
      epoch: subscriber.epoch,
      generation: subscriber.generation,
      result,
    });
  }
  subscriber.port?.postMessage(result);
  subscriber.resolve(result);
}

function cancelEntryIfUnused(entry) {
  if (entryHasConsumers(entry)) return;
  entry.state = "cancelled";
  entry.controller.abort();
  if (warmups.get(entry.key) === entry) warmups.delete(entry.key);
}

function entryHasConsumers(entry) {
  return [...entry.subscribers].some((subscriber) => !subscriber.done);
}

function cancelAllWarmups(message) {
  const result = { ok: false, message };
  for (const entry of warmups.values()) {
    for (const subscriber of entry.subscribers)
      finishWarmupSubscriber(subscriber, result);
    entry.state = "cancelled";
    entry.controller.abort();
  }
  warmups.clear();
  warmupQueue.length = 0;
  completedWarmups.clear();
}

async function cancelChecklistWarmups(data, port, source) {
  const clientId = warmupText(source?.id);
  const scope = warmupText(data.scope);
  const attempt = data.attempt == null ? null : warmupText(data.attempt);
  if (
    !clientId ||
    !scope ||
    (data.attempt != null && !attempt) ||
    !Number.isInteger(Number(data.userId)) ||
    !Number.isInteger(Number(data.epoch)) ||
    data.generation !== BUILD_GENERATION
  ) {
    port?.postMessage({ ok: false, cancelled: 0 });
    return { ok: false, cancelled: 0 };
  }
  let cancelled = 0;
  for (const entry of [...warmups.values()]) {
    for (const subscriber of entry.subscribers) {
      if (
        !subscriber.done &&
        subscriber.clientId === clientId &&
        subscriber.scope === scope &&
        (attempt === null || subscriber.attempt === attempt) &&
        subscriber.userId === Number(data.userId) &&
        subscriber.epoch === Number(data.epoch) &&
        subscriber.generation === data.generation
      ) {
        cancelled++;
        finishWarmupSubscriber(subscriber, {
          ok: false,
          message: "Подготовка отменена.",
        });
      }
    }
    cancelEntryIfUnused(entry);
  }
  for (const [key, memo] of completedWarmups) {
    if (
      memo.clientId === clientId &&
      memo.scope === scope &&
      (attempt === null || memo.attempt === attempt) &&
      memo.userId === Number(data.userId) &&
      memo.epoch === Number(data.epoch) &&
      memo.generation === data.generation
    )
      completedWarmups.delete(key);
  }
  const result = { ok: true, cancelled };
  port?.postMessage(result);
  pumpWarmups();
  return result;
}

async function cacheChecklist(data, port, source) {
  const url = safeChecklist(data.url);
  const clientId = warmupText(source?.id);
  const scope = warmupText(data.scope);
  const attempt = warmupText(data.attempt ?? data.scope);
  const userId = Number(data.userId);
  const actorEpoch = Number(data.epoch);
  const generation = data.generation;
  if (
    !url ||
    !clientId ||
    !scope ||
    !attempt ||
    !Number.isInteger(userId) ||
    !Number.isInteger(actorEpoch) ||
    generation !== BUILD_GENERATION ||
    !(await warmupContextValid(userId, actorEpoch, generation))
  ) {
    const result = { ok: false, message: "Сессия подготовки устарела." };
    port?.postMessage(result);
    return result;
  }
  const key = warmupKey(userId, actorEpoch, generation, url);
  const ownerKey = consumerKey(clientId, data, attempt);
  const completed = completedWarmups.get(memoKey(ownerKey, key));
  if (completed) {
    port?.postMessage(completed.result);
    return completed.result;
  }
  let entry = warmups.get(key);
  if (!entry || entry.state === "cancelled" || ![...entry.subscribers].some((subscriber) => !subscriber.done)) {
    entry = {
      key,
      url,
      userId,
      epoch: actorEpoch,
      generation,
      state: "queued",
      controller: new AbortController(),
      subscribers: new Set(),
    };
    warmups.set(key, entry);
    warmupQueue.push(entry);
  }
  const promise = new Promise((resolve) => {
    entry.subscribers.add({
      clientId,
      scope,
      attempt,
      userId,
      epoch: actorEpoch,
      generation,
      memoKey: memoKey(ownerKey, key),
      port,
      resolve,
      done: false,
    });
  });
  pumpWarmups();
  return promise;
}

function pumpWarmups() {
  if (foregroundNavigations > 0) return;
  while (activeWarmups < WARMUP_LIMIT && warmupQueue.length > 0) {
    const entry = warmupQueue.shift();
    if (
      !entry ||
      entry.state !== "queued" ||
      warmups.get(entry.key) !== entry ||
      !entryHasConsumers(entry)
    )
      continue;
    dispatchWarmup(entry);
  }
}

function dispatchWarmup(entry) {
  entry.state = "inflight";
  activeWarmups++;
  (async () => {
    if (!(await warmupContextValid(entry.userId, entry.epoch, entry.generation)))
      throw new Error("Сессия подготовки устарела.");
    const result = await refreshWarmup(entry);
    if (!(await warmupContextValid(entry.userId, entry.epoch, entry.generation)))
      throw new Error("Сессия подготовки устарела.");
    entry.state = "completed";
    for (const subscriber of entry.subscribers)
      finishWarmupSubscriber(subscriber, { ok: true, ...result }, true);
  })()
    .catch((error) => {
      const result = {
        ok: false,
        message: error?.message || "Не удалось сохранить объект.",
        reasonCode: error?.reasonCode,
      };
      for (const subscriber of entry.subscribers)
        finishWarmupSubscriber(subscriber, result);
    })
    .finally(() => {
      activeWarmups--;
      if (warmups.get(entry.key) === entry) warmups.delete(entry.key);
      pumpWarmups();
    });
}

async function refreshWarmup(entry) {
  const response = await fetch(entry.url.href, {
    credentials: "include",
    cache: "no-store",
    signal: combined(entry.controller.signal, 15000),
  });
  const userId = await authenticated(response);
  if (userId !== entry.userId)
    throw new Error("Учётная запись изменилась. Войдите снова.");
  if (!entryHasConsumers(entry)) throw new DOMException("Cancelled", "AbortError");
  if (!(await warmupContextValid(entry.userId, entry.epoch, entry.generation)))
    throw new Error("Сессия подготовки устарела.");
  const contentHash = await responseHash(response);
  if (!entryHasConsumers(entry)) throw new DOMException("Cancelled", "AbortError");
  if (!(await warmupContextValid(entry.userId, entry.epoch, entry.generation)))
    throw new Error("Сессия подготовки устарела.");
  const own=await FMonitorFieldCache.ownItem(userId,Number(entry.url.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1])), expected={userId,epoch:entry.epoch,generation:entry.generation}, proof=own?{userId,objectId:Number(own.objectId),source:"own",expiresAt:null,confirmedAt:Date.now(),metadata:own}:null, admitted=await FMonitorFieldCache.admit(userId,entry.url.pathname,await cacheableResponse(response),expected,proof);
  if(!admitted.ok){const error=new Error("Недостаточно места для сохранения объекта.");error.reasonCode=admitted.reasonCode;throw error;}
  let committed=false;
  try{
    if(own&&!(await FMonitorFieldCache.record(userId,own,false,expected)))throw Error("Не удалось подтвердить объект.");
    if(!entryHasConsumers(entry)||!(await warmupContextValid(entry.userId,entry.epoch,entry.generation)))throw Error("Сессия подготовки устарела.");
    await FMonitorFieldCache.commitWrite(admitted.writeId);committed=true;
  }finally{if(!committed)await FMonitorFieldCache.deleteIfWrite(userId,entry.url.pathname,admitted.writeId);}
  return { contentHash, generation: BUILD_GENERATION };
}

async function checkDocument(data, port, source) {
  const url = safeChecklist(data.url);
  const valid=await validManagedMessage(data,source),objectId=Number(url?.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1]);
  if(!valid||!url||!Number.isInteger(objectId)||!(await FMonitorFieldCache.cacheCheckAllowed(Number(data.userId),objectId))){port?.postMessage({ok:false,contentHash:null});return;}
  const found=await FMonitorFieldCache.findObjectDocument(Number(data.userId),objectId,url.pathname),response=found?.response||null;
  const contentHash = response ? await responseHash(response) : null;
  if(!(await validManagedMessage(data,source))){port?.postMessage({ok:false,contentHash:null});return;}
  port?.postMessage({
    ok:
      Boolean(response) &&
      (!data.contentHash || data.contentHash === contentHash),
    contentHash,
  });
}

async function foregroundNavigate(request, url) {
  foregroundNavigations++;
  try {
    return await navigate(request, url);
  } finally {
    foregroundNavigations--;
    pumpWarmups();
  }
}

function redirectKind(response, requestedUrl) {
  if (!response.redirected) return "none";
  try {
    const target = new URL(response.url);
    if (target.origin !== self.location.origin) return "unrecognized";
    if (["/pilot/login", "/pilot/otiz/login"].includes(target.pathname))
      return "login";
    const requestedId = requestedUrl.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1];
    const targetId = target.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1];
    return checklistPath(target.pathname) && requestedId === targetId
      ? "checklist"
      : "unrecognized";
  } catch {
    return "unrecognized";
  }
}

async function navigate(request, url) {
  const startingIdentity = await identity();
  epoch = Math.max(epoch, Number(startingIdentity?.epoch || 0));
  const captured = epoch;
  const capturedGeneration = await activeGeneration();
  try {
    const response = await racedFetch(request, 3000);
    const redirect = redirectKind(response, url);
    if (
      response.status === 401 ||
      response.status === 403 ||
      redirect === "login"
    ) {
      const deniedId=Number(url.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1]);
      const deniedFence=startingIdentity?{userId:Number(startingIdentity.userId),epoch:Number(startingIdentity.epoch),generation:capturedGeneration}:null;
      if(deniedId&&deniedFence)try{await FMonitorFieldCache.revoke(deniedFence.userId,deniedId,deniedFence);}catch{}
      return response;
    }
    if (redirect === "unrecognized") return response;
    if (response.status < 500) {
      const userId = await authenticated(response);
      const current = await identity();
      if (
        userId &&
        !current?.tombstone &&
        (!current?.userId || Number(current.userId) === userId) &&
        Number(current?.epoch || captured) === captured &&
        captured === epoch &&
        capturedGeneration === BUILD_GENERATION &&
        (await activeGeneration()) === BUILD_GENERATION
      ) {
        if (!current)
          await writeIdentity({ userId, epoch: captured, tombstone: false });
        if (captured === epoch && checklistPath(url.pathname)) {
          const metadata=await offlineMetadata(response,url,userId);
          if(!metadata||metadata===false)return response;
          const expected={userId,epoch:captured,generation:BUILD_GENERATION},own=await FMonitorFieldCache.ownItem(userId,metadata.objectId),proof={userId,objectId:Number(metadata.objectId),source:own?"own":"substitution",expiresAt:own?null:Date.now()+FMonitorFieldCache.TTL,confirmedAt:Date.now(),metadata};
          const admitted=await FMonitorFieldCache.admit(userId,url.pathname,await cacheableResponse(response),expected,proof);
          if(admitted.ok){
            let committed=false;
            try{
              if(!(await FMonitorFieldCache.record(userId,metadata,true,expected)))throw Error("Cache metadata failed");
              const after=await identity();
              if(after?.tombstone||Number(after?.userId)!==userId||Number(after?.epoch)!==captured||captured!==epoch||(await activeGeneration())!==BUILD_GENERATION)throw Error("Cache context stale");
              await FMonitorFieldCache.commitWrite(admitted.writeId);committed=true;
              try{await maintainManagedCache(userId,captured);}catch{}
            }catch{}
            finally{if(!committed)await FMonitorFieldCache.deleteIfWrite(userId,url.pathname,admitted.writeId);}
          }
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
  const objectId=Number(url.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1]), offlineFence={userId:Number(current.userId),epoch:Number(current.epoch),generation:BUILD_GENERATION}, access=await FMonitorFieldCache.access(current.userId,objectId,offlineFence);
  if(access===false)return new Response("Этот чек-лист недоступен без подключения.",{status:503});
  const currentCache = await documentCache(current.userId);
  const cached = await currentCache.match(url.pathname);
  if (cached) return cached;
  const alias=await FMonitorFieldCache.findObjectDocument(current.userId,objectId,url.pathname);if(alias)return (await promotePreviousDocument(alias.path,current.userId))||alias.response;
  const promoted = await promotePreviousDocument(url.pathname, current.userId);
  return (
    promoted ||
    new Response("Этот чек-лист ещё не сохранён на устройстве.", {
      status: 503,
    })
  );
}

async function offlineMetadata(response,url,userId){
  try{
    const html=await response.clone().text(), match=html.match(/\sdata-offline-cache="([^"]+)"/);if(!match)return null;
    const binary=atob(match[1]), bytes=Uint8Array.from(binary,x=>x.charCodeAt(0)), value=JSON.parse(new TextDecoder().decode(bytes));
    if(Number(value.userId)!==Number(userId)||Number(value.objectId)!==Number(url.pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1])||!validCatalogueItem(value))return false;
    const canonical=new URL(value.checklistUrl,self.location.origin).pathname;
    if(canonical!==url.pathname&&!checklistPath(canonical))return false;
    return {...value,objectId:Number(value.objectId),userId:Number(value.userId),checklistUrl:url.pathname};
  }catch{return false;}
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
      headers: response.headers,
    });
    const current=await identity(),expected={userId,epoch:Number(current?.epoch),generation:BUILD_GENERATION},admitted=await FMonitorFieldCache.admit(userId,path,promoted.clone(),expected,null);
    if(!admitted.ok)return promoted;
    let committed=false;
    try{if(!(await FMonitorFieldCacheFence(expected)))throw Error("Promotion context stale");await FMonitorFieldCache.commitWrite(admitted.writeId);committed=true;}
    finally{if(!committed)await FMonitorFieldCache.deleteIfWrite(userId,path,admitted.writeId);}
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
    "checklist.js",
  ];
  const scripts = ordered
    .map((name) => byName.get(name))
    .filter(Boolean)
    .map((url) => `<script src="${url}" defer></script>`)
    .join("");
  const engineNames=ordered.map(name=>name.replace(/[.*+?^${}()|[\]\\]/g,"\\$&")).join("|");
  const engineTag=new RegExp(`<script\\b[^>]*\\bsrc\\s*=\\s*(["'])\\/pilot\\/assets\\/(?:${engineNames})(?:\\?[^"']*)?\\1[^>]*>\\s*<\\/script\\s*>`,"gi");
  const marker="<!--FMONITOR_FIELD_ENGINE-->";let marked=false;
  let result=html.replace(engineTag,()=>{if(marked)return "";marked=true;return marker;});
  if(marked)result=result.replace(marker,scripts);
  else if(/<\/body\s*>/i.test(result))result=result.replace(/<\/body\s*>/i,`${scripts}</body>`);
  else result+=scripts;
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
  const cached = url.searchParams.has("v")
    ? await publishedAssetMatch(request)
    : null;
  if (cached) return cached;
  return fetch(request);
}

async function publishedAssetMatch(request) {
  const names = await caches.keys();
  const allowed = new Set(
    names.filter((name) => name.startsWith("fmonitor2-checklist-shell-")),
  );
  const meta = await caches.open(META_CACHE);
  const retained = await meta.match(GENERATION_HISTORY_KEY);
  let generations = [];
  try {
    generations = retained ? await retained.json() : [];
  } catch {}
  const active = await activeGeneration();
  if (active) generations.push(active);
  for (const generation of new Set(generations)) {
    if (typeof generation === "string" && /^[a-f0-9]{64}$/.test(generation))
      allowed.add(`fmonitor2-field-shell-${generation}`);
  }
  for (const name of allowed) {
    if (!names.includes(name)) continue;
    const response = await (await caches.open(name)).match(request);
    if (response) return response;
  }
  return null;
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
