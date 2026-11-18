<?php
/*
Name: Only ABX
*/
global $wpdb, $ci_cutoffs, $report;
global $ci_abx_reports;



$id             = isset($_REQUEST['id']) ? $_REQUEST['id'] : (isset($id) ? $id : 0);
$bid            = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : (isset($bid) ? $bid : '');

$group_title    = tr_get_option('ci_pdf_data', 'group_head_pcr');
$diable_show_same2group = false;
$pdf_title      = tr_get_option('ci_pdf_data', 'pcr_pdf_title');

if (empty($pdf_title)) {
	$pdf_title = 'Molecular PCR Summary Lab Report';
}
$abx_groupk     = 'uti';

if (empty($group_title)) {
	$group_title = 'Test Performed';
}

$has_report     = false;
$has_style      = false;
$group_logo     = '';

$upload_dir     = tr_wp_upload_dir();
$logo_dir       = $upload_dir['basedir'] . '/logos/';
$logo_url       = $upload_dir['baseurl'] . '/logos/';

$mid = isset($_REQUEST['mid']) ? $_REQUEST['mid'] : (isset($mid) ? $mid : 0);

if (!$report) {
	$report = tr_pdd_get_report_by_id($id, 'pcr', $mid);
}

if (isset($_GET['dbtime'])) {
	global $db_time_log;
	$db_time_log .= 'report  assen3:' . $report['assen'] . '<br>';
}

if (!$report || empty($report['assen'])) {
	return;
}

if (tr_get_option('ci_pdf_data', 'cli_new_abx')) {
	$cli_otheropts = $wpdb->get_var("select cli_other_options from ci_clinic where cID = {$report['cli_ID']}");
	$cli_otheropts = json_decode($cli_otheropts, true);

	if (is_array($cli_otheropts) && !$cli_otheropts['newabx']) {
		$ci_abx_reports[$report['id']] = false;
	}
}

$shownewabx = isset($ci_abx_reports[$report['id']]) && $ci_abx_reports[$report['id']];
if (!$shownewabx) {
	$shownewabx = isset($_REQUEST['shownewabx']) || isset($_GET['newabx']) || isset($_GET['debugabx']);
}
if (isset($_GET['dbtime'])) {
	global $db_time_log;
	$db_time_log .= '$shownewabx ' . $shownewabx . '<br>';
}

if (isset($_GET['aaa'])) {
	var_dump($shownewabx);
}

if (tr_get_option('ci_pdf_data', 'new_abx') && $shownewabx) {

	require(TRCPDD_PATH . 'reports/abx.php');
	return;
}

//removed old abx below
//include(TRCPDD_PATH . 'reports/abx/old_abx.php');

if (defined('TRPDD_ARK') && TRPDD_ARK) {
	//include(TRCPDD_PATH . 'reports/abx/old_abx_ark.php');
}
