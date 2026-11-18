<?php
global $wpdb, $current_role, $blog_id;

$datetime_now = date_i18n('Y-m-d H:i:s');
$today = date_i18n('Y-m-d');

if (isset($_REQUEST['today'])) {
	$today = $_REQUEST['today'];
}

$batch_id = tr_pdd_get_current_batch_id();
$user_id = get_current_user_id();

if ($act == 'ci_update_position') {
	$return = array('status' => 'ok');
	$mid = $_REQUEST['mid'];
	$position = $_REQUEST['position'];
	$re_ID = $_REQUEST['re_ID'] > 0 ? $_REQUEST['re_ID'] : 0;

	list($table, $mid, $btype, $subtype) = tr_pdd_get_table_mid_by_id($mid);

	if ($re_ID > 0) {
		$receiving = $wpdb->get_row("select * from ci_receiving where re_ID = '{$re_ID}' ");
	}

	$batchdt = $wpdb->get_row("select * from {$table} where mid = {$mid} ");

	$key_pos = $btype;
	if (!empty($batchdt->initials1)) {
		$key_pos = strtolower($batchdt->initials1);
	} else if ($btype == 'tox') {
		$key_pos = strtolower($batchdt->type_of_specimen);
	}

	if ($btype == 'tox') {
		$sql_count = "select count(rb.*) from ci_receiving_batches rb
		                    join ci_batches ba ON ba.mid = rb.mid
	                        where rb.re_ID = {$re_ID} and rb.btype='{$btype}' ";
		if (!empty($batchdt->initials1)) {
			$sql_count .= " and ba.initials1='{$batchdt->initials1}' ";
		} else {
			$sql_count .= " and ba.type_of_specimen = '{$key_pos}' ";
		}
	} else {
		$sql_count = "select count(*) from ci_receiving_batches rb
	                        join {$table} ba ON ba.mid = rb.mid
	                        where rb.re_ID = {$re_ID} and rb.btype='{$btype}' ";
		if (!empty($batchdt->initials1)) {
			$sql_count .= " and ba.initials1='{$batchdt->initials1}' ";
		}
	}

	$count = $wpdb->get_var($sql_count);

	$return['key_pos'] = $key_pos;
	$prev_field = 're_prev_pos_' . $key_pos;

	if ($count <= 1) {
		$update = array(
			$prev_field => $position - 1
		);
		$wpdb->update('ci_receiving', $update, array('re_ID' => $re_ID));
		$return['prev_pos'] = $position;
	} else {
		if ($btype == 'tox') {
			$sql_min = "select min(ba.position) from ci_receiving_batches rb
		                    join ci_batches ba ON ba.mid = rb.mid
	                        where rb.re_ID = {$re_ID} and rb.btype='{$btype}' and ba.position>0 ";
			if (!empty($batchdt->initials1)) {
				$sql_min .= " and ba.initials1='{$batchdt->initials1}' ";
			} else {
				$sql_min .= " and ba.type_of_specimen = '{$key_pos}' ";
			}
		} else {
			$sql_min = "select min(ba.position) from ci_receiving_batches rb
	                        join {$table} ba ON ba.mid = rb.mid
	                        where rb.re_ID = {$re_ID} and rb.btype='{$btype}'  and ba.position>0 ";
			if (!empty($batchdt->initials1)) {
				$sql_min .= " and ba.initials1='{$batchdt->initials1}' ";
			}
		}

		$min_pos = $wpdb->get_var($sql_min);

		$update = array(
			$prev_field => $min_pos - 1
		);
		$wpdb->update('ci_receiving', $update, array('re_ID' => $re_ID));
		$return['prev_pos'] = $min_pos;
	}

	if ($batchdt->position != $position) {
		$sql_exist = "select ba.mid from {$table} ba 
	                    join ci_receiving_batches rb ON rb.mid = ba.mid and rb.btype = '{$btype}'
	                     join ci_receiving ri ON ri.re_ID = rb.re_ID
	                     where ri.re_today = '{$receiving->re_today}' 
	                     and ba.mid != {$mid} and ba.position={$position} ";
		$exist = $wpdb->get_var($sql_exist);

		if ($exist) {
			$return['status'] = 'exist';
		} else {
			$wpdb->update($table, array('position' => $position), array('mid' => $mid));

			trpdd_log_user($user_id, 'receiving_change_pos', "{$batchdt->position} to {$position}", $mid, $btype, '', '', $batchdt->assen);
		}
	}

	wp_send_json($return);
} else if ($act == 'ci_update_spec_type') {
	$return = array('status' => 'ok');
	//asdf();

	$mid = $_REQUEST['mid'];
	$spec_type = $_REQUEST['spec_type'];
	$re_ID = $_REQUEST['re_ID'];

	list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);

	$batchdt = $wpdb->get_row("select * from {$table} where mid = {$mid} ");

	if ($re_ID > 0) {
		$receiving = $wpdb->get_row("select * from ci_receiving where re_ID = {$re_ID} ");
	} else {
		$receiving = null;
	}


	$update = array('type_of_specimen' => $spec_type);

	$key_pos = $btype;
	if (!empty($batchdt->initials1)) {
		$key_pos = strtolower($batchdt->initials1);
	} else if ($btype == 'tox') {
		$key_pos = strtolower($batchdt->type_of_specimen);
	}

	//find new position:
	if ($btype == 'tox' && in_array($key_pos, array('urine', 'oral'))) {
		$base_positions = array();
		$specimens = $wpdb->get_results("select * from ci_test_specimens where ts_type='{$btype}'");

		foreach ($specimens as $r) {
			if ($r->ts_position > 0) {
				$r->ts_position -= 1;
			}

			if ($r->ts_type == 'tox') {
				$r->ts_type = strtolower($r->ts_name);
			}

			$base_positions[$r->ts_type] = $r->ts_position;
		}
		//var_dump($base_positions);exit;

		$new_position = $base_positions[$key_pos] + 1;


		//find all position exist
		$sql_exist_positions = "select ba.position from {$table} ba 
	                    join ci_receiving_batches rb ON rb.mid = ba.mid and rb.btype = '{$btype}'
	                     join ci_receiving ri ON ri.re_ID = rb.re_ID
	                     where ri.re_today = '{$receiving->re_today}' ";
		$exist_positions = $wpdb->get_col($sql_exist_positions);

		while (in_array($new_position, $exist_positions)) {
			$new_position++;
		}

		$update['position'] = $new_position;
		$return['position'] = $new_position;
	}



	//end find new position


	$rs = $wpdb->update($table, $update, array('mid' => $mid));

	if ($rs === false) {
		$return['status'] = 'error';
		$return['msg'] = $wpdb->last_error;
	}


	wp_send_json($return);
} else if ($act == 'ci_update_alcohol') {
	$return = array('status' => 'ok');
	$update = array();

	$mid = $_REQUEST['mid'];
	$alcohol = $_REQUEST['alcohol'];
	$re_ID = $_REQUEST['re_ID'];

	list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);

	if ($re_ID > 0) {
		$receiving = $wpdb->get_row("select * from ci_receiving where re_ID = {$re_ID} ");
	} else {
		$receiving = null;
	}

	$batchdt = $wpdb->get_row("select * from {$table} where mid = {$mid} ");

	$tests = $batchdt->panel_tests_requested;
	$tests = explode(',', $tests);
	if ($alcohol) {
		if (!in_array('ETG', $tests)) {
			$tests[] = 'ETG';
		}
		if (!in_array('ETS', $tests)) {
			$tests[] = 'ETS';
		}
	} else {
		if (in_array('ETG', $tests)) {
			$idx = array_search('ETG', $tests);
			unset($tests[$idx]);
		}
		if (in_array('ETS', $tests)) {
			$idx = array_search('ETS', $tests);
			unset($tests[$idx]);
		}
	}

	$update['panel_tests_requested'] = implode(',', $tests);


	$rs = $wpdb->update($table, $update, array('mid' => $mid));

	if ($rs === false) {
		$return['status'] = 'error';
		$return['msg'] = $wpdb->last_error;
	}


	wp_send_json($return);
} else if ($act == 'ci_update_culture') {
	$return = array('status' => 'ok');
	$update = array();

	$mid = $_REQUEST['mid'];
	$culture = $_REQUEST['culture'];
	$re_ID = $_REQUEST['re_ID'];

	list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);

	$update = array('culture' => $culture);
	$rs = $wpdb->update($table, $update, array('mid' => $mid));

	if ($rs === false) {
		$return['status'] = 'error';
		$return['msg'] = $wpdb->last_error;
	}


	wp_send_json($return);
} else if ($act == 'ci_update_doc') {
	$return = array('status' => 'ok');
	$update = array();

	$mid 	= $_REQUEST['mid'];
	$doc	= $_REQUEST['doc'];
	$re_ID 	= $_REQUEST['re_ID'];

	list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);

	$update = array('doc' => $doc);
	$rs = $wpdb->update($table, $update, array('mid' => $mid));

	if ($rs === false) {
		$return['status'] = 'error';
		$return['msg'] = $wpdb->last_error;
	}


	wp_send_json($return);
} else if ($act == 'ci_update_receiving') {
	$return = array('status' => 'ok');

	$re_ID 		= $_REQUEST['re_ID'];
	$fieldname 	= $_REQUEST['fieldname'];
	$val 		= $_REQUEST['val'];

	if ($re_ID > 0) {
		if ($fieldname == 're_tracking_number_barcode' && stripos($val, 'remove') !== false) {
			$rs = $wpdb->delete('ci_receiving', array('re_ID' => $re_ID));
			if ($rs !== false) {
				$return['status'] = 'removed';
			}
		} else {
			$receiving = $wpdb->get_row("select * from ci_receiving where re_ID = {$re_ID} ");
			$rs = $wpdb->update('ci_receiving', array($fieldname => $val), array('re_ID' => $re_ID));
			if ($rs === false) {
				$return['status'] = 'error';
				$return['msg'] = $wpdb->last_error;
			}
		}
	} else {
		$return['status'] = 'error';
	}

	wp_send_json($return);
} else if ($act == 'ci_remove_rbatch') {
	$return 	= array('status' => 'ok');
	$mid 		= $_REQUEST['mid'];
	$re_ID 		= $_REQUEST['re_ID'];

	list($table, $mid, $btype, $subtype) = tr_pdd_get_table_mid_by_id($mid);

	$batchdt	= $wpdb->get_row("select * from {$table} where mid = {$mid} ");


	$key_pos 	= $btype;
	if (!empty($batchdt->initials1)) {
		$key_pos = strtolower($batchdt->initials1);
	} else if ($btype == 'tox') {
		$key_pos = strtolower($batchdt->type_of_specimen);
	}


	$fieldname = 're_qty_' . $key_pos;

	if ($key_pos != 'pcr') {
		$wpdb->query("update ci_receiving set {$fieldname}={$fieldname} -1 where re_ID = {$re_ID} ");
	}

	$wpdb->query("delete from ci_receiving_batches where re_ID = {$re_ID} 
	and mid = {$mid} 
	and btype='{$btype}' 
	and subtype = '{$subtype}' ");
	$wpdb->query("update {$table} set batch_id=0, batch_status='', spec_received='',position='' where mid = {$mid}  ");
	wp_send_json($return);
} else if ($act == 'ci_add_all_current_batch') {

	$return = array('status' => 'ok');

	$today      = date_i18n('Y-m-d');

	$batch_id   = tr_pdd_get_current_batch_id();
	if (isset($_POST['bid']) && !empty($_POST['bid'])) {
		$batch_id = $_POST['bid'];
	}

	$lcms_tests = null;

	if (defined('TRPDD_HOUR_CHANGE_BATCH') && TRPDD_HOUR_CHANGE_BATCH > 0) {
		if (TRPDD_HOUR_CHANGE_BATCH > 13 && $hournow >= TRPDD_HOUR_CHANGE_BATCH) {
			//$today = date('Y-m-d', strtotime($today) + 86400);
		} else if (TRPDD_HOUR_CHANGE_BATCH < 7 && $hournow < TRPDD_HOUR_CHANGE_BATCH) {
			$today = date('Y-m-d', strtotime($today) - 86400);
		}
	}

	$sql = "select * from ci_receiving where re_today >= '{$today}' ";
	if (isset($_REQUEST['today'])) {
		$today = $_REQUEST['today'];
		$sql = "select * from ci_receiving where re_today = '{$today}' ";
	}
	$receivings = $wpdb->get_results($sql);
	foreach ($receivings as $receiving) {
		if (!tr_get_option('ci_pdf_data', 'multi_batch_id')) {
			$batch_id = str_replace('-', '', $receiving->re_today);
		}

		if (empty($receiving->re_batch_id)) {
			$wpdb->update('ci_receiving', array('re_batch_id' => $batch_id), array('re_ID' => $receiving->re_ID));
		}

		$re_batches = $wpdb->get_results("select * from ci_receiving_batches where re_ID = {$receiving->re_ID} ");
		foreach ($re_batches as $re_batch) {
			$btype = $re_batch->btype;
			$mid = $re_batch->mid;
			$update = array();

			if ($btype == 'tox') {
				if ($lcms_tests === null) {
					$lcms_tests = tr_pdd_get_profiles_tests_requesteds('tox', true, false, '');
				}

				if (empty($lcms_tests)) {
					$update['only_screening'] = 1;
				}
			}

			$table = tr_pdd_get_table_by_btype($btype);

			$batchdt = $wpdb->get_row("select mid,batch_id, batch_status, assen from {$table} where mid = {$mid} ");

			$need_update_batch_id = empty($batchdt->batch_id);
			if (!tr_get_option('ci_pdf_data', 'multi_batch_id')) {
				$need_update_batch_id = $batchdt->batch_id != $batch_id;
			}
			if ($need_update_batch_id) {
				$update['batch_id'] = $batch_id;

				trpdd_log_user($user_id, 'receiving_add_batch', $batch_id, $mid, $btype, '', '', $batchdt->assen);
			}

			if (count($update) > 0) {
				$wpdb->update($table, $update, array('mid' => $mid));
			}
		}
	}

	if (!$wpdb->get_var("select count(*) from ci_batch_ids where batch_id = '{$batch_id}' ")) {
		$wpdb->query("insert IGNORE into ci_batch_ids set batch_id = '{$batch_id}' ");
	}

	wp_clear_scheduled_hook('cron_add_manual_reports', array('batch_id' => $batch_id));
	wp_schedule_single_event(time(), 'cron_add_manual_reports', array('batch_id' => $batch_id));

	wp_send_json($return);
} else if ($act == 'ci_call_manual_reports') {
	$return = array('status' => 'ok');
	ob_start();
	$batch_id = $_REQUEST['bid'];
	$_GET['testcron'] = true;
	include(TRCPDD_PATH . 'inc/crons/add_manual_reports.php');
	$log = ob_get_clean();
	$return['log'] = $log;
	wp_send_json($return);
} else if ($act == 'add_to_batch') {
	$return = array('status' => 'ok');

	$mid = $_REQUEST['mid'];
	$re_ID = $_REQUEST['reid'];
	list($table, $mid, $btype) = tr_pdd_get_table_mid_by_id($mid);

	if ($re_ID > 0) {
		$receiving = $wpdb->get_row("select * from ci_receiving where re_ID = {$re_ID} ");
	} else {
		$receiving = null;
	}

	if (!tr_get_option('ci_pdf_data', 'multi_batch_id')) {
		$batch_id = str_replace('-', '', $receiving->re_today);
	}


	$update = array();

	$table = tr_pdd_get_table_by_btype($btype);

	$batchdt = $wpdb->get_row("select mid,batch_id, batch_status, assen from {$table} where mid = {$mid} ");

	$need_update_batch_id = empty($batchdt->batch_id);
	if (!tr_get_option('ci_pdf_data', 'multi_batch_id')) {
		$need_update_batch_id = $batchdt->batch_id != $batch_id;
	}
	if ($need_update_batch_id) {
		$update['batch_id'] = $batch_id;

		trpdd_log_user($user_id, 'receiving_add_batch', $batch_id, $mid, $btype, '', '', $batchdt->assen);
	}

	if (count($update) > 0) {
		$wpdb->update($table, $update, array('mid' => $mid));
	}
	$return['batch_id'] = $batch_id;

	if (!$wpdb->get_var("select count(*) from ci_batch_ids where batch_id = '{$batch_id}' ")) {
		$wpdb->query("insert IGNORE into ci_batch_ids set batch_id = '{$batch_id}' ");
	}

	wp_clear_scheduled_hook('cron_add_manual_reports', array('batch_id' => $batch_id));
	wp_schedule_single_event(time(), 'cron_add_manual_reports', array('batch_id' => $batch_id));

	wp_send_json($return);
} else if ($act == 'add_batch_no') {
	$return = array('status' => 'ok');
	$arr = get_option('tr_batch_num_st', array());
	$last_batch_id = date_i18n("Ymd");
	$arr['num'] += 1;
	$arr['bid'] = $last_batch_id;
	update_option('tr_batch_num_st', $arr);

	$batch_id = tr_pdd_get_current_batch_id();
	$return['batch_id'] = $batch_id;

	wp_send_json($return);
}


add_thickbox();
wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('wp-jquery-ui-dialog');
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_script('at-timepicker', TRCPDD_URL . 'admin/js/time-and-date/jquery-ui-timepicker-addon.js', array('jquery', 'jquery-ui-slider'), null, true);
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);





