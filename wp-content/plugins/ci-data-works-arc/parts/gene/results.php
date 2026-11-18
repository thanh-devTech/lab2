<?php
global $wpdb, $medications, $report_id, $cutoffs, $drug_pairs, $report_these_drugs;
global $current_role;

$use_upload         = true;
$btype              = 'gene';
$user_id            = get_current_user_id();
$auto_fax           = tr_get_option('ci_pdf_data', 'auto_fax_approve_gene');

$table_batch        = 'ci_batches_gene';
$table_report       = 'ci_reports_gene';
$table_results      = 'ci_results_gene';

$created_date       = date_i18n('Y-m-d');
$created            = date_i18n("Y-m-d H:i:s");

$log_dir            = WP_CONTENT_DIR . '/log/results_gene/';
if (!is_dir($log_dir)) {
	wp_mkdir_p($log_dir);
}

add_thickbox();

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);




if ($act == 'change_hold') {

	$return = array('status' => 'ok', 'ids' => array());

	$lab_id = $_REQUEST['lab_id'];
	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");


	$sql = "
        select ba.mid, re.id, ba.batch_status, ba.assen from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where lab.lab_ID={$lab_id} and ( ba.batch_id = '{$batch_id}')
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
		trpdd_update_batch_meta($re->mid, $btype, 'hold_batch_status', $re->batch_status);
		trpdd_log_user(get_current_user_id(), 'hold_rs', '', $re->mid, 'gene', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'remove_hold') {

	$return = array('status' => 'ok', 'ids' => array());

	$lab_id = $_REQUEST['lab_id'];
	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$sql = "
        select ba.mid,ba.batch_status, re.id, ba.assen from {$table_report} re
        JOIN {$table_batch} ba ON ba.assen = re.patient_id
        JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
        where lab.lab_ID={$lab_id} and ba.batch_id = '{$batch_id}'
         ";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	}

	$reports = $wpdb->get_results($sql);

	$now = date_i18n('Y-m-d H:i:s');
	foreach ($reports as $re) {
		$return['ids'][] = $re->id;

		if ($re->batch_status != 'hold') continue;

		$hold_status = $wpdb->get_var("select meta_value from {$table_batch}_meta where mid={$re->$mid} and meta_key = 'hold_batch_status'") ?: '';
		$wpdb->update($table_batch, array('batch_status' => $hold_status), array('mid' => $re->mid));
		$wpdb->delete($table_batch . '_meta', ['mid' => $re->$mid, 'meta_key' => 'hold_batch_status']);
		trpdd_log_user(get_current_user_id(), 'removehold_rs', '', $re->mid, 'gene', '', '', $re->assen);
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
		$wpdb->update('ci_reports_gene', array('final_results' => '1'), array('id' => $report['id']));
	}
	wp_send_json($return);
} else if ($act == 'approve_selected') {
	$return = array('status' => 'ok', 'fails' => array(), 'msg' => 'Approved Successfully!', 'adding_html' => array());
	$need_fax = false;

	$batch_id = $_REQUEST['batch_id'];

	$ids = trim($_REQUEST['ids'], ",");
	$where = " re.id in ({$ids}) ";
	if (empty($ids) && !empty($batch_id)) {
		$where = " ba.batch_id = '{$batch_id}' ";
	}

	$lab_id = $_REQUEST['lab_id'];
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
                    cli.`cli_om-fax` as fax
            from {$table_report} re
            JOIN {$table_batch} ba ON ba.assen = re.patient_id
            JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
            left JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
            where {$where}
            ";


	$reports = $wpdb->get_results($sql, ARRAY_A);

	$now = date_i18n('Y-m-d H:i:s');
	$count_approved = 0;
	foreach ($reports as $report) {
		if ($report['estatus'] != 'C') {

			$has_more_panel = substr_count($report['panel_tests_requested'], 'GROUP_') >= 2;


			if ($current_role == 'results' && $report['final_results'] != 1) {
				$return['fails'][] = $report['id'];
				continue;
			}

			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $now,
				'faxed' => 0,
			);


			$wpdb->update($table_report, $update_report, array('id' => $report['id']));
			$count_approved++;

			$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'gene');
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


			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'gene', '', '', $report['assen']);
		}
	}

	if ($count_approved == 0) {
		$return['msg'] = 'Not found. Maybe approved before!';
	} else {
		trpdd_add_cron_after_approve('gene', $need_fax);
	}


	wp_send_json($return);
} else if ($act == 'approve_all') {

	$return = array('status' => 'ok', 'msg' => '', 'adding_html' => array());
	$need_fax = false;
	$count_approved = 0;
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
        left JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
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

			$has_more_panel = substr_count($report['panel_tests_requested'], 'GROUP_') >= 2;


			if ($current_role == 'results' && $report['final_results'] != 1) {
				continue;
			}

			$count_approved++;
			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $now,
				'faxed' => 0,
			);

			$wpdb->update($table_report, $update_report, array('id' => $report['id']));

			$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'gene');
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

			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'gene', '', '', $report['assen']);
		}
	}

	if ($count_approved > 0) {
		trpdd_add_cron_after_approve('gene', $need_fax);
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
                    cli.`cli_om-fax` as fax
                from {$table_report} re
                join {$table_batch} ba ON ba.assen = re.patient_id
                left JOIN ci_clinic cli ON ba.cli_ID = cli.cID and cli.cli_faxres = '1'
                where re.id={$id}", ARRAY_A);

	if ($report['estatus'] != 'C') {
		$status = 'C';
		$now = date_i18n('Y-m-d H:i:s');


		$has_more_panel = substr_count($report['panel_tests_requested'], 'GROUP_') >= 2;

		if ($current_role == 'results' && $report['final_results'] != 1) {
			Tr_Session_Class::add_error('Cant approve not Final');
		} else {
			$update_report = array(
				'estatus' => 'C',
				'approve_date' => $now,
				'faxed' => 0,
			);

			$wpdb->update($table_report, $update_report, array('id' => $report['id']));

			$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'gene');

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


			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'gene', '', '', $report['assen']);

			Tr_Session_Class::add("Approved Result #" . $id);
		}
	} else {
		Tr_Session_Class::add("Approved Result #" . $id);
	}

	trpdd_add_cron_after_approve('gene', $need_fax);

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

		//update api
		$wpdb->update('ci_sync_api_orders', array('mo_status' => '0'), array('mo_mid' => $report['mid']));

		trpdd_log_user(get_current_user_id(), 'reverse_rs', '', $report['mid'], 'gene', '', '', $report['patient_id']);

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

	$report = tr_pdd_get_report_by_id($id, 'gene');
	$mid = $report['mid'];

	if ($mid > 0) {
		$wpdb->update($table_batch, array('is_saved' => 2, 'result_pdf' => ''), array('mid' => $mid));
		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $mid, 'gene', '', '', $report['patient_id']);
		trpdd_log_user(get_current_user_id(), 'delete_req', '', $mid, 'gene', '', '', $report['patient_id']);
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

		$wpdb->update($table_batch, array('is_saved' => 2, 'result_pdf' => ''), array('mid' => $rs->mid));
		$wpdb->delete($table_report, array('id' => $id));
		$wpdb->delete($table_results, array('report_id' => $id));

		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $rs->mid, 'gene', '', '', $rs->assen);

		$return['ids'][] = $id;
	}
	wp_send_json($return);
} else if ($act == 'change_final_results') {
	$id = $_REQUEST['id'];
	$val = $_REQUEST['val'];
	$return = array('status' => 'ok');

	$wpdb->update('ci_reports_gene', array('final_results' => $val), array('id' => $id));
	wp_send_json($return);
} else if ($act == 'view_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'view';
	include_once(TRCPDD_PATH . 'pages/formdata.php');
	include_once(tr_pdd_get_req_file('gene', isset($_GET['short'])));
	return;
} else if ($act == 'edit_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'edit';
	include_once(TRCPDD_PATH . 'pages/formdata.php');
	include_once(tr_pdd_get_req_file('gene', isset($_GET['short'])));
	return;
}

