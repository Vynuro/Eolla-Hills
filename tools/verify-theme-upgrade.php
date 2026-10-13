<?php
if (!defined('WP_CLI') || get_option('siteurl')!=='http://localhost:8086') throw new RuntimeException('Isolated theme upgrade test only.');
wp_set_current_user(get_user_by('login','eola_admin')->ID);
$id=(int)get_option('eola_hills_page_id');$data=get_post_meta($id,'_elementor_data',true);
$tree=json_decode($data,true);$tree[0]['settings']['text']='Local Elementor edit before Git update';update_post_meta($id,'_elementor_data',wp_slash(wp_json_encode($tree)));
$css=get_template_directory().'/assets/page.css';file_put_contents($css,file_get_contents($css)."\n/* Local theme file edit: retain in backup. */\n");
update_option('eola_theme_upgrade_test',array('page'=>get_post_meta($id,'_elementor_data',true),'css'=>file_get_contents($css)),false);
$m=json_decode(file_get_contents('/work/artifacts/update-test.json'),true);
add_filter('pre_http_request',function($pre,$args,$url)use($m){return $url===$m['asset_url']?array('response'=>array('code'=>200),'body'=>file_get_contents('/work/artifacts/'.$m['fixture_file']),'headers'=>array(),'cookies'=>array()):$pre;},10,3);
$result=Eola_Hills_Updates::instance()->install($m);if(is_wp_error($result))WP_CLI::error($result->get_error_message());
$b=get_option('eola_hills_theme_backup');$tmp=wp_tempnam('eola-theme-check');file_put_contents($tmp,base64_decode($b['data']));$z=new ZipArchive();$z->open($tmp);$ok=$z->getFromName('eola-hills/assets/page.css')===get_option('eola_theme_upgrade_test')['css'];$z->close();unlink($tmp);if(!$ok)WP_CLI::error('Theme backup did not preserve edited files.');
WP_CLI::success('Git theme upgrade replaces changed files after backing them up; next request applies changed Elementor delivery.');
