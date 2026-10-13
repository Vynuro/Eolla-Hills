<?php
/**
 * Plugin Name: Eola Hills Property
 * Description: An editable Elementor property landing page for Eola Hills.
 * Version: 1.3.1
 * Requires at least: 6.8
 * Requires PHP: 8.0
 * Requires Plugins: elementor
 * Author: Eola Hills
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/malikazhar545/5eolahills
 * Text Domain: eola-hills
 */
defined( 'ABSPATH' ) || exit;
define( 'EOLA_HILLS_VERSION', '1.3.1' );
define( 'EOLA_HILLS_CONTENT_VERSION', 'client-2026-10-13-1' );
define( 'EOLA_HILLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EOLA_HILLS_URL', plugin_dir_url( __FILE__ ) );
require_once EOLA_HILLS_DIR . 'inc/setup.php';
require_once EOLA_HILLS_DIR . 'inc/content-update.php';

// Keep the enhanced carousel dimensions editable in Elementor, including mobile.
add_action( 'elementor/element/image-carousel/section_style_image/before_section_end', function ( $element ) {
    $element->add_responsive_control( 'eola_image_height', array(
        'label' => 'Photo height', 'type' => \Elementor\Controls_Manager::SLIDER,
        'size_units' => array( 'px', 'vh' ),
        'range' => array( 'px' => array( 'min' => 100, 'max' => 1000 ), 'vh' => array( 'min' => 10, 'max' => 100 ) ),
        'selectors' => array( '{{WRAPPER}} .swiper-slide-image' => 'width:100%; height:{{SIZE}}{{UNIT}}; object-fit:cover;' ),
    ) );
} );

function eola_hills_is_page() {
    return is_singular( 'page' ) && get_post_meta( get_queried_object_id(), '_eola_hills_page', true );
}
add_action( 'wp_enqueue_scripts', function () {
    if ( ! eola_hills_is_page() ) { return; }
    wp_enqueue_style( 'eola-hills', EOLA_HILLS_URL . 'assets/page.css', array(), EOLA_HILLS_VERSION );
    wp_enqueue_script( 'eola-hills', EOLA_HILLS_URL . 'assets/page.js', array(), EOLA_HILLS_VERSION, true );
} );
add_filter( 'elementor/fonts/additional_fonts', function ( $fonts ) {
    $fonts['Eola Display'] = 'system'; $fonts['Eola Sans'] = 'system'; return $fonts;
} );
add_action( 'elementor/editor/after_enqueue_styles', function () {
    wp_enqueue_style( 'eola-hills-editor-fonts', EOLA_HILLS_URL . 'assets/page.css', array(), EOLA_HILLS_VERSION );
} );
add_filter( 'body_class', function ( $classes ) {
    if ( eola_hills_is_page() ) { $classes[] = 'eola-hills-page'; }
    return $classes;
} );

// Fallback metadata only when an SEO plugin is not responsible for it.
function eola_hills_has_seo() {
    return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}
add_filter( 'pre_get_document_title', function ( $title ) {
    return eola_hills_is_page() && ! eola_hills_has_seo() ? get_post_meta( get_queried_object_id(), '_eola_hills_seo_title', true ) ?: $title : $title;
} );
add_action( 'wp_head', function () {
    if ( ! eola_hills_is_page() || eola_hills_has_seo() ) { return; }
    $description = get_post_meta( get_queried_object_id(), '_eola_hills_seo_description', true );
    if ( $description ) { echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n"; }
} );

add_action( 'add_meta_boxes_page', function () {
    add_meta_box( 'eola-hills-seo', 'Eola Hills search preview', function ( $post ) {
        if ( ! get_post_meta( $post->ID, '_eola_hills_page', true ) ) { echo '<p>These settings apply to pages created with Eola Hills.</p>'; return; }
        wp_nonce_field( 'eola_hills_seo', 'eola_hills_seo_nonce' );
        foreach ( array( 'title' => 'SEO title', 'description' => 'Meta description' ) as $key => $label ) {
            echo '<p><label>' . esc_html( $label ) . '<br><input class="widefat" name="eola_seo_' . esc_attr( $key ) . '" value="' . esc_attr( get_post_meta( $post->ID, '_eola_hills_seo_' . $key, true ) ) . '"></label></p>';
        }
        echo '<p>If an SEO plugin is active, manage search metadata in that plugin.</p>';
    } );
} );
add_action( 'save_post_page', function ( $id ) {
    if ( ! isset( $_POST['eola_hills_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eola_hills_seo_nonce'] ) ), 'eola_hills_seo' ) || ! current_user_can( 'edit_post', $id ) || wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) { return; }
    foreach ( array( 'title', 'description' ) as $key ) {
        if ( isset( $_POST[ 'eola_seo_' . $key ] ) ) { update_post_meta( $id, '_eola_hills_seo_' . $key, sanitize_text_field( wp_unslash( $_POST[ 'eola_seo_' . $key ] ) ) ); }
    }
} );

// Version changes invalidate generated CSS only. They never reseed saved page data.
add_action( 'admin_init', function () {
    if ( current_user_can( 'manage_options' ) && did_action( 'elementor/loaded' ) && get_option( 'eola_hills_code_version' ) !== EOLA_HILLS_VERSION ) {
        \Elementor\Plugin::instance()->files_manager->clear_cache();
        update_option( 'eola_hills_code_version', EOLA_HILLS_VERSION );
    }
} );
