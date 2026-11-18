<?php
global $wpdb, $current_role, $show_menus_options, $ci_active_menu;
$schedule_report 	= new CI_Report_Schedule('insurance_verification', 'Insurance Verification');
$races = array(
	'' => '',
	'1002-5' => 'American Indian or Alaska  Native',
	'2028-9' => 'Asian',
	'2054-5' => 'Black / African American',
	'2076-8' => 'Native Hawaiian or Other  Pacific Islander',
	'2106-3' => 'White',
	'2131-1' => 'Other'
);
$Ethnicitis = array(
	'' => '',
	'H' => 'Hispanic or Latino',
	'N' => 'Not Hispanic or Latino',
	'U' => 'Unknown'
);
$settings = tr_billing_get_settings();
$all_status = tr_pdd_get_batch_statuses(false, false, false, 'all');
$user_id = get_current_user_id();

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$fromdate = isset($_REQUEST['fromdate']) ? $_REQUEST['fromdate'] : date_i18n('Y-m-d');
$todate = isset($_REQUEST['todate']) ? $_REQUEST['todate'] : date_i18n('Y-m-d');
$paytype = isset($_REQUEST['paytype']) ? $_REQUEST['paytype'] : 'insu';
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : '';
$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$is_billing = isset($_REQUEST['billing']) ? $_REQUEST['billing'] : 0;
$is_summary = isset($_REQUEST['summary']) ? $_REQUEST['summary'] : 0;

$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';
$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$cid = 0;


$settings['redtest_ins_report'] = isset($settings['redtest_ins_report']) && $settings['redtest_ins_report'];
$settings['cstatus_ins_report'] = isset($settings['cstatus_ins_report']) && $settings['cstatus_ins_report'];
$settings['enable_us_date_format'] = isset($settings['enable_us_date_format']) && $settings['enable_us_date_format'];

$enable_ins_report_rsd = true;
$enable_result_insurance = isset($settings['result_insurance']) ? $settings['result_insurance'] : false;
$enable_insurance_rp_mpi = isset($settings['insurance_rp_mpi']) ? $settings['insurance_rp_mpi'] : false;
$insurance_Salesperson = isset($settings['insurance_rp_svd']) ? $settings['insurance_rp_svd'] : false;


$show_rapid_collect = defined('TRPDD_INS_COLLECT_MILES') && TRPDD_INS_COLLECT_MILES;

$enable_stat = tr_get_option('ci_pdf_data', 'enable_stat');


$columncds = $wpdb->get_col("SHOW COLUMNS FROM ci_clinic_doc");
$need_sp = in_array('doc_need_sp_id', $columncds);
$all_sp_doctors = array();
if ($need_sp) {
	$all_sp_doctors_rs = $wpdb->get_results("select doc.doc_ID,
	doc.doc_first, 
	doc.doc_last, 
	doc.doc_npi
	from ci_doc doc 
	JOIN ci_clinic_doc cd ON doc.doc_ID = cd.doc_need_sp_id
	where cd.doc_need_sp_id > 0
	group by doc.doc_ID");

	foreach ($all_sp_doctors_rs as $r) {
		$all_sp_doctors[$r->doc_ID] = $r;
	}
}



if (isset($_REQUEST['trajax'])) {
	if (stripos($_REQUEST['trajax'], 'Facility Bill') !== false) {
		$act = 'facility_bill_export';
	} else if (stripos($_REQUEST['trajax'], 'export') !== false) {
		$act = 'export';
	}
}

$covid_group_ids = array();
$groups = array();
$groups_notestcb = array();
$all_clinics = array();

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

$rs = $wpdb->get_results("select * from ci_test_panels 
                            where te_type in ( 'pcr', 'blood') ");
$all_tests = array();
foreach ($rs as $r) {
	$all_tests[$r->te_ID] = $r;

	if ($r->te_nocheckbox && !isset($groups_notestcb[$r->te_group_id])) {
		$groups_notestcb[$r->te_group_id] = array();
	}
	if (isset($groups_notestcb[$r->te_group_id])) {
		$groups_notestcb[$r->te_group_id][] = $r->te_ID;
	}
}

if (isset($_GET['debug2'])) {
	echo '$groups_notestcb:';
	var_dump($groups_notestcb);
}

if ($insurance_Salesperson) {
	$rs = $wpdb->get_results("select cID,
	cli_clinic,
	rep.rep_last,
	rep.rep_first,
	dis.dis_name as distributor,
	mg.gr_name as marketing_group
	from ci_clinic cli
	left JOIN ci_lab_reps rep ON cli.`cli_rep-ID` = rep.rep_ID
	left JOIN ci_distributors dis ON cli.cli_distributor_ID = dis.dis_ID
	left JOIN ci_marketing_groups mg ON cli.cli_marketing_group_ID = mg.gr_ID
	");
	foreach ($rs as $r) {
		$r->salesperson = $r->rep_first . ' ' . $r->rep_last;
		$all_clinics[$r->cID] = $r;
	}
}


$all_dagnostic_codes = array();
foreach (trbilling_req_types() as $bt) {
	if ($bt == 'study')
		continue;
	if (!empty($btype_selected) && $bt != $btype_selected) {
		continue;
	}

	$all_dagnostic_codes = array_merge($all_dagnostic_codes, tr_pdd_get_dagnostic_codes($bt));
}

$orderby = ' order by re.created_date desc, ba.mid desc';
$where_cli = "";
$where = " and ba.doc >= '{$fromdate}' and ba.doc <= '{$todate} 23:59:59' ";

if ($current_role == 'BillbyClinic') {
	$clinics = get_user_meta($user_id, '_bill_cli_IDs', true);
	if (!is_array($clinics)) {
		$clinics = array(0);
	}
	$clinics = implode(',', $clinics);
	$where_cli .= " and cli.cID in ({$clinics}) ";
} else if ($current_role == 'BillbyRefLab') {
	$reflabid = get_user_meta($user_id, '_ref_lab_id', true);
	if (empty($reflabid)) {
		$reflabid = -1;
	}
	$where .= " and ba.ref_lab_ID in ({$reflabid}) ";
} else if ($current_role == 'office_manager') {

	$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
	if ($current_clinic > 0) {
		$where_cli .= " and cID = {$current_clinic} ";
	} else {

		if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true) || $cid > 0) {
			$cid = get_user_meta($user_id, '_clinic_id', true);
			$cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
			$cli_ids = implode(',', $cli_ids);


			$where_cli .= " and cID in ( {$cli_ids} )";
		}
	}
} else if ($current_role == 'Billing' && !$settings['disable_billbyclinic']) {
	$excludecids = get_option('ci_bill_cli_IDs', array());

	if (is_array($excludecids) && count($excludecids) > 0) {
		$excludecids = implode(',', $excludecids);
		$where_cli .= " and cli.cID not in ({$excludecids}) ";
	}
}

$dateby = 'drs';

$dateby = isset($_REQUEST['dateby']) ? $_REQUEST['dateby'] : $dateby;
$orderby = ' order by ba.doc desc, ba.mid desc';



$where .= " and (ba.batch_status = '' or ba.batch_status is null)";


if (defined('TRPDD_UPSAVED') && TRPDD_UPSAVED) {
	$where .= " and ba.is_saved = 0 ";
}


if (isset($settings['exclude_ref_lab']) && !empty($settings['exclude_ref_lab'])) {
	$where .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id = '{$current_lab_id}' ) ";
} else {
	if (isset($settings['include_ref_labs']) && !empty($settings['include_ref_labs'])) {
		$where .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id in ( {$settings['include_ref_labs']} ) ) ";
	}

	if (isset($settings['exclude_ref_labs']) && !empty($settings['exclude_ref_labs']) && empty($_REQUEST['lab_reflab'])) {
		$where_child = array();
		foreach (explode(',', $settings['exclude_ref_labs']) as $rlkey) {
			$where_ex = array();
			list($lab_id, $ref_lab_id) = explode('_', $rlkey);
			if ($lab_id > 0) {
				$where_ex[] = " ba.lab_ID = {$lab_id} ";
			}

			if ($ref_lab_id > 0) {
				$where_ex[] = " ba.ref_lab_ID = {$ref_lab_id} ";
			} else {
				$where_ex[] = " ba.ref_lab_ID = 0 ";
			}

			if (count($where_ex) > 0) {
				$where_child[] = ' ( ' . implode(' and ', $where_ex) . ' ) ';
			}
		}
		if (count($where_child) > 0) {
			$where .= " and NOT (" . implode(' or ', $where_child) . ") ";
		}
	}
}


