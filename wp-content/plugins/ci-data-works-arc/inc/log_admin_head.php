<?php
global $title, $wpdb;
$pagetitle = $title;
$action = 'view';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	return;
}
$l_pagetab = '';

if (isset($_GET['pagetab'])) {
	switch ($_GET['pagetab']) {

		case 'ci_payer_list':
			$pagetitle .= '|tab: Payer List';
			$l_pagetab = 'payer';
			break;
		case 'ci_test_panels':
			$pagetitle .= '|tab: Panels';
			$l_pagetab = 'panel';
			break;
		case 'levey_jennings':
			$pagetitle .= '|tab: L-J LCMS';
			$l_pagetab = 'levey_jennings';
			break;
		case 'ci_wsdl_keys':
			$pagetitle .= '|tab: APIs';
			$l_pagetab = 'apis';
			break;
		case 'this':
			$pagetitle .= '|tab: Other Settings';
			$l_pagetab = 'settings';
			break;
		case 'ci_resend_wc':
			$pagetitle .= '|tab: ReSend Welcome';
			$l_pagetab = 'ci_resend_wc';
			break;
		case 'ci_fix_duplicate':
			$pagetitle .= '|tab: Duplicate';
			$l_pagetab = 'ci_fix_duplicate';
			break;
	}
}

if (isset($_GET['tab'])) {
	switch ($_GET['tab']) {
		case 'list':
			$pagetitle .= ' | tab: List';
			break;
		case 'saved_form':
			$pagetitle .= ' | tab: Saved';
			break;
		case 'report_date':
			$pagetitle .= '| tab: Collection Date Report';
			break;
		case 'report_info':
			$pagetitle .= ' | tab: Billing info Report';
			break;
		case 'form_fill':
			$pagetitle .= ' | tab: Form Fill Report';
			break;
		case 'calendar':
			$pagetitle .= ' | tab: Specimen Calendar';
			break;
		case 'new_account_setup':
			$pagetitle .= ' | tab: New Account Setup Form';
			if ($_GET['clinic_id'] > 0) {
				$action = 'edit';
				$pagetitle .= '|Edit: #' . $_GET['clinic_id'];
			}
			break;
		case 'custom_provider':
			$pagetitle .= '|tab: Custom Provider Tox';
			break;
		case 'collector_training':
			$pagetitle .= '|tab: Collector Training Form';
			break;
		case 'upload':
			$pagetitle .= '|tab: Upload';
			break;
		case 'efax_setup':
			$pagetitle .= '|tab: eFax Setup';
			break;
		case 'ref_labs':
			$pagetitle .= '|tab: Ref labs';
			break;
		case 'move_account':
			$pagetitle .= '|tab: Move Account to new Rep';
			break;
		case 'moving_all_acount_rep':
			$pagetitle .= '|tab: Move All Accts to new Rep';
			break;
		case 'ci_marketing_groups':
			$pagetitle .= '|tab: Marketing Groups';
			break;

		case 'dna':
			$pagetitle .= '|tab: PGx';
			break;
		case 'blood':
			$pagetitle .= '|tab: Blood';
			break;
		case 'allergy':
			$pagetitle .= '|tab: Allergy';
			break;
		case 'pcr':
			$pagetitle .= '|tab: PCR';
			break;

		case 'batches':
			$pagetitle .= '|tab: Batches';
			break;
		case 'current_batches':
			$pagetitle .= '|tab: Current Batch';
			break;
		case 'short_loadlist':
			$pagetitle .= '|tab: Short Form';
			break;
		case 'today_batch':
			$pagetitle .= '|tab: Today\'s Batch';
			break;
		case 'check_reference':
			$pagetitle .= '|tab: Check Reference';
			break;
		case 'loadlist_send':
			$pagetitle .= '|tab: Loadlist Send';
			break;
		case 'specimen_issues':
			$pagetitle .= '|tab: Specimen Issues';
			break;
		case 'list_receiving':
			$pagetitle .= '|tab: List Receiving';
			break;
		case 'priority_receiving':
			$pagetitle .= '|tab: Priority';
			break;
		case 'tecan':
			$pagetitle .= '|tab: Tecan';
			break;
		case 'cold_storage':
			$pagetitle .= '|tab: Cold Storage';
			break;
		case 'plate_view':
			$pagetitle .= '|tab: Plate View';
			break;
		case 'processing':
			$pagetitle .= '|tab: Processing';
			break;
		case 'confirmation_pending':
			$pagetitle .= '|tab: Confirmation Pending';
			break;
		case 'generator':
			$pagetitle .= '|tab: Generator';
			break;
		case 'upload_results_pdf':
			$pagetitle .= '|tab: Upload Results PDF';
			break;
		case 'incomplete':
			$pagetitle .= '|tab: Incomplete Orders';
			break;
		case 'levey_jennings_qc':
			$pagetitle .= '|tab: Levey Jennings QC';
			break;
		case 'vendor':
			$pagetitle .= '|tab: vendors';
			break;
		case 'admin':
			$pagetitle .= '|tab: admin';
			break;
		case 'urine_validity':
			$pagetitle .= '|tab: Urine Validity';
			break;
		case 'dru':
			$pagetitle .= '|tab: Drugs';
			break;
		case 'metabolites':
			$pagetitle .= '|tab: Metabolites';
			break;
		case 'import':
			$pagetitle .= '|tab: Import';
			break;
		case 'drug_classes':
			$pagetitle .= '|tab: Billing Codes - Commercial';
			break;
		case 'codes_medicare':
			$pagetitle .= '|tab: Billing Codes - Medicare';
			break;
		case 'settings':
			$pagetitle .= '|tab: settings';
			break;
	}
}
if (!empty($_GET['date_from'])) {
	$pagetitle .= '| From: ' . $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
	$pagetitle .= '| To: ' . $_GET['date_to'];
}
if (!empty($_GET['patient'])) {
	$pagetitle .= '| patient: ' . $_GET['patient'];
}

