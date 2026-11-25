<?php
/** Copied from Taabeer site-foundation.php, GPL-2.0-or-later.
 * The dependency list is narrowed to Elementor for this property page; recommended
 * WordPress.org plugins are installed and activated once, then left to the site owner. */
defined( 'ABSPATH' ) || exit;
function eola_hills_required_plugins() { return array( 'elementor' => 'elementor/elementor.php' ); }
function eola_hills_recommended_plugins() { return array( 'limit-login-attempts-reloaded' => 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' ); }
function eola_hills_install_foundation_plugins() {
	require_once ABSPATH.'wp-admin/includes/plugin.php';
	require_once ABSPATH.'wp-admin/includes/plugin-install.php';
	require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
	$recommended=eola_hills_recommended_plugins();
	foreach(eola_hills_required_plugins()+$recommended as $slug=>$file) {
		$fresh=!file_exists(WP_PLUGIN_DIR.'/'.$file);
		// A recommended plugin the owner removed or deactivated is not forced back.
		if(isset($recommended[$slug]) && !$fresh) { continue; }
		if(isset($recommended[$slug]) && get_option('eola_hills_recommended_'.$slug)) { continue; }
		if($fresh) {
			$api=plugins_api('plugin_information',array('slug'=>$slug,'fields'=>array('sections'=>false)));
			// A recommended plugin must never block the website sync; it is retried next Sync.
			if(is_wp_error($api)) { if(isset($recommended[$slug])) { continue; } return $api; }
			$upgrader=new Plugin_Upgrader(new Automatic_Upgrader_Skin());
			$result=$upgrader->install($api->download_link);
			if((is_wp_error($result) || !$result) && isset($recommended[$slug])) { continue; }
			if(is_wp_error($result) || !$result) { return is_wp_error($result)?$result:new WP_Error('install_failed','Could not install '.$slug.'. Check filesystem permissions.'); }
		}
		if(!is_plugin_active($file)) { $result=activate_plugin($file,'',false,isset($recommended[$slug])?false:true); if(is_wp_error($result)) { if(isset($recommended[$slug])) { continue; } return $result; } }
		if(isset($recommended[$slug])) { update_option('eola_hills_recommended_'.$slug,time(),false); }
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
