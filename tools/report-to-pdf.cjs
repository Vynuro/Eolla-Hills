// Convert a downloaded Eola Care website report (.html) to an A4 PDF.
// Usage: node tools/report-to-pdf.cjs report.html [report.pdf] [preview.png]
const {chromium}=require('@playwright/test');
const path=require('node:path');
const {pathToFileURL}=require('node:url');
(async()=>{
 const input=process.argv[2];
 if(!input){console.error('Usage: node tools/report-to-pdf.cjs report.html [report.pdf] [preview.png]');process.exit(1);}
 const output=process.argv[3]||input.replace(/\.html?$/i,'')+'.pdf';
 const browser=await chromium.launch({executablePath:process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const page=await browser.newPage({viewport:{width:760,height:1000}});
 await page.goto(pathToFileURL(path.resolve(input)).href);
 await page.pdf({path:output,format:'A4',printBackground:true,margin:{top:'12mm',bottom:'12mm',left:'10mm',right:'10mm'}});
 if(process.argv[4])await page.screenshot({path:process.argv[4],fullPage:true});
 await browser.close();
 console.log(output);
})();
