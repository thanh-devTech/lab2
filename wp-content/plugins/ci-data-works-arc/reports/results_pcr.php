<?php
/*
Name: Default
*/
global $wpdb, $ci_cutoffs, $report;
global $pdf_file_adding, $pdf_has_report;
global $rereun_by_both_rna_fail;
global $ci_abx_reports;
global $db_time_log;
global $pdf_header_text;


if (isset($_GET['debugabx'])) {
	$_REQUEST['style'] = $_GET['style'] = 3;
	$_REQUEST['html'] = $_GET['html'] = true;
}

$btype				= 'pcr';
$id             	=  isset($id) && $id > 0 ? $id : (isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
$bid                = isset($bid) ? $bid : 0;
$bid 				= isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;
$hide_patient_info  = isset($_REQUEST['hidepatient']) || get_user_meta(get_current_user_id(), '_hide_patientpdf', true);

$group_title        = tr_get_option('ci_pdf_data', 'group_head_pcr');
$hidescresultpcr    = tr_get_option('ci_pdf_data', 'hidescresultpcr');
$pcr_pdf_antigen    = tr_get_option('ci_pdf_data', 'pcr_pdf_antigen');
$pr_code            = tr_get_option('ci_pdf_data', 'pcr_proceduralcode');
$pr_code_antigen    = tr_get_option('ci_pdf_data', 'pcr_proceduralcode_antigen');
$pr_code_uti        = tr_get_option('ci_pdf_data', 'pcr_proceduralcode_uti');
$showdatetime       = tr_get_option('ci_pdf_data', 'results_dtime');
$report_green_neg   = tr_get_option('ci_pdf_data', 'report_green_neg');
$hide_patient_phone = tr_get_option('ci_pdf_data', 'pdf_hide_phone');
$show_patient_age 	= tr_get_option('ci_pdf_data', 'pdf_show_age');
$show_pid 			= tr_get_option('ci_pdf_data', 'show_patient_refid') || isset($_GET['showpid']);
$hide_patient_address = tr_get_option('ci_pdf_data', 'pdf_hide_address');
$enable_utinrow     = tr_get_option('ci_pdf_data', 'enable_utinrow');
$pdf_title          = tr_get_option('ci_pdf_data', 'pcr_pdf_title');
$style_num          = tr_get_option('ci_pdf_data', 'pcr_pdf_style', 1);

$req_settings   	= get_option('ci_req_settings_pcr', array());
$result_settings   	= get_option('ci_result_settings_pcr', array());

if (!function_exists('trpdd_pcr_show_specimen_source')) {
	function trpdd_pcr_show_specimen_source($report, $groupdata = array())
	{
		return tr_get_option('ci_req_settings_pcr', 'enable_panel_specimen_source')
			&& isset($groupdata['other_options']['e_specimen_source'])
			&& $groupdata['other_options']['e_specimen_source'];
	}
}

if (!function_exists('trpdd_pcr_get_specimen_source')) {
	function trpdd_pcr_get_specimen_source($report, $groupdata)
	{
		if (!trpdd_pcr_show_specimen_source($report, $groupdata)) {
			return '';
		}
		$specimen_sources = trpdd_get_batch_meta($report['mid'], 'pcr', 'specimen_source');
		if (!is_array($specimen_sources) || !isset($groupdata['id'])) {
			return '';
		}
		return isset($specimen_sources[$groupdata['id']]) ? $specimen_sources[$groupdata['id']] : '';
	}
}

if (!isset($result_settings['pos_moderate'])) {
	$result_settings['pos_moderate'] = false;
}
if (isset($_GET['teestpaname'])) {
	$report['patient_name'] .= 'ABC DDD eee';
}
$show_summary       = isset($result_settings['result_e_summary']) ? $result_settings['result_e_summary'] : false;
$is_rerun			= false;
$need_break_summary_page = false;
$positive_to_top    = false;
$has_antigen        = false;
$has_covid          = false;
$hide_Cycle_Threshold = tr_get_option('ci_pdf_data', 'pcr_pdf_hide_ct');
$hide_controls      = false;
$always_show_controls      = false;
$show_controls_negative = false;
$show_red_sample_type = false;
$hide_patient_box   = false;
$has_report         = false;
$sum                = true; //now live sum
$group_logo         = '';
$notes_pos_neg      = '';
$hide_text_Pathogen	= '';

if (empty($pdf_title)) {
	$pdf_title = 'Molecular PCR Summary Lab Report';
}

if (empty($group_title)) {
	$group_title = 'Test Performed';
}



$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos/';
$logo_url = $upload_dir['baseurl'] . '/logos/';

$mid = isset($_REQUEST['mid']) ? $_REQUEST['mid'] : (isset($mid) ? $mid : 0);

if (!$report) {
	$report = tr_pdd_get_report_by_id($id, 'pcr', $mid);
	$report['bold_text_positive'] = false;
}


if (!$report || empty($report['assen'])) {
	//echo 'Not found';
	return;
}
$report['panels_name'] = '';
$report['panels_code'] = '';



if ($id == 0) {
	$id = $report['id'];
}

if (
	stripos($report['physician'], ', doctor') !== false
	|| stripos($report['physician'], ', -') !== false
) {
	$report['physician'] = 'Self-Referral';
}
if (tr_get_option('ci_pdf_data', 'results_sp_adoctor') && !empty($report['doc_specialty'])) {
	$report['physician'] .= ' ' . $report['doc_specialty'];
}

// use header address directly from report

$patient_adress = trpdd_format_address($report['pa_address'], $report['pa_city'], $report['pa_state'], $report['pa_zip']);
//$patient_adress = strtolower($patient_adress);
//$patient_adress = ucwords($patient_adress);

$clinic_address = trpdd_format_address($report['cli_street'], $report['cli_city'], $report['cli_state'], $report['cli_zip']);
if ($report['cli_street'] == '.' || $report['cli_city'] == '.' || $report['cli_street'] == '-' || $report['cli_city'] == '-') {
	$clinic_address = trpdd_format_address($report['lab_address'], $report['lab_city'], $report['lab_state'], $report['lab_zip']);
}

if ($hide_patient_info) {
	$report['patient_name'] = '__';
	$report['patient_dob'] = '__';

	$hide_patient_phone = true;
	$hide_patient_address = true;
	$report['assen'] = 'XXXXXXX' . substr($report['assen'], -4, 4);
}
//parse panels

$has_manual_antibiotic  = false;
$has_manual2_antibiotic = false;
$has_pcr_results  		= false;
$except_covid           = false;
$all_tests              = array();
$show_location		 	= tr_get_option('ci_pdf_data', 'req_patient_location') && !empty($report['pa_location']);


$rs = $wpdb->get_results("select * from ci_test_panels where te_type='pcr' ");
foreach ($rs as $r) {
	$r->te_name = strtoupper($r->te_name);
	$all_tests[$r->te_name] = $r;
}
$reflex_main_tests = array();
if (!isset($is_demo_group)) {
	$reflex_main_tests = tr_pdd_check_group_reflex($report, 'pcr');
}

if (isset($reflex_main_tests['add_tests']) && count($reflex_main_tests['add_tests']) > 0) {
	$report['panel_tests_requested'] = explode(',', $report['panel_tests_requested']);
	$report['panel_tests_requested'] = array_merge($report['panel_tests_requested'], $reflex_main_tests['add_tests']);
	$report['panel_tests_requested'] = array_unique($report['panel_tests_requested']);
	$report['panel_tests_requested'] = implode(',', $report['panel_tests_requested']);
}
if (isset($reflex_main_tests['remove_tests']) && count($reflex_main_tests['remove_tests']) > 0) {
	$report['panel_tests_requested'] = explode(',', $report['panel_tests_requested']);
	$report['panel_tests_requested'] = array_diff($report['panel_tests_requested'], $reflex_main_tests['remove_tests']);
	$report['panel_tests_requested'] = implode(',', $report['panel_tests_requested']);
}

if (isset($_GET['debug'])) {
	echo '<br>Tests after reflex: ' . $report['panel_tests_requested'] . '<br><br>';
}
$in_panel_tests_requested = array();
$group_ids = array();
$group_ids_ordered = array();
foreach (explode(',', $report['panel_tests_requested']) as $t) {
	if (stripos($t, 'group') === false) {
		if ($t > 0) {
			$in_panel_tests_requested[] = $t;
		}
	} else {
		$gid = str_ireplace('group_', '', $t);
		$group_ids[] = $gid;
		$group_ids_ordered[] = $gid;
	}
}
if (empty($report['panel_tests_requested']) && empty($in_panel_tests_requested)) {
	$group_ids[] = -1;
}
$test_ids = implode(',', $in_panel_tests_requested);
if (empty($test_ids)) {
	$test_ids = '0';
}
$group_ids_str      = implode(',', $group_ids);

$group_ids_str_w_childs = $wpdb->get_col("select tg_ID from ci_test_groups 
	where tg_ID in ({$group_ids_str}) or tg_parent in ({$group_ids_str}) ");

$group_ids_str_w_childs = implode(',', $group_ids_str_w_childs);
if (empty($group_ids_str_w_childs)) {
	$group_ids_str_w_childs = '-1';
}

$group_ids_only_ornocheckboxes = $wpdb->get_col("select tg_ID from ci_test_groups 
	where (tg_ID in ({$group_ids_str}) or tg_parent in ({$group_ids_str}) )
	and (tg_only_group = 1 or tg_notestcheckbox= 1) ");

$group_ids_only_ornocheckboxes = implode(',', $group_ids_only_ornocheckboxes);
if (empty($group_ids_only_ornocheckboxes)) {
	$group_ids_only_ornocheckboxes = '-1';
}

if (isset($_GET['debug'])) {
	echo '<br><br>';
	echo $wpdb->last_query;
	echo '<br><br>$group_ids_str: ';
	var_dump($group_ids_str);
	echo '<br><br>';
}
$where_group        = '';
$more_query_test    = '';

if (count($group_ids) > 0) {
	$where_group .= " or ( tg_ID in ({$group_ids_only_ornocheckboxes}) )";
	$where_group .= " or ( tg_parent in ({$group_ids_only_ornocheckboxes}) )";

	$more_query_test .= " or (tp.te_nocheckbox=1 and tp.te_group_id in ({$group_ids_str_w_childs}))";
}


if (isset($req_settings['enabel_test_control_by']) && $req_settings['enabel_test_control_by']) {
	//add results for test
	$ctrl_tests = $wpdb->get_results(
		"select te_ID, te_name 
		from ci_test_panels tp
		left join ci_results_pcr rs ON (rs.drug = tp.te_name or rs.drug = tp.te_abrev) and rs.report_id = {$id}
		where te_type='pcr' and te_in_req = 1
		and (te_control_bys!='' and te_control_bys is not null) 
		and te_hide_pdf != 1
		and rs.id is null
		and (tp.te_ID in ({$test_ids}) 
		or tp.te_group_id in (select tg_ID from ci_test_groups where (tg_aw_results = 1 and tg_ID in ({$group_ids_str}))  {$where_group})
			{$more_query_test}
		)"
	);
	foreach ($ctrl_tests as $ctrtest) {
		$wpdb->insert('ci_results_pcr', array(
			'report_id' => $id,
			'drug' => strtoupper($ctrtest->te_name),
		));
	}
}


$wherehidetest = " and te_hide_pdf != 1 ";
if (isset($report['shownogrowth_note']) && $report['shownogrowth_note'] == '1') {
	$wherehidetest = '';
}


$drugkeys = array('te_abrev2', 'te_abrev', 'te_name');
$all_res = isset($pre_all_res) ? $pre_all_res : array();
foreach ($drugkeys as $drugkey) {
	$sql = "SELECT *
	FROM ci_results_pcr rs 
	join ci_test_panels tp ON tp.te_type='pcr' and  tp.{$drugkey} = rs.drug and rs.drug!=''
	WHERE report_id='{$id}' 
	{$wherehidetest}
	and (	tp.te_ID in ({$test_ids}) 
			or tp.te_group_id in (select tg_ID from ci_test_groups where (tg_aw_results = 1 and tg_ID in ({$group_ids_str})) {$where_group})
			{$more_query_test}
		)
	";

	$date_test = date('Y-m-d', strtotime($report['created']));

	$sql .= " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
	$sql .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";

	$all_results_q = $wpdb->get_results($sql, ARRAY_A);

	if (isset($_GET['debugq'])) {
		echo $wpdb->last_error;
		echo '<br><br><br>';
		echo ($sql);
		echo '<br><br><br>';
		var_dump($all_results_q);
		echo '<br><br><br>';
		echo '<br><br><br>';
	}

	foreach ($all_results_q as $arr) {
		if (isset($all_res[$arr['te_ID']])) {
			continue;
		}
		$arr['dbdrug'] = strtoupper($arr['drug']);
		$arr['drug'] = strtoupper($arr['te_name']);
		$all_res[$arr['te_ID']] = $arr;

		if (!in_array($arr['te_group_id'], $group_ids)) {
			$group_ids[] = $arr['te_group_id'];
		}
	}
}




$custom_added_Antibiotic 	= false;
$remove_crossed 			= false;
$found_root_groups 			= array();
$all_groups 				= array();
$reflex_pass 				= array();

$checked_types = explode(',', strtolower($report['sample_type']));
$drug_groups = tr_pdd_get_drug_groups('pcr', $group_ids, true);


//find Antibiotic Resistance
$antibiotic_resitance_id = 0;
foreach ($drug_groups as $groupid => $groupdata) {
	if ($groupdata['name'] == 'Antibiotic Resistance' || $groupdata['antibiotic']) {
		$antibiotic_resitance_id = $groupid;
		break;
	}
}
if ($antibiotic_resitance_id == 0) {
	//$drug_groups['Antibiotic Resistance'] = array('name' => 'Antibiotic Resistance', 'drugs' => array());
}


$report['groups'] = array();
foreach ($drug_groups as $groupid => $groupdata) {

	$all_groups[$groupid] = $groupdata['name'];

	if (!isset($drug_groups[$groupid]['subgroups'])) {
		$drug_groups[$groupid]['subgroups'] = array();
	}

	if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {
		$drug_groups[$groupdata['parent']]['subgroups'][$groupdata['id']] = $groupdata['name'];
	}

	if (!empty($groupdata['reflex_ID'])) {
		foreach ($groupdata['reflex_ID'] as $rlid) {
			if ($rlid > 0) {
				$reflex_pass[$rlid] = false;
			}
		}
	}

	if (!isset($drug_groups[$groupid]['has_sub'])) {
		$drug_groups[$groupid]['has_sub'] = false;
	}

	if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {

		if (isset($drug_groups[$groupdata['parent']])) {
			$drug_groups[$groupdata['parent']]['has_sub'] = true;
		}
	} else {

		if (isset($groupdata['pos_2_top']) && $groupdata['pos_2_top']) {
			$positive_to_top = true;
		}

		if (isset($groupdata['hide_phone_address']) && $groupdata['hide_phone_address']) {
			$hide_patient_phone = true;
			$hide_patient_address = true;
		}
	}
}

if (isset($_GET['debug334'])) {

	var_dump($reflex_pass);
	echo '<br>';
}

if (!$remove_crossed && date_i18n('Ymd') >= 20200201) {
	$remove_crossed = true;
}

foreach ($drug_groups as $groupid => $groupdata) {

	$has_pos = false;

	$groupdata['name'] 	= trim(str_ireplace('(new)', '', $groupdata['name']));

	if (!tr_get_option('ci_pdf_data', 'report_st_top')) {
		$group_types = array();
		if (isset($groupdata['types']) && is_array($groupdata['types']) && count($groupdata['types']) > 0) {
			foreach ($checked_types as $checked_type) {
				if (isset($groupdata['types'][$checked_type])) {
					$group_types[] = $groupdata['types'][$checked_type];
				}
			}
		}
		$group_types = array_unique($group_types);
		$groupdata['sample_type'] = implode(', ', $group_types);
	} else {
		$groupdata['sample_type'] = '';
	}


	$te_IDs = isset($groupdata['te_IDs']) && is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();

	if ($groupdata['name'] == 'Antibiotic Resistance') {
		foreach ($all_res as $te_ID => $arr) {
			if (isset($arr['antibiotic']) && $arr['antibiotic'] && !isset($arr['showed'])) {
				$te_IDs[] = $te_ID;
			}
		}
	}

	$groupdata['tests']     = array();
	$groupdata['top_tests'] = array();
	$groupdata['all_neg']	= true;

	if (isset($groupdata['manual_antibiotic']) && $groupdata['manual_antibiotic']) {
		$has_manual_antibiotic = true;
	}


	if (isset($groupdata['other_options']['mantibiotic']) && $groupdata['other_options']['mantibiotic']) {
		$has_manual2_antibiotic = true;
	}

	foreach ($te_IDs as $te_ID) {
		$arr = isset($all_res[$te_ID]) ? $all_res[$te_ID] : array();

		if ($groupdata['name'] == 'nCoV-2019' || $groupdata['is_covid']) {
			$has_covid = true;
			if (empty($arr)) {
				$arr = $wpdb->get_row("select te.* from ci_test_panels te
			            where te.te_ID = {$te_ID} and te_type='pcr' ", ARRAY_A);

				$arr['drug'] = ($arr['te_name']);
			}
			$arr['showed'] = true;

			$arr = trpdd_cal_ncovid19($arr, $id, $report['instrument'], $report['created_date']);

			if ($arr['pos-neg'] == 'PEN') {
				//unset($all_res[$te_ID]);
				//unset($drug_groups[$groupid]);
				//break;
			}
			$groupdata['tests'] = array();
			$groupdata['tests'][$arr['drug']] = $arr;

			if (isset($arr['notes']) && strlen($arr['notes']) > 2) {
				$notes_pos_neg = '<div class="' . $arr['te_abrev'] . '">' . $arr['notes'] . '</div>';
			}
			if (isset($arr['pdf2file']) && !empty($arr['pdf2file'])) {
				$report['pdf2file'] = $arr['pdf2file'];
			}

			$all_res[$te_ID] = $arr;
			$has_pcr_results = true;
			break;
		} else if (!empty($arr)) {

			if (!in_array($te_ID, $in_panel_tests_requested) && !$groupdata['only_group']) {
				//	continue;
			}

			if ($arr['te_inconclusive_ID'] === '-1') {
				unset($all_res[$te_ID]);
				continue;
			}
			$arr['showed'] = true;
			$arr['type_of_specimen'] = $report['type_of_specimen'];
			$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);
			if (($groupdata['parent'] > 0 || stripos($arr['drug'], 'Marker ') > 0)
				&& in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVL', 'PVH'))
			) {
				//$arr['pos-neg'] = 'POS';
			}


			$all_res[$te_ID] = $arr;
			$has_pcr_results = true;

			if (
				!in_array($groupdata['name'], array('Antibiotic Resistance', 'ABX'))
				&& !$groupdata['antibiotic']
				&& isset($arr['antibiotic']) && $arr['antibiotic']
			) {
				unset($all_res[$te_ID]['showed']);
				continue;
			}

			if (isset($arr['notes']) && strlen($arr['notes']) > 2) {
				$notes_pos_neg = '<div class="' . $arr['te_abrev'] . '">' . $arr['notes'] . '</div>';
			}


			if (in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVL', 'PVH'))) {
				$groupdata['all_neg'] = false;
				$has_pos = true;

				if (!empty($groupdata['reflex_ID'])) {
					foreach ($groupdata['reflex_ID'] as $rlid) {
						if ($rlid > 0  && isset($reflex_pass[$rlid])) {
							$reflex_pass[$rlid] = true;
						}
					}
				}

				if ($positive_to_top) {
					if (!isset($groupdata['top_tests'][$arr['pos-neg']])) {
						$groupdata['top_tests'][$arr['pos-neg']] = array();
					}
					$groupdata['top_tests'][$arr['pos-neg']][$arr['drug']] = $arr;
					continue;
				}
			}

			if (empty($arr['conc']) && empty($arr['pos-neg'])) {
				continue;
			}

			$groupdata['tests'][$arr['drug']] = $arr;
		}
	}

	if (count($groupdata['top_tests']) > 0) {
		if (isset($groupdata['top_tests']['POS'])) {
			$groupdata['tests'] = array_merge($groupdata['top_tests']['POS'], $groupdata['tests']);
		}
		if (isset($groupdata['top_tests']['POL'])) {
			$groupdata['tests'] = array_merge($groupdata['top_tests']['POL'], $groupdata['tests']);
		}
		if (isset($groupdata['top_tests']['POM'])) {
			$groupdata['tests'] = array_merge($groupdata['top_tests']['POM'], $groupdata['tests']);
		}
		if (isset($groupdata['top_tests']['POH'])) {
			$groupdata['tests'] = array_merge($groupdata['top_tests']['POH'], $groupdata['tests']);
		}
	}

	if (isset($groupdata['other_options']['groupnegs']) && $groupdata['other_options']['groupnegs'] && $groupdata['all_neg']) {
		$oldtests = $groupdata['tests'];
		$groupdata['tests'] = array();
		$testnames = '';
		$testkey = '';
		foreach ($oldtests as $arr) {
			if (empty($testkey)) {
				list($testkey) = explode('-', $arr['te_name']);
				$testnames = $testkey . '-' . str_replace($testkey . '-', '', $arr['te_name']);
			} else {
				$testnames .= '/' . str_replace($testkey . '-', '', $arr['te_name']);
			}

			$arr['te_name'] = $testnames;
			$groupdata['tests']['all'] = $arr;
		}
	}



	if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {
		$groupparentname = $all_groups[$groupdata['parent']];

		if (!empty($groupparentname)) {

			$groupdata['showed'] = true;

			$drug_groups[$groupdata['parent']]['subgroups'][$groupdata['id']] = $groupdata['name'];

			if (isset($_GET['debugsub'])) {
				echo '<br><br>grouppr: ';
				var_dump($groupdata['id']);
				var_dump($groupdata['name']);
				echo 'parent:';
				var_dump($groupdata['parent']);
				var_dump($groupparentname);
				echo '<br><br><br>';
				var_dump($drug_groups[$groupdata['parent']]['subgroups']);
				echo '<br><br>';
			}

			$abx_type = '';
			if (tr_get_option('ci_pdf_data', 'new_abx') && isset($drug_groups[$groupdata['parent']]['other_options']['abx_type']) && !empty($drug_groups[$groupdata['parent']]['other_options']['abx_type'])) {
				$abx_type = $drug_groups[$groupdata['parent']]['other_options']['abx_type'];
			}

			//check subgroups
			$has_gram_group = false;
			foreach ($drug_groups[$groupdata['parent']]['subgroups'] as $sgn) {
				if (stripos($sgn, 'Gram ') !== false || $sgn == 'Pathogens') {
					$has_gram_group = true;
					break;
				}
			}

			if ($groupparentname == 'STI') {
				$has_gram_group = false;
			}

			//remove old abx from 2025-04-01
			/*
			if (strtotime($report['doc']) < strtotime('2025-04-01') && ($has_gram_group || !empty($abx_type))) {
				if ((!isset($report['add_style']) || empty($report['add_style'])) && (!isset($report['checked_style3']) || !$report['checked_style3'])) {
					$uti_wou_key = '';

					if (!empty($abx_type)) {
						$uti_wou_key = $abx_type == 'uti' ? 'enable_utire' : 'enable_' . $abx_type . 'abx';
					} else if (stripos($groupparentname, 'wound') !== false) {
						$uti_wou_key = 'enable_woundabx';
					} else if (stripos($groupparentname, 'uti') !== false || stripos($groupparentname, 'Urinary Tract Infection') !== false) {
						$uti_wou_key = 'enable_utire';
					} else if (stripos($groupparentname, 'gi') !== false || stripos($groupparentname, 'Gastrointestinal') !== false) {
						$uti_wou_key = 'enable_giabx';
					} else if (stripos($groupparentname, 'Respiratory') !== false) {
						$uti_wou_key = 'enable_rabx';
					}


					$enablestyle3 = tr_get_option('ci_pdf_data', $uti_wou_key, false);
					if ($enablestyle3 && strtotime($report['doc']) >= strtotime('2025-04-01')) {
						$enablestyle3 = false; //disable old abx from now
					} else if ($enablestyle3) {
						$enable_date = tr_get_option('ci_pdf_data', $uti_wou_key . '_start');
						if (!empty($enable_date) && strtotime($enable_date) > strtotime($report['doc'])) {
							$enablestyle3 = false;
						}
					}

					$report['enablestyle3'] = $enablestyle3;

					if ($enablestyle3 && ($report['ref_lab_ID'] == 0 || ($report['ref_lab_ID'] > 0 && tr_get_option('ci_pdf_data', $uti_wou_key . '_ref_lab')))) {
						$report['add_style'] = 3;
						if (tr_get_option('ci_pdf_data', 'uti_bold_text')) {
							$report['bold_text_positive'] = true;
						}
					}
					$report['checked_style3'] = true;
				}
			}
				*/
		}
	} else {

		$report['groups'][$groupid] = $groupdata;

		$pname = !empty($groupdata['short_name']) ? $groupdata['short_name'] : $groupdata['name'];
		$pcode = !empty($groupdata['code']) ? $groupdata['code'] : $groupdata['short_name'];

		$report['panels_name'] = !empty($report['panels_name']) ? $report['panels_name'] . '-' . $pname : $pname;
		if (!empty($pcode)) {
			$report['panels_code'] = !empty($report['panels_code']) ? $report['panels_code'] . '-' . $pcode : $pcode;
		}
	}
	if (isset($_GET['dbtime'])) {

		$db_time_log .= 'group ' . $groupdata['name'] . ' count tests: ' . count($groupdata['tests']) . '<br>';
		$db_time_log .= 'group ' . $groupdata['name'] . ' count subgroups: ' . $groupdata['has_sub'] . '<br>';
	}

	if ((count($groupdata['tests']) > 0 || $groupdata['has_sub'])) {
		if ($groupdata['hide_ct']) {
			$hide_Cycle_Threshold = true;
		}

		if ($groupdata['parent'] == 0) {
			if (!in_array($groupdata['name'], $found_root_groups) && stripos($groupdata['name'], 'Antibiotic Resistance') === false) {
				$found_root_groups[] = $groupdata['name'];
			}

			if (!empty($groupdata['logo'])) {
				$group_logo = $groupdata['logo'];
			}
			if (empty($report['group_id'])) {
				$report['group_id'] = $groupdata['id'];
			}
		}


		if (!empty($groupdata['pdf_title'])) {
			$pdf_title = $groupdata['pdf_title'];
		}

		if ($groupdata['show_controls_pe'] || isset($_GET['dd23'])) {
			$remove_crossed = false;
		}

		if ($groupdata['remove_controls']) {
			$hide_controls = true;
		}
		if ((isset($groupdata['other_options']['tg_always_show_controls']) && $groupdata['other_options']['tg_always_show_controls'])) {
			$always_show_controls = true;
		}
		if ((isset($groupdata['other_options']['show_controls_neg']) && $groupdata['other_options']['show_controls_neg'])) {
			$show_controls_negative = true;
		}
		if ((isset($groupdata['other_options']['red_sample_type']) && $groupdata['other_options']['red_sample_type'])) {
			$show_red_sample_type = true;
		}
		if ((isset($groupdata['other_options']['controls_rmpa']) && $groupdata['other_options']['controls_rmpa'])) {
			$hide_text_Pathogen = true;
		}

		if (tr_get_option('ci_pdf_data', 'new_abx') && isset($groupdata['other_options']['new_abx']) && $groupdata['other_options']['new_abx'] && !$groupdata['antibiotic']) {

			if (!isset($ci_abx_reports[$report['id']]) || $ci_abx_reports[$report['id']] == false) {
				$ci_abx_reports[$report['id']] = true;
				if (empty($report['approve_date'])) {
					$report['approve_date'] = $report['doc'];
				}

				if (!isset($_GET['shownewabx']) && !empty($groupdata['other_options']['new_abx_date']) && strtotime($groupdata['other_options']['new_abx_date']) > strtotime($report['approve_date'])) {
					$ci_abx_reports[$report['id']] = false;
				} else {
					$report['add_style'] = 3;
					$report['checked_style3'] = true;
					$db_time_log .= 'add_style: 3<br>';
				}
				if (isset($_GET['debug334'])) {
					echo '$ci_abx_reports';
					var_dump($ci_abx_reports[$report['id']]);
					echo '<br>';
				}
			}
		}

		//get group style
		if ($groupdata['pdf_style'] > 0) {
			$style_num = $groupdata['pdf_style'];
		}

		if (isset($groupdata['other_options']['pdf_show_summary']) && $groupdata['other_options']['pdf_show_summary']) {
			$show_summary = true;
		}
	}

	if (tr_get_option('ci_result_settings_pcr', 'pos_at_top')) {
		uasort($groupdata['tests'], function ($a, $b) {
			$pos_neg_order = array(
				'PVH' => 1,
				'POH' => 2,
				'POM' => 3,
				'POL' => 4,
				'POS' => 5,
				'PVL' => 6,
				'NEG' => 7,
				'' => 8
			);
			$a_order = isset($pos_neg_order[$a['pos-neg']]) ? $pos_neg_order[$a['pos-neg']] : 9;
			$b_order = isset($pos_neg_order[$b['pos-neg']]) ? $pos_neg_order[$b['pos-neg']] : 9;
			return $a_order - $b_order;
		});
	}

	$drug_groups[$groupid] = $groupdata;
}

//remove reflex
if (isset($_GET['debug334'])) {
	echo '$reflex_pass';
	var_dump($reflex_pass);
	echo '<br>';
}
if (count($reflex_pass) > 0) {
	foreach ($reflex_pass as $gid => $refpass) {
		if (!$refpass) {
			foreach ($drug_groups as $groupid => $groupdata) {
				if ($groupdata['id'] == $gid && $groupdata['antibiotic']) {

					unset($drug_groups[$groupid]);
					if ($groupdata['parent'] > 0) {
						unset($drug_groups[$groupdata['parent']]['subgroups'][$gid]);
					}
					break;
				}
			}
		}
	}
}



if (isset($result_settings['e_reruntest']) && $result_settings['e_reruntest']) {

	$is_rerun = $is_rerun || tr_pdd_check_rerun('pcr', $report);
}

if ($is_rerun && !$rereun_by_both_rna_fail && isset($result_settings['e_rerunttoinvalid']) && $result_settings['e_rerunttoinvalid']) {
	foreach ($drug_groups as $groupid => $groupdata) {
		foreach ($groupdata['tests'] as $tdr => $arr) {
			if (!$arr['te_is_control']) {
				$arr['pos-neg'] = 'INV';

				$groupdata['tests'][$tdr] = $arr;
			}
		}
		$drug_groups[$groupid] = $groupdata;
	}
}



if (count($found_root_groups) == 1) {
	$one_group_name = $found_root_groups[0];

	if ($pcr_pdf_antigen && stripos($one_group_name, 'Antigen') !== false) {
		$pdf_title     = $one_group_name . ' Report';
		$title2 = tr_get_option('ci_pdf_data', 'pcr_pdf_antigentext');
		if (!empty($title2)) {
			$pdf_title = $title2;
		}
		$hide_controls = true;
		$has_antigen    = true;
		if (strlen($pr_code_antigen) > 0) {
			$pr_code = $pr_code_antigen;
		}
	} else if (stripos($one_group_name, 'UTI') !== false) {
		if (strlen($pr_code_uti) > 0) {
			$pr_code = $pr_code_uti;
		}
	} else if (stripos($one_group_name, 'expedited') !== false) {
		$hide_controls = true;
	}

	if (stripos($one_group_name, 'SARS-COV-2 PCR TEST EXPEDITED') !== false) {
		$hide_Cycle_Threshold = true;
	} else if (stripos($one_group_name, 'Express 1hr PCR') !== false || stripos($one_group_name, 'Express PCR') !== false) {
		$hide_Cycle_Threshold = true;
	}
}



if ($has_antigen) {
	$hide_Cycle_Threshold = true;
}

$pos_neg = array(
	'' => '',
	'NEG' => 'Negative',
	'POS' => 'Positive',
	'PVL' => 'Detected-Very Low',
	'POL' => 'Detected-Low',
	'POM' => $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium',
	'POH' => 'Detected-High',
	'PVH' => 'Detected-Very High',
	'PEN' => 'Pending',
	'INC' => 'Inconclusive',
	'PRP' => 'Positive',
	'INV' => 'Invalid',
	'NGE' => 'Negative',
	'RER' => 'ReRun',
	'EQU' => 'Equivocal',
	'N/A' => 'N/A',
	'IND' => 'Indeterminate',
);
$no_collection_date = (empty($report['collection_date']) || $report['collection_date'] == '0000-00-00' || $report['collection_date'] == '0000-00-00 00:00:00') && $report['doc'] == '0000-00-00';
$notes = get_option('ci_notes_result_pcr');


$text_PRELIMINARY = tr_pdd_get_result_status($report['final_results'], true);
$final_adding_font = '';
if (isset($result_settings['final_bold']) && $result_settings['final_bold']) {
	$final_adding_font .= 'font-weight:bold;text-transform: uppercase;';
}
if (count($drug_groups) == 0) {
	$has_report = false;
	$pdf_has_report = false;
}

if (isset($_REQUEST['debug']) && isset($_REQUEST['html'])) {
	var_export($report);
	echo '<br><br>';
	echo '<br><br>Tests:';
	var_export($in_panel_tests_requested);
	echo '<br><br>';
	//echo '$all_res:';var_export($all_res);echo '<br><br>';
	echo '$group_ids: ';
	var_dump($group_ids);
	echo '<br><br>';
	echo '$drug_groups: ';
	var_dump($drug_groups);
	echo '<br><br>';

	echo '<br><br>foundgroups: ';
	var_dump($found_root_groups);
	echo '<br><br>$all_groups: ';
	var_dump($all_groups);
	var_dump($report['bold_text_positive']);
	echo '<br><br>';
	echo '$group_ids_only_ornocheckboxes:';
	var_dump($group_ids_only_ornocheckboxes);
	echo '<br><br>';
}

if ($report['cli_result_style_pcr'] > 1) {
	$style_num = $report['cli_result_style_pcr'];
	if ($style_num == 5 && !empty($result_settings['start_style_5'])) {
		if (strtotime($report['doc']) < strtotime($result_settings['start_style_5'])) {
			$style_num = 1;
		}
	}
}

if (isset($_GET['style']) && !empty($_GET['style'])) {
	$style_num = $_GET['style'];
	if ($style_num == 'debug') {
		include(TRCPDD_PATH . 'reports/results_debug.php');
		return;
	}
}

if (isset($_GET['html'])) {
	echo '<div>style: ' . $style_num . '</div>';
}

if (isset($_GET['dbtime'])) {
	$db_time_log .= '#1 style: ' . $style_num . '<br>';
}

if ($style_num > 1) {
	$filepath = TRCPDD_PATH . 'reports/results_pcr_' . $style_num . '.php';
	if (file_exists($filepath)) {
		if (isset($_GET['debug'])) {
			echo '<h3>Style #' . $style_num . '</h3>';
		}
		if ($report['add_style'] == $style_num) {
			$report['add_style'] = '';
		}
		include($filepath);
		return;
	}
}

$margin_top = 5.4;

if (empty($text_PRELIMINARY)) {
	$margin_top = 5.2;
}
if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) {
	$margin_top += 0.5;
}
if (tr_get_option('ci_pdf_data', 'pcr_patient_fvaccine') && !empty($report['pa_fully_vaccinated'])) {
	$margin_top += 0.5;
}
if (strlen($report['dagnostic_codes1']) > 30 && $margin_top <= 6) {
	$margin_top += 0.5;
}
if (strlen($report['patient_name']) > 40 && $margin_top <= 6) {
	$margin_top += 0.25;
	if (strlen($report['patient_name']) > 60) {
		$margin_top += 0.25;
	}
}

$pdf_notes = trpdd_get_pdf_note($report, 'pcr');

if (isset($_GET['ddd2'])) {
	$has_report = true;
	//$hide_controls = true;
	$show_controls_negative = true;

	$hide_patient_box = true;
}

/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */


$footer_height = 1.4;
ob_start();
?>

<div id="footer">
	<?php
	$footer_text = trpdd_get_footer_text($report, 'pcr');


	$director_signature = '';
	if (!empty($report['lab_director_signature'])) {
		$sign_dir = tr_get_signature_dir();
		$director_signature_url = $sign_dir['path'] . '/' . $report['lab_director_signature'];
		$director_signature = '<img src="' . $director_signature_url . '" style="height:20px">';
		if ($footer_height < 2) {
			$footer_height = 2.2;
		}
	}

	$footer_text = str_replace('[signature]', $director_signature, $footer_text);

	if (preg_match("/height\s?:\s?([0-9]+)px/", $footer_text, $match_footer_height)) {

		$new_footer_height = ($match_footer_height[1] + 10) * 0.0261;
		if ($new_footer_height > $footer_height) {
			$footer_height = $new_footer_height;
		}
	}

	$footer_text = str_replace('<br>', '<br />', $footer_text);
	if ((strlen($footer_text) > 300 || substr_count($footer_text, '<br />') >= 3) && $footer_height < 3) {
		$footer_height += substr_count($footer_text, '<br />') * 0.4;
	}

	echo $footer_text;
	?>
</div>

<?php
$footer_html = ob_get_clean();

$margin_bottom = 0.3;
$rotate_margin_left = -14;
$rotate_margin_top = 150;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
	$margin_bottom = $footer_height;
	$footer_height = 0.3;
	$rotate_margin_top = 80;
	$rotate_margin_left = -1;
}
?>
<!DOCTYPE html>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta id="final" content="<?php echo $report['final_results'] ?>" />
	<title><?php echo $report['assen'] ?></title>
	<style>
		@page {
			margin: 0.5cm 0.5cm <?php echo $footer_height ?>cm 0.5cm;
		}

		body {
			margin-top: <?php echo $margin_top ?>cm;
			margin-bottom: <?php echo $margin_bottom ?>cm;
		}

		break,
		.break {
			page-break-after: always;
			border: 0;
		}

		* {
			font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
			font-size: 10px;
			line-height: 10px;
		}

		.font8 {
			font-size: 8pt;
		}

		table .sample_type {
			font-size: 7pt;
			text-transform: none;
			font-weight: normal;
		}

		table .sample_type.stype1,
		table .sample_type.stype2,
		table .sample_type.stype3,
		table .sample_type.stype4 {
			display: block;
			float: right;
			text-align: right;
		}

		hr {
			height: 1px;
			padding: 0;
			margin: 1em 0;
			border: 0;
			border-top: 1px solid #CCC;
		}

		br {
			line-height: 5px;
		}

		table {
			width: 100%;
			border-spacing: 0px;
			page-break-inside: auto;
			border-collapse: collapse;
		}

		table th {
			text-align: left;
			padding: 5px;
		}

		table td {
			padding: 5px;
			vertical-align: top
		}

		table.nopadding td {
			padding: 0
		}

		table.border {
			border: 1px solid black;
			border-spacing: 0px;
			width: 100%;
		}

		table.tdborder td,
		table.tdborder th {
			border: 1px solid #aaa;
		}

		table.border2.border.tdborder {
			border-top: 1px solid black;
			border-left: 1px solid black;
			border-right: 2px solid black;
			border-bottom: 2px solid #000
		}

		table.noborder td,
		table.noborder th {
			border: 0 !important
		}

		.table {
			margin-top: 0pt;
			margin-bottom: 0pt;
		}

		.tableCenter {
			text-align: center;
			margin-top: 0pt;
			margin-bottom: 0pt;
		}

		.header {
			margin-top: 10pt;
			margin-bottom: 10pt;
		}

		.center {
			text-align: center
		}

		.upper {
			text-transform: uppercase;
		}

		.in_medications {
			background: #92ccff
		}

		.bg_red {
			background: #ff9495
		}

		table tr.headernoborder th {
			border: 3px solid #000;
			border-left: 0;
			border-right: 0;
			font-weight: bold;
			font-size: 12px;
			padding-top: 0px;
			padding-bottom: 5px
		}

		table.noboder,
		table tr.noborder td {
			border: none;
		}

		table .font_blue td {
			color: blue
		}

		h4,
		.h4 {
			font-size: 12px;
			margin: 0;
			padding: 0;
			line-height: 12px;
			font-weight: bold
		}

		h5 {
			font-weight: normal;
			font-size: 12px;
			color: <?php echo $report['color'] ?>;
			margin: 10px 0 0 0
		}

		.right {
			text-align: right;
		}

		.right_pre {
			margin-top: -3px;
			color: red;
			text-align: right;
			<?php echo $final_adding_font ?>
		}

		.blue {
			color: blue
		}

		.red,
		.rowred td,
		tr td.rowred {
			color: red
		}

		.rowbold td,
		tr td.rowbold {
			font-weight: bold;
		}

		.rowneg .posneg {
			color: darkgreen;
		}

		.small_table th,
		table th.small,
		.small_table td,
		table td.small {
			font-size: 8pt;
			padding: 1.7px 2px;
			line-height: 10px;
		}

		.smallheader th {
			font-size: 8pt;
			line-height: 10px;
		}

		table th.small {
			font-size: 6pt;
			line-height: 8px;
			padding: 1px 2px;
		}

		.small_padding_table th,
		.small_padding_table td {
			padding: 2px;
			color: #000;
		}

		.small_padding_table h5 {
			margin-top: 3px;
		}

		.address_right div {
			font-size: 10px;
			line-height: 9px;
		}

		table tr th.header_black {
			background: #444;
			color: #fff;
			padding: 4px;
		}

		table tr th.header_none {
			border-top: none;
			border-right: none
		}

		<?php if (!isset($_REQUEST['debug'])) : ?>#header,
		#footer {
			position: fixed;
			left: 0;
			right: 0;
			color: #666;
			font-size: 0.9em;
		}

		<?php endif; ?>#header {
			top: 0;
		}

		#footer {
			bottom: 5px;
			border-top: 0.1pt solid #aaa;
			padding-top: 5px;
		}

		#footer,
		#footer * {
			font-size: 10px;
			line-height: 12px;
			margin: 0;
		}

		.header_tbl th {
			text-transform: uppercase;
			background: #555;
			border-color: #555 !important;
			text-align: center;
			height: 16px;
			color: #fff;
		}

		.title_tbl th {
			text-align: center;
			border-bottom: 1px solid #ddd;
			height: 15px;
			background: #ddd;
			color: #333;
			padding-top: 0;
			padding-bottom: 0;
		}

		tr th.title_tbl {
			background: #eee;
		}

		.smalltext {
			font-size: 9px;
		}

		.istyle {
			padding-top: 3px;
			font-size: 10px;
			line-height: 12px !important;
			font-style: italic;
		}

		body table th.rotate {
			text-align: left !important;
			min-width: 30px !important;
			position: relative;
			height: 160px;
			vertical-align: bottom;
		}

		body table th.rotate.labrsrotate {
			height: auto;
		}

		.rotate span {
			display: none;
		}

		body .rotate:after {
			left: 50%;
			text-align: left;
			margin-left: -12px;
			white-space: nowrap;
			margin-left: <?php echo $rotate_margin_left ?>px;
			margin-top: <?php echo $rotate_margin_top ?>px;
			content: attr(title);
			position: absolute;
			-webkit-transform: translateX(-50%) translateY(-50%) rotate(-90deg);
			transform: translateX(-50%) translateY(-50%) rotate(-90deg);
		}

		body .rotate.labrsrotate:after {
			margin-top: 185px;
		}

		.antibiotic_tbl td.vl_R,
		.antibiotic_tbl td.vlmix_X {
			background: #ff3d35 !important;
			color: #fff !important;
		}

		.antibiotic_tbl td.vl_I,
		.antibiotic_tbl td.vlmix_V {
			background: #d6d6d6 !important;
		}

		body tr.thicklineabove td {
			border-top: 2px solid #000;
		}

		.controls sup,
		.resultcol sup {
			font-size: 9px;
			font-weight: normal;
		}

		.controls td td {
			line-height: 12px;
		}

		table td.bg_col {
			background: #f4f4f4;
		}

		table.split_tbl {
			border: none !important;
		}

		table.split_tbl .title_tbl th {
			border-bottom: none;
		}

		.space_cell {
			border-bottom: none !important;
			border-top: none !important;
		}

		.bottom_notes {
			padding-top: 8px;
		}

		.bottom_notes * {
			font-size: 8pt;
		}

		.patientblocktbl td {
			line-height: 10px;
			padding: 1px;
		}

		table.comment_next_row tr td {
			border-color: #ddd;
			border-left: 0;
			border-right: 0;
		}

		table.comment_next_row tr th {
			border: none;
		}

		table tr.comment_at_bottom td {
			border-bottom: 0;
		}

		table.comment_next_row td.pos_neg {
			font-weight: bold;
		}

		table.comment_next_row td.conc {
			background: #fbfbfb;
		}

		table.border.comment_next_row {
			border: none;
		}

		<?php
		if ($show_red_sample_type) {
			echo '.sample_type{color:red}';
		}
		if (tr_get_option('ci_pdf_data', 'pdf_bold_assen')) {
			echo '.assen_number{font-weight:bold;font-size:9pt !important}';
		}
		?>
	</style>
