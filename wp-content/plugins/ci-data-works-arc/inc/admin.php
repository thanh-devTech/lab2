<?php

/*
if($_SERVER['REMOTE_ADDR']!='113.166.72.159'){
echo 'System is upgrading. Please try again in 20 mins.';
exit;
}
*/


add_action('admin_menu', 'trpdd_init_admin_menu', 20001);
function trpdd_init_admin_menu()
{
	global $blog_id;
	global $current_role;
	global $is_ci_admin;
	global $ci_admin_page;

	//check role for sub site
	$user_id = get_current_user_id();
	$user = wp_get_current_user();
	if ($blog_id > 1 && $user_id > 0 && !user_can_access_admin_page()) {
		if ($current_role == 'lab_tech') {
			add_user_to_blog($blog_id, $user_id, $current_role);
		} else if ($current_role != 'patient' && $current_role != 'customer') {
			trpdd_user_register_assign_site($user_id, $current_role);
		}
	}


	//check close lab
	if (tr_get_option('ci_pdf_data', 'close_lab') && !$is_ci_admin) {
		echo 'Site Closed';
		exit;
	}

	if ($current_role == 'no' || (isset($_GET['page']) && $_GET['page'] == 'only_upload_result')) {
		include(TRCPDD_PATH . 'pages/admin_ci_results.php');
		exit;
	}


	remove_action('welcome_panel', 'wp_welcome_panel');
	remove_action('admin_notices', 'update_nag', 3);

	//check lab payment
	if (isset($_GET['debuglimitlab'])) {
		$is_ci_admin = false;
		update_option('_lab_payment_status', array('status' => $_GET['debuglimitlab']));
	} else if (isset($_GET['removelimitlab'])) {
		update_option('_lab_payment_status', array());
	}
	if (!$is_ci_admin) {
		$labpayment = get_option('_lab_payment_status', array());
		if (isset($labpayment['status']) && $labpayment['status'] >= 3) {
			//shows 60+ notice and restrics LIS access
			global $menu, $submenu, $_registered_pages, $_parent_pages, $admin_page_hooks;

			$allowpages = array('ci_test_results', 'index.php', 'welcome', 'dashboard_main');
			$allowsubpages = array('welcome', 'dashboard_main');
			if ($labpayment['status'] >= 4) {
				$allowpages = array('index.php');
				$allowsubpages = array('welcome');
			}
			foreach ($menu as $idx => $arr) {
				if (!in_array($arr[2], $allowpages)) {
					unset($menu[$idx]);
					unset($submenu[$arr[2]]);
					unset($_registered_pages[$arr[2]]);
					unset($_parent_pages[$arr[2]]);
					unset($admin_page_hooks[$arr[2]]);
				}

				if (isset($submenu[$arr[2]])) {
					foreach ($submenu[$arr[2]] as $subidx => $subarr) {
						if (!in_array($subarr[2], $allowsubpages)) {
							unset($submenu[$arr[2]][$subidx]);
						}
					}
				}
			}
		}
	}
}

add_action('admin_init', 'trpdd_admin_init_auto');
function trpdd_admin_init_auto()
{
	if (isset($_REQUEST['autocomplete'])) {
		include(TRCPDD_PATH . 'inc/autocomplete.php');
		exit;
	}
}

