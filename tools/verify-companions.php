<?php
if (!defined('WP_CLI') || get_option('siteurl')!=='http://localhost:8087') throw new RuntimeException('Isolated local site only.');
wp_set_current_user(get_user_by('login','eola_admin')->ID);
function companion_check($ok,$label){if(!$ok)WP_CLI::error($label);WP_CLI::log('PASS: '.$label);}
$u=Eola_Hills_Updates::instance();
$m=json_decode(file_get_contents('/work/artifacts/eola-hills-theme-update.json'),true);
$m['asset_url']='https://api.github.com/repos/malikazhar545/5eolahills/releases/assets/999990';
$assets=[$m['asset_url']=>'/work/artifacts/'.$m['package_asset']];
foreach($m['plugins'] as $i=>&$p){$p['asset_url']='https://api.github.com/repos/malikazhar545/5eolahills/releases/assets/'.(999991+$i);$assets[$p['asset_url']]='/work/artifacts/'.$p['package_asset'];}unset($p);
add_filter('pre_http_request',function($pre,$args,$url)use($assets){return isset($assets[$url])?['response'=>['code'=>200],'body'=>file_get_contents($assets[$url]),'headers'=>[],'cookies'=>[]]:$pre;},10,3);
$r=$u->install($m);companion_check(!is_wp_error($r),'Local release installs theme plus both independent companion plugins: '.(is_wp_error($r)?$r->get_error_message():'success'));
$file='eola-hills-analytics/eola-hills-analytics.php';$path=WP_PLUGIN_DIR.'/'.$file;
companion_check(is_file($path)&&is_plugin_active($file)&&is_plugin_active('eola-hills-care/eola-hills-care.php'),'New analytics and care plugins install and start active.');
companion_check(is_plugin_active('limit-login-attempts-reloaded/limit-login-attempts-reloaded.php'),'Limit Login Attempts Reloaded installed from WordPress.org and active.');
$ver=get_plugin_data($path,false,false)['Version'];
$backup=get_option('eola_hills_theme_backup');
companion_check(!empty($backup['chunks'])&&strlen($u->backup_bytes($backup))>16*MB_IN_BYTES,'Large theme backup survives a default 16 MB database packet limit.');
$key=$backup['prefix'].'0';$part=get_option($key);delete_option($key);companion_check(false===$u->backup_bytes($backup),'Incomplete backup is rejected.');add_option($key,$part,'',false);
$r=activate_plugin($file);companion_check(!is_wp_error($r),'Analytics activates independently.');update_option('eola_analytics_test_data','retained');
file_put_contents($path,str_replace('Version: '.$ver,'Version: 0.9.0',file_get_contents($path)));
$r=$u->install($m);companion_check(!is_wp_error($r)&&is_plugin_active($file)&&get_option('eola_analytics_test_data')==='retained','Git plugin upgrade preserves activation and saved data.');
deactivate_plugins($file);file_put_contents($path,str_replace('Version: '.$ver,'Version: 0.9.0',file_get_contents($path)));
$r=$u->install($m);companion_check(!is_wp_error($r)&&!is_plugin_active($file),'Git plugin upgrade preserves deactivation.');
$b=get_option('eola_hills_analytics_backup');companion_check((bool)$u->backup_bytes($b),'Analytics code backup is recoverable.');
$bad=$m;$bad['plugins'][0]['slug']='unrelated';companion_check(is_wp_error($u->validate_manifest($bad)),'Unapproved companion slug rejected.');
$p=$m['plugins'][0];$p['sha256']=str_repeat('0',64);companion_check(is_wp_error($u->validate_plugin_zip('/work/artifacts/'.$p['package_asset'],$p)),'Modified companion package rejected.');
WP_CLI::success('Companion sync and chunked backup checks passed.');
