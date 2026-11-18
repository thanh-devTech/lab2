<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

$limit 	= 100;
$runing = 0;
$debug 	= isset($_GET['debug']) && isset($_GET['testcron']);

echo 'Cron put result';

$log_file = WP_CONTENT_DIR . '/log/cron_wsdl_put_result.txt';

if (!file_exists($log_file)) {
	touch($log_file);
}

$ftime = filemtime($log_file);
if ($ftime - 300 > time() && !isset($_GET['testcron']) && !isset($_GET['do_cron'])) {
	echo 'out time';
	return;
}

$f_log = fopen($log_file, 'a+');

$today = date_i18n('Y-m-d');
$limit_doc = date('Y-m-d', time() - 86400 * 30);
if (isset($_GET['testcron']) || isset($_GET['do_cron'])) {
	$limit_doc = date('Y-m-d', time() - 86400 * 130);
}

$sql_keys = "select * from ci_wsdl_keys k 
                        where k.wsdl_url!='' 
                        and k.wsdl_url is not null 
                        and k.wsdl_type like 'result\_%' 
                        
                        and k.wsdl_start <= '{$today}'
                        ";

if (!isset($_GET['testcron'])) {
	$sql_keys .= " and k.wsdl_active = 1 ";
}

$keys = $wpdb->get_results($sql_keys);


if (count($keys) == 0) {
	echo '<br>empty keys';
	return;
} else {
	echo '<br>count keys: ' . count($keys);
}


fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => start\n");



foreach ($keys as $key) {
	$client = false;
	$btype = str_replace('result_', '', $key->wsdl_type);

	$where = '';
	$where .= " and (re.re_import_ID = 0 or re.re_import_ID is null) "; //not send received
	$cli_ids = $wpdb->get_col("select cli_ID from ci_wsdl_key_clinics where wsdl_ID = {$key->wsdl_ID} ");
	if (count($cli_ids) > 0) {
		$cli_ids = implode(',', $cli_ids);
		$where .= " and ba.cli_ID in ({$cli_ids}) ";
	}

	if ($key->wsdl_lab_ID > 0) {
		$where .= " and ba.lab_id = {$key->wsdl_lab_ID} ";
	}

	if ($key->wsdl_ref_lab_ID > 0) {
		$where .= " and ba.ref_lab_id = {$key->wsdl_ref_lab_ID} ";
	}

	if (!empty($key->wsdl_group_IDs)) {
		$groups = explode(',', $key->wsdl_group_IDs);
		$where_group = array();
		foreach ($groups as $gid) {
			if ($gid > 0) {
				$where_group[] = " FIND_IN_SET('GROUP_{$gid}', ba.panel_tests_requested ) ";
			}
		}
		$where .= ' and (' . implode(' or ', $where_group) . ' ) ';
	}

	if (!empty($key->wsdl_start) && $key->wsdl_start != '0000-00-00') {
		$where .= " and ba.doc >= '{$key->wsdl_start}' ";
	}

	try {
		$client = new CI_Data_Work_Client($key);

		if (isset($_GET['testcron'])) {
			$client->debug = true;
		}
	} catch (Exception $e) {
		var_dump($e);
		continue;
	}

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$sql = "select ba.mid, ba.assen, wb.b_remote_ID
            from {$batch_tbl} ba
            JOIN {$report_tbl} re ON re.patient_id = ba.assen and re.estatus = 'C'
            left join ci_wsdl_batch wb ON ba.mid = wb.b_mid 
                                        and wb.btype = '{$btype}'
                                        and wb.b_wsdl_ID = '{$key->wsdl_ID}'
            where ba.batch_status !='archived'  and ba.batch_status not like 're\_%'
            and ba.batch_id !='' and ba.batch_id!='0'
            and ba.patient_id > 0
            and ba.doc > '{$limit_doc}'
            and (wb.b_mid is null or wb.b_status = 0 or wb.b_status = 2)
            {$where} ";

	$sql .= " order by mid asc limit {$limit}";



	$batches = $wpdb->get_results($sql, ARRAY_A);

	echo '<br>count rs: ' . count($batches);

	if (!empty($wpdb->last_error)) {
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>error1: ' . $wpdb->last_error . "\n\n\n");
		echo '<br>' . $wpdb->last_error;
	}

	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>count result: ' . count($batches) . "\n\n\n");

	foreach ($batches as $batch) {
		$rs = false;
		try {
			$rs = $client->put_result($batch['mid'], $btype, $batch['b_remote_ID'], $debug);
			if ($debug || isset($_GET['do_cron'])) {
				echo '<textarea style="width:100%;height:300px">';
				var_dump($rs);
				echo '</textarea>';
			}
		} catch (Exception $e) {
			var_dump($e);
			continue;
		}

		if ($client->debug) {
			var_dump($rs);
			exit;
		}

		$sent = false;


		if ($rs && $rs->status == 'OK' && $rs->ID > 0) {
			$exist = $wpdb->get_var("select count(*) from ci_wsdl_batch
        	                where b_mid = {$batch['mid']}
        	                and btype = '{$btype}'
        	                and b_wsdl_ID = {$key->wsdl_ID}");
			if ($exist) {
				$wpdb->update('ci_wsdl_batch', array(
					'b_status' => 1,
					'bdate' => date_i18n('Y-m-d H:i:s')
				), array(
					'b_mid' => $batch['mid'],
					'b_wsdl_ID' => $key->wsdl_ID,
					'btype' => $btype,
				));
			} else {
				$wpdb->insert(
					'ci_wsdl_batch',
					array(
						'b_mid'     => $batch['mid'],
						'b_wsdl_ID' => $key->wsdl_ID,
						'b_remote_ID' => (int)$rs->ID,
						'b_status'  => 1,
						'btype'     => $btype,
						'bdate' => date_i18n('Y-m-d H:i:s')
					)
				);
			}
			if ($debug || isset($_GET['do_cron'])) {
				echo 'Send id: ' . $rs->ID, '<br>';
			}
			$runing++;
			$sent = true;
		} else {
			$log = array(
				'mid' => $batch['mid'],
				'btype' => $btype,
				'rs' => $rs,
				'b_wsdl_ID' => $key->wsdl_ID
			);
			trpdd_log_form('wsdl_put_req', $log);

			if ($debug) {
				echo '<br>';
			}
		}
		ob_start();
		var_dump($rs);
		echo '=>' . ($sent ? 'sent' : 'fail');
		$logstr = ob_get_clean();
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>' . $batch['assen'] . '=>' . $logstr . "\n\n\n");
	}
}

if ($runing > 0) {
	$nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_result');
	$nextcron2 = wp_next_scheduled('ci_cron_sync_wsdl_client_result', array(1));
	if ($nextcron2 > 0 && $nextcron2 < $nextcron) {
		$nextcron = $nextcron2;
	}
	if ($nextcron == 0 || $nextcron > time() + 360) {
		wp_schedule_single_event(time() + 180, 'ci_cron_sync_wsdl_client_result', array(1));
	}
}


fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => end ' . "\n\n\n");
fclose($f_log);

if (filesize($log_file) > 5000000) {
	$olddata = substr(file_get_contents($log_file), 4000000);
	file_put_contents($log_file, $olddata);
}
