// RELIABLE-FIELD-OFFLINE-001 A3: public session/Store fault boundaries.
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import {webcrypto as crypto} from 'node:crypto';
const asset=name=>fs.readFileSync(new URL('../../app/YiiRuntime/Assets/'+name,import.meta.url),'utf8');
const source=asset('field-offline.js'),storeSource=asset('field-offline-store.js');
const payload={sectionId:1,mime:'image/png',size:3,sha256:'a'.repeat(64),originalName:'retained.png'},blob=new Blob(['png'],{type:'image/png'});
function fixture(){
 let actorListener,snapshot,closed=0,readsFail=false,urlFail=false,readGate=null,durableRows=[],write=async()=>{throw new DOMException('quota','QuotaExceededError')},calls=0;
 const events=new EventTarget(),doc=new EventTarget(),revoked=[],created=[];
 const store={open:async()=>{},deviceId:async()=>'device',scopeFor:(u,d,o)=>`${u}:${d}:${o}`,actorState:async()=>({deviceInstallationId:'device',activeUserId:1,epoch:1}),migrateLegacy:async()=>{},projection:async()=>({revision:0,items:{},photos:[],completedSections:{}}),list:async()=>{if(readsFail)throw Error('post-commit read failed');if(readGate){const held=readGate;readGate=null;return held}return durableRows},onActorChange:fn=>{actorListener=fn;return()=>{}},enqueue:async args=>{calls++;return write(args)},enqueueBatch:async args=>{calls++;return write(args)}};
 const context={Blob,DOMException,TypeError,Error,Promise,Map,Set,Number,Date,Math,JSON,Object,Array,String,structuredClone,crypto,indexedDB:{},navigator:{onLine:true,storage:{}},document:doc,URL:{createObjectURL:()=>{if(urlFail)throw Error('URL presentation unavailable');const url=`blob:${crypto.randomUUID()}`;created.push(url);return url},revokeObjectURL:url=>revoked.push(url)},queueMicrotask,setTimeout,clearTimeout,addEventListener:events.addEventListener.bind(events),removeEventListener:events.removeEventListener.bind(events),FMonitorFieldStore:store,FMonitorFieldPhoto:{},FMonitorFieldSync:{create:()=>({sync:async()=>{},retry:async()=>{},close:()=>{}})}};
 vm.runInNewContext(source,context);
 return {api:context.FMonitorField,revoked,created,get snapshot(){return snapshot},get calls(){return calls},get closed(){return closed},set write(fn){write=fn},holdNextRead(p){readGate=p},setRows(rows){durableRows=rows},failReads(){readsFail=true},failUrls(){urlFail=true},changeActor(){actorListener({deviceInstallationId:'device',activeUserId:2,epoch:2})},warns(){const e=new Event('beforeunload',{cancelable:true});events.dispatchEvent(e);return e.defaultPrevented},async open(){return context.FMonitorField.open({userId:1,objectId:1,onChange:s=>{snapshot=s},onClose:()=>{closed++}})}};
}
function storeBoundary(mode){
 let db;
 const indexedDB={open(){const opening={};queueMicrotask(()=>{
  if(mode==='open'){opening.onerror();return;}
  if(mode==='blocked-open'){opening.onblocked();return;}
  db={close(){},transaction(){const tx={abort(){queueMicrotask(()=>tx.onabort())},objectStore(){return{get(){const request={};queueMicrotask(()=>request.onerror());return request}}}};if(mode==='transaction')queueMicrotask(()=>tx.onabort());if(mode==='blocked-write')queueMicrotask(()=>tx.oncomplete());return tx}};opening.result=db;opening.onsuccess();
 });return opening}};
 const context={indexedDB,crypto,structuredClone,DOMException,Error,TypeError,Map,Set,Promise,Object,Array,Number,String,Date,JSON,queueMicrotask};vm.runInNewContext(storeSource,context);return{api:context.FMonitorFieldStore,versionChange(){db.onversionchange()}};
}
const results=[];
async function check(name,run){try{await run();results.push({name,pass:true})}catch(error){results.push({name,pass:false,error:error.message})}}
await check('ten concurrent reservations; eleventh refused; honest saving before failure',async()=>{
 const f=fixture(),s=await f.open(),pending=[];f.write=()=>new Promise((resolve,reject)=>pending.push({resolve,reject}));const promises=Array.from({length:11},()=>s.enqueue('photo_uploaded',payload,blob).then(()=>null,e=>e));await Promise.resolve();await Promise.resolve();
 const before={calls:f.calls,count:f.api.unsavedCount(1),connection:f.snapshot.connection,warns:f.warns()};for(const p of pending)p.reject(new DOMException('quota','QuotaExceededError'));const errors=await Promise.all(promises);const after=f.snapshot.unsavedPhotos;f.api.closeAll();
 assert.equal(before.calls,10);assert.equal(before.count,10);assert(before.warns);assert.notEqual(before.connection.state,'storage_recovery','reservations must not claim a storage failure');assert.notEqual(before.connection.state,'online');assert.match(before.connection.message,/сохраняем|сохранени/i);assert.equal(errors.filter(Boolean).length,11);assert.equal(after.length,10);assert(after.every(p=>f.revoked.includes(p.previewUrl)));assert.equal(f.api.unsavedCount(1),0);assert(!f.warns());assert.equal(f.closed,1);
});
for(const kind of ['initial','retry','batch'])await check(kind+' commit remains truthful after refresh failure',async()=>{
 const f=fixture(),s=await f.open();let id;
 if(kind==='retry'){await assert.rejects(s.enqueue('photo_uploaded',payload,blob));id=f.snapshot.unsavedPhotos[0].id;assert.equal(s.exportUnsavedPhoto(id).blob,blob)}
 const committed={clientOperationId:'committed',type:kind==='batch'?'item_completed':'photo_uploaded',status:'queued',...payload};f.write=async()=>{f.failReads();return kind==='batch'?[committed]:committed};
 const saved=kind==='retry'?await s.retryUnsavedPhoto(id):kind==='batch'?await s.enqueueBatch('item_completed',[{sectionId:1,itemId:28}]):await s.enqueue('photo_uploaded',payload,blob);
 assert.equal((Array.isArray(saved)?saved[0]:saved).clientOperationId,'committed');assert.equal(f.api.unsavedCount(1),0);assert.equal(f.snapshot.operations.filter(op=>op.clientOperationId==='committed').length,1,'known committed intention must survive a failed read');assert.notEqual(f.snapshot.connection.state,'online','pending durable intention is not synchronized');if(id){await s.retryUnsavedPhoto(id).catch(()=>{});assert.equal(f.calls,2)}f.api.closeAll();
});
for(const kind of ['initial','retry','batch'])await check(kind+' older successful read cannot erase a later known commit',async()=>{
 const f=fixture(),s=await f.open();let id;
 if(kind==='retry'){await assert.rejects(s.enqueue('photo_uploaded',payload,blob));id=f.snapshot.unsavedPhotos[0].id}
 let release;f.holdNextRead(new Promise(resolve=>release=resolve));const previous=s.list();
 const committed={clientOperationId:'newer-commit',type:kind==='batch'?'item_completed':'photo_uploaded',status:'queued',...payload};
 f.write=async()=>{f.setRows([committed]);return kind==='batch'?[committed]:committed};
 const saving=kind==='retry'?s.retryUnsavedPhoto(id):kind==='batch'?s.enqueueBatch('item_completed',[{sectionId:1,itemId:28}]):s.enqueue('photo_uploaded',payload,blob);
 await Promise.resolve();await Promise.resolve();release([]);await Promise.all([previous,saving]);
 assert.equal(f.snapshot.operations.filter(op=>op.clientOperationId==='newer-commit').length,1,'older read cannot erase a known later commit');assert.notEqual(f.snapshot.connection.state,'online','older read cannot claim synchronization');f.api.closeAll();
});
await check('a pending refresh preserves the current usable preview until replacement',async()=>{
 const f=fixture(),s=await f.open();f.setRows([{clientOperationId:'old-photo',status:'queued',type:'photo_uploaded',savedBlob:blob,...payload}]);await s.list();const current=f.snapshot.operations[0].previewUrl;assert(current);
 let release;f.holdNextRead(new Promise(resolve=>release=resolve));const reading=s.list();const revokedBeforeReplacement=f.revoked.includes(current);release([]);await reading;f.api.closeAll();assert(!revokedBeforeReplacement,'in-flight read cannot revoke the currently displayed preview');
});
await check('closing during a held read cannot leak late private Blob URLs',async()=>{
 const f=fixture(),s=await f.open();let release;f.holdNextRead(new Promise(resolve=>release=resolve));const reading=s.list();f.api.closeAll();release([{clientOperationId:'late-photo',status:'queued',type:'photo_uploaded',savedBlob:blob,...payload}]);await reading;assert(f.created.every(url=>f.revoked.includes(url)),'closed session must revoke any late read URLs');assert.equal(f.snapshot.operations.length,0,'closed session never emits late private rows');
});
await check('late storage failure cannot resurrect changed actor memory',async()=>{
 const f=fixture(),s=await f.open();let reject;f.write=()=>new Promise((_,r)=>{reject=r});const attempt=s.enqueue('photo_uploaded',payload,blob).catch(e=>e);await Promise.resolve();f.changeActor();reject(new DOMException('quota','QuotaExceededError'));await attempt;assert.equal(f.api.unsavedCount(1),0);assert.equal(f.closed,1);assert.equal(f.snapshot.unsavedPhotos.length,0);
});
await check('domain/type/program errors are never storage failures, regardless of message',async()=>{
 for(const error of [Error('quota calculation program bug'),new TypeError('IndexedDB payload bug'),Object.assign(Error('transaction aborted'),{code:'ACTOR_CHANGED'}),Object.assign(Error('database unavailable'),{code:'PHOTO_LIMIT_REACHED'}),Object.assign(Error('IndexedDB program bug'),{name:'QuotaExceededError'})]){
  const f=fixture(),s=await f.open();f.write=async()=>{throw error};await assert.rejects(s.enqueue('photo_uploaded',payload,blob));const count=f.api.unsavedCount(1);f.api.closeAll();assert.equal(count,0,error.code||error.name+': '+error.message);
 }
});
await check('URL creation failure cannot discard the prepared recovery Blob',async()=>{
 const f=fixture(),s=await f.open();f.failUrls();await assert.rejects(s.enqueue('photo_uploaded',payload,blob));assert.equal(f.api.unsavedCount(1),1);const photo=f.snapshot.unsavedPhotos[0];assert(photo);const copy=s.exportUnsavedPhoto(photo.id);assert.equal(copy.blob,blob);assert.equal(copy.name,payload.originalName);f.api.closeAll();
});
for(const [mode,code]of [['open','LOCAL_STORAGE_OPEN_FAILED'],['blocked-open','LOCAL_STORAGE_BLOCKED'],['transaction','LOCAL_STORAGE_TRANSACTION_ABORTED'],['request','LOCAL_STORAGE_REQUEST_FAILED'],['blocked-write','LOCAL_STORAGE_BLOCKED']])await check('actual Store '+mode+' fallback has explicit storage code',async()=>{
 const f=storeBoundary(mode);const action=async()=>{if(mode==='open'||mode==='blocked-open')return f.api.open();if(mode==='request')return f.api.deviceId();if(mode==='blocked-write'){await f.api.open();const pending=f.api.transaction(['meta'],'readwrite',async()=>{});f.versionChange();return pending}return f.api.transaction(['meta'],'readwrite',async()=>{})};await assert.rejects(action(),error=>error.code===code);
});
for(const result of results)console.log(JSON.stringify(result));
assert.equal(results.filter(r=>!r.pass).length,0,'INTENDED_RED A3 recovery state/IDB-boundary invariants');
console.log('PASS: RELIABLE-FIELD-OFFLINE-001 recovery '+results.length+' cases');
