const fs = require('fs'), path = require('path');
const {chromium} = require(process.env.FMONITOR_TEST_PLAYWRIGHT_MODULE || path.resolve(__dirname, '../../../shlz-ui/node_modules/playwright'));
(async () => {
  const browser = await chromium.launch({headless: true});
  try {
    const page = await browser.newPage();
    const html = fs.readFileSync(process.argv[2], 'utf8');
    await page.route('http://127.0.0.1/**', route => route.fulfill({status: 200, contentType: 'text/html', body: html}));
    await page.goto('http://127.0.0.1/pilot/construction-control?ownership=all&query=BULK&completed=1&page=2');
    await page.evaluate(() => {
      window.__manifestRequests = [];
      window.fetch = async url => { window.__manifestRequests.push(String(url)); return {ok: false, status: 503}; };
      window.__submits = [];
      document.querySelector('[data-control-filter-form]').addEventListener('submit', event => {
        event.preventDefault();
        const url = new URL(event.currentTarget.action, location.href);
        for (const [key, value] of new FormData(event.currentTarget)) url.searchParams.append(key, value);
        window.__submits.push(url.search);
      });
    });
    const assets = path.dirname(process.argv[3]);
    for (const name of ['field-offline-store.js', 'field-offline-sync.js', 'field-offline-photo.js', 'field-offline.js']) await page.addScriptTag({path: path.join(assets, name)});
    await page.evaluate(async () => {
      const store = FMonitorFieldStore, device = await store.deviceId();
      await store.putActorState({deviceInstallationId: device, activeUserId: 73, epoch: 0, tombstonedAt: null});
      await store.enqueue({scope: store.scopeFor(73, device, 5000), userId: 73, objectId: 5000, deviceInstallationId: device, type: 'item_completed', payload: {sectionId: 1, itemId: 28}, projection: {revision: 0, photos: []}, actor: {deviceInstallationId: device, activeUserId: 73, epoch: 0}});
    });
    await page.addScriptTag({path: process.argv[3]});
    await page.addScriptTag({path: path.join(assets, 'field-offline-preparation.js')});
    await page.waitForFunction(() => document.querySelector('[data-object-id="5000"] [data-local-sync]')?.dataset.state === 'queued');
    const rows = await page.locator('[data-control-row]').count(), total = (await page.locator('[data-result-count]').innerText()).trim();
    await page.locator('[data-control-search]').fill('changed');
    await page.waitForTimeout(400);
    const changed = await page.evaluate(() => ({current: location.search, target: window.__submits.at(-1) || ''}));
    const pageReset = changed.current.includes('page=2') && changed.target.includes('query=changed') && !changed.target.includes('page=');
    await page.locator('[data-clear-filters]').click();
    await page.waitForTimeout(50);
    const cleared = await page.evaluate(() => ({current: location.search, target: window.__submits.at(-1) || ''}));
    const clearReset = cleared.current.includes('page=2') && cleared.target.includes('ownership=mine') && !cleared.target.includes('query=') && !cleared.target.includes('completed=') && !cleared.target.includes('page=');
    const localState = await page.locator('[data-object-id="5000"] [data-local-sync]').getAttribute('data-state');
    const preparationRequested = await page.evaluate(() => window.__manifestRequests.includes('/pilot/construction-control/offline-manifest?page=1'));
    const shipment = await page.locator('[data-shipment-state]').count() > 0;
    const checklistLink = (await page.locator('[data-control-row] a[href*="/checklist"]').count()) === rows;
    process.stdout.write(JSON.stringify({rows, total, pageReset, clearReset, localState, preparationRequested, shipment, checklistLink}));
  } finally { await browser.close(); }
})().catch(error => { process.stderr.write(error.stack || String(error)); process.exit(1); });
