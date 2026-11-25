<?php
defined( 'ABSPATH' ) || exit;
final class Eola_Care {
    use Eola_Care_Automation, Eola_Care_Firewall, Eola_Care_Reports;
    public static function boot() {
        add_filter( 'cron_schedules', function ( $schedules ) { $schedules['eola_care_five_minutes'] = array( 'interval' => 300, 'display' => 'Every five minutes (Eola Care)' ); return $schedules; } );
        add_action( 'init', array( __CLASS__, 'schedule' ) );
        add_action( 'eola_care_tick', array( __CLASS__, 'cron' ) );
        add_action( 'eola_care_continue', array( __CLASS__, 'cron' ) );
        add_action( 'eola_care_tick', array( __CLASS__, 'report_tick' ), 20 );
        add_action( 'template_redirect', array( __CLASS__, 'firewall' ), -100 );
        self::harden();
        add_action( 'admin_post_eola_care_firewall', array( __CLASS__, 'save_firewall' ) );
        add_action( 'admin_post_eola_care_reports', array( __CLASS__, 'save_reports' ) );
        add_action( 'admin_post_eola_care_report_file', array( __CLASS__, 'download_report' ) );
        add_action( 'admin_post_eola_care_automation', array( __CLASS__, 'save_automation' ) );
        add_action( 'admin_post_eola_care_report', array( __CLASS__, 'care_report' ) );
        add_action( 'admin_menu', function () { add_menu_page( 'Eola Care', 'Eola Care', 'manage_options', 'eola-care', array( __CLASS__, 'screen' ), 'dashicons-shield', 32 ); } );
        add_action( 'admin_enqueue_scripts', function ( $hook ) {
            if ( 'toplevel_page_eola-care' !== $hook ) { return; }
            wp_enqueue_style( 'eola-care', plugins_url( '../assets/care.css', __FILE__ ), array(), EOLA_CARE_VERSION );
            wp_enqueue_script( 'eola-care', plugins_url( '../assets/care.js', __FILE__ ), array(), EOLA_CARE_VERSION, true );
            wp_add_inline_script( 'eola-care', 'window.eolaCare=' . wp_json_encode( array( 'endpoint' => rest_url( 'eola-care/v1/task' ), 'nonce' => wp_create_nonce( 'wp_rest' ) ) ) . ';', 'before' );
        } );
        add_action( 'rest_api_init', function () { register_rest_route( 'eola-care/v1', '/task', array( 'methods' => 'POST', 'permission_callback' => function () { return current_user_can( 'manage_options' ) && ! is_multisite(); }, 'callback' => array( __CLASS__, 'api' ) ) ); } );
        add_action( 'admin_post_eola_care_download', array( __CLASS__, 'download' ) );
        add_action( 'template_redirect', function () {
            $root = self::root( false ); if ( is_wp_error( $root ) ) { return; }
            if ( self::read( 'maintenance', false ) && ! current_user_can( 'manage_options' ) ) { nocache_headers(); wp_die( 'The website is being restored. Please check back shortly.', 'Website maintenance', array( 'response' => 503 ) ); }
        }, 0 );
    }
    public static function root( $create = true ) {
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $web = $document_root ? realpath( $document_root ) : realpath( get_option( 'eola_care_public_root', '/nonexistent-eola-root' ) );
        if ( defined( 'EOLA_CARE_BACKUP_DIR' ) ) { $root = EOLA_CARE_BACKUP_DIR; }
        elseif ( $web && is_dir( $web ) ) { $root = dirname( $web ) . '/eola-private-backups'; }
        else { return new WP_Error( 'storage', 'Configure EOLA_CARE_BACKUP_DIR as a writable directory outside the web root.' ); }
        $normalized = wp_normalize_path( $root );
        foreach ( array_filter( array( realpath( ABSPATH ), $web ) ) as $public ) {
            $public = trailingslashit( wp_normalize_path( $public ) );
            if ( str_starts_with( trailingslashit( $normalized ), $public ) ) { return new WP_Error( 'storage', 'Backup storage must be outside the public web root.' ); }
        }
        if ( ! is_dir( $root ) && ( ! $create || ! wp_mkdir_p( $root ) ) ) { return new WP_Error( 'storage', 'Private backup directory is unavailable. Set EOLA_CARE_BACKUP_DIR outside the web root.' ); }
        $resolved = realpath( $root );
        foreach ( array_filter( array( realpath( ABSPATH ), $web ) ) as $public ) {
            if ( str_starts_with( trailingslashit( wp_normalize_path( $resolved ) ), trailingslashit( wp_normalize_path( $public ) ) ) ) { return new WP_Error( 'storage', 'Backup storage resolves inside the public web root.' ); }
        }
        if ( ! is_writable( $resolved ) ) { return new WP_Error( 'storage', 'Private backup directory is not writable.' ); }
        @chmod( $resolved, 0700 );
        return $resolved;
    }
    private static function path( $name ) {
        $root = self::root(); if ( is_wp_error( $root ) ) { throw new RuntimeException( $root->get_error_message() ); }
        if ( ! preg_match( '/^[a-zA-Z0-9_.-]+$/D', $name ) ) { throw new RuntimeException( 'Invalid storage key.' ); } return $root . '/' . $name;
    }
    private static function read( $name, $default = array() ) { $p = self::path( $name . '.json' ); return is_file( $p ) ? ( json_decode( file_get_contents( $p ), true ) ?: $default ) : $default; }
    private static function save( $name, $data ) {
        $p = self::path( $name . '.json' ); $tmp = $p . '.tmp';
        if ( false === file_put_contents( $tmp, wp_json_encode( $data ), LOCK_EX ) || ! rename( $tmp, $p ) ) { throw new RuntimeException( 'Cannot save private job state.' ); } @chmod( $p, 0600 );
    }
    private static function audit( $action, $detail = '' ) { file_put_contents( self::path( 'audit.jsonl' ), wp_json_encode( array( 'time' => gmdate( 'c' ), 'user' => get_current_user_id(), 'action' => $action, 'detail' => $detail ) ) . "\n", FILE_APPEND | LOCK_EX ); }
    private static function files() {
        $root = realpath( ABSPATH ); $paths = array(); $bytes = 0;
        $iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iter as $file ) {
            if ( $file->isLink() ) { throw new RuntimeException( 'A symbolic link needs manual backup review: ' . $file->getFilename() ); }
            if ( ! $file->isFile() ) { continue; }
            $relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
            if ( preg_match( '~^(?:\.git/|wp-content/(?:cache|upgrade|upgrade-temp-backup)/)|(?:^|/)debug\.log$~', $relative ) ) { continue; }
            $paths[] = $relative; $bytes += $file->getSize();
            if ( count( $paths ) > 40000 || $bytes > 2 * GB_IN_BYTES ) { throw new RuntimeException( 'This local backup engine supports up to 40,000 files / 2 GB. Use a host backup for this site size.' ); }
        }
        sort( $paths ); return array( $paths, $bytes );
    }
    private static function safe_file( $relative, $existing = false ) {
        if ( ! is_string( $relative ) || '' === $relative || preg_match( '~(?:^|/)\.\.(?:/|$)|[\\\\:\x00]|^/~', $relative ) ) { throw new RuntimeException( 'Unsafe file path.' ); }
        $root = trailingslashit( realpath( ABSPATH ) ); $path = $root . $relative; $parent = dirname( $path );
        while ( ! file_exists( $parent ) && dirname( $parent ) !== $parent ) { $parent = dirname( $parent ); }
        if ( ! str_starts_with( trailingslashit( realpath( $parent ) ), $root ) || is_link( $path ) || ( $existing && ( ! is_file( $path ) || ! str_starts_with( realpath( $path ), $root ) ) ) ) { throw new RuntimeException( 'File leaves the WordPress directory.' ); }
        return $path;
    }
    private static function new_backup( $after = '' ) {
        global $wpdb;
        if ( ! class_exists( 'ZipArchive' ) ) { throw new RuntimeException( 'PHP Zip extension is required.' ); }
        list( $files, $bytes ) = self::files(); $root = self::root();
        if ( disk_free_space( $root ) < $bytes * 2 + 128 * MB_IN_BYTES ) { throw new RuntimeException( 'Insufficient free disk space for a verified backup.' ); }
        $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
        $id = 'backup-' . gmdate( 'Ymd-His' ) . '-' . substr( wp_generate_uuid4(), 0, 8 );
        $job = array( 'kind' => 'backup', 'status' => 'running', 'id' => $id, 'phase' => 'database', 'tables' => $tables, 'table_index' => 0, 'row_offset' => 0, 'files' => $files, 'offset' => 0, 'after' => $after, 'started' => gmdate( 'c' ) );
        file_put_contents( self::path( $id . '.jsonl' ), wp_json_encode( array( 'format' => 1, 'site' => home_url(), 'prefix' => $wpdb->prefix ) ) . "\n" );
        self::save( 'job', $job ); self::audit( 'backup_started', $id ); return $job;
    }
    private static function identifier( $name ) { if ( ! preg_match( '/^[a-zA-Z0-9_]+$/D', $name ) ) { throw new RuntimeException( 'Unsupported database identifier.' ); } return '`' . $name . '`'; }
    private static function backup_step( &$job ) {
        global $wpdb;
        $id = $job['id'];
        if ( 'database' === $job['phase'] ) {
            $table = $job['tables'][$job['table_index']] ?? null;
            if ( ! $table ) { $job['phase'] = 'files'; return; }
            $name = self::identifier( $table ); $lines = '';
            if ( 0 === $job['row_offset'] ) { $schema = $wpdb->get_row( "SHOW CREATE TABLE $name", ARRAY_N ); if ( ! $schema ) { throw new RuntimeException( 'Cannot export database schema.' ); } $lines .= wp_json_encode( array( 'table' => $table, 'schema' => $schema[1] ) ) . "\n"; }
            $rows = $wpdb->get_results( "SELECT * FROM $name LIMIT 25 OFFSET " . (int) $job['row_offset'], ARRAY_A );
            if ( $wpdb->last_error ) { throw new RuntimeException( 'Database export failed.' ); }
            foreach ( $rows as $row ) { foreach ( $row as &$value ) { if ( null !== $value ) { $value = base64_encode( (string) $value ); } } unset( $value ); $lines .= wp_json_encode( array( 'table' => $table, 'row' => $row ) ) . "\n"; }
            if ( false === file_put_contents( self::path( $id . '.jsonl' ), $lines, FILE_APPEND | LOCK_EX ) ) { throw new RuntimeException( 'Database export could not be written.' ); }
            $job['row_offset'] += count( $rows );
            if ( count( $rows ) < 25 ) { $job['table_index']++; $job['row_offset'] = 0; }
            return;
        }
        $zip = new ZipArchive(); if ( true !== $zip->open( self::path( $id . '.zip' ), ZipArchive::CREATE ) ) { throw new RuntimeException( 'Cannot open backup archive.' ); }
        try {
            if ( 0 === $job['offset'] ) { $zip->addFile( self::path( $id . '.jsonl' ), 'database.jsonl' ); }
            for ( $n = 0; $n < 100 && $job['offset'] < count( $job['files'] ); $n++, $job['offset']++ ) {
                $relative = $job['files'][$job['offset']]; $file = self::safe_file( $relative, true );
                if ( ! $zip->addFile( $file, 'files/' . $relative ) ) { throw new RuntimeException( 'Cannot archive ' . $relative ); }
                if ( preg_match( '/\.(?:webp|jpg|jpeg|png|pdf|zip|mp4)$/i', $relative ) ) { $zip->setCompressionName( 'files/' . $relative, ZipArchive::CM_STORE ); }
            }
        } finally { if ( ! $zip->close() ) { throw new RuntimeException( 'Backup archive could not be saved.' ); } }
        if ( $job['offset'] < count( $job['files'] ) ) { return; }
        $path = self::path( $id . '.zip' ); $check = new ZipArchive();
        if ( true !== $check->open( $path, ZipArchive::CHECKCONS ) ) { throw new RuntimeException( 'Backup archive verification failed.' ); } $check->close();
        $index = self::read( 'backups' );
        $index[$id] = array( 'id' => $id, 'created' => gmdate( 'c' ), 'size' => filesize( $path ), 'sha256' => hash_file( 'sha256', $path ), 'site' => home_url(), 'prefix' => $wpdb->prefix, 'files' => count( $job['files'] ), 'recovery_for' => $job['after'] );
        self::save( 'backups', $index ); unlink( self::path( $id . '.jsonl' ) ); @chmod( $path, 0600 ); self::audit( 'backup_complete', $id );
        if ( $job['after'] ) { $job = self::restore_job( $job['after'], $id ); }
        else { $job = array( 'kind' => 'backup', 'status' => 'done', 'id' => $id, 'message' => 'Verified website files and database backup saved.' ); }
    }
    private static function verified_backup( $id ) {
        $index = self::read( 'backups' ); $entry = $index[$id] ?? null;
        if ( ! $entry || ! preg_match( '/^backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/D', $id ) || ! is_file( self::path( $id . '.zip' ) ) || ! hash_equals( $entry['sha256'], hash_file( 'sha256', self::path( $id . '.zip' ) ) ) ) { throw new RuntimeException( 'Backup is missing or failed its integrity check.' ); }
        return $entry;
    }
    private static function restore_job( $id, $recovery ) {
        global $wpdb;
        $entry = self::verified_backup( $id );
        if ( home_url() !== $entry['site'] || $wpdb->prefix !== $entry['prefix'] ) { throw new RuntimeException( 'Restore is restricted to the original site and database prefix.' ); }
        $zip = new ZipArchive(); if ( true !== $zip->open( self::path( $id . '.zip' ) ) ) { throw new RuntimeException( 'Cannot open backup.' ); }
        $files = array(); $seen = array();
        try {
            for ( $i = 0; $i < $zip->numFiles; $i++ ) {
                $name = $zip->getNameIndex( $i );
                if ( 'database.jsonl' === $name ) { continue; }
                if ( ! str_starts_with( $name, 'files/' ) || isset( $seen[$name] ) ) { throw new RuntimeException( 'Unexpected backup entry.' ); }
                $relative = substr( $name, 6 ); self::safe_file( $relative ); $seen[$name] = true;
                // Keep connection credentials and the currently running recovery controller intact.
                if ( 'wp-config.php' === $relative || str_starts_with( $relative, 'wp-content/plugins/eola-hills-care/' ) ) { continue; }
                $files[] = $relative;
            }
            $stream = $zip->getStream( 'database.jsonl' ); if ( ! $stream ) { throw new RuntimeException( 'Database snapshot is missing.' ); }
            $out = fopen( self::path( 'restore-database.jsonl' ), 'wb' ); stream_copy_to_stream( $stream, $out ); fclose( $stream ); fclose( $out );
        } finally { $zip->close(); }
        $in = fopen( self::path( 'restore-database.jsonl' ), 'rb' ); $header = json_decode( fgets( $in ), true );
        if ( 1 !== ( $header['format'] ?? 0 ) || $wpdb->prefix !== ( $header['prefix'] ?? '' ) || home_url() !== ( $header['site'] ?? '' ) ) { fclose( $in ); throw new RuntimeException( 'Invalid database snapshot header.' ); }
        $tables = array(); $token = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 8 );
        while ( false !== ( $line = fgets( $in ) ) ) {
            $record = json_decode( $line, true ); $table = $record['table'] ?? ''; self::identifier( $table );
            if ( ! str_starts_with( $table, $wpdb->prefix ) || ( ! isset( $record['schema'] ) && ! is_array( $record['row'] ?? null ) ) ) { fclose( $in ); throw new RuntimeException( 'Invalid database snapshot.' ); }
            if ( isset( $record['schema'] ) && ! str_starts_with( $record['schema'], 'CREATE TABLE `' . $table . '`' ) ) { fclose( $in ); throw new RuntimeException( 'Unexpected schema statement.' ); }
            if ( isset( $record['schema'] ) ) {
                if ( stripos( $record['schema'], 'FOREIGN KEY' ) !== false ) { fclose( $in ); throw new RuntimeException( 'This snapshot contains foreign-key tables; use a hosting-level database restore.' ); }
                $tables[$table] = '_ecr_' . $token . '_' . substr( hash( 'sha256', $table ), 0, 16 );
            }
            foreach ( $record['row'] ?? array() as $column => $value ) { self::identifier( $column ); if ( null !== $value && false === base64_decode( $value, true ) ) { fclose( $in ); throw new RuntimeException( 'Invalid database value.' ); } }
        }
        fclose( $in ); self::audit( 'restore_started', $id ); self::save( 'maintenance', true );
        return array( 'kind' => 'restore', 'status' => 'running', 'id' => $id, 'recovery' => $recovery, 'phase' => 'files', 'files' => $files, 'offset' => 0, 'db_offset' => 0, 'staging_tables' => $tables );
    }
    private static function restore_step( &$job ) {
        global $wpdb;
        if ( 'files' === $job['phase'] ) {
            $zip = new ZipArchive(); if ( true !== $zip->open( self::path( $job['id'] . '.zip' ) ) ) { throw new RuntimeException( 'Cannot reopen backup.' ); }
            try {
                for ( $n = 0; $n < 80 && $job['offset'] < count( $job['files'] ); $n++, $job['offset']++ ) {
                    $relative = $job['files'][$job['offset']]; $path = self::safe_file( $relative );
                    if ( ! wp_mkdir_p( dirname( $path ) ) ) { throw new RuntimeException( 'Cannot create restore directory.' ); }
                    $stream = $zip->getStream( 'files/' . $relative ); $tmp = $path . '.eola-restore-tmp'; $out = fopen( $tmp, 'wb' );
                    if ( ! $stream || ! $out ) { throw new RuntimeException( 'Cannot write restore file.' ); }
                    stream_copy_to_stream( $stream, $out ); fclose( $stream ); fclose( $out );
                    if ( ! rename( $tmp, $path ) ) { throw new RuntimeException( 'Cannot replace restore file.' ); } @chmod( $path, 0644 );
                }
            } finally { $zip->close(); }
            if ( $job['offset'] >= count( $job['files'] ) ) { $job['phase'] = 'database'; } return;
        }
        $in = fopen( self::path( 'restore-database.jsonl' ), 'rb' ); fseek( $in, $job['db_offset'] );
        $wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
        try {
            for ( $n = 0; $n < 25 && false !== ( $line = fgets( $in ) ); $n++ ) {
                $record = json_decode( $line, true ); if ( isset( $record['format'] ) ) { continue; }
                $staging = $job['staging_tables'][$record['table']] ?? ''; $table = self::identifier( $staging );
                if ( isset( $record['schema'] ) ) {
                    $schema = preg_replace( '/^CREATE TABLE `' . preg_quote( $record['table'], '/' ) . '`/', 'CREATE TABLE ' . $table, $record['schema'], 1 );
                    if ( false === $wpdb->query( "DROP TABLE IF EXISTS $table" ) || false === $wpdb->query( $schema ) ) { throw new RuntimeException( 'Cannot prepare database restore table.' ); }
                } else {
                    $columns = array(); $values = array();
                    foreach ( $record['row'] as $column => $value ) { $columns[] = self::identifier( $column ); $values[] = null === $value ? 'NULL' : "'" . $wpdb->_real_escape( base64_decode( $value, true ) ) . "'"; }
                    if ( false === $wpdb->query( "INSERT INTO $table (" . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ')' ) ) { throw new RuntimeException( 'Cannot restore database row.' ); }
                }
            }
            $job['db_offset'] = ftell( $in ); $done = feof( $in );
        } finally { fclose( $in ); $wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' ); }
        wp_cache_flush();
        if ( $done ) {
            // Build all tables separately, then swap them in one atomic RENAME TABLE statement.
            $options = self::identifier( $job['staging_tables'][$wpdb->options] );
            $active = maybe_unserialize( $wpdb->get_var( "SELECT option_value FROM $options WHERE option_name='active_plugins'" ) );
            $active = is_array( $active ) ? $active : array(); $active[] = 'eola-hills-care/eola-hills-care.php';
            $wpdb->query( $wpdb->prepare( "UPDATE $options SET option_value=%s WHERE option_name='active_plugins'", maybe_serialize( array_values( array_unique( $active ) ) ) ) );
            $renames = array(); $old = array();
            foreach ( $job['staging_tables'] as $original => $staging ) {
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $original ) ) ) === $original ) { $previous = '_eco_' . substr( hash( 'sha256', $staging ), 0, 24 ); $renames[] = self::identifier( $original ) . ' TO ' . self::identifier( $previous ); $old[] = $previous; }
                $renames[] = self::identifier( $staging ) . ' TO ' . self::identifier( $original );
            }
            if ( false === $wpdb->query( 'RENAME TABLE ' . implode( ',', $renames ) ) ) { throw new RuntimeException( 'Atomic database replacement failed; original tables remain in place.' ); }
            wp_cache_flush();
            foreach ( $old as $previous ) { $wpdb->query( 'DROP TABLE ' . self::identifier( $previous ) ); }
            if ( class_exists( '\Elementor\Plugin' ) ) { \Elementor\Plugin::instance()->files_manager->clear_cache(); }
            self::audit( 'restore_complete', $job['id'] ); unlink( self::path( 'restore-database.jsonl' ) );
            self::save( 'maintenance', false );
            $job = array( 'kind' => 'restore', 'status' => 'done', 'id' => $job['id'], 'recovery' => $job['recovery'], 'message' => 'Backup files and database restored. wp-config.php and the recovery plugin were retained; files absent from the snapshot were not deleted.' );
        }
    }
    private static function new_scan() {
        list( $files ) = self::files();
        $response = wp_safe_remote_get( add_query_arg( array( 'version' => get_bloginfo( 'version' ), 'locale' => get_locale() ), 'https://api.wordpress.org/core/checksums/1.0/' ), array( 'timeout' => 20, 'limit_response_size' => 2 * MB_IN_BYTES ) );
        $checksums = is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ? array() : ( json_decode( wp_remote_retrieve_body( $response ), true )['checksums'] ?? array() );
        return array( 'kind' => 'scan', 'status' => 'running', 'files' => $files, 'offset' => 0, 'findings' => array(), 'skipped' => 0, 'checksums' => $checksums, 'started' => gmdate( 'c' ) );
    }
    private static function scan_step( &$job ) {
        for ( $n = 0; $n < 150 && $job['offset'] < count( $job['files'] ); $n++, $job['offset']++ ) {
            $relative = $job['files'][$job['offset']]; $path = self::safe_file( $relative, true ); $reasons = array();
            if ( isset( $job['checksums'][$relative] ) && ! hash_equals( $job['checksums'][$relative], md5_file( $path ) ) ) { $reasons[] = 'WordPress core checksum differs from the official distribution'; }
            if ( preg_match( '/\.(?:php[0-9]?|phtml|phar|js|html?|htaccess)$/i', $relative ) ) {
                if ( filesize( $path ) > 2 * MB_IN_BYTES ) { $job['skipped']++; continue; }
                $code = file_get_contents( $path );
                if ( false === $code ) { $job['skipped']++; continue; }
                if ( preg_match( '~^wp-content/uploads/.*\.(?:php[0-9]?|phtml|phar)$~i', $relative ) ) { $reasons[] = 'Executable file in uploads'; }
                if ( preg_match( '/(?:eval|assert)\s*\(\s*(?:base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(/i', $code ) ) { $reasons[] = 'Encoded code execution pattern'; }
                if ( preg_match( '/(?:eval|system|shell_exec|passthru)\s*\(\s*\$_(?:POST|GET|REQUEST|COOKIE)\s*\[/i', $code ) ) { $reasons[] = 'Request input passed to code or shell execution'; }
                // Markers are assembled so this scanner's own source never matches itself.
                if ( preg_match( '/\b(?:' . implode( '|', array( 'c99' . 'shell', 'r57' . 'shell', 'Files' . 'Man', 'WSO_' . 'VERSION' ) ) . ')\b/i', $code ) ) { $reasons[] = 'Known web-shell marker (manual verification required)'; }
            }
            if ( $reasons ) { $job['findings'][] = array( 'path' => $relative, 'sha256' => hash_file( 'sha256', $path ), 'reasons' => $reasons ); }
        }
        if ( $job['offset'] >= count( $job['files'] ) ) {
            $report = array( 'time' => gmdate( 'c' ), 'scanned' => count( $job['files'] ), 'skipped' => $job['skipped'], 'core_verified' => ! empty( $job['checksums'] ), 'findings' => $job['findings'] );
            self::save( 'scan', $report ); self::audit( 'scan_complete', count( $report['findings'] ) . ' files flagged' );
            $job = array( 'kind' => 'scan', 'status' => 'done', 'message' => 'Scan complete. Findings are review flags, not a malware verdict.' );
        }
    }
    public static function api( WP_REST_Request $request ) {
        $lock = null;
        try {
            wp_raise_memory_limit( 'admin' );
            $lock = fopen( self::path( 'task.lock' ), 'c' ); if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) { throw new RuntimeException( 'Another care task is running.' ); }
            $action = $request->get_param( 'action' ); $job = self::read( 'job' ); $busy = 'running' === ( $job['status'] ?? '' );
            if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS && in_array( $action, array( 'restore', 'quarantine', 'recover_file', 'delete_file' ), true ) ) { throw new RuntimeException( 'File modifications are disabled by this WordPress installation.' ); }
            if ( $busy && ! in_array( $action, array( 'step', 'status' ), true ) ) { throw new RuntimeException( 'Finish or resume the current task first.' ); }
            if ( 'backup' === $action ) { $job = self::new_backup(); }
            elseif ( 'scan' === $action ) { $job = self::new_scan(); self::save( 'job', $job ); }
            elseif ( 'restore' === $action ) {
                $id = (string) $request->get_param( 'id' ); if ( 'RESTORE ' . $id !== $request->get_param( 'confirm' ) ) { throw new RuntimeException( 'Type RESTORE followed by the backup ID to confirm replacement of website files and data.' ); }
                self::verified_backup( $id ); $job = self::new_backup( $id );
            } elseif ( 'delete' === $action ) {
                $id = (string) $request->get_param( 'id' ); if ( 'DELETE ' . $id !== $request->get_param( 'confirm' ) ) { throw new RuntimeException( 'Backup deletion needs its exact confirmation.' ); }
                $index = self::read( 'backups' ); if ( ! isset( $index[$id] ) ) { throw new RuntimeException( 'Backup not found.' ); }
                if ( 'restore' === ( $job['kind'] ?? '' ) && 'done' !== ( $job['status'] ?? '' ) && in_array( $id, array( $job['id'], $job['recovery'] ), true ) ) { throw new RuntimeException( 'Recovery backups cannot be deleted during an unfinished restore.' ); }
                if ( ! unlink( self::path( $id . '.zip' ) ) ) { throw new RuntimeException( 'Backup could not be deleted.' ); } unset( $index[$id] ); self::save( 'backups', $index ); self::audit( 'backup_deleted', $id );
            } elseif ( in_array( $action, array( 'quarantine', 'recover_file', 'delete_file' ), true ) ) { self::quarantine( $action, $request ); }
            elseif ( 'step' === $action ) {
                if ( ! $busy ) { return rest_ensure_response( self::public_job( $job ) ); }
                self::advance( $job );
            } elseif ( 'status' !== $action ) { throw new RuntimeException( 'Unknown care action.' ); }
            return rest_ensure_response( self::public_job( $job ) );
        } catch ( Throwable $error ) {
            if ( isset( $job ) && 'step' === ( $action ?? '' ) ) { $job['status'] = 'error'; $job['message'] = $error->getMessage(); self::save( 'job', $job ); self::audit( 'task_error', $error->getMessage() ); }
            if ( ! empty( $job['automation'] ) && 'step' === ( $action ?? '' ) ) { self::run_record( $job['automation']['id'], array( 'status' => 'error', 'finished' => gmdate( 'c' ), 'message' => $error->getMessage() ) ); }
            return new WP_Error( 'eola_care', $error->getMessage(), array( 'status' => 400 ) );
        } finally { if ( is_resource( $lock ) ) { flock( $lock, LOCK_UN ); fclose( $lock ); } }
    }
    private static function public_job( $job ) { return array( 'kind' => $job['kind'] ?? '', 'status' => $job['status'] ?? 'idle', 'phase' => $job['phase'] ?? '', 'processed' => $job['offset'] ?? 0, 'total' => count( $job['files'] ?? array() ), 'message' => $job['message'] ?? '', 'recovery' => $job['recovery'] ?? '' ); }
    private static function quarantine( $action, $request ) {
        $index = self::read( 'quarantine' ); $path = (string) $request->get_param( 'id' );
        if ( 'quarantine' === $action ) {
            if ( 'QUARANTINE ' . $path !== $request->get_param( 'confirm' ) ) { throw new RuntimeException( 'Exact file confirmation is required.' ); }
            if ( 'wp-config.php' === $path || str_starts_with( $path, 'wp-content/plugins/eola-hills-care/' ) ) { throw new RuntimeException( 'Connection/recovery files require manual review outside this tool.' ); }
            $finding = null; foreach ( self::read( 'scan' )['findings'] ?? array() as $row ) { if ( $row['path'] === $path ) { $finding = $row; break; } }
            $source = self::safe_file( $path, true );
            if ( ! $finding || ! hash_equals( $finding['sha256'], hash_file( 'sha256', $source ) ) ) { throw new RuntimeException( 'File changed or was not flagged in the last scan; scan again.' ); }
            $id = 'quarantine-' . wp_generate_uuid4(); $dest = self::path( $id . '.bin' );
            if ( ! copy( $source, $dest ) || ! hash_equals( $finding['sha256'], hash_file( 'sha256', $dest ) ) ) { throw new RuntimeException( 'Private quarantine copy failed.' ); }
            $index[$id] = array( 'path' => $path, 'sha256' => $finding['sha256'], 'date' => gmdate( 'c' ) ); self::save( 'quarantine', $index );
            if ( ! unlink( $source ) ) { throw new RuntimeException( 'File could not be removed from the website; private copy retained.' ); } self::audit( 'file_quarantined', $path );
        } else {
            $item = $index[$path] ?? null; if ( ! $item ) { throw new RuntimeException( 'Quarantine entry missing.' ); }
            if ( strtoupper( $action ) . ' ' . $path !== $request->get_param( 'confirm' ) ) { throw new RuntimeException( 'Exact quarantine confirmation is required.' ); }
            $source = self::path( $path . '.bin' );
            if ( 'recover_file' === $action ) {
                $dest = self::safe_file( $item['path'] ); if ( file_exists( $dest ) ) { throw new RuntimeException( 'A file already exists at the original location; it was not overwritten.' ); }
                if ( ! hash_equals( $item['sha256'], hash_file( 'sha256', $source ) ) || ! wp_mkdir_p( dirname( $dest ) ) || ! copy( $source, $dest ) ) { throw new RuntimeException( 'Quarantined file could not be restored.' ); }
            }
            if ( ! unlink( $source ) ) { throw new RuntimeException( 'Quarantine cleanup failed.' ); } unset( $index[$path] ); self::save( 'quarantine', $index ); self::audit( $action, $item['path'] );
        }
    }
    public static function download() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); } check_admin_referer( 'eola_care_download' );
        try { $id = sanitize_text_field( wp_unslash( $_GET['id'] ?? '' ) ); self::verified_backup( $id ); $path = self::path( $id . '.zip' ); }
        catch ( Throwable $e ) { wp_die( esc_html( $e->getMessage() ) ); }
        nocache_headers(); header( 'Content-Type: application/zip' ); header( 'Content-Disposition: attachment; filename="' . $id . '.zip"' ); header( 'Content-Length: ' . filesize( $path ) );
        $stream = fopen( $path, 'rb' ); fpassthru( $stream ); fclose( $stream ); exit;
    }
    public static function screen() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        echo '<div class="wrap ec-wrap"><p class="ec-eyebrow">WEBSITE MANAGEMENT</p><h1>Eola Care</h1><p>Daily backups, security scans, visitor rate protection and website reports.</p>';
        $root = self::root(); if ( is_multisite() || is_wp_error( $root ) ) { echo '<div class="notice notice-error"><p>' . esc_html( is_multisite() ? 'This care plugin supports single-site WordPress only.' : $root->get_error_message() ) . '</p></div></div>'; return; }
        self::reports_screen();
        self::automation_screen();
        self::firewall_screen();
        $job = self::public_job( self::read( 'job' ) );
        echo '<section class="ec-card"><h2>Website protection</h2><div class="ec-actions"><button class="button button-primary" data-task="backup">Create website backup</button><button class="button" data-task="scan">Scan website files</button><button class="button" data-task="resume">Resume current task</button></div><p id="ec-progress" role="status" aria-live="polite">' . esc_html( $job['message'] ?: ( $job['status'] . ' ' . $job['phase'] ) ) . '</p><p>Keep this tab open while a task runs. Reloading pauses progress; Resume continues it. Backups include WordPress files and this site\'s database tables, excluding cache, update work folders and debug logs.</p></section>';
        echo '<section class="ec-card"><h2>Saved backups</h2><p>Download a copy off this server. Restore first creates a recovery backup, then replaces backed-up files and database records. wp-config.php and this recovery plugin stay in place; extra files created after the snapshot are not deleted.</p><div class="ec-scroll"><table><thead><tr><th>Backup (UTC)</th><th>Size</th><th>Actions</th></tr></thead><tbody>';
        foreach ( array_reverse( self::read( 'backups' ), true ) as $id => $item ) {
            echo '<tr><td>' . esc_html( $item['created'] ) . '<br><small>' . esc_html( $id ) . ( $item['recovery_for'] ? ' / recovery snapshot' : ( ! empty( $item['automatic'] ) ? ' / automatic daily' : ' / manual' ) ) . '</small></td><td>' . esc_html( size_format( $item['size'] ) ) . '</td><td><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eola_care_download&id=' . $id ), 'eola_care_download' ) ) . '">Download</a> <button class="button" data-task="restore" data-id="' . esc_attr( $id ) . '">Restore</button> <button class="button" data-task="delete" data-id="' . esc_attr( $id ) . '">Delete</button></td></tr>';
        } echo '</tbody></table></div></section>';
        $scan = self::read( 'scan' );
        echo '<section class="ec-card"><h2>File scan</h2><p>Checks official WordPress core hashes when available, executable uploads and suspicious execution patterns. This is not a full antivirus, vulnerability feed, firewall or guarantee of a clean site. Review every finding before removing a file.</p>';
        if ( $scan ) { echo '<p>' . esc_html( $scan['time'] . ' | ' . $scan['scanned'] . ' files examined | ' . $scan['skipped'] . ' oversized/unreadable code files skipped | Core checksums: ' . ( $scan['core_verified'] ? 'available' : 'unavailable; pattern scan only' ) ) . '</p>'; }
        foreach ( $scan['findings'] ?? array() as $item ) { echo '<div class="ec-finding"><strong>' . esc_html( $item['path'] ) . '</strong><p>' . esc_html( implode( '; ', $item['reasons'] ) ) . '</p><button class="button" data-task="quarantine" data-id="' . esc_attr( $item['path'] ) . '">Review & quarantine</button></div>'; }
        if ( $scan && ! $scan['findings'] ) { echo '<p>No findings from these checks. This is not a malware-free certification.</p>'; } echo '</section>';
        echo '<section class="ec-card"><h2>Quarantined files</h2><p>Files are stored outside the website. Recover returns a file only if its original path is empty. Permanent deletion cannot be undone.</p>';
        foreach ( self::read( 'quarantine' ) as $id => $item ) { echo '<div class="ec-finding"><strong>' . esc_html( $item['path'] ) . '</strong><p><button class="button" data-task="recover_file" data-id="' . esc_attr( $id ) . '">Recover file</button> <button class="button" data-task="delete_file" data-id="' . esc_attr( $id ) . '">Permanently delete</button></p></div>'; } echo '</section></div>';
    }
}
