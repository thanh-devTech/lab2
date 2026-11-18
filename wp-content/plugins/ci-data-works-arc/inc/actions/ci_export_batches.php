<?php

global $wpdb;
global  $current_role;

if (isset($_REQUEST['view'])) return;


$inb_show_pcode = tr_get_option('ci_pdf_data', 'inb_show_pcode');
$type               = $_REQUEST['type'] ?? '';
$ms_export          = isset($_REQUEST['ms']);
$type_of_specimen   = isset($_REQUEST['t']) ? $_REQUEST['t'] : '';
$method             = isset($_REQUEST['m']) ? $_REQUEST['m'] : '';
$is_ref             = false;

if ($type == 'reflabtransfered') {
	include(trpdd_get_file_path('inbound', 'export_reflabtransfered'));
	exit;
}

$all_status     = tr_pdd_get_batch_statuses(false, false, false, 'all');

if ($type_of_specimen == 'ref_urine') {
	$type_of_specimen = 'urine';
	$is_ref = true;
} else if ($type_of_specimen == 'ref_oral') {
	$type_of_specimen = 'oral';
	$is_ref = true;
} else if ($type_of_specimen == 'ref_blood') {
	$type_of_specimen = 'blood';
	$is_ref = true;
}

if (!isset($lab_id) || $lab_id == 0) {
	$lab_id = CI_Base_Lab::get_current_lab_id();
}

$batch_id       = $_REQUEST['batch_id'];
$filename       = 'Batch_' . $batch_id . '.csv';

$screenings     = array();
$screenings_ids = array();

if (in_array($type, array('current_qc', 'screening_pos'))) {
	$rs = $wpdb->get_results("select * from ci_urine_validity where u_showinreq = 1");
	$screenings_ids = array();
	foreach ($rs as $r) {
		$screenings[$r->u_ID] = $r;
		$screenings_ids[] = $r->u_ID;
	}
	$screenings_ids = implode(',', $screenings_ids);
}

$groups             = array();


$rs = $wpdb->get_results("select tg_ID, tg_name, tg_cpt_code,
                            tg_notestcheckbox, tg_only_group, tg_is_covid
                            from ci_test_groups 
                            where tg_type in( 'pcr', 'blood')
                            ");
foreach ($rs as $r) {
	$groups[$r->tg_ID] = $r;
	if ($r->tg_notestcheckbox || $r->tg_only_group || $r->tg_is_covid) {
		$groups_notestcb[$r->tg_ID] = array();
	}
	if ($r->tg_is_covid) {
		$covid_group_ids[] = $r->tg_ID;
	}
}

$all_tests = array();
$rs = $wpdb->get_results("select * from ci_test_panels where te_type !='tox' ");

foreach ($rs as $r) {
	if ($inb_show_pcode) {
		if (!empty($r->te_loinc_code)) {
			$r->te_name = $r->te_loinc_code;
		}
	}
	$all_tests[$r->te_ID] = $r;
}
$all_groups = array();
$ts = $wpdb->get_results("select * from ci_test_groups");
foreach ($ts as $t) {
	$all_groups[$t->tg_ID] = $t;
}


$where  = '';
if ($lab_id > 0) {
	$where .= " and (ba.lab_id = {$lab_id} or ba.ref_lab_ID={$lab_id})";
}
if (!empty($type_of_specimen)) {
	$where .= " and ba.type_of_specimen = '{$type_of_specimen}'";
}
if ($type == 'etg') {
	$where .= " and (
					FIND_IN_SET('etg',ba.panel_tests_requested)
					or FIND_IN_SET('ets',ba.panel_tests_requested)
				) ";
}

if ($batch_id == 0) {
	$where .= " and batch_status !='archived' ";
}

