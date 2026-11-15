import assert from 'node:assert/strict';import fs from 'node:fs';import vm from 'node:vm';
// PRODUCTION-PERFORMANCE-001 A02: drive the shipped service-worker event seam.
const listeners=new Map(),storage=new Map(),waits=[],requests=[],gates=[],assetRequests=[];let active=0,maxActive=0;
const cache={async open(name){if(!storage.has(name))storage.set(name,new Map());const m=storage.get(name);return{async match(k){return m.get(typeof k==='string'?k:k.url)?.clone()},async put(k,v){m.set(typeof k==='string'?k:k.url,v.clone())},async delete(k){return m.delete(k)}}},async keys(){return[...storage.keys()]},async delete(k){return storage.delete(k)}};
const context={URL,Request,Response,Headers,AbortController,AbortSignal,Map,Set,Promise,setTimeout,clearTimeout,console,caches:cache,location:{origin:'https://fmonitor.test'},fetch:async(input,opts={})=>{const url=typeof input==='string'?input:input.url;if(url.includes('/pilot/assets/')){assetRequests.push({url,cache:opts.cache});return new Response('fresh generation bytes');}if(!url.includes('/checklist'))return new Response('ok');requests.push(url);active++;maxActive=Math.max(maxActive,active);await new Promise((resolve,reject)=>{let settled=false;const finish=()=>{if(settled)return;settled=true;active--;resolve()};const abort=()=>{if(settled)return;settled=true;active--;reject(new DOMException('Aborted','AbortError'))};opts.signal?.addEventListener('abort',abort,{once:true});gates.push(finish)});return new Response('<main data-checklist data-user-id="1"></main>',{headers:{'Content-Type':'text/html'}})},DOMException};
context.self={location:context.location,clients:{claim:async()=>{},get:async()=>({id:'queue',url:'https://fmonitor.test/pilot/construction-control'}),matchAll:async()=>[{id:'queue',url:'https://fmonitor.test/pilot/construction-control'}]},skipWaiting:async()=>{},addEventListener:(name,fn)=>listeners.set(name,fn)};
vm.runInNewContext(fs.readFileSync(new URL('../../app/YiiRuntime/Assets/checklist-sw.js',import.meta.url),'utf8'),context);
const tick=()=>new Promise(r=>setTimeout(r,10));
function message(data){listeners.get('message')({data,source:{id:'queue',url:'https://fmonitor.test/pilot/construction-control',postMessage(){}},ports:[{postMessage(){}}],waitUntil(p){waits.push(Promise.resolve(p))}})}
let install;listeners.get('install')({waitUntil(p){install=p}});await install;assert.ok(assetRequests.length>5,'generation seeds complete public shell');for(const asset of assetRequests)assert.equal(asset.cache,'no-store','new shell generation bypasses stale browser HTTP cache');
const urls=Array.from({length:6},(_,i)=>`https://fmonitor.test/pilot/objects/${i+1}/checklist`);
message({type:'CACHE_CHECKLIST_BATCH',urls,scope:'queue',generation:'test'});message({type:'CACHE_CHECKLIST_BATCH',urls,scope:'queue',generation:'test'});await tick();
assert.equal(requests.length,2,'INTENDED_RED A02 batch starts exactly two real network slots');assert.equal(maxActive,2,'bounded concurrency');
for(let n=0;n<20&&requests.length<6;n++){gates.splice(0).forEach(f=>f());await tick();}gates.splice(0).forEach(f=>f());await Promise.all(waits);await tick();
assert.equal(requests.length,6,'lifecycle duplicate batches coalesce each URL');assert.equal(new Set(requests).size,6);assert.ok(maxActive<=2);
for(const name of await cache.keys())if(name.includes('checklist-doc'))assert.ok(name.endsWith('-1'),'document cached only under authenticated user');
// A completed lifecycle repeat must not refetch the same generation.
message({type:'CACHE_CHECKLIST_BATCH',urls,scope:'queue',generation:'test'});await tick();assert.equal(requests.length,6,'completed warmups deduplicate lifecycle repeat');
const more=Array.from({length:4},(_,i)=>`https://fmonitor.test/pilot/objects/${20+i}/checklist`);
message({type:'CACHE_CHECKLIST_BATCH',urls:more,scope:'old',generation:'old'});await tick();assert.equal(active,2,'two old requests started');
message({type:'CANCEL_CHECKLIST_WARMUPS',scope:'old'});
message({type:'CACHE_CHECKLIST_BATCH',urls:more,scope:'new',generation:'new'});await tick();
message({type:'CACHE_CHECKLIST_BATCH',urls:more,scope:'new',generation:'new'});await tick();
for(let n=0;n<12;n++){gates.splice(0).forEach(f=>f());await tick();}await Promise.all(waits);
assert.equal(requests.length,12,'cancelled two old + exactly four new; stale finally cannot delete newer registry');assert.ok(maxActive<=2,'cancel/restart retains max two');
// Logout invalidates in-flight/queued work and purges authenticated documents.
message({type:'CACHE_CHECKLIST_BATCH',urls:['https://fmonitor.test/pilot/objects/91/checklist','https://fmonitor.test/pilot/objects/92/checklist','https://fmonitor.test/pilot/objects/93/checklist'],scope:'logout',generation:'logout'});await tick();let logout;
listeners.get('fetch')({request:{url:'https://fmonitor.test/pilot/logout',method:'POST'},respondWith(p){logout=p}});await logout;gates.splice(0).forEach(f=>f());await Promise.all(waits);await tick();assert.equal((await cache.keys()).filter(n=>n.includes('checklist-doc')).length,0,'logout removes every private document cache');assert.equal(active,0,'logout cancels in-flight warmups');
let navigation;listeners.get('fetch')({request:{url:'https://fmonitor.test/pilot/objects/101/checklist',method:'GET',mode:'navigate'},respondWith(p){navigation=p}});await tick();assert.equal(active,1,'navigation response deliberately held');let secondLogout;listeners.get('fetch')({request:{url:'https://fmonitor.test/pilot/logout',method:'POST'},respondWith(p){secondLogout=p}});await secondLogout;gates.splice(0).forEach(f=>f());assert.equal((await navigation).status,200,'real navigation response preserved');await tick();assert.equal((await cache.keys()).filter(n=>n.includes('checklist-doc')).length,0,'late pre-logout navigation cannot recreate private offline cache');
console.log('PASS PRODUCTION-PERFORMANCE-001 A02 worker queue cancellation/logout/stale navigation');
