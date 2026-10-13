<?php
defined( 'ABSPATH' ) || exit;

/** Import reviewed client media without replacing attachments used by saved pages. */
function eola_hills_prepare_template() {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $template = json_decode( str_replace( '{{EOLA_ASSETS}}', untrailingslashit( EOLA_HILLS_URL ) . '/assets', file_get_contents( EOLA_HILLS_DIR . 'templates/property.json' ) ), true );
    $manifest = json_decode( file_get_contents( EOLA_HILLS_DIR . 'assets/media/manifest.json' ), true );
    if ( empty( $template['content'] ) || ! is_array( $manifest ) ) { return new WP_Error( 'template_missing', 'The property template or media manifest could not be read.' ); }
    $media = (array) get_option( 'eola_hills_client_media', array() );
    $urls = array();
    foreach ( $manifest as $key => $item ) {
        $file = basename( $item['file'] );
        $source = EOLA_HILLS_DIR . 'assets/media/' . $file;
        if ( ! is_file( $source ) || ! hash_equals( $item['sha256'], hash_file( 'sha256', $source ) ) ) { return new WP_Error( 'media_integrity', 'A bundled client asset did not pass its integrity check.' ); }
        $id = ! empty( $media[$key]['id'] ) ? (int) $media[$key]['id'] : 0;
        if ( ! $id || ! get_post( $id ) || ! is_file( (string) get_attached_file( $id ) ) || ( $media[$key]['sha256'] ?? '' ) !== $item['sha256'] ) {
            $upload = wp_upload_bits( 'eola-' . substr( $item['sha256'], 0, 10 ) . '-' . $file, null, file_get_contents( $source ) );
            if ( $upload['error'] ) { return new WP_Error( 'media_upload', $upload['error'] ); }
            $mime = wp_check_filetype( $file )['type'];
            $id = wp_insert_attachment( array( 'post_title' => $item['title'], 'post_mime_type' => $mime, 'post_content' => $item['description'], 'post_status' => 'inherit' ), $upload['file'], 0, true );
            if ( is_wp_error( $id ) ) { return $id; }
            if ( strpos( $mime, 'image/' ) === 0 ) {
                // Keep supplied plans intact; only derivative thumbnails are generated.
                add_filter( 'big_image_size_threshold', '__return_false' );
                $metadata = wp_generate_attachment_metadata( $id, $upload['file'] );
                remove_filter( 'big_image_size_threshold', '__return_false' );
                wp_update_attachment_metadata( $id, $metadata );
            }
            update_post_meta( $id, '_wp_attachment_image_alt', $item['alt'] );
            update_post_meta( $id, '_eola_hills_client_asset', $key );
            $media[$key] = array( 'id' => $id, 'sha256' => $item['sha256'] );
            update_option( 'eola_hills_client_media', $media, false );
        }
        $urls[ EOLA_HILLS_URL . 'assets/media/' . $file ] = array( 'id' => $id, 'url' => wp_get_attachment_url( $id ) );
    }
    $replace = function ( &$value ) use ( &$replace, $urls ) {
        if ( ! is_array( $value ) ) { return; }
        if ( isset( $value['url'], $urls[$value['url']] ) ) {
            $asset = $urls[$value['url']];
            $value['url'] = $asset['url'];
            if ( array_key_exists( 'id', $value ) ) { $value['id'] = $asset['id']; }
        }
        foreach ( $value as &$child ) { if ( is_array( $child ) ) { $replace( $child ); } }
    };
    $replace( $template['content'] );
    return $template;
}

