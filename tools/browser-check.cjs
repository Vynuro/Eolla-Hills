const { chromium } = require('@playwright/test');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
(async () => {
  fs.mkdirSync('artifacts/screenshots', {recursive:true});
  const browser = await chromium.launch({executablePath:process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless:true});
  const errors=[];
  const page=await browser.newPage();
  page.on('pageerror', e=>errors.push(e.message));
  for (const [name,width,height] of [['desktop',1440,1000],['tablet',768,1024],['mobile',375,812],['small-mobile',320,740],['landscape',812,375]]) {
    await page.setViewportSize({width,height});
    await page.goto('http://localhost:8085/',{waitUntil:'networkidle'});
    await page.locator('h1').waitFor();
    await page.evaluate(async()=>{for(let y=0;y<document.body.scrollHeight;y+=700){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,35));}window.scrollTo(0,0);});
    await page.waitForTimeout(250);
    assert.equal(await page.locator('h1').count(),1);
    assert.match(await page.locator('h1').innerText(), /Unmistakably/);
    assert.ok(await page.locator('.elementor-widget-image').count() >= 19);
    await page.locator('.elementor-widget-image img').evaluateAll(images=>images.forEach(img=>{img.loading='eager';}));
    await page.waitForFunction(()=>[...document.querySelectorAll('.elementor-widget-image img')].every(img=>img.complete&&img.naturalWidth>0));
    await page.evaluate(()=>Promise.all(document.getAnimations().map(a=>a.finished.catch(()=>{}))));
    assert.ok(await page.locator('a[href^="mailto:"]').count() >= 4);
    const layout=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth}));
    assert.ok(layout.scroll<=layout.width+1, `${name} horizontal overflow: ${JSON.stringify(layout)}`);
    const brokenAnchors=await page.locator('a[href^="#"]').evaluateAll(links=>links.filter(a=>!document.getElementById(a.hash.slice(1))).map(a=>a.hash));
    assert.deepEqual(brokenAnchors,[]);
    assert.equal(await page.locator('meta[name="robots"]').getAttribute('content').then(x=>x.includes('noindex')),true);
    await page.screenshot({path:`artifacts/screenshots/${name}.png`,fullPage:true});
    if(name==='desktop') await page.screenshot({path:'artifacts/screenshots/desktop-fold.png'});
    if(name==='mobile') await page.screenshot({path:'artifacts/screenshots/mobile-fold.png'});
    console.log(`${name}: native widgets, links, anchors and width checks passed.`);
  }
  await page.emulateMedia({reducedMotion:'reduce'});
  await page.keyboard.press('Tab');
  assert.match(await page.locator(':focus').innerText(), /Skip to property/);
  assert.deepEqual(errors,[]);
  await browser.close();
})().catch(error=>{console.error(error);process.exit(1)});
