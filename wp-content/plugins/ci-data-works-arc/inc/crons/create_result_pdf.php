<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
global $pdf_file_is_exist;

if(defined('DEMO_SITE') && DEMO_SITE && !isset($_GET['testcron']))
{
	return;
}
$_REQUEST['use_cache'] = true;



$log_file = WP_CONTENT_DIR.'/log/log_create_result_pdf.txt';
if(time() - filemtime($log_file) < 200 && !isset($_GET['testcron']))
{
	$next_cron = wp_next_scheduled('cron_create_result_pdf');
	if(!$next_cron)
	{
		wp_schedule_single_event(time()+200, 'cron_create_result_pdf',array('*'));
	}
	return;
}

$f_log = fopen($log_file,'a+');


$dt_from = date('Y-m-d H:i:s', strtotime(date_i18n('Y-m-d H:i:s')) - 300);

foreach(tr_pdd_get_batch_types() as $btype)
{
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$report_ids = $wpdb->get_col("select id from {$report_tbl} 
					where estatus = 'C' 
					and approve_date > '{$dt_from}'
					limit 30");

	foreach($report_ids as $id)
	{
		$outfile = trpdd_export_results_pdf($id,'',true,$btype);
		var_dump($id);
		var_dump($pdf_file_is_exist);
		if($pdf_file_is_exist)
		{
			//fwrite($f_log, date_i18n('Y-m-d H:i:s').': exist: '.$outfile."\n\n");
		}
		else{
			fwrite($f_log, date_i18n('Y-m-d H:i:s').': created: '.$outfile."\n\n");
		}
	}
}

fclose($f_log);

if(filesize($log_file)>100000)
{
	file_put_contents($log_file,substr(file_get_contents($log_file),90000));
}

if(isset($_GET['testcron']))
{
	echo 'ran!';
}

