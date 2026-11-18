<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;

$log_file = WP_CONTENT_DIR . '/log/manual_reports.txt';

if (empty($batch_id)) {
	$batch_id = tr_pdd_get_current_batch_id();
}

if (time() - filemtime($log_file) < 60 && !isset($_GET['testcron'])) {

	$next_cron = wp_next_scheduled('cron_add_manual_reports', array($batch_id));

	if (!$next_cron || $next_cron - 400 > time()) {
		wp_clear_scheduled_hook('cron_add_manual_reports', array($batch_id));
		wp_schedule_single_event(time() + 100, 'cron_add_manual_reports', array($batch_id));
	}

	return;
}


$f = fopen($log_file, 'a+');

$today = Tr_Base_Class_V4::date('Y-m-d');



foreach (tr_pdd_get_batch_types() as $btype) {
	if ($f) {
		fwrite($f, date_i18n('Y-m-d H:i:s') . ' ' . $btype . ":\n");
	}

	$batch_tbl = tr_pdd_get_table_by_btype($btype);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);

	$group_ids = $wpdb->get_col("select tg_ID from ci_test_groups 
							where tg_manual_edit = 1 
							and tg_parent = 0
							and tg_type = '{$btype}' ");
	if (count($group_ids) == 0) {
		if ($f) {
			fwrite($f, '=> no manual groups' . "\n");
		}
		continue;
	} else {
		if ($f) {
			fwrite($f, '=> found groups: ' . implode(', ', $group_ids) . "\n");
		}
	}

	$where_mchils = array();
	foreach ($group_ids as $gid) {
		$where_mchils[] = " find_in_set( 'GROUP_{$gid}', ba.panel_tests_requested ) ";
	}
	$where_mchils = implode(' or ', $where_mchils);

	$sql = "select 
				ba.assen,
				ba.batch_id,
				ba.lab,
				ba.lab_id
	        from {$batch_tbl} ba
			left join {$report_tbl} re ON re.patient_id = ba.assen
			where ba.batch_id = '{$batch_id}'
			and re.id is null
			and ( {$where_mchils} )";
	//file_put_contents(WP_CONTENT_DIR.'/log.txt',$sql);
	$rows = $wpdb->get_results($sql);
	if (!empty($wpdb->last_error) && $f) {
		fwrite($f, 'error=> ' . $wpdb->last_error . "\n");
	} else {
		if ($f) {
			fwrite($f, '=> found ' . count($rows) . " records\n");
		}
	}

	foreach ($rows as $row) {
		$report_data = array(
			'patient_id' => $row->assen,
			'batch_id' => $row->batch_id,
			'created' => date_i18n('Y-m-d H:i:s'),
			'created_date' => $today,
			'sublab' => $row->lab,
			'lab_id' => $row->lab_id
		);
		$wpdb->insert($report_tbl, $report_data);
		if (!empty($wpdb->last_error) && $f) {
			fwrite($f, 'error=> ' . $wpdb->last_error . "\n");
		}
	}


	//change Phlebotomist to completed when received
	if ($btype == 'blood' && tr_get_option('ci_req_settings_' . $btype, 'Phlebotomist_reced_comed')) {
		$today = date_i18n('Y-m-d');

		$rs = $wpdb->get_results("select ba.mid from {$batch_tbl} ba
		JOIN ci_receiving_batches rb ON rb.mid = ba.mid and rb.btype = '{$btype}'
		join ci_receiving r ON r.re_ID = rb.re_ID
		where r.re_today >='{$today}' and (ba.batch_status is null or ba.batch_status ='')
		limit 100");
		foreach ($rs as $r) {
			$wpdb->update($batch_tbl, array('batch_status' => 'so_collected'), array('mid' => $r->mid));

			if (!empty($wpdb->last_error) && $f) {
				fwrite($f, 'error=> ' . $wpdb->last_error . "\n");
			}
		}
	}
}

if ($f) {
	fclose($f);
	if (filesize($log_file) > 500000) {
		$olddata = substr(file_get_contents($log_file), 390000);
		file_put_contents($log_file, $olddata);
	}
}