$created = date_i18n('Y-m-d H:i:s');

$priorities = array('low' => 'Low', 'med' => 'Med', 'high' => 'High');
$timings = array('24' => '24hrs', '48' => '48hrs', '72' => '72hrs', '120' => '5days', '168' => '7days');


$current_time = time() + (get_option('gmt_offset') * HOUR_IN_SECONDS);
$today = date('Y-m-d', $current_time);

if ($act == 'delete' && $_REQUEST['id'] > 0) {
	$wpdb->delete('ci_receiving', array('re_ID' => $_REQUEST['id']));
	Tr_Session_Class::add('deleted successfully!');
}

if (isset($_GET['fix_bid'])) {
	tr_pdd_cron_receiving_batch_id();
	exit;
}
$tabs = array(
	'' => 'Receiving',
	'list_receiving' => 'List',
	'priority_receiving' => 'Priority',
	'racks' => 'Racks',
	'carriers' => 'Carriers'
);

$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if (!isset($tabs[$tab])) {
	$tab = key($tabs);
}
if (!isset($_REQUEST['trajax'])) {
?>
	<div class="wrap">
		<h2 class="nav-tab-wrapper">
			<?php

			foreach ($tabs as $k => $txt) {
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link = Tr_Base_Class_V6::link(array('tab' => $k, 'act' => '', 'id' => '', 'date' => '', 'assen' => ''), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
			}
			?>
		</h2>
	</div>
<?php
}

if ($tab == 'carriers') {
	include('admin_ci_receiving_carriers.php');
} else if ($tab == 'racks') {
	include(trpdd_get_file_path('rack', 'main'));
} else if ($tab == 'list_receiving') {
	include('admin_ci_receiving_list.php');
} else if ($tab == 'priority_receiving') {
	include('admin_ci_receiving_priority.php');
} else if (isset($_GET['rnew'])) {
	include('admin_ci_receiving_form_new.php');
} else {
	$maintainent = false;
	if ($maintainent) {
		echo '<br><br><h2 class="center">Please back after 15mins</h2>';
		exit;
	}
	include('admin_ci_receiving_form.php');
}
