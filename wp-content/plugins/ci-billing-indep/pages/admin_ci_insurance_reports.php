<?php
global $wpdb, $current_role, $show_menus_options, $ci_active_menu;
global $is_ci_admin;

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
	''  => '',
	'H' => 'Hispanic or Latino',
	'N' => 'Not Hispanic or Latino',
	'U' => 'Unknown'
);
$settings       = tr_billing_get_settings();
$all_status     = tr_pdd_get_batch_statuses(false, false, false, 'all');
$user_id        = get_current_user_id();
$is_close_lab_export = isset($ci_close_lab_export) && $ci_close_lab_export;
$is_close_lab_export_batch = isset($ci_close_lab_export_batch) && $ci_close_lab_export_batch;

$act            = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$fromdate       = $is_close_lab_export ? '1900-01-01' : (isset($_REQUEST['fromdate']) ? $_REQUEST['fromdate'] : date_i18n('Y-m-d'));
$todate         = $is_close_lab_export ? date_i18n('Y-m-d') : (isset($_REQUEST['todate']) ? $_REQUEST['todate'] : date_i18n('Y-m-d'));
$paytype        = $is_close_lab_export ? '' : (isset($_REQUEST['paytype']) ? $_REQUEST['paytype'] : 'insu');
$btype_selected = $is_close_lab_export_batch && isset($ci_close_lab_export_current_type)
	? $ci_close_lab_export_current_type
	: ($is_close_lab_export ? '' : (isset($_REQUEST['btype']) ? $_REQUEST['btype'] : ''));
$cli_ID         = $is_close_lab_export ? 0 : (isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0);
$is_billing     = $is_close_lab_export ? 0 : (isset($_REQUEST['billing']) ? $_REQUEST['billing'] : 0);
$is_summary     = isset($_REQUEST['summary']) ? $_REQUEST['summary'] : 0;
$lab_id         = 0;
$ref_lab_id     = 0;
$cid            = 0;
$mids_exported  = array();


$settings['redtest_ins_report'] = isset($settings['redtest_ins_report']) && $settings['redtest_ins_report'];
$settings['cstatus_ins_report'] = isset($settings['cstatus_ins_report']) && $settings['cstatus_ins_report'];
$settings['enable_us_date_format'] = isset($settings['enable_us_date_format']) && $settings['enable_us_date_format'];

$enable_ins_report_rsd      = true;
$enable_result_insurance    = isset($settings['result_insurance']) ? $settings['result_insurance'] : false;
$enable_insurance_rp_mpi    = isset($settings['insurance_rp_mpi']) ? $settings['insurance_rp_mpi'] : false;
$insurance_Salesperson    = isset($settings['insurance_rp_svd']) ? $settings['insurance_rp_svd'] : false;


$show_rapid_collect         = defined('TRPDD_INS_COLLECT_MILES') && TRPDD_INS_COLLECT_MILES;

$enable_stat 				= tr_get_option('ci_pdf_data', 'enable_stat');


$columncds 	= $wpdb->get_col("SHOW COLUMNS FROM ci_clinic_doc");
$need_sp 	= in_array('doc_need_sp_id', $columncds);
$all_sp_doctors	= array();
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

$covid_group_ids    = array();
$groups             = array();
$groups_notestcb    = array();
$groups_reflexes    = array();
$all_clinics    	= array();

