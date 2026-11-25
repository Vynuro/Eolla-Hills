const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const wp=(...args)=>execFileSync('docker',['compose','run','--rm','cli',...args],{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
(async()=>{
 const count=wp('eval','global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."eola_events");');
 try {
  wp('plugin','deactivate','eola-hills-analytics');
  const html=await (await fetch('http://localhost:8085/')).text();assert(!/id=["']eola-analytics-js["']/.test(html));
  const response=await fetch('http://localhost:8085/?rest_route=/eola-analytics/v1/events',{method:'POST',headers:{Origin:'http://localhost:8085','Content-Type':'application/json'},body:'{}'});assert.equal(response.status,404);
  assert.equal(wp('eval','global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."eola_events");'),count);
 } finally { wp('plugin','activate','eola-hills-analytics'); }
 const html=await (await fetch('http://localhost:8085/')).text();assert(/id=["']eola-analytics-js["']/.test(html));
 const care=await fetch('http://localhost:8085/?rest_route=/eola-care/v1/task',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'backup'})});assert.equal(care.status,401);
 console.log('PASS: analytics deactivation removes tracker/endpoint and preserves data; reactivation works; anonymous care operations rejected.');
})().catch(e=>{console.error(e);process.exit(1)});
