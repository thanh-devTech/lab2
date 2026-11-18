<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include(TRCPDD_PATH.'api/ci_api_provista.php');

if(!tr_get_option('ci_pdf_data','api_provista')){
	return false;
}

$log_file = WP_CONTENT_DIR.'/log/log_api_provista.txt';
$log_last_cron = WP_CONTENT_DIR.'/log/log_api_provista_lastcron.txt';
if(filemtime($log_file)> time()-300)
{
	return;
}
$run_start = time();


touch($log_last_cron, time());

$options = get_option('tr_api_provista_st',array());
if($options['enable']){
	try{
		if(empty($options['cron']))
		{
			$options['cron'] = 2;
		}

		$limit_run_time = $options['cron']*60 -5;

		$limit  = intval($limit_run_time/3);
		$sent   = 0;

		$f_log = fopen($log_file,'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => start '.$limit."\n");


		$client = new CI_Api_Provista($options);
		$client->setReSourceLog($f_log);

		if($client->authenticate())
		{
			$sent = $client->sync_reqs($limit,$limit_run_time);
		}else{
			$client->log.=" authenticate fail";
		}



		if(!empty($client->log))
		{
			fwrite($f_log, $client->log."\n");
		}
		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => end '.$sent.' '."\n");

		fclose($f_log);
		if(filesize($log_file)>100000)
		{
			$olddata = file_get_contents($log_file);
			$olddata = substr($olddata,90000);
			file_put_contents($log_file,$olddata);
		}
		touch($log_file, $run_start-600);

		if($client->has_more)
		{
			$next_cron = wp_next_scheduled('cron_api_provista');
			if(!$next_cron || $next_cron > time()+$limit_run_time)
			{
				wp_schedule_single_event(time(), 'cron_api_provista',array(1));
			}

		}



	}catch(Exception $e)
	{
		Tr_Session_Class::add_error($e->getMessage());
	}
}