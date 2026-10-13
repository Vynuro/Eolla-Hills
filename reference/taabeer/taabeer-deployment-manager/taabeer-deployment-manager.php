<?php
/**
 * Plugin Name: TAABEER Deployment Manager
 * Description: Securely deploys validated TAABEER theme releases from a GitHub repository while preserving WordPress and Elementor content.
 * Version: 1.0.1
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: TAABEER
 * License: GPL-2.0-or-later
 * Text Domain: taabeer-deployment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TAABEER_DEPLOYMENT_VERSION', '1.0.1' );
define( 'TAABEER_DEPLOYMENT_FILE', __FILE__ );
define( 'TAABEER_DEPLOYMENT_DIR', plugin_dir_path( __FILE__ ) );

require_once TAABEER_DEPLOYMENT_DIR . 'inc/class-taabeer-deployment-manager.php';

register_activation_hook( __FILE__, array( 'Taabeer_Deployment_Manager', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Taabeer_Deployment_Manager', 'deactivate' ) );

Taabeer_Deployment_Manager::instance();
