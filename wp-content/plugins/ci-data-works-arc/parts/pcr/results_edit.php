<?php
global $wpdb, $report, $ci_cutoffs, $current_role;
global $rereun_by_both_rna_fail;


$btype			= 'pcr';
$id             = isset($_GET['id']) ? $_GET['id'] : $id;
$act			= isset($_GET['act']) ? $_GET['act'] : 'edit';
$user_id        = get_current_user_id();
$checked_types  = array();
$has_covid_panel = false;
$covid_tests    = trpdd_get_covid_tests('*');
$covidtests_str = implode("','", $covid_tests);
$main_panel_ids  = array();
$can_edit       = true;
$can_delete     = true;
$can_fax        = true;
$use_style      = 1;
$can_approve    = true;
$is_qc_form     = isset($_GET['qc']);
$has_pos        = null;
$has_rna_fail_pos = null;
$has_manual_antibiotic = false;
$has_manual2_antibiotic = 0;
$covid_pos      = null;
$result_ids     = array();

if ($act == 'view') $can_edit = false;

$all_drugs              = array();
$in_panel_tests_requested = array();
$all_test_groups        = array();
$group_ids              = array();
$is_rerun				= false;

$req_settings   = get_option('ci_req_settings_pcr', array());
$result_settings = get_option('ci_result_settings_pcr', array());

$comment_text_col = 'Comments';
if (isset($result_settings['disable_test_comments']) && $result_settings['disable_test_comments']) {
	$comment_text_col = 'Internal Comments';
}
if ($current_role == 'results') {
	if (tr_get_option('ci_pdf_data', 'results_oview')) {
		$can_edit     = false;
		$can_delete   = false;
		$can_fax      = false;
	}
}

include_once(trpdd_get_file_path($btype, 'actions'));

include_once(TRCPDD_PATH . 'pages/formdata.php');


$report = tr_pdd_get_report_by_id($id, $btype);


if (empty($report['id']) && isset($_GET['createnew'])) {
	tr_pcr_create_report();
}

if (empty($report['id'])) {
	echo '<h2>No Results Found!</h2>';
	return;
}

if (isset($_GET['debugpdf'])) {
	include(TRCPDD_PATH . 'reports/results_pcr.php');
}

$report['group_ids'] = array();
$report['group_pos_ids'] = array();

if ($report['cli_result_style_pcr'] > 1) {
	$use_style = $report['cli_result_style_pcr'];
}
$reflex_main_tests = tr_pdd_check_group_reflex($report, 'pcr');
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
	echo '$reflex_main_tests by reflex:';
	var_dump($reflex_main_tests);
	echo '<br><br>';
}

//prepare data
if (isset($result_settings['e_listby_plate']) && $result_settings['e_listby_plate']) {
	//check has WH
	if (isset($_GET['updatewh']) || $has_wh_group) {
		trpdd_pcr_check_update_WH($report);
	}
}



foreach (explode(',', $report['panel_tests_requested']) as $t) {
	if (stripos($t, 'group') === false) {
		if ($t > 0 && !in_array($t, $in_panel_tests_requested)) {
			$in_panel_tests_requested[] = $t;
		}
	} else {
		$group_ids[] = str_ireplace('group_', '', $t);
	}
}




if (empty($report['panel_tests_requested']) && empty($in_panel_tests_requested)) {
	$group_ids[] = -1;
}





$test_ids = implode(',', $in_panel_tests_requested);
if (empty($test_ids)) {
	$test_ids = '0';
}




$where_group 		= '';
$more_query_test 	= '';
$group_ids_str 		= '';