if ($type == 'allf') {

	$filename   = $batch_id . '.csv';

	$child = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$child[] = "( select assen, doc from {$batch_tbl} ba 
				where ba.batch_id = '{$batch_id}' )";
	}
	$child = implode(' UNION ALL ', $child);
	$sql = "select * from ({$child}) as r";

	$batches = $wpdb->get_results($sql);

	if (isset($filepath) && !empty($filepath)) {
		if (count($batches) == 0) {
			return false;
		}
		$f = fopen($filepath, 'w');
	} else {
		if (count($batches) == 0 && !isset($_GET['debug'])) {
			if (!empty($wpdb->last_error)) {
				Tr_Session_Class::add_error($wpdb->last_error);
			}
			Tr_Session_Class::add_error("No Results Found!");
			wp_redirect($_SERVER['HTTP_REFERER']);
			exit;
		}

		if (!isset($_GET['debug'])) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"$filename\";");
			header("Content-Transfer-Encoding: binary");
		} else {
			var_dump($wpdb->last_error);
		}
		$f = fopen('php://output', 'w');
	}

	$header = array('Accession', 'Date of Collection');
	fputcsv($f, $header);

	foreach ($batches as $row) {
		fputcsv($f, array(
			$row->assen,
			$row->doc,
		));
	}

	@fclose($f);
	if (isset($filepath) && !empty($filepath)) {
		return $filepath;
	}
	return;
} else if ($type == 'blood') {


	$filename   = 'blood_' . $batch_id . '.csv';

	$sql = "select batch_id,
	assen,
	doc,
	patient_name,
	dob,
	panel_tests_requested,
	pa.pa_gender
	from ci_batches_blood ba
	JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
	where ba.batch_id = '{$batch_id}'  
	order by mid asc";

	$batches = $wpdb->get_results($sql);

	if (isset($filepath) && !empty($filepath)) {
		if (count($batches) == 0) {
			return false;
		}
		$f = fopen($filepath, 'w');
	} else {
		if (count($batches) == 0 && !isset($_GET['debug'])) {
			if (!empty($wpdb->last_error)) {
				Tr_Session_Class::add_error($wpdb->last_error);
			}
			Tr_Session_Class::add_error("No Results Found!");
			wp_redirect($_SERVER['HTTP_REFERER']);
			exit;
		}

		if (!isset($_GET['debug'])) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"$filename\";");
			header("Content-Transfer-Encoding: binary");
		} else {
			var_dump($wpdb->last_error);
		}
		$f = fopen('php://output', 'w');
	}

	$header = array(
		'Batch#',
		'Accession',
		'Patient Name',
		'DOB',
		'Gender',
		'Req Tests'
	);
	fputcsv($f, $header);

	foreach ($batches as $row) {
		$tests = array();
		foreach (explode(',', $row->panel_tests_requested) as $t) {
			if (stripos($t, 'GROUP_') !== false) {
				$t = str_ireplace('GROUP_', '', $t);
				if (isset($all_groups[$t])) {
					if ($inb_show_pcode) {
						if (!empty($all_groups[$t]->tg_cpt_code)) {
							$tests[] = $all_groups[$t]->tg_cpt_code;
						}
					} else {
						$tests[] = $all_groups[$t]->tg_name;
					}
				}
			} else {
				if (isset($all_tests[$t])) {
					$tests[] = $all_tests[$t]->te_name;
				}
			}
		}

		$tests = implode(', ', $tests);

		fputcsv($f, array(
			$row->batch_id,
			$row->assen,
			$row->patient_name,
			$row->dob,
			$row->pa_gender,
			$tests,
		));
	}

	@fclose($f);
	if (isset($filepath) && !empty($filepath)) {
		return $filepath;
	}
	return;
} else if ($type == 'blood_mf' || $type == 'blood_fa') {

	$group_name = $type == 'blood_mf' ? 'Mechanical Fragility' : 'Blood Adhesion';
	$filename   = $group_name . '_' . $batch_id . '.csv';

	$group_id = $wpdb->get_var("select tg_ID from ci_test_groups 
					where tg_type = 'blood'
					and tg_name like '%{$group_name}%'");

	$sql = "select assen,doc from ci_batches_blood ba
			where ba.batch_id = '{$batch_id}'  
			and FIND_IN_SET('GROUP_{$group_id}',ba.panel_tests_requested)";

	$batches = $wpdb->get_results($sql);
	if (isset($filepath) && !empty($filepath)) {
		if (count($batches) == 0) {
			return false;
		}
		$f = fopen($filepath, 'w');
	} else {
		if (count($batches) == 0 && !isset($_GET['debug'])) {
			if (!empty($wpdb->last_error)) {
				Tr_Session_Class::add_error($wpdb->last_error);
			}
			Tr_Session_Class::add_error("No Results Found!");
			wp_redirect($_SERVER['HTTP_REFERER']);
			exit;
		}

		if (!isset($_GET['debug'])) {
			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"$filename\";");
			header("Content-Transfer-Encoding: binary");
		} else {
			var_dump($wpdb->last_error);
		}
		$f = fopen('php://output', 'w');
	}

	$header = array('Accession', 'Date of Collection');
	fputcsv($f, $header);

	foreach ($batches as $row) {
		fputcsv($f, array(
			$row->assen,
			$row->doc,
		));
	}

	@fclose($f);
	if (isset($filepath) && !empty($filepath)) {
		return $filepath;
	}
	return;
} else if ($type == 'screening_pos') {

	$filename = "QC_POS_" . $filename;

	$sql = "select  ba.mid,
					ba.position,
					ba.assen,
					ba.patient_name,
					re.id,
					pa.pa_gender
            from ci_batches ba
			JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
            left join ci_reports re ON re.patient_id = ba.assen
            where ba.batch_id = '{$batch_id}' {$where}";
	$sql .= " group by ba.mid order by ba.position asc, ba.mid desc ";
} else if ($type == 'current') {
	$sql = "select ba.*,
        ref_lab.lab_dba_init as lab_ref,
        u.user_login,
        cp.cus_injections,
        ins.ins_address, ins.ins_member_id, ins.ins_member_id, ins.ins_group_id, ins.ins_city, ins.ins_state, ins.ins_zip,
		pa.pa_gender
        from ci_batches ba
		JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
        Left JOIN {$wpdb->users} u ON u.ID = ba.author_ID and ba.batch_short=1
        left JOIN ci_labs ref_lab ON ref_lab.lab_ID = ba.ref_lab_ID
        left JOIN ci_custom_providers cp ON cp.doc_ID = ba.doc_ID and cp.cli_ID = ba.cli_ID
        left JOIN ci_insurances ins ON ins.ins_ID = ba.insurance1
        ";

	if ($method == 'alcohol') {
		$sql .= " where ba.panel_tests_requested like '%ethanol*%' and ba.batch_id ='{$batch_id}' ";
	} else if ($method == 'export_meth') {
		$sql .= " JOIN ci_reports re ON ba.assen = re.patient_id 
                     JOIN ci_results rs ON rs.report_id = re.id and drug='METHAMPHETAMINE' and `pos-neg`='POS' ";
		$sql .= " where  ba.type_of_specimen not like 'oral%' and ba.batch_id IN (" . implode(',', array_map('intval', $_REQUEST['idm'])) . ")";

		$batch_id = 'methaphetamine-dl';
	} else {
		$sql .= " where ba.batch_id ='{$batch_id}'  {$where}";
	}
	$sql .= " group by ba.mid order by ba.position asc, ba.mid desc ";
} else if ($type == 'all') {

	if (tr_get_option('ci_pdf_data', 'inb_exsamebill')) {
		$lists = $wpdb->get_results("select * from ci_dagnostic_codes ");
		$all_diagnostic_codes = array();
		foreach ($lists as $list) {
			$all_diagnostic_codes[$list->key] = $list->desc;
		}
	}

	$child = array();
	foreach (tr_pdd_get_batch_types() as $btype) {
		$batch_tbl = tr_pdd_get_table_by_btype($btype);
		$report_tbl = tr_pdd_get_table_report_by_btype($btype);

		$fields_more = '';
		$joins_more = '';
		if (tr_get_option('ci_pdf_data', 'inb_exsamebill')) {

			$fields_more .= ',re.estatus,
						pa.pa_first,
                        pa.pa_last,
                        pa.pa_address,
                        pa.pa_city,
                        pa.pa_state,
                        pa.pa_zip,
                        pa.pa_phone,
                        pa.pa_email,
                        pa.pa_ssn,
                        pa.pa_mrn,
                        pa.pa_code,
                        doc.doc_first,
                        doc.doc_last,
                        doc.doc_npi,
                        doc.doc_phone,
                        re.id,
                        re.approve_date,
                        
                        ins.ins_first,
                        ins.ins_last,
                        ins.ins_dob,
                        ins.ins_company,
                        ins.ins_relation,
                        ins.ins_member_id,
                        ins.ins_group_id,
                        ins.ins_address,
                        ins.ins_city,
                        ins.ins_state,
                        ins.ins_zip,
                        ins.ins_phone,
                        
                        ins2.ins_first as ins_first2,
                        ins2.ins_last as in_last2,
                        ins2.ins_dob as in_dob2,
                        ins2.ins_company as ins_company2,
                        ins2.ins_relation as ins_relation2,
                        ins2.ins_member_id as ins_member_id2,
                        ins2.ins_group_id as ins_group_id2,
                        ins2.ins_address as ins_address2,
                        ins2.ins_city as ins_city2,
                        ins2.ins_state as ins_state2,
                        ins2.ins_zip as ins_zip2,
                        ins2.ins_phone as ins_phone2,
						cli.cli_city,
						ba.dagnostic_codes1';

			$joins_more .= " left join {$report_tbl} re ON re.patient_id = ba.assen 
					left join ci_insurances ins ON ins.ins_ID = ba.insurance1
	                left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
					left join ci_doc doc ON doc.doc_ID = ba.doc_ID
					left JOIN ci_clinic cli ON cli.cID = ba.cli_ID
					";
		}

		$child[] = "( 
				select ba.mid,ba.position,ba.batch_id,ba.batch_type,ba.batch_status,ba.assen,ba.oldreq,ba.patient_name,ba.current_medication,
                ba.requesting_practice,ba.requesting_physician,ba.physician_phone,ba.insurance,ba.doc,ba.spec_received,ba.type_of_specimen,
                ba.current_medication_other,ba.subscriber_insurance,ba.panel_tests_requested,ba.notes,ba.qc, ba.culture,ba.billing_notes,
                ba.ref_lab_ID,ba.lab_id,ba.dob,ba.lab,
				ref_lab.lab_dba_init as lab_ref,
				pa.pa_gender,
				pa.pa_dob,
				'{$btype}' as btype
				{$fields_more}
				from {$batch_tbl} ba 
				JOIN ci_patients pa ON pa.pa_ID = ba.patient_id
				left JOIN ci_labs ref_lab ON ref_lab.lab_ID = ba.ref_lab_ID
				{$joins_more}
				where ba.batch_id = '{$batch_id}' {$where} 
				group by ba.mid order by ba.position asc, ba.mid desc 
				)";
	}
	$child = implode(' UNION ALL ', $child);
	$sql = "select * from ({$child}) as r";
} else {
	$sql = "select ba.*,
            ref_lab.lab_dba_init as lab_ref
            from ci_batches ba
            left JOIN ci_labs ref_lab ON ref_lab.lab_ID = ba.ref_lab_ID
            where ba.batch_id = '{$batch_id}'  {$where} ";
}



