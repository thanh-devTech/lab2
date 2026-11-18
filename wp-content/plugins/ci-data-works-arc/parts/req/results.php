<?php
global $wpdb, $current_role, $blog_id;
$user_id        = get_current_user_id();
$btype          = 'req';
$pending        = isset($_REQUEST['pending']);
$user_id        = get_current_user_id();
$auto_fax       = tr_get_option('ci_pdf_data', 'auto_fax_approve_req');

$table_batch = 'ci_batches_req';
$table_report = 'ci_reports_req';
$table_results = 'ci_results_req';

$created_date = date_i18n('Y-m-d');
$created = date_i18n("Y-m-d H:i:s");

$log_dir = WP_CONTENT_DIR . '/log/results_req/';
if (!is_dir($log_dir)) {
	wp_mkdir_p($log_dir);
}

if (!empty($act)) {
	$needreturn = include(trpdd_get_file_path('req', 'results_actions'));

	if ($needreturn === true) {
		return;
	}
}



wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


$disable_upload_results = tr_get_option('ci_pdf_data', 'disable_upload_results');

$tab = 'list';
if (empty($tabs)) {
	$tabs = array(
		'list' => 'Results',
		'pending' => 'Pending',
		'qc' => 'QC',
		'pc_historical_data' => ' PC Historical Data',
		'patient_historical_data' => ' Patient Historical Data',
		'generator' => 'Report Generator',
		'upload' => 'Upload Results',
		'levey_jennings_qc' => 'Levey–Jennings QC',
	);
	if ($disable_upload_results) {
		unset($tabs['upload']);
		unset($tabs['generator']);
	}
	if (tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW', 'lab_director', 'labmanager'))) {
		$tabs['efax_setup'] = 'eFax Setup';
	} else if ($current_role == 'Billing') {
		unset($tabs['generator']);
		unset($tabs['upload']);
	} else if ($current_role == 'reflab_manager') {
		$tabs = array(
			'list' => $tabs['list'],
		);
	}
	$tab = !empty($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
	if (!isset($_REQUEST['trajax'])) {
?>
		<h2 class="nav-tab-wrapper">
			<?php

			foreach ($tabs as $k => $txt) {
				$class = $tab == $k ? 'nav-tab-active' : '';
				$link  = Tr_Base_Class_V6::link(array(
					'tab'   => $k,
					'act'   => '',
					'panelid'   => '',
					'id'    => '',
					'mid'   => '',
					'paged' => '',
					's'     => ''
				), false);
				echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
			}
			?>
		</h2>

<?php
	}
}


$tabfile = '';

if ($act == 'view' || $act == 'edit') {
	$tabfile = trpdd_get_file_path('req', 'results_edit');
} else if ($tab == 'efax_setup') {
	$tabfile = trpdd_get_file_path('settings', 'efax_setup');
}
if (in_array($tab, ['upload', 'generator']) && !$disable_upload_results) {
	include(trpdd_get_file_path('req', 'results_' . $tab));
} else if (!in_array($tab, ['upload', 'generator'])) {
	$tabfile = trpdd_get_file_path('req', 'results_' . $tab);
}

if (!empty($tabfile)) {
	include($tabfile);
}
