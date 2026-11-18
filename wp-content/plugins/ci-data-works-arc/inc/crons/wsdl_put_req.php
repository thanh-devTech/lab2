<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
$limit = 50;
$runing = 0;
$debug = isset($_GET['debug']) && isset($_GET['testcron']);

echo 'start';

$log_file = WP_CONTENT_DIR . '/log/cron_wsdl_put_req.txt';


$ftime = file_exists($log_file) ? filemtime($log_file) : 0;

if ($ftime - 300 > time() && !isset($_GET['testcron']) && !isset($_GET['do_cron'])) {
	echo 'out time';
	return;
}
$today = date_i18n('Y-m-d');
$limit_doc = date('Y-m-d', time() - 86400 * 30);
if (isset($_GET['testcron']) || isset($_GET['do_cron'])) {
	$limit_doc = date('Y-m-d', time() - 86400 * 130);
}

$sql_keys = "select * from ci_wsdl_keys k 
                        where k.wsdl_url!='' 
                        and k.wsdl_url is not null 
                        and k.wsdl_type like 'req\_%' 
                        
                        and k.wsdl_start <= '{$today}'
                        ";

if (!isset($_GET['testcron'])) {
	$sql_keys .= " and k.wsdl_active = 1 ";
}
echo $sql_keys;

$keys = $wpdb->get_results($sql_keys);


if (count($keys) == 0) {
	echo 'empty keys';
	return;
} else {
	echo 'count keys: ' . count($keys);
}

$f_log = fopen($log_file, 'a+');
fwrite($f_log, date_i18n('Y-m-d H:i:s') . " => start\n");



