<?php

$actions = array(
	'fix_pdf_html_image_path' => 'Fix PDF wrong Images paths',
	'generate_demo' => 'Generate Demo',
	'generate_req_report' => 'Generate Req and report',
	'report_tool' => 'Report Tool',
	'rebuild_history' => 'Rebuild History',
	'reset_key' => 'Reset Key',
	'abx_tool' => 'ABX Check',
);

if (!defined('DEMO_SITE')) {
	unset($actions['generate_demo']);
	unset($actions['generate_req_report']);
}



$act = !empty($_REQUEST['act']) ? $_REQUEST['act'] : '';


if (!isset($_REQUEST['trajax'])) {
	echo '<br><ul>';
	foreach ($actions as $act_key => $act_label) {
		echo '<li><a href="' . Tr_Base_Class_V6::link(array('act' => $act_key), false) . '">' . $act_label . '</a></li>';
	}
	echo '</ul>';
}

if ($act == 'reset_key') {
	include_once(TRLCN_PATH . '/api/ci_lab_client.php');
	$client = new CI_Lab_Client_Api(true);
	$rs = $client->get_key(true);
	echo 'request key : ';
	var_dump($rs);
} else if (!empty($act)) {
	include(TRLCN_PATH . 'pages/admin_' . $act . '.php');
}
