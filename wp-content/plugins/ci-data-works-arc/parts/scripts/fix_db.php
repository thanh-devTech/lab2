<?php
global $wpdb;
include_once(TRCPDD_PATH . 'inc/functions_fix.php');

$log_file = WP_CONTENT_DIR . '/log/log_fix_db.json';
touch($log_file);
$logdata = json_decode(file_get_contents($log_file), true);



$timeout 			= time() - 86400 * 300;
$timeoutdatetime 	= date('Y-m-d', $timeout);
$log_limit_time 	= date('Y-m-d', time() - 86400 * 360 * 6); //6years
$log_limit_time50 	= date('Y-m-d', time() - 86400 * 50);
$log_limit_time25 	= date('Y-m-d', time() - 86400 * 25);
$log_limit_time10 	= date('Y-m-d', time() - 86400 * 10);
$log_limit_time_loginfail = date('Y-m-d', time() - 86400 * 10);
$log_limit_time_login = date('Y-m-d', time() - 86400 * 15);
$log_limit_view 	= date('Y-m-d', time() - 86400 * 10);


if ($logdata['last_delete_log_users'] == 0 || $logdata['last_delete_log_users'] < time() - 86400 * 7) {
	$timestart = microtime(true);
	$logdata['last_delete_log_users'] = time();
	file_put_contents($log_file, json_encode($logdata));

	//$wpdb->query("delete from ci_log_forms where form!='delete_assen' and created_date < '{$timeoutdatetime}'");

	$wpdb->query("update ci_wsdl_log set wl_data=null where wl_at < '{$log_limit_time25}'");
	$wpdb->query("delete from ci_wsdl_log where  wl_at < '{$log_limit_time50}'");
	$wpdb->query("delete from ci_wsdl_log where  wl_at < '{$log_limit_time10}' and wl_dataout like '%<total>0</total>%' ");

	$wpdb->query("delete from `ci_result_test_speciments` where ((conc is null or conc='') and (outcome is null or outcome = '') and checked = 0 )");

	$wpdb->query("delete from `ci_log_users` where user_action='loginfail' and created < '{$log_limit_time}'");
	$wpdb->query("delete from `ci_log_users` where user_action='logout' and created < '{$log_limit_time}'");
	$wpdb->query("delete from `ci_log_users` where user_action='login' and created < '{$log_limit_time}'");
	$wpdb->query("delete from `ci_log_users` where user_action='view' and created < '{$log_limit_time}'");
	$wpdb->query("delete from `ci_log_users` where user_action='view_edit' and created < '{$log_limit_time}'");
	$wpdb->query("delete from `ci_log_users` where user_action not like 'api%' and created < '{$log_limit_time}'");


	$wpdb->query("delete from `ci_sync_api_orders` where mo_file_id=-18 and mo_test_ID < '{$log_limit_time} 23:59:59'");

	echo 'delete loguser=>' . (microtime(true) - $timestart) . "<br>\n";
}


