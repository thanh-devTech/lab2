<?php
global $current_role, $ci_all_btypes, $access_btypes;

$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
	trpdd_plugin_activate();
}

if (isset($_REQUEST['trajax'])) {
	if ($_REQUEST['trajax'] == 'upload_doctors') {
		$return = array('status' => 'ok', 'list' => array());

		if (!empty($_FILES['importfile']['tmp_name'])) {
			$data = trpdd_get_data($_FILES['importfile']['tmp_name'], '', array('first name', 'last name'), true);

			foreach ($data['data'] as $row) {
				$email = trpdd_get_string_exist($row, array('email', 'email address'));
				$email = trim($email);
				$email = str_replace(' ', '', $email);
				if (stripos($email, 'N/A') !== false || stripos($email, 'na') !== false) {
					$email = '';
				}
				$data = array(
					'doc_first'     => trpdd_get_string_exist($row, array('first', 'first name')),
					'doc_last'      => trpdd_get_string_exist($row, array('last', 'last name')),
					'doc_specialty' => trpdd_get_string_exist($row, array('degree', 'ft/pt/ic', 'specialty')),
					'doc_npi'       => trpdd_get_string_exist($row, array('npi', 'npi number')),
					'doc_email'     => $email,
					'doc_phone'     => trpdd_get_string_exist($row, array('phone', 'phone no', 'phone no.')),
				);
				$return['list'][] = $data;
			}
		}
		wp_send_json($return);
		exit;
	} else if ($_REQUEST['trajax'] == 'upload_managers') {
		$return = array('status' => 'ok', 'list' => array());
		if (!empty($_FILES['importfile']['tmp_name'])) {
			$data = trpdd_get_data($_FILES['importfile']['tmp_name'], '', array('name', 'email'), true);
			foreach ($data['data'] as $row) {
				$name = trim($row['name']);
				$email = trim($row['email']);
				$return['list'][] = array(
					'ma_email' => $email,
					'ma_manager' => $name,
				);
			}
		}
		wp_send_json($return);
		exit;
	} else if ($_REQUEST['trajax'] == 'upload_ms_managers') {
		$return = array('status' => 'ok', 'list' => array());
		if (!empty($_FILES['importfile']['tmp_name'])) {
			$data = trpdd_get_data($_FILES['importfile']['tmp_name'], '', array('name', 'email'), true);
			foreach ($data['data'] as $row) {
				$name = trim($row['name']);
				$email = trim($row['email']);
				$return['list'][] = array(
					'ma_email' => $email,
					'ma_name' => $name,
				);
			}
		}
		wp_send_json($return);
		exit;
	} else if ($_REQUEST['trajax'] == 'upload_collectors') {
		$return = array('status' => 'ok', 'list' => array());

		if (!empty($_FILES['importfile']['tmp_name'])) {
			$data = trpdd_get_data($_FILES['importfile']['tmp_name'], '', array('first name', 'last name', 'email'), true);

			foreach ($data['data'] as $row) {
				$email = trpdd_get_string_exist($row, array('email', 'email address'));
				$email = trim($email);
				$email = str_replace(' ', '', $email);
				if (stripos($email, 'N/A') !== false || stripos($email, 'na') !== false) {
					$email = '';
				}
				$date = trpdd_get_string_exist($row, array('date'));
				if (empty($date)) {
					$date = '';
				} else {
					$date = date('Y-m-d', strtotime($date));
				}
				$return['list'][] = array(
					'col_first'     => trpdd_get_string_exist($row, array('first', 'first name')),
					'col_last'      => trpdd_get_string_exist($row, array('last', 'last name')),
					'col_middle' => trpdd_get_string_exist($row, array('middle', 'middle initia')),
					'col_rdate' 	=> $date,
					'col_email'     => $email,
					'col_phone'     => trpdd_get_string_exist($row, array('phone', 'phone no', 'phone no.')),
				);
			}
		}
		wp_send_json($return);
		exit;
	} else if ($_REQUEST['trajax'] == 'account_pdf') {
		$filename = 'New-Account-Form.pdf';
		$_REQUEST['act'] = $_GET['act'] = $act = 'print';
		$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

		ob_start();
		$formfile = trpdd_get_file_path('accountforms', 'new_account_form');
		include($formfile);
		$content = ob_get_clean();
		$content = trpdd_pdf_html($content, 'New Account Form');

		$content = preg_replace("#\<option[^\>]*selected[^\>]*\>([^\<]*)\<\/option\>#is", '$1', $content);
		$content = preg_replace("#\<option([^\>]*)\>([^\<]*)\<\/option\>#is", '', $content);
		$content = preg_replace("#\<input[^\>]*type=\"hidden\"[^\>]*>#is", '', $content);
		$content = preg_replace("#\<select[^\>]*\>([^\<]*)\<\/select\>#is", '<span class="text">$1</span>', $content);

		$content = str_ireplace(array('<span class="text">Select State</span>', '<span class="text">Select</span>'), '<span class="text"> </span>', $content);
		$content = str_ireplace(array('type="email"', 'type="search"'), 'type="text"', $content);

		if (strpos($content, 'print.min.css') === false && strpos($content, 'print.css') === false) {
			$content .= '<link href="' . TRCPDD_URL . 'css/print.css?' . time() . '" media="all" type="text/css" rel=\'stylesheet\'/>';
		}
		if (isset($_GET['html'])) {
			var_dump($content);
			exit;
		}


		header('Content-Type: application/pdf');
		if (!isset($_GET['view'])) {
			header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
		}
		trpdd_build_pdf($content, array('filename' => $filename));
		exit;
	} else if ($_REQUEST['trajax'] == 'demo_manager_csv') {
		$columns = array(
			'name',
			'email',
			'clinic'
		);
		header("Content-disposition: attachment; filename=office_managers.csv");
		header("Content-Type: text/csv");
		$output = fopen("php://output", 'w');
		fputcsv($output, $columns);
		$row = array('Test manager', 'manager@lis-demo.com', 'Clinic eight');
		fputcsv($output, $row);
		exit;
	} else if ($_REQUEST['trajax'] == 'demo_ms_manager_csv') {
		$columns = array(
			'name',
			'email'
		);
		header("Content-disposition: attachment; filename=Multi_Site_Manager.csv");
		header("Content-Type: text/csv");
		$output = fopen("php://output", 'w');
		fputcsv($output, $columns);
		$row = array('Multi-Site Manager', 'manager@lis-demo.com');
		fputcsv($output, $row);
		exit;
	} else if ($_REQUEST['trajax'] == 'demo_doctor_csv') {
		$columns = array(
			'first name',
			'last name',
			'specialty',
			'npi',
			'email',
			'phone'
		);
		header("Content-disposition: attachment; filename=doctors.csv");
		header("Content-Type: text/csv");
		$output = fopen("php://output", 'w');
		fputcsv($output, $columns);

		$row = array('doc first', 'doc last', 'spe', '0292929', 'doc@lis-demo.com', '0123456789');
		fputcsv($output, $row);
		exit;
	} else if ($_REQUEST['trajax'] == 'demo_collector_csv') {
		$columns = array(
			'first name',
			'last name',
			'Middle Initia',
			'Date',
			'Phone No.',
			'Email'
		);
		header("Content-disposition: attachment; filename=collectors.csv");
		header("Content-Type: text/csv");
		$output = fopen("php://output", 'w');
		fputcsv($output, $columns);

		$row = array('first name', 'last name', 'Md', '2024-01-01', '+10000111', 'collector@lis-demo.com');
		fputcsv($output, $row);
		exit;
	}
}