if (isset($_GET['act'])) {
	switch ($_GET['act']) {
		case 'edit':
			$action = 'view_edit';
			$pagetitle .= ' | Edit #' . $_GET['id'];
			break;
		case 'delete':
			$action = 'delete';
			$pagetitle .= ' | Delete #' . $_GET['id'];
			break;
		case 'add':
			$action = 'view_add';
			$pagetitle .= ' | Add Page';
			break;
		case 'view':
			$pagetitle .= ' | View #' . $_GET['id'];
			break;
		case 'list':
			$pagetitle .= '| List';
			break;
		case 'add_members':
			$pagetitle .= '| Add members #' . $_GET['id'];
			break;
	}
}
if (!in_array($action, array('edit', 'editpage', 'addpage', 'delete'))) {
	if (! empty($_GET['s'])) {
		$pagetitle .= ' | Search: ' . $_GET['s'];
	}
	if (isset($_GET['paged']) && $_GET['paged'] > 0) {
		$pagetitle .= ' | Page: ' . $_GET['paged'];
	}
}


if ($action != 'view') {
	$user_id = get_current_user_id();
	$l_page = $_GET['page'];
	$l_mid = '';
	$l_btype = '';
	$l_assen = '';
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	}

	if ($action == 'view_edit' && isset($_GET['page'])) {
		$should_log = false;
		$is_req_rs = strpos($l_page, "req_form") !== false || strpos($l_page, "results") !== false;
		$is_billing = strpos($l_page, "ci_billings") !== false && !isset($_GET['tab']);
		if (($is_req_rs || $is_billing) && isset($_GET['id'])) {
			$l_btype = explode('_', $l_page)[0] ?? '';
			if ($is_billing) {
				$l_btype = $_GET['btype'];
			}
			$is_result = strpos($l_page, "results") !== false;
			if (!empty($l_btype)) {
				$table = $is_result ? tr_pdd_get_table_report_by_btype($l_btype) : tr_pdd_get_table_by_btype($l_btype);
				if ($is_result) {
					$l_record = $wpdb->get_row("select id, patient_id as assen from {$table} where id = {$_GET['id']}");
				} else {
					$l_record = $wpdb->get_row("select mid as id, assen from {$table} where mid = {$_GET['id']}");
				}
				if ($l_record) {
					$action     = 'view' . ($is_result ? '_rs' : '_req');
					$l_mid  = $l_record->id;
					$l_assen = $l_record->assen;
					$should_log = true;
				}
			}
		}
		if (isset($_GET['lab_id']) || strpos($l_page, "_labs") !== false) {
			$action =  $action . '_lab';
			$l_mid =  $_GET['lab_id'] ?? $_GET['id'];
			$l_assen = $l_mid;
			$should_log = true;
		}
		if (in_array($l_page, ['ci_pdf_data', 'ci_data_forms'])) {
			if ($l_page == 'ci_pdf_data' && $l_pagetab === '') {
				$l_pagetab = 'panel';
			}
			if ($l_pagetab) {
				$action =  $action . '_' . $l_pagetab;
				if ($l_pagetab == 'panel' && isset($_GET['tabtype'])) {
					$action = $action . '_' . $_GET['tabtype'];
				}
			}
			if (isset($_GET['tab']) && $l_pagetab != 'panel') {
				$action =  $action . '_' . $_GET['tab'];
			}
			$l_mid = $_GET['id'];
			$l_assen = $l_mid;
			$should_log = true;
		}
		if ($should_log) {
			$where = '';
			if (!empty($l_mid)) $where .= " AND mid = {$l_mid}";
			if (!empty($l_btype)) $where .= " AND btype = '{$l_btype}'";

			$sql = "SELECT created FROM ci_log_users 
			WHERE user_id = {$user_id} AND user_action = '{$action}' AND ip = '{$ip}' {$where}
			ORDER BY created DESC
			LIMIT 1";
			$last_log = $wpdb->get_var($sql);
			$should_log = true;
			if ($last_log) {
				$diff = strtotime(date_i18n('Y-m-d H:i:s')) - strtotime($last_log);
				if ($diff < 300) {
					$should_log = false;
				}
			}
			if ($should_log) {
				trpdd_log_user($user_id, $action, '', $l_mid, $l_btype, '', '', $l_assen);
			}
		}
	} else if ($action !== 'view_add') {
		trpdd_log_user($user_id, $action, $pagetitle);
	}
}


