<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( get_option( 'siteurl' ), array( 'http://localhost:8085', 'http://localhost:8086' ), true ) ) { throw new RuntimeException( 'Local test only.' ); }
function eola_content_check( $ok, $message ) { if ( ! $ok ) { WP_CLI::error( $message ); } WP_CLI::log( 'PASS: ' . $message ); }
$id = (int) get_option( 'eola_hills_page_id' );
$before = get_post_meta( $id, '_elementor_data', true );
$settings = get_post_meta( $id, '_elementor_page_settings', true );
$status = get_post_status( $id ); $url = get_permalink( $id ); $front = get_option( 'page_on_front' );
wp_set_current_user( 0 );
eola_content_check( 'permission' === eola_hills_update_content()->get_error_code(), 'Anonymous content replacement is rejected.' );
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$result = eola_hills_update_content();
eola_content_check( true === $result, 'Explicit client update applies: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'success' ) );
$updated = get_post_meta( $id, '_elementor_data', true );
eola_content_check( strpos( $updated, '3,018' ) !== false && strpos( $updated, '3,112' ) === false && strpos( $updated, '644545554' ) !== false, 'Current listing dimensions and RMLS replace old values.' );
eola_content_check( strpos( $updated, 'eola-demo-' ) === false && strpos( $updated, 'assets/photos/' ) === false && strpos( $updated, '{{EOLA_ASSETS}}' ) === false, 'All client media and document links resolve to the Media Library.' );
eola_content_check( $status === get_post_status( $id ) && $url === get_permalink( $id ) && $front === get_option( 'page_on_front' ), 'Page status, URL and homepage remain unchanged.' );
$backup = get_option( 'eola_hills_content_backup' );
eola_content_check( $backup['meta']['_elementor_data']['value'] === $before, 'Previous Elementor content is backed up byte-for-byte.' );
eola_content_check( 'already_applied' === eola_hills_update_content()->get_error_code(), 'Repeat application cannot overwrite subsequent editor changes.' );
$media = get_option( 'eola_hills_client_media' );
eola_content_check( count( $media ) === 27, 'All 27 supplied assets are imported.' );
$manifest = json_decode( file_get_contents( EOLA_HILLS_DIR . 'assets/media/manifest.json' ), true );
foreach ( array( 'floorplan-1', 'floorplan-2', 'floorplan-3', 'floorplan-4', 'amenities' ) as $key ) {
    eola_content_check( hash_equals( $manifest[$key]['sha256'], hash_file( 'sha256', get_attached_file( $media[$key]['id'] ) ) ), $key . ' matches its packaged client file byte-for-byte.' );
}
eola_content_check( true === eola_hills_update_content( true ), 'Previous content restores successfully.' );
eola_content_check( $before === get_post_meta( $id, '_elementor_data', true ) && $settings === get_post_meta( $id, '_elementor_page_settings', true ), 'Restore recovers exact old data and native settings.' );
eola_content_check( true === eola_hills_update_content(), 'Client content reapplies after restoring.' );
eola_content_check( $media === get_option( 'eola_hills_client_media' ), 'Repeat import reuses media without duplicates.' );
eola_content_check( ! get_option( 'eola_hills_content_lock' ), 'Content update lock is released.' );
WP_CLI::success( 'Client content update, private backup and restore checks passed.' );
