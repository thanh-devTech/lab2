<?php

if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
	return;
}

if (!function_exists('tr_pdd_lis_caps')) {
	include_once(TRCPDD_PATH . 'inc/form_define.php');
}
if (!function_exists('get_editable_roles')) {
	include_once(ABSPATH . 'wp-admin/includes/user.php');
}

//'AdminCIDW','administrator','administrator_p','clms'
$all_lis_caps = array();
$all_lis_caps['read'] = true;
foreach (tr_pdd_lis_caps() as $cap => $arg) {
	$all_lis_caps[$cap] = true;
}



Tr_Base_Class_V4::create_role('lab_tech', 'Lab Tech', array(
	'ci_receiving' => true,
	'form_custom_profiles' => true,
	'ci_batches_view' => true,
	'ci_tecan_processing' => true,
	'ci_results' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'ci_consumable_inv' => true,
));


Tr_Base_Class_V4::create_role('ref_lab_tech', 'Ref Lab Tech', array(
	'form_custom_profiles' => true,
	'ci_batches_view' => true,
	'ci_results' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_results_blood' => true,
	'ci_phlebotomist' => true,
	'form_pcr' => true,
	'ci_results_pcr' => true,
	'form_gene' => true,
	'ci_results_gene' => true,
	'form_lab_test_req' => true,
));


Tr_Base_Class_V4::create_role('reflab_manager', 'Ref Lab Office Manager', array(
	'ci_test_results' => true,
	'ci_patient_chart' => true,
	'ci_patient_trends' => true,
));

Tr_Base_Class_V4::create_role('data_export', 'Data Export', array(
	'read' => true,
	'ci_data_export' => true,
));
$data_export_role = get_role('data_export');
if ($data_export_role) {
	$data_export_role->add_cap('read');
	$data_export_role->add_cap('ci_data_export');
}

Tr_Base_Class_V4::create_role('lab', 'Lab', array(
	'clinic_statistics' => true,
	'clinic_support' => true,
	'tox_report' => true,
	'ci_data_reports' => true,
	'ci_clinic_report' => true,
	'ci_lost_providers' => true,
	'ci_lab_flow' => true,
	'ci_data_forms' => true,
	'ci_current_clients' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'form_pcr' => true,
	'ci_resutls_pcr' => true,
	'form_gene' => true,
	'ci_results_gene' => true,
	'ci_ins_mix' => true,
	'ci_data_store' => true,
	'ci_results' => true,
	'ci_manage_reps' => true,
	'ci_batches_view' => true,
	'ci_manage_distributors' => true,
	'ci_customers' => true,
));

Tr_Base_Class_V4::create_role('labb', 'LabB', array(
	'clinic_statistics' => true,
	'clinic_support' => true,
	'tox_report' => true,
	'ci_data_reports' => true,
	'ci_clinic_report' => true,
	'ci_lost_providers' => true,
	'ci_lab_flow' => true,
	'ci_data_forms' => true,
	'ci_current_clients' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'form_pcr' => true,
	'ci_resutls_pcr' => true,
	'form_gene' => true,
	'ci_results_gene' => true,
	'ci_ins_mix' => true,
	'ci_data_store' => true,
	'ci_results' => true,
	'ci_manage_reps' => true,
	'ci_batches_view' => true
));
Tr_Base_Class_V4::create_role('lab_director', 'Sales director', array(
	'clinic_statistics' => true,
	'tox_report' => true,
	'ci_data_reports' => true,
	'ci_clinic_report' => true,
	'ci_lost_providers' => true,
	'ci_lab_flow' => true,
	'ci_data_forms' => true,
	'ci_current_clients' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'form_pcr' => true,
	'ci_resutls_pcr' => true,
	'form_gene' => true,
	'ci_results_gene' => true,
	'ci_ins_mix' => true,
	'ci_data_store' => true,
	'ci_results' => true,
	'ci_manage_reps' => true,
	'ci_batches_view' => true
));

Tr_Base_Class_V4::create_role('lab_manager', 'Sales regional manager', array(
	'clinic_statistics' => true,
	'clinic_support' => true,
	'tox_report' => true,
	'ci_data_reports' => true,
	'ci_clinic_report' => true,
	'ci_lost_providers' => true,
	'ci_lab_flow' => true,
	'ci_data_forms' => true,
	'ci_current_clients' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'form_pcr' => true,
	'ci_resutls_pcr' => true,
	'form_gene' => true,
	'ci_results_gene' => true,
	'ci_ins_mix' => true,
	'ci_data_store' => true,
	'ci_results' => true,
	'ci_manage_reps' => true,
	'ci_batches_view' => true
)); //Regional sales

Tr_Base_Class_V4::create_role('lab_rep', 'Lab Rep', array(
	'ci_data_reports' => true,
	'ci_lost_providers' => true,
	'ci_lab_flow' => true,
	'ci_data_forms' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'form_allergy' => true,
	'ci_results_allergy' => true,
	'form_pcr' => true,
	'form_gene' => true,
	'ci_data_store' => true,
));


