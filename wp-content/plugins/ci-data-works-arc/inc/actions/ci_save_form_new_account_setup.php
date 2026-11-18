<?php
global $wpdb, $current_role;
$_POST = tr_pdd_stripslashes_deep($_POST);

$cli  = $_POST['cli'];
$col = $_POST['col'];
$docs = $_POST['doc'];
$lab = $_POST['lab'];
$managers = $_POST['manager'];
$user_id = get_current_user_id();
//update clinic

if (isset($cli['cli_in-net-list'])) {
	$cli['cli_in-net-list'] = implode('|', (array)$cli['cli_in-net-list']);
}
if (isset($cli['cli_payor-mix'])) {
	$cli['cli_payor-mix'] = implode('|', (array)$cli['cli_payor-mix']);
}

if (!empty($_POST['cli_new_group'])) {
	//create new clinic group
	$wpdb->insert('ci_clinician_groups', array('clig_name' => $_POST['cli_new_group']));
	$cli['cli_group_ID'] = $wpdb->insert_id;
}

$cli_id = $cli['cID'];
if ($current_role == 'distributor') {
	$cli['cli_distributor_ID'] = $wpdb->get_var("select dis_ID from ci_distributors where dis_user_ID = {$user_id}");
}

$old_cli = $wpdb->get_row("select * from ci_clinic where cID = {$cli_id} ", ARRAY_A);

if ($lab['lab_ID'] > 0) {
	$old_cli['cli_lab-ID'] = $lab['lab_ID'];
	$cli['cli_lab-ID'] = $lab['lab_ID'];
}

$all_clinic_api_fields = tr_get_all_api_fields('cli_');
if (!empty($all_clinic_api_fields)) {
	$posted_clinic_api_fields = array_intersect(array_keys($cli), $all_clinic_api_fields);
	if (!empty($posted_clinic_api_fields)) {
		tr_ensure_clinic_api_columns($posted_clinic_api_fields);
	}
}

$rs = $wpdb->update('ci_clinic', $cli, array('cID' => $cli_id));

if ($rs !== false) {
	tr_check_and_create_clinic_user($cli_id);

	Tr_Session_Class::add('Saved Account');
} else {
	Tr_Session_Class::add_error('Error: ' . $wpdb->last_error);
}

//doc
$wpdb->query("delete from ci_clinic_doc where cli_ID = {$cli_id}");
foreach ($docs as $doc) {
	$doc['cli_ID'] = $cli_id;
	$doc = tr_data_update_doc($doc);
}



//col
$wpdb->delete('ci_clinic_collector', array('cli_ID' => $cli_id));
foreach ($_REQUEST['cols'] as $col) {

	if ($col['col_ID'] > 0) {
		$wpdb->update('ci_collector', $col, array('col_ID' => $col['col_ID']));
		tr_pdd_create_user_collector($col, false);
	} else {
		$col['lab_ID'] = ($old_cli['cli_lab-ID'] > 0) ? $old_cli['cli_lab-ID'] : CI_Base_Lab::get_current_lab_id();

		$wpdb->insert('ci_collector', $col);
		$col['col_ID'] = $wpdb->insert_id;
		tr_pdd_create_user_collector($col, true);
	}
	$wpdb->insert('ci_clinic_collector', array('col_ID' => $col['col_ID'], 'cli_ID' => $cli_id));
}

//manager
$ma_ids = array(0);
foreach ($managers as $ma) {
	$ma['ma_cli_ID'] = $cli_id;
	if (empty($ma['ma_manager'])) continue;

	if ($ma['ma_ID'] > 0) {
		$wpdb->update('ci_clinic_manager', $ma, array('ma_ID' => $ma['ma_ID']));
	} else {
		$wpdb->insert('ci_clinic_manager', $ma);
		$ma['ma_ID'] = $wpdb->insert_id;
	}

	if ($ma['ma_ID'] > 0) {
		tr_check_and_create_office_manager_user($cli_id, $ma);
		$ma_ids[] = $ma['ma_ID'];
	}
}
$ma_ids = implode(',', $ma_ids);
$wpdb->query("delete from ci_clinic_manager where ma_cli_ID = {$cli_id} and ma_ID not in ({$ma_ids})");


trpdd_log_form('ci_save_form_new_account_setup');
