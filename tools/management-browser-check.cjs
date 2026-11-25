// Local admin check of the management screens: general analytics with locked detail, Eola Care reports/protection.
const {chromium}=require('@playwright/test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
(async()=>{
 const env=Object.fromEntries(fs.readFileSync('.env','utf8').trim().split(/\r?\n/).map(l=>[l.slice(0,l.indexOf('=')),l.slice(l.indexOf('=')+1)]));
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 fs.mkdirSync('artifacts/screenshots',{recursive:true});
 for(const [width,name] of [[1440,'desktop'],[390,'mobile']]){
  const page=await browser.newPage({viewport:{width,height:900}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://localhost:8085/wp-login.php');await page.locator('#user_login').fill(env.EOLA_LOCAL_ADMIN_USER);await page.locator('#user_pass').fill(env.EOLA_LOCAL_ADMIN_PASSWORD);await page.locator('#wp-submit').click();await page.waitForURL(/wp-admin/);
  await page.goto('http://localhost:8085/wp-admin/admin.php?page=eola-analytics');
  const analytics=await page.locator('.ea-dashboard').innerText();
  for(const text of ['Measured visitors','Sessions','Page views','Returning sessions','Traffic sources','Pages','Devices'])assert.ok(analytics.includes(text),text);
  assert.ok((await page.locator('.ea-locked').count())>=3,'Detailed panels are locked');
  assert.ok(analytics.includes('Unlock this feature')&&analytics.includes('Contact developer'));
  assert.equal(await page.locator('a:has-text("Download report CSV")').count(),0,'CSV export is locked');
  assert.ok((await page.locator('a:has-text("Contact developer")').first().getAttribute('href')).startsWith('mailto:'));
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No horizontal overflow on analytics');
  await page.screenshot({path:`artifacts/screenshots/analytics-general-${name}.png`,fullPage:true});
  await page.goto('http://localhost:8085/wp-admin/admin.php?page=eola-care');
  const care=await page.locator('.ec-wrap').innerText();
  for(const text of ['Website report every 3 days','Always emailed to the site administrator and the developer','ma9440863@gmail.com','Daily automatic protection','Visitor rate protection','Recent blocks','Saved backups'])assert.ok(care.includes(text),text);
  await page.screenshot({path:`artifacts/screenshots/care-management-${name}.png`,fullPage:true});
  const blocked=await page.request.get('http://localhost:8085/wp-admin/admin-post.php?action=eola_analytics_export');
  assert.notEqual(blocked.status(),200,'Export endpoint refuses without unlock');
  assert.deepEqual(errors,[]);
  await page.close();
 }
 await browser.close();console.log('General analytics with locked detail, Eola Care report/protection screens and locked export checks passed.');
})();
