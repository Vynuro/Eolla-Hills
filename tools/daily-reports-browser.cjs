const {chromium}=require('@playwright/test');
const fs=require('node:fs'),assert=require('node:assert/strict');
(async()=>{
 const env=Object.fromEntries(fs.readFileSync('.env','utf8').trim().split(/\r?\n/).map(l=>[l.slice(0,l.indexOf('=')),l.slice(l.indexOf('=')+1)]));
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 fs.mkdirSync('artifacts/reports',{recursive:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}});page.setDefaultNavigationTimeout(90000);const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://localhost:8085/wp-login.php');await page.locator('#user_login').fill(env.EOLA_LOCAL_ADMIN_USER);await page.locator('#user_pass').fill(env.EOLA_LOCAL_ADMIN_PASSWORD);await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');
  for(const [slug,input,title,csvButton] of [['analytics','#ea-report-date','Daily website analytics','Download daily CSV'],['care','#ec-report-date','Daily website care report','Download daily care CSV']]){
   await page.goto(`http://localhost:8085/wp-admin/admin.php?page=eola-${slug}`);
   const date=await page.locator(input).inputValue();
   for(const [button,extension] of [['Download client report','html'],[csvButton,'csv']]){
    const pending=page.waitForEvent('download');await page.getByRole('button',{name:button,exact:true}).click();const download=await pending;
    assert(download.suggestedFilename().includes(date));assert(download.suggestedFilename().endsWith('.'+extension));
    const path=`artifacts/reports/${slug}-${date}.${extension}`;await download.saveAs(path);assert.equal(await download.failure(),null);
    const content=fs.readFileSync(path,'utf8');assert(content.includes(date));assert(!content.includes('Fatal error'));
    if(extension==='html'){
     assert(content.includes(title));assert(content.includes('Print / Save as PDF'));
     const report=await browser.newPage({viewport:{width:1200,height:950}});await report.setContent(content);await report.screenshot({path:`artifacts/screenshots/${slug}-daily-report.png`,fullPage:true});
     await report.setViewportSize({width:390,height:844});assert(await report.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await report.close();
    }
   }
   await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.setViewportSize({width:1440,height:1000});
   await page.screenshot({path:`artifacts/screenshots/${slug}-daily-dashboard.png`,fullPage:true});
   const form=page.locator(input).locator('..');const token=await form.locator('input[name="_wpnonce"]').inputValue();
   const action=slug==='care'?'eola_care_report':'eola_analytics_daily';
   const forged=await page.request.get(`http://localhost:8085/wp-admin/admin-post.php?action=${action}&date=${date}&_wpnonce=invalid`);assert.equal(forged.status(),403);
   const invalid=await page.request.get(`http://localhost:8085/wp-admin/admin-post.php?action=${action}&date=2099-02-31&_wpnonce=${token}`);assert.equal(invalid.status(),400);
  }
  assert.deepEqual(errors,[]);console.log('PASS: dated HTML/CSV downloads, printable client reports, mobile layouts, invalid-date rejection and export nonce protection.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
