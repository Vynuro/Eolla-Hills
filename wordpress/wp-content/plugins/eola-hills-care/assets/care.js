(() => {
 const config=window.eolaCare, status=document.querySelector('#ec-progress'); if(!config||!status)return;
 let busy=false;
 async function request(data){const response=await fetch(config.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce},body:JSON.stringify(data)});const body=await response.json();if(!response.ok)throw Error(body.message||'Care task failed');return body;}
 async function run(action,id,confirm){
  if(busy)return;busy=true;document.querySelectorAll('[data-task]').forEach(b=>b.disabled=true);
  try{
   let job=await request({action:action==='resume'?'status':action,id,confirm});
   while(job.status==='running'){
    status.textContent=`${job.kind}: ${job.phase||'preparing'} — ${job.processed} / ${job.total} files. Keep this tab open.`;
    job=await request({action:'step'});
   }
   status.textContent=job.message||'Completed.';location.reload();
  }catch(e){status.textContent=e.message+' Your saved backups remain available.';}
  finally{busy=false;document.querySelectorAll('[data-task]').forEach(b=>b.disabled=false);}
 }
 document.addEventListener('click',e=>{
  const button=e.target.closest('[data-task]');if(!button)return;
  const action=button.dataset.task,id=button.dataset.id||'';let confirm='';
  if(['restore','delete','quarantine','recover_file','delete_file'].includes(action)){
   const phrase=action.toUpperCase()+' '+id;
   const warning=action==='restore'?'Restore replaces website data. A recovery backup is created first.':action==='quarantine'?'Quarantining a valid file may break the website. Review the flagged path and reason first.':'This changes or removes the selected saved item.';
   confirm=prompt(warning+'\n\nType exactly: '+phrase)||'';if(confirm!==phrase)return;
  }
  run(action,id,confirm);
 });
})();