$user_ref_lab_id = get_user_meta($user_id, '_ref_lab_id', true);
if ($user_ref_lab_id > 0) {
	$where .= " and ba.ref_lab_id = {$user_ref_lab_id}  ";
}

$ormorepaytype = '';

if (!empty($paytype)) {

	if (isset($settings['paytypebynoinscash']) && $settings['paytypebynoinscash']) {
		if ($paytype == 'cash') {
			//	$ormorepaytype .= ' or ba.paid > 0 ';
		} else if ($paytype == 'insu') {
			//	$ormorepaytype .= ' or ba.paid = 0 ';
		}
	}
	if ($paytype !== 'no') {
		//	$where_cli .= " and (cli.cli_pay_type = '{$paytype}'  {$ormorepaytype} ) ";
	} else {
		//$where_cli .= " and cli.cli_pay_type = 'insu' ";
	}
	/*
	if ($paytype == 'no' || $cid === 'no') {
		$where .= " and (ba.insurance = 'No Insurance - Cash' or ba.insurance='') ";
	} else if ($paytype == 'insu') {
		$where .= " and ba.insurance != 'No Insurance - Cash' and ba.insurance!='' ";
	}*/
}


if ($cli_ID > 0) {
	$where_cli .= " and cli.cID = {$cli_ID} ";
}




if (isset($_REQUEST['deleted_clinic'])) {
	$where .= " and ba.cli_ID not in (select cID from ci_clinic cli ) ";
} else if (!empty($where_cli)) {
	$where .= " and ba.cli_ID in (select cID from ci_clinic cli where 1 {$where_cli}) ";
} else if (!empty($ormorepaytype)) {
	$where .= " and 1 {$ormorepaytype} ";
}


if (!empty($_REQUEST['lab_reflab'])) {
	list($lab_id, $ref_lab_id) = explode('_', $_REQUEST['lab_reflab']);
	if ($lab_id > 0) {
		$where .= " and ba.lab_ID = {$lab_id} ";
	}

	if ($ref_lab_id > 0) {
		$where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
	} else {
		$where .= " and ba.ref_lab_ID = 0 ";
	}
}

$sql_child = array();
$more_fields = '';
$joins = '';
$has_more_type = false;
$rpp_gi = false;

if ($enable_result_insurance) {
	$more_fields .= " re.covid_pos, ";
}
if ($show_rapid_collect) {
	$more_fields .= " ba.collect_by_rapid, ba.miles_rapid, ";
}
if (isset($settings['insurance_sresg']) && $settings['insurance_sresg']) {
	$more_fields .= " pa.pa_race, pa.pa_ethnicity, pa.pa_sexual_oriention,pa.pa_gender_identity,";
}


if ($need_sp) {
	$joins .= " left join ci_clinic_doc cd ON cd.cli_ID = ba.cli_ID and cd.doc_ID = ba.doc_ID ";
	$more_fields .= 'cd.doc_need_sp_id,';
}

if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
	$joins .= " left join ci_collector cole ON cole.col_ID = ba.col_ID ";
	$more_fields .= " CONCAT(cole.col_first,' ',cole.col_last) AS collector, ";
}

if ($settings['cstatus_ins_report']) {
	$more_fields .= ' bb.bill_status, ';
	$where_join = " bb.assen = ba.assen ";
	$joins .= " left join ci_batch_billings bb ON {$where_join} ";
}

$schedule_report->ajax();

