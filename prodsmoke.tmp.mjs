import { chromium } from 'playwright';
const BASE = 'https://cellar-os.on-forge.com';
const b = await chromium.launch();
const p = await (await b.newContext({ viewport:{width:1440,height:900} })).newPage();
await p.goto(`${BASE}/login`);
await p.fill('input[type=email]','demo@cellaros.test');
await p.fill('input[type=password]','password');
await p.click('button[type=submit]');
await p.waitForURL(/dashboard/);
await p.goto(`${BASE}/catalogue?colour=Sparkling`);
await p.waitForSelector('table tbody tr');
await p.waitForTimeout(2500);
const info = await p.evaluate(() => {
  const sub = document.querySelector('select[wire\\:model\\.live="sub_type"]');
  const panelOpen = !!document.querySelector('select[wire\\:model\\.live="colour"]')?.offsetParent;
  const opts = sub ? [...sub.options].map(o=>o.text.trim()) : [];
  const typeCells = [...document.querySelectorAll('table tbody tr')].slice(0,4)
    .map(r => [...r.querySelectorAll('td')].map(td=>td.innerText.trim()).find(t=>t.startsWith('Sparkling')) || '-');
  return { subTypeSelectExists: !!sub, subTypeVisible: !!sub?.offsetParent, filterPanelOpen: panelOpen, opts, typeCells };
});
console.log(JSON.stringify(info, null, 1));
await b.close();
