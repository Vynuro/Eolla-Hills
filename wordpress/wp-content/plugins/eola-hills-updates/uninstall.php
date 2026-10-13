<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
foreach ( array( 'eola_hills_token', 'eola_hills_baseline', 'eola_hills_code_backup', 'eola_hills_last_install', 'eola_hills_install_lock', 'eola_hills_pending_initial_setup' ) as $option ) { delete_option( $option ); }
delete_transient( 'eola_hills_release' );
delete_transient( 'eola_hills_theme_release' );
wp_clear_scheduled_hook( 'eola_hills_release_check' );
// Theme, Elementor documents, media and migration/theme/page backups are retained.
