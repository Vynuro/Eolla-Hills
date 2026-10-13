<?php
if (!defined('WP_CLI') || get_option('siteurl')!=='http://localhost:8086') throw new RuntimeException('Isolated theme upgrade test only.');
wp_set_current_user(get_user_by('login','eola_admin')->ID);Eola_Hills_Updates::instance()->complete_sync();
$id=(int)get_option('eola_hills_page_id');$backup=get_option('eola_hills_content_backup');
if(get_option('eola_hills_pending_theme_sync')||strpos(get_post_meta($id,'_elementor_data',true),'Skip to updated property')===false)WP_CLI::error('Changed Git content did not sync automatically.');
if($backup['meta']['_elementor_data']['value']!==get_option('eola_theme_upgrade_test')['page'])WP_CLI::error('Pre-update Elementor edits were not preserved in the page backup.');
WP_CLI::success('New Git layout automatically applied and previous native Elementor edits retained in a restorable backup.');
