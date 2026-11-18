<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

include (TRCPPR_PATH.'api/ci_api_vital_axis.php');

$config = get_option('ci_vital_axis_api_st',array());

$log_dir = WP_CONTENT_DIR.'/log/vital_axis/';
if(!file_exists($log_dir))
{
	wp_mkdir_p($log_dir);
}
$log_file = WP_CONTENT_DIR.'/log/log_api_vital_axis.txt';

if(time() - filemtime($log_file) < 60)
{
	return;
}
$timenow = time();

$limit = 30;


if($config['active']){


	$f_log = fopen($log_file,'a+');
	fwrite($f_log, date_i18n('Y-m-d H:i:s').' => start '."\n");
	fclose($f_log);


	$api = new Ci_Api_VitalAxis($config);
	$sentcount = $api->sync_reqs($limit);


	if($sentcount>$limit)
	{
		$next_cron = wp_next_scheduled('cron_api_vital_axis');
		if($next_cron - time() > 100)
		{
			wp_schedule_single_event(time()+10,'cron_api_vital_axis',array($sentcount));
			$api->log.= "sent {$sentcount} \n";
			$api->log.= "added cron \n";
		}
	}




	$f_log = fopen($log_file,'a+');
	fwrite($f_log, $api->log."\n");
	fwrite($f_log, date_i18n('Y-m-d H:i:s').' => end '."\n");
	fclose($f_log);

	if(filesize($log_file)>100000)
	{
		file_put_contents($log_file,substr(file_get_contents($log_file),90000));
	}
	touch($log_file,$timenow-60);
}

if(function_exists('trpdd_clear_by_dir') && !empty($log_dir) && file_exists($log_dir)){
	trpdd_clear_by_dir($log_dir,86400*30,time());
}
