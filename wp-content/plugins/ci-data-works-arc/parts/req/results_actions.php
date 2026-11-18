<?php


if ($act == 'load_results') {
	include(trpdd_get_file_path('req', 'load_results'));
	exit;
} else if ($act == 'change_hold') {

	$return = array('status' => 'ok', 'ids' => array());

	$batch_id 	= $_REQUEST['batch_id'];
	$ids 		= trim($_REQUEST['ids'], ",");
	$reason 	= $_REQUEST['reason'];

	$sql = "
        select ba.mid, re.id, ba.batch_status, ba.assen from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where  (re.estatus != 'C' or re.estatus is null)";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	} else {
		$sql .= " and ( ba.batch_id = '{$batch_id}') ";
	}
	$reports = $wpdb->get_results($sql);

	foreach ($reports as $re) {
		$return['ids'][] = $re->id;
		if ($re->batch_status == 'hold') continue;

		$wpdb->update($table_batch, array('batch_status' => 'hold'), array('mid' => $re->mid));

		//trpdd_update_batch_meta($re->mid, $btype,'_reason_hold',$reason);

		trpdd_log_user(get_current_user_id(), 'hold_rs', $reason, $re->mid, 'req', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'remove_hold') {

	$return 	= array('status' => 'ok', 'ids' => array());

	$batch_id 	= $_REQUEST['batch_id'];
	$ids 		= trim($_REQUEST['ids'], ",");

	$sql = "
        select ba.mid,ba.batch_status,ba.assen, re.id 
		from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        left JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where ba.batch_status = 'hold'
         ";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	} else {
		$sql .= " and ( ba.batch_id = '{$batch_id}') ";
	}

	$reports = $wpdb->get_results($sql);

	foreach ($reports as $re) {
		if ($re->batch_status != 'hold') continue;
		$return['ids'][] = $re->id;


		$wpdb->update($table_batch, array('batch_status' => 'received'), array('mid' => $re->mid));


		trpdd_log_user(get_current_user_id(), 'removehold_rs', '', $re->mid, 'req', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'approve_all') {

	$lab_id = $_REQUEST['lab_id'];
	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$sql = "select 
                    ba.*,
                    re.id,
	                re.estatus,
	                re.faxed,
	                re.upload_files,
	                re.instrument,
	                re.final_results,
					res.has_pos,
                    cli.`cli_om-fax` as fax
         from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        left JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
        where lab.lab_ID={$lab_id} and (re.batch_id = '{$batch_id}' or ba.batch_id = '{$batch_id}')";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	}
	$reports = $wpdb->get_results($sql, ARRAY_A);

	$now = date_i18n('Y-m-d H:i:s');
	$need_fax = false;
	foreach ($reports as $report) {
		if ($report['estatus'] != 'C') {
			$wpdb->update($table_report, array('estatus' => 'C', 'approve_date' => $now), array('id' => $report['id']));

			$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'req');
			if (!isset($rejected_statuses[$report['batch_status']])) {
				$wpdb->update($table_batch, array('batch_status' => 'completed'), array('mid' => $report['mid']));
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
							'created'    => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
							'created_by' => '-1'
						)
					);
				} else {
					$wpdb->update('ci_box_upload', array('status' => '5'), array('id' => $added_fax));
				}

				$need_fax = true;
			}


			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'req', '', '', $report['assen']);

			trpdd_trigger_approve_report($report, 'req');
		}
	}

	trpdd_add_cron_after_approve('req', $need_fax);

	Tr_Session_Class::add("Approved Results");
	if (isset($_REQUEST['trajax'])) {
		$url = Tr_Base_Class_V6::link(array('act' => '', 'trajax' => '', 'lab_id' => '', 'batch_id' => ''), false);
		wp_redirect($url);
		exit;
	}
} else if ($act == 'results_approve') {
	$id = $_REQUEST['id'];
	$report = $wpdb->get_row("select 
                    ba.*,
                    re.id,
	                re.estatus,
	                re.faxed,
	                re.upload_files,
	                re.instrument,
	                re.final_results,
                    cli.`cli_om-fax` as fax
             from {$table_report} re
            JOIN {$table_batch} ba ON ba.assen = re.patient_id
            JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
            left JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
	                where re.id={$id}", ARRAY_A);


	$need_fax = false;
	if ($report['estatus'] != 'C') {
		$status = 'C';
		$now = date_i18n('Y-m-d H:i:s');

		$wpdb->update($table_report, array('estatus' => $status, 'approve_date' => $now), array('id' => $id));

		$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'req');
		if (!isset($rejected_statuses[$report['batch_status']])) {
			$wpdb->update($table_batch, array('batch_status' => 'completed'), array('mid' => $report['mid']));
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
						'uptime'     => time(),
						'sender'     => $report['requesting_practice'],
						'created'    => Tr_Base_Class_V4::date('Y-m-d H:i:s'),
						'created_by' => -1,
					)
				);
			} else {
				$wpdb->update('ci_box_upload', array('status' => '5'), array('id' => $added_fax));
			}
			$need_fax = true;
		}

		trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'req', '', '', $report['assen']);

		trpdd_trigger_approve_report($report, 'req');

		Tr_Session_Class::add("Approved Result #" . $id);
	} else {
		Tr_Session_Class::add("Approved Result #" . $id);
	}

	trpdd_add_cron_after_approve('req', $need_fax);

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
	$report = $wpdb->get_row("select re.*, ba.mid, ba.batch_status
	                from {$table_report} re
	                join {$table_batch} ba ON ba.assen = re.patient_id
	                where re.id={$id}", ARRAY_A);


	if ($report['estatus'] == 'C') {

		$wpdb->update($table_report, array('estatus' => 'M'), array('id' => $id));

		if ($report['batch_status'] == 'completed') {
			$wpdb->update($table_batch, array('batch_status' => 'resulted'), array('mid' => $report['mid']));
		}

		trpdd_log_user(get_current_user_id(), 'reverse_rs', '', $report['mid'], 'req', '', '', $report['patient_id']);

		Tr_Session_Class::add("Reversed Result #" . $id);
	} else {
		Tr_Session_Class::add("Reversed Result #" . $id);
	}
	if (isset($_REQUEST['trajax'])) {
		Tr_Session_Class::show();
		echo 'ok';
		exit;
	}
} else if ($act == 'results_delete' || $act == 'pdelete') {
	$id = $_REQUEST['id'] > 0 ? $_REQUEST['id'] : $_REQUEST['idd'];

	$report = tr_pdd_get_report_by_id($id, 'req');
	$mid = $report['mid'];

	if ($mid > 0) {
		$wpdb->update($table_batch, array('result_pdf' => ''), array('mid' => $mid));
		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $mid, 'req', '', '', $report['patient_id']);
	}


	$wpdb->delete($table_report, array('id' => $id));
	$wpdb->delete($table_results, array('report_id' => $id));

	Tr_Session_Class::add("Deteled Result #" . $id);
	if (isset($_REQUEST['trajax'])) {
		Tr_Session_Class::show();
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

		$wpdb->delete($table_report, array('id' => $id));
		$wpdb->delete($table_results, array('report_id' => $id));

		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $rs->mid, 'req', '', '', $rs->assen);

		$return['ids'][] = $id;
	}
	wp_send_json($return);
} else if ($act == 'view_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'view';
	include_once(tr_pdd_get_req_file('req', isset($_GET['short'])));
	return true;
} else if ($act == 'edit_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'edit';
	include_once(TRCPDD_PATH . 'pages/formdata.php');
	include_once(tr_pdd_get_req_file('req', isset($_GET['short'])));
	return true;
}
