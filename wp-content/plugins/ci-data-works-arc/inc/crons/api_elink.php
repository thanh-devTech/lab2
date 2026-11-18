<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include(TRCPDD_PATH.'api/ci_api_elink.php');


$log_file = WP_CONTENT_DIR.'/log/log_api_elink.txt';
$running_file = WP_CONTENT_DIR.'/log/running_api_elink.txt';
$timenow = time();
if($timenow - filemtime($running_file) < 200)
{
	//$f_log = fopen($log_file,'a+');
	//fwrite($f_log, date_i18n('Y-m-d H:i:s').' => out '."\n");
	//fclose($f_log);
	return;
}

touch($running_file, $timenow+200);

$f_log = fopen($log_file,'a+');



$options = get_option('_tr_elink_settings',array());
if(isset($options['active']) && $options['active']){
	$count_orders = 0;
	$count_results = 0;

	$count_onum = -1;
	$numrp = 0;
	$timestart = microtime(true);

	if($f_log)
	{
		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => start '."\n");
	}

	try{
		$cteh = new CI_Api_ELINK($options);

		for($i=1;$i<=9;$i++)
		{
			if($count_onum==-1 || $count_onum>40)
			{
				try{

					$count_onum = $cteh->import_reqs(100);
					$count_orders+= $count_onum;

					fwrite($f_log, $cteh->log);
					$cteh->log = '';

					fwrite($f_log, date_i18n('Y-m-d H:i:s').' => imported  '.$i." => ".$count_onum."\n\n");
				}catch (Exception $e2){

				}
			}

			if($numrp==0 || $numrp==50)
			{
				$numrp = $cteh->sync_reports(50);
				$count_results+= $numrp;

				fwrite($f_log, $cteh->log);

				$cteh->log = '';

				fwrite($f_log, date_i18n('Y-m-d H:i:s').' => sent report  '.$i." => ".$numrp."\n\n");
			}

			if($numrp<49 && $count_onum<40)break;

		}

	}catch(Exception $e)
	{

	}


	if($f_log)
	{
		$runtime = microtime(true) - $timestart;

		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => count orders: '.$count_orders.
		               ' | count reports: '.$count_results.
		               ' | runtime: '. $runtime.
		               "\n\n\n\n");

	}

	$next_cron = wp_next_scheduled('cron_api_elink');
	if(!$next_cron)
	{
		wp_clear_scheduled_hook('cron_api_elink');
		if(empty($options['cron']))
		{
			$options['cron'] = 2;
		}
		wp_schedule_event(time()+1,$options['cron'].'min','cron_api_elink');
	}


}else{
	fwrite($f_log, date_i18n('Y-m-d H:i:s').' => not active '."\n");
}

fclose($f_log);

if(filesize($log_file)>1000000)
{
	$olddata = substr(file_get_contents($log_file),900000);
	file_put_contents($log_file,$olddata);
}

touch($running_file, $timenow-200);
