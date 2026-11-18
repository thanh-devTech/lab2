<?php
/*
Name: New ABX report
*/
global $wpdb;
global $abx_v_style;
global $asset_url;
global $_ci_override_pregnancy;
global $_ci_override_renal;
global $_ci_exclude_antibiotic_IDs;
global $new_abx_v_amr;
global $all_markers;
global $pos_markers;




$has_style 		= false;
$asset_url 	    = isset($_GET['html']) ? TRCPDD_URL : TRCPDD_PATH;
$id             =  isset($id) && $id != 0 ? $id : (isset($_REQUEST['id']) ? $_REQUEST['id'] : 0);
$count_errors	= isset($count_errors) ? $count_errors : 0;
$all_markers 	= array();
$pos_markers 	= array();
$abx_adding_html = '';

$reflex_pass 		= array();
$abx_style 			= 1;
$abx_v_style 		= 1;
$export_es_ls		= isset($_GET['export_es_ls']) ? true : false;
if ($export_es_ls) {
	$_GET['debugabx'] = $_REQUEST['debugabx'] = false;
}

if (isset($_GET['abx2']) || tr_get_option('ci_pdf_data', 'new_abx_style', 1) == 2) {
	$abx_style = 2;
}

if (isset($_GET['abxv2']) || tr_get_option('ci_pdf_data', 'new_abx_v_style', 1) == 2) {
	$abx_v_style = 2;
}


if (isset($_GET['abx1'])) {
	$abx_style = 1;
}

$use_piechart 	= (isset($use_piechart) && $use_piechart) || tr_get_option('ci_pdf_data', 'new_abx_pie');
$use_biofilm   	= isset($_REQUEST['biofilm']) || tr_get_option('ci_pdf_data', 'new_abx_biofilm');
$pregnancy_uti  = isset($_REQUEST['pregnancy_uti']) || tr_get_option('ci_pdf_data', 'new_abx_pregnancy_uti');
$pregnancy_uti_by  = isset($_REQUEST['pregnancy_by']) || tr_get_option('ci_pdf_data', 'new_abx_pregnancy_by');
$new_abx_v_amr  = isset($_REQUEST['vamr']) || tr_get_option('ci_pdf_data', 'new_abx_v_amr');
$pregnancy_by_panel = false;
$abx_renal_aoe 		= false;


$pdf_title = 'PCR Results Analysis';
$pdf_subtitle  = '';

if (!is_array($report) || ($id > 0 && $id != $report['id'])) {
	$report = tr_pdd_get_report_by_id($id, 'pcr', $mid);

	$patient_adress = trpdd_format_address($report['pa_address'], $report['pa_city'], $report['pa_state'], $report['pa_zip']);
	$clinic_address = trpdd_format_address($report['cli_street'], $report['cli_city'], $report['cli_state'], $report['cli_zip']);
}
//parse panels

$reflex_main_tests = tr_pdd_check_group_reflex($report, 'pcr');
if (isset($reflex_main_tests['add_tests']) && count($reflex_main_tests['add_tests']) > 0) {
	$report['panel_tests_requested'] = explode(',', $report['panel_tests_requested']);
	$report['panel_tests_requested'] = array_merge($report['panel_tests_requested'], $reflex_main_tests['add_tests']);
	$report['panel_tests_requested'] = implode(',', $report['panel_tests_requested']);
}
if (isset($reflex_main_tests['remove_tests']) && count($reflex_main_tests['remove_tests']) > 0) {
	$report['panel_tests_requested'] = explode(',', $report['panel_tests_requested']);
	$report['panel_tests_requested'] = array_diff($report['panel_tests_requested'], $reflex_main_tests['remove_tests']);
	$report['panel_tests_requested'] = implode(',', $report['panel_tests_requested']);
}


$in_panel_tests_requested = array();
$group_ids  = array();

foreach (explode(',', $report['panel_tests_requested']) as $t) {
	if (stripos($t, 'group') === false) {
		if ($t > 0) {
			$in_panel_tests_requested[] = $t;
		}
	} else {
		$group_ids[] = str_ireplace('group_', '', $t);
	}
}
$test_ids = implode(',', $in_panel_tests_requested);
if (empty($test_ids)) {
	$test_ids = '0';
}
$group_ids_str = implode(',', $group_ids);