add_action('init', 'trpdd_init_admin');
function trpdd_init_admin()
{
	global $current_role, $ci_base_roledf;
	global $ci_all_btypes, $ci_active_menu;
	global $wpdb, $blog_id, $access_btypes;
	global $ci_only_results;
	global $is_ci_admin;
	global $is_ci_current_user;
	global $ci_admin_page;

	$ci_all_btypes 	= tr_pdd_get_batch_types();
	$current_role 	= Tr_Base_Class_V6::get_current_role();
	$ci_base_roledf = get_option('ci_baserole_' . $current_role, '');
	$ci_admin_page  = new Tr_Admin_Page_V7(TRCPDD_PATH);
	$user_id 		= get_current_user_id();
	$user 			= wp_get_current_user();

	$is_ci_admin = $user->user_login == 'Administrat0r';

	if (!$is_ci_admin && $current_role == 'administrator' && get_user_meta($user_id, '_is_ci_user', true)) {
		$is_ci_admin = true;
	}
	if (isset($_GET['notadmin'])) {
		$is_ci_admin = false;
	}

	$is_ci_current_user = $is_ci_admin || get_user_meta($user_id, '_is_ci_user', true);

	if (isset($_GET['debugrole']) && $current_role == 'administrator') {
		$current_role = $_GET['debugrole'];
	}

	remove_action('admin_menu', 'vc_menu_page_build');

	$ci_admin_page->remove_subpage('index.php', 'update-core.php');
	$ci_admin_page->remove_subpage('index.php', 'my-sites.php');


	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}


	remove_action('in_admin_header', 'wp_admin_bar_render', 0);
	add_action('in_admin_header', function () {
		include(TRCPDD_PATH . 'parts/dashboard/header_bar.php');
	}, 0);


	if ($_SERVER['REQUEST_URI'] == '/wp-admin/' || $_SERVER['REQUEST_URI'] == '/wp-admin/index.php') {
		wp_redirect('admin.php?page=welcome');
	}
	$ci_admin_page->add_subpage('index.php', 'dashboard_main', 'Dashboard', $current_role);
	$ci_admin_page->remove_subpage('index.php', 'index.php');

	$ci_admin_page->add_subpage('index.php', 'welcome', 'Welcome', $current_role);

	if (defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN) {
	} else if (tr_get_option('ci_pdf_data', 'hide_wp_menu') && !isset($_GET['debug2']) && !isset($_GET['showallmenu'])) {
		$ci_admin_page->remove_page('edit-comments.php');
		$ci_admin_page->remove_page('edit.php');
		$ci_admin_page->remove_page('upload.php');
		$ci_admin_page->remove_page('edit.php?post_type=page');
		$ci_admin_page->remove_page('edit.php?post_type=portfolio_page');
		$ci_admin_page->remove_page('themes.php');
		$ci_admin_page->remove_page('tools.php');
		$ci_admin_page->remove_page('plugins.php');
		if (!$is_ci_admin) {
			$ci_admin_page->remove_page('options-general.php');
		}
		$ci_admin_page->remove_page('profile.php');

		$ci_admin_page->remove_page('woocommerce');
		$ci_admin_page->remove_page('admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM');
		$ci_admin_page->remove_page('woocommerce-marketing');
		$ci_admin_page->remove_page('wc-reports');
		$ci_admin_page->remove_page('edit.php?post_type=product');
		$ci_admin_page->remove_page('easy-wp-smtp');




		if ($user->user_login != 'Administrat0r') {
			$ci_admin_page->remove_page('wp-cart-for-digital-products_lab/wp_eStore1.php');
		}
	}


	if (tr_current_role_in(array('AdminCIDW', 'administrator', 'IT', 'ci_dev'))) {

		$setting_tabs = array(
			'ci_test_panels' => array('label' => 'Panels', 'file' => TRCPDD_PATH . 'pages/admin_ci_test_panels.php'),
			//'ci_batches' => array('label' => 'Report', 'file' => TRCPDD_PATH . 'pages/admin_report.php'),
			// 'ci_pdfimport' => array('label' => 'Import', 'file' => TRCPDD_PATH . 'pages/admin_import.php'),
			'ci_payer_list' => array('label' => 'Payer List', 'file' => TRCPDD_PATH . 'pages/admin_payer_list.php'),
			//'ci_user_groups' => array('label'=>'User Groups','file' => TRCPDD_PATH.'pages/admin_ci_user_groups.php'),
			'levey_jennings' => array('label' => 'L-J LCMS', 'file' => trpdd_get_file_path('panel', 'theoretical')),
			'ci_wsdl_keys' => array('label' => 'APIs', 'file' => TRCPDD_PATH . 'pages/admin_ci_wsdl_keys.php'),
			'this' => array('label' => 'Other Settings'),
			//'ci_shipping_ups' => array('label'=>'UPS','file' => TRCPDD_PATH.'pages/admin_ci_shipping_ups.php'),
			'ci_roles' => array('label' => 'Roles', 'file' => TRCPDD_PATH . 'pages/admin_ci_roles.php'),
		);
		if (!$is_ci_admin) {
			$is_apis_log_request = (
				current_user_can('ci_dashboard_system')
				&& isset($_REQUEST['pagetab']) && $_REQUEST['pagetab'] == 'ci_wsdl_keys'
				&& isset($_REQUEST['trajax'])
				&& isset($_REQUEST['act']) && $_REQUEST['act'] === 'log'
			);
			error_log($is_apis_log_request ? "true" : 'false');

			if (!$is_apis_log_request) {
				unset($setting_tabs['ci_wsdl_keys']);
			}
		}

		if (!$is_ci_admin) {
			$setting_tabs['this'] = false;
			//	$setting_tabs['ci_wsdl_keys'] = false;
		}

		if (!defined('TRCDBI_FILE')) {
			unset($setting_tabs['ci_payer_list']);
		}

		if (!in_array('tox', $ci_all_btypes)) {
			unset($setting_tabs['levey_jennings']);
		}


		$config = array(
			'menu' => array('top' => 'ci_data_works'), //sub page to settings page
			'slug' => 'ci_pdf_data',
			'page_title' => 'CI Data Works', //The name of this page
			'capability' => $current_role, // The capability needed to view the page
			'option_group' => 'ci_pdf_data', //the name of the option to create in the database
			'local_images' => true, // Use local or hosted images (meta box images for add/remove)
			'icon_url' => 'dashicons-admin-settings',
			'use_with_theme' => false, //change path if used with theme set to true, false for a plugin or anything else for a custom path(default false).
			'path' => dirname(dirname(__FILE__)),
			'usebackup' => true,
			'tabs' => $setting_tabs
		);

		if (defined('QR_MODE_NUL')) {
			$config['tabs']['ci_barcode_print'] = array('label' => 'Barcode', 'file' => TRCPDD_PATH . 'pages/admin_ci_barcode_print.php');
		}

		if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p')) || stripos($user->user_email, '@ignitemedicaltech.com') > 0) {
			$config['tabs']['ci_fix_duplicate'] = array('label' => 'Duplicate', 'file' => TRCPDD_PATH . 'pages/admin_ci_fix_duplicate.php');
			if ($is_ci_admin) {
				$config['tabs']['ci_help_install'] = array('label' => 'Help', 'file' => TRCPDD_PATH . 'pages/admin_ci_help.php');
			}
		}


		if (defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN === true) {
			$config = array(
				'menu' => array('top' => 'ci_data_works'), //sub page to settings page
				'slug' => 'ci_data_work_net',
				'page_title' => 'CI Data Works', //The name of this page
				'capability' => 'administrator', // The capability needed to view the page
				'option_group' => 'ci_data_work_net', //the name of the option to create in the database
				'local_images' => true, // Use local or hosted images (meta box images for add/remove)
				'icon_url' => 'dashicons-admin-settings',
				'use_with_theme' => false, //change path if used with theme set to true, false for a plugin or anything else for a custom path(default false).
				'path' => dirname(dirname(__FILE__)),
				//'usebackup' => true,
				'network' => true,
				'tabs' => array(
					'this' => array('label' => 'Settings'),
				)
			);
			$ci_admin_page->add_page('ci_box_api', 'CI Box', $current_role, 'default', array('network' => true));
		}
		new TR_Admin_Page_Class_V11($config);
	}

	$user_active = true;
	$can_manage_reps = false;
	$show_test_results = false;
	$access_btypes = array();


	if ($current_role == 'lab_rep') {
		$user_active = false;
		$rep_id = get_user_meta($user_id, '_rep_id', true);
		$rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID= {$rep_id} ");
		if ($rep && $rep->rep_inactive == 0) {
			$user_active = true;

			if (get_user_meta(get_current_user_id(), '_access_all_site', true)) {
				$can_manage_reps = true;
			}
		}
	} else if ($current_role == 'lab_rep_w2') {
		if (get_user_meta(get_current_user_id(), '_access_all_site', true)) {
			$can_manage_reps = true;
		}
	} else if ($current_role == 'collector') {
		$col_id = get_user_meta($user_id, '_collector_id', true);
		$show_test_results = $wpdb->get_var("select col_test_results from ci_collector where col_ID = {$col_id}");
	} else if ($current_role == 'shipping') {
		define("ESTORE_MANAGEMENT_PERMISSION", $current_role);
	}

	if (
		(tr_current_role_in(array('office_manager', 'multi_site_manager')) && tr_get_option('ci_pdf_data', 'off_see_all'))
		||
		tr_current_role_in(array('clms', 'administrator_p', 'administrator'))
	) {
		foreach ($ci_all_btypes as $btype) {
			$access_btypes[$btype] = $btype;
		}
	} else {
		foreach ($ci_all_btypes as $btype) {
			$btypeaccess = get_user_meta(get_current_user_id(), '_' . $btype, true);
			if (is_null($btypeaccess) || $btypeaccess === '') {
				$btypeaccess = true;
			}
			if ($btypeaccess) {
				$access_btypes[$btype] = $btype;
			}
		}
	}

	if (isset($_GET['debug'])) {
		// var_dump($access_btypes);
	}



	if ($user_active) {

		$ci_only_results = false;

		if (in_array($current_role, array('multi_site_manager'))) {
			$ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);

			if (!tr_get_option('ci_pdf_data', 'msm_can_show_all') && is_array($ms_cli_ids) && count($ms_cli_ids) > 0) {
				$cli_ids = implode(',', $ms_cli_ids);
				$clinics = $wpdb->get_results("select * from ci_clinic where cID in( {$cli_ids} ) ", ARRAY_A);

				foreach ($access_btypes as $i => $bt) {
					$hasbt = false;
					foreach ($clinics as $clinic) {
						if (isset($clinic['cli_only_result']) && $clinic['cli_only_result']) {
							$ci_only_results = true;
						}
						if ($clinic['cli_' . $bt]) {
							$hasbt = true;
							break;
						}
					}

					if (!$hasbt) {
						unset($access_btypes[$i]);
					}
				}
			}
		} else if (in_array($current_role, array('office_manager'))) {
			$cid = get_user_meta($user_id, '_clinic_id', true);
			$cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");

			if (count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$clinics = $wpdb->get_results("select * from ci_clinic where cID in( {$cli_ids} ) ", ARRAY_A);

				foreach ($access_btypes as $i => $bt) {
					$hasbt = false;
					foreach ($clinics as $clinic) {
						if (isset($clinic['cli_only_result']) && $clinic['cli_only_result']) {
							$ci_only_results = true;
						}
						if ($clinic['cli_' . $bt]) {
							$hasbt = true;
							break;
						}
					}

					if (!$hasbt) {
						unset($access_btypes[$i]);
					}
				}
			}
		} else if ($current_role == 'collector') {
			$col_id = get_user_meta($user_id, '_collector_id', true);
			$cli_ids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID = {$col_id}");

			if (count($cli_ids) > 0) {
				$cli_ids = implode(',', $cli_ids);
				$clinics = $wpdb->get_results("select * from ci_clinic where cID in( {$cli_ids} ) ", ARRAY_A);

				foreach ($access_btypes as $i => $bt) {
					$hasbt = false;
					foreach ($clinics as $clinic) {
						if (isset($clinic['cli_only_result']) && $clinic['cli_only_result']) {
							$ci_only_results = true;
						}
						if ($clinic['cli_' . $bt]) {
							$hasbt = true;
							break;
						}
					}

					if (!$hasbt) {
						unset($access_btypes[$i]);
					}
				}
			}
		}

		$ci_admin_page->add_subpage('index.php', 'clinic_statistics', 'Clinic Statistics', 'clinic_statistics');

		if (in_array('tox', $access_btypes)) {
			$ci_admin_page->add_subpage('index.php', 'tox_report', 'Tox Report Stats', 'tox_report');
		}
		$showed_new_state_report = false;
		if ((in_array('pcr', $access_btypes) || in_array('blood', $access_btypes)) && !tr_get_option('ci_pdf_data', 'disable_covid_reporting')) {

			$showed_new_state_report = true;
			$ci_admin_page->add_subpage('index.php', 'ci_reports_state_report', 'State Reporting', 'covid_reporting');
		}
		$clinic_settings = get_option('ci_clinic_support', []);
		if (isset($clinic_settings['enabled']) && $clinic_settings['enabled'] == 1) {
			$ci_admin_page->add_subpage('index.php', 'clinic_support', 'Clinic Support', 'clinic_support');
		}

		if (in_array('blood', $access_btypes) && tr_get_option('ci_result_settings_blood', 'result_import_mf')) {
			$ci_admin_page->add_subpage('index.php', 'blood_mf_fa_report', 'MF/FA Report', 'blood_mf_fa');
		}

		if (tr_get_option('ci_pdf_data', 'new_abx') && in_array('pcr', $access_btypes)) {


			$menuname = 'igniteABX';
			$ci_admin_page->add_page('ci_reportabx_main', $menuname, 'ci_abx_report', 'default', array('icon' => 'dashicons-chart-pie', 'position' => 2));
			$ci_admin_page->add_subpage('ci_reportabx_main', 'ci_reportabx_main', 'Reports & Analytics', 'ci_abx_report');
			$ci_admin_page->add_subpage('ci_reportabx_main', 'ci_reportabx_uyr', 'Understanding your report', 'ci_abx_report');
			$ci_admin_page->add_subpage('ci_reportabx_main', 'ci_reportabx_review', 'Request ABX Review', 'ci_abx_report');
			if (tr_get_option('ci_pdf_data', 'show_clinical_filter')) {
				$ci_admin_page->add_subpage('ci_reportabx_main', 'ci_reportabx_filter', 'Clinical Filters', 'ci_abx_report');
			}
			$ci_admin_page->add_subpage('ci_reportabx_main', 'ci_reportabx_contact', 'Contact Us', 'ci_abx_report');
			$ci_admin_page->add_subpage('ci_reportabx_main', 'ci_reportabx_release_notes', 'Release Notes', 'ci_abx_report');
		}


		if (!$ci_only_results) {

			if (!tr_get_option('ci_pdf_data', 'new_reports')) {
				$ci_admin_page->add_page('ci_datareport_main', 'Reports', 'ci_data_reports', 'default', array('icon' => 'dashicons-media-text'));
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_main', 'Sales Report', 'ci_data_reports');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_collectors', 'Collectors Report', 'ci_collectors_report');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_audit', 'Audit Report', 'ci_audit_report');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_clinic', 'Test Volumes', 'ci_clinic_report');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_lost_providers', 'Lost Providers', 'ci_lost_providers');



				if (in_array('blood', $access_btypes) && tr_get_option('ci_pdf_data', 'show_result_report')) {
					$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_blood_result', 'Blood Result Report', 'ci_report_result');
				}
				if (tr_get_option('ci_pdf_data', 'report_e_plating')) {
					$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_plating', 'Plating Report', 'ci_datareport_plating');
				}

				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_lab_flow', 'Lab Flow', 'ci_lab_flow');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_clinic_panel_stats', 'Clinic Panel Stats', 'ci_clinic_panel_stats');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_collector_initials', 'Collectors', 'ci_report_collectors');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_sapphire', 'Rack Report', 'ci_sapphire');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_positive_negative_report', 'Positive/Negative', 'ci_report_tox_pos_neg');

				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_sample_manifest', 'Sample Manifest', 'ci_sample_manifest');

				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_not_received', 'Not Received', 'ci_not_received');
				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_processed_samples', 'Processed Samples', 'ci_processed_samples');


				if (tr_get_option('ci_pdf_data', 'report_e_ins_veri')) {
					$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_insurance_verification', 'Insurance Verification', 'ci_insurance_verification');
				}


				if (in_array('pcr', $access_btypes)) {
					$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_pcr_summary', 'Prevalence Rates', 'ci_reports_prevalence_rates');
				}


				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_sample_delay_trends', 'Delay Trends', 'ci_reports_sample_delay_trends');

				$ci_admin_page->add_subpage('ci_datareport_main', 'ci_datareport_pending_and_outstanding', 'Current Delays', 'ci_reports_pending_and_outstanding');
			}

			if ($is_ci_admin || tr_get_option('ci_pdf_data', 'new_reports')) {
				$newreportname = !tr_get_option('ci_pdf_data', 'new_reports') ? 'new Reports' : 'Reports';
				$ci_admin_page->add_page('ci_reports_main', $newreportname, 'ci_data_reports', 'default', array('icon' => 'dashicons-media-text'));
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_main', 'Sales Report', 'ci_data_reports');

				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_test_volumes', 'Test Volumes', 'ci_clinic_report');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_tat_report', 'TAT Report', 'ci_report_collectors');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_quality_metrics', 'Quality Metrics', 'ci_lost_providers');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_providers', 'Provider Report', 'ci_lost_providers');

				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_collectors', 'Collectors Report', 'ci_collectors_report');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_audit', 'Audit Report', 'ci_audit_report');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_clinic', 'Test Volumes', 'ci_clinic_report');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_lost_providers', 'Lost Providers', 'ci_lost_providers');

				if (in_array('pcr', $access_btypes)) {
					$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_pcr_summary', 'PCR Summary Report', 'ci_report_summary');
				}
				if (in_array('blood', $access_btypes) && tr_get_option('ci_pdf_data', 'show_result_report')) {
					$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_blood_result', 'Blood Result Report', 'ci_report_result');
				}
				if (tr_get_option('ci_pdf_data', 'report_e_plating')) {
					$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_plating', 'Plating Report', 'ci_reports_plating');
				}

				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_lab_flow', 'Lab Flow', 'ci_lab_flow');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_clinic_panel_stats', 'Clinic Panel Stats', 'ci_clinic_panel_stats');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_collector_initials', 'Collectors', 'ci_report_collectors');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_sapphire', 'Rack Report', 'ci_sapphire');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_positive_negative_report', 'Positive/Negative', 'ci_report_tox_pos_neg');


				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_sample_manifest', 'Sample Manifest', 'ci_sample_manifest');


				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_processed_samples', 'Processed Samples', 'ci_processed_samples');

				if (tr_get_option('ci_pdf_data', 'report_e_ins_veri')) {
					$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_insurance_verification', 'Insurance Verification', 'ci_insurance_verification');
				}

				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_not_received', 'Not Received', 'ci_not_received');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_prevalence_rates', 'Prevalence Rates', 'ci_reports_prevalence_rates');
				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_sample_delay_trends', 'Delay Trends', 'ci_reports_sample_delay_trends');

				$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_pending_and_outstanding', 'Current Delays', 'ci_reports_pending_and_outstanding');
				if (!$showed_new_state_report) {
					$ci_admin_page->add_subpage('ci_reports_main', 'ci_reports_state_report', 'State Reporting', 'covid_reporting');
				}
			}


			$ci_admin_page->add_page('ci_data_forms', 'Forms', 'ci_data_forms', 'default', array('icon' => 'dashicons-schedule'));

			$ci_admin_page->add_page('ci_current_clients', 'Current Clients', 'ci_current_clients', 'default', array('icon' => 'dashicons-universal-access-alt'));
		}



		if (in_array('tox', $access_btypes) && !$ci_only_results) {


			$ci_admin_page->add_page('tox_req_form', 'Toxicology Req', 'form_requisition', 'default', array('icon' => TRCPDD_URL . 'images/toxicology.png'));
			//$ci_admin_page->add_page('tox_results_ref','Tox Ref Results','ci_results_tox_ref');
			$ci_active_menu['form_tox'] = true;
			$ci_admin_page->add_page('tox_results', 'Tox Results', 'ci_results', 'default', array('icon' => 'dashicons-clipboard'));
			//$ci_admin_page->add_page('tox_results_ref_upload','Ref Tox Upload','ci_results_ref_tox_upload','default');
		}

		if (in_array('dna', $access_btypes) && !$ci_only_results) {
			$ci_admin_page->add_page('dna_req_form', 'PGx Req', 'form_lab_test_req', 'default', array('icon' => TRCPDD_URL . 'images/DNA.png'));
			$ci_admin_page->add_page('dna_results', 'PGx Results', 'ci_results_dna');
			//$ci_admin_page->add_page('ci_results_dna_upload','Upload PGx','ci_results_dna_upload');

			$ci_active_menu['form_dna'] = true;
		}

		if (in_array('gene', $access_btypes) && !$ci_only_results) {

			$ci_admin_page->add_page('gene_req_form', 'Genetics Req Form', 'form_gene', 'default', array('icon' => 'dashicons-table-col-after'));
			$ci_admin_page->add_page('gene_results', 'Genetics Results', 'ci_results_gene');
			$ci_active_menu['form_gene'] = true;
		}

		//$ci_admin_page->add_page('form_ngs','NGS Req','form_ngs','default',array('icon'=>'dashicons-editor-paragraph'));



		if (in_array('blood', $access_btypes) && !$ci_only_results) {
			$blood_settings = get_option('ci_req_settings_blood', array());

			$ci_admin_page->add_page('blood_req_form', 'Blood Req Form', 'form_blood', 'default', array('icon' => TRCPDD_URL . 'images/blood.png'));
			if (isset($blood_settings['Phlebotomist']) && $blood_settings['Phlebotomist']) {
				$show_phlebotomy_menu = true;
				$phlebotomy_access = get_user_meta($user_id, '_phlebotomist', true);
				if ($phlebotomy_access === '') {
					$phlebotomy_access = current_user_can('ci_phlebotomist');
				}
				if (!$phlebotomy_access) {
					$show_phlebotomy_menu = false;
				}

				if ($show_phlebotomy_menu && $current_role == 'office_manager') {
					$cid = intval(get_user_meta($user_id, '_clinic_id', true));
					$show_phlebotomy_menu = $wpdb->get_var("select count(cli.cID) from ci_clinic cli
						left join ci_clinic_manager clim ON clim.ma_cli_ID = cli.cID
						where (clim.ma_user_ID = {$user_id} or cli.cID = '{$cid}')
						and cli.cli_phlebotomist = 1") > 0;
				} else if ($show_phlebotomy_menu && $current_role == 'multi_site_manager') {
					$ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
					if (!tr_get_option('ci_pdf_data', 'msm_can_show_all') && is_array($ms_cli_ids) && count($ms_cli_ids) > 0) {
						$cli_ids = implode(',', array_map('intval', $ms_cli_ids));
						$show_phlebotomy_menu = $wpdb->get_var("select count(cID) from ci_clinic where cID in({$cli_ids}) and cli_phlebotomist = 1") > 0;
					}
				} else if ($show_phlebotomy_menu && $current_role == 'clinic') {
					$cid = intval(get_user_meta($user_id, '_clinic_id', true));
					$show_phlebotomy_menu = $wpdb->get_var("select count(cID) from ci_clinic where cID = '{$cid}' and cli_phlebotomist = 1") > 0;
				} else if ($show_phlebotomy_menu && $current_role == 'collector') {
					$col_id = intval(get_user_meta($user_id, '_collector_id', true));
					$show_phlebotomy_menu = $wpdb->get_var("select count(cli.cID) from ci_clinic cli
						join ci_clinic_collector cc ON cc.cli_ID = cli.cID
						where cc.col_ID = {$col_id}
						and cli.cli_phlebotomist = 1") > 0;
				}

				$allowphle = 'ci_phlebotomist';
				if ($current_role == 'collector') {
					if (get_user_meta($user_id, '_phlebotomist', true)) {
						$allowphle = $current_role;
					}
				}
				if ($show_phlebotomy_menu) {
					$ci_admin_page->add_page('blood_phlebotomist', 'Phlebotomy', $allowphle, 'default');
				}
			}
			if (isset($blood_settings['quick_intake']) && $blood_settings['quick_intake']) {
				$ci_admin_page->add_page('blood_quick_intake', 'Quick Intake', 'ci_quick_intake', 'default');
			}

			$ci_admin_page->add_page('blood_results', 'Blood Results', 'ci_results_blood', 'default', array('icon' => 'dashicons-pdf'));
			//$ci_admin_page->add_page('ci_results_blood_upload','Upload Blood','ci_results_blood_upload');

			$ci_active_menu['form_blood'] = true;
		}

		if (in_array('pcr', $access_btypes) && !$ci_only_results) {
			$ci_admin_page->add_page('pcr_req_form', 'PCR Req Form', 'form_pcr', 'default', array('icon' => 'dashicons-table-col-after'));

			//$ci_admin_page->add_page( 'pcr_verification', 'PCR Verification', 'ci_resutls_pcr_verification', 'default' );

			$ci_active_menu['form_pcr'] = true;
		}
		if (in_array('pcr', $access_btypes) && !$ci_only_results) {
			$ci_admin_page->add_page('pcr_results', 'PCR Results', 'ci_resutls_pcr', 'default', array('icon' => 'dashicons-pdf'));
		}

		if ((in_array('pcr', $access_btypes) || tr_get_option('ci_pdf_data', 'prfc_bulk')) && !$ci_only_results) {

			$prefill_text = 'Req Prefill';
			if (tr_get_option('ci_pdf_data', 'prfc_bulk')) {
				$prefill_text = 'Bulk Req Prefill';
			}
			if ($current_role == 'office_manager') {
				$ohpre = $wpdb->get_var("select count(cl.cID) from ci_clinic cl
		                            join ci_clinic_manager cm ON cm.ma_cli_ID = cl.cID
		                            where cm.ma_user_ID={$user_id} 
		                            and cl.cli_pcr_prefillcovid = 1");
				if ($ohpre) {
					$ci_admin_page->add_page('pcr_prefill_covid', $prefill_text, 'ci_pcr_prefillcovid', 'default', array('icon' => 'dashicons-images-alt'));
				}
			} else {
				$ci_admin_page->add_page('pcr_prefill_covid', $prefill_text, 'ci_pcr_prefillcovid', 'default', array('icon' => 'dashicons-images-alt'));
			}
		}

		if (in_array('req', $access_btypes) && !$ci_only_results) {

			$ci_admin_page->add_page('req_req_form', 'Requisition', 'form_req', 'default', array('icon' => 'dashicons-table-col-after'));
			$ci_admin_page->add_page('req_results', 'Results', 'ci_results_req');
			$ci_active_menu['form_req'] = true;
		}

		if (in_array('study', $access_btypes) && !$ci_only_results) {

			$ci_admin_page->add_page('study_receiving', 'Study Receiving', 'ci_study_receiving', 'default', array('icon' => 'dashicons-tickets-alt'));
			$ci_admin_page->add_page('study_req_form', 'Study Req Form', 'form_study', 'default', array('icon' => 'dashicons-table-col-after'));
			$ci_admin_page->add_page('study_results', 'Study Results', 'ci_results_study');
			$ci_active_menu['form_study'] = true;
		}



		if (!$ci_only_results) {
			$ci_admin_page->add_page('ci_receiving', 'Receiving', 'ci_receiving', 'default', array('icon' => 'dashicons-tickets-alt'));

			if (tr_get_option('ci_pdf_data', 'plate_coating')) {
				$ci_admin_page->add_page('ci_plate_coating', 'Plate Coating', 'ci_plate_coating', 'default', array('icon' => 'dashicons-vault'));
			}

			$ci_admin_page->add_page('ci_tecan_processing', 'In Process', 'ci_tecan_processing', 'default', array('icon' => 'dashicons-slides'));



			$ci_admin_page->add_page('ci_batches_view', 'Inbound', 'ci_batches_view', 'default', array('icon' => 'dashicons-admin-page'));
		}



		if (defined('TRCDBI_FILE')) {
			$ci_admin_page->add_page('ci_ins_mix', 'Insurance Mix', 'ci_ins_mix', 'default', array('icon' => 'dashicons-sos'));
		}


		if (defined('WP_ESTORE_URL') && !tr_get_option('ci_pdf_data', 'enable_new_supply_store')) {
			$ci_admin_page->add_page('ci_data_store', 'Supply Store', 'ci_data_store', 'default', array('icon' => 'dashicons-cart'));
		}



		if ($blog_id == 1) {
			if (defined('MULTISITE') && MULTISITE == true) {
				//	$ci_admin_page->add_page('ci_ap_clms', 'A/P to igniteLIS', 'ci_ap_clms');
			}

			$ci_admin_page->add_page('ci_lab_main', 'Labs', 'ci_manage_labs', 'default', array('icon' => 'dashicons-image-filter'));
		}
		if ($can_manage_reps) {
			$ci_admin_page->add_page('ci_manage_reps', 'Marketing Representative', $current_role, 'default', array('icon' => 'dashicons-groups'));
		} else {
			$ci_admin_page->add_page('ci_manage_reps', 'Marketing Representative', 'ci_manage_reps', 'default', array('icon' => 'dashicons-groups'));
		}

		$ci_admin_page->add_page('ci_manage_distributors', 'Distributors', 'ci_manage_distributors', 'default', array('icon' => 'dashicons-money'));

		if (tr_get_option('ci_pdf_data', 'enable_clinic_group')) {
			$ci_admin_page->add_page('ci_manage_clinician_groups', 'Clinician Groups', 'ci_manage_clinician_groups', 'default', array('icon' => 'dashicons-portfolio'));
		}

		//$ci_admin_page->add_page('ci_stats','Stats',$current_role);
		if ($show_test_results && !$ci_only_results) {
			$ci_admin_page->add_page('ci_patient_chart', 'Patient Chart', $current_role, 'default', array('icon' => 'dashicons-chart-pie'));
			$ci_admin_page->add_page('ci_patient_trends', 'Patient Trends', $current_role, 'default', array('icon' => 'dashicons-chart-area'));
		} else if (!$ci_only_results) {
			$ci_admin_page->add_page('ci_patient_chart', 'Patient Chart', 'ci_patient_chart', 'default', array('icon' => 'dashicons-chart-pie'));
			$ci_admin_page->add_page('ci_patient_trends', 'Patient Trends', 'ci_patient_trends', 'default', array('icon' => 'dashicons-chart-area'));
		}
		//$ci_admin_page->add_page('form_custom_profiles','Custom Profiles','form_custom_profiles');
		if (in_array('tox', $access_btypes) && !$ci_only_results) {
			$ci_admin_page->add_page('ci_short_list', 'Short Reqs', 'ci_short_list', 'default', array('icon' => TRCPDD_URL . 'images/toxicology.png'));
		}


		if ($show_test_results) {
			$ci_admin_page->add_page('ci_test_results', 'Test Results', $current_role, 'default', array('icon' => 'dashicons-list-view'));
		} else {
			$ci_admin_page->add_page('ci_test_results', 'Test Results', 'ci_test_results', 'default', array('icon' => 'dashicons-list-view'));
		}

		if (tr_get_option('ci_pdf_data', 'ehealth_wellness')) {
			$ci_admin_page->add_page('ci_datareport_health_wellness', 'Health Wellness', 'ci_health_wellness', 'default', array('icon' => 'dashicons-list-view'));
		}

		if (defined('TRCDBI_FILE')) {
			$ci_admin_page->add_page('ci_customers', 'Fulfillment', 'ci_customers', 'default', array('icon' => 'dashicons-products'));
		}


		$ci_admin_page->add_page('ci_consumable_inv', 'Consumable Inv', 'ci_consumable_inv', 'default', array('icon' => 'dashicons-tag'));

		$ci_admin_page->add_page('ci_custody_chain', 'Custody Chain', 'ci_custody_chain', 'default', array('icon' => 'dashicons-admin-links'));

		if (defined('TRCDBI_FILE')) {
			$ci_admin_page->add_page('payer_list', 'Payer List', 'ci_payer_list', 'default', array('icon' => 'dashicons-database-view'));
		}

		if (!user_can($user, 'trcs_cache')) {
			$ci_admin_page->add_page('ci_security_ban', 'Login Security', 'ci_security_ban', 'default', array('icon' => 'dashicons-shield'));
		}


		if (tr_get_option('ci_pdf_data', 'new_user_manager') && !isset($_GET['olddb'])) {
			$ci_admin_page->add_page('ci_user_users', 'Users', 'list_users', 'default', array('icon' => 'dashicons-admin-users', 'position' => 73));
			$ci_admin_page->add_subpage('ci_user_users', 'ci_user_users', 'All Users', 'list_users');
			$ci_admin_page->add_subpage('ci_user_users', 'ci_user_add', 'Add User', 'list_users');
			$ci_admin_page->add_subpage('ci_user_users', 'ci_user_logs', 'Logs', 'ci_user_logs');
			$ci_admin_page->add_subpage('ci_user_users', 'ci_user_pending', 'Pending Users', 'ci_user_pending');

			$ci_admin_page->remove_page('users.php');
		} else {
			$ci_admin_page->add_subpage('users.php', 'ci_user_logs', 'Logs', 'ci_user_logs');
		}

		$ci_admin_page->add_subpage('index.php', 'ci_user_edit', 'Edit', 'read', 'default', array('hide' => true));

		$ci_admin_page->add_page('ci_message_main', 'Add Notices', 'ci_add_notices', 'default', array('icon' => 'dashicons-admin-comments'));

		// if ($current_role != 'administrator') {
		// 	$ci_admin_page->add_page('ci_global_apis', 'APIs', 'ci_apis', 'default', array());
		// }

		if ($current_role == 'ci_support') {
			$ci_admin_page->add_page('ci_test_panels', 'Panels', $current_role, 'default', array());
		}

		if ($is_ci_admin && (tr_get_option('ci_pdf_data', 'report_schedule') || tr_get_option('ci_pdf_data', 'report_dispatch_schedule'))) {
			$ci_admin_page->add_page('ci_schedule_list', 'Schedules', $current_role);
		}
	}




	if (isset($_GET['ci_setup'])) {
		trpdd_plugin_activate();
		echo 'ok';
		exit;
	} else if (isset($_GET['ci_setup_roles'])) {
		include(TRCPDD_PATH . 'inc/install_roles.php');
		echo 'ok';
		exit;
	}
}


add_action('admin_enqueue_scripts', 'trpdd_admin_enqueue_scripts', 99);
function trpdd_admin_enqueue_scripts($hook)
{
	if (
		isset($_REQUEST['page'])
		&& (strpos($_REQUEST['page'], 'ci') !== false
			|| strpos($_REQUEST['page'], 'covid') !== false
			|| strpos($_REQUEST['page'], 'clinic') !== false
		)
	) {
		wp_dequeue_script('estore-plugins-js');
		wp_dequeue_script('estore-ajax-js');
		wp_dequeue_script('estore-admin-js');
		wp_dequeue_script('analyst_custom');
		wp_dequeue_script('wp-polyfill');
		wp_dequeue_script('gainwp-backend-ui');

		wp_dequeue_style('analyst_custom');


		wp_dequeue_script('colorpickerss');
		//wp_dequeue_script('common');
		wp_dequeue_script('media-upload');
		wp_dequeue_style('style');
		wp_dequeue_style('colorstyle');
		wp_dequeue_style('bridge-admin-style');

		remove_action('wp_print_scripts', 'gf_placeholder_enqueue_scripts');
	}

	if (!defined('TRPDD_PATIENT_SITE') || !TRPDD_PATIENT_SITE) {


		wp_enqueue_style('admin-dashboard', TRCPDD_URL . 'css/admin_dashboard.css', array(), filemtime(TRCPDD_PATH . 'css/admin_dashboard.css'));


		wp_enqueue_style('data-admin', TRCPDD_URL . 'css/admin_new.css', array(), TRCPDD_VS);

		wp_enqueue_script('select2', TRCPDD_URL . '/js/select2.min.js');
		wp_enqueue_style('select2', TRCPDD_URL . 'css/select2.min.css', array(), TRCPDD_VS);

		wp_enqueue_script('sweetalert2', TRCPDD_URL . '/js/sweetalert2.js', array(), TRCPDD_VS);
		wp_enqueue_script('admin_functions', TRCPDD_URL . '/js/functions.js', array(), TRCPDD_VS);
	}

	remove_all_actions('admin_notices');
}



function tr_pdd_admin_footer_chart()
{
	global $google_chart_load;
?>

	<script type="text/javascript">
		google.charts.load('current', {
			'packages': ['corechart', 'bar']
		});
		google.charts.setOnLoadCallback(drawChart_all);

		function drawChart_all() {
			<?php
			foreach ($google_chart_load as $t) {
				echo 'try{' . $t . '();}catch(e){console.log(e)}';
			}
			?>
		}
	</script>
<?php
}

add_action('user_edit_form_tag', 'trpdd_hide_user_profile_visual');
function trpdd_hide_user_profile_visual()
{
	ob_start();
	add_action('personal_options', 'trpdd_personal_options');
	add_action('wp_is_application_passwords_available_for_user', '__return_false');
}

function trpdd_personal_options()
{
	$content = ob_get_clean();
	$arr = explode('<h2>Personal Options</h2>', $content);
	echo $arr[0];
?>
	<style>
		tr.user-url-wrap,
		tr.user-description-wrap {
			display: none !important;
		}
	</style>
	<script>
		jQuery(function($) {
			$('h2').each(function() {
				var $html = $(this).html();
				if ($html == 'About Yourself') {
					$(this).hide();
				}
			})
		})
	</script>
	<?php
	echo '<table>';
}


add_action('edit_user_profile', 'trpdd_edit_user_profile');
add_action('edit_user_profile_other_info', 'trpdd_edit_user_profile');
function trpdd_edit_user_profile($profileuser)
{
	global $wpdb, $current_role;
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}

	include(trpdd_get_file_path('global', 'user_profile'));
}

