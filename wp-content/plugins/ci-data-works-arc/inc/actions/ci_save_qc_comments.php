<?php
global $wpdb;

$_POST = tr_pdd_stripslashes_deep($_POST);
$btype = isset($_POST['btype']) ? $_POST['btype'] : 'tox';
$return 	= array('status' => 'ok');

$report_tbl = 'ci_reports';
$result_tbl = 'ci_results';
$conc_field = 'conc';
$id_field   = 'id';

if ($btype != 'tox') {
	$conc_field = 'TestResult';
	$id_field   = 'ID';
	$report_tbl = 'ci_' . $btype . '_qc';
	$result_tbl = 'ci_' . $btype . '_qc_tests';
}

$value 		= trim($_POST['value']);
$id 		= $_POST['id'];

if ($btype == 'tox') {
	$rs 		= $wpdb->update($result_tbl, array('comments' => $value), array('id' => $id));
} else {


	$rs 		= $wpdb->update($result_tbl, array('comments' => $value), array('ID' => $id));
}



wp_send_json($return);
exit;
