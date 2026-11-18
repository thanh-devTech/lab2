<?php
global  $wpdb;

$page = trim($_GET['page'] ?? '');

$moved_pages = array(
	'form_requisition' => 'tox_req_form',
	'ci_results' => 'tox_results',
	'form_blood' => 'blood_req_form',
	'ci_results_blood' => 'blood_results',
	'form_pcr' => 'pcr_req_form',
	'ci_resutls_pcr' => 'pcr_results',
	'form_lab_test_req' => 'dna_req_form',

	'ci_datareport_tox_positive_negative_report' => 'ci_datareport_positive_negative_report',
	'ci_abx_report' => 'ci_reportabx_main',
	'ci_datareport_main' => 'ci_reports_main',
	'ci_lab_main' => 'ci_manage_labs',
	'ci_manage_labs' => 'ci_lab_main',
	'ci_user_users' => 'users.php',
	'users.php' => 'ci_user_users',
);


echo '<a href="?page=welcome">Welcome Page</a>';

if (isset($moved_pages[$page])) {
	$newpage = $moved_pages[$page];
	$url = Tr_Base_Class_V6::link(array('page' => $newpage), false);
	if (stripos($newpage, '.php') !== false) {
		$url = admin_url($newpage);
	}

	echo ' <meta http-equiv="refresh" content="0;' . $url . '">';
} else {
	$url = Tr_Base_Class_V6::link(array('page' => 'welcome'), false);
	echo ' <meta http-equiv="refresh" content="3;' . $url . '">';
}
