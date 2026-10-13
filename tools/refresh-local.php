<?php
// Deliberate reseed for developer preview ONLY, never called by either plugin.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:8085' !== get_option( 'siteurl' ) ) { throw new RuntimeException( 'Local CLI only.' ); }
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$id = (int) get_option( 'eola_hills_page_id' );
$template = eola_hills_prepare_template();
if ( is_wp_error( $template ) ) { WP_CLI::error( $template->get_error_message() ); }
\Elementor\Plugin::instance()->documents->get( $id )->save( array( 'elements' => $template['content'], 'settings' => $template['page_settings'] ) );
\Elementor\Plugin::instance()->files_manager->clear_cache();
WP_CLI::success( 'Refreshed the local developer preview from the source template.' );