if (count($group_ids) > 0) {
	$group_ids_str 			= implode(',', $group_ids);
	$group_childs = $wpdb->get_col("select tg_ID from ci_test_groups g 
	where g.tg_parent in (select tg_ID from ci_test_groups g2 where g2.tg_ID in ({$group_ids_str}) 
							and (g2.tg_only_group = 1 or g2.tg_notestcheckbox= 1)) ");

	if (count($group_childs) > 0) {
		$group_ids = array_merge($group_ids, $group_childs);
		$group_ids_str 			= implode(',', $group_ids);
	}




	$group_ids_str_w_childs = $wpdb->get_col("select tg_ID from ci_test_groups 
	where tg_ID in ({$group_ids_str}) or tg_parent in ({$group_ids_str}) ");

	$group_ids_str_w_childs = implode(',', $group_ids_str_w_childs);
	if (empty($group_ids_str_w_childs)) {
		$group_ids_str_w_childs = '-1';
	}

	$group_ids_only_ornocheckboxes = $wpdb->get_col("select tg_ID from ci_test_groups 
	where (tg_ID in ({$group_ids_str}) or tg_parent in ({$group_ids_str})  )
	and (tg_only_group = 1 or tg_notestcheckbox= 1) ");

	$group_ids_only_ornocheckboxes = implode(',', $group_ids_only_ornocheckboxes);
	if (empty($group_ids_only_ornocheckboxes)) {
		$group_ids_only_ornocheckboxes = '-1';
	}

	if (isset($_GET['debug44'])) {
		var_dump($group_ids_str);
		echo '$group_childs: <br><br>';
		var_dump($group_childs);
		echo '<br><br>$group_ids_str_w_childs:';
		var_dump($group_ids_str_w_childs);
		echo '<br><br>$group_ids_only_ornocheckboxes:';
		var_dump($group_ids_only_ornocheckboxes);
		echo '<br><br>';
		echo '<br><br>$test_ids: ';
		var_dump($test_ids);
	}

	$where_group .= " or ( tg_ID in ({$group_ids_only_ornocheckboxes}) )";
	$where_group .= " or ( tg_parent in ({$group_ids_only_ornocheckboxes}) )";
	$more_query_test .= " or (tp.te_nocheckbox=1 and tp.te_group_id in ({$group_ids_str_w_childs}))";
	$more_query_test .= " or (tp.te_is_control=1 and tp.te_group_id in ({$group_ids_str_w_childs}))";
	//te_is_control=1 : control
}

$groups_ids_selected 	= $group_ids;



$result_adding_infile = array();
$all_res        = array();
$all_results_q  = array();
$date_test      = date('Y-m-d', strtotime($report['created']));
$where_testdate = " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
$where_testdate .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";


$drugkeys       = array('te_abrev2', 'te_abrev', 'te_name');


$query_control_by = '';
if (isset($req_settings['enabel_test_control_by']) && $req_settings['enabel_test_control_by']) {
	$query_control_by = " or (te_control_bys!='' and te_control_bys is not null) ";

	//get test has control by
	$control_by_tests = $wpdb->get_results("select * from ci_test_panels 
	where te_type='pcr' 
	and te_ID in ({$test_ids})
	and (te_control_bys !='' and te_control_bys is not null) ", ARRAY_A);
	foreach ($control_by_tests as $arr) {
		$arr['drug'] = strtoupper($arr['te_name']);
		$arr['dbdrug'] = $arr['drug'];
		$arr['report_id'] = $id;
		$all_res[$arr['te_ID']] = $arr;
	}
}


$found_rs_ids   = array();
$found_rs_drugs   = array();
foreach ($drugkeys as $drugkey) {
	$sql = "SELECT *
        FROM ci_results_pcr rs 
        join ci_test_panels tp ON tp.te_type='pcr' and  tp.{$drugkey} = rs.drug and rs.drug!=''
        WHERE report_id='" . $id . "' 
        and (tp.te_ID in ({$test_ids}) 
            or tp.te_group_id in (select tg_ID from ci_test_groups where (tg_aw_results = 1 and tg_ID in ({$group_ids_str})) {$where_group})
             {$more_query_test}
            )
        {$where_testdate}
        ";

	$all_results_q = $wpdb->get_results($sql, ARRAY_A);

	if (isset($_GET['debug22'])) {
		echo $sql;
		echo '<br><br>';
	}

	foreach ($all_results_q as $arr) {
		$found_rs_ids[] = $arr['id'];
		$found_rs_drugs[] = strtoupper($arr['te_name']);
		if (!empty($arr['te_abrev'])) {
			$found_rs_drugs[] = strtoupper($arr['te_abrev']);
		}
		if (!empty($arr['te_abrev2'])) {
			$found_rs_drugs[] = strtoupper($arr['te_abrev2']);
		}

		if (isset($all_res[$arr['te_ID']]) && strlen($arr['conc']) == 0 && empty($arr['pos-neg'])) {
			continue;
		}
		//remove old one
		if (isset($all_res[$arr['te_ID']])) {
			$oldid = $all_res[$arr['te_ID']]['id'];
			$wpdb->query("delete from ci_results_pcr where id = {$oldid} 
			and (conc = '' or conc is null)
			and (`pos-neg` ='' or `pos-neg` is null)");
		}
		//end remove

		$found_test_ids[] = $arr['te_ID'];

		$arr['dbdrug'] = $arr['drug'];
		$arr['drug'] = strtoupper($arr['te_name']);
		$all_res[$arr['te_ID']] = $arr;
		if (!in_array($arr['te_ID'], $in_panel_tests_requested)) {
			$in_panel_tests_requested[] = $arr['te_ID'];
		}

		if (!in_array($arr['te_group_id'], $group_ids)) {
			$group_ids[] = $arr['te_group_id'];
		}
	}
}

//get tests imported not ordered
if (tr_get_option('ci_result_settings_pcr', 'edit_show_iptest') && count($found_rs_ids) > 0) {
	$result_adding_infile = $wpdb->get_results("select * from ci_results_pcr rs 
	where report_id = {$id}
	and id not in (" . implode(',', $found_rs_ids) . ")
	and conc is not null ", ARRAY_A);
}

//get control
if (!empty($group_ids_str_w_childs)) {
	foreach ($drugkeys as $drugkey) {
		$sql = "SELECT *
			FROM ci_test_panels tp
			left join ci_results_pcr rs  ON tp.te_type='pcr' and  tp.{$drugkey} = rs.drug and rs.drug!='' and report_id='" . $id . "' 
			WHERE 
			 (
				tp.te_is_control=1 and tp.te_group_id in ({$group_ids_str_w_childs})
				)
			{$where_testdate}
			";

		$all_results_q = $wpdb->get_results($sql, ARRAY_A);

		if (isset($_GET['debug223'])) {
			echo $sql;
			echo '<br><br>';
		}

		foreach ($all_results_q as $arr) {
			if (isset($all_res[$arr['te_ID']]) && empty($arr['conc']) && empty($arr['pos-neg'])) {
				continue;
			}
			//remove old one
			if (isset($all_res[$arr['te_ID']])) {
				$oldid = $all_res[$arr['te_ID']]['id'];
				$wpdb->query("delete from ci_results_pcr where id = {$oldid} 
				and (conc = '' or conc is null)
				and (`pos-neg` ='' or `pos-neg` is null)");
			}
			//end remove

			$found_test_ids[] = $arr['te_ID'];

			$arr['dbdrug'] = $arr['drug'];
			$arr['drug'] = strtoupper($arr['te_name']);
			$all_res[$arr['te_ID']] = $arr;
		}
	}
}

//var_dump($all_res);exit;


//check manual group
$has_test_ids = array_keys($all_res);
$has_test_ids = implode(',', $has_test_ids);
if (empty($has_test_ids)) $has_test_ids = '0';

$manualedit_groups = $wpdb->get_col("select tg_ID 
from ci_test_groups 
where ( tg_ID in ({$group_ids_str}) and tg_manual_edit = 1 )
or tg_parent in (select tg_ID from ci_test_groups where tg_ID in ({$group_ids_str})  and tg_manual_edit = 1) ");

if (isset($_GET['debug_manual'])) {
	echo 'debug_manual<br>';
	var_dump($group_ids_str);
	echo '<br><br>';
	var_dump($manualedit_groups);
	echo '<br><br><br>';
}


if (count($manualedit_groups) > 0) {
	$manualedit_groups_str = implode(',', $manualedit_groups);


	$sql = "SELECT *
	FROM ci_test_panels tp 
	JOIN ci_test_groups tg ON tp.te_group_id = tg.tg_ID
	left join ci_results_pcr rs ON  tp.te_name = rs.drug and report_id='" . $id . "'
	WHERE  tp.te_type='pcr' and rs.id is null
	and tp.te_ID not in ({$has_test_ids}) 
	and (
			tp.te_group_id in  ({$manualedit_groups_str}) 
			{$query_control_by}
		)
	and (
		tp.te_group_id in (select tg_ID from ci_test_groups where (tg_only_group = 1 or tg_notestcheckbox= 1) and tg_ID in ({$manualedit_groups_str}) ) 
		or tg.tg_parent in (select tg_ID from ci_test_groups where (tg_only_group = 1 or tg_notestcheckbox= 1) and tg_ID in ({$manualedit_groups_str}) ) 
		OR (tp.te_nocheckbox = 1 and tp.te_group_id in  ({$manualedit_groups_str}) )
		or tp.te_ID in ({$test_ids})
	)
	{$where_testdate}
	";

	$all_results_q = $wpdb->get_results($sql, ARRAY_A);

	if (isset($_GET['debug_manual'])) {
		echo '<br><br>';
		var_dump($sql);
		echo '<br><br>';
		echo count($all_results_q);
		echo '<br><br>';
		echo '<br><br>';
	}


	foreach ($all_results_q as $arr) {
		if (isset($all_res[$arr['te_ID']])) {
			continue;
		}
		$arr['dbdrug'] = strtoupper($arr['drug']);
		$arr['drug'] = strtoupper($arr['te_name']);
		$arr['te_abrev'] = empty($arr['te_abrev']) ? $arr['te_name'] : $arr['te_abrev'];
		$arr['conc'] = '';
		$arr['pos-neg'] = '';
		if (!in_array($arr['te_ID'], $in_panel_tests_requested)) {
			$in_panel_tests_requested[] = $arr['te_ID'];
		}


		//add rs
		if (empty($arr['id'])) {
			$arr['id'] = $wpdb->get_var("select id from ci_results_pcr 
							where report_id = {$report['id']}
							 and drug = '{$arr['te_abrev']}'
							 and group_id = {$arr['te_group_id']} ");
		}
		if (empty($arr['id'])) {

			$wpdb->insert(
				'ci_results_pcr',
				array(
					'report_id' => $report['id'],
					'drug' => strtoupper($arr['te_abrev']),
					'group_id' => $arr['te_group_id']
				)
			);
			$arr['id'] = $wpdb->insert_id;
		}

		$all_res[$arr['te_ID']] = $arr;

		if (!in_array($arr['te_group_id'], $group_ids)) {
			$group_ids[] = $arr['te_group_id'];
		}
	}
}



if (isset($_GET['add_alltests']) || isset($_GET['add_all_tests'])) {
	$all_res = tr_pcr_result_add_all_results($all_res, $report, $test_ids, $group_ids);
}

if (isset($_GET['add_covid_demo']) && $has_covid_panel) {
	foreach ($covid_tests as $t) {
		$exists = $wpdb->get_var("select id from ci_results_pcr where report_id = {$id} and drug = '{$t}'");
		if (!$exists) {
			$wpdb->insert('ci_results_pcr', array(
				'report_id' => $id,
				'drug' => $t,
				'conc' => 40
			));
		}
	}
}

if (isset($_GET['createddate'])) {
	$report['created_date'] = $_GET['createddate'];
}



foreach ($in_panel_tests_requested as $t) {
	$t = strtoupper($t);
	$miss_test[$t] = $t;
}


$drug_groups = tr_pdd_get_drug_groups('pcr', $group_ids, true);


//find Antibiotic Resistance
$reflex_pass = array();
$antibiotic_resitance_id = 0;
foreach ($drug_groups as $groupid => $groupdata) {
	if ($groupdata['name'] == 'Antibiotic Resistance') {
		$antibiotic_resitance_id = $groupid;
	}

	if (!empty($groupdata['reflex_ID'])) {
		foreach ($groupdata['reflex_ID'] as $rlid) {
			if ($rlid > 0) {
				$reflex_pass[$rlid] = false;
			}
		}
	}
	$drug_groups[$groupid]['subgroups'] = array();
}
if ($antibiotic_resitance_id == 0) {
	$drug_groups['Antibiotic Resistance'] = array('name' => 'Antibiotic Resistance', 'drugs' => array());
}

if (empty($report['instrument'])) {
	//Tr_Session_Class::add_error("Empty Instrument");
	//get from file

	$all_instruments = $wpdb->get_col("select instrument from `ci_instruments` where btype='pcr' ");
	$all_instruments = array_map("strtoupper", $all_instruments);


	$fname = $wpdb->get_var("select upload_files from ci_reports_pcr where id = {$report['id']}");

	if (!empty($fname)) {
		foreach ($all_instruments as $mc) {
			if (stripos($fname, $mc) > 0) {
				$report['instrument'] = substr(strtoupper($mc), 0, 15);
				$wpdb->update('ci_reports_pcr', array('instrument' => $report['instrument']), array('id' => $report['id']));
				break;
			}
		}
	}
}




$inew 			= 0;
$all_groups 	= array();
$report['groups'] = array();

foreach ($drug_groups as $groupid => $groupdata) {
	$groupdata['p'] = null;
	if (isset($groupdata['parent']) && $groupdata['parent'] > 0 && isset($drug_groups[$groupdata['parent']])) {
		$groupdata['p'] = $drug_groups[$groupdata['parent']];

		if (isset($groupdata['p']['other_options']['default_neg']) && empty($groupdata['other_options']['default_neg']) && $groupdata['p']['other_options']['default_neg']) {
			$groupdata['other_options']['default_neg'] = $groupdata['p']['other_options']['default_neg'];
		}
	}

	$groupdata['id'] = isset($groupdata['id']) ? $groupdata['id'] : 0;
	$all_groups[$groupdata['id']] = $groupdata['name'];
	$rerun_test_ids = array();

	$group_types = array();
	if (!isset($groupdata['types']) || !is_array($groupdata['types'])) {
		$groupdata['types'] = array();
	}
	if (count($groupdata['types']) > 0) {
		foreach ($checked_types as $checked_type) {
			if (isset($groupdata['types'][$checked_type])) {
				$group_types[] = $groupdata['types'][$checked_type];
			}
		}
	}
	$groupdata['sample_type'] = implode(', ', $group_types);

	$te_IDs = isset($groupdata['te_IDs']) && is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();


	if ($groupdata['name'] == 'Other') {
		foreach ($all_res as $te_ID => $arr) {
			if (!isset($arr['showed'])) {
				$te_IDs[] = $te_ID;
			}
		}
	} else {
		if ($groupdata['name'] == 'Antibiotic Resistance') {
			foreach ($all_res as $te_ID => $arr) {
				if (isset($arr['antibiotic']) && $arr['antibiotic'] && (!isset($arr['showed']) || !$arr['showed'])) {
					$te_IDs[] = $te_ID;
				}
			}
		}
	}


	if (isset($groupdata['manual_antibiotic']) && $groupdata['manual_antibiotic']) {
		$has_manual_antibiotic = true;
	}

	if (isset($groupdata['other_options']['mantibiotic']) && $groupdata['other_options']['mantibiotic']) {
		$has_manual2_antibiotic = $groupid;
		if ($groupdata['parent'] > 0) {
			$has_manual2_antibiotic = $groupdata['parent'];
		}
	}



	$groupdata['tests'] = array();

	foreach ($te_IDs as $te_ID) {
		if (!isset($all_res[$te_ID]) && !$groupdata['is_covid']) {
			continue;
		}
		$arr = $all_res[$te_ID];

		if (isset($groupdata['other_options']['default_neg']) && $groupdata['other_options']['default_neg'] && empty($arr['pos-neg'])) {
			if ($arr['te_is_control'] && tr_get_option('ci_result_settings_pcr', 'default_empty_pos_ctrl')) {
				$arr['pos-neg'] = 'POS';
			} else if ($groupdata['other_options']['default_neg'] == 2) {
				$arr['old_pos-neg'] = $arr['pos-neg'];
				$arr['pos-neg'] = 'N/A';
			} else if ($groupdata['other_options']['default_neg'] == 1) {
				$arr['old_pos-neg'] = $arr['pos-neg'];
				$arr['pos-neg'] = 'NEG';
			}
		}

		$arr['report_id'] = $id;
		$arr['manual_antibiotic'] = $groupdata['manual_antibiotic'];
		if ($groupdata['name'] == 'nCoV-2019' || $groupdata['is_covid']) {

			if (empty($arr)) {
				$arr = $wpdb->get_row("select te.* from ci_test_panels te
			            where te.te_ID = {$te_ID} and te_type='pcr' ", ARRAY_A);
				$arr['drug'] = ($arr['te_name']);
				$arr['id'] = 'new' . $inew++;
			}
			if (empty($report['instrument'])) {
				$cvtid = $arr['te_ID'];
				$istm = '';
				if ($cvtid > 0) {
					$istm = $wpdb->get_var("select cu_ms 
					from ci_test_panels_cutoffs 
					where cu_te_ID = {$cvtid} 
					and (cu_start is null or cu_start ='' or cu_start <= '{$report['doc']}' ) 
						 and (cu_end is null or cu_end = '' or cu_end > '{$report['doc']}')
						 limit 1");
				}


				if (!empty($istm)) {
					$istms = explode(',', $istm);
					foreach ($istms as $istm) {
						$istm = trim($istm);
						if (!empty($istm)) {
							$report['instrument'] = $istm;
							$wpdb->update('ci_reports_pcr', array('instrument' => $report['instrument']), array('id' => $report['id']));
							break;
						}
					}
				}
			}



			$old_posneg = $arr['pos-neg'];
			$arr        = trpdd_cal_ncovid19($arr, $id, $report['instrument'], $report['created_date']);
			$arr['showed'] = true;

			if ($arr['pos-neg'] == 'PEN' && !empty($old_posneg)) {
				$arr['pos-neg'] = $old_posneg;
			}
			$groupdata['tests'] = array();
			$groupdata['tests'][$arr['drug']] = $arr;
			$all_res[$te_ID] = $arr;

			if ($arr['id'] > 0) {
				$result_ids[] = $arr['id'];
			}


			$covid_pos      = '';
			if ($arr['pos-neg'] == 'POS') {
				$covid_pos = 1;
			} else if ($arr['pos-neg'] == 'RER') {
				$covid_pos = 2;
				$can_approve = false;
			} else if ($arr['pos-neg'] == 'INC') {
				$covid_pos = 4;
				$can_approve = false;
			} else if ($arr['pos-neg'] == 'NEG') {
				$covid_pos = 0;
			}

			break;
		} else if (!empty($arr)) {
			if (!empty($arr['te_name'])) {
				$arr['drug'] = $arr['te_name'];
			}
			$arr['showed'] = true;
			$arr['type_of_specimen'] = $report['type_of_specimen'];

			$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);


			$all_res[$te_ID] = $arr;

			if (!empty($groupdata['reflex_ID'])) {
				foreach ($groupdata['reflex_ID'] as $rlid) {
					if ($rlid > 0) {
						if ($rlid > 0  && isset($reflex_pass[$rlid])) {
							if ($reflex_pass[$rlid] === false) {
								$reflex_pass[$rlid] = 0;
							}

							if (in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVL', 'PVH'))) {
								$reflex_pass[$rlid]++;
							}
						}
					}
				}
			}



			if (
				!in_array($groupdata['name'], array('Antibiotic Resistance', 'ABX'))
				&& !$groupdata['antibiotic']
				&& isset($arr['antibiotic']) && $arr['antibiotic']
			) {
				unset($all_res[$te_ID]['showed']);
				continue;
			}


			if ($arr['notfound'] && empty($arr['range'])) {
				//Tr_Session_Class::add_error('Cutoff not found for : "'.$arr['drug'].'" ');
			}
			if (empty($arr['id'])) {
				$arr['id'] = 'new' . $inew++;
			} else if ($arr['id'] > 0) {
				$result_ids[] = $arr['id'];
			}

			if (in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PVH', 'PVL')) && !$arr['te_is_control']) {
				$has_rna_fail_pos = 1;
				if ((isset($reflex_pass[$groupid]) && $reflex_pass[$groupid] === 0) || $groupdata['antibiotic']) {
					//nothing
				} else {
					//var_dump($reflex_pass[$groupid]);
					$has_pos = 1;
					$groupdata['has_pos'] = 1;
				}
			} else if ($arr['pos-neg'] == 'NEG' && is_null($has_pos)) {
				$has_pos = 0;
			}

			if ($arr['pos-neg'] == 'RER') {
				$is_rerun = true;
			}

			$groupdata['tests'][$arr['drug']] = $arr;

			if (isset($arr['controls']) && count($arr['controls']) > 0) {
				foreach ($arr['controls'] as $ctrarr) {
					$groupdata['tests'][$ctrarr['drug']] = $ctrarr;
				}
			}
		}

		if (in_array($arr['te_ID'], $in_panel_tests_requested)) {
			$all_test_groups[] = $arr['drug'];
		}
	}

	if ($groupid > 0 && count($groupdata['tests']) > 0) {
		$report['group_ids'][] = $groupid;
	}

	if ($groupid > 0 && $groupdata['has_pos']) {
		$report['group_pos_ids'][] = $groupid;
	}

	if ($groupdata['name'] == 'nCoV-2019' || (isset($groupdata['is_covid']) && $groupdata['is_covid'])) {
		$in_panel_tests_requested[] = -11;
		foreach ($groupdata['tests'] as $dr => $arr) {
			if (!in_array($arr['te_ID'], $in_panel_tests_requested)) {
				$in_panel_tests_requested[] = $arr['te_ID'];
			}
		}
	}

	if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {
		$groupdata['parent_name'] = isset($all_groups[$groupdata['parent']]) ? $all_groups[$groupdata['parent']] : '';
		$main_panel_ids[$groupdata['parent']] = $groupdata['parent'];


		$drug_groups[$groupdata['parent']]['subgroups'][$groupid] = $groupdata;
	} else {
		$main_panel_ids[$groupid] = $groupid;
		$report['groups'][$groupid] = $groupdata;
	}

	$drug_groups[$groupid] = $groupdata;



	if (count($groupdata['tests']) > 0 && in_array($groupdata['id'], $groups_ids_selected)) {
		$all_test_groups[] = $groupid;
	}
}

$update_rp = array();

if ($covid_pos !== null) {
	$update_rp['covid_pos'] = $covid_pos;
}
if ($has_pos !== null) {
	$update_rp['has_pos'] = $has_pos;
	$update_rp['has_pos_modified'] = time();
}

if (isset($result_settings['e_reruntest']) && $result_settings['e_reruntest']) {

	if (isset($_GET['debugrerun'])) {
		echo 'rerun1: ' . ($is_rerun ? 1 : 0) . '<br>';
	}
	$is_rerun = $is_rerun || tr_pdd_check_rerun('pcr', $report);


	if (!isset($report['rerun_count']) || is_null($report['rerun_count'])) {
		$report['rerun_count'] = $wpdb->get_var("select rerun_count from ci_reports_pcr where id = {$report['id']} ");
	}

	if (!isset($report['rerun_modified'])) {
		$report['rerun_modified']  =  $wpdb->get_var("select rerun_modified from ci_reports_pcr where id = {$report['id']} ");
	}

	if ($is_rerun) {
		$can_approve = false;
	}

	if ($report['rerun_count'] == 0 && $is_rerun) {
		$update_rp['rerun_count'] = 1;
	} else if ($report['rerun_count'] > 0 && !$is_rerun) {
		$update_rp['rerun_count'] = -1;
	} else if ($report['rerun_count'] == -1 && $is_rerun) {
		$update_rp['rerun_count'] = 1;
	} else if ($is_rerun && $report['rerun_modified'] < $report['modified'] && $report['rerun_count'] >= 1) {
		$update_rp['rerun_count'] = $report['rerun_count'] + 1;
		$update_rp['rerun_modified'] = time() + 5;


		if ($update_rp['rerun_count'] >= 2) {
			$can_approve = true;
		}
	} else if ($report['rerun_count'] == 9) {
		$update_rp['rerun_count'] = 2;
	}
}
if (isset($_GET['debugrerun'])) {
	echo 'rerun: ' . ($is_rerun ? 1 : 0) . '<br>';
}


if ($is_rerun && $rereun_by_both_rna_fail && $has_rna_fail_pos) {

	if ($report['rerun_count'] == 1 || $update_rp['rerun_count'] == 1) {
		$update_rp['rerun_count'] = 2;
	}
} else if ($is_rerun && !$rereun_by_both_rna_fail &&  isset($result_settings['e_rerunttoinvalid']) && $result_settings['e_rerunttoinvalid']) {
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

if (count($update_rp) > 0) {
	$wpdb->update('ci_reports_pcr', $update_rp, array('id' => $id));
}

if (count($result_ids) > 0) {
	$result_ids_str = implode(',', $result_ids);
	$wpdb->query("update ci_results_pcr set has_order=0 
	                where report_id = {$id} 
	                and id not in ({$result_ids_str}) ");
}



if ($report['estatus'] == 'C') {
	$can_approve = false;
} else if ($is_rerun && $report['rerun_count'] >= 2) {
	$can_approve = true;
}


if (isset($is_clear_results) && $is_clear_results) {
	return;
}

if (isset($_REQUEST['debug'])) {
	var_dump($report);
	echo '<br><br>';
	var_dump($report['panel_tests_requested']);
	echo '<br><br>';
	echo 'Tests:';
	echo '<br><br>';
	var_export($in_panel_tests_requested);
	echo '<br><br>';
	echo '$all_res:';
	echo '<br><br>';
	var_export($all_groups);
	var_export($group_ids);
	echo '<br>$drug_groups<br>';
	var_dump($drug_groups);
}



$count_test_show = 0;
$count_all_test = count($in_panel_tests_requested);
$miss_test = array();
$csv_file = '';
if ($act != 'view') {
	$csv_files = !empty($report['upload_files']) ? explode(',', $report['upload_files']) : array();
	if (count($csv_files) > 0) {
		$filename = end($csv_files);
		$csv_file = '/wp-content/log/results_pcr/' . $filename;
		//check assen in file
		$csv_path = WP_CONTENT_DIR . '/log/results_pcr/' . $filename;
		if (stripos(file_get_contents($csv_path), $report['assen']) === false) {
			//search files
			$files = scandir(WP_CONTENT_DIR . '/log/results_pcr/');
			foreach ($files as $f) {
				$filename2 = str_replace('.csv', '', $f);
				if (stripos($f, $filename2) !== false && $f != $filename) {
					$csv_path2 = WP_CONTENT_DIR . '/log/results_pcr/' . $f;
					if (stripos(file_get_contents($csv_path2), $report['assen']) !== false) {
						$csv_file = '/wp-content/log/results_pcr/' . $f;

						$upload_files_new = str_replace($filename, $f, $report['upload_files']);
						$wpdb->update('ci_reports_pcr', array('upload_files' => $upload_files_new), array('id' => $report['id']));
						break;
					}
				}
			}
		}
	}
}

$req_form_url = 'admin.php?page=pcr_req_form&act=edit&id=' . $report['mid'];
?>

<style>
	.rowline .frow {
		border-bottom: 1px solid #ddd;
		padding-bottom: 4px;
		padding-top: 4px;
		margin-bottom: 0;
	}

	.in_medications {
		background: #80d4ff
	}

	.in_medications input,
	.in_medications textarea {
		background: rgba(128, 212, 255, 0.9);
	}

	.bg_red,
	.bg_red input,
	.bg_red textarea {
		background: #ff8080;
	}

	.rs_note {
		color: red;
		font-weight: 600;
	}

	<?php
	if ($act == 'view') {
	?>.frow h4,
	.frow.hlabel>div>label:first-child,
	body div .eStore_cart_fancy1_header {
		line-height: 31px;
	}

	.frow input[type="text"],
	.frow input[type="password"],
	.frow input[type="number"],
	.frow input[type="date"],
	.frow input[type="email"],
	.frow select,
	.frow textarea {
		height: 28px;
		font-size: 12px;
		border: none;
		font-weight: 700;
		pointer-events: none;
		white-space: normal;
	}

	.tbl_results td input[type="text"],
	.tbl_results td textarea {
		height: 28px;
		font-size: 12px;
		border: none;
		pointer-events: none;
		background-color: #f6f6f6;
		white-space: normal;
	}

	tr.group_header {
		background: #ddd;
		height: 30px !important;
	}

	#TB_window #TB_title {
		height: 30px;
	}

	/* #TB_window {
		position: fixed !important;
		inset: 20px !important;
		width: auto !important;
		height: auto !important;
		margin: 0 !important;
		transform: none !important;

		background: #fff;
		box-shadow: 0 8px 24px rgba(0, 0, 0, .25);
	} */

	#TB_ajaxContent,
	#TB_iframeContent {
		width: 100% !important;
		height: 100% !important;
		box-sizing: border-box;
	}

	.header_fix {
		display: none;
	}

	.group_header th strong {
		padding-left: 3px
	}

	.wp-core-ui select.disabled,
	.wp-core-ui select:disabled {
		color: black;
		border: none;
		background-image: none;
		font-size: 12px;
	}

	<?php } ?>
</style>

<div class="wrap">

	<?php if ($act == 'edit') { ?>
		<h2>Edit <span style="float:right">Style: <?php echo $use_style ?></span></h2>
	<?php } else if (empty($GLOBALS['tr_results_quick_view'])) { ?>
		<div class="rs_note">Note: Displays current values in the system. These may differ from values at the time of release if any data was updated after reporting.</div>
	<?php } ?>
	<?php Tr_Session_Class::show() ?>

	<form method="post">

		<div class="frow">
			<h4>Patient Info</h4>
		</div>

		<div class="frow">
			<div class="col4">
				<label title="<?php echo $report['mid'] ?>">Patient Name</label>
				<input type="text" value="<?php echo tr_pdd_capitalized($report['patient_name']) ?>" readonly>
			</div>
			<div class="col4">
				<label class="lblock"><span>Physician</span> &nbsp; <small><?php echo $report['doc_npi'] ?></small></label>
				<input type="text" value="<?php echo $report['physician'] ?>" readonly>
			</div>

			<div class="col4">
				<label>Patient ID <a target="_blank" href="<?php echo $req_form_url ?>">.</a></label>
				<input type="text" value="<?php echo $report['assen'] ?>" readonly />
			</div>
			<div class="col4">
				<label>Lab</label>
				<input type="text" value="<?php echo $report['sublab'] ?>" readonly>
			</div>
		</div>

		<div class="frow">
			<div class="col4">
				<label>Patient DOB</label>
				<input type="text" placeholder="YYYY-mm-dd" value="<?php echo $report['patient_dob'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Patient Gender</label>
				<input type="text" value="<?php echo $report['pa_gender'] ?>">
			</div>

			<div class="col4">
				<label>Acquisition Date</label>
				<input type="text" class="" name="info[acquisition_date]" placeholder="YYYY-mm-dd" value="<?php echo $report['acquisition_date'] ?>">
			</div>
			<div class="col4">
				<label>Sample Type</label>
				<input type="text" value="<?php echo $report['sample_type'] ?>" readonly>
			</div>
		</div>

		<div class="frow">
			<div class="col4">
				<label>Collection Date</label>
				<input type="text" value="<?php echo $report['collection_date'] ?>" readonly>
			</div>
			<div class="col4">
				<label>Received in Lab</label>
				<input type="text" class="<?php if (!tr_get_option('ci_pdf_data', 'receiving_noedit')) echo tr_get_option('ci_pdf_data', 'receiving_offtime') ? 'hasdate' : 'hasdatetime' ?>" name="updatebat[spec_received]" value="<?php echo $report['spec_received'] ?>" <?php if (tr_get_option('ci_pdf_data', 'receiving_noedit')) echo 'readonly' ?> />
			</div>
			<div class="col4">
				<label>Report Date</label>
				<input type="text" class="hasdatetime" name="info[approve_date]" value="<?php echo $report['approve_date'] ?>" />
			</div>
			<div class="col4">
				<label>Instrument <?php if (!empty($csv_file)) echo '<a target="_blank" href="' . $csv_file . '" style="text-decoration: none;"><span style="font-size:14px" class="dashicons dashicons-format-aside"></span></a>' ?></label>
				<input type="text" value="<?php echo $report['instrument'] ?>" name="info[instrument]">
			</div>
		</div>
		<div class="header_fix_top"></div>
		<div class="header_fix" style="display: none;">
			<span><tt>P:</tt> <?php echo $report['patient_name'] ?> </span>
			<span><tt>D:</tt> <?php echo $report['physician'] ?></span>
			<span><tt>A:</tt> <a class="show_test_panels" href="#"><?php echo $report['patient_id'] ?></a></span>
			<span><tt>L:</tt> <?php echo $report['sublab'] ?></span>

			<span><tt>C:</tt> <?php echo $report['collection_date'] ?></span>
			<span><tt>M:</tt> <?php echo $report['medications'] ?></span>
			<span><a target="_blank" href="?page=pcr_req_form&act=edit&id=<?php echo $report['mid'] ?>">.</a></span>
		</div>
		<div class="list_test_panels" style="display: none;">
			<div><?php echo implode(', ', $all_test_groups); ?></div>
		</div>


		<?php
		if (count($in_panel_tests_requested) == 0 && count($group_ids) == 0) {
		?>
			<h1 title="<?php echo count($in_panel_tests_requested) ?>" style="color: red;">No Custom Profile/No Tests Requested Otherwise</h1>
		<?php
		} else if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
			$rejected = tr_pdd_get_batch_statuses($report['batch_status']);
		?>
			<h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
			<div style="text-align: center;"><?php echo $report['billing_notes'] ?></div>
		<?php
		} else {

			include(trpdd_get_file_path('pcr', 'results_edit_mic'));
		?>


			<div class="frow">
				<h4>&nbsp;</h4>
			</div>


			<?php

			if (count($result_adding_infile) > 0) {
				ob_start();
				$has_content_missing = false;
			?>
				<table style="width:100%" class="tbl_results">
					<thead>
						<tr class="group_header">
							<th style="width:25%"><strong>Test</strong></th>
							<th style="width:15%"><strong>Result</strong></th>
							<?php
							if (isset($is_amp_cq) && $is_amp_cq) {
								echo '<th  style="width:8%"><strong>CT</strong></th>';
								echo '<th  style="width:8%"><strong>Amp Score</strong></th>';
								echo '<th  style="width:8%"><strong>Cq Confidence</strong></th>';
							} else {
								echo '<th  style="width:20%"><strong>CT (ng/mL)</strong></th>';
							}
							if (isset($group_e_percent_positive) && $group_e_percent_positive) {
								echo '<th  style="width:8%"><strong>% Positive</strong></th>';
							}
							?>
							<th style="width:12%"><strong>Range</strong></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ($result_adding_infile as $arr) {
							$result_row_class = 'isred';
							if (in_array(strtoupper($arr['drug']), $found_rs_drugs)) {
								continue;
							}
							$has_content_missing = true;
						?>
							<tr class="crow result_row <?php echo $result_row_class ?>">
								<td>
									<input type="text" value="<?php echo strip_tags($arr['drug']) ?>" readonly />
								</td>
								<td></td>
								<?php
								if (isset($is_amp_cq) && $is_amp_cq) {
								?>
									<td>
										<?php if (!isset($arr['ismain'])) { ?>
											<input type="text" class="conc " value="<?php echo $arr['conc'] ?>" readonly />
										<?php } ?>
									</td>
									<td>
										<?php if (!isset($arr['ismain'])) { ?>
											<input type="text" class="amp_score " readonly />
										<?php } ?>
									</td>
									<td>
										<?php if (!isset($arr['ismain'])) { ?>
											<input type="text" class="cq " value="<?php echo $arr['cq'] ?>" readonly />
										<?php } ?>
									</td>
								<?php
								} else {
								?>
									<td>
										<input type="text" class="conc" value="<?php echo $arr['conc'] ?>" readonly />
									</td>
								<?php
								}
								if (isset($group_e_percent_positive) && $group_e_percent_positive) {
								?>
									<td>
										<?php if (!isset($arr['ismain'])) { ?>
											<input type="text" class="percent_positive" value="<?php echo $arr['percent_positive'] ?>" readonly />
										<?php } ?>
									</td>
								<?php
								}
								?>
								<td class="center">

								</td>
								<td></td>
							</tr>
						<?php
						}
						?>
					</tbody>
				</table>
				<?php

				$content_missing = ob_get_clean();
				if ($has_content_missing) {
					echo $content_missing;
				}
			}


			$show_group_parents = array();

			foreach ($drug_groups as $groupid => $groupdata) {

				$group_e_percent_positive = isset($groupdata['other_options']['percent_positive']) && $groupdata['other_options']['percent_positive'];
				if (!$group_e_percent_positive && isset($groupdata['parent']) && $groupdata['parent'] > 0) {
					$parentopt = $drug_groups[$groupdata['parent']]['other_options'];
					$group_e_percent_positive = isset($parentopt['percent_positive']) && $parentopt['percent_positive'];
				}

				$is_amp_cq = tr_get_option('ci_result_settings_pcr', 'enable_rs_ct_amp_cq');
				if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {
					$parentopt = $drug_groups[$groupdata['parent']];

					if (isset($parentopt['other_options']['is_rpp'])) {
						if ($parentopt['other_options']['is_rpp'] == 2) {
							$is_amp_cq = false;
						} else if ($parentopt['other_options']['is_rpp']) {
							$is_amp_cq = true;
						}
					}
				} else if (isset($groupdata['other_options']['is_rpp'])) {
					if ($groupdata['other_options']['is_rpp'] == 2) {
						$is_amp_cq = false;
					} else if ($groupdata['other_options']['is_rpp']) {
						$is_amp_cq = true;
					}
				}
				if (isset($_GET['dd3'])) {
					var_dump($groupid);
					if ($groupid == 6) {
						var_dump($groupdata['tests']);
					}
				}
				ob_start();
				foreach ($groupdata['tests'] as $arr) {
					$count_test_show++;
					unset($miss_test[$arr['drug']]);

					if (isset($arr['is_covid']) && $arr['is_covid']) {

						if ($arr['pos-neg'] == 'PEN' && !$groupdata['manual_edit']) {
							continue;
						}
					}


					$count_test_show++;
					unset($miss_test[$arr['drug']]);

					if (empty($arr['tt'])) {
						$arr['tt'] = $arr['te_abrev'];
					}

					$preview_arr2 = '';
					if (isset($arr['value2'])) {
						$preview_arr2 = 'ct: ' . $arr['value2']['conc'];
						$preview_arr2 .= '| amp: ' . $arr['value2']['amp_score'];
						$preview_arr2 .= '| cq: ' . $arr['value2']['cq'];
						$preview_arr2 .= '| result: ' . $arr['value2']['pos-neg'];
					}

					if (($groupdata['name'] == 'nCoV-2019' || $groupdata['is_covid']) && empty($arr['list'])) {
						if (empty($arr['id'])) {
							$arr['id'] = 'newcovid';
						}
					}

					$result_row_class = '';

					if ($arr['te_is_control']) {
						$result_row_class .= ' hide_pdf ';
					} else if (!$arr['te_in_req']) {
						continue;
					} else if ($arr['te_hide_pdf']) {
						$result_row_class .= ' hide_pdf ';
					}

					if ($arr['te_is_control']) {
						if ($arr['pos-neg'] == 'NEG') {
							if (stripos($arr['te_abrev'], 'RNAseP') !== false && stripos($report['type_of_specimen'], 'Urine') !== false) {
								//nothing
							} else {
								$result_row_class .= ' isred ';
							}
						}
					} else if (in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PVL', 'PVH'))) {
						$result_row_class .= ' isred ';
					}
					if (isset($arr['ismain'])) {
						$result_row_class .= ' bordertop ';
					}
					$arr['cutoffs'] = array(
						'PVL' => isset($arr['vlow_1']) ? trpdd_float_val($arr['vlow_1']) : '',
						'POL' => isset($arr['low_1']) ? trpdd_float_val($arr['low_1']) : '',
						'POM' => isset($arr['medium_1']) ? trpdd_float_val($arr['medium_1']) : '',
						'POH' => isset($arr['high_1']) ? trpdd_float_val($arr['high_1']) : '',
						'PVH' => isset($arr['vhigh_1']) ? trpdd_float_val($arr['vhigh_1']) : '',
					);

				?>
					<tr class="crow result_row <?php echo $result_row_class ?>" data-cutoffs="<?php echo htmlspecialchars(json_encode($arr['cutoffs'])) ?>">
						<td title="<?php echo isset($arr['tt']) ? $arr['tt'] : '' ?>">
							<input type="text" <?php if (stripos($arr['id'], 'new') !== false) echo 'name="data_results[' . $arr['id'] . '][drug]"' ?> value="<?php echo strip_tags($arr['drug']) ?>" readonly />
							<input type="hidden" <?php if (stripos($arr['id'], 'new') !== false) echo 'name="data_results[' . $arr['id'] . '][group_id]"' ?> value="<?php echo $groupid ?>" readonly />
						</td>
						<td>
							<?php if (isset($arr['ismain'])) {
								echo '<input type="text" title="' . $arr['count_pos'] . '" readonly class=" ' . ($arr['pos-neg'] == 'POS' ? 'red' : '') . '" value="' . $arr['flag'] . '">';
							} else { ?>
								<select class="pos_neg" <?php if (!$can_edit) echo 'disabled' ?> name="data_results[<?php echo $arr['id'] ?>][pos-neg]" data-value="<?php echo $arr['pos-neg'] ?>"
									<?php if (isset($arr['is_blue']) && $arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>>
									<?php
									foreach ($arr['pos_neg_list'] as $k => $vl) {

										$selected = $k == $arr['pos-neg'] ? 'selected="selected"' : '';
										echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
									}
									?>
								</select>
							<?php } ?>
						</td>
						<?php
						if ($is_amp_cq) {
						?>
							<td>
								<?php if (!isset($arr['ismain'])) { ?>
									<input type="text" class="conc <?php echo (in_array($arr['ct_pos_neg'], array('POS', 'PVL', 'POL', 'POM', 'POH', 'PVH'))) ? 'red' : 'green' ?>" name="data_results[<?php echo $arr['id'] ?>][conc]" value="<?php echo $arr['conc'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
								<?php } else {
									echo '<input type="text" class="conc" value="' . $arr['conc_avg'] . '" readonly />';
								} ?>
							</td>
							<td>
								<?php if (!isset($arr['ismain'])) { ?>
									<input type="text" class="amp_score <?php echo ($arr['amp_pos_neg'] == 'POS') ? 'red' : 'green' ?>" name="data_results[<?php echo $arr['id'] ?>][amp_score]" value="<?php echo $arr['amp_score'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
								<?php } ?>
							</td>
							<td>
								<?php if (!isset($arr['ismain'])) { ?>
									<input type="text" class="cq <?php echo ($arr['cq_pos_neg'] == 'POS') ? 'red' : 'green' ?>" name="data_results[<?php echo $arr['id'] ?>][cq]" value="<?php echo $arr['cq'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
								<?php } ?>
							</td>
						<?php
						} else {
						?>
							<td>
								<input type="text" class="conc" name="data_results[<?php echo $arr['id'] ?>][conc]" value="<?php echo $arr['conc'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
							</td>
						<?php
						}
						if ($group_e_percent_positive) {
						?>
							<td>
								<?php if (!isset($arr['ismain'])) { ?>
									<input type="text" class="percent_positive" name="data_results[<?php echo $arr['id'] ?>][percent_positive]" value="<?php echo $arr['percent_positive'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
								<?php } ?>
							</td>
						<?php

							//remove duplicate
							$drugremove = $arr['te_abrev'];
							if (stripos($arr['drug'], '-') > 0) {
								list($temp1, $drmk) = explode('-', $arr['drug']);
								$drugremove .= '-' . $drmk;
							}
							$duprs = $wpdb->get_row("select * from ci_results_pcr where report_id = {$report['id']} 
							and id!={$arr['id']}  and drug = '{$drugremove}' ");

							if ($duprs) {
								$wpdb->delete('ci_results_pcr', array('id' => $duprs->id));
								if (empty($arr['percent_positive']) && !empty($duprs->percent_positive)) {
									$wpdb->update('ci_results_pcr', array('percent_positive' => $duprs->percent_positive), array('id' => $arr['id']));
								}
							}
							$wpdb->query("delete from ci_results_pcr where report_id = {$report['id']} 
							and id!={$arr['id']}  and drug = '{$arr['drug']}' ");
						}
						?>
						<td class="center" title="<?php echo $preview_arr2 ?>">
							<?php echo trim($arr['range']) ?>
						</td>

						<td data-did="<?php echo isset($arr['drug_comment_id']) ? $arr['drug_comment_id'] : '' ?>">
							<textarea name="data_results[<?php echo $arr['id'] ?>][comments]" data-posc="<?php echo isset($arr['posc']) ? $arr['posc'] : '' ?>" <?php if (!$can_edit) echo 'readonly' ?>><?php echo $arr['comments'] ?></textarea>
						</td>
					</tr>
					<?php

					if ($groupdata['name'] == 'nCoV-2019' || $groupdata['is_covid']) {
						if (is_array($arr['list']) && !empty($arr['list'])) {
							$testscv = $arr['list'];
						} else {
							$testscv = $wpdb->get_results(
								"select * from ci_results_pcr where report_id = {$id}
                                    and drug in ('{$covidtests_str}') ",
								ARRAY_A
							);
						}

						foreach ($testscv as $arr2) {
							if (empty($arr2)) continue;
					?>
							<tr>
								<td><input type="text" <?php echo 'name="data_results[' . $arr2['id'] . '][drug]"' ?> readonly value="<?php echo $arr2['drug'] ?>" /> </td>
								<td><?php echo ($arr2['pos-neg']) == 'POS' ? '.' : '-' ?></td>
								<td><input type="text" <?php echo 'name="data_results[' . $arr2['id'] . '][conc]"' ?> <?php if (!$can_edit) echo 'readonly' ?> value="<?php echo $arr2['conc'] ?>" /> </td>
								<td></td>
								<td></td>
							</tr>
						<?php
						}
					} else if (isset($arr['list']) && count($arr['list']) > 0) {
						foreach ($arr['list'] as $arr2) {
							if (empty($arr2)) continue;
							if (!isset($arr2['pos_neg_list'])) {
								$arr2['pos_neg_list'] = $arr['pos_neg_list'];
							}

							$result_row_class = '';
							if ($arr2['te_hide_pdf']) {
								$result_row_class .= ' hide_pdf ';
							} else if (!$arr2['te_in_req']) {
								continue;
							}
						?>

							<tr class="crow result_row <?php echo $result_row_class ?>">
								<td title="<?php echo  $arr2['dbdrug'] ?>">
									<?php if (!isset($arr2['hidedrug'])) { ?>
										<input type="text" <?php if (stripos($arr2['id'], 'new') !== false) echo 'name="data_results[' . $arr2['id'] . '][drug]"' ?> value="<?php echo $arr2['drug'] ?>" readonly />
									<?php } ?>
								</td>
								<td>
									<select class="pos_neg" <?php if (!$can_edit) echo 'disabled' ?> name="data_results[<?php echo $arr2['id'] ?>][pos-neg]" <?php if (isset($arr2['is_blue']) && $arr2['is_blue'] && $arr2['pos-neg'] == 'NEG')
																																									echo 'style="font-weight:600"' ?>>
										<?php
										foreach ($arr2['pos_neg_list'] as $k => $vl) {

											$selected = $k == $arr2['pos-neg'] ? 'selected="selected"' : '';
											echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
										}
										?>
									</select>
								</td>
								<?php
								if ($is_amp_cq) {
								?>
									<td>
										<input type="text" class="conc <?php echo (in_array($arr2['ct_pos_neg'], array('POS', 'POL', 'POM', 'POH', 'PCH', 'PCL', 'PVH', 'PVL'))) ? 'red' : 'green' ?>" name="data_results[<?php echo $arr2['id'] ?>][conc]" value="<?php echo $arr2['conc'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
									<td>
										<input type="text" class="amp_score <?php echo (in_array($arr2['amp_pos_neg'], array('POS', 'POL', 'POM', 'POH', 'PCH', 'PCL', 'PVH', 'PVL'))) ? 'red' : 'green' ?>" name="data_results[<?php echo $arr2['id'] ?>][amp_score]" value="<?php echo $arr2['amp_score'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
									<td>
										<input type="text" class="cq <?php echo (in_array($arr2['cq_pos_neg'], array('POS', 'POL', 'POM', 'POH', 'PCH', 'PCL', 'PVH', 'PVL'))) ? 'red' : 'green' ?>" name="data_results[<?php echo $arr2['id'] ?>][cq]" value="<?php echo $arr2['cq'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
								<?php
								} else {
								?>
									<td>
										<input type="text" class="conc" name="data_results[<?php echo $arr2['id'] ?>][conc]" value="<?php echo $arr2['conc'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
								<?php
								}
								if ($group_e_percent_positive) {
								?>
									<td>
										<input type="text" class="percent_positive" name="data_results[<?php echo $arr2['id'] ?>][percent_positive]" value="<?php echo $arr2['percent_positive'] ?>" <?php if (!$can_edit) echo 'readonly' ?> />
									</td>
								<?php

									//remove duplicate
									$drugremove = $arr2['te_abrev'];
									if (stripos($arr2['drug'], '-') > 0) {
										list($temp1, $drmk) = explode('-', $arr2['drug']);
										$drugremove .= '-' . $drmk;
									}
									$duprs = $wpdb->get_row("select * from ci_results_pcr where report_id = {$report['id']} 
									and id!={$arr2['id']}  and drug = '{$drugremove}' ");

									if ($duprs) {
										$wpdb->delete('ci_results_pcr', array('id' => $duprs->id));
										if (empty($arr2['percent_positive']) && !empty($duprs->percent_positive)) {
											$wpdb->update('ci_results_pcr', array('percent_positive' => $duprs->percent_positive), array('id' => $arr['id']));
										}
									}
									$wpdb->query("delete from ci_results_pcr where report_id = {$report['id']} 
									and id!={$arr2['id']}  and drug = '{$arr2['drug']}' ");
								}
								?>
								<td class="center">
									<?php echo trim($arr2['range']) ?>
								</td>

								<td data-did="<?php echo isset($arr2['drug_comment_id']) ? $arr2['drug_comment_id'] : '' ?>">
									<?php if (!isset($arr2['hidedrug'])) { ?>
										<textarea name="data_results[<?php echo $arr2['id'] ?>][comments]" data-posc="<?php echo isset($arr2['posc']) ? $arr2['posc'] : '' ?>" <?php if (!$can_edit) echo 'readonly' ?>><?php echo $arr2['comments'] ?></textarea>
									<?php } ?>
								</td>
							</tr>
					<?php
						}
					}
				}

				$drug_content = ob_get_clean();
				if (!empty($drug_content)) {
					if ($groupdata['parent'] > 0 && !in_array($groupdata['parent'], $show_group_parents)) {
						$parentgname = $drug_groups[$groupdata['parent']]['name'];
						echo '<div class="frow"><h4 class="center">' . $parentgname . '</h4></div>';
						$show_group_parents[] = $groupdata['parent'];
					}
					if ($groupdata['id'] > 0 && !in_array($groupdata['id'], $show_group_parents)) {
						echo '<h4 class="center">' . $groupdata['name'] . '</h4>';
						$show_group_parents[] = $groupdata['id'];
					}
					?>
					<table style="width:100%" class="tbl_results">
						<tr class="group_header">
							<th style="width:25%"><strong>Test</strong></th>
							<th style="width:15%"><strong>Result</strong></th>
							<?php
							if ($is_amp_cq) {
								echo '<th  style="width:8%"><strong>CT</strong></th>';
								echo '<th  style="width:8%"><strong>Amp Score</strong></th>';
								echo '<th  style="width:8%"><strong>Cq Confidence</strong></th>';
							} else {
								echo '<th  style="width:20%"><strong>CT (ng/mL)</strong></th>';
							}
							if ($group_e_percent_positive) {
								echo '<th  style="width:8%"><strong>% Positive</strong></th>';
							}
							?>
							<th style="width:12%"><strong>Range</strong></th>
							<th><strong><?php echo $comment_text_col ?></strong></th>
						</tr>
						<?php echo $drug_content; ?>
					</table>
		<?php
				}
			}
		}
		?>

		<?php if (isset($result_settings['cli_pha_guid']) && $result_settings['cli_pha_guid']) {
			if (!function_exists('add_the_table_plugin')) {
				function tr_add_the_table_plugin($plugins)
				{
					$plugins['table'] = TRCPDD_URL . 'js/tinymce-plugin-table.min.js';
					return $plugins;
				}
				add_filter('mce_external_plugins', 'tr_add_the_table_plugin');
			}

			$tinymce_options = array(
				'plugins' => "table,lists,link,textcolor,hr",
				'toolbar1' => "fontsizeselect,forecolor,backcolor,bold,italic,underline,strikethrough,alignleft,aligncenter,alignright,alignjustify",
				'toolbar2' => "blockquote,hr,table,bullist,numlist,undo,redo,link,unlink"
			);
			$field_settings = array(
				'textarea_name' => 'info[clinical_pharmacy_guidance]',
				'default_editor' => true,
				'tinymce' => $tinymce_options
			);


			$datetime = date_i18n('m/d/Y H:iA');
			$df_pha_table = '<table style="border-collapse: collapse; width: 100%;font-size:8px" border="1"><tbody><tr><td style="width: 17.0883%; text-align: center; background-color: #f1f1f1;"><span style="color: #000000;"><strong>Medication</strong></span></td><td style="width: 26.0763%; text-align: center; background-color: #f1f1f1;"><span style="color: #000000;"><strong>Common Dosing</strong></span></td><td style="width: 56.8353%; text-align: center; background-color: #f1f1f1;"><span style="color: #000000;"><strong>Comments</strong></span></td></tr><tr><td></td><td></td><td> </td></tr></tbody></table>';


			$signedby_raw = "Electronically signed by CJ Michaud, PharmD, BCCCP * Pharmacotherapy Solutions, LLC";
			$signedby = '<div style="font-weight: bold;font-size:8pt;" class="electronically_div">' . $signedby_raw . ' ' . $datetime . '</div>';

			$signedby_raw2 = "Electronically signed by Devin Schmidt, PharmD, BCACP * Pharmacotherapy Solutions, LLC";
			$signedby2 = '<div style="font-weight: bold;font-size:8pt;" class="electronically_div">' . $signedby_raw2 . ' ' . $datetime . '</div>';

			$more_guidance = '
                              <div style="font-size:8pt;" class="additional_questions">For additional questions, consultation, or to discuss patient factors that may impact treatment, email the clinical pharmacist at PCRPharmD@gmail.com.</div>
                              <div style="font-size:10px!important;font-style: italic" class="disclaimer_div">DISCLAIMER: ' . $labname . ' contracts with Pharmacotherapy Solutions, LLC to provide clinical pharmacy guidance. 
                                    Recommendations are based solely on the pathogen and resistance results shown above, which represent a
                                    snapshot of the patient’s microbiota. Additional patient factors must be considered
                                    before prescribing treatment, including but not limited to: age, allergies,
                                    comorbidities, potential drug interactions, renal/hepatic function, and cost.
                                </div>';

		?>
			<div class="frow">
				<h4><?php echo tr_get_option('ci_pdf_data', 'cliphagui_comment') ? 'Content' : 'Clinical Pharmacy Guidance' ?></h4>
			</div>
			<?php wp_editor(stripslashes($report['clinical_pharmacy_guidance']), 'clinical_pharmacy_guidance', $field_settings); ?>
			<?php if (!tr_get_option('ci_pdf_data', 'cliphagui_comment')) { ?>
				<a class="button add_tbl_pha">add table</a>
				<a class="button add_signedby_pha" data-ref="df_guidance">add guidance</a>
				<a class="button add_signedby_pha" data-ref="df_pha_signedby">Signed by CJ</a>
				<a class="button add_signedby_pha" data-ref="df_pha_signedby2">Signed by Devin</a>
				<div id="df_pha_table" style="display:none"><?php echo $df_pha_table ?></div>
				<div id="df_guidance" style="display:none"><?php echo $more_guidance ?></div>
				<div id="df_pha_signedby" style="display:none"><?php echo $signedby ?></div>
				<div id="df_pha_signedby2" style="display:none"><?php echo $signedby2 ?></div>
			<?php } ?>
			<script>
				jQuery(function($) {
					$('a.add_tbl_pha').click(function() {
						var $html = $('#df_pha_table').html();
						tinymce.activeEditor.execCommand('mceInsertContent', false, $html);
						tinymce.execCommand("fontSize", false, "8pt");
						return false;
					})
					$('a.add_signedby_pha').click(function() {
						var $html = $('#' + $(this).data('ref')).html();
						tinymce.activeEditor.execCommand('mceInsertContent', false, $html);
						tinymce.execCommand("fontSize", false, "8pt");
						return false;
					})
				})
			</script>
		<?php
		}
		?>

		<div class="frow">
			<h4>Notes (Show on Report)</h4>
		</div>
		<div class="frow" title="<?php echo $count_test_show ?>" data-miss="<?php echo implode(',', $miss_test) ?>">
			<div class="">
				<textarea name="updatebat[notes_report]" <?php if (!$can_edit) echo 'readonly' ?>><?php echo stripslashes($report['notes_report']) ?></textarea>
			</div>
		</div>

		<div class="frow">
			<h4>Internal Notes</h4>
		</div>
		<div class="frow">
			<div class="">
				<textarea name="updatebat[internal_notes]" <?php if (!$can_edit) echo 'readonly' ?>><?php echo stripslashes($report['internal_notes']) ?></textarea>
			</div>
		</div>


		<div class="frow">
			<div class="col3">
				<label>Status</label>
				<?php
				$status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'pcr'), $report['batch_status'], $report['mid'], $table_batch, 'pcr');
				$disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('pcr', 'show_in_result_form', $report['batch_status']);
				tr_select_option('updatebat[batch_status]', $status_options, @$report['batch_status'], ['disabled_options' => $disabled_keys]);
				?>
			</div>
		</div>



		<br>
		<div class="frow">
			<?php
			if (tr_get_option('ci_pdf_data', 'pdf_approved_by')) {
				$all_cls = get_users(array('role' => 'cls'));
				echo '<div class="col2">';
				echo '<label>Approved by: </label>';
				echo '<select name="info[approved_by_ID]">';
				echo '<option value=""></option>';
				foreach ($all_cls as $cls) {
					$selected = $cls->ID == $report['approved_by_ID'] ? 'selected' : '';
					echo '<option value="' . $cls->ID . '" ' . $selected . '>' . $cls->user_email . '</option>';
				}
				echo '</select>';
				echo '</div>';
			}
			?>
		</div>

		<div class="frow">
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="0" <?php checked($report['final_results'], '0') ?> <?php if (!$can_edit) echo 'disabled' ?> /> None </label>
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="2" <?php checked($report['final_results'], '2') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Preliminary </label>
			<label style="margin-right:20px"><input type="radio" name="info[final_results]" value="1" <?php checked($report['final_results'], '1') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Final </label>
			<label><input type="radio" name="info[final_results]" value="3" <?php checked($report['final_results'], '3') ?> <?php if (!$can_edit) echo 'disabled' ?> /> Amended Report </label>
		</div>
		<br><br>


		<?php if ($act != 'view') { ?>
			<div class="frow">
				<?php
				$sql = "select 
                    g.tg_ID,
                    g.tg_popup_approve
                from ci_test_groups g
                where g.tg_type = 'pcr' ";

				$rs = $wpdb->get_results($sql);
				$pop_msg = '';
				foreach ($rs as $r) {
					if (isset($r->tg_popup_approve) && strlen($r->tg_popup_approve) > 10 && in_array($r->tg_ID, $group_ids)) {
						$pop_msg = $r->tg_popup_approve;
					}
				}
				$next = tr_pdd_next_result($id, $report['batch_id'], $report['lab_id'], 'pcr');
				if ($can_edit) {
					echo ' <input type="submit" name="submit" value="Submit" class="button-primary" /> ';
				}
				if ($next > 0) {
					echo ' <input type="submit" name="submit_next" value="Submit & Next" class="button"> ';
				}
				?>

				<input type="submit" <?php if (!$can_approve) echo 'style="display:none"' ?> name="approval" value="Approve" class="button approve_btn">

				<?php

				if ($next > 0) {
					$next_url = Tr_Base_Class_V6::link(array('id' => $next), false);
				?>
					<input type="hidden" name="next_link" value="<?php echo $next_url ?>" />

					<input type="submit" <?php if (!$can_approve) echo 'style="display:none"' ?> name="approval_next" value="Approve & Next" class="button approve_btn">

					<a href="<?php echo $next_url ?>" class="button">Next</a>
				<?php
				}
				?>

				<a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $report['id'], 'btype' => 'pcr', 'view' => 1)) ?>">PDF</a>

				<a class="button" href="<?php Tr_Base_Class_V6::link(array('checkmatch' => '1')) ?>">Check File</a>

				<input type="hidden" name="tr_action" value="ci_save_result_edit" />
				<input type="hidden" name="btype" value="pcr" />
				<input type="hidden" id="pop_msg" value="<?php echo $pop_msg ?>" />
				<input type="hidden" name="id" value="<?php echo $report['id'] ?>" />

				<?php trpdd_form_unique_id() ?>
			</div>
		<?php } ?>
	</form>
</div>
<?php

include(trpdd_get_file_path('global', 'report_history'));

if (isset($_GET['checkmatch'])) {
	$drugs_matched = array();
	foreach ($all_res as $tid => $arr) {
		$drugs_matched[] = $arr['drug'];
		$drugs_matched[] = $arr['te_abrev'];
	}
	$drugs_matched = implode("','", $drugs_matched);
	$wherem        = " and drug not in ('{$covidtests_str}') ";
	$sql2          = "select * from ci_results_pcr where report_id = {$id} 
    and drug not in ('{$drugs_matched}') {$wherem}";

	$rsdb         = $wpdb->get_results($sql2);
	$count_infile = $wpdb->get_var("select count(*) from ci_results_pcr 
	                        where report_id = {$id}
	                        {$wherem}");


?>
	<div class="wrap" id="checkfile">
		<div>Total Tests Requested: <?php echo count($in_panel_tests_requested) ?></div>
		<div>Total Tests In File: <?php echo $count_infile ?></div>
		<div>Total Tests Miss: <?php echo count($in_panel_tests_requested) - count($all_res) ?></div>
		<table class="wp-list-table widefat striped pages blue_tbl hover_tbl">
			<thead class="sticky sticky2">
				<tr>
					<th>#</th>
					<th>igniteLIS Name</th>
					<th>Abrev</th>
					<th>Abrev2</th>
					<th>File Name</th>
				</tr>
			</thead>
			<?php
			$i      = 1;
			$te_IDm = array();
			foreach ($all_res as $tid => $arr) {

				if (strtoupper($arr['dbdrug']) == strtoupper($arr['te_name'])) {
					$arr['dbdrug'] = strtoupper($arr['te_abrev']);
				}
			?>
				<tr>
					<td><?php echo $i++ ?></td>
					<td><?php echo strtoupper($arr['te_name']) ?></td>
					<td><?php echo strtoupper($arr['te_abrev']) ?></td>
					<td><?php echo strtoupper($arr['te_abrev2']) ?></td>
					<td><?php echo $arr['dbdrug'] ?></td>
				</tr>
			<?php
				$te_IDm[] = $tid;
			}
			$te_IDm         = implode(',', $te_IDm);
			$sql3           = "select * from ci_test_panels te where te.te_type='pcr'
    					and te.te_ID not in ({$te_IDm}) and te.te_ID in ({$test_ids}) ";
			$rest_requested = $wpdb->get_results($sql3, ARRAY_A);
			foreach ($rest_requested as $i => $arr) {
				if (strtoupper($arr['dbdrug']) == strtoupper($arr['te_name'])) {
					$arr['dbdrug'] = strtoupper($arr['te_abrev']);
				}
			?>
				<tr>
					<td><?php echo $i + 1 ?></td>
					<td style="color:green"><?php echo strtoupper($arr['te_name']) ?></td>
					<td><?php echo strtoupper($arr['te_abrev']) ?></td>
					<td><?php echo strtoupper($arr['te_abrev2']) ?></td>
					<td><?php echo $arr['dbdrug'] ?></td>
				</tr>
			<?php
			}
			foreach ($rsdb as $i => $r) {
			?>
				<tr>
					<td><?php echo $i + 1 ?></td>
					<td></td>
					<td></td>
					<td></td>
					<td style="color:orangered"><?php echo $r->drug ?></td>
				</tr>
			<?php
			}

			?>
		</table>
	</div>
	<style>
		thead.sticky.sticky2 th {
			top: 60px;
		}
	</style>
	<script>
		jQuery(function($) {
			$('html, body').animate({
				scrollTop: $('#checkfile').offset().top - 100
			}, 800, function() {});
		})
	</script>
<?php
}
?>

<?php

if (isset($_GET['debug2'])) {
	echo '$count_test_show:' . $count_test_show . '<br><br>';
	echo '$in_panel_tests_requested:' . '<br>';
	var_dump($in_panel_tests_requested);
	echo '<br><br>';
	echo '$all_res:<br>';
	var_dump($all_res);
	echo '<br><br>';
}
?>

<div id="popup_selecter" style="display: none;"></div>
<style>
	input.conc,
	input.cutoff {
		width: 80px;
	}

	tr.group_header {
		background: #ddd;
		height: 60px;
	}

	tr.hide_pdf td {
		background: #fff;
	}

	tr.hide_pdf td * {
		opacity: 0.6;
	}

	tr.isred select,
	tr.isred input {
		color: red;
	}
</style>
<script>
	var $need_final = <?php echo ($current_role == 'results') ? 'true' : 'false' ?>;
	var $rejecteds = <?php echo json_encode(tr_pdd_where_batch_rejected(true, false, 'pcr')) ?>;
	var $count_missing_test = <?php echo intval(count($miss_test)) ?>;

	jQuery(function($) {
		function tr_has_missing() {
			if ($count_missing_test > 0) return true;
			var $has_missing = false;
			$('tr.result_row').each(function() {
				var $tr = $(this);
				var $posneg = $('select.pos_neg', $tr).val();
				var $conc = $('input.conc', $tr).val();
				if ($conc == '' && ($posneg == '' || $posneg == 'PEN')) {
					$has_missing = true;
				}
			})
			return $has_missing;
		}

		function setEditorContent(editorId, newContent) {
			if (typeof tinymce === 'undefined') return;

			if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
				// Visual (TinyMCE) mode is active
				tinymce.get(editorId).setContent(newContent);
			} else {
				// Text (HTML) mode or TinyMCE not loaded, target the original textarea
				document.getElementById(editorId).value = newContent;
			}
			// Trigger a save event to ensure the content is synced back to the hidden textarea
			// This is important for form submissions
			tinymce.triggerSave();
		}

		$('.change_comments').change(function() {
			var $vl = $(this).val();
			if ($vl != '') {
				var $target = $('#' + $(this).data('target'));
				var $oldtext = $target.val();
				var $newtext = $vl + ($oldtext ? "\n" + $oldtext : '');
				$target.val($newtext).change();
				setEditorContent($(this).data('target'), $newtext);
				$(this).val('');
			}
		})

		$('body').on('change', 'select.pos_neg', function() {
			var $pr = $(this).parents('tr.crow');
			var $cutoffs = $pr.data('cutoffs');
			var $vl = $(this).val();
			if ($cutoffs && $cutoffs[$vl]) {
				$('input.conc', $pr).val($cutoffs[$vl]);
			} else {
				$('input.conc', $pr).val('');
			}

		}).on('change', '[name="updatebat[batch_status]"]', function() {
			var $vl = $(this).val();
			if ($rejecteds[$vl]) {
				$('.approve_btn').show();
			} else {
				$('.approve_btn').hide();
			}
		}).on('click', '.approve_btn', function() {
			var $pop_msg = $('#pop_msg').val();
			if ($pop_msg.length > 10 && confirm($pop_msg) == false) {
				return false;
			}

			if ($('[name="info[final_results]"]:checked').val() == '1') {
				//check missing
				if (tr_has_missing()) {
					if (!confirm('Are you sure you want to approve this result as final? There are ordered tests pending.')) {
						$('[name="info[final_results]"][value="2"]').prop('checked', true);
					}
				}
			} else if ($('[name="info[final_results]"]:checked').val() != '1' && $need_final) {
				alert('Status must be Final!');
				return false;
			}
		}).on('click', 'a.show_test_panels', function() {
			$('.list_test_panels').toggle();
			return false;
		})

		$(window).scroll(function() {
			var $t = $(this).scrollTop();

			$f = $('.header_fix_top').offset().top;
			if ($t > $f - 20) {
				$('.header_fix').show();
			} else {
				$('.header_fix').hide();
				$('.list_test_panels').hide();
			}

		})
	})
</script>