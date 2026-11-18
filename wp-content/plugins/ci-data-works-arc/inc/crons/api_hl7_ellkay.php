<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include (TRCPDD_PATH.'api/ci_api_hl7_ellkay.php');

$options = get_option('tr_hl7_ellkay_st',array());


$log_cron = true;
$log_file = WP_CONTENT_DIR.'/log/log_api_ellkay.txt';

if($options['enable']) {
	if($log_cron)
	{
		$f_log = fopen($log_file,'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => start '."\n");
		fclose($f_log);
	}
	try {
		$client = new CI_Api_Hl7_Ellkay( $options );

		$client->connect();
		$client->login();
		echo 'logged in<br>';
		$client->sync_reqs( );
		$client->sync_reports( );


	} catch ( Exception $e ) {
		Tr_Session_Class::add_error( $e->getMessage() );
	}

	if($log_cron)
	{
		$f_log = fopen($log_file,'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => end '."\n");
		fclose($f_log);

		if(filesize($log_file)>100000)
		{
			$olddata = file_get_contents($log_file);
			$olddata = substr($olddata,90000);
			file_put_contents($log_file,$olddata);
		}
	}
}