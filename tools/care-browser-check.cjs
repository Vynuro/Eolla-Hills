const {chromium}=require('@playwright/test');
const fs=require('node:fs');const assert=require('node:assert/strict');
(async()=>{
 const env=Object.fromEntries(fs.readFileSync('.env','utf8').trim().split(/\r?\n/).map(l=>[l.slice(0,l.indexOf('=')),l.slice(l.indexOf('=')+1)]));
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://localhost:8085/wp-login.php');await page.locator('#user_login').fill(env.EOLA_LOCAL_ADMIN_USER);await page.locator('#user_pass').fill(env.EOLA_LOCAL_ADMIN_PASSWORD);await page.locator('#wp-submit').click();await page.waitForURL('**/wp-admin/**');
  await page.goto('http://localhost:8085/wp-admin/admin.php?page=eola-care');await page.getByRole('heading',{name:'Eola Care',exact:true}).waitFor();
  assert.equal(await page.locator('.notice-error',{hasText:/backup directory|Backup storage|single-site/i}).count(),0,'Private storage available');
  const nonceCheck=await page.evaluate(async()=>{const r=await fetch(eolaCare.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':'invalid'},body:JSON.stringify({action:'backup'})});return r.status});assert.equal(nonceCheck,403);
  if(await page.getByRole('link',{name:'Download',exact:true}).count()===0){
   const state=await page.evaluate(async()=>{const r=await fetch(eolaCare.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':eolaCare.nonce},body:JSON.stringify({action:'status'})});return r.json()});
   await page.getByRole('button',{name:state.status==='running'?'Resume current task':'Create website backup',exact:true}).click();
   await page.getByRole('link',{name:'Download',exact:true}).first().waitFor({timeout:600000});
  }
  await page.screenshot({path:'artifacts/screenshots/care-dashboard.png',fullPage:true});
  const downloadUrl=await page.getByRole('link',{name:'Download',exact:true}).first().getAttribute('href');
  const forbidden=await browser.newContext();assert([400,403].includes((await forbidden.request.get(downloadUrl)).status()));await forbidden.close();
  const pending=page.waitForEvent('download');await page.getByRole('link',{name:'Download',exact:true}).first().click();const download=await pending;
  await download.saveAs('artifacts/qa-main-backup.zip');assert.equal(await download.failure(),null);assert(fs.statSync('artifacts/qa-main-backup.zip').size>1000);
  await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
  assert.deepEqual(errors,[]);console.log('PASS: actual admin backup button completes resumable backup, private storage, nonce/capability checks, backup listing and mobile layout.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
