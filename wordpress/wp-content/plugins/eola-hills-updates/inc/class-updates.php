<?php
/** Theme deployment and migration, using Taabeer's backup/encryption routines. */
defined( 'ABSPATH' ) || exit;
final class Eola_Hills_Updates {
    use Eola_Hills_Taabeer_Deployment;
    const THEME_SLUG = 'eola-hills';
    const LEGACY_PLUGIN = 'eola-hills/eola-hills.php';
    const OPTION_BASELINE = 'eola_hills_theme_baseline';
    const REPO = 'malikazhar545/5eolahills';
    const CACHE = 'eola_hills_theme_release';
    private static $instance;
    public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct() {
        add_action( 'admin_menu', function () { add_management_page( 'Eola Hills Updates', 'Eola Hills Updates', 'manage_options', 'eola-hills-updates', array( $this, 'screen' ) ); } );
        add_action( 'admin_post_eola_hills_updates', array( $this, 'handle' ) );
        add_action( 'eola_hills_release_check', function () { $this->release( true ); } );
        add_action( 'admin_init', array( $this, 'complete_sync' ), 70 );
    }
    public static function activate() { if ( ! wp_next_scheduled( 'eola_hills_release_check' ) ) { wp_schedule_event( time() + 300, 'hourly', 'eola_hills_release_check' ); } }
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
        $headers = array( 'Accept' => $binary ? 'application/octet-stream' : 'application/vnd.github+json', 'User-Agent' => 'Eola-Hills-Updates/2.0', 'X-GitHub-Api-Version' => '2022-11-28' );
        if ( $this->token() ) { $headers['Authorization'] = 'Bearer ' . $this->token(); }
        $response = wp_safe_remote_get( $url, array( 'headers' => $headers, 'timeout' => 90, 'redirection' => 0, 'limit_response_size' => 64 * MB_IN_BYTES ) );
        if ( is_wp_error( $response ) ) { return $response; }
        $code = wp_remote_retrieve_response_code( $response );
        if ( in_array( $code, array( 301, 302, 303, 307 ), true ) && $binary ) {
            $location = wp_remote_retrieve_header( $response, 'location' );
            $host = wp_parse_url( $location, PHP_URL_HOST );
            if ( 'https' !== wp_parse_url( $location, PHP_URL_SCHEME ) || ! in_array( $host, array( 'release-assets.githubusercontent.com', 'objects.githubusercontent.com' ), true ) ) { return new WP_Error( 'redirect', 'Unexpected asset redirect.' ); }
            $response = wp_safe_remote_get( $location, array( 'timeout' => 90, 'redirection' => 0, 'limit_response_size' => 64 * MB_IN_BYTES ) );
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
        foreach ( array( 'theme', 'version', 'sha256', 'package_asset', 'requires_wp', 'requires_php' ) as $field ) {
            if ( ! is_array( $manifest ) || ! isset( $manifest[$field] ) || ! is_string( $manifest[$field] ) ) { return new WP_Error( 'manifest', 'Invalid theme release manifest.' ); }
        }
        if ( self::THEME_SLUG !== $manifest['theme'] || ! preg_match( '/^\d+\.\d+\.\d+$/', $manifest['version'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $manifest['sha256'] ) || 'eola-hills-theme-' . $manifest['version'] . '.zip' !== $manifest['package_asset'] ) { return new WP_Error( 'manifest', 'Unexpected theme, version or package.' ); }
        if ( version_compare( get_bloginfo( 'version' ), $manifest['requires_wp'], '<' ) || version_compare( PHP_VERSION, $manifest['requires_php'], '<' ) ) { return new WP_Error( 'requirements', 'Update WordPress/PHP before installing this release.' ); }
        return $manifest;
    }
    public function release( $force = false ) {
        if ( ! $force && ( $cached = get_transient( self::CACHE ) ) ) { return $cached; }
        $body = $this->request( 'https://api.github.com/repos/' . self::REPO . '/releases/tags/client-preview' );
        if ( is_wp_error( $body ) ) { return $body; }
        $release = json_decode( $body, true );
        $url = $this->asset( $release, 'eola-hills-theme-update.json' );
        if ( ! $url ) { return new WP_Error( 'manifest_missing', 'The theme release has not been published yet.' ); }
        $body = $this->request( $url, true );
        if ( is_wp_error( $body ) ) { return $body; }
        $manifest = $this->validate_manifest( json_decode( $body, true ) );
        if ( is_wp_error( $manifest ) ) { return $manifest; }
        $manifest['asset_url'] = $this->asset( $release, $manifest['package_asset'] );
        if ( ! $manifest['asset_url'] ) { return new WP_Error( 'package_missing', 'The theme package is missing.' ); }
        set_transient( self::CACHE, $manifest, 15 * MINUTE_IN_SECONDS );
        return $manifest;
    }
    public function validate_zip( $path, $manifest ) {
        if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'zip', 'The PHP Zip extension is required.' ); }
        if ( ! hash_equals( $manifest['sha256'], hash_file( 'sha256', $path ) ) ) { return new WP_Error( 'checksum', 'Theme package checksum verification failed.' ); }
        $zip = new ZipArchive();
        if ( true !== $zip->open( $path ) ) { return new WP_Error( 'zip', 'Cannot open the theme package.' ); }
        $seen = array(); $total = 0;
        try {
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $entry = $zip->statIndex( $i ); $name = $entry['name']; $total += $entry['size'];
                $zip->getExternalAttributesIndex( $i, $opsys, $attributes );
                if ( 0 !== strpos( $name, 'eola-hills/' ) || preg_match( '~(?:^|/)\.\.(?:/|$)|[\\\\:\x00]~', $name ) || isset( $seen[$name] ) || 0120000 === ( ( $attributes >> 16 ) & 0170000 ) || $total > 128 * MB_IN_BYTES || $zip->numFiles > 2000 ) { return new WP_Error( 'zip_path', 'Unsafe or oversized theme package.' ); }
                $seen[$name] = true;
            }
            $style = $zip->getFromName( 'eola-hills/style.css' );
            if ( ! $style || ! preg_match( '/^\s*\*?\s*Version:\s*' . preg_quote( $manifest['version'], '/' ) . '\s*$/m', $style ) ) { return new WP_Error( 'zip_theme', 'Theme version does not match the release.' ); }
            foreach ( array( 'functions.php', 'index.php', 'templates/property.json', 'assets/media/manifest.json' ) as $file ) { if ( false === $zip->getFromName( 'eola-hills/' . $file ) ) { return new WP_Error( 'zip_theme', 'The theme package is incomplete.' ); } }
        } finally { $zip->close(); }
        return true;
    }
    public function version() { return wp_get_theme( self::THEME_SLUG )->exists() ? wp_get_theme( self::THEME_SLUG )->get( 'Version' ) : '0.0.0'; }
    public function backup( $legacy = false ) {
        $source = $legacy ? WP_PLUGIN_DIR . '/eola-hills' : get_theme_root( self::THEME_SLUG ) . '/eola-hills';
        if ( ! is_dir( $source ) ) { return true; }
        $files = $this->theme_file_manifest( $source );
        if ( is_wp_error( $files ) ) { return $files; }
        $temp = wp_tempnam( 'eola-theme-backup' );
        if ( ! $temp ) { return new WP_Error( 'backup', 'Cannot create a backup file.' ); }
        try {
            $result = $this->zip_directory( $source, $temp );
            if ( is_wp_error( $result ) ) { return $result; }
            $bytes = file_get_contents( $temp );
            if ( ! $bytes ) { return new WP_Error( 'backup', 'The code backup is empty.' ); }
            $backup = array( 'created' => current_time( 'mysql', true ), 'version' => $legacy ? 'legacy-plugin' : $this->version(), 'sha256' => hash( 'sha256', $bytes ), 'data' => base64_encode( $bytes ) );
            $option = $legacy ? 'eola_hills_legacy_backup' : 'eola_hills_theme_backup';
            update_option( $option, $backup, false );
            if ( get_option( $option ) !== $backup ) { return new WP_Error( 'backup_store', 'The code backup could not be saved. No files were replaced.' ); }
            return true;
        } finally { @unlink( $temp ); }
    }
    /** Git is authoritative for theme files; every replacement is backed up first. */
    public function install( $manifest ) {
        if ( ! current_user_can( 'install_themes' ) || ! current_user_can( 'switch_themes' ) || ! current_user_can( 'activate_plugins' ) || ! current_user_can( 'manage_options' ) ) { return new WP_Error( 'permission', 'Administrator installation access is required.' ); }
        $valid = $this->validate_manifest( $manifest ); if ( is_wp_error( $valid ) ) { return $valid; }
        if ( version_compare( $manifest['version'], $this->version(), '<' ) ) { return new WP_Error( 'version', 'An older theme release cannot replace this version.' ); }
        $lock = (int) get_option( 'eola_hills_install_lock' );
        if ( $lock && time() - $lock > 900 ) { delete_option( 'eola_hills_install_lock' ); }
        if ( ! add_option( 'eola_hills_install_lock', time(), '', false ) ) { return new WP_Error( 'locked', 'A website sync is already running.' ); }
        $temp = '';
        try {
            wp_raise_memory_limit( 'admin' ); if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 240 ); }
            $foundation = eola_hills_install_foundation_plugins(); if ( is_wp_error( $foundation ) ) { return $foundation; }
            if ( version_compare( $manifest['version'], $this->version(), '>' ) ) {
                $bytes = $this->request( $manifest['asset_url'] ?? '', true ); if ( is_wp_error( $bytes ) ) { return $bytes; }
                $temp = wp_tempnam( 'eola-theme-release' );
                if ( ! $temp || false === file_put_contents( $temp, $bytes ) ) { return new WP_Error( 'temp', 'Could not save the theme package.' ); }
                $valid = $this->validate_zip( $temp, $manifest ); if ( is_wp_error( $valid ) ) { return $valid; }
                $backup = $this->backup(); if ( is_wp_error( $backup ) ) { return $backup; }
                if ( is_file( WP_PLUGIN_DIR . '/' . self::LEGACY_PLUGIN ) ) {
                    $backup = $this->backup( true ); if ( is_wp_error( $backup ) ) { return $backup; }
                    update_option( 'eola_hills_retire_legacy', 1, false );
                }
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                $upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
                $result = $upgrader->install( $temp, array( 'overwrite_package' => true ) );
                if ( is_wp_error( $result ) || ! $result ) { return is_wp_error( $result ) ? $result : new WP_Error( 'install', 'Theme installation failed. The code backup is retained.' ); }
                wp_clean_themes_cache( true );
                if ( $manifest['version'] !== $this->version() ) { return new WP_Error( 'version', 'Installed theme version does not match the release.' ); }
                $this->record_theme_baseline( $manifest['version'] );
            }
            // The old plugin's PHP remains loaded for this request; sync on the redirect.
            if ( is_plugin_active( self::LEGACY_PLUGIN ) ) { deactivate_plugins( self::LEGACY_PLUGIN ); }
            if ( self::THEME_SLUG !== get_stylesheet() ) {
                update_option( 'eola_hills_previous_theme', get_stylesheet(), false ); switch_theme( self::THEME_SLUG );
            }
            update_option( 'eola_hills_pending_theme_sync', $manifest['version'], false );
            delete_option( 'eola_hills_sync_error' ); delete_transient( self::CACHE );
            return true;
        } finally { if ( $temp && is_file( $temp ) ) { @unlink( $temp ); } delete_option( 'eola_hills_install_lock' ); }
    }
    public function complete_sync() {
        $pending = get_option( 'eola_hills_pending_theme_sync' );
        if ( ! $pending || ! current_user_can( 'manage_options' ) || ! function_exists( 'eola_hills_sync_release' ) || ! defined( 'EOLA_HILLS_VERSION' ) || $pending !== EOLA_HILLS_VERSION || get_option( 'eola_hills_sync_error' ) ) { return; }
        $result = eola_hills_sync_release();
        if ( is_wp_error( $result ) ) { update_option( 'eola_hills_sync_error', $result->get_error_message(), false ); return; }
        delete_option( 'eola_hills_pending_theme_sync' ); delete_option( 'eola_hills_pending_initial_setup' );
        update_option( 'eola_hills_last_install', array( 'version' => $pending, 'date' => current_time( 'mysql' ) ), false );
        if ( get_option( 'eola_hills_retire_legacy' ) && get_option( 'eola_hills_legacy_backup' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            $retired = delete_plugins( array( self::LEGACY_PLUGIN ) );
            if ( ! is_wp_error( $retired ) && $retired ) { delete_option( 'eola_hills_retire_legacy' ); }
        }
    }
    public function handle() {
        if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'update_themes' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'eola_hills_updates' );
        $action = sanitize_key( $_POST['operation'] ?? '' ); $result = true; $message = '';
        if ( 'sync' === $action || 'check' === $action ) {
            $result = $this->release( true );
            if ( ! is_wp_error( $result ) ) { $result = 'sync' === $action ? $this->install( $result ) : true; $message = 'sync' === $action ? 'Theme installed. Website content sync completes automatically on this page.' : 'GitHub checked.'; }
        } elseif ( 'save' === $action ) {
            if ( defined( 'EOLA_HILLS_GITHUB_TOKEN' ) ) { $result = new WP_Error( 'constant', 'The token is managed in wp-config.php.' ); }
            else { $token = trim( sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) ) ); if ( $token ) { $result = $this->store_token( $token ); } }
        } elseif ( in_array( $action, array( 'backup', 'legacy_backup' ), true ) ) {
            $backup = get_option( 'legacy_backup' === $action ? 'eola_hills_legacy_backup' : 'eola_hills_theme_backup' );
            $bytes = $backup ? base64_decode( $backup['data'], true ) : false;
            if ( ! $bytes || ! hash_equals( $backup['sha256'], hash( 'sha256', $bytes ) ) ) { wp_die( 'No valid code backup is available.' ); }
            nocache_headers(); header( 'Content-Type: application/zip' ); header( 'Content-Disposition: attachment; filename="eola-hills-' . $action . '.zip"' ); echo $bytes; exit;
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
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        echo '<div class="wrap" style="max-width:900px"><h1>Eola Hills — GitHub website sync</h1><p>One updater, one theme, an editable Elementor website.</p>';
        $notice = get_transient( 'eola_hills_notice_' . get_current_user_id() );
        if ( $notice ) { echo '<div class="notice ' . ( $notice['error'] ? 'notice-error' : 'notice-success' ) . '"><p>' . esc_html( $notice['message'] ) . '</p></div>'; delete_transient( 'eola_hills_notice_' . get_current_user_id() ); }
        if ( $error = get_option( 'eola_hills_sync_error' ) ) { echo '<div class="notice notice-error"><p>Content sync needs attention: ' . esc_html( $error ) . ' Click Sync from GitHub to retry.</p></div>'; }
        echo '<p>Repository: <code>' . esc_html( self::REPO ) . '</code><br>Installed theme: <strong>' . esc_html( $this->version() ) . '</strong></p>';
        $last = get_option( 'eola_hills_last_install' );
        if ( $last && ! get_option( 'eola_hills_pending_theme_sync' ) ) { echo '<p>Website synced: ' . esc_html( $last['version'] . ' · ' . $last['date'] ) . '</p>'; }
        echo '<p>Sync installs the latest GitHub theme and automatically applies changed client layouts and media. Theme files and replaced page content are backed up first. Header, footer, sections and media remain editable in Elementor. Code-only releases preserve saved page edits.</p>';
        $this->form( 'sync', 'Sync from GitHub', true ); $this->form( 'check', 'Check GitHub' );
        $id = (int) get_option( 'eola_hills_page_id' );
        if ( $id ) { echo '<p><a class="button" href="' . esc_url( admin_url( 'post.php?post=' . $id . '&action=elementor' ) ) . '">Edit website in Elementor</a> <a class="button" href="' . esc_url( get_permalink( $id ) ) . '">View website</a></p>'; }
        if ( get_option( 'eola_hills_theme_backup' ) ) { $this->form( 'backup', 'Download previous theme backup' ); }
        if ( get_option( 'eola_hills_legacy_backup' ) ) { $this->form( 'legacy_backup', 'Download retired property plugin backup' ); }
        echo '<p>Restore page content from the Eola Hills menu. Keep separate complete WordPress database/media backups.</p><h2>Private repository access</h2><p>This public repository needs no token. Existing saved authentication is retained during migration.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'eola_hills_updates' );
        echo '<input type="hidden" name="action" value="eola_hills_updates"><input type="hidden" name="operation" value="save"><label>GitHub token <input type="password" name="token" autocomplete="new-password" value="" class="regular-text"></label>';
        submit_button( 'Save connection' ); echo '</form></div>';
    }
}
