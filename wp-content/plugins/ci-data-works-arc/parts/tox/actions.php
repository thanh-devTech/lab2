<?php

function tr_tox_create_report()
{
	global $wpdb;

	$mid = $_GET['createnew'];
	if (empty($mid)) return;

	$bat = $wpdb->get_row("select * from ci_batches where mid = {$mid} ");

	if ($bat) {
		$id = $wpdb->get_var("select id from ci_reports where patient_id='{$bat->assen}'");
		if ($id == 0) {
			if (empty($bat->batch_id)) {
				$bat->batch_id = tr_pdd_get_current_batch_id();
				$wpdb->update('ci_batches', array('batch_id' => $bat->batch_id), array('mid' => $mid));
			}
			$_GET['add_covid_demo'] = true;
			$data = array(
				'batch_id' => $bat->batch_id,
				'patient_id' => $bat->assen,
				'created_date' => date_i18n('Y-m-d'),
				'created' => date_i18n('Y-m-d H:i:s'),
			);
			$wpdb->insert('ci_reports', $data);
			$id = $wpdb->insert_id;
		}

		$report = tr_pdd_get_report_by_id($id, 'tox');
		echo '<meta http-equiv="refresh" content="1;url=' .
			Tr_Base_Class_V6::link(array('id' => $id, 'add_alltests' => true, 'createnew' => ''), false)
			. '" />';
		exit;
	}
}



function tr_tox_result_add_all_results($all_res, $report, $in_panel_tests_requested)
{
	$inew = 0;
	foreach ($in_panel_tests_requested as $drug_test) {
		$arr = array();
		if (isset($all_res[$drug_test])) {
			$arr['id'] = $all_res[$drug_test]['id'];
		} else {
			$arr['id'] = 'new' . $inew++;
		}
		$arr['dbdrug'] = $drug_test;
		$arr['drug'] = strtoupper($drug_test);
		$arr['conc'] = 40;
		$arr['pos-neg'] = "NEG";
		$all_res[$drug_test] = $arr;
	}
	return $all_res;
}
