<?php
// Local management check (8085 only): foundation plugins, reports and daily email content.
// Usage: wp eval-file verify-management.php <foundation|report|daily>
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
    $end = current_datetime()->setTime( 0, 0 )->modify( '+1 day' );
    eola_mgmt_check( Eola_Care::send_report( $end, 3 ), 'Report built, archived and handed to wp_mail' );
    $mail = get_option( 'eola_test_mail' )[0];
    $to = array_map( 'strtolower', $mail['to'] );
    eola_mgmt_check( in_array( strtolower( get_option( 'admin_email' ) ), $to, true ) && in_array( EOLA_CARE_DEVELOPER_EMAIL, $to, true ), 'Report goes to the site administrator and the developer: ' . implode( ', ', $to ) );
    foreach ( array( 'Hacking attempts blocked', 'Bots / scrapers blocked', 'Spam comments blocked', 'Daily backups and security scans', 'Visitors', 'Website status' ) as $part ) { eola_mgmt_check( false !== strpos( $mail['message'], $part ), 'Report includes ' . $part ); }
    file_put_contents( '/tmp/eola-report.html', $mail['message'] );
    WP_CLI::log( 'SUBJECT: ' . $mail['subject'] );
    $log = get_option( 'eola_care_report_log' ); $last = end( $log );
    eola_mgmt_check( ! empty( $last['sent'] ) && 2 <= $last['recipients'], 'Report log records the send' );
}
if ( 'daily' === $step ) {
    delete_option( 'eola_test_mail' ); $date = wp_date( 'Y-m-d' );
    $sent = get_option( 'eola_care_daily_email', array() ); unset( $sent[$date] ); update_option( 'eola_care_daily_email', $sent, false );
    Eola_Care::daily_email( $date );
    $mail = get_option( 'eola_test_mail' )[0] ?? null;
    eola_mgmt_check( $mail && false !== strpos( $mail['subject'], 'Daily backup and security' ) && in_array( EOLA_CARE_DEVELOPER_EMAIL, $mail['to'], true ), 'Daily backup email to admin and developer: ' . ( $mail['subject'] ?? 'none' ) );
    Eola_Care::daily_email( $date );
    eola_mgmt_check( 1 === count( get_option( 'eola_test_mail' ) ), 'Only one daily email per day' );
}
delete_option( 'eola_test_mail' );
WP_CLI::success( 'Management step: ' . $step );
