<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:8086' !== get_option( 'siteurl' ) ) { throw new RuntimeException( 'Isolated local update-test instance only.' ); }
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$manifest = json_decode( file_get_contents( '/work/artifacts/eola-hills-update.json' ), true );
$manifest['asset_url'] = 'https://api.github.com/repos/malikazhar545/5eolahills/releases/assets/999998';
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( $manifest ) {
    if ( $url === $manifest['asset_url'] ) { return array( 'response'=>array('code'=>200,'message'=>'OK'), 'body'=>file_get_contents('/work/artifacts/eola-hills-1.0.0.zip'), 'headers'=>array(), 'cookies'=>array() ); }
    return $pre;
}, 10, 3 );
$result = Eola_Hills_Updates::instance()->install( $manifest );
if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
if ( ! is_plugin_active( 'elementor/elementor.php' ) || ! is_plugin_active( 'eola-hills/eola-hills.php' ) || ! get_option( 'eola_hills_pending_initial_setup' ) ) { WP_CLI::error( 'Bootstrap did not activate dependencies and queue page setup.' ); }
WP_CLI::success( 'One installer installed/activated Elementor and the property package; page setup is queued for the next request.' );