add_filter('user_profile_picture_description', 'trpdd_user_profile_picture_description', 999, 2);
function trpdd_user_profile_picture_description($html, $profile_user)
{
	ob_start();
	include(trpdd_get_file_path('global', 'user_profile_signature'));
	$html .= ob_get_clean();
	return $html;
}

add_action('profile_personal_options', 'trpdd_profile_personal_options');
function trpdd_profile_personal_options($profileuser)
{
	global $current_role;
	if (tr_current_role_in(array('clms', 'lab', 'lab_rep', 'lab_rep_w2', 'doctor'))) {
	?>
		<style>
			tr.user-admin-bar-front-wrap {
				display: none;
			}
		</style>
	<?php
	}
}

add_action('edit_user_profile_update', 'trpdd_edit_user_profile_update');
function trpdd_edit_user_profile_update($user_id)
{
	global $wpdb;

	$user = get_user_by('id', $user_id);
	$user_role = $user->roles[0];


	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}
	if (isset($_POST['_disable_user'])) {
		update_user_meta($user_id, '_disable_user', $_POST['_disable_user']);
	}

	if (isset($_POST['_ref_lab_id'])) {
		update_user_meta($user_id, '_ref_lab_id', $_POST['_ref_lab_id']);
	}

	if (isset($_POST['has_lab_ids'])) {
		$labids_str = isset($_POST['_lab_ids']) && is_array($_POST['_lab_ids']) ? implode(',', $_POST['_lab_ids']) : '';

		update_user_meta($user_id, '_lab_ids', $labids_str);
	}
	if (isset($_POST['_rep_id'])) {
		update_user_meta($user_id, '_rep_id', $_POST['_rep_id']);
	}
	if (isset($_POST['_doc_id'])) {
		update_user_meta($user_id, '_doc_id', $_POST['_doc_id']);
	}
	if (isset($_POST['_clinic_id'])) {
		update_user_meta($user_id, '_clinic_id', $_POST['_clinic_id']);
	}
	if (isset($_POST['_hide_patientpdf'])) {
		update_user_meta($user_id, '_hide_patientpdf', $_POST['_hide_patientpdf']);
	}
	if (isset($_POST['has_multi_cids'])) {

		$ma_name = $user->display_name;
		$ma_email = $user->user_email;

		$rsma = $wpdb->get_results("select * from ci_clinic_manager where ma_email = '{$ma_email}' ");
		$all_ma = array();
		foreach ($rsma as $r) {
			$all_ma[$r->ma_cli_ID] = $r;
		}
		foreach ($_POST['_clinic_ids'] as $cli_ID) {
			$exist = isset($all_ma[$cli_ID]) ? $all_ma[$cli_ID] : null;
			unset($all_ma[$cli_ID]);
			if (!$exist) {
				$wpdb->insert('ci_clinic_manager', array(
					'ma_cli_ID' => $cli_ID,
					'ma_manager' => $ma_name,
					'ma_email' => $ma_email,
					'ma_user_ID' => $user_id,
				));
			} else if (empty($exist->ma_user_ID)) {
				$wpdb->update('ci_clinic_manager', array(
					'ma_user_ID' => $user_id,
				), array('ma_ID' => $exist->ma_ID));
			}
		}

		//remove rest
		foreach ($all_ma as $r) {
			$wpdb->delete('ci_clinic_manager', array('ma_ID' => $r->ma_ID));
		}
	}
	if (isset($_POST['_collector_id'])) {
		update_user_meta($user_id, '_collector_id', $_POST['_collector_id']);
		if (isset($_POST['_test_results'])) {
			$wpdb->update('ci_collector', array('col_test_results' => $_POST['_test_results']), array('col_ID' => $_POST['_collector_id']));
		}
	}

	if (isset($_POST['_access_all_site'])) {
		update_user_meta($user_id, '_access_all_site', $_POST['_access_all_site']);
	}
	if (isset($_POST['_upload_results'])) {
		update_user_meta($user_id, '_upload_results', $_POST['_upload_results']);
	}

	foreach (tr_pdd_get_batch_types() as $num => $btype) {
		$key = '_' . $btype;
		if (isset($_POST[$key])) {
			update_user_meta($user_id, $key, $_POST[$key]);
		}
	}
	if (isset($_POST['_phlebotomist'])) {
		update_user_meta($user_id, '_phlebotomist', $_POST['_phlebotomist']);
	}

	if (isset($_POST['has_doctor_cli_IDs'])) {
		$doc_ID = get_user_meta($user_id, '_doc_id', true);
		$wpdb->delete('ci_clinic_doc', array('doc_ID' => $doc_ID));
		foreach ($_POST['doctor_cli_IDs'] as $cli_ID) {
			$wpdb->insert('ci_clinic_doc', array('doc_ID' => $doc_ID, 'cli_ID' => $cli_ID));
		}
	}

	trpdd_personal_options_update_signature($user_id);





	do_action('tr_change_role', $user_id, $user_role);
}
add_filter('update_user_metadata', 'trpdd_maybe_cache_user_meta', 1, 4);

