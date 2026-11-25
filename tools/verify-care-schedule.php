<?php
if (!defined('WP_CLI') || get_option('siteurl') !== 'http://localhost:8087') throw new RuntimeException('Automation integration test is isolated to localhost:8087.');
$settings=Eola_Care::automation_settings();$oldRuns=get_option('eola_care_runs',[]);$root=Eola_Care::root();if(is_wp_error($root))WP_CLI::error($root->get_error_message());
$originalJob=is_file($root.'/job.json')?file_get_contents($root.'/job.json'):null;
$originalIndex=json_decode(file_get_contents($root.'/backups.json'),true)?:[];
$fixtureIds=[];
try{
    Eola_Care::deactivate();if(wp_next_scheduled('eola_care_tick'))WP_CLI::error('Deactivation left heartbeat');Eola_Care::schedule();
    $next=wp_next_scheduled('eola_care_tick');Eola_Care::schedule();if(!$next||$next!==wp_next_scheduled('eola_care_tick'))WP_CLI::error('Duplicate schedule');
    update_option('eola_care_runs',[]);update_option('eola_care_automation',['backup'=>0,'scan'=>0,'backup_time'=>'00:00','scan_time'=>'00:00','keep'=>7]);
    Eola_Care::cron();if(get_option('eola_care_runs',[]))WP_CLI::error('Disabled schedule started work');
    update_option('eola_care_automation',['backup'=>1,'scan'=>1,'backup_time'=>'00:00','scan_time'=>'00:00','keep'=>7]);
    file_put_contents($root.'/maintenance.json','true');Eola_Care::cron();if(get_option('eola_care_runs',[]))WP_CLI::error('Automation ran during recovery');file_put_contents($root.'/maintenance.json','false');
    $lock=fopen($root.'/task.lock','c');flock($lock,LOCK_EX);Eola_Care::cron();if(get_option('eola_care_runs',[]))WP_CLI::error('Overlapping worker acquired lock');flock($lock,LOCK_UN);fclose($lock);
    WP_CLI::log('PASS: schedule registration, deactivation cleanup, disabled scheduling, recovery and overlap guards.');
    // Valid tiny test archives exercise retention without risking any existing backup.
    $index=$originalIndex;
    for($i=1;$i<=8;$i++){
        $id='backup-2026010'.$i.'-000000-aabbccdd';$fixtureIds[]=$id;
        $z=new ZipArchive();$z->open($root.'/'.$id.'.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addFromString('fixture.txt','Retention test only');$z->close();
        $index[$id]=['id'=>$id,'created'=>'2026-01-0'.$i.'T00:00:00+00:00','automatic'=>true,'recovery_for'=>'','size'=>filesize($root.'/'.$id.'.zip'),'sha256'=>hash_file('sha256',$root.'/'.$id.'.zip')];
    }
    file_put_contents($root.'/backups.json',json_encode($index));
    for($i=0;$i<80;$i++){
        Eola_Care::cron();$runs=get_option('eola_care_runs',[]);
        foreach($runs as $r)if($r['status']==='error')WP_CLI::error($r['message']);
        $done=array_filter($runs,fn($r)=>$r['status']==='done');WP_CLI::log('Background batch '.($i+1).': '.count($done).' daily tasks completed.');
        if(count($done)===2)break;
    }
    if(count($done)!==2)WP_CLI::error('Automatic tasks did not complete');
    $before=get_option('eola_care_runs');Eola_Care::cron();if(get_option('eola_care_runs')!==$before)WP_CLI::error('Repeated same-day automatic run');
    $index=json_decode(file_get_contents($root.'/backups.json'),true);
    foreach($originalIndex as $id=>$entry)if(empty($entry['automatic'])&&!isset($index[$id]))WP_CLI::error('Manual/recovery backup pruned');
    if(is_file($root.'/'.$fixtureIds[0].'.zip')||is_file($root.'/'.$fixtureIds[1].'.zip'))WP_CLI::error('Old automatic archives not pruned');
    if(count(array_filter($index,fn($r)=>!empty($r['automatic'])))!==7)WP_CLI::error('Retention count differs');
    WP_CLI::success('Real automatic backup and scan complete without a browser; daily deduplication, dated records and seven-backup retention pass.');
} finally {
    foreach($fixtureIds as $id){if(is_file($root.'/'.$id.'.zip'))unlink($root.'/'.$id.'.zip');}
    $index=json_decode(file_get_contents($root.'/backups.json'),true)?:[];foreach($fixtureIds as $id)unset($index[$id]);file_put_contents($root.'/backups.json',json_encode($index));
    update_option('eola_care_automation',$settings);
    // Keep the real completed QA runs for report endpoint verification on this isolated site.
    foreach($oldRuns as $id=>$r){$runs=get_option('eola_care_runs',[]);$runs[$id]=$r;update_option('eola_care_runs',$runs);}
}
