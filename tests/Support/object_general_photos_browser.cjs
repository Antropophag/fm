const fs=require('node:fs'),assert=require('node:assert/strict');
const c=JSON.parse(fs.readFileSync(process.argv[2],'utf8')),{chromium}=require(c.playwright);
const wait=ms=>new Promise(r=>setTimeout(r,ms));
async function rows(p){return p.evaluate(()=>new Promise((resolve,reject)=>{const q=indexedDB.open('fmonitor2-fast-pilot');q.onerror=()=>reject(q.error);q.onsuccess=()=>{const db=q.result;const t=db.transaction(['operations','photoBlobs']);const o=t.objectStore('operations').getAll(),b=t.objectStore('photoBlobs').getAll();t.oncomplete=async()=>{db.close();try{resolve({operations:o.result,blobs:await Promise.all(b.result.map(async({blob,...metadata})=>({...metadata,size:blob.size,type:blob.type,bytes:Array.from(new Uint8Array(await blob.arrayBuffer()))})))});}catch(error){reject(error);}};};}));}
async function until(p,predicate){let value;for(let n=0;n<240;n++){value=await rows(p);if(predicate(value))return value;await wait(50);}throw Error('queue condition failed '+JSON.stringify(value));}
(async()=>{
 const browser=await chromium.launch({headless:true}),context=await browser.newContext({viewport:{width:393,height:852}}),page=await context.newPage();page.setDefaultTimeout(10000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.addInitScript(()=>{
   globalThis.generalQuota=sessionStorage.getItem('general-quota-allowed')!=='1';
   const put=IDBObjectStore.prototype.put;
   IDBObjectStore.prototype.put=function(value,...args){const request=put.call(this,value,...args);if(globalThis.generalQuota&&this.name==='operations'&&value?.type==='photo_uploaded'&&value?.sectionId===0){const tx=this.transaction;request.addEventListener('success',()=>tx.abort(),{once:true});}return request;};
  });
  await page.goto(c.origin+'/pilot/login');await page.locator('[name=email]').fill(c.email);await page.locator('button[type=submit]').click();await page.locator('[name=password]').fill(c.password);await page.locator('button[type=submit]').click();await page.waitForURL(u=>!u.pathname.includes('/login'));
  await page.goto(c.origin+'/pilot/objects/4512/checklist');await page.locator('[data-checklist][data-field-ready="true"]').waitFor();
  const general=page.locator('[data-general-photos]');assert.equal(await general.count(),1,'INTENDED_RED general photo controls exist');
  assert.equal(await general.getByLabel('Снять фото объекта',{exact:true}).count(),1,'full camera accessible name');assert.equal(await general.getByLabel('Выбрать фото объекта',{exact:true}).count(),1,'full picker accessible name');assert.equal(await page.getByRole('button',{name:/^Все фото/}).count(),1,'gallery accessible name');
  assert.equal(await general.locator('[data-photo-input][capture="environment"]').count(),1,'camera offered');assert.equal(await general.locator('[data-photo-input][multiple]').count(),1,'file picker offered');
  await page.evaluate(()=>Promise.race([navigator.serviceWorker.ready,new Promise((_,reject)=>setTimeout(()=>reject(Error('worker timeout')),10000))]));await page.reload();await page.locator('[data-checklist][data-field-ready="true"]').waitFor();
  await context.setOffline(true);
  await general.locator('[data-photo-input][multiple]').setInputFiles({name:'общий-вид.png',mimeType:'image/png',buffer:Buffer.from(c.png,'base64')});
  const unsaved=general.locator('[data-unsaved-photo]');await unsaved.waitFor();
  const failed=await rows(page);assert.equal(failed.operations.length,0,'failed general write is not queued');assert.equal(failed.blobs.length,0,'failed general transaction leaves no blob');assert(await unsaved.locator('[data-unsaved-download]').isVisible(),'general unsaved bytes downloadable');
  await page.evaluate(()=>{globalThis.generalQuota=false;sessionStorage.setItem('general-quota-allowed','1');});await unsaved.locator('[data-unsaved-retry]').click();
  const queued=await until(page,s=>s.operations.some(o=>o.type==='photo_uploaded'&&o.sectionId===0)&&s.blobs.length===1),op=queued.operations.find(o=>o.type==='photo_uploaded');
  assert.equal(op.sectionId,0);assert.equal(await page.locator('[data-total-progress]').innerText(),'0');assert.equal(await page.locator('[data-check-section] [data-photo-key]').count(),0,'general photo is outside section strips');
  await general.locator('[data-photo-key]').waitFor();
  for(const width of [320,393,1366]){await page.setViewportSize({width,height:852});await general.scrollIntoViewIfNeeded();assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'general actions fit '+width);await page.screenshot({path:c.artifacts+'/general-photos-'+width+'.png'});}
  await page.locator('[data-open-gallery]').click();await page.locator('[data-gallery] img').waitFor();assert.equal(await page.locator('[data-gallery] img').count(),1,'general photo in all photos');await page.locator('[data-close-gallery]').click();
  await page.reload();await page.locator('[data-checklist][data-field-ready="true"]').waitFor();const restored=await until(page,s=>s.operations.some(o=>o.clientOperationId===op.clientOperationId)&&s.blobs.length===1);assert.equal(restored.operations.find(o=>o.clientOperationId===op.clientOperationId).sectionId,0,'offline reload preserves general target');await general.locator('[data-photo-key]').waitFor();
  await context.setOffline(false);await until(page,s=>s.operations.some(o=>o.clientOperationId===op.clientOperationId&&o.status==='accepted'));
  const projection=await page.evaluate(()=>fetch('/pilot/construction-control/objects/4512/sync-context').then(r=>r.json()));assert.equal(projection.projection.photos.length,1);assert.equal(projection.projection.photos[0].sectionId,0);assert.equal(Object.keys(projection.projection.items).length,0);assert.equal(Object.keys(projection.projection.completedSections).length,0);
  await page.reload();await page.locator('[data-checklist][data-field-ready="true"]').waitFor();assert.equal(await general.locator('[data-photo-key]').count(),1,'accepted general photo reloads');assert.deepEqual(errors,[]);
  // Accepted general photo revoke uses the existing public reason workflow.
  await general.getByRole('button',{name:/Отозвать фото/}).click();
  await page.locator('[data-reason-input]').fill('Ошибочный общий снимок');await page.locator('[data-reason-confirm]').click();
  await until(page,s=>s.operations.some(o=>o.type==='photo_revoked'&&o.sectionId===0&&o.status==='accepted'));
  await page.reload();await page.locator('[data-checklist][data-field-ready="true"]').waitFor();assert.equal(await general.locator('[data-photo-key]').count(),0,'revoked general photo stays absent after reload');assert.equal(await page.locator('[data-photo-total]').innerText(),'0','revoked photo not counted in gallery');
  // Fill the general cap with distinct valid bytes, including one multi-file selection.
  await context.setOffline(true);
  const file=async(name,seed)=>({name,mimeType:'image/png',buffer:Buffer.from(await page.evaluate(seed=>{const cv=document.createElement('canvas');cv.width=4;cv.height=4;cv.getContext('2d').fillStyle=`rgb(${seed},70,90)`;cv.getContext('2d').fillRect(0,0,4,4);return cv.toDataURL('image/png').split(',')[1];},seed),'base64')});
  const files=[];for(let n=0;n<11;n++)files.push(await file('general-cap-'+n+'.png',30+n));assert.equal(new Set(files.map(x=>require('node:crypto').createHash('sha256').update(x.buffer).digest('hex'))).size,11,'cap uses distinct hashes');
  await general.locator('[data-photo-input][multiple]').setInputFiles(files.slice(0,2));await until(page,s=>s.blobs.length===2);
  for(let n=2;n<10;n++){await general.locator('[data-photo-input][multiple]').setInputFiles(files[n]);await until(page,s=>s.blobs.length===n+1);}
  await general.locator('[data-photo-input][multiple]').setInputFiles(files[10]);
  await page.locator('[data-toast][data-state="error"]:visible').waitFor();assert.equal((await rows(page)).blobs.length,10,'general cap prevents eleventh reserved photo');assert.equal(await general.locator('[data-unsaved-photo]').count(),0,'general cap is not storage failure');
  const discardId=(await rows(page)).operations.find(o=>o.originalName==='general-cap-0.png').clientOperationId;
  const guardedDiscard=async(id,kind,depth=0)=>page.evaluate(async({id,kind,depth})=>{
   const store=FMonitorFieldStore,device=await store.deviceId(),state=await store.actorState(device),actor={deviceInstallationId:device,activeUserId:state.activeUserId,epoch:state.epoch};
   const scope=store.scopeFor(73,device,4512);let original;
   const update=async(transform)=>new Promise((resolve,reject)=>{const q=indexedDB.open('fmonitor2-fast-pilot');q.onsuccess=()=>{const db=q.result,t=db.transaction('operations','readwrite'),o=t.objectStore('operations'),r=o.getAll();r.onsuccess=()=>transform(r.result,o);t.oncomplete=()=>{db.close();resolve();};t.onabort=()=>{db.close();reject(t.error);};};});
   if(kind==='actor')actor.epoch++;
   else await update((all,o)=>{let row=all.find(x=>x.clientOperationId===id);for(let n=0;n<depth;n++)row=all.find(x=>x.localPredecessorId===row.clientOperationId);if(!row)throw Error('guard fixture missing descendant');original=structuredClone(row);if(kind==='attempted')row.attempted=true;else row.wire={clientOperationId:row.clientOperationId,sectionId:row.sectionId};o.put(row);});
   let rejected=false;try{await store.discard(scope,id,actor);}catch{rejected=true;}
   finally{if(original)await update((all,o)=>o.put(original));}
   return rejected;
  },{id,kind,depth});
  const prior=await rows(page);
  for(const [kind,depth] of [['actor',0],['attempted',0],['attempted',1],['attempted',3]]){
   assert(await guardedDiscard(discardId,kind,depth),`GENERAL_DISCARD_CONTRACT ${kind} depth ${depth} rejected`);
   assert.deepEqual(await rows(page),prior,`${kind} depth ${depth} rejection preserves every row and byte`);
  }

  await general.locator(`[data-field-photo-discard][data-operation-id="${discardId}"]`).click();assert.equal((await rows(page)).blobs.length,10,'general discard waits for confirmation');await page.locator('dialog[open] [data-confirm]').click();await until(page,s=>s.operations.some(o=>o.clientOperationId===discardId&&o.status==='discarded'));
  const afterDiscard=await rows(page);assert.equal(afterDiscard.blobs.length,9,'GENERAL_DISCARD_CONTRACT deleting one general photo preserves every unrelated blob');
  const removed=prior.operations.find(o=>o.clientOperationId===discardId);
  const expected=structuredClone(prior);
  expected.blobs=expected.blobs.filter(b=>b.id!==removed.id);
  for(const row of expected.operations){
   if(row.clientOperationId===discardId){row.status='discarded';row.updatedAt=afterDiscard.operations.find(o=>o.clientOperationId===discardId).updatedAt;assert(Number.isFinite(row.updatedAt)&&row.updatedAt>=removed.updatedAt,'discard timestamp advances');}
   else if(row.localPredecessorId===discardId)row.localPredecessorId=removed.localPredecessorId;
  }
  assert.deepEqual(afterDiscard,expected,'GENERAL_DISCARD_CONTRACT only selected blob/status and direct predecessor links change; all operation fields, ordering and blob bytes are preserved');
  await general.locator(`[data-photo-key="${discardId}"]`).waitFor({state:'detached'});assert.equal(await general.locator(`[data-photo-key="${discardId}"]`).count(),0,'confirmed general discard removes local preview');
  const next=afterDiscard.operations.find(o=>o.originalName==='general-cap-1.png');assert.equal(next.localPredecessorId,null,'removed first general photo splices serialization chain');
  for(const depth of [0,1,3]){const before=await rows(page);assert(await guardedDiscard(next.clientOperationId,'wire',depth),`GENERAL_DISCARD_CONTRACT frozen wire depth ${depth} rejects discard`);assert.deepEqual(await rows(page),before,`frozen wire depth ${depth} preserves all facts and bytes`);}

  await general.locator('[data-photo-input][multiple]').setInputFiles(files[10]);await until(page,s=>s.blobs.length===10);
  const section=page.locator('[data-check-section="1"]');if(await section.locator('.fm2-section-toggle').getAttribute('aria-expanded')==='false')await section.locator('.fm2-section-toggle').click();
  await section.locator('[data-photo-input]').last().setInputFiles({name:'separate-section.png',mimeType:'image/png',buffer:files[1].buffer});
  await until(page,s=>s.blobs.length===11);assert.equal(await section.locator('[data-photo-key]').count(),1,'general cap does not consume section capacity');
  // A real attempted upload may no longer be discarded as an unattempted photo.
  await page.route('**/checklist/photos',r=>r.abort('connectionreset'));await context.setOffline(false);
  const attempted=await until(page,s=>s.operations.some(o=>o.sectionId===0&&o.type==='photo_uploaded'&&o.status!=='accepted'&&o.attempted));await context.setOffline(true);
  const attemptedId=attempted.operations.find(o=>o.sectionId===0&&o.type==='photo_uploaded'&&o.status!=='accepted'&&o.attempted).clientOperationId;
  assert.equal(await general.locator(`[data-field-photo-discard][data-operation-id="${attemptedId}"]`).count(),0,'attempted general photo has no unsafe discard');
  const readerContext=await browser.newContext(),reader=await readerContext.newPage();
  await reader.goto(c.origin+'/pilot/login');await reader.locator('[name=email]').fill(c.readerEmail);await reader.locator('button[type=submit]').click();await reader.locator('[name=password]').fill(c.password);await reader.locator('button[type=submit]').click();await reader.waitForURL(u=>!u.pathname.includes('/login'));await reader.goto(c.origin+'/pilot/objects/4512/checklist');
  assert(await reader.getByLabel('Снять фото объекта',{exact:true}).isDisabled(),'reader camera disabled');assert(await reader.getByLabel('Выбрать фото объекта',{exact:true}).isDisabled(),'reader file picker disabled');await readerContext.close();assert.deepEqual(errors,[]);
  fs.writeFileSync(c.result,JSON.stringify({clientOperationId:op.clientOperationId}));console.log('PASS general photo offline roundtrip');
 }catch(e){await page.screenshot({path:c.artifacts+'/general-failure.png',fullPage:true}).catch(()=>{});throw e;}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
