<?php
if (!defined('WP_CLI') || get_option('siteurl')!=='http://localhost:8087') throw new RuntimeException('Fresh local bootstrap only.');
wp_set_current_user(get_user_by('login','eola_admin')->ID);
$m=json_decode(file_get_contents('/work/artifacts/bootstrap-test.json'),true);
add_filter('pre_http_request',function($pre,$args,$url)use($m){return $url===$m['asset_url']?array('response'=>array('code'=>200),'body'=>file_get_contents('/work/artifacts/'.$m['fixture_file']),'headers'=>array(),'cookies'=>array()):$pre;},10,3);
$result=Eola_Hills_Updates::instance()->install($m);if(is_wp_error($result))WP_CLI::error($result->get_error_message());
if(!is_plugin_active('elementor/elementor.php')||get_stylesheet()!=='eola-hills'||!get_option('eola_hills_pending_theme_sync'))WP_CLI::error('Installer did not set up the theme and Elementor.');
WP_CLI::success('One custom updater installed Elementor and the theme on fresh WordPress; automatic delivery queued.');
