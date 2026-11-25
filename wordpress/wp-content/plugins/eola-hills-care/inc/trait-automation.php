<?php
defined( 'ABSPATH' ) || exit;
trait Eola_Care_Automation {
    public static function automation_settings() {
        return wp_parse_args( get_option( 'eola_care_automation', array() ), array( 'backup' => 1, 'scan' => 1, 'backup_time' => '02:00', 'scan_time' => '04:00', 'keep' => 7 ) );
    }
    public static function schedule() {
        if ( is_multisite() ) { return; }
        if ( ! wp_next_scheduled( 'eola_care_tick' ) ) { wp_schedule_event( time() + 60, 'eola_care_five_minutes', 'eola_care_tick' ); }
        // A browser request discovers the public root for later CLI/server-cron runs.
        if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) && realpath( $_SERVER['DOCUMENT_ROOT'] ) ) { update_option( 'eola_care_public_root', realpath( $_SERVER['DOCUMENT_ROOT'] ), false ); }
    }
    public static function deactivate() { wp_clear_scheduled_hook( 'eola_care_tick' ); wp_clear_scheduled_hook( 'eola_care_continue' ); }
    public static function save_automation() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'eola_care_automation' );
        $settings = array( 'backup' => isset( $_POST['backup'] ) ? 1 : 0, 'scan' => isset( $_POST['scan'] ) ? 1 : 0 );
        foreach ( array( 'backup_time', 'scan_time' ) as $key ) {
            $time = wp_unslash( $_POST[$key] ?? '' );
            if ( ! is_string( $time ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time ) ) { wp_die( 'Choose a valid daily time.' ); }
            $settings[$key] = $time;
        }
        $keep = absint( $_POST['keep'] ?? 7 ); $settings['keep'] = in_array( $keep, array( 7, 14, 30 ), true ) ? $keep : 7;
        update_option( 'eola_care_automation', $settings, false ); self::schedule();
        wp_safe_redirect( admin_url( 'admin.php?page=eola-care' ) ); exit;
    }
    private static function run_record( $id, $data ) {
        $runs = get_option( 'eola_care_runs', array() );
        $runs[$id] = array_merge( $runs[$id] ?? array(), $data );
        // Keep dated management history independently from archive retention.
        $runs = array_slice( $runs, -730, null, true );
        update_option( 'eola_care_runs', $runs, false );
    }
    private static function advance( &$job ) {
        $auto = $job['automation'] ?? null;
        if ( 'backup' === $job['kind'] ) { self::backup_step( $job ); }
        elseif ( 'restore' === $job['kind'] ) { self::restore_step( $job ); }
        elseif ( 'scan' === $job['kind'] ) { self::scan_step( $job ); }
        if ( $auto ) {
            $job['automation'] = $auto;
            if ( 'done' === $job['status'] ) {
                $data = array( 'status' => 'done', 'finished' => gmdate( 'c' ), 'message' => $job['message'] );
                if ( 'backup' === $job['kind'] ) {
                    $index = self::read( 'backups' ); $entry = $index[$job['id']];
                    $index[$job['id']]['automatic'] = true; self::save( 'backups', $index );
                    $data += array( 'backup_id' => $job['id'], 'files' => $entry['files'], 'bytes' => $entry['size'], 'sha256' => $entry['sha256'] );
                } else {
                    $scan = self::read( 'scan' );
                    $data += array( 'files' => $scan['scanned'], 'flagged' => count( $scan['findings'] ), 'skipped' => $scan['skipped'], 'core_checksums' => $scan['core_verified'] ? 'available' : 'unavailable' );
                }
                self::run_record( $auto['id'], $data );
                // One daily email once the last enabled task of the day is done.
                if ( 'scan' === $job['kind'] || empty( self::automation_settings()['scan'] ) ) { self::daily_email( $auto['date'] ); }
                if ( 'backup' === $job['kind'] ) {
                    try { self::prune_automatic_backups(); }
                    catch ( Throwable $e ) { self::run_record( $auto['id'], array( 'retention_note' => $e->getMessage() ) ); }
                }
            }
        }
        self::save( 'job', $job );
    }
    private static function prune_automatic_backups() {
        $index = self::read( 'backups' );
        $auto = array_filter( $index, fn( $entry ) => ! empty( $entry['automatic'] ) && empty( $entry['recovery_for'] ) );
        uasort( $auto, fn( $a, $b ) => strcmp( $b['created'], $a['created'] ) );
        foreach ( array_slice( $auto, (int) self::automation_settings()['keep'], null, true ) as $id => $entry ) {
            $path = self::path( $id . '.zip' );
            if ( is_file( $path ) && ! unlink( $path ) ) { throw new RuntimeException( 'An old automatic backup could not be removed. Review storage.' ); }
            unset( $index[$id] ); self::save( 'backups', $index ); self::audit( 'automatic_backup_expired', $id );
        }
    }
    public static function cron() {
        if ( is_multisite() ) { return; }
        $lock = null; $job = array(); $run_id = null;
        try {
            wp_raise_memory_limit( 'admin' );
            $lock = fopen( self::path( 'task.lock' ), 'c' );
            if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) { return; }
            update_option( 'eola_care_last_tick', time(), false ); delete_option( 'eola_care_schedule_error' );
            $job = self::read( 'job' );
            // Never start automation during a restore/recovery or take over manual tasks.
            if ( self::read( 'maintenance', false ) || ( 'running' === ( $job['status'] ?? '' ) && empty( $job['automation'] ) ) ) { return; }
            if ( 'running' !== ( $job['status'] ?? '' ) ) {
                $settings = self::automation_settings(); $now = current_datetime(); $today = $now->format( 'Y-m-d' );
                $kind = null;
                foreach ( array( 'backup', 'scan' ) as $candidate ) {
                    if ( empty( $settings[$candidate] ) || $now->format( 'H:i' ) < $settings[$candidate . '_time'] ) { continue; }
                    $already = false;
                    foreach ( get_option( 'eola_care_runs', array() ) as $run ) { if ( $run['date'] === $today && $run['kind'] === $candidate ) { $already = true; break; } }
                    if ( ! $already ) { $kind = $candidate; break; }
                }
                if ( ! $kind ) { return; }
                $run_id = wp_generate_uuid4();
                self::run_record( $run_id, array( 'date' => $today, 'timezone' => wp_timezone_string(), 'kind' => $kind, 'started' => gmdate( 'c' ), 'status' => 'running' ) );
                $job = 'backup' === $kind ? self::new_backup() : self::new_scan();
                $job['automation'] = array( 'id' => $run_id, 'date' => $today ); self::save( 'job', $job );
            }
            $run_id = $job['automation']['id'];
            // Persist each batch; no open browser or logged-in administrator is needed.
            $deadline = microtime( true ) + 15;
            do { self::advance( $job ); } while ( 'running' === $job['status'] && microtime( true ) < $deadline );
            if ( 'running' === $job['status'] && ! wp_next_scheduled( 'eola_care_continue' ) ) { wp_schedule_single_event( time() + 60, 'eola_care_continue' ); }
        } catch ( Throwable $e ) {
            update_option( 'eola_care_schedule_error', gmdate( 'c' ) . ' ' . $e->getMessage(), false );
            if ( $run_id ) {
                self::run_record( $run_id, array( 'status' => 'error', 'finished' => gmdate( 'c' ), 'message' => $e->getMessage() ) );
                try { self::daily_email( $job['automation']['date'] ?? wp_date( 'Y-m-d' ), true ); } catch ( Throwable $ignored ) {}
                if ( ! empty( $job['automation'] ) ) { $job['status'] = 'error'; $job['message'] = $e->getMessage(); try { self::save( 'job', $job ); } catch ( Throwable $ignored ) {} }
            }
        } finally { if ( is_resource( $lock ) ) { flock( $lock, LOCK_UN ); fclose( $lock ); } }
    }
    private static function automation_screen() {
        $s = self::automation_settings(); $next = wp_next_scheduled( 'eola_care_tick' ); $last = get_option( 'eola_care_last_tick' );
        echo '<section class="ec-card"><h2>Daily automatic protection</h2><p>Site timezone: <strong>' . esc_html( wp_timezone_string() ) . '</strong>. One backup and one security scan per day. Scheduled tasks continue in background batches; this tab can be closed. Findings are never deleted automatically.</p>';
        if ( $error = get_option( 'eola_care_schedule_error' ) ) { echo '<p class="ec-alert">' . esc_html( $error ) . '</p>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eola_care_automation">'; wp_nonce_field( 'eola_care_automation' );
        foreach ( array( 'backup' => 'Daily backup', 'scan' => 'Daily security scan' ) as $kind => $label ) {
            echo '<p><label><input type="checkbox" name="' . $kind . '" value="1" ' . checked( $s[$kind], 1, false ) . '> ' . $label . '</label> <label>At <input aria-label="' . $label . ' time" type="time" name="' . $kind . '_time" value="' . esc_attr( $s[$kind . '_time'] ) . '" required></label></p>';
        }
        echo '<p><label>Keep latest automatic backups <select name="keep">'; foreach ( array( 7, 14, 30 ) as $n ) { echo '<option ' . selected( $s['keep'], $n, false ) . '>' . $n . '</option>'; }
        echo '</select></label> Manual and recovery backups are retained. Old automatic copies expire only after a new successful backup.</p>'; submit_button( 'Save daily schedule', 'secondary', 'submit', false ); echo '</form>';
        echo '<p>Next scheduler check: ' . esc_html( $next ? wp_date( 'Y-m-d H:i:s', $next ) : 'not scheduled' ) . '. Last check: ' . esc_html( $last ? wp_date( 'Y-m-d H:i:s', $last ) : 'not run yet' ) . '.</p><p>WordPress cron depends on requests. For reliable timing on a quiet site, configure the hosting scheduler to run WordPress cron every five minutes. Deactivating Eola Care stops its scheduled tasks. Backup storage stays on this server; keep off-server copies too.</p></section>';
        echo '<section class="ec-card"><h2>Dated management reports</h2><form method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eola_care_report">'; wp_nonce_field( 'eola_care_report' );
        echo '<label for="ec-report-date">Report date</label> <input id="ec-report-date" type="date" name="date" value="' . esc_attr( wp_date( 'Y-m-d' ) ) . '" max="' . esc_attr( wp_date( 'Y-m-d' ) ) . '" required> <button class="button" name="format" value="csv">Download daily care CSV</button> <button class="button" name="format" value="html">Download client report</button></form><p>The client report opens in a browser and can be printed or saved as PDF. History keeps the latest 730 scheduled runs; a missing run is shown as not recorded.</p><div class="ec-scroll"><table><thead><tr><th>Date</th><th>Task</th><th>Status</th><th>Result</th></tr></thead><tbody>';
        foreach ( array_slice( array_reverse( get_option( 'eola_care_runs', array() ), true ), 0, 20 ) as $run ) {
            $result = $run['message'] ?? 'Background work in progress';
            if ( isset( $run['flagged'] ) ) { $result .= ' Flagged: ' . $run['flagged'] . '; skipped: ' . $run['skipped'] . '.'; }
            echo '<tr><td>' . esc_html( $run['date'] ) . '</td><td>' . esc_html( $run['kind'] ) . '</td><td>' . esc_html( $run['status'] ) . '</td><td>' . esc_html( $result ) . '</td></tr>';
        } echo '</tbody></table></div></section>';
    }
    public static function care_report() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); } check_admin_referer( 'eola_care_report' );
        $date = sanitize_text_field( wp_unslash( $_GET['date'] ?? '' ) );
        $parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
        if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date || $date > wp_date( 'Y-m-d' ) ) { wp_die( 'Choose a valid report date.', '', array( 'response' => 400 ) ); }
        $runs = array_filter( get_option( 'eola_care_runs', array() ), fn( $r ) => $r['date'] === $date );
        $rows = array();
        foreach ( array( 'backup', 'scan' ) as $kind ) {
            $matching = array_filter( $runs, fn( $r ) => $r['kind'] === $kind );
            if ( ! $matching ) { $rows[] = array( $kind, 'Not recorded', '', '', '', '', '', '', 'No scheduled run is recorded for this date.' ); }
            foreach ( $matching as $r ) { $rows[] = array( $kind, $r['status'], $r['started'] ?? '', $r['finished'] ?? '', $r['files'] ?? '', $r['flagged'] ?? '', $r['skipped'] ?? '', $r['core_checksums'] ?? '', ( $r['message'] ?? '' ) . ( isset( $r['retention_note'] ) ? ' ' . $r['retention_note'] : '' ) ); }
        }
        $headers = array( 'Task', 'Status', 'Started UTC', 'Finished UTC', 'Files', 'Flagged', 'Skipped', 'Core checksums', 'Notes' );
        $note = 'Scheduled work only. A completed scan is not a guarantee that the site is malware-free. Flags require review. Backups are stored on this server and older automatic copies may expire under retention settings.';
        nocache_headers(); header( 'X-Content-Type-Options: nosniff' );
        if ( 'html' !== ( $_GET['format'] ?? '' ) ) {
            header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="eola-care-' . $date . '.csv"' );
            $out = fopen( 'php://output', 'w' );
            foreach ( array_merge( array( array( 'Eola Care', home_url(), $date, wp_timezone_string(), 'Generated ' . gmdate( 'c' ) ), $headers ), $rows, array( array( $note ) ) ) as $row ) {
                fputcsv( $out, array_map( fn( $v ) => preg_match( '/^[=+@\-\t\r]/', (string) $v ) ? "'" . $v : $v, $row ), ',', '"', '' );
            } fclose( $out ); exit;
        }
        header( 'Content-Type: text/html; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="eola-care-' . $date . '.html"' );
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily care report ' . esc_html( $date ) . '</title><style>body{font:15px/1.6 Arial,sans-serif;color:#252b45;max-width:980px;margin:40px auto;padding:24px}h1,h2{color:#4b5598}section{border-top:1px solid #ddd;margin-top:24px;padding-top:16px}dl{display:grid;grid-template-columns:170px 1fr;gap:6px}dt{font-weight:bold}dd{margin:0;overflow-wrap:anywhere}button{padding:10px 16px}small{color:#555}@media print{button{display:none}section{break-inside:avoid}body{margin:0}}</style><body><button onclick="window.print()">Print / Save as PDF</button><h1>Daily website care report</h1><p>' . esc_html( home_url() . ' | ' . $date . ' | ' . wp_timezone_string() ) . '</p><small>Generated ' . esc_html( gmdate( 'c' ) ) . ' UTC</small>';
        foreach ( $rows as $row ) { echo '<section><h2>' . esc_html( ucfirst( $row[0] ) ) . '</h2><dl>'; foreach ( $headers as $i => $label ) { if ( 0 === $i ) { continue; } echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( '' === (string) $row[$i] ? 'Not applicable / not recorded' : (string) $row[$i] ) . '</dd>'; } echo '</dl></section>'; }
        echo '<section><p>' . esc_html( $note ) . '</p></section></body></html>'; exit;
    }
}
