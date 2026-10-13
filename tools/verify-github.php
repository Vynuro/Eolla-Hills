<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:8086' !== get_option( 'siteurl' ) ) { throw new RuntimeException( 'Isolated local release test only.' ); }
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$updates = Eola_Hills_Updates::instance();
$page_id = (int) get_option( 'eola_hills_page_id' );
$before = $page_id ? get_post_meta( $page_id, '_elementor_data', true ) : null;
$release = $updates->release( true );
if ( is_wp_error( $release ) ) { WP_CLI::error( $release->get_error_message() ); }
WP_CLI::log( 'GitHub release manifest fetched: ' . $release['version'] );
$result = $updates->install( $release );
if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
if ( $page_id && $before !== get_post_meta( $page_id, '_elementor_data', true ) ) { WP_CLI::error( 'GitHub update changed saved Elementor content.' ); }
if ( $release['version'] !== $updates->version() ) { WP_CLI::error( 'Installed version does not match the release.' ); }
WP_CLI::success( 'Published GitHub package downloaded, checksum verified, installed and activated on isolated WordPress.' );
if ( $page_id ) { WP_CLI::success( 'Existing Elementor content preserved byte-for-byte.' ); }
