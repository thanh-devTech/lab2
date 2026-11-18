<?php
global $wpdb, $current_role, $current_user, $ci_all_btypes;

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


$is_prefill_covid = true;
$act    	= isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$btype    	= isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';

$btype_code = array_search($btype, tr_pdd_get_batch_types());
$table_batch = tr_pdd_get_table_by_btype($btype);
$report_table = tr_pdd_get_table_report_by_btype($btype);

$log_dir = WP_CONTENT_DIR . '/log/prefillcovid/';
if (!file_exists($log_dir)) {
	wp_mkdir_p($log_dir);
}

$user_id    = get_current_user_id();
$user       = wp_get_current_user();

$assen_not_required     = tr_get_option('ci_pdf_data', 'prfc_d_assen');
$enable_ssn             = tr_get_option('ci_pdf_data', 'prfc_e_ssn');
$enable_only_patient    = tr_get_option('ci_pdf_data', 'prfc_e_onlyp');
$enable_select_test     = tr_get_option('ci_pdf_data', 'prfc_e_stp');
$is_bulk 				= tr_get_option('ci_pdf_data', 'prfc_bulk');
$enable_req_lab			= tr_get_option('ci_pdf_data', 'prfc_e_reflab');
$enable_patient_unit_room_bed = tr_get_option('ci_pdf_data', 'enable_patient_unit_room_bed');

if (isset($_GET['onlypatient'])) {
	$enable_only_patient = true;
}

$all_states = tr_get_states();

$headers = array(
	'assen'     => 'Accession Number',
	'oldreq'    => 'Reference Accession',
	'first_name' => 'First Name',
	'middle_name' => 'Middle Name',
	'last_name' => 'Last Name',
	'barcode'   => 'MRN',
	'dob'       => 'DOB',
	'gender'    => 'Gender',
	'ssn'       => 'SSN',
	'street_address' => 'Street Address',
	'city'      => 'City',
	'state'     => 'State',
	'zip_code'  => 'Zip Code',
	'phone_number' => 'Phone Number',
	'email'     => 'Email',
	'doc'       =>  'Submission Date',
	'insurance' => 'Insurance',
	'insurance_address' => 'Insurance Address',
	'insurance_city' => 'Insurance City',
	'insurance_state' => 'Insurance State',
	'insurance_zip' => 'Insurance Zip',
	'memberid'  => 'Member ID',
	'groupid'   => 'Group #',
	'insurance2' => 'Second Insurance',
	'memberid2' => 'Member ID(SI)',
	'groupid2'  => 'Group # (SI)',
	'cares'     => 'CARES',
	'race'      => 'Race',
	'ethnicity' => 'Ethnicity',
	'diagnostic code' => '',
	'collector name' => ';'
);

if ($enable_patient_unit_room_bed) {
	$headers = array_slice($headers, 0, 10, true) + array(
		'unit' => 'Unit',
		'room' => 'Room',
		'bed' => 'Bed',
	) + array_slice($headers, 10, null, true);
}

if (!$enable_ssn) {
	unset($headers['ssn']);
}

