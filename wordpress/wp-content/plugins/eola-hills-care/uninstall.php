<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
wp_clear_scheduled_hook( 'eola_care_tick' );
wp_clear_scheduled_hook( 'eola_care_continue' );
// Private backups, quarantine, audit log and reports are deliberately retained.