if ($logdata['last_fix_instruments'] == 0 || $logdata['last_fix_instruments'] < time() - 86400 * 2) {
	$timestart = microtime(true);
	$logdata['last_fix_instruments'] = time();
	file_put_contents($log_file, json_encode($logdata));
	$instruments = $wpdb->get_results("select cu_ms,cu_btype from `ci_test_panels_cutoffs` where cu_ms!='' group by cu_ms, cu_btype");
	$all_instruments = array();
	foreach ($instruments as $instrument) {
		$ms = explode(',', $instrument->cu_ms);
		if (!isset($all_instruments[$instrument->cu_btype]) || !is_array($all_instruments[$instrument->cu_btype])) {
			$all_instruments[$instrument->cu_btype] = array();
		}
		foreach ($ms as $m) {
			$m = strtoupper(trim($m));
			if (!empty($m) && !in_array($m, $all_instruments[$instrument->cu_btype])) {
				$all_instruments[$instrument->cu_btype][] = $m;
			}
		}
	}

	foreach ($all_instruments as $btype => $arr) {

		$list_str = implode("','", $arr);
		$wpdb->query("delete from ci_instruments where btype='{$btype}' and instrument not in ('{$list_str}') ");

		foreach ($arr as $m) {
			$wpdb->query("insert IGNORE into ci_instruments (instrument,btype)
	                                values ('{$m}','{$btype}') ");
		}
	}



	//fix empty instrument for reports_pcr
	$instruments = array();
	$rs = $wpdb->get_results("select id,upload_files from ci_reports_pcr where (instrument='' or instrument is null) 
											and upload_files!= '' and upload_files is not null");
	foreach ($rs as $r) {
		$filename = $r->upload_files;
		if (!isset($instruments[$filename])) {
			foreach ($all_instruments['pcr'] as $mc) {
				if (stripos($filename, $mc) > 0) {
					$instruments[$filename] = strtoupper($mc);
					break;
				}
			}
		}


		$mc = isset($instruments[$filename]) ? $instruments[$filename] : '';
		if (!empty($mc)) {
			$wpdb->update('ci_reports_pcr', array('instrument' => $mc), array('id' => $r->id));
		}
	}

	//fix empty instrument for reports
	$instruments = array();
	$rs = $wpdb->get_results("select id,upload_files from ci_reports where (instrument='' or instrument is null) 
											and upload_files!= '' and upload_files is not null");
	foreach ($rs as $r) {
		$filename = $r->upload_files;
		if (!isset($instruments[$filename])) {
			foreach ($all_instruments['tox'] as $mc) {
				if (stripos($filename, $mc) > 0) {
					$instruments[$filename] = strtoupper($mc);
					break;
				}
			}
		}


		$mc = isset($instruments[$filename]) ? $instruments[$filename] : '';
		if (!empty($mc)) {
			$wpdb->update('ci_reports', array('instrument' => $mc), array('id' => $r->id));
		}
	}

	echo 'fix instrument =>' . (microtime(true) - $timestart) . "<br>\n";
}


if ($logdata['last_archived'] == 0 || $logdata['last_archived'] < time() - 86400 || isset($_GET['testcron'])) {
	$timestart = microtime(true);
	$logdata['last_archived'] = time();
	file_put_contents($log_file, json_encode($logdata));

	$marchive_days = tr_get_option('ci_pdf_data', 'marchive_days');
	if ($marchive_days > 0) {
		$archive_date = date('Y-m-d', strtotime('- ' . $marchive_days . ' days '));
	} else {
		$archive_date = date('Y-m-d', strtotime('- 1 month '));
	}


	//change to archived
	foreach (tr_pdd_get_batch_types() as $btype) {
		$table = tr_pdd_get_table_by_btype($btype);
		$wpdb->query("update {$table} set batch_status='archived'   
						where (batch_status = '' or batch_status = '0' or batch_status='archiving' or batch_status='rejected') 
							and (batch_id = '' or batch_id='0')
							and (batch_created < '{$archive_date}' or doc < '{$archive_date}')
							");
		echo $wpdb->last_error;
	}

	echo '<br>archived =>' . (microtime(true) - $timestart) . "<br>\n";
	echo '<br>archive_date =>' . $archive_date . "<br>\n";
}


if ($logdata['last_batch_ids'] == 0 || $logdata['last_batch_ids'] < time() - 86400 || isset($_GET['testcron'])) {


	$timestart = microtime(true);
	$logdata['last_batch_ids'] = time();
	file_put_contents($log_file, json_encode($logdata));

	trpdd_sync_batch_ids(date('Y-m-d', strtotime('-7 days')));

	echo 'sync batchid =>' . (microtime(true) - $timestart) . "<br>\n";
}


$syncodctime = get_option('ci_need_sync_doc_clinic', 0);
$need_sync_doctor = $syncodctime > time() - 86400;
if (date_i18n("H") == 1 && ($logdata['last_assign_doctors'] == 0 || $logdata['last_assign_doctors'] < time() - 86400)) {
	$need_sync_doctor = true;
}

if ($need_sync_doctor) {

	$timestart = microtime(true);
	$logdata['last_assign_doctors'] = time();
	file_put_contents($log_file, json_encode($logdata));

	$need_assign_doctors = tr_get_option('ci_pdf_data', 'doc_o_cli');
	if ($need_assign_doctors) {
		$timestart = microtime(true);
		$doctors   = $wpdb->get_col("select doc_ID from ci_doc doc
					where doc.doc_ID in (select doc_ID from ci_clinic_doc)");
		$rs        = $wpdb->get_col("select cID from ci_clinic cli");
		foreach ($rs as $cid) {
			$exists = $wpdb->get_col("select doc.doc_ID from ci_doc doc
							join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
							where cd.cli_ID = {$cid} ");
			foreach ($doctors as $did) {
				if (! in_array($did, $exists)) {
					$wpdb->query("INSERT IGNORE INTO ci_clinic_doc set doc_ID = {$did},
                                                    cli_ID = {$cid} ");
				}
			}
		}
		echo 'fix doctors same clinic =>' . (microtime(true) - $timestart) . "<br>\n";
	}
	update_option('ci_need_sync_doc_clinic', 0);


	$sitename = $wpdb->get_var("select meta_value from wp_sitemeta where meta_key = 'site_name'");
	$blogname = get_option('blogname');
	if ($sitename != $blogname) {
		$wpdb->update('wp_sitemeta', array('meta_value' => $blogname), array('meta_key' => 'site_name'));
	}
}

// fix clinic reflex urine Chemistry
if ($logdata['fix_cli_reflex'] == 0 || $logdata['fix_cli_reflex'] < time() - 86400) {
	$logdata['fix_cli_reflex'] = time();

	$active_clinics = $wpdb->get_col("select cID from ci_clinic where cli_status = 1");
	if (count($active_clinics) > 0) {
		$rs = $wpdb->get_results("select u_ID, u_reflex_clinics from ci_urine_validity 
        where u_reflex_clinics !='' and u_reflex_clinics is not null");
		foreach ($rs as $r) {
			$clids = explode(',', $r->u_reflex_clinics);
			$has_change = false;
			foreach ($clids as $i => $cid) {
				if (!in_array($cid, $active_clinics)) {
					unset($clids[$i]);
					$has_change = true;
				}
			}
			if ($has_change) {
				$u_reflex_clinics = implode(',', $clids);
				$wpdb->update(
					'ci_urine_validity',
					array('u_reflex_clinics' => $u_reflex_clinics),
					array('u_ID' => $r->u_ID)
				);
			}
		}
	}
}

//fix error log
$logerrorfile = WP_CONTENT_DIR . '/log/errors/error_log.log';
$filesize = filesize($logerrorfile);
if ($filesize > 1000000) {
	function limitLogFileSize($filePath, $maxSize = 1024 * 1024)
	{ // 1MB
		if (!file_exists($filePath)) return;

		$size = filesize($filePath);
		if ($size <= $maxSize) return;

		$fp = fopen($filePath, 'r');
		fseek($fp, -$maxSize / 2, SEEK_END); // Keep last half of maxSize
		$data = fread($fp, $maxSize / 2);
		fclose($fp);

		// Truncate and write back
		file_put_contents($filePath, ltrim($data));
	}

	limitLogFileSize($logerrorfile);
}


file_put_contents($log_file, json_encode($logdata));
echo 'done';