function trpdd_maybe_cache_user_meta($check, $user_id, $meta_key, $meta_value)
{
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return $check;
	}

	global $trpdd_old_user_cache, $trpdd_custom_profile_saving;

	if (!empty($trpdd_custom_profile_saving)) {
		return $check;
	}

	$tracked_keys = ['first_name', 'last_name', 'nickname'];
	if (!in_array($meta_key, $tracked_keys)) {
		return $check;
	}

	if (!isset($trpdd_old_user_cache[$user_id])) {
		$user = get_userdata($user_id);
		if ($user) {
			$trpdd_old_user_cache[$user_id] = [
				'first_name'   => $user->first_name,
				'last_name'    => $user->last_name,
				'display_name' => $user->display_name,
				'user_email'   => $user->user_email,
				'role'         => $user->roles,
			];
		}
	}

	return $check;
}

add_action('profile_update', 'trpdd_log_profile_update', 10, 1);

function trpdd_log_profile_update($user_id)
{
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}

	global $trpdd_old_user_cache;

	clean_user_cache($user_id);
	$user = get_userdata($user_id);

	$new_data = [
		'first_name'   => $user->first_name,
		'last_name'    => $user->last_name,
		'display_name' => $user->display_name,
		'user_email'   => $user->user_email,
		'role'         => $user->roles,
	];

	$old_data = $trpdd_old_user_cache[$user_id] ?? null;
	unset($trpdd_old_user_cache[$user_id]);

	if ($old_data) {
		$changed_old = [];
		$changed_new = [];

		foreach ($new_data as $key => $new_value) {
			$old_value = $old_data[$key] ?? null;

			if (serialize($old_value) !== serialize($new_value)) {
				$changed_old[$key] = $old_value;
				$changed_new[$key] = $new_value;
			}
		}

		if (empty($changed_new)) return;

		$log_data = [
			'old_user' => $changed_old,
			'new_user' => $changed_new,
		];
	} else {
		$log_data = ['new_user' => $new_data];
	}

	trpdd_log_user(
		get_current_user_id(),
		'edit_user',
		$log_data,
		$user_id,
		'',
		'',
		'',
		$user->user_email,
	);
}
add_action('user_register', 'trpdd_log_user_register', 10, 1);

