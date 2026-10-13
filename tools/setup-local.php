<?php
// Local-only preview setup; never included in either plugin ZIP.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:8085' !== get_option( 'siteurl' ) ) { throw new RuntimeException( 'Local CLI only.' ); }
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$id = eola_hills_create_page();
if ( is_wp_error( $id ) ) { WP_CLI::error( $id->get_error_message() ); }
wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $id );
update_option( 'blog_public', 0 );
\Elementor\Plugin::instance()->files_manager->clear_cache();
WP_CLI::success( 'Local preview page ' . $id . ' is ready; search indexing is disabled.' );
