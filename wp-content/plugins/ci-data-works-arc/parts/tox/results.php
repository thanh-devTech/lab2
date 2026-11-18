<?php
global $wpdb, $medications, $report_id, $cutoffs, $drug_pairs, $report_these_drugs;
global $current_role;

$user_id        = get_current_user_id();

$created_date   = date_i18n('Y-m-d');
$created        = date_i18n("Y-m-d H:i:s");

$log_dir        = WP_CONTENT_DIR . '/log/results/';
if (!is_dir($log_dir)) {
	wp_mkdir_p($log_dir);
}

$table_batch    = 'ci_batches';
$table_report   = 'ci_reports';
$table_results  = 'ci_results';


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

	$now = date_i18n('Y-m-d H:i:s');
	foreach ($reports as $re) {
		$return['ids'][] = $re->id;
		if ($re->batch_status == 'hold') continue;

		$wpdb->update($table_batch, array('batch_status' => 'hold'), array('mid' => $re->mid));
		trpdd_update_batch_meta($re->mid, $btype, 'hold_batch_status', $re->batch_status);
		//trpdd_update_batch_meta($re->mid, $btype,'_reason_hold',$reason);

		trpdd_log_user(get_current_user_id(), 'hold_rs', $reason, $re->mid, 'tox', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'remove_hold') {

	$return = array('status' => 'ok', 'ids' => array());

	$lab_id 	= $_REQUEST['lab_id'];
	$batch_id 	= $_REQUEST['batch_id'];
	$ids 		= trim($_REQUEST['ids'], ",");
	$sql = "
        select ba.mid,ba.batch_status,ba.assen, re.id from {$table_report} re
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

	$now = date_i18n('Y-m-d H:i:s');
	foreach ($reports as $re) {
		$return['ids'][] = $re->id;

		if ($re->batch_status != 'hold') continue;

		$hold_status = $wpdb->get_var("select meta_value from {$table_batch}_meta where mid={$re->$mid} and meta_key = 'hold_batch_status'") ?: '';
		$wpdb->update($table_batch, array('batch_status' => $hold_status), array('mid' => $re->mid));
		$wpdb->delete($table_batch . '_meta', ['mid' => $re->$mid, 'meta_key' => 'hold_batch_status']);

		trpdd_log_user(get_current_user_id(), 'removehold_rs', '', $re->mid, 'tox', '', '', $re->assen);
	}

	wp_send_json($return);
} else if ($act == 'approve_all') {

	$lab_id = $_REQUEST['lab_id'];
	$batch_id = $_REQUEST['batch_id'];
	$ids = trim($_REQUEST['ids'], ",");
	$sql = "
        select 
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
        where lab.lab_ID={$lab_id} and ba.batch_id = '{$batch_id}' ";
	if (!empty($ids)) {
		$sql .= " and re.id in ({$ids})";
	}
	$reports = $wpdb->get_results($sql, ARRAY_A);

	$now = date_i18n('Y-m-d H:i:s');
	foreach ($reports as $report) {
		$id = $report['id'];
		if ($report['estatus'] != 'C') {
			$wpdb->update($table_report, array(
				'estatus' => 'C',
				'approve_date' => $now,
				'faxed' => 0,
				'modified' => time()
			), array('id' => $id));

			trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'tox', '', '', $report['assen']);

			trpdd_trigger_approve_report($report, 'tox');
		}
	}
	Tr_Session_Class::add("Approved Results");
	if (isset($_REQUEST['trajax'])) {
		$url = Tr_Base_Class_V6::link(array('act' => '', 'trajax' => '', 'lab_id' => '', 'batch_id' => ''), false);
		wp_redirect($url);
		exit;
	}
} else if ($act == 'results_approve') {
	$id = $_REQUEST['id'];
	$report = $wpdb->get_row("select re.*, ba.mid,ba.batch_status, ba.assen
	                from {$table_report} re
	                join {$table_batch} ba ON ba.assen = re.patient_id
	                where re.id={$id}", ARRAY_A);
	if ($report['estatus'] != 'C') {
		$status = 'C';
		$now = date_i18n('Y-m-d H:i:s');

		$wpdb->update($table_report, array('estatus' => $status, 'approve_date' => $now, 'faxed' => 0, 'modified' => time()), array('id' => $id));

		$rejected_statuses = tr_pdd_get_batch_statuses(false, true, true, 'tox');
		if (!isset($rejected_statuses[$report['batch_status']])) {
			$wpdb->update($table_batch, array('batch_status' => 'completed'), array('mid' => $report['mid']));
		}



		trpdd_log_user(get_current_user_id(), 'approve_rs', $report['assen'], $report['mid'], 'tox', '', '', $report['assen']);

		trpdd_trigger_approve_report($report, 'tox');

		Tr_Session_Class::add("Approved Result #" . $id);


		trpdd_add_cron_after_approve('tox');
	}
	if (isset($_REQUEST['trajax'])) {
		Tr_Session_Class::show();
		echo 'ok';
		exit;
	}
} else if ($act == 'results_reverse') {
	$id = $_REQUEST['id'];
	$report = $wpdb->get_row("select re.*, ba.mid, batch_status
	                from {$table_report} re
	                join {$table_batch} ba ON ba.assen = re.patient_id
	                where re.id={$id}", ARRAY_A);

	if ($report['estatus'] == 'C') {
		$wpdb->update($table_report, array('estatus' => 'M', 'modified' => time()), array('id' => $id));

		if ($report['batch_status'] == 'completed') {
			$wpdb->update($table_batch, array('batch_status' => 'resulted'), array('mid' => $report['mid']));
		}

		trpdd_log_user(get_current_user_id(), 'reverse_rs', '', $report['mid'], 'tox', '', '', $report['patient_id']);
		Tr_Session_Class::add("Reversed Result #" . $id);
	}
	if (isset($_REQUEST['trajax'])) {
		Tr_Session_Class::show();
		echo 'ok';
		exit;
	}
} else if ($act == 'results_delete' || $act == 'pdelete') {
	$id = $_REQUEST['id'] > 0 ? $_REQUEST['id'] : $_REQUEST['idd'];

	$report = tr_pdd_get_report_by_id($id);
	$mid = $report['mid'];

	if ($mid > 0) {
		$wpdb->update($table_batch, array('is_saved' => 2, 'result_pdf' => ''), array('mid' => $mid));

		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $mid, 'tox', '', '', $report['patient_id']);
		trpdd_log_user(get_current_user_id(), 'delete_req', '', $mid, 'tox', '', '', $report['patient_id'], 'Req Form');
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

		trpdd_log_user(get_current_user_id(), 'delete_rs', '', $rs->mid, 'tox', '', '', $rs->assen);

		$return['ids'][] = $id;
	}
	wp_send_json($return);
} else if ($act == 'view_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'view';
	include_once(tr_pdd_get_req_file('tox', isset($_GET['short'])));
	return;
} else if ($act == 'edit_req') {
	$hide_tabs = true;
	$act = $_REQUEST['act'] = $_GET['act'] = 'edit';
	include_once(tr_pdd_get_req_file('tox', isset($_GET['short'])));
	return;
}


wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);
$disable_upload_results = tr_get_option('ci_pdf_data', 'disable_upload_results');