$total = 0;
foreach (tr_pdd_get_batch_types() as $t) {

	if ($t == 'study') continue;
	$batch_tbl = tr_pdd_get_table_by_btype($t);
	$has_more_type = true;

	$more_fields_btype = $more_fields;

	if ($enable_stat) {
		if ($t == 'blood') {
			$more_fields_btype .= ' ba.stat, ';
		} else {
			$more_fields_btype .= " '' as stat, ";
		}
	}

	if (!in_array($act, array('export', 'facility_bill_export'))) {
		$total += $wpdb->get_var("select count(*) from {$batch_tbl} ba 
		                    where 1 {$where} ");
	}


	$sql_child[] = "(select 
						ba.mid,ba.position,ba.assen,ba.oldreq,ba.batch_id, ba.spec_received,ba.doc,
                        ba.current_medication_other,ba.current_medication,ba.insurance,ba.subscriber_insurance, ba.requesting_physician,
                        ba.panel_tests_requested,ba.custom_profile,ba.requesting_practice,ba.type_of_specimen,ba.batch_status,ba.notes,
                        ba.dagnostic_codes1,ba.priority_hours,ba.cli_ID, ba.internal_notes,
                        cli.cli_city,
                        lab.lab_dba_init as lab,
                        lr.lab_dba_init as lab_ref,
                        pa.pa_first,
                        pa.pa_last,
                        pa.pa_gender,
                        pa.pa_address,
                        pa.pa_city,
                        pa.pa_state,
                        pa.pa_zip,
                        pa.pa_dob,
                        pa.pa_phone,
                        pa.pa_email,
                        pa.pa_ssn,
                        pa.pa_mrn,
                        pa.pa_code,
                        doc.doc_first,
                        doc.doc_last,
                        doc.doc_npi,
                        doc.doc_phone,
                        
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
                        {$more_fields_btype}
                        '{$t}' as btype
                
                    from {$batch_tbl} ba
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
                    left JOIN ci_clinic cli ON cli.cID = ba.cli_ID
	                left JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
	                left JOIN ci_labs lr ON lr.lab_ID = ba.ref_lab_ID
	                left join ci_insurances ins ON ins.ins_ID = ba.insurance1
	                left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
	                {$joins}
                    where 1=1 {$where} 
                    group by ba.mid 
                    order by ba.cli_ID asc, ba.mid desc)";
}

$sql_child = implode("\n UNION ALL \n", $sql_child);



if (!in_array($act, array('export', 'facility_bill_export'))) {
	$row_per_page = 10000;
	$pagination = new Tr_Pagination_class($row_per_page);
	$offset = $pagination->offset();
	$time_start = microtime(true);
	$pagination->setTotal($total);

	$sql = "select * from ( {$sql_child} ) as rs  
    limit {$offset},{$row_per_page}";
} else {
	$sql = "select * from ( {$sql_child} ) as rs  ";
}

$rows = $wpdb->get_results($sql);

if ($act == 'facility_bill_export') {

	$enable_result_insurance = false;

	$paytypename = 'All';
	if ($paytype == 'cash') {
		$paytypename = 'CASH';
	} else if ($paytype == 'insu') {
		$paytypename = 'INS';
	} else if ($paytype == 'no') {
		$paytypename = 'NO-INS';
	}
	$fdname = date('dmY', strtotime($fromdate));
	$tdname = date('dmY', strtotime($todate));
	if (!isset($_REQUEST['view'])) {

		$filename = 'Facility_Bill_' . $paytypename . '_' . $fdname . '-to-' . $tdname . '.csv';
		header("Content-disposition: attachment; filename={$filename}");
		header("Content-Type: text/csv");
	}

	if ($filepath_save_csv != '') {
		$output = fopen($filepath_save_csv, 'w');
		$csv_out_file = basename($filename);
	} else {
		$output = fopen('php://output', 'w');
	}

	$lists = $wpdb->get_results("select * from ci_dagnostic_codes ");
	$all_diagnostic_codes = array();
	foreach ($lists as $list) {
		$all_diagnostic_codes[$list->key] = $list->desc;
	}


	$headers = array(
		'Position',
		'Resulted',
		'Accession',
		'Ref Accession Number',
		'Clinic'
	);

	if (isset($settings['show_mrn_col']) && $settings['show_mrn_col']) {
		$headers[] = 'MRN';
	}

	$headers += array(
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
		'Supervisory Physician',
		'Supervisory Physician NPI',
		'Collection',
		'Received',
		'Lab',
		'Referenced',
		'Panel Name',
		'Test Requested',
		'Insurance',
		'Insurance ID',
		'Group Number',
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
		'Billing Codes',
		'Notes',
		'internal Notes',
		'Status',
		'Priority'
	);
	if ($enable_result_insurance) {
		$headers[] = 'Result';
	}
	if ($show_rapid_collect) {
		$headers[] = 'Collect';
		$headers[] = 'Miles';
	}
	if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
		$headers[] = 'Collector';
	}
	fputcsv($output, $headers, ',');

	foreach ($rows as $row) {
		$has_covid = false;
		//check insurance_name
		if (empty($row->insurance)) {
			$row->insurance = $row->subscriber_insurance;
		}

		$row->doc_sp = '';
		$row->doc_sp_npi = '';
		if ($need_sp && $row->doc_need_sp_id > 0) {
			$sp_doctor = isset($all_sp_doctors[$row->doc_need_sp_id]) ? $all_sp_doctors[$row->doc_need_sp_id] : false;
			if ($sp_doctor) {
				$row->doc_sp = $sp_doctor->doc_first . ' ' . $sp_doctor->doc_last;
				$row->doc_sp_npi = $sp_doctor->doc_npi;
			}
		}


		$result_values = array();
		foreach (explode('|', strtoupper($row->testes_resulted)) as $tdr) {
			$arrtdr = explode('=', $tdr);
			$result_values[$arrtdr[0]] = $arrtdr[1];
		}

		$parent_name = '';
		$parent_dob = '';

		if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
			$parent_name = $row->ins_last . ', ' . $row->ins_first;
			$parent_dob = $row->ins_dob;
		}


		$row->withtimes = 1;
		$billing_codes = tr_billing_get_cpt_code($row);

		$diagnostic_desc = isset($all_diagnostic_codes[$row->dagnostic_codes1]) ? $all_diagnostic_codes[$row->dagnostic_codes1] : '';
		$diagnostic_desc = str_ireplace($row->dagnostic_codes1, '', $diagnostic_desc);


		$priority_name = ($row->priority_hours > 0) ? $row->priority_hours . 'hr' : '';
		if (defined('TRPDD_SHOW_PRIORITY_NAME') && TRPDD_SHOW_PRIORITY_NAME) {
			$arrpriorities = $row->btype == 'pcr' ? TRPDD_PRIORITIES_PCR : TRPDD_PRIORITIES;
			$priority_name = isset($arrpriorities[$row->priority_hours]) ? $arrpriorities[$row->priority_hours] : '';
		}

		if (!isset($row->payer_fin_mnemonic)) {
			$row->payer_fin_mnemonic = '';
		}

		$panel = array();
		$groupids = array();
		$testids = array();

		foreach (explode(',', $row->panel_tests_requested) as $testid) {
			$testid = trim($testid);
			$testid = str_replace('+', '', $testid);
			if (stripos($testid, 'group_') !== false) {
				$groupid = str_ireplace('group_', '', $testid);
				if (isset($groups_notestcb[$groupid])) {
					foreach ($groups_notestcb[$groupid] as $tid) {
						$testids[] = $tid;
					}
				}

				if (in_array($groupid, $covid_group_ids)) {
					$result_values[strtoupper($groups[$groupid])] = '';
				}
			} else {
				$testids[] = $testid;
			}
		}


		foreach ($testids as $testid) {
			$testid = trim($testid);
			$testid = str_replace('+', '', $testid);
			if (stripos($testid, 'group_') !== false) {
				continue;
			} else if (isset($all_tests[$testid])) {
				$drugpars = array(
					strtoupper($all_tests[$testid]->te_name),
					strtoupper($all_tests[$testid]->te_abrev),
					strtoupper($all_tests[$testid]->te_abrev2),
				);
				$testdrug = '';
				foreach ($drugpars as $tdr) {
					if (isset($result_values[$tdr])) {
						$testdrug = $tdr;
						break;
					}
				}
				if (!empty($testdrug)) {
					$group_id = $all_tests[$testid]->te_group_id;
					$panelname = isset($groups[$group_id]) ? $groups[$group_id]->tg_name : '';
					$testname = $all_tests[$testid]->te_name;

					if (stripos($panelname, 'cov-') !== false || stripos($testname, 'cov-') !== false) {
						$has_covid = true;
					}

					if ($settings['enable_us_date_format']) {
						if (!empty($row->spec_received)) {
							$row->spec_received = date('m/d/Y h:iA', strtotime($row->spec_received));
						}
						if (!empty($row->doc)) {
							$row->doc = date('m/d/Y h:iA', strtotime($row->doc));
						}

						if (!empty($row->pa_dob)) {
							$row->pa_dob = date('m/d/Y', strtotime($row->pa_dob));
						}
					}


					$rowdata = array(
						$row->position,
						$row->resulted ? 'Yes' : 'No',
						$row->assen,
						$row->oldreq,
						$row->requesting_practice
					);
					if (isset($settings['show_mrn_col']) && $settings['show_mrn_col']) {
						$rowdata[] = $row->pa_mrn;
					}
					$rowdata += array(
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
						$row->doc_sp,
						$row->doc_sp_npi,
						$row->doc,
						$row->spec_received,
						$row->lab,
						$row->lab_ref,
						$panelname,
						$testname,

						$row->insurance,
						$row->ins_member_id,
						$row->ins_group_id,
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
						$billing_codes,
						$row->notes,
						$row->internal_notes,
						$all_status[$row->batch_status],
						$priority_name,
					);

					if ($enable_result_insurance) {
						$resultpn = '';
						if ($has_covid) {
							$groupd_covid2_id = 0;
							if (is_null($row->covid_pos)) {
								if (function_exists('trpdd_cal_ncovid19')) {
									$arr = trpdd_get_pcr_covid_test();
									$arr['drug'] = $arr['te_name'];

									$is_covid2 = $groupd_covid2_id > 0 && stripos($row->panel_tests_requested, 'GROUP_' . $groupd_covid2_id) !== false;
									$arr = trpdd_cal_ncovid19($arr, $row->id, $row->instrument, $row->doc, $is_covid2);
									$row->covid_pos = $arr['covid_pos'];
								}
							}

							if ($row->covid_pos === '1') {
								$resultpn = 'Positive';
							} else if ($row->covid_pos >= 2) {
								$resultpn = 'Inconclusive';
							} else {
								$resultpn = 'Negative';
							}
						}

						$rowdata[] = $resultpn;
					}

					if ($show_rapid_collect) {
						$rowdata[] = $row->collect_by_rapid ? 'Yes' : 'No';
						$rowdata[] = $row->miles_rapid;
					}

					if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
						$rowdata[] = $row->collector;
					}

					fputcsv($output, $rowdata, ',');
				}
			}
		}
	}
	@fclose($output);
	if ($filepath_save_csv == "") exit;
} else if (($act == 'export' && $is_billing) || $export_func != "") {
	$paytypename = 'All';
	if ($paytype == 'cash') {
		$paytypename = 'CASH';
	} else if ($paytype == 'insu') {
		$paytypename = 'INS';
	} else if ($paytype == 'no') {
		$paytypename = 'NO-INS';
	}
	$fdname = date('dmY', strtotime($fromdate));
	$tdname = date('dmY', strtotime($todate));
	$filename = $paytypename . '_' . $fdname . '-to-' . $tdname . '.csv';
	$output = null;
	tr_pdd_header('csv', $outfile);
	if ($filepath_save_csv != '') {
		$output = fopen($filepath_save_csv, 'w');
		$csv_out_file = basename($filename);
	} else {
		$output = fopen('php://output', 'w');
	}

	$headers = array(
		'Service Date',
		'Accession',
		'Ref Acc',
		'Last Name',
		'First Name',
		'DOB',
		//'Notes',
		'CPT',
		'Test Name',
		'Amount',
		//'REIMBURSMENT'
	);

	fputcsv($output, $headers, ',');

	foreach ($rows as $row) {
		$panels = array();
		$result_values = array();
		foreach (explode('|', strtoupper($row->testes_resulted)) as $tdr) {
			$arrtdr = explode('=', $tdr);
			$result_values[$arrtdr[0]] = $arrtdr[1];
		}

		if (in_array($row->btype, array('pcr', 'blood'))) {

			$row->bytest = true;
			$row->withobj = true;
			$billing_codes = tr_billing_get_cpt_code($row);
			$total_amount = '';
			$groupids = array();
			$showed_amount = array();
			foreach (explode(',', $row->panel_tests_requested) as $testid) {
				$testid = trim($testid);
				$testid = str_replace('+', '', $testid);
				if (stripos($testid, 'group_') !== false) {
					$group_id = str_ireplace('group_', '', $testid);
					if (!in_array($group_id, $groupids) && isset($groups[$group_id])) {
						$panels[] = array(
							'name' => $groups[$group_id]->tg_name . '*',
							'code' => $groups[$group_id]->tg_cpt_code,
							'amount' => ''
						);

						if (isset($groups_notestcb[$group_id])) {

							foreach ($groups_notestcb[$group_id] as $tid) {
								if (isset($all_tests[$tid])) {

									if (in_array($billing_codes[$tid]['code'], $showed_amount)) {
										$billing_codes[$tid]['amount'] = '';
									} else {
										$total_amount += $billing_codes[$tid]['amount'];
									}
									$showed_amount[] = $billing_codes[$tid]['code'];
									if (!$is_summary) {
										$panels[] = array(
											'name' => $all_tests[$tid]->te_name,
											'code' => $billing_codes[$tid]['code'],
											'amount' => $billing_codes[$tid]['amount']
										);
									}
								}
							}
						}
						$groupids[] = $group_id;
					}
				} else if (isset($all_tests[$testid])) {
					$drugpars = array(
						strtoupper($all_tests[$testid]->te_name),
						strtoupper($all_tests[$testid]->te_abrev),
						strtoupper($all_tests[$testid]->te_abrev2),
					);
					$testdrug = '';
					foreach ($drugpars as $tdr) {
						if (isset($result_values[$tdr])) {
							$testdrug = $tdr;
							break;
						}
					}

					if (!empty($testdrug)) {
						$group_id = $all_tests[$testid]->te_group_id;
						if ($group_id > 0 && !in_array($group_id, $groupids) && isset($groups[$group_id])) {
							$panels[] = array(
								'name' => $groups[$group_id]->tg_name . '*',
								'code' => $groups[$group_id]->tg_cpt_code,
								'amount' => ''
							);
							$groupids[] = $group_id;
						}


						if (in_array($billing_codes[$testid]['code'], $showed_amount)) {
							$billing_codes[$testid]['amount'] = '';
						} else {
							$total_amount += $billing_codes[$testid]['amount'];
						}
						if (!$is_summary) {
							$panels[] = array(
								'name' => $all_tests[$testid]->te_name,
								'code' => $billing_codes[$testid]['code'],
								'amount' => $billing_codes[$testid]['amount']
							);
						}
					}
				}
			}
		}

		if ($settings['enable_us_date_format']) {
			if (!empty($row->spec_received)) {
				$row->spec_received = date('m/d/Y h:iA', strtotime($row->spec_received));
			}
			if (!empty($row->doc)) {
				$row->doc = date('m/d/Y h:iA', strtotime($row->doc));
			}

			if (!empty($row->pa_dob)) {
				$row->pa_dob = date('m/d/Y', strtotime($row->pa_dob));
			}
		}

		$rowdata = array(
			$row->doc,
			$row->assen,
			$row->oldreq,
			$row->pa_last,
			$row->pa_first,
			$row->dob,
			//	$row->notes,

		);
		if (count($panels) == 0) {
			$panels = array(array());
		}
		foreach ($panels as $i => $pnarr) {
			if ($i > 0) {
				$rowdata = array('', '', '', '', '', '');
				$total_amount = '';
			}
			$rowdata[] = $pnarr['code'];
			$rowdata[] = $pnarr['name'];
			$rowdata[] = $pnarr['amount'];
			//$rowdata[] = $total_amount;
			fputcsv($output, $rowdata, ',');
		}
	}
	@fclose($output);
	if ($filepath_save_csv == "") exit;
} else if ($act == 'export') {
	$paytypename = 'All';
	if ($paytype == 'cash') {
		$paytypename = 'CASH';
	} else if ($paytype == 'insu') {
		$paytypename = 'INS';
	} else if ($paytype == 'no') {
		$paytypename = 'NO-INS';
	}
	$fdname = date('dmY', strtotime($fromdate));
	$tdname = date('dmY', strtotime($todate));
	$filename = $paytypename . '_' . $fdname . '-to-' . $tdname . '.csv';
	$output = null;
	tr_pdd_header('csv', $outfile);
	if ($filepath_save_csv != '') {
		$output = fopen($filepath_save_csv, 'w');
		$csv_out_file = basename($filename);
	} else {
		$output = fopen('php://output', 'w');
	}

	$lists = $wpdb->get_results("select * from ci_dagnostic_codes ");
	$all_diagnostic_codes = array();
	foreach ($lists as $list) {
		$all_diagnostic_codes[$list->key] = $list->desc;
	}


	$headers = array(
		'Position',
		'Resulted',
		'Accession',
		'Ref Accession Number',
		'Clinic'
	);

	if (isset($settings['show_mrn_col']) && $settings['show_mrn_col']) {
		$headers[] = 'MRN';
	}

	$headers = array_merge($headers, array(
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
		'Supervisory Physician',
		'Supervisory Physician NPI',
		'Collection',
		'Received',
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
		'Billing Codes',
		'Notes',
		'internal Notes',
		'Status',
		'Priority'
	));

	if ($enable_result_insurance) {
		$headers[] = 'Result';
	}
	if ($show_rapid_collect) {
		$headers[] = 'Collect';
		$headers[] = 'Miles';
	}
	if ($insurance_Salesperson) {
		$headers[] = 'SalesPerson';
		$headers[] = 'Distributor';
		$headers[] = 'Marketing Group';
	}
	if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
		$headers[] = 'Collector';
	}
	fputcsv($output, $headers, ',');
	foreach ($rows as $row) {
		$has_covid = false;
		//check insurance_name
		if (empty($row->insurance)) {
			$row->insurance = $row->subscriber_insurance;
		}

		$row->doc_sp = '';
		$row->doc_sp_npi = '';
		if ($need_sp && $row->doc_need_sp_id > 0) {
			$sp_doctor = isset($all_sp_doctors[$row->doc_need_sp_id]) ? $all_sp_doctors[$row->doc_need_sp_id] : false;
			if ($sp_doctor) {
				$row->doc_sp = $sp_doctor->doc_first . ' ' . $sp_doctor->doc_last;
				$row->doc_sp_npi = $sp_doctor->doc_npi;
			}
		}

		//get panel
		if ($row->custom_profile == 'Use Custom Profile' && !empty($row->panel_tests_requested)) {
			$panel = $row->panel_tests_requested;
		} else if (strpos($row->panel_tests_requested, '+') === false) {
			$panel = trim(ucfirst($row->custom_profile) . ' +' . $row->panel_tests_requested, '+');
		} else {
			$panel = $row->panel_tests_requested;
		}

		$result_values = array();
		foreach (explode('|', strtoupper($row->testes_resulted)) as $tdr) {
			$arrtdr = explode('=', $tdr);
			$result_values[$arrtdr[0]] = $arrtdr[1];
		}

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
					$drugpars = array(
						strtoupper($all_tests[$testid]->te_name),
						strtoupper($all_tests[$testid]->te_abrev),
						strtoupper($all_tests[$testid]->te_abrev2),
					);
					$testdrug = '';
					foreach ($drugpars as $tdr) {
						if (isset($result_values[$tdr])) {
							$testdrug = $tdr;
							break;
						}
					}

					if (!empty($testdrug)) {
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

		$parent_name = '';
		$parent_dob = '';
		if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
			$parent_name = $row->ins_last . ', ' . $row->ins_first;
			$parent_dob = $row->ins_dob;
		}
		$row->withtimes = 1;

		$billing_codes = tr_billing_get_cpt_code($row);
		$billing_codes = str_replace(',', ',  ', $billing_codes);

		$diagnostic_desc = isset($all_diagnostic_codes[$row->dagnostic_codes1]) ? $all_diagnostic_codes[$row->dagnostic_codes1] : '';
		$diagnostic_desc = str_ireplace($row->dagnostic_codes1, '', $diagnostic_desc);


		$priority_name = ($row->priority_hours > 0) ? $row->priority_hours . 'hr' : '';
		if (defined('TRPDD_SHOW_PRIORITY_NAME') && TRPDD_SHOW_PRIORITY_NAME) {
			$arrpriorities = $row->btype == 'pcr' ? TRPDD_PRIORITIES_PCR : TRPDD_PRIORITIES;
			$priority_name = isset($arrpriorities[$row->priority_hours]) ? $arrpriorities[$row->priority_hours] : '';
		}

		if (!isset($row->payer_fin_mnemonic)) {
			$row->payer_fin_mnemonic = '';
		}

		if ($settings['enable_us_date_format']) {
			if (!empty($row->spec_received)) {
				$row->spec_received = date('m/d/Y h:iA', strtotime($row->spec_received));
			}
			if (!empty($row->doc)) {
				$row->doc = date('m/d/Y h:iA', strtotime($row->doc));
			}

			if (!empty($row->pa_dob)) {
				$row->pa_dob = date('m/d/Y', strtotime($row->pa_dob));
			}
		}

		$rowdata = array(
			$row->position,
			$row->resulted ? 'Yes' : 'No',
			$row->assen,
			$row->oldreq,
			$row->requesting_practice
		);
		if (isset($settings['show_mrn_col']) && $settings['show_mrn_col']) {
			$rowdata[] = $row->pa_mrn;
		}
		$rowdata = array_merge($rowdata, array(
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
			$row->doc_sp,
			$row->doc_sp_npi,
			$row->doc,
			$row->spec_received,
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
			$billing_codes,
			$row->notes,
			$row->internal_notes,
			$all_status[$row->batch_status],
			$priority_name,
		));

		if ($enable_result_insurance) {
			$resultpn = '';
			if ($has_covid) {
				$groupd_covid2_id = 0;
				if (is_null($row->covid_pos)) {
					if (function_exists('trpdd_cal_ncovid19')) {
						$arr = trpdd_get_pcr_covid_test();
						$arr['drug'] = $arr['te_name'];

						$is_covid2 = $groupd_covid2_id > 0 && stripos($row->panel_tests_requested, 'GROUP_' . $groupd_covid2_id) !== false;
						$arr = trpdd_cal_ncovid19($arr, $row->id, $row->instrument, array(), $row->doc, $is_covid2);
						$row->covid_pos = $arr['covid_pos'];
					}
				}

				if ($row->covid_pos === '1') {
					$resultpn = 'Positive';
				} else if ($row->covid_pos >= 2) {
					$resultpn = 'Inconclusive';
				} else {
					$resultpn = 'Negative';
				}
			}

			$rowdata[] = $resultpn;
		}

		if ($show_rapid_collect) {
			$rowdata[] = $row->collect_by_rapid ? 'Yes' : 'No';
			$rowdata[] = $row->miles_rapid;
		}

		if ($insurance_Salesperson) {
			$salesperson = $all_clinics[$row->cli_ID]->salesperson;
			$distributor = $all_clinics[$row->cli_ID]->distributor;
			$marketing_group = $all_clinics[$row->cli_ID]->marketing_group;

			$rowdata[] = $salesperson;
			$rowdata[] = $distributor;
			$rowdata[] = $marketing_group;
		}
		if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
			$rowdata[] = $row->collector;
		}


		fputcsv($output, $rowdata, ',');
	}
	@fclose($output);
	if ($filepath_save_csv == "") exit;
}

if (isset($_GET['debug'])) {
	var_dump(nl2br($sql));
	echo '<br><br>';
	var_dump(count($rows));
	echo '<br><br>';
}

$labs = $wpdb->get_results("select lab_ID, lab_name, lab_dba_init, lab_is_ref from ci_labs");

$clinics = array();
if ($settings['enable_filter_clinic']) {
	$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic 
		                                    from ci_clinic cli
                                            where cli.cli_status = 1
                                            group by cli.cID
                                            order by cli_clinic asc");
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
	return;
}
include_once(TRCPDD_PATH . 'pages/formdata.php');
?>

<style>
	div.panels {
		overflow: auto;
		max-height: 150px
	}

	body table.report_tbl td .testcpttbl tr {
		background: none !important;
		height: 14px;
	}

	body table.report_tbl td .testcpttbl td {
		padding: 0 !important;
		background: none !important;
		line-height: 12px !important;
	}

	body table.report_tbl td .testcpttbl tr:last-child td {
		border-bottom: none;
	}

	.testcpttbl {
		width: 100%;
	}

	.testcpttbl td.cptcode {
		width: 60px;
	}

	.testcpttbl td.amount {
		width: 40px;
	}

	.testcpttbl td.pname {
		width: 260px;
	}

	span.fillred,
	tr.fillred td {
		color: red !important;
	}

	h1,
	h2,
	h3,
	h4,
	h5,
	h6 {
		font-weight: unset !important;
		font-size: inherit;
		margin-bottom: 6px !important;
	}

	h1,
	h4 {
		margin: unset;
	}

	h1 {
		margin-bottom: 2px;
	}

	h4 {
		margin-bottom: 5px;
	}

	h5 {
		font-weight: var(--font-weight-medium);
		margin: 0px;
		--tw-font-weight: var(--font-weight-medium);
		font-size: 13px;
		margin-bottom: 10px;
	}

	.text-primary {
		color: var(--primary);
	}

	ol {
		list-style-type: none;
		margin: 0px;
		margin-top: px;
		padding-top: 5px;
		padding-left: 5px;
	}

	ol li a {
		color: unset;
	}


	.text-2xl {
		font-size: var(--text-2xl);
		line-height: var(--tw-leading, var(--text-2xl--line-height));
	}
</style>
<?php
$schedule_report->style();
?>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
	<div class="space-y-6">
		<div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
			<nav aria-label="breadcrumb" data-slot="breadcrumb">
				<ol data-slot="breadcrumb-list"
					class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">
					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
						<a data-slot="breadcrumb-link"
							class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
							href="?page=dashboard_main">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
								<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
								<path
									d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
								</path>
							</svg>
							<span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span>
						</a>
					</li>

					<li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
						class="[&amp;&gt;svg]:size-3.5">
						<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
							class="lucide lucide-chevron-right">
							<path d="m9 18 6-6-6-6"></path>
						</svg>
					</li>

					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
						<a data-slot="breadcrumb-link"
							class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
							href="?page=ci_reports_main">
							<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
								<path
									d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
								</path>
								<path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
								<path d="M10 9H8"></path>
								<path d="M16 13H8"></path>
								<path d="M16 17H8"></path>
							</svg>
							<span class="truncate max-w-[120px] sm:max-w-none">Reports</span>
						</a>
					</li>

					<li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
						class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
							viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
							<path d="m9 18 6-6-6-6"></path>
						</svg></li>

					<li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
							data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
							class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg
								xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
								stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3" aria-hidden="true">
								<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
								<path d="M18 17V9"></path>
								<path d="M13 17V5"></path>
								<path d="M8 17v-3"></path>
							</svg><span class="truncate max-w-[120px] sm:max-w-none">Insurance
								Verification</span></span></li>
				</ol>
			</nav>
		</div>
		<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
			<div>
				<h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
					Insurance Verification</h1>
				<p class="text-muted-foreground mt-1">Sample volume analysis by Insurance
					Verification</p>
			</div>
		</div>
		<div>
			<div class="space-y-5">

				<div data-slot="card"
					class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
					<div data-slot="card-header"
						class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
						<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
							<div>
								<h4 data-slot="card-title" class="leading-none flex items-center gap-2">
									<div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
											width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
											stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
											<path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
											<path d="M18 17V9"></path>
											<path d="M13 17V5"></path>
											<path d="M8 17v-3"></path>
										</svg></div>Volume by Test Panel
								</h4>
							</div>
							<div class="flex items-center gap-3">
								<div style="position:relative;display:inline-block;">

									<button id="export_report" ; data-slot="dropdown-menu-trigger"
										class="inline-flex export_item btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-primary hover:bg-primary/90"
										type="button" id="radix-:rkg:" aria-haspopup="menu" aria-expanded="false"
										data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
											viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
											stroke-linecap="round" stroke-linejoin="round"
											class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
											<path d="M12 15V3"></path>
											<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
											<path d="m7 10 5 5 5-5"></path>
										</svg>Export Report
										</svg>
									</button>
								</div>
								<?php $schedule_report->btn_schedule(); ?>
							</div>
						</div>
						<div class="flex flex-wrap items-center gap-3 mt-4">

							<?php Tr_Session_Class::show() ?>
							<div class="tablenav top">
								<form method="get" class="left alignleft">
									<input type="hidden" name="page"
										value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
									<input type="hidden" name="tab"
										value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />

									<label>From:</label>
									<input type="search" id="date_from" class="hasdate" name="fromdate" value="<?php echo $fromdate ?>"
										style="width:120px" placeholder="From Date" required />
									<label>To:</label>
									<input type="search" id="date_to" class="hasdate" name="todate" value="<?php echo $todate ?>" style="width:120px" placeholder="To Date" required />
									<input type="submit" class="button bsty6" value="Search" />
									<?php
									if (isset($settings['facility_bill_export']) && $settings['facility_bill_export']) {
									?>
										<input type="submit" class="button" name="trajax" value="Facility Bill Export" />
									<?php
									}
									?>
								</form>
								<?php if (isset($pagination))
									echo $pagination->pagination() ?>
							</div>

						</div>
					</div>
					<div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
						<div class="border rounded-lg overflow-hidden">
							<div class="overflow-x-auto">
								<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl"
									style="width:<?php echo $is_billing ? '100%' : '5000px' ?>">
									<thead class="sticky">
										<tr>
											<?php if ($is_billing) {
											?>
												<th>Service Date</th>
												<th>Accession</th>
												<th>Ref Acc</th>
												<th>Last Name</th>
												<th>First Name</th>
												<th style="width:100px">DOB</th>
												<?php if ($paytype == 'insu') {
													echo '<th>Insurance</th>';
												} ?>
												<th style="width:60px">CPT</th>
												<th style="width:260px">Test Name</th>
												<th style="width:40px">Amount</th>
											<?php
											} else { ?>
												<th style="width:20px">#</th>
												<th style="width:20px">Resulted</th>
												<th style="width:100px">Accession Number</th>
												<?php tr_tbl_sort('cli', 'Facility', '', 'min-width:150px') ?>
												<?php if (isset($settings['show_mrn_col']) && $settings['show_mrn_col']) { ?>
													<th>MRN</th>
												<?php } ?>
												<th style="min-width:100px">First Name</th>
												<th style="min-width:100px">Last Name</th>
												<th style="min-width:40px">Gender</th>
												<?php if (isset($settings['insurance_sresg']) && $settings['insurance_sresg']) {
												?>
													<th style="min-width:50px">Race</th>
													<th style="min-width:50px">Ethnicity</th>
													<th style="min-width:100px">Sexual Orientation</th>
													<th style="min-width:100px">Gender Identity</th>
												<?php
												}
												?>
												<th style="min-width:80px">Patient Address</th>
												<th style="min-width:80px">City</th>
												<th style="min-width:30px">State</th>
												<th style="min-width:80px">Zip</th>
												<th style="width:80px">DOB</th>
												<th style="min-width:120px">Patient Phone</th>
												<th>Patient ID</th>
												<th>Parent/Spouse Name</th>
												<th>Parent/Spouse DOB</th>
												<th style="min-width:80px">Clinic City</th>
												<th>Dr First</th>
												<th>Dr Last</th>
												<th>NPI</th>
												<th>Supervisory Physician</th>
												<th>Supervisory Physician NPI</th>
												<th>Dr Phone</th>
												<?php tr_tbl_sort('doc', 'Collection', '', 'max-width:80px') ?>
												<th style="width:80px;max-width:80px;">Received</th>
												<th style="width:80px;max-width:80px;">Result Date</th>
												<th style="width:30px">Lab</th>
												<th>Referenced</th>
												<?php if ($enable_stat)
													echo '<th>STAT</th>'; ?>
												<th style="min-width:250px">Req Panel Tests</th>
												<th>Insurance</th>
												<th>Insurance ID</th>
												<th>Group Number</th>
												<th>Second Insurance</th>
												<th>Second Insurance ID</th>
												<?php if ($enable_insurance_rp_mpi) { ?>
													<th>MP index</th>
												<?php } ?>
												<th>Type of Specimen</th>
												<th style="min-width:100px">Diagnosis Codes</th>
												<th style="min-width:150px">Diagnosis Codes Description</th>
												<?php if ($show_rapid_collect) {
												?>
													<th style="width:50px">Collect</th>
													<th style="min-width:120px">Miles</th>
												<?php
												} ?>
												<th style="width:100px">Status</th>
												<?php if ($settings['cstatus_ins_report']) {
													echo '<th style="width:100px">Billing Status</th>';
												} else {
													echo '<th style="width:100px">Priority</th>';
												}
												?>

												<th style="min-width:150px;max-width:300px;">Billing Codes</th>
												<?php if ($enable_result_insurance) { ?>
													<th>Result</th>
												<?php } ?>
												<th style="min-width:120px">Notes</th>
											<?php } ?>
											<th style="min-width:120px">Internal Notes</th>
											<?php
											if ($insurance_Salesperson) {
												echo '<th>Salesperson</th>';
												echo '<th>Distributor</th>';
												echo '<th>Marketing Group</th>';
											}
											if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
												echo '<th>Collector</th>';
											}
											?>
										</tr>
									</thead>
									<tbody>
										<?php
										$count_rows = 0;

										foreach ($rows as $row) {
											$parent_name = '';
											$parent_dob = '';
											$has_covid = false;

											$row->doc_sp = '';
											$row->doc_sp_npi = '';
											if ($need_sp && $row->doc_need_sp_id > 0) {
												$sp_doctor = isset($all_sp_doctors[$row->doc_need_sp_id]) ? $all_sp_doctors[$row->doc_need_sp_id] : false;
												if ($sp_doctor) {
													$row->doc_sp = $sp_doctor->doc_first . ' ' . $sp_doctor->doc_last;
													$row->doc_sp_npi = $sp_doctor->doc_npi;
												}
											}

											//check insurance_name
											if (empty($row->insurance)) {
												$row->insurance = $row->subscriber_insurance;
											}
											$result_values = array();
											foreach (explode('|', strtoupper($row->testes_resulted)) as $tdr) {
												$arrtdr = explode('=', $tdr);
												$result_values[$arrtdr[0]] = $arrtdr[1];
											}


											if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
												$parent_name = $row->ins_last . ', ' . $row->ins_first;
												$parent_dob = $row->ins_dob;
											}
											if ($settings['enable_us_date_format']) {
												if (!empty($row->doc)) {
													$row->doc = date('m/d/Y h:iA', strtotime($row->doc));
												}
												if (!empty($row->spec_received)) {
													$row->spec_received = date('m/d/Y h:iA', strtotime($row->spec_received));
												}

												if (!empty($row->pa_dob)) {
													$row->pa_dob = date('m/d/Y', strtotime($row->pa_dob));
												}
											}
											if ($is_billing) {

												include(TRCDBI_PATH . '/pages/loop_insurance_report_billing.php');
											} else {
												include(TRCDBI_PATH . '/pages/loop_insurance_report.php');
											}
											$count_rows++;
										}
										if ($count_rows == 0) {
										?>
											<tr>
												<td colspan="16">Not found</td>
											</tr>
										<?php
										} else {
										}
										?>
									</tbody>
								</table>

								<div class="tablenav bottom"><?php if (isset($pagination)) echo $pagination->pagination() ?></div>

							</div>
						</div>
					</div>
				</div>

				<div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
					<p class="text-xs text-gray-600 text-center">
						<strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
						igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
					</p>
				</div>

			</div>
		</div>
	</div>
</div>
<script>
	var url_main_page = 'ci_reports_insurance_verification';

	jQuery(function($) {
		var $bill_statuses = <?php echo json_encode($bill_statuses) ?>;

		function trcpp_load_results(id) {
			var $obj = $('tr.child.p_' + id + ' > td');
			if ($obj.html() != '') {
				return;
			} else {
				$obj.html('<div class="loading center"></div>');
			}
			$.ajax({
				data: {
					trajax: 1,
					act: 'load_detail',
					cid: id
				},
				type: 'post',
				success: function($html) {
					$obj.html($html);
				}
			})
		}

		$('body').on('click', 'tr.parent', function(e) {
			if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
			$('tr.child').hide();
			if ($(this).data('showed') != '1') {
				var id = $(this).data('id');
				var $obj = $('tr.p_' + id);
				$obj.show();
				$('tr.parent').data('showed', '0');
				$(this).data('showed', '1');
				$(this).addClass('active');
				$('body,html').scrollTop($obj.offset().top - 80);
				trcpp_load_results(id);
			} else {
				$('tr.p_' + $(this).data('p')).hide();
				$(this).data('showed', '0');
				$(this).removeClass('active');
			}
			return false;
		}).on('change', '.bill_status select', function() {
			var $pr = $(this).parent();
			var $vl = $(this).val();
			var $id = $(this).data('id');
			var $btype = $(this).data('btype');
			$pr.append('<span class="sloading"></span>');
			$.post(ajaxurl, {
				tr_action: 'ci_change_batch_bill_status',
				action: 'ajax',
				id: $id,
				btype: $btype,
				status: $(this).val()
			}, function(rs) {
				if ($vl == 'Need Info') {
					var $url = '?tr_action=ci_patient_billing_notes&mid=' + $id;
					tb_show("Billing Notes", $url);
				} else {
					alert(rs);
				}
				$('.sloading', $pr).remove();
			})
		});

		$('td.bill_status').each(function() {
			var $html = '<select data-id="' + $(this).parent().data('id') + '" data-btype="' + $(this)
				.parent().data('btype') + '">';
			for (k in $bill_statuses) {
				$html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') +
					'>' + $bill_statuses[k] + '</option>';
			}
			$html += '</select>';
			$(this).html($html);
		});

		$(".export_item").click(async function() {
			var export_type = "csv";
			var $url = location.href + '&trajax=export_report&export_type=' + export_type;
			$url += '&export_func=all';
			$url += '&date_from=' + $("#date_from").val();
			$url += '&date_to=' + $("#date_to").val();
			showToast('info', 'Preparing your download...', 'Generating...', 4000);
			try {
				const response = await fetch($url);

				// 🔥 Lấy filename từ header
				const disposition = response.headers.get("Content-Disposition");
				let filename = "download." + export_type;

				if (disposition && disposition.includes("filename=")) {
					const match = disposition.match(/filename="?([^"]+)"?/);
					if (match && match[1]) filename = match[1];
				}

				const blob = await response.blob();

				const a = document.createElement("a");
				a.href = URL.createObjectURL(blob);
				a.download = filename; // giữ nguyên tên file
				a.click();

				showToast('success', 'Download ready!', 'Your file "' + filename +
					'" has been downloaded.', 4000);

			} catch (err) {
				showToast('error', 'Error', 'Download failed.', 4000);
			}
			return false;
		});

	})
</script>
<?php $schedule_report->js(); ?>