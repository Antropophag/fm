/** OFFLINE-INDICATOR-001: native Chromium manifest + actual rendered preparation UI. */
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const c = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const {chromium} = require(process.env.FMONITOR_TEST_PLAYWRIGHT_MODULE || path.resolve(c.root, '../shlz-ui/node_modules/playwright'));
const origin = 'http://localhost:18743';
const results = [];
(async () => {
 const browser = await chromium.launch({headless:true});
 async function check(name, run) {try {await run();results.push({name,pass:true});}catch(e){results.push({name,pass:false,error:e.message});}console.log(JSON.stringify(results.at(-1)));}
 await check('same-origin manifest allowed, external manifest denied; other directives unchanged', async()=>{
  for(const [route,csp] of Object.entries(c.policies)){
   const directives=Object.fromEntries(csp.split(';').map(x=>x.trim().split(/\s+/)).map(([k,...v])=>[k,v.join(' ')]));
   assert.equal(directives['manifest-src'],"'self'",route);
   const checklist=route.endsWith('/checklist');
   const expected={'default-src':"'none'",'script-src':"'self'",'connect-src':"'self'",'style-src':"'self'",'img-src':checklist?"'self' blob:":"'self'",'font-src':"'self'",'form-action':"'self'",'base-uri':"'none'",'frame-ancestors':"'none'",'manifest-src':"'self'"};
   if(checklist)expected['worker-src']="'self'";
   assert.deepEqual(directives,expected);
  }
 });
 async function contextFor(mode, external=false, checklist=false) {
  const context=await browser.newContext({viewport:{width:390,height:844}});
  await context.route('**/*',async route=>{
   const url=new URL(route.request().url());
   if(url.pathname==='/pilot/construction-control'||url.pathname.endsWith('/checklist')){
    // The real Yii-rendered page; lifecycle/store dependencies replaced at their public ports below.
    let html=(url.pathname.endsWith('/checklist')?(mode==='readonly'?c.readOnlyHtml:c.checklistHtml):c.html).replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,'');
    if(external)html=html.replace('href="/pilot/assets/manifest.webmanifest"','href="https://external.invalid/app.webmanifest"');
    return route.fulfill({status:200,headers:{'content-type':'text/html','content-security-policy':c.policies[url.pathname.endsWith('/checklist')?'pilot/objects/4512/checklist':'pilot/construction-control']},body:html});
   }
   if(url.pathname.endsWith('/offline-manifest'))return route.fulfill({json:{userId:73,pages:1,page:1,total:2,setVersion:'fixture',items:[1,2].map(objectId=>({objectId,checklistUrl:`/pilot/objects/${objectId}/checklist`}))}});
   if(url.pathname.startsWith('/pilot/assets/')){
    const name=url.pathname.slice('/pilot/assets/'.length), file=path.join(c.root,'app/YiiRuntime/Assets',name);
    if(fs.existsSync(file))return route.fulfill({path:file,contentType:name.endsWith('.css')?'text/css':name.endsWith('.webmanifest')?'application/manifest+json':name.endsWith('.png')?'image/png':undefined});
   }
   return route.fulfill({status:404,body:''});
  });
  await context.addInitScript(({mode})=>{
   window.fixtureMode=mode;
   const worker={postMessage(message,ports){if(!ports?.[0])return;
    if(['FIELD_SET_OWN_OBJECTS','FIELD_CACHE_MAINTAIN','FIELD_CACHE_STATUS','FIELD_CLIENT_GENERATION'].includes(message.type))return ports[0].postMessage({ok:true,items:[],budgetBytes:104857600,reserveBytes:209715200,cacheBytes:0,storageVerified:true});
    if(message.type==='FIELD_RUNTIME_INFO')return ports[0].postMessage({protocol:3,generation:'a'.repeat(64)});
    if(message.type==='CACHE_CHECKLIST'){
     if(window.fixtureMode==='preparing')return;
     const failed=['partial','budget'].includes(window.fixtureMode)&&message.url.includes('/2/');
     return ports[0].postMessage({ok:!failed,contentHash:'a'.repeat(64),generation:'a'.repeat(64),reasonCode:window.fixtureMode==='budget'?'CACHE_BUDGET_EXHAUSTED':undefined,message:window.fixtureMode==='budget'?'Недостаточно места для сохранения объекта.':'Не удалось сохранить объект.'});
    }
   }};
   const registration={active:worker};
   Object.defineProperty(navigator,'serviceWorker',{value:{register:async()=>registration,ready:Promise.resolve(registration),controller:worker}});
   window.FMonitorFieldStore={open:async()=>{if(window.fixtureMode==='error')throw Error('Локальное хранилище недоступно.');},deviceId:async()=>'fixture',actorState:async()=>({activeUserId:73,epoch:1}),getPreparation:async()=>null,putPreparationFenced:async()=>{},putPreparedDocumentFenced:async()=>{},onActorChange:()=>{}};
  },{mode});
  if(checklist)await context.addInitScript({path:path.join(c.root,'tests/Support/checklist_minimal_fixture.js')});
  const page=await context.newPage();await page.goto(origin+(checklist?'/pilot/objects/4512/checklist':'/pilot/construction-control'));
  return {context,page};
 }
 await check('R1 insufficient capacity retains exact prepared count and explains the cause',async()=>{
  const {context,page}=await contextFor('budget');try{await page.addScriptTag({url:origin+'/pilot/assets/field-offline-preparation.js'});await page.locator('[data-offline-summary][data-state="partial"]').waitFor();assert.equal(await page.locator('[data-offline-count]').innerText(),'Доступно оффлайн: 1 из 2');const summary=page.locator('[data-offline-summary]');assert.match(await summary.getAttribute('aria-label'),/недостаточно места/i,'INTENDED_RED capacity refusal reason must reach accessible summary');assert.match(await summary.getAttribute('title'),/недостаточно места/i);}finally{await context.close();}
 });
 await check('Chromium parses actual manifest and icons; cross-origin manifest is blocked',async()=>{
  for(const external of [false,true]){
   const {context,page}=await contextFor('ready',external);
   const client=await context.newCDPSession(page);
   const manifest=await client.send('Page.getAppManifest');
   if(external)assert(!manifest.data,'external manifest must not load');
   else {assert(manifest.data,'browser must load own manifest');assert.equal(manifest.errors.length,0,JSON.stringify(manifest.errors));const parsed=JSON.parse(manifest.data);assert.equal(parsed.display,'standalone');for(const icon of parsed.icons){const dimensions=await page.evaluate(src=>new Promise((resolve,reject)=>{const i=new Image();i.onload=()=>resolve([i.naturalWidth,i.naturalHeight]);i.onerror=()=>reject(Error('icon failed'));i.src=src;}),icon.src);assert.equal(dimensions.join('x'),icon.sizes);}}
   await context.close();
  }
 });
 for(const mode of ['preparing','ready','partial','error'])await check('compact accessible '+mode+' state, native install untouched',async()=>{
  const {context,page}=await contextFor(mode);
  try{
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.addScriptTag({url:origin+'/pilot/assets/field-offline-preparation.js'});
   await page.locator(`[data-offline-summary][data-state="${mode}"]`).waitFor();
   const root=page.locator('[data-offline-summary]');
   const expectedCount={preparing:'Доступно оффлайн: 0 из 2',ready:'Доступно оффлайн: 2 объекта',partial:'Доступно оффлайн: 1 из 2',error:'Подготовка не удалась'}[mode];
   await page.waitForFunction(expected=>document.querySelector('[data-offline-count]')?.textContent===expected,expectedCount,{timeout:3000});
   assert.equal(await page.locator('[data-offline-count]').innerText(),expectedCount,'offline count uses preparation, independently of empty filtered list');

   assert.equal(await page.locator('[data-offline-install]').count(),0,'no custom install button');
   assert.equal(await page.evaluate(()=>{const event=new Event('beforeinstallprompt',{cancelable:true});dispatchEvent(event);return event.defaultPrevented;}),false,'native prompt not cancelled');
   for(const width of [320,390,800,1366]){
    await page.setViewportSize({width,height:844});
    const box=await root.boundingBox();assert(box.height>0&&box.height<=12&&box.width<=12,`${width}px indicator height ${box.height} must remain a small visible dot`);
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${width}px overflow`);
    await page.screenshot({path:path.join(c.artifacts,`${mode}-${width}.png`)});
   }
   assert.equal(await root.locator('details, summary, button, [data-offline-retry], [data-offline-copy], [data-offline-updated]').count(),0,'passive indicator without disclosures or controls');
   const label=await root.getAttribute('aria-label');
   assert(label&&label.length>0,'accessible state');
   assert.equal(await root.getAttribute('title'),label,'native tooltip matches accessible state');
   if(mode==='ready')assert(label.includes('2'),'exact ready count');
   if(mode==='partial')assert(label.includes('1 из 2'),'partial truth retained');
   if(mode==='error')assert(label.includes('недоступно'),'error meaning retained');
   const before=await root.getAttribute('data-state');await root.click();
   assert.equal(await root.getAttribute('data-state'),before,'click does not open or mutate indicator');
   assert.deepEqual(errors,[]);
  }finally{await context.close();}
 });
 await check('OFFLINE-CONSTRUCTION-CHECKLIST-UI A3 closeout information without action',async()=>{
  const {context,page}=await contextFor('ready',false,true);
  try{assert.match(await page.locator('.fm2-check-closeout').innerText(),/Последние 15% закрываются актом ПТО и декларацией/);assert.equal(await page.locator('.fm2-check-closeout a, .fm2-check-closeout button').count(),0,'INTENDED_RED closeout action removed');}finally{await context.close();}
 });
 await check('OFFLINE-CONSTRUCTION-CHECKLIST-UI A4 general-photo text centered',async()=>{
  const {context,page}=await contextFor('ready',false,true);
  try{for(const width of [320,390,800,1366]){
   await page.setViewportSize({width,height:900});await page.locator('[data-general-photos]').scrollIntoViewIfNeeded();
   const boxes=await page.locator('.fm2-general-photos__actions label').evaluateAll(nodes=>nodes.map(e=>{const text=[...e.childNodes].find(n=>n.nodeType===Node.TEXT_NODE&&n.textContent.trim())||e.querySelector('span');const range=document.createRange();range.selectNodeContents(text);const t=range.getBoundingClientRect(),b=e.getBoundingClientRect();return {label:e.textContent.trim(),delta:t.y+t.height/2-b.y-b.height/2,height:b.height};}));
   await page.screenshot({path:path.join(c.artifacts,'general-labels-'+width+'.png')});
   const aligned=await page.locator('.fm2-general-photos__actions').evaluate(e=>{const controls=[...e.children].map(c=>c.getBoundingClientRect());const gallery=e.querySelector('[data-open-gallery]'),range=document.createRange();range.selectNodeContents(gallery.firstChild);const text=range.getBoundingClientRect(),badge=gallery.querySelector('span').getBoundingClientRect();return {top:Math.max(...controls.map(x=>x.top))-Math.min(...controls.map(x=>x.top)),height:Math.max(...controls.map(x=>x.height))-Math.min(...controls.map(x=>x.height)),badge:Math.abs(text.y+text.height/2-badge.y-badge.height/2)};});assert(aligned.top<=1&&aligned.height<=1&&aligned.badge<=3,'aligned actions and inline gallery count '+JSON.stringify(aligned));
   assert(boxes.every(x=>Math.abs(x.delta)<=3&&x.height>=(width<=680?44:40)),'INTENDED_RED label center '+width+' '+JSON.stringify(boxes));
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no photo overflow');
  }assert.equal(await page.getByLabel('Снять фото объекта',{exact:true}).count(),1);assert.equal(await page.getByLabel('Выбрать фото объекта',{exact:true}).count(),1);assert.equal(await page.locator('[data-general-photos] input[capture=environment]').count(),1);}finally{await context.close();}
 });
 await check('A3 general and section file inputs preserve focus, capture, multiple and enabled state',async()=>{
  const {context,page}=await contextFor('ready',false,true);try{
   const general=page.locator('[data-general-photos]'),section=page.locator('[data-check-section="1"]');assert.equal(await section.locator('[data-photo-input]').count(),2);assert.equal(await section.locator('input[capture=environment]').count(),1);assert.equal(await section.locator('input[multiple]').count(),1);
   await page.addScriptTag({url:origin+'/pilot/assets/checklist.js'});await page.locator('[data-checklist][data-field-ready="true"]').waitFor();
   for(const input of await general.locator('[data-photo-input]').all()){assert.equal(await input.isDisabled(),false);await input.focus();assert.equal(await input.evaluate(e=>document.activeElement===e),true,'file input keyboard-focusable');}
   const picker=general.getByLabel('Выбрать фото объекта',{exact:true});await picker.evaluate(e=>{e.addEventListener('change',()=>{window.photoInputChangeCount=e.files.length;},{capture:true});});await picker.setInputFiles({name:'fixture.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=','base64')});assert.equal(await page.evaluate(()=>window.photoInputChangeCount),1,'native picker still delivers files');
  }finally{await context.close();}
 });
 await check('A3 read-only checklist keeps general and section photo inputs disabled',async()=>{const {context,page}=await contextFor('readonly',false,true);try{for(const input of await page.locator('[data-photo-input]').all())assert.equal(await input.isDisabled(),true);}finally{await context.close();}});
 await check('CHECKLIST-MINIMAL routine queue absent; sync colors; recovery actions preserved',async()=>{
  const {context,page}=await contextFor('ready',false,true);
  try {
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.addScriptTag({url:origin+'/pilot/assets/checklist.js'});
   await page.locator('[data-checklist][data-field-ready="true"]').waitFor();
   const banner=page.locator('[data-sync-banner]'), dot=banner.locator('.fm2-sync-dot');
   const colors={};
   assert.equal(await page.locator('[data-sync-now]').count(),0,'no manual sync button; automatic engine remains owner');
   for(const status of ['queued','sending','retryable_error','uncertain','offline','online','conflict','rejected','auth_required','access_denied','storage_recovery','unsaved','error','unknown']){
    await page.evaluate(s=>window.checklistFixtureSet(s),status);
    assert.equal(await dot.count(),1,'visible sync dot '+status);
    assert(await dot.isVisible(),'dot visible '+status);
    colors[status]=await dot.evaluate(e=>getComputedStyle(e).backgroundColor);
    const detail=await banner.getAttribute('aria-label');assert(detail&&detail.length>0,'full accessible sync status '+status);assert.equal(await banner.getAttribute('title'),detail,'tooltip and accessible context agree '+status);
    if(status==='auth_required')assert.equal(detail,'Войдите снова. Данные сохранены','authentication recovery explanation retained');
    if(status==='uncertain')assert.match(detail,/не подтверждён/,'uncertain receipt explained');
    if(status==='access_denied')assert.match(detail,/Доступ отозван.*данные сохранены/,'revoked permission preserves local facts');

    for(const width of [320,393]){
     await page.setViewportSize({width,height:852});
     const b=await banner.boundingBox();assert(b.height<=24,'sync status must be one line '+status+' at '+width+': '+b.height);
     const back=await page.locator('.fm2-check-topline .fm2-back-link').boundingBox();assert(Math.abs(b.y+b.height/2-back.y-back.height/2)<=3,'status shares top row '+status+' at '+width);
     assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'no horizontal overflow '+status+' at '+width);
    }

    if(['queued','sending','retryable_error','uncertain','offline','online'].includes(status)){
     assert.equal(await page.locator('[data-field-operations]:visible').count(),0,'no queue wall for '+status);
     assert.equal(await page.locator('[data-field-queue-details]').count(),0,'no hidden routine disclosure');
     if(status!=='online'){
      assert.equal(await page.locator('[data-check-item="28"] .fm2-check-toggle').getAttribute('aria-checked'),'true','local check retained');
      assert.equal(await page.locator('[data-photo-key="photo-one"]').count(),1,'local photo retained');
      assert(!(await banner.innerText()).includes('Синхронизировано'),'pending never claims synced');
     }
    }
    if(['conflict','rejected','auth_required','access_denied','storage_recovery'].includes(status))assert(await page.locator('[data-field-operations]:visible').count()>0,'actionable recovery retained '+status);
    if(status==='unsaved')assert(await page.locator('[data-unsaved-photo] [data-unsaved-retry]').isVisible(),'unsaved recovery retained');
   }
   for(const status of ['conflict','auth_required','storage_recovery']){
    await page.evaluate(s=>window.checklistFixtureSet(s,false,0),status);
    const recovery=page.locator('[data-field-operations]');assert.match(await recovery.innerText(),/Фото объекта · фото/,'general recovery uses user-facing ownership');assert(!(await recovery.innerText()).includes('Раздел 0'),'internal bucket absent');
   }
   assert.notEqual(colors.online,colors.sending,'green versus sending blue');
   assert.notEqual(colors.online,colors.queued,'green versus waiting amber');
   assert.notEqual(colors.online,colors.conflict,'green versus conflict red');
   assert.equal(colors.queued,colors.retryable_error,'retry waits amber');
   assert.equal(colors.conflict,colors.auth_required,'auth red');
   assert.equal(colors.conflict,colors.error,'error red');assert.notEqual(colors.online,colors.unknown,'unknown never green');
   await page.evaluate(()=>window.checklistFixtureSet('queued'));
   for(const width of [320,390,800,1366]){await page.setViewportSize({width,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'checklist fits '+width);await page.screenshot({path:path.join(c.artifacts,'checklist-minimal-'+width+'.png')});}
   const remove=page.locator('[data-field-photo-discard][data-operation-id="photo-one"]');
   assert.equal(await remove.count(),1,'local photo discard on its thumbnail');
   await remove.click();assert.deepEqual(await page.evaluate(()=>window.checklistDiscards),[],'confirmation required');
   await page.locator('dialog[open] [data-confirm]').click();assert.deepEqual(await page.evaluate(()=>window.checklistDiscards),['photo-one']);
   assert.equal(await page.locator('[data-photo-key="photo-one"]').count(),0,'local photo removed after confirmation');
   await page.evaluate(()=>window.checklistFixtureSet('queued',true));assert.equal(await page.locator('[data-field-photo-discard]').count(),0,'attempted photo cannot be discarded');
   assert.deepEqual(errors,[]);
  }finally{await context.close();}
 });
 await browser.close();fs.writeFileSync(path.join(c.artifacts,'results.json'),JSON.stringify(results,null,2));
 process.exitCode=results.some(x=>!x.pass)?1:0;
})().catch(e=>{console.error(e);process.exitCode=1;});
