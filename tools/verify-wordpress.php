<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'http://localhost:8086' !== get_option( 'siteurl' ) ) { throw new RuntimeException( 'Run only against the isolated update-test instance.' ); }
function check_eola( $value, $message ) { if ( ! $value ) { WP_CLI::error( $message ); } WP_CLI::log( 'PASS: ' . $message ); }
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$updates = Eola_Hills_Updates::instance();
$source_version = $updates->version();
$id = eola_hills_create_page();
check_eola( ! is_wp_error( $id ) && 'draft' === get_post_status( $id ), 'Initial setup creates a draft.' );
check_eola( 'page' !== get_option( 'show_on_front' ), 'Setup does not change the homepage.' );
$elements = json_decode( get_post_meta( $id, '_elementor_data', true ), true );
$elements[0]['settings']['text'] = 'Client edited skip link';
$elements[1]['settings']['padding_mobile'] = array( 'unit'=>'px', 'top'=>'31', 'right'=>'21', 'bottom'=>'27', 'left'=>'19', 'isLinked'=>false );
$elements[1]['settings']['background_color'] = '#eeeadd';
\Elementor\Plugin::instance()->documents->get( $id )->save( array( 'elements' => $elements ) );
$before = get_post_meta( $id, '_elementor_data', true );
$post_before = get_post( $id )->post_content;
check_eola( $id === eola_hills_create_page() && $before === get_post_meta( $id, '_elementor_data', true ), 'Repeat setup preserves client text, colour and responsive spacing.' );
$manifest = json_decode( file_get_contents( '/work/artifacts/update-test.json' ), true );
$package = '/work/artifacts/' . basename( $manifest['fixture_file'] );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( $manifest, $package ) {
    if ( $url === $manifest['asset_url'] ) { return array( 'response'=>array('code'=>200,'message'=>'OK'), 'body'=>file_get_contents($package), 'headers'=>array(), 'cookies'=>array() ); }
    return $pre;
}, 10, 3 );
check_eola( true === $updates->validate_zip( $package, $manifest ), 'Valid next-version package passes archive validation.' );
$bad = $manifest; $bad['sha256'] = str_repeat( '0', 64 );
check_eola( is_wp_error( $updates->validate_zip( $package, $bad ) ), 'Corrupt checksum is rejected.' );
$bad = $manifest; $bad['slug'] = 'taabeer';
check_eola( is_wp_error( $updates->validate_manifest( $bad ) ), 'Wrong project manifest is rejected.' );
$bad = $manifest; $bad['version'] = '1.0.2';
check_eola( is_wp_error( $updates->validate_zip( $package, $bad ) ), 'Package version mismatch is rejected.' );
$unsafe = wp_tempnam( 'eola-unsafe' ); copy( $package, $unsafe );
$zip = new ZipArchive(); $zip->open( $unsafe ); $zip->addFromString( 'eola-hills/../escape.php', '<?php exit;' ); $zip->close();
$bad = $manifest; $bad['sha256'] = hash_file( 'sha256', $unsafe );
check_eola( is_wp_error( $updates->validate_zip( $unsafe, $bad ) ), 'Archive path traversal is rejected.' ); unlink( $unsafe );
wp_set_current_user( 0 );
check_eola( 'permission' === $updates->install( $manifest )->get_error_code(), 'Unauthenticated deployment is rejected.' );
wp_set_current_user( get_user_by( 'login', 'eola_admin' )->ID );
$updates->baseline();
$css_path = WP_PLUGIN_DIR . '/eola-hills/assets/page.css'; $css = file_get_contents( $css_path );
file_put_contents( $css_path, $css . '\n/* Live file edit */' );
check_eola( 'conflict' === $updates->install( $manifest )->get_error_code(), 'Direct live file changes block deployment.' );
file_put_contents( $css_path, $css );
$result = $updates->install( $manifest );
check_eola( true === $result, 'WordPress installs the verified release: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'success' ) );
check_eola( $manifest['version'] === $updates->version(), 'Installed package version changes to the fixture version.' );
check_eola( is_plugin_active( Eola_Hills_Updates::PLUGIN ), 'Plugin activation survives the update.' );
check_eola( $before === get_post_meta( $id, '_elementor_data', true ) && $post_before === get_post( $id )->post_content, 'The real code update preserves edited Elementor data and page content byte-for-byte.' );
$backup = get_option( 'eola_hills_code_backup' );
check_eola( $source_version === $backup['version'] && hash_equals( $backup['sha256'], hash( 'sha256', base64_decode( $backup['data'] ) ) ), 'A verified previous-version code backup is retained privately.' );
check_eola( ! get_option( 'eola_hills_install_lock' ), 'Installation lock is released.' );
check_eola( 'version' === $updates->install( $manifest )->get_error_code(), 'Reinstalling the same release is blocked.' );
WP_CLI::success( 'WordPress integration and real plugin-update checks passed.' );