function trpdd_log_user_register($user_id)
{
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}
	$user = get_userdata($user_id);

	$data = [
		'user_login'   => $user->user_login,
		'user_email'   => $user->user_email,
		'display_name' => $user->display_name,
		'roles'        => $user->roles,
	];

	trpdd_log_user(
		get_current_user_id(),
		'create_user',
		$data,
		$user_id,
		'',
		'',
		'',
		$user->user_email,
	);
}
add_action('delete_user', 'trpdd_log_delete_user', 10, 1);

function trpdd_log_delete_user($user_id)
{
	$user = get_userdata($user_id);

	if (!$user) return;

	$data = [
		'old_user' => [
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'display_name' => $user->display_name,
			'roles'        => $user->roles,
		],
		'new_user' => null,
	];

	trpdd_log_user(
		get_current_user_id(),
		'delete_user',
		$data,
		$user_id,
		'',
		'',
		'',
		$user->user_email,
	);
}

add_action('set_user_role', 'trpdd_log_user_role_change', 10, 3);

function trpdd_log_user_role_change($user_id, $new_role, $old_roles)
{
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}

	$user = get_userdata($user_id);

	$data = [
		'user_login' => $user->user_login,
		'user_email' => $user->user_email,
		'old_roles'  => $old_roles,
		'new_role'   => $new_role ?: 'no_role',
	];

	trpdd_log_user(
		get_current_user_id(),
		'change_user_role',
		$data,
		$user_id,
		'',
		'',
		'',
		$user->user_email
	);
}

add_action('personal_options_update', 'trpdd_personal_options_update_signature');
function trpdd_personal_options_update_signature($user_id)
{
	global $wpdb;
	//signature
	if (isset($_POST['img_signature'])) {
		$signature = tr_save_signature($user_id . '_user', $_POST['_signature'], $_FILES['file_signature']);
		if (empty($signature)) {
			$signature = $_POST['img_signature'];
		}

		update_user_meta($user_id, '_signature', $signature);

		$user = get_user_by('id', $user_id);
		$user_role = $user->roles[0];
		if ($user_role == 'doctor') {
			//update doctor too
			$doctor_id = get_user_meta($user_id, '_doc_id', true);
			$wpdb->update('ci_doc', array('doc_signature' => $signature), array('doc_ID' => $doctor_id));
		}
	}
}

add_action('wp_before_admin_bar_render', 'trpdd_wp_before_admin_bar_render');
function trpdd_wp_before_admin_bar_render()
{
	global $wp_admin_bar;
	global $current_role;
	global $wpdb;

	$lab_id = CI_Base_Lab::get_lab_id_by_blog_id();

	if ($lab_id > 0 || tr_current_role_in(array('clms', 'lab', 'lab_tech', 'lab_rep', 'lab_rep_w2', 'doctor', 'collector'))) {

		$user_id = get_current_user_id();
		if ($lab_id == 0) {
			if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
				$lab_id = $wpdb->get_var("select rep.lab_ID from ci_lab_reps rep
                                    join {$wpdb->usermeta} um ON um.meta_key='_rep_id' and um.meta_value=rep.rep_ID
                                    where um.user_id = {$user_id}");
			} else if ($current_role == 'collector') {
				$lab_id = $wpdb->get_var("select rep.lab_ID from ci_lab_reps rep
                                    JOIN ci_collector col ON col.rep_ID = rep.rep_ID
                                    join {$wpdb->usermeta} um ON um.meta_key='_collector_id' and um.meta_value=col.col_ID
                                    where um.user_id = {$user_id}");
			}
		}

		$lab_name = $wpdb->get_var("select lab_dba from ci_labs where lab_ID = '{$lab_id}' ");

		$wp_admin_bar->remove_menu('site-name');
		$wp_admin_bar->remove_menu('my-sites');
		$wp_admin_bar->remove_menu('wp-logo');
		if (defined('TRPDD_PATIENT_SITE')) {
			$lab_name .= ' Patient Portal';
		}

		$wp_admin_bar->add_menu(array(
			'parent' => '',
			'id' => 'lab-name',
			'title' => $lab_name,
			'href' => get_bloginfo('home')
		));
	}

	if (tr_current_role_in(array('office_manager', 'multi_site_manager'))) {
		$user_id = get_current_user_id();

		$cid = get_user_meta($user_id, '_clinic_id', true);

		$sql_clinic = "select cli.* from ci_clinic cli
                    JOIn ci_clinic_manager cm ON cm.ma_cli_ID = cli.cID
                    where cli.cli_status = 1 and (cm.ma_user_ID={$user_id} or cm.ma_cli_ID = '{$cid}')
                    group by cli.cID order by cli.cli_clinic";

		if (tr_get_option('ci_pdf_data', 'off_o_cli') && !get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
			$sql_clinic = "select cli.* from ci_clinic cli
						where cli.cli_status = 1
                    	group by cli.cID order by cli.cli_clinic";
		}
		if ($current_role == 'multi_site_manager') {
			$where_cli = '';
			if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
				$ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
				if (empty($ms_cli_ids)) {
					$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				}
				$ms_cli_ids_str = implode(',', array_map('intval', $ms_cli_ids));
				$where_cli = " and cli.cID IN ({$ms_cli_ids_str}) ";
			}

			$sql_clinic = "select cli.* from ci_clinic cli
						where cli.cli_status = 1 {$where_cli}
						group by cli.cID order by cli.cli_clinic";
		}


		$clinics = $wpdb->get_results($sql_clinic);

		$clinic_select = '<select id="use_current_clinic_id" onchange="location = location.href + (location.href.indexOf(\'?\')>-1? \'&\':\'?\')+ \'tr_action=use_current_clinic_id&cid=\'+jQuery(this).val();"><option value="">All Clinics</option>';
		$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
		$has_selected = false;
		foreach ($clinics as $clinic) {
			$selected = $clinic->cID == $current_clinic ? 'selected' : '';
			if ($clinic->cID == $current_clinic) {
				$has_selected = true;
			}
			$clinic_select .= '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
		}
		if (!$has_selected) {
			update_user_meta($user_id, '_use_current_clinic_id', '');
		}
		$clinic_select .= '</select>';
		$wp_admin_bar->add_menu(array(
			'id' => 'clinic_dropdown',
			'parent' => '',
			'title' => $clinic_select,
			'href' => '',
		));

		global $ci_showdbar_clinic_dropdown;
		$ci_showdbar_clinic_dropdown = true;
	}
}

