<?php
if (!defined('WP_CLI') || get_option('siteurl')!=='http://localhost:8086') throw new RuntimeException('Isolated migration test only.');
function eola_delivery_check($ok,$text){if(!$ok)WP_CLI::error($text);WP_CLI::log('PASS: '.$text);}
wp_set_current_user(get_user_by('login','eola_admin')->ID);
Eola_Hills_Updates::instance()->complete_sync();
eola_delivery_check(!get_option('eola_hills_pending_theme_sync'),'Automatic theme/content sync completed: '.get_option('eola_hills_sync_error',''));
$s=get_option('eola_theme_test_state');$id=$s['id'];$data=get_post_meta($id,'_elementor_data',true);
eola_delivery_check(strpos($data,'3,018')!==false&&strpos($data,'644545554')!==false&&strpos($data,'eola-demo-')===false,'Real client content automatically replaces the old delivery.');
eola_delivery_check(get_permalink($id)===$s['url']&&get_post_status($id)===$s['status']&&get_option('page_on_front')===$s['front'],'Existing page URL, status and homepage remain unchanged.');
eola_delivery_check(get_option('eola_hills_content_backup')['meta']['_elementor_data']['value']===$s['data'],'Original Elementor page is backed up byte-for-byte.');
eola_delivery_check(!is_file(WP_PLUGIN_DIR.'/eola-hills/eola-hills.php')&&is_plugin_active('eola-hills-updates/eola-hills-updates.php')&&is_plugin_active('elementor/elementor.php'),'Legacy property plugin removed; single custom updater plus Elementor remain.');
$edit=json_decode($data,true);$edit[0]['settings']['text']='Client editable skip link';update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($edit)));$edited=get_post_meta($id,'_elementor_data',true);
eola_delivery_check(eola_hills_sync_release()===true&&get_post_meta($id,'_elementor_data',true)===$edited,'Unchanged delivery keeps later native Elementor edits.');
update_post_meta($id,'_elementor_data',wp_slash($data));
eola_delivery_check(eola_hills_update_content(true)===true&&get_post_meta($id,'_elementor_data',true)===$s['data'],'Previous page can be restored exactly.');
eola_delivery_check(eola_hills_sync_release()===true&&get_post_meta($id,'_elementor_data',true)===$s['data'],'Restored page remains restored for this delivery.');
delete_post_meta($id,'_eola_hills_restored_delivery');eola_delivery_check(eola_hills_sync_release()===true,'Client delivery reapplies when explicitly requested in the test.');
WP_CLI::success('Single-updater migration, automatic delivery, native edit preservation and restore passed.');
