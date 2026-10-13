<?php
/**
 * Plugin Name: Eola Hills Updates
 * Description: Install verified Eola Hills Property releases from GitHub without replacing Elementor content.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.0
 * Author: Eola Hills
 * License: GPL-2.0-or-later
 * Text Domain: eola-hills-updates
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/inc/trait-taabeer-deployment.php';
require_once __DIR__ . '/inc/taabeer-foundation.php';
require_once __DIR__ . '/inc/class-updates.php';
Eola_Hills_Updates::instance();
register_activation_hook( __FILE__, array( 'Eola_Hills_Updates', 'activate' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'eola_hills_release_check' ); } );
