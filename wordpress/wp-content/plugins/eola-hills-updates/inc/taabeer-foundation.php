<?php
/** Copied from Taabeer site-foundation.php, GPL-2.0-or-later.
 * The dependency list is narrowed to Elementor for this property page. */
defined( 'ABSPATH' ) || exit;
function eola_hills_required_plugins() { return array( 'elementor' => 'elementor/elementor.php' ); }
function eola_hills_install_foundation_plugins() {
	require_once ABSPATH.'wp-admin/includes/plugin.php';
	require_once ABSPATH.'wp-admin/includes/plugin-install.php';
	require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
	foreach(eola_hills_required_plugins() as $slug=>$file) {
		if(!file_exists(WP_PLUGIN_DIR.'/'.$file)) {
			$api=plugins_api('plugin_information',array('slug'=>$slug,'fields'=>array('sections'=>false)));
			if(is_wp_error($api)) { return $api; }
			$upgrader=new Plugin_Upgrader(new Automatic_Upgrader_Skin());
			$result=$upgrader->install($api->download_link);
			if(is_wp_error($result) || !$result) { return is_wp_error($result)?$result:new WP_Error('install_failed','Could not install '.$slug.'. Check filesystem permissions.'); }
		}
		if(!is_plugin_active($file)) { $result=activate_plugin($file,'',false,true); if(is_wp_error($result)) { return $result; } }
	}
	return true;
}

function eola_hills_ensure_elementor_kit() {
	if(!did_action('elementor/init') || !isset(\Elementor\Plugin::instance()->kits_manager)){return 0;}
	$manager=\Elementor\Plugin::instance()->kits_manager;
	$id=(int)get_option('elementor_active_kit');
	if($id && $manager->is_kit($id) && 'trash'!==get_post_status($id)){
		if('publish'!==get_post_status($id)){wp_update_post(array('ID'=>$id,'post_status'=>'publish'));}
		return $id;
	}
	$available=get_posts(array('post_type'=>'elementor_library','post_status'=>'publish','posts_per_page'=>1,'meta_key'=>'_elementor_template_type','meta_value'=>'kit'));
	$id=$available?(int)$available[0]->ID:$manager->create_default();
	if(!$id || is_wp_error($id)){return 0;}
	if(!$available){update_post_meta($id,'_eola_hills_kit_needs_brand_defaults',1);}
	update_option('elementor_active_kit',$id);
	\Elementor\Plugin::instance()->files_manager->clear_cache();
	return $id;
}
