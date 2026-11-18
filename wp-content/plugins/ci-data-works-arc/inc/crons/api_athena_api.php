<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include(TRCPDD_PATH.'api/ci_api_hl7_athena_web_api.php');


$log_file_orders = WP_CONTENT_DIR.'/log/log_api_athena_api.txt';
$log_file_results = WP_CONTENT_DIR.'/log/log_api_athena_api_results.txt';
if(time() - filemtime($log_file_orders) < 500
|| time() - filemtime($log_file_results) < 500)
{
	//$f_log = fopen($log_file,'a+');
	//fwrite($f_log, date_i18n('Y-m-d H:i:s').' => out '."\n");
	//fclose($f_log);
	return;
}

touch($log_file_orders);


$f_log_orders = fopen($log_file_orders,'a+');
$f_log_results = fopen($log_file_results,'a+');

$options = get_option('tr_hl7_athena_api_st',array());
if($options['enable']){

	$timestart = microtime(true);

	if($f_log_orders)
	{
		fwrite($f_log_orders, date_i18n('Y-m-d H:i:s').' => start '."\n");
	}


	$client = new CI_Api_Hl7_Athena_WEB_API($options);

	try{

		$client->sync_reqs();

		if($f_log_orders)
		{
			if(!empty($client->log_orders))
			{
				fwrite($f_log_orders, $client->log_orders."\n");
				fwrite($f_log_orders, date_i18n('Y-m-d H:i:s').' => end '."\n");
			}
		}
		
	}catch(Exception $e)
	{
		$client->log.= "error: {$e->getMessage()}\n";
	}

	try{
		if($f_log_results)
		{
			fwrite($f_log_results, date_i18n('Y-m-d H:i:s').' => start '."\n");
		}
		$client->sync_reports();

		if($f_log_results)
		{
			if(!empty($client->log_results))
			{
				fwrite($f_log_results, $client->log_results."\n");
				fwrite($f_log_results, date_i18n('Y-m-d H:i:s').' => end '."\n");
			}
		}
	}catch (Exception $e)
	{
		$client->log.= "error: {$e->getMessage()}\n";
	}




}else{
	fwrite($f_log_orders, date_i18n('Y-m-d H:i:s').' => not active '."\n");
}

fclose($f_log_orders);
fclose($f_log_results);

if(filesize($log_file_orders)>9000000)
{
	file_put_contents($log_file_orders,substr(file_get_contents($log_file_orders),8000000));
}

if(filesize($log_file_results)>9000000)
{
	file_put_contents($log_file_results,substr(file_get_contents($log_file_results),8000000));
}

touch($log_file_orders, time()-400);
touch($log_file_results, time()-400);