$batches = array();
if ($type_of_specimen != 'blood') {
	$batches = $wpdb->get_results($sql);
}


if ($type != 'all' && !empty($batch_id) && !$ms_export && (empty($type_of_specimen) || $type_of_specimen == 'blood')) {
	$sql2 = str_replace('ci_batches', 'ci_batches_blood', $sql);
	$bloods = $wpdb->get_results($sql2);
	if (count($bloods) > 0) {
		if (count($batches) > 0) {
			$header_title = new stdClass();
			$header_title->header_title = 'Blood';
			$batches[] = $header_title;
			$batches = array_merge($batches, $bloods);
		} else {
			$batches = $bloods;
		}
	}
}

if (isset($_GET['debug'])) {
	echo count($batches);
	echo '<br>';
	echo $sql;
}
if (isset($filepath) && !empty($filepath)) {
	if (count($batches) == 0) {
		return false;
	}
	$f = fopen($filepath, 'w');
} else {
	if (count($batches) == 0 && !isset($_GET['debug'])) {
		if (!empty($wpdb->last_error)) {
			Tr_Session_Class::add_error($wpdb->last_error);
		}
		Tr_Session_Class::add_error("No Results Found!");
		wp_redirect($_SERVER['HTTP_REFERER']);
		exit;
	}

	if (!isset($_GET['debug'])) {
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"$filename\";");
		header("Content-Transfer-Encoding: binary");
	} else {
		var_dump($wpdb->last_error);
	}

	$f = fopen('php://output', 'w');
}

