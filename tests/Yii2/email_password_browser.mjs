import fs from 'node:fs';
import {createRequire} from 'node:module';
const config=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const require=createRequire(import.meta.url);
const {chromium}=require(config.playwright);
const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
const assert=(condition,message)=>{if(!condition)throw new Error(message);};
async function login(page,email,password){await page.goto(config.origin+'/pilot/login');await page.locator('[name=email]').fill(email);await page.locator('button[type=submit]').click();await page.locator('[name=password]').fill(password);await page.locator('button[type=submit]').click();}
try {
    const admin=await browser.newPage();await login(admin,config.adminEmail,config.adminPassword);await admin.goto(config.origin+'/pilot/admin/users');
    const create=admin.locator('form[action="/pilot/admin/users/create"]');
    assert(await create.count()===1,'INTENDED_RED admin create form without invitation');
    await create.locator('[name=email]').fill('browser.new@shlz.ru');await create.locator('[name=fullName]').fill('Browser New');await create.locator('button[type=submit]').click();
    assert(await admin.locator('[data-invitation-copy]').count()===0,'invitation link absent');
    const context=await browser.newContext({viewport:{width:390,height:844}});const user=await context.newPage();
    await user.goto(config.origin+'/pilot/login');await user.locator('[name=email]').fill('browser.new@shlz.ru');await user.locator('[name=email]').press('Enter');await user.locator('form[action="/pilot/first-password"]').waitFor();
    await user.locator('[name=password]').fill(config.password);await user.locator('[name=passwordConfirmation]').fill(config.password);await user.locator('[name=passwordConfirmation]').press('Enter');await user.getByText('Пароль установлен',{exact:false}).waitFor();
    await login(user,'browser.new@shlz.ru',config.password);await user.goto(config.origin+'/pilot/login');
    // Separate unauthenticated context proves that recovery does not need an active session.
    const recoveryContext=await browser.newContext({viewport:{width:390,height:844}});const reset=await recoveryContext.newPage();await reset.goto(config.origin+'/pilot/login');await reset.locator('[name=email]').fill('browser.new@shlz.ru');await reset.locator('[name=email]').press('Enter');await reset.getByText('Забыли пароль?',{exact:true}).click();
    await reset.locator('[name=email]').fill('browser.new@shlz.ru');await reset.locator('[name=email]').press('Enter');await reset.getByText('Если для этого email',{exact:false}).waitFor();
    const deadline=Date.now()+15000;while(!fs.existsSync(config.mailbox)&&Date.now()<deadline)await new Promise(resolve=>setTimeout(resolve,50));assert(fs.existsSync(config.mailbox),'recovery instruction delivered by fixture transport');
    const mail=JSON.parse(fs.readFileSync(config.mailbox,'utf8'));const match=mail.text.match(/https:\/\/portal\.example\.test(\/pilot\/reset-password\?token=[A-Za-z0-9_-]{43})/);assert(match,'trusted recovery URL');
    await reset.goto(config.origin+match[1]);await reset.locator('[name=password]').fill(config.newPassword);await reset.locator('[name=passwordConfirmation]').fill(config.newPassword);await reset.locator('[name=passwordConfirmation]').press('Enter');await reset.getByText('Пароль изменён',{exact:false}).waitFor();
    assert(await reset.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'narrow layout has no horizontal overflow');await reset.screenshot({path:config.artifacts+'/password-reset-mobile.png',fullPage:true});
    await user.goto(config.origin+'/pilot/admin/users');assert(new URL(user.url()).pathname==='/pilot/login','old browser session revoked');await login(reset,'browser.new@shlz.ru',config.newPassword);assert(!new URL(reset.url()).pathname.endsWith('/login'),'new password login');
    const anonymous=await browser.newPage();await anonymous.goto(config.origin+'/pilot/login');await anonymous.locator('[name=email]').fill('missing.browser@shlz.ru');await anonymous.locator('[name=email]').press('Enter');await anonymous.getByText('Для предоставления доступа создайте заявку в ИТСМ',{exact:false}).waitFor();
    console.log('PASS browser first password and email recovery');
} finally {await browser.close();}
