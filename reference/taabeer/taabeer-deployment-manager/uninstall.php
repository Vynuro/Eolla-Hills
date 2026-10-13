<?php
/**
 * Remove updater credentials and schedules when the plugin is deleted.
 *
 * Installed theme files and WordPress content are deliberately preserved.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'taabeer_deployment_settings' );
delete_option( 'taabeer_deployment_github_token' );
delete_option( 'taabeer_deployment_last_release' );
delete_option( 'taabeer_deployment_theme_baseline' );
delete_site_transient( 'taabeer_deployment_release_check' );
wp_clear_scheduled_hook( 'taabeer_deployment_scheduled_check' );