$rs = $wpdb->get_results("select tg_ID, 
							tg_name, 
							tg_cpt_code,
                            tg_notestcheckbox,
                            tg_only_group,
                            tg_is_covid,
							tg_parent,
							tg_reflex_group_ID
                            from ci_test_groups 
                            ");
foreach ($rs as $r) {
	$groups[$r->tg_ID] = $r;
	if ($r->tg_notestcheckbox || $r->tg_only_group || $r->tg_is_covid) {
		$groups_notestcb[$r->tg_ID] = array();
	}
	if ($r->tg_is_covid) {
		$covid_group_ids[] = $r->tg_ID;
	}
	if (!empty($r->tg_reflex_group_ID)) {
		$listreflexids = explode(',', $r->tg_reflex_group_ID);
		$groups_reflexes = array_merge($groups_reflexes, $listreflexids);
	}
}

$rs = $wpdb->get_results("select te.*,
					tg.tg_parent,
					tg.tg_antibiotic
					from ci_test_panels te
					JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_id
					where te.te_in_req = 1 
					and te.te_is_control = 0");
$all_tests = array();
foreach ($rs as $r) {
	$r->te_antibiotic = $r->te_antibiotic || $r->tg_antibiotic;
	$r->has_reflex = in_array($r->te_group_id, $groups_reflexes);
	$all_tests[$r->te_ID] = $r;
	$all_tests[strtoupper($r->te_name)] = $r;

	if ($r->te_nocheckbox && !isset($groups_notestcb[$r->te_group_id])) {
		$groups_notestcb[$r->te_group_id] = array();
		if ($r->tg_parent > 0 && !isset($groups_notestcb[$r->tg_parent])) {
			$groups_notestcb[$r->tg_parent] = array();
		}
	}

	if (isset($groups_notestcb[$r->te_group_id])) {
		$groups_notestcb[$r->te_group_id][] = $r->te_ID;
	}

	if ($r->tg_parent > 0 && isset($groups_notestcb[$r->tg_parent])) {
		$groups_notestcb[$r->tg_parent][] = $r->te_ID;
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


$report_req_types = $is_close_lab_export ? tr_pdd_get_batch_types() : trbilling_req_types();
$all_dagnostic_codes = array();
foreach ($report_req_types as $bt) {
	if ($bt == 'study') continue;
	if (!empty($btype_selected) && $bt != $btype_selected) {
		continue;
	}

	$all_dagnostic_codes = array_merge($all_dagnostic_codes, tr_pdd_get_dagnostic_codes($bt));
}

$orderby = ' order by re.created_date desc, ba.mid desc';
$where_cli = "";

if (!$is_close_lab_export && $current_role == 'BillbyClinic') {
	$clinics = get_user_meta($user_id, '_bill_cli_IDs', true);
	if (!is_array($clinics)) {
		$clinics = array(0);
	}
	$clinics = implode(',', $clinics);
	$where_cli .= " and cli.cID in ({$clinics}) ";
} else if (!$is_close_lab_export && $current_role == 'office_manager') {

	$current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
	if ($current_clinic > 0) {
		$where_cli .= " and cID = {$current_clinic} ";
	} else {

		if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true) || $cid > 0) {
			$cid     = get_user_meta($user_id, '_clinic_id', true);
			$cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
			$cli_ids = implode(',', $cli_ids);


			$where_cli .= " and cID in ( {$cli_ids} )";
		}
	}
} else if (!$is_close_lab_export && $current_role == 'Billing' && !$settings['disable_billbyclinic']) {
	$excludecids = get_option('ci_bill_cli_IDs', array());

	if (is_array($excludecids) && count($excludecids) > 0) {
		$excludecids = implode(',', $excludecids);
		$where_cli .= " and cli.cID not in ({$excludecids}) ";
	}
}

$dateby = 'drs';

$dateby = isset($_REQUEST['dateby']) ? $_REQUEST['dateby'] : $dateby;

if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
	$s = sanitize_text_field($_REQUEST['s']);
	$where = " and ( CONCAT(pa.pa_first,' ',pa.pa_last) like '%{$s}%' or ba.assen like '%{$s}%' ) ";
} else if ($dateby == 'drs') {
	$where = " and re.approve_date >= '{$fromdate}' and re.approve_date <= '{$todate} 23:59:59' ";
	$orderby = ' order by re.approve_date desc, ba.mid desc';
} else {
	$where = " and ba.doc >= '{$fromdate}' and ba.doc <= '{$todate} 23:59:59' ";
	$orderby = ' order by ba.doc desc, ba.mid desc';
}

if (isset($_REQUEST['withoutrs'])) {
	$where .= " and (re.estatus not in ('C','F') or re.id is null)";
} else {
	$where .= " and  re.estatus in ('C','F') ";
}


$where .= " and " . tr_pdd_where_batch_rejected(false, true, '', array('cancelled')) . ' ';


if (defined('TRPDD_UPSAVED') && TRPDD_UPSAVED) {
	$where .= " and ba.is_saved = 0 ";
}


if (!$is_close_lab_export && isset($settings['exclude_ref_lab']) && !empty($settings['exclude_ref_lab'])) {
	$where .= " and (ba.ref_lab_id = 0 or ba.ref_lab_id = '{$current_lab_id}' ) ";
} else if (!$is_close_lab_export) {
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
if (!$is_close_lab_export && $user_ref_lab_id > 0) {
	$where .= " and ba.ref_lab_id = {$user_ref_lab_id}  ";
}

$ormorepaytype = '';

if (!empty($paytype)) {

	if (isset($settings['paytypebynoinscash']) && $settings['paytypebynoinscash']) {
		if ($paytype == 'cash') {
			$ormorepaytype .= ' or ba.paid > 0 ';
		} else if ($paytype == 'insu') {
			$ormorepaytype .= ' or ba.paid = 0 ';
		}
	}

	if ($paytype !== 'no' && $paytype !== 'clientb') {
		$where_cli .= " and (cli.cli_pay_type = '{$paytype}' {$ormorepaytype} ) ";
	}

	if ($paytype == 'clientb') {
		$where .= " AND (cli.cli_pay_type = '{$paytype}' OR ba.insurance = 'Client Bill') ";
	} else if ($paytype == 'no') {
		$where .= " AND (
			cli.cli_pay_type = '{$paytype}' 
			OR (ba.insurance = 'No Insurance - Cash' OR ba.insurance = '')
		) ";
	} else if ($paytype == 'cash') {
		$where .= " AND (
			cli.cli_pay_type = '{$paytype}'
			OR ba.insurance = 'No Insurance - Cash'
		) ";
	} else if ($paytype == 'insu') {
		$where .= " AND (
			cli.cli_pay_type = '{$paytype}' 
			OR (ba.insurance != 'No Insurance - Cash' OR ba.insurance != '')
		) ";
	}
}


if ($cli_ID > 0) {
	$where_cli .= " and cli.cID = {$cli_ID} ";
}


//$where .= " and ba.insurance != 'No Insurance - Cash' and ba.insurance!=''";

if (isset($_REQUEST['deleted_clinic'])) {
	$where .= " and ba.cli_ID not in (select cID from ci_clinic cli ) ";
} else if (!empty($where_cli)) {
	$where .= " and ba.cli_ID in (select cID from ci_clinic cli where 1 {$where_cli}) ";
} else if (!empty($ormorepaytype)) {
	$where .= " and 1 {$ormorepaytype} ";
}


