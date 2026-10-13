const {chromium}=require('@playwright/test');
const assert=require('node:assert/strict');
const crypto=require('node:crypto');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const page=await browser.newPage({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
 await page.goto('http://localhost:8085/',{waitUntil:'networkidle'});
 const content=await page.locator('body').innerText();
 for(const value of ['3,018','644545554','931000172','200211111','Anita Borgaes'])assert.ok(content.includes(value),value);
 assert.ok(!content.includes('3,112'));
 const markup=await page.content();assert.ok(!markup.includes('eola-demo-'));
 assert.equal(await page.locator('.eola-gallery-tile').count(),15);
 assert.equal(await page.locator('.eola-agent-card img').count(),2);
 assert.ok(await page.locator('.elementor-widget-heading h1,.elementor-widget-heading h2,.elementor-widget-heading h3,.eola-amenities .elementor-accordion-title').evaluateAll(es=>es.every(e=>getComputedStyle(e).fontWeight==='600')));
 const nav=await page.locator('.eola-header').evaluate(el=>{const r=el.children[1].getBoundingClientRect();return r.x+r.width/2;});assert.ok(Math.abs(nav-720)<1);
 await page.locator('img').evaluateAll(imgs=>imgs.forEach(i=>i.loading='eager'));
 await page.waitForFunction(()=>[...document.images].every(i=>i.complete&&i.naturalWidth>0));
 await page.locator('#amenities').scrollIntoViewIfNeeded();
 const tab=page.locator('.eola-amenities .elementor-tab-title').nth(1);await tab.click();assert.equal(await tab.getAttribute('aria-expanded'),'true');
 const pdf=await page.locator('#amenities a[href$=".pdf"]').getAttribute('href');
 const res=await page.request.get(pdf);assert.equal(res.status(),200);
 const manifest=JSON.parse(fs.readFileSync('wordpress/wp-content/plugins/eola-hills/assets/media/manifest.json','utf8'));
 assert.equal(crypto.createHash('sha256').update(await res.body()).digest('hex'),manifest.amenities.sha256);
 const plan=page.locator('#floor-plans .elementor-widget-image a').first();await plan.click();
 await page.locator('.elementor-lightbox').waitFor({state:'visible'});await page.keyboard.press('Escape');
 for(const name of ['home','workshop','garage','amenities','floor-plans','team']){
  const section=page.locator('#'+name);
  await section.scrollIntoViewIfNeeded();
  await section.locator('img').evaluateAll(imgs=>Promise.all(imgs.map(i=>i.decode())));
  await page.waitForTimeout(1000);
  await section.screenshot({path:`artifacts/screenshots/client-${name}.png`});
 }
 await browser.close();console.log('Client facts/media, 600-weight headings, centered menu, amenities accordion, original PDF and floor-plan lightbox checks passed.');
})().catch(e=>{console.error(e);process.exit(1)});