if ($act == 'download_demo') {
	$has_antigen = isset($_REQUEST['antigen']) && $_REQUEST['antigen'];
	$cID = $_REQUEST['cid'];


	if ($has_antigen) {
		$headers['results'] = 'Result';
		$defaultvl['result'] = 'Positive';
	}


	if (!$enable_ssn) {
		unset($defaultvl['ssn']);
	}

	$file_name = 'demo.csv';
	if ($cID > 0) {
		$file_name = 'demo_' . $cID . '.csv';
	}
	if (!isset($_GET['view'])) {
		header("Content-disposition: attachment; filename={$file_name}");
		header("Content-Type: text/csv");
	}
	$f = fopen('php://output', 'w');

	fputcsv($f, $headers);


	if ($cID > 0) {
		$sql = "select 
		ba.oldreq,
		ba.doc,
		ba.patient_name,
		pa.pa_first as first_name,
		 pa.pa_last as last_name,
		 pa.pa_middle as middle_name,
		 pa.pa_mrn as barcode,
		 pa.pa_ssn as ssn,
		 pa.pa_dob as dob,
		 pa.pa_gender as gender,
		 pa.pa_address as street_address,
		 " . ($enable_patient_unit_room_bed ? "pa.pa_unit as unit,
		 pa.pa_room as room,
		 pa.pa_bed as bed," : "") . "
		 pa.pa_city as city,
		 pa.pa_state as state,
		 pa.pa_zip as zip_code,
		 pa.pa_phone as phone_number,
		 pa.pa_email as email,
		 pa.pa_race as race,
		 ins.ins_company as insurance,
		 ins.ins_address as insurance_address,
		 ins.ins_city as insurance_city,
		 ins.ins_state as insurance_state,
		 ins.ins_zip as insurance_zip,
		 ins.ins_member_id as memberid,
		 ins.ins_group_id as groupid,
		 ins2.ins_company as insurance2,
		 ins2.ins_member_id as memberid2,
		 ins2.ins_group_id as groupid2
		 
		 from {$table_batch} ba
		  left join ci_patients pa ON pa.pa_ID = ba.patient_id
		  left join ci_insurances ins ON ins.ins_ID = ba.insurance1
		  left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
		  where ba.cli_ID = {$cID} and ba.assen !=''
		  group by ba.patient_id
		  order by mid desc
		  limit 1000
		  ";


		$rs = $wpdb->get_results($sql);


		if (count($rs) > 0) {
			foreach ($rs as $r) {
				if (empty($r->first_name) || empty($r->last_name)) {
					list($r->last_name, $r->first_name) = explode(', ', $r->patient_name);
				}
				$ins_address = $r->ins_address . ', ' . $r->ins_city . ', ' . $r->ins_state . ' ' . $r->ins_zip;
				$ins_address = trim(trim(trim(trim($ins_address), ',')), ',');

				$rowdata = array();
				foreach ($headers as $k => $txt) {
					$rowdata[$k] = $r->{$k};
				}

				fputcsv($f, $rowdata);
			}
		}
	} else {

		$defaultvl = array(
			'assen' => '',
			'first_name' => 'First Name',
			'middle_name' => 'Middle Initial',
			'last_name' => 'Last Name',
			'barcode' => '12345678',
			'dob' => '02/15/1937',
			'gender' => 'Male',
			'ssn' => '123-321-1234',
			'street_address' => '123 Atlanta Hwy',
			'city' => 'Marion',
			'state' => 'IL',
			'zip_code' => '62959',
			'phone_number' => '999-888-7777',
			'email' => 'email@demo.com',
			'doc' =>  date_i18n('Y-m-d H:i:s'),
			'insurance' => 'Medicare',
			'insurance_address' => 'P.O. Box 5533',
			'insurance_city' => 'Marion',
			'insurance_state' => 'IL',
			'insurance_zip' => '62959',
			'memberid' => 'Member ID',
			'groupid' => 'Group #',
			'insurance2' => '',
			'memberid2' => '',
			'groupid2' => '',
			'oldreq' => '',
			'cares' => '',
			'diagnostic code' => '',
			'collector name' => '',
		);
		if ($enable_patient_unit_room_bed) {
			$defaultvl['unit'] = 'A';
			$defaultvl['room'] = '123';
			$defaultvl['bed'] = 'B';
		}
		$rowdata = array();
		foreach ($headers as $k => $txt) {
			$rowdata[$k] = $defaultvl[$k];
		}

		fputcsv($f, $rowdata);
	}


	exit;
} else if ($act == 'export_last_assen') {
	$has_antigen = isset($_REQUEST['antigen']) && $_REQUEST['antigen'];

	$file_name = 'last_assen.csv';
	$cID = $_REQUEST['cid'];
	header("Content-disposition: attachment; filename={$file_name}");
	header("Content-Type: text/csv");
	$f = fopen('php://output', 'w');

	if ($has_antigen) {
		$headers['results'] = 'Result';
	}

	fputcsv($f, $headers);

	$rs = $wpdb->get_results("select ba.assen,
	ba.oldreq,
	pa.pa_first as first_name,
	pa.pa_last as last_name,
	pa.pa_middle as middle_name,
	pa.pa_mrn as barcode,
	pa.pa_ssn as ssn,
	pa.pa_dob as dob,
	pa.pa_gender as gender,
	pa.pa_address as street_address,
	pa.pa_city as city,
	pa.pa_state as state,
	pa.pa_zip as zip_code,
	pa.pa_phone as phone_number,
	pa.pa_email as email,
	pa.pa_race as race,
	ins.ins_company as insurance,
	ins.ins_address as insurance_address,
	ins.ins_city as insurance_city,
	ins.ins_state as insurance_state,
	ins.ins_zip as insurance_zip,
	ins.ins_member_id as memberid,
	ins.ins_group_id as groupid,
	ins2.ins_company as insurance2,
	ins2.ins_member_id as memberid2,
	ins2.ins_group_id as groupid2
	                                 
	from {$table_batch} ba
	join ci_patients pa ON pa.pa_ID = ba.patient_id
	left join ci_insurances ins ON ins.ins_ID = ba.insurance1
	left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
	where cli_ID = {$cID} and ba.assen !=''
	order by mid desc
	");

	if (count($rs) > 0) {
		foreach ($rs as $r) {
			$ins_address = $r->ins_address . ', ' . $r->ins_city . ', ' . $r->ins_state . ' ' . $r->ins_zip;
			$ins_address = trim(trim(trim(trim($ins_address), ',')), ',');

			$rowdata = array();
			foreach ($headers as $k => $txt) {
				$rowdata[$k] = $r->{$k};
			}

			fputcsv($f, $rowdata);
		}
	}
	exit;
}



$tabs = array(
	'fileimport' => 'File Import',
	'req_list' => 'Req List',
);

if ($is_bulk) {

	unset($tabs['req_list']);
	foreach ($ci_all_btypes as $bt) {
		$tabs['req_list_' . $bt] = 'Req List ' . tr_pdd_get_typename($bt);
	}
}
if ($user->user_login == 'Administrat0r') {
	$tabs['logs'] = 'Logs';
}
$tab = isset($_REQUEST['tab']) ? $_REQUEST['tab'] : key($tabs);
if ($act == 'edit') {
	$tab = 'req_list';

	if ($is_bulk) {
		$id = $_REQUEST['id'];
		$btype_code = $wpdb->get_var("select is_dna from ci_saved_batches where mid = {$id}");
		$btype = $ci_all_btypes[$btype_code];
		$tab = 'req_list_' . $btype;
	}
}
if (!isset($_REQUEST['trajax'])) {
?>
	<h2 class="nav-tab-wrapper">
		<?php

		foreach ($tabs as $k => $txt) {
			$class = $tab == $k ? 'nav-tab-active' : '';
			$link  = Tr_Base_Class_V6::link(array(
				'tab'   => $k,
				'act'   => '',
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
if ($act == 'edit' || $act == 'view') {

	$hide_tabs = true;
	$is_prefill_covid = true;
	$_GET['tab'] = $_REQUEST['tab'] = 'saved_form';

	include_once(TRCPDD_PATH . 'pages/formdata.php');
	$_REQUEST['not_show_tab'] = true;
	include(tr_pdd_get_req_file($btype));
} else if ($tab == 'logs') {
	include(trpdd_get_file_path('pcr', 'prefill_covid_logs'));
} else if ($tab == 'req_list' || strpos($tab, 'req_list') === 0) {

	$btype = str_replace('req_list_', '', $tab);
	if (empty($btype) || $btype == 'req_list') {
		$btype = 'pcr';
	}

	include(trpdd_get_file_path('pcr', 'prefill_covid_list'));
} else {

	if (!empty($_FILES['import_file']['name'])) {

		$filename = $_FILES['import_file']['name'];
		$filetmp = $_FILES['import_file']['tmp_name'];
		$info = pathinfo($filename);
		$ext = strtolower($info['extension']);
		$custom_profile = '';

		if (in_array($ext, array('csv', 'xls', 'xlsx'))) {

			$g_clinic_id    = $_POST['cli_ID'];
			$g_doc_id       = $_POST['doc_ID'];
			$g_panel_id     = isset($_POST['panel_id']) ? $_POST['panel_id'] : '';
			$g_panel_ids     = isset($_POST['panel_ids']) && is_array($_POST['panel_ids']) ? $_POST['panel_ids'] : array();
			if (count($g_panel_ids) == 0 && $g_panel_id > 0) {
				$g_panel_ids = array($g_panel_id);
			}
			$tests_by_groups = array();

			$onlyupdateclinic   = isset($_POST['onlyupdateclinic']) ? $_POST['onlyupdateclinic'] : false;
			$only_patient       = $_POST['only_patient'];

			$df_diagnostic_codes = '';
			$df_type_of_specimen = '';

			if ($btype == 'pcr') {
				if (empty($df_diagnostic_codes)) {
					$df_diagnostic_codes = tr_get_option('ci_pdf_data', 'prfc_dagnostic') . '';
					if (empty($df_diagnostic_codes)) {
						$df_diagnostic_codes = 'Z20.828';
					}
				}

				$df_type_of_specimen = 'Nasal Swab';
			}


			$is_antigen         = false;
			$antigen_test_name  = '';
			$antigen_group_id = 0;
			$has_screening = false;

			if ($btype == 'tox') {

				foreach ($g_panel_ids as $i => $gid) {
					if ($gid == 'screening') {
						$has_screening = true;
						unset($g_panel_ids[$i]);
						continue;
					}
					$tests_by_groups[$gid] = $wpdb->get_col("select te_name from ci_test_panels
					where te_in_req= 1 and te_{$gid} = 1 ");
					$tests_by_groups[$gid] = array_map('strtoupper', $tests_by_groups[$gid]);

					$custom_profile = 'Comprehensive';
					if ($gid == 'basic') {
						$custom_profile = 'Custom Low Risk';
					} else if ($gid == 'standard') {
						$custom_profile = 'Custom Mid Risk';
					} else if ($gid == 'highrisk') {
						$custom_profile = 'Custom High Risk';
					}
				}
			} else if (count($g_panel_ids) > 0) {
				foreach ($g_panel_ids as $gid) {
					$tests_by_groups[$gid] = array();
					$groupdata = $wpdb->get_row("select * from ci_test_groups where tg_ID = {$gid}");
					if (!$groupdata->tg_only_group && !$groupdata->tg_notestcheckbox) {
						//find tests
						$tests_by_groups[$gid] = $wpdb->get_col("select te_ID from ci_test_panels 
						where (te_group_id = {$gid} or te_group_id in (select tg_ID from ci_test_groups where tg_parent = {$gid}) )
						and te_in_req = 1");
					}


					if (!$is_antigen) {
						$group_name = $groupdata->tg_name;
						$is_antigen = stripos($group_name, 'antigen') !== false;
						if ($is_antigen) {
							$antigen_group_id = $gid;
							$antigen_test_name = $wpdb->get_var("select te_name from ci_test_panels
																where te_group_id = {$gid}
																");
							if (empty($antigen_test_name)) {
								$antigen_test_name = 'COVID-19 ANTIGEN';
							} else {
								$antigen_test_name = strtoupper($antigen_test_name);
							}
						}
					}
				}
			} else if ((empty($btype) || $btype == 'pcr') && !$enable_select_test) {
				$g_panel_ids[] = $wpdb->get_var("select tg_ID from ci_test_groups 
			                where (tg_is_covid=1 or tg_name ='nCoV-2019') 
			                and tg_type='pcr'");
			}

			$ref_labs_codes = array();
			$rsrl = $wpdb->get_results("select lab_ID , lab_dba_init from ci_labs 
			                where (lab_is_ref = 1 or lab_is_ref = 2) and lab_dba_init!='' ");
			foreach ($rsrl as $r) {
				if (strlen($r->lab_dba_init) >= 3) {
					$r->lab_dba_init = strtoupper($r->lab_dba_init);
					$ref_labs_codes[$r->lab_dba_init] = $r->lab_ID;
				}
			}


			$usermetaarr = array(
				'cli_ID' => $g_clinic_id,
				'doc_ID' => $g_doc_id,
				'panel_ids' => $g_panel_ids,
				'lastfile' => $filename,
			);
			update_user_meta($user_id, 'tr_prefill_covid_opts', $usermetaarr);

			$g_doc_name 	= '';
			$g_clinic_name 	= '';
			$clinic 		= array();
			$all_doctors 	= array();

			if ($g_clinic_id > 0) {
				$clinic = $wpdb->get_row("select * from ci_clinic where cID = {$g_clinic_id}", ARRAY_A);
				$g_clinic_name = $clinic['cli_clinic'];
				if ($g_doc_id > 0) {
					$doctor = $wpdb->get_row("select doc_first,doc_last from ci_doc where doc_ID = {$g_doc_id}");
					$g_doc_name = $doctor->doc_last . ', ' . $doctor->doc_first;
				}
			}

			$filepath = $log_dir . $filename;
			if (file_exists($filepath)) {
				$filepath = $log_dir . time() . '_' . $filename;
			}


			if (move_uploaded_file($filetmp, $filepath)) {
				$required_columns = array(
					'assen' => array('accession number', 'accession', 'ascension', 'unique_id'),
					'first_name' => array('first name', 'firstname', 'first_name'),
					'last_name' => array('last name', 'lastname', 'last_name')
				);
				if ($only_patient || $assen_not_required) {
					unset($required_columns['assen']);
				}


				$data = trpdd_get_data($filepath, $ext, $required_columns, true, false, false);


				if (empty($data['header'])) {
					if (isset($data['missing'])) {
						$missing = array_map('ucwords', $data['missing']);
						$missing = implode(', ', $missing);
						Tr_Session_Class::add_error("Required Columns: " . $missing);
					} else {
						Tr_Session_Class::add_error("Required Columns: Accession Number, First Name, Last Name");
					}
				} else {

					$total          = 0;
					$inserted_count = 0;
					$updated_count  = 0;
					$exists_count   = 0;

					if (empty($data['data'])) {
						Tr_Session_Class::add_error("Empty data");
					}


					foreach ($data['data'] as $row) {

						$assen  = trpdd_get_string_exist($row, array(
							'accession number',
							'accession',
							'ascension'
						));
						$oldreq = trpdd_get_string_exist($row, array(
							'ref. accession',
							'Ref. Accession',
							'unique_id',
							'reference accession',
							'reference',
						));

						if (!empty($assen)) {
							$assen2 = preg_replace("/[^a-zA-Z0-9-]/", '', $assen);
							if (!empty($assen2)) {
								$assen = $assen2;
							}
						}

						$diagnostic_codes = trpdd_get_string_exist($row, array('diagnostic codes', 'diagnostic code'));

						if (empty($diagnostic_codes)) {
							$diagnostic_codes = $df_diagnostic_codes;
						}

						$collectorname = trpdd_get_string_exist($row, array('collector name', 'collector_name', 'collector'));


						$type_of_specimen = $df_type_of_specimen;



						$result          = trpdd_get_string_exist($row, array('result', 'results'));

						$ssn             = trpdd_get_string_exist($row, array('ssn', 'patient ssn'));
						$mrn             = trpdd_get_string_exist($row, array('barcode', 'mrn'));
						$pa_dob          = trpdd_get_string_exist($row, array(
							'date of birth:',
							'dob',
							'birth date',
							'date_of_birth'
						));

						$pa_gender  = trpdd_get_string_exist($row, array('gender at birth:', 'gender'));
						$doc        = trpdd_get_string_exist($row, array(
							'submission date',
							'doc',
							'date of collection',
							'date_collected'
						));
						$pa_first   = trpdd_get_string_exist($row, array('first name', 'firstname', 'first', 'first_name'));
						$pa_last    = trpdd_get_string_exist($row, array('last name', 'lastname', 'last', 'last_name'));
						$pa_middle  = trpdd_get_string_exist($row, array('middle name', 'middle', 'middle_name'));
						$pa_address = trpdd_get_string_exist($row, array(
							'street address',
							'street address line',
							'street',
							'address'
						));
						if ($enable_patient_unit_room_bed) {
							$pa_unit    = trpdd_get_string_exist($row, array('unit', 'patient unit'));
							$pa_room    = trpdd_get_string_exist($row, array('room', 'patient room'));
							$pa_bed     = trpdd_get_string_exist($row, array('bed', 'patient bed'));
						}
						$pa_city    = trpdd_get_string_exist($row, array('city', 'patient city'));
						$pa_state   = trpdd_get_string_exist($row, array('state / province', 'state'));

						$pa_zip     = trpdd_get_string_exist($row, array(
							'postal / zip code',
							'postal/zip code',
							'zip code',
							'zip'
						));
						$pa_phone   = trpdd_get_string_exist($row, array(
							'mobile phone number:',
							'phone number',
							'home phone',
							'phone',
							'participant phone number'
						));
						$pa_email   = trpdd_get_string_exist($row, array(
							'email',
							'participant email',
							'email address'
						));

						$pa_race = trpdd_get_string_exist($row, array(
							'race',
							'patient race'
						));

						$pa_ethnicity = trpdd_get_string_exist($row, array(
							'ethnicity',
							'patient ethnicity'
						));

						if (empty($pa_address)) {
							$pa_address = $clinic['cli_street'];
							$pa_city    = $clinic['cli_city'];
							$pa_state   = $clinic['cli_state'];
							$pa_zip     = $clinic['cli_zip'];
						}

						if (empty($pa_phone)) {
							//$pa_phone = $clinic['cli_om-phone'];
						}
						$pa_dob = trim($pa_dob);
						$doc = trim($doc);
						if (in_array($ext, array('xls', 'xlsx'))) {
							if (!empty($doc) && strpos($doc, '/') === false && strpos($doc, '-') === false) {

								$doc = gmdate('Y-m-d H:i:s', PHPExcel_Shared_Date::ExcelToPHP($doc));
							}
							$doc = preg_replace("/([0-9]{2})-([0-9]{2})-([0-9]{4})/", '$3-$1-$2', $doc); //09-08-2021

							if (!empty($pa_dob) && strpos($pa_dob, '/') === false && strpos($pa_dob, '-') === false) {
								$pa_dob = gmdate('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($pa_dob));
							}

							$pa_dob = preg_replace("/([0-9]{2})-([0-9]{2})-([0-9]{4})/", '$3-$1-$2', $pa_dob); //09-08-2021

						}

						if (!empty($doc)) {
							$doc = date('Y-m-d H:i:s', strtotime($doc));
						}

						if (empty($doc)) {
							$doc = date_i18n('Y-m-d H:i:s');
						}

						if (!empty($pa_dob)) {
							if (strpos($pa_dob, '/') > 0) {
								list($month, $day, $year) = explode('/', $pa_dob);
								$pa_dob = intval($year) . '-' . intval($month) . '-' . intval($day);
							}

							$pa_dob = date('Y-m-d', strtotime($pa_dob));
						}


						$pa_gender = stripos($pa_gender, 'u') !== false ? 'U' : $pa_gender;
						$pa_gender = stripos($pa_gender, 'f') !== false ? 'F' : 'M';

						//fix phone
						if (!empty($pa_phone)) {
							$pa_phone = str_replace(array(' ', '(', ')', '-', '.'), '', $pa_phone);
						}

						$pa = array(
							'pa_first'   => ($pa_first),
							'pa_last'    => ($pa_last),
							'pa_middle'    => ($pa_middle),
							'pa_address' => ($pa_address),
							'pa_city'    => ($pa_city),
							'pa_state'   => ($pa_state),
							'pa_zip'     => ($pa_zip),
							'pa_phone'   => ($pa_phone),
							'pa_dob'     => ($pa_dob),
							'pa_gender'  => $pa_gender,
							'pa_email'   => ($pa_email),
							'pa_mrn'     => $mrn,
							'pa_ssn'     => $ssn,
						);

						if ($enable_patient_unit_room_bed) {
							$pa['pa_unit'] = $pa_unit;
							$pa['pa_room'] = $pa_room;
							$pa['pa_bed'] = $pa_bed;
						}

						if (!empty($pa_race)) {
							if (stripos($pa_race, 'White or Caucasian') !== false) {
								$pa_race = '2106-3';
							} else if (stripos($pa_race, 'American Indian or Alaska  Native') !== false) {
								$pa_race = '1002-5';
							} else if (stripos($pa_race, 'Asian') !== false) {
								$pa_race = '2028-9';
							} else if (stripos($pa_race, 'African American') !== false || stripos($pa_race, 'Black') !== false) {
								$pa_race = '2054-5';
							} else if (stripos($pa_race, 'Pacificn') !== false) {
								$pa_race = '2076-8';
							}
							$pa['pa_race'] = substr($pa_race, 0, 10);
						}

						if (!empty($ethnicity)) {
							$pa['pa_ethnicity'] = $ethnicity;
						}



						$pa['pa_state'] = strtoupper($pa['pa_state']);
						if (strlen($pa['pa_state']) > 2) {
							$found_state = false;
							foreach ($all_states as $k => $txt) {
								if (stripos($pa['pa_state'], $txt) !== false) {
									$pa['pa_state'] = $k;
									$found_state    = true;
									break;
								}
							}

							if (!$found_state) {
								$pa['pa_state'] = substr($pa['pa_state'], 0, 2);
							}
						}


						$insurance_name  = trpdd_get_string_exist($row, array(
							'insurance health plan name:',
							'insurance',
							'insurance name'
						));
						$insurance_cares  = trpdd_get_string_exist($row, array(
							'cares'
						));

						$insurance_name2 = trpdd_get_string_exist($row, array(
							'second insurance:',
							'second insurance'
						));

						$ins_address = trpdd_get_string_exist($row, array(
							'insurance address:',
							'insurance address'
						));

						$ins_city = trpdd_get_string_exist($row, array(
							'insurance city:',
							'insurance city'
						));

						$ins_state = trpdd_get_string_exist($row, array(
							'insurance state:',
							'insurance state'
						));

						$ins_zip = trpdd_get_string_exist($row, array(
							'insurance zip:',
							'insurance zip'
						));

						if (empty($ins_city)) {
							list($ins_address, $ins_city, $ins_state, $ins_zip) = trpdd_parse_address($ins_address);
						}




						if (strlen($ins_city) == 2 && strlen($ins_state) > 4) {
							$ins_zip = $ins_state;
							$ins_state = $ins_city;
							$ins_city = '';
						}

						$ins_address2 = trpdd_get_string_exist($row, array(
							'insurance address:',
							'insurance address'
						));
						list($ins_address2, $ins_city2, $ins_state2, $ins_zip2) = trpdd_parse_address($ins_address2);

						$ins_member_id = trpdd_get_string_exist($row, array(
							'insurance policy number:',
							'member id'
						));
						$ins_group_id  = trpdd_get_string_exist($row, array(
							'insurance group number:',
							'group #'
						));

						$ins_member_id2 = trpdd_get_string_exist($row, array(
							'second member id',
							'member id (si)',
							'member id si'
						));
						$ins_group_id2  = trpdd_get_string_exist($row, array(
							'second group #',
							'second group id',
							'group # (si)'
						));

						if ($only_patient) {

							if (empty($pa['pa_first']) && empty($pa['pa_last'])) {
								continue;
							}
							$total++;
							$patient = false;
							if (!empty($pa['pa_mrn'])) {
								$patient = $wpdb->get_row("select * from ci_patients 
		                                where pa_mrn = '{$pa['pa_mrn']}' 
		                                    and pa_dob = '{$pa['pa_dob']}' ");
							} else if (!empty($pa['pa_ssn'])) {
								$patient = $wpdb->get_row("select * from ci_patients 
		                                    where pa_ssn = '{$pa['pa_ssn']}' 
		                                    and pa_dob = '{$pa['pa_dob']}' ");
							}
							if (!$patient && !empty($pa['pa_first']) && !empty($pa['pa_last']) && !empty($pa['pa_dob'])) {
								$patient = $wpdb->get_row($wpdb->prepare(
									"select * from ci_patients 
			                                    where pa_first = %s
													and pa_last = %s
													 and pa_dob = %s ",
									$pa['pa_first'],
									$pa['pa_last'],
									$pa['pa_dob']
								));
							}



							$pa['pa_clinic_ID'] = $g_clinic_id;
							if (!$patient) {
								$rs = $wpdb->insert('ci_patients', $pa);

								$inserted_count++;
								$patient_id = $wpdb->insert_id;
							} else {
								$patient_id = $patient->pa_ID;

								$rs = $wpdb->update('ci_patients', $pa, array('pa_ID' => $patient->pa_ID));
								$updated_count++;
							}

							if (!empty($insurance_name)) {
								$insurace_data = array(
									'ins_first'     => $pa_first,
									'ins_last'      => $pa_last,
									'ins_relation'  => 'Self',
									'ins_company'   => $insurance_name,
									'ins_member_id' => $ins_member_id,
									'ins_group_id'  => $ins_group_id,
									'ins_address'   => trpdd_stringval($ins_address),
									'ins_city'      => trpdd_stringval($ins_city),
									'ins_state'     => trpdd_stringval($ins_state),
									'ins_zip'       => trpdd_stringval($ins_zip),
									'pa_ID'         => $patient_id
								);



								$insurance_id = $wpdb->get_var("select ins_ID 
	                                                        from ci_insurances 
	                                                        where pa_ID = {$patient_id} 
	                                                                and ins_is_second = 0
	                                                        order by ins_ID desc 
	                                                        limit 1");


								if ($insurance_id == 0) {
									$wpdb->insert('ci_insurances', $insurace_data);
									$insurance_id = $wpdb->insert_id;
								} else {
									$update_ins = array();
									foreach ($insurace_data as $k => $vl) {
										if (!empty($vl)) {
											$update_ins[$k] = $vl;
										}
									}

									if (count($update_ins) > 0) {
										$wpdb->update('ci_insurances', $update_ins, array('ins_ID' => $insurance_id));
									}
								}
							}

							if (!empty($insurance_name2)) {
								$insurace_data2 = array(
									'ins_first'     => $pa_first,
									'ins_last'      => $pa_last,
									'ins_relation'  => 'Self',
									'ins_company'   => $insurance_name2,
									'ins_member_id' => $ins_member_id2,
									'ins_group_id'  => $ins_group_id2,
									'ins_address'   => trpdd_stringval($ins_address2),
									'ins_city'      => trpdd_stringval($ins_city2),
									'ins_state'     => trpdd_stringval($ins_state2),
									'ins_zip'       => trpdd_stringval($ins_zip2),
									'pa_ID'         => $patient_id,
									'ins_is_second' => 1
								);

								$insurance_id2 = $wpdb->get_var("select ins_ID 
	                                                        from ci_insurances 
	                                                        where pa_ID = {$patient_id} 
	                                                                and ins_is_second = 1
	                                                        order by ins_ID desc 
	                                                        limit 1");


								if ($insurance_id2 == 0) {
									$wpdb->insert('ci_insurances', $insurace_data2);
									$insurance_id2 = $wpdb->insert_id;
								} else {
									$update_ins = array();
									foreach ($insurace_data2 as $k => $vl) {
										if (!empty($vl)) {
											$update_ins[$k] = $vl;
										}
									}

									if (count($update_ins) > 0) {
										$wpdb->update('ci_insurances', $update_ins, array('ins_ID' => $insurance_id2));
									}
								}
							}
						} else {

							$ref_lab = array('lab_dba_init' => '');
							if (isset($_POST['reflab']) && $_POST['reflab'] > 0) {
								$ref_lab = $wpdb->get_row("select * from ci_labs 
								where lab_ID = {$_POST['reflab']}
								and lab_ref_assen_init = 1", ARRAY_A);
							}

							if (empty($assen) && isset($row['unique_id']) && !empty($row['unique_id'])) {

								$bat = null;
								if (!empty($oldreq)) {
									$bat = $wpdb->get_row("select * from ci_saved_batches where oldreq = '{$oldreq}' ");
									if (!$bat) {
										$bat = $wpdb->get_row("select * from {$table_batch} where oldreq = '{$oldreq}' ");
									}
								}

								if (!$bat) {
									$assen = tr_get_new_assen($btype, false, $ref_lab['lab_dba_init'], $ref_lab);
								} else {
									$assen = $bat->assen;
								}
							} else if ((count($g_panel_ids) > 0 || $assen_not_required || $has_screening) && empty($assen)) {
								$assen = tr_get_new_assen($btype, false, $ref_lab['lab_dba_init'], $ref_lab);
							}


							if (
								empty($assen) || (empty($pa_first) && empty($pa_last))
							) {
								continue;
							}
							$total++;

							$exists = $wpdb->get_var("select mid from {$table_batch} where assen = '{$assen}' ");
							if ($exists) {

								if (isset($_GET['onlyupdatedate'])) {
									$bat = $wpdb->get_row("select * from {$table_batch} where assen = '{$assen}' ");
									if ($bat) {
										$patient_id = $bat->patient_id;
										$wpdb->update('ci_patients', array('pa_dob' => $pa_dob), array('pa_ID' => $patient_id));
										$wpdb->update($table_batch, array(
											'dob' => $pa_dob,
											'doc' => $doc
										), array('mid' => $bat->mid));
									}
								}

								$exists_count++;
								continue;
							}

							$bat = $wpdb->get_row("select * from ci_saved_batches where assen = '{$assen}' ");


							$npi       = trpdd_get_string_exist($row, array('npi'));
							$doc_first = trpdd_get_string_exist($row, array('doc_first'));
							$doc_last  = trpdd_get_string_exist($row, array('doc_last'));

							if (empty($doc_first)) {
								$doc_name = trpdd_get_string_exist($row, array('doctor'));
								if (!empty($doc_name)) {
									if (stripos($doc_name, ',') !== false) {
										list($doc_last, $doc_first) = explode(',', $doc_name);
									} else {
										$doc_name = str_ireplace('Dr.', '', $doc_name);
										$doc_name = trim($doc_name);
										list($doc_first, $doc_last) = explode(' ', $doc_name);
									}
									$doc_last  = trim($doc_last);
									$doc_first = trim($doc_first);
								}
							}



							$patient_id      = $bat && $bat->patient_id > 0 ? $bat->patient_id : 0;
							$insurance_id    = $bat && $bat->insurance1 > 0 ? $bat->insurance1 : 0;
							$insurance_id2   = $bat && $bat->insurance2 > 0 ? $bat->insurance2 : 0;
							$clinic_id       = $bat && $bat->cli_ID > 0 ? $bat->cli_ID : 0;
							$doc_id          = $bat && $bat->doc_ID > 0 ? $bat->doc_ID : 0;
							$tests_requested = $bat && $bat->panel_tests_requested ? $bat->panel_tests_requested : '';
							$tests_requested_arr = explode(',', $tests_requested);


							if (isset($_GET['onlyupdatedate'])) {
								if ($bat) {
									$wpdb->update('ci_patients', array('pa_dob' => $pa_dob), array('pa_ID' => $patient_id));
									$wpdb->update($table_batch, array(
										'dob' => $pa_dob,
										'doc' => $doc
									), array('mid' => $bat->mid));
								}
								continue;
							}
							if (isset($_REQUEST['debug1'])) {
								var_dump($pa);
								echo '<br>';
								echo '<br>';
								var_dump($doc);
								echo '<br>';
								echo '<br>';
								continue;
							}

							if ($patient_id > 0) {
								$patient = $wpdb->get_row(
									$wpdb->prepare(
										"select * from ci_patients 
	                                            where pa_ID = {$patient_id}
	                                                and pa_first = %s
													and pa_last = %s 
													and pa_dob = %s ",
										$pa['pa_first'],
										$pa['pa_last'],
										$pa['pa_dob']
									)
								);
								if (!$patient) {
									$patient_id = 0;
								}
							}

							if ($patient_id == 0) {
								$patient = null;

								if (!empty($pa['pa_mrn'])) {
									$patient = $wpdb->get_row("select * from ci_patients 
								            where pa_mrn = '{$pa['pa_mrn']}' 
								            and pa_dob = '{$pa['pa_dob']}' ");
								}

								if (!$patient && !empty($pa['pa_first']) && !empty($pa['pa_last']) && !empty($pa['pa_dob'])) {
									$patient = $wpdb->get_row($wpdb->prepare(
										"select * from ci_patients 
	                                            where pa_first = %s
													and pa_last = %s 
													and pa_dob = %s ",
										$pa['pa_first'],
										$pa['pa_last'],
										$pa['pa_dob']
									));
								}

								if ($patient) {
									$patient_id = $patient->pa_ID;
								}

								if (empty($patient_id)) {
									$rs = $wpdb->insert('ci_patients', $pa);
									$patient_id = $wpdb->insert_id;
								}
							}

							if ($patient) {
								$update = array();
								foreach ($pa as $k => $vl) {
									if (!empty($vl)) {
										$update[$k] = $vl;
									}
								}


								if (!empty($update)) {
									$wpdb->update('ci_patients', $update, array('pa_ID' => $patient_id));
								}
							}

							//main insurance

							if (empty($insurance_name)) {
								$insurance_name = 'No Insurance - Cash';
							}

							$insurace_data = array(
								'ins_first'     => $pa_first,
								'ins_last'      => $pa_last,
								'ins_relation'  => 'Self',
								'ins_company'   => $insurance_name,
								'ins_member_id' => $ins_member_id,
								'ins_group_id'  => $ins_group_id,
								'ins_address'   => trpdd_stringval($ins_address),
								'ins_city'      => trpdd_stringval($ins_city),
								'ins_state'     => trpdd_stringval($ins_state),
								'ins_zip'       => trpdd_stringval($ins_zip),
							);
							if ($insurance_cares == 'x') {
								$insurance_name = 'No Insurance - CARES Act';
								$insurace_data['ins_company'] = $insurance_name;
								$insurace_data['ins_cares_act'] = '1';
							}


							if ($insurance_id == 0) {
								$wpdb->insert('ci_insurances', $insurace_data);
								$insurance_id = $wpdb->insert_id;
							} else {
								//$insurance_name = $bat->insurance;
								$update_ins = array();
								foreach ($insurace_data as $k => $vl) {
									if (!empty($vl)) {
										$update_ins[$k] = $vl;
									}
								}

								if (count($update_ins) > 0) {
									$wpdb->update('ci_insurances', $update_ins, array('ins_ID' => $insurance_id));
								}
							}

							//second insurance
							if (!empty($insurance_name2)) {


								$insurace_data = array(
									'ins_first'     => $pa_first,
									'ins_last'      => $pa_last,
									'ins_relation'  => 'Self',
									'ins_company'   => trpdd_stringval($insurance_name2),
									'ins_member_id' => $ins_member_id2,
									'ins_group_id'  => $ins_group_id2
								);

								if ($insurance_id2 == 0) {
									$insurace_data['ins_is_second'] = 1;
									$wpdb->insert('ci_insurances', $insurace_data);
									$insurance_id2 = $wpdb->insert_id;
								} else {
									$insurance_name2 = $bat->insurance;
									$update_ins      = array();
									foreach ($insurace_data as $k => $vl) {
										if (!empty($vl)) {
											$update_ins[$k] = $vl;
										}
									}

									if (count($update_ins) > 0) {
										$wpdb->update('ci_insurances', $update_ins, array('ins_ID' => $insurance_id2));
									}
								}
							} else if ($insurance_id2 > 0) {
								$ins2 = $wpdb->get_row("select * from ci_insurances 
							                        where ins_ID = {$insurance_id2}");
								if ($ins2->ins_relation == 'Self') {
									$update_ins = array(
										'ins_first' => $pa_first,
										'ins_last'  => $pa_last,
									);
									$wpdb->update('ci_insurances', $update_ins, array('ins_ID' => $insurance_id2));
								}
							}

							//check tests in file
							if (!isset($_POST['panel_ids']) || empty($_POST['panel_ids'])) {
								$loinc_codes = trpdd_get_string_exist($row, array('loinc code', 'loinccode', 'loinc', 'tests'));
								if (!empty($loinc_codes)) {
									$loinc_codes = explode(';', $loinc_codes);

									foreach ($loinc_codes as $loinc_code) {
										$loinc_code = trim($loinc_code);
										if (empty($loinc_code)) {
											continue;
										}
										$has_test_loic_code = false;
										$testobjs = $wpdb->get_results(
											"select te_ID,te_group_id 
										from ci_test_panels te
										JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
										where te_type = '{$btype}' 
										and tg_show_req = 1
										and (te_loinc_code = '{$loinc_code}' )
										and te_in_req = 1
										order by tg_position asc
										limit 1"
										);

										if (count($testobjs) > 0) {
											$has_test_loic_code = true;
											foreach ($testobjs as $testobj) {
												if ($testobj && $testobj->te_ID > 0) {
													$group_str = 'GROUP_' . $testobj->te_group_id;
													if (!in_array($group_str, $tests_requested_arr)) {
														$tests_requested_arr[] = $group_str;
													}

													$tests_requested_arr[] = $testobj->te_ID;
													$g_panel_ids = array();
												}
											}
										} else {
											$group_id = $wpdb->get_var("select tg_ID from ci_test_groups 
											where tg_type = '{$btype}' 
											and tg_show_req = 1
											and tg_cpt_code='{$loinc_code}'
											
											");
											if ($group_id > 0) {
												$testobjs = $wpdb->get_results(
													"select te_ID,te_group_id 
												from ci_test_panels te
												JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
												where te_type = '{$btype}' 
												and (
													tg_ID = {$group_id} 
													or  
													(tg_parent = {$group_id} and tg_show_req = 0)
													)
												and te_in_req = 1
												order by tg_position asc"
												);

												$has_test_loic_code = true;
												foreach ($testobjs as $testobj) {
													if ($testobj && $testobj->te_ID > 0) {
														$group_str = 'GROUP_' . $testobj->te_group_id;
														if (!in_array($group_str, $tests_requested_arr)) {
															$tests_requested_arr[] = $group_str;
														}

														$tests_requested_arr[] = $testobj->te_ID;
														$g_panel_ids = array();
													}
												}
											}
										}
									}
								}
							}

							if (count($g_panel_ids) > 0) {

								foreach ($g_panel_ids as $gid) {
									if ($btype != 'tox') {
										$group_str = 'GROUP_' . $gid;
										if (!in_array($group_str, $tests_requested_arr)) {
											$tests_requested_arr[] = $group_str;
										}
									}


									foreach ($tests_by_groups[$gid] as $tid) {
										if (!in_array($tid, $tests_requested_arr)) {
											$tests_requested_arr[] = $tid;
										}
									}
								}
							}

							$tests_requested = implode(',', $tests_requested_arr);

							$tests_requested = trim($tests_requested, ',');


							$batdata = array(
								'assen'                 => $assen,
								'doc'                   => $doc,
								'batch_type'            => 'I',
								'lab_id'                => CI_Base_Lab::get_current_lab_id(),
								'patient_id'            => $patient_id,
								'insurance1'            => $insurance_id,
								'insurance2'            => $insurance_id2,
								'insurance'             => $insurance_name,
								'panel_tests_requested' => $tests_requested,
								'type_of_specimen'      => $type_of_specimen,
								'dagnostic_codes1'      => $diagnostic_codes,
								'author_ID'             => $user_id,
								'oldreq'                => $oldreq,
								'deleted'               => '0',
								'comments'              => $filename,
								'is_dna'              	=> $btype_code,
							);

							if ($btype == 'tox') {
								$batdata['type_of_specimen'] = $_POST['type_of_specimen'];
							}

							if (!empty($custom_profile)) {
								$batdata['custom_profile'] = $custom_profile;
							}
							if (!empty($pa['pa_dob'])) {
								$batdata['dob'] = $pa['pa_dob'];
							}

							if (!empty($pa['pa_last']) || !empty($pa['pa_first'])) {
								$batdata['patient_name'] = trim($pa['pa_last'] . ',' . $pa['pa_first'], ',');
							}

							if ($is_antigen && !empty($result)) {

								$batdata['notes_report'] = $antigen_group_id . '|' . $antigen_test_name . '|' . $result;
							} else {
								$batdata['notes_report'] = '';
							}

							$assen3c = strtoupper(substr($assen, 0, 3));
							if (isset($_POST['reflab']) && $_POST['reflab'] > 0) {
								$batdata['ref_lab_ID'] = $_POST['reflab'];
							} else if (isset($ref_labs_codes[$assen3c])) {
								$batdata['ref_lab_ID'] = $ref_labs_codes[$assen3c];
							} else if ($g_clinic_id > 0) {
								//check reflab by clinic
								$cli_ref_lab_ID = $wpdb->get_var("select cli_ref_lab_ID from ci_clinic where cID = {$g_clinic_id}");
								if ($cli_ref_lab_ID > 0) {
									$batdata['ref_lab_ID'] = $cli_ref_lab_ID;
								}
							}


							if (isset($batdata['ref_lab_ID']) && $batdata['ref_lab_ID'] == $batdata['lab_id']) {
								$batdata['ref_lab_ID'] = 0;
							}

							if (!empty($collectorname)) {
								$arr = explode(' ', $collectorname);
								$col_first = $arr[0];
								$col_last = $arr[1];
								if (substr_count($collectorname, ' ') > 2) {
									$col_first = $arr[0] . ' ' . $arr[1];
									$col_last = isset($arr[3]) ? $arr[2] . ' ' . $arr[3] : $arr[2];
								}
								$col_ID = $wpdb->get_var($wpdb->prepare("select col_ID from ci_collector where col_first = % and col_last = %", $col_first, $col_last));
								if ($col_ID > 0) {
									$batdata['col_ID'] = $col_ID;
								} else {
									$wpdb->insert('ci_collector', array('col_first' => $col_first, 'col_last' => $col_last));
									$batdata['col_ID'] = $wpdb->insert_id;
								}
								if ($batdata['col_ID'] > 0 && $g_clinic_id > 0) {

									$wpdb->query("insert IGNORE into ci_clinic_collector set col_ID = {$batdata['col_ID']}, cli_ID = {$g_clinic_id} ");
								}
							}


							if ($g_clinic_id > 0) {
								$batdata['cli_ID']              = $g_clinic_id;
								$batdata['requesting_practice'] = $g_clinic_name;
								$doc_id                         = $g_doc_id;
								$doc_name                       = $g_doc_name;



								if (isset($clinic['cli_ref_lab_ID']) && $clinic['cli_ref_lab_ID'] > 0 && (!isset($batdata['ref_lab_ID']) || $batdata['ref_lab_ID'] == 0)) {
									$batdata['ref_lab_ID'] = $clinic['cli_ref_lab_ID'];
								}

								if (!empty($npi)) {
									if (isset($all_doctors[$npi])) {
										$doctor = $all_doctors[$npi];
									} else {
										$doctor = $wpdb->get_row("select doc_ID, doc_first, doc_last 
									                from ci_doc where doc_npi='{$npi}'");
										if (!$doctor) {
											$doc_insert = array(
												'doc_first' => $doc_first,
												'doc_last'  => $doc_last,
												'doc_npi'   => $npi
											);
											$wpdb->insert('ci_doc', $doc_insert);
											$doc_insert['doc_ID'] = $wpdb->insert_id;
											$doctor               = (object) $doc_insert;
										}
										$all_doctors[$npi] = $doctor;
									}
									$doc_id   = $doctor->doc_ID;
									$doc_name = $doctor->doc_last . ', ' . $doctor->doc_first;

									$wpdb->query("insert IGNORE into ci_clinic_doc set doc_ID = {$doc_id}, cli_ID = {$g_clinic_id} ");
								}

								if ($doc_id > 0) {
									$batdata['doc_ID']               = $doc_id;
									$batdata['requesting_physician'] = $doc_name;
								}
							}


							if ($bat) {
								$wpdb->update('ci_saved_batches', $batdata, array('mid' => $bat->mid));
								$updated_count++;
								$mid = $bat->mid;
							} else {

								$batdata['batch_created'] = date_i18n('Y-m-d H:i:s');

								$wpdb->insert('ci_saved_batches', $batdata);
								$inserted_count++;

								$mid = $wpdb->insert_id;
							}

							if ($has_screening && $mid > 0) {
								$specimen_tbl = 'ci_result_test_speciments_saved';
								$screenings = tr_pdd_get_result_validity(1, $batdata['batch_created'], $mid, true);

								foreach ($screenings as $arr) {
									$tid = $arr['u_ID'];
									$update = array(
										'mid' => $mid,
										'testid' => $tid,
										'checked' => 1
									);
									$exists = $wpdb->get_var("select mid from {$specimen_tbl} where mid = {$mid} and testid={$tid} ");
									if ($exists) {
										$wpdb->update($specimen_tbl, $update, array('mid' => $mid, 'testid' => $tid));
									} else {
										$wpdb->insert($specimen_tbl, $update);
									}
								}
							}
						}
					}

					Tr_Session_Class::add('Imported Successfully!');
					Tr_Session_Class::add('Total: ' . $total);
					Tr_Session_Class::add('Inserted: ' . $inserted_count);
					Tr_Session_Class::add('Updated: ' . $updated_count);
					Tr_Session_Class::add('Exist: ' . $exists_count);
				}
			} else {
				Tr_Session_Class::add_error("Upload Fail!");
			}
		} else {
			Tr_Session_Class::add_error("Your Uploaded file not support!");
		}
	}

	$where_clinic = '';
	switch ($current_role) {
		case 'lab_rep':
		case 'lab_rep_w2':

			$rep_id = get_user_meta($user_id, '_rep_id', true);
			$rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


			$clis = $wpdb->get_results("select * from ci_clinic where `cli_rep-ID`={$rep_id}", ARRAY_A);
			$cls_id = array();
			foreach ($clis as $row) {
				$cls_id[] = $row['cID'];
			}
			$cls_id = implode(', ', $cls_id);
			$where_clinic .= " and cID in  ({$cls_id})";
			break;

		case 'doctor':
			$doc_id = get_user_meta($user_id, '_doc_id', true);
			$where_clinic .= " and cID in (select cli_ID from ci_clinic_doc where doc_ID = {$doc_id}) ";
			break;

		case 'clinic':

		case 'multi_site_manager':
		case 'office_manager':
			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$where_clinic .= " and cID = {$current_clinic} ";
			} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {

				$cid = get_user_meta($user_id, '_clinic_id', true);
				$cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
				$cli_ids = implode(',', $cli_ids);

				$where_clinic .= " and cID in ( {$cli_ids} )";
			}
			break;
	}

	$clinics = $wpdb->get_results("select cID, cli_clinic from ci_clinic 
	                        where cli_status = 1 {$where_clinic} 
	                        order by cli_clinic asc");

	$doctors = $wpdb->get_results("select doc.doc_ID, 
	                                        doc.doc_first, 
	                                        doc.doc_last,
	                                        cd.cli_ID
	                            from ci_doc doc
	                            join ci_clinic_doc cd ON doc.doc_ID = cd.doc_ID
	                            order by doc.doc_first asc", ARRAY_A);

	$opts = get_user_meta($user_id, 'tr_prefill_covid_opts', true);
	if (!is_array($opts)) $opts = array();
	$selected_cli = isset($opts['cli_ID']) ? $opts['cli_ID'] : 0;
	$selected_doc = isset($opts['doc_ID']) ? $opts['doc_ID'] : 0;



	if ($is_bulk) {
		$panels = $wpdb->get_results("select tg_name,tg_ID,tg_type from ci_test_groups 
		where tg_show_req = '1' and tg_type!='tox' order by tg_position asc", ARRAY_A);
		if (in_array('tox', $ci_all_btypes)) {


			$panels[] = array(
				'tg_ID' => 'screening',
				'tg_name' => 'Screening',
				'tg_type' => 'tox'
			);

			$panels[] = array(
				'tg_ID' => 'highrisk',
				'tg_name' => 'Custom High Risk',
				'tg_type' => 'tox'
			);


			$panels[] = array(
				'tg_ID' => 'standard',
				'tg_name' => 'Custom Mid Risk',
				'tg_type' => 'tox'
			);
			$panels[] = array(
				'tg_ID' => 'basic',
				'tg_name' => 'Custom Low Risk',
				'tg_type' => 'tox'
			);
			$panels[] = array(
				'tg_ID' => 'comprehensive',
				'tg_name' => 'Comprehensive',
				'tg_type' => 'tox'
			);
		}
	} else {
		$panels = $wpdb->get_results("select tg_name,tg_ID,tg_type from ci_test_groups 
		where tg_show_req = '1' and tg_type='pcr' order by tg_position asc", ARRAY_A);
	}

?>
	<div class="wrap">
		<?php Tr_Session_Class::show() ?>
		<div class="form-wrap center">
			<form method="post" enctype="multipart/form-data" id="upload_form" class="need_validate">
				<div class="frow">
					<h3>Import File</h3>
					<p>csv format file support only</p>

					<div class="frow card_file">
						<a class="button selectfile" href="#">Choose Files</a>
						<span class="view"></span>
						<input type="file" name="import_file" accept="text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
					</div>
				</div>

				<div class="frow">
					<label style="display: inline-block;">Clinic</label>
					<select name="cli_ID" required class="njs_select2" style="width:350px;display: inline-block;">
						<option value="">Select Clinic</option>
						<?php
						foreach ($clinics as $clinic) {
							$selected = $clinic->cID == $selected_cli || count($clinics) == 1 ? 'selected' : '';
							echo '<option value="' . $clinic->cID . '" ' . $selected . '>' . $clinic->cli_clinic . '</option>';
						}
						?>
					</select>
				</div>
				<div class="frow" id="doctors_dv" style="display: none;">
					<label style="display: inline-block;">Doctor</label>
					<select name="doc_ID" class="njs_select2" style="width:350px;display: inline-block;"></select>
				</div>

				<?php
				if ($enable_req_lab) {
					echo '<div class="frow">';
					echo '<label style="display: inline-block;">Ref Lab</label>';
					echo '<select name="reflab" style="width:350px;display: inline-block">';
					echo '<option value="">Select</option>';
					$ref_labs = CI_Base_Lab::get_ref_labs();
					foreach ($ref_labs as $rl) {
						echo '<option value="' . $rl->lab_ID . '">' . $rl->lab_name . '</option>';
					}
					echo '</select>';
					echo '</div>';
				}
				if ($is_bulk) {
					echo '<div class="frow" id="btype_div" ' . (count($ci_all_btypes) == 1 ? 'style="display:none"' : '') . '>';
					echo '<label style="display: inline-block;">Type</label>';
					echo '<select name="btype" id="btype" style="width:350px;display: inline-block" required>';
					echo '<option value="">Select</option>';
					foreach ($ci_all_btypes as $bt) {
						$selected = count($ci_all_btypes) == 1 ? 'selected' : '';
						echo '<option value="' . $bt . '" ' . $selected . '>' . tr_pdd_get_typename($bt) . '</option>';
					}
					echo '</select>';
					echo '</div>';

					if (in_array('tox', $ci_all_btypes)) {
						$types = tr_pdd_get_type_of_specimens();
						echo '<div class="frow showbytype" data-type="tox" style="display:none">';
						echo '<label style="display: inline-block;">Type of Specimen</label>';
						echo '<select name="type_of_specimen" id="type_of_specimen" style="width:350px;display: inline-block" >';
						echo '<option value="">Select</option>';
						foreach ($types as $t => $txt) {
							echo '<option value="' . $t . '">' . $txt . '</option>';
						}
						echo '</select>';
						echo '</div>';
					}
				}

				if ($enable_select_test) {
				?>
					<div class="frow" id="panels_div">
						<label style="display: inline-block;">Panel</label>
						<select name="panel_ids[]" id="panelids" multiple class="njs_select2" style="width:350px;display: inline-block;"></select>
					</div>

				<?php
				}

				if ($enable_only_patient) {
				?>
					<div class="frow">
						<label><input type="checkbox" name="only_patient" value="1" <?php checked($is_bulk) ?> /> Patient Demographics Only </label>
					</div>
				<?php
				}
				?>

				<div class="frow">
					<br>
					<input type="submit" class="button-primary" value="Upload">
					<input type="hidden" name="insert_files" value="upload">
					<br><br>
					<br><br>
					<br><br>
					<a class="button download_demo" href="#">Demographics Export</a>
					<a class="button export_last_assen">Export last Accession</a>
				</div>
			</form>
		</div>
	</div>
	<style>
		.frow>label {
			display: inline-block;
			min-width: 130px;
			text-align: right;
			padding-right: 5px;
		}
	</style>
	<script>
		var $doctors = <?php echo json_encode($doctors) ?>;
		var $selected_doc = <?php echo intval($selected_doc) ?>;
		var $panels = <?php echo json_encode($panels) ?>;
		jQuery(function($) {

			$('[name="cli_ID"]').change(function() {
				var $cli_id = $(this).val();
				var $html = '<option value="">Select Doctor</option>';
				var $count_doc = 0;
				for (i in $doctors) {
					var $doctor = $doctors[i];
					if ($doctor.cli_ID == $cli_id) {
						$count_doc++;
						var $selected = $doctor.doc_ID == $selected_doc ? 'selected' : '';
						if (!$selected_doc) $selected = 'doc_selected';
						$html += '<option value="' + $doctor.doc_ID + '" ' + $selected + '>' + $doctor.doc_first + ' ' + $doctor.doc_last + '</option>';
					}
				}
				if ($count_doc == 1 && !$selected_doc) {
					$html = $html.replace('doc_selected', 'selected');
				}
				$('[name="doc_ID"]').html($html).val('');
				try {
					$('[name="doc_ID"]').trigger('change');
				} catch (e) {}
				if ($cli_id == '') {
					$('#doctors_dv').hide();
				} else {
					$('#doctors_dv').show();
				}


			}).change();
			$('a.download_demo').click(function() {
				var $cid = $('[name="cli_ID"]').val();
				var $addingurl = '';
				if ($('select[name="panel_id"]').length > 0) {
					var $optvl = $('select[name="panel_id"] option:selected').html();
					console.log("ovl:" + $optvl);
					if ($optvl.indexOf('Antigen') > -1) {
						$addingurl += '&antigen=1';
					}
					$addingurl += '&pid=' + $('select[name="panel_id"]').val();

				}
				location.href = '<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'download_demo')) ?>&cid=' + $cid + $addingurl;
				return false;
			})
			$('a.export_last_assen').click(function() {
				var $cid = $('[name="cli_ID"]').val();
				var $addingurl = '';
				if ($('select[name="panel_id"]').length > 0) {
					var $optvl = $('select[name="panel_id"] option:selected').html();
					if ($optvl.indexOf('Antigen') > -1) {
						$addingurl += '&antigen=1';
					}
					$addingurl += '&pid=' + $('select[name="panel_id"]').val();
				}
				location.href = '<?php Tr_Base_Class_V6::link(array('trajax' => '1', 'act' => 'export_last_assen')) ?>&cid=' + $cid + $addingurl;
				return false;
			})
			$('[name="only_patient"]').change(function() {
				if ($(this).is(':checked')) {
					$('#doctors_dv').hide();
					$('#panels_div').hide();
					$('#btype_div').hide();
					$('#btype').prop('required', false);
					$('#panelids').prop('required', false);
				} else {
					$('#doctors_dv').show();
					$('#panels_div').show();
					$('#btype_div').show();
					$('#btype').prop('required', true);
					//	$('#panelids').prop('required',true);
				}
			})


			function trpdd_build_panels($btype) {
				var $html = '';
				for (i in $panels) {
					var $p = $panels[i];
					if ($p.tg_type == $btype) {
						$html += '<option value="' + $p.tg_ID + '">' + $p.tg_name + '</option>';
					}
				}
				$('select#panelids').html($html).change();
				$('.showbytype').hide();
				$('.showbytype[data-type="' + $btype + '"]').show();

			}

			if ($('select[name="btype"]').length > 0) {
				$('select[name="btype"]').change(function() {
					trpdd_build_panels($(this).val());
				}).change();

				$('#panelids').on('select2:select', function(e) {
					var id = e.params.data.id;
					if ($('select[name="btype"]').val() == 'tox' && id !== 'screening') {
						$('#panelids option').each(function() {
							if ($(this).attr('value') !== 'screening' && $(this).attr('value') !== id) {
								$(this).prop('selected', false).removeAttr('selected');
							}
						})
						$('#panelids').change();
					}
				})
			} else if ($('select#panelids').length > 0) {
				trpdd_build_panels('pcr');
			}

			$('[name="only_patient"]').change();

		})
	</script>
<?php
}