$group_ids_str_w_childs = $wpdb->get_col("select tg_ID from ci_test_groups 
	where (tg_ID in ({$group_ids_str}) or tg_parent in ({$group_ids_str}) ) ");

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


$where_group = '';
$more_query_test    = '';

if (count($group_ids) > 0) {
	$where_group .= " or ( tg_ID in ({$group_ids_only_ornocheckboxes}) )";
	$where_group .= " or ( tg_parent in ({$group_ids_only_ornocheckboxes}) )";
	$more_query_test .= " or (tp.te_nocheckbox=1 and tp.te_group_id in ({$group_ids_str_w_childs}))";
}


//Reflex
$reflex_test_ids = array();
if (tr_get_option('ci_pdf_data', 'enable_reflex') || tr_get_option('ci_result_settings_pcr', 'enable_ref_postest')) {
	$reflexs = $wpdb->get_results("select te_ID, te_reflex_tests from ci_test_panels
                    where te_type = 'pcr' 
                    and te_ID in ({$test_ids})
                    and te_reflex_tests!='' ");

	foreach ($reflexs as $refl) {
		$relxs = explode(',', $refl->te_reflex_tests);
		foreach ($relxs as $retid) {
			if ($retid > 0) {
				//$test_ids.=','.$retid;

				if (!isset($reflex_test_ids[$retid])) {
					$reflex_test_ids[$retid] = array();
				}
				$reflex_test_ids[$retid][] = $refl->te_ID;
			}
		}
	}
}


$drugkeys   = array('te_abrev2', 'te_abrev', 'te_name');
$all_res = isset($pre_all_res) ? $pre_all_res : array();

foreach ($drugkeys as $drugkey) {
	$sql = "SELECT *
        FROM ci_results_pcr rs 
        join ci_test_panels tp ON tp.te_type='pcr' and  tp.{$drugkey} = rs.drug and rs.drug!=''
        WHERE report_id='" . $id . "' 
        and (	tp.te_ID in ({$test_ids}) 
            	or tp.te_group_id in (select tg_ID from ci_test_groups where (tg_aw_results = 1 and tg_ID in ({$group_ids_str}) ) {$where_group}) 
				{$more_query_test}
            )
        ";

	$date_test = date('Y-m-d', strtotime($report['created']));

	$sql .= " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
	$sql .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";

	$all_results_q = $wpdb->get_results($sql, ARRAY_A);



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

if (isset($_GET['debug3'])) {
	var_dump($all_res);
	echo '<br><br>';
}

$abx_group_id 		= 0;
$abx_group_ids 		= array();
$abx_type_by_group = array();
$quantitative 		= null;
$dismkquantitative = false;
$percent_quantitative = null;
$abx_panel_options 	= array();
$abx_type 			= 'uti';
$has_manuatype 		= false;
$drug_groups 		= tr_pdd_get_drug_groups('pcr', $group_ids, true);

if (isset($_GET['debugabx2']) && !$export_es_ls) {
	echo '<br><br>report2:<br>';
	var_dump($report);
	echo '<br><br>$pre_all_res:<br>';
	var_dump($pre_all_res);
	echo '<br><br>$group_ids:<br>';
	var_dump($group_ids);
	echo '<br><br> drug_groups:<br>';
	var_dump($drug_groups);
	echo '<br><br>';
}

foreach ($drug_groups as $groupid => $groupdata) {


	if (!empty($groupdata['reflex_ID'])) {
		if (!is_array($groupdata['reflex_ID'])) $groupdata['reflex_ID'] = array($groupdata['reflex_ID']);
		foreach ($groupdata['reflex_ID'] as $rlid) {
			if ($rlid > 0) {
				$reflex_pass[$rlid] = false;
			}
		}
	}

	if (isset($groupdata['id'])) {
		$all_groups[$groupdata['id']] = $groupdata['name'];
		$drug_groups[$groupid]['subgroups'] = array();
	}

	$groupdata['antibiotic'] = $groupdata['antibiotic'] || stripos($groupdata['name'], 'Antibiotic Resistance') !== false;

	if ($groupdata['antibiotic'] && stripos($groupdata['name'], 'gram ') !== false) {
		$groupdata['antibiotic'] = false;
	}
	$drug_groups[$groupid]['antibiotic'] = $groupdata['antibiotic'];

	if ($groupdata['antibiotic']) {
		if ($groupdata['parent'] == 0) {
			foreach ($drug_groups as $cgid => $cgdata) {
				if ($cgdata['parent'] == $groupdata['id']) {
					$drug_groups[$cgid]['antibiotic'] = true;
				}
			}
		}
	} else if ($groupdata['parent'] == 0 && !empty($groupdata['other_options']['abx_type'])) {
		$abx_type = $groupdata['other_options']['abx_type'];
		$is_abx_active = $groupdata['other_options']['new_abx'];
		if (!empty($groupdata['other_options']['new_abx_date']) && !isset($_GET['newabx']) && !isset($_GET['shownewabx']) && !isset($_GET['showabx'])) {
			$abx_date = $groupdata['other_options']['new_abx_date'];
			$report_date = isset($report['approve_date']) && $report['approve_date'] != '0000-00-00 00:00:00' ? $report['approve_date'] : $report['created'];
			if (empty($report_date)) {
				$report_date = $report['doc'];
			}
			if (strtotime($report_date) < strtotime($abx_date)) {
				$is_abx_active = false;
			}
		}
		$has_manuatype = true;


		if ($is_abx_active) {
			$abx_type_by_group[$groupid] = $abx_type;

			$abx_group_ids[$groupid] 	= $groupid;
		}

		if ($is_abx_active && $abx_group_id == 0) {
			$abx_group_id 		= $groupid;


			$abx_panel_options = isset($groupdata['other_options']) && is_array($groupdata['other_options']) ? $groupdata['other_options'] : array();
			if (isset($groupdata['other_options']['quantitative'])) {
				$quantitative = $groupdata['other_options']['quantitative'];
			}
			if (isset($groupdata['other_options']['dismkquantitative'])) {
				$dismkquantitative = $groupdata['other_options']['dismkquantitative'];
			}

			if (isset($groupdata['other_options']['perquantitative'])) {
				$percent_quantitative = $groupdata['other_options']['perquantitative'];
			}

			if (isset($groupdata['other_options']['abx_pie_chart']) && $groupdata['other_options']['abx_pie_chart']) {
				$use_piechart = true;
			}
			if (isset($groupdata['other_options']['abx_pregnancy_by']) && $groupdata['other_options']['abx_pregnancy_by']) {
				$pregnancy_by_panel = $groupdata['other_options']['abx_pregnancy_by'];
			}

			if (isset($groupdata['other_options']['abx_renal_aoe']) && $groupdata['other_options']['abx_renal_aoe']) {
				$abx_renal_aoe = $groupdata['other_options']['abx_renal_aoe'];
			}
			if (isset($abx_panel_options['not_show_for_cids']) && !empty($abx_panel_options['not_show_for_cids'])) {
				$cids =  explode(',', $abx_panel_options['not_show_for_cids']);
				if (in_array($report['cli_ID'], $cids)) {
					$not_show_for_clinic = true;
					break;
				}
			}

			$groupparentname 	= $all_groups[$groupdata['id']];
		}
	} else if (!$has_manuatype && isset($groupdata['parent']) && $groupdata['parent'] > 0) {

		$parentgroupdata 	= $drug_groups[$groupdata['parent']];
		if ($abx_group_id == 0) {
			$abx_group_id 		= $groupdata['parent'];
		}
		$abx_group_ids[$groupdata['parent']] 	= $groupdata['parent'];

		$groupparentname 	= $all_groups[$groupdata['parent']];
		$abx_panel_options 	= isset($parentgroupdata['other_options']) && is_array($parentgroupdata['other_options']) ? $parentgroupdata['other_options'] : array();
		$drug_groups[$groupdata['parent']]['subgroups'][$groupid] = $groupdata['name'];

		if (isset($parentgroupdata['other_options']['quantitative'])) {
			$quantitative = $parentgroupdata['other_options']['quantitative'];
		}
		if (isset($parentgroupdata['other_options']['dismkquantitative'])) {
			$dismkquantitative = $parentgroupdata['other_options']['dismkquantitative'];
		}
		if (isset($parentgroupdata['other_options']['perquantitative'])) {
			$percent_quantitative = $parentgroupdata['other_options']['perquantitative'];
		}

		if (isset($parentgroupdata['other_options']['abx_pie_chart']) && $parentgroupdata['other_options']['abx_pie_chart']) {
			$use_piechart = true;
		}

		if (isset($parentgroupdata['other_options']['abx_pregnancy_by']) && $parentgroupdata['other_options']['abx_pregnancy_by']) {
			$pregnancy_by_panel = $parentgroupdata['other_options']['abx_pregnancy_by'];
		}

		if (isset($parentgroupdata['other_options']['abx_renal_aoe']) && $parentgroupdata['other_options']['abx_renal_aoe']) {
			$abx_renal_aoe = $parentgroupdata['other_options']['abx_renal_aoe'];
		}

		if (stripos($groupparentname, 'Wound') !== false) {
			$abx_type = 'wound';
		} else if (stripos($groupparentname, 'GI') !== false || stripos($groupparentname, 'Gastrointestinal') !== false) {

			$abx_type = 'gi';
		} else if (stripos($groupparentname, 'Respiratory') !== false) {

			$abx_type = 'r';
		}
	} else if (!$has_manuatype && $groupdata['name'] == 'Wound ABR') {
		$abx_type = 'wound';
	}
}

if (isset($not_show_for_clinic)) {
	return;
}
$abx_type_title = 'UTI Panel';
switch ($abx_type) {
	case 'wound':
		$abx_type_title = 'WOUND Panel';
		break;
	case 'gi':
		$abx_type_title = 'GI Panel';
		break;
	case 'r':
		$abx_type_title = 'Respiratory Panel';
		break;
	case 'v':
	case 'sm':
	case 'sp':
	case 'sr':
	case 'sl':
	case 'es':
		$abx_type = 'v';
		$abx_type_title = 'Vaginal Health';
		$abx_style = $abx_v_style == 2 ? 'v2' : 'v';
		break;

	case 'ei':
		$abx_type_title = 'ENT Panel';
		break;
	case 'nail':
		$abx_type_title = 'Nail Panel';
		break;
}
$pdf_title = $abx_type_title . ': ' . $pdf_title;
if (isset($abx_panel_options['new_abx_title']) && !empty($abx_panel_options['new_abx_title'])) {
	$pdf_title = $abx_panel_options['new_abx_title'];
}

if (isset($abx_panel_options['new_abx_sub_title']) && !empty($abx_panel_options['new_abx_sub_title'])) {
	$pdf_subtitle = $abx_panel_options['new_abx_sub_title'];
}

//get abx group key
$abx_version = '';
if (!empty($report['approve_date']) && $report['approve_date'] != '0000-00-00 00:00:00' && strtotime($report['approve_date']) < strtotime('2024-04-01')) {
	$abx_version = 'V1';
}

if (isset($_GET['nocacheabx'])) {
	$abx_version = '';
	if (isset($_GET['oldabx'])) {
		$abx_version = 'V1';
	}
}
if (isset($_GET['debugabx'])) {
	echo '<br>abx type: ' . $abx_type . '<br>';
}
$not_lacto = true;

$opt = get_option('ci_pdf_data', array());

if ($abx_type == 'v') {
	$abx = new CI_ABX_BV();
	$abx->style = $abx_style;
	$abx->yeast_version = 2;
	$abx->enable_AMR = $new_abx_v_amr;
	if (isset($opt['new_abx_percentp']) && $opt['new_abx_percentp']) {
		$abx->show_percent_bacteria = true; //default false
	}

	if (isset($opt['new_abx_bvnewrisk']) && $opt['new_abx_bvnewrisk']) {
		$abx->use_new_calc_risk = true; //default false
	}

	if (isset($opt['new_abx_bvdesc']) && $opt['new_abx_bvdesc']) {
		$abx->show_bv_desc = true; //default false
	}

	if (isset($opt['new_abx_separate_opportunistic']) && $opt['new_abx_separate_opportunistic']) {
		$abx->separate_opportunistic = true; //default false
	}
} else if ($abx_version == 'V1' || isset($_GET['abxv1'])) {

	$abx = new CI_ABX_V1($abx_type);
	$abx->style = $abx_style;
	//$abx->version         = '2.0';
} else {
	$abx = new CI_ABX($abx_type);
	$abx->style = $abx_style;

	if ($abx_style == 2 && $use_piechart) {
		$abx->version         = '3.3.1';
	}



	if (isset($opt['new_abx_orphancol']) && $opt['new_abx_orphancol'] === true) {
		$abx->ShowColOrphan = true; //default false
	}

	if (isset($opt['new_abx_prio_oral']) && $opt['new_abx_prio_oral'] === false) {
		$abx->PrioritizeOral = false; //default true
	}

	if (isset($opt['new_abx_prio_acti']) && $opt['new_abx_prio_acti'] === false) {
		$abx->PrioritizeActivity = false; //default true
	}
	if (isset($opt['new_abx_ob4acti']) && $opt['new_abx_ob4acti'] === true) {
		$abx->PrioritizeOrialB4Activity = true; //default false
	}

	if ((isset($opt['new_abx_p_quant_new']) && $opt['new_abx_p_quant_new'] === true) || isset($_GET['newalgo'])) {
		$abx->percent_quantitative_new = true; //default false
	}

	if ((isset($opt['new_abx_sumquantscore']) && $opt['new_abx_sumquantscore'] === true) || isset($_GET['newalgo'])) {
		$abx->Quantitative_Sum_score = true; //default false
	}
	if ((isset($opt['new_abx_pair_30_oral90']) && $opt['new_abx_pair_30_oral90'] === true) || isset($_GET['pair30_90'])) {
		$abx->pair_3_oral_90 = true; //default false
	}
}

if (isset($_GET['debugabx'])) {
	var_dump($abx_group_ids);
	echo '<br>abx group id: ' . $abx_group_id . '<br>';
}
if ($abx_group_id > 0) {
	$gidkey = implode('_', $abx_group_ids);

	$hascacheid = get_option('ci_abx_type_' . $abx_group_id);
	if ($hascacheid) {
		$report['abx_cache_id'] = $gidkey;
	}

	foreach ($abx_group_ids as $abxgid) {
		$abx_bac_mk_key	= '_abx_' . $abxgid . '_bac_mk_active_ids';
		$abx_bac_mk_active_ids = get_option($abx_bac_mk_key, array());

		if (isset($abx_bac_mk_active_ids['bac']) && count($abx_bac_mk_active_ids['bac']) > 0) {
			$abx->bacteria_ids_by_panel = array_merge($abx->bacteria_ids_by_panel, $abx_bac_mk_active_ids['bac']);
		}
		if (isset($abx_bac_mk_active_ids['marker']) && count($abx_bac_mk_active_ids['marker']) > 0) {
			$abx->marker_ids_by_panel = array_merge($abx->marker_ids_by_panel, $abx_bac_mk_active_ids['marker']);
		}
	}
} else {
	return;
}


if (isset($_GET['debugabx']) && isset($_GET['html'])) {
	$abx->debug = true;
}

if (empty($report['pa_state'])) {
	$report['pa_state'] = $report['cli_state'];
}


if ($quantitative !== null) {
	$abx->setQuantitative($quantitative);
} else {
	$quantitative = get_option('ci_abx_Quantitative', null);

	if ($quantitative === null || isset($_GET['checkQuantitative'])) {
		$abx->checkQuantitative();
	} else {
		$abx->setQuantitative($quantitative);
	}
}

$abx->dismkquantitative = $dismkquantitative;

if ($percent_quantitative) {
	$abx->set_Show_Percent_Quantitative(true);
}


if (tr_get_option('ci_pdf_data', 'new_abx_rm0')) {
	$abx->excludeRisk0(true);
}

if ($use_biofilm) {
	$abx->set_Biofilm($use_biofilm);
}


$abx->enableConditional     = true;


if (tr_get_option('ci_pdf_data', 'new_abx_baseabx')) {
	$abx->base_abx = true;
}
if (tr_get_option('ci_pdf_data', 'new_abx_pediatric')) {
	$abx->Pediatric_ranges = true;
}

if (tr_get_option('ci_pdf_data', 'new_abx_hide_infection_complexity')) {
	$abx->hide_infection_complexity = true;
}

if (tr_get_option('ci_result_settings_pcr', 'enable_verylh')) {
	$abx->Quantitative5Levels = true;
}

//check formula
$abx_panel_options['abx_formula'] = isset($abx_panel_options['abx_formula']) ? $abx_panel_options['abx_formula'] : '';
if (!empty($abx_panel_options['abx_formula'])) {
	$abx->load_formula = $abx_panel_options['abx_formula'];
	if (in_array($abx->load_formula, array('GL', 'NQC'))) {
		if (!empty($abx_panel_options['abx_y_intercept'])) {
			$abx->load_y_intercept = $abx_panel_options['abx_y_intercept'];
		}
		if (!empty($abx_panel_options['abx_slope'])) {
			$abx->load_slope = $abx_panel_options['abx_slope'];
		}
	}
	if (in_array($abx->load_formula, array('NQC'))) {
		if (!empty($abx_panel_options['abx_load_cutoff'])) {
			$abx->load_cutoff = $abx_panel_options['abx_load_cutoff'];
		}
		if (!empty($abx_panel_options['abx_slope'])) {
			$abx->load_slope = $abx_panel_options['abx_slope'];
		}
	}
	if (in_array($abx->load_formula, array('NQY'))) {
		if (!empty($abx_panel_options['abx_delta'])) {
			$abx->load_delta = $abx_panel_options['abx_delta'];
		}
		if (!empty($abx_panel_options['abx_slope'])) {
			$abx->load_slope = $abx_panel_options['abx_slope'];
		}
	}
	if (in_array($abx->load_formula, array('Q10', 'Q100', 'QG', 'QG1', 'QG_HL', 'QG_HM'))) {
		if (!empty($abx_panel_options['abx_ct1'])) {
			$abx->load_ct1 = $abx_panel_options['abx_ct1'];
		}
		if (!empty($abx_panel_options['abx_ct2'])) {
			$abx->load_ct2 = $abx_panel_options['abx_ct2'];
		}
	}

	if (in_array($abx->load_formula, array('QG', 'QG1', 'QG_HL', 'QG_HM'))) {
		if (!empty($abx_panel_options['abx_load1'])) {
			$abx->load1 = $abx_panel_options['abx_load1'];
		}
		if (!empty($abx_panel_options['abx_load2'])) {
			$abx->load2 = $abx_panel_options['abx_load2'];
		}
	}
}

if (isset($abx_panel_options['abx_load_high']) && $abx_panel_options['abx_load_high'] > 0) {
	$abx->load_high = $abx_panel_options['abx_load_high'];
}


$abx->setReport($report);
$abx->setSelectedState($report['pa_state']);

$pa_pregnancy = false;
if ($pregnancy_by_panel === 'aoe' || $pregnancy_uti_by === 'aoe') {
	$pa_pregnancy = trpdd_get_batch_meta($report['mid'], 'pcr', 'pregnancy');
	$pa_pregnancy = strtolower($pa_pregnancy) == 'yes' || $pa_pregnancy == '1';
}

if (isset($_ci_override_renal) && $_ci_override_renal !== null) {
	$abx->patient_renal = $_ci_override_renal;
} else if ($abx_renal_aoe) {
	$abx->patient_renal = trpdd_get_batch_meta($report['mid'], 'pcr', 'renal');
}


if (isset($_ci_override_pregnancy) && $_ci_override_pregnancy !== null) {
	$abx->set_Show_Pregnancy($_ci_override_pregnancy, 'aoe');
} else if (!empty($pregnancy_by_panel)) {
	if ($pregnancy_by_panel == 'age') {
		$pa_pregnancy = true;
	}
	$abx->set_Show_Pregnancy($pa_pregnancy, $pregnancy_by_panel);
} else if ($pregnancy_uti && $abx_type == 'uti') {
	if ($pregnancy_uti_by == 'age') {
		$pa_pregnancy = true;
	}

	$abx->set_Show_Pregnancy($pa_pregnancy, $pregnancy_uti_by);
}


if (isset($_ci_exclude_antibiotic_IDs) && is_array($_ci_exclude_antibiotic_IDs)) {
	$abx->exclude_antibiotic_IDs = $_ci_exclude_antibiotic_IDs;
}



$pos_markers 	= array();
$pos_bacterias 	= array();
$pos_yeast_bacterias = array();
$all_markers 	= array();
$pos_viruses 	= array();
$same_abx_tests = array();


foreach ($drug_groups as $groupid => $groupdata) {


	$is_antibiotic_resistance_group = $groupdata['antibiotic'];

	if (isset($_GET['debugbacteria'])) {
		echo '<h5>Group #' . $groupid . ': ' .  $groupdata['name'] . '</h5>';
	}

	$not_same_abx_group = $abx_group_id > 0 && $groupdata['parent'] > 0 && $groupdata['parent'] != $abx_group_id;
	if ($not_same_abx_group && $groupdata['parent'] > 0) {
		//check if same abx type
		if (isset($abx_type_by_group[$groupdata['parent']]) && $abx_type_by_group[$groupdata['parent']] == $abx_type) {
			$not_same_abx_group = false;
		}
	}

	if (!$is_antibiotic_resistance_group && $not_same_abx_group) {
		if (isset($_GET['debugabx'])) {
			echo $groupdata['name'] . ': Skip group because not same abx group<br>';
		}
		continue;
	}


	$te_IDs = isset($groupdata['te_IDs']) && is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();

	$sub_abx_type 	= isset($groupdata['other_options']['abx_type']) ? $groupdata['other_options']['abx_type'] : '';
	$main_group_abx_type = '';
	if ($groupdata['parent'] > 0) {
		$parentgroupdata 		= $drug_groups[$groupdata['parent']];
		$main_group_abx_type 	= isset($parentgroupdata['other_options']['abx_type']) ? $parentgroupdata['other_options']['abx_type'] : '';

		if (!$is_antibiotic_resistance_group && empty($sub_abx_type)) {
			$sub_abx_type = $main_group_abx_type;
		}
	}


	foreach ($te_IDs as $te_ID) {
		if (empty($te_ID)) {
			continue;
		}
		$arr = isset($all_res[$te_ID]) ? $all_res[$te_ID] : array();
		if (!empty($arr)) {
			if (!$arr['te_in_req'] || $arr['te_is_control']) {
				continue;
			}

			$arr['showed']	 = true;
			$arr['type_of_specimen'] = $report['type_of_specimen'];
			if (defined('TR_ABX_TESTING') && TR_ABX_TESTING) {
				if (empty($report['instrument'])) {
					$arr['instrument'] = $wpdb->get_var("select cu_ms from ci_test_panels_cutoffs where cu_te_ID = {$arr['te_ID']} limit 1");
					if (strpos($arr['instrument'], ',') !== false) {
						$instruments = explode(',', $arr['instrument']);
						$arr['instrument'] = trim($instruments[0]);
						$report['instrument'] = $arr['instrument'];
					}
				} else {
					$arr['instrument'] = $report['instrument'];
				}
			}
			$arr 			= tr_pdd_parse_pcr_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);

			$arr['abx_type'] = $main_group_abx_type;

			if (in_array($sub_abx_type, array('sm', 'sr', 'sp', 'sl', 'es'))) {
				$arr['bv_type'] = $sub_abx_type;
			}

			if ($arr['conc'] == 40) {
				$arr['pos-neg'] = 'NEG';
			}

			if (in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVH', 'PVL'))) {


				if (count($reflex_test_ids) > 0) {
					foreach ($reflex_test_ids as $flid => $tids) {
						if (in_array($arr['te_ID'], $tids)) {
							unset($reflex_test_ids[$flid]);
						}
					}
				}
				if (!empty($groupdata['reflex_ID'])) {
					if (!is_array($groupdata['reflex_ID'])) $groupdata['reflex_ID'] = array($groupdata['reflex_ID']);
					foreach ($groupdata['reflex_ID'] as $rlid) {
						if ($rlid > 0  && isset($reflex_pass[$rlid])) {
							$reflex_pass[$rlid] = true;
						}
					}
				}




				if ($is_antibiotic_resistance_group || (isset($arr['te_antibiotic']) && $arr['te_antibiotic'])) {

					$all_markers[$te_ID] = $arr;
					$pos_markers[$te_ID] = $arr;
					//$has_style = true;
				} else {

					$is_bacteria = false;
					if (
						stripos($groupdata['name'], 'Gram Negative') !== false
						|| stripos($groupdata['name'], 'Gram Positive') !== false
						|| stripos($groupdata['name'], 'No Gram') !== false
						|| stripos($groupdata['name'], 'Pathogens') !== false
						|| stripos($groupdata['name'], 'BACTERIA') !== false
						|| stripos($groupdata['name'], 'PARASITE') !== false
						|| $abx_type == 'v'
					) {
						$is_bacteria = true;
					}

					if (!$is_bacteria && $groupdata['parent'] == 0) {
						$is_bacteria = $wpdb->get_var("select meta_value from ci_test_meta 
							where test_id = {$te_ID} and meta_key = 'bac_tests' ");
						$is_bacteria = strlen($is_bacteria) > 0;
					}



					if (
						!$is_bacteria
						&& (
							($abx_type == 'uti' && $sub_abx_type == 'yuti')
							|| ($abx_type == 'wound' && $sub_abx_type == 'ywound')
							|| ($abx_type == 'wound' && $sub_abx_type == 'vwound')
							|| ($abx_type == 'ei' && $sub_abx_type == 'yent')
							|| ($abx_type == 'nail' && $sub_abx_type == 'ynail')
						)
						&& (
							strtotime($groupdata['other_options']['new_abx_date']) <= strtotime($report['approve_date'])
							|| isset($_GET['showabx'])
							|| isset($_GET['shownewabx'])
							|| isset($_GET['debugabx'])
						)
					) {
						$arr['bv_type'] = 'yeast';
						if ($sub_abx_type == 'vwound') {
							$arr['bv_type'] = 'wv';
						}
						$pos_yeast_bacterias[$te_ID] = $arr;
						$has_style = true;
					} else if (!$is_bacteria && $sub_abx_type == 'sti') {
						$arr['bv_type'] = 'sti';
						$pos_yeast_bacterias[$te_ID] = $arr;
						$has_style = true;
					} else if (stripos($groupdata['name'], 'Virus') !== false || stripos($arr['te_name'], 'Virus') !== false) {
						$arr['name'] = $arr['te_name'];
						$pos_viruses[$te_ID] = $arr;
					}

					if (isset($_GET['debugbacteria'])) {
						echo '<br>Test name: ' . $arr['te_name'] . ' in group: ' . $groupdata['name'] . ' is bacteria: ' . ($is_bacteria ? 'yes' : 'no') . '<br>';
					}

					if ($is_bacteria) {
						$pos_bacterias[$te_ID] = $arr;

						if (!isset($same_abx_tests[$arr['te_name']])) {
							$same_abx_tests[$arr['te_name']] = array();
						}
						$same_abx_tests[$arr['te_name']][] = $te_ID;
					}
				}
			} else {
				//NEG
				if ($is_antibiotic_resistance_group || (isset($arr['te_antibiotic']) && $arr['te_antibiotic'])) {
					$all_markers[$te_ID] = $arr;
				} elseif ($abx_type == 'v') {
					$abx->addTestNeg($arr);
				}

				if (isset($_GET['debugbacteria'])) {
					echo '<br>Test name: ' . $arr['te_name'] . ' in group: ' . $groupdata['name'] . ' is Neg ' . $arr['pos-neg'] . '<br>';
					//var_dump($arr['list']);
				}
			}

			//check exist lactobacillus
			if ($abx_type == 'v' && stripos($arr['te_name'], 'Lactobacillus') !== false) {
				$not_lacto = false;
			}
		}
	}
}

//fix $same_abx_tests
foreach ($same_abx_tests as $te_name => $te_ids) {
	if (count($te_ids) > 1) {
		$has_main_type = false;
		$ids_not_main_type = array();
		foreach ($te_ids as $te_ID) {
			if ($pos_bacterias[$te_ID]['abx_type'] == $abx_type) {
				$has_main_type = true;
			} else {
				$ids_not_main_type[] = $te_ID;
			}
		}
		if ($has_main_type) {
			foreach ($ids_not_main_type as $te_ID) {
				unset($pos_bacterias[$te_ID]);
			}
		}
	}
}

//remove reflex
if (tr_get_option('ci_pdf_data', 'enable_reflex') || tr_get_option('ci_result_settings_pcr', 'enable_ref_postest')) {
	if (isset($_GET['debug334'])) {
		var_dump($reflex_pass);
		echo '<br>';
		var_dump($reflex_test_ids);
		echo '<br>';
		var_dump($pos_markers);
	}
	if (count($reflex_pass) > 0) {
		foreach ($reflex_pass as $gid => $refpass) {
			if (!$refpass) {
				foreach ($drug_groups as $groupid => $groupdata) {
					if ($groupid == $gid && $groupdata['antibiotic']) {

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


	if (count($reflex_test_ids) > 0) {
		foreach ($drug_groups as $groupid => $groupdata) {
			foreach ($groupdata['te_IDs'] as $te_ID) {

				if (isset($reflex_test_ids[$te_ID])) {
					if (isset($pos_bacterias[$te_ID])) {
						if ($abx->debug) {
							echo '<br>Remove reflex bacteria: ' . $pos_bacterias[$te_ID]['te_name'] . ' for test ID: ' . $te_ID . '<br>';
						}
						unset($pos_bacterias[$te_ID]);
					}
					if (isset($pos_markers[$te_ID])) {
						unset($pos_markers[$te_ID]);
					}
				}
			}
		}
	}
}

if (isset($_GET['debugposmk'])) {
	echo '<br>POS markers: ';
	var_dump($pos_markers);
	echo '<br>';
}

foreach ($pos_markers as $arr) {
	$abx->addMarker($arr);
}

$abx->NotLactobacillus = $not_lacto;

if ($abx->debug) {
	$abx->log .= 'Bacterias :' . count($pos_bacterias) . '<br>';
}

if (isset($_GET['debugabx2'])) {
	echo '<br>Pos bacterias: ';
	var_dump($pos_bacterias);
	echo '<br>';
}

foreach ($pos_bacterias as $te_ID => $arr) {
	$has_style = true;
	if ($abx_panel_options['abx_formula'] == 'SY') {
		$arr['y_intercept'] = $wpdb->get_var("select meta_value from ci_test_meta 
							where test_id = {$te_ID} and meta_key = 'idl_y_int' ");

		$arr['slope'] = $wpdb->get_var("select meta_value from ci_test_meta 
							where test_id = {$te_ID} and meta_key = 'idl_slope' ");
	}
	$abx->addBacteria($arr);
}

if ($use_biofilm) {
	foreach ($pos_yeast_bacterias as $arr) {
		$abx->add_Biofilm_yeast($arr);
	}
}
if (isset($_GET['debugabx2'])) {
	echo '<br>Has style: ';
	var_dump($has_style);
	echo '<br>';
}


if (!$has_style) {

	if (isset($_GET['html'])) {
		echo 'no data.';
	}

	$has_report = false;

	return;
}

if (tr_get_option('ci_pdf_data', 'new_abx_mkrbrs') || $new_abx_v_amr) {
	$abx->setAllMarkers($all_markers);
}
$abx->hasTestMakers = count($all_markers) > 0;


if (tr_get_option('ci_pdf_data', 'new_abx_respiratory_algo')) {
	$abx->enable_specimen_respiratory_algo = true;
	$abx->setAllViruses($pos_viruses);
}

$abx->build_all_scores();

$abx->buildscores();

if (isset($only_get_score) && $only_get_score) {
	return;
}

if ($abx_type != 'v') {
	if (!$abx->hasMatchOral) {
		trpdd_update_batch_meta($report['mid'], 'pcr', '_not_match_oral', 1);
	} else {
		trpdd_update_batch_meta($report['mid'], 'pcr', '_not_match_oral', 0);
	}
}


if (count($pos_yeast_bacterias) > 0 && !isset($_GET['notyeast'])) {
	if (!function_exists('tr_report_yeast_uti_wound')) {
		function tr_report_yeast_uti_wound($abx_type, $pos_yeast_bacterias, $report)
		{
			global $wpdb, $abx_style, $asset_url, $adding_bac_selected, $new_abx_v_amr, $all_markers, $pos_markers;

			$abx_style = 'v2';
			$abx = new CI_ABX_BV();
			$abx->yeast_uti_wound = $abx_type;
			$abx->parent_type = 'v';
			$abx->yeast_version = 2;
			$abx->style = $abx_style;
			$abx->enable_AMR = $new_abx_v_amr;

			$abx->debug = isset($_GET['debugabx']);

			$abx->load_formula = $report['load_formula'];
			$abx->load_y_intercept = $report['load_y_intercept'];
			$abx->load_slope = $report['load_slope'];
			$abx->load_cutoff = $report['load_cutoff'];
			$abx->load_cutoff = $report['load_cutoff'];
			$abx->load_delta = $report['load_delta'];
			$abx->load_ct1 = $report['load_ct1'];
			$abx->load_ct2 = $report['load_ct2'];
			$abx->load1 = $report['load1'];
			$abx->load2 = $report['load2'];


			$abx->setReport($report);

			$abx->setSelectedState($report['pa_state']);

			foreach ($pos_yeast_bacterias as $te_ID =>  $arr) {

				if ($abx->load_formula == 'SY') {
					$arr['y_intercept'] = $wpdb->get_var("select meta_value from ci_test_meta 
							where test_id = {$te_ID} and meta_key = 'idl_y_int' ");

					$arr['slope'] = $wpdb->get_var("select meta_value from ci_test_meta 
							where test_id = {$te_ID} and meta_key = 'idl_slope' ");
				}
				$abx->addBacteria($arr);
			}

			if ($new_abx_v_amr) {
				foreach ($pos_markers as $arr) {
					$abx->addMarker($arr);
				}

				$abx->setAllMarkers($all_markers);
			}

			$abx->build_all_scores();

			$abx->buildscores();

			$adding_bac_selected = $abx->selectedBacterias;

			ob_start();
			if ($report['has_main']) {
				echo '<div class="break"></div>';
			}
			echo '<link href="' . $asset_url . 'css/abx_yeast_uti.css" rel="stylesheet" />';
			include('abx' . $abx_style . '.php');

			if ($abx->debug) {
				echo $abx->get_log();
				echo '<br><br>';
				echo $abx->log_exclude;
				echo '<br><br>';
				echo $abx->logpair;
			}
			return ob_get_clean();
		}
	}


	$report['has_main'] = count($abx->getSelectedBacterias()) > 0;
	if ($abx->Biofilm_Risk_infection !== null) {
		$report['BioFilm'] = true;
		$report['biofilm_yeasts'] = $abx->biofilm_yeasts;
	}
	$report['load_formula'] = $abx->load_formula;
	$report['load_y_intercept'] = $abx->load_y_intercept;
	$report['load_slope'] = $abx->load_slope;
	$report['load_cutoff'] = $abx->load_cutoff;
	$report['load_cutoff'] = $abx->load_cutoff;
	$report['load_delta'] = $abx->load_delta;
	$report['load_ct1'] = $abx->load_ct1;
	$report['load_ct2'] = $abx->load_ct2;
	$report['load1'] = $abx->load1;
	$report['load2'] = $abx->load2;



	$abx_adding_html = tr_report_yeast_uti_wound($abx_type, $pos_yeast_bacterias, $report);
}

if (isset($_GET['debugabx']) && isset($_GET['html'])) {

	global $adding_bac_selected;

	$all_bv_types = array(
		'bv' => 1,
		'av' => 2,
		'ur' => 3,
		'sti' => 4,
		'yeast' => 5,
		'sm' => 6,
		'sr' => 7,
		'sp' => 8,
		'sl' => 9,
		'es' => 10,
	);

	echo 'state: ' . $report['pa_state'] . '<br>';
	if (empty($report['age'])) {
		$report['age'] = $abx->age;
	}
	echo 'age: ' . $report['age'] . '<br>';
	echo 'gender: ' . $report['pa_gender'] . '<br>';

	$lab_connect_link = 'https://imt.ignitelabconnect.com/wp-admin/admin.php?page=ci_abx&tab=test2&test=1&type=' . $abx_type;
	$lab_connect_link .= '&state=' . $report['pa_state'];
	$lab_connect_link .= '&dob=' . $report['pa_dob'];
	$lab_connect_link .= '&gender=' . $report['pa_gender'];
	$lab_connect_link .= '&specimen=' . $report['type_of_specimen'];
	$lab_connect_link .= '&Quantitative=' . $abx->Quantitative;
	$lab_connect_link .= '&Quantitative5lv=' . $abx->Quantitative5Levels;
	$lab_connect_link .= '&showColumnOrphan=' . $abx->ShowColOrphan;
	$lab_connect_link .= '&prio_oral=' . $abx->PrioritizeOral;
	$lab_connect_link .= '&prio_activity=' . $abx->PrioritizeActivity;
	$lab_connect_link .= '&ob4a=' . $abx->PrioritizeOrialB4Activity;
	$lab_connect_link .= '&lab=' . $report['lab_dba_init'];
	$lab_connect_link .= '&abxstyle=' . $abx_style;
	$lab_connect_link .= '&formula=' . $abx->load_formula;
	$lab_connect_link .= '&yi=' . $abx_panel_options['abx_y_intercept'];
	$lab_connect_link .= '&slope=' . $abx_panel_options['abx_slope'];
	if (!$abx->has_marker_Q) {
		$lab_connect_link .= '&mknq=1';
	}

	if ($use_biofilm) {
		$lab_connect_link .= '&bio=1';
	}

	if ($percent_quantitative) {
		$lab_connect_link .= '&pqua=1';
	}

	if (!empty($abx->virus_detected_list)) {
		$lab_connect_link .= '&dvirus=1';
	}

	if ($abx->enable_AMR) {
		$lab_connect_link .= '&vamr=1';
	}

	if ($abx->pair_3_oral_90) {
		$lab_connect_link .= '&changepair=1';
	}

	$lab_connect_link .= '&pie=' . ($use_piechart ? 1 : 0);
	$lab_connect_link .= '&pediatric=' . ($abx->Pediatric_ranges ? 1 : 0);
	if (isset($abx->NotTestAV)) {
		$lab_connect_link .= '&noav=' . ($abx->NotTestAV ? 1 : 0);
	}


	foreach ($abx->selectedBacterias as  $bat) {
		$lab_connect_link .= '&bacs[]=' . $bat->ID;
		if (in_array($bat->bv_type, array('bv', 'av', 'ur', 'sti', 'yeast', 'sm', 'sr', 'sp', 'sl', 'es'))) {
			$lab_connect_link .= '_' . $all_bv_types[$bat->bv_type];
		}

		$lab_connect_link .= '&lmh[' . $bat->ID . ']=' . (isset($bat->posneg) ? $bat->posneg : '');
		$lab_connect_link .= '&concvl[' . $bat->ID . ']=' . $bat->conc;

		if ($abx->load_formula == 'SY') {
			$lab_connect_link .= '&by[' . $bat->ID . ']=' . $bat->y_pep;
			$lab_connect_link .= '&bl[' . $bat->ID . ']=' . $bat->slope;
		}
	}

	if (is_array($adding_bac_selected)) {
		foreach ($adding_bac_selected as $bat) {
			$lab_connect_link .= '&bacs[]=' . $bat->ID . '_' . $all_bv_types[$bat->bv_type];
			$lab_connect_link .= '&lmh[' . $bat->ID . ']=' . $bat->posneg;
			$lab_connect_link .= '&concvl[' . $bat->ID . ']=' . $bat->conc;
		}
	}


	foreach ($abx->selectedMarkers as $mk) {
		$lab_connect_link .= '&mkrs[]=' . $mk->ID;
	}

	$lab_connect_link .= "&lmitmk=" . implode(',', $abx->limit_markers);

	echo '<br><a target=_blank href="' . $lab_connect_link . '">Lab Connect</a><br><br>';
	echo '<style>body{background:#fff!important;}.abxstyle2,.abxstylev,#footer,#header{max-width:755px;margin:0 auto !important;} table.abxestimatedtbl{width:100%!important}</style>';
}

if ($export_es_ls) {
	ob_clean();
	include_once('abx/abx_debug_table.php');
	exit;
}

$count_errors = $abx->count_errors;
if (isset($_GET['debugae'])) {
	var_dump($abx->count_errors);
	var_dump($abx->errors);
}

$footer_height = 2;


$labname 			= $report['lab_name'];
$contact_form_url 	= 'https://igniteabx.com/contact/?lab=' . urlencode($labname) . '&assen=' . $report['assen'];
$QR 				= trpdd_get_qr_code($contact_form_url, 100, 'H', 15, 0);
$cachedir 			= WP_CONTENT_DIR . '/cache/qrcache/';
if (!file_exists($cachedir)) {
	wp_mkdir_p($cachedir);
}
$qr_file 			= $cachedir . $report['assen'] . '.jpg';

if (!file_exists($qr_file)) {
	$rs = imagejpeg($QR, $qr_file);
}



$contactqr = 'data:image/jpg;base64,' . base64_encode(file_get_contents($qr_file));
ob_start();


?>
<div id="footer">

	<div class="footer">
		<table>
			<tr>
				<td style="width:190px;padding:4px;border:3px solid #ddd">
					<table>
						<tr>
							<td><img src="<?php echo $contactqr ?>" style="width:60px" /></td>
							<td style="padding-left:5px;vertical-align: middle;">For questions or comments, please scan the QR code,
								or go to <a id="contactlink" href="<?php echo $contact_form_url ?>"><?php echo 'igniteabx.com/contact/' ?></a></td>
						</tr>
					</table>
				</td>
				<td style="line-height: 10px;padding:4px 10px;border:3px solid #ddd;vertical-align:middle">
					For educational purposes only: The information provided in this report is not intended as a recommendation for treatment. It is generated algorithmically from testing results and does not account for all patient-specific variables. Please consider all relevant factors before prescribing a treatment regimen.

				</td>
				<td style="width:190px;line-height: 12px;padding:4px;border:3px solid #ddd;vertical-align: middle;">
					<table>
						<tr>
							<td style="vertical-align: middle;width:44px;text-align:center"><img src="<?php echo $asset_url . 'images/slogo.png' ?>" style="width:34px" /></td>
							<td style="vertical-align: middle;">©<?php echo date_i18n('Y') ?> igniteABX. All rights reserved. Version <?php echo $abx->version ?></td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>

</div>

<?php
$footer_html = ob_get_clean();
$header_height = 3.1;
$footer_height = 2.24;
$margin_bottom = 0.22;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
	$margin_bottom = $footer_height;
	$footer_height = 0.17;
}

if (strlen($report['requesting_practice']) > 25) {
	$header_height += 0.3;
}
$html_class = (defined('TRCDPDF_NEW') && TRCDPDF_NEW) ? 'dompdf_new' : 'dompdf_old';

if (isset($_GET['nocacheabx']) && isset($_GET['testclinic'])) {
	$report['requesting_practice'] = $wpdb->get_var("select cli_clinic from ci_clinic order by RAND() LIMIT 1");
}
if (strlen($report['requesting_practice']) > 34) {
	$html_class .= ' clinic2lines';
}


$abx_css_file = 'abx.css';
if ($abx_style >= 2 || in_array($abx_style, array('v', 'v2'))) {
	$abx_css_file = 'abx' . $abx_style . '.css?' . time();
}
?>

<html class="<?php echo $html_class ?>">

<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta id="final" content="<?php echo $report['final_results'] ?>" />
	<title><?php echo $report['assen'] ?></title>

	<link href="<?php echo $asset_url . 'css/' . $abx_css_file ?>" rel='stylesheet' />

	<style>
		@page {
			margin: 0.5cm 0.5cm <?php echo $footer_height ?>cm 0.5cm;
		}

		body {
			margin-top: <?php echo $header_height ?>cm;
			margin-bottom: <?php echo $margin_bottom ?>cm;
		}

		.break {
			page-break-after: always;
			border: 0;
		}

		.dontbreakinside {
			page-break-inside: avoid;
		}

		* {
			font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
			font-size: 9pt;
			line-height: 10px;
		}

		table {
			width: 100%;
			border-spacing: 0px;
			page-break-inside: auto;
			border-collapse: collapse;
		}

		table th {
			text-align: left;
			padding: 2px;
		}

		table td {
			padding: 2px;
			vertical-align: top;
			line-height: 10px;
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
			border: 1px solid black;
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
			text-align: right
		}

		.center_pre {
			text-align: center;
			color: red;
		}

		.red,
		.rowred td {
			color: red
		}

		.small_table th,
		.small_table td {
			font-size: 8pt;
			padding: 2px;
		}

		sup,
		.sup {
			font-size: 7px !important;
			position: relative;
		}

		.sup {
			position: absolute;
			top: -1px;
		}


		.small_padding_table th,
		.small_padding_table td {
			padding: 1px 2px;
			color: #000;
		}

		.address_right div {
			font-size: 10px;
			line-height: 10px;
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

		table.valmiddle td {
			vertical-align: middle;
		}

		<?php if (!isset($_REQUEST['html'])) { ?>#header,
		#footer {
			position: fixed;
			left: 0;
			right: 0;
			color: #666;
			font-size: 0.9em;
		}

		<?php } ?>#header {
			top: 0;
		}

		#footer {
			bottom: 0.5cm;
		}


		#footer,
		#footer * {
			font-size: 9px;
			line-height: 10px;
			margin: 0;
		}

		.header_tbl th {
			text-transform: uppercase;
			background: #555;
			text-align: center;
			height: 18px;
			color: #fff;
		}

		.title_tbl th {
			text-align: center;
			border-bottom: 1px solid #ddd;
			height: 18px;
			background: #ddd;
			color: #000;
			padding-top: 0;
			padding-bottom: 0;
		}

		.smalltext {
			font-size: 9px;
		}

		ul {
			padding-left: 10px;
			margin-bottom: 0;
			margin-top: 0;
		}

		a#contactlink {
			text-decoration: none;
			color: #191964;
		}
	</style>
</head>

<body class="patientinheader">



	<div id="header">
		<table style="width: 100%;border:0;border-collapse:collapse">
			<tr>
				<td style="width:30%;height:60px;text-align:left;vertical-align:middle" valign="top">
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
						$logo_second = '';
					}

					if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
						$max_width = 150;
						$max_height = 60;
						$logo_path = $logo_dir . $logo_main; //trpdd_get_resized_image($logo_dir . $logo_main, $max_width+10, $max_height+10);
					?>
						<img style="border:none;max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_path ?>" />
					<?php
					}

					?>

				</td>
				<td style="width:40%;text-align: center;vertical-align:middle">
					<h2 class="pdf_title" style="color:<?php echo $report['color'] ?>;"><?php echo $pdf_title  ?></h2>
					<?php if (!empty($pdf_subtitle)) { ?>
						<h5 style="color:<?php echo $report['color'] ?>;"><?php echo $pdf_subtitle  ?></h5>
					<?php } ?>
					<?php if (tr_get_option('ci_pdf_data', 'centerfinalreport')) { ?>
						<div class="center_pre"><?php echo isset($text_PRELIMINARY) ? $text_PRELIMINARY : '' ?></div>
					<?php } ?>
				</td>
				<td class="address_right" style="width:30%;color:<?php echo $report['color'] ?>;text-align: right;vertical-align:middle">
					<?php if (!tr_get_option('ci_pdf_data', 'centerfinalreport')) { ?>
						<div class="right_pre"><?php echo isset($text_PRELIMINARY) ? $text_PRELIMINARY : '' ?></div>
					<?php } ?>
					<div><?php echo $report['header_lab_name'] ?></div>
					<?php
					if (!empty($report['header_lab_second_line_hpdf'])) {
						echo '<div>' . $report['header_lab_second_line_hpdf'] . '</div>';
					}
					if (!empty($report['header_lab_phone']) && $report['header_lab_phone'] != '000-000-000' && !tr_get_option('ci_pdf_data', 'report_hilaphone')) {
						echo '<div>Phone: ' . $report['header_lab_phone'] . '</div>';
					}
					if (!empty($report['header_lab_clia'])) {
						echo '<div>CLIA ID: ' . $report['header_lab_clia'] . '</div>';
					}
					?>
				</td>
			</tr>
		</table>

		<div style="padding-bottom:6px;padding-top:6px;background:#eee;margin-bottom:10px">
			<table class="small_padding_table">
				<tr>
					<td style="width:33%">
						<table class="noboder small_table">
							<tr>
								<td style="width:43px">Clinic:</td>
								<td><?php echo $report['requesting_practice'] ?></td>
							</tr>
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
					<td style="width:34%">
						<table class="noboder small_table">
							<tr>
								<td style="width:20%">Patient:</td>
								<td colspan="3"><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
							</tr>
							<tr>
								<td>DOB:</td>
								<td style="width:50%"><?php echo tr_pdd_display_date($report['patient_dob']) ?></td>
								<td>Gender:</td>
								<td><?php echo htmlspecialchars($report['pa_gender']) ?></td>
							</tr>
						</table>
					</td>
					<td>
						<table class="noboder small_table">
							<tr>
								<td style="width:46%">Accession Number:</td>
								<td><?php echo $report['assen'] ?></td>
							</tr>
							<tr>
								<td>Date Reported:</td>
								<td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>

	</div>


	<?php
	if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
		echo stripslashes($footer_html);
	}

	if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
		$rejected = tr_pdd_get_batch_statuses($report['batch_status'], false, false, 'pcr');

	?>
		<br>
		<br><br><br><br>
		<h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
		<p style="text-align: center;"><?php echo $report['billing_notes'] ?></p>
		<p style="text-align: center;"><?php echo $report['notes_report'] ?></p>
	<?php
		$has_report = true;
	} else {
		$has_report = true;





		if (count($pos_yeast_bacterias) > 0) {
			$abx->pathogen_detected_notes = 'Fungi also detected; see last page.';
		}



		include('abx' . $abx_style . '.php');


		echo $abx_adding_html;
	} //end if rejected


	if (isset($_REQUEST['html'])) {
		echo stripslashes($footer_html);
	}


	if (isset($_GET['debugabx'])) {
		echo '<div class="logdebug">';
		include('abx/abx_debug_table.php');
		echo $abx->get_log();
		echo '<br><br>';
		echo $abx->logpair;
		if (is_array($abx->errors) && count($abx->errors) > 0) {
			echo '<br><br>errors:<br>';
			var_dump($abx->errors);
		}
		echo '</div>';
	}

	if ($has_style && !isset($_GET['debugabx']) && !isset($_GET['shownewabx'])) {

		$abx_type_num       = 1; //1: uti; 2: wound: 3: gi , 4:rpp
		if ($abx_type == 'wound') {
			$abx_type_num = 2;
		} else if ($abx_type == 'gi') {
			$abx_type_num = 3;
		} else if ($abx_type == 'r') {
			$abx_type_num = 4;
		} else if ($abx_type == 'v') {
			$abx_type_num = 5;
		} else if ($abx_type == 'ei') {
			$abx_type_num = 6;
		} else if ($abx_type == 'sti') {
			$abx_type_num = 7;
		} else if ($abx_type == 'nail') {
			$abx_type_num = 8;
		}
		$wpdb->update('ci_reports_pcr', array('has_uti' => $abx_type_num), array('id' => $report['id']));
	}

	if ($count_errors > 0) {
		update_option('ci_abx_last_change', time());
	}
	?>
	<?php if (tr_get_option('ci_pdf_data', 'new_abx_pagination')) include('_pdf_pagination.php'); ?>
</body>

</html>