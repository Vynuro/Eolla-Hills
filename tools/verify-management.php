<?php
// Local management check (8085 only): foundation plugins, reports and daily email content.
// Usage: wp eval-file verify-management.php <foundation|report|wait>
if ( ! defined( 'WP_CLI' ) || ! in_array( get_option( 'siteurl' ), array( 'http://localhost:8085', 'http://localhost:8086', 'http://localhost:8087' ), true ) ) { throw new RuntimeException( 'Local test only.' ); }
function eola_mgmt_check( $ok, $message ) { if ( ! $ok ) { WP_CLI::error( $message ); } WP_CLI::log( 'PASS: ' . $message ); }
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$step = $args[0] ?? '';
// Capture outgoing email instead of sending it.
add_filter( 'pre_wp_mail', function ( $null, $atts ) { $log = get_option( 'eola_test_mail', array() ); $log[] = array( 'to' => (array) $atts['to'], 'subject' => $atts['subject'], 'message' => $atts['message'] ); update_option( 'eola_test_mail', $log, false ); return true; }, 10, 2 );
if ( 'foundation' === $step ) {
    $r = eola_hills_install_foundation_plugins();
    eola_mgmt_check( true === $r, 'Foundation plugins installed: ' . ( is_wp_error( $r ) ? $r->get_error_message() : 'ok' ) );
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    eola_mgmt_check( is_plugin_active( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' ), 'Limit Login Attempts Reloaded is active' );
    deactivate_plugins( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' );
    eola_hills_install_foundation_plugins();
    eola_mgmt_check( ! is_plugin_active( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' ), 'An owner-deactivated recommended plugin is not forced back on' );
    activate_plugin( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' );
}
if ( 'report' === $step ) {
    delete_option( 'eola_test_mail' );
    eola_mgmt_check( Eola_Care::send_report( current_datetime(), 3 ), 'Report built, archived and handed to wp_mail' );
    $mail = get_option( 'eola_test_mail' )[0];
    $to = array_map( 'strtolower', $mail['to'] );
    eola_mgmt_check( in_array( strtolower( get_option( 'admin_email' ) ), $to, true ) && in_array( EOLA_CARE_DEVELOPER_EMAIL, $to, true ), 'Report goes to the site administrator and the developer: ' . implode( ', ', $to ) );
    foreach ( array( 'Hacking attempts blocked', 'Bots / scrapers blocked', 'Spam comments blocked', 'Daily backups and security scans', 'Visitors', 'Website status' ) as $part ) { eola_mgmt_check( false !== strpos( $mail['message'], $part ), 'Report includes ' . $part ); }
    file_put_contents( '/tmp/eola-report.html', $mail['message'] );
    WP_CLI::log( 'SUBJECT: ' . $mail['subject'] );
    $log = get_option( 'eola_care_report_log' ); $last = end( $log );
    eola_mgmt_check( ! empty( $last['sent'] ) && 2 <= $last['recipients'], 'Report log records the send' );
}
if ( 'wait' === $step ) {
    // The automatic report waits for today's backup and scan, then sends once.
    $saved = get_option( 'eola_care_runs', array() ); $today = wp_date( 'Y-m-d' );
    $settings = get_option( 'eola_care_reports', array() ); update_option( 'eola_care_reports', array_merge( Eola_Care::report_settings(), array( 'time' => '00:00' ) ), false );
    $log = get_option( 'eola_care_report_log', array() ); unset( $log[$today] ); update_option( 'eola_care_report_log', $log, false );
    delete_option( 'eola_care_report_last' ); delete_option( 'eola_test_mail' );
    $runs = array_filter( $saved, fn( $r ) => $r['date'] !== $today );
    $runs['t-backup'] = array( 'date' => $today, 'kind' => 'backup', 'status' => 'done', 'bytes' => 1000, 'files' => 10 );
    $runs['t-scan'] = array( 'date' => $today, 'kind' => 'scan', 'status' => 'running' );
    update_option( 'eola_care_runs', $runs, false );
    Eola_Care::report_tick();
    eola_mgmt_check( ! get_option( 'eola_test_mail' ) && ! get_option( 'eola_care_report_last' ), 'No report while the scan is still running' );
    $runs['t-scan'] = array( 'date' => $today, 'kind' => 'scan', 'status' => 'done', 'files' => 100, 'flagged' => 0, 'skipped' => 0 );
    update_option( 'eola_care_runs', $runs, false );
    Eola_Care::report_tick(); Eola_Care::report_tick();
    $mail = get_option( 'eola_test_mail', array() );
    eola_mgmt_check( 1 === count( $mail ), 'Exactly one report once backup and scan are done' );
    eola_mgmt_check( false !== strpos( $mail[0]['message'], '10 files' ) && false !== strpos( $mail[0]['message'], '100 files checked' ), "Report includes today's backup and scan" );
    eola_mgmt_check( 1 === count( array_filter( $mail[0]['to'], fn( $t ) => $t === EOLA_CARE_DEVELOPER_EMAIL ) ) && in_array( strtolower( get_option( 'admin_email' ) ), $mail[0]['to'], true ), 'Same report to the administrator and the developer' );
    update_option( 'eola_care_runs', $saved, false ); update_option( 'eola_care_reports', $settings, false );
    $log = get_option( 'eola_care_report_log', array() ); unset( $log[$today] ); update_option( 'eola_care_report_log', $log, false ); delete_option( 'eola_care_report_last' );
}
delete_option( 'eola_test_mail' );
WP_CLI::success( 'Management step: ' . $step );
