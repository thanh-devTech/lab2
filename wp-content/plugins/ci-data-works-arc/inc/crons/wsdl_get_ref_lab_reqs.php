<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

$log_file = WP_CONTENT_DIR.'/log/cron_wsdl_get_ref_lab_reqs.txt';
if(time() - filemtime($log_file) < 500 && !isset($_GET['testcron']))
{
	echo 'outtime';
	return;
}

$start_run_time = time();

$f_log = fopen( $log_file, 'a+' );
fwrite( $f_log, date_i18n( 'Y-m-d H:i:s' ) . " => start\n" );

$syncrefs = $wpdb->get_results("select ws.*,lab.lab_dba_init 
	                    from ci_wsdl_keys ws 
	                    JOIN ci_labs lab ON lab.lab_ID = ws.wsdl_ref_lab_ID
	                    where ws.wsdl_active = 1 
                        and ws.wsdl_type = 'getreqrec'
                        and lab.lab_is_ref =1
                        ");
$has_more = false;
var_dump($syncrefs);
echo '<br><br>';
foreach($syncrefs as $syncref)
{
	$sql = "select assen, 'pcr' as btype
			from ci_batches_pcr ba
			where ba.assen like '{$syncref->lab_dba_init}%' 
			and (ba.patient_id is null or ba.patient_id = 0)
			order by ba.mid asc
			limit 200";
	$reqs = $wpdb->get_results($sql);
	if(count($reqs)>0)
	{
		$has_more = true;
		try{
			ob_start();
			$client = new CI_Data_Work_Client($syncref);
			$client->ref_lab_id = $syncref->wsdl_ref_lab_ID;
			$client->default_cli_ID = $syncref->wsdl_df_cli_ID;
			$client->default_doc_ID = $syncref->wsdl_df_doc_ID;

			foreach($reqs as $req)
			{
				$rs = $client->sync_req($req->assen,$req->btype);
				var_dump($rs);
			}
			$log = ob_get_clean();
			fwrite( $f_log, $log. "\n\n\n" );
			var_dump($log);

		}catch (Exception $e)
		{
			var_dump($e);
		}
	}
}

if($has_more)
{
	$next_cron = wp_next_scheduled('ci_cron_sync_wsdl_get_ref_lab_reqs');
	if(!$next_cron || $next_cron > time()+300)
	{
		wp_schedule_single_event(time()+200, 'ci_cron_sync_wsdl_get_ref_lab_reqs',array($has_more));
	}
	$start_run_time-=500;
}


fwrite( $f_log, date_i18n( 'Y-m-d H:i:s' ) . ' => end ' . "\n\n\n" );
fclose( $f_log );

if(filesize($log_file)>500000)
{
	file_put_contents($log_file,substr(file_get_contents($log_file),390000));
}
touch($log_file, $start_run_time-300);