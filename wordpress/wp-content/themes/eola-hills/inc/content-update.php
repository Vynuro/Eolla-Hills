<?php
defined( 'ABSPATH' ) || exit;

/** Explicit content replacement, separate from non-destructive plugin upgrades. */
function eola_hills_update_content( $restore = false ) {
    $id = (int) get_option( 'eola_hills_page_id' );
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $id ) ) { return new WP_Error( 'permission', 'Administrator access is required.' ); }
    if ( ! $id || ! get_post_meta( $id, '_eola_hills_page', true ) || ! did_action( 'elementor/loaded' ) ) { return new WP_Error( 'page_missing', 'The existing Elementor property page could not be found.' ); }
    if ( ! $restore && EOLA_HILLS_CONTENT_VERSION === get_post_meta( $id, '_eola_hills_content_version', true ) ) { return new WP_Error( 'already_applied', 'This client content has already been applied. Edit the current page in Elementor.' ); }
    $lock = (int) get_option( 'eola_hills_content_lock' );
    if ( $lock && time() - $lock > 600 ) { delete_option( 'eola_hills_content_lock' ); }
    if ( ! add_option( 'eola_hills_content_lock', time(), '', false ) ) { return new WP_Error( 'locked', 'A content update is already running.' ); }
    try {
        wp_raise_memory_limit( 'image' );
        if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 240 ); }
        if ( $restore ) {
            $backup = get_option( 'eola_hills_content_backup' );
            if ( empty( $backup['meta'] ) || (int) $backup['page_id'] !== $id ) { return new WP_Error( 'backup_missing', 'No matching page backup is available.' ); }
            foreach ( $backup['meta'] as $key => $entry ) {
                if ( $entry['exists'] ) { update_post_meta( $id, $key, wp_slash( $entry['value'] ) ); }
                else { delete_post_meta( $id, $key ); }
            }
            $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $backup['post_content'] ) ), true );
            if ( is_wp_error( $result ) ) { return $result; }
            delete_option( 'eola_hills_content_backup' );
            update_post_meta( $id, '_eola_hills_restored_delivery', EOLA_HILLS_CONTENT_VERSION );
        } else {
            $backup = array( 'page_id' => $id, 'created' => current_time( 'mysql', true ), 'post_content' => get_post_field( 'post_content', $id, 'raw' ), 'meta' => array() );
            foreach ( array( '_elementor_data', '_elementor_page_settings', '_wp_page_template', '_eola_hills_seed_version', '_eola_hills_content_version' ) as $key ) {
                $backup['meta'][$key] = array( 'exists' => metadata_exists( 'post', $id, $key ), 'value' => get_post_meta( $id, $key, true ) );
            }
            update_option( 'eola_hills_content_backup', $backup, false );
            if ( get_option( 'eola_hills_content_backup' ) !== $backup ) { return new WP_Error( 'backup_failed', 'The current page could not be backed up. No content was replaced.' ); }
            $template = eola_hills_prepare_template();
            if ( is_wp_error( $template ) ) { return $template; }
            $document = \Elementor\Plugin::instance()->documents->get( $id );
            if ( ! $document || ! $document->save( array( 'elements' => $template['content'], 'settings' => $template['page_settings'] ) ) ) { return new WP_Error( 'save_failed', 'The client update could not be saved. The previous page backup is available.' ); }
            update_post_meta( $id, '_eola_hills_seed_version', EOLA_HILLS_VERSION );
            update_post_meta( $id, '_eola_hills_content_version', EOLA_HILLS_CONTENT_VERSION );
            delete_post_meta( $id, '_eola_hills_restored_delivery' );
        }
        \Elementor\Plugin::instance()->files_manager->clear_cache();
        clean_post_cache( $id );
        return true;
    } finally {
        delete_option( 'eola_hills_content_lock' );
    }
}

add_action( 'admin_post_eola_hills_content_update', function () {
    check_admin_referer( 'eola_hills_content_update' );
    $restore = isset( $_POST['restore'] ) && '1' === $_POST['restore'];
    $result = eola_hills_update_content( $restore );
    if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 'Eola Hills content update', array( 'back_link' => true ) ); }
    wp_safe_redirect( admin_url( 'admin.php?page=eola-hills&content_updated=1' ) ); exit;
} );

function eola_hills_content_update_panel( $id ) {
    if ( ! $id ) { return; }
    echo '<h2>Client property content</h2>';
    echo '<p>GitHub theme releases automatically apply changed client layouts and media after saving a private page backup. Every section remains editable in Elementor. Code-only releases keep your saved page edits.</p>';
    $backup = get_option( 'eola_hills_content_backup' );
    if ( ! empty( $backup['page_id'] ) && (int) $backup['page_id'] === (int) $id ) {
        echo '<p>Previous page backup: ' . esc_html( $backup['created'] ) . ' UTC. Restoring replaces the current page content with that saved version.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'eola_hills_content_update' );
        echo '<input type="hidden" name="action" value="eola_hills_content_update"><input type="hidden" name="restore" value="1">';
        submit_button( 'Restore previous page content', 'secondary', 'submit', false );
        echo '</form>';
    }
}

/** Invoked automatically by the single updater after the new theme has loaded. */
function eola_hills_sync_release() {
    if ( ! current_user_can( 'manage_options' ) || ! did_action( 'elementor/init' ) ) { return new WP_Error( 'not_ready', 'Elementor and administrator access are required to sync the website.' ); }
    if ( function_exists( 'eola_hills_ensure_elementor_kit' ) ) { eola_hills_ensure_elementor_kit(); }
    $id = (int) get_option( 'eola_hills_page_id' );
    if ( $id && get_post( $id ) ) {
        if ( EOLA_HILLS_CONTENT_VERSION === get_post_meta( $id, '_eola_hills_content_version', true ) || EOLA_HILLS_CONTENT_VERSION === get_post_meta( $id, '_eola_hills_restored_delivery', true ) ) { return true; }
        return eola_hills_update_content();
    }
    $id = eola_hills_create_page();
    if ( is_wp_error( $id ) ) { return $id; }
    if ( ! (int) get_option( 'page_on_front' ) ) {
        $result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
        if ( is_wp_error( $result ) ) { return $result; }
        update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $id );
    }
    return true;
}

add_action( 'after_switch_theme', function () { update_option( 'eola_hills_pending_theme_sync', EOLA_HILLS_VERSION, false ); } );