$i = 1;

if ($type == 'all' && tr_get_option('ci_pdf_data', 'inb_exsamebill')) {
	$keys = array(
		'Position',
		'Resulted',
		'Accession',
		'Ref Accession Number',
		'Clinic',
		'Patient First Name',
		'Patient Last Name',
		'Patient Address',
		'Patient City',
		'Patient State',
		'Patient Zip',
		'Patient Gender',
		'DOB',
		'Patient Phone',
		'Patient ID',
		'Patient Email',
		'Patient SSN',
		'Parent/Spouse Name',
		'Parent/Spouse DOB',
		'Clinic City',
		'Dr First',
		'Dr Last',
		'Doctor Phone',
		'NPI',
		'Collection',
		'Received',
		'Result Date',
		'Lab',
		'Referenced',
		'Req Panel Tests',
		'Insurance',
		'Insurance ID',
		'Group Number',
		'Second Insurance',
		'Second Insurance ID',
		'MP index',
		'Insurance Address',
		'Insurance City',
		'Insurance State',
		'Insurance Zip',
		'Insurance Phone',

		'Secondary Insurance',
		'Secondary ID',
		'Secondary Group Number',
		'Secondary Address',
		'Secondary City',
		'Secondary State',
		'Secondary Zip',
		'Secondary Phone',
		'Type of Specimen',
		'Diagnosis Codes',
		'Diagnosis Codes Description',
		'Notes',
		'Status',
		'Priority'
	);
} else if ($type == 'screening_pos') {
	$keys = array(
		'Position',
		'Accession',
		'Patient Name',
		'Positive Screening Tests',
		'LCMS Positive Tests',
		'Below Cutoff'
	);
} else if ($is_ref) {
	$keys = array(
		'Position',
		'Accession',
		'DOB',
	);
} else if ($ms_export) {
	$keys = array(
		'Position',
		'Lab',
		'Patient Name',
		'Gender',
		'Accession',
		'Clinic',
		'Doctor',
		'DOB',
		'Collection date',
		'Received Date',
		'Position',
		'Reference Lab',
		'Lab',
		'Medication',
	);
} else if ($current_role == 'lab_tech') {
	$keys = array(
		'Position',
		'Lab',
		'Referenced',
		'Patient Name',
		'Gender',
		'DOB',
		'ID#',
		'Accession',
		'Medication',
		'Clinic',
		'Doctor Name',
		'DOC',
		'Received',
		'Insurance',
		'Notes',
		'Req Panel Tests',
	);

	if ($type == 'current_qc') {
		$keys[] = 'Drug Screen Markers';
		$keys[] = 'Injections';
		$keys[] = 'Username';
	} else if ($type == 'current') {
		$keys[] = 'Injections';
		$keys[] = 'Username';
	}
} else {
	$keys = array(
		'Position',
		'Patient Name',
		'Gender',
		'Accession',
		'Medication',
		'DOB',
		'Clinic',
		'Doctor Name',
		'Doctor Phone',
		'Collection Date',
		'Received',
		'Lab',
		'Referenced',
		'Req Panel Tests',
	);
	if ($type == 'current_qc') {
		$keys[] = 'Drug Screen Markers';
		$keys[] = 'Insurance';
		$keys[] = 'Insurance Address';
		$keys[] = 'Insurance City';
		$keys[] = 'Insurance State';
		$keys[] = 'Insurance Zip';
		$keys[] = 'Insurance Member ID';
		$keys[] = 'Insurance Group ID';
		$keys[] = 'Diagnostic codes';
		$keys[] = 'Username';
	} else if ($type == 'current') {
		$keys[] = 'Insurance';
		$keys[] = 'Insurance Address';
		$keys[] = 'Insurance City';
		$keys[] = 'Insurance State';
		$keys[] = 'Insurance Zip';
		$keys[] = 'Insurance Member ID';
		$keys[] = 'Insurance Group ID';
		$keys[] = 'Diagnostic codes';
		$keys[] = 'Username';
	} else {
		$keys[] = 'Insurance';
	}
	$keys[] = 'Notes';
}
fputcsv($f, $keys);

