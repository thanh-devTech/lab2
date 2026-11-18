<?php

function tr_pdd_get_results_by_testsid($report_id, $btype, $testids, $groups = array(), $instrument = '', $is_pdf = false, $report = array())
{
	global $wpdb;
	$lists = array();
	$inew = 0;
	$drugkeys       = array('te_abrev2', 'te_abrev', 'te_name');
	if (!is_array($groups) || empty($groups)) $groups = array();

	$result_tbl = tr_pdd_get_table_result_by_btype($btype);

	if (is_array($groups) && count($groups) > 0) {
		$groups_str = implode(",", $groups);
		$addingtests = $wpdb->get_col("select te.te_ID 
		from ci_test_panels te 
		JOIN ci_test_groups g ON g.tg_ID = te.te_group_id 
		where g.tg_only_group = 1 and (g.tg_ID in ({$groups_str})  or g.tg_parent in ({$groups_str}) )");

		foreach ($addingtests as $atid) {
			if (!in_array($atid, $testids)) {
				$testids[] = $atid;
			}
		}
	}


	if (count($testids) == 0) {
		return array();
	}

	$testids_str = implode(',', $testids);


	foreach ($drugkeys as $drugkey) {

		$sql = "SELECT *
			FROM ci_test_panels tp
			JOIN ci_test_groups g ON g.tg_ID = tp.te_group_id
			" . ($is_pdf ? '' : 'LEFT') . " JOIN {$result_tbl} rs ON  tp.{$drugkey} = rs.drug  and rs.drug!=''
					and report_id='" . $report_id . "' and (rs.group_id = 0 or rs.group_id is null or rs.group_id = tp.te_group_id)
			WHERE tp.te_type='{$btype}'
			and tp.te_ID in ({$testids_str})
			" . ($is_pdf ? ' and rs.id > 0 ' : '');

		$all_results_q = $wpdb->get_results($sql, ARRAY_A);

		foreach ($all_results_q as $arr) {

			$arr['dbdrug'] = $arr['drug'];
			$arr['drug'] = strtoupper($arr['te_name']);

			if (isset($lists[$arr['te_ID']]) && (!empty($lists[$arr['te_ID']]['conc']) || !empty($lists[$arr['te_ID']]['pos-neg']))) {
				continue;
			}

			if ($arr['id'] > 0) {
				if ($btype == 'pcr') {
					$arr = tr_pdd_parse_pcr_test($arr, $instrument, $arr['tg_ID'], null);
				} else if ($btype == 'blood') {
					$arr = tr_pdd_parse_blood_test($arr, $instrument, $arr['tg_ID'], null);
				} else if ($btype == 'gene') {
					$arr = tr_pdd_parse_gene_test($arr, $instrument, $arr['tg_ID'], $report);
				} else if ($btype == 'tox') {
					global $medications;
					global $ci_cutoffs;
					global $in_panel_tests_requested;
					global $report;
					$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);
				}

				$ipos = array_search($arr['te_ID'], $testids);
				unset($testids[$ipos]);
			} else if (!$is_pdf) {
				$arr['id'] = 'new_' . $arr['te_ID'] . $inew++;
			}

			if (isset($_GET['debug112'])) {
				echo '<br><br>';
				var_dump($arr);
				echo '<br><br>';
			}


			$lists[$arr['te_ID']] = $arr;
		}
	}
	return $lists;
}

