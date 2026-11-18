<?php
global $wpdb, $medications, $report_id, $cutoffs, $drug_pairs, $report_these_drugs;
global $current_role;

$use_upload = true;
$btype = 'study';
$user_id = get_current_user_id();
$auto_fax = tr_get_option('ci_pdf_data', 'auto_fax_approve_study');

$table_batch = 'ci_batches_study';
$table_report = 'ci_reports_study';
$table_results = 'ci_results_study';

$created_date = date_i18n('Y-m-d');
$created = date_i18n("Y-m-d H:i:s");

$log_dir = WP_CONTENT_DIR . '/log/results_study/';
if (!is_dir($log_dir)) {
	wp_mkdir_p($log_dir);
}

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


if ($act == 'export_csv') {
	include('export_csv.php');
	exit;
} else if ($act == 'load_results_study') {
	include('results_load.php');
	exit;
} else if ($act == 'change_hold') {

	$return = array('status' => 'ok', 'ids' => array());

	$lab_id = $_REQUEST['lab_id'];
	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$sql = "
        select ba.mid, re.id, ba.batch_status, ba.assen from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where lab.lab_ID={$lab_id} and (re.batch_id = '{$batch_id}' or ba.batch_id = '{$batch_id}')
        and (re.estatus != 'C' or re.estatus is null) ";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	}
	$reports = $wpdb->get_results($sql);

	$now = date_i18n('Y-m-d H:i:s');
	foreach ($reports as $re) {
		$return['ids'][] = $re->id;
		if ($re->batch_status == 'hold') continue;

		$wpdb->update($table_batch, array('batch_status' => 'hold'), array('mid' => $re->mid));
		trpdd_log_user(get_current_user_id(), 'hold_rs', '', $re->mid, 'study', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'remove_hold') {

	$return = array('status' => 'ok', 'ids' => array());

	$lab_id = $_REQUEST['lab_id'];
	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$sql = "
        select ba.mid,ba.batch_status,ba.assen, re.id from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where lab.lab_ID={$lab_id} and (re.batch_id = '{$batch_id}' or ba.batch_id = '{$batch_id}')
         ";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	}
	$reports = $wpdb->get_results($sql);

	$now = date_i18n('Y-m-d H:i:s');
	foreach ($reports as $re) {
		$return['ids'][] = $re->id;

		if ($re->batch_status != 'hold') continue;

		$wpdb->update($table_batch, array('batch_status' => 'received'), array('mid' => $re->mid));
		trpdd_log_user(get_current_user_id(), 'removehold_rs', '', $re->mid, 'study', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'final_selected') {
	$return = array('status' => 'ok');

	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$sql = "select re.id
        from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where re.id in ({$ids})
        ";

	$reports = $wpdb->get_results($sql, ARRAY_A);

	$now = date_i18n('Y-m-d H:i:s');
	foreach ($reports as $report) {
		$wpdb->update('ci_reports_study', array('final_results' => '1'), array('id' => $report['id']));
	}
	wp_send_json($return);
} else if ($act == 'approve_selected') {
	$return = array('status' => 'ok', 'fails' => array(), 'msg' => 'Approved Successfully!', 'adding_html' => array());
	$need_fax = false;

	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$where = "re.id in ({$ids})";
	if (empty($ids) && !empty($batch_id)) {
		$where = " ba.batch_id = '{$batch_id}' ";
	}
	$sql = "select 
	                ba.*,
                    re.id,
	                re.estatus,
	                re.faxed,
	                re.upload_files,
	                re.final_results
            from {$table_report} re
            JOIN {$table_batch} ba ON ba.assen = re.patient_id
            where {$where}
            ";


	$reports = $wpdb->get_results($sql, ARRAY_A);

	$now = date_i18n('Y-m-d H:i:s');
	$count_approved = 0;
	foreach ($reports as $report) {
		if ($report['estatus'] != 'C') {
			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $now,
				'faxed' => 0,
			);
			if ($has_covid && $has_more_panel) {
				$update_report['final_results'] = 2;
			} else if ($has_covid) {
				$update_report['final_results'] = 1;
			}

			$wpdb->update($table_report, $update_report, array('id' => $report['id']));
			$count_approved++;


			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'study', '', '', $report['assen']);
		}
	}

	if ($count_approved == 0) {
		$return['msg'] = 'Not found. Maybe approved before!';
	}

	if ($need_fax) {
		wp_clear_scheduled_hook('ci_check_upload_apis', array(1, 'single'));
		wp_schedule_single_event(time(), 'ci_check_upload_apis', array(1, 'single'));
	}
	wp_send_json($return);
} else if ($act == 'approve_all') {

	$return = array('status' => 'ok', 'msg' => '', 'adding_html' => array());
	$need_fax = false;

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
	                re.final_results
        from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        where ba.batch_id = '{$batch_id}' ";
	if ($lab_id > 0) {
		$sql .= " and ba.lab_id = {$lab_id} ";
	}
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	}
	$reports = $wpdb->get_results($sql, ARRAY_A);

	$now = date_i18n('Y-m-d H:i:s');
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

			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $now,
				'faxed' => 0,
			);
			if ($has_covid && $has_more_panel) {
				$update_report['final_results'] = 2;
			} else if ($has_covid) {
				$update_report['final_results'] = 1;
			}
			$wpdb->update($table_report, $update_report, array('id' => $report['id']));

			$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'study');
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
							'created'    => Tr_Base_Class_V6::date('Y-m-d H:i:s')
						)
					);
				} else {
					$wpdb->update('ci_box_upload', array('status' => '5'), array('id' => $added_fax));
				}

				$need_fax = true;
				$return['adding_html'][$report['id']]['mstatus'] = 'Faxing...';
			}

			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'study', '', '', $report['assen']);
		}
	}

	if ($need_fax) {
		wp_clear_scheduled_hook('ci_check_upload_apis', array(1, 'single'));
		wp_schedule_single_event(time(), 'ci_check_upload_apis', array(1, 'single'));
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
	                re.final_results
                from {$table_report} re
                join {$table_batch} ba ON ba.assen = re.patient_id
                where re.id={$id}", ARRAY_A);

	if ($report['estatus'] != 'C') {
		$status = 'C';
		$now = date_i18n('Y-m-d H:i:s');

		$has_covid = stripos($report['panel_tests_requested'], $covid_group_key) !== false;
		$has_more_panel = substr_count($report['panel_tests_requested'], 'GROUP_') >= 2;

		if ($current_role == 'results' && $report['final_results'] != 1) {
			Tr_Session_Class::add_error('Cant approve not Final');
		} else {
			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $now,
				'faxed' => 0,
			);
			if ($has_covid && $has_more_panel) {
				$update_report['final_results'] = 2;
			} else if ($has_covid) {
				$update_report['final_results'] = 1;
			}
			$wpdb->update($table_report, $update_report, array('id' => $report['id']));

			$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'study');

			if (!isset($rejected_statuses[$report['batch_status']])) {
				$wpdb->update($table_batch, array('batch_status' => 'completed'), array('mid' => $report['mid']));
			}


			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'study', '', '', $report['assen']);

			Tr_Session_Class::add("Approved Result #" . $id);
		}
	} else {
		Tr_Session_Class::add("Approved Result #" . $id);
	}

	if ($need_fax) {
		wp_clear_scheduled_hook('ci_check_upload_apis', array(1, 'single'));
		wp_schedule_single_event(time(), 'ci_check_upload_apis', array(1, 'single'));
	}

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
			$wpdb->update($table_batch, array('batch_status' => ''), array('mid' => $report['mid']));
		}

		//update api
		$wpdb->update('ci_sync_api_orders', array('mo_status' => '0'), array('mo_mid' => $report['mid']));

		trpdd_log_user(get_current_user_id(), 'reverse_rs', '', $report['mid'], 'study', '', '', $report['patient_id']);

		Tr_Session_Class::add("Reversed Result #" . $id);
	} else {
		Tr_Session_Class::add("Reversed Result #" . $id);
	}
	if (isset($_REQUEST['trajax'])) {
		echo '<div class="ajaxresult">';
		echo '<div class="ajaxstatus">ok</div>';
		Tr_Session_Class::show();
		echo '</div>';
		exit;
	}
} else if ($act == 'results_delete' || $act == 'pdelete') {
	$id = $_REQUEST['id'] > 0 ? $_REQUEST['id'] : $_REQUEST['idd'];

	$report = tr_pdd_get_report_by_id($id, 'study');
	$mid = $report['mid'];

	if ($mid > 0) {
		$wpdb->update($table_batch, array('result_pdf' => ''), array('mid' => $mid));
		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $mid, 'study', '', '', $report['patient_id']);
	}


	$wpdb->delete($table_report, array('id' => $id));
	$wpdb->delete($table_results, array('report_id' => $id));

	Tr_Session_Class::add("Deteled Result #" . $id);
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

		$wpdb->delete($table_report, array('id' => $id));
		$wpdb->delete($table_results, array('report_id' => $id));

		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $rs->mid, 'study', '', '', $rs->assen);

		$return['ids'][] = $id;
	}
	wp_send_json($return);
} else if ($act == 'change_final_results') {
	$id = $_REQUEST['id'];
	$val = $_REQUEST['val'];
	$return = array('status' => 'ok');

	$wpdb->update('ci_reports_study', array('final_results' => $val), array('id' => $id));
	wp_send_json($return);
}

$disable_upload_results = tr_get_option('ci_pdf_data', 'disable_upload_results');

if (empty($tabs)) {
	$tabs = array(
		'list'      => 'Results',
		'pending'      => 'Pending',
		'template'      => 'Result Template',
		'generator' => 'Report Generator',
	);
	if ($disable_upload_results) {
		unset($tabs['generator']);
	}


	if ($current_role == 'results') {
		$tabs = array('list' => 'Results');
	}

	if (!current_user_can('ci_results_study_tmp')) {
		unset($tabs['template']);
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
	include('results_edit.php');
} else if ($tab == 'generator' && !$disable_upload_results) {
	include('results_import.php');
} else if ($tab == 'template') {
	include('results_template.php');
} else if ($tab == 'pending') {
	include('results_pending.php');
} else {
	include('results_list.php');
}