$disable_upload_results = tr_get_option('ci_pdf_data', 'disable_upload_results');

if (empty($tabs)) {
	$tabs = array(
		'list'        => 'Results',
		'pending'     => 'Pending',
		'upload'      => 'Upload Results',
		'generator'   => 'Report Generator',
		'incomplete'  => 'Incomplete Orders',
	);
	if ($disable_upload_results) {
		unset($tabs['upload']);
		unset($tabs['generator']);
	}

	if ($current_role == 'Billing') {
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
	include(trpdd_get_file_path('gene', 'results_edit'));
} else if ($tab == 'upload' && !$disable_upload_results) {
	include(trpdd_get_file_path('gene', 'results_upload'));
} else if ($tab == 'generator' && !$disable_upload_results) {
	include(trpdd_get_file_path('gene', 'results_import'));
} else if ($tab == 'levey_jennings_qc') {
	include(trpdd_get_file_path('gene', 'results_qc'));
} else if ($tab == 'efax_setup') {
	include(trpdd_get_file_path('settings', 'efax_setup'));
} else if ($tab == 'incomplete') {
	include(trpdd_get_file_path('gene', 'results_incomplete'));
} else if ($tab == 'batch_stats') {
	include(trpdd_get_file_path('gene', 'results_batch_stats'));
} else if ($tab == 'data_qc') {
	include(trpdd_get_file_path('gene', 'results_data_qc'));
} else if ($tab == 'pending') {
	include(trpdd_get_file_path('gene', 'results_pending'));
} else if ($tab == 'ct_stats') {
	include(trpdd_get_file_path('gene', 'results_ct_stats'));
} else {
	include(trpdd_get_file_path('gene', 'results_list'));
}
