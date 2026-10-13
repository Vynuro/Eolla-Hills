<?php
if (!defined('WP_CLI') || get_option('siteurl')!=='http://localhost:8086') throw new RuntimeException('Isolated migration test only.');
function eola_migrate_check($ok,$text){if(!$ok)WP_CLI::error($text);WP_CLI::log('PASS: '.$text);}
wp_set_current_user(get_user_by('login','eola_admin')->ID);
$u=Eola_Hills_Updates::instance();$id=(int)get_option('eola_hills_page_id');
$css=WP_PLUGIN_DIR.'/eola-hills/assets/page.css';
file_put_contents($css,file_get_contents($css)."\n/* Simulated live plugin-file edit for migration. */\n");
$state=array('id'=>$id,'data'=>get_post_meta($id,'_elementor_data',true),'url'=>get_permalink($id),'status'=>get_post_status($id),'front'=>get_option('page_on_front'),'css'=>file_get_contents($css));
update_option('eola_theme_test_state',$state,false);
$m=json_decode(file_get_contents('/work/artifacts/eola-hills-theme-update.json'),true);
$m['asset_url']='https://api.github.com/repos/malikazhar545/5eolahills/releases/assets/999998';
$package='/work/artifacts/'.$m['package_asset'];
add_filter('pre_http_request',function($pre,$args,$url)use($m,$package){return $url===$m['asset_url']?array('response'=>array('code'=>200),'body'=>file_get_contents($package),'headers'=>array(),'cookies'=>array()):$pre;},10,3);
$bad=$m;$bad['theme']='taabeer';eola_migrate_check(is_wp_error($u->validate_manifest($bad)),'Wrong theme rejected.');
$bad=$m;$bad['sha256']=str_repeat('0',64);eola_migrate_check(is_wp_error($u->validate_zip($package,$bad)),'Checksum mismatch rejected.');
$unsafe=wp_tempnam('eola-unsafe');copy($package,$unsafe);$z=new ZipArchive();$z->open($unsafe);$z->addFromString('eola-hills/../escape.php','<?php exit;');$z->close();$bad=$m;$bad['sha256']=hash_file('sha256',$unsafe);eola_migrate_check(is_wp_error($u->validate_zip($unsafe,$bad)),'Path traversal rejected.');unlink($unsafe);
wp_set_current_user(0);eola_migrate_check($u->install($m)->get_error_code()==='permission','Anonymous install rejected.');wp_set_current_user(get_user_by('login','eola_admin')->ID);
$fail=function($new,$old){return $old;};add_filter('pre_update_option_eola_hills_legacy_backup',$fail,10,2);$result=$u->install($m);remove_filter('pre_update_option_eola_hills_legacy_backup',$fail,10);
eola_migrate_check(is_wp_error($result)&&$result->get_error_code()==='backup_store','Failed backup prevents migration.');
eola_migrate_check(is_plugin_active('eola-hills/eola-hills.php')&&file_get_contents($css)===$state['css'],'Failed migration retains current plugin and edits.');
$result=$u->install($m);eola_migrate_check($result===true,'Theme migration installs: '.(is_wp_error($result)?$result->get_error_message():'success'));
eola_migrate_check(get_stylesheet()==='eola-hills'&&!is_plugin_active('eola-hills/eola-hills.php'),'Theme is active and legacy plugin is deactivated.');
eola_migrate_check(get_post_meta($id,'_elementor_data',true)===$state['data'],'Code install waits for automatic content sync on next request.');
$b=get_option('eola_hills_legacy_backup');$tmp=wp_tempnam('eola-backup-check');file_put_contents($tmp,base64_decode($b['data']));$z=new ZipArchive();$z->open($tmp);eola_migrate_check($z->getFromName('eola-hills/assets/page.css')===$state['css'],'Legacy backup captures actual changed plugin files exactly.');$z->close();unlink($tmp);
eola_migrate_check(!get_option('eola_hills_install_lock'),'Install lock released.');
WP_CLI::success('Migration phase one passed; next request loads the theme and auto-syncs content.');
