<?php
global $wpdb;
$redirect_url = '';
$_POST = tr_pdd_stripslashes_deep($_POST);

if (trpdd_check_form_unique_id()) {

	$_POST = array();
	return;
}

$pos_neg = array(
	'' => 'Blank',
	'NEG' => 'Negative',
	'POS' => 'Positive',
	'PEN' => 'Pending',
	'INC' => 'Inconclusive',
	'INV' => 'Invalid',
	'RER' => 'Rerun',
	'N/A' => 'N/A'
);

$btype  	= isset($_POST['btype']) && !empty($_POST['btype']) ? $_POST['btype'] : 'tox';
$id     	= $_REQUEST['id'];
$update 	= $_POST['info'];

$changed 	= array();
$update_batch = is_array($_POST['updatebat']) ? $_POST['updatebat'] : array();


$table_batch 	= tr_pdd_get_table_by_btype($btype);
$table_report 	= tr_pdd_get_table_report_by_btype($btype);
$table_result 	= tr_pdd_get_table_result_by_btype($btype);
$result_settings = get_option('ci_result_settings_' . $btype, array());

$report = $wpdb->get_row("select 
				re.*,
				ba.mid,
				ba.lab_id, 
				ba.assen,
				ba.batch_status
            from {$table_report} re
            JOIN {$table_batch} ba ON ba.assen = re.patient_id
             where re.id={$id}", ARRAY_A);


$old_results = array();
$old_rs = $wpdb->get_results("select * from {$table_result} where report_id = {$id}");
foreach ($old_rs as $old_r) {
	$old_results[$old_r->id] = $old_r;
}
unset($old_rs);


$log_action = '';
$batch_status = $report['batch_status'];
if (isset($update_batch['batch_status']) && $update_batch['batch_status'] != $report['batch_status']) {
	$batch_status = $update_batch['batch_status'];
}

// hold
if ($update_batch['batch_status'] == 'hold' && $report['batch_status']  != 'hold') {
	$wpdb->replace($table_batch . '_meta', [
		'mid' => $report['mid'],
		'meta_key' => 'hold_batch_status',
		'meta_value' => $report['batch_status']
	]);
}
if ($report['batch_status'] == 'hold' && $update_batch['batch_status']  != 'hold') {
	$wpdb->delete($table_batch . '_meta', ['mid' => $report['mid'], 'meta_key' => 'hold_batch_status']);
}


$imagefiledir = WP_CONTENT_DIR . '/results/images/';
$imagefileurl = '/wp-content/results/images/';

if (isset($_FILES['resultimages']) && isset($_FILES['resultimages']['name'])) {


	if (!file_exists($imagefiledir)) {
		wp_mkdir_p($imagefiledir);
	}

	foreach ($_FILES['resultimages']['name'] as $k => $fname) {
		$tmp_name = $_FILES['resultimages']['tmp_name'][$k];

		$ext =  pathinfo($fname, PATHINFO_EXTENSION);
		$filename = $report['assen'] . '_' . $k . '.png';
		$filepath = $imagefiledir . '/' . $filename;
		if (file_exists($filepath)) {
			unlink($filepath);
		}
		if (move_uploaded_file($tmp_name, $filepath)) {
			//done
		}
	}
} else if (isset($_POST['resultimages']) && is_array($_POST['resultimages'])) {
	foreach ($_POST['resultimages'] as $k => $vl) {
		if ($vl === '') {
			$filename = $report['assen'] . '_' . $k . '.png';
			$filepath = $imagefiledir . '/' . $filename;
			unlink($filepath);
		}
	}
}




if (isset($_POST['reverse'])) {
	$log_action = 'reverse';
	$update['estatus'] = 'M';
} else if (isset($_POST['approval']) || isset($_POST['approval_next'])) {

	if ($update['approve_date'] == '0000-00-00 00:00:00' || empty($update['approve_date'])) {
		$update['approve_date'] = date_i18n("Y-m-d H:i:s");
	}
	$update['estatus'] = 'C';
	if (isset($_POST['approval_next'])) {
		//get next
		if (!empty($_POST['next_link'])) {
			$redirect_url = $_POST['next_link'];
		} else {
			$next = tr_pdd_next_result($id, $report['batch_id'], $report['lab_id'], $btype);
			if ($next > 0) {
				$redirect_url = Tr_Base_Class_V6::link(array('id' => $next), false);
			}
		}
	}
	$nextcron = wp_next_scheduled('ci_cron_clean_results');
	if ($nextcron == 0 || $nextcron > time() + 360) {
		wp_schedule_single_event(time() + 180, 'ci_cron_clean_results', array(0));
	}

	$log_action = 'approve';
} else if (isset($_POST['submit_next'])) {
	//get next
	if (!empty($_POST['next_link'])) {
		$redirect_url = $_POST['next_link'];
	} else {
		$next = tr_pdd_next_result($id, $report['batch_id'], $report['lab_id'], $btype);
		if ($next > 0) {
			$redirect_url = Tr_Base_Class_V6::link(array('id' => $next), false);
		}
	}
}

$update['modified'] = time();
if ($report['final_results'] == 2 && $update['final_results'] == 1) {
	$update['faxed'] = 0;
}

if ($report['instrument'] != $update['instrument']) {
	$wpdb->update($table_result, array('instrument' => $update['instrument']), array('report_id' => $id));
}


$inconclusive_drugs = array();
$rs = $wpdb->get_results("select * from ci_test_panels where te_type='{$btype}'");
foreach ($rs as $r) {
	$r->drug = strtoupper($r->te_name);
	$all_tests[$r->te_ID] = $r;
	if ($r->te_inconclusive_ID == -1 || $r->te_is_control) {
		$inconclusive_drugs[] = strtoupper($r->te_name);
		if (!empty($r->abrev)) {
			$inconclusive_drugs[] = strtoupper($r->te_abrev);
		}
	}
}

$has_pos = '';
$has_covid = false;
if (!isset($_POST['data_results']) || !is_array($_POST['data_results'])) {
	$_POST['data_results'] = array();
}

foreach ($_POST['data_results'] as $k => $vl) {


	if (isset($vl['drug']) && $vl['drug'] == 'Ethanol*' && $vl['pos-neg'] == 'POS' && empty($vl['comments'])) {
		$vl['comments'] = 'Recent Alcohol Use';
	}
	$has_cmvalue = false;
	if ($vl['drug'] == 'antibiotic_gram_stains') {
		$vl['comments'] = implode('|', is_array($vl['comments']) ? $vl['comments'] : []);
	} else if (is_array($vl['comments'])) {
		if (isset($old_results[$k])) {
			$oldcomments = json_decode($old_results[$k]->comments, true);
			if (is_array($oldcomments) && count($oldcomments) > 0) {
				$vl['comments'] = array_merge($oldcomments, $vl['comments']);
			}
		}
		$vl['comments'] = json_encode($vl['comments']);
		$has_cmvalue  = true;
	} else if (!empty($vl['comments'])) {

		$vl['comments'] = trpdd_show_symbol($vl['comments']);
	}

	if (isset($vl['place'])) {
		unset($vl['place']);
	}

	if ($btype == 'study') {
		unset($vl['pos-neg']);

		if (is_array($vl['conc'])) {
			$vl['conc'] = json_encode($vl['conc']);
		}
	}


	if (stripos($k, 'new') !== false && !empty($vl['drug'])) {
		//check exist
		$existssql = $wpdb->prepare("select * from {$table_result} where report_id =  {$id} and drug = %s ", $vl['drug']);
		if (isset($vl['group_id']) && $vl['group_id'] > 0) {
			$existssql .= " and group_id = {$vl['group_id']}";
		}
		$exist1 = $wpdb->get_row($existssql);


		if ($exist1 && $exist1->id > 0) {
			$k = $exist1->id;
		}
	}


	if (stripos($k, 'new') !== false) {
		$vl['report_id'] = $id;
		if (trim($vl['conc']) === '' && empty($vl['pos-neg']) && !$has_cmvalue) {
			continue;
		}

		$rs = $wpdb->insert($table_result, $vl);
		$k = $wpdb->insert_id;
		if ($rs === false) {
			Tr_Session_Class::add_error('error: ' . $wpdb->last_error);
		}
	} else {

		//get changes
		if (isset($old_results[$k])) {
			$olddata = $old_results[$k];

			if ($vl['conc'] != $olddata->conc) {
				$changed[$olddata->drug] = $olddata->conc . ' (to) ' . $vl['conc'];
			} else if ($vl['pos-neg'] != $olddata->{'pos-neg'}) {
				$changed[$olddata->drug] = $pos_neg[$olddata->{'pos-neg'}] . ' (to) ' . $pos_neg[$vl['pos-neg']];
			}
		}

		$rs = $wpdb->update($table_result, $vl, array('id' => $k));
		if ($rs === false) {
			Tr_Session_Class::add_error('error: ' . $wpdb->last_error);
		}
	}

	if (empty($vl['drug'])) {
		$vl['drug'] = $wpdb->get_var("select drug from {$table_result} where id = {$k} ");
	}

	$vl['drug'] = strtoupper($vl['drug']);
	if (count($inconclusive_drugs) > 0 && in_array($vl['drug'], $inconclusive_drugs)) {
		//nothing now
	} else if (in_array($vl['drug'], array('N GENE', 'S GENE', 'ORF1AB', 'MS2'))) {
		$has_covid = true;
	} else if ($has_pos < 1 && in_array($vl['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PVH', 'PVL'))) {
		$has_pos = 1;
	} else if (in_array($vl['pos-neg'], array('PCH', 'PCL'))) {
		$has_pos = 2;
	} else if ($has_pos === '' && $vl['pos-neg'] == 'NEG') {
		$has_pos = 0;
	}
}


//update data_specimen
$spectests = array();
if ($btype == 'tox') {
	$spectrs = $wpdb->get_results("select * from ci_urine_validity");
	foreach ($spectrs as $sr) {
		$spectests[$sr->u_ID] = $sr->u_name;
	}
}
if (isset($_REQUEST['data_specimen'])) {

	foreach ($_REQUEST['data_specimen'] as $k => $vl) {
		if ($k == 0) continue;
		$spec_update = array(
			'mid' => $report['mid'],
			'testid' => $k,
		);
		if (isset($vl['conc'])) {
			$spec_update['conc'] = $vl['conc'];
		}
		if (isset($vl['outcome'])) {
			$spec_update['outcome'] = $vl['outcome'];
		}
		if (isset($vl['comments'])) {
			$spec_update['comments'] = $vl['comments'];
		}
		if (isset($vl['checked'])) {
			$spec_update['checked'] = $vl['checked'];
		}

		$exists = $wpdb->get_row("select * from ci_result_test_speciments where mid = {$report['mid']} and testid={$k} ");
		if ($exists) {
			if ($exists->conc != $spec_update['conc'] || $exists->outcome != $spec_update['outcome'] || $exists->comments != $spec_update['comments'] || $exists->checked != $spec_update['checked']) {
				$changed[$spectests[$k]] = "{$exists->conc} | {$exists->outcome} (to) {$spec_update['conc']} | {$spec_update['outcome']}";
			}
			$wpdb->update('ci_result_test_speciments', $spec_update, array('mid' => $report['mid'], 'testid' => $k));
		} else {
			$changed[$spectests[$k]] = " (to) {$spec_update['conc']} | {$spec_update['outcome']}";
			$wpdb->insert('ci_result_test_speciments', $spec_update);
		}
	}
}

if (isset($_REQUEST['data_specimen_screening'])) {
	foreach ($_REQUEST['data_specimen_screening'] as $k => $vl) {
		if ($k == 0) continue;
		$screening_update = array(
			'mid' => $report['mid'],
			'testid' => $k,
		);
		if (isset($vl['conc'])) {
			$screening_update['conc'] = $vl['conc'];
		}
		if (isset($vl['outcome'])) {
			$screening_update['outcome'] = $vl['outcome'];
		}
		if (isset($vl['comments'])) {
			$screening_update['comments'] = $vl['comments'];
		}
		if (isset($vl['checked'])) {
			$screening_update['checked'] = $vl['checked'];
		}

		$exists = $wpdb->get_row("select * from ci_result_test_speciments where mid = {$report['mid']} and testid={$k} ");
		if ($exists) {
			if ($exists->conc != $screening_update['conc'] || $exists->outcome != $screening_update['outcome'] || $exists->comments != $screening_update['comments'] || $exists->checked != $screening_update['checked']) {
				$changed[$spectests[$k]] = "{$exists->conc} | {$exists->outcome} (to) {$screening_update['conc']} | {$screening_update['outcome']}";
			}
			$wpdb->update('ci_result_test_speciments', $screening_update, array('mid' => $report['mid'], 'testid' => $k));
		} else {
			$changed[$spectests[$k]] = " (to) {$screening_update['conc']} | {$screening_update['outcome']}";
			$wpdb->insert('ci_result_test_speciments', $screening_update);
		}
	}
}
//update Methamphetamine
if (isset($_POST['methamphetamine'])) {
	$metha_table = 'ci_result_metha';
	$metha = $wpdb->get_row("select * from {$metha_table} WHERE report_id='" . $id . "' ", ARRAY_A);
	$me_val = $_POST['methamphetamine'];
	if ($metha) {
		$wpdb->update($metha_table, $me_val, array('report_id' => $metha['report_id']));
	} else {
		$me_val['report_id'] = $id;
		$wpdb->insert($metha_table, $me_val);
	}
}





if (isset($_POST['has_antibiotics'])) {
	if (!isset($_POST['antibioticsdata']) || !is_array($_POST['antibioticsdata'])) {
		$_POST['antibioticsdata'] = array();
	}
	if (!isset($_POST['labresult']) || !is_array($_POST['labresult'])) {
		$_POST['labresult'] = array();
	}
	$antibioticids = array(0);
	foreach ($_POST['antibioticsdata'] as $teid => $arr) {
		foreach ($arr as $taid => $vl) {
			if (!empty($vl)) {
				$mic_vl = null;
				$symbol = '';
				if (isset($_POST['micdata'])) {
					$mic_vl = $_POST['micdata'][$teid][$taid];
				}

				if (isset($_POST['symbol_antibioticsdata'])) {
					$symbol = isset($_POST['symbol_antibioticsdata'][$teid][$taid]) ? $_POST['symbol_antibioticsdata'][$teid][$taid] : '';
				} else if (isset($_POST['symbol_micdata'])) {
					$symbol = isset($_POST['symbol_micdata'][$teid][$taid]) ? $_POST['symbol_micdata'][$teid][$taid] : '';
				}



				if (!empty($symbol)) {
					if (!empty($mic_vl)) {
						$mic_vl = trim($symbol . $mic_vl);
					} else {
						$vl = trim($symbol . $vl);
					}
				}

				$rid = $wpdb->get_var("select ID 
									from ci_results_antibiotics
									where report_id = {$id}
									and btype = '{$btype}'
									and te_ID = {$teid}
									and ta_ID = {$taid}
									");
				$darr = array(
					'report_id' => $id,
					'btype'     => $btype,
					'te_ID'     => $teid,
					'ta_ID'     => $taid,
					'result'     => $vl,
				);

				if ($mic_vl !== null) {
					$darr['mic_value'] = $mic_vl;
				}

				if ($rid > 0) {
					$wpdb->update('ci_results_antibiotics', $darr, array('ID' => $rid));
				} else {
					$wpdb->insert('ci_results_antibiotics', $darr);
					$rid = $wpdb->insert_id;
				}
				$antibioticids[] = $rid;
			}
		}
	}
	$antibioticids = implode(',', $antibioticids);
	$wpdb->query("delete from ci_results_antibiotics 
					where report_id = {$id} 
					and btype = '{$btype}'
					and ID not in ({$antibioticids})");


	$labresultteids = array(0);
	foreach ($_POST['labresult'] as $teid => $vl) {
		$rid = $wpdb->get_var("select ID from ci_results_labresults
									where report_id = {$id}
									and te_ID = {$teid}
									");
		$darr = array(
			'report_id' => $id,
			'te_ID'     => $teid,
			'result'     => $vl,
		);
		if ($rid > 0) {
			$wpdb->update('ci_results_labresults', $darr, array('ID' => $rid));
		} else {
			$wpdb->insert('ci_results_labresults', $darr);
		}
		$labresultteids[] = $teid;
	}
	$labresultteids = implode(',', $labresultteids);
	$wpdb->query("delete from ci_results_labresults 
					where report_id = {$id} 
					and te_ID not in ({$labresultteids})");
}




if ($btype == 'pcr') {
	if ($has_covid) {
		$arr = trpdd_get_pcr_covid_test();
		$arr['drug'] = $arr['te_name'];

		$arr = trpdd_cal_ncovid19($arr, $id, $report['instrument'], $report['created_date']);
		if ($arr['pos-neg'] == 'POS') {
			$update['covid_pos'] = 1;
		} else if ($arr['pos-neg'] == 'RER') {
			$update['covid_pos'] = 2;
		} else if ($arr['pos-neg'] == 'NEG') {
			$update['covid_pos'] = 0;
		}
	}

	if ($result_settings['e_listby_plate']) {
		trpdd_pcr_check_update_WH($report, true);
	}
}


if (empty($report['upload_files']) && empty($report['estatus'])) {
	$update['created_date'] = date_i18n('Y-m-d');
}

$rs = $wpdb->update($table_report, $update, array('id' => $id));
if ($rs === false) {
	Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
} else {
	Tr_Session_Class::add('Saved Form Successfully');

	//upldate $report values
	foreach ($update as $k => $vl) {
		$report[$k . '_old'] = $report[$k] ?? '';
		$report[$k] = $vl;
	}

	$log_action = 'edit' . $log_action . '_rs';

	if (count($changed) > 0) {
		$log_action .= '_cd';
	}

	trpdd_log_user(get_current_user_id(), $log_action, $changed, $report['mid'], $btype, '', '', $report['assen']);

	if (isset($update['estatus'])) {
		if ($update['estatus'] == 'C') {
			$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, $btype);
			if (!isset($rejected_statuses[$batch_status])) {
				$update_batch['batch_status'] = 'completed';
			}

			trpdd_trigger_approve_report($report, $btype);

			trpdd_add_cron_after_approve($btype);
		} else if ($update['estatus'] == 'M' && $report['batch_status'] == 'completed') {
			$update_batch['batch_status'] = 'resulted';
		}
	}


	if (count($update_batch) > 0) {
		$wpdb->update($table_batch, $update_batch, array('mid' => $report['mid']));
	}

	//update meta
	if (isset($_POST['batmeta']) && is_array($_POST['batmeta'])) {
		foreach ($_POST['batmeta'] as $k => $vl) {
			trpdd_update_batch_meta($report['mid'], $btype, $k, $vl);
		}
	}
}


$_POST['mid'] = $report['mid'];
trpdd_log_form('savers_' . $btype);

if (empty($redirect_url) && (isset($_GET['add_covid_demo']) || isset($_GET['createnew']) || isset($_GET['add_alltests']))) {
	$redirect_url = Tr_Base_Class_V6::link(array(
		'add_covid_demo' => '',
		'createnew' => '',
		'add_alltests' => '',
	), false);
}

if (!empty($redirect_url)) {
	wp_redirect($redirect_url);
	exit;
}
