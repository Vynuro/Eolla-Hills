const {chromium}=require('@playwright/test');
const assert=require('node:assert/strict');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const page=await browser.newPage({viewport:{width:1440,height:1000}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://localhost:8085/',{waitUntil:'networkidle'});
 await page.locator('#gallery').scrollIntoViewIfNeeded();
 for(const [key,count] of [['home',4],['grounds',4],['spaces',2],['all',10]]){
   await page.locator(`.eola-filter-${key} a`).click();
   assert.equal(await page.locator('.eola-gallery-tile:visible').count(),count);
   assert.equal(await page.locator(`.eola-filter-${key} a`).getAttribute('aria-pressed'),'true');
 }
 await page.locator('.eola-gallery-tile a').first().click();
 await page.locator('.elementor-lightbox').waitFor({state:'visible'});
 await page.screenshot({path:'artifacts/screenshots/gallery-lightbox.png'});
 await page.keyboard.press('Escape');
 await page.locator('.elementor-lightbox').waitFor({state:'hidden'});
 const carousel=page.locator('.eola-property-carousel');
 await carousel.scrollIntoViewIfNeeded();
 const before=await carousel.locator('.swiper-slide-active img').getAttribute('src');
 await carousel.locator('.elementor-swiper-button-next').click();
 await page.waitForTimeout(800);
 const after=await carousel.locator('.swiper-slide-active img').getAttribute('src');
 assert.notEqual(before,after);
 await page.emulateMedia({reducedMotion:'reduce'});
 await page.waitForTimeout(100);
 assert.equal(await page.evaluate(()=>document.getAnimations().filter(x=>x.playState==='running').length),0);
 await page.locator('#property').scrollIntoViewIfNeeded();
 await page.screenshot({path:'artifacts/screenshots/property-section.png'});
 await page.locator('#gallery').scrollIntoViewIfNeeded();
 await page.screenshot({path:'artifacts/screenshots/gallery-section.png'});
 assert.deepEqual(errors,[]);
 await browser.close();
 console.log('Filters, native lightbox/keyboard close, carousel navigation, reduced motion and runtime-error checks passed.');
})().catch(e=>{console.error(e);process.exit(1)});
