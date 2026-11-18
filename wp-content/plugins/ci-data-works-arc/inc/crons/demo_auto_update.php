<?php
if (!defined('ABSPATH')) {
	http_response_code(403);
	exit('Forbidden');
}
global $wpdb;
if (!defined('DEMO_SITE') || !DEMO_SITE) {
	return;
}


echo 'auto demo';
$limit_modify = date_i18n('Y-m-d');
$modified_date = date_i18n('Y-m-d H:i:s');
$todaytime = strtotime(date_i18n('Y-m-d'));
$timenow = strtotime(date_i18n('Y-m-d H:i:s'));

if (!isset($_GET['onlyley'])) {
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);
		$result_tbl = tr_pdd_get_table_result_by_btype($btype);

		$sql = "select mid, doc,assen from {$batch_tbl} ";
		$sql .= " where batch_modified < '{$limit_modify}' ";
		$sql .= "  and cli_ID > 0 ";
		$sql .= " order by doc desc";
		$rows = $wpdb->get_results($sql);

		$demo_report = false;
		$demo_bat = false;
		$demo_report_rows = array();

		$datecount = 0;
		foreach ($rows as $i => $row) {
			if ($i % 12 == 0 && $i>0) {
				$datecount++;
			}
			$doc_new_time = $todaytime - ($datecount * 86400);
			$approved_time = $doc_new_time + 86400;
			if ($approved_time > $timenow) {
				$approved_time = $timenow;
			}
			$doc_new = date('Y-m-d H:i:s', $doc_new_time + (rand(1, 4) * 3600));
			$received_new = date('Y-m-d H:i:s', strtotime($doc_new) + (rand(1, 10) * 3600));
			$approved_date = date('Y-m-d H:i:s', $approved_time + (rand(6, 10) * 3600));
			$batch_id = date('Ymd', strtotime($doc_new));
			

			$update_bat = array(
				'doc' => $doc_new,
				'spec_received' => $received_new,
				'batch_id' => $batch_id,
				'batch_modified' => $modified_date
			);

			$report_id = $wpdb->get_var("select id from {$report_tbl} where patient_id = '{$row->assen}'");
			if (!$report_id) {
				if (!$demo_report) {
					$demo_report = $wpdb->get_row("select * from {$report_tbl} where estatus = 'C' and upload_files!='' and instrument!='' and final_results = 1 and count_drugs>0");
					$demo_bat = $wpdb->get_row("select * from {$batch_tbl} where assen = '{$demo_report->patient_id}'");
					$demo_report_rows = $wpdb->get_results("select * from {$result_tbl} where report_id = {$demo_report->id}");
				}
				

				$update_bat['panel_tests_requested'] = $demo_bat->panel_tests_requested;

				$wpdb->insert($report_tbl, array(
					'patient_id' => $row->assen,
					'created_date' => $approved_date,
					'approve_date' => $approved_date,
					'estatus' => 'C',
					'author_id' => $demo_report->author_id,
					'instrument' => $demo_report->instrument,
					'final_results' => 1,
					'count_drugs' => $demo_report->count_drugs
				));
				$report_id = $wpdb->insert_id;
				foreach ($demo_report_rows as $r) {
					$wpdb->insert($result_tbl, array(
						'drug' => $r->drug,
						'conc' => $r->conc,
						'report_id' => $report_id,
						'pos-neg' => $r->{'pos-neg'},
					));
				}
			} else {
				$rs = $wpdb->update($report_tbl, array(
					'created_date' => $approved_date,
					'approve_date' => $approved_date
				), array('patient_id' => $row->assen));
			}


			$wpdb->update($batch_tbl, $update_bat, array('mid' => $row->mid));
		}
	}
}
//update levey-jenningQC
foreach (array('HQC', 'LQC', 'MQC') as $qc) {
	$datecount = 0;
	$sql = "select acquisition_date, id from ci_reports re";
	$sql .= " where acquisition_date < '{$limit_modify}' ";
	$sql .= "  and re.patient_name='{$qc}'  ";
	$sql .= " order by acquisition_date desc";
	$rows = $wpdb->get_results($sql);
	foreach ($rows as $i => $row) {

		for ($i = 1; $i <= 30; $i++) {

			$datecount++;
			$doc_new_time = $todaytime - ($datecount * 86400);

			$doc_new = date('Y-m-d', $doc_new_time);
			$batch_id = str_replace('-', '', $doc_new);

			$update = array(
				'acquisition_date' => $doc_new,
				'collection_date' => $doc_new,
				'created_date' => $doc_new,
				'created' => $doc_new,
				'batch_id' => $batch_id,
			);

			$wpdb->update('ci_reports', $update, array('id' => $row->id));

			if (!empty($wpdb->last_error) && stripos($wpdb->last_error, 'Duplicate') !== false) {
				continue;
			}
			break;
		}
	}
}
