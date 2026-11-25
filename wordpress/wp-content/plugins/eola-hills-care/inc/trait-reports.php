<?php
defined( 'ABSPATH' ) || exit;
/**
 * Website management report every few days: visitors (Eola Analytics, if active),
 * security blocks, login lockouts, backups, scans and update status. Emailed to the
 * configured recipients and archived privately for download.
 */
trait Eola_Care_Reports {
    public static function report_settings() {
        return wp_parse_args( get_option( 'eola_care_reports', array() ), array( 'enabled' => 1, 'every' => 3, 'time' => '08:00', 'recipients' => '' ) );
    }
    public static function fixed_recipients() { return array_values( array_unique( array_filter( array( get_option( 'admin_email' ), EOLA_CARE_DEVELOPER_EMAIL ), 'is_email' ) ) ); }
    private static function recipients() {
        $extra = array_filter( array_map( 'trim', explode( ',', (string) self::report_settings()['recipients'] ) ), 'is_email' );
        return array_values( array_unique( array_map( 'strtolower', array_merge( self::fixed_recipients(), $extra ) ) ) );
    }
    private static function mail( $subject, $html ) {
        $to = self::recipients(); $error = '';
        if ( ! $to ) { return array( false, 'No valid recipient email address.', 0 ); }
        $capture = function ( $e ) use ( &$error ) { $error = $e->get_error_message(); };
        add_action( 'wp_mail_failed', $capture );
        $sent = wp_mail( $to, $subject . ' | ' . wp_parse_url( home_url(), PHP_URL_HOST ), $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
        remove_action( 'wp_mail_failed', $capture );
        return array( (bool) $sent, $sent ? '' : ( $error ?: 'The server did not accept the email.' ), count( $to ) );
    }
    /** One short email per day once the scheduled backup and scan finish, or as soon as one fails. */
    public static function daily_email( $date, $failed = false ) {
        $sent = get_option( 'eola_care_daily_email', array() );
        if ( ! empty( $sent[$date] ) && ! $failed ) { return; }
        $runs = array_filter( get_option( 'eola_care_runs', array() ), fn( $r ) => $r['date'] === $date );
        $rows = ''; $problem = $failed; $cell = 'padding:8px;border-bottom:1px solid #e3e4ec';
        foreach ( array( 'backup' => 'Backup', 'scan' => 'Security scan' ) as $kind => $label ) {
            $match = array_values( array_filter( $runs, fn( $r ) => $r['kind'] === $kind ) ); $r = end( $match ) ?: array();
            $status = $r ? ( 'done' === $r['status'] ? 'Completed' : ucfirst( $r['status'] ) ) : 'Not run';
            if ( $r && 'error' === $r['status'] ) { $problem = true; }
            if ( ! empty( $r['flagged'] ) ) { $problem = true; }
            $detail = 'backup' === $kind ? ( isset( $r['bytes'] ) ? size_format( $r['bytes'] ) . ', ' . (int) $r['files'] . ' files' : ( $r['message'] ?? '' ) ) : ( isset( $r['flagged'] ) ? (int) $r['files'] . ' files checked, ' . (int) $r['flagged'] . ' flagged for review' : ( $r['message'] ?? '' ) );
            $rows .= '<tr><td style="' . $cell . '"><strong>' . esc_html( $label ) . '</strong></td><td style="' . $cell . '">' . esc_html( $status ) . '</td><td style="' . $cell . '">' . esc_html( $detail ) . '</td></tr>';
        }
        $html = '<div style="max-width:620px;font:15px/1.5 Arial,sans-serif;color:#25232B"><p style="color:#55A044;font-size:12px;letter-spacing:1px;margin:0">DAILY BACKUP AND SECURITY</p><h2 style="margin:6px 0">' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) . ' | ' . $date ) . '</h2><table cellspacing="0" width="100%">' . $rows . '</table><p style="font-size:12px;color:#666">Backups are stored privately on the server. Review flagged files in WordPress: Eola Care.</p></div>';
        [ $ok, $error ] = self::mail( ( $problem ? 'Action needed: ' : '' ) . 'Daily backup and security ' . $date, $html );
        $sent[$date] = $ok ? 'sent' : 'failed: ' . $error;
        update_option( 'eola_care_daily_email', array_slice( $sent, -60, null, true ), false );
    }
    /** Runs on the five-minute Care tick. */
    public static function report_tick() {
        if ( is_multisite() ) { return; }
        $s = self::report_settings(); $now = current_datetime(); $today = $now->format( 'Y-m-d' );
        if ( empty( $s['enabled'] ) || $now->format( 'H:i' ) < $s['time'] ) { return; }
        $last = get_option( 'eola_care_report_last' );
        if ( $last && $today < DateTimeImmutable::createFromFormat( '!Y-m-d', $last, wp_timezone() )->modify( '+' . (int) $s['every'] . ' days' )->format( 'Y-m-d' ) ) { return; }
        // Claim the day first so overlapping ticks cannot send twice.
        if ( get_transient( 'eola_care_report_sending' ) ) { return; }
        set_transient( 'eola_care_report_sending', 1, 10 * MINUTE_IN_SECONDS );
        update_option( 'eola_care_report_last', $today, false );
        try { self::send_report( $now->setTime( 0, 0 ), (int) $s['every'] ); }
        catch ( Throwable $e ) { self::report_record( $today, array( 'error' => $e->getMessage() ) ); }
        finally { delete_transient( 'eola_care_report_sending' ); }
    }
    private static function report_record( $date, $data ) {
        $log = get_option( 'eola_care_report_log', array() );
        $log[$date] = array_merge( $log[$date] ?? array(), $data, array( 'date' => $date ) );
        update_option( 'eola_care_report_log', array_slice( $log, -200, null, true ), false );
    }
    /** Builds, archives and emails the report for the $days whole days before $end (local midnight). */
    public static function send_report( DateTimeImmutable $end, $days ) {
        $start = $end->modify( '-' . $days . ' days' );
        $html = self::report_html( $start, $end );
        $date = $end->format( 'Y-m-d' );
        file_put_contents( self::path( 'report-' . $date . '.html' ), $html, LOCK_EX ); @chmod( self::path( 'report-' . $date . '.html' ), 0600 );
        [ $sent, $error, $count ] = self::mail( 'Website report ' . $start->format( 'M j' ) . ' - ' . $end->modify( '-1 day' )->format( 'M j, Y' ), $html );
        self::report_record( $date, array( 'period' => $start->format( 'Y-m-d' ) . ' to ' . $end->modify( '-1 day' )->format( 'Y-m-d' ), 'sent' => $sent, 'recipients' => $count, 'error' => $error, 'created' => gmdate( 'c' ) ) );
        return (bool) $sent;
    }
    private static function report_data( DateTimeImmutable $start, DateTimeImmutable $end ) {
        $utc = new DateTimeZone( 'UTC' ); $from = $start->getTimestamp(); $to = $end->getTimestamp();
        $d = array( 'analytics' => null );
        if ( class_exists( 'Eola_Analytics' ) && get_option( 'eola_analytics_schema' ) === '1' ) {
            $r = Eola_Analytics::report_between( $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
            $d['analytics'] = array( 'visitors' => (int) $r['summary']['visitors'], 'sessions' => (int) $r['summary']['sessions'], 'views' => (int) $r['summary']['views'], 'returning' => (int) $r['returning'],
                'sources' => array_slice( array_map( fn( $v ) => array( $v['source'] . ( $v['medium'] ? ' / ' . $v['medium'] : '' ), (int) $v['sessions'] ), $r['tables']['sources'] ), 0, 5 ),
                'devices' => array_map( fn( $v ) => array( ucfirst( $v['device'] ), (int) $v['sessions'] ), $r['tables']['devices'] ) );
        }
        $blocks = self::blocks_between( $from, $to );
        $ev = self::events_between( $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) );
        $rate = array_filter( $blocks, fn( $b ) => 'rate' === ( $b['reason'] ?? 'rate' ) );
        $d['attacks'] = array( 'probe' => (int) ( $ev['probe'] ?? 0 ), 'user_scan' => (int) ( $ev['user_scan'] ?? 0 ), 'xmlrpc' => (int) ( $ev['xmlrpc'] ?? 0 ), 'spam' => (int) ( $ev['comment_spam'] ?? 0 ), 'bots' => count( $rate ) );
        $d['blocks'] = array( 'count' => count( $blocks ), 'ips' => count( array_unique( array_column( $blocks, 'ip' ) ) ), 'refused' => array_sum( array_column( $blocks, 'hits' ) ), 'rows' => array_slice( array_reverse( $blocks ), 0, 10 ) );
        $lockouts = 0;
        foreach ( (array) get_option( 'limit_login_logged', array() ) as $users ) {
            foreach ( (array) $users as $entry ) { if ( is_array( $entry ) && ( $entry['date'] ?? 0 ) >= $from && ( $entry['date'] ?? 0 ) < $to ) { $lockouts += (int) ( $entry['counter'] ?? 1 ); } }
        }
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $d['login'] = array( 'active' => is_plugin_active( 'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' ), 'lockouts' => $lockouts );
        $runs = array_filter( get_option( 'eola_care_runs', array() ), fn( $r ) => $r['date'] >= $start->format( 'Y-m-d' ) && $r['date'] < $end->format( 'Y-m-d' ) );
        $d['runs'] = array();
        for ( $day = $start; $day < $end; $day = $day->modify( '+1 day' ) ) {
            foreach ( array( 'backup', 'scan' ) as $kind ) {
                $match = array_values( array_filter( $runs, fn( $r ) => $r['date'] === $day->format( 'Y-m-d' ) && $r['kind'] === $kind ) );
                $r = end( $match ) ?: array();
                $detail = 'backup' === $kind ? ( isset( $r['bytes'] ) ? size_format( $r['bytes'] ) . ', ' . (int) $r['files'] . ' files' : '' ) : ( isset( $r['flagged'] ) ? (int) $r['files'] . ' files checked, ' . (int) $r['flagged'] . ' flagged for review' : '' );
                $d['runs'][] = array( $day->format( 'D M j' ), 'backup' === $kind ? 'Backup' : 'Security scan', $r ? ( 'done' === $r['status'] ? 'Completed' : ucfirst( $r['status'] ) ) : 'Not recorded', $detail ?: ( $r['message'] ?? '' ) );
            }
        }
        wp_update_plugins(); $plugins = get_site_transient( 'update_plugins' ); $core = get_site_transient( 'update_core' );
        $core_update = false; foreach ( (array) ( $core->updates ?? array() ) as $u ) { if ( 'upgrade' === ( $u->response ?? '' ) ) { $core_update = $u->current; } }
        $d['site'] = array( 'WordPress' => get_bloginfo( 'version' ) . ( $core_update ? ' (update ' . $core_update . ' available)' : ' (up to date)' ), 'PHP' => PHP_VERSION, 'Plugin updates waiting' => count( (array) ( $plugins->response ?? array() ) ), 'HTTPS' => 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) ? 'Enabled' : 'Not enabled' );
        return $d;
    }
    public static function report_html( DateTimeImmutable $start, DateTimeImmutable $end ) {
        $d = self::report_data( $start, $end ); $e = 'esc_html';
        $table = function ( $headers, $rows ) use ( $e ) {
            $h = '<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px"><tr>';
            foreach ( $headers as $x ) { $h .= '<th align="left" style="border-bottom:2px solid #4B5598;color:#4B5598">' . $e( $x ) . '</th>'; } $h .= '</tr>';
            foreach ( $rows as $row ) { $h .= '<tr>'; foreach ( $row as $x ) { $h .= '<td style="border-bottom:1px solid #e3e4ec">' . $e( (string) $x ) . '</td>'; } $h .= '</tr>'; }
            return $h . '</table>';
        };
        $metric = fn( $label, $value ) => '<td width="25%" style="background:#F1F2F8;padding:14px;border-radius:8px"><div style="font-size:12px;color:#555">' . $e( $label ) . '</div><div style="font-size:24px;font-weight:bold;color:#25232B">' . $e( (string) $value ) . '</div></td>';
        $h2 = fn( $t ) => '<h2 style="color:#4B5598;font-size:18px;margin:28px 0 10px">' . $e( $t ) . '</h2>';
        $period = $start->format( 'M j' ) . ' - ' . $end->modify( '-1 day' )->format( 'M j, Y' );
        $o = '<!doctype html><html><body style="margin:0;background:#f5f5f8"><div style="max-width:680px;margin:0 auto;padding:24px;font:15px/1.5 Arial,sans-serif;color:#25232B;background:#fff">';
        $o .= '<p style="margin:0;color:#55A044;font-size:12px;letter-spacing:1px">WEBSITE MANAGEMENT REPORT</p><h1 style="margin:6px 0;font-size:24px">' . $e( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</h1><p style="margin:0;color:#555">' . $e( $period . ' (' . wp_timezone_string() . ')' ) . '</p>';
        $o .= $h2( 'Visitors' );
        if ( $d['analytics'] ) {
            $a = $d['analytics'];
            $o .= '<table width="100%" cellspacing="6"><tr>' . $metric( 'Visitors', $a['visitors'] ) . $metric( 'Visits', $a['sessions'] ) . $metric( 'Page views', $a['views'] ) . $metric( 'Returning visits', $a['returning'] ) . '</tr></table>';
            if ( $a['sources'] ) { $o .= '<p style="margin:14px 0 4px"><strong>Where visitors came from</strong></p>' . $table( array( 'Source', 'Visits' ), $a['sources'] ); }
            if ( $a['devices'] ) { $o .= '<p style="margin:14px 0 4px"><strong>Devices</strong></p>' . $table( array( 'Device', 'Visits' ), $a['devices'] ); }
            $o .= '<p style="font-size:12px;color:#666">Measured browsers only: logged-in staff, opted-out browsers and known bots are excluded.</p>';
        } else { $o .= '<p>Eola Analytics is not active, so visitor numbers are not included.</p>'; }
        $b = $d['blocks'];
        $at = $d['attacks']; $login = $d['login']['active'] ? $d['login']['lockouts'] : 0;
        $hacking = $at['probe'] + $at['user_scan'] + $at['xmlrpc'] + $login;
        $o .= $h2( 'Security: attacks blocked' ) . '<table width="100%" cellspacing="6"><tr>' . $metric( 'Hacking attempts blocked', $hacking ) . $metric( 'Bots / scrapers blocked', $at['bots'] ) . $metric( 'Spam comments blocked', $at['spam'] ) . $metric( 'Blocked IPs', $b['ips'] ) . '</tr></table>';
        $o .= $table( array( 'Protection', 'Blocked' ), array(
            array( 'Searches for secret or exploit files (.env, backups, shells)', $at['probe'] ),
            array( 'Searches for admin usernames', $at['user_scan'] ),
            array( 'Password guessing via XML-RPC (disabled)', $at['xmlrpc'] ),
            array( 'Login lockouts after wrong passwords', $d['login']['active'] ? $login : 'Protection not active' ),
            array( 'Bots and scrapers opening pages too fast', $at['bots'] ),
            array( 'Spam comments', $at['spam'] ),
            array( 'Requests refused while blocked', $b['refused'] ),
        ) );
        if ( $b['rows'] ) { $o .= '<p style="margin:14px 0 4px"><strong>Latest blocked IPs</strong> (blocked for 15 minutes)</p>' . $table( array( 'Blocked at', 'IP', 'Reason' ), array_map( fn( $x ) => array( wp_date( 'M j H:i', $x['started'] ), $x['ip'], self::reason_label( $x['reason'] ?? 'rate' ) ), $b['rows'] ) ); }
        $o .= '<p style="font-size:12px;color:#666">Counts are automated attempts the website refused. Most come from bots that scan many websites. A blocked IP can be shared by several people on one network.</p>';
        $o .= $h2( 'Daily backups and security scans' ) . $table( array( 'Day', 'Task', 'Status', 'Details' ), $d['runs'] );
        $o .= '<p style="font-size:12px;color:#666">Backups are kept privately on the server. A completed scan is not a guarantee that a site is malware-free; flagged files are reviewed by the developer.</p>';
        $o .= $h2( 'Website status' ) . $table( array( 'Item', 'Status' ), array_map( null, array_keys( $d['site'] ), array_values( $d['site'] ) ) );
        $o .= '<p style="margin-top:28px;font-size:12px;color:#666">Generated ' . $e( wp_date( 'M j, Y H:i' ) ) . ' by Eola Care.</p></div></body></html>';
        return $o;
    }
    public static function save_reports() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'eola_care_reports' );
        $time = wp_unslash( $_POST['time'] ?? '08:00' );
        if ( ! is_string( $time ) || ! preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time ) ) { wp_die( 'Choose a valid time.' ); }
        $emails = array_filter( array_map( 'trim', explode( ',', str_replace( array( "\n", ';' ), ',', wp_unslash( $_POST['recipients'] ?? '' ) ) ) ) );
        foreach ( $emails as $email ) { if ( ! is_email( $email ) ) { wp_die( esc_html( 'Not a valid email address: ' . $email ) ); } }
        update_option( 'eola_care_reports', array_merge( self::report_settings(), array( 'enabled' => isset( $_POST['enabled'] ) ? 1 : 0, 'time' => $time, 'recipients' => implode( ', ', array_slice( $emails, 0, 10 ) ) ) ), false );
        $notice = '';
        if ( ! empty( $_POST['send_now'] ) ) {
            try { $notice = self::send_report( current_datetime()->setTime( 0, 0 ), (int) self::report_settings()['every'] ) ? 'sent' : 'failed'; }
            catch ( Throwable $e ) { $notice = 'failed'; }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=eola-care' . ( $notice ? '&report=' . $notice : '' ) ) ); exit;
    }
    public static function download_report() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'eola_care_report_file' );
        $date = sanitize_text_field( wp_unslash( $_GET['date'] ?? '' ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) || ! is_file( self::path( 'report-' . $date . '.html' ) ) ) { wp_die( 'Report not found.', '', array( 'response' => 404 ) ); }
        nocache_headers(); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Type: text/html; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="website-report-' . $date . '.html"' );
        readfile( self::path( 'report-' . $date . '.html' ) ); exit;
    }
    private static function reports_screen() {
        $s = self::report_settings(); $last = get_option( 'eola_care_report_last' );
        echo '<section class="ec-card"><h2>Website report every ' . (int) $s['every'] . ' days</h2>';
        if ( isset( $_GET['report'] ) ) { echo 'sent' === $_GET['report'] ? '<p class="ec-ok">Report sent.</p>' : '<p class="ec-alert">The report was saved but the email could not be sent. See the error below.</p>'; }
        echo '<p>Always emailed to the site administrator and the developer: <strong>' . esc_html( implode( ', ', self::fixed_recipients() ) ) . '</strong>. They also get a short email every day after the backup and security scan, and straight away if one fails. Change the administrator address in Settings &gt; General.</p>';
        echo '<p>Covers visitors, hacking attempts and bots blocked, spam comments, login lockouts, daily backups and scans and update status for the previous ' . (int) $s['every'] . ' days. Last automatic report: ' . esc_html( $last ?: 'not sent yet' ) . '.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eola_care_reports">'; wp_nonce_field( 'eola_care_reports' );
        echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked( $s['enabled'], 1, false ) . '> Send automatically</label> <label>at <input type="time" name="time" value="' . esc_attr( $s['time'] ) . '" required></label></p>';
        echo '<p><label for="ec-recipients">Also email (optional, comma separated)</label><br><input id="ec-recipients" class="regular-text" type="text" name="recipients" value="' . esc_attr( $s['recipients'] ) . '"></p>';
        submit_button( 'Save report settings', 'secondary', 'submit', false ); echo ' '; submit_button( 'Save and send a report now', 'primary', 'send_now', false ); echo '</form>';
        echo '<div class="ec-scroll"><table><thead><tr><th>Report date</th><th>Period</th><th>Email</th><th></th></tr></thead><tbody>';
        $log = array_slice( array_reverse( get_option( 'eola_care_report_log', array() ), true ), 0, 20, true );
        foreach ( $log as $date => $r ) {
            echo '<tr><td>' . esc_html( $date ) . '</td><td>' . esc_html( $r['period'] ?? '' ) . '</td><td>' . esc_html( ! empty( $r['sent'] ) ? 'Sent to ' . (int) $r['recipients'] : 'Not sent: ' . ( $r['error'] ?? '' ) ) . '</td><td>';
            if ( isset( $r['period'] ) ) { echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eola_care_report_file&date=' . $date ), 'eola_care_report_file' ) ) . '">Download</a>'; }
            echo '</td></tr>';
        }
        if ( ! $log ) { echo '<tr><td colspan="4">No report yet.</td></tr>'; }
        echo '</tbody></table></div><p>Hosting email can land in spam; add the sender to contacts. If emails never arrive, an SMTP mail plugin may be needed.</p></section>';
    }
}