foreach ($keys as $key) {
	$client = false;
	$btype = str_replace('req_', '', $key->wsdl_type);
	$batch_tbl = tr_pdd_get_table_by_btype($btype);

	$where = '';
	$where .= " and ba.paid != -57 "; //not by received by other
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
		$where .= " and ba.batch_created >= '{$key->wsdl_start}' ";
	}

	try {
		$client = new CI_Data_Work_Client($key);
		$client->fix_ref_test = $key->wsdl_fix_ref_test;

		if (isset($_GET['testcron'])) {
			$client->debug = true;
		}
	} catch (Exception $e) {
		var_dump($e);
		continue;
	}


	//sync clinic
	if ($key->wsdl_sync_clinic && $key->wsdl_ref_lab_ID > 0) {
		echo '<br>sync clinic<br>';
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . " =>sync clinic: \n\n\n");

		$sql = "select cli.cID from ci_clinic cli
				JOIN {$batch_tbl} ba ON ba.cli_ID = cli.cID
				left join ci_wsdl_batch wb ON cli.cID = wb.b_mid
										and wb.btype = 'cli'
                                        and wb.b_wsdl_ID = '{$key->wsdl_ID}'
                  where (wb.b_mid is null or wb.b_status = 0 or wb.b_status = 2)
                  and ba.ref_lab_ID = {$key->wsdl_ref_lab_ID} 
				  group by cli.cID";

		$cli_ids = $wpdb->get_col($sql);
		echo 'count: ' . count($cli_ids);
		echo $wpdb->last_error;
		foreach ($cli_ids as $cid) {
			try {
				$rs = $client->put_clinic($cid, 0, $debug);
				if ($debug || isset($_GET['do_cron'])) {
					echo '<textarea style="width:100%;height:300px">';
					var_dump($rs);
					echo '</textarea>';
				}
				fwrite($f_log, date_i18n('Y-m-d H:i:s') . " =>put clinic: {$cid} \n\n\n");
				if ($rs && $rs->status == 'OK' && $rs->ID > 0) {
					$exist = $wpdb->get_var("select count(*) from ci_wsdl_batch
        	                where b_mid = {$cid}
        	                and btype = 'cli'
        	                and b_wsdl_ID = {$key->wsdl_ID}");
					if ($exist) {
						$wpdb->update('ci_wsdl_batch', array('b_status' => 1), array(
							'b_mid' => $cid,
							'b_wsdl_ID' => $key->wsdl_ID,
							'b_remote_ID' => (int)$rs->ID,
							'btype' => 'cli',
						));
					} else {
						$wpdb->insert(
							'ci_wsdl_batch',
							array(
								'b_mid' => $cid,
								'b_wsdl_ID' => $key->wsdl_ID,
								'b_remote_ID' => (int)$rs->ID,
								'b_status' => 1,
								'btype' => 'cli',
							)
						);
					}
					echo 'Send id: ' . $rs->ID, '<br>';

					$runing++;
				}
			} catch (Exception $e) {
				var_dump($e);
				fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>error: ' . $e . "\n\n\n");
			}
		}
	}


	if ($key->wsdl_sync_confirmation && $btype == 'tox') {
		$where .= " and CHAR_LENGTH(ba.panel_tests_requested) > 1 ";
	}
	$sql = "select ba.mid,
			ba.assen, 
			wb.b_remote_ID,
			wb.b_status
            from {$batch_tbl} ba
            left join ci_wsdl_batch wb ON ba.mid = wb.b_mid 
                                        and wb.btype = '{$btype}'
                                        and wb.b_wsdl_ID = '{$key->wsdl_ID}'
            where ba.batch_status !='archived' 
            and ba.patient_id > 0
            and ba.cli_ID > 0
            and ba.doc > '{$limit_doc}'
            and (wb.b_mid is null or wb.b_status = 0 or wb.b_status = 2)
            {$where} ";

	$sql .= " order by mid asc limit {$limit}";


	$batches = $wpdb->get_results($sql, ARRAY_A);


	if (isset($_GET['do_cron'])) {

		echo '<br><br>';
		echo 'count b: ' . count($batches);
		echo '<br><br>';
	}

	if (!empty($wpdb->last_error)) {
		var_dump($wpdb->last_error);
		fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>error: ' . $wpdb->last_error . "\n\n\n");
	}

	echo 'Cron put req:<br>';
	echo count($batches) . '<br>';
	fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>count req: ' . count($batches) . "\n\n\n");

	foreach ($batches as $batch) {
		$rs = false;

		try {
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => ' . $batch['assen'] . ': ');

			$is_resend = $batch['b_status'] == 2 ? true : false;
			$rs = $client->put_req($batch['mid'], $btype, $batch['b_remote_ID'], $debug, $is_resend);
			if ($debug || isset($_GET['do_cron'])) {
				echo '<textarea style="width:100%;height:300px">';
				var_dump($rs);
				echo '</textarea>';
			}
		} catch (Exception $e) {
			var_dump($e);
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>error: ' . $e . "\n\n\n");
			continue;
		}




		if ($rs && in_array($rs->status, array('success', 'OK')) && $rs->ID > 0) {
			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>done: ' . "\n\n\n");

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
					'btype' => $btype
				));
			} else {
				$wpdb->insert(
					'ci_wsdl_batch',
					array(
						'b_mid' => $batch['mid'],
						'b_wsdl_ID' => $key->wsdl_ID,
						'b_remote_ID' => (int)$rs->ID,
						'b_status' => 1,
						'btype' => $btype,
						'bdate' => date_i18n('Y-m-d H:i:s')
					)
				);
			}
			echo 'Send id: ' . $rs->ID, '<br>';

			if ($key->wsdl_fix_ref_test && $key->wsdl_ref_lab_ID > 0 && isset($rs->test_not_found)) {
				$testskeys = explode(',', $rs->test_not_found);
				foreach ($testskeys as $testkey) {
					$testkey = trim($testkey);
					$wpdb->update(
						'ci_test_panels',
						array(
							'te_not_ref_lab_ID' => $key->wsdl_ref_lab_ID,
							'te_ref_lab_ID' => 0
						),
						array('te_name' => $testkey, 'te_not_ref_lab_ID' => 0, 'te_type' => $btype)
					);
				}
			}

			$runing++;
		} else {

			fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' =>fail: ' . $rs . "\n\n\n");

			$log = array(
				'mid'	=> $batch['mid'],
				'btype' => $btype,
				'rs'	=> $rs,
				'b_wsdl_ID' => $key->wsdl_ID
			);
			trpdd_log_form('wsdl_put_req', $log);

			echo '<br>';
		}
	}
}

if ($runing > 0) {
	$nextcron = wp_next_scheduled('ci_cron_sync_wsdl_client_req');
	if ($nextcron == 0 || $nextcron > time() + 360) {
		wp_schedule_single_event(time() + 30, 'ci_cron_sync_wsdl_client_req', array($runing));
	}
}


fwrite($f_log, date_i18n('Y-m-d H:i:s') . ' => end ' . "\n\n\n");
fclose($f_log);

if (filesize($log_file) > 500000) {
	file_put_contents($log_file, substr(file_get_contents($log_file), 390000));
}