$tabs = array(
	'list' => 'Results',
	'confirmation_pending' => 'Pending',
	//'pending' => 'Pending',
	'generator' => 'Report Generator',
	'upload' => 'Upload Results PDF',
	'incomplete' => 'Incomplete Orders',
	'tox_stats' => 'Tox Stats',
	//'qc' => 'QC',
	'levey_jennings_qc' => 'Levey–Jennings QC',
);

if ($disable_upload_results) {
	unset($tabs['upload']);
	unset($tabs['generator']);
}

if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'lab_director', 'labmanager'))) {
	$tabs['efax_setup'] = 'eFax Setup';
}
if (tr_current_role_in(array('lab_sec', 'labsecman', 'leadlabsecman', 'labb'))) {
	$tabs = array(
		'list' => 'Results',
		'pending' => 'Pending Results',
		'incomplete' => 'Incomplete Orders'
	);
} else if (tr_current_role_in(array('Billing', 'LabResultsGen'))) {
	unset($tabs['incomplete']);
} else if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
	$tabs = array(
		'generator' => 'Report Generator',
	);
} else if ($current_role == 'ResultsUpload' || $current_role == 'RefManager') {
	$tabs = array(
		'list' => 'Results',
		'generator' => 'Report Generator',
	);
}
$tab = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);

if (!isset($_REQUEST['trajax'])) {
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


if ($act == 'edit') {
	include(trpdd_get_file_path('tox', 'results_edit'));
} else if ($tab == 'efax_setup') {
	include(trpdd_get_file_path('settings', 'efax_setup'));
} else if (in_array($tab, ['upload', 'generator']) && !$disable_upload_results) {
	include(trpdd_get_file_path('tox', 'results_' . $tab));
} else if (!in_array($tab, ['upload', 'generator'])) {
	include(trpdd_get_file_path('tox', 'results_' . $tab));
}