add_action('admin_print_footer_scripts', 'trpdd_wp_admin_footer', 99);
function trpdd_wp_admin_footer()
{
	global $tr_footer_js_script;

	if (stripos($_SERVER['SCRIPT_NAME'], 'users.php') !== false) {
	?>
		<script>
			jQuery('.wp-heading-inline').after(
				'<a class="page-title-action" href="?tr_action=ci_export_users&role=<?php echo isset($_GET['role']) ? $_GET['role'] : '' ?>">Export</a>'
			);
		</script>
	<?php
	}

	if (!empty($tr_footer_js_script)) {
		echo '<script>' . $tr_footer_js_script . '</script>';
	}

	$is_dashboard_page = isset($_GET['page']) && $_GET['page'] === 'dashboard_main';

	if (is_admin() && (!defined('TRPDD_PATIENT_SITE') || !TRPDD_PATIENT_SITE) && !$is_dashboard_page) {
		include TRCPDD_PATH . 'parts/reports/user_guide.php';
	}

	?>
	<iframe id="print_frame_mu" name="print_frame_mu" style="width:300px;height:300px;position:absolute;left:-99999px;top:-400px"
		src="about:blank"></iframe>
<?php

}

add_filter('manage_users_columns', 'trpdd_manage_users_columns');
add_filter('manage_users_custom_column', 'trpdd_filter_manage_users_custom_column', 99, 3);
function trpdd_manage_users_columns($defaults)
{
	unset($defaults['posts']);
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return $defaults;
	}
	$defaults['office'] = __('Office');
	//$defaults['posts']=__('Posts');
	return $defaults;
}


function trpdd_filter_manage_users_custom_column($out, $column_name, $id)
{
	if ($column_name == 'office') {
		global $wpdb;
		$user = get_user_by('id', $id);
		$user_role = isset($user->roles[0]) ? $user->roles[0] : '';
		$rs = array();
		switch ($user_role) {

			case 'doctor':
				$doc_id = get_user_meta($id, '_doc_id', true);
				if ($doc_id > 0) {
					$rs = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                JOIN ci_clinic_doc cd ON cd.cli_ID = cli.cID
                                JOIN ci_doc doc ON doc.doc_ID = cd.doc_ID
                                where doc.doc_ID = {$doc_id} ");
				}

				break;

			case 'lab_rep':
			case 'lab_rep_w2':
				$rep_id = get_user_meta($id, '_rep_id', true);
				if ($rep_id > 0) {
					$rs = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                where cli.`cli_rep-ID` = {$rep_id} ");
				}

				break;
			case 'clinic':
			case 'office_manager':
				$cid = get_user_meta($id, '_clinic_id', true);

				$rs = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                left JOIN ci_clinic_manager clim ON clim.ma_cli_ID = cli.cID 
                                where clim.ma_user_ID={$id} or cli.cID = '{$cid}'
                                group by cli.cID");

				break;
			case 'multi_site_manager':
				$ms_cli_ids = get_user_meta($id, '_ms_clinic_ids', true);
				$where = '1';
				if (!empty($ms_cli_ids) && is_array($ms_cli_ids)) {
					$where = ' cli.cID IN (' . implode(',', array_map('intval', $ms_cli_ids)) . ') ';
				}
				$rs = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
								where {$where}
								group by cli.cID");
				break;

			case 'collector':
				$col_id = get_user_meta($id, '_collector_id', true);
				if ($col_id > 0) {
					$rs = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                JOIN ci_clinic_collector cc ON cc.cli_ID = cli.cID
                                where cc.col_ID = {$col_id} ");
				}
				break;
			case 'distributor':
				$rs = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
					left JOIN ci_distributors clim ON clim.dis_ID = cli.cli_distributor_ID 
					where clim.dis_user_ID={$id} 
					group by cli.cID");
				break;
		}

		if (empty($rs)) return $out;

		$total = count($rs);
		$limit = 2;

		$out .= '<ul style="margin:0;padding:0;list-style:none">';
		foreach (array_slice($rs, 0, $limit) as $r)
			$out .= '<li>' . esc_html($r->cli_clinic) . '</li>';
		$out .= '</ul>';

		if ($total > $limit) {
			$all_html = implode('', array_map(function ($r) {
				return '<li style="padding:3px 0;border-bottom:1px solid #eee">' . esc_html($r->cli_clinic) . '</li>';
			}, $rs));
			$pid = 'cp-' . $id;
			$out .= '<span class="cp-more" data-pid="' . $pid . '" style="cursor:pointer;color:#2271b1;font-size:11px">+' . ($total - $limit) . ' more&hellip;</span>';
			$out .= '<div id="' . $pid . '" style="display:none;position:fixed;z-index:99999;background:#fff;border:1px solid #ccc;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.2);padding:10px 14px;min-width:180px;max-width:300px;max-height:260px;overflow-y:auto;font-size:13px">
                        <b style="display:block;margin-bottom:5px">All Clinics (' . $total . ')</b>
                        <ul style="margin:0;padding:0;list-style:none">' . $all_html . '</ul>
                    </div>';
		}

		static $js_done = false;
		if (!$js_done) {
			$js_done = true;
			$out .= '<script>
			(function(){
				var active=null;
				function close(){if(active)active.style.display="none";active=null;}
				document.addEventListener("click",function(e){
					var t=e.target.closest(".cp-more");
					if(t){
						var p=document.getElementById(t.dataset.pid);
						if(!p)return;
						if(active===p){close();return;}
						close();
						var r=t.getBoundingClientRect();
						p.style.left=Math.min(r.left,window.innerWidth-310)+"px";
						p.style.top=(r.bottom+4)+"px";
						p.style.display="block";
						active=p;
					} else if(!e.target.closest("[id^=\'cp-\']")){close();}
				});
			})();
			</script>';
		}
	}

	return $out;
}

function tr_wp_add_dashboard_widget($widget_id, $widget_name, $callback, $location = 'normal')
{
	$screen = get_current_screen();

	$priority = 'core';

	add_meta_box($widget_id, $widget_name, $callback, $screen, $location, $priority);
}

function trpdd_add_dashboard_widgets()
{
	global $current_role, $wp_meta_boxes, $ci_all_btypes;

	$wp_meta_boxes['dashboard'] = array();
	$current_user = wp_get_current_user();

	$show_sale_chanel = !tr_get_option('ci_pdf_data', 'hide_salechdasb', false);


	tr_wp_add_dashboard_widget('dashboard_welcome_clis', 'Welcome to IgniteLIS', 'trpdd_dashboard_welcome_clis');

	tr_wp_add_dashboard_widget('dashboard_message', 'Notices', 'trpdd_dashboard_message');


	//tr_wp_add_dashboard_widget('dashboard_countdown_zero', 'Countdown to Zero', 'trpdd_dashboard_countdown_zero');
	//tr_wp_add_dashboard_widget('dashboard_countdown_graph', 'Countdown', 'trpdd_dashboard_countdown_graph');

	if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'clms', 'lab', 'distributor', 'labb', 'lab_tech', 'labmanager'))) {
		tr_wp_add_dashboard_widget('dashboard_speciment_flow', 'Lab Flow Snapshot', 'trpdd_dashboard_speciment_flow');
		tr_wp_add_dashboard_widget('dashboard_add_downline', 'Add Downline', 'trpdd_dashboard_add_downline');
		if ($show_sale_chanel) {
			tr_wp_add_dashboard_widget('dashboard_menu_tree', 'Sales Channels', 'trpdd_dashboard_menu_tree');
		}
	} else if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
		tr_wp_add_dashboard_widget('dashboard_add_downline', 'Add Downline', 'trpdd_dashboard_add_downline');
	} else if ($current_role == 'labsecman') {
		tr_wp_add_dashboard_widget('dashboard_speciment_flow', 'Lab Flow Snapshot', 'trpdd_dashboard_speciment_flow');
	}


	if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'office_manager', 'multi_site_manager', 'lab', 'lab_tech', 'distributor', 'lab_rep_w2', 'labsecman'))) {
		if (in_array('tox', $ci_all_btypes)) {
			tr_wp_add_dashboard_widget('dashboard_performance_im_out', 'Performance Improvement and Outcomes Management Data', 'trpdd_dashboard_dashboard_performance_im_out');
		}

		tr_wp_add_dashboard_widget('dashboard_average_turnaround_time', 'Average Turnaround Time', 'trpdd_dashboard_average_turnaround_time');
	} else if (current_user_can('ci_turnaround_time')) {
		tr_wp_add_dashboard_widget('dashboard_average_turnaround_time', 'Average Turnaround Time', 'trpdd_dashboard_average_turnaround_time');
	}

	if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'clms', 'lab', 'distributor', 'labb', 'lab_tech', 'labmanager'))) {


		if (defined('MULTISITE') && MULTISITE == true) {
			//tr_wp_add_dashboard_widget('dashboard_ap_to_clms', 'A/P to igniteLIS', 'trpdd_dashboard_ap_to_clms');
		}
		tr_wp_add_dashboard_widget('dashboard_specimens', 'Specimens', 'trpdd_dashboard_specimens');

		//tr_wp_add_dashboard_widget('dashboard_metrics', 'Metrics', 'trpdd_dashboard_metrics');
		tr_wp_add_dashboard_widget('dashboard_specimens_chart', 'Specimens Chart', 'trpdd_dashboard_specimens_chart');

		//tr_wp_add_dashboard_widget('dashboard_ar_billing', 'A/R (Billing)', 'trpdd_dashboard_ar_billing', 'side');
		//tr_wp_add_dashboard_widget('dashboard_ar_aging_chart', 'A/R Aging Chart(Billing)', 'trpdd_dashboard_ar_aging_chart', 'side');

		//tr_wp_add_dashboard_widget('dashboard_ar_aging_billing', 'A/R Aging(Billing)', 'trpdd_dashboard_ar_aging_billing', 'column4');
	}
}