function tr_pdd_get_results_by_report($report, $btype)
{
	global $wpdb;
	global $pre_all_res;

	$req_settings   = get_option('ci_req_settings_' . $btype, array());

	$result_tbl 	= tr_pdd_get_table_result_by_btype($btype);

	if (is_numeric($report)) {
		$id = intval($report);
		$report = tr_pdd_get_report_by_id($id, $btype);
	} else {
		$id = intval($report['id']);
	}

	$reflex_main_tests = tr_pdd_check_group_reflex($report, $btype);
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
			left join {$result_tbl} rs ON (rs.drug = tp.te_name or rs.drug = tp.te_abrev) and rs.report_id = {$id}
			where te_type='{$btype}' and te_in_req = 1
			and (te_control_bys!='' and te_control_bys is not null) 
			and te_hide_pdf != 1
			and rs.id is null
			and (tp.te_ID in ({$test_ids}) 
			or tp.te_group_id in (select tg_ID from ci_test_groups where (tg_aw_results = 1 and tg_ID in ({$group_ids_str}))  {$where_group})
			{$more_query_test}
		)"
		);
		foreach ($ctrl_tests as $ctrtest) {
			$wpdb->insert($result_tbl, array(
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
	$all_res = isset($pre_all_res) && is_array($pre_all_res) ? $pre_all_res : array();
	foreach ($drugkeys as $drugkey) {
		$sql = "SELECT *
		FROM {$result_tbl} rs 
		join ci_test_panels tp ON tp.te_type='{$btype}' and  tp.{$drugkey} = rs.drug and rs.drug!=''
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


	$all_groups 				= array();
	$reflex_pass 				= array();

	$checked_types = explode(',', strtolower($report['sample_type']));
	$drug_groups = tr_pdd_get_drug_groups($btype, $group_ids, true);


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

		if (!isset($all_groups[$groupid]['has_sub'])) {
			$drug_groups[$groupid]['has_sub'] = false;
		}

		if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {

			if (isset($drug_groups[$groupdata['parent']])) {
				$drug_groups[$groupdata['parent']]['has_sub'] = true;
			}
		}
	}

	foreach ($drug_groups as $groupid => $groupdata) {

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



		foreach ($te_IDs as $te_ID) {
			$arr = isset($all_res[$te_ID]) ? $all_res[$te_ID] : array();

			if ($groupdata['name'] == 'nCoV-2019' || $groupdata['is_covid']) {
				if (empty($arr)) {
					$arr = $wpdb->get_row("select te.* from ci_test_panels te
			            where te.te_ID = {$te_ID} and te_type='{$btype}' ", ARRAY_A);

					$arr['drug'] = ($arr['te_name']);
				}
				$arr['showed'] = true;

				$arr = trpdd_cal_ncovid19($arr, $id, $report['instrument'], $report['created_date']);

				$groupdata['tests'] = array();
				$groupdata['tests'][$arr['drug']] = $arr;


				$all_res[$te_ID] = $arr;
				break;
			} else if (!empty($arr)) {


				if ($arr['te_inconclusive_ID'] === '-1') {
					unset($all_res[$te_ID]);
					continue;
				}
				$arr['showed'] = true;
				$arr['type_of_specimen'] = $report['type_of_specimen'];

				if ($arr['id'] > 0) {
					if ($btype == 'pcr') {
						$arr = tr_pdd_parse_pcr_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);
					} else if ($btype == 'blood') {
						$arr = tr_pdd_parse_blood_test($arr, $report['instrument'], $groupdata['id'], $report['created_date']);
					} else if ($btype == 'gene') {
						$arr = tr_pdd_parse_gene_test($arr, $report['instrument'], $groupdata['id'], $report);
					} else if ($btype == 'tox') {
						global $medications;
						global $ci_cutoffs;
						global $in_panel_tests_requested;
						global $report;
						$arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);
					}
				}


				$all_res[$te_ID] = $arr;

				if (
					!in_array($groupdata['name'], array('Antibiotic Resistance', 'ABX'))
					&& !$groupdata['antibiotic']
					&& isset($arr['antibiotic']) && $arr['antibiotic']
				) {
					unset($all_res[$te_ID]['showed']);
					continue;
				}


				if (in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'POM', 'PVL', 'PVH'))) {

					if (!empty($groupdata['reflex_ID'])) {
						foreach ($groupdata['reflex_ID'] as $rlid) {
							if ($rlid > 0  && isset($reflex_pass[$rlid])) {
								$reflex_pass[$rlid] = true;
							}
						}
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



		if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {
			$groupparentname = $all_groups[$groupdata['parent']];

			if (!empty($groupparentname)) {

				$groupdata['showed'] = true;

				$drug_groups[$groupdata['parent']]['subgroups'][$groupdata['id']] = $groupdata['name'];
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
		$drug_groups[$groupid] = $groupdata;
	}

	//remove reflex

	if (count($reflex_pass) > 0) {
		foreach ($reflex_pass as $gid => $refpass) {
			if (!$refpass) {
				foreach ($drug_groups as $groupid => $groupdata) {
					if ($groupdata['id'] == $gid) {

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

	$report['all_groups'] = $drug_groups;

	return $report;
}

function tr_pdd_parse_pcr_test($arr, $instrument, $group_id = 0, $datereport = null, $tests_str = null)
{
	global $wpdb, $ci_allways_cache_tests;
	static $all_tests_pcr;
	static $all_group_pcr;
	global $xeno_control_pos_neg;

	$specimen = isset($arr['type_of_specimen']) ? strtoupper($arr['type_of_specimen']) : '';
	$arr['flag_adding'] = '';
	$arr['pos-neg'] = isset($arr['pos-neg']) ? $arr['pos-neg'] : '';
	$arr['te_is_detected'] = isset($arr['te_is_detected']) ? $arr['te_is_detected'] : 0;

	if (!empty($arr['instrument'])) {
		$instrument = $arr['instrument'];
	}
	if (!isset($arr['range']) || empty($arr['range'])) {
		$arr['range'] = isset($arr['te_range']) ? $arr['te_range'] : '';
	}

	$instrument = trim(strtoupper($instrument));
	$instrument = str_replace(' ', '', $instrument);

	if (empty($all_group_pcr)) {
		$rs = $wpdb->get_results("select tg_ID, tg_name, tg_other_options, tg_parent
		from ci_test_groups 
		where tg_type = 'pcr'", ARRAY_A);
		foreach ($rs as $r) {
			$r['others'] = !empty($r['tg_other_options']) ? json_decode($r['tg_other_options'], true) : array();
			$all_group_pcr[$r['tg_ID']] = $r;
			$all_group_pcr[strtoupper($r['tg_name'])] = $r;
		}
	}

	//get group_id from group name
	$groupdata = array();
	if (isset($arr['te_group_id']) && !empty($arr['te_group_id'])) {
		$group_id = $arr['te_group_id'];
	}
	if (!empty($group_id) && is_string($group_id) && $group_id == 0) {
		$group_id = strtoupper($group_id);
		if (isset($all_group_pcr[$group_id])) {
			$groupdata = $all_group_pcr[$group_id];
			$group_id = $groupdata['tg_ID'];
		}
	} else if (isset($all_group_pcr[$group_id])) {
		$groupdata = $all_group_pcr[$group_id];
	}

	$is_amp_cq = tr_get_option('ci_result_settings_pcr', 'enable_rs_ct_amp_cq') ? true : false;
	$is_whqc = false;
	if ($groupdata['tg_parent'] > 0) {
		$groupdataparent = $all_group_pcr[$groupdata['tg_parent']];
		if (isset($groupdataparent['others']['is_rpp'])) {
			if ($groupdataparent['others']['is_rpp'] == 2) {
				$is_amp_cq = false;
			} else if ($groupdataparent['others']['is_rpp']) {
				$is_amp_cq = true;
			}
		}

		$arr['group'] = $groupdataparent['tg_name'];

		if (
			$is_amp_cq
			&& (strpos($groupdataparent['tg_name'], 'WH') !== false
				|| strpos($groupdata['tg_name'], 'WH') !== false
				|| strpos($groupdataparent['tg_name'], 'Wound') !== false
				|| (isset($groupdataparent['others']['amp_cq_array']) && $groupdataparent['others']['amp_cq_array'])
			)
		) {
			$is_whqc = true;
		}
	} else {
		$arr['group'] = $groupdata['tg_name'];
		if (isset($groupdata['others']['is_rpp'])) {
			if ($groupdata['others']['is_rpp'] == 2) {
				$is_amp_cq = false;
			} else if ($groupdata['others']['is_rpp']) {
				$is_amp_cq = true;
			}
		}

		if (
			$is_amp_cq
			&& (strpos($groupdata['tg_name'], 'WH') !== false
				|| strpos($groupdata['tg_name'], 'WH') !== false
				|| strpos($groupdata['tg_name'], 'Wound') !== false
				|| (isset($groupdata['others']['amp_cq_array']) && $groupdata['others']['amp_cq_array'])
			)
		) {
			$is_whqc = true;
		}
	}


	if (tr_get_option('ci_req_settings_pcr', 'enabel_test_control_by')) {
		if (!empty($arr['te_control_bys'])) {
			//include control by too

			$ctrids = explode(',', $arr['te_control_bys']);

			$arr['list'] = tr_pdd_get_results_by_testsid($arr['report_id'], 'pcr', $ctrids, array(), $instrument);
			if (isset($_GET['debug112'])) {
				var_dump($arr['report_id']);
				echo '<br><br>';
				var_dump($arr['list']);
			}
			if (count($arr['list']) == count($ctrids)) {
				$arr['pos-neg'] = 'NEG';
				foreach ($arr['list'] as $arr1) {
					if ($arr1['pos-neg'] == 'POS') {
						$arr['pos-neg'] = 'POS';
						break;
					}
				}

				if ($arr['id'] > 0 && stripos($arr['id'], 'new') === false) {
					$wpdb->update('ci_results_pcr', array('pos-neg' => $arr['pos-neg']), array('id' => $arr['id']));
				} else {
					$wpdb->insert('ci_results_pcr', array(
						'report_id' => $arr['report_id'],
						'drug' => $arr['te_name'],
						'conc' => '',
						'pos-neg' => $arr['pos-neg']
					));
					$arr['id'] = $wpdb->insert_id;
				}
			}
		}
	}
	if (isset($_GET['debug11'])) {
		var_dump($group_id);
		echo '<br>';
		var_dump($groupdata);
		echo '<br>$is_amp_cq';
		var_dump($is_amp_cq);
		echo '<br><br>$is_whqc';
		var_dump($is_whqc);
		echo '<br><br>';
		var_dump($arr['conc']);
		echo '<br><br>';
	}

	if (isset($arr['parsed_test' . $group_id]) && $arr['parsed_test' . $group_id]) {
		return $arr;
	}

	if (empty($datereport) || $ci_allways_cache_tests === true) {
		$datereport = date_i18n('Y-m-d');
	} else {
		$datereport = date('Y-m-d', strtotime($datereport));
	}

	if (empty($all_tests_pcr[$datereport])) {
		$all_tests_pcr[$datereport] = array();
		global $ci_cache_queries;
		if (!is_array($ci_cache_queries)) $ci_cache_queries = array();
		$sql = "select * from ci_test_panels where te_type='pcr' ";
		$qkey = md5($sql);
		if (!isset($ci_cache_queries[$qkey])) {
			$rs = $wpdb->get_results($sql);
			$ci_cache_queries[$qkey] = $rs;
		} else {
			$rs = $ci_cache_queries[$qkey];
		}

		foreach ($rs as $r) {
			$r->te_name = strtoupper($r->te_name);
			$sql_cutoff = "select * from ci_test_panels_cutoffs where cu_te_ID = {$r->te_ID} 
			                and (cu_start is null or cu_start ='' or cu_start <= '{$datereport}' ) 
			                and (cu_end is null or cu_end = '' or cu_end >= '{$datereport}')";

			$cutoffs = $wpdb->get_results($sql_cutoff);

			foreach ($cutoffs as $cut) {
				$mcs = explode(',', $cut->cu_ms);
				foreach ($mcs as $mc) {
					if (empty($mc)) $mc = '*';

					$mc = trim(strtoupper($mc));
					$mc = str_replace(' ', '', $mc);

					$newr = clone $r;

					$newr->te_cutoff = $cut->cu_cutoff;
					$newr->te_upper_limit = $cut->cu_upper_limit;
					$newr->te_medium = $cut->cu_medium;
					$newr->te_invalid = $cut->cu_invalid;
					$newr->te_critical_high = $cut->cu_critical_high;
					$newr->te_critical_low = $cut->cu_critical_low;
					$newr->te_hqc = $cut->cu_hqc;
					$newr->te_critical_low_concen = $cut->cu_critical_low_concen;
					$newr->te_cutoff_concen = $cut->cu_cutoff_concen;
					$newr->te_medium_concen = $cut->cu_medium_concen;
					$newr->te_upper_limit_concen = $cut->cu_upper_limit_concen;
					$newr->te_te_critical_high_concen = $cut->cu_critical_high_concen;

					$newr->te_amp_score = $cut->cu_amp_score;
					$newr->te_cq = $cut->cu_cq;

					$newr->specimen = $cut->cu_type_of_specimen;
					$newr->mc = $mc;

					$all_tests_pcr[$datereport][$r->te_ID . '_' . $mc] = $newr;
					if ($cut->cu_type_of_specimen > 0) {
						$spname = $wpdb->get_var("select ts_name from ci_test_specimens where ts_ID = {$cut->cu_type_of_specimen}");
						if (!empty($spname)) {
							$spname = strtoupper($spname);
							$all_tests_pcr[$datereport][$r->te_ID . '_' . $mc . '_' . $spname] = $newr;
						}
					}
					if (!isset($all_tests_pcr[$datereport][$r->te_ID . '_count'])) {
						$all_tests_pcr[$datereport][$r->te_ID . '_count'] = 0;
					}
					if (!isset($all_tests_pcr[$datereport][$r->te_ID . '_onemc'])) {
						$all_tests_pcr[$datereport][$r->te_ID . '_onemc'] = 0;
					}
					$all_tests_pcr[$datereport][$r->te_ID . '_count']++;
					$all_tests_pcr[$datereport][$r->te_ID . '_onemc'] = $newr;
				}
			}
		}
	}

	if (isset($_GET['debug33'])) {
		var_dump($all_tests_pcr[$datereport]);
		exit;
	}
	$arr['drug'] = strtoupper($arr['drug']);

	$keypairs = array();
	if (tr_get_option('ci_result_settings_pcr', 'enable_rangebyspec')) {
		$keypairs[] =  $arr['te_ID'] . '_' . $instrument . '_' . $specimen;
	}

	$keypairs[] =  $arr['te_ID'] . '_' . $instrument;
	$keypairs[] =  $arr['te_ID'] . '_*';


	$arr['keypair'] = $arr['te_ID'] . '_' . $instrument;
	foreach ($keypairs as $kpair) {
		if (isset($all_tests_pcr[$datereport][$kpair])) {
			$arr['keypair'] = $kpair;
			break;
		}
	}

	if (isset($_GET['debugcal'])) {
		echo '<br>';
		var_dump($arr['drug']);
		echo '<br>';
		var_dump($keypairs);
		echo '<br>';
		var_dump($arr);
		echo '<br><br>';
	}


	$testdb = isset($all_tests_pcr[$datereport][$arr['keypair']]) ? $all_tests_pcr[$datereport][$arr['keypair']] : false;
	if (!$testdb && isset($all_tests_pcr[$datereport][$arr['te_ID'] . '_count']) && $all_tests_pcr[$datereport][$arr['te_ID'] . '_count'] == 1) {
		$testdb = $all_tests_pcr[$datereport][$arr['te_ID'] . '_onemc'];
	}

	if ($testdb) {
		if (isset($_GET['createddate'])) {
			echo '<br><br>$testdb:';
			var_dump($testdb);
			echo '<br><br>';
			var_dump($arr);
			echo '<br><br>';
		}

		$high_med_low 	= '';
		$pass_equa 		= false;
		$arr['notfound'] = false;

		$arr['only_detected'] = false;

		if (empty($testdb->te_medium) && (empty($testdb->te_cutoff) || empty($testdb->te_upper_limit))) {
			$arr['only_detected'] = true;
		} else if (empty($testdb->te_cutoff) && empty($testdb->te_upper_limit)) {
			$arr['only_detected'] = true;
		}

		$arr['cutoffs'] = (array) $testdb;

		$cut_arr        = explode('-', !is_null($testdb->te_critical_low) ? $testdb->te_critical_low : '');
		$arr['vlow_1']   = isset($cut_arr[0]) ? $cut_arr[0] : 0;
		$arr['vlow_2']   = isset($cut_arr[1]) ? $cut_arr[1] : 0;

		$cut_arr        = explode('-', $testdb->te_cutoff);
		$arr['low_1']   = isset($cut_arr[0]) ? $cut_arr[0] : 0;
		$arr['low_2']   = isset($cut_arr[1]) ? $cut_arr[1] : 0;


		$cut_arr        = explode('-', $testdb->te_medium);
		$arr['medium_1'] = isset($cut_arr[0]) ? $cut_arr[0] : 0;
		$arr['medium_2'] = isset($cut_arr[1]) ? $cut_arr[1] : 0;

		$cut_arr        = explode('-', $testdb->te_upper_limit);
		$arr['high_1']  = isset($cut_arr[0]) ? $cut_arr[0] : 0;
		$arr['high_2']  = isset($cut_arr[1]) ? $cut_arr[1] : 0;

		$cut_arr        = explode('-', !is_null($testdb->te_critical_high) ? $testdb->te_critical_high : '');
		$arr['vhigh_1']  = isset($cut_arr[0]) ? $cut_arr[0] : 0;
		$arr['vhigh_2']  = isset($cut_arr[1]) ? $cut_arr[1] : 0;

		if (!empty($arr['vhigh_1']) && !empty($testdb->te_te_critical_high_concen)) {
			$arr['vhigh_concentration'] =  $testdb->te_te_critical_high_concen;
		}
		if (!empty($arr['medium_1']) && !empty($testdb->te_medium_concen)) {
			$arr['medium_concentration'] =  $testdb->te_medium_concen;
		}

		if (!isset($arr['old_pos-neg'])) {
			$arr['old_pos-neg'] = $arr['pos-neg'];
		}


		//add range
		if (empty($arr['range'])) {
			if (tr_get_option('ci_result_settings_pcr', 'enable_verylh') && !empty($testdb->te_critical_low)) {
				$arr['verylh'] = true;
				$arr['range'] = $testdb->te_critical_low;
				if (strpos($arr['range'], '-') > 0) {
					$rangarr = explode('-', $arr['range']);
					if (strpos($rangarr[0], '>') !== false && strpos($rangarr[1], '<') !== false) {
						$arr['range'] = $rangarr[1];
					}
				}
			} else if (!empty($testdb->te_cutoff)) {
				$arr['range'] = $testdb->te_cutoff;
			} else if (!empty($testdb->te_upper_limit)) {
				$arr['range'] = $testdb->te_upper_limit;
			} else if (tr_get_option('ci_result_settings_pcr', 'enable_verylh') && !empty($testdb->te_critical_high)) {
				$arr['range'] = $testdb->te_critical_high; //very high
			}


			if (strpos($arr['range'], '-') > 0) {
				$arr['range'] = str_replace(array('<', '=', '>'), '', $arr['range']);
			}
			$arr['range'] = trpdd_show_symbol($arr['range']);
		}


		//calc by conc
		if (stripos($arr['conc'], 'Undetermined') !== false) {
			$arr['conc'] = tr_get_option('ci_result_settings_pcr', 'undetecter_value', 45);
		} else if ($is_amp_cq && $arr['conc'] === '' && strlen($arr['amp_score']) > 0 && strlen($arr['cq']) > 0) {
			$arr['conc'] = tr_get_option('ci_result_settings_pcr', 'undetecter_value', 46);
		} else if (strtolower($arr['conc']) == 'nan') {
			$nanvalue = tr_get_option('ci_result_settings_pcr', 'nan_value', 40);
			if ($nanvalue < 10) $nanvalue = 40;

			$arr['conc'] = $nanvalue;
			$arr['is_nan'] = true;
		}

		if (empty($arr['conc']) && empty($arr['old_pos-neg'])) {

			if ($arr['te_is_control'] && tr_get_option('ci_result_settings_pcr', 'default_empty_pos_ctrl')) {
				$arr['old_pos-neg'] = 'POS';
				$arr['pos-neg'] = 'POS';
			} else if (tr_get_option('ci_result_settings_pcr', 'default_empty_neg')) {
				$arr['old_pos-neg'] = 'NEG';
				$arr['pos-neg'] = 'NEG';
			}
		}


		if ($arr['conc'] == 'N/A') {
			//$arr['conc'] = '';
		} else if (stripos($arr['conc'], 'NaN') !== false || (isset($arr['is_nan']) && $arr['is_nan'] == true)) {
			$arr['range'] = tr_get_option('ci_result_settings_pcr', 'nan_value', 40);
			$arr['pos-neg'] = 'NEG';
		} else if (stripos($arr['conc'], 'Undetermined') !== false) {
			$arr['range'] = tr_get_option('ci_result_settings_pcr', 'undetecter_value', 45);
			$arr['pos-neg'] = 'NEG';
		} else if (($arr['conc'] === '0' || $arr['conc'] === '0.0') && empty($arr['pos-neg'])) {
			$arr['pos-neg'] = 'NEG';
		} else if (($arr['conc'] !== '' && $arr['conc'] !== null) || (empty($arr['conc']) && !empty($testdb->te_invalid))) {


			$arr['conc'] = str_replace(',', '', $arr['conc']);
			if (empty($arr['pos-neg']) && !empty($arr['conc'])) $arr['pos-neg'] = 'NEG';
			$can_go_neg = true;

			$has_condition = false;

			if ($arr['conc'] == 'No Ct') {
				$arr['conc'] =  tr_get_option('ci_result_settings_pcr', 'blank_vl', 40);

				if ($arr['conc'] < 30) {
					$arr['conc'] = 40;
				}
			}
			$conc = $arr['conc'];



			if (isset($_GET['debugtest']) && $_GET['debugtest'] == $arr['drug']) {
				global $is_deebug_pass_condition;
				$is_deebug_pass_condition = true;
			}

			//check very high
			if (tr_get_option('ci_result_settings_pcr', 'enable_verylh')) {
				$arr['verylh'] = true;
				if (!$pass_equa && !empty($conc) && !empty($testdb->te_critical_high)) {
					$has_condition = true;
					if (tr_pdd_pass_condition($conc, $testdb->te_critical_high, '>')) {
						$arr['pos-neg'] = 'PVH';
						$high_med_low = 'H';
						$pass_equa = true;
					}
				}
			}

			//check high
			if (!$pass_equa && !empty($conc) && !empty($testdb->te_upper_limit)) {
				$has_condition = true;
				if (tr_pdd_pass_condition($conc, $testdb->te_upper_limit, '>')) {
					$arr['pos-neg'] = $arr['te_is_detected'] == '4' ? 'POS' : 'POH';
					$high_med_low = 'H';
					$pass_equa = true;
				}
			}

			//check medium
			if (!$pass_equa && !empty($conc) && !empty($testdb->te_medium)) {
				$has_condition = true;
				if (tr_pdd_pass_condition($conc, $testdb->te_medium)) {
					$high_med_low = 'M';
					$arr['pos-neg'] = 'POM';
					$pass_equa = true;
				}
			}


			//check low
			if (!$pass_equa && !empty($conc) && !empty($testdb->te_cutoff)) {
				$has_condition = true;
				if (tr_pdd_pass_condition($conc, $testdb->te_cutoff, '<')) {
					$hight_med_low = 'L';
					$arr['pos-neg'] = $arr['te_is_detected'] == '4' ? 'IND' : 'POL';
					$pass_equa = true;
				}
			}

			//check very low
			if (tr_get_option('ci_result_settings_pcr', 'enable_verylh')) {
				if (!$pass_equa && !empty($conc) && !empty($testdb->te_critical_low)) {
					$has_condition = true;
					if (tr_pdd_pass_condition($conc, $testdb->te_critical_low, '<')) {
						$arr['pos-neg'] = 'PVL';
						$high_med_low = 'L';
						$pass_equa = true;
					}
				}
			}


			//check invalid
			if (!$pass_equa && !empty($testdb->te_invalid)) {
				$has_condition = true;
				if ($conc !== '' && $conc > 0 && tr_pdd_pass_condition($conc, $testdb->te_invalid)) {
					$can_go_neg = false;
					$arr['pos-neg'] = 'INV';
					$high_med_low = '';
					$pass_equa = false;
				}
			}

			//not-detected all tests
			if ($pass_equa && ($instrument == '*' || $instrument == '')) {
				$notdtrange = tr_get_option('ci_result_settings_pcr', 'notdtecall', '');
				$rerunrange = tr_get_option('ci_result_settings_pcr', 'rerunall', '');
				if (!empty($notdtrange) && tr_pdd_pass_condition($conc, $notdtrange)) {
					$pass_equa = false;
					$arr['pos-neg'] = 'NEG';
					$high_med_low = '';
				} else if (!empty($rerunrange) && tr_pdd_pass_condition($conc, $rerunrange)) {
					$pass_equa = false;
					$arr['pos-neg'] = 'RER';
					$high_med_low = '';
				}
			}


			if ($testdb->te_control_by_ID > 0 && $testdb->te_control_by_ID != $arr['te_ID']) {
				$controlarr = trpdd_check_controls_pcr($testdb->te_control_by_ID, $arr['report_id'], $instrument, $datereport);
				if (in_array($controlarr['pos-neg'], array('NEG'))) {
					$can_go_neg = false;
					$arr['pos-neg'] = 'INV';
					$high_med_low = '';
					$pass_equa = false;
				}
			} else if ($testdb->te_inconclusive_ID > 0 && $testdb->te_inconclusive_ID != $arr['te_ID'] && $arr['pos-neg'] == 'POS') {
				$controlarr = trpdd_check_controls_pcr($testdb->te_inconclusive_ID, $arr['report_id'], $instrument, $datereport);
				if (in_array($controlarr, array('NEG', 'INV'))) {
					$can_go_neg = false;
					$arr['pos-neg'] = 'INC';
					$high_med_low = '';
					$pass_equa = false;
				}
			}


			if ($pass_equa) {
				if (!$testdb->te_is_detected || $arr['only_detected']) {
					$arr['pos-neg'] = 'POS';
				}
			} else if ($has_condition && in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PCH', 'PCL', 'PVH', 'PVL', 'IND'))) {
				$arr['pos-neg'] = 'NEG';
			} else if (
				$can_go_neg
				&& (in_array($arr['old_pos-neg'], array('INV', 'INC'))
					|| (empty($arr['pos-neg']) && $has_condition)
				)
			) {
				$arr['pos-neg'] = 'NEG';
			}


			if ($is_amp_cq) {
				$arr['ct_pos_neg'] = $arr['pos-neg'];
				$arr['pos-neg'] = 'NEG';
				$pass_equa = false;


				$arr['amp_pos_neg'] = 'NEG';
				if (!empty($arr['amp_score']) && !empty($testdb->te_amp_score)) {
					if (tr_pdd_pass_condition($arr['amp_score'], $testdb->te_amp_score, '>')) {
						$arr['amp_pos_neg'] = 'POS';
					}
				}
				$arr['cq_pos_neg'] = 'NEG';
				if (!empty($arr['cq']) && !empty($testdb->te_cq)) {
					if (tr_pdd_pass_condition($arr['cq'], $testdb->te_cq, '>')) {
						$arr['cq_pos_neg'] = 'POS';
					}
				}

				if (in_array($arr['ct_pos_neg'], array('POS', 'PVL', 'POL', 'POM', 'POH', 'PVH')) && $arr['amp_pos_neg'] == 'POS' && $arr['cq_pos_neg'] == 'POS') {
					$arr['pos-neg'] = $arr['ct_pos_neg'];
					$pass_equa = true;
				} else if ($xeno_control_pos_neg == 'POS') {
					$arr['pos-neg'] = 'NEG';
				}

				if (stripos($arr['drug'], 'xeno') !== false) {
					$xeno_control_pos_neg = $arr['pos-neg'];
				}

				if (isset($_GET['debugrpp'])) {
					var_dump($arr);
					echo '<br>';
				}
			}
		}

		if ($is_amp_cq && $is_whqc && !isset($arr['checked2'])) {

			$arr['is_whqc'] = true;

			if (!isset($arr['dbdrug']) || empty($arr['dbdrug'])) {
				$arr['dbdrug'] = $arr['drug'];
			}
			$arr['dbdrug'] = strtoupper($arr['dbdrug']);
			$drugs2 	= array(
				$arr['drug'] . '-2' => $arr['dbdrug'] . '-2',
				$arr['drug'] . '-3' => $arr['dbdrug'] . '-3',
				$arr['drug'] . '-4' => $arr['dbdrug'] . '-4',
				$arr['drug'] . '-5' => $arr['dbdrug'] . '-5',
				$arr['drug'] . '-6' => $arr['dbdrug'] . '-6',
			);

			$drugs2_str = implode("','", $drugs2);

			$check2s_rs = $wpdb->get_results("select * 
			from ci_results_pcr 
			where report_id = {$arr['report_id']} 
			and drug in ( '{$drugs2_str}' ) ", ARRAY_A);


			$check2s = array();
			foreach ($check2s_rs as $arr2) {
				$check2s[$arr2['drug']] = $arr2;
			}
			if (isset($_GET['debugrpp2'])) {
				echo $arr['te_name'] . ' - check2s:';
				echo count($check2s);

				echo '<br>';
				echo '<br>';
			}
			if (count($check2s) > 0) {
				$count_pos = 0;

				$arr['conc_detected'] = array();
				$arr1 = $arr;
				$arr1['hidedrug'] = true;
				$arr['list'] = array($arr['drug'] => $arr1);

				if (in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PCH', 'PCL', 'PVH', 'PVL'))) {
					$count_pos++;
					$arr['conc_detected'][] = $arr['conc'];
				}


				foreach ($drugs2 as $drug2 => $dbdrug2) {
					if (!isset($check2s[$drug2]) && !isset($check2s[$dbdrug2])) {
						continue;
					}
					$arr2 = isset($check2s[$drug2]) ? $check2s[$drug2] : $check2s[$dbdrug2];
					if (empty($arr2)) {
						//get $arr2 from file
						$arr2 = trpdd_get_WH_arr($arr['report_id'], $drug2, $arr['te_abrev']);
					}

					$arrtemp = $arr1;

					$arrtemp['checked2'] 	= true;
					$arrtemp['id'] 			= $arr2['id'];
					$arrtemp['drug'] 		= $arr2['drug'];
					$arrtemp['conc'] 		= $arr2['conc'];
					$arrtemp['pos-neg'] 	= $arr2['pos-neg'];
					$arrtemp['cq'] 			= $arr2['cq'];
					$arrtemp['amp_score'] 	= $arr2['amp_score'];
					$arrtemp['hidedrug'] 	= true;
					$arrtemp['parsed_test' . $group_id] = false;


					$arrtemp = tr_pdd_parse_pcr_test($arrtemp, $instrument, $group_id, $datereport, $tests_str);
					$arr['list'][$drug2] = $arrtemp;

					if (in_array($arrtemp['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PCH', 'PCL', 'PVH', 'PVL'))) {
						$count_pos++;
						$arr['pos-negar'] = $arrtemp['pos-neg'];
						$arr['conc_detected'][] = $arrtemp['conc'];
					}
				}

				$num_rpos = count($arr['list']) / 2;

				if ($count_pos > $num_rpos) {
					if ($arr['pos-neg'] == 'NEG') {
						$arr['pos-neg'] = $arr['pos-negar'];
					}
					if (count($arr['conc_detected']) > 0) {
						$arr['conc_avg'] = array_sum($arr['conc_detected']) / count($arr['conc_detected']);
						$arr['conc'] = $arr['conc_avg'];
					}
				} else {
					$arr['pos-neg'] = 'NEG';
				}
				$arr['ismain'] = true;
				$arr['count_pos'] = $count_pos;
				$arr['num_rpos'] = $num_rpos;

				if (isset($_GET['debug_amp'])) {
					var_dump($arr['te_name']);
					echo '=> count rows : ' . count($arr['list']);
					echo '=> $count_pos: ' . $count_pos;
					echo '=> $num_rpos: ' . $num_rpos;
					echo '<br>';
				}
			}
		}


		if (empty($arr['comments'])) {
			if ($arr['pos-neg'] == 'NEG') {
				$arr['comments'] = $arr['te_comments_neg'];
			} else if (in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PCH', 'PCL', 'PVH', 'PVL'))) {
				$arr['comments'] = $arr['te_comments'];
			} else if (in_array($arr['pos-neg'], array('IND')) && isset($arr['te_comments_ind'])) {
				$arr['comments'] = $arr['te_comments_ind'];
			}
		}


		if ($arr['pos-neg'] == 'NEG') {
			$arr['notes'] = $arr['te_pdf_note_neg'];
			if (isset($arr['te_pdf_file_neg'])) {
				$arr['pdf2file'] = $arr['te_pdf_file_neg'];
			}
		} else if (in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PCH', 'PCL', 'PVH', 'PVL'))) {
			$arr['notes'] = $arr['te_pdf_note_pos'];
			if (isset($arr['te_pdf_file_pos'])) {
				$arr['pdf2file'] = $arr['te_pdf_file_pos'];
			}
		}

		$update = array();
		if ($group_id > 0 && $arr['old_pos-neg'] != $arr['pos-neg'] && $arr['id'] > 0) {
			$update['pos-neg'] = $arr['pos-neg'];
		}
		if (!isset($arr['high_med_low']) || is_null($arr['high_med_low']) || $arr['high_med_low'] != $high_med_low) {
			$update['high_med_low'] = $high_med_low;
		}
		if (empty($arr['group_id']) || $arr['group_id'] != $group_id) {
			$update['group_id'] = $group_id;
		}

		if (count($update) > 0 && $arr['id'] > 0) {
			$wpdb->update('ci_results_pcr', $update, array('id' => $arr['id']));
		}

		$arr['te_medium'] = $testdb->te_medium;
		$arr['te_critical_high'] = $testdb->te_critical_high;
		$arr['te_upper_limit'] = $testdb->te_upper_limit;

		$arr['antibiotic'] = $testdb->te_antibiotic;
		$arr['parsed_test' . $group_id] = true;

		$arr['detected_type'] = $testdb->te_is_detected;


		if ($arr['pos-neg'] == 'PVL' && !empty($testdb->te_critical_low_concen)) {
			$arr['flag_adding'] =  $testdb->te_critical_low_concen;
		} else if ($arr['pos-neg'] == 'POL' && !empty($testdb->te_cutoff_concen)) {
			$arr['flag_adding'] =  $testdb->te_cutoff_concen;
		} else if ($arr['pos-neg'] == 'POM' && !empty($testdb->te_medium_concen)) {
			$arr['flag_adding'] =  $testdb->te_medium_concen;
		} else if ($arr['pos-neg'] == 'POH' && !empty($testdb->te_upper_limit_concen)) {
			$arr['flag_adding'] =  $testdb->te_upper_limit_concen;
		} else if ($arr['pos-neg'] == 'PVH' && !empty($testdb->te_te_critical_high_concen)) {
			$arr['flag_adding'] =  $testdb->te_te_critical_high_concen;
		}

		if (!empty($arr['flag_adding'])) {
			$arr['flag_adding'] = preg_replace("/\^([0-9]+)/", '<sup>$1</sup>', $arr['flag_adding']);
		}
	} else {

		if (($arr['conc'] === '0' || $arr['conc'] === '0.0') && empty($arr['pos-neg'])) {
			$arr['pos-neg'] = 'NEG';
		}
		$arr['notfound'] = true;
		$arr['only_detected'] = !in_array($arr['pos-neg'], array('POL', 'POM', 'POH', 'PCH', 'PCL', 'PVH', 'PVL'));

		if ($arr['pos-neg'] == 'NEG' && empty($arr['comments'])) {
			$arr['comments'] = $arr['te_comments_neg'];
		} else if (in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'PVL', 'PVH')) && empty($arr['comments'])) {
			$arr['comments'] = $arr['te_comments'];
		}

		if ($arr['te_control_by_ID'] > 0) {
			$controlarr = trpdd_check_controls_pcr($arr['te_control_by_ID'], $arr['report_id'], $instrument, $datereport);
			if (in_array($controlarr['pos-neg'], array('NEG'))) {
				$arr['pos-neg'] = 'INV';
			}
		}
	}

	if (tr_get_option('ci_result_settings_pcr', 'enable_verylh')) {
		$arr['verylh'] = true;
	}

	$pos_neg_vls = array(
		'' => 'Blank',
		'NEG' => 'Negative',
		'POS' => 'Positive',
		'PEN' => 'Pending',
		'INC' => 'Inconclusive',
		'INV' => 'Invalid',
		'N/A' => 'N/A'
	);

	if (isset($arr['is_covid']) && $arr['is_covid']) {

		$pos_neg_vls = array(
			'' => 'Select',
			'POS' => $pos_neg_vls['POS'],
			'NEG' => $pos_neg_vls['NEG'],
			'RER' => 'ReRun',
			'INC' => 'Inconclusive',
			'INV' => 'Invalid'
		);
	} else if (isset($arr['pos-neg']) && $arr['pos-neg'] == 'RER') {
		$pos_neg_vls['RER'] = 'ReRun';
	}


	if ($arr['te_is_detected'] == '4') {

		$pos_neg_vls['POS'] = 'Detected';
		$pos_neg_vls['NEG'] = 'Not Detected';
		$pos_neg_vls['IND'] = 'Indeterminate';
		if (in_array($arr['pos-neg'], array('POL'))) {
			$arr['pos-neg'] = 'IND';
		} else if (in_array($arr['pos-neg'], array('POH', 'POM', 'PVH'))) {
			$arr['pos-neg'] = 'POS';
		}
	} elseif ($arr['te_is_detected'] == '3') {

		$pos_neg_vls['NEG'] = 'Normal';
		$pos_neg_vls['POS'] = 'Elevated';
		if (in_array($arr['pos-neg'], array('POH', 'POM', 'POL'))) {
			$arr['pos-neg'] = 'POS';
		}
	} elseif ($arr['te_is_detected'] == '2') {
		if (isset($arr['verylh'])) {
			$pos_neg_vls['PVL'] = 'Detected-Very Low';
		}
		$pos_neg_vls['POL'] = 'Detected-Low';
		$pos_neg_vls['NEG'] = 'Normal';
		$pos_neg_vls['POM'] = tr_get_option('ci_result_settings_pcr', 'pos_moderate', false) ? 'Detected-Moderate' : 'Detected-Medium';
		$pos_neg_vls['POH'] = 'Detected-High';
		if (isset($arr['verylh'])) {
			$pos_neg_vls['PVH'] = 'Detected-Very High';
		}
		unset($pos_neg_vls['POS']);
		if ($arr['pos-neg'] == 'POS') {
			$arr['pos-neg'] = 'POH';
		}
	} else if ($arr['te_is_detected'] == '1') {

		$pos_neg_vls['POS'] = 'Detected';
		$pos_neg_vls['NEG'] = 'Not Detected';
		if (!$arr['only_detected']) {
			if (isset($arr['verylh'])) {
				$pos_neg_vls['PVL'] = 'Detected-Very Low';
			}
			$pos_neg_vls['POL'] = 'Detected-Low';
			$pos_neg_vls['POM'] = tr_get_option('ci_result_settings_pcr', 'pos_moderate', false) ? 'Detected-Moderate' : 'Detected-Medium';
			$pos_neg_vls['POH'] = 'Detected-High';
			if (isset($arr['verylh'])) {
				$pos_neg_vls['PVH'] = 'Detected-Very High';
			}
			unset($pos_neg_vls['POS']);
			if ($arr['pos-neg'] == 'POS') {
				$arr['pos-neg'] = 'POL';
			}
		}
	}
	if ($arr['te_inconclusive_ID'] === '-1') {
		$pos_neg_vls['POS'] = 'SUFFICIENT';
		$pos_neg_vls['NEG'] = 'INSUFFICIENT';
	}
	if (stripos($arr['te_name'], 'MonkeyPox') !== false) {
		$pos_neg_vls['EQU'] = 'Equivocal';
		$pos_neg_vls['INC'] = 'Inconclusive';
	}

	$arr['pos_neg_list'] 	= $pos_neg_vls;
	$arr['flag'] 			= !empty($arr['pos-neg']) && isset($pos_neg_vls[$arr['pos-neg']]) ? $pos_neg_vls[$arr['pos-neg']] : '';


	if (isset($arr['te_conc_decimal']) && $arr['te_conc_decimal'] > 0) {
		$arr['conc'] = !empty($conc) ? number_format(floatval($conc), $arr['te_conc_decimal'] > 1 ? $arr['te_conc_decimal'] : 1) : '';
	}


	return $arr;
}

function trpdd_check_controls_pcr($testid, $report_id, $instrument, $datereport = null)
{
	global $wpdb, $list_checked_inconclusive_pcr;
	$key = $testid . '_' . $report_id;

	if (!is_array($list_checked_inconclusive_pcr)) {
		$list_checked_inconclusive_pcr = array();
	}
	if (isset($list_checked_inconclusive_pcr[$key])) {
		return $list_checked_inconclusive_pcr[$key];
	}
	$sql = "select * from ci_test_panels te
            left join ci_results_pcr rs ON report_id = {$report_id} 
			and (te.te_name = rs.drug or te.te_abrev = rs.drug )
            where te.te_ID = {$testid}";
	$arr = $wpdb->get_row($sql, ARRAY_A);
	$arr['drug'] = strtoupper($arr['te_name']);
	if (empty($arr['id'])) {
		$arr['id'] = 'new_t' . $testid;
	}
	$arr = tr_pdd_parse_pcr_test($arr, $instrument, $arr['te_group_id'], $datereport);

	$list_checked_inconclusive_pcr[$key] = $arr; //in_array($arr['pos-neg'], array('NEG', 'INV'));
	return $list_checked_inconclusive_pcr[$key];
}

function tr_pdd_parse_req_test($arr, $instrument, $group_id, $report)
{

	if ($arr['te_type'] == 'blood') {
		$arr['te_type'] = 'req';
		$arr = tr_pdd_parse_blood_test($arr, $instrument, $group_id, $report);
	} else if ($arr['te_type'] == 'pcr') {
		$arr['te_type'] = 'req';
		$arr['type_of_specimen'] = $report['type_of_specimen'];
		$arr = tr_pdd_parse_pcr_test($arr, $instrument, $group_id, $report['created_date']);
	}
	return $arr;
}

function tr_pdd_parse_blood_test($arr, $instrument, $group_id = 0, $report = array())
{
	global $wpdb, $all_res;
	global $current_cal_btype;
	$current_cal_btype = 'blood';
	static $all_tests_blood;
	static $all_group_blood;


	if (!isset($arr['critical'])) {
		$arr['critical'] = '';
	}

	$is_cancel = false;
	$arr['old_pos-neg']  = $arr['pos-neg'];
	if ($arr['pos-neg'] === 'CAN') {
		$is_cancel = true;
	}

	if (!is_array($report)) {
		$report = (array)$report;
	}

	$datereport = isset($report['doc']) ? $report['doc'] : (isset($report['created_date']) ? $report['created_date'] : '');
	if (empty($datereport)) {
		$datereport = date_i18n('Y-m-d');
	}

	$gender = isset($report['pa_gender']) && !empty($report['pa_gender']) ? $report['pa_gender'] : (isset($report['gender']) ? $report['gender'] : '');

	if (isset($_GET['test_gender']) && !empty($_GET['test_gender'])) {
		$gender = substr($_GET['test_gender'], 0, 1);
	}
	$gender = strtoupper($gender);
	$fasting = isset($report['fasting']) && $report['fasting'] ? 'fasting' : 'nonfasting';

	$dob = isset($report['pa_dob']) && !empty($report['pa_dob']) ? $report['pa_dob'] : (isset($report['dob']) ? $report['dob'] : '');

	if (isset($_GET['test_dob']) && !empty($_GET['test_dob'])) {
		$dob = date('Y-m-d', strtotime($_GET['test_dob']));
	}

	if (!empty($arr['instrument'])) {
		$instrument = $arr['instrument'];
	}

	if (stripos($instrument, 'Liaison') !== false) {
		$mc = 'Liaison';
	}

	$instrument = trim(strtoupper($instrument));
	if (empty($instrument)) {
		$instrument = '*';
	}

	if (empty($all_group_blood)) {
		$rs = $wpdb->get_results("select * from ci_test_groups where tg_type = 'blood'");
		foreach ($rs as $r) {
			$r->tg_name = strtoupper($r->tg_name);
			$all_group_blood[$r->tg_ID] = $r;
			$all_group_blood[$r->tg_name] = $r->tg_ID;
		}
	}


	//get group_id from group name
	if (!empty($group_id) && is_string($group_id) && $group_id == 0) {
		$group_name = strtoupper($group_id);
		if (isset($all_group_blood[$group_name]) && !is_object($all_group_blood[$group_name])) {
			$group_id = $all_group_blood[$group_name];
		}
	}

	if ($group_id > 0 && isset($all_group_blood[$group_id])) {
		$current_group = $all_group_blood[$group_id];
	}

	if (isset($arr['parsed_test' . $group_id]) && $arr['parsed_test' . $group_id]) {
		return $arr;
	}

	if (empty($all_tests_blood[$datereport])) {
		$all_tests_blood[$datereport] = array();
		$rs = $wpdb->get_results("select * from ci_test_panels where te_type='blood' ");
		foreach ($rs as $r) {
			$r->te_name = strtoupper($r->te_name);
			$sql_cutoff = "select * from ci_test_panels_cutoffs where cu_te_ID = {$r->te_ID} 
			                and (cu_start is null or cu_start ='' or cu_start <= '{$datereport}' ) 
			                and (cu_end is null or cu_end = '' or cu_end >= '{$datereport}')";

			$cutoffs = $wpdb->get_results($sql_cutoff);

			foreach ($cutoffs as $cut) {
				$cut->cu_ms = trim($cut->cu_ms);

				if (empty($cut->cu_ms)) {
					$cut->cu_ms = '*';
				}

				$mcs = explode(',', $cut->cu_ms);
				foreach ($mcs as $mc) {
					if (empty($mc)) {
						continue;
					}
					if (stripos($mc, 'Liaison') !== false) {
						$mc = 'Liaison';
					}
					$mc = trim(strtoupper($mc));


					$newr = clone $r;

					$newr->te_critical_low = $cut->cu_critical_low;
					$newr->te_critical_high = $cut->cu_critical_high;
					$newr->te_cutoff = $cut->cu_cutoff;
					$newr->te_upper_limit = $cut->cu_upper_limit;
					$newr->te_medium = $cut->cu_medium;
					$newr->te_invalid = $cut->cu_invalid;
					$newr->te_ul = isset($cut->cu_ul) ? $cut->cu_ul : '';
					$newr->te_ll = isset($cut->cu_ll) ? $cut->cu_ll : '';
					$newr->fasting = '';

					if (!isset($newr->cu_medium)) {
						$newr->cu_medium = '';
					}
					if (stripos($newr->cu_medium, 'non-fasting') !== false) {
						$newr->fasting = 'nonfasting';
						$newr->cu_medium = '';
					} else if (stripos($newr->cu_medium, 'fasting') !== false) {
						$newr->fasting = 'fasting';
						$newr->cu_medium = '';
					}

					if (!empty($cut->cu_gender)) {
						$mc .= '_' . strtoupper($cut->cu_gender);
					}
					if (!empty($newr->fasting)) {
						$mc .= '_' . $newr->fasting;
					}

					$newr->mc = $mc;



					if (!empty($cut->cu_age)) {
						if (
							!isset($all_tests_blood[$datereport][$newr->te_name . '_' . $mc])
							|| !is_array($all_tests_blood[$datereport][$newr->te_name . '_' . $mc])
						) {
							$all_tests_blood[$datereport][$newr->te_name . '_' . $mc] = array();
						}
						if (
							!isset($all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc])
							|| !is_array($all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc])
						) {
							$all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc] = array();
						}
						if (
							!isset($all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id])
							|| !is_array($all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id])
						) {
							$all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id] = array();
						}
						$all_tests_blood[$datereport][$newr->te_name . '_' . $mc][$cut->cu_age] = $newr;
						$all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc][$cut->cu_age] = $newr;
						$all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id][$cut->cu_age] = $newr;
					} else {
						$all_tests_blood[$datereport][$newr->te_name . '_' . $mc] = $newr;
						$all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc] = $newr;
						$all_tests_blood[$datereport][$newr->te_name . '_' . $newr->te_group_id] = $newr;
					}
				}
			}
		}
	}

	$arr['fasting'] = $fasting;
	$arr['drug'] = strtoupper($arr['te_name']);
	$check_keys = array();
	if ($group_id > 0) {
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $instrument . '_' . $gender . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $instrument . '_' . $gender;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $instrument . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $instrument;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_*_' . $gender . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_*_' . $gender;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_*' . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_*';
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id;
	} else {
		$check_keys[] = $arr['drug'] . '_' . $instrument . '_' . $gender . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $instrument . '_' . $gender;
		$check_keys[] = $arr['drug'] . '_*_' . $gender . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_*_' . $gender;
		$check_keys[] = $arr['drug'] . '_*' . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_*';
	}


	$arr['keypair'] = '';
	foreach ($check_keys as $check_key) {
		if (isset($all_tests_blood[$datereport][$check_key])) {
			$arr['keypair'] = $check_key;
			break;
		}
	}

	$testdb = isset($all_tests_blood[$datereport][$arr['keypair']]) ? $all_tests_blood[$datereport][$arr['keypair']] : '';

	//check age
	$age_arr = array();
	if (is_array($testdb)) {
		$tmparray = $testdb;
		$testdb = false;
		$age_arr = trdd_dob2ages($dob, $datereport, true);
		$arr['dob'] = $dob;
		$arr['ages'] = $age_arr;

		foreach ($tmparray as $age_range => $testdbtmp) {
			if (tr_pdd_pass_condition_ages($age_arr, $age_range)) {
				$testdb = $testdbtmp;
				$arr['age_range'] = $age_range;
				break;
			}
		}
	}

	//calculation value
	$arr['has_calculation'] = false;
	if (isset($arr['te_calculation']) && strlen($arr['te_calculation']) > 3) {
		try {
			global $report_id, $calculator_no_value, $current_test_calculation;
			$report_id = $report['id'] > 0 ? $report['id'] : $arr['report_id'];
			$calculator_no_value = false;

			$current_test_calculation = $arr;
			$current_test_calculation['cal_testids'] = array();

			$replace_female = '';
			$replace_male = '';
			$replace_gender = '';
			$replace_gender_female = '';
			$replace_gender_male = '';
			if ($gender == 'F') {
				$replace_female = ' $1 ';
				$replace_gender = ' $1 ';
				$replace_gender_female = ' $1 ';
			} else {
				$replace_male = ' $1 ';
				$replace_gender = ' $2 ';
				$replace_gender_male = ' $2 ';
			}
			if (!isset($age_arr['year'])) {
				$age_arr = trdd_dob2ages($dob, $datereport, true);
			}
			$age = $age_arr['year'];

			if (isset($_GET['debuge'])) {
				echo '<br>';
				echo '<b>' . $arr['drug'] . '</b> <br> ';
				echo ($arr['te_calculation']);
				echo '<br>';
			}
			$arr['raw_calculation'] = $arr['te_calculation'];
			$arr['te_calculation'] = preg_replace('/\[female\]([^\[]+)\[\/female\]/', $replace_female, $arr['te_calculation']);
			$arr['te_calculation'] = preg_replace('/\[male\]([^\[]+)\[\/male\]/', $replace_male, $arr['te_calculation']);
			$arr['te_calculation'] = str_replace('[age]', $age, $arr['te_calculation']);
			$arr['te_calculation'] = str_replace(' x ', ' * ', $arr['te_calculation']);
			$arr['te_calculation'] = str_replace(' : ', ' / ', $arr['te_calculation']);
			$arr['te_calculation'] = preg_replace_callback('/\[([A-Za-z0-9\-\s\=]+)\]/', 'trpdd_replace_calculation', $arr['te_calculation']);

			$arr['te_calculation'] = preg_replace('/\[gender\s+female="([^\"]+)"\s+male="([^\"]+)"\s*\]/', $replace_gender, $arr['te_calculation']);
			$arr['te_calculation'] = preg_replace('/\[gender\s+female="([^\"]+)"\s*\]/', $replace_gender_female, $arr['te_calculation']);
			$arr['te_calculation'] = preg_replace('/\[gender\s+male="([^\"]+)"\s*\]/', $replace_gender_male, $arr['te_calculation']);


			if ($calculator_no_value) {
				$arr['pos-neg'] = '';
				$arr['conc'] = '';
				$arr['cal_missing'] = true;

				if (isset($_GET['debuge'])) {
					echo ' == missing value';;
					echo '<br><br>';
				}
			} else {
				$conc = '';
				if (!empty($arr['te_calculation'])) {
					if (isset($_GET['debuge'])) {
						echo ($arr['te_calculation']);
					}

					$eval_str = '$conc = ' . $arr['te_calculation'] . ';';

					try {
						eval($eval_str);
					} catch (Error $e) {
					}
				}

				if (isset($_GET['debuge'])) {
					echo ' == ' . $conc;
					echo '<br><br>';
				}

				if (is_float($conc) && (is_nan($conc) || is_infinite($conc))) {
					$conc = '';
				} else {
					$arr['conc'] = !empty($conc) ? number_format(floatval($conc), $arr['te_conc_decimal'] > 1 ? intval($arr['te_conc_decimal']) : 1) : '';
					$arr['has_calculation'] = true;

					//check default =0;
					if (stripos($arr['raw_calculation'], '=0') > 0) {
						if (isset($_GET['debuge'])) {
							echo 'has =0<br>';
							var_dump($current_test_calculation['cal_testids']);
							echo '<br>';
						}
						foreach ($current_test_calculation['cal_testids'] as $tidc) {
							if ($conc == 0) {
								if (isset($all_res[$tidc]) && is_null($all_res[$tidc]['conc'])) {
									$all_res[$tidc]['conc'] = 0;
								}
							} else {
								if (isset($_GET['debuge'])) {
									var_dump(isset($all_res[$tidc]));
								}
								if (isset($all_res[$tidc]) && $all_res[$tidc]['conc'] == 0) {
									$all_res[$tidc]['conc'] = 'unknown';
									if (isset($_GET['debuge'])) {
										echo $tidc . ' =0<br>';
									}
								}
							}
						}
					}
				}
			}
		} catch (Exception $e) {
			var_dump($e);
		}
	}

	if (!isset($arr['conc'])) {
		$arr['conc'] = '';
	}
	$arr['conc'] = str_replace(',', '', $arr['conc']);


	if (!isset($arr['conc_dis'])) {
		$arr['conc_dis'] = $arr['conc'];

		if (strpos($arr['conc_dis'], '.') > 0 && ($arr['te_conc_decimal'] > 0 || $arr['te_conc_decimal'] === '0')) {
			$has_percent = strpos($arr['conc_dis'], '%') > 0;
			$comparechar = '';
			if (strpos($arr['conc_dis'], '>') === 0) {
				$comparechar = '>';
			} else if (strpos($arr['conc_dis'], '<') === 0) {
				$comparechar = '<';
			}
			$arr['conc_dis'] = str_replace(array('>', '<', '=', '%'), '', $arr['conc_dis']);
			$arr['conc_dis'] = number_format(floatval($arr['conc_dis']), intval($arr['te_conc_decimal']));
			$arr['conc_dis'] = $comparechar . $arr['conc_dis'];
			if ($has_percent) {
				$arr['conc_dis'] .= '%';
			}
		}

		if (isset($arr['te_comparison_symbol']) && strlen($arr['te_comparison_symbol']) > 2 && strlen($arr['conc']) > 0) {
			if (strpos($arr['conc_dis'], '>') === false && strpos($arr['conc_dis'], '<') === false) {
				$tsymbol = '';
				if (stripos($arr['te_comparison_symbol'], '<') !== false) {
					$tsymbol = '<';
				} else if (stripos($arr['te_comparison_symbol'], '>') !== false) {
					$tsymbol = '>';
				}

				if (!empty($tsymbol) && strpos($arr['conc'], '<') === false && strpos($arr['conc'], '>') === false) {
					$symnum = str_replace(array('>', '<', '%'), '', $arr['te_comparison_symbol']);
					$concnum = str_replace('%', '', $arr['conc']);
					if ($tsymbol == '<' && $concnum < $symnum) {
						$arr['conc'] = $arr['conc_dis'] = $arr['te_comparison_symbol'];
					} else if ($tsymbol == '>' && $concnum > $symnum) {
						$arr['conc'] = $arr['conc_dis'] = $arr['te_comparison_symbol'];
					}
				}
			}
		}
	}


	if (isset($_GET['debugtest']) && stripos($arr['te_name'], $_GET['debugtest']) !== false) {
		echo '<br><br>$testdb:';
		var_dump($testdb);
		echo '<br><br>';
		var_dump($arr);
		echo '<br><br>';
		echo '$group_id: ';
		var_dump($group_id);
		echo '<br><br>';
	}
	if ($testdb) {


		$arr['only_detected'] = false;
		$arr['has_critical'] = !empty($testdb->te_critical_high) || !empty($testdb->te_critical_low);

		if (empty($testdb->te_medium) && (empty($testdb->te_cutoff) || empty($testdb->te_upper_limit))) {
			$arr['only_detected'] = true;
		} else if (empty($testdb->te_cutoff) && empty($testdb->te_upper_limit)) {
			$arr['only_detected'] = true;
		}


		if (!empty($testdb->te_cutoff) && !empty($testdb->te_upper_limit)) {
			$arr['range'] = trpdd_show_symbol($testdb->te_cutoff) . ' - ' . trpdd_show_symbol($testdb->te_upper_limit);
			$arr['range'] = str_replace(array('<', '>', '='), '', $arr['range']);
		} else if (!empty($testdb->te_cutoff)) {
			if (strpos($testdb->te_cutoff, '-') > 0) {
				$arr['range'] = trpdd_show_symbol($testdb->te_cutoff);
			} else if (stripos($testdb->te_cutoff, '<') !== false) {
				$arr['range'] = $testdb->te_cutoff;
				$arr['range'] = trpdd_show_symbol(str_replace('<=', '>', $arr['range']));
			} else {
				if (strtolower($testdb->te_cutoff) == 'none') {
					$arr['range'] = $testdb->te_cutoff;
				} else {
					$arr['range'] = trpdd_show_symbol('>=' . $testdb->te_cutoff);
				}
			}
		} else if (!empty($testdb->te_upper_limit)) {
			if (strpos($testdb->te_upper_limit, '-') > 0) {
				$arr['range'] = trpdd_show_symbol($testdb->te_upper_limit);
			} else if (stripos($testdb->te_upper_limit, '>') !== false) {
				$arr['range'] = $testdb->te_upper_limit;
				$arr['range'] = trpdd_show_symbol(str_replace('>=', '<', $arr['range']));
			} else {
				if (strtolower($testdb->te_upper_limit) == 'none') {
					$arr['range'] = $testdb->te_upper_limit;
				} else {
					$arr['range'] = trpdd_show_symbol('<=' . $testdb->te_upper_limit);
				}
			}
		}

		$arr['notfound']    = false;
		$pass_equa          = false;
		$need_go_to_neg     = false;



		if (!empty($arr['conc']) && $arr['conc'] != 'Undetermined') {

			$arr['conc'] = str_replace(array('>', '<', '='), '', $arr['conc']);
			$arr['conc'] = trim($arr['conc']);


			$arr['old_pos-neg'] = $arr['pos-neg'];
			if (!empty($testdb->te_upper_limit) || !empty($testdb->te_cutoff)) {
				$arr['pos-neg'] = 'NEG';
			}

			//check High
			if (!$pass_equa && !empty($testdb->te_upper_limit)) {
				$limitnum = str_replace(array('<', '>', '=', '-'), '', $testdb->te_upper_limit);
				if (empty($testdb->te_cutoff) && floatval($limitnum) == 0) {
					if (strtoupper($arr['conc']) != strtoupper($testdb->te_upper_limit)) {
						$arr['pos-neg'] = 'POS';
					}
				} else if (tr_pdd_pass_condition($arr['conc'], $testdb->te_upper_limit, '>')) {
					$arr['pos-neg'] = 'POH';
					$pass_equa = true;
				} else {
					$need_go_to_neg = true;
				}

				if (!empty($testdb->te_critical_high) && tr_pdd_pass_condition($arr['conc'], $testdb->te_critical_high, '>')) {
					$pass_equa = true;
					$arr['critical'] = 'H';
					$arr['pos-neg'] = 'PCH';
					$need_go_to_neg = false;

					if ($arr['te_type'] == 'req') {
						$arr['pos-neg'] = 'PCH';
						$arr['critical'] = '';
						$arr['conc_dis'] = $testdb->te_critical_high;
					}
				}
			} else if (empty($testdb->te_upper_limit) && !empty($testdb->te_critical_high) && tr_pdd_pass_condition($arr['conc'], $testdb->te_critical_high, '>')) {
				$arr['critical'] = 'H';
				$arr['pos-neg'] = 'PCH';
				$pass_equa = true;

				if ($arr['te_type'] == 'req') {
					$arr['pos-neg'] = 'PCH';
					$arr['critical'] = '';
					$arr['conc_dis'] = $testdb->te_critical_high;
				}
			}


			//check LOW
			if (!$pass_equa && !empty($testdb->te_cutoff)) {
				$low_condition = !empty($testdb->te_upper_limit) ? '<' : '<=';
				if (!empty($testdb->te_critical_low) && tr_pdd_pass_condition($arr['conc'], $testdb->te_critical_low, '<')) {
					$arr['critical'] = 'L';
					$arr['pos-neg'] = 'PCL';
					$pass_equa = true;
					if ($arr['te_type'] == 'req') {
						$arr['pos-neg'] = 'POL';
						$arr['critical'] = '';
						$arr['conc_dis'] = $testdb->te_critical_low;
					}
				} else if (tr_pdd_pass_condition($arr['conc'], $testdb->te_cutoff, $low_condition)) {
					$arr['pos-neg'] = 'POL';
					$pass_equa = true;
				} else {
					$need_go_to_neg = true;
				}
			} else if (empty($testdb->te_cutoff) && !empty($testdb->te_critical_low) && tr_pdd_pass_condition($arr['conc'], $testdb->te_critical_low, '<')) {
				$arr['critical'] = 'L';
				$arr['pos-neg'] = 'PCL';
				$pass_equa = true;
				if ($arr['te_type'] == 'req') {
					$arr['pos-neg'] = 'POL';
					$arr['critical'] = '';
					$arr['conc_dis'] = $testdb->te_critical_low;
				}
			}

			if (!isset($_GET['act']) || $_GET['act'] != 'edit') {
				if (isset($_GET['html'])) {
					var_dump($testdb->te_ul);
				}
				if (($pass_equa || strpos($testdb->te_ul, '>') !== false) && !empty($testdb->te_ul) && tr_pdd_pass_condition($arr['conc'], $testdb->te_ul, '>')) {
					$arr['conc_dis'] = $testdb->te_ul;
				} else if (($pass_equa || strpos($testdb->te_ll, '<') !== false) && !empty($testdb->te_ll) && tr_pdd_pass_condition($arr['conc'], $testdb->te_ll, '<')) {
					$arr['conc_dis'] = $testdb->te_ll;
				}
			}


			//check Medium
			if (!empty($arr['conc']) && !empty($testdb->te_medium)) {
				$has_condition = true;
				if (tr_pdd_pass_condition($arr['conc'], $testdb->te_medium)) {
					$arr['pos-neg'] = 'NEG';
					$pass_equa = false;
				}
			}



			if ($pass_equa) {
				if (!$testdb->te_is_detected || $arr['only_detected'] && !in_array($arr['pos-neg'], array('PCL', 'PCH'))) {
					$arr['pos-neg'] = 'POS';
				}
			} else if ($need_go_to_neg) {
				$arr['pos-neg'] = 'NEG';
			}


			if ($group_id > 0 && $arr['old_pos-neg'] != $arr['pos-neg'] && $arr['id'] > 0 && !$is_cancel) {
				$wpdb->update('ci_results_blood', array('pos-neg' => $arr['pos-neg']), array('id' => $arr['id']));
			}
		}

		if (!empty($testdb->te_upper_limit)) {
			$arr['low'] = $testdb->te_cutoff;
			$arr['high'] = $testdb->te_upper_limit;
		} else {
			$arr['low'] = stripos($testdb->te_cutoff, '-') > 0 ? $testdb->te_cutoff : '<' . $testdb->te_cutoff;
		}
		$arr['te_ll'] = $testdb->te_ll;
		$arr['te_ul'] = $testdb->te_ul;
		$arr['te_medium'] = $testdb->te_medium;
		$arr['te_critical_high'] = $testdb->te_critical_high;
		$arr['te_upper_limit'] = $testdb->te_upper_limit;

		$arr['antibiotic'] = $testdb->te_antibiotic;
		$arr['parsed_test' . $group_id] = true;

		$arr['detected_type'] = $testdb->te_is_detected;
	} else {
		$arr['notfound'] = true;
	}

	if (isset($arr['te_range']) && !empty($arr['te_range'])) {
		$arr['range'] = $arr['te_range'];
	}
	if (!isset($arr['pos-neg'])) {
		$arr['pos-neg'] = '';
	}


	if (empty($arr['comments'])) {
		if ($arr['pos-neg'] == 'POS') {
			$arr['comments'] = $arr['te_comments'];
		} else if ($arr['pos-neg'] == 'NEG') {
			$arr['comments'] = $arr['te_comments_neg'];
		}
	}
	if ($arr['pos-neg'] == 'POS') {
		$arr['notes'] = $arr['te_pdf_note_pos'];
	} else if ($arr['pos-neg'] == 'NEG') {
		$arr['notes'] = $arr['te_pdf_note_neg'];
	}

	//fix comment & notes
	if (!empty($arr['comments']) && stripos($arr['comments'], '[onc') !== false) {
		$arr['comments'] = str_replace('[onc]', $arr['conc'], $arr['comments']);
	}

	if (!empty($arr['te_reportable_note']) && stripos($arr['te_reportable_note'], '[onc') !== false) {
		$readablenote = str_replace('[onc]', $arr['conc'], $arr['te_reportable_note']);
		global $current_conc_match;
		$current_conc_match = $arr['conc'];
		$readablenote = preg_replace_callback("/\[onc[^\]]+value=\"([^\"]+)\"\]([^\[]+)\[\/onc\]/", function ($matches) {
			global $current_conc_match;
			$range = $matches[1];
			$text = '';
			if (tr_pdd_pass_condition($current_conc_match, $range)) {
				$text = $matches[2];
			}

			return $text;
		}, $readablenote);

		$arr['te_reportable_note'] = $readablenote;
	}


	$pos_neg_vls = array(
		'NEG' => 'Negative',
		'POS' => 'Positive',
	);

	if ($arr['te_type'] == 'req') {
		$arr['has_critical'] = false;
	}


	if (tr_get_option('ci_result_settings_blood', 'result_cancel_rejected')) {
		$pos_neg_vls['ICL'] = 'Inconclusive';
		$pos_neg_vls['CAN'] = 'Cancel';
		$pos_neg_vls['REJ'] = 'Rejected';
		$pos_neg_vls['INC'] = 'Incomplete';
	}

	if ($arr['te_is_detected'] == '1') {
		$pos_neg_vls['POS'] = 'High';
		$pos_neg_vls['NEG'] = 'Normal';
		if (!isset($arr['only_detected']) || !$arr['only_detected']) {
			$pos_neg_vls['POL'] = 'Low';
			if ($arr['te_type'] != 'req') {
				$pos_neg_vls['POM'] = 'Medium';
			}

			$pos_neg_vls['POH'] = 'High';
			unset($pos_neg_vls['POS']);
			if ($arr['pos-neg'] == 'POS') {
				$arr['pos-neg'] = 'POL';
			}
		}
	} else if ($arr['te_is_detected'] == '2') {
		$pos_neg_vls['POS'] = 'Detected';
		$pos_neg_vls['NEG'] = 'Not Detected';
	} else if ($arr['te_is_detected'] == '3') {
		$pos_neg_vls['POS'] = 'Reactive';
		$pos_neg_vls['NEG'] = 'Not Reactive';
	} else if ($arr['te_is_detected'] == '4') {
		$pos_neg_vls['NEG'] = 'Negative';
		$pos_neg_vls['POL'] = 'Equivocal';
		$pos_neg_vls['POS'] = 'Positive';

		if ($arr['pos-neg'] == 'POH') {
			$arr['pos-neg'] = 'POS';
		}
	} else if ($arr['te_is_detected'] == '5') {

		$pos_neg_vls['NEG'] = 'Normal';
		$pos_neg_vls['POS'] = 'AbNormal';
	} else if ($arr['te_is_detected'] == '6') {
		unset($pos_neg_vls['POS']);

		$pos_neg_vls['NEG'] = 'Normal';
		$pos_neg_vls['PCH'] = 'High';
		$pos_neg_vls['POH'] = 'At Risk';
	} else if ($arr['te_is_detected'] == '7' && empty($arr['te_result_values'])) {
		$arr['te_result_values'] = '1+,2+,3+';
	} else if ($arr['te_is_detected'] == '8' && empty($arr['te_result_values'])) {
		$arr['te_result_values'] = '1+,2+,3+';
	}


	if ($is_cancel) {
		$arr['pos-neg'] = 'CAN';
	}

	if (isset($arr['has_critical']) && $arr['has_critical']) {
		$pos_neg_vls['PCL'] = 'Critical Low';
		$pos_neg_vls['PCH'] = 'Critical High';
	}

	if (!empty($testdb->te_critical_low) && $arr['te_type'] != 'req') {
		$pos_neg_vls['PCL'] = 'Critical-Low';
	}

	if (!empty($testdb->te_critical_high) && $arr['te_type'] != 'req') {
		$pos_neg_vls['PCH'] = 'Critical-High';
	}

	$pos_neg_vls['INV'] = 'Invalid';
	$pos_neg_vls['PEN'] = 'Pending';
	$pos_neg_vls['CAN'] = 'Test Cancelled';
	$pos_neg_vls['IND'] = 'Indeterminate';


	if (!empty($arr['te_result_values'])) {
		$vls = explode(',', $arr['te_result_values']);

		$pos_neg_vls_new = array();
		foreach ($vls as $vt) {
			$vt = trim($vt);
			if (!empty($vt)) {
				$vt_k = trim(substr($vt, 0, 15));
				$pos_neg_vls_new[$vt_k] = $vt;
			}
		}
		if (count($pos_neg_vls_new) > 0) {
			//$pos_neg_vls = $pos_neg_vls_new;
			$pos_neg_vls = array();
			$arr['values'] = $pos_neg_vls_new;
			$arr['pos-neg'] = '';
		}
		$arr['flag'] = $arr['conc'];
		if ($arr['te_is_detected'] == '7') {
			$arr['flag'] = 'None Seen';
		} else if ($arr['te_is_detected'] == '8') {
			$arr['flag'] = '';
			$arr['pos-neg'] = $arr['old_pos-neg'];
			$pos_neg_vls = array(
				'TRA' => 'Trace',
				'LOW' => 'Low',
				'SMA' => 'Small',
				'MED' => 'Medium',
				'LAR' => 'Large',
				'NEG' => 'Negative',
			);
		}
	}

	$arr['pos_neg_list'] = $pos_neg_vls;
	$arr['flag'] = isset($pos_neg_vls[$arr['pos-neg']]) ? $pos_neg_vls[$arr['pos-neg']] : $arr['flag'];



	return $arr;
}


function trpdd_replace_calculation($matches)
{
	global $wpdb, $report_id, $all_res, $calculator_no_value, $current_test_calculation;
	global $current_cal_btype;

	$slug = strtoupper($matches[1]);
	$defaultzero = false;
	if (substr($slug, -2, 2) == '=0') {
		$slug = substr($slug, 0, strlen($slug) - 2);
		$defaultzero = true;
	}
	$test_group_id = $current_test_calculation['te_group_id'];

	if (isset($_GET['debuge'])) {
		echo ' - abrev: ' . $slug;
		echo '<br>';
	}
	$value_out_groups = array(1 => 0, 2 => 0, 3 => 0);

	foreach ($all_res as $testID => $arr) {

		if ($slug == strtoupper($arr['te_abrev'])) {
			if (isset($_GET['debuge'])) {
				echo '   found test:' . $arr['drug'] . ' value: ' . $arr['conc'];
				echo '<br>';
			}

			if ($test_group_id > 0 && $arr['te_group_id'] > 0 && $test_group_id != $arr['te_group_id']) {
				if (isset($_GET['debuge'])) {
					echo 'not in group ' . $arr['te_group_id'] . ' value: ' . $arr['conc'];
				}
				$value_out_groups[1] = $arr['conc'];
				continue;
			}

			if ($arr['conc'] === '' || is_null($arr['conc'])) {
				$calculator_no_value = true;
			}
			return floatval($arr['conc']);
			break;
		} else if (isset($arr['te_dimension_exl'])) {
			if (!empty($arr['instrument']) && $slug == strtoupper($arr['te_dimension_exl'])) {
				$value_out_groups[2] = $arr['conc'];
			}
			if ($slug == strtoupper($arr['te_dimension_exl'])) {
				$value_out_groups[3] = $arr['conc'];
			}
		}
	}

	//no values
	//check in db
	if (!empty($current_cal_btype)) {
		$result_tbl = tr_pdd_get_table_result_by_btype($current_cal_btype);
		$vl = $wpdb->get_var($wpdb->prepare("SELECT rs.conc 
			FROM {$result_tbl} rs
			JOIN ci_test_panels te ON te.te_name = rs.drug or te.te_abrev = rs.drug
			 WHERE te.te_abrev = %s and rs.report_id = %s", $slug, $report_id));
		if (isset($_GET['debuge'])) {
			echo 'slug: ' . $slug . '=> value: ' . $vl;
			echo '<br>';
		}
		if ($vl !== null) {
			return floatval($vl);
		}
	}

	foreach ($value_out_groups as $nu => $vl) {
		if ($vl !== 0) {
			if (isset($_GET['debuge'])) {
				echo 'slug: ' . $slug . '(' . $nu . ') => value: ' . $vl;
				echo '<br>';
			}
			return floatval($vl);
		}
	}
	$calculator_no_value = true;
	return 0;
}



function tr_pdd_pass_condition($value, $condition_value, $condition = '')
{

	$value = floatval($value);
	$pass = false;
	$condition_value = trim($condition_value);
	$value_no_condition  = str_replace(array('-', '>', '<', '='), '', $condition_value) === $condition_value;
	if ($value_no_condition && !empty($condition)) {
		$condition_value = $condition . $condition_value;
	}

	if (strpos($condition_value, '-') !== false) {
		list($low, $high) = explode('-', $condition_value);
		$low = trim($low);
		$high = trim($high);

		global $is_deebug_pass_condition;
		if ($is_deebug_pass_condition) {
			echo '<br>';
			var_dump($value);
			var_dump($low);
			var_dump($high);
			echo '<br>';
		}

		if (strpos($low, '>') !== false && strpos($low, '>=') === false) {
			$low = floatval(trim(str_replace(array('>', '='), '', $low)));
			$pass_low = $value > $low;
		} else {
			$low = floatval(trim(str_replace(array('>', '='), '', $low)));
			$pass_low = $value >= $low;
		}

		if (strpos($high, '<') !== false && strpos($low, '<=') === false) {
			$high = floatval(trim(str_replace(array('<', '='), '', $high)));
			$pass_high = $value < $high;
		} else {
			$pass_high = $value <= $high;
		}

		$pass = $pass_low && $pass_high;

		if ($is_deebug_pass_condition) {
			echo '<br>';
			var_dump($pass_low);
			var_dump($pass_high);
			var_dump($pass);
			echo '<br>';
		}
	} else {
		if (strpos($condition_value, '>=') !== false) {
			$limit = trim(str_replace(array('>=', ' '), '', $condition_value));
			$pass  = $value >= $limit;
		} else if (strpos($condition_value, '<=') !== false) {
			$limit = trim(str_replace(array('<=', ' '), '', $condition_value));
			$pass  = $value <= $limit;
		} else if (strpos($condition_value, '>') !== false) {
			$limit = trim(str_replace(array('>', ' '), '', $condition_value));
			$pass  = $value > $limit;
		} else if (strpos($condition_value, '<') !== false) {
			$limit = trim(str_replace(array('<', ' '), '', $condition_value));
			$pass  = $value < $limit;
		}
	}

	return $pass;
}

function tr_pdd_get_pass_age_by($value, $default_by = '', $compare = '')
{
	$value = trim(strtolower($value));
	$by = '';
	if (strpos($value, 'day') > 0) {
		$by = 'day';
	} else if (strpos($value, 'week') > 0) {
		$by = 'week';
	} else if (strpos($value, 'month') > 0) {
		$by = 'month';
	} else if (strpos($value, 'year') > 0) {
		$by = 'year';
	}

	if (!empty($by)) {
		$arr = explode($by, $value);
		if (!empty($arr[0])) {
			$value = $arr[0];
		}
	} else if (!empty($default_by)) {
		$by = $default_by;
	}

	if (strpos($value, '>=') !== false) {
		$compare = '>=';
	} else if (strpos($value, '<=') !== false) {
		$compare = '<=';
	} else if (strpos($value, '>') !== false) {
		$compare = '>';
	} else if (strpos($value, '<') !== false) {
		$compare = '<';
	}
	$value = str_replace($compare, '', $value);
	$value = intval(trim($value));

	return array('value' => $value, 'by' => $by, 'compare' => $compare);
}

function tr_pdd_pass_condition_ages($age_arr, $condition)
{
	$pass = false;
	$condition = trim($condition);

	if (strpos($condition, '-') > 0) {
		$arr = explode('-', $condition);
		$high = tr_pdd_get_pass_age_by($arr[1], 'year', '<=');
		$low = tr_pdd_get_pass_age_by($arr[0], $high['by'], '>=');

		$pass_low = $low['compare'] == '>=' ? $age_arr[$low['by']] >= $low['value'] : $age_arr[$low['by']] > $low['value'];
		$pass_high = $high['compare'] == '<=' ? $age_arr[$high['by']] <= $high['value'] : $age_arr[$high['by']] < $high['value'];

		$pass = $pass_low && $pass_high;

		if (isset($_GET['debug2'])) {
			var_dump($age_arr);
			echo '<br>';
			var_dump($condition);
			echo '<br>';
			var_dump($low);
			echo '<br>';
			var_dump($high);
			echo '<br>';
			var_dump($pass);
			echo '<br><br><br>';
		}
	} else {
		$arr = tr_pdd_get_pass_age_by($condition, 'year');
		if (isset($age_arr[$arr['by']])) {
			if ($arr['compare'] == '>=') {
				$pass = $age_arr[$arr['by']] >= $arr['value'];
			} else if ($arr['compare'] == '<=') {
				$pass = $age_arr[$arr['by']] <= $arr['value'];
			} else if ($arr['compare'] == '<') {
				$pass = $age_arr[$arr['by']] < $arr['value'];
			} else if ($arr['compare'] == '>') {
				$pass = $age_arr[$arr['by']] > $arr['value'];
			}
		}


		if (isset($_GET['debug2'])) {
			var_dump($age_arr);
			echo '<br>';
			var_dump($condition);
			echo '<br>';
			var_dump($arr);
			echo '<br>';
			var_dump($pass);
			echo '<br><br><br>';
		}
	}

	return $pass;
}

function tr_pdd_check_rerun($btype, $row)
{
	global $wpdb;
	global $rereun_by_both_rna_fail;
	$rereun_by_both_rna_fail = false;
	if (is_array($row)) {
		$row = (object)$row;
	}


	$is_rerun = false; //must = false

	if ($btype == 'pcr') {

		if (!isset($row->norerun_role)) {
			$row->norerun_role = $wpdb->get_var("select norerun_role from ci_reports_pcr where id = {$row->id} ");
		}
		if ($row->norerun_role) {
			return false;
		}

		if (!isset($row->type_of_specimen)) {
			$batch_tbl = 'ci_batches_pcr';
			$row->type_of_specimen = $wpdb->get_var("select type_of_specimen from {$batch_tbl} where mid = {$row->mid}");
		}
		$types = explode(',', strtolower($row->type_of_specimen));
		$has_urine = stripos($row->type_of_specimen, 'urine') !== false;

		if (isset($_GET['debugrerun'])) {
			echo '<br>check rerun: hasurine: ' . ($has_urine ? 1 : 0) . '<br>';
			echo '<br>count groups: ' . count($row->groups) . '<br>';
		}

		foreach ($row->groups as $group) {

			if (is_array($group)) {
				$group = (object) $group;
			}

			$eec_pos  = false;
			$cond_or_has_neg = null;

			$rerun_must_all_neg_ids = isset($group->other_options['rerun_test_id']) && !empty($group->other_options['rerun_test_id']) ? explode(',', $group->other_options['rerun_test_id']) : array();
			$rerun_ors = isset($group->other_options['rerun_test_id_or']) && !empty($group->other_options['rerun_test_id_or']) ? explode(',', $group->other_options['rerun_test_id_or']) : array();

			if (!isset($group->other_options['rnasep_notrerun_urine'])) {
				$group->other_options['rnasep_notrerun_urine'] = 0;
				if (!empty($rerun_must_all_neg_ids) || !empty($rerun_ors)) {
					$group->other_options['rnasep_notrerun_urine'] = 1;
				}
			}

			$RNASep_notrerun_urine = $group->other_options['rnasep_notrerun_urine'];

			//Dont rerun when any test Detected
			$dontrerun_anydetected = isset($group->other_options['not_rerun_or']) ? $group->other_options['not_rerun_or'] : false;

			if (isset($_GET['debugrerun'])) {
				echo '<br>';
				var_dump($group->name);
				echo '<br> Dont rerun when any test Detected: ';
				var_dump($dontrerun_anydetected);
				echo '<br>';
			}

			if (!empty($rerun_must_all_neg_ids) || !empty($rerun_ors)) {
				$rerun_testarrs = array_merge($rerun_must_all_neg_ids, $rerun_ors);
				$rerun_test_str = implode(',', $rerun_testarrs);
				$rerun_specimen = isset($group->other_options['rerun_specimen']) ? strtolower($group->other_options['rerun_specimen']) : '';

				if (!empty($rerun_specimen)) {
					$rerun_specimen_arr = explode(',', $rerun_specimen);
					$found_specimen = false;
					foreach ($rerun_specimen_arr as $tsp) {
						if (in_array($tsp, $types)) {
							$found_specimen = true;
							break;
						}
					}

					if (!$found_specimen) {
						continue;
					}
				}

				$arrs = $wpdb->get_results("select * from ci_results_pcr rs 
				join ci_test_panels t On t.te_name = rs.drug or t.te_abrev = rs.drug or t.te_abrev2 = rs.drug
				where t.te_ID in ({$rerun_test_str})
				and rs.report_id = {$row->id}
				group by te_ID ", ARRAY_A);

				if (isset($_GET['debugrerun']) && count($arrs) == 0) {
					echo '<br>';
					echo $wpdb->last_error;
					echo '<br>';
					echo $wpdb->last_query;
					echo '<br>';
					echo 'No rerun test found';
					echo '<br>';
				}

				$log_main_checked = '';
				$log_second_checked = '';


				foreach ($arrs as $arr) {

					$group->tg_ID = isset($group->tg_ID) ? $group->tg_ID : $group->id;
					$arr = tr_pdd_parse_pcr_test($arr, $row->instrument, $group->tg_ID, $row->created_date);

					if (empty($arr['pos-neg'])) {
						continue;
					}

					if (in_array($arr['te_ID'], $rerun_ors)) {

						if ($arr['pos-neg'] === 'NEG') {

							if ($has_urine && $RNASep_notrerun_urine && (stripos($arr['te_abrev'], 'RNAseP') !== false || stripos($arr['te_name'], 'RNAseP') !== false)) {
								//nothing
								if (isset($_GET['debugrerun'])) {
									echo '<br>Not rerun for RNAseP + Urine<br>';
								}
							} else {
								$cond_or_has_neg = true;
								if (count($rerun_ors) >= 2) {
									$rereun_by_both_rna_fail = true;
								}
							}
						} else {
							$rereun_by_both_rna_fail = false;
						}

						if (isset($_GET['debugrerun'])) {
							$log_second_checked .= $arr['te_name'] . '=';
							$log_second_checked .= var_export($arr['pos-neg'], true);
							$log_second_checked .= '<br>';
						}
					} else if (in_array($arr['te_ID'], $rerun_must_all_neg_ids)) {
						//main rerun test
						if ($arr['te_abrev'] == 'EEC' && in_array($arr['pos-neg'], array('POS', 'POH', 'POL', 'PVL', 'PVH'))) {
							$eec_pos = true;
						}

						if (!empty($arr['pos-neg']) && $arr['pos-neg'] == 'NEG') { //&& !empty($arr['conc'])
							if ($has_urine && $RNASep_notrerun_urine && (stripos($arr['te_abrev'], 'RNAseP') !== false || stripos($arr['te_name'], 'RNAseP') !== false)) {
								//nothing
								if (isset($_GET['debugrerun'])) {
									echo '<br>Not rerun for RNAseP + Urine<br>';
								}
							} else {
								if (isset($_GET['debugrerun'])) {
								}
								if ($is_rerun === false) {
									$is_rerun = 1;
								}
							}
						} else {
							$is_rerun = 0;
						}

						if (isset($_GET['debugrerun'])) {
							$log_main_checked .= $arr['te_name'] . '=';
							$log_main_checked .= var_export($arr['pos-neg'], true);
							$log_main_checked .= '<br>';
						}
					}
				}

				if (isset($_GET['debugrerun'])) {
					if (count($rerun_must_all_neg_ids) > 0) {
						echo '<br>Grouped Controls (rerun on all fail): ' . ($is_rerun ? 'yes' : 'no') . '<br>';
						echo $log_main_checked;
						echo '<br>';
					}

					if (count($rerun_ors) > 0) {
						echo 'Independent Controls (any or all): ' . ($cond_or_has_neg ? 'yes' : 'no') . '<br>';
						echo $log_second_checked;
						echo '<br>';
					}
				}



				//rerun when any control NEG (#2)
				if (!$is_rerun && count($rerun_ors) > 0 && $cond_or_has_neg === true) {
					$is_rerun = 1;
				} else {
					$rereun_by_both_rna_fail = false;
				}

				if ($has_urine && $eec_pos && $is_rerun !== false) {
					$is_rerun = 0;
				}

				if ($is_rerun) {

					//check dont rerun when any test detected
					if ($dontrerun_anydetected) {
						//Dont rerun when any test Detected
						$tids = array();
						foreach (explode(',', $row->panel_tests_requested) as $tid) {
							if (stripos($tid, 'group_') === false) {
								$tids[] = $tid;
							}
						}
						$test_ids_str = implode(',', $tids);
						$hasotherdt = $wpdb->get_var("select count(*) from ci_results_pcr rs 
						join ci_test_panels t On t.te_name = rs.drug or t.te_abrev = rs.drug or t.te_abrev2 = rs.drug
						join ci_test_groups g ON g.tg_ID = t.te_group_id 
						where t.te_ID in ({$test_ids_str})
						and rs.report_id = {$row->id}
						and rs.`pos-neg` in ('POS','POL','POH','POM','PVL','PVH')
						 ");

						if ($hasotherdt) {
							$is_rerun = 0;
						}
					}

					break; //break by group
				}
			}
		}
	}
	return $is_rerun;
}

function tr_pdd_check_group_reflex($bat, $btype)
{
	global $wpdb, $tr_cache_group_reflexs;
	global $tr_reflex_hide_groups;
	global $report;

	$tr_reflex_hide_groups = array();
	$adding = array(
		'add_tests' => array(),
		'remove_tests' => array(),
	);
	$report_tbl = tr_pdd_get_table_report_by_btype($btype);
	$result_tbl = tr_pdd_get_table_result_by_btype($btype);

	if (!is_array($tr_cache_group_reflexs)) {

		$tr_cache_group_reflexs = array();

		$rs = $wpdb->get_results("select 
        tg.tg_ID,
        tg.tg_parent,
        tg.tg_reflex_group_ID,
        ref.tg_only_group as ref_only_group,
        ref.tg_notestcheckbox as ref_nocheck 
		from ci_test_groups tg
        JOIN ci_test_groups ref ON FIND_IN_SET(ref.tg_ID, tg.tg_reflex_group_ID) 
        where ref.tg_ID > 0 ");

		foreach ($rs as $r) {

			$reflex_tests = array();

			$tests = $wpdb->get_col("select te_ID 
                from ci_test_panels 
                where te_group_ID = {$r->tg_ID} ");

			$listreflexids = explode(',', $r->tg_reflex_group_ID);

			foreach ($listreflexids as $reflexid) {
				if ($reflexid > 0) {
					if (!$r->ref_only_group && !$r->ref_nocheck) {
						$reflex_tests = $wpdb->get_col("select te_ID from ci_test_panels 
                    	where te_group_ID = {$reflexid} ");
					}

					$tr_cache_group_reflexs[$r->tg_ID] = array(
						'parent' 		=> $r->tg_parent,
						'tests'			=> $tests,
						'reflex_group' 	=> $reflexid,
						'reflex_tests' 	=> $reflex_tests
					);
				}
			}
		}
	}

	$assen 	= $bat['assen'];
	$panels = is_array($bat['panel_tests_requested']) ? $bat['panel_tests_requested'] : explode(',', $bat['panel_tests_requested']);
	if (empty($assen) || empty($panels)) {
		return $adding;
	}

	$report_id = $bat['id'];
	if (!$report_id) {
		$report_id = $wpdb->get_var("select id from {$report_tbl} where patient_id = '{$assen}' ");
	}

	if (!$report_id) {
		return $adding;
	}

	$test_ids 	= array();
	$group_ids 	= array();
	foreach ($panels as $pn) {
		if (stripos($pn, 'GROUP_') === false) {
			if ($pn > 0 && !in_array($pn, $test_ids)) {
				$test_ids[] = $pn;
			}
		} else {
			$group_id = str_ireplace('GROUP_', '', $pn);

			if (!in_array($group_id, $group_ids)) {
				$group_ids[] = $group_id;
			}
		}
	}

	//check reflex by test
	if ($btype == 'pcr') {
		if (tr_get_option('ci_pdf_data', 'enable_reflex') || tr_get_option('ci_result_settings_' . $btype, 'enable_ref_postest')) {
			$tests_ids_str = implode(',', $test_ids);

			//check & fix pos-neg = null
			$sql_null = "select * from {$result_tbl} rs 
			join ci_test_panels te ON (te.te_abrev = rs.drug or te.te_abrev2 = rs.drug or te.te_name = rs.drug)
			where rs.report_id = {$report_id}
			and te.te_type = '{$btype}'
			and te.te_ID in ({$tests_ids_str})
			and te_reflex_tests !='' and te_reflex_tests is not null
			and (rs.`pos-neg` = '' or rs.`pos-neg` IS NULL) ";
			$testref_nulls = $wpdb->get_results($sql_null, ARRAY_A);
			if (isset($_GET['debug_reflexnull'])) {
				echo 'SQL NULL: ' . $sql_null . '<br><br>';
				echo 'Found NULL results: ' . count($testref_nulls) . '<br><br>';
			}
			foreach ($testref_nulls as $arrnull) {
				tr_pdd_parse_pcr_test($arrnull, $report['instrument'], 0, $report['created_date']);
			}

			$sql_pos = "select * from {$result_tbl} rs 
			join ci_test_panels te ON (te.te_abrev = rs.drug or te.te_abrev2 = rs.drug or te.te_name = rs.drug)
			where rs.report_id = {$report_id}
			and te.te_type = '{$btype}'
			and te.te_ID in ({$tests_ids_str})
			and te_reflex_tests !='' and te_reflex_tests is not null ";
			$testspos = $wpdb->get_results($sql_pos, ARRAY_A);
			foreach ($testspos as $tarr) {
				if (!empty($tarr['te_reflex_tests'])) {
					$refids = explode(',', $tarr['te_reflex_tests']);
					foreach ($refids as $tid) {
						if ($tid > 0) {
							if (in_array($tarr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PVL', 'PVM', 'PVH'))) {
								$adding['add_tests'][] = $tid;
							} else {
								$adding['remove_tests'][] = $tid;
							}
						}
					}
				}
			}

			if (isset($_GET['debug_reflex2'])) {
				echo 'SQL POS: ' . $sql_pos . '<br><br>';
				var_dump($testspos);
				echo '<br><br>';
			}
		}
	}

	if (tr_get_option('ci_pdf_data', 'enable_tereflexgr')) {
		//check reflex by tests
		$group_ids_str = implode(',', $group_ids);
		$tests_ids_str = implode(',', $test_ids);

		//check & fix pos-neg = null
		$sql_null = "select rs.*, te.*
		from {$result_tbl} rs 
		join ci_test_panels te ON (te.te_abrev = rs.drug or te.te_name = rs.drug)
		JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_ID
		JOIN ci_test_meta tm ON te.te_ID = tm.test_id and tm.meta_key = 'reflex_groups'
		where rs.report_id = {$report_id}
		and te.te_type = '{$btype}'
		and (
			te.te_ID in ('{$tests_ids_str}')  
			or (
				te.te_group_ID in ({$group_ids_str}) 
				and (tg.tg_only_group = 1 or tg.tg_notestcheckbox = 1)
				)
			)
		and (rs.`pos-neg` ='' or rs.`pos-neg` IS NULL)
		GROUP by te.te_ID ";
		$testref_nulls = $wpdb->get_results($sql_null, ARRAY_A);
		if (isset($_GET['debug_reflexnull'])) {
			echo 'SQL NULL: ' . $sql_null . '<br><br>';
			echo 'Found NULL results: ' . count($testref_nulls) . '<br><br>';
		}
		foreach ($testref_nulls as $arrnull) {
			if ($btype == 'pcr') {

				tr_pdd_parse_pcr_test($arrnull, $report['instrument'], 0, $report['created_date']);
			}
		}


		$sql_pos = "select tm.meta_value, 
			count(*) as c
		from {$result_tbl} rs 
		join ci_test_panels te ON (te.te_abrev = rs.drug or te.te_name = rs.drug)
		JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_ID
		JOIN ci_test_meta tm ON te.te_ID = tm.test_id and tm.meta_key = 'reflex_groups'
		where rs.report_id = {$report_id}
		and te.te_type = '{$btype}'
		and (
			te.te_ID in ('{$tests_ids_str}')  
			or (
				te.te_group_ID in ({$group_ids_str}) 
				and (tg.tg_only_group = 1 or tg.tg_notestcheckbox = 1)
				)
			)
		and rs.`pos-neg` in ('POS','POL','POH','PVH','PVL')
		GROUP by te.te_ID ";
		$testrefpos = $wpdb->get_results($sql_pos);

		$adding_groups = array();
		$adding_testids = array();

		foreach ($testrefpos as $testrefp) {
			if ($testrefp->c > 0) {
				foreach (explode(',', $testrefp->meta_value) as $gid) {
					$gnamek = 'GROUP_' . $gid;
					if ($bat['orderable_test'] == '-3') {
						$adding_groups[] = $gid;
					}
					if ($gid > 0 && !in_array($gnamek, $adding) && !in_array($gid, $group_ids)) {
						$adding['add_tests'][] = $gnamek;
						//adding tests
						$grfids = $wpdb->get_col("select tg_ID from ci_test_groups
						where (tg_ID = {$gid} or (tg_parent = {$gid} and tg_show_req = 0) ) 
						and tg_only_group !=1 
						and tg_notestcheckbox!=1");
						if (count($grfids) > 0) {
							$groupaddingstr = implode(',', $grfids);
							$ttests = $wpdb->get_col("select te_ID from ci_test_panels
							where te_group_id in ({$groupaddingstr})");
							foreach ($ttests as $ttid) {
								$adding['add_tests'][] = $ttid;
								if ($bat['orderable_test'] == '-3') {
									$adding_testids[] = $ttid;
								}
							}
						}
					}
				}
			}
		}

		if (count($adding_groups) > 0) {
			foreach ($adding_groups as $gid) {
				$group_ids[] = $gid;
			}
		}
		if (count($adding_testids) > 0) {
			foreach ($adding_testids as $tid) {
				$test_ids[] = $tid;
			}
		}
	}


	if (count($tr_cache_group_reflexs) > 0) {

		foreach ($tr_cache_group_reflexs as $group_id => $rarr) {
			$has_group = in_array($group_id, $group_ids);
			if (!$has_group && $rarr['parent'] > 0 && in_array($rarr['parent'], $group_ids)) {
				foreach ($rarr['tests'] as $tid) {
					if (in_array($tid, $test_ids)) {
						$has_group = true;
						break;
					}
				}
			}
			$tests_ids_str = implode("','", $rarr['tests']);

			if (isset($_GET['debug_reflex'])) {
				var_dump($rarr['tests']);
				echo 'groupid: ' . $group_id . '<br>';
				echo '$tests_ids_str:';
				var_dump($tests_ids_str);
				echo '<br><br>';
				echo '$has_group:';
				var_dump($has_group);
				echo '<br><br>';
			}

			if ($has_group) {

				//check & fix pos-neg = null
				$sql_null = "select * from {$result_tbl} rs 
                join ci_test_panels te ON (te.te_abrev = rs.drug or te.te_abrev2 = rs.drug or te.te_name = rs.drug)
                where rs.report_id = {$report_id}
                and te.te_type = '{$btype}'
                and te.te_ID in ('{$tests_ids_str}')
                and te.te_group_ID = {$group_id}
                and (rs.`pos-neg` ='' or rs.`pos-neg` IS NULL) ";
				$testref_nulls = $wpdb->get_results($sql_null, ARRAY_A);
				if (isset($_GET['debug_reflexnull'])) {
					echo 'SQL NULL: ' . $sql_null . '<br><br>';
					echo 'Found NULL results: ' . count($testref_nulls) . '<br><br>';
				}
				if (empty($report) || ($report_id > 0 && $report_id != $report['id'])) {
					if (isset($_GET['dbtime'])) {
						global $db_time_log;
						$db_time_log .= 'reportid:' . $report_id . ' | reportid in $report' . $report['id'] . '<br>';
					}
					$report = tr_pdd_get_report_by_id($report_id, $btype);
				}
				foreach ($testref_nulls as $arrnull) {
					if ($btype == 'pcr') {
						tr_pdd_parse_pcr_test($arrnull, $report['instrument'], $arrnull['te_group_id'], $report['created_date']);
					}
				}

				$sql_pos = "select count(*) from {$result_tbl} rs 
                join ci_test_panels te ON (te.te_abrev = rs.drug or te.te_abrev2 = rs.drug or te.te_name = rs.drug)
                where rs.report_id = {$report_id}
                and te.te_type = '{$btype}'
                and te.te_ID in ('{$tests_ids_str}')
                and te.te_group_ID = {$group_id}
                and rs.`pos-neg` in ('POS','POL','POH','POM','PVL','PVM','PVH') ";
				$has_pos = $wpdb->get_var($sql_pos);

				if (isset($_GET['debug_reflex'])) {
					echo '$sql_pos:';
					var_dump($sql_pos);
					echo '<br><br>';
					echo '$has_pos:';
					var_dump($has_pos);
					echo '<br><br>';
				}
				if ($has_pos) {

					$adding['add_tests'][] = 'GROUP_' . $rarr['reflex_group'];
					foreach ($rarr['reflex_tests'] as $tid) {
						$adding['add_tests'][] = $tid;
					}

					$tr_reflex_hide_groups[$rarr['reflex_group']] = false;
				} else if (!isset($tr_reflex_hide_groups[$rarr['reflex_group']])) {
					$tr_reflex_hide_groups[$rarr['reflex_group']] = true;
				}
			}
		}
	}

	//make sure $adding['remove_tests'] not in $adding['add_tests']
	if (count($adding['remove_tests']) > 0) {
		foreach ($adding['remove_tests'] as $k => $tid) {
			if (in_array($tid, $adding['add_tests'])) {
				unset($adding['remove_tests'][$k]);
			}
		}
	}

	if (isset($_GET['debug_reflex'])) {
		echo 'Final adding: <br>';
		var_dump($adding);
		echo '<br><br>';
	}


	return $adding;
}


function tr_pdd_parse_gene_test($arr, $instrument, $group_id = 0, $report = array())
{
	global $wpdb, $all_res;
	static $all_tests_gene;
	static $all_group_gene;

	global $current_cal_btype;
	$current_cal_btype = 'gene';



	if (!isset($arr['critical'])) {
		$arr['critical'] = '';
	}
	if ($arr['pos-neg'] === 'CAN') {
		return $arr;
	}

	$datereport = isset($report['doc']) ? $report['doc'] : (isset($report['created_date']) ? $report['created_date'] : '');
	if (empty($datereport)) {
		$datereport = date_i18n('Y-m-d');
	}

	$gender = isset($report['pa_gender']) && !empty($report['pa_gender']) ? $report['pa_gender'] : (isset($report['gender']) ? $report['gender'] : '');

	if (isset($_GET['test_gender']) && !empty($_GET['test_gender'])) {
		$gender = substr($_GET['test_gender'], 0, 1);
	}
	$gender = strtoupper($gender);
	$fasting = isset($report['fasting']) && $report['fasting'] ? 'fasting' : 'nonfasting';

	$dob = isset($report['pa_dob']) && !empty($report['pa_dob']) ? $report['pa_dob'] : (isset($report['dob']) ? $report['dob'] : '');

	if (isset($_GET['test_dob']) && !empty($_GET['test_dob'])) {
		$dob = date('Y-m-d', strtotime($_GET['test_dob']));
	}

	if (!empty($arr['instrument'])) {
		$instrument = $arr['instrument'];
	}

	$instrument = trim(strtoupper($instrument));
	if (empty($instrument)) {
		$instrument = '*';
	}
	$current_group = null;

	if (empty($all_group_gene)) {
		$rs = $wpdb->get_results("select * from ci_test_groups where tg_type = 'gene'");
		foreach ($rs as $r) {
			$r->tg_name = strtoupper($r->tg_name);
			$all_group_gene[$r->tg_ID] = $r;
			$all_group_gene[$r->tg_name] = $r->tg_ID;
		}
	}


	//get group_id from group name
	if (!empty($group_id) && is_string($group_id) && $group_id == 0) {
		$group_name = strtoupper($group_id);
		if (isset($all_group_gene[$group_name]) && !is_object($all_group_gene[$group_name])) {
			$group_id = $all_group_gene[$group_name];
		}
	}

	if ($group_id > 0 && isset($all_group_gene[$group_id])) {
		$current_group = $all_group_gene[$group_id];
	}

	if (isset($arr['parsed_test' . $group_id]) && $arr['parsed_test' . $group_id]) {
		return $arr;
	}

	if (empty($all_tests_gene[$datereport])) {
		$all_tests_gene[$datereport] = array();
		$rs = $wpdb->get_results("select * from ci_test_panels where te_type='gene' ");
		foreach ($rs as $r) {
			$r->te_name = strtoupper($r->te_name);
			$sql_cutoff = "select * from ci_test_panels_cutoffs where cu_te_ID = {$r->te_ID} 
			                and (cu_start is null or cu_start ='' or cu_start <= '{$datereport}' ) 
			                and (cu_end is null or cu_end = '' or cu_end >= '{$datereport}')";

			$cutoffs = $wpdb->get_results($sql_cutoff);

			foreach ($cutoffs as $cut) {
				$cut->cu_ms = trim($cut->cu_ms);

				if (empty($cut->cu_ms)) {
					$cut->cu_ms = '*';
				}

				$mcs = explode(',', $cut->cu_ms);
				foreach ($mcs as $mc) {
					if (empty($mc)) {
						continue;
					}

					$mc = trim(strtoupper($mc));

					$newr = clone $r;

					$newr->te_critical_low = $cut->cu_critical_low;
					$newr->te_critical_high = $cut->cu_critical_high;
					$newr->te_cutoff = $cut->cu_cutoff;
					$newr->te_upper_limit = $cut->cu_upper_limit;
					$newr->te_medium = $cut->cu_medium;
					$newr->te_invalid = $cut->cu_invalid;
					$newr->fasting = '';

					if (!isset($newr->cu_medium)) {
						$newr->cu_medium = '';
					}
					if (stripos($newr->cu_medium, 'non-fasting') !== false) {
						$newr->fasting = 'nonfasting';
						$newr->cu_medium = '';
					} else if (stripos($newr->cu_medium, 'fasting') !== false) {
						$newr->fasting = 'fasting';
						$newr->cu_medium = '';
					}

					if (!empty($cut->cu_gender)) {
						$mc .= '_' . strtoupper($cut->cu_gender);
					}
					if (!empty($newr->fasting)) {
						$mc .= '_' . $newr->fasting;
					}

					$newr->mc = $mc;


					if (!empty($cut->cu_age)) {
						if (
							!isset($all_tests_gene[$datereport][$newr->te_name . '_' . $mc])
							|| !is_array($all_tests_gene[$datereport][$newr->te_name . '_' . $mc])
						) {
							$all_tests_gene[$datereport][$newr->te_name . '_' . $mc] = array();
						}
						if (
							!isset($all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc])
							|| !is_array($all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc])
						) {
							$all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc] = array();
						}
						if (
							!isset($all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id])
							|| !is_array($all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id])
						) {
							$all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id] = array();
						}
						$all_tests_gene[$datereport][$newr->te_name . '_' . $mc][$cut->cu_age] = $newr;
						$all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc][$cut->cu_age] = $newr;
						$all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id][$cut->cu_age] = $newr;
					} else {
						$all_tests_gene[$datereport][$newr->te_name . '_' . $mc] = $newr;
						$all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id . '_' . $mc] = $newr;
						$all_tests_gene[$datereport][$newr->te_name . '_' . $newr->te_group_id] = $newr;
					}
				}
			}
		}
	}

	$arr['fasting'] = $fasting;
	$arr['drug'] = strtoupper($arr['drug']);
	$check_keys = array();
	if ($group_id > 0) {
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $instrument . '_' . $gender . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $instrument . '_' . $gender;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $instrument . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $instrument;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_*_' . $gender . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_*_' . $gender;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_*' . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_*';
		$check_keys[] = $arr['drug'] . '_' . $group_id . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $group_id;
	} else {
		$check_keys[] = $arr['drug'] . '_' . $instrument . '_' . $gender . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_' . $instrument . '_' . $gender;
		$check_keys[] = $arr['drug'] . '_*_' . $gender . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_*_' . $gender;
		$check_keys[] = $arr['drug'] . '_*' . '_' . $fasting;
		$check_keys[] = $arr['drug'] . '_*';
	}


	$arr['keypair'] = '';
	foreach ($check_keys as $check_key) {
		if (isset($all_tests_gene[$datereport][$check_key])) {
			$arr['keypair'] = $check_key;
			break;
		}
	}

	$testdb = isset($all_tests_gene[$datereport][$arr['keypair']]) ? $all_tests_gene[$datereport][$arr['keypair']] : '';

	//check age
	$age_arr = array();
	if (is_array($testdb)) {
		$tmparray = $testdb;
		$testdb = false;
		$age_arr = trdd_dob2ages($dob, $datereport, true);
		$arr['dob'] = $dob;
		$arr['ages'] = $age_arr;

		foreach ($tmparray as $age_range => $testdbtmp) {
			if (tr_pdd_pass_condition_ages($age_arr, $age_range)) {
				$testdb = $testdbtmp;
				$arr['age_range'] = $age_range;
				break;
			}
		}
	}

	//calculation value
	$arr['has_calculation'] = false;
	if (isset($arr['te_calculation']) && strlen($arr['te_calculation']) > 3) {
		try {
			global $report_id, $calculator_no_value, $current_test_calculation;
			$report_id = $report['id'] > 0 ? $report['id'] : $arr['report_id'];
			$calculator_no_value = false;

			$current_test_calculation = $arr;
			$current_test_calculation['cal_testids'] = array();

			$replace_female = '';
			$replace_male = '';
			$replace_gender = '';
			$replace_gender_female = '';
			$replace_gender_male = '';
			if ($gender == 'F') {
				$replace_female = ' $1 ';
				$replace_gender = ' $1 ';
				$replace_gender_female = ' $1 ';
			} else {
				$replace_male = ' $1 ';
				$replace_gender = ' $2 ';
				$replace_gender_male = ' $2 ';
			}
			if (!isset($age_arr['year'])) {
				$age_arr = trdd_dob2ages($dob, $datereport, true);
			}
			$age = $age_arr['year'];

			if (isset($_GET['debuge'])) {
				echo '<b>' . $arr['drug'] . '</b> <br> ';
				echo ($arr['te_calculation']);
				echo '<br>';
			}
			$arr['raw_calculation'] = $arr['te_calculation'];
			$arr['te_calculation'] = preg_replace('/\[female\]([^\[]+)\[\/female\]/', $replace_female, $arr['te_calculation']);
			$arr['te_calculation'] = preg_replace('/\[male\]([^\[]+)\[\/male\]/', $replace_male, $arr['te_calculation']);
			$arr['te_calculation'] = str_replace('[age]', $age, $arr['te_calculation']);
			$arr['te_calculation'] = str_replace(' x ', ' * ', $arr['te_calculation']);
			$arr['te_calculation'] = str_replace(' : ', ' / ', $arr['te_calculation']);
			$arr['te_calculation'] = preg_replace_callback('/\[([A-Za-z0-9\-\s\=]+)\]/', 'trpdd_replace_calculation', $arr['te_calculation']);

			$arr['te_calculation'] = preg_replace('/\[gender\s+female="([^\"]+)"\s+male="([^\"]+)"\s*\]/', $replace_gender, $arr['te_calculation']);
			$arr['te_calculation'] = preg_replace('/\[gender\s+female="([^\"]+)"\s*\]/', $replace_gender_female, $arr['te_calculation']);
			$arr['te_calculation'] = preg_replace('/\[gender\s+male="([^\"]+)"\s*\]/', $replace_gender_male, $arr['te_calculation']);


			if ($calculator_no_value) {
				$arr['pos-neg'] = '';
				$arr['conc'] = '';
				$arr['cal_missing'] = true;
			} else {
				$conc = '';
				if (!empty($arr['te_calculation'])) {
					if (isset($_GET['debuge'])) {
						echo ($arr['te_calculation']);
					}

					$eval_str = '$conc = ' . $arr['te_calculation'] . ';';

					try {
						eval($eval_str);
					} catch (Error $e) {
					}
				}

				if (isset($_GET['debuge'])) {
					echo ' = ' . $conc;
					echo '<br><br>';
				}

				if (is_nan($conc) || is_infinite($conc)) {
					$conc = '';
				} else {
					$arr['conc'] = number_format($conc, $arr['te_conc_decimal'] > 1 ? $arr['te_conc_decimal'] : 1);
					$arr['has_calculation'] = true;

					//check default =0;
					if (stripos($arr['raw_calculation'], '=0') > 0) {
						if (isset($_GET['debuge'])) {
							echo 'has =0<br>';
							var_dump($current_test_calculation['cal_testids']);
							echo '<br>';
						}
						foreach ($current_test_calculation['cal_testids'] as $tidc) {
							if ($conc == 0) {
								if (isset($all_res[$tidc]) && is_null($all_res[$tidc]['conc'])) {
									$all_res[$tidc]['conc'] = 0;
								}
							} else {
								if (isset($_GET['debuge'])) {
									var_dump(isset($all_res[$tidc]));
								}
								if (isset($all_res[$tidc]) && $all_res[$tidc]['conc'] == 0) {
									$all_res[$tidc]['conc'] = 'unknown';
									if (isset($_GET['debuge'])) {
										echo $tidc . ' =0<br>';
									}
								}
							}
						}
					}
				}
			}
		} catch (Exception $e) {
			var_dump($e);
		}
	}

	if (!isset($arr['conc'])) {
		$arr['conc'] = '';
	}
	$arr['conc'] = str_replace(',', '', $arr['conc']);


	if (!isset($arr['conc_dis'])) {
		$arr['conc_dis'] = $arr['conc'];

		if (strpos($arr['conc_dis'], '.') > 0 && ($arr['te_conc_decimal'] > 0 || $arr['te_conc_decimal'] === '0')) {
			$has_percent = strpos($arr['conc_dis'], '%') > 0;
			$comparechar = '';
			if (strpos($arr['conc_dis'], '>') === 0) {
				$comparechar = '>';
			} else if (strpos($arr['conc_dis'], '<') === 0) {
				$comparechar = '<';
			}
			$arr['conc_dis'] = str_replace(array('>', '<', '=', '%'), '', $arr['conc_dis']);
			$arr['conc_dis'] = number_format($arr['conc_dis'], $arr['te_conc_decimal']);
			$arr['conc_dis'] = $comparechar . $arr['conc_dis'];
			if ($has_percent) {
				$arr['conc_dis'] .= '%';
			}
		}

		if (isset($arr['te_comparison_symbol']) && strlen($arr['te_comparison_symbol']) > 2 && strlen($arr['conc']) > 0) {
			if (strpos($arr['conc_dis'], '>') === false && strpos($arr['conc_dis'], '<') === false) {
				$tsymbol = '';
				if (stripos($arr['te_comparison_symbol'], '<') !== false) {
					$tsymbol = '<';
				} else if (stripos($arr['te_comparison_symbol'], '>') !== false) {
					$tsymbol = '>';
				}

				if (!empty($tsymbol) && strpos($arr['conc'], '<') === false && strpos($arr['conc'], '>') === false) {
					$symnum = str_replace(array('>', '<', '%'), '', $arr['te_comparison_symbol']);
					$concnum = str_replace('%', '', $arr['conc']);
					if ($tsymbol == '<' && $concnum < $symnum) {
						$arr['conc'] = $arr['conc_dis'] = $arr['te_comparison_symbol'];
					} else if ($tsymbol == '>' && $concnum > $symnum) {
						$arr['conc'] = $arr['conc_dis'] = $arr['te_comparison_symbol'];
					}
				}
			}
		}
	}



	if ($testdb) {
		if (isset($_GET['createddate'])) {
			echo '<br><br>$testdb:';
			var_dump($testdb);
			echo '<br><br>';
			var_dump($arr);
			echo '<br><br>';
		}

		$arr['only_detected'] = false;
		$arr['has_critical'] = !empty($testdb->te_critical_high) || !empty($testdb->te_critical_low);

		if (empty($testdb->te_medium) && (empty($testdb->te_cutoff) || empty($testdb->te_upper_limit))) {
			$arr['only_detected'] = true;
		} else if (empty($testdb->te_cutoff) && empty($testdb->te_upper_limit)) {
			$arr['only_detected'] = true;
		}


		if (!empty($testdb->te_cutoff) && !empty($testdb->te_upper_limit)) {
			$arr['range'] = trpdd_show_symbol($testdb->te_cutoff) . ' - ' . trpdd_show_symbol($testdb->te_upper_limit);
			$arr['range'] = str_replace(array('<', '>', '='), '', $arr['range']);
		} else if (!empty($testdb->te_cutoff)) {
			if (strpos($testdb->te_cutoff, '-') > 0) {
				$arr['range'] = trpdd_show_symbol($testdb->te_cutoff);
			} else if (stripos($testdb->te_cutoff, '<') !== false) {
				$arr['range'] = $testdb->te_cutoff;
				$arr['range'] = trpdd_show_symbol(str_replace('<=', '>', $arr['range']));
			} else {
				$arr['range'] = trpdd_show_symbol('>=' . $testdb->te_cutoff);
			}
		} else if (!empty($testdb->te_upper_limit)) {
			if (strpos($testdb->te_upper_limit, '-') > 0) {
				$arr['range'] = trpdd_show_symbol($testdb->te_upper_limit);
			} else if (stripos($testdb->te_upper_limit, '>') !== false) {
				$arr['range'] = $testdb->te_upper_limit;
				$arr['range'] = trpdd_show_symbol(str_replace('>=', '<', $arr['range']));
			} else {
				$arr['range'] = trpdd_show_symbol('<=' . $testdb->te_upper_limit);
			}
		}

		$arr['notfound']    = false;
		$pass_equa          = false;
		$need_go_to_neg     = false;



		if (!empty($arr['conc']) && $arr['conc'] != 'Undetermined') {

			$arr['conc'] = str_replace(array('>', '<', '='), '', $arr['conc']);
			$arr['conc'] = trim($arr['conc']);


			$arr['old_pos-neg'] = $arr['pos-neg'];
			if (!empty($testdb->te_upper_limit) || !empty($testdb->te_cutoff)) {
				$arr['pos-neg'] = 'NEG';
			}

			if (!$pass_equa && !empty($testdb->te_upper_limit)) {
				$limitnum = str_replace(array('<', '>', '=', '-'), '', $testdb->te_upper_limit);
				if (empty($testdb->te_cutoff) && floatval($limitnum) == 0) {
					if (strtolower($arr['conc']) != strtolower($testdb->te_upper_limit)) {
						$arr['pos-neg'] = 'POS';
					}
				} else if (tr_pdd_pass_condition($arr['conc'], $testdb->te_upper_limit, '>')) {
					$arr['pos-neg'] = 'POH';
					$pass_equa = true;

					if (!empty($testdb->te_critical_high) && tr_pdd_pass_condition($arr['conc'], $testdb->te_critical_high, '>')) {
						$arr['critical'] = 'H';
						$arr['pos-neg'] = 'PCH';
					}
				} else {
					$need_go_to_neg = true;
				}
			}

			//check normal
			if (!$pass_equa && !empty($arr['conc']) && !empty($testdb->te_medium)) {
				$has_condition = true;
				if (tr_pdd_pass_condition($arr['conc'], $testdb->te_medium)) {
					$arr['pos-neg'] = 'NEG';
					$pass_equa = false;
				}
			}

			if (!$pass_equa && !empty($testdb->te_cutoff)) {
				$low_condition = !empty($testdb->te_upper_limit) ? '<' : '<=';
				if (tr_pdd_pass_condition($arr['conc'], $testdb->te_cutoff, $low_condition)) {
					$arr['pos-neg'] = 'POL';
					$pass_equa = true;

					if (!empty($testdb->te_critical_low) && tr_pdd_pass_condition($arr['conc'], $testdb->te_critical_low, '<')) {
						$arr['critical'] = 'L';
						$arr['pos-neg'] = 'PCL';
					}
				} else {
					$need_go_to_neg = true;
				}
			}

			if ($pass_equa) {
				if (!$testdb->te_is_detected || $arr['only_detected'] && !in_array($arr['pos-neg'], array('PCL', 'PCH'))) {
					$arr['pos-neg'] = 'POS';
				}
			} else if ($need_go_to_neg) {
				$arr['pos-neg'] = 'NEG';
			}


			if ($group_id > 0 && $arr['old_pos-neg'] != $arr['pos-neg'] && $arr['id'] > 0) {
				$wpdb->update('ci_results_gene', array('pos-neg' => $arr['pos-neg']), array('id' => $arr['id']));
			}
		}

		if (!empty($testdb->te_upper_limit)) {
			$arr['low'] = $testdb->te_cutoff;
			$arr['high'] = $testdb->te_upper_limit;
		} else {
			$arr['low'] = stripos($testdb->te_cutoff, '-') > 0 ? $testdb->te_cutoff : '<' . $testdb->te_cutoff;
		}

		$arr['antibiotic'] = $testdb->te_antibiotic;
		$arr['parsed_test' . $group_id] = true;

		$arr['detected_type'] = $testdb->te_is_detected;
	} else {
		$arr['notfound'] = true;
		$arr['only_detected'] = true;
	}

	if (isset($arr['te_range']) && !empty($arr['te_range'])) {
		$arr['range'] = $arr['te_range'];
	}
	if (!isset($arr['pos-neg'])) {
		$arr['pos-neg'] = '';
	}

	if (empty($arr['comments'])) {
		if ($arr['pos-neg'] == 'POS') {
			$arr['comments'] = $arr['te_comments'];
		} else if ($arr['pos-neg'] == 'NEG') {
			$arr['comments'] = $arr['te_comments_neg'];
		}
	}
	if ($arr['pos-neg'] == 'POS') {
		$arr['notes'] = $arr['te_pdf_note_pos'];
	} else if ($arr['pos-neg'] == 'NEG') {
		$arr['notes'] = $arr['te_pdf_note_neg'];
	}

	return $arr;
}

function trpdd_parse_tox_urine_test($arr, $report = array())
{
	global $wpdb;

	$arr['old_outcome'] = $arr['outcome'];

	$arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

	if (isset($arr['readonly']) && $arr['readonly'] && !empty($arr['outcome'])) {
		//dont change pos/neg
	} else if ($arr['outcome'] != 'CAN') {
		if (empty($arr['outcome']) && empty($arr['conc']) && $arr['u_default_blank']) {
			$arr['outcome'] = '';
		} else if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
			$arr['outcome'] = 'POS';
		} else if (!empty($arr['conc']) || $arr['conc'] === '0') {
			$arr['outcome'] =  'NEG';
		}

		if (strtolower($arr['comments']) == 'description') {
			$arr['comments'] = '';
		}
	}
	if (($arr['outcome'] == 'POS' || $arr['outcome'] == 'ABNORMAL')) {
		$k = $arr['conc'] < $arr['u_low'] ? 'low' : 'high';
		$cm = $arr['u_comments'];
		if (stripos($cm, '||') > 0) {
			if (stripos($arr['comments'], ($k == 'low' ? 'high' : 'low')) === 0) {
				$arr['comments'] = '';
			}
			if (empty($arr['comments'])) {
				$cms = explode('||', $cm);
				$has_cm = false;
				foreach ($cms as $c) {
					$c = trim($c);
					if (stripos($c, $k) === 0) {
						$cm = $c;
						$has_cm = true;
						break;
					}
				}
				if (!$has_cm) {
					$cm = $arr['conc'] < $arr['u_low'] ? $cms[0] : $cms[1];
				}
			}
			$arr['comments'] = $cm;
		} else if (empty($arr['comments'])) {
			$arr['comments'] = $arr['u_comments'];
		}
	} else if ($arr['comments'] == $arr['u_comments']) {
		$arr['comments'] = '';
	}

	if ($arr['old_outcome'] != $arr['outcome']) {
		$wpdb->update(
			'ci_result_test_speciments',
			array('outcome' => $arr['outcome'], 'comments' => $arr['comments']),
			array('mid' => $report['mid'], 'testid' => $arr['testid'])
		);
	}

	$outcomes = array(
		'' => 'Select',
		'NEG' => 'NORMAL',
		'POS' => 'ABNORMAL',
		'CAN' => 'Canceled',
	);
	if ($arr['u_neg_pos']) {
		$outcomes = array(
			'' => 'Select',
			'NEG' => 'NEG',
			'POS' => 'POS',
			'CAN' => 'Canceled',
		);
	}

	$arr['outcome_options'] = $outcomes;

	return $arr;
}

function trpdd_pcr_build_WH_plate_pos($id, $rebuild = false)
{
	global $wpdb;

	$log_dir            = WP_CONTENT_DIR . '/log/results_pcr/';

	$file   	= $wpdb->get_row("select * from ci_reports_files where rf_ID = {$id} ");
	$assens 	= explode(',', $file->rf_assen_list);
	$meta_data 	= json_decode($file->rf_meta_data, true);

	$headers = array(
		'wellposition' => 'Well Pos',
		'samplename' => 'Accession',
		'targetname' => 'Target name',
		'crt' => 'Crt',
		'crtmean' => 'Crt mean',
		'crtsd' => 'Crt sd',
		'ampscore' => 'Amp score',
		'cqconf' => 'Cq conf',
		'ampstatus' => 'Amp status',
		'highsd' => 'Highsd',
		'roxsignal' => 'Rox signal',
		'U' => 'Replicate Call',
		'AD' => 'Final Call',
		'AS' => '% Positive BV',
		'BD' => 'Auto QC Flag',
		'BF' => 'Notifiable Condition',
		'BH' => 'QC Note',
	);

	if (isset($_GET['debugwh'])) {
		$meta_data = array();
		if (isset($_GET['debug_header'])) {
			$hedebugs = array('AC', 'AT', 'AU', 'AP', 'AV', 'AW', 'AX', 'BD', 'BE', 'BF', 'BG', 'U', 'AD', 'AS', 'BF', 'BH');

			foreach ($hedebugs as $k) {
				if (isset($headers[$k])) {
					$headers[$k] = $k . '<br>' . $headers[$k];
				} else {
					$headers[$k] = $k;
				}
			}
		}
	}

	$need_build = $rebuild;
	if (isset($_GET['rebuild'])) {
		$need_build = true;
	}
	$data = array(
		'data' => $meta_data['rows']
	);
	if (empty($meta_data['rows'])) {

		$filepath = $log_dir . $file->rf_file;

		$data = trpdd_get_data($filepath, null, array('Well', 'TargetName'), true, false, true);
		$need_build = true;
	}

	if ($need_build) {

		$tests_wh = array();
		$all_tests = array();
		$sum_targets		= array();
		$sum_targets_keys	= array('AU', 'AT', 'AV');
		$sum_AQ 			= array();
		$all_row 			= array();
		$AD_has_detected 	= array();
		$all_report_ids 	= array();
		$updated_pos 		= array();
		$BD_string 			= array();
		$all_results		= array();
		$test_name2abrev	= array();
		$sp_drug_row_counts	= array();

		$notificols = array(
			'HD_Ba04646228_s1',
			'CTRA_Ba04646249_s1',
			'NG_Ba04646252_s1',
			//'GBS_Ba04646276_s1',
			'TP_Ba04646237_s1',
		);


		$grouppid = $wpdb->get_var("select tg_ID from ci_test_groups where tg_name = 'Comprehensive Vaginitis (WH)' ");
		if (empty($grouppid)) {
			$grouppid = $wpdb->get_var("select tg_ID from ci_test_groups where tg_short_name = 'WH' ");
		}
		$group_ids = $wpdb->get_col("select tg_ID from ci_test_groups where tg_parent = {$grouppid}");

		$group_ids[] = $grouppid;
		$group_ids_str = implode(',', $group_ids);
		$tests_wh_rs = $wpdb->get_results("select 
		te_ID, 
		te_name, 
		te_abrev,
		te_group_id,
		m.meta_value as idl_slope,
		(select meta_value from ci_test_meta where meta_key='idl_y_int' and test_id = te.te_ID limit 1) as idl_y_int
		from ci_test_panels te
		left JOIN ci_test_meta m ON m.test_id = te.te_ID and m.meta_key='idl_slope' and m.meta_value !=''
		where te.te_type = 'pcr' 
		and te_group_id in ({$group_ids_str})
		 ", ARRAY_A);


		foreach ($tests_wh_rs as $t) {
			$tests_wh[strtoupper($t['te_abrev'])] = $t;

			$test_name2abrev[strtoupper($t['te_name'])] = $t['te_abrev'];
		}

		$all_tests_rs = $wpdb->get_results("select te_ID, te_name, te_abrev,
		te_group_id,
		m.meta_value as idl_slope,
		(select meta_value from ci_test_meta where meta_key='idl_y_int' and test_id = te.te_ID limit 1) as idl_y_int
		from ci_test_panels te
		left JOIN ci_test_meta m ON m.test_id = te.te_ID and m.meta_key='idl_slope' and m.meta_value !=''
		where te.te_type = 'pcr' and te_in_req = 1
		
		 ", ARRAY_A);


		foreach ($all_tests_rs as $t) {
			$all_tests[strtoupper($t['te_abrev'])] = $t;
		}

		//get $all_results
		$assens_str  = implode("','", $assens);
		$all_results_rs = $wpdb->get_results(
			"select 
			re.id,
			re.patient_id,
			rs.drug, 
			rs.conc ,
			rs.cq, 
			rs.amp_score,
			rs.id as rsid
			from ci_reports_pcr re
			left join ci_results_pcr rs ON re.id = rs.report_id
			WHERE re.patient_id IN ('{$assens_str}') "
		);

		foreach ($all_results_rs as $r) {
			$r->patient_id = strtoupper($r->patient_id);
			$all_report_ids[$r->patient_id] = $r->id;

			$r->targetname = strtoupper($r->drug);
			$arr1 = explode('-', $r->targetname);
			$num = count($arr1) == 2 ? $arr1[1] : 0;
			if ($num >= 2) {
				$r->targetname = $arr1[0];
			}
			if (isset($test_name2abrev[$r->targetname])) {
				$r->targetname = strtoupper($test_name2abrev[$r->targetname]);
			} else if (isset($tests_wh[$r->targetname])) {
				//conver abrev to name
				$testname = strtoupper($tests_wh[$r->targetname]['te_name']);
				if ($num >= 2) {
					$testname .= '-' . $num;
				}
				$r->drug = $testname;
				$wpdb->update('ci_results_pcr', array(
					'drug' => $testname
				), array('id' => $r->rsid));
			}
			if ($num >= 2) {
				$r->targetname .= '-' . $num;
			}

			$all_results[$r->patient_id . '-' . $r->targetname] = array(
				'crt' => $r->conc,
				'cqconf' => $r->cq,
				'ampscore' => $r->amp_score,
				'report_id' => $r->id,
				'drug' => $r->drug,
				'id' => $r->rsid,
			);
		}

		if (isset($_GET['rebuild'])) {
			var_dump($all_results);
		}

		foreach ($data['data'] as $i => $row) {

			$row['samplename'] = strtoupper(trim($row['samplename']));
			$row['targetname'] = trim($row['targetname']);
			if (empty($row['samplename'])) {
				unset($data['data'][$i]);
				continue;
			}
			if (!in_array($row['samplename'], $assens)) {
				//unset($data['data'][$i]);
				//continue;
			}

			$target_key = strtoupper($row['targetname']);
			$row['test'] = array();
			if (isset($tests_wh[$target_key])) {
				$row['test'] = $tests_wh[$target_key];
			}
			if (empty($row['test']) && isset($all_tests[$target_key])) {
				$row['test'] = $all_tests[$target_key];
			}
			$row['test_name'] = $row['test']['te_name'];

			if (!isset($sp_drug_row_counts[$row['samplename'] . $row['targetname']])) {
				$sp_drug_row_counts[$row['samplename'] . $row['targetname']] = 0;
			}

			$sp_drug_row_counts[$row['samplename'] . $row['targetname']]++;
			//get data from result table
			if ($sp_drug_row_counts[$row['samplename'] . $row['targetname']] >= 2) {
				$target_key = $target_key . '-' . $sp_drug_row_counts[$row['samplename'] . $row['targetname']];
			}


			if (isset($all_results[$row['samplename'] . '-' . $target_key])) {
				$rsvl = $all_results[$row['samplename'] . '-' . $target_key];
				if (isset($_GET['rebuild'])) {
					//var_dump($rsvl);
					$wpdb->update('ci_results_pcr', array(
						'conc' => $row['crt'],
						'cq' => $row['cqconf'],
						'amp_score' => $row['ampscore'],
					), array(
						'report_id' => $rsvl['report_id'],
						'drug' => $rsvl['drug']
					));
				} else {

					$row['crt'] = $rsvl['crt'];
					$row['cqconf'] = $rsvl['cqconf'];
					$row['ampscore'] = $rsvl['ampscore'];
					unset($all_results[$row['samplename'] . '-' . $target_key]);
				}
			}

			$row['class'] = '';
			if ($row['test']['te_group_id'] == 35) {
				$row['class'] = isset($row['test']['te_name']) ? 'bacteria ba_' . strtolower($row['test']['te_name']) : '';
			}


			$row['idl_slope'] = '';
			$row['idl_y_int'] = '';

			if ($row['test']['te_group_id'] == 35) {
				$row['idl_slope'] = $row['test']['idl_slope']; //AE
				$row['idl_y_int'] = $row['test']['idl_y_int']; //AF
			}


			$row['lisamp'] = $row['ampscore'] >= 1.15 ? 1 : 0; //=IF(L5>=1,15;1;0)
			$row['liscq'] = $row['cqconf'] >= 0.75 ? 1 : 0; //=IF(M>=0,75;1;0)
			$row['lisct'] = $row['crt'] > 0 && $row['crt'] <= 31.4 ? 'true' : 'false';
			$row['qcsum'] = $row['lisamp'] + $row['liscq'];
			$row['U'] = ($row['lisct'] == 'true' && $row['qcsum'] >= 2) ? "DETECTED" : "NOT DETECTED"; //U

			$row['V'] = $row['samplename'] . $row['targetname']; //=D&E
			$row['W'] = $row['samplename'] . $row['targetname']; //=D&E
			$row['Z'] = $row['Y'] = $row['U'] . $row['V'];

			$all_row['Y'][] = $row['Y'];
			$all_row['W'][] = $row['W'];

			$data['data'][$i] = $row;
		}

		$Ycounts = array_count_values($all_row['Y']);
		$Wcounts = array_count_values($all_row['W']);

		foreach ($data['data'] as $i => $row) {

			if (isset($sp_drug_row_counts[$row['samplename'] . $row['targetname']])) {
				$row['rowcount'] = $sp_drug_row_counts[$row['samplename'] . $row['targetname']];
			}

			$row['AA'] = $Ycounts[$row['Z']]; //=COUNTIF(Y:Y;Z222)
			$row['X'] = $Wcounts[$row['W']]; //=COUNTIF(V:V;W222)
			$row['AB'] = $row['U'] == 'DETECTED' ? $row['AA'] : 0; //=IF(U229="DETECTED";AA229;0)
			$row['AC'] = $row['AB'] / $row['X']; //AC = =AB231/X231
			$row['AT'] = $row['crt'] <= 31 && $row['crtmean'] < 1 ? 1 : 0; //=IF(AND(I353<=31,4;L353<1,14);1;0)

			$row['AD'] = $row['AC'] >= 0.5 ? 'DETECTED' : 'NOT DETECTED'; //AD = =IF(AC232>=50%;"DETECTED";"")

			if ($row['AD'] == 'DETECTED') {
				$AD_has_detected[$row['samplename'] . $row['targetname']] = true;
			}

			$data['data'][$i] = $row;
		}

		foreach ($data['data'] as $i => $row) {

			$row['AO'] = $AD_has_detected[$row['samplename'] . $row['targetname']] ? 'DETECTED' : 'NOT DETECTED'; //AO = =IF(COUNTIF(AD41:AD43;"DETECTED");"DETECTED";"NOT DETECTED")
			$row['AD'] = $AD_has_detected[$row['samplename'] . $row['targetname']] ? 'DETECTED' : 'NOT DETECTED';
			$row['AG'] = $row['idl_slope'] != 0 ? round(pow(10, (($row['crtmean'] - $row['idl_y_int']) / $row['idl_slope'])), 2) : 0; //AG = POWER(10;(J236-AF236)/AE236)
			$row['AQ'] = $row['AO'] == 'DETECTED' ? round($row['AG'], 2) : 0; //AQ = =IF(AND(ISNUMBER(AG232);AO232="DETECTED");AG232;"")
			if (!isset($sum_AQ[$row['samplename']])) {
				$sum_AQ[$row['samplename']] = array();
			}
			$sum_AQ[$row['samplename']][$row['targetname']] = $row['AQ'];

			foreach ($sum_targets_keys as $tk) {
				if (!isset($sum_targets[$tk][$row['samplename'] . $row['targetname']])) {
					$sum_targets[$tk][$row['samplename'] . $row['targetname']] = array();
				}
				$sum_targets[$tk][$row['samplename'] . $row['targetname']][] = $row[$tk];
			}



			$data['data'][$i] = $row;
		}

		foreach ($sum_AQ as $k => $arr) {
			$sum_AQ[$k] = array_sum($arr);
		}

		foreach ($sum_targets_keys as $tk) {
			foreach ($sum_targets[$tk] as $k => $arr) {
				$sum_targets[$tk][$k] = array_sum($arr);
			}
		}


		foreach ($data['data'] as $i => $row) {


			$row['AS'] = $sum_AQ[$row['samplename']] > 0 && $row['AQ'] > 0 ? round(($row['AQ'] / $sum_AQ[$row['samplename']]) * 100, 2) : '';
			$row['AS'] = !empty($row['AS']) ? $row['AS'] . '%' : '';

			$row['AP'] = $row['AO'] = $row['AD'];

			//$row['BE'] = ($row['AP'] == 'DETECTED' && empty($row['AS'])) ? 'Notifiable' : '';
			$row['BE'] = in_array($row['targetname'], $notificols) ? 'Notifiable' : '';

			$row['AW'] = $sum_targets['AT'][$row['samplename'] . $row['targetname']] > 0 && $row['AP'] == 'DETECTED' ? ' Amp Score Fail: Possible False Positive ' : ''; //=IF(AND(SUM(AT350:AT352)>0;AP350="DETECTED");"Amp Score Fail: Possible False Positive ";"")
			$row['AX'] = $sum_targets['AU'][$row['samplename'] . $row['targetname']] > 0 && $row['AP'] == 'DETECTED' ? ' Cq Conf Fail: Possible False Positive ' : ''; //==IF(AND(SUM(AU350:AU352)>0;AP350="DETECTED");"Cq Conf Fail: Possible False Positive ";"")
			$row['AY'] = $sum_targets['AV'][$row['samplename'] . $row['targetname']] > 0 && $row['AP'] == 'DETECTED' ? ' SD Fail: Possible False Positive ' : ''; //==IF(AND(SUM(AV350:AV352)>0;AP350="DETECTED");"SD Failure: Possible False Positive ";"")

			$row['BF'] = $row['BE'] == 'Notifiable' && $row['AP'] == 'DETECTED' ? ' NOTIFY HEALTH DEPARTMENT ' : ''; //=IF(AND(BE353="Notifiable";AP353="DETECTED");"NOTIFY HEALTH DEPARTMENT";"")
			$row['BG'] = stripos($row['samplename'], 'ntc') !== false ? 1 : 0; //=IF(ISNUMBER(SEARCH("ntc";AH16S92));1;0)
			$row['BH'] = $row['BG'] > 0 && $row['AP'] == 'DETECTED' ? 'NTC CONTAMINATED' : ''; //=IF(AND(BG1697>0;AP1697="DETECTED");"NTC CONTAMINATED";"")

			if (!isset($BD_string[$row['samplename'] . $row['targetname']])) {
				$BD_string[$row['samplename'] . $row['targetname']] = $row['BF'];
			}
			$BD_string[$row['samplename'] . $row['targetname']] .= $row['AW'] . $row['AX'] . $row['AY'] . $row['BH'];


			$data['data'][$i] = $row;
		}

		//update %positive 
		foreach ($data['data'] as $i => $row) {

			if (isset($_GET['dd2'])) {
				var_dump($row['samplename']);
			}

			$row['BD'] = $BD_string[$row['samplename'] . $row['targetname']]; //==AW353&AW354&AW355&AX353&AX354&AX355&AY353&AY354&AY355&BH353&BH354&BH355&BF353

			if (!isset($updated_pos[$row['samplename'] . $row['targetname']])) {

				$report_id = isset($all_report_ids[$row['samplename']]) ? $all_report_ids[$row['samplename']] : 0;

				if ($report_id > 0) {
					$wpdb->update(
						'ci_results_pcr',
						array(
							'percent_positive' => $row['AS']
						),
						array(
							'report_id' => $report_id,
							'drug' => $row['test_name'],
						)
					);

					$wpdb->update(
						'ci_results_pcr',
						array(
							'percent_positive' => $row['AS']
						),
						array(
							'report_id' => $report_id,
							'drug' => $row['targetname'],
						)
					);
				}
			}


			//prepare before save to db

			foreach ($row as $k => $vl) {
				if (!in_array($k, array('test_name', 'class', 'AQ', 'AS', 'rowcount')) && !isset($headers[$k])) {
					unset($row[$k]);
				}
			}

			$data['data'][$i] = $row;
		}


		$rows = $data['data'];



		$rs = $wpdb->update(
			'ci_reports_files',
			array(
				'rf_meta_data' => json_encode(array(
					'rows' => $rows,
					'time' => time(),
				))
			),
			array('rf_ID' => $id)
		);
	}

	return array('headers' => $headers, 'rows' => $data['data']);
}

function trpdd_pcr_check_update_WH($report = array(), $force_update = false)
{
	global $wpdb;
	$assen = isset($report['assen']) && !empty($report['assen']) ? $report['assen'] : $report['patient_id'];

	$file = $wpdb->get_row("select * from ci_reports_files 
	where rf_covid_type = 'PWH' and FIND_IN_SET('{$assen}', rf_assen_list) 
	ORDER BY rf_ID desc
	limit 1");


	if ($file) {
		//var_dump($file->rf_file);
		if ($force_update || is_null($file->rf_meta_data) || empty($file->rf_meta_data)) {
			trpdd_pcr_build_WH_plate_pos($file->rf_ID, $force_update);
		}
	}
}

function trpdd_get_WH_arr($report_id, $drug, $abrev)
{
	global $wpdb;
	static $ci_wh_got_data;
	if (!is_array($ci_wh_got_data)) {
		$ci_wh_got_data = array();
	}
	$arr = array(
		'drug' => $drug,
		'report_id' => $report_id,
	);

	$arr1 = explode('-', $drug);
	$targetname = $arr1[0];
	$num = count($arr1) == 2 ? $arr1[1] : 0;

	$assen = $wpdb->get_var("select patient_id from ci_reports_pcr where id = {$report_id}");
	$id = $wpdb->get_var("select rf_ID 
	from ci_reports_files 
	where find_in_set('{$assen}', rf_assen_list) 
	and rf_covid_type = 'PWH' 
	order by rf_ID desc
	limit 1");

	if ($id > 0) {
		if (!isset($ci_wh_got_data[$id])) {
			$ci_wh_got_data[$id] = trpdd_pcr_build_WH_plate_pos($id);
		}
		$numnow = 0;
		foreach ($ci_wh_got_data[$id]['rows'] as $row) {
			if ($assen != $row['samplename'] || $abrev != $row['targetname']) {
				continue;
			}
			$numnow++;
			if ($num == $numnow) {
				$arr['conc'] = $row['crt'];
				$arr['cq'] = $row['cqconf'];
				$arr['amp_score'] = $row['ampscore'];

				$existid = $wpdb->get_var(
					"select id 
					from ci_results_pcr
					where report_id = {$report_id} 
					and drug = '{$arr['drug']}' "
				);

				if ($existid > 0) {
					$wpdb->update('ci_results_pcr', $arr, array('id' => $existid));
					$arr['id'] = $existid;
				} else {
					$wpdb->insert('ci_results_pcr', $arr);
					$arr['id'] = $wpdb->insert_id;
				}
			}
		}
	}
	return $arr;
}


function trpdd_get_bmi($height, $weight)
{
	$bmi 	= '';
	$feets 	= 0;
	$inches = $weight;

	if (strpos($height, '\'') > 0) {
		list($feets, $inches) = explode('\'', $height);
	} else if (stripos($height, 'feets') > 0) {
		list($feets, $inches) = explode('feets', $height, 2);
	} else if (stripos($height, 'foot') > 0) {
		list($feets, $inches) = explode('foot', $height, 2);
	}

	$height = (floatval($feets) * 12) + floatval($inches);
	if ($height > 0 && $weight > 0) {
		$bmi = number_format($weight / ($height ** 2)  * 703, 2);
	}

	return $bmi;
}


function tr_pdd_get_result_flags($arr, $btype, $showall = false)
{
	if (!isset($arr['te_is_detected'])) {
		Tr_Session_Class::add_error("test missing pos/neg type");
	}
	$pos_neg_vls = array();
	if ($btype == 'blood') {
		$pos_neg_vls = array(
			'INV' => 'Invalid',
			'NEG' => 'Negative',
			'POS' => 'Positive',
			'PEN' => 'Pending',
			'CAN' => 'Test Cancelled',
		);


		if (tr_get_option('ci_result_settings_blood', 'result_cancel_rejected')) {
			$pos_neg_vls['ICL'] = 'Inconclusive';
			$pos_neg_vls['CAN'] = 'Cancel';
			$pos_neg_vls['REJ'] = 'Rejected';
			$pos_neg_vls['INC'] = 'Incomplete';
		}

		if ($arr['te_is_detected'] == '1') {
			$pos_neg_vls['POS'] = 'High';
			$pos_neg_vls['NEG'] = 'Normal';
			if (!isset($arr['only_detected']) || !$arr['only_detected']) {
				$pos_neg_vls['POL'] = 'Low';
				$pos_neg_vls['POM'] = 'Medium';
				$pos_neg_vls['POH'] = 'High';
				if (!$showall) {
					unset($pos_neg_vls['POS']);
				}
			}
		} else if ($arr['te_is_detected'] == '2') {
			$pos_neg_vls['POS'] = 'Detected';
			$pos_neg_vls['NEG'] = 'Not Detected';
		} else if ($arr['te_is_detected'] == '3') {
			$pos_neg_vls['POS'] = 'Reactive';
			$pos_neg_vls['NEG'] = 'Not Reactive';
		}


		if (isset($arr['has_critical']) && $arr['has_critical']) {
			$pos_neg_vls['PCL'] = 'Critical Low';
			$pos_neg_vls['PCH'] = 'Critical High';
		}


		if (!empty($arr['te_critical_low']) || $showall) {
			$pos_neg_vls['PCL'] = 'Critical-Low';
		}

		if (!empty($arr['te_critical_high']) || $showall) {
			$pos_neg_vls['PCH'] = 'Critical-High';
		}

		if (isset($arr['te_result_values']) && strlen($arr['te_result_values']) > 3) {
			$vls = explode(',', $arr['te_result_values']);
			$pos_neg_vls_new = array();
			foreach ($vls as $vt) {
				$vt = trim($vt);
				if (!empty($vt)) {
					$vt_k = trim(substr($vt, 0, 15));
					$pos_neg_vls_new[$vt_k] = $vt;
				}
			}
			if (count($pos_neg_vls_new) > 0) {
				$pos_neg_vls = $pos_neg_vls_new;
			}
		}
	} else if ($btype == 'pcr') {
		$pos_neg_vls = array(
			'' => '',
			'NEG' => 'Not Detected',
			'POS' => 'Detected',
			'POL' => 'Detected-Low',
			'POM' => 'Detected-Medium',
			'POH' => 'Detected-High',
			'PEN' => 'Pending',
			'INC' => 'Inconclusive',
			'INV' => 'Invalid',
			'IND' => 'Indeterminate',
			'CAN' => 'Test Cancelled',
			'REJ' => 'Rejected'
		);
	} else {
		$pos_neg_vls = array(
			'NEG' => 'NEG',
			'POS' => 'POS',
			'PEN' => 'Pending'
		);
	}
	return $pos_neg_vls;
}

function tr_pdd_get_result_flag($arr, $btype)
{
	$flags = tr_pdd_get_result_flags($arr, $btype, true);

	$flag = isset($flags[$arr['pos-neg']]) ? $flags[$arr['pos-neg']] : $arr['pos-neg'];
	if (!empty($arr['te_result_values'])) {
		$flag = isset($flags[$arr['conc']]) ? $flags[$arr['conc']] : '';
	}
	if (isset($_GET['debug11'])) {
		$flag .= json_encode($flags) . $arr['te_is_detected'];
	}
	return $flag;
}


function trpdd_average($arr)
{
	return count($arr) ? array_sum($arr) / count($arr) : 0;
}

function trpdd_stdev($arr)
{
	$n = count($arr);
	if ($n < 2) return 0;
	$mean = trpdd_average($arr);
	$sumSq = 0;
	foreach ($arr as $value) {
		$sumSq += pow($value - $mean, 2);
	}

	return sqrt($sumSq / ($n - 1));
}
function trpdd_float_val($val)
{
	$val = str_replace(array('<', '>', '='), '', $val);
	return floatval($val);
}
//end file