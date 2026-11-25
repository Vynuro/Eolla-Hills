<?php
if (!defined('WP_CLI') || get_option('siteurl')!=='http://localhost:8087') throw new RuntimeException('Destructive recovery test is isolated to localhost:8087.');
wp_set_current_user(get_user_by('login','eola_admin')->ID);
function care_request($action,$extra=[]){$r=new WP_REST_Request('POST','/eola-care/v1/task');$r->set_body_params(array_merge(['action'=>$action],$extra));$v=Eola_Care::api($r);if(is_wp_error($v))WP_CLI::error($v->get_error_message());return $v->get_data();}
function care_finish($job){$i=0;while($job['status']==='running'){ $job=care_request('step'); if(++$i%30===0)WP_CLI::log($job['kind'].' '.$job['phase'].' '.$job['processed'].'/'.$job['total']);if($i>3000)WP_CLI::error('Job did not finish.');}if($job['status']!=='done')WP_CLI::error('Job failed.');return $job;}
$root=Eola_Care::root();if(is_wp_error($root))WP_CLI::error($root->get_error_message());
$fixture=ABSPATH.'wp-content/uploads/eola-care-restore-fixture.txt';file_put_contents($fixture,'original test file');update_option('eola_care_restore_fixture','original database value');
care_finish(care_request('backup'));$index=json_decode(file_get_contents($root.'/backups.json'),true);$id=array_key_last($index);
WP_CLI::log('PASS: Full private files/database backup completed.');
file_put_contents($fixture,'changed test file');update_option('eola_care_restore_fixture','changed database value');
$job=care_finish(care_request('restore',['id'=>$id,'confirm'=>'RESTORE '.$id]));
if(file_get_contents($fixture)!=='original test file'||get_option('eola_care_restore_fixture')!=='original database value')WP_CLI::error('Restore did not recover files and database.');
if(empty($job['recovery']))WP_CLI::error('No pre-restore recovery backup.');
WP_CLI::log('PASS: Full restore recovers changed file/database and retains a pre-restore recovery snapshot.');
$malware=ABSPATH.'wp-content/uploads/eola-care-scan-fixture.php';file_put_contents($malware,"<?php /* Harmless scanner test: eval(base64_decode('fixture')); */");
care_finish(care_request('scan'));$scan=json_decode(file_get_contents($root.'/scan.json'),true);$match=array_filter($scan['findings'],fn($f)=>$f['path']==='wp-content/uploads/eola-care-scan-fixture.php');if(!$match)WP_CLI::error('Scanner missed fixture.');
$relative='wp-content/uploads/eola-care-scan-fixture.php';care_request('quarantine',['id'=>$relative,'confirm'=>'QUARANTINE '.$relative]);if(file_exists($malware))WP_CLI::error('Quarantine did not remove fixture.');
$q=json_decode(file_get_contents($root.'/quarantine.json'),true);$qid=array_key_last($q);care_request('recover_file',['id'=>$qid,'confirm'=>'RECOVER_FILE '.$qid]);if(!file_exists($malware))WP_CLI::error('Quarantine recovery failed.');
care_request('quarantine',['id'=>$relative,'confirm'=>'QUARANTINE '.$relative]);$q=json_decode(file_get_contents($root.'/quarantine.json'),true);$qid=array_key_last($q);care_request('delete_file',['id'=>$qid,'confirm'=>'DELETE_FILE '.$qid]);
WP_CLI::log('PASS: Suspicious fixture flagged, quarantined, recovered and permanently deleted.');
care_request('delete',['id'=>$id,'confirm'=>'DELETE '.$id]);if(file_exists($root.'/'.$id.'.zip'))WP_CLI::error('Backup deletion failed.');
unlink($fixture);delete_option('eola_care_restore_fixture');
WP_CLI::success('Full backup, atomic database restore, recovery snapshot, scanner, quarantine and deletion checks passed on isolated WordPress.');