$forms = array(
	//'new_account_due' => 'NEW ACCOUNT DUE DILIGENCE',
	//'new_account_setup' =>'NEW ACCOUNT SETUP FORM',
	'new_account_form' => 'NEW ACCOUNT FORM',
	'new_account_form_list' => 'SAVED FORMS',
	'global_doctors' => 'Global PHYSICIANS',
	'study_setup_form' => 'Study Setup Form',
	'custom_provider' => 'CUSTOM PROVIDER TOX',
	//'custom_profiles' => 'CUSTOM PROFILES',
	//'collector_request' => 'COLLECTOR REQUEST FORM',
	'collector_training' => 'COLLECTOR TRAINING FORM'
);

if (!in_array('tox', $access_btypes)) {
	unset($forms['custom_provider']);
	unset($forms['collector_training']);
} else if (
	current_user_can('custom_provider_tox')
	&& !tr_current_role_in(array('administrator', 'administrator_p', 'AdminCIDW'))
) {
	unset($forms['custom_provider']);
}

if (!tr_get_option('ci_pdf_data', 'doc_global')) {
	unset($forms['global_doctors']);
}

if (!tr_current_role_in(array('labmanager', 'lab_tech', 'administrator', 'administrator_p'))) {
	unset($forms['custom_profiles']);
	unset($forms['global_doctors']);
}

if (tr_current_role_in(array('office_manager', 'multi_site_manager'))) {
	unset($forms['collector_training']);
}

if (!in_array('study', $access_btypes) || !current_user_can('ci_study_setup')) {
	unset($forms['study_setup_form']);
}

if (isset($_GET['debug'])) {
	$forms['import_doctors'] = 'Import Physicians';
	$forms['import_managers'] = 'Import Office Managers';
} else if (tr_current_role_in(array('administrator', 'administrator_p', 'labmanager'))) {
	if (tr_get_option('ci_pdf_data', 'e_import_doc')) {
		$forms['import_doctors'] = 'Import Physicians';
	}
	if (tr_get_option('ci_pdf_data', 'e_import_om')) {
		$forms['import_managers'] = 'Import Office Managers';
	}
}

$tab = isset($_REQUEST['tab']) && isset($forms[$_REQUEST['tab']]) ? $_REQUEST['tab'] : key($forms);

$formf = 0;
if (!isset($_REQUEST['trajax'])) {
	echo '<h2 class="nav-tab-wrapper">';
	foreach ($forms as $k => $txt) {
		$actl = '';
		$knew = $k;
		if ($k == 'new_account_form_list') {
			$knew = 'new_account_form';
			$actl = 'list';
		}

		$class = $tab == $knew ? 'nav-tab-active' : '';

		if ($k == 'new_account_form' && $act == 'list') {
			$class = '';
		} else if ($k == 'new_account_form_list' && $act != 'list') {
			$class = '';
		}

		$link  = Tr_Base_Class_V6::link(array('tab' => $knew, 'act' => $actl, 'id' => '', 'clinic_id' => ''), false);
		echo '<a class="nav-tab ' . $class . '" href="' . $link . '">' . $txt . '</a>';
	}
	echo '</h2>';

	echo '<div class="wrap_tabs">';
}

if ($tab == 'study_setup_form') {
	$formfile = trpdd_get_file_path('study', 'setup_form');
} else {
	$formfile = trpdd_get_file_path('accountforms', $tab);
}

if (file_exists($formfile)) {
	include($formfile);
}
if (!isset($_REQUEST['trajax'])) {
	echo '</div>';
}
