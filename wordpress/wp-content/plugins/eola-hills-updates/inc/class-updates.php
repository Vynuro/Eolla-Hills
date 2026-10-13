<?php
/**
 * Plugin deployment built on the Taabeer updater approach.
 * Token encryption adapted from TAABEER Deployment Manager (GPL-2.0-or-later).
 */
defined( 'ABSPATH' ) || exit;
final class Eola_Hills_Updates {
    use Eola_Hills_Taabeer_Deployment;
    const PACKAGE_SLUG = 'eola-hills';
    const OPTION_BASELINE = 'eola_hills_baseline';
    const REPO = 'malikazhar545/5eolahills';
    const PLUGIN = 'eola-hills/eola-hills.php';
    const CACHE = 'eola_hills_release';
    private static $instance;
    public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct() {
        add_action( 'admin_menu', function () { add_management_page( 'Eola Hills Updates', 'Eola Hills Updates', 'update_plugins', 'eola-hills-updates', array( $this, 'screen' ) ); } );
        add_action( 'admin_post_eola_hills_updates', array( $this, 'handle' ) );
        add_action( 'eola_hills_release_check', function () { $this->release( true ); } );
        add_action( 'admin_init', array( $this, 'baseline' ) );
        add_action( 'admin_init', array( $this, 'complete_initial_setup' ), 30 );
    }
    public static function activate() {
        if ( ! wp_next_scheduled( 'eola_hills_release_check' ) ) { wp_schedule_event( time() + 300, 'twicedaily', 'eola_hills_release_check' ); }
        self::instance()->baseline();
    }
    public function baseline() {
        if ( ! current_user_can( 'update_plugins' ) ) { return; }
        if ( ! get_option( 'eola_hills_baseline' ) && is_file( WP_PLUGIN_DIR . '/' . self::PLUGIN ) ) {
            $this->record_plugin_baseline( $this->version() );
        }
    }
    private function token() {
        if ( defined( 'EOLA_HILLS_GITHUB_TOKEN' ) ) { return trim( EOLA_HILLS_GITHUB_TOKEN ); }
        $stored = (string) get_option( 'eola_hills_token', '' );
        return $stored ? $this->decrypt_token( $stored ) : '';
    }
    private function store_token( $token ) {
        $encrypted = $this->encrypt_token( $token );
        if ( ! $encrypted ) { return new WP_Error( 'crypto', 'Token could not be encrypted. Configure EOLA_HILLS_GITHUB_TOKEN in wp-config.php instead.' ); }
        update_option( 'eola_hills_token', $encrypted, false );
        return true;
    }
    /** Restrict credentials to GitHub API; do not forward them to asset redirects. */
    private function request( $url, $binary = false ) {
        $prefix = 'https://api.github.com/repos/' . self::REPO . '/releases/';
        if ( 0 !== strpos( $url, $prefix ) ) { return new WP_Error( 'source', 'Unexpected release source.' ); }
        $headers = array( 'Accept' => $binary ? 'application/octet-stream' : 'application/vnd.github+json', 'User-Agent' => 'Eola-Hills-Updates/1.0', 'X-GitHub-Api-Version' => '2022-11-28' );
        if ( $this->token() ) { $headers['Authorization'] = 'Bearer ' . $this->token(); }
        $response = wp_safe_remote_get( $url, array( 'headers' => $headers, 'timeout' => 45, 'redirection' => 0, 'limit_response_size' => 12 * MB_IN_BYTES ) );
        if ( is_wp_error( $response ) ) { return $response; }
        $code = wp_remote_retrieve_response_code( $response );
        if ( in_array( $code, array( 301, 302, 303, 307 ), true ) && $binary ) {
            $location = wp_remote_retrieve_header( $response, 'location' );
            $host = wp_parse_url( $location, PHP_URL_HOST );
            if ( 'https' !== wp_parse_url( $location, PHP_URL_SCHEME ) || ! in_array( $host, array( 'release-assets.githubusercontent.com', 'objects.githubusercontent.com' ), true ) ) { return new WP_Error( 'redirect', 'Unexpected asset redirect.' ); }
            $response = wp_safe_remote_get( $location, array( 'timeout' => 45, 'redirection' => 0, 'limit_response_size' => 12 * MB_IN_BYTES ) );
            if ( is_wp_error( $response ) ) { return $response; }
            $code = wp_remote_retrieve_response_code( $response );
        }
        if ( 200 !== $code ) { return new WP_Error( 'github', 'GitHub returned HTTP ' . $code . '. Check the release and repository access.' ); }
        return wp_remote_retrieve_body( $response );
    }
    private function asset( $release, $name ) {
        foreach ( $release['assets'] ?? array() as $asset ) {
            if ( $name === ( $asset['name'] ?? '' ) && ! empty( $asset['id'] ) ) { return 'https://api.github.com/repos/' . self::REPO . '/releases/assets/' . absint( $asset['id'] ); }
        }
        return '';
    }
    public function validate_manifest( $manifest ) {
        foreach ( array( 'slug', 'plugin', 'version', 'sha256', 'package_asset', 'requires_wp', 'requires_php' ) as $field ) {
            if ( ! is_array( $manifest ) || ! isset( $manifest[$field] ) || ! is_string( $manifest[$field] ) ) { return new WP_Error( 'manifest_field', 'Missing or invalid manifest field.' ); }
        }
        if ( ! is_array( $manifest ) || 'eola-hills' !== ( $manifest['slug'] ?? '' ) || self::PLUGIN !== ( $manifest['plugin'] ?? '' ) || ! preg_match( '/^\d+\.\d+\.\d+$/', $manifest['version'] ?? '' ) || ! preg_match( '/^[a-f0-9]{64}$/', $manifest['sha256'] ?? '' ) ) { return new WP_Error( 'manifest', 'Invalid Eola Hills update manifest.' ); }
        if ( 'eola-hills-' . $manifest['version'] . '.zip' !== ( $manifest['package_asset'] ?? '' ) ) { return new WP_Error( 'asset', 'Unexpected package name.' ); }
        if ( version_compare( get_bloginfo( 'version' ), $manifest['requires_wp'] ?? '6.8', '<' ) || version_compare( PHP_VERSION, $manifest['requires_php'] ?? '8.0', '<' ) ) { return new WP_Error( 'requirements', 'Update WordPress/PHP before installing this release.' ); }
        return $manifest;
    }
    public function release( $force = false ) {
        if ( ! $force && ( $cached = get_transient( self::CACHE ) ) ) { return $cached; }
        $body = $this->request( 'https://api.github.com/repos/' . self::REPO . '/releases/tags/client-preview' );
        if ( is_wp_error( $body ) ) { return $body; }
        $release = json_decode( $body, true );
        $manifest_url = $this->asset( $release, 'eola-hills-update.json' );
        if ( ! $manifest_url ) { return new WP_Error( 'manifest_missing', 'No Eola Hills manifest is published yet.' ); }
        $body = $this->request( $manifest_url, true );
        if ( is_wp_error( $body ) ) { return $body; }
        $manifest = $this->validate_manifest( json_decode( $body, true ) );
        if ( is_wp_error( $manifest ) ) { return $manifest; }
        $manifest['asset_url'] = $this->asset( $release, $manifest['package_asset'] );
        if ( ! $manifest['asset_url'] ) { return new WP_Error( 'package_missing', 'The release package is missing.' ); }
        set_transient( self::CACHE, $manifest, HOUR_IN_SECONDS );
        return $manifest;
    }
    public function validate_zip( $path, $manifest ) {
        if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'zip', 'The PHP Zip extension is required.' ); }
        if ( ! hash_equals( $manifest['sha256'], hash_file( 'sha256', $path ) ) ) { return new WP_Error( 'checksum', 'Package checksum verification failed.' ); }
        $zip = new ZipArchive();
        if ( true !== $zip->open( $path ) ) { return new WP_Error( 'zip', 'Cannot open the package.' ); }
        $seen = array(); $total = 0;
        try {
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $entry = $zip->statIndex( $i ); $name = $entry['name'];
                $total += $entry['size'];
                $zip->getExternalAttributesIndex( $i, $opsys, $attributes );
                if ( 0 !== strpos( $name, 'eola-hills/' ) || preg_match( '~(?:^|/)\.\.(?:/|$)|[\\\\:\x00]~', $name ) || isset( $seen[$name] ) || 0120000 === ( ( $attributes >> 16 ) & 0170000 ) || $total > 30 * MB_IN_BYTES || $zip->numFiles > 1000 ) { return new WP_Error( 'zip_path', 'Unsafe or oversized package.' ); }
                $seen[$name] = true;
            }
            $main = $zip->getFromName( self::PLUGIN );
            if ( ! $main || ! preg_match( '/^\s*\*?\s*Version:\s*' . preg_quote( $manifest['version'], '/' ) . '\s*$/m', $main ) || false === $zip->getFromName( 'eola-hills/templates/property.json' ) ) { return new WP_Error( 'zip_plugin', 'Package is not the expected Eola Hills plugin version.' ); }
        } finally { $zip->close(); }
        return true;
    }
    public function hashes() {
        $root = WP_PLUGIN_DIR . '/eola-hills';
        if ( ! is_dir( $root ) ) { return array(); }
        try { return $this->plugin_file_manifest( $root ); }
        catch ( Exception $error ) { return new WP_Error( 'files', 'Cannot inspect installed plugin files.' ); }
    }
    private function installed_plugin() {
        return new class {
            public function exists() { return is_file( WP_PLUGIN_DIR . '/eola-hills/eola-hills.php' ); }
            public function get( $field ) { return get_file_data( WP_PLUGIN_DIR . '/eola-hills/eola-hills.php', array( 'Version' => 'Version' ) )[$field] ?? ''; }
            public function get_stylesheet_directory() { return WP_PLUGIN_DIR . '/eola-hills'; }
        };
    }
    public function complete_initial_setup() {
        if ( ! current_user_can( 'manage_options' ) || ! get_option( 'eola_hills_pending_initial_setup' ) || ! function_exists( 'eola_hills_create_page' ) || ! did_action( 'elementor/init' ) ) { return; }
        eola_hills_ensure_elementor_kit();
        $id = eola_hills_create_page();
        if ( ! is_wp_error( $id ) ) { delete_option( 'eola_hills_pending_initial_setup' ); }
    }
    public function backup() {
        $hashes = $this->hashes();
        if ( is_wp_error( $hashes ) || ! $hashes ) { return $hashes; }
        if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'zip', 'The PHP Zip extension is required.' ); }
        $file = wp_tempnam( 'eola-backup' );
        if ( ! $file ) { return new WP_Error( 'backup', 'Could not create a code backup.' ); }
        $result = $this->zip_directory( WP_PLUGIN_DIR . '/eola-hills', $file );
        if ( is_wp_error( $result ) ) { @unlink( $file ); return $result; }
        if ( filesize( $file ) > 12 * MB_IN_BYTES ) { @unlink( $file ); return new WP_Error( 'backup_size', 'Code backup exceeds the supported size.' ); }
        $bytes = file_get_contents( $file ); @unlink( $file );
        $backup = array( 'created' => current_time( 'mysql' ), 'version' => $this->version(), 'sha256' => hash( 'sha256', $bytes ), 'data' => base64_encode( $bytes ) );
        update_option( 'eola_hills_code_backup', $backup, false );
        if ( get_option( 'eola_hills_code_backup' ) !== $backup ) { return new WP_Error( 'backup_store', 'Could not save the code backup.' ); }
        return true;
    }
    public function version() {
        $file = WP_PLUGIN_DIR . '/' . self::PLUGIN;
        return is_file( $file ) ? get_file_data( $file, array( 'Version' => 'Version' ) )['Version'] : '0.0.0';
    }
    /** Called only from authorized admin action; also exercised by local integration tests. */
    public function install( $manifest ) {
        if ( ! current_user_can( 'update_plugins' ) || ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) { return new WP_Error( 'permission', 'You cannot install plugin updates.' ); }
        $valid = $this->validate_manifest( $manifest );
        if ( is_wp_error( $valid ) ) { return $valid; }
        if ( ! version_compare( $manifest['version'], $this->version(), '>' ) ) { return new WP_Error( 'version', 'No newer version is available.' ); }
        $lock = get_option( 'eola_hills_install_lock' );
        if ( $lock && $lock < time() - 600 ) { delete_option( 'eola_hills_install_lock' ); }
        if ( ! add_option( 'eola_hills_install_lock', time(), '', false ) ) { return new WP_Error( 'locked', 'An installation is already running.' ); }
        $temp = '';
        try {
            $hashes = $this->hashes();
            if ( is_wp_error( $hashes ) ) { return $hashes; }
            $bytes = $this->request( $manifest['asset_url'] ?? '', true );
            if ( is_wp_error( $bytes ) ) { return $bytes; }
            $temp = wp_tempnam( 'eola-release' );
            if ( ! $temp || false === file_put_contents( $temp, $bytes ) ) { return new WP_Error( 'temp', 'Could not save the downloaded package.' ); }
            $valid = $this->validate_zip( $temp, $manifest );
            if ( is_wp_error( $valid ) ) { return $valid; }
            $conflicts = $this->find_local_plugin_conflicts( $temp );
            if ( is_wp_error( $conflicts ) ) { return $conflicts; }
            if ( $conflicts ) { return new WP_Error( 'conflict', 'Update would overwrite live plugin-file edits. Commit those edits first: ' . implode( ', ', array_slice( $conflicts, 0, 5 ) ) ); }
            $first_install = ! is_file( WP_PLUGIN_DIR . '/' . self::PLUGIN );
            if ( $first_install ) {
                $foundation = eola_hills_install_foundation_plugins();
                if ( is_wp_error( $foundation ) ) { return $foundation; }
            }
            if ( $hashes ) { $backup = $this->backup(); if ( is_wp_error( $backup ) ) { return $backup; } }
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            $active = is_plugin_active( self::PLUGIN );
            $upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
            $result = $upgrader->install( $temp, array( 'overwrite_package' => true ) );
            if ( is_wp_error( $result ) || ! $result ) { return is_wp_error( $result ) ? $result : new WP_Error( 'install', 'Installation failed. Download the retained code backup for recovery.' ); }
            // First installation activates the property plugin; updates preserve activation state.
            if ( ( $active || $first_install ) && ! is_plugin_active( self::PLUGIN ) ) {
                $activation = activate_plugin( self::PLUGIN );
                if ( is_wp_error( $activation ) ) { return $activation; }
            }
            $new_hashes = $this->hashes();
            if ( is_wp_error( $new_hashes ) ) { return $new_hashes; }
            $this->record_plugin_baseline( $manifest['version'] );
            if ( $first_install ) { update_option( 'eola_hills_pending_initial_setup', 1, false ); }
            update_option( 'eola_hills_last_install', array( 'version' => $manifest['version'], 'date' => current_time( 'mysql' ) ), false );
            delete_transient( self::CACHE );
            return true;
        } finally { if ( $temp && is_file( $temp ) ) { @unlink( $temp ); } delete_option( 'eola_hills_install_lock' ); }
    }
    public function handle() {
        if ( ! current_user_can( 'update_plugins' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'eola_hills_updates' );
        $action = sanitize_key( $_POST['operation'] ?? '' ); $result = true; $message = '';
        if ( 'save' === $action ) {
            if ( defined( 'EOLA_HILLS_GITHUB_TOKEN' ) ) { $result = new WP_Error( 'constant', 'The token is managed in wp-config.php.' ); }
            else { $token = trim( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) ); if ( $token ) { $result = $this->store_token( $token ); } }
            delete_transient( self::CACHE ); $message = 'Connection saved.';
        } elseif ( 'disconnect' === $action ) {
            if ( defined( 'EOLA_HILLS_GITHUB_TOKEN' ) ) { $result = new WP_Error( 'constant', 'Remove EOLA_HILLS_GITHUB_TOKEN from wp-config.php to disconnect it.' ); }
            else { delete_option( 'eola_hills_token' ); }
            delete_transient( self::CACHE ); $message = 'Saved token removed. Public releases remain available.';
        } elseif ( 'backup' === $action ) {
            $backup = get_option( 'eola_hills_code_backup' );
            if ( ! $backup ) { wp_die( 'No code backup has been created yet.' ); }
            $bytes = base64_decode( $backup['data'], true );
            if ( ! $bytes || ! hash_equals( $backup['sha256'], hash( 'sha256', $bytes ) ) ) { wp_die( 'Backup integrity check failed.' ); }
            nocache_headers(); header( 'Content-Type: application/zip' ); header( 'Content-Disposition: attachment; filename="eola-hills-backup.zip"' );
            echo $bytes; exit;
        } elseif ( in_array( $action, array( 'check', 'install' ), true ) ) {
            $result = $this->release( true );
            if ( ! is_wp_error( $result ) ) {
                if ( 'install' === $action ) { $result = $this->install( $result ); $message = 'Code installed. Saved Elementor pages were preserved.'; }
                else { $message = 'GitHub checked. Review the release below.'; }
            }
        } else { $result = new WP_Error( 'action', 'Unknown operation.' ); }
        set_transient( 'eola_hills_notice_' . get_current_user_id(), array( 'error' => is_wp_error( $result ), 'message' => is_wp_error( $result ) ? $result->get_error_message() : $message ), 60 );
        wp_safe_redirect( admin_url( 'tools.php?page=eola-hills-updates' ) ); exit;
    }
    private function form( $operation, $label, $primary = false ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 10px 10px 0">';
        wp_nonce_field( 'eola_hills_updates' );
        echo '<input type="hidden" name="operation" value="' . esc_attr( $operation ) . '"><input type="hidden" name="action" value="eola_hills_updates">';
        submit_button( $label, $primary ? 'primary' : 'secondary', 'submit', false ); echo '</form>';
    }
    public function screen() {
        if ( ! current_user_can( 'update_plugins' ) ) { return; }
        echo '<div class="wrap" style="max-width:900px"><h1>Eola Hills Updates</h1><p>Install reviewed property-plugin releases while preserving saved Elementor pages, media and settings.</p>';
        $notice = get_transient( 'eola_hills_notice_' . get_current_user_id() );
        if ( $notice ) { echo '<div class="notice ' . ( $notice['error'] ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>'; delete_transient( 'eola_hills_notice_' . get_current_user_id() ); }
        echo '<p>Repository: <code>' . esc_html( self::REPO ) . '</code> &middot; Channel: <code>client-preview</code></p><p>Installed property plugin: <strong>' . esc_html( $this->version() ) . '</strong></p>';
        $release = get_transient( self::CACHE );
        if ( $release ) { echo '<p>Available release: <strong>' . esc_html( $release['version'] ) . '</strong></p>'; }
        $this->form( 'check', 'Check GitHub' );
        if ( $release && version_compare( $release['version'], $this->version(), '>' ) ) { $this->form( 'install', '0.0.0' === $this->version() ? 'Install website and Elementor' : 'Back up and install release', true ); }
        if ( get_option( 'eola_hills_page_id' ) ) { echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=eola-hills' ) ) . '">Open website and Elementor editor</a></p>'; }
        if ( get_option( 'eola_hills_code_backup' ) ) { $this->form( 'backup', 'Download previous code backup' ); }
        echo '<h2>Private repository access</h2><p>Public repositories need no token. For a private repository, use a fine-grained GitHub token limited to this repository with Contents: Read-only. Tokens are encrypted and never displayed.</p>';
        echo '<p>Authentication: ' . ( $this->token() ? 'configured' : 'not configured' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'eola_hills_updates' );
        echo '<input type="hidden" name="action" value="eola_hills_updates"><input type="hidden" name="operation" value="save"><label>GitHub token <input type="password" name="token" autocomplete="new-password" value="" class="regular-text"></label>';
        submit_button( 'Save connection' ); echo '</form>'; $this->form( 'disconnect', 'Remove saved token' );
        echo '<h2>Recovery and content</h2><p>Before replacement, the previous plugin ZIP is saved privately in the database for administrator download. Restore it using Plugins &rarr; Add New &rarr; Upload Plugin. Only the most recent code backup is retained. Database/media backups are separate.</p><p>Direct plugin-file changes block deployment. Elementor edits do not. New template designs do not overwrite existing pages. This updater itself can be updated using its release ZIP.</p></div>';
    }
}
