import assert from 'node:assert/strict';
import vm from '../Support/worker_vm.mjs';
import {readFileSync} from 'node:fs';

// YII2-INSPECTION-JOURNEY-001 A7: the offline worker owns checklist
// documents and the prepared field queue, never unrelated pilot navigations.
class MemoryCache {
  constructor() { this.values = new Map(); }
  key(value) { return new URL(typeof value === 'string' ? value : value.url, 'https://pilot.test').href; }
  async put(key, value) { this.values.set(this.key(key), value.clone()); }
  async match(key) { return this.values.get(this.key(key))?.clone(); }
  async delete(key) { return this.values.delete(this.key(key)); }
  async keys() { return [...this.values.keys()].map(url => new Request(url)); }
  async addAll(paths) { for (const path of paths) { const response = await fetch(path); if (!response.ok) throw new Error("Mandatory shell unavailable"); await this.put(path, response); } }
}

const stores = new Map();
const listeners = new Map();
const caches = {
  async open(name) {
    if (!stores.has(name)) stores.set(name, new MemoryCache());
    return stores.get(name);
  },
  async keys() { return [...stores.keys()]; },
  async match(key) { for (const cache of stores.values()) { const response = await cache.match(key); if (response) return response; } },
  async delete(name) { return stores.delete(name); },
};

let offline = false;
let skipWaitingCalls = 0;
let claimCalls = 0;
const fetch = async input => {
  if (offline) throw new TypeError('offline');
  const url = new URL(typeof input === 'string' ? input : input.url, 'https://pilot.test');
  if (url.pathname === '/pilot/assets/field-shell.json') return new Response(JSON.stringify({generation: 'a'.repeat(64), assets: ['/pilot/assets/checklist.js?v=fixture']}), {headers: {'content-type': 'application/json'}});
  return new Response(`<main data-checklist data-user-id="17" data-path="${url.pathname}"></main>`, {
    headers: {'content-type': 'text/html; charset=UTF-8'},
  });
};
const self = {
  location: new URL('https://pilot.test/pilot/assets/checklist-sw.js'),
  clients: {claim: async () => { claimCalls++; }, matchAll: async () => []},
  skipWaiting: async () => { skipWaitingCalls++; },
  addEventListener: (name, handler) => listeners.set(name, handler),
};
vm.runInNewContext(
  readFileSync(new URL('../../app/YiiRuntime/Assets/checklist-sw.js', import.meta.url), 'utf8').replaceAll('__FMONITOR_WORKER_GENERATION__', 'a'.repeat(64)),
  {self, caches, fetch, URL, Response, Request, Headers, TypeError, AbortController, AbortSignal, DOMException, setTimeout, clearTimeout, TextEncoder, crypto: globalThis.crypto},
);

const lifecycle = async type => {
  let work;
  listeners.get(type)({waitUntil(value) { work = value; }});
  await work;
};
const preservedPath = '/pilot/objects/9999/checklist';
await (await caches.open('fmonitor2-checklist-doc-v8-17')).put(
  preservedPath,
  new Response('<main data-checklist data-user-id="17"></main>', {headers: {'content-type': 'text/html'}}),
);
await (await caches.open('fmonitor2-checklist-doc-v6-17')).put(preservedPath, new Response('stale'));
await lifecycle('install');
await lifecycle('activate');
assert.equal(skipWaitingCalls, 0, 'updated worker does not replace live old clients prematurely');
assert.equal(claimCalls, 0, 'updated worker leaves existing clients on their compatible generation');
assert.ok(await (await caches.open('fmonitor2-checklist-doc-v8-17')).match(preservedPath), 'current checklist cache survives worker update');
assert.equal(stores.has('fmonitor2-checklist-doc-v6-17'), true, 'activation does not destructively delete prior private documents');
offline = true;

const navigation = async path => {
  let response;
  listeners.get('fetch')({
    request: {url: `https://pilot.test${path}`, method: 'GET', mode: 'navigate', destination: 'document'},
    respondWith(value) { response = value; },
  });
  return {controlled: response !== undefined, response: response === undefined ? undefined : await response};
};

for (const path of [
  '/pilot/',
  '/pilot/login',
  '/pilot/objects',
  '/pilot/objects/4512',
  '/pilot/installers',
  '/pilot/dashboard',
  '/pilot/objects/4512/checklist/operations',
  '/pilot/objects/4512/checklist/history',
  '/pilot/objects/0/checklist',
  '/pilot/objects/04512/checklist',
  '/pilot/construction-control/objects/4512/checklist/operations',
  '/pilot/construction-control/objects/4512/checklist/history',
  '/pilot/construction-control/objects/0/checklist',
  '/pilot/construction-control/objects/04512/checklist',
]) {
  assert.equal((await navigation(path)).controlled, false, `${path} must remain browser-owned`);
}

const queue = await navigation('/pilot/construction-control');
assert.equal(queue.controlled, true, 'prepared queue/PWA start is worker-owned');
assert.equal(queue.response.status, 200, 'prepared queue has an installed offline shell');

for (const path of [
  '/pilot/objects/4512/checklist',
  '/pilot/construction-control/objects/4512/checklist',
]) {
  const result = await navigation(path);
  assert.equal(result.controlled, true, `${path} must remain worker-controlled`);
  assert.equal(result.response.status, 503, `${path} retains offline fallback`);
}

offline = false;
const online = await navigation('/pilot/objects/4512/checklist');
assert.equal(online.response.status, 200, 'online checklist remains available through worker');

console.log('PASS: YII2-INSPECTION-JOURNEY-001 field/checklist worker navigation boundaries');
