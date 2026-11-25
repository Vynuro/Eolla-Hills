<?php
/**
 * Plugin Name: Eola Analytics
 * Description: Private property website analytics, campaigns and engagement reports in WordPress.
 * Version: 1.2.0
 * Requires at least: 6.8
 * Requires PHP: 8.0
 * Author: Eola Hills
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;
define( 'EOLA_ANALYTICS_VERSION', '1.2.0' );
// General analytics by default. Detailed reports unlock with define( 'EOLA_ANALYTICS_UNLOCKED', true ) in wp-config.php.
defined( 'EOLA_ANALYTICS_DEVELOPER_EMAIL' ) || define( 'EOLA_ANALYTICS_DEVELOPER_EMAIL', 'ma9440863@gmail.com' );
require_once __DIR__ . '/inc/trait-daily-reports.php';
require_once __DIR__ . '/inc/class-analytics.php';
Eola_Analytics::boot();
register_activation_hook( __FILE__, array( 'Eola_Analytics', 'activate' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'eola_analytics_cleanup' ); } );
