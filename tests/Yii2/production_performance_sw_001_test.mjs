import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from '../Support/worker_vm.mjs';
// PRODUCTION-PERFORMANCE-001 A02/A12 and RELIABLE-FIELD-OFFLINE-001 A1/A8.
// Exercise the shipped individual RPC protocol. Scope is one preparation attempt;
// a later scope intentionally refreshes documents, including already-cached ones.
const build='b'.repeat(64), source=fs.readFileSync(new URL('../../app/YiiRuntime/Assets/checklist-sw.js',import.meta.url),'utf8').replaceAll('__FMONITOR_WORKER_GENERATION__',build);
const tick=()=>new Promise(r=>setTimeout(r,10));
function fixture(){
 const listeners=new Map(),storage=new Map(),requests=[],assets=[],gates=[],jobs=[],notices=[];let active=0,maxActive=0;
 const key=k=>new URL(typeof k==='string'?k:k.url,'https://fmonitor.test').href;
 const fetcher=async(input,options={})=>{const url=key(input),u=new URL(url);
  if(u.pathname==='/pilot/assets/field-shell.json'){assets.push({url,cache:options.cache});return new Response(JSON.stringify({generation:build,assets:['shlz.css','pilot.css','checklist.js'].map(x=>'/pilot/assets/'+x+'?v=fixture-content')}),{headers:{'Content-Type':'application/json'}});}
  if(u.pathname.includes('/assets/')){assets.push({url,cache:options.cache});return new Response('exact:'+url);}
  if(u.pathname.endsWith('/offline'))return new Response('<main data-offline-shell></main>',{headers:{'Content-Type':'text/html'}});
  if(u.pathname.endsWith('/logout'))return new Response('logged out');
  if(!u.pathname.endsWith('/checklist'))return new Response('<main data-authenticated-user-id="1"></main>',{headers:{'Content-Type':'text/html'}});
  const foreground=typeof input!=='string'&&input.mode==='navigate';requests.push({url,foreground});if(!foreground){active++;maxActive=Math.max(maxActive,active);}
  await new Promise((resolve,reject)=>{let settled=false;const end=error=>{if(settled)return;settled=true;if(!foreground)active--;error?reject(error):resolve();};options.signal?.addEventListener('abort',()=>end(new DOMException('Aborted','AbortError')),{once:true});gates.push({url,foreground,release:()=>end()});if(options.signal?.aborted)end(new DOMException('Aborted','AbortError'));});
  return new Response('<main data-checklist data-user-id="1"></main>',{headers:{'Content-Type':'text/html'}});
 };
 const caches={async open(name){if(!storage.has(name))storage.set(name,new Map());const m=storage.get(name);return{async match(k){return m.get(key(k))?.clone()},async put(k,v){m.set(key(k),v.clone())},async delete(k){return m.delete(key(k))},async keys(){return [...m.keys()].map(x=>new Request(x))},async addAll(paths){for(const p of paths){const r=await fetcher(p);if(!r.ok)throw Error('asset');m.set(key(p),r.clone());}}};},async keys(){return [...storage.keys()]},async delete(name){return storage.delete(name)}};
 const clients=['a','b'].map(id=>({id,type:'window',frameType:'top-level',url:'https://fmonitor.test/pilot/construction-control',postMessage:message=>notices.push(message)}));
 const self={location:new URL('https://fmonitor.test/pilot/assets/checklist-sw.js'),clients:{get:async id=>clients.find(x=>x.id===id),matchAll:async()=>clients,claim:async()=>{throw Error('unexpected claim')}},skipWaiting:async()=>{throw Error('unexpected skipWaiting')},addEventListener:(type,handler)=>listeners.set(type,handler)};
 vm.runInNewContext(source,{self,caches,fetch:fetcher,URL,Request,Response,Headers,AbortController,AbortSignal,DOMException,TextEncoder,crypto:globalThis.crypto,setTimeout,clearTimeout,console});
 const rpc=(data,client='a')=>{let work,reply;listeners.get('message')({data,source:clients.find(x=>x.id===client),ports:[{postMessage:value=>reply=value}],waitUntil:p=>work=p});const promise=Promise.resolve(work).then(()=>reply);jobs.push(promise);return promise;};
 const lifecycle=async type=>{let work;listeners.get(type)({waitUntil:p=>work=p});await work;};
 const warm=(id,scope='first',client='a')=>rpc({type:'CACHE_CHECKLIST',url:'https://fmonitor.test/pilot/objects/'+id+'/checklist',userId:1,epoch:0,generation:build,scope},client);
 const cancel=(scope,client='a')=>rpc({type:'CANCEL_CHECKLIST_WARMUPS',scope,userId:1,epoch:0,generation:build},client);
 const nav=path=>{const request=new Request('https://fmonitor.test'+path);Object.defineProperty(request,'mode',{value:'navigate'});Object.defineProperty(request,'destination',{value:'document'});let response;listeners.get('fetch')({request,respondWith:p=>response=p});jobs.push(Promise.resolve(response));return response;};
 const drain=async()=>{for(let n=0;n<30;n++){gates.splice(0).forEach(x=>x.release());await tick();}await Promise.allSettled(jobs);};
 return{requests,assets,notices,storage,lifecycle,warm,cancel,rpc,nav,drain,gates,active:()=>active,max:()=>maxActive,async ready(){await lifecycle('install');await lifecycle('activate');const actor=await rpc({type:'FIELD_ESTABLISH_ACTOR',userId:1,epoch:0,generation:build,url:'https://fmonitor.test/pilot/objects'});assert.equal(actor.ok,true,'fixture established authenticated actor');}};
}
const failures=[];
async function test(name,run){const f=fixture();try{await f.ready();await run(f);console.log('PASS '+name);}catch(error){failures.push({name,message:error.message});console.error('FAIL '+name+': '+error.message);}finally{await f.drain();}}
await test('A12 complete generation bypasses HTTP cache',async f=>{assert.ok(f.assets.length>10);for(const r of f.assets)assert.equal(r.cache,'no-store','generation asset '+r.url);});
await test('A9 first activation does not announce an update',async f=>{assert.equal(f.notices.filter(x=>x.type==='FIELD_UPDATE_READY').length,0,'no update exists on first install');});
await test('A02 global two slots, duplicate coalescing and explicit refresh',async f=>{
 const first=Array.from({length:6},(_,i)=>f.warm(i+1)),duplicate=Array.from({length:6},(_,i)=>f.warm(i+1));await tick();assert.equal(f.requests.length,2,'exactly two starts across duplicate burst');assert.equal(f.max(),2);await f.drain();for(const r of await Promise.all([...first,...duplicate]))assert.equal(r.ok,true);assert.equal(f.requests.length,6,'six URLs fetched once within attempt');
 const completed=f.warm(1);await tick();assert.equal(f.requests.length,6,'completed same attempt coalesces');assert.equal((await completed).ok,true);const refreshed=f.warm(1,'new-attempt');await tick();assert.equal(f.requests.length,7,'new preparation attempt refreshes same URL');await f.drain();assert.equal((await refreshed).ok,true);
});
await test('A02 two clients share slots and pending URL without cancelling each other',async f=>{
 const a=f.warm(1,'client-a','a'),b=f.warm(1,'client-b','b'),c=f.warm(2,'client-a','a'),d=f.warm(3,'client-b','b');await tick();assert.equal(f.requests.length,2);assert.equal(f.max(),2);await f.cancel('client-a','a');await f.drain();assert.equal((await b).ok,true,'other client still receives shared request');assert.equal((await d).ok,true);assert.equal((await a).ok,false);assert.equal((await c).ok,false);assert.equal(f.requests.filter(x=>x.url.endsWith('/1/checklist')).length,1,'shared URL fetched once');assert.ok(f.max()<=2);
});
await test('A02 owner-only cancel and stale-finally cannot remove replacement',async f=>{
 const old=[20,21,22,23].map(id=>f.warm(id,'old'));await tick();assert.equal(f.active(),2);await f.cancel('old','b');assert.equal(f.active(),2,'other client cannot cancel owner scope');const cancellation=f.cancel('old');const fresh=[20,21,22,23].map(id=>f.warm(id,'new'));const duplicates=[20,21,22,23].map(id=>f.warm(id,'new'));await cancellation;await f.drain();for(const r of await Promise.all(old))assert.equal(r.ok,false);for(const r of await Promise.all([...fresh,...duplicates]))assert.equal(r.ok,true);assert.equal(f.requests.length,6,'two cancelled old + four new, no stale-registry loss');assert.ok(f.max()<=2);
});
await test('A02 foreground navigation has priority over queued warmups',async f=>{
 const work=[40,41,42,43].map(id=>f.warm(id));await tick();const foreground=f.nav('/pilot/objects/900/checklist');await tick();assert.equal(f.requests.filter(x=>x.foreground).length,1,'navigation does not wait for prefetch slots');const held=f.gates.splice(0);for(const gate of held.filter(x=>!x.foreground))gate.release();f.gates.push(...held.filter(x=>x.foreground));await tick();assert.equal(f.requests.filter(x=>!x.foreground).length,2,'queued speculation waits behind foreground');
 f.gates.splice(0).filter(x=>x.foreground).forEach(x=>x.release());assert.equal((await foreground).status,200);await f.drain();for(const r of await Promise.all(work))assert.equal(r.ok,true);assert.ok(f.max()<=2);
});
await test('A8 logout aborts all warmups and rejects stale epoch without a fetch',async f=>{
 const work=[91,92,93].map(id=>f.warm(id,'logout'));await tick();await f.rpc({type:'FIELD_LOGOUT'});await f.drain();for(const r of await Promise.all(work))assert.equal(r.ok,false);assert.equal(f.active(),0);const count=f.requests.length;const stale=f.warm(94,'stale');await tick();assert.equal(f.requests.length,count,'stale actor rejected before network');assert.equal((await stale).ok,false);const identity=await f.rpc({type:'FIELD_CHECK_ACTOR'});assert.equal(identity.identity.tombstone,true);
});
await test('A8 late foreground navigation cannot recreate private cache',async f=>{
 const response=f.nav('/pilot/objects/101/checklist');await tick();await f.rpc({type:'FIELD_LOGOUT'});f.gates.splice(0).forEach(x=>x.release());assert.equal((await response).status,200,'real HTTP result preserved');for(const [name,values]of f.storage)if(name.includes('field-doc-'))assert.ok(!values.has('https://fmonitor.test/pilot/objects/101/checklist'),'late pre-logout response not privately cached');
});
assert.deepEqual(failures,[],'PRODUCTION-PERFORMANCE-001 current worker public contract');
