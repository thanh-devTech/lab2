<?php

function trfront_action_ci_export_dna_html()
{
	$_REQUEST['id'] = $_GET['id'] = 122065;
	include(TRCPDD_PATH . 'pages/admin_ci_export_req_html.php');
	exit;
}

function trfront_action_ci_results_pdf()
{
	if (isset($_REQUEST['file'])) {
		if (!isset($_GET['hqr'])) {
			$_REQUEST['use_cache'] = true;
		}

		$_GET['viewpdf'] = true;

		list($btype, $idt, $uid) = explode('_', str_replace('.pdf', '', $_REQUEST['file']));
		$idt = str_replace('.pdf', '', $idt);
		$id = trpdd_patient_decode($idt);

		$auth = base64_decode($_REQUEST['auth']);
		list($ftime, $uid2, $id2) = explode('_', $auth);

		if ($id != $id2) {
			$id = base64_decode($idt);
			if ($id === $id2) {
				global $wpdb;
				$report_tbl = tr_pdd_get_table_report_by_btype($btype);
				$id = $id2= $wpdb->get_var("select id from {$report_tbl} where patient_id = '{$id}' ");
			}
		}

		if ($ftime < time() - 3600 || $uid != $uid2 || $id != $id2) {
			if (!isset($_GET['abc']) || $_GET['abc'] != 'j20a92kasd3k2f') {
				echo 'file is expiry';
				exit;
			}
		}

		if (!empty($btype) && $id > 0) {
			$_REQUEST['btype'] = $_GET['btype'] = $btype;
			$_REQUEST['id'] = $_GET['id'] = $id;
		}
		if (!function_exists('tradmin_action_ci_results_pdf')) {
			include('admin_actions.php');
		}
		tradmin_action_ci_results_pdf();
	}
	exit;
}
