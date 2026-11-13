import fs from 'node:fs';
import {createRequire} from 'node:module';
const require=createRequire(import.meta.url),c=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const {chromium}=require(c.playwright),browser=await chromium.launch({headless:true});
const check=(value,message)=>{if(!value)throw new Error(message);};
async function login(page){await page.goto(c.origin+'/pilot/login');await page.locator('[name=email]').fill(c.email);await page.locator('[name=email]').locator('xpath=ancestor::form').locator('button[type=submit]').click();await page.locator('[name=password]').fill(c.password);await Promise.all([page.waitForResponse(r=>r.url().endsWith('/pilot/login')&&r.status()===303),page.locator('[name=password]').locator('xpath=ancestor::form').locator('button[type=submit]').click()]);}
const visibleColumns=page=>page.locator('[data-register-table] thead th[data-column]:visible').evaluateAll(es=>es.map(e=>e.dataset.column));
const state=page=>page.locator('[data-register-state]').evaluate(e=>JSON.parse(e.textContent));
try{
 const context=await browser.newContext({viewport:{width:1440,height:960}}),page=await context.newPage(),assetFailures=[];
 page.on('response',r=>{if(new URL(r.url()).pathname.startsWith('/pilot/assets/')&&r.status()>=400)assetFailures.push(r.status()+' '+r.url());});
 await login(page);const r=await page.goto(c.origin+'/pilot/address-register');check(r.status()===200,'INTENDED_RED address register unavailable');
 await page.getByRole('heading',{name:'Адресный перечень',exact:true}).waitFor();const initial=await visibleColumns(page);check(initial.includes('actualFinishDate')&&initial[0]==='address','confirmed default visible columns');
 check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'desktop contained width');
 await page.screenshot({path:c.artifacts+'/desktop.png',fullPage:true});
 const viewport=page.locator('[data-register-table]').locator('..');const pinned=await page.locator('[data-register-table] th[data-column=address]').boundingBox();
 await viewport.evaluate(e=>{e.scrollTop=180;e.scrollLeft=250;});const scrolled=await page.locator('[data-register-table] th[data-column=address]').boundingBox();check(Math.abs(pinned.x-scrolled.x)<2&&Math.abs(pinned.y-scrolled.y)<2,'sticky header/address remain in scroll viewport');await viewport.evaluate(e=>{e.scrollTop=0;e.scrollLeft=0;});
 await page.locator('[data-register-open]').focus();await page.keyboard.press('Enter');await page.locator('[data-register-settings]').waitFor({state:'visible'});
 const row=key=>page.locator(`[data-register-settings] [data-column-key="${key}"]`);
 const search=page.locator('[data-register-column-search]');await search.fill('Комментарий');check(await row('comment').isVisible()&&!(await row('status').isVisible()),'column search really hides unmatched rows');await search.fill('');
 await row('comment').locator('input[type=checkbox]').check();await row('address').locator('input[type=number]').fill('440');await row('comment').locator('[data-column-up]').click();
 await page.locator('[data-register-cancel]').click();check(JSON.stringify(await visibleColumns(page))===JSON.stringify(initial),'cancel leaves saved view');
 await page.locator('[data-register-open]').click();check(!(await row('comment').locator('input[type=checkbox]').isChecked()),'cancel restores draft');
 await row('comment').locator('input[type=checkbox]').check();await row('address').locator('input[type=number]').fill('440');await row('registrationNumber').locator('[data-column-up]').click();
 await page.screenshot({path:c.artifacts+'/desktop-columns.png',fullPage:true});
 const saved=page.waitForResponse(r=>r.url().endsWith('/address-register/preferences')&&r.request().method()==='POST');await page.locator('[data-register-save]').click();const sr=await saved;check(sr.status()===200,'save succeeds');const expected=await sr.json();check(expected.widths.address===440&&expected.visible.includes('comment'),'exact settings saved');
 await page.reload();check(JSON.stringify(await visibleColumns(page))===JSON.stringify(expected.visible),'reload preserves order/visible');check((await state(page)).widths.address===440,'reload preserves width');
 // A new browser context means there is no localStorage or shared session state.
 const second=await browser.newContext(),p2=await second.newPage();await login(p2);await p2.goto(c.origin+'/pilot/address-register');check(JSON.stringify(await visibleColumns(p2))===JSON.stringify(expected.visible),'new browser uses account view');
 // Stale second tab must not overwrite a later change.
 await p2.locator('[data-register-open]').click();await page.locator('[data-register-open]').click();await row('factoryNumber').locator('input[type=checkbox]').check();const save2=page.waitForResponse(r=>r.url().endsWith('/address-register/preferences')&&r.request().method()==='POST');await page.locator('[data-register-save]').click();check((await save2).status()===200,'first tab advances');
 const conflict=p2.waitForResponse(r=>r.url().endsWith('/address-register/preferences')&&r.request().method()==='POST');await p2.locator('[data-register-save]').click();check((await conflict).status()===409,'stale tab conflict');check((await p2.locator('[data-register-status]').textContent()).trim().length>0,'conflict explained');await second.close();
 await page.setViewportSize({width:390,height:844});await page.reload();check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile contained width');
 await page.screenshot({path:c.artifacts+'/mobile.png',fullPage:true});await page.locator('[data-register-open]').click();
 const nav=await page.locator('.fm2-sidebar').boundingBox();for(const name of ['save','reset','cancel']){const box=await page.locator(`[data-register-${name}]`).boundingBox();check(box&&box.y>=0&&box.y+box.height<=nav.y,'mobile '+name+' remains above bottom navigation');}
 await page.screenshot({path:c.artifacts+'/mobile-columns.png',fullPage:true});
 check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile settings contained');await page.keyboard.press('Escape');check(!(await page.locator('[data-register-settings]').isVisible()),'Escape closes settings');check(await page.locator('[data-register-open]').evaluate(e=>e===document.activeElement),'focus restored');
 await page.locator('[data-register-open]').click();await page.locator('[data-register-reset]').click();const reset=page.waitForResponse(r=>r.url().endsWith('/address-register/preferences')&&r.request().method()==='POST');await page.locator('[data-register-save]').click();check((await reset).status()===200,'reset saves');await page.reload();check(JSON.stringify(await visibleColumns(page))===JSON.stringify(initial),'defaults restored');
 await page.locator('[name=q]').fill('no-such-object');await page.locator('[name=q]').locator('xpath=ancestor::form').locator('button[type=submit]').click();check(await page.locator('[data-register-open]').isVisible(),'empty still configurable');
 fs.writeFileSync(c.result,JSON.stringify({complete:true,assetFailures}));
}finally{await browser.close();}
