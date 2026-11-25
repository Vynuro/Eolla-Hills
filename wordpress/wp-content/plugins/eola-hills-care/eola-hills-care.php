<?php
/**
 * Plugin Name: Eola Care
 * Description: Daily private backups, security scans, visitor rate protection and emailed website reports.
 * Version: 1.2.1
 * Requires at least: 6.8
 * Requires PHP: 8.0
 * Author: Eola Hills
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;
define( 'EOLA_CARE_VERSION', '1.2.1' );
// The site administrator and the developer always receive the website report.
defined( 'EOLA_CARE_DEVELOPER_EMAIL' ) || define( 'EOLA_CARE_DEVELOPER_EMAIL', 'ma9440863@gmail.com' );
require_once __DIR__ . '/inc/trait-automation.php';
require_once __DIR__ . '/inc/trait-firewall.php';
require_once __DIR__ . '/inc/trait-reports.php';
require_once __DIR__ . '/inc/class-care.php';
Eola_Care::boot();
register_activation_hook( __FILE__, array( 'Eola_Care', 'schedule' ) );
register_deactivation_hook( __FILE__, array( 'Eola_Care', 'deactivate' ) );
