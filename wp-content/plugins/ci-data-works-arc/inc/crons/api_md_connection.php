<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
include (TRCPDD_PATH.'api/ci_api_md_connection.php');

$options = get_option('tr_md_connection_st',array());
$active = tr_get_option('ci_pdf_data','api_md_connection');

$log_cron = true;
$log_file = WP_CONTENT_DIR.'/log/log_api_md_connection.txt';

if($active && $options['enable']) {
	if($log_cron)
	{
		$f_log = fopen($log_file,'a+');
		fwrite($f_log, date_i18n('Y-m-d H:i:s').' => start '."\n");
		fclose($f_log);
	}
	try {
		$client = new CI_Api_MD_Connection( $options );

		$client->connect();
		$client->login();
		echo 'logged in<br>';
		$client->sync_in();
		$client->sync_out();


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
			file_put_contents($log_file,substr(file_get_contents($log_file),90000));
		}
	}
}