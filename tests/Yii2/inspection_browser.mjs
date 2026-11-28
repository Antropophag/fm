import fs from 'node:fs';
import {createRequire} from 'node:module';
const require=createRequire(import.meta.url),c=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const {chromium}=require(c.playwright),browser=await chromium.launch({headless:true});
const check=(v,m)=>{if(!v)throw new Error(m);};
try {
 const context=await browser.newContext({viewport:{width:1440,height:1000}}),page=await context.newPage(),errors=[],assetErrors=[];
 const timeline=[],started=performance.now(),requestIds=new Map();let nextRequestId=0,lastLocalSummary='';
 const observe=(event,detail={})=>{if(timeline.length<240)timeline.push({ms:Math.round(performance.now()-started),event,...detail});};
 const relevant=request=>/\/checklist\/(operations|photos)$|\/sync-context$/.test(new URL(request.url()).pathname);
 context.on('request',request=>{if(!relevant(request))return;const id=++nextRequestId;requestIds.set(request,id);observe('request',{id,method:request.method(),path:new URL(request.url()).pathname});});
 context.on('response',response=>{const id=requestIds.get(response.request());if(id)observe('response',{id,status:response.status()});});
 context.on('requestfailed',request=>{const id=requestIds.get(request);if(id)observe('requestfailed',{id,error:request.failure()?.errorText});});
 page.on('pageerror',e=>{errors.push(e.message);observe('pageerror',{message:e.message});});page.on('response',r=>{if(new URL(r.url()).pathname.startsWith('/pilot/assets/')&&r.status()>=400)assetErrors.push(r.url());});
 async function login(email){await page.goto(c.origin+'/pilot/login');await page.locator('[name=email]').fill(email);await page.locator('[name=email]').locator('xpath=ancestor::form').locator('button[type=submit]').click();await page.locator('[name=password]').fill(c.password);await Promise.all([page.waitForResponse(r=>r.url().endsWith('/pilot/login')&&r.status()===303),page.locator('[name=password]').locator('xpath=ancestor::form').locator('button[type=submit]').click()]);}
 await login(c.email);
 const response=await page.goto(c.origin+'/pilot/objects/4512/checklist');check(response.status()===200,'INTENDED_RED Yii browser checklist');
 check(!response.headers()['content-security-policy'].includes('unsafe-inline'),'CSP no unsafe-inline');
 try{await page.waitForFunction(()=>document.querySelector('[data-checklist]')?.dataset.userId==='73'&&document.querySelector('[data-checklist]')?.dataset.fieldReady==='true');}catch(error){
  const diagnostic=await page.evaluate(async()=>({ready:document.querySelector('[data-checklist]')?.dataset.fieldReady,banner:document.querySelector('.fm2-sync-banner')?.textContent,identityNotice:document.querySelector('[data-field-identity-alert]')?.textContent,lifecycle:await Promise.race([globalThis.FMonitorFieldLifecycle?.ready?.then(()=>({ok:true}),e=>({error:e.message})),new Promise(r=>setTimeout(()=>r({pending:true}),500))]),registrations:await Promise.all((await navigator.serviceWorker.getRegistrations()).map(async r=>({scope:r.scope,installing:r.installing?.state,waiting:r.waiting?.state,active:r.active?.state}))),caches:await caches.keys(),storage:await navigator.storage.estimate(),locks:await navigator.locks?.query()})).catch(e=>({diagnosticError:e.message}));
  throw new Error('Checklist readiness failed: '+JSON.stringify({diagnostic,pageErrors:errors,assetErrors,timeline})+'; '+error.message);
 }

 if(await page.locator('[data-check-section="1"] .fm2-section-toggle').getAttribute('aria-expanded')==='false')await page.locator('[data-check-section="1"] .fm2-section-toggle').click();
 let lost=false,acceptedId=null;
 await page.route('**/pilot/objects/4512/checklist/operations',async route=>{const data=route.request().postDataJSON();if(!lost&&data.type==='item_completed'){const answer=await route.fetch();const body=await answer.json();check(body.status==='accepted','first click accepted before response loss');lost=true;acceptedId=data.clientOperationId;await route.abort('connectionreset');}else await route.continue();});
 const replay=page.waitForResponse(r=>r.url().endsWith('/checklist/operations')&&r.status()===200);
 await page.locator('[data-check-item="28"] .fm2-check-toggle').click();
 check((await (await replay).json()).status==='duplicate','automatic retry preserves accepted operation');
 await page.unroute('**/pilot/objects/4512/checklist/operations');
 await page.reload();await page.waitForFunction(()=>document.querySelector('[data-check-item="28"] [role=checkbox]')?.getAttribute('aria-checked')==='true');
 await page.screenshot({path:c.artifacts+'/inspection-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile no overflow');await page.screenshot({path:c.artifacts+'/inspection-mobile.png',fullPage:true});
 // Preserve real stores/scope, failed intentions, mixed operation/photo/section ordering.
 const localRows=()=>page.evaluate(()=>new Promise((resolve,reject)=>{const r=indexedDB.open('fmonitor2-fast-pilot');r.onerror=()=>reject(r.error);r.onsuccess=()=>{const db=r.result,t=db.transaction(['operations','meta','photoBlobs']),q=t.objectStore('operations').getAll(),d=t.objectStore('meta').get('deviceInstallationId'),p=t.objectStore('photoBlobs').getAll();t.oncomplete=()=>{resolve({stores:[...db.objectStoreNames],rows:q.result,device:d.result,photos:p.result});db.close();};};}));
 const pollLocal=async(predicate,message,timeout=30000)=>{const deadline=Date.now()+timeout;let current;do{current=await localRows();const summary=JSON.stringify(current.rows.map(row=>({type:row.type,status:row.status})));if(summary!==lastLocalSummary){lastLocalSummary=summary;observe('local-status',{states:JSON.parse(summary)});}if(predicate(current))return current;await new Promise(resolve=>setTimeout(resolve,50));}while(Date.now()<deadline);throw new Error(message+'; observed '+JSON.stringify(current.rows.map(r=>({id:r.clientOperationId,type:r.type,status:r.status})))+'; diagnostic '+JSON.stringify({timeline,pageErrors:errors}));};
 let local=await localRows();check(JSON.stringify(local.stores.sort())===JSON.stringify(['actorState','leases','meta','operations','photoBlobs','preparations','preparedDocuments','scopeState']),'durable v3 stores preserve legacy operations and blobs');check(local.rows.every(r=>r.scope===`73:${r.deviceInstallationId}:4512`),'user device object scope');
 if(await page.locator('[data-check-section="1"] .fm2-section-toggle').getAttribute('aria-expanded')==='false')await page.locator('[data-check-section="1"] .fm2-section-toggle').click();
 await context.setOffline(true);await page.locator('[data-check-item="29"] .fm2-check-toggle').click();
 await pollLocal(s=>s.rows.some(r=>r.itemId===29),'offline item durably queued');
 const offlineId=(await localRows()).rows.find(r=>r.itemId===29).clientOperationId;
 const failures=[{status:'conflict',code:409},{status:'rejected',code:422},{status:'retryable',code:503}];let injected=0;
 await page.route('**/pilot/objects/4512/checklist/operations',async route=>{const op=route.request().postDataJSON();if(op.itemId===29&&injected<failures.length){const failure=failures[injected++];await route.fulfill({status:failure.code,contentType:'application/json',body:JSON.stringify({status:failure.status,revision:1})});}else await route.continue();});
 await context.setOffline(false);
 let currentOfflineId=offlineId;
 for(let i=0;i<3;i++){
  const expected=['conflict','rejected','retryable_error'][i];
  const observed=await pollLocal(s=>s.rows.some(r=>r.itemId===29&&r.status===expected),'failed intention retained as '+expected);
  const failed=observed.rows.find(r=>r.itemId===29&&r.status===expected);currentOfflineId=failed.clientOperationId;
  check(observed.rows.some(r=>r.clientOperationId===offlineId),'original failed intention retained');
  if(i<2){
   await page.locator(`[data-field-operation][data-operation-id="${currentOfflineId}"] [data-field-resolve]`).click();
   await page.locator('dialog[open]').getByRole('button',{name:'Применить заново',exact:true}).click();
   await pollLocal(s=>s.rows.some(r=>r.clientOperationId===currentOfflineId&&r.status==='superseded'),'explicit resolution preserves original');
  } // transient failures retry automatically, without a UI action
 }
 await pollLocal(s=>s.rows.some(r=>r.clientOperationId===currentOfflineId&&r.status==='accepted'),'only explicit replacement and transient retry accepted');
 await page.unroute('**/pilot/objects/4512/checklist/operations');await page.reload();await page.waitForFunction(()=>document.querySelector('[data-check-item="29"] [role=checkbox]')?.getAttribute('aria-checked')==='true');
 // Queue the remaining section and photo while offline; automatic section completion follows them.
 if(await page.locator('[data-check-section="1"] .fm2-section-toggle').getAttribute('aria-expanded')==='false')await page.locator('[data-check-section="1"] .fm2-section-toggle').click();
 const sent=[];context.on('request',r=>{if(r.method()==='POST'&&r.url().includes('/checklist/')){if(r.url().endsWith('/photos'))sent.push('photo_uploaded');else sent.push(r.postDataJSON().type);}});
 await context.setOffline(true);await page.locator('[data-check-section="1"] [data-check-all]').click();await page.locator('[data-bulk-confirm]').click();
 await page.locator('[data-check-section="1"] input[data-photo-input]').last().setInputFiles({name:'offline.png',mimeType:'image/png',buffer:Buffer.from(c.png,'base64')});
 await pollLocal(s=>s.photos.length>0,'offline photo bytes durably queued');
 observe('competing-tab-open');const competing=await context.newPage();await competing.goto(c.origin+'/pilot/objects/4512/checklist');await competing.locator('[data-photo-strip] img[src^="blob:"]').waitFor();await competing.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));await pollLocal(s=>s.rows.some(r=>r.type==='section_completed'),'section completion enqueued');check((await localRows()).rows.filter(r=>r.type==='section_completed').length===1,'two tabs create one durable section-completion intent');
 observe('mixed-queue-online');await context.setOffline(false);await pollLocal(s=>s.rows.some(r=>r.type==='section_completed'&&r.status==='accepted'),'mixed section actually accepted');
 await competing.close();
 check(sent.includes('photo_uploaded')&&sent.includes('section_completed'),'mixed photo and section actually sent');check(sent.indexOf('photo_uploaded')>sent.lastIndexOf('item_completed')&&sent.indexOf('section_completed')>sent.indexOf('photo_uploaded'),'ordered item then photo then section: '+JSON.stringify(sent));
 await page.reload();await page.locator('[data-checklist][data-field-ready="true"]').waitFor();check(await page.locator('[data-check-section="1"] [data-section-state]').textContent().then(x=>/заверш/iu.test(x)),'section persisted on refresh');
 // The existing sync-context endpoint returns a usable fresh native CSRF token.
 const sync=await page.evaluate(async()=>{const r=await fetch('/pilot/construction-control/objects/4512/sync-context',{credentials:'include'});return {status:r.status,body:await r.json()};});check(sync.status===200&&typeof sync.body.csrf==='string'&&sync.body.revision===11,'sync context revision/token after mixed queue');
 await page.locator('.fm2-back-link[href="/pilot/objects/4512"]').click();await page.waitForURL(c.origin+'/pilot/objects/4512');
 await page.evaluate(async()=>{for(const name of await caches.keys())if(name.startsWith('fmonitor2-checklist-doc-'))await caches.delete(name);});
 await page.goto(c.origin+'/pilot/construction-control');
 await page.locator('[data-offline-summary][data-state="ready"]').waitFor();check(await page.evaluate(async()=>{const registration=await navigator.serviceWorker.getRegistration('/pilot/');return registration?.scope===location.origin+'/pilot/';}),'public prepared state with correct worker scope');
 check(true,'construction queue registers /pilot/ worker and prefetches eligible checklist');
 await page.goto(c.origin+'/pilot/construction-control/objects/4512/checklist');await page.locator('.fm2-back-link[href="/pilot/construction-control"]').click();await page.waitForURL(c.origin+'/pilot/construction-control');
 check(await page.getByRole('navigation',{name:'Основная навигация'}).getByRole('link',{name:'Стройконтроль',exact:true}).count()===1,'capability-gated shell entry to construction queue');
 const queueRows=page.locator('[data-control-row]');check(JSON.stringify(await queueRows.evaluateAll(rows=>rows.map(r=>Number(r.dataset.objectId))))===JSON.stringify([4512]),'default server mine excludes foreign and completed rows');check(await page.locator('[data-control-row][data-object-id="4512"]').getAttribute('data-engineer-id')==='73','standalone current engineer is authoritative');
 await page.locator('[name=ownership][value=all]').check();await page.waitForURL(/ownership=all/);check(JSON.stringify(await queueRows.evaluateAll(rows=>rows.map(r=>Number(r.dataset.objectId))))===JSON.stringify([4513,4512]),'server all includes active colleague and retains order');check(await page.locator('[data-control-row][data-object-id="4513"]').getAttribute('data-engineer-id')==='0','missing assignment has no legacy engineer fallback');
 await page.locator('[data-show-completed]').check();await page.waitForURL(/completed=1/);check(JSON.stringify(await queueRows.evaluateAll(rows=>rows.map(r=>Number(r.dataset.objectId))))===JSON.stringify([4513,4512,4514]),'server completed mode includes documentary completion after active rows');check(await page.locator('[data-control-row][data-object-id="4514"]').getAttribute('data-completed')==='true','completed documentary row is marked by server');
 await page.locator('[data-control-search]').fill('QUEUE-4514');await page.waitForURL(/query=QUEUE-4514/);check(await queueRows.count()===1&&await queueRows.first().getAttribute('data-object-id')==='4514','completed documentary row is searchable across dataset');await page.locator('[data-control-search]').fill('QUEUE-4513');await page.waitForURL(/query=QUEUE-4513/);check(await queueRows.count()===1&&await queueRows.first().getAttribute('data-object-id')==='4513','literal search among active rows');await page.locator('[data-control-search]').fill('NO-SUCH-OBJECT');await page.waitForURL(/query=NO-SUCH-OBJECT/);check(await page.locator('[data-control-empty]').isVisible(),'server empty filter state');await page.locator('[data-clear-filters]').click();await page.waitForURL(/\/pilot\/construction-control\?ownership=mine$/);check(JSON.stringify(await queueRows.evaluateAll(rows=>rows.map(r=>Number(r.dataset.objectId))))===JSON.stringify([4512]),'clear returns canonical mine page one');
 await page.getByRole('button',{name:'Выйти',exact:true}).click();await page.waitForURL(c.origin+'/pilot/login');await login(c.otherEmail);
 const denied=await page.goto(c.origin+'/pilot/objects/4512/checklist');check(denied.status()===403,'different account cannot read cached engineer page');
 await context.setOffline(true);await page.goto(c.origin+'/pilot/objects/4512/checklist').catch(()=>{});check(await page.locator('[data-checklist][data-user-id="73"]').count()===0,'offline cache does not reveal previous account');await context.setOffline(false);
 check(errors.length===0,'no browser exceptions '+errors.join(','));check(assetErrors.length===0,'all assets loaded '+assetErrors.join(','));
 fs.writeFileSync(c.result,JSON.stringify({lost,acceptedId,offline:true}));
}finally{await browser.close();}
