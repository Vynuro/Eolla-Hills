<?php
/**
 * Plugin Name: Eola isolated GitHub mock (8086 test only)
 * Serves /work/artifacts/selftest/release-<tag>.json instead of the real GitHub release.
 */
if ( 'http://localhost:8086' !== get_option( 'siteurl' ) ) { return; }
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    $tag = get_option( 'eola_test_release' );
    if ( ! $tag ) { return $pre; }
    $dir = '/work/artifacts/selftest/';
    $release = json_decode( file_get_contents( $dir . 'release-' . $tag . '.json' ), true );
    $base = 'https://api.github.com/repos/malikazhar545/5eolahills/releases/';
    $reply = function ( $body ) { return array( 'response' => array( 'code' => 200 ), 'body' => $body, 'headers' => array(), 'cookies' => array() ); };
    if ( $url === $base . 'tags/client-preview' ) {
        return $reply( wp_json_encode( array( 'assets' => array_map( function ( $a ) { return array( 'id' => $a['id'], 'name' => $a['name'] ); }, $release['assets'] ) ) ) );
    }
    foreach ( $release['assets'] as $asset ) {
        if ( $url === $base . 'assets/' . $asset['id'] ) { return $reply( file_get_contents( $dir . $asset['file'] ) ); }
    }
    return $pre;
}, 10, 3 );