/** Explicit, repeat-safe creation. Existing pages are never overwritten. */
function eola_hills_create_page() {
    if ( ! did_action( 'elementor/loaded' ) ) { return new WP_Error( 'elementor_missing', 'Install and activate Elementor Free first.' ); }
    $existing = (int) get_option( 'eola_hills_page_id' );
    if ( $existing && get_post( $existing ) ) {
        return 'trash' === get_post_status( $existing ) ? new WP_Error( 'page_trashed', 'Restore the existing Eola Hills page from Trash before continuing.' ) : $existing;
    }
    // A first activation may not have a global kit yet (notably via WP-CLI).
    $kits = \Elementor\Plugin::instance()->kits_manager;
    $kit_id = (int) get_option( 'elementor_active_kit' );
    if ( ! $kit_id || ! get_post( $kit_id ) || ! $kits->is_kit( $kit_id ) || 'trash' === get_post_status( $kit_id ) ) {
        $kit_id = $kits->create_default();
        if ( ! $kit_id || is_wp_error( $kit_id ) ) { return new WP_Error( 'kit_missing', 'Elementor could not create its global settings kit.' ); }
        update_option( 'elementor_active_kit', $kit_id );
    }
    $template = eola_hills_prepare_template();
    if ( is_wp_error( $template ) ) { return $template; }
    $content = $template['content'];
    $id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => '9055 SE Eola Hills Rd', 'post_name' => '9055-se-eola-hills-rd', 'post_status' => 'draft' ), true );
    if ( is_wp_error( $id ) ) { return $id; }
    update_post_meta( $id, '_elementor_edit_mode', 'builder' );
    update_post_meta( $id, '_elementor_template_type', 'wp-page' );
    update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
    $document = \Elementor\Plugin::instance()->documents->get( $id );
    if ( ! $document || ! $document->save( array( 'elements' => $content, 'settings' => $template['page_settings'] ) ) ) {
        wp_delete_post( $id, true );
        return new WP_Error( 'template_save', 'Elementor could not save the page. No existing content was changed.' );
    }
    update_post_meta( $id, '_eola_hills_page', 1 );
    update_post_meta( $id, '_eola_hills_seed_version', EOLA_HILLS_VERSION );
    update_post_meta( $id, '_eola_hills_content_version', EOLA_HILLS_CONTENT_VERSION );
    update_post_meta( $id, '_eola_hills_seo_title', '9055 SE Eola Hills Rd, Amity OR | Eola Hills Luxury Property' );
    update_post_meta( $id, '_eola_hills_seo_description', 'Explore 9055 SE Eola Hills Rd in Amity, Oregon. A distinctive 4.89-acre property with a custom home, barn, workshop, outdoor living and scenic surroundings.' );
    update_option( 'eola_hills_page_id', $id );
    return $id;
}

add_action( 'admin_menu', function () {
    add_menu_page( 'Eola Hills', 'Eola Hills', 'manage_options', 'eola-hills', 'eola_hills_admin', 'dashicons-admin-home', 58 );
} );
add_action( 'admin_post_eola_hills_setup', function () {
    if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_pages' ) ) { wp_die( 'You cannot create this page.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'eola_hills_setup' );
    $result = eola_hills_create_page();
    if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 'Page setup', array( 'back_link' => true ) ); }
    wp_safe_redirect( admin_url( 'admin.php?page=eola-hills' ) ); exit;
} );
function eola_hills_admin() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $id = (int) get_option( 'eola_hills_page_id' );
    ?>
    <div class="wrap" style="max-width:900px">
        <h1>Eola Hills property page</h1>
        <p>A complete property page built from native Elementor Free containers, headings, text, images and buttons.</p>
        <?php if ( $id && get_post( $id ) ) : ?>
            <p><strong>Page status: <?php echo esc_html( get_post_status( $id ) ); ?></strong></p>
            <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=elementor' ) ); ?>">Edit with Elementor</a>
            <a class="button" href="<?php echo esc_url( get_preview_post_link( $id ) ); ?>">Preview page</a>
            <a class="button" href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">Page and SEO settings</a></p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="eola_hills_setup">
                <?php wp_nonce_field( 'eola_hills_setup' ); ?>
                <?php submit_button( 'Create draft landing page', 'primary', 'submit', false ); ?>
            </form>
        <?php endif; ?>
        <?php eola_hills_content_update_panel( $id ); ?>
        <h2>Editing and publishing</h2>
        <p>Open Elementor's Structure panel to find named sections. Header, footer, text, images, buttons, colours, typography and responsive spacing are native editable elements. Duplicate a page or save sections as Elementor templates for future pages.</p>
        <p>The bundled media, logos, amenities and floor plans are supplied by the client. The listed living area is 3,018 sq ft; the original floor plans show different totals. The professional video has not yet been supplied. Every media item is editable through the Media Library and Elementor.</p>
        <p>Tour buttons currently open Linda's email composer. Edit their links to use a booking destination if supplied. No emails are sent automatically.</p>
        <p>The draft does not change your site's theme or homepage. After review, publish it and optionally select it in Settings &rarr; Reading as the homepage.</p>
        <h2>Code updates</h2>
        <p>Install Eola Hills Updates to check and install GitHub releases from WordPress. Saved Elementor content stays in the database; a code update never recreates this page. Keep database and media backups separately.</p>
        <p>Analytics is not connected yet. Add the client-owned measurement setup when access and tracking preferences are available.</p>
    </div>
    <?php
}
