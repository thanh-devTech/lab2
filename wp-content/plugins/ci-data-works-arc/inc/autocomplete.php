<?php
global $wpdb, $blog_id, $current_role;


$auto   = isset($_REQUEST['autocomplete']) ? $_REQUEST['autocomplete'] : '';
$search = isset($_REQUEST['term']) ? $_REQUEST['term'] : '';
$btype  = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : '';
$target  = isset($_REQUEST['target']) ? $_REQUEST['target'] : '';

$current_id = 0;
if (!empty($_SERVER['HTTP_REFERER'])) {
	parse_str($_SERVER['HTTP_REFERER'], $resultarr);
	if (isset($resultarr['id']) && $resultarr['id'] > 0) {
		$current_id = $resultarr['id'];
	}
}

if (empty($btype)) {
	if (stripos($_SERVER['HTTP_REFERER'], 'pcr') !== false) {
		$btype = 'pcr';
	} else if (stripos($_SERVER['HTTP_REFERER'], 'blood') !== false) {
		$btype = 'blood';
	} else {
		$btype = 'tox';
	}
}


$batch_tbl  = tr_pdd_get_table_by_btype($btype);

$user_id    = get_current_user_id();
$req_settings = get_option('ci_req_settings_' . $btype, array());


switch ($auto) {
	case 'ins_company_auto':

		$sql = $wpdb->prepare("select payer_id as id,
                                    payer_name as value,
                                    payer_name as label,
                                    payer_address,
                                    payer_city,
                                    payer_state,
                                    payer_mnemonic,
                                    payer_zip,
                                    payer_phone
                                    from ci_payer_list
                                where payer_name like %s limit 10", $search . '%');
		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'requesting_practice_auto':
		$df_doctor = tr_get_option('ci_pdf_data', 'df_doctor_clinic');

		$lab_id 	= isset($_REQUEST['labid']) ? intval($_REQUEST['labid']) : CI_Base_Lab::get_current_lab_id();
		$where_clis = '';
		$more_fields = '';
		if ($df_doctor) {
			$more_fields .= ", cli.cli_default_doc_ID ";
		}

		if (CI_Base_Lab::has_user_labs()) {
			$lab_str 	= CI_Base_Lab::get_user_lab_ids_str();
			$where_clis .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
		}

		$sql = $wpdb->prepare("select
                                    cli.cID as id,
                                    cli.cli_clinic as value,
                                    cli.cli_clinic as label,
                                    cli.cli_show_submit_print_bc as pbtn
                                    {$more_fields}
                                    from ci_clinic cli
                                    where cli.cli_clinic like %s {$where_clis} limit 40", $search . '%');
		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'get_doctor_info':
		$did = $_REQUEST['doc_id'];

		$return = array('success' => 0);
		$doctor = $wpdb->get_row("select * from ci_doc where doc_ID = {$did} ");
		if ($doctor) {
			$return['success'] = 1;
			$return['doctor_name'] = $doctor->doc_last . ', ' . $doctor->doc_first;
			$return['doc_ID'] = $doctor->doc_ID;
		}
		wp_send_json($return);
		break;

	case 'clinic_rep_auto':
		$lab_id 	= isset($_REQUEST['labid']) ? intval($_REQUEST['labid']) : CI_Base_Lab::get_current_lab_id();
		$where_clis = '';

		if (CI_Base_Lab::has_user_labs()) {
			$lab_str 	= CI_Base_Lab::get_user_lab_ids_str();
			$where_clis .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
		}

		$sql = $wpdb->prepare("select
                                    cli.cID as id,
                                    cli.cli_clinic as value,
                                    cli.cli_clinic as label,
                                    r.rep_ID,
                                    trim(concat(r.rep_first,' ',r.rep_last)) as rep_Name
                                    from ci_clinic cli
                                    left JOIN ci_lab_reps r ON r.rep_ID = cli.`cli_rep-ID`
                                    where cli.cli_clinic like %s {$where_clis} limit 40", $search . '%');

		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'rep_auto':
		$lab_id 	= isset($_REQUEST['labid']) ? intval($_REQUEST['labid']) : 0;
		$where 		= '';

		if ($lab_id > 0) {
			$where .= " and r.`lab_ID` = '{$lab_id}' ";
		} else if (CI_Base_Lab::has_user_labs()) {
			$lab_str = CI_Base_Lab::get_user_lab_ids_str();
			$where .= " and r.`lab_ID` in ( {$lab_str} ) ";
		}

		$sql = $wpdb->prepare("select
                                    r.rep_ID as id,
                                    trim(concat(r.rep_first,' ',r.rep_last)) as value,
                                    trim(concat(r.rep_first,' ',r.rep_last,' ',r.rep_email)) as label
                                    from ci_lab_reps r
                                    where (r.rep_first like %s or r.rep_last like %s) {$where} limit 40", $search . '%', $search . '%');

		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'autocomment_coll_last_auto':
		$blood_settings = get_option('ci_req_settings_blood', array());

		$columns = "col_last,
                    col_ID,
                    col_first,
                    col_middle,
                    col_rdate,
                    col_phone,
                    col_email";
		if ($blood_settings['Phlebotomist']) {
			$columns .= ",col_phlebotomist_code ";
		}
		$sql = $wpdb->prepare("select
                    {$columns}
                    from ci_collector
                             where  col_last like %s
                             order by col_first asc limit 10", $search . '%');
		$results = $wpdb->get_results($sql, ARRAY_A);

		$return = array();
		foreach ($results as $row) {
			$row['label'] = tr_trim_name($row['col_last'] . ', ' . $row['col_first']);
			$row['value'] = $row['col_last'];
			if (!empty($row['col_phone'])) {
				$row['label'] .= ' (' . $row['col_phone'] . ')';
			}
			$return[] = $row;
		}
		wp_send_json($return);
		break;

	case 'requesting_patients_last_auto':

		$cli_id = $_REQUEST['cli_id'];

		list($last_name, $first_name) = explode(',', $search);

		$where = " pa.pa_last like '" . trim($last_name) . "%' ";
		if (!empty($first_name)) {
			$where .= " and pa.pa_first like '" . trim($first_name) . "%' ";
		}
		if ($cli_id > 0) {
			if ($search == '*') {
				$where = " pa.pa_last != '' ";
			}

			/*
			$child_query = array();
			foreach(tr_pdd_get_batch_types() as $bt)
			{
				$table_batch = tr_pdd_get_table_by_btype($bt);
				$child_query[] = " select distinct(patient_id) from {$table_batch} where cli_ID = {$cli_id} ";
			}
			$child_query = implode(" UNION ALL ", $child_query);
			$sql = "select distinct(patient_id) from ({$child_query}) rs";

			$pa_ids = $wpdb->get_col($sql);
			*/

			$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$cli_id} ");


			$pa_ids = implode(',', $pa_ids);
			if (empty($pa_ids)) $pa_ids = '0';
			$where .= " and (pa.pa_ID in ({$pa_ids}) 
								or pa.pa_clinic_ID = {$cli_id} 
								)";
		} else if (tr_current_role_in(array('office_manager',  'clinic'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);

				$where .= " and (pa.pa_ID in ({$pa_ids}) 
								or pa.pa_clinic_ID = {$current_clinic} 
								or pa.pa_clinic_ID = 0 
								or pa.pa_clinic_ID is null)";
			} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
				$cid = get_user_meta(get_current_user_id(), '_clinic_id', true);
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID in (select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}') ");
				$pa_ids = implode(',', $pa_ids);
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
		} else if (tr_current_role_in(array('multi_site_manager'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);

				$where .= " and (pa.pa_ID in ({$pa_ids}) 
								or pa.pa_clinic_ID = {$current_clinic} 
								or pa.pa_clinic_ID = 0 
								or pa.pa_clinic_ID is null)";
			} else  if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
				//can see all clinics
			} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
				$ms_cli_ids = get_user_meta($uid, '_ms_clinic_ids', true);
				if (empty($ms_cli_ids)) {
					$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				}
				$ms_cli_ids_str = implode(',', array_map('intval', $ms_cli_ids));
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID in ({$ms_cli_ids_str}) ");
				$pa_ids = implode(',', $pa_ids);
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
		}

		$sql = "SELECT * FROM ci_patients pa 
                    WHERE {$where}
                    order by pa.pa_first asc
            limit 50
        ";
		$patients = array();
		$results = $wpdb->get_results($sql, ARRAY_A);

		$card_dir = tr_get_ins_card_dir();

		foreach ($results as $r) {
			$r['pa_last'] = tr_pdd_capitalized($r['pa_last']);
			$r['pa_first'] = tr_pdd_capitalized($r['pa_first']);
			$r['pa'] = $r;
			$r['label'] = $r['pa_last'] . ', ' . $r['pa_first'] . ' (' . $r['pa_dob'] . ')';
			$r['value'] = $r['pa_last'];

			$ins1 = $wpdb->get_row(
				"select * from ci_insurances 
				where pa_ID = '{$r['pa_ID']}'
				  and ins_is_second = 0
				order by ins_ID desc 
				limit 1",
				ARRAY_A
			);

			if ($ins1) {
				$r['ins1'] = $ins1;
				$r['ins2'] = $wpdb->get_row(
					"select * from ci_insurances 
								where pa_ID = '{$r['pa_ID']}' 
                                and ins_ID!={$ins1['ins_ID']}
                                 and ins_company!='N/a' and ins_company != '{$ins1['ins_company']}'
                                order by ins_ID desc limit 1",
					ARRAY_A
				);

				$bat = $wpdb->get_row("select ins_card_front,ins_card_back,patient_id_front,patient_id_back 
												from {$batch_tbl}
                                                where patient_id = {$r['pa_ID']} and ins_card_front!=''
                                                order by ins_card_front,ins_card_back,patient_id_front,patient_id_back desc limit 1");

				$r['card_url'] = $card_dir['url'];
				$images = array();
				$images['ins_card_front'] = isset($bat->ins_card_front) ? $bat->ins_card_front : '';
				$images['ins_card_back'] = isset($bat->ins_card_back) ? $bat->ins_card_back : '';
				$images['patient_id_front'] = isset($bat->patient_id_front) ? $bat->patient_id_front : '';
				$images['patient_id_back'] = isset($bat->patient_id_back) ? $bat->patient_id_back : '';

				foreach ($images as $k => $vl) {
					$img = $card_dir['path'] . '/' . $vl;
					if (!is_file($img)) {
						$images[$k] = '';
					}
				}
				$r['images'] = $images;
			} else {
				$r['ins1'] = false;
			}

			//search tests & medication
			if (!tr_get_option('ci_pdf_data', 'disable_prev_orders')) {
				if ($btype == 'blood') {
					$whereba1 = '';
					if ($current_id > 0) {
						$whereba1 .= " and mid < {$current_id} ";
					}
					$lastb = $wpdb->get_row(
						"select mid,
					panel_tests_requested,
					medication 
					from {$batch_tbl}
						where patient_id = {$r['pa_ID']} 
						{$whereba1}
						order by mid desc limit 1"
					);

					if ($lastb) {
						if (isset($req_settings['disable_auto_ptests']) && $req_settings['disable_auto_ptests']) {
							$return['last_tests'] = '';
						} else {
							$r['last_tests'] = $lastb->panel_tests_requested;
						}

						$r['medication'] = $lastb->medication;
						$r['midmed'] = $lastb->mid;
					}
				} else if ($btype == 'tox') {
					$ds = $wpdb->get_row("select current_medication 
									from {$batch_tbl} where patient_id = '{$r['pa_ID']}' 
									and current_medication!='' 
									and current_medication is not null 
									order by mid desc
									limit 1", ARRAY_A);
					// $r['diagnostic'] = isset($ds['dagnostic_codes1']) ? $ds['dagnostic_codes1'] : '';
					$r['medication'] = isset($ds['current_medication']) ? $ds['current_medication'] : '';
				}
			} else {
				if (tr_get_option('ci_pdf_data', 'auto_fill_dx_code')) {
					$dx_codes = $wpdb->get_var("select dagnostic_codes1 from {$batch_tbl} where patient_id = '{$r['pa_ID']}' and dagnostic_codes1 !='' order by mid desc limit 1");
					$r['diagnostic'] = $dx_codes ? $dx_codes : '';
				}
			}



			$patients[] = $r;
		}
		wp_send_json($patients);
		break;
	case 'requesting_physician_auto':
		$where_clis = '';
		$cli_id = @$_REQUEST['cli_id'];
		if (isset($_REQUEST['cli_id']) && $cli_id >= 0) {
			$where_clis .= " and cli.cID = '{$cli_id}' ";
		} else if (CI_Base_Lab::has_user_labs()) {
			$lab_str 	= CI_Base_Lab::get_user_lab_ids_str();
			$where_clis .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
		}

		$doc_specialty 	= '';
		$has_commo 		= true;

		if ($search == '*') $search = '';

		if (strpos($search, ',') > 0) {
			list($doc_last, $doc_first) = explode(',', $search, 2);

			//check specialty
			$doc_first = trim($doc_first);
			$doc_last = trim($doc_last);
			if (strpos($doc_first, ' ') > 0) {
				list($doc_first, $doc_specialty) = explode(' ', $doc_first, 2);
			}
			$has_commo = true;
		} else {
			list($doc_first, $doc_last) = explode(' ', $search, 2);
			$doc_first = trim($doc_first);
			$doc_last = trim($doc_last);
		}
		$sql = $wpdb->prepare(
			"select doc.doc_ID,doc.doc_first,doc.doc_last,doc_specialty,
                            cp.cus_individuals
                            from ci_doc as doc
                            JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                            JOIN ci_clinic cli ON cd.cli_ID = cli.cID
                            left JOIN ci_custom_providers cp ON doc.doc_ID = cp.doc_ID
                            where
                            ((doc.doc_first like %s and doc.doc_last like %s ) or
                            (doc.doc_first like %s and doc.doc_last like %s ))
                            
                            {$where_clis}
                            group by doc.doc_ID limit 10",
			$doc_first . '%',
			$doc_last . '%',
			$doc_last . '%',
			$doc_first . '%'
		);
		//echo $sql;exit;
		$results = $wpdb->get_results($sql, ARRAY_A);

		$return = array();
		foreach ($results as $row) {

			if ($has_commo) {
				$vl = tr_trim_name($row['doc_last'] . ', ' . $row['doc_first']);
			} else {
				$vl = tr_trim_name($row['doc_first'] . ' ' . $row['doc_last']);
			}
			$vl = stripslashes($vl);
			$r = array(
				'id' => $row['doc_ID'],
				'value' => $vl,
				'label' => $vl,
				'low' => $row['cus_individuals'],
				'med' => $row['cus_med_risk'],
				'high' => $row['cus_high_risk'],
			);
			$return[] = $r;
		}
		wp_send_json($return);
		break;

	case 'requesting_physician_npi_auto':
		$where_clis = '';
		if (CI_Base_Lab::has_user_labs()) {
			$lab_str 	= CI_Base_Lab::get_user_lab_ids_str();
			$where_clis .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
		}

		$sql = $wpdb->prepare("select doc.*
                            from ci_doc as doc
                            JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                            JOIN ci_clinic cli ON cd.cli_ID = cli.cID
                            where
                            doc.doc_npi like %s
                            {$where_clis}
                            group by doc.doc_ID
                            limit 10", $search . '%');

		$results = $wpdb->get_results($sql, ARRAY_A);

		$return = array();
		foreach ($results as $row) {
			$label = $row['doc_npi'];
			$label .= ' - ' . tr_trim_name($row['doc_last'] . ', ' . $row['doc_first']);
			$r = array(
				'id' => $row['doc_ID'],
				'label' => $label,
				'value' => $row['doc_npi'],
				'doc_first' => $row['doc_first'],
				'doc_last' => $row['doc_last'],
				'doc_specialty' => $row['doc_specialty'],
				'doc_phone' => $row['doc_phone'],
				'doc_email' => $row['doc_email'],
			);
			$return[] = $r;
		}
		wp_send_json($return);
		break;

	case 'requesting_physician_last_auto':
		$where_clis = '';
		$cli_id = @$_REQUEST['cli_id'];
		if (isset($_REQUEST['cli_id'])) {
			$where_clis .= " and cli.cID = '{$cli_id}' ";
		} else if (CI_Base_Lab::has_user_labs()) {
			$lab_str 	= CI_Base_Lab::get_user_lab_ids_str();
			$where_clis .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
		}

		if (!isset($_REQUEST['provider'])) {
			$where_clis .= " and doc.doc_npi != ''";
		}

		$sql = $wpdb->prepare("select doc.*,cp.cus_ID
                            from ci_doc as doc
                            JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                            JOIN ci_clinic cli ON cd.cli_ID = cli.cID
                            left join ci_custom_providers cp ON cp.doc_ID = doc.doc_ID and cp.cli_ID= cli.cID
                            where
                            doc.doc_last like %s
                            {$where_clis}
                             group by doc.doc_ID
                             limit 10", $search . '%');

		//echo $sql;exit;
		$results = $wpdb->get_results($sql, ARRAY_A);

		$return = array();
		foreach ($results as $row) {

			$vl = tr_trim_name($row['doc_last'] . ', ' . $row['doc_first']);
			if (!empty($row['doc_npi'])) {
				$vl .= ' - ' . $row['doc_npi'];
			}
			$vl = stripslashes($vl);
			$r = array(
				'id' => $row['doc_ID'],
				'label' => $vl,
				'value' => $row['doc_last'],
				'doc_first' => $row['doc_first'],
				'doc_specialty' => $row['doc_specialty'],
				'doc_phone' => $row['doc_phone'],
				'doc_email' => $row['doc_email'],
				'doc_npi' => $row['doc_npi'],
				'cus_ID' => $row['cus_ID']
			);
			$return[] = $r;
		}
		wp_send_json($return);
		break;

	case 'requesting_physician_first_auto':
		$where_clis = '';
		$cli_id = @$_REQUEST['cli_id'];
		if (isset($_REQUEST['cli_id'])) {
			$where_clis .= " and cli.cID = '{$cli_id}' ";
		} else if (CI_Base_Lab::has_user_labs()) {
			$lab_str 	= CI_Base_Lab::get_user_lab_ids_str();
			$where_clis .= " and cli.`cli_lab-ID` in ( {$lab_str} ) ";
		}

		if (!isset($_REQUEST['provider'])) {
			$where_clis .= " and doc.doc_npi != ''";
		}

		$sql = $wpdb->prepare("select doc.*,cp.cus_ID
                            from ci_doc as doc
                            JOIN ci_clinic_doc cd ON cd.doc_ID = doc.doc_ID
                            JOIN ci_clinic cli ON cd.cli_ID = cli.cID
                            left join ci_custom_providers cp ON cp.doc_ID = doc.doc_ID and cp.cli_ID= cli.cID
                            where
                            doc.doc_first like %s
                            {$where_clis}
                             group by doc.doc_ID
                             limit 10", $search . '%');

		$results = $wpdb->get_results($sql, ARRAY_A);

		$return = array();
		foreach ($results as $row) {

			$vl = tr_trim_name($row['doc_last'] . ', ' . $row['doc_first']);
			if (!empty($row['doc_npi'])) {
				$vl .= ' - ' . $row['doc_npi'];
			}

			$vl = stripslashes($vl);
			$r = array(
				'id' => $row['doc_ID'],
				'label' => $vl,
				'value' => $row['doc_first'],
				'doc_last' => $row['doc_last'],
				'doc_specialty' => $row['doc_specialty'],
				'doc_phone' => $row['doc_phone'],
				'doc_email' => $row['doc_email'],
				'doc_npi' => $row['doc_npi'],
				'cus_ID' => $row['cus_ID']
			);
			$return[] = $r;
		}
		wp_send_json($return);
		break;

	case 'patient_last_auto':

		$where = '';
		if (stripos($search, ',') > 0) {
			list($last_name, $first_name) = explode(',', $search);
		} else {
			list($last_name, $first_name) = explode(' ', $search);
		}

		$last_name = trim($last_name);
		$first_name = trim($first_name);

		if (tr_current_role_in(array('office_manager', 'clinic'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
				$cid = get_user_meta(get_current_user_id(), '_clinic_id', true);
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID in (select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}') ");
				$pa_ids = implode(',', $pa_ids);
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
		} else if (tr_current_role_in(array('multi_site_manager'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);

				$where .= " and (pa.pa_ID in ({$pa_ids}) 
								or pa.pa_clinic_ID = {$current_clinic} 
								or pa.pa_clinic_ID = 0 
								or pa.pa_clinic_ID is null)";
			} else  if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
				$ms_cli_ids = get_user_meta($uid, '_ms_clinic_ids', true);
				if (empty($ms_cli_ids)) {
					$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				}
				$ms_cli_ids_str = implode(',', array_map('intval', $ms_cli_ids));
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID in ({$ms_cli_ids_str}) ");
				$pa_ids = implode(',', $pa_ids);
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
		}

		$where .= $wpdb->prepare(" and pa_last like %s ", $last_name . '%');

		if (!empty($first_name)) {
			$where .= $wpdb->prepare(" and pa_first like %s ", $first_name . '%');
		}
		$more_fields = '';
		if (tr_get_option('ci_pdf_data', 'enable_patient_notes')) {
			$more_fields .= ", pa_notes";
		}
		$sql = "select pa_ID ,
                    pa_first,
                    pa_last,
                    pa_dob,
                    pa_ssn,
                    pa_address 
                    {$more_fields}
                    from ci_patients
                 where 1 {$where}
                 order by pa_first asc, pa_ssn desc, pa_address desc
                  limit 70";

		$results = $wpdb->get_results($sql, ARRAY_A);

		$return = array();
		foreach ($results as $row) {

			$row['pa_last'] = tr_pdd_capitalized($row['pa_last']);
			$row['pa_first'] = tr_pdd_capitalized($row['pa_first']);

			$vl = tr_trim_name($row['pa_last'] . ', ' . $row['pa_first']) . ' (' . $row['pa_dob'] . ')';
			$vl .= ' - ' . $row['pa_ssn'] . ' - ' . $row['pa_address'];

			$rowarr = array(
				'id' => $row['pa_ID'],
				'label' => $vl,
				'value' => $row['pa_last'],
				'pa_first' => $row['pa_first'],
				'pa_dob' => $row['pa_dob']
			);

			if (!tr_get_option('ci_pdf_data', 'disable_prev_orders')) {
				if ($btype == 'blood') {
					$whereba1 = '';
					if ($current_id > 0) {
						$whereba1 .= " and mid < {$current_id} ";
					}
					$lastb = $wpdb->get_row(
						"select mid,
					panel_tests_requested,
					medication 
					from {$batch_tbl}
						where patient_id = {$row['pa_ID']} 
						{$whereba1}
						order by mid desc limit 1"
					);

					if ($lastb) {
						if (isset($req_settings['disable_auto_ptests']) && $req_settings['disable_auto_ptests']) {
							$rowarr['last_tests'] = '';
						} else {
							$rowarr['last_tests'] = $lastb->panel_tests_requested;
						}

						$rowarr['medication'] = $lastb->medication;
						$rowarr['midmed'] = $lastb->mid;
					}
				} else if ($btype == 'tox') {
					$ds = $wpdb->get_row("select dagnostic_codes1,current_medication 
									from {$batch_tbl} where patient_id = '{$row['pa_ID']}' 
									and current_medication!='' 
									and current_medication is not null 
									order by mid desc
									limit 1
									", ARRAY_A);
					$rowarr['diagnostic'] = isset($ds['dagnostic_codes1']) ? $ds['dagnostic_codes1'] : '';
					$rowarr['medication'] = isset($ds['current_medication']) ? $ds['current_medication'] : '';
				}
			}
			$return[] = $rowarr;
		}
		wp_send_json($return);
		break;

	case 'patient_first_auto':

		list($first_name, $last_name) = explode(' ', $search);

		$last_name = trim($last_name);
		$first_name = trim($first_name);

		$where = '';
		if (tr_current_role_in(array('office_manager', 'clinic'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);
				if (empty($pa_ids)) {
					$pa_ids = '0';
				}
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
				$cid = get_user_meta(get_current_user_id(), '_clinic_id', true);
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID in (select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}') ");
				$pa_ids = implode(',', $pa_ids);
				if (empty($pa_ids)) {
					$pa_ids = '0';
				}
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
		} else if (tr_current_role_in(array('multi_site_manager'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);

				$where .= " and (pa.pa_ID in ({$pa_ids}) 
								or pa.pa_clinic_ID = {$current_clinic} 
								or pa.pa_clinic_ID = 0 
								or pa.pa_clinic_ID is null)";
			} else  if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
				$ms_cli_ids = get_user_meta($uid, '_ms_clinic_ids', true);
				if (empty($ms_cli_ids)) {
					$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				}
				$ms_cli_ids_str = implode(',', array_map('intval', $ms_cli_ids));
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID in ({$ms_cli_ids_str}) ");
				$pa_ids = implode(',', $pa_ids);
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
		}

		$where .= $wpdb->prepare(" and pa_last like %s ", $last_name . '%');

		if (!empty($first_name)) {
			$where .= $wpdb->prepare(" and pa_first like %s ", $first_name . '%');
		}

		$sql = "select pa_ID ,pa_first,pa_last,pa_dob from ci_patients
                             where  1 {$where}
                             order by pa_first asc limit 70";



		$results = $wpdb->get_results($sql, ARRAY_A);

		$return = array();
		foreach ($results as $row) {

			$row['pa_last'] = tr_pdd_capitalized($row['pa_last']);
			$row['pa_first'] = tr_pdd_capitalized($row['pa_first']);

			$vl = tr_trim_name($row['pa_last'] . ', ' . $row['pa_first']) . ' (' . $row['pa_dob'] . ')';
			$return[] = array(
				'id' => $row['pa_ID'],
				'label' => $vl,
				'value' => $row['pa_first'],
				'pa_last' => $row['pa_last'],
				'pa_dob' => $row['pa_dob']
			);
		}
		wp_send_json($return);
		break;

	case 'patient_auto':
		$cli_ID     = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
		$joins = '';
		if (strpos($search, ',') > 0) {
			list($pa_last, $pa_first) = explode(',', $search);
		} else {
			$pa_last = $search;
			$pa_first = '';
		}
		$pa_last = trim($pa_last);
		$pa_first = trim($pa_first);

		$where = $wpdb->prepare("(pa_last like %s and pa_first like %s) AND (pa_last !='' OR pa_first!='')", $pa_last . '%', $pa_first . '%');

		if (tr_current_role_in(array('office_manager', 'clinic'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) 
								from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);
				if (empty($pa_ids)) {
					$pa_ids = '0';
				}
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
				$cid = get_user_meta(get_current_user_id(), '_clinic_id', true);

				$pa_ids = $wpdb->get_col("select distinct(patient_id) 
												from {$batch_tbl} 
                                where cli_ID in (select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}') ");
				$pa_ids = implode(',', $pa_ids);
				if (empty($pa_ids)) {
					$pa_ids = '0';
				}
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
			if ($cli_ID > 0) {
				$where .= " and (
					pa.pa_clinic_ID = {$cli_ID} 
					or pa.pa_ID in (select distinct(patient_id) 
									from {$batch_tbl} 
									where cli_ID = {$cli_ID}) 
				)";
			}
		} else if (tr_current_role_in(array('multi_site_manager'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);

				$where .= " and (pa.pa_ID in ({$pa_ids}) 
								or pa.pa_clinic_ID = {$current_clinic} 
								or pa.pa_clinic_ID = 0 
								or pa.pa_clinic_ID is null)";
			} else  if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
				$ms_cli_ids = get_user_meta($uid, '_ms_clinic_ids', true);
				if (empty($ms_cli_ids)) {
					$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				}
				$ms_cli_ids_str = implode(',', array_map('intval', $ms_cli_ids));
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID in ({$ms_cli_ids_str}) ");
				$pa_ids = implode(',', $pa_ids);
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
		} else if (tr_current_role_in(array('doctor'))) {
			$doc_ID = get_user_meta(get_current_user_id(), '_doc_id', true);
			$where  .= " and pa.pa_ID in (select distinct(patient_id) 
												from {$batch_tbl} 
                                                where doc_ID = {$doc_ID})";
		} else if ($cli_ID > 0) {
			$where .= " and (
								pa.pa_clinic_ID = {$cli_ID} 
								or pa.pa_ID in (select distinct(patient_id) 
												from {$batch_tbl} 
                                                where cli_ID = {$cli_ID}) 
						)";
		} else if (!empty($btype) && $btype !== 'all') {
			$joins .= " JOIN {$batch_tbl} ba ON ba.patient_id = pa.pa_ID ";
		}


		$sql = "select pa_ID ,pa_first,pa_last,pa_dob 
				from ci_patients pa
				{$joins}
                 where  {$where}
                 group by pa.pa_ID
                 order by pa_first asc limit 40";

		$results = $wpdb->get_results($sql, ARRAY_A);
		$return = array();
		if (count($results) == 0) {
			$return['error'] = $wpdb->last_error;
		}

		//$return['log'] = $wpdb->last_query;


		foreach ($results as $row) {

			$row['pa_last'] = tr_pdd_capitalized($row['pa_last']);
			$row['pa_first'] = tr_pdd_capitalized($row['pa_first']);

			$vl = tr_trim_name($row['pa_last'] . ', ' . $row['pa_first']) . ' (' . $row['pa_dob'] . ')';
			$return[] = array(
				'id' => $row['pa_ID'],
				'label' => $vl,
				'value' => $row['pa_last'] . ', ' . $row['pa_first'],
				'pa_first' => $row['pa_first'],
				'pa_dob' => $row['pa_dob']
			);
		}
		wp_send_json($return);
		break;

	case 'find_patient':

		$return     = array('status' => 'notfound', 'last_tests' => '', 'medication' => '');

		$ssn        = trim($_REQUEST['ssn']);
		$med_number = trim($_REQUEST['med_number']);
		$stype      = isset($_REQUEST['stype']) ? $_REQUEST['stype'] : 'ssn';
		$cli_ID     = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;




		$return['alert'] = ($stype == 'ssn') ? 'Not found SSN' : 'Not Found Medical Record Number';

		$lab_id = CI_Base_Lab::get_current_lab_id();
		$where = '';
		if ($stype != 'ssn') {
			$where = " pa_mrn = '{$med_number}'";
		} else {
			if ($ssn == '000-00-0000' || empty($ssn) || strpos($ssn, '000-00-0000') === 0 || strpos($ssn, '00000') === 0 || $ssn == '0' || $ssn == 'NA' || $ssn == 'N/A') {
				$ssn = '000notfoundabc000';
			}
			$where = " (pa_ssn='{$ssn}' or replace(pa_ssn,'-','')= replace('{$ssn}','-','') )";
		}

		if (tr_current_role_in(array('office_manager', 'clinic'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) 
								from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);
				if (empty($pa_ids)) {
					$pa_ids = '0';
				}
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			} else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
				$cid 	= get_user_meta(get_current_user_id(), '_clinic_id', true);

				$pa_ids = $wpdb->get_col("select distinct(patient_id) 
												from {$batch_tbl} 
                                where cli_ID in (select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}') ");
				$pa_ids = implode(',', $pa_ids);
				if (empty($pa_ids)) {
					$pa_ids = '0';
				}
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
			if ($cli_ID > 0) {
				$where .= " and (
					pa.pa_clinic_ID = {$cli_ID} 
					or pa.pa_ID in (select distinct(patient_id) 
									from {$batch_tbl} 
									where cli_ID = {$cli_ID}) 
				)";
			}
		} else if (tr_current_role_in(array('multi_site_manager'))) {

			$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
			if ($current_clinic > 0) {
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID = {$current_clinic} ");
				$pa_ids = implode(',', $pa_ids);

				$where .= " and (pa.pa_ID in ({$pa_ids}) 
								or pa.pa_clinic_ID = {$current_clinic} 
								or pa.pa_clinic_ID = 0 
								or pa.pa_clinic_ID is null)";
			} else  if (!tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
				$ms_cli_ids = get_user_meta($uid, '_ms_clinic_ids', true);
				if (empty($ms_cli_ids)) {
					$ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
				}
				$ms_cli_ids_str = implode(',', array_map('intval', $ms_cli_ids));
				$pa_ids = $wpdb->get_col("select distinct(patient_id) from {$batch_tbl} 
                                where cli_ID in ({$ms_cli_ids_str}) ");
				$pa_ids = implode(',', $pa_ids);
				$where  .= " and pa.pa_ID in ({$pa_ids})";
			}
		} else if (tr_current_role_in(array('doctor'))) {
			$doc_ID = get_user_meta(get_current_user_id(), '_doc_id', true);
			$where  .= " and pa.pa_ID in (select patient_id 
												from {$batch_tbl} 
                                                where doc_ID = {$doc_ID} )";
		} else if ($cli_ID > 0) {
			$where .= " and (
						pa.pa_clinic_ID = {$cli_ID} 
						or pa.pa_ID in (select distinct(patient_id) 
										from {$batch_tbl} 
										where cli_ID = {$cli_ID}) 
				)";
		}




		$sql = "select pa.*
                        from ci_patients pa
                        where  {$where} 
                        order by pa.pa_ID desc 
                        limit 1";



		$data = $wpdb->get_row($sql, ARRAY_A);

		if ($is_ci_admin) {
			$return['sql'] = $sql;
			$return['error'] = $wpdb->last_error;
		}

		if ($data) {

			$data['pa_last'] = tr_pdd_capitalized($data['pa_last']);
			$data['pa_first'] = tr_pdd_capitalized($data['pa_first']);

			$return['alert'] = 'found';
			$return['status'] = 'found';
			$return['data'] = $data;

			//find insurance
			if ($lab_id > 0) {
				$sql_ins = "select * from ci_insurances 
							where pa_ID = '{$data['pa_ID']}'
							 and ins_is_second = 0 
							 and ins_company!='' 
							 and ins_address!='' 
							 and ins_address!='N/A' 
				            order by ins_ID desc 
				            limit 1";

				$ins1 = $wpdb->get_row($sql_ins, ARRAY_A);

				if (!$ins1) {
					$sql_ins = "select * from ci_insurances 
							where pa_ID = '{$data['pa_ID']}'
							 and ins_is_second = 0 
							 and ins_company!='' 
				            order by ins_ID desc 
				            limit 1";

					$ins1 = $wpdb->get_row($sql_ins, ARRAY_A);
					if (!$ins1) {
						$sql_ins = "select * from ci_insurances 
							where pa_ID = '{$data['pa_ID']}'
							 and ins_is_second = 0 
				            order by ins_ID desc 
				            limit 1";

						$ins1 = $wpdb->get_row($sql_ins, ARRAY_A);
					}
				}

				if ($ins1) {
					$return['ins1'] = $ins1;
					$return['ins2'] = $wpdb->get_row(
						"select * from ci_insurances 
								where pa_ID = '{$data['pa_ID']}' 
                                and ins_ID!={$ins1['ins_ID']} 
                                and ins_is_second = 1
                                order by ins_ID desc limit 1",
						ARRAY_A
					);

					if (!$return['ins2']) {
						$return['ins2'] = $wpdb->get_row(
							"select * from ci_insurances 
								where pa_ID = '{$data['pa_ID']}' 
                                and ins_ID!={$ins1['ins_ID']} 
                                and ins_ID in (select insurance2 from {$batch_tbl} ba where ba.patient_id = '{$data['pa_ID']}' order by mid desc)
                                order by ins_ID desc limit 1",
							ARRAY_A
						);
					}

					//search images
					$sql_bat = "select ins_card_front,ins_card_back,patient_id_front,patient_id_back 
												from {$batch_tbl}
                                                where patient_id = {$data['pa_ID']} and ins_card_front!=''
                                                order by ins_card_front,ins_card_back,patient_id_front,patient_id_back desc 
                                                limit 1";

					$bat = $wpdb->get_row($sql_bat);

					$card_dir = tr_get_ins_card_dir();
					$return['card_url'] = $card_dir['url'];

					$images = array();
					$images['ins_card_front'] = $bat->ins_card_front;
					$images['ins_card_back'] = $bat->ins_card_back;
					$images['patient_id_front'] = $bat->patient_id_front;
					$images['patient_id_back'] = $bat->patient_id_back;
					foreach ($images as $k => $vl) {
						$img = $card_dir['path'] . '/' . $vl;
						if (!is_file($img)) {
							$images[$k] = '';
						}
					}

					$return['images'] = $images;
				} else {
					$return['ins1'] = false;
				}

				if (!$return['ins2']) {
					$return['ins2'] = false;
				}


				//search tests & medication
				if (!tr_get_option('ci_pdf_data', 'disable_prev_orders')) {
					if ($btype == 'blood') {

						$lastb = $wpdb->get_row(
							"select panel_tests_requested,medication 
											from {$batch_tbl}
												where patient_id = {$data['pa_ID']} 
												and (panel_tests_requested!='' 
														or medication !='')
												order by mid desc limit 1"
						);

						if ($lastb) {
							if (isset($req_settings['disable_auto_ptests']) && $req_settings['disable_auto_ptests']) {
								$return['last_tests'] = '';
							} else {
								$return['last_tests'] = $lastb->panel_tests_requested;
							}

							$return['medication'] = $lastb->medication;
						}
					} else if ($btype == 'tox') {
						$ds = $wpdb->get_row("select current_medication 
										from {$batch_tbl} where patient_id = '{$data['pa_ID']}' 
										and current_medication!='' 
										and current_medication is not null 
										order by mid desc
										limit 1
										", ARRAY_A);
						// $return['diagnostic'] = isset($ds['dagnostic_codes1']) ? $ds['dagnostic_codes1'] : '';
						$return['medication'] = isset($ds['current_medication']) ? $ds['current_medication'] : '';
					}
				} else {
					if (tr_get_option('ci_pdf_data', 'auto_fill_dx_code')) {
						$dx_codes = $wpdb->get_var("select dagnostic_codes1 from {$batch_tbl} where patient_id = '{$data['pa_ID']}' and dagnostic_codes1 !='' order by mid desc limit 1");
						$return['diagnostic'] = $dx_codes ? $dx_codes : '';
					}
				}
			}
		}
		wp_send_json($return);
		break;

	case 'ref_lab_auto':
		$sql = $wpdb->prepare("select
        lab_ID as id,
        lab_dba_init as value,
        lab_dba_init as label
        from ci_labs
         where (lab_is_ref = 1 or lab_is_ref = 2)
		 and (lab_dba like %s or lab_dba_init like %s)
         order by lab_is_ref asc limit 40", $search . '%', $search . '%');
		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'lab_auto':
		$sql = $wpdb->prepare("select
        lab_ID as id,
        lab_dba_init as value,
        lab_dba_init as label
        from ci_labs
         where (lab_dba like %s or lab_dba_init like %s) and (lab_is_ref=0 or lab_is_ref=2)
         order by lab_is_ref asc limit 40", $search . '%', $search . '%');
		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'user_auto':
		$joins = '';
		if ($blog_id > 1) {
			$joins .= " join {$wpdb->usermeta} cap ON u.ID = cap.user_id and cap.meta_key like '%{$blog_id}_capabilities' and cap.meta_value !='a:0:{}' ";
		}
		if ($target == 'gr_admins') {
			$joins .= " join {$wpdb->usermeta} cap2 ON u.ID = cap2.user_id and cap2.meta_key like '%_capabilities' and cap2.meta_value like '%lab\_manager%' ";
		}
		$where = $wpdb->prepare("where u.user_login like %s or fname.meta_value like %s or lname.meta_value like %s", $search . '%', $search . '%', $search . '%');
		$sql = "select
        u.ID as id,
        concat(u.user_login ,' (', fname.meta_value ,' ',lname.meta_value ,')') as value
        from {$wpdb->users} u
        left join {$wpdb->usermeta} fname ON fname.user_id = u.ID and fname.meta_key='first_name'
        left join {$wpdb->usermeta} lname ON lname.user_id = u.ID and lname.meta_key='last_name'
        {$joins}
         {$where}
         order by u.user_login asc limit 10";

		if (!empty($wpdb->last_error)) {
			wp_send_json(array('error' => $wpdb->last_error, 'sql' => $sql));
		}


		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);

		break;

	case 'col_auto':
		$sql = $wpdb->prepare("select
        col_ID as id,
        col_first,
        col_last,
        col_phone
        from ci_collector
         where col_first like %s 
         order by col_first asc limit 20", $search . '%');
		$rs = $wpdb->get_results($sql, ARRAY_A);
		$results = array();
		foreach ($rs as $r) {
			$name = tr_trim_name($r['col_first'] . ' ' . $r['col_last']);
			$results[] = array(
				'id' => $r['id'],
				'value' => $name,
				'phone' => $r['col_phone'],
			);
		}
		wp_send_json($results);
		break;

	case 'custom_profile_auto':
		if (strpos($search, ',') > 0) {
			list($doc_last, $doc_first) = explode(',', $search, 2);

			//check specialty
			$doc_first = trim($doc_first);
			$doc_last = trim($doc_last);
			if (strpos($doc_first, ' ') > 0) {
				list($doc_first, $doc_specialty) = explode(' ', $doc_first, 2);
			}
		} else {
			list($doc_first, $doc_last) = explode(' ', $search, 2);
			$doc_first = trim($doc_first);
			$doc_last = trim($doc_last);
		}
		$sql = $wpdb->prepare(
			"select cus.* from ci_custom_providers cus
                            where
                           ((cus.cus_first like %s and cus.cus_last like %s )
                        or
                         (cus.cus_first like %s and cus.cus_last like %s ))

                          limit 10",
			$doc_first . '%',
			$doc_last . '%',
			$doc_last . '%',
			$doc_first . '%'
		);

		$results = $wpdb->get_results($sql, ARRAY_A);
		//var_dump($results);exit;

		$return = array();
		foreach ($results as $row) {
			$profile = trim(ucfirst($row['cus_testing_profile']) . ' +' . $row['cus_individuals'], '+');
			$vl = tr_trim_name($row['cus_last'] . ', ' . $row['cus_first']);
			$vl = stripslashes($vl);
			$r = array(
				'id' => $row['cus_ID'],
				'value' => $vl,
				'label' => $vl,
				'cus_confirm_positive' => $row['cus_confirm_positive'],
				'cus_injections' => $row['cus_injections'],
				'cus_testing_profile' => strtolower($row['cus_testing_profile']),
				'cus_individuals' => $row['cus_individuals']
			);
			$return[] = $r;
		}
		wp_send_json($return);
		break;
	case 'us_states_auto';
		$return = array();
		foreach (tr_get_states() as $k => $vl) {
			if ((stripos($vl, $search) !== 0 && stripos($k, $search) !== 0) || empty($k)) {
				continue;
			}
			$label =  $vl . ' (' . $k . ')';
			$return[] = array('value' => $k, 'label' => $label);
		}
		wp_send_json($return);
		break;

	case 'user_groups_auto':
		$sql = $wpdb->prepare("select
        gr_ID as id,
        gr_name as value,
        gr_name as label
        from ci_user_groups
         where gr_name like %s
         order by gr_name asc limit 40", $search . '%');

		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'marketing_groups_auto':
		$sql = $wpdb->prepare("select
        gr_ID as id,
        gr_name as value,
        gr_name as label
        from ci_marketing_groups
         where gr_name like %s
         order by gr_name asc limit 40", $search . '%');

		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'lab_rep_auto':
		$where_rep = '';
		if ($current_role == 'distributor') {
			$dis = $wpdb->get_row("SELECT * FROM `ci_distributors` WHERE `dis_user_ID`=$user_id ");
			$dis_id = $dis->dis_ID;
			$arr_marketing_groups = $wpdb->get_results("SELECT * FROM `ci_marketing_groups` where `gr_distributor_ID`= {$dis_id} " . ($active == '0' ? "and `gr_inactive`='0'" : ''));
			$g_ids = array(-1);
			foreach ($arr_marketing_groups as $g) {
				$g_ids[] = $g->gr_ID;
			}
			$g_ids = implode(',', $g_ids);
			$where_rep .= " and (`rep_marketing_group_ID` IN ({$g_ids}) or rep_distributor_ID={$dis_id}) ";
		}

		$sql = $wpdb->prepare("select
        rep_ID as id,
        concat(rep_first,' ',rep_last) as value,
        concat(rep_first,' ',rep_last,' ',rep_email) as label
        from ci_lab_reps
         where (rep_first like %s or rep_last like %s) {$where_rep}
         order by value asc limit 40", $search . '%', $search . '%');

		$results = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($results);
		break;

	case 'auto_collector_last':
		$where = '';

		if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
			$rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
			$joins .= " join ci_rep_collector rc ON rc.col_ID = col.col_ID and rc.rep_ID = {$rep_id} ";
		} else if ($current_role == 'clinic') {
			$cli_id = get_user_meta(get_current_user_id(), '_clinic_id', true);

			$where .= " and col.cli_ID = {$cli_id} ";
		}

		if (CI_Base_Lab::has_user_labs()) {
			$lab_str = CI_Base_Lab::get_user_lab_ids_str();
			$where .= " and col.lab_ID in ( {$lab_str} ) ";
		}

		$where .= $wpdb->prepare(" and col_last like %s", $search . '%');

		$sql = "select col.col_first,
        col.col_last,
        col.col_phone,
        col.col_email,
        col.col_ID
        from ci_collector col
        {$joins}
         where 1 {$where} limit 40";
		$results = $wpdb->get_results($sql, ARRAY_A);
		$cols = array();
		foreach ($results as $row) {
			$label = $row['col_first'] . ' ' . $row['col_last'];
			if (!empty($row['col_phone'])) {
				$label .= '(' . $row['col_phone'] . ')';
			}
			$row['label'] = $label;
			$row['value'] = $row['col_last'];
			$cols[] = $row;
		}
		wp_send_json($cols);
		break;

	case 'auto_collector_first':
		$where = '';

		if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
			$rep_id = get_user_meta(get_current_user_id(), '_rep_id', true);
			$joins .= " join ci_rep_collector rc ON rc.col_ID = col.col_ID and rc.rep_ID = {$rep_id} ";
		} else if ($current_role == 'clinic') {
			$cli_id = get_user_meta(get_current_user_id(), '_clinic_id', true);

			$where .= " and col.cli_ID = {$cli_id} ";
		}

		if (CI_Base_Lab::has_user_labs()) {
			$lab_str = CI_Base_Lab::get_user_lab_ids_str();
			$where .= " and col.lab_ID  in ( {$lab_str} ) ";
		}

		$where .= $wpdb->prepare(" and col_first like %s", $search . '%');

		$sql = "select col.col_first,
        col.col_last,
        col.col_phone,
        col.col_email,
        col.col_ID
        from ci_collector col
        {$joins}
         where 1 {$where} limit 40";

		$results = $wpdb->get_results($sql, ARRAY_A);
		$cols = array();
		foreach ($results as $row) {
			$label = $row['col_first'] . ' ' . $row['col_last'];
			if (!empty($row['col_phone'])) {
				$label .= '(' . $row['col_phone'] . ')';
			}
			$row['label'] = $label;
			$row['value'] = $row['col_first'];
			$cols[] = $row;
		}
		wp_send_json($cols);
		break;

	case 'clinic':
		$clinics = array();
		$where = $wpdb->prepare(" cli_clinic like %s", $search . '%');
		$sql = "select cID as id, cli_clinic as value 
					from ci_clinic
					where {$where}
					order by cli_clinic asc";

		$clinics = $wpdb->get_results($sql, ARRAY_A);
		wp_send_json($clinics);
		break;


	case 'check_insurance_limit':
		$return = array(
			'limited' => false,
			'msg' => ''
		);
		$limited = false;
		$year_date = date_i18n('Y-01-01');
		$patient_id = intval($_REQUEST['pa_id']);

		$sql = $wpdb->prepare("select payer_udc_limit,
						payer_udc_patient_limit,
						payer_address as ins_address,
						payer_city as ins_city,
						payer_state as ins_state,
						payer_zip as ins_zip,
						payer_phone as ins_phone
		            from ci_payer_list 
		            where payer_name=%s ", $search);

		$insurance = $wpdb->get_row($sql);


		if ($insurance && ($insurance->payer_udc_limit > 0 || $insurance->payer_udc_patient_limit > 0)) {
			$countptsql = '';
			if ($insurance->payer_udc_patient_limit > 0) {
				if ($patient_id == 0 && !empty($_REQUEST['pa_ssn'])) {
					$patient_id = $wpdb->get_var("select pa_ID from ci_patients where pa_ssn= '{$_REQUEST['pa_ssn']}'");
				}
				if ($patient_id > 0) {
					$countptsql .= " , sum(if(ba.patient_id = {$patient_id}, 1, 0 )) as count_patient";
				}
			}

			//check in approved list
			$approved = false;
			if ($patient_id > 0) {
				$year = date_i18n('Y');
				$approved = $wpdb->get_var("select approved_year from ci_patients_payers_udc 
                                            where pa_ID = {$patient_id} and payer_ID = {$insurance->payer_ID}
                                            and approved_year = {$year}");
			}

			if (!$approved) {
				$sql = "select count(mid) as count_all 
                                        {$countptsql}
                                        from {$batch_tbl} ba 
                                        JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
                                        where ins.ins_payer_ID = {$insurance->payer_ID}
                                        and ba.doc >= '{$year_date}' and ba.patient_id > 0 ";
				$count = $wpdb->get_row($sql, ARRAY_A);
				$return['count'] = $count;
				$return['sql'] = $sql;
				if ($insurance->payer_udc_limit > 0 && $insurance->payer_udc_limit < $count['count_all']) {
					$return['limited'] = true;
				} else if ($insurance->payer_udc_patient_limit > 0 && $insurance->payer_udc_patient_limit < $count['count_patient']) {
					$return['limited'] = true;
				}
			} else {
				$return['approved'] = true;
			}
		}
		if ($return['limited']) {
			$return['msg'] = 'Do not collect patient sample. Patient has reached maximum number of urine drug confirmations for the year. Verify successful submission and approval from insurance company by submitting "prior authorization for additional testing" to the insurance company and the lab will over ride this stop at that time.';
		}
		$return['ins'] = $insurance;

		wp_send_json($return);
		break;
}