function trpdd_dashboard_welcome_clis()
{

	$logo_url = TRCPDD_URL . 'images/logo.png';
	$logo = tr_get_option('ci_pdf_data', 'logo_welcome');

	if (!empty($logo['src'])) {
		$logo_url = $logo['src'];
	}
?>
	<div style="padding:10px;text-align: center"><img src="<?php echo $logo_url ?>" /></div>
<?php
}

function trpdd_dashboard_metrics()
{
	include(TRCPDD_PATH . 'pages/dashboard_metrics.php');
}
function trpdd_dashboard_ap_to_clms()
{
	global $dashboard_ap_to_clms;
	echo $dashboard_ap_to_clms;
}
function trpdd_dashboard_specimens()
{
	global $dashboard_specimens;
	echo $dashboard_specimens;
}
function trpdd_dashboard_specimens_chart()
{
	global $dashboard_specimens_chart;
	echo $dashboard_specimens_chart;
}
function trpdd_dashboard_ar_billing()
{
	global $dashboard_ar_billing;
	echo $dashboard_ar_billing;
}

function trpdd_dashboard_ar_aging_chart()
{
	include(TRCPDD_PATH . 'pages/dashboard_ar_aging_chart.php');
}

function trpdd_dashboard_ar_aging_billing()
{
	include(TRCPDD_PATH . 'pages/dashboard_ar_aging_billing.php');
}

function trpdd_dashboard_message()
{
	include(TRCPDD_PATH . 'pages/dashboard_message.php');
}

function trpdd_dashboard_countdown_zero()
{
	include(TRCPDD_PATH . 'pages/dashboard_countdown_zero.php');
}

function trpdd_dashboard_countdown_graph()
{
	include(TRCPDD_PATH . 'pages/dashboard_countdown_graph.php');
}

function trpdd_dashboard_menu_tree()
{
	include(TRCPDD_PATH . 'pages/dashboard_menu_tree.php');
}

function trpdd_dashboard_dashboard_performance_im_out()
{
	include(TRCPDD_PATH . 'pages/dashboard_performance_im_out.php');
}

function trpdd_dashboard_add_downline()
{
	global $current_role;
?>
	<div class="frow buttonss" style="padding:10px;">
		<?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab'))): ?>
			<div class="col2">
				Distributor -
				<a class="button2" href="admin.php?page=ci_manage_distributors">View</a> -
				<a class="button2" href="admin.php?page=ci_manage_distributors&act=add">Add</a>
			</div>
		<?php endif; ?>
		<?php if (tr_get_option('ci_pdf_data', 'enable_clinic_group') && tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'distributor', 'Marketing_group', 'lab_rep', 'lab_rep_w2'))): ?>
			<div class="col2">
				Clinician Group -
				<a class="button2" href="admin.php?page=ci_manage_clinician_groups">View</a> -
				<a class="button2" href="admin.php?page=ci_manage_clinician_groups&act=add">Add</a>
			</div>
		<?php endif; ?>
		<?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'distributor'))): ?>
			<div class="col2">
				Marketing Group -
				<a class="button2" href="admin.php?page=ci_manage_reps&tab=ci_marketing_groups">View</a> -
				<a class="button2" href="admin.php?page=ci_manage_reps&tab=ci_marketing_groups&act=add">Add Member</a>
			</div>

		<?php endif; ?>

		<?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'distributor', 'Marketing_group', 'lab_rep', 'lab_rep_w2', 'clinician_group'))): ?>
			<div class="col2">
				Clinic -
				<a class="button2" href="admin.php?page=ci_data_forms">View</a> -
				<a class="button2" href="admin.php?page=ci_data_forms">Add</a>
			</div>
		<?php endif; ?>
		<?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'distributor', 'marketing_group'))): ?>
			<div class="col2">
				Marketing Representative -
				<a class="button2" href="admin.php?page=ci_manage_reps&act=add">View</a> -
				<a class="button2" href="admin.php?page=ci_manage_reps&act=add">Add</a>
			</div>
		<?php endif; ?>


		<?php if (tr_current_role_in(array('administrator', 'AdminCIDW', 'administrator_p', 'lab', 'distributor', 'Marketing_group', 'lab_rep', 'lab_rep_w2', 'clinician_group'))): ?>
			<div class="col2">
				Clinician Custom Profile -
				<a class="button2" href="admin.php?page=ci_data_forms&tab=custom_provider">View</a> -
				<a class="button2" href="admin.php?page=ci_data_forms&tab=custom_provider">Add</a>
			</div>
		<?php endif; ?>
		<div class="clear"></div>
	</div>
<?php
}

function trpdd_dashboard_speciment_flow()
{
	include(TRCPDD_PATH . 'pages/dashboard_speciment_flow.php');
}


add_filter('admin_body_class', 'trpdd_filter_admin_body_class');
function trpdd_filter_admin_body_class($classes)
{
	global $current_role;
	if ($current_role != 'administrator') {
		$classes .= ' no_admin_role ';
	}
	return $classes;
}


function trpdd_dashboard_average_turnaround_time()
{
	include(TRCPDD_PATH . 'pages/dashboard_average_turnaround_time.php');
}

add_action('remove_user_from_blog', 'tr_pdd_remove_user_from_blog', 10, 2);
add_action('delete_user', 'tr_pdd_remove_user_from_blog', 10, 1);
add_action('wpmu_delete_user', 'tr_pdd_remove_user_from_blog', 10, 1);
function tr_pdd_remove_user_from_blog($user_id, $blog_id = 0)
{
	$user = get_userdata($user_id);
	$data = array(
		'blog_id' => $blog_id,
		'user_id' => $user_id,
		'email' => $user->user_email
	);
	trpdd_log_form('remove_user', $data);
}



add_action('user_new_form', 'trpdd_user_new_form');
function trpdd_user_new_form()
{
	global $current_role;
	if (!empty($current_role) && stripos($current_role, 'admin') !== false) {
		$role = get_role($current_role);
		if ($role) {
			$role->add_cap('manage_network_users');
		}
	}
?>
	<style>
		#add-existing-user,
		#add-existing-user+p,
		form#adduser,
		h2#create-new-user {
			display: none !important;
		}

		<?php if ($current_role != 'administrator') {
		?>select#role option[value="administrator"] {
			display: none;
		}

		<?php
		}

		?>
	</style>
	<script>
		jQuery(function($) {
			$('#adduser-noconfirmation,#noconfirmation').prop('checked', true);
			if ($('#noconfirmation').length == 0) {
				$('#createuser').append('<input type="hidden" name="noconfirmation" value="1"/>');
			}
			if ($('#adduser-noconfirmation').length == 0) {
				$('#adduser').append('<input type="hidden" name="noconfirmation" value="1"/>');
			}
		})
	</script>
<?php
}

function trpdd_get_hidden_roles()
{
	return array(
		'clinic',
		'lab_rep',
		'lab_rep_w2',
		'collector',
		'office_manager',
		'multi_site_manager',
		'doctor',
		'Phlebotomist',
		'patient',
		'distributor',
	);
}

add_filter('editable_roles', 'trpdd_editable_roles');
function trpdd_editable_roles($roles)
{
	global $current_role, $profile_user, $is_ci_admin;

	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return $roles;
	}

	//sort roles by name
	uasort($roles, function ($a, $b) {
		return strcmp($b['name'], $a['name']);
	});

	$hidden_roles    = trpdd_get_hidden_roles();
	if (isset($roles['data_export']) && !is_super_admin()) {
		$is_current_role = is_object($profile_user)
			&& isset($profile_user->roles[0])
			&& $profile_user->roles[0] === 'data_export';
		if ($is_current_role) {
			$roles['data_export']['disabled'] = true;
		} else {
			unset($roles['data_export']);
		}
	}

	// 1. Mark hidden/disabled roles
	foreach ($roles as $role_key => $role_data) {
		$is_hidden   = in_array($role_key, $hidden_roles);
		$is_disabled = isset($role_data['capabilities']['disable_role']);

		if ($is_hidden || $is_disabled) {
			$roles[$role_key]['disabled'] = true;
		}
	}

	if ($is_ci_admin) {
		return $roles;
	}

	$disable_or_remove = function ($roles, $role_key) use ($profile_user) {
		if (!isset($roles[$role_key])) return $roles;

		$is_current_role = is_object($profile_user)
			&& isset($profile_user->roles[0])
			&& $profile_user->roles[0] === $role_key;

		if ($is_current_role) {
			$roles[$role_key]['disabled'] = true;
		} else {
			unset($roles[$role_key]);
		}

		return $roles;
	};

	if ($current_role !== 'administrator') {
		$roles = $disable_or_remove($roles, 'administrator');
	}

	if (is_object($profile_user) && isset($profile_user->roles[0])) {
		$userrole = $profile_user->roles[0];
		if (!in_array($userrole, ['office_manager', 'doctor', 'Phlebotomist'])) {
			foreach (['doctor', 'office_manager', 'Phlebotomist', 'lab_rep', 'collector', 'patient'] as $r) {
				$roles = $disable_or_remove($roles, $r);
			}
		}
	}

	if (in_array($current_role, ['reflab_manager', 'ref_lab_tech'])) {
		$allowed        = ['office_manager', 'collector', 'doctor'];
		$filtered_roles = [];
		foreach ($roles as $role_key => $role_data) {
			if (in_array($role_key, $allowed)) {
				$filtered_roles[$role_key] = $role_data;
			} else {
				$is_current_role = is_object($profile_user)
					&& isset($profile_user->roles[0])
					&& $profile_user->roles[0] === $role_key;
				if ($is_current_role) {
					$role_data['disabled'] = true;
					$filtered_roles[$role_key] = $role_data;
				}
			}
		}
		return $filtered_roles;
	}

	return $roles;
}