$favicon_url_32 = TRCPDD_URL . 'images/favicon32.png';
$favicon_url_apple = TRCPDD_URL . 'images/apple-touch-icon.png';
echo '<link rel="icon" type="image/png" href="' . esc_url($favicon_url_32) . '">' . "\n";
echo '<link rel="apple-touch-icon" href="' . esc_url($favicon_url_apple) . '">' . "\n";

$current_lab = CI_Base_Lab::get_current_lab();
if ($current_lab && !defined('TRPDD_PATIENT_SITE')) {
	$upload_dir = tr_wp_upload_dir();
	$logo       = $current_lab['lab_logo'];
	$logo_path = $upload_dir['basedir'] . '/logos/' . $logo;
	$logo_url   = $upload_dir['baseurl'] . '/logos/' . $logo;
	if ($logo != '') {
?>
		<style>
			#adminmenu::before {
				content: "";
				display: block;
				background: url("<?php echo $logo_url; ?>?<?php echo TRCPDD_VS ?>") no-repeat center center;
				background-size: contain;
				box-sizing: border-box;
				background-origin: content-box;
				padding: 12px;
				height: 80px;
				background-color: white;
				border-right: 1px solid #e0e0e0;
			}

			#adminmenu {
				margin: 0px;
			}
		</style>

<?php

	}
}