if (!$is_close_lab_export && !empty($_REQUEST['lab_reflab'])) {
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



$sql_child      = array();
$more_fields    = '';
$joins          = '';
$has_more_type  = false;
$rpp_gi         = false;

if ($enable_result_insurance) {
	$more_fields .= " re.covid_pos, ";
}
if ($show_rapid_collect) {
	$more_fields .= " ba.collect_by_rapid, ba.miles_rapid, ";
}
if (isset($settings['insurance_sresg']) && $settings['insurance_sresg']) {
	$more_fields .= " pa.pa_race, pa.pa_ethnicity, pa.pa_sexual_oriention,pa.pa_gender_identity,";
}
if ($enable_insurance_rp_mpi) {
	$joins .= "left join ci_payer_list pl ON pl.payer_name = ba.insurance ";
	$more_fields .= 'pl.payer_fin_mnemonic,';
}

if ($need_sp) {
	$joins .= " left join ci_clinic_doc cd ON cd.cli_ID = ba.cli_ID and cd.doc_ID = ba.doc_ID ";
	$more_fields .= 'cd.doc_need_sp_id,';
}

if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
	$joins .= " left join ci_collector cole ON cole.col_ID = ba.col_ID ";
	$more_fields .= " CONCAT(cole.col_first,' ',cole.col_last) AS collector, ";
}

$more_fields .= ' bb.bill_status, ';
$where_join = " bb.assen = ba.assen ";
$joins .= " left join ci_batch_billings bb ON {$where_join} ";
$batch_cursor_where = '';
if ($is_close_lab_export_batch && !empty($ci_close_lab_export_last_mid)) {
	$batch_cursor_where = ' and ba.mid < ' . (int) $ci_close_lab_export_last_mid;
}
$batch_where = $where . $batch_cursor_where;

$total = 0;
foreach ($report_req_types as $t) {

	if ($t == 'study') continue;

	if (!empty($btype_selected) && $t != $btype_selected) {
		continue;
	}



	if ((isset($show_menus_options['all']['form_' . $t]) && $show_menus_options['all']['form_' . $t])
			|| (isset($ci_active_menu['form_' . $t]) && $ci_active_menu['form_' . $t])
			|| in_array($current_role, array('Billing', 'BillbyClinic', 'AdminCIDW'))
			|| $is_close_lab_export
		) {


		$batch_tbl = tr_pdd_get_table_by_btype($t);
		$report_tbl = tr_pdd_get_table_report_by_btype($t);
		$result_tbl = tr_pdd_get_table_result_by_btype($t);
		if ($is_close_lab_export
			&& (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $batch_tbl))
				|| !$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $report_tbl)))) {
			continue;
		}
		$result_field = "'' as testes_resulted,";
		if (!$is_close_lab_export || $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $result_tbl))) {
			$result_field = "(select group_concat(drug,'=',`pos-neg`,';',conc SEPARATOR '|') from {$result_tbl} rs where rs.report_id = re.id group by rs.report_id) as testes_resulted,";
		}
		$batch_mid_filter = '';
		if ($is_close_lab_export_batch) {
			$candidate_where = str_replace(array('ba.', 're.'), array('ba_find.', 're_find.'), $batch_where);
			$batch_mid_filter = " and ba.mid in (
				select batch_ids.mid from (
					select STRAIGHT_JOIN ba_find.mid
					from {$batch_tbl} ba_find
					join {$report_tbl} re_find ON ba_find.assen = re_find.patient_id
					where 1=1 {$candidate_where}
					order by ba_find.mid desc
					limit {$ci_close_lab_export_limit}
				) batch_ids
			)";
		}
		$report_order = $is_close_lab_export_batch ? 'order by ba.mid desc' : 'order by ba.cli_ID asc, ba.mid desc';
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
		                    left join {$report_tbl} re ON  ba.assen = re.patient_id
							left JOIN ci_clinic cli ON cli.cID = ba.cli_ID
		                    where 1 {$where} ");
		}

		if ($t == 'tox') {
			$more_fields_btype .= ' ba.only_screening, ';
		} else {
			$more_fields_btype .= " '' as only_screening, ";
		}


		$sql_child[] = "(select 
						ba.mid,ba.position,ba.assen,ba.oldreq,ba.batch_id, ba.spec_received,ba.doc,
						ba.current_medication_other,ba.current_medication,ba.insurance,ba.insurance2,ba.subscriber_insurance, ba.requesting_physician,
                        ba.panel_tests_requested,ba.custom_profile,ba.requesting_practice,ba.type_of_specimen,ba.batch_status,ba.notes,
                        ba.dagnostic_codes1,ba.priority_hours,ba.cli_ID, ba.internal_notes,ba.billing_notes,
						ba.ref_lab_ID,
                        cli.cli_city,
                        lab.lab_dba_init as lab,
                        lr.lab_dba_init as lab_ref,
                        IF(re.id is not null or (ba.result_pdf is not null and ba.result_pdf !=''), 1 , 0) as resulted,
                        {$result_field}
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
                        re.id,
                        re.approve_date,
						re.has_pos,
                        
                        ins.ins_first,
                        ins.ins_last,
                        ins.ins_dob,
                        ins.ins_company,
						ins.ins_code,
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
						ins2.ins_code as ins_code2, 
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
                    left join {$report_tbl} re ON  ba.assen = re.patient_id  
                    left join ci_doc doc ON doc.doc_ID = ba.doc_ID
                    left join ci_patients pa ON pa.pa_ID = ba.patient_id
                    left JOIN ci_clinic cli ON cli.cID = ba.cli_ID
	                left JOIN ci_labs lab ON lab.lab_ID = ba.lab_id
	                left JOIN ci_labs lr ON lr.lab_ID = ba.ref_lab_ID
	                left join ci_insurances ins ON ins.ins_ID = ba.insurance1
	                left join ci_insurances ins2 ON ins2.ins_ID = ba.insurance2
	                {$joins}
                    where 1=1 {$batch_where} {$batch_mid_filter}
                    group by ba.mid 
                    {$report_order})";
	}
}