foreach ($batches as $row) {

	$priority_name = '';

	if ($type == 'all' && tr_get_option('ci_pdf_data', 'inb_exsamebill')) {

		$parent_name    = '';
		$parent_dob     = '';

		if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
			$parent_name = $row->ins_last . ', ' . $row->ins_first;
			$parent_dob = $row->ins_dob;
		}
		$diagnostic_desc = isset($all_diagnostic_codes[$row->dagnostic_codes1]) ? $all_diagnostic_codes[$row->dagnostic_codes1] : '';
		$diagnostic_desc = str_ireplace($row->dagnostic_codes1, '', $diagnostic_desc);

		//get panel
		if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
			$panel = $row->panel_tests_requested;
		} else if (strpos($row->panel_tests_requested, '+') === false) {
			$panel = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
		} else {
			$panel = $row->panel_tests_requested;
		}

		$testes_resulted = explode('|', strtoupper($row->testes_resulted));
		if (in_array($row->btype, array('pcr', 'blood'))) {
			$testids = explode(',', $row->panel_tests_requested);
			$panel = array();
			$groupids = array();
			foreach ($testids as $testid) {
				$testid = trim($testid);
				$testid = str_replace('+', '', $testid);
				if (stripos($testid, 'group_') !== false) {
					$group_id = str_ireplace('group_', '', $testid);
					if (!in_array($group_id, $groupids) && isset($groups[$group_id])) {
						$panel[] = $groups[$group_id]->tg_name . '*';
						$groupids[] = $group_id;
						if (stripos($groups[$group_id]->tg_name, 'cov-') !== false) {
							$has_covid = true;
						}
					}
				} else if (isset($all_tests[$testid])) {
					if (
						in_array(strtoupper($all_tests[$testid]->te_name), $testes_resulted)
						|| in_array(strtoupper($all_tests[$testid]->te_abrev), $testes_resulted)
						|| in_array(strtoupper($all_tests[$testid]->te_abrev2), $testes_resulted)
					) {
						$group_id = $all_tests[$testid]->te_group_id;
						if ($group_id > 0 && !in_array($group_id, $groupids) && isset($groups[$group_id])) {
							$panel[] = $groups[$group_id]->tg_name . '*';
							$groupids[] = $group_id;
							if (stripos($groups[$group_id]->tg_name, 'cov-') !== false) {
								$has_covid = true;
							}
						}
						$panel[] = $all_tests[$testid]->te_name;
						if (stripos($all_tests[$testid]->te_name, 'cov-') !== false) {
							$has_covid = true;
						}
					}
				}
			}
			$panel = implode(', ', $panel);
		} else if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
			$panel = $row->panel_tests_requested;
		} else if (strpos($row->panel_tests_requested, '+') === false) {
			$panel = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
		} else {
			$panel = $row->panel_tests_requested;
		}
		//$panel = str_replace(',', "\n", $panel);
		$panel = trim($panel);

		$row_data = array(
			$row->position,
			$row->estatus == 'C' ? 'Yes' : 'No',
			$row->assen,
			$row->oldreq,
			$row->requesting_practice,
			$row->pa_first,
			$row->pa_last,
			$row->pa_address,
			$row->pa_city,
			$row->pa_state,
			$row->pa_zip,
			$row->pa_gender,
			$row->pa_dob,
			$row->pa_phone,
			$row->pa_code,
			$row->pa_email,
			$row->pa_ssn,
			$parent_name,
			$parent_dob,
			$row->cli_city,
			$row->doc_first,
			$row->doc_last,
			$row->doc_phone,
			$row->doc_npi,
			$row->doc,
			$row->spec_received,
			$row->approve_date,
			$row->lab,
			$row->lab_ref,
			$panel,

			$row->insurance,
			$row->ins_member_id,
			$row->ins_group_id,
			$row->ins_company2,
			$row->ins_member_id2,
			$row->payer_fin_mnemonic,
			$row->ins_address,
			$row->ins_city,
			$row->ins_state,
			$row->ins_zip,
			$row->ins_phone,

			$row->insurance2,
			$row->ins_member_id2,
			$row->ins_group_id2,
			$row->ins_address2,
			$row->ins_city2,
			$row->ins_state2,
			$row->ins_zip2,
			$row->ins_phone2,

			$row->type_of_specimen,
			$row->dagnostic_codes1,
			$diagnostic_desc,
			$row->notes,
			$all_status[$row->batch_status],
			$priority_name,
		);
	} else if ($type == 'screening_pos') {
		$tests = $wpdb->get_results("select * from ci_result_test_speciments where mid = {$row->mid} and checked = 1
						and testid in ({$screenings_ids})");


		$screening_tests_pos = array();
		$lcms_pos_tests = array();
		$low_cutoff = array();
		foreach ($tests as $t) {
			$test = $screenings[$t->testid];

			if (!empty($t->conc) && ($t->conc < $test->u_low || ($t->conc > $test->u_high && $test->u_high > 0))) {
				$screening_tests_pos[] = $test->u_name;
			}
		}

		if ($row->id > 0) {
			$all_rs = $wpdb->get_results("select drug,conc,conc_number,`pos-neg`,cutoff 
							from ci_results
							where report_id = {$row->id} and has_order = 1");
			foreach ($all_rs as $r) {
				if ($r->{'pos-neg'} == 'POS') {
					$lcms_pos_tests[] = $r->drug;
				}

				if (!empty($r->conc_number)) {
					$r->conc = $r->conc_number;
				}

				if ($r->conc != 'N/A' && !empty($r->conc) && $r->conc < $r->cutoff) {
					$low_cutoff[] = $r->drug;
				}
			}
			if (isset($_REQUEST['debug'])) {
				var_dump($all_rs);
			}
		}



		if (empty($screening_tests_pos) && empty($lcms_pos_tests) && empty($low_cutoff)) {

			if (isset($_GET['debug'])) {
				var_dump($lcms_pos_tests);
				var_dump($wpdb->last_error);
				echo "\n<br>";
			} else {
				continue;
			}
		}
		$screening_tests_pos 	= implode(', ', $screening_tests_pos);
		$lcms_pos_tests 		= implode(', ', $lcms_pos_tests);
		$low_cutoff 			= implode(', ', $low_cutoff);

		$row_data = array(
			$row->position,
			$row->assen,
			$row->patient_name,
			$screening_tests_pos,
			$lcms_pos_tests,
			$low_cutoff
		);
	} else {

		if ($type == 'current_qc') {
			$tests = $wpdb->get_results("select * from ci_result_test_speciments where mid = {$row->mid} and checked = 1
						and testid in ({$screenings_ids})");
			$row->screenings = array();

			foreach ($tests as $t) {
				$row->screenings[] = $screenings[$t->testid]->u_name;
			}
			$row->screenings = implode(', ', $row->screenings);
		}
		//for mat patients name to last,first
		if (strpos($row->patient_name, ',') === false) {
			list($fname, $lname) = explode(' ', $row->patient_name, 2);
			$lname = trim($lname);
			$fname = trim($fname);
		} else {
			list($lname, $fname) = explode(',', $row->patient_name, 2);
			$lname = trim($lname);
			$fname = trim($fname);
		}

		if ($type == 'current') {
			if (empty($row->spec_received)) {
				$row->spec_received = $row->doc;
			}

			$row->patient_name = ucfirst(strtolower($lname)) . ', ' . ucfirst(strtolower($fname));
		} else {
			$row->patient_name = ucfirst(strtolower($lname)) . ',' . ucfirst(strtolower($fname));
		}

		//format medication
		$row->current_medication = str_replace('|', ',', $row->current_medication);
		$medications             = explode(',', trim($row->current_medication));
		if (!empty($row->current_medication_other)) {
			$row->current_medication_other = str_replace('|', ',', $row->current_medication_other);
			$others                        = explode(',', trim($row->current_medication_other));
			$medications                   = array_merge($medications, $others);
		}
		foreach ($medications as $j => &$med) {
			$med = trim($med);
			$med = ucfirst(strtolower($med));
			if (empty($med)) {
				unset($medications[$j]);
			}
		}
		$medications = implode(',', $medications);
		$medications = str_replace(',', ', ', $medications);

		//check insurance_name
		if (empty($row->insurance)) {
			$row->insurance = $row->subscriber_insurance;
		}

		//fix doctor
		$row->requesting_physician = trim($row->requesting_physician);
		if ($type == 'current') {
			$row->requesting_physician = str_replace(array("'", "\\'"), array(
				'',
				''
			), $row->requesting_physician);
		} else {
			$row->requesting_physician = str_replace(array("'", "\\'", ', '), array(
				'',
				'',
				','
			), $row->requesting_physician);
		}


		//fix clinic
		$row->requesting_practice = trim($row->requesting_practice);
		//$row->assen = $row->assen;

		//get panel
		$panels = '';
		if ($row->btype == 'tox') {
			if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
				$testnames = $row->panel_tests_requested;
			} else if (strpos($row->panel_tests_requested, '+') === false && !empty($row->custom_profile)) {
				$testnames = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
			} else {
				$testnames = $row->panel_tests_requested;
			}
		} else {
			$testids = explode(',',  $row->panel_tests_requested);
			$testnames_arr = array();
			$panels_arr = array();
			foreach ($testids as $testid) {
				$testid = trim($testid);
				if (stripos($testid, 'group') !== false) {
					$testid = str_ireplace('GROUP_', '', $testid);
					if ($inb_show_pcode && isset($all_groups[$testid])) {
						if (!empty($all_groups[$testid]->tg_cpt_code)) {
							$panels_arr[] = $all_groups[$testid]->tg_cpt_code;
						}
					} else if (isset($all_groups[$testid])) {
						$panels_arr[] = $all_groups[$testid]->tg_name;
					}
				} else if (isset($all_tests[$testid])) {
					$panels_arr[] = $all_tests[$testid]->te_name;
				}
			}
			$testnames = implode(', ', $testnames_arr);
			$panels = implode(', ', $panels_arr);
		}


		$no = (in_array($type, array('current', 'current_qc', 'etg'))) ? $row->position : $i;
		$i++;

		if (isset($row->header_title)) {
			fputcsv($f, array('', '')); //blank line
			$row_data = array(
				$row->header_title
			);
		} else if ($is_ref) {
			$row_data = array(
				$row->position,
				$row->assen,
				$row->dob,
			);
		} else if ($ms_export) {
			$row_data = array(
				$row->position,
				$row->lab,
				$row->patient_name,
				$row->pa_gender,
				$row->assen,
				$row->requesting_practice,
				$row->requesting_physician,
				$row->dob,
				$row->doc,
				$row->spec_received,
				$row->position,
				$row->lab_ref,
				$row->lab,
				$medications,
			);
		} else if ($current_role == 'lab_tech') {

			$row_data = array(
				$no,
				$row->lab,
				$row->lab_ref,
				$row->patient_name,
				$row->pa_gender,
				$row->dob,
				$row->assen,
				$row->assen,
				$medications,
				$row->requesting_practice,
				$row->requesting_physician,
				$row->doc,
				$row->spec_received,
				$row->insurance,
				$row->notes,
				$panels,
			);
			if ($type == 'current_qc') {
				$row_data[] = $row->screenings;
				$row_data[] = $row->cus_injections;
				$row_data[] = $row->user_login;
			}
			if ($type == 'current') {
				$row_data[] = $row->cus_injections;
				$row_data[] = $row->user_login;
			}
		} else {
			$row_data = array(
				$no,
				$row->patient_name,
				$row->pa_gender,
				$row->assen,
				$medications,
				$row->dob,
				$row->requesting_practice,
				$row->requesting_physician,
				$row->physician_phone,
				$row->doc,
				$row->spec_received,
				$row->lab,
				$row->lab_ref,
				$panels,
			);

			if ($type == 'current_qc') {
				$row_data[] = $row->screenings;
				$row_data[] = $row->insurance;
				$row_data[] = $row->ins_address;
				$row_data[] = $row->ins_city;
				$row_data[] = $row->ins_state;
				$row_data[] = $row->ins_zip;
				$row_data[] = $row->ins_member_id;
				$row_data[] = $row->ins_group_id;
				$row_data[] = $row->dagnostic_codes1;
				$row_data[] = $row->user_login;
			} else if ($type == 'current') {
				$row_data[] = $row->insurance;
				$row_data[] = $row->ins_address;
				$row_data[] = $row->ins_city;
				$row_data[] = $row->ins_state;
				$row_data[] = $row->ins_zip;
				$row_data[] = $row->ins_member_id;
				$row_data[] = $row->ins_group_id;
				$row_data[] = $row->dagnostic_codes1;
				$row_data[] = $row->user_login;
			} else {
				$row_data[] = $row->insurance;
			}
			$row_data[] = $row->notes;
		}
	}


	fputcsv($f, $row_data);
}
$data_log = array(
	'type' => $type,
	'file_name' => $filename,
	'total_records' => count($batches),
	'from_date' => $batch_id,
	'to_date' => $batch_id,
);
trpdd_log_user(get_current_user_id(), 'download_batches', $data_log, '', '', '', '', 'Batches_' . date_i18n('mdY'));

@fclose($f);
if (isset($filepath) && !empty($filepath)) {
	return $filepath;
}
