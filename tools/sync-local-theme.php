<?php
if (!defined('WP_CLI') || !in_array(get_option('siteurl'),array('http://localhost:8085','http://localhost:8086'),true)) throw new RuntimeException('Local theme verification only.');
wp_set_current_user(get_user_by('login','eola_admin')->ID);
update_option('eola_hills_pending_theme_sync',EOLA_HILLS_VERSION,false);
delete_option('eola_hills_sync_error');
Eola_Hills_Updates::instance()->complete_sync();
if (get_option('eola_hills_pending_theme_sync')) WP_CLI::error(get_option('eola_hills_sync_error')?:'Theme sync is still pending.');
WP_CLI::success('Theme and native Elementor client content synced in one delivery.');
