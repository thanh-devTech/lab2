<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include (TRCPDD_PATH.'api/ci_api_lifetime.php');

$options    = get_option('tr_lifetime_st',array());

$dir        = dirname(ABSPATH).'/lifetime/';
$order_dir  = $dir.'orders/';
$order_log_dir = $dir.'archive/';
$result_dir = $dir.'results/';

$log_file   = WP_CONTENT_DIR.'/log/log_api_lifetime.txt';
$log_run_file = WP_CONTENT_DIR.'/log/log_api_lifetime_run.txt';

if(time() - filemtime($log_run_file) < 500)
{
	return;
}

file_put_contents($log_run_file, time());

if($options['enable']) {

	$f_log = fopen($log_file,'a+');
	fwrite($f_log, date_i18n('Y-m-d H:i:s').' => start ---'."\n");

	$client = new CI_Api_Lifetime($options);
	$client->debug = true;

	$daterun = date_i18n('Y-m-d');
	$hour = date_i18n('H');
	if($hour<=22)
	{
		$daterun = date('Y-m-d', strtotime($daterun)-86400);
	}

	if($hour==0 && $options['last_date']!=$daterun)
	{
		try {

			$client->sync_reqs( );

			$options['last_date'] = $client->current_date;
			update_option('tr_lifetime_st',$options);

		} catch ( Exception $e ) {
			$client->log.= "error: {$e->getMessage()}\n";
		}
	}

	try {

		$client->sync_reports( );

	} catch ( Exception $e ) {
		$client->log.= "error: {$e->getMessage()}\n";
	}

	if(!empty($client->log))
	{
		fwrite($f_log, $client->log."\n\n");
		//fwrite($f_log, date_i18n('Y-m-d H:i:s').' => end '."\n");
	}

	fclose($f_log);

	if(filesize($log_file)>100000)
	{
		file_put_contents($log_file,substr(file_get_contents($log_file),90000));
	}
}

touch($log_run_file, 0);