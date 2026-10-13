<?php
if (!defined('WP_CLI') || get_option('siteurl')!=='http://localhost:8087') throw new RuntimeException('Fresh isolated site only.');
wp_set_current_user(get_user_by('login','eola_admin')->ID);
Eola_Hills_Updates::instance()->complete_sync();
$id=(int)get_option('eola_hills_page_id');
if(get_option('eola_hills_pending_theme_sync'))WP_CLI::error(get_option('eola_hills_sync_error')?:'Sync still pending.');
if(!$id||get_post_status($id)!=='publish'||(int)get_option('page_on_front')!==$id||get_stylesheet()!=='eola-hills')WP_CLI::error('Fresh theme/page/homepage setup failed.');
if(!is_plugin_active('elementor/elementor.php')||is_plugin_active('eola-hills/eola-hills.php'))WP_CLI::error('Incorrect plugin architecture.');
$data=get_post_meta($id,'_elementor_data',true);
if(strpos($data,'3,018')===false||strpos($data,'644545554')===false||strpos($data,'{{EOLA_ASSETS}}')!==false)WP_CLI::error('Fresh content/media incorrect.');
WP_CLI::success('Fresh WordPress receives the theme, Elementor, real client content and published homepage through one updater action.');
