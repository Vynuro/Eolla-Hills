<?php
defined( 'ABSPATH' ) || exit;
/**
 * Page-load rate limiting: too many public page opens from one IP in a short window
 * blocks that IP for a while. Only WordPress page renders are counted (not images,
 * CSS or admin/REST/AJAX requests). Logged-in users, allowlisted IPs and verified
 * Google/Bing crawlers are never blocked. This is application-level protection; it
 * does not stop network floods, which need hosting/CDN firewalls.
 */
trait Eola_Care_Firewall {
    public static function firewall_settings() {
        return wp_parse_args( get_option( 'eola_care_firewall', array() ), array( 'enabled' => 1, 'limit' => 10, 'window' => 60, 'block' => 900, 'allow' => array() ) );
    }
    private static function client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
    }
    private static function ip_key( $ip ) { return substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 ); }
    /** Daily counters of refused attack attempts for the management report. */
    public static function count_event( $type ) {
        $events = get_option( 'eola_care_events', array() ); $day = wp_date( 'Y-m-d' );
        $events[$day][$type] = ( $events[$day][$type] ?? 0 ) + 1;
        ksort( $events ); update_option( 'eola_care_events', array_slice( $events, -90, null, true ), false );
    }
    public static function events_between( $from_date, $to_date ) {
        $total = array();
        foreach ( get_option( 'eola_care_events', array() ) as $day => $counts ) {
            if ( $day < $from_date || $day >= $to_date ) { continue; }
            foreach ( $counts as $type => $n ) { $total[$type] = ( $total[$type] ?? 0 ) + (int) $n; }
        }
        return $total;
    }
    /** Hardening that also feeds the report: XML-RPC off, no user enumeration, spam counted. */
    public static function harden() {
        add_filter( 'xmlrpc_enabled', '__return_false' );
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
            self::count_event( 'xmlrpc' );
            status_header( 403 ); nocache_headers(); exit( 'XML-RPC is disabled on this website.' );
        }
        add_filter( 'rest_endpoints', function ( $endpoints ) {
            if ( is_user_logged_in() ) { return $endpoints; }
            foreach ( array_keys( $endpoints ) as $route ) { if ( str_starts_with( $route, '/wp/v2/users' ) ) { unset( $endpoints[$route] ); } }
            return $endpoints;
        } );
        add_filter( 'pre_comment_approved', function ( $approved ) { if ( 'spam' === $approved ) { self::count_event( 'comment_spam' ); } return $approved; }, 99 );
        add_action( 'comment_closed', function () { self::count_event( 'comment_spam' ); } );
    }
    public static function firewall() {
        $s = self::firewall_settings();
        if ( empty( $s['enabled'] ) || is_user_logged_in() || is_robots() || is_favicon() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) { return; }
        $ip = self::client_ip();
        if ( ! $ip || in_array( $ip, (array) $s['allow'], true ) ) { return; }
        $key = self::ip_key( $ip ); $now = time();
        $uri = wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' );
        $reason = '';
        // No real page uses these paths, so match whether or not WordPress reports a 404.
        if ( preg_match( '~(/\.env|/\.git/|/\.aws|wp-config\.php[^/]|\.sql(\.gz)?$|phpmyadmin|/adminer|/vendor/phpunit|eval-stdin|/(wso|shell|alfa|c99|r57)[^/]*\.php|\.\./|/wp-content/(uploads|plugins)/[^?]*\.php)~i', $uri ) ) { $reason = 'probe'; }
        elseif ( isset( $_GET['author'] ) && ! is_admin() ) { $reason = 'user_scan'; }
        if ( $reason && ! self::verified_crawler( $ip ) ) {
            self::count_event( $reason );
            $blocked = array( 'id' => wp_generate_uuid4(), 'ip' => $ip, 'reason' => $reason, 'started' => $now, 'until' => $now + (int) $s['block'], 'hits' => 1, 'opens' => 1, 'agent' => mb_substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 120 ), 'path' => mb_substr( esc_url_raw( $uri ), 0, 120 ) );
            set_transient( 'eola_fw_b_' . $key, $blocked, (int) $s['block'] ); self::block_log( $blocked ); self::refuse( (int) $s['block'] );
        }
        $blocked = get_transient( 'eola_fw_b_' . $key );
        if ( is_array( $blocked ) && $blocked['until'] > $now ) {
            if ( $blocked['hits'] < 100000 ) { $blocked['hits']++; set_transient( 'eola_fw_b_' . $key, $blocked, $blocked['until'] - $now ); self::block_log( $blocked ); }
            self::refuse( $blocked['until'] - $now );
        }
        $hits = array_filter( (array) get_transient( 'eola_fw_h_' . $key ), fn( $t ) => is_int( $t ) && $t > $now - (int) $s['window'] );
        $hits[] = $now;
        if ( count( $hits ) <= (int) $s['limit'] ) { set_transient( 'eola_fw_h_' . $key, array_slice( $hits, - (int) $s['limit'] ), (int) $s['window'] ); return; }
        // Only now pay for DNS: genuine search engine crawlers keep access for SEO.
        if ( self::verified_crawler( $ip ) ) { return; }
        delete_transient( 'eola_fw_h_' . $key );
        $blocked = array( 'id' => wp_generate_uuid4(), 'ip' => $ip, 'reason' => 'rate', 'started' => $now, 'until' => $now + (int) $s['block'], 'hits' => 1, 'opens' => count( $hits ), 'agent' => mb_substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 120 ), 'path' => mb_substr( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ), 0, 120 ) );
        set_transient( 'eola_fw_b_' . $key, $blocked, (int) $s['block'] );
        self::block_log( $blocked );
        self::refuse( (int) $s['block'] );
    }
    public static function reason_label( $reason ) {
        return array( 'rate' => 'Too many page requests (bot/scraper)', 'probe' => 'Hacking attempt: searched for secret or exploit files', 'user_scan' => 'Hacking attempt: searched for admin usernames' )[$reason] ?? $reason;
    }
    private static function verified_crawler( $ip ) {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ( ! preg_match( '/Googlebot|Google-InspectionTool|bingbot|AdIdxBot/i', $agent ) ) { return false; }
        $cache = 'eola_fw_bot_' . self::ip_key( $ip );
        $known = get_transient( $cache );
        if ( false !== $known ) { return 'yes' === $known; }
        // Reverse DNS must be a Google/Bing host that resolves forward to the same IP.
        $host = gethostbyaddr( $ip ); $ok = false;
        if ( $host && $host !== $ip && preg_match( '/\.(googlebot\.com|google\.com|googleusercontent\.com|search\.msn\.com)$/i', $host ) ) {
            $records = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? (array) dns_get_record( $host, DNS_AAAA ) : (array) dns_get_record( $host, DNS_A );
            foreach ( $records as $record ) { if ( inet_pton( $record['ipv6'] ?? $record['ip'] ?? '' ) === inet_pton( $ip ) ) { $ok = true; break; } }
        }
        set_transient( $cache, $ok ? 'yes' : 'no', DAY_IN_SECONDS );
        return $ok;
    }
    private static function block_log( $blocked ) {
        $log = get_option( 'eola_care_blocks', array() );
        $log[ $blocked['id'] ] = $blocked;
        $cutoff = time() - 60 * DAY_IN_SECONDS;
        $log = array_slice( array_filter( $log, fn( $b ) => $b['started'] > $cutoff ), -2000, null, true );
        update_option( 'eola_care_blocks', $log, false );
    }
    private static function refuse( $seconds ) {
        nocache_headers(); header( 'Retry-After: ' . max( 1, (int) $seconds ) );
        wp_die( 'Too many page requests from your network in a short time. Please wait ' . (int) ceil( $seconds / 60 ) . ' minutes and try again.', 'Please slow down', array( 'response' => 429 ) );
    }
    public static function blocks_between( $since, $until ) {
        return array_filter( get_option( 'eola_care_blocks', array() ), fn( $b ) => $b['started'] >= $since && $b['started'] < $until );
    }
    public static function save_firewall() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'eola_care_firewall' );
        $allow = array_values( array_unique( array_filter( array_map( 'trim', preg_split( '/[\s,]+/', wp_unslash( $_POST['allow'] ?? '' ) ) ), fn( $ip ) => filter_var( $ip, FILTER_VALIDATE_IP ) ) ) );
        update_option( 'eola_care_firewall', array_merge( self::firewall_settings(), array( 'enabled' => isset( $_POST['enabled'] ) ? 1 : 0, 'allow' => array_slice( $allow, 0, 100 ) ) ), false );
        if ( ! empty( $_POST['unblock'] ) ) {
            $ip = wp_unslash( $_POST['unblock'] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { delete_transient( 'eola_fw_b_' . self::ip_key( $ip ) ); delete_transient( 'eola_fw_h_' . self::ip_key( $ip ) ); self::audit( 'firewall_unblock', $ip ); }
        }
        wp_safe_redirect( admin_url( 'admin.php?page=eola-care' ) ); exit;
    }
    private static function firewall_screen() {
        $s = self::firewall_settings(); $now = time();
        echo '<section class="ec-card"><h2>Visitor rate protection</h2><p>An IP that opens more than ' . (int) $s['limit'] . ' pages within ' . (int) $s['window'] . ' seconds is blocked for ' . (int) ( $s['block'] / 60 ) . ' minutes. Logged-in users, allowlisted IPs and verified Google/Bing crawlers are never blocked. Several people on one office or mobile network can share an IP. This protects WordPress from fast scrapers and bots; network floods need hosting-level protection.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eola_care_firewall">'; wp_nonce_field( 'eola_care_firewall' );
        echo '<p><label><input type="checkbox" name="enabled" value="1" ' . checked( $s['enabled'], 1, false ) . '> Enable visitor rate protection</label></p><p><label for="ec-allow">Never block these IPs (one per line)</label><br><textarea id="ec-allow" name="allow" rows="3" cols="40">' . esc_textarea( implode( "\n", (array) $s['allow'] ) ) . '</textarea></p>';
        submit_button( 'Save protection settings', 'secondary', 'submit', false ); echo '</form>';
        echo '<h3>Recent blocks</h3><div class="ec-scroll"><table><thead><tr><th>Blocked at</th><th>IP</th><th>Reason</th><th>Page opens</th><th>Refused requests</th><th>Until</th><th></th></tr></thead><tbody>';
        $recent = array_slice( array_reverse( get_option( 'eola_care_blocks', array() ) ), 0, 25 );
        foreach ( $recent as $b ) {
            echo '<tr><td>' . esc_html( wp_date( 'Y-m-d H:i', $b['started'] ) ) . '</td><td>' . esc_html( $b['ip'] ) . '</td><td>' . esc_html( self::reason_label( $b['reason'] ?? 'rate' ) ) . '<br><small>' . esc_html( $b['path'] ?? '' ) . '</small></td><td>' . (int) $b['opens'] . '</td><td>' . (int) $b['hits'] . '</td><td>' . esc_html( wp_date( 'Y-m-d H:i', $b['until'] ) ) . '</td><td>';
            if ( $b['until'] > $now ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eola_care_firewall"><input type="hidden" name="unblock" value="' . esc_attr( $b['ip'] ) . '">' . ( $s['enabled'] ? '<input type="hidden" name="enabled" value="1">' : '' ) . '<input type="hidden" name="allow" value="' . esc_attr( implode( ' ', (array) $s['allow'] ) ) . '">'; wp_nonce_field( 'eola_care_firewall' ); echo '<button class="button">Unblock</button></form>'; }
            echo '</td></tr>';
        }
        if ( ! $recent ) { echo '<tr><td colspan="7">No IP has been blocked.</td></tr>'; }
        echo '</tbody></table></div><p>Block history is kept for 60 days.</p></section>';
    }
}
