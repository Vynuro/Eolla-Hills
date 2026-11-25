<?php
if (!defined('WP_CLI') || get_option('siteurl') !== 'http://localhost:8087') throw new RuntimeException('Daily report fixtures are isolated to localhost:8087.');
global $wpdb;
$zone=get_option('timezone_string'); $table=Eola_Analytics::table(); $ids=[];
try {
    update_option('timezone_string','America/Los_Angeles');
    $date=current_datetime()->modify('-1 day')->format('Y-m-d');
    $empty=Eola_Analytics::daily_report($date); $from=strtotime($empty['since'].' UTC');$to=strtotime($empty['until'].' UTC');
    $visitor=hash('sha256','daily-test-visitor');$page=(int)get_option('page_on_front');
    foreach ([[$from-1,'prior','page_view',0],[$from,'current','page_view',0],[$to-1,'current','engagement',12],[$to,'next','page_view',0]] as [$when,$session,$event,$seconds]) {
        $wpdb->insert($table,['event_key'=>hash('sha256',wp_generate_uuid4()),'received_at'=>gmdate('Y-m-d H:i:s',$when),'visitor'=>$visitor,'session'=>hash('sha256',$session),'event'=>$event,'page_id'=>$page,'source'=>'daily_test','campaign'=>'daily_report_qa','device'=>'desktop','seconds'=>$seconds]);
        if(!$wpdb->insert_id)WP_CLI::error('Fixture insertion failed');$ids[]=$wpdb->insert_id;
    }
    $r=Eola_Analytics::daily_report($date);
    foreach (['views'=>1,'sessions'=>1,'seconds'=>12] as $k=>$n) if ((int)$r['summary'][$k]-(int)$empty['summary'][$k]!==$n) WP_CLI::error('Incorrect bounded daily '.$k);
    if((int)$r['returning']-(int)$empty['returning']!==1)WP_CLI::error('Returning session missing');
    WP_CLI::log('PASS: local calendar day has inclusive start/exclusive end, exact engagement and returning-session counts.');
    update_option('timezone_string','Pacific/Auckland');
    $dst=Eola_Analytics::daily_report('2026-09-27');
    if(strtotime($dst['until'].' UTC')-strtotime($dst['since'].' UTC')!==23*3600)WP_CLI::error('DST day is not 23 hours');
    foreach (['2026-02-31','tomorrow','2099-01-01','2020-01-01'] as $invalid) {
        $rejected=false;try{Eola_Analytics::daily_report($invalid);}catch(InvalidArgumentException $e){$rejected=true;}if(!$rejected)WP_CLI::error('Invalid date accepted');
    }
    WP_CLI::success('DST transition, invalid/future/expired date rejection and daily aggregation checks passed.');
} finally {
    foreach($ids as $id)$wpdb->delete($table,['id'=>$id],['%d']);
    update_option('timezone_string',$zone);
}
