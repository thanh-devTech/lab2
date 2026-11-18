<?php
global $wpdb, $current_role, $blog_id;
$user_id        = get_current_user_id();
$btype          = 'blood';
$pending        = isset($_REQUEST['pending']);
$user_id        = get_current_user_id();
$auto_fax       = tr_get_option('ci_pdf_data', 'auto_fax_approve_blood');

$table_batch = 'ci_batches_blood';
$table_report = 'ci_reports_blood';
$table_results = 'ci_results_blood';

$created_date = date_i18n('Y-m-d');
$created = date_i18n("Y-m-d H:i:s");

$log_dir = WP_CONTENT_DIR . '/log/results_blood/';
if (!is_dir($log_dir)) {
	wp_mkdir_p($log_dir);
}




wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


if ($act == 'change_hold') {

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
		trpdd_update_batch_meta($re->mid, $btype, 'hold_batch_status', $re->batch_status);
		//trpdd_update_batch_meta($re->mid, $btype,'_reason_hold',$reason);

		trpdd_log_user(get_current_user_id(), 'hold_rs', $reason, $re->mid, 'blood', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'remove_hold') {

	$return 	= array('status' => 'ok', 'ids' => array());

	$batch_id 	= $_REQUEST['batch_id'];
	$ids 		= trim($_REQUEST['ids'], ",");

	$sql = "
        select ba.mid,ba.batch_status, re.id, ba.assen
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

		$hold_status = $wpdb->get_var("select meta_value from {$table_batch}_meta where mid={$re->$mid} and meta_key = 'hold_batch_status'") ?: '';
		$wpdb->update($table_batch, array('batch_status' => $hold_status), array('mid' => $re->mid));
		$wpdb->delete($table_batch . '_meta', ['mid' => $re->$mid, 'meta_key' => 'hold_batch_status']);

		trpdd_log_user(get_current_user_id(), 'removehold_rs', '', $re->mid, 'blood', '', '', $re->assen);
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

			$updaterp = array('estatus' => $status, 'approve_date' => $now);
			if ($report['final_results'] == 0) {
				$report['final_results'] = 2;
				$updaterp['final_results'] = 2;
			}


			$wpdb->update($table_report, $updaterp, array('id' => $report['id']));

			$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'blood');
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


			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'blood', '', '', $report['assen']);

			trpdd_trigger_approve_report($report, 'blood');
		}
	}

	trpdd_add_cron_after_approve('blood', $need_fax);

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

		$updaterp = array('estatus' => $status, 'approve_date' => $now);
		if ($report['final_results'] == 0) {
			$report['final_results'] = 2;
			$updaterp['final_results'] = 2;
		}

		$wpdb->update($table_report, $updaterp, array('id' => $id));

		$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'blood');
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

		trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'blood', '', '', $report['assen']);

		trpdd_trigger_approve_report($report, 'blood');

		Tr_Session_Class::add("Approved Result #" . $id);
	} else {
		Tr_Session_Class::add("Approved Result #" . $id);
	}

	trpdd_add_cron_after_approve('blood', $need_fax);

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

		trpdd_log_user(get_current_user_id(), 'reverse_rs', '', $report['mid'], 'blood', '', '', $report['patient_id']);

		Tr_Session_Class::add("Reversed Result #" . $id);
	} else {
		Tr_Session_Class::add("Reversed Result #." . $id);
	}
	if (isset($_REQUEST['trajax'])) {

		echo '<div class="ajaxresult" data-id="' . $id . '" data-table="' . $table_report . '">';
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
		trpdd_log_user(get_current_user_id(), 'final_rs', '', $report['mid'], 'blood', '', '', $report['patient_id']);

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
	$id = $_REQUEST['id'] > 0 ? $_REQUEST['id'] : $_REQUEST['idd'];

	$report = tr_pdd_get_report_by_id($id, 'blood');
	$mid = $report['mid'];

	if ($mid > 0) {
		$wpdb->update($table_batch, array('is_saved' => 2, 'result_pdf' => ''), array('mid' => $mid));
		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $mid, 'blood', '', '', $report['patient_id']);
		trpdd_log_user(get_current_user_id(), 'delete_req', '', $mid, 'blood', '', '', $report['patient_id']);
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

		$wpdb->update($table_batch, array('is_saved' => 2, 'result_pdf' => ''), array('mid' => $rs->mid));
		$wpdb->delete($table_report, array('id' => $id));
		$wpdb->delete($table_results, array('report_id' => $id));

		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $rs->mid, 'blood', '', '', $rs->assen);

		$return['ids'][] = $id;
	}
	wp_send_json($return);
} else if ($act == 'view_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'view';
	include_once(tr_pdd_get_req_file('blood', isset($_GET['short'])));
	return;
} else if ($act == 'edit_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'edit';
	include_once(TRCPDD_PATH . 'pages/formdata.php');
	include_once(tr_pdd_get_req_file('blood', isset($_GET['short'])));
	return;
}

$disable_upload_results = tr_get_option('ci_pdf_data', 'disable_upload_results');

if (empty($tabs)) {
	$tabs = array(
		'list' => 'Blood Results',
		'pending' => 'Pending',
		'generator' => 'Report Generator',
		'upload' => 'Upload PDFs',
		'qc' => 'QC',
		'levey_jennings_qc' => 'Levey–Jennings QC',
	);
	if ($disable_upload_results) {
		unset($tabs['upload']);
		unset($tabs['generator']);
	}
	if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'lab_director', 'labmanager'))) {
		$tabs['efax_setup'] = 'eFax Setup';
	} else if ($current_role == 'Billing') {
		unset($tabs['generator']);
		unset($tabs['upload']);
	} else if ($current_role == 'reflab_manager') {
		$tabs = array(
			'list' => 'Blood Results',
		);
	}
	$tab = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
	if (!isset($_REQUEST['trajax'])) {
?>
		<h2 class="nav-tab-wrapper">
			<?php

			foreach ($tabs as $k => $txt) {
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link  = Tr_Base_Class_V6::link(array(
					'tab'   => $k,
					'act'   => '',
					'panelid'   => '',
					'id'    => '',
					'mid'   => '',
					'paged' => '',
					's'     => ''
				), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
			}
			?>
		</h2>

<?php
	}
}


if ($act == 'view' || $act == 'edit') {
	include(trpdd_get_file_path('blood', 'results_edit'));
} else if ($tab == 'generator' && !$disable_upload_results) {
	include(trpdd_get_file_path('blood', 'results_generator'));
} else if ($tab == 'pending') {
	include(trpdd_get_file_path('blood', 'results_pending'));
} else if ($tab == 'upload' && !$disable_upload_results) {
	include(trpdd_get_file_path('blood', 'results_upload'));
} else if ($tab == 'levey_jennings_qc') {
	include(trpdd_get_file_path('blood', 'results_levey_jennings_qc'));
} else if ($tab == 'qc') {
	include(trpdd_get_file_path('blood', 'results_qc'));
} else if ($tab == 'efax_setup') {
	include(trpdd_get_file_path('settings', 'efax_setup'));
} else {
	include(trpdd_get_file_path('blood', 'results_list'));
}
