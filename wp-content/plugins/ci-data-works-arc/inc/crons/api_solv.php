<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include (TRCPDD_PATH.'api/ci_api_solv.php');

$options = get_option('tr_solv_st',array());

$dir = dirname(ABSPATH).'/solv/';
$order_dir = $dir.'orders/';
$order_log_dir = $dir.'archive/';
$result_dir = $dir.'results/';

$log_file = WP_CONTENT_DIR.'/log/log_api_solv.txt';
$log_run_file = WP_CONTENT_DIR.'/log/log_api_solv_run.txt';

if(time() - filemtime($log_run_file) < 500)
{
	return;
}

file_put_contents($log_run_file, time());

if($options['enable']) {

	$f_log = fopen($log_file,'a+');
	fwrite($f_log, date_i18n('Y-m-d H:i:s').' => start '."\n");

	$client = new CI_Api_Solv($options['lab_id'],$options['cli_id'],$options['doc_id']);

	try {

		$client->sync_reqs( $order_dir );

	} catch ( Exception $e ) {
		$client->log.= "error: {$e->getMessage()}\n";
	}

	if(!empty($client->log))
	{
		fwrite($f_log, $client->log."\n");
		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => end '."\n");
	}

	fclose($f_log);

	if(filesize($log_file)>100000)
	{
		file_put_contents($log_file,substr(file_get_contents($log_file),90000));
	}
}

touch($log_run_file, 0);