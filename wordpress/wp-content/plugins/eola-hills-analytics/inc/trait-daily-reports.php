<?php
defined( 'ABSPATH' ) || exit;
trait Eola_Analytics_Daily_Reports {
    public static function daily_report( $date ) {
        if ( ! is_string( $date ) ) { throw new InvalidArgumentException( 'Choose a valid report date.' ); }
        $start = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
        $today = current_datetime()->setTime( 0, 0 );
        if ( ! $start || $start->format( 'Y-m-d' ) !== $date || $start > $today || $start < $today->modify( '-89 days' ) ) { throw new InvalidArgumentException( 'Choose a date within the last 90 calendar days, including today.' ); }
        // Convert both local midnights separately: DST days can be 23 or 25 hours.
        $utc = new DateTimeZone( 'UTC' );
        $r = self::report_between( $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $start->modify( '+1 day' )->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) );
        $r['date'] = $date; $r['timezone'] = wp_timezone_string(); $r['partial'] = $date === $today->format( 'Y-m-d' );
        return $r;
    }
    private static function daily_controls() {
        echo '<section class="ea-panel"><h2>Daily client report</h2><form class="ea-report-form" method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="eola_analytics_daily">'; wp_nonce_field( 'eola_analytics_daily' );
        echo '<label for="ea-report-date">Report date</label> <input id="ea-report-date" type="date" name="date" value="' . esc_attr( wp_date( 'Y-m-d', strtotime( '-1 day' ) ) ) . '" min="' . esc_attr( current_datetime()->modify( '-89 days' )->format( 'Y-m-d' ) ) . '" max="' . esc_attr( wp_date( 'Y-m-d' ) ) . '" required> <button class="button" name="format" value="csv">Download daily CSV</button> <button class="button button-primary" name="format" value="html">Download client report</button></form><p>Daily reports use the WordPress timezone (' . esc_html( wp_timezone_string() ) . '). The client report includes its date and can be opened in a browser, printed or saved as PDF. Today is marked as an incomplete day. Download reports within the 90-day data-retention period.</p></section>';
    }
    private static function client_tables( $r ) {
        $t = $r['tables'];
        return array(
            array( 'Traffic sources', array( 'Source / medium', 'Sessions', 'Contact clicks' ), array_map( fn( $v ) => array( $v['source'] . ( $v['medium'] ? ' / ' . $v['medium'] : '' ), $v['sessions'], $v['contacts'] ), $t['sources'] ) ),
            array( 'Campaigns and QR traffic', array( 'Campaign', 'Source / medium', 'Sessions', 'Contact clicks' ), array_map( fn( $v ) => array( $v['campaign'], $v['source'] . ' / ' . $v['medium'], $v['sessions'], $v['contacts'] ), $t['campaigns'] ) ),
            array( 'Sections receiving attention', array( 'Section', 'Views', 'Active seconds' ), array_map( fn( $v ) => array( $v['target'], $v['views'], $v['seconds'] ), $t['sections'] ) ),
            array( 'Content and button interactions', array( 'Action', 'Item', 'Events' ), array_map( fn( $v ) => array( str_replace( '_', ' ', $v['event'] ), self::item_label( $v['target'], $v['page_id'] ), $v['total'] ), $t['actions'] ) ),
            array( 'Pages', array( 'Page', 'Views', 'Sessions' ), array_map( fn( $v ) => array( get_the_title( $v['page_id'] ) ?: 'Page ' . $v['page_id'], $v['views'], $v['sessions'] ), $t['pages'] ) ),
            array( 'Devices', array( 'Device', 'Sessions' ), array_map( fn( $v ) => array( $v['device'], $v['sessions'] ), $t['devices'] ) ),
        );
    }
    public static function daily_export() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); } check_admin_referer( 'eola_analytics_daily' );
        if ( ! self::unlocked() ) { wp_die( 'Detailed daily reports are locked. Contact the developer to unlock this feature.', '', array( 'response' => 403 ) ); }
        try { $r = self::daily_report( wp_unslash( $_GET['date'] ?? '' ) ); }
        catch ( Throwable $e ) { wp_die( esc_html( $e->getMessage() ), '', array( 'response' => 400 ) ); }
        $s = $r['summary']; $date = $r['date']; $tables = self::client_tables( $r );
        $metrics = array( 'Measured visitors' => (int) $s['visitors'], 'Sessions' => (int) $s['sessions'], 'Page views' => (int) $s['views'], 'Average active seconds / session' => round( (int) $s['seconds'] / max( 1, (int) $s['sessions'] ) ), 'Contact clicks' => (int) $s['contacts'], 'Returning sessions' => (int) $r['returning'], 'Download clicks' => (int) $s['downloads'] );
        $period = $r['partial'] ? 'Today: partial day, collection still in progress.' : 'Selected calendar day.';
        $notes = 'Automatic first-party measurement with opt-out. Staff, opted-out browsers and known bots excluded; blockers can reduce coverage. Zero means no measured activity, not proof of no visitors. Active time is an estimate. Contact and download clicks are not confirmed enquiries or completed downloads. Returning sessions use retained browser history, not personal identity. Breakdown tables show the top 20 items (30 actions). No search rankings or heatmaps.';
        nocache_headers(); header( 'X-Content-Type-Options: nosniff' );
        if ( 'html' !== ( $_GET['format'] ?? '' ) ) {
            header( 'Content-Type: text/csv; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="eola-analytics-daily-' . $date . '.csv"' );
            $out = fopen( 'php://output', 'w' );
            $write = function ( $row ) use ( $out ) { fputcsv( $out, array_map( fn( $v ) => preg_match( '/^[=+@\-\t\r]/', (string) $v ) ? "'" . $v : $v, $row ), ',', '"', '' ); };
            $write( array( 'Daily website analytics', home_url(), $date, $r['timezone'], $period ) ); $write( array( 'Generated UTC', gmdate( 'c' ), 'UTC start inclusive', $r['since'], 'UTC end exclusive', $r['until'] ) );
            foreach ( $metrics as $label => $value ) { $write( array( $label, $value ) ); }
            foreach ( $tables as [ $title, $headers, $rows ] ) { $write( array( $title ) ); $write( $headers ); foreach ( $rows as $row ) { $write( $row ); } if ( ! $rows ) { $write( array( 'No measured activity' ) ); } }
            $write( array( $notes ) ); fclose( $out ); exit;
        }
        header( 'Content-Type: text/html; charset=utf-8' ); header( 'Content-Disposition: attachment; filename="eola-analytics-daily-' . $date . '.html"' );
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily analytics ' . esc_html( $date ) . '</title><style>body{font:15px/1.6 Arial,sans-serif;color:#252b45;max-width:1000px;margin:36px auto;padding:24px}h1,h2{color:#4b5598}header{border-bottom:3px solid #4b5598;padding-bottom:20px}.metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:24px 0}.metric{padding:18px;background:#f1f2f8;border-radius:8px}.metric strong{display:block;font-size:26px}table{border-collapse:collapse;width:100%;font-size:13px}td,th{text-align:left;padding:10px;border-bottom:1px solid #ddd;overflow-wrap:anywhere}.ea-panel{margin:28px 0}.ea-scroll{overflow:auto}small,.note{color:#555}button{padding:10px 16px}@media(max-width:600px){.metrics{grid-template-columns:1fr 1fr}body{padding:14px}}@media print{button{display:none}body{margin:0}.metric,tr{break-inside:avoid}thead{display:table-header-group}h2{break-after:avoid}}</style><body><button onclick="window.print()">Print / Save as PDF</button><header><h1>Daily website analytics</h1><p>' . esc_html( home_url() ) . '</p><strong>' . esc_html( $date . ' | ' . $r['timezone'] ) . '</strong><p>' . esc_html( $period ) . '</p><small>Generated ' . esc_html( gmdate( 'c' ) ) . ' UTC</small></header><div class="metrics">';
        foreach ( $metrics as $label => $value ) { echo '<div class="metric">' . esc_html( $label ) . '<strong>' . esc_html( $value ) . '</strong></div>'; } echo '</div>';
        foreach ( $tables as [ $title, $headers, $rows ] ) { self::table_html( $title, $headers, $rows ); }
        echo '<p class="note">' . esc_html( $notes ) . '</p></body></html>'; exit;
    }
}
