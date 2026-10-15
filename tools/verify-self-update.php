<?php
// Isolated 8086 updater self-update test. Run each step as its own CLI request, in order:
// same, tampered, self, resume, after-resume, content. See docs/development.md.
if ( ! defined( 'WP_CLI' ) || 'http://localhost:8086' !== get_option( 'siteurl' ) ) { throw new RuntimeException( 'Isolated test only.' ); }
function eola_self_check( $ok, $message ) { if ( ! $ok ) { WP_CLI::error( $message ); } WP_CLI::log( 'PASS: ' . $message ); }
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$u = Eola_Hills_Updates::instance();
$dir = '/work/artifacts/selftest/';
$current = json_decode( file_get_contents( $dir . 'manifest-current.json' ), true );
$next = json_decode( file_get_contents( $dir . 'manifest-next.json' ), true );
$step = $args[0] ?? '';
if ( 'same' === $step ) {
    update_option( 'eola_test_release', 'current' );
    $m = $u->release( true );
    eola_self_check( ! is_wp_error( $m ) && $m['updater']['version'] === $u->plugin_version(), 'Manifest with updater entry validates; installed updater is current' );
    eola_self_check( false === $u->self_update( $m ), 'No self-update when the installed updater is current' );
}
if ( 'tampered' === $step ) {
    update_option( 'eola_test_release', 'tampered' );
    $m = $u->release( true ); $r = $u->self_update( $m );
    eola_self_check( is_wp_error( $r ) && 'checksum' === $r->get_error_code(), 'Tampered updater package rejected' );
    eola_self_check( $current['updater']['version'] === $u->plugin_version() && ! get_option( 'eola_hills_resume_sync' ), 'Updater unchanged after rejection' );
    $bad = $m; $bad['updater']['package_asset'] = '../evil.zip';
    eola_self_check( is_wp_error( $u->validate_manifest( $bad ) ), 'Unexpected updater asset name rejected' );
}
if ( 'self' === $step ) {
    update_option( 'eola_test_release', 'next' );
    $m = $u->release( true ); $r = $u->self_update( $m );
    eola_self_check( true === $r, 'Self-update from the GitHub release: ' . ( is_wp_error( $r ) ? $r->get_error_message() : 'ok' ) );
    eola_self_check( $next['updater']['version'] === $u->plugin_version() && is_plugin_active( Eola_Hills_Updates::PLUGIN_FILE ), 'New updater installed and still active' );
    eola_self_check( get_option( 'eola_hills_resume_sync' ) && $current['version'] === $u->version(), 'Theme untouched; sync resumes on the redirect' );
    $b = get_option( 'eola_hills_updater_backup' ); $tmp = wp_tempnam( 'eola-updater-check' ); file_put_contents( $tmp, $u->backup_bytes( $b ) );
    $z = new ZipArchive(); $z->open( $tmp ); $header = $z->getFromName( Eola_Hills_Updates::PLUGIN_FILE ); $z->close(); unlink( $tmp );
    eola_self_check( $header && false !== strpos( $header, 'Version: ' . $current['updater']['version'] ) && $current['updater']['version'] === $b['version'], 'Previous updater backed up under its own folder' );
}
if ( 'resume' === $step ) {
    eola_self_check( false !== strpos( file_get_contents( WP_PLUGIN_DIR . '/eola-hills-updates/inc/class-updates.php' ), 'self-update-test' ), 'This request runs the new updater code' );
    add_filter( 'wp_redirect', function ( $location ) { update_option( 'eola_test_redirect', $location ); return false; } );
    $u->resume_sync();
}
if ( 'after-resume' === $step ) {
    eola_self_check( false !== strpos( (string) get_option( 'eola_test_redirect' ), 'tools.php?page=eola-hills-updates' ) && ! get_option( 'eola_hills_resume_sync' ), 'Resume redirected to the updater screen and cleared its flag' );
    eola_self_check( $next['version'] === $u->version() && $next['version'] === get_option( 'eola_hills_pending_theme_sync' ), 'Resumed sync installed the new theme; content sync pending' );
    $n = get_transient( 'eola_hills_notice_' . get_current_user_id() );
    eola_self_check( $n && ! $n['error'] && false !== strpos( $n['message'], 'Updater ' . $next['updater']['version'] . ' installed' ), 'Success notice: ' . ( $n['message'] ?? '' ) );
    $b = get_option( 'eola_hills_theme_backup' );
    eola_self_check( $b && $current['version'] === $b['version'] && $u->backup_bytes( $b ), 'Previous theme backed up in verified chunks' );
}
if ( 'content' === $step ) {
    $u->complete_sync();
    $id = (int) get_option( 'eola_hills_page_id' );
    eola_self_check( ! get_option( 'eola_hills_pending_theme_sync' ) && false !== strpos( get_post_meta( $id, '_elementor_data', true ), 'Skip to updated property' ), 'Elementor content from the new release applied' );
    ob_start(); $u->screen(); $html = ob_get_clean();
    eola_self_check( false !== strpos( $html, 'Updater: <strong>' . $next['updater']['version'] ) && false !== strpos( $html, 'Download previous updater backup' ) && false !== strpos( $html, 'Download previous theme backup' ), 'Screen shows the new updater and both backups' );
    delete_option( 'eola_test_release' ); delete_option( 'eola_test_redirect' );
}
WP_CLI::success( 'Self-update step: ' . $step );
