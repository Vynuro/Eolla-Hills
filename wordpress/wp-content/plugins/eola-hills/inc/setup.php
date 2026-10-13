<?php
defined( 'ABSPATH' ) || exit;

/** Import bundled demo media once so every image uses the normal Media Library. */
function eola_hills_prepare_template() {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $template = json_decode( str_replace( '{{EOLA_ASSETS}}', untrailingslashit( EOLA_HILLS_URL ) . '/assets', file_get_contents( EOLA_HILLS_DIR . 'templates/property.json' ) ), true );
    if ( empty( $template['content'] ) ) { return new WP_Error( 'template_missing', 'The property template could not be read.' ); }
    $media = (array) get_option( 'eola_hills_demo_media', array() );
    foreach ( glob( EOLA_HILLS_DIR . 'assets/photos/*.webp' ) as $source ) {
        $name = basename( $source, '.webp' );
        if ( ! empty( $media[$name] ) && get_post( $media[$name] ) ) { continue; }
        $upload = wp_upload_bits( 'eola-demo-' . $name . '.webp', null, file_get_contents( $source ) );
        if ( $upload['error'] ) { return new WP_Error( 'media_upload', $upload['error'] ); }
        $id = wp_insert_attachment( array( 'post_title' => ucwords( $name ), 'post_mime_type' => 'image/webp', 'post_content' => 'Temporary stock photo for local design review. Not a photograph of the listed property; replace with approved client media before launch.', 'post_status' => 'inherit' ), $upload['file'], 0, true );
        if ( is_wp_error( $id ) ) { return $id; }
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
        update_post_meta( $id, '_wp_attachment_image_alt', ucwords( $name ) );
        update_post_meta( $id, '_eola_hills_demo', 1 );
        $media[$name] = $id;
        update_option( 'eola_hills_demo_media', $media, false );
    }
    $replace = function ( &$value ) use ( &$replace, $media ) {
        if ( ! is_array( $value ) ) { return; }
        if ( isset( $value['url'] ) && preg_match( '~/assets/photos/([a-z-]+)\.webp$~', $value['url'], $match ) && isset( $media[$match[1]] ) ) {
            $value['id'] = $media[$match[1]]; $value['url'] = wp_get_attachment_url( $value['id'] );
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
        <h2>Editing and publishing</h2>
        <p>Open Elementor's Structure panel to find named sections. Header, footer, text, images, buttons, colours, typography and responsive spacing are native editable elements. Duplicate a page or save sections as Elementor templates for future pages.</p>
        <p>The bundled stock photographs are for design review and are not photos of this listing. Replace them through the Media Library before launch. Replace the native image carousel with Elementor's Video widget when the professional video is available. Confirm listing details, agent contacts and branding before publishing.</p>
        <p>Tour buttons currently open Linda's email composer. Edit their links to use a booking destination if supplied. No emails are sent automatically.</p>
        <p>The draft does not change your site's theme or homepage. After review, publish it and optionally select it in Settings &rarr; Reading as the homepage.</p>
        <h2>Code updates</h2>
        <p>Install Eola Hills Updates to check and install GitHub releases from WordPress. Saved Elementor content stays in the database; a code update never recreates this page. Keep database and media backups separately.</p>
        <p>Analytics is not connected yet. Add the client-owned measurement setup when access and tracking preferences are available.</p>
    </div>
    <?php
}
