<?php
defined( 'ABSPATH' ) || exit;
final class Eola_Analytics {
    use Eola_Analytics_Daily_Reports;
    const EVENTS = array( 'page_view', 'engagement', 'section_view', 'gallery_open', 'gallery_filter', 'photo_change', 'floorplan_open', 'download', 'contact_click', 'showing_click', 'navigation_click', 'video_start', 'video_complete', 'amenity_open' );
    public static function table() { global $wpdb; return $wpdb->prefix . 'eola_events'; }
    public static function boot() {
        add_action( 'init', function () { if ( get_option( 'eola_analytics_schema' ) !== '1' ) { self::activate(); } } );
        add_action( 'rest_api_init', function () {
            register_rest_route( 'eola-analytics/v1', '/events', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array( __CLASS__, 'collect' ) ) );
        } );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'admin_menu', function () { add_menu_page( 'Eola Analytics', 'Eola Analytics', 'manage_options', 'eola-analytics', array( __CLASS__, 'screen' ), 'dashicons-chart-area', 31 ); } );
        add_action( 'admin_enqueue_scripts', function ( $hook ) { if ( 'toplevel_page_eola-analytics' === $hook ) { wp_enqueue_style( 'eola-analytics-admin', plugins_url( '../assets/admin.css', __FILE__ ), array(), EOLA_ANALYTICS_VERSION ); } } );
        add_action( 'admin_post_eola_analytics_settings', array( __CLASS__, 'settings' ) );
        add_action( 'admin_post_eola_analytics_export', array( __CLASS__, 'export' ) );
        add_action( 'admin_post_eola_analytics_daily', array( __CLASS__, 'daily_export' ) );
        add_action( 'eola_analytics_cleanup', array( __CLASS__, 'cleanup' ) );
    }
    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $charset = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_key char(64) NOT NULL,
            received_at datetime NOT NULL,
            visitor char(64) NOT NULL,
            session char(64) NOT NULL,
            event varchar(32) NOT NULL,
            page_id bigint(20) unsigned NOT NULL,
            target varchar(80) NOT NULL DEFAULT '',
            source varchar(100) NOT NULL DEFAULT 'direct',
            medium varchar(40) NOT NULL DEFAULT '',
            campaign varchar(80) NOT NULL DEFAULT '',
            device varchar(12) NOT NULL DEFAULT 'desktop',
            seconds smallint(5) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY event_key (event_key),
            KEY received_at (received_at),
            KEY session (session),
            KEY visitor_time (visitor,received_at),
            KEY event_time (event,received_at)
        ) $charset;" );
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table ) { update_option( 'eola_analytics_schema', '1', false ); }
        add_option( 'eola_analytics_enabled', 1, '', false );
        if ( ! wp_next_scheduled( 'eola_analytics_cleanup' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'eola_analytics_cleanup' ); }
    }
    public static function cleanup() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE received_at < %s', gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) );
    }
    public static function enqueue() {
        if ( ! get_option( 'eola_analytics_enabled' ) || is_user_logged_in() || is_preview() || ! is_singular() || 'publish' !== get_post_status() || post_password_required() || isset( $_GET['elementor-preview'] ) ) { return; }
        wp_enqueue_style( 'eola-analytics-choice', plugins_url( '../assets/choice.css', __FILE__ ), array(), EOLA_ANALYTICS_VERSION );
        wp_enqueue_script( 'eola-analytics', plugins_url( '../assets/tracker.js', __FILE__ ), array(), EOLA_ANALYTICS_VERSION, true );
        wp_add_inline_script( 'eola-analytics', 'window.eolaAnalytics=' . wp_json_encode( array( 'endpoint' => rest_url( 'eola-analytics/v1/events' ), 'page' => get_queried_object_id(), 'privacy' => get_privacy_policy_url() ) ) . ';', 'before' );
    }
    private static function fail( $message, $status = 400 ) { return new WP_Error( 'eola_analytics', $message, array( 'status' => $status ) ); }
    private static function uuid( $value ) { return is_string( $value ) && preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value ); }
    private static function label( $value, $max = 80 ) { return is_string( $value ) && strlen( $value ) <= $max && preg_match( '/^[a-zA-Z0-9_.\-]*$/D', $value ) ? strtolower( $value ) : ''; }
    public static function collect( WP_REST_Request $request ) {
        global $wpdb;
        if ( ! get_option( 'eola_analytics_enabled' ) ) { return self::fail( 'Collection is paused.', 403 ); }
        if ( strlen( $request->get_body() ) > 10000 ) { return self::fail( 'Request too large.', 413 ); }
        // Cookie auth in REST may have been reset without a REST nonce. Exclude staff anyway.
        if ( is_user_logged_in() || wp_validate_auth_cookie( '', 'logged_in' ) ) { return new WP_REST_Response( array( 'accepted' => 0 ), 200 ); }
        $origin = $request->get_header( 'origin' ); $site = wp_parse_url( home_url() ); $from = wp_parse_url( $origin );
        if ( ! $origin || ! is_array( $from ) || ( $from['host'] ?? '' ) !== ( $site['host'] ?? '' ) || ( $from['scheme'] ?? '' ) !== ( $site['scheme'] ?? '' ) || ( $from['port'] ?? null ) !== ( $site['port'] ?? null ) ) { return self::fail( 'Same-origin requests only.', 403 ); }
        if ( '1' === $request->get_header( 'sec-gpc' ) || '1' === $request->get_header( 'dnt' ) ) { return new WP_REST_Response( array( 'accepted' => 0 ), 200 ); }
        $ua = $request->get_header( 'user-agent' );
        if ( ! $ua || preg_match( '/bot|crawler|spider|headless|curl|wget|python|lighthouse/i', $ua ) ) { return new WP_REST_Response( array( 'accepted' => 0 ), 200 ); }
        $data = $request->get_json_params();
        if ( ! is_array( $data ) || 'automatic' !== ( $data['mode'] ?? '' ) || ! self::uuid( $data['visitor'] ?? null ) || ! self::uuid( $data['session'] ?? null ) || ! is_array( $data['events'] ?? null ) || count( $data['events'] ) < 1 || count( $data['events'] ) > 15 ) { return self::fail( 'Invalid event batch.' ); }
        $rows = array(); $visitor = hash_hmac( 'sha256', $data['visitor'], wp_salt( 'auth' ) ); $session = hash_hmac( 'sha256', $data['session'], wp_salt( 'auth' ) );
        foreach ( $data['events'] as $event ) {
            if ( ! is_array( $event ) || ! self::uuid( $event['id'] ?? null ) || ! in_array( $event['name'] ?? '', self::EVENTS, true ) ) { return self::fail( 'Unknown event.' ); }
            $page = get_post( absint( $event['page'] ?? 0 ) );
            if ( ! $page || 'publish' !== $page->post_status || $page->post_password || ! is_post_type_viewable( $page->post_type ) ) { return self::fail( 'Invalid public page.' ); }
            $rows[] = array( 'event_key' => hash( 'sha256', $data['session'] . $event['id'] ), 'received_at' => current_time( 'mysql', true ), 'visitor' => $visitor, 'session' => $session, 'event' => $event['name'], 'page_id' => $page->ID, 'target' => self::label( $event['target'] ?? '' ), 'source' => self::label( $data['source'] ?? '', 100 ) ?: 'direct', 'medium' => self::label( $data['medium'] ?? '', 40 ), 'campaign' => self::label( $data['campaign'] ?? '' ), 'device' => in_array( $data['device'] ?? '', array( 'desktop', 'tablet', 'mobile' ), true ) ? $data['device'] : 'desktop', 'seconds' => 'engagement' === $event['name'] ? min( 30, absint( $event['seconds'] ?? 0 ) ) : 0 );
        }
        // Short-lived keyed IP digest for abuse limiting; never store raw IP/user agent in event rows.
        $rate_key = 'eola_rate_' . hash_hmac( 'sha256', ( $_SERVER['REMOTE_ADDR'] ?? '' ) . gmdate( 'YmdHi' ), wp_salt( 'nonce' ) );
        $rate = (int) get_transient( $rate_key );
        if ( $rate + count( $rows ) > 180 ) { return self::fail( 'Rate limit exceeded.', 429 ); }
        set_transient( $rate_key, $rate + count( $rows ), 2 * MINUTE_IN_SECONDS );
        $accepted = 0;
        foreach ( $rows as $row ) {
            // Retries carry the same key, so the unique index prevents duplicate counts.
            $columns = implode( ',', array_keys( $row ) );
            $sql = $wpdb->prepare( 'INSERT IGNORE INTO ' . self::table() . " ($columns) VALUES (" . implode( ',', array_fill( 0, count( $row ), '%s' ) ) . ')', array_values( $row ) );
            $result = $wpdb->query( $sql );
            if ( false === $result ) { return self::fail( 'Unable to store events.', 503 ); }
            $accepted += $result;
        }
        $response = new WP_REST_Response( array( 'accepted' => $accepted ), 200 ); $response->header( 'Cache-Control', 'no-store' ); return $response;
    }
    public static function days() { $days = absint( $_GET['days'] ?? 30 ); return in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30; }
    public static function report( $days ) {
        return self::report_between( gmdate( 'Y-m-d 00:00:00', time() - ( $days - 1 ) * DAY_IN_SECONDS ), gmdate( 'Y-m-d 00:00:00', time() + DAY_IN_SECONDS ) );
    }
    public static function report_between( $since, $until ) {
        global $wpdb;
        $table = self::table();
        $summary = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor) visitors,COUNT(DISTINCT session) sessions,SUM(event='page_view') views,SUM(seconds) seconds,SUM(event='contact_click') contacts,SUM(event='download') downloads FROM $table WHERE received_at >= %s AND received_at < %s", $since, $until ), ARRAY_A );
        $returning = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT a.session) FROM $table a WHERE a.event='page_view' AND a.received_at >= %s AND a.received_at < %s AND EXISTS (SELECT 1 FROM $table b WHERE b.visitor=a.visitor AND b.session<>a.session AND b.received_at<a.received_at)", $since, $until ) );
        $tables = array();
        foreach ( array( 'sources' => 'source,medium', 'campaigns' => 'campaign,source,medium', 'pages' => 'page_id', 'devices' => 'device' ) as $key => $group ) {
            $extra = 'campaigns' === $key ? " AND campaign<>''" : '';
            $tables[$key] = $wpdb->get_results( $wpdb->prepare( "SELECT $group,COUNT(DISTINCT session) sessions,SUM(event='page_view') views,SUM(event='contact_click') contacts FROM $table WHERE received_at >= %s AND received_at < %s $extra GROUP BY $group ORDER BY sessions DESC LIMIT 20", $since, $until ), ARRAY_A );
        }
        $tables['actions'] = $wpdb->get_results( $wpdb->prepare( "SELECT event,target,page_id,COUNT(*) total FROM $table WHERE received_at >= %s AND received_at < %s AND event NOT IN ('page_view','engagement','section_view') GROUP BY event,target,page_id ORDER BY total DESC LIMIT 30", $since, $until ), ARRAY_A );
        $tables['sections'] = $wpdb->get_results( $wpdb->prepare( "SELECT target,SUM(event='section_view') views,SUM(seconds) seconds FROM $table WHERE received_at >= %s AND received_at < %s AND event IN ('section_view','engagement') AND target<>'' GROUP BY target ORDER BY seconds DESC LIMIT 20", $since, $until ), ARRAY_A );
        $daily = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(received_at) day,COUNT(DISTINCT session) sessions FROM $table WHERE received_at >= %s AND received_at < %s GROUP BY DATE(received_at) ORDER BY day", $since, $until ), ARRAY_A );
        return compact( 'summary', 'returning', 'tables', 'daily', 'since', 'until' );
    }
    public static function settings() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); } check_admin_referer( 'eola_analytics_settings' );
        update_option( 'eola_analytics_enabled', isset( $_POST['enabled'] ) ? 1 : 0, false );
        wp_safe_redirect( admin_url( 'admin.php?page=eola-analytics' ) ); exit;
    }
    public static function export() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); } check_admin_referer( 'eola_analytics_export' );
        if ( ! self::unlocked() ) { wp_die( 'Detailed exports are locked. Contact the developer to unlock this feature.', '', array( 'response' => 403 ) ); }
        $report = self::report( self::days() ); nocache_headers(); header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="eola-analytics-' . gmdate( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        $write = function ( $row ) use ( $out ) { fputcsv( $out, array_map( function ( $value ) { $value = (string) $value; return preg_match( '/^[=+@\-\t\r]/', $value ) ? "'" . $value : $value; }, $row ), ',', '"', '' ); };
        $write( array( 'Eola Analytics', 'UTC', $report['since'], '90-day retention; automatic first-party measurement with opt-out' ) );
        $write( array_keys( $report['summary'] ) ); $write( array_values( $report['summary'] ) );
        foreach ( $report['tables'] as $name => $rows ) { $write( array( $name ) ); if ( $rows ) { $write( array_keys( $rows[0] ) ); foreach ( $rows as $row ) { $write( array_values( $row ) ); } } }
        fclose( $out ); exit;
    }
    private static function table_html( $title, $headers, $rows ) {
        echo '<section class="ea-panel"><h2>' . esc_html( $title ) . '</h2><div class="ea-scroll"><table><thead><tr>';
        foreach ( $headers as $header ) { echo '<th scope="col">' . esc_html( $header ) . '</th>'; } echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) { echo '<tr>'; foreach ( $row as $value ) { echo '<td>' . esc_html( (string) $value ) . '</td>'; } echo '</tr>'; }
        if ( ! $rows ) { echo '<tr><td colspan="' . count( $headers ) . '">No measured activity in this period.</td></tr>'; } echo '</tbody></table></div></section>';
    }
    private static function item_label( $target, $page_id ) {
        static $maps = array();
        if ( ! isset( $maps[$page_id] ) ) {
            $maps[$page_id] = array();
            $walk = function ( $elements ) use ( &$walk, &$maps, $page_id ) {
                foreach ( $elements as $el ) {
                    $s = $el['settings'] ?? array();
                    $label = $s['text'] ?? $s['_title'] ?? $s['title'] ?? '';
                    if ( is_string( $label ) ) { $maps[$page_id][$el['id']] = mb_substr( wp_strip_all_tags( $label ), 0, 90 ); }
                    foreach ( $s['tabs'] ?? array() as $index => $tab ) { $maps[$page_id]['amenity-' . $el['id'] . '-' . ( $index + 1 )] = wp_strip_all_tags( $tab['tab_title'] ?? 'Amenity' ); }
                    $walk( $el['elements'] ?? array() );
                }
            };
            $walk( json_decode( get_post_meta( $page_id, '_elementor_data', true ), true ) ?: array() );
        }
        $id = preg_replace( '/^(phone|email)-/', '', $target );
        return $maps[$page_id][$id] ?? str_replace( array( '_', '-' ), ' ', $target );
    }
    public static function unlocked() { return defined( 'EOLA_ANALYTICS_UNLOCKED' ) && EOLA_ANALYTICS_UNLOCKED; }
    private static function locked_html( $title, $what ) {
        $contact = 'mailto:' . rawurlencode( EOLA_ANALYTICS_DEVELOPER_EMAIL ) . '?subject=' . rawurlencode( 'Unlock analytics: ' . $title . ' (' . wp_parse_url( home_url(), PHP_URL_HOST ) . ')' );
        return '<section class="ea-panel ea-locked"><h2>' . esc_html( $title ) . '</h2><p class="ea-lock-icon" aria-hidden="true">&#128274;</p><p><strong>Unlock this feature</strong></p><p>' . esc_html( $what ) . '</p><a class="button button-primary" href="' . esc_url( $contact ) . '">Contact developer</a></section>';
    }
    public static function screen() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $days = self::days(); $r = self::report( $days ); $s = $r['summary']; $enabled = (bool) get_option( 'eola_analytics_enabled' );
        echo '<div class="wrap ea-dashboard"><header class="ea-header"><div><p class="ea-eyebrow">PROPERTY INTELLIGENCE</p><h1>Eola Analytics</h1><p>Understand attention. Measure interest. Plan your next campaign.</p></div><span class="ea-status">' . ( $enabled ? 'Collection enabled' : 'Collection paused' ) . '</span></header>';
        echo '<div class="ea-toolbar"><form method="get"><input type="hidden" name="page" value="eola-analytics"><label for="ea-period">Report period</label> <select name="days" id="ea-period">';
        foreach ( array( 7, 30, 90 ) as $d ) { echo '<option value="' . $d . '" ' . selected( $days, $d, false ) . '>Last ' . $d . ' days</option>'; } echo '</select> <button class="button">Apply</button></form>';
        echo self::unlocked() ? '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=eola_analytics_export&days=' . $days ), 'eola_analytics_export' ) ) . '">Download report CSV</a></div>' : '<span class="ea-chip">&#128274; CSV export: unlock with developer</span></div>';
        if ( self::unlocked() ) { self::daily_controls(); } else { echo '<p class="ea-note">Your website report arrives by email every 3 days (Eola Care). <strong>General analytics</strong> are shown below; detailed reports are marked "Unlock this feature".</p>'; }
        if ( ! (int) $s['views'] ) { echo '<div class="ea-empty"><strong>No measured activity in this period.</strong><p>Automatic measurement starts on public pages without an approval popup. Logged-in staff, opted-out browsers and known bots are excluded. No sample numbers are shown.</p></div>'; }
        echo '<div class="ea-metrics">';
        $deep = self::unlocked();
        foreach ( array( 'Measured visitors' => (int) $s['visitors'], 'Sessions' => (int) $s['sessions'], 'Page views' => (int) $s['views'], 'Returning sessions' => (int) $r['returning'], 'Avg. active time / session' => $deep ? round( (int) $s['seconds'] / max( 1, (int) $s['sessions'] ) ) . 's' : null, 'Contact clicks' => $deep ? (int) $s['contacts'] : null ) as $label => $value ) { echo null === $value ? '<section class="ea-metric ea-metric-locked"><p>' . esc_html( $label ) . '</p><strong>&#128274;</strong><small>Unlock this feature</small></section>' : '<section class="ea-metric"><p>' . esc_html( $label ) . '</p><strong>' . esc_html( $value ) . '</strong></section>'; } echo '</div>';
        echo '<section class="ea-panel"><h2>Sessions over time <small>UTC</small></h2><div class="ea-bars" role="img" aria-label="Daily session counts; each bar includes its date and value">';
        $daily = array_column( $r['daily'], 'sessions', 'day' ); $max = max( array_merge( array( 1 ), array_values( $daily ) ) );
        for ( $i = $days - 1; $i >= 0; $i-- ) { $date = gmdate( 'Y-m-d', time() - $i * DAY_IN_SECONDS ); $n = (int) ( $daily[$date] ?? 0 ); echo '<div class="ea-bar" tabindex="0" title="' . esc_attr( $date . ': ' . $n . ' sessions' ) . '" aria-label="' . esc_attr( $date . ': ' . $n . ' sessions' ) . '"><span style="height:' . max( 2, round( 100 * $n / $max ) ) . '%"></span><small>' . esc_html( substr( $date, 8 ) ) . '</small></div>'; } echo '</div></section><div class="ea-grid">';
        $t = $r['tables'];
        if ( $deep ) {
            self::table_html( 'Traffic sources', array( 'Source / medium', 'Sessions', 'Contact clicks' ), array_map( fn( $v ) => array( $v['source'] . ( $v['medium'] ? ' / ' . $v['medium'] : '' ), $v['sessions'], $v['contacts'] ), $t['sources'] ) );
            self::table_html( 'Campaigns & QR traffic', array( 'Campaign / source / medium', 'Sessions', 'Contact clicks' ), array_map( fn( $v ) => array( $v['campaign'] . ' / ' . $v['source'] . ' / ' . $v['medium'], $v['sessions'], $v['contacts'] ), $t['campaigns'] ) );
            self::table_html( 'Sections receiving attention', array( 'Section', 'Views', 'Active seconds' ), array_map( fn( $v ) => array( $v['target'], $v['views'], $v['seconds'] ), $t['sections'] ) );
            self::table_html( 'Content & button interactions', array( 'Action', 'Item', 'Clicks / events' ), array_map( fn( $v ) => array( str_replace( '_', ' ', $v['event'] ), self::item_label( $v['target'], $v['page_id'] ), $v['total'] ), $t['actions'] ) );
        } else {
            self::table_html( 'Traffic sources', array( 'Source / medium', 'Sessions' ), array_map( fn( $v ) => array( $v['source'] . ( $v['medium'] ? ' / ' . $v['medium'] : '' ), $v['sessions'] ), $t['sources'] ) );
            echo self::locked_html( 'Campaigns & QR traffic', 'See which flyers, QR codes and ads bring visitors and enquiries.' );
            echo self::locked_html( 'Sections receiving attention', 'See which parts of the page visitors read longest: barn, kitchen, floor plans and more.' );
            echo self::locked_html( 'Content & button interactions', 'Track phone, email and showing clicks, gallery photos, floor plans, video and PDF downloads.' );
        }
        self::table_html( 'Pages', array( 'Page', 'Views', 'Sessions' ), array_map( fn( $v ) => array( get_the_title( $v['page_id'] ) ?: 'Page ' . $v['page_id'], $v['views'], $v['sessions'] ), $t['pages'] ) );
        self::table_html( 'Devices', array( 'Device', 'Sessions' ), array_map( fn( $v ) => array( $v['device'], $v['sessions'] ), $t['devices'] ) );
        echo '</div><section class="ea-panel"><h2>Collection controls</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'; wp_nonce_field( 'eola_analytics_settings' );
        echo '<input type="hidden" name="action" value="eola_analytics_settings"><label><input type="checkbox" name="enabled" value="1" ' . checked( $enabled, true, false ) . '> Enable automatic first-party analytics</label> '; submit_button( 'Save settings', 'secondary', 'submit', false ); echo '</form><p>Visitors do not see an approval popup. Analytics preferences provide an opt-out; prior opt-outs, Do Not Track and Global Privacy Control are respected. A random browser ID is kept for up to 90 days; this is not a consent record. Deactivate this plugin to stop collection entirely. Existing reports remain saved. Raw events are retained for 90 days; download reports for your monthly archive.</p></section>';
        echo '<p class="ea-note">Active time counts visible, recently active browsing, not exact reading time. Returning sessions depend on the same measured browser and retained history, not a person\'s identity. Button clicks are expressions of interest, not confirmed enquiries or buyers. Referrals can be unavailable; untagged QR visits appear as direct traffic. These reports do not include search rankings or heatmaps.</p></div>';
    }
}