Tr_Base_Class_V4::create_role('lab_rep_w2', 'Lab Rep W2', array(
	'ci_data_reports' => true,
	'ci_lost_providers' => true,
	'ci_lab_flow' => true,
	'ci_data_forms' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'form_allergy' => true,
	'ci_results_allergy' => true,
	'form_pcr' => true,
	'form_gene' => true,
	'ci_data_store' => true,
));


Tr_Base_Class_V4::create_role('labmanager', 'LabManager', array(
	'ci_receiving' => true,
	'form_custom_profiles' => true,
	'ci_batches_view' => true,
	'ci_tecan_processing' => true,
	'ci_results' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'ci_data_forms' => true,
	'ci_current_clients' => true,
	'ci_data_reports' => true,
	'ci_audit_report' => true,
	'ci_lost_providers' => true,
	'ci_lab_flow' => true,
	'ci_patient_chart' => true,
));


Tr_Base_Class_V4::create_role('doctor', 'Doctor', array(
	'clinic_statistics' => true,
	'clinic_support' => true,
	'tox_report' => true,
	'ci_data_store' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'form_lab_test_req' => true,
	'form_pcr' => true,
	'form_gene' => true,
	'ci_test_results' => true,
	'ci_patient_chart' => true,
	'ci_patient_trends' => true,
	'ci_abx_report' => true,
));


Tr_Base_Class_V4::create_role('Phlebotomist', 'Phlebotomist', array(
	'form_blood' => true,
	'ci_standing_orders' => true,
	'ci_phlebotomist' => true,
));




Tr_Base_Class_V4::create_role('collector', 'Collector', array(
	'ci_data_store' => true,
	'form_requisition' => true,
	'ci_results_ref_tox_upload' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'ci_results_dna' => true,
	'form_pcr' => true,
	'ci_resutls_pcr' => true,
	'form_gene' => true,
	'ci_results_gene' => true,
));


Tr_Base_Class_V4::create_role('office_manager', 'Office Manager', array(
	'clinic_statistics' => true,
	'clinic_support' => true,
	'tox_report' => true,
	'ci_data_forms' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'form_lab_test_req' => true,
	'form_allergy' => true,
	'form_pcr' => true,
	'form_gene' => true,
	'ci_data_store' => true,
	'ci_test_results' => true,
	'ci_patient_chart' => true,
	'ci_patient_trends' => true,
	'ci_received_list' => true,
	'ci_abx_report' => true,
));

//like office manager but always see all clinics
Tr_Base_Class_V4::create_role('multi_site_manager', 'Multi-Site Manager', array(
	'clinic_statistics' => true,
	'clinic_support' => true,
	'tox_report' => true,
	'ci_data_forms' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'form_lab_test_req' => true,
	'form_allergy' => true,
	'form_pcr' => true,
	'form_gene' => true,
	'ci_data_store' => true,
	'ci_test_results' => true,
	'ci_patient_chart' => true,
	'ci_patient_trends' => true,
	'ci_received_list' => true,
));

Tr_Base_Class_V4::create_role('labsecman', 'Lab Sec Man', array(
	'ci_data_forms' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'ci_short_list' => true,
	'ci_batches_view' => true,
	'ci_receiving' => true,
	'ci_results' => true,
	'ci_consumable_inv' => true,
));

Tr_Base_Class_V4::create_role('leadlabsecman', 'Lead Lab Sec Man', array(
	'ci_data_forms' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'ci_short_list' => true,
	'ci_batches_view' => true,
	'ci_receiving' => true,
	'ci_results' => true,
	'ci_consumable_inv' => true,
	'ci_custody_chain' => true,
));


Tr_Base_Class_V4::create_role('lab_sec', 'Lab Sec', array(
	'ci_data_forms' => true,
	'form_requisition' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
	'form_lab_test_req' => true,
	'ci_short_list' => true,
	'ci_batches_view' => true,
	'ci_receiving' => true,
	'ci_results' => true,
	'ci_consumable_inv' => true,
));

Tr_Base_Class_V4::create_role('ResultsUpload', 'ResultsUpload', array(
	'ci_results' => true
));


Tr_Base_Class_V4::create_role('RefManager', 'Ref Manager', array(
	'ci_batches_view' => true,
	'ci_results' => true,
));

Tr_Base_Class_V4::create_role('sales_accounting', 'Sales Accounting', array(
	'ci_manage_reps' => true,
	'ci_data_reports' => true,
	'ci_data_store' => true,
	'ci_data_forms' => true,
));


//Tr_Base_Class_V4::create_role('clinic','Clinic');

Tr_Base_Class_V4::create_role('scientist', 'Scientist', array(
	'ci_results' => true,
	'ci_batches_view' => true,
	'ci_patient_chart' => true,
));

Tr_Base_Class_V4::create_role('shipping', 'Shipping', array(
	'ci_data_forms' => true,
	'ci_current_clients' => true,
	'ci_customers' => true,
	'ci_data_reports' => true,
));




