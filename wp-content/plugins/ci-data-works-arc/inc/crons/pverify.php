<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb,$blog_id;

if($mid>0 && $cron_blog_id>0)
{
    $blog_id = $cron_blog_id;

    $bat = $wpdb->get_row("select * from ci_batches where mid = {$mid}");
    $logid = $wpdb->get_var("select id from ci_log_forms WHERE target_id = {$mid} AND form = 'pverify'");
    if($bat->batch_status=='pre-verify'){
        if(isset($_REQUEST['debug2'])){
            echo 'status is pre-verify';
        }
        return;
    }

    $_REQUEST['mid'] = $mid;
    $_REQUEST['logid'] = $logid;
    $need_update = $_REQUEST['need_update'] = true;
    $need_return_rs = true;
    $is_cron = true;

    include( 'ci_xml_pverify_run.php' );

    if(isset($_REQUEST['debug2'])){
        echo '$need_cron_pverify: ';var_dump($need_cron_pverify);
    }

    if($need_cron_pverify)
    {
        wp_clear_scheduled_hook('ci_cron_pverify',array(0));
        wp_schedule_single_event(time()+60*6, 'ci_cron_pverify',array(0));
    }
}
else if(isset($_REQUEST['debug2'])){
    var_dump($cron_blog_id);
    var_dump($mid);
}