add_action('admin_footer', 'trpdd_disable_roles_via_js');
function trpdd_disable_roles_via_js()
{
	if (defined('TRPDD_PATIENT_SITE') && TRPDD_PATIENT_SITE) {
		return;
	}


	$screen = get_current_screen();
	if (!$screen || !in_array($screen->id, ['users', 'user-edit', 'user'])) return;

	$cached_roles = get_editable_roles();
	$disabled_roles = [];

	foreach ($cached_roles as $role_key => $role_data) {
		if (!empty($role_data['disabled'])) {
			$disabled_roles[] = $role_key;
		}
	}

	if (empty($disabled_roles)) return;
?>
	<script>
		(function() {
			const disabledRoles = <?php echo json_encode($disabled_roles); ?>;

			function disableRoleOptions() {
				document.querySelectorAll('select[name="role"], select[name="new_role"], select[name="new_role2"]').forEach(function(select) {
					select.querySelectorAll('option').forEach(function(option) {
						if (disabledRoles.includes(option.value)) {
							option.disabled = true;
						}
					});
				});
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', disableRoleOptions);
			} else {
				disableRoleOptions();
			}
		})();
	</script>
<?php
}


add_action('admin_head', 'trpdd_admin_head');
function trpdd_admin_head()
{
	include('log_admin_head.php');
}

add_filter('user_row_actions', 'trpdd_user_row_actions', 10, 2);
function trpdd_user_row_actions($actions, $user)
{
	global $wpdb;
	$count = $wpdb->get_var("select count(*) from ci_log_users where user_id = {$user->ID}");
	$actions['log'] = '<a href="?tr_action=ci_view_user_logs&id=' . $user->ID . '&width=1000" title="Log of #' . $user->ID . ' | ' . $user->user_email . '" class="thickbox">Log(' . $count . ')</a>';
	unset($actions['view']);
	return $actions;
}

add_filter('admin_title', 'trpdd_admin_title');
function trpdd_admin_title($title)
{
	$title = str_replace(' &#8212; WordPress', '', $title);
	return $title;
}

add_action('admin_page_access_denied', 'trpdd_admin_page_access_denied');
function trpdd_admin_page_access_denied()
{
	include(trpdd_get_file_path('global', 'admin_page_access_denied'));
}

add_filter('admin_footer_text', '__return_empty_string', 11);
add_filter('update_footer', '__return_empty_string', 11);


add_filter('wp_is_large_user_count', '__return_false', 99);

add_action('saved_req', 'trpdd_saved_req', 11, 4);
function trpdd_saved_req($btype, $mid, $batch, $pa)
{
	include(TRCPDD_PATH . 'inc/actions/saved_req.php');
}

function add_ref_lab_field_admin($user)
{
	$ref_labs = CI_Base_Lab::get_ref_labs();
	$lab_id = get_user_meta($user->ID, '_ref_lab_id', true);
?>
	<table class="form-table" id="ref-lab-table" style="display:none;">
		<tr class="form-field form-required">
			<th><label for="_ref_lab_id">Ref Lab</label></th>
			<td>
				<select name="_ref_lab_id" id="_ref_lab_id" required aria-required="true">
					<option value="">Select</option>
					<?php foreach ($ref_labs as $lab) :
						$selected = ($lab->lab_ID == $lab_id) ? 'selected' : '';
					?>
						<option value="<?php echo esc_attr($lab->lab_ID); ?>" <?php echo $selected; ?>>
							<?php echo esc_html($lab->lab_name . ' (' . $lab->lab_dba_init . ')'); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>

	<script type="text/javascript">
		(function($) {
			var allowedRoles = ['ref_lab_tech', 'reflab_manager'];

			function toggleRefLabField() {
				var selectedRole = $('#role').val() || [];
				if (Array.isArray(selectedRole)) {
					var show = selectedRole.some(r => allowedRoles.includes(r));
				} else {
					var show = allowedRoles.includes(selectedRole);
				}
				if (show) {
					$('#ref-lab-table').show();
				} else {
					$('#ref-lab-table').hide();
				}
			}

			toggleRefLabField();

			$('#role').on('change', toggleRefLabField);

			$('form#your-profile, form#createuser').on('submit', function(e) {
				var selectedRole = $('#role').val() || [];
				var isRoleRequired = Array.isArray(selectedRole) ?
					selectedRole.some(r => allowedRoles.includes(r)) :
					allowedRoles.includes(selectedRole);

				if (isRoleRequired) {
					var labValue = $('#_ref_lab_id').val();
					if (!labValue) {
						e.preventDefault();
						$('#ref-lab-table tr').addClass('form-invalid');
						$('#_ref_lab_id').focus();
						return false;
					}
				}
			});

			$('#_ref_lab_id').on('change', function() {
				if ($(this).val()) {
					$('#ref-lab-table tr').removeClass('form-invalid');
				}
			});
		})(jQuery);
	</script>
<?php
}

add_action('user_new_form', 'add_ref_lab_field_admin');

function save_ref_lab_field_admin($user_id)
{
	if (!current_user_can('edit_user', $user_id)) return;

	if (isset($_POST['_ref_lab_id'])) {
		update_user_meta($user_id, '_ref_lab_id', sanitize_text_field($_POST['_ref_lab_id']));
	}
}
add_action('user_register', 'save_ref_lab_field_admin');


add_action('pre_get_users', function ($query) {
	global $wpdb, $filtered_user_ids;
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'users') return;
	$user = wp_get_current_user();
	$roles = array_values($user->roles ?? []);
	if (!array_intersect($roles, ['reflab_manager', 'ref_lab_tech'])) return;

	$ref_lab_ID = get_user_meta($user->ID, '_ref_lab_id', true);

	if (empty($ref_lab_ID)) {
		$query->set('include', [0]);
		return;
	}

	$clinics = $wpdb->get_col("select cID from ci_clinic where cli_ref_lab_ID = {$ref_lab_ID} and cli_status = 1");

	if (empty($clinics)) {
		$query->set('include', [0]);
		return;
	}
	$filtered_user_ids = [];
	foreach ($clinics as $cli_ID) {
		// managers
		$ma_user_ids = $wpdb->get_col("select ma_user_ID from ci_clinic_manager where ma_cli_ID = {$cli_ID}");

		// multi site managers
		$ms_ids = [];

		$ms_manager_ids = array();
		if (!empty($ms_manager_ids)) {
			foreach ($ms_manager_ids as $userID) {
				$ms_cli_ids = get_user_meta($userID, '_ms_clinic_ids', true);
				if (!is_array($ms_cli_ids) || in_array($cli_ID, $ms_cli_ids)) {
					$ms_ids[] = $userID;
				}
			}
		}

		// docs
		$doc_ids = $wpdb->get_col("select doc_ID from ci_clinic_doc where cli_ID = {$cli_ID}");
		$doc_user_ids = [];
		if (!empty($doc_ids)) {
			$placeholders = implode(',', array_fill(0, count($doc_ids), '%d'));
			$query_sql = $wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_doc_id' AND meta_value IN ($placeholders)",
				...$doc_ids
			);

			$doc_user_ids = $wpdb->get_col($query_sql);
		}
		// collectors
		$colector_ids = $wpdb->get_col("select col_ID from ci_clinic_collector where cli_ID = {$cli_ID}");
		$collector_user_ids = [];
		if (!empty($colector_ids)) {
			$placeholders = implode(',', array_fill(0, count($colector_ids), '%d'));
			$query_sql = $wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_collector_id' AND meta_value IN ($placeholders)",
				...$colector_ids
			);

			$collector_user_ids = $wpdb->get_col($query_sql);
		}

		$filtered_user_ids = array_merge($filtered_user_ids, $ma_user_ids, $ms_ids, $doc_user_ids, $collector_user_ids);
	}
	$filtered_user_ids = array_unique($filtered_user_ids);

	if (!empty($filtered_user_ids)) {
		$query->set('include', $filtered_user_ids);
	} else {
		$query->set('include', [0]);
	}
});
add_filter('views_users', function ($views) {
	global $wpdb, $filtered_user_ids;
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'users') return $views;
	$user = wp_get_current_user();
	$roles = array_values($user->roles ?? []);
	if (!array_intersect($roles, ['reflab_manager', 'ref_lab_tech'])) return $views;

	if (empty($filtered_user_ids)) {
		$filtered_user_ids = [0];
	}

	foreach ($views as $role => $label) {
		if ($role === 'all') {
			$count = count($filtered_user_ids);
		} else {
			$placeholders = implode(',', array_fill(0, count($filtered_user_ids), '%d'));
			$query_sql = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->users} u
                 INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                 WHERE um.meta_key = '{$wpdb->prefix}capabilities'
                 AND um.meta_value LIKE %s
                 AND u.ID IN ($placeholders)",
				'%' . $role . '%',
				...$filtered_user_ids
			);
			$count = (int) $wpdb->get_var($query_sql);
		}

		$views[$role] = preg_replace('/\(\d+\)/', "($count)", $label);
	}

	return $views;
});


add_action('admin_footer-users.php', function () {
	$user = wp_get_current_user();
	$user_roles = array_values($user->roles ?? []);

	if (!array_intersect($user_roles, ['reflab_manager', 'ref_lab_tech'])) {
		return;
	}

	$allowed_roles = ['office_manager', 'collector', 'doctor'];
?>
	<script>
		(function() {
			const items = document.querySelectorAll('.subsubsub li');

			items.forEach(item => {
				const classList = Array.from(item.classList);

				const roleClass = classList.find(c => c !== 'all' && c !== 'current');

				if (!roleClass) return;

				const allowed = <?php echo json_encode($allowed_roles); ?>;

				if (!allowed.includes(roleClass)) {
					item.style.display = 'none';
				}
			});
		})();
	</script>
<?php
});

// Remove user completely when removed from blog and has no other blogs
//add_action('remove_user_from_blog', 'delete_user_completely_on_remove', 10, 2);

function delete_user_completely_on_remove($user_id, $blog_id)
{
	static $is_deleting = [];

	if (isset($is_deleting[$user_id])) {
		return;
	}
	$is_deleting[$user_id] = true;

	if (!function_exists('wpmu_delete_user')) {
		require_once ABSPATH . 'wp-admin/includes/ms.php';
	}

	if (!get_userdata($user_id)) {
		unset($is_deleting[$user_id]);
		return;
	}

	$user_blogs = get_blogs_of_user($user_id);
	if (isset($user_blogs[$blog_id])) {
		unset($user_blogs[$blog_id]);
	}

	if (count($user_blogs) === 0) {
		wpmu_delete_user($user_id);
	}

	unset($is_deleting[$user_id]);
}
//End File
