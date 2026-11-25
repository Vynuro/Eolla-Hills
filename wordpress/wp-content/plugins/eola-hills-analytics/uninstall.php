<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
wp_clear_scheduled_hook( 'eola_analytics_cleanup' );
// Retain reporting data and settings deliberately, including on reinstall.