</head>

<body>

	<div id="header">
		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:25%;height:60px;" valign="top">
					<?php
					$logo_path = $logo_dir . $report['lab_logo'];
					$logo_main = $report['lab_logo'];
					if (!empty($notes['pdf_logo'])) {
						$logo_main = $notes['pdf_logo'];
					}
					if (!empty($group_logo)) {
						$logo_main = $group_logo;
					}


					$logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

					$has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
					if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
						$logo_main = $logo_second;
					}

					if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {

						$max_width = 200;
						$max_height = 70;
						$logo_path = $logo_dir . $logo_main;
						//$logo_path = trpdd_get_resized_image($logo_path, $max_width+60, $max_height+60);
					?>
						<img border="0" alt="Logo" style="max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_path ?>" />
					<?php
					}

					?>

				</td>
				<td style="width:44%;text-align: center">
					<h2 style="color:<?php echo $report['color'] ?>;font-size:17px;font-weight: normal;line-height: 18px;"><?php echo $pdf_title ?></h2>
				</td>
				<td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">

					<div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
					<?php if (!empty($pdf_header_text)) {
						echo apply_filters('the_content', $pdf_header_text);
					} else { ?>
						<div><?php echo $report['header_lab_name'] ?></div>
						<div><?php echo $report['header_lab_address'] ?></div>
					<?php
						if (!empty($report['header_lab_second_line_hpdf'])) {
							echo '<div>' . $report['header_lab_second_line_hpdf'] . '</div>';
						}
						if (!empty($report['header_lab_phone'])  && !tr_get_option('ci_pdf_data', 'report_hilaphone')) {
							echo '<div>Phone: ' . $report['header_lab_phone'] . '</div>';
						}
						if (!empty($report['header_lab_clia'])) {
							echo '<div>CLIA ID: ' . $report['header_lab_clia'] . '</div>';
						}
					}
					?>
				</td>
			</tr>
		</table>

		<table class="small_padding_table" style="border-top:3px solid <?php echo $report['color'] ?>;border-bottom:3px solid <?php echo $report['color'] ?>">
			<tr>
				<td style="width:30%">
					<h5>CLINIC INFORMATION</h5>
				</td>
				<td style="width:39%">
					<h5>PATIENT INFORMATION</h5>
				</td>
				<td>
					<h5>SPECIMEN INFORMATION</h5>
				</td>
			</tr>
			<tr>
				<td style="border-right:1px solid #ddd">
					<table class="noboder small_table patientblocktbl">
						<tr>
							<td style="width:23%">Name:</td>
							<td><?php echo $report['requesting_practice'] ?></td>
						</tr>
						<tr>
							<td>Address:</td>
							<td><?php echo $clinic_address ?></td>
						</tr>
						<?php
						if (tr_get_option('ci_pdf_data', 'pdf_show_clinic_phone') && !empty($report['cli_om-phone'])) {
						?>
							<tr>
								<td>Phone:</td>
								<td><?php echo htmlspecialchars($report['cli_om-phone']) ?></td>
							</tr>
						<?php
						}
						?>
						<tr>
							<td>Provider:</td>
							<td><?php echo htmlspecialchars($report['physician']) ?></td>
						</tr>
						<?php
						if (tr_get_option('ci_pdf_data', 'pdf_show_npi') && !empty($report['doc_npi'])) {
						?>
							<tr>
								<td>NPI:</td>
								<td><?php echo htmlspecialchars($report['doc_npi']) ?></td>
							</tr>
						<?php
						}
						?>

					</table>
				</td>
				<td style="border-right:1px solid #ddd;padding-left:10px;">
					<table class="noboder small_table patientblocktbl">
						<tr>
							<td style="width:20%">Name:</td>
							<td class="patientname" colspan="<?php echo $show_pid ? 1 : 2; ?>" style="max-width:200px;word-wrap:break-word">
								<?php echo tr_pdd_capitalized($report['patient_name']); ?>
							</td>
							<?php
							if ($show_pid) { ?>
								<td style="width:90px">ID: <?php echo htmlspecialchars($report['pa_mrn']) ?></td>
							<?php } ?>
						</tr>
						<tr>
							<td>DOB:</td>
							<td colspan="<?php echo $show_patient_age ? 1 : 2; ?>">
								<?php echo tr_pdd_display_date($report['patient_dob']) ?>
							</td>
							<?php if ($show_patient_age) { ?>
								<td style="width:90px">
									<span>Age:</span>
									<span><?php echo trdd_dob2ages($report['patient_dob'], $report['doc']) ?></span>
								</td>
							<?php } ?>
						</tr>
						<?php
						if (!$hide_patient_phone) { ?>
							<tr>
								<td>Phone:</td>
								<td>
									<?php echo $report['pa_phone'] ?>
								</td>
								<td style="width:90px">
									<span>Gender:</span>
									<span><?php echo htmlspecialchars($report['pa_gender']) ?></span>
								</td>
							</tr>
						<?php
						} else {
						?>
							<tr>
								<td>Gender:</td>
								<td colspan="2">
									<?php echo htmlspecialchars($report['pa_gender']) ?>
								</td>
							</tr>
						<?php
						}

						if (!$hide_patient_address) { ?>
							<tr>
								<td>Address:</td>
								<td colspan="2" class="smalltext"><?php echo $patient_adress ?></td>
							</tr>
						<?php } ?>
						<?php
						if (tr_get_option('ci_pdf_data', 'pcr_patient_fvaccine') && !empty($report['pa_fully_vaccinated'])) {
						?>
							<tr>
								<td colspan="3">Vaccination Status: <?php echo $report['pa_fully_vaccinated'] == 'yes' ? 'Yes' : 'No' ?></td>
							</tr>
						<?php
						}
						if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) { ?>
							<tr>
								<td>Passport:</td>
								<td colspan="2"><?php echo htmlspecialchars($report['pa_passport']) ?></td>
							</tr>
						<?php }

						if (tr_get_option('ci_pdf_data', 'show_pa_mrnasid') && !empty($report['pa_mrn'])) { ?>
							<tr>
								<td>Patient ID:</td>
								<td colspan="2"><?php echo htmlspecialchars($report['pa_mrn']) ?></td>
							</tr>
						<?php }

						if ($show_location && !empty($report['pa_location'])) { ?>
							<tr>
								<td>Loc:</td>
								<td colspan="2"><?php echo htmlspecialchars($report['pa_location']) ?></td>
							</tr>
						<?php } ?>
					</table>
				</td>
				<td style="padding-left:10px;">
					<table class="noboder small_table patientblocktbl" style="margin-bottom:1px;">
						<tr>
							<td style="width:42%"><?php echo isset($result_settings['assentxt']) && $result_settings['assentxt'] ? $result_settings['assentxt'] : 'Accession' ?>:</td>
							<td class="assen_number"><?php echo $report['assen'] ?></td>
						</tr>
						<?php
						if (!empty($report['oldreq']) && tr_get_option('ci_pdf_data', 'pcrpdf_show_ref')) {
						?>
							<tr>
								<td>Order:</td>
								<td class="oldreq">
									<?php echo $report['oldreq'] ?>
								</td>
							</tr>
						<?php
						}
						?>
						<tr>
							<td>Date Collected:</td>
							<td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<tr>
							<td>Date Received:</td>
							<td><?php echo tr_pdd_display_date($report['spec_received'], 'm/d/Y H:i', false, true) ?></td>
						</tr>
						<tr>
							<td>Date Reported:</td>
							<td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
						</tr>
						<?php
						if (tr_get_option('ci_pdf_data', 'report_st_top')) {
						?>
							<tr>
								<?php if (strlen($report['sample_type']) < 50) {
									echo '<td>Specimen Type:</td>';
								} ?>
								<td <?php if (strlen($report['sample_type']) >= 50) echo 'colspan="2" style="font-size:8px"' ?>><?php echo $report['sample_type'] ?></td>
							</tr>
						<?php
						}
						?>
						<?php
						if (!empty($report['dagnostic_codes1']) && tr_get_option('ci_pdf_data', 'pcrpdf_show_diagnostic')) {
						?>
							<tr>
								<td>Diagnostic code:</td>
								<td style="line-height:8px;<?php if (strlen($report['dagnostic_codes1']) > 30) echo 'font-size:9px' ?>">
									<?php echo str_replace(',', ', ', $report['dagnostic_codes1']) ?>
								</td>
							</tr>
						<?php
						}

						if (!empty($pr_code) && $pr_code !== '0') {
							//87635, 99211 for Vir
						?>
							<tr>
								<td>Procedural codes:</td>
								<td><?php echo $pr_code ?></td>
							</tr>
						<?php
						}
						if (tr_get_option('ci_pdf_data', 'show_ehr')) {
						?>
							<tr>
								<td>EHR:</td>
								<td><?php echo $report['p_ehr'] ?></td>
							</tr>
						<?php
						}
						?>
					</table>
				</td>
			</tr>
		</table>

	</div>
	<?php
	if (!isset($_REQUEST['debug']) && !isset($use_footer_file)) {
		echo stripslashes($footer_html);
	}

	if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
		include('rejected_default.php');
	} else {

		include('pcr_main.php');
	} //end if rejected


	include('_approved_by.php');

	if (!empty($notes_pos_neg)) {
		echo '<div class="bottom_notes">';
		echo trpdd_filter_comments_in_pdf($notes_pos_neg);
		echo '</div>';
	}

	if (!empty($report['notes_report'])) {
		echo '<div style="padding-top:6px;font-size:10px;line-height:12px" class="notes_report">';
		echo trpdd_filter_comments_in_pdf($report['notes_report']);
		echo '</div>';
	}

	if (!empty($notes['result_imagenote'])) {

		echo '<div style="text-align:center">';
		echo '<img src="' . $logo_dir . '/' . $notes['result_imagenote'] . '" style="width:auto;margin:0 auto;height:auto"/>';
		echo '</div>';
	}

	if (!isset($notes['onlyscovid'])) {
		$notes['onlyscovid'] = 0;
	}
	if (!empty($notes['result_image']) && (!@$notes['onlyscovid'] || $has_covid)) {

		echo '<div class="break"></div>';
		echo '<div style="background-color: white; z-index: 2;position:fixed;height:1200px">';
		echo '<img src="' . $logo_dir . '/' . $notes['result_image'] . '" style="width:100%;margin:0 auto;height:auto"/>';
		echo '</div>';
	}

	if (isset($_REQUEST['debug'])) {
		echo stripslashes($footer_html);
	}


	?>


	<?php include('_pdf_pagination.php'); ?>

</body>

</html>