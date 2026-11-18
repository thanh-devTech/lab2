<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include(TRCPDD_PATH.'api/ci_api_cteh.php');

$log_cron = true;
$log_file = WP_CONTENT_DIR.'/log/log_api_cteh.txt';

$options = get_option('_tr_cteh_settings',array());
if($options['active']){

	$f_log = fopen($log_file,'a+');
	if($log_cron)
	{
		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => start '."\n");
	}

	$client = new CI_Api_CTEH($options['key']);

	try{
		$client->import_reqs();
		$client->sync_reports();


	}catch(Exception $e)
	{
		$client->log.= "error: {$e->getMessage()}\n";
	}

	if($log_cron)
	{
		if(!empty($client->log))
		{
			fwrite($f_log, $client->log."\n");
			fwrite($f_log, date_i18n('Y-m-d H:i:s').' => end '."\n");
		}
	}
	fclose($f_log);
	if(filesize($log_file)>100000)
	{
		$olddata = file_get_contents($log_file);
		$olddata = substr($olddata,90000);
		file_put_contents($log_file,$olddata);
	}
}