Tr_Base_Class_V4::create_role('Billing', 'Billing', array(
	'ci_data_forms' => true,
));


Tr_Base_Class_V4::create_role('distributor', 'Distributor', array(
	'ci_data_reports' => true,
	'ci_lost_providers' => true,
	'ci_lab_flow' => true,
	'ci_manage_reps' => true,
	'ci_data_forms' => true,
	'form_requisition' => true,
	'form_lab_test_req' => true,
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'form_allergy' => true,
	'form_pcr' => true,
	'form_gene' => true,
	'ci_data_store' => true,
	'ci_manage_clinician_groups' => true,
	'ci_current_clients' => true,
));


Tr_Base_Class_V4::create_role('results', 'Results', array(
	'ci_results_tox_ref' => true,
	'ci_results_dna' => true,
	'ci_results_blood' => true,
	'ci_resutls_pcr' => true,
	'ci_resutls_gene' => true,
	'ci_patient_chart' => true,
	'ci_patient_trends' => true,
	'ci_results' => true,
	'ci_test_results' => true,
));


Tr_Base_Class_V4::create_role('pcr_verify', 'PCR Verification', array(
	'form_pcr' => true,
	'ci_resutls_pcr' => true,
	'ci_resutls_pcr_verification' => true,
));



Tr_Base_Class_V4::create_role('BloodLabManager', 'BloodLabManager', array(
	'form_blood' => true,
	'ci_phlebotomist' => true,
	'ci_results_blood' => true,
));


Tr_Base_Class_V4::create_role('administrator_p', 'Administrator-WP', array(
	'read' => true, // true allows this capability
	'edit_posts' => true, // Allows user to edit their own posts
	'edit_pages' => true, // Allows user to edit pages
	'edit_others_posts' => true, // Allows user to edit others posts not just their own
	'create_posts' => true, // Allows user to create new posts
	'manage_categories' => true, // Allows user to manage post categories
	'publish_posts' => true, // Allows the user to publish, otherwise posts stays in draft mode
	'edit_themes' => false, // false denies this capability. User can�t edit your theme
	'install_plugins' => false, // User cant add new plugins
	'update_plugin' => false, // User can�t update any plugins
	'update_core' => false, // user cant perform core updates
	'edit_users' => true,
	'edit_user' => true,
	'create_users' => true,
	'list_users' => true,
	'edit_dashboard' => true,
) + $all_lis_caps);


Tr_Base_Class_V4::create_role('wp_admin', 'WP Admin', tr_pdd_core_wp_caps());

Tr_Base_Class_V4::create_role('user_management', 'User Management', array(
	'read' => true, // true allows this capability

	'edit_users' => true,
	'edit_user' => true,
	'create_users' => true,
	'manage_network_users' => true,
	'do_not_allow' => true,
	'promote_users' => true,
	'list_users' => true,

	'ci_data_forms' => true,
	'ci_security_ban' => true,
));



Tr_Base_Class_V4::create_role('AdminCIDW', 'Administrator+CIDW', array(
	'read' => true, // true allows this capability

	'edit_users' => true,
	'edit_user' => true,
	'create_users' => true,
	'manage_network_users' => true,
	'do_not_allow' => true,
	'promote_users' => true,
	'list_users' => true,
	'ci_edit_statuses' => true,
) + $all_lis_caps);

Tr_Base_Class_V4::create_role('IT', 'IT', array(
	'read' => true, // true allows this capability

	'edit_users' => true,
	'edit_user' => true,
	'create_users' => true,
	'manage_network_users' => true,
	'do_not_allow' => true,
	'promote_users' => true,
	'list_users' => true,
	'ci_add_notices' => true,
) + $all_lis_caps);


Tr_Base_Class_V4::create_role('Lab_Reporting', 'Lab Reporting', array(
	'ci_turnaround_time' => true,
	'ci_processed_samples' => true,
	'ci_report_summary' => true,
	'ci_data_reports' => true,
));

$administrator = get_role('administrator');
foreach ($all_lis_caps as $cap => $arg) {
	$administrator->add_cap($cap);
}

$roleuser = get_role('AdminCIDW');
foreach ($all_lis_caps as $cap => $arg) {
	$roleuser->add_cap($cap);
}

$roleuser = get_role('IT');
$roleuser->add_cap('ci_add_notices');

$roleuser = get_role('administrator_p');
$roleuser->add_cap('ci_add_notices');

$all_roles      = get_editable_roles();
foreach ($all_roles as $rk => $rolearr) {
	$role = get_role($rk);
	if (isset($rolearr['capabilities']['form_blood']) && $rolearr['capabilities']['form_blood']) {
		$role->add_cap('ci_phlebotomist');
	}
	foreach (array('ci_prep_station', 'ci_send_welcome') as $deprecated_cap) {
		if (isset($rolearr['capabilities'][$deprecated_cap])) {
			$role->remove_cap($deprecated_cap);
		}
	}
}

//remove roles
remove_role('patient');
