<?php
global $wpdb, $medications, $report_id, $cutoffs, $drug_pairs, $report_these_drugs;
global $current_role;

$use_upload         = true;
$btype              = 'pcr';
$user_id            = get_current_user_id();
$auto_fax           = tr_get_option('ci_pdf_data', 'auto_fax_approve_pcr');

$table_batch        = 'ci_batches_pcr';
$table_report       = 'ci_reports_pcr';
$table_results      = 'ci_results_pcr';

$created_date       = date_i18n('Y-m-d');
$created            = date_i18n("Y-m-d H:i:s");

$log_dir            = WP_CONTENT_DIR . '/log/results_pcr/';
if (!is_dir($log_dir)) {
	wp_mkdir_p($log_dir);
}

add_thickbox();

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'pcr');

$covid_group = $wpdb->get_row("select * from ci_test_groups where tg_is_covid=1 and tg_type='pcr' ");

if (!$covid_group) {
	$covid_group = $wpdb->get_row("select * from ci_test_groups where tg_name like '%ncov%' and tg_type='pcr' ");
}
$covid_group_id = $covid_group ? $covid_group->tg_ID : 0;
$covid_group_key = 'GROUP_' . $covid_group_id;

if ($act == 'change_hold') {

	$return = array('status' => 'ok', 'ids' => array());

	$lab_id 	= $_REQUEST['lab_id'];
	$batch_id 	= $_REQUEST['batch_id'];
	$ids 		= trim($_REQUEST['ids'], ",");
	$reason 	= $_REQUEST['reason'];


	$sql = "
        select ba.mid, re.id, ba.batch_status, ba.assen
		from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        left JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where  (re.estatus != 'C' or re.estatus is null) ";

	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	} else {
		$sql .= " and lab.lab_ID={$lab_id} and ( ba.batch_id = '{$batch_id}') ";
	}
	$reports = $wpdb->get_results($sql);


	foreach ($reports as $re) {
		$return['ids'][] = $re->id;
		if ($re->batch_status == 'hold') continue;

		$wpdb->update($table_batch, array('batch_status' => 'hold'), array('mid' => $re->mid));
		trpdd_update_batch_meta($re->mid, $btype, 'hold_batch_status', $re->batch_status);

		//trpdd_update_batch_meta($re->mid, $btype,'_reason_hold',$reason);

		trpdd_log_user(get_current_user_id(), 'hold_rs', $reason, $re->mid, 'pcr',  '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'remove_hold') {

	$return = array('status' => 'ok', 'ids' => array());

	$lab_id 	= $_REQUEST['lab_id'];
	$batch_id 	= $_REQUEST['batch_id'];
	$ids 		= trim($_REQUEST['ids'], ",");
	$sql = "
        select ba.mid,ba.batch_status, ba.assen, re.id from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where ba.batch_status = 'hold'
         ";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	} else {
		$sql .= " and lab.lab_ID={$lab_id} and ( ba.batch_id = '{$batch_id}') ";
	}

	$reports = $wpdb->get_results($sql);


	foreach ($reports as $re) {
		$return['ids'][] = $re->id;

		if ($re->batch_status != 'hold') continue;
		$hold_status = $wpdb->get_var("select meta_value from {$table_batch}_meta where mid={$re->$mid} and meta_key = 'hold_batch_status'") ?: '';
		$wpdb->update($table_batch, array('batch_status' => $hold_status), array('mid' => $re->mid));
		$wpdb->delete($table_batch . '_meta', ['mid' => $re->$mid, 'meta_key' => 'hold_batch_status']);

		trpdd_log_user(get_current_user_id(), 'removehold_rs', '', $re->mid, 'pcr', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'final_selected') {
	$return = array('status' => 'ok');

	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$sql = "select re.id, 
		re.final_results, 
		re.estatus, 
		re.approve_date, 
		re.patient_id
        from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        left JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where re.id in ({$ids})
        ";

	$reports = $wpdb->get_results($sql, ARRAY_A);


	foreach ($reports as $report) {
		if ($report['final_results'] === '1') {
			continue;
		}
		$wpdb->update('ci_reports_pcr', array('final_results' => '1'), array('id' => $report['id']));
		if (!$report['final_results'] && $report['estatus'] == 'C') {
		}
	}
	wp_send_json($return);
} else if ($act == 'approve_selected') {
	$return = array('status' => 'ok', 'fails' => array(), 'msg' => '', 'adding_html' => array(), 'done_ids' => array(-1));
	$need_fax = false;

	$batch_id = isset($_REQUEST['batch_id']) ? $_REQUEST['batch_id'] : 0;
	$received_date = isset($_REQUEST['received_date']) ? $_REQUEST['received_date'] : '';
	$result_date = isset($_REQUEST['result_date']) ? $_REQUEST['result_date'] : '';

	$ids = trim($_REQUEST['ids'], ",");
	$where = " re.id in ({$ids}) ";
	if (empty($ids) && !empty($batch_id)) {
		$where = " ba.batch_id = '{$batch_id}' ";
	}

	$lab_id = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
	if ($lab_id > 0 && (!defined('TRPDD_SAME_LAB') || TRPDD_SAME_LAB == false)) {
		$where = " ba.lab_id = '{$lab_id}' ";
	}

	$sql = "select 
	                ba.*,
                    re.id,
	                re.estatus,
	                re.faxed,
	                re.upload_files,
	                re.instrument,
	                re.final_results,
	                re.covid_pos,
	                re.covid_inc_completed,
                    cli.`cli_om-fax` as fax
            from {$table_report} re
            JOIN {$table_batch} ba ON ba.assen = re.patient_id
            left JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
            left JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
            where {$where}
            ";


	$reports = $wpdb->get_results($sql, ARRAY_A);

	$approve_date = date_i18n('Y-m-d H:i:s');
	if (!empty($result_date)) {
		$approve_date = $result_date;
	}

	$errors = array();
	$done_arr = array();
	foreach ($reports as $report) {
		if ($report['estatus'] != 'C') {

			if (!isset($rejected_statuses[$report['batch_status']])) {
				//must not rejected
				$has_tests = $wpdb->get_var("select count(*) from {$table_results} 
				where report_id = {$report['id']} 
				and (
					(conc is not null and conc !='' )
					or `pos-neg` in ('NEG','POS','POL','POH','POM','PVH','PVL')
				)");
				if (!$has_tests) {
					$errors[] = $report['assen'] . ' cant approve because Results is blank.';
					continue;
				}
			}

			$has_covid = stripos($report['panel_tests_requested'], $covid_group_key) !== false;
			$has_more_panel = substr_count($report['panel_tests_requested'], 'GROUP_') >= 2;

			if ($has_covid && (($report['covid_pos'] == 2 || $report['covid_pos'] == 3) && $report['covid_inc_completed'] == 0)) {
				$return['fails'][] = $report['id'];
				$errors[] = $report['assen'] . ' cant approve because Rerun.';
				continue;
			}

			if ($current_role == 'results' && $report['final_results'] != 1) {
				$return['fails'][] = $report['id'];

				$errors[] = $report['assen'] . ' cant approve . You only can approve as final.';
				continue;
			}

			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $approve_date,
				'faxed' => 0,
			);

			$report['approve_date'] = $update_report['approve_date'];
			if ($has_covid && $has_more_panel) {
				$update_report['final_results'] = 2;
				$report['final_results'] = 2;
			} else if ($has_covid) {
				$update_report['final_results'] = 1;
				$report['final_results'] = 1;
			}



			$wpdb->update($table_report, $update_report, array('id' => $report['id']));

			$done_arr[] = $report['assen'] . ' approved';
			$return['done_ids'][] = $report['id'];

			$update_batch = array();
			if (!empty($received_date)) {
				$update_batch['spec_received'] = $received_date;
			}


			if (!isset($rejected_statuses[$report['batch_status']])) {
				$update_batch['batch_status'] = 'completed';
			}

			if (!empty($update_batch)) {
				$wpdb->update($table_batch, $update_batch, array('mid' => $report['mid']));
			}


			//add to fax
			if ($auto_fax && !$report['faxed'] && !empty($report['fax'])) {
				$type_result = 'rs_efax' . $btype;
				$added_fax = $wpdb->get_var("select id from ci_box_upload 
                                        where mid = {$report['mid']} 
                                        and type='{$type_result}' 
                                        ");
				if (!$added_fax) {

					list($path, $file) = trpdd_get_path_result_pdf($report, $btype);
					$path_db = $path . '/' . $file;

					$wpdb->insert(
						'ci_box_upload',
						array(
							'path'       => $path_db,
							'type'       => $type_result,
							'status'     => '5',
							'mid'        => $report['mid'],
							'account_id' => $report['cli_ID'],
							'etag'       => trim($report['fax']),
							'lab_ID'     => $report['lab_id'],
							'batch_id'   => $report['batch_id'],
							'btype'      => $btype,
							'uptime'      => time(),
							'sender'     => $report['requesting_practice'],
							'created'    => Tr_Base_Class_V6::date('Y-m-d H:i:s'),
							'created_by' => '-1'
						)
					);
				} else {
					$wpdb->update('ci_box_upload', array('status' => '5'), array('id' => $added_fax));
				}

				$need_fax = true;
				$return['adding_html'][$report['id']]['mstatus'] = 'Faxing...';
			}


			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'pcr', '', '', $report['assen']);

			trpdd_trigger_approve_report($report, 'pcr');
		} else {

			$errors[] = $report['assen'] . ' was approved before.';
		}
	}

	if (!empty($done_arr)) {
		$return['msg'] .= implode("\n", $done_arr);
	}

	if (!empty($errors)) {
		$return['msg'] .= "\n" . implode("\n", $errors);
	}

	if (count($done_arr) > 0) {
		trpdd_add_cron_after_approve('pcr', $need_fax);
	}


	wp_send_json($return);
} else if ($act == 'approve_all') {

	$return = array('status' => 'ok', 'msg' => '', 'adding_html' => array());
	$need_fax = false;
	$count_approved = 0;
	$lab_id 		= $_REQUEST['lab_id'];
	$batch_id 		= $_REQUEST['batch_id'];

	$received_date 	= isset($_REQUEST['received_date']) ? $_REQUEST['received_date'] : '';
	$result_date 	= isset($_REQUEST['result_date']) ? $_REQUEST['result_date'] : '';

	$ids = trim($_REQUEST['ids'], ",");
	$sql = "select 
                ba.*,
                re.id,
                re.estatus,
                re.faxed,
                re.upload_files,
                re.instrument,
                re.final_results,
                re.approve_date,
                re.covid_pos,
                re.covid_inc_completed,
                cli.`cli_om-fax` as fax
        from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        left JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
        where ba.batch_id = '{$batch_id}' ";
	if ($lab_id > 0) {
		$sql .= " and ba.lab_id = {$lab_id} ";
	}
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	}
	$reports = $wpdb->get_results($sql, ARRAY_A);

	$approve_date = date_i18n('Y-m-d H:i:s');
	if (!empty($result_date)) {
		$approve_date = $result_date;
	}

	foreach ($reports as $report) {
		if ($report['estatus'] != 'C') {
			$has_covid = stripos($report['panel_tests_requested'], $covid_group_key) !== false;
			$has_more_panel = substr_count($report['panel_tests_requested'], 'GROUP_') >= 2;

			if ($has_covid && (($report['covid_pos'] == 2 || $report['covid_pos'] == 3) && $report['covid_inc_completed'] == 0)) {
				Tr_Session_Class::add_error('Cant approve rerun');
				continue;
			}
			if ($current_role == 'results' && $report['final_results'] != 1) {
				continue;
			}

			$count_approved++;
			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $approve_date,
				'faxed' => 0,
			);
			$report['approve_date'] = $update_report['approve_date'];

			if ($has_covid && $has_more_panel) {
				$update_report['final_results'] = 2;
				$report['final_results'] = 2;
			} else if ($has_covid) {
				$update_report['final_results'] = 1;
				$report['final_results'] = 1;
			}
			$wpdb->update($table_report, $update_report, array('id' => $report['id']));



			$update_batch = array();
			if (!empty($received_date)) {
				$update_batch['spec_received'] = $received_date;
			}


			if (!isset($rejected_statuses[$report['batch_status']])) {
				$update_batch['batch_status'] = 'completed';
			}

			if (!empty($update_batch)) {
				$wpdb->update($table_batch, $update_batch, array('mid' => $report['mid']));
			}

			//update elink to send again
			$wpdb->update(
				'ci_sync_api_orders',
				array('mo_status' => 2),
				array('mo_status' => 1, 'mo_mid' => $report['mid'], 'mo_file_id' => -2)
			);

			//add to fax
			if ($auto_fax && !$report['faxed'] && !empty($report['fax'])) {
				$type_result = 'rs_efax' . $btype;
				$added_fax = $wpdb->get_var("select id from ci_box_upload 
                                        where mid = {$report['mid']} 
                                        and type='{$type_result}' 
                                        ");
				if (!$added_fax) {

					list($path, $file) = trpdd_get_path_result_pdf($report, $btype);
					$path_db = $path . '/' . $file;

					$wpdb->insert(
						'ci_box_upload',
						array(
							'path'       => $path_db,
							'type'       => $type_result,
							'status'     => '5',
							'mid'        => $report['mid'],
							'account_id' => $report['cli_ID'],
							'etag'       => trim($report['fax']),
							'lab_ID'     => $report['lab_id'],
							'batch_id'   => $report['batch_id'],
							'btype'      => $btype,
							'uptime'      => time(),
							'sender'     => $report['requesting_practice'],
							'created'    => Tr_Base_Class_V6::date('Y-m-d H:i:s'),
							'created_by' => '-1'
						)
					);
				} else {
					$wpdb->update('ci_box_upload', array('status' => '5'), array('id' => $added_fax));
				}

				$need_fax = true;
				$return['adding_html'][$report['id']]['mstatus'] = 'Faxing...';
			}

			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'pcr', '', '', $report['assen']);

			trpdd_trigger_approve_report($report, 'pcr');
		}
	}

	if ($count_approved > 0) {
		trpdd_add_cron_after_approve('pcr', $need_fax);
	}


	Tr_Session_Class::add("Approved Results");
	if (isset($_REQUEST['trajax'])) {
		if ($_REQUEST['trajax'] === '2') {
			wp_send_json($return);
			exit;
		}
		$url = Tr_Base_Class_V6::link(array('act' => '', 'trajax' => '', 'lab_id' => '', 'batch_id' => ''), false);
		wp_redirect($url);
		exit;
	}
} else if ($act == 'results_approve') {
	$id = $_REQUEST['id'];
	$need_fax = false;
	$report = $wpdb->get_row("select 
	                ba.*,
                    re.id,
	                re.estatus,
	                re.faxed,
	                re.upload_files,
	                re.instrument,
	                re.final_results,
	                re.covid_pos,
	                re.covid_inc_completed,
                    cli.`cli_om-fax` as fax
                from {$table_report} re
                join {$table_batch} ba ON ba.assen = re.patient_id
                left JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
                where re.id={$id}", ARRAY_A);

	//check blank tests

	if (!isset($rejected_statuses[$report['batch_status']]) && empty($report['result_pdf'])) {
		//must not rejected
		$has_tests = $wpdb->get_var("select count(*) from {$table_results} 
		where report_id = {$report['id']} 
		and (
				(conc is not null and conc !='' )
				or `pos-neg` in ('NEG','POS','POL','POH','POM','PVH','PVL')
			)");
		if (!$has_tests) {

			echo '<div class="ajaxresult">';
			echo '<div class="ajaxstatus">error</div>';
			echo '<div class="message_alert">You can\'t Approve because Results is Blank</div>';
			echo '</div>';
			exit;
		}
	}
	if ($report['estatus'] != 'C') {
		$status = 'C';
		$now 			= date_i18n('Y-m-d H:i:s');
		$has_covid 		= stripos($report['panel_tests_requested'], $covid_group_key) !== false;
		$has_more_panel = substr_count($report['panel_tests_requested'], 'GROUP_') >= 2;

		if ($current_role == 'results' && $report['final_results'] != 1) {
			Tr_Session_Class::add_error('Cant approve not Final');
		} else if ($has_covid && (($report['covid_pos'] == 2 || $report['covid_pos'] == 3) && $report['covid_inc_completed'] === '0')) {
			//nothing
			Tr_Session_Class::add_error('Cant approve rerun');
		} else {
			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $now,
				'faxed' => 0,
			);
			$report['approve_date'] = $update_report['approve_date'];
			if ($has_covid && $has_more_panel) {
				$update_report['final_results'] = 2;
				$report['final_results'] = 2;
			} else if ($has_covid) {
				$update_report['final_results'] = 1;
				$report['final_results'] = 1;
			}
			$wpdb->update($table_report, $update_report, array('id' => $report['id']));



			if (!isset($rejected_statuses[$report['batch_status']])) {
				$wpdb->update($table_batch, array('batch_status' => 'completed'), array('mid' => $report['mid']));
			}

			//update elink to send again
			$wpdb->update(
				'ci_sync_api_orders',
				array('mo_status' => 2),
				array('mo_status' => 1, 'mo_mid' => $report['mid'], 'mo_file_id' => -2)
			);
			//add to fax

			if ($auto_fax && !$report['faxed'] && !empty($report['fax'])) {
				$type_result = 'rs_efax' . $btype;
				$added_fax = $wpdb->get_var("select id from ci_box_upload 
                                        where mid = {$report['mid']} 
                                        and type='{$type_result}' 
                                        ");
				if (!$added_fax) {

					list($path, $file) = trpdd_get_path_result_pdf($report, $btype);
					$path_db = $path . '/' . $file;

					$rsf = $wpdb->insert(
						'ci_box_upload',
						array(
							'path'       => $path_db,
							'type'       => $type_result,
							'status'     => '5',
							'mid'        => $report['mid'],
							'account_id' => $report['cli_ID'],
							'etag'       => trim($report['fax']),
							'lab_ID'     => $report['lab_id'],
							'batch_id'   => $report['batch_id'],
							'btype'      => $btype,
							'uptime'      => time(),
							'sender'     => $report['requesting_practice'],
							'created'    => Tr_Base_Class_V6::date('Y-m-d H:i:s'),
							'created_by' => '-1'
						)
					);
				} else {
					$wpdb->update('ci_box_upload', array('status' => '5'), array('id' => $added_fax));
				}
				$need_fax = true;
			}


			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'pcr', '', '', $report['assen']);

			trpdd_trigger_approve_report($report, 'pcr');

			Tr_Session_Class::add("Approved Result #" . $id);
		}
	} else {
		Tr_Session_Class::add("Approved Result #" . $id);
	}

	trpdd_add_cron_after_approve('pcr', $need_fax);

	if (isset($_REQUEST['trajax'])) {
		echo '<div class="ajaxresult">';
		echo '<div class="ajaxstatus">ok</div>';
		Tr_Session_Class::show();

		if ($need_fax) {
			echo '<div class="adding_html" id="mstatus">Faxing...</div>';
		}
		echo '</div>';
		exit;
	}
} else if ($act == 'results_reverse') {
	$id = $_REQUEST['id'];
	$rsupd = false;
	$report = $wpdb->get_row("select re.*, ba.mid, ba.batch_status
	                from {$table_report} re
	                join {$table_batch} ba ON ba.assen = re.patient_id
	                where re.id={$id}", ARRAY_A);

	if ($report['estatus'] == 'C') {
		$updaterp = array('estatus' => 'M');
		if ($report['final_results'] === '1') {
			$updaterp['final_results'] = '';
		}
		$rsupd = $wpdb->update($table_report, $updaterp, array('id' => $id));

		if ($report['batch_status'] == 'completed') {
			$wpdb->update($table_batch, array('batch_status' => 'resulted'), array('mid' => $report['mid']));
		}

		//update api
		$wpdb->update('ci_sync_api_orders', array('mo_status' => '0'), array('mo_mid' => $report['mid'], 'mo_btype' => 'pcr'));

		trpdd_log_user(get_current_user_id(), 'reverse_rs', '', $report['mid'], 'pcr', '', '', $report['patient_id']);

		Tr_Session_Class::add("Reversed Result #" . $id);
	} else {
		Tr_Session_Class::add("Reversed Result .#" . $id);
	}
	if (isset($_REQUEST['trajax'])) {
		echo '<div class="ajaxresult" data-id="' . $id . '">';
		echo '<div class="ajaxstatus">' . ($rsupd !== false ? 'ok' : 'fail') . '</div>';
		Tr_Session_Class::show();
		echo '</div>';
		exit;
	}
} else if ($act == 'results_final') {
	$id = $_REQUEST['id'];
	$rsupd = false;
	$report = $wpdb->get_row("select re.*, ba.mid, ba.batch_status
	                from {$table_report} re
	                join {$table_batch} ba ON ba.assen = re.patient_id
	                where re.id={$id}", ARRAY_A);

	if ($report['final_results'] !== '1') {
		$rsupd = $wpdb->update($table_report, array('final_results' => '1'), array('id' => $id));
		trpdd_log_user(get_current_user_id(), 'final_rs', '', $report['mid'], 'pcr', '', '', $report['patient_id']);

		Tr_Session_Class::add("Changed to Final: #" . $id);
	} else {
		Tr_Session_Class::add("Changed to Final: #" . $id);
	}
	if (isset($_REQUEST['trajax'])) {
		echo '<div class="ajaxresult" data-id="' . $id . '">';
		echo '<div class="ajaxstatus">' . ($rsupd !== false ? 'ok' : 'fail') . '</div>';
		Tr_Session_Class::show();
		echo '</div>';
		exit;
	}
} else if ($act == 'results_delete' || $act == 'pdelete') {
	$id = isset($_REQUEST['id']) && $_REQUEST['id'] > 0 ? $_REQUEST['id'] : $_REQUEST['idd'];

	if ($id > 0) {
		$report = tr_pdd_get_report_by_id($id, 'pcr');
		$mid = $report['mid'];

		if ($mid > 0) {

			$wpdb->update($table_batch, array('is_saved' => 2, 'result_pdf' => ''), array('mid' => $mid));
			trpdd_log_user(get_current_user_id(), 'delete_rs', '', $mid, 'pcr', '', '', $report['patient_id']);
			trpdd_log_user(get_current_user_id(), 'delete_req', '', $mid, 'pcr', '', '', $report['patient_id']);
		}


		$wpdb->delete($table_report, array('id' => $id));
		$wpdb->delete($table_results, array('report_id' => $id));
	}


	Tr_Session_Class::add("Deteled Req & Result #" . $id);
	if (isset($_REQUEST['trajax'])) {
		echo '<div class="ajaxresult">';
		echo '<div class="ajaxstatus">ok</div>';
		Tr_Session_Class::show();
		echo '</div>';
		exit;
	}
} else if ($act === 'delete_selected') {
	$ids = $_REQUEST['ids'];
	$ids = explode(',', $ids);
	$return = array('status' => 'ok', 'ids' => array());
	foreach ($ids as $id) {
		if (empty($id)) continue;
		$rs = $wpdb->get_row("select ba.mid, ba.assen from {$table_batch} ba
		                join {$table_report} re ON re.patient_id = ba.assen
		                where re.id = {$id} ");
		if (!$rs) {
			continue;
		}

		$wpdb->update($table_batch, array('is_saved' => 2, 'result_pdf' => ''), array('mid' => $rs->mid));

		$wpdb->delete($table_report, array('id' => $id));
		$wpdb->delete($table_results, array('report_id' => $id));

		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $rs->mid, 'pcr', '', '', $rs->assen);

		$return['ids'][] = $id;
	}
	wp_send_json($return);
} else if ($act == 'change_final_results') {
	$id = $_REQUEST['id'];
	$val = $_REQUEST['val'];
	$return = array('status' => 'ok');

	$wpdb->update('ci_reports_pcr', array('final_results' => $val), array('id' => $id));
	wp_send_json($return);
} else if ($act == 'view_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'view';
	include_once(TRCPDD_PATH . 'pages/formdata.php');
	include_once(tr_pdd_get_req_file('pcr', isset($_GET['short'])));
	return;
} else if ($act == 'edit_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'edit';
	include_once(TRCPDD_PATH . 'pages/formdata.php');
	include_once(tr_pdd_get_req_file('pcr', isset($_GET['short'])));
	return;
} else if ($act == 'list_plate_qc') {
	include(trpdd_get_file_path('pcr', 'list_plate_qc'));
	exit;
} else if ($act == 'plate_qc_chart') {
	include(trpdd_get_file_path('pcr', 'plate_qc_chart'));
	exit;
} else if (isset($_POST['trajax']) && $_POST['trajax'] === 'add_antibiotic_pcr') {
	$ta_name = sanitize_text_field($_POST['ta_name']);
	$group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
	$ta_groups = $group_id > 0 ? (string) $group_id : '';
	if (empty($ta_name)) {
		ob_clean();
		wp_send_json(array('status' => 'error', 'message' => 'Name is required'));
		exit;
	}
	$existing_id = $wpdb->get_var($wpdb->prepare(
		"select ID from ci_test_antibiotics where ta_name = %s and ta_groups = %s limit 1",
		$ta_name,
		$ta_groups
	));

	if ($existing_id) {
		$ID = $existing_id;
	} else {
		$wpdb->insert('ci_test_antibiotics', array(
			'ta_name'    => $ta_name,
			'ta_groups'  => $ta_groups,
			'iorder'     => 0,
			'created'    => date_i18n('Y-m-d H:i:s'),
			'created_by' => get_current_user_id(),
		));
		$ID = $wpdb->insert_id;
	}

	$ta_abrev = $wpdb->get_var($wpdb->prepare("select ta_abrev from ci_test_antibiotics where ID = %d", $ID));

	ob_clean();
	wp_send_json(array('status' => 'ok', 'ID' => $ID, 'ta_abrev' => $ta_abrev ?: ''));
	exit;
} else if (isset($_POST['trajax']) && $_POST['trajax'] === 'add_pathogen_pcr') {
	$te_name  = sanitize_text_field($_REQUEST['te_name']);
	$group_id = isset($_REQUEST['group_id']) ? intval($_REQUEST['group_id']) : 0;

	$groups_pathogen = array();
	if (isset($_REQUEST['te_groups_pathogen']) && is_array($_REQUEST['te_groups_pathogen'])) {
		$groups_pathogen = array_map('intval', $_REQUEST['te_groups_pathogen']);
		$groups_pathogen = array_filter($groups_pathogen);
	}

	if (empty($te_name)) {
		ob_clean();
		wp_send_json(array('status' => 'error', 'message' => 'Name is required'));
		exit;
	}

	$existing_id = $wpdb->get_var($wpdb->prepare(
		"select te_ID from ci_test_panels where te_type = 'pats' and te_name = %s limit 1",
		$te_name
	));

	if ($existing_id) {
		$te_ID = $existing_id;
		$current_group = $wpdb->get_var($wpdb->prepare("select te_group_id from ci_test_panels where te_ID = %d", $te_ID));
		$update_data = array();
		if (empty($current_group) && $group_id > 0) {
			$update_data['te_group_id'] = $group_id;
		}
		if (!empty($groups_pathogen)) {
			$update_data['te_groups_pathogen'] = implode(',', $groups_pathogen);
		}
		if (!empty($update_data)) {
			$wpdb->update('ci_test_panels', $update_data, array('te_ID' => $te_ID));
		}
	} else {
		$wpdb->insert('ci_test_panels', array(
			'te_type'             => 'pats',
			'te_name'             => $te_name,
			'te_group_id'         => $group_id,
			'te_groups_pathogen'  => implode(',', $groups_pathogen),
		));
		$te_ID = $wpdb->insert_id;
	}

	ob_clean();
	wp_send_json(array('status' => 'ok', 'te_ID' => $te_ID));
	exit;
}
$disable_upload_results = tr_get_option('ci_pdf_data', 'disable_upload_results');

if (empty($tabs)) {
	$tabs = array(
		'list'        => 'Results',
		'pending'     => 'PCR Pending',
		'upload'      => 'Upload PDFs',
		'generator'   => 'Report Generator',
		'incomplete'  => 'Incomplete Orders',
		'batch_stats' => 'Batch Stats',
		'ct_stats'    => 'CT Stats',
		'data_qc'     => 'Data QC',
		'qc'		  => 'QC',
		'levey_jennings_qc' => 'Levey–Jennings QC',
	);
	if ($disable_upload_results) {
		unset($tabs['upload']);
		unset($tabs['generator']);
	}


	if (!tr_get_option('ci_result_settings_pcr', 'e_leyvey')) {
		unset($tabs['levey_jennings_qc']);
	}
	if (!tr_get_option('ci_result_settings_pcr', 'e_qc')) {
		unset($tabs['qc']);
	}

	if (in_array($current_role, array('ref_lab_tech', 'reflab_manager'))) {
		unset($tabs['data_qc']);
		unset($tabs['levey_jennings_qc']);
		unset($tabs['ct_stats']);
	} else if ($current_role == 'Billing') {
		unset($tabs['generator']);
		unset($tabs['upload']);
	} else if (in_array($current_role, array(
		'administrator',
		'administrator_p',
		'AdminCIDW',
		'lab_director',
		'labmanager'
	))) {
		$tabs['efax_setup'] = 'eFax Setup';
	} else if ($current_role == 'results') {
		$tabs = array('list' => 'Results');
	}

	$tab = ! empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

	if (! isset($_REQUEST['trajax'])) {
?>
		<div class="wrap">
			<h2 class="nav-tab-wrapper">
				<?php

				foreach ($tabs as $k => $txt) {
					$class = $tab == $k ? 'nav-tab-active' : '';
					$link  = Tr_Base_Class_V6::link(array(
						'tab'         => $k,
						'act'         => '',
						'id'          => '',
						'orderby'     => '',
						'order'       => '',
						's'           => '',
						'paged'       => '',
						'month'       => '',
						'testc'       => '',
						'display'     => '',
						'drug'        => '',
						'lab'         => '',
						'm'           => '',
						'ctab'        => '',
						'misstest'    => '',
						'only_insert' => '',
						'showarchive' => '',
						'overwritten' => ''
					), false);
					echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
				}
				?>
			</h2>
		</div>
<?php
	}
}

if ($act == 'edit' || $act == 'view') {
	include(trpdd_get_file_path('pcr', 'results_edit'));
} else if ($tab == 'upload' && !$disable_upload_results) {
	include(trpdd_get_file_path('pcr', 'results_upload'));
} else if ($tab == 'generator'  && !$disable_upload_results) {
	include(trpdd_get_file_path('pcr', 'results_import'));
} else if ($tab == 'levey_jennings_qc') {
	include(trpdd_get_file_path('pcr', 'results_levey_jennings_qc'));
} else if ($tab == 'efax_setup') {
	include(trpdd_get_file_path('settings', 'efax_setup'));
} else if ($tab == 'incomplete') {
	include(trpdd_get_file_path('pcr', 'results_incomplete'));
} else if ($tab == 'batch_stats') {
	include(trpdd_get_file_path('pcr', 'results_batch_stats'));
} else if ($tab == 'data_qc') {
	include(trpdd_get_file_path('pcr', 'results_data_qc'));
} else if ($tab == 'qc') {
	include(trpdd_get_file_path('pcr', 'results_qc'));
} else if ($tab == 'pending') {
	include(trpdd_get_file_path('pcr', 'results_pending'));
} else if ($tab == 'ct_stats') {
	include(trpdd_get_file_path('pcr', 'results_ct_stats'));
} else {
	include(trpdd_get_file_path('pcr', 'results_list'));
}
