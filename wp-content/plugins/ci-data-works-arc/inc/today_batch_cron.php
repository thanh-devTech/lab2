<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

include_once(TRCPDD_PATH.'inc/admin_actions.php');
$dir = WP_CONTENT_DIR.'/cache/';
$log = '';

$headers = 'From: ' .get_bloginfo('name').'<'. get_bloginfo('admin_email').'>' . "\r\n";
$headers .= 'Reply-To: ' . get_bloginfo('admin_email'). "\r\n";
$headers .= "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";

$offset = get_option( 'gmt_offset' ) * HOUR_IN_SECONDS;
$local_time = time() + $offset;
$today_time = $local_time - (3600*4);
$hour = date('H',$local_time);
if($hour<7)
{
    $today_time -= 3600*7;
}
$today = date('Y-m-d',$today_time);
if(isset($_REQUEST['today'])){
    $today = $_REQUEST['today'];
}
$current = date('Y-m-d H:i:s',$local_time);




$log.= 'Today: '.$today."\n";
$log.= 'Current: '.$current . "\n";

if(isset($_REQUEST['debug'])){
    $hour = 22;
}

if(($hour>=21 || $hour<7)){


    $labs = $wpdb->get_results("select * from ci_labs where  lab_email_notify !='' and lab_day_sent != '{$today}' ");


    foreach($labs as $lab)
    {
        $email = $lab->lab_email_notify;
        $filepath = $dir . 'report_'.$today.'.csv';
        $log.= 'lab: '.$lab->lab_ID.': '.$lab->lab_dba_init;

        $has = tradmin_action_ci_export_today_batches($lab->lab_ID,$today,$filepath);
        if(!$has)
        {
            $log.= '=> no batch'."\n";
            continue;
        }
        $subject = 'Today Batches';
        $message = 'Hi, Here is Today batches';

        $atts = array(
            $filepath
        );

        if(isset($_REQUEST['debug'])){
            $email = $_REQUEST['email'];
        }
        $rs = wp_mail($email,$subject,$message,$headers,$atts);
        if($rs)
        {
            $update = array(
                'lab_day_sent' => $today,
                'lab_day_notify' => $current
            );
            if(!isset($_REQUEST['debug']))
            {
                $wpdb->update('ci_labs',$update,array('lab_ID'=>$lab->lab_ID));
            }

            $log.= '=> sent to '.$email."\n";
        }else{
            $log.= '=> send fail to '.$email."\n";
        }

    }

}else{
    $log.= "out hour";
    //wp_clear_scheduled_hook( 'ci_today_batch_cron',array('single'));
    //wp_schedule_single_event( time()+1000, 'ci_today_batch_cron' ,array('single') );
}


$filename = WP_CONTENT_DIR .'/batch_cron.txt';
$log.="\n\n------------\n\n";
$f = fopen($filename, 'a+');
fwrite($f, $log);
fclose($f);