$sql_child = implode("\n UNION ALL \n", $sql_child);

if ($is_close_lab_export_batch) {
	$ci_close_lab_export_limit = (int) $ci_close_lab_export_limit;
	$sql = "select * from ( {$sql_child} ) as rs
		order by btype asc, mid desc
		limit {$ci_close_lab_export_limit}";
} else if (!in_array($act, array('export', 'facility_bill_export'))) {
	$row_per_page = 10000;
	$pagination   = new Tr_Pagination_class($row_per_page);
	$offset       = $pagination->offset();
	$time_start   = microtime(true);
	$pagination->setTotal($total);

	$sql = "select * from ( {$sql_child} ) as rs  
    limit {$offset},{$row_per_page}";
} else {
	$sql = "select * from ( {$sql_child} ) as rs  ";
}

$rows = $is_close_lab_export_batch && empty($sql_child) ? array() : $wpdb->get_results($sql);
if (isset($_GET['debug'])) {
	echo $sql;
}

///parse rows
foreach ($rows as $row) {
	if ($row->bill_status == 'Do Not Bill') {
		$dont_bill_note = '<span style="color:red">Do Not Bill</span>';
		if ($act == 'export') {
			$dont_bill_note = 'Do Not Bill';
		}
		$row->billing_notes .= (!empty($row->billing_notes) ? ". " : '') . $dont_bill_note;
	}
}

if ($act == 'facility_bill_export') {

	$enable_result_insurance = false;

	$paytypename = 'All Paytypes';
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

		$lab_name = 'All Labs';
		if ($ref_lab_id > 0) {
			$lab_name = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID={$ref_lab_id} ");
		} else if ($lab_id > 0) {
			$lab_name = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID={$lab_id} ");
		}
		$filename = 'Facility_Bill_' . $lab_name . '_' . $paytypename . '_' . $fdname . '-to-' . $tdname . '.csv';
		header("Content-disposition: attachment; filename={$filename}");
		header("Content-Type: text/csv");
	}

	$output = fopen("php://output", 'w');

	$lists = $wpdb->get_results("select * from ci_dagnostic_codes ");
	$all_diagnostic_codes = array();
	foreach ($lists as $list) {
		$all_diagnostic_codes[$list->key] = $list->desc;
	}


	$headers = array(
		'#',
		'Position',
		'Resulted',
		'Accession',
		'Ref Accession Number',
		'Clinic'
	);

	$headers = array_merge($headers, array(
		'MRN',
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
		'Result Date',
		'Lab',
		'Referenced',
		'Panel Name',
		'Test Requested',
	));

	if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
		$headers[] = 'Insurance Code';
	}

	$headers = array_merge($headers, array(
		'Insurance',
		'Insurance ID',
		'Group Number',
		'MP index',
		'Insurance Address',
		'Insurance City',
		'Insurance State',
		'Insurance Zip',
		'Insurance Phone',
	));

	if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
		$headers[] = 'Secondary Insurance Code';
	}

	$headers = array_merge($headers, array(
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
		'Billing Notes',
		'Internal Notes',
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
	if (isset($settings['show_collec_col']) && $settings['show_collec_col']) {
		$headers[] = 'Collector';
	}
	fputcsv($output, $headers, ',');

	$num = 1;
	foreach ($rows as $row) {
		$has_covid = false;
		//check insurance_name
		if (empty($row->insurance)) {
			$row->insurance = $row->subscriber_insurance;
		}

		$row->doc_sp 		= '';
		$row->doc_sp_npi 	= '';
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

		$parent_name    = '';
		$parent_dob     = '';

		if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
			$parent_name = $row->ins_last . ', ' . $row->ins_first;
			$parent_dob = $row->ins_dob;
		}


		$row->withtimes = 1;
		$billing_codes  = tr_billing_get_cpt_code($row);

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

		$panel      = array();
		$groupids   = array();
		$testids    = array();

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
				$testobj = $all_tests[$testid];
				if ($testobj->has_reflex && !$row->has_pos) {
					continue;
				}

				$drugpars = array(
					strtoupper($testobj->te_name),
					strtoupper($testobj->te_abrev),
					strtoupper($testobj->te_abrev2),
				);
				$testdrug = '';
				foreach ($drugpars as $tdr) {
					if (isset($result_values[$tdr])) {
						$testdrug = $tdr;
						break;
					}
				}
				if (!empty($testdrug)) {
					$group_id = $testobj->te_group_id;
					$panelname = isset($groups[$group_id]) ? $groups[$group_id]->tg_name : '';
					$testname = $testobj->te_name;

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
						if (!empty($row->approve_date)) {
							$row->approve_date = date('m/d/Y h:iA', strtotime($row->approve_date));
						}
						if (!empty($row->pa_dob)) {
							$row->pa_dob = date('m/d/Y', strtotime($row->pa_dob));
						}
					}


					$rowdata = array(
						$num++,
						$row->position,
						$row->resulted ? 'Yes' : 'No',
						$row->assen,
						$row->oldreq,
						$row->requesting_practice
					);

					$rowdata[] = $row->pa_mrn;

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
						$row->approve_date,
						$row->lab,
						$row->lab_ref,
						$panelname,
						$testname,
					));

					if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
						$rowdata[] = $row->ins_code;
					}

					$rowdata = array_merge($rowdata, array(
						$row->insurance,
						$row->ins_member_id,
						$row->ins_group_id,
						$row->payer_fin_mnemonic,
						$row->ins_address,
						$row->ins_city,
						$row->ins_state,
						$row->ins_zip,
						$row->ins_phone,
					));

					if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
						$rowdata[] = $row->ins_code2;
					}

					$rowdata = array_merge($rowdata, array(
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
						$row->billing_notes,
						$row->internal_notes,
				isset($all_status[$row->batch_status]) ? $all_status[$row->batch_status] : $row->batch_status,
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
	exit;
} else if ($act == 'export' && $is_billing) {
	$paytypename = 'All Paytypes';
	if ($paytype == 'cash') {
		$paytypename = 'CASH';
	} else if ($paytype == 'insu') {
		$paytypename = 'INS';
	} else if ($paytype == 'no') {
		$paytypename = 'NO-INS';
	}
	$fdname = date('dmY', strtotime($fromdate));
	$tdname = date('dmY', strtotime($todate));
	$lab_name = 'All Labs';
	if ($ref_lab_id > 0) {
		$lab_name = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID={$ref_lab_id} ");
	} else if ($lab_id > 0) {
		$lab_name = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID={$lab_id} ");
	}
	$filename = $lab_name . '_' . $paytypename . '_' . $fdname . '-to-' . $tdname . '.csv';
	if (!isset($_GET['view'])) {
		header("Content-disposition: attachment; filename={$filename}");
	}

	header("Content-Type: text/csv");
	$output = fopen("php://output", 'w');

	$headers = array(
		'#',
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
		'Sample Total',
		//'REIMBURSMENT'
	);

	fputcsv($output, $headers, ',');

	$num = 1;
	foreach ($rows as $row) {
		$panels = array();
		$result_values = array();
		foreach (explode('|', strtoupper($row->testes_resulted)) as $tdr) {
			$arrtdr = explode('=', $tdr);
			$result_values[$arrtdr[0]] = $arrtdr[1];
		}



		$row->bytest = true;
		$row->withobj = true;
		$billing_codes = tr_billing_get_cpt_code($row);
		$total_amount  = 0;
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
								} else if ($billing_codes[$tid]['amount'] > 0) {
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
				$testdb = $all_tests[$testid];
				$drugpars = array(
					strtoupper($testdb->te_name),
					strtoupper($testdb->te_abrev),
					strtoupper($testdb->te_abrev2),
				);
				$testdrug = '';
				foreach ($drugpars as $tdr) {
					if (isset($result_values[$tdr])) {
						$testdrug = $tdr;
						break;
					}
				}

				if (!empty($testdrug)) {
					$group_id = $testdb->te_group_id;
					if ($group_id > 0 && !in_array($group_id, $groupids) && isset($groups[$group_id])) {
						if ($is_summary && !isset($billing_codes['G' . $group_id])) {
							continue;
						}
						$panels[] = array(
							'name' => $groups[$group_id]->tg_name . '*',
							'code' => $groups[$group_id]->tg_cpt_code,
							'amount' => ''
						);
						$groupids[] = $group_id;
					}


					if (in_array($billing_codes[$testid]['code'], $showed_amount)) {
						$billing_codes[$testid]['amount'] = '';
					} else if ($billing_codes[$testid]['amount'] > 0) {
						$total_amount += $billing_codes[$testid]['amount'];
					}
					if (!$is_summary) {
						$panels[] = array(
							'name' => $testdb->te_name,
							'code' => $billing_codes[$testid]['code'],
							'amount' => $billing_codes[$testid]['amount']
						);
					}
				}
			}
		}


		if (isset($billing_codes['classes']) && $total_amount == 0) {

			foreach ($billing_codes['classes'] as $class_name => $class_info) {
				$panels[] = array(
					'name' => $class_info['class'],
					'code' => $class_info['code'],
					'amount' => $class_info['amount']
				);

				if ($class_info['amount'] > 0) {
					$total_amount += $class_info['amount'];
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
			if (!empty($row->approve_date)) {
				$row->approve_date = date('m/d/Y h:iA', strtotime($row->approve_date));
			}
			if (!empty($row->pa_dob)) {
				$row->pa_dob = date('m/d/Y', strtotime($row->pa_dob));
			}
		}

		$rowdata = array(
			$num++,
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
				$rowdata = array('', '', '', '', '', '', '');
				$total_amount = '';
			}
			$rowdata[] = $pnarr['code'];
			$rowdata[] = $pnarr['name'];
			$rowdata[] = $pnarr['amount'];
			$rowdata[] = $total_amount;
			fputcsv($output, $rowdata, ',');
		}
	}
	@fclose($output);
	exit;
} else if ($act == 'export') {
	$paytypename = 'All Paytypes';
	if ($paytype == 'cash') {
		$paytypename = 'CASH';
	} else if ($paytype == 'insu') {
		$paytypename = 'INS';
	} else if ($paytype == 'no') {
		$paytypename = 'NO-INS';
	}
	$fdname = date('dmY', strtotime($fromdate));
	$tdname = date('dmY', strtotime($todate));
	$lab_name = 'All Labs';
	if ($ref_lab_id > 0) {
		$lab_name = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID={$ref_lab_id} ");
	} else if ($lab_id > 0) {
		$lab_name = $wpdb->get_var("select lab_dba_init from ci_labs where lab_ID={$lab_id} ");
	}
	$filename = $is_close_lab_export
		? 'Patient_Testing_Data_All_Labs_All_Paytypes_All_Dates.csv'
		: $lab_name . '_' . $paytypename . '_' . $fdname . '-to-' . $tdname . '.csv';
	if (!isset($return_file)) {

		header("Content-disposition: attachment; filename={$filename}");
		header("Content-Type: text/csv");
		$output = fopen("php://output", 'w');
	} else {
		$output = fopen($return_file, $is_close_lab_export_batch && !$ci_close_lab_export_write_headers ? 'a' : 'w');
	}


	$lists = $wpdb->get_results("select * from ci_dagnostic_codes ");
	$all_diagnostic_codes = array();
	foreach ($lists as $list) {
		$all_diagnostic_codes[$list->key] = $list->desc;
	}


	$headers = array(
		'#',
		'Position',
		'Resulted',
		'Accession',
		'Ref Accession Number',
		'Clinic'
	);

	$headers[] = 'MRN';

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
		'Result Date',
		'Lab',
		'Referenced',
		'Req Panel Tests',
	));

	if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
		$headers[] = 'Insurance Code';
	}

	$headers = array_merge($headers, array(
		'Insurance',
		'Insurance ID',
		'Group Number',

	));

	if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
		$headers[] = 'Second Insurance Code';
	}

	$headers = array_merge($headers, array(
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
		'Billing Notes',
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
	if (!$is_close_lab_export_batch || $ci_close_lab_export_write_headers) {
		fputcsv($output, $headers, ',');
	}
	$close_lab_result_labels = array(
		'NEG' => 'Not Detected',
		'POS' => 'Detected',
		'POL' => 'Detected-Low',
		'POM' => 'Detected-Medium',
		'POH' => 'Detected-High',
		'PVL' => 'Detected-Low',
		'PVH' => 'Detected-High',
		'PRP' => 'Detected',
	);
	$num = $is_close_lab_export_batch ? $ci_close_lab_export_offset + 1 : 1;
	foreach ($rows as $row) {
		$has_covid = false;
		//check insurance_name
		if (empty($row->insurance)) {
			$row->insurance = $row->subscriber_insurance;
		}

		$row->doc_sp 		= '';
		$row->doc_sp_npi 	= '';
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
			$result_values[$arrtdr[0]] = isset($arrtdr[1]) ? $arrtdr[1] : '';
		}

			if (!in_array($row->btype, array('tox'))) {
				$testids = explode(',', $row->panel_tests_requested);
				$panel = array();
				$groupids = array();
				foreach ($testids as $testid) {
					$testid = trim($testid);
					$testid = str_replace('+', '', $testid);

					if (stripos($testid, 'group_') !== false) {
						$group_id = str_ireplace('group_', '', $testid);
						if (!in_array($group_id, $groupids) && isset($groups[$group_id])) {
							$panel_group_id = $group_id;
							if ($is_close_lab_export && $groups[$group_id]->tg_parent > 0 && isset($groups[$groups[$group_id]->tg_parent])) {
								$panel_group_id = $groups[$group_id]->tg_parent;
							}
							if (!in_array($panel_group_id, $groupids)) {
								$panel[] = $groups[$panel_group_id]->tg_name . '*';
								$groupids[] = $panel_group_id;
							}
							$groupids[] = $group_id;
							if (stripos($groups[$group_id]->tg_name, 'cov-') !== false) {
								$has_covid = true;
							}

							if (isset($groups_notestcb[$group_id])) {
								foreach ($groups_notestcb[$group_id] as $tid) {
									if (isset($all_tests[$tid])) {
										$drugpars = array(
											strtoupper($all_tests[$tid]->te_name),
											strtoupper($all_tests[$tid]->te_abrev),
											strtoupper($all_tests[$tid]->te_abrev2),
										);
										$testdrug = '';
										$testname = $all_tests[$tid]->te_name;
										foreach ($drugpars as $tdr) {
											if (isset($result_values[$tdr])) {
												$testdrug = $tdr;
												break;
											}
										}
										if (!empty($testdrug)) {
											list($pos_neg, $conc) = explode(';', $result_values[$testdrug]);
											if ($is_close_lab_export) {
												$testname .= ' ' . (isset($close_lab_result_labels[$pos_neg]) ? $close_lab_result_labels[$pos_neg] : $pos_neg);
											}
											if (!$is_close_lab_export && $settings['redtest_ins_report'] && in_array($pos_neg, array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVH'))) {
												$digi = $testdrug == 'ONCOSURE' ? 'G' : 'D';
												$panel[] = $testname . ' (' . $digi . ')';
											} else {
												$panel[] = $testname;
											}
										}
									}
								}
							}
						}
					} else if (isset($all_tests[$testid])) {
						$drugpars = array(
							strtoupper($all_tests[$testid]->te_name),
							strtoupper($all_tests[$testid]->te_abrev),
							strtoupper($all_tests[$testid]->te_abrev2),
						);
						$testdrug = '';
						$testname = $all_tests[$testid]->te_name;
						foreach ($drugpars as $tdr) {
							if (isset($result_values[$tdr])) {
								$testdrug = $tdr;
								break;
							}
						}

						if (!empty($testdrug)) {
							$group_id = $all_tests[$testid]->te_group_id;
							if ($group_id > 0 && !in_array($group_id, $groupids) && isset($groups[$group_id])) {
								$panel_group_id = $group_id;
								if ($is_close_lab_export && $groups[$group_id]->tg_parent > 0 && isset($groups[$groups[$group_id]->tg_parent])) {
									$panel_group_id = $groups[$group_id]->tg_parent;
								}
								if (!in_array($panel_group_id, $groupids)) {
									$panel[] = $groups[$panel_group_id]->tg_name . '*';
									$groupids[] = $panel_group_id;
								}
								$groupids[] = $group_id;
								if (stripos($groups[$group_id]->tg_name, 'cov-') !== false) {
									$has_covid = true;
								}
							}

							list($pos_neg, $conc) = explode(';', $result_values[$testdrug]);
							if ($is_close_lab_export) {
								$testname .= ' ' . (isset($close_lab_result_labels[$pos_neg]) ? $close_lab_result_labels[$pos_neg] : $pos_neg);
							}
							if (!$is_close_lab_export && $settings['redtest_ins_report'] && in_array($pos_neg, array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVH'))) {
								$digi = $testdrug == 'ONCOSURE' ? 'G' : 'D';
								$panel[] = $testname . ' (' . $digi . ')';
							} else {
								$panel[] = $testname;
							}

							if (stripos($all_tests[$testid]->te_name, 'cov-') !== false) {
								$has_covid = true;
							}
						}
					}
				}
				$panel = implode(', ', $panel);
		} else if ($is_close_lab_export) {
			$panel_tests = array();
			foreach (explode(',', $panel) as $testname) {
				$testkey = strtoupper(trim(str_replace('+', '', $testname)));
				if (isset($result_values[$testkey])) {
					list($pos_neg, $conc) = explode(';', $result_values[$testkey]);
					$testname .= ' ' . (isset($close_lab_result_labels[$pos_neg]) ? $close_lab_result_labels[$pos_neg] : $pos_neg);
				}
				$panel_tests[] = trim($testname);
			}
			$panel = implode(', ', $panel_tests);
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
			if (!empty($row->approve_date)) {
				$row->approve_date = date('m/d/Y h:iA', strtotime($row->approve_date));
			}
			if (!empty($row->pa_dob)) {
				$row->pa_dob = date('m/d/Y', strtotime($row->pa_dob));
			}
		}

		$rowdata = array(
			$num++,
			$row->position,
			$row->resulted ? 'Yes' : 'No',
			$row->assen,
			$row->oldreq,
			$row->requesting_practice
		);

		$rowdata = array_merge($rowdata,  array(
			$row->pa_mrn,
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
			$row->approve_date,
			$row->lab,
			$row->lab_ref,
			$panel,
		));

		if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
			$rowdata[] = $row->ins_code;
		}

		$rowdata = array_merge($rowdata, array(
			$row->insurance,
			$row->ins_member_id,
			$row->ins_group_id,

		));

		if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) {
			$rowdata[] = $row->ins_code2;
		}

		$rowdata = array_merge($rowdata, array(
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
				$row->billing_notes,
				$row->internal_notes,
				isset($all_status[$row->batch_status]) ? $all_status[$row->batch_status] : $row->batch_status,
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

		if (!isset($mids_exported[$row->btype])) {
			$mids_exported[$row->btype] = array();
		}
		$mids_exported[$row->btype][] = $row->mid;
	}
	if ($is_close_lab_export_batch) {
		$ci_close_lab_export_processed = count($rows);
	}
	@fclose($output);
	if (!$is_close_lab_export && function_exists('trpdd_log_user')) {
		$data_log = [
			'type' => 'Insurance Reports',
			'file_name' => $filename,
			'total_records' => count($rows),
			'from_date' => $fromdate,
			'to_date' => $todate,
		];
		trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
	}
	if (isset($return_file)) {
		return $filename;
	}
	exit;
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




$filter_btypes = array();
	foreach ($report_req_types as $bty) {

	if ($bty == 'study') continue;

	if (
		(isset($show_menus_options['all']['form_' . $bty]) && $show_menus_options['all']['form_' . $bty])
		|| (isset($ci_active_menu['form_' . $bty]) && $ci_active_menu['form_' . $bty])
		|| in_array($current_role, array('Billing', 'BillbyClinic', 'AdminCIDW'))
	) {
		$filter_btypes[$bty] = tr_pdd_get_typename($bty);
	}
}
?>
<div class="wrap">
	<h2>Insurance Reports</h2>
	<?php Tr_Session_Class::show() ?>


	<div class="tablenav top">
		<form method="get" class="left alignleft">
			<input type="hidden" name="page" value="<?php echo isset($_GET['page']) ? $_GET['page'] : '' ?>" />
			<input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />

			<?php if ($is_ci_admin) { ?>
				<input type="text" name="s" value="<?php echo stripslashes(isset($_REQUEST['s']) ? $_REQUEST['s'] : '') ?>" placeholder="Patient Name/Accession" />
			<?php } ?>
			<label>From:</label>
			<input type="search" class="hasdate" name="fromdate" value="<?php echo $fromdate ?>" style="width:120px" placeholder="From Date" required />
			<label>To:</label>
			<input type="search" class="hasdate" name="todate" value="<?php echo $todate ?>" style="width:120px" placeholder="To Date" required />

			<?php if ($enable_ins_report_rsd) { ?>
				<select name="dateby">
					<?php
					foreach (array('drs' => 'by Result Date', 'doc' => 'by Service Date') as $k => $vl) {
						$selected = $k == $dateby ? 'selected' : '';
						echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
					}
					?>
				</select>
			<?php } ?>
			<?php if (count($labs) > 1) { ?>
				<select name="lab_reflab" class="fixsline">
					<option value="">Select Lab</option>
					<?php
					$include_ref_arr = !empty($settings['include_ref_labs']) ? explode(',', $settings['include_ref_labs']) : array();
					$exclude_ref_arr = !empty($settings['exclude_ref_labs']) ? explode(',', $settings['exclude_ref_labs']) : array();
					foreach ($labs as $lab) {
						if ($lab->lab_is_ref == 1) continue;

						$selected = isset($_REQUEST['lab_reflab']) && $_REQUEST['lab_reflab'] == $lab->lab_ID ? 'selected' : '';
						if (count($exclude_ref_arr) > 0 && in_array($lab->lab_ID, $exclude_ref_arr)) {
							//no thing now
						} else {
							echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_name . '</option>';
						}

						if (!$settings['exclude_ref_lab']) {
							foreach ($labs as $ref) {
								$labkey = $lab->lab_ID . '_' . $ref->lab_ID;

								if (!$ref->lab_is_ref || $ref->lab_ID == $lab->lab_ID) continue;
								if (count($exclude_ref_arr) > 0 && in_array($labkey, $exclude_ref_arr)) {
									continue;
								}
								if (count($include_ref_arr) > 0 && !in_array($ref->lab_ID, $include_ref_arr)) {
									continue;
								}
								$labkey = $lab->lab_ID . '_' . $ref->lab_ID;
								$selected = isset($_REQUEST['lab_reflab']) && $_REQUEST['lab_reflab'] == $labkey ? 'selected' : '';
								echo '<option value="' . $labkey . '" ' . $selected . '>' . $lab->lab_name . ' + Ref: ' . $ref->lab_dba_init . '</option>';
							}
						}
					}
					?>
				</select>
			<?php } ?>
			<?php
			if ($settings['enable_filter_clinic'] && isset($clinics)) {
			?>
				<select name="cli_ID" class="select2" data-placeholder="Select Clinic" style="width:200px">
					<option value="">All Clinic</option>
					<?php
					foreach ($clinics as $cli) {
						$selected = $cli->cID == $cli_ID ? 'selected' : '';
						echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
					}
					?>
				</select>
			<?php
			}
			?>

			<select name="paytype" class="fixsline">
				<?php
				echo '<option value="">All Pay Type</option>';
				foreach ($settings['paytypes'] + array('no' => 'No-Insurance') as $k => $txt) {
					if ((isset($settings['enable_cashpay']) && $settings['enable_cashpay']) && $k == 'cash') {
						//continue;
					}
					$selected = $k == $paytype ? 'selected' : '';
					echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
				}

				?>
			</select>
			<?php
			if (count($filter_btypes) > 1) {
			?>
				<select name="btype" class="fixsline">
					<?php
					echo '<option value="">Req Type</option>';
					foreach ($filter_btypes as $bty => $txt) {

						$selected = $bty == $btype_selected ? 'selected' : '';
						echo '<option value="' . $bty . '" ' . $selected . '>' . $txt . '</option>';
					}
					?>
				</select>
			<?php
			}
			?>

			<label><input type="checkbox" name="billing" value="1" <?php checked($is_billing) ?> /> Billing</label>
			<label><input type="checkbox" name="summary" value="1" <?php checked($is_summary) ?> /> Summary</label>

			<input type="submit" class="button bsty6" value="Search" />
			<input type="submit" class="button" name="trajax" value="Export" />
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

	<div class="wraptbl">
		<table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" style="width:<?php echo $is_billing ? '100%' : '5000px' ?>">
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
						<th>MRN</th>
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
						<?php if ($enable_stat) echo '<th>STAT</th>'; ?>
						<th style="min-width:250px">Req Panel Tests</th>
						<?php if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) { ?>
							<th>Insurance Code</th>
						<?php } ?>
						<th>Insurance</th>
						<th>Insurance ID</th>
						<th>Group Number</th>
						<?php if (isset($settings['enable_insurance_codes']) && $settings['enable_insurance_codes']) { ?>
							<th>Second Insurance Code</th>
						<?php } ?>
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
						<th style="min-width:120px">Billing Notes</th>
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
					<?php } ?>
				</tr>
			</thead>
			<tbody>
				<?php
				$count_rows = 0;

				foreach ($rows as $row) {
					$parent_name 	= '';
					$parent_dob 	= '';
					$has_covid 		= false;

					$row->doc_sp 		= '';
					$row->doc_sp_npi 	= '';
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
						$result_values[$arrtdr[0]] = isset($arrtdr[1]) ? $arrtdr[1] : '';
					}


					if ($row->ins_relation != 'Self' && !empty($row->ins_relation)) {
						$parent_name = $row->ins_last . ', ' . $row->ins_first;
						$parent_dob = $row->ins_dob;
					}
					/*
					if ($settings['enable_us_date_format']) {
						if (!empty($row->doc)) {
							$row->doc = date('m/d/Y h:iA', strtotime($row->doc));
						}
						if (!empty($row->spec_received)) {
							$row->spec_received = date('m/d/Y h:iA', strtotime($row->spec_received));
						}
						if (!empty($row->approve_date)) {
							$row->approve_date = date('m/d/Y h:iA', strtotime($row->approve_date));
						}
						if (!empty($row->pa_dob)) {
							$row->pa_dob = date('m/d/Y', strtotime($row->pa_dob));
						}
					}
						*/
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
	</div>
	<div class="tablenav bottom"><?php if (isset($pagination))
										echo $pagination->pagination() ?></div>
</div>

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
</style>


<script>
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
			var $html = '<select data-id="' + $(this).parent().data('id') + '" data-btype="' + $(this).parent().data('btype') + '">';
			for (k in $bill_statuses) {
				$html += '<option value="' + k + '" ' + (k == $(this).data('status') ? 'selected' : '') + '>' + $bill_statuses[k] + '</option>';
			}
			$html += '</select>';
			$(this).html($html);
		});

	})
</script>
