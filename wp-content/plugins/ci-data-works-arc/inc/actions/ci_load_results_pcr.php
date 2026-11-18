<?php
global $wpdb, $blog_id, $current_role;
global $exclude_assens;
global $is_ci_admin;
global $rereun_by_both_rna_fail;

$btype					= 'pcr';
$exclude_assens         = array();
$rows_top_html_arr      = array();
$rows_html_arr          = array();

$user_id                = get_current_user_id();
$batch_id               = isset($_REQUEST['batch_id']) ? $_REQUEST['batch_id'] : '';
$lab_id                 = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : '';
$ref_lab_id             = isset($_REQUEST['ref_lab_id']) ? $_REQUEST['ref_lab_id'] : '';
$verify                 = isset($_REQUEST['verify']);
$is_data_qc             = isset($_REQUEST['data_qc']);
$filename               = isset($_REQUEST['filename']) ? $_REQUEST['filename'] : '';
$pending                = isset($_REQUEST['pending']);
$orderable      		= isset($_REQUEST['orderable']) ? $_REQUEST['orderable'] : '';
$allow_rack4s           = tr_get_option('ci_pdf_data', 'rack_4s_well');

$admin_view         	= isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']) || $is_ci_admin;
$hide_patient_info      = isset($_REQUEST['hidepatient']);
$shownewabx				= isset($_GET['shownewabx']);
$can_delete             =  !tr_get_option('ci_pdf_data', 'req_ddelete', false);
$can_edit               = true;
$can_view               = true;
$only_approve           = false;
$show_pos_top           = false;
$need_reload            = false;
$covid_group_id         = 0;
$all_groups             = array();
$group_all_negs 		= array();
$reflex_group_ids 		= array();
$all_tests              = array();
$inconclusive_drugs     = array();
$state_testids 			= array();
$tests_in_reflex     = array();
$limit_time_auto_approve = time() - 86400 * 5;
$pcr_cv_file            = tr_get_option('ci_pdf_data', 'pcr_cv_file');
$pcr_dcvbrerun          = tr_get_option('ci_pdf_data', 'pcr_dcvbrerun');
$show_final_btn          = tr_get_option('ci_pdf_data', 'rs_show_finalbtn');
$show_view_btn          = tr_get_option('ci_pdf_data', 'rs_show_viewbtn');
$pdf_browser			= tr_get_option('ci_pdf_data', 'pdf_browser') || $admin_view;
$rejected_status        = tr_pdd_get_batch_statuses(false, true, true, 'pcr');

$result_settings 		= get_option('ci_result_settings_pcr', array());
if (!isset($result_settings['list_show_rlab'])) {
	$result_settings['list_show_rlab'] = false;
}

if (!isset($result_settings['reflab_status'])) {
	$result_settings['reflab_status'] = false;
}

if ($current_role == 'results') {
	$can_delete = false;
	$can_edit = false;
	if (tr_get_option('ci_pdf_data', 'pcr_pos_top')) {
		$show_pos_top = true;
	}
}

$all_labs = array();
$rsl = $wpdb->get_results("select lab_ID, lab_dba_init from ci_labs");
foreach ($rsl as $r) {
	$all_labs[$r->lab_ID] = $r;
}


$rs_groups = $wpdb->get_results("select 
	tg_ID, 
	tg_name,
	tg_is_covid,
	tg_other_options,
	tg_parent,
	tg_auto_approve_neg,
	tg_auto_approve_pos_neg,
	tg_reflex_group_ID
	from ci_test_groups where tg_type = 'pcr' ");

foreach ($rs_groups as $g) {
	$g->other_options = json_decode($g->tg_other_options, true);
	$all_groups[$g->tg_ID] = $g;
	if (stripos($g->tg_name, 'nCoV-2019') !== false || $g->tg_is_covid == 1) {
		$covid_group_id = $g->tg_ID;
	}

	if ($g->tg_auto_approve_neg && $g->tg_parent == 0) {
		$group_all_negs[$g->tg_ID] = 0;
	}
	$reflexids = explode(',', $g->tg_reflex_group_ID);
	foreach ($reflexids as $reflexid) {
		if ($reflexid > 0) {
			$reflex_group_ids[] = $reflexid;
		}
	}
}
unset($rs_groups);


$rs = $wpdb->get_results("select te.* ,
g.tg_parent,
g.tg_antibiotic
from ci_test_panels te 
JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
where te_type='pcr'");
foreach ($rs as $r) {
	$r->drug = strtoupper($r->te_name);
	$all_tests[$r->te_ID] = $r;
	if ($r->te_inconclusive_ID == -1 || $r->te_is_control) {
		$inconclusive_drugs[] = $r->te_name;
		if (!empty($r->abrev)) {
			$inconclusive_drugs[] = $r->te_abrev;
		}
		if (!empty($r->abrev2)) {
			$inconclusive_drugs[] = $r->te_abrev2;
		}
	}
	$mgroupid = $r->tg_parent > 0 ? $r->tg_parent : $r->te_group_id;

	if (isset($group_all_negs[$mgroupid]) && !$r->te_is_control) {
		$group_all_negs[$mgroupid]++;
	}

	if (in_array($r->te_group_id, $reflex_group_ids) || $r->tg_antibiotic) {
		$tests_in_reflex[] = $r->te_ID;
	}
}

$inconclusive_drugs = implode("','", $inconclusive_drugs);
$tests_in_reflex 	= implode(',', $tests_in_reflex);


if ($is_data_qc) {
	$covid_type = !empty($_REQUEST['cvtype']) ? $_REQUEST['cvtype'] : 'ic';
	$covid_tests = array();
	$all_covid_tests = trpdd_get_covid_tests($covid_type);
	foreach ($all_covid_tests as $tt => $t) {
		$t = strtoupper($t);
		$covid_tests[] = $t;
	}
	$tests_str = implode("','", $covid_tests);
	$bygroup = false;
	$where_drug = " and rs.drug = '{$covid_tests[0]}' ";
	if (stripos($covid_type, 'g_') !== false) {
		$bygroup = true;
		$covid_group_id = str_replace('g_', '', $covid_type);
		$covid_tests = array();
		$where_drug = '';
		$where_rf   .= " and rf.rf_covid_type = '' ";
		$where      .= " and FIND_IN_SET('GROUP_{$covid_group_id}', ba.panel_tests_requested) ";
	}


	$pcr_notaqcnan = tr_get_option('ci_pdf_data', 'pcr_notaqcnan');
	$show_plate_well = tr_get_option('ci_pdf_data', 'pcr_qcshowplate');
	$rack_16 = tr_get_option('ci_pdf_data', 'rack_16');

	$where = " ba.is_saved = 0 ";
	if (!empty($_REQUEST['s'])) {
		$where .= " and ba.assen = '{$_REQUEST['s']}' ";
	} else if (!empty($_REQUEST['ids'])) {
		$sql_more = "select re.id from ci_reports_pcr re
                                join ci_batches_pcr ba ON ba.assen = re.patient_id 
                                join ci_results_pcr rs On rs.report_id = re.id {$where_drug}
                                where FIND_IN_SET('{$filename}',re.upload_files)  ";
		$more_ids = $wpdb->get_col($sql_more);


		$ids = explode(',', $_REQUEST['ids']);

		$ids = array_merge($ids, $more_ids);
		$ids = implode(',', $ids);
		$where .= " and re.id in ({$ids}) ";
	} else {
		$fileobj = $wpdb->get_row("select * from ci_reports_files where rf_file = '{$filename}' and rf_btype='pcr' ");

		$assens = explode(',', $fileobj->rf_assen_list);
		$assens_str = implode("','", $assens);
		$where .= " and ba.assen in ('{$assens_str}') ";
	}


	if (!tr_current_role_in(array('administrator', 'lab', 'lab_rep')) && current_user_can('only_rs_final')) {
		$where .= " and re.final_results = 1 ";
	}

	if (CI_Base_Lab::has_user_labs()) {
		$lab_str = CI_Base_Lab::get_user_lab_ids_str();
		$where .= " and (ba.lab_id in ({$lab_str})  or ba.ref_lab_ID in ({$lab_str}) )";
	}



	$selectmore = '';
	$joinsmore = '';

	$orderby = "ba.position";
	if ($show_plate_well) {
		if ($rack_16) {
			$joinsmore .= " left JOIN ci_plates_batches pb ON pb.mid = ba.mid and pb.btype='pcr' ";
			$joinsmore .= " left JOIN ci_plates pl ON pl.pl_ID = pb.pl_ID";
			$selectmore .= ", pb.combine_position as well, pl.pl_combine_ID, pb.pl_ID ";
			//$orderby = "ba.position, pb.combine_position";
		} else {
			$joinsmore .= " left JOIN ci_plates_batches pb ON pb.mid = ba.mid and pb.btype='pcr' ";
			$selectmore .= ", pb.position as well, 0 as pl_combine_ID, pb.pl_ID ";
			//$orderby = "pb.position";
		}
	}



	$sql = "select 
		ba.mid,
		ba.position,
		ba.assen,
		ba.batch_id,
		ba.batch_status,
		ba.panel_tests_requested,
		ba.cli_ID,
		ba.doc,
		ba.culture,
		
		re.id,
		re.upload_files,
		re.estatus,
		re.instrument,
		re.created_date,
		re.faxed,
		re.final_results,
        re.missingcovid,
        re.has_pos,
        re.covid_pos,
        re.rerun_count,
        re.modified,
        re.rerun_modified
		{$selectmore}
    from ci_batches_pcr ba
    join ci_reports_pcr re ON re.patient_id = ba.assen
    join ci_results_pcr rs On rs.report_id = re.id {$where_drug}
    {$joinsmore}
    where {$where} 
    group by ba.mid
    order by {$orderby} asc";

	$results = $wpdb->get_results($sql);

	$covid_test_w = 160 - count($covid_tests) * 5;
	foreach ($results as $row) {
		$update = array();
		$mstatus = '';
		$flag = '';
		$row->classes = '';
		$row->no_action = false;
		$row->result = array();
		$more_text_estatus = '';
		$has_value = true;
		$panels = array();

		if (in_array($row->assen, $exclude_assens)) {
			continue;
		}

		if (isset($row->culture) && $row->culture) {
			$mstatus = '<ogr>Cu</ogr>';
		}

		if ($row->faxed) {
			$mstatus .= '<span class="ricon faxed" title="Faxed">F</span>';
		}


		foreach (explode(',', $row->panel_tests_requested) as $t) {
			if (stripos($t, 'group') !== false) {
				$group_id = str_ireplace('group_', '', $t);

				if (isset($all_groups[$group_id])) {
					$panels[] = $all_groups[$group_id]->tg_name;
				}
			}
		}


		if (!$bygroup) {
			$rs = $wpdb->get_results("select * from ci_results_pcr where report_id = {$row->id}
			and drug in ('{$tests_str}')", ARRAY_A);
			$has_value = count($rs) >= count($covid_tests) - 1;

			$arr = array();
			$arr['list'] = array();
			foreach ($rs as $ra) {
				$drug = strtoupper($ra['drug']);
				$row->result[$drug] = $ra['conc'];
				if ($ra['conc'] === '' || is_null($ra['conc'])) {
					$has_value = false;
				}
				$arr['list'][$drug] = $ra;
			}

			$arr = trpdd_cal_ncovid19($arr, $row->id, $row->instrument, $row->created_date);
			if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PCH', 'PCL', 'PVH', 'PVL'))) {
				$row->classes .= " has_pos ";
				if ($pcr_cv_file && $arr['cal_posneg'] == 'NEG') {
					$more_text_estatus .= ' <checkmark class="white"></checkmark>';
				}
			} else if ($pcr_cv_file && in_array($arr['cal_posneg'], array('POS', 'POL', 'POH', 'POM', 'PCH', 'PCL', 'PVH', 'PVL'))) {
				$more_text_estatus .= ' <checkmark class="red"></checkmark>';
			}


			$old_covid_pos = $row->covid_pos;

			if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PCH', 'PCL', 'PVH', 'PVL'))) {
				$row->covid_pos = 1;
			} else if ($arr['pos-neg'] == 'RER' || $arr['pos-neg'] == 'INV') {
				$row->covid_pos = 2;
				$has_value = false;
			} else if ($arr['pos-neg'] == 'NEG') {
				$row->covid_pos = 0;
			}

			if ($old_covid_pos != $row->covid_pos) {
				$update['covid_pos'] = $row->covid_pos;
				if ($row->covid_pos == 2 && $row->rerun_modified < $row->modified) {
					$update['rerun_count'] = $row->rerun_count + 1;
					$update['rerun_modified'] = $row->modified;
				}
			}

			if (count($update) > 0) {
				$update = implode(', ', $update);
				$wpdb->query("update ci_reports_pcr set {$update} where id = {$row->id}");
				$count_updated++;
			}

			//check rack4
			if ($allow_rack4s) {
				$row = trpdd_pcr_parse_rack4($row, $results);
			}

			if (isset($rejected_status[$row->batch_status])) {
				$mstatus .= '<span class="ricon rejected" title="Rejected">X</span>';
			} else if ($row->covid_pos == 2) {

				$rerun_html = '<span style="color:' . ($row->rerun_count > 1 ? 'green' : 'black') . '">';
				$rerun_html .= 'Rerun';
				$rerun_html .= '</span>';

				if ($pcr_dcvbrerun && $row->rerun_count == 1) {
					$mstatus .= '<span class="firstrerun"></span>';
				} else {
					$mstatus .= $rerun_html;
				}
			}

			if (!$has_value) {
				$row->classes .= " no_value ";

				//can approve when rerun
				if ($row->rerun_count > 1) {
					$has_value = true;
				}
			}
		}


		if ($row->cli_ID == 0) {
			$row->classes .= " no_batch ";
		}
		$row->classes .= ' bs_' . $row->batch_status;

		if ($show_plate_well) {
			$row->position = trpdd_pos_to_well($row->well, $row->pl_ID, $row->pl_combine_ID);
		}

		if (isset($row->need_reload) && $row->need_reload) {
			$need_reload = true;
		}

		ob_start();
?>
		<tr class="childp <?php echo $row->classes ?>" title="<?php if ($row->batch_status == 'hold') echo 'On Hold' ?>" data-id="<?php echo $row->id ?>">
			<td style="width: 42px;" class="cid" title="<?php echo $row->mid ?>"><?php echo $row->id ?></td>
			<td style="width: 120px;"><a href="<?php Tr_Base_Class_V6::link(array('act' => 'view_req', 'id' => $row->mid)) ?>" target="_blank"><?php echo $row->assen ?></a></td>
			<td style="width: 80px"><?php echo $row->position ?></td>
			<td style="width: 18%" class="panels"><?php
													foreach ($panels as $panel) {
														echo '<li><span>' . $panel . '</span></li>';
													} ?></td>
			<td style="width: 110px" class="final_results" data-val="<?php echo $row->final_results ?>"><?php echo tr_pdd_get_result_status($row->final_results); ?></td>
			<?php
			foreach ($covid_tests as $t) {
				$vl = isset($row->result[$t]) ? $row->result[$t] : '';
				if ($vl == 'Undetermined' || stripos($vl, 'nan') !== false || stripos($vl, 'no ct') !== false) {
					$vl = 45;
				}
			?>
				<td class="center" title="cutoff: <?php echo isset($arr['cutoff']) ? $arr['cutoff'] : '' ?>" style="width: <?php echo $covid_test_w ?>px"><?php echo $vl ?></td>
			<?php
			}
			?>
			<td style="width:40px" class="mstatus"><?php echo $mstatus ?></td>
			<td style="width: 60px" class="statuscol result_<?php echo $row->estatus ?>">
				<div class="statusbg"><?php echo $row->estatus . $more_text_estatus ?></div>
			</td>
			<td class="cactions">
				<?php
				if (!$row->no_action) {
					if ($can_edit) {
				?>
						<span class="editlink" <?php if ($row->estatus == 'C')
													echo 'style="display:none"' ?>>
							<a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id, 'qc' => 1)) ?>">Edit</a>
						</span>
					<?php
					}
					$text_approve = $row->estatus == 'C' ? 'Reverse' : 'Approve';
					if ((!isset($only_view) || !$only_view) && $has_value && $row->cli_ID > 0) {
					?>

						<span class="approvediv"><a class="approvebtn bsty5" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a></span>
				<?php
					}
				}

				?>
			</td>
		</tr>
	<?php
		$rows_html_arr[$row->assen] = ob_get_clean();
	}

	if ($need_reload) {
		echo '<div class="need_reload">Need reload</div>';
		exit;
	}

	echo '<table class="report_tbl child_list">';
	foreach ($rows_html_arr as $assen => $html) {
		if (!in_array($assen, $exclude_assens)) {
			echo $html;
		}
	}
	echo '</table>';
} else {

	$today          = Tr_Base_Class_V6::date('Y-m-d');


	$is_test_results = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';
	$last_login = get_user_meta($user_id, '_last_login', true);
	if (empty($last_login)) {
		$last_login = date('Y-m-d H:i:s', strtotime('-2days'));
	}
	$last_login_time = strtotime($last_login);


	$result_dir = tr_get_result_upload_dir();

	$where = " and ba.batch_id = '{$batch_id}' ";
	$where .= " and ba.batch_status != 'archived' ";


	if ($ref_lab_id > 0) {
		$where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
	} else if ($lab_id > 0) {
		$where .= " and (ba.lab_id = {$lab_id} ) ";
	} else if (CI_Base_Lab::has_user_labs()) {
		$lab_str = CI_Base_Lab::get_user_lab_ids_str();
		$where .= " and (ba.lab_id in ({$lab_str})  or ba.ref_lab_ID in ({$lab_str}) )";
	}

	$order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';


	if ($result_settings['sort_df_assen']) {
		if (!isset($_REQUEST['orderby']) || empty($_REQUEST['orderby'])) {
			$_REQUEST['orderby'] = 'assen';
		}
	}
	$orderby = isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'ba.position';

	$orderby = ' order by ' . $orderby . ' ' . $order;

	$joins = '';



	$only_view = false;

	$conditions = trpdd_get_batch_conditions(0, 'pcr');
	$where      .=  $conditions['where'];

	$only_view 		= $conditions['only_view_result'];
	$can_delete 	= $conditions['can_delete_result'];
	$can_approve 	= $conditions['can_approve_result'];
	$can_req_edit 	= $conditions['can_view_req_result'];


	if (!tr_current_role_in(array('administrator', 'lab', 'lab_rep')) && current_user_can('only_rs_final')) {
		$where .= " and re.final_results = 1 ";
	}



	$search = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';

	if (!empty($search) || !empty($_REQUEST['drug'])) {


		if (stripos($search, 'medication:') !== false) {
			$pn = str_replace('medication:', '', $search);
			$where .= $wpdb->prepare(" and re.medications like %s ", '%' . $pn . '%');
		} else if (stripos($search, 'position:') !== false) {
			$pn = str_replace('position:', '', $search);
			$where .= $wpdb->prepare(" and ba.position=%s ", $pn);
		} else if (stripos($search, 'panel:') !== false) {
			$pn = str_replace('panel:', '', $search);
			$pn = trim($pn);
			foreach (explode(',', $pn) as $pn1) {
				$group_ids = $wpdb->get_col("select tg_ID from ci_test_groups where tg_name like '%{$pn1}%' ");
				$wheregroups = array();
				foreach ($group_ids as $gid) {
					$wheregroups[] = "  find_in_set('GROUP_{$gid}', ba.panel_tests_requested  ) ";
				}
				$wheregroups = implode(' or ', $wheregroups);

				$where .= " and ({$wheregroups})";
			}
		} else if (stripos($search, 'test:') !== false) {
			$pn = str_replace('test:', '', $search);
			$pn = trim($pn);
			foreach (explode(',', $pn) as $pn1) {
				$tests = $wpdb->get_results("select te_ID, tg_ID, tg_parent
				from ci_test_panels te
				JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
				where te_name like '%{$pn1}%' ");
				$where_tests = array();
				foreach ($tests as $test) {
					if ($test->tg_parent > 0) {
						$where_tests[] = "  find_in_set('GROUP_{$test->tg_parent}', ba.panel_tests_requested  ) ";
					}
					$where_tests[] = "  find_in_set('{$test->te_ID}', ba.panel_tests_requested  ) ";
				}
				$where_tests = implode(' or ', $where_tests);

				$where .= " and ({$where_tests})";
			}
		} else if (stripos($search, 'labstate:') !== false) {
			$pn = str_replace('labstate:', '', $search);
			$pn = trim($pn);
			$where .= " and ba.patient_id in(select pa_ID from ci_patients where pa_state='{$pn}') ";
			$options     = get_option('ci_send_hub_tests', array());
			$where_tests = array();
			$state_testids = array();
			foreach ($options as $oarr) {
				if (strtoupper($oarr['state']) == strtoupper($pn)) {
					$where_tests[] = "  find_in_set('{$oarr['tid']}', ba.panel_tests_requested  ) ";
					$state_testids[] = $oarr['tid'];
				}
			}
			$where_tests = implode(' or ', $where_tests);

			$where .= " and ({$where_tests})";
			$where .= " and re.has_pos = 1 ";
		} else if (stripos($search, 'state:') !== false) {
			$pn = str_replace('state:', '', $search);
			$pn = trim($pn);
			$where .= " and ba.patient_id in(select pa_ID from ci_patients where pa_state='{$pn}') ";
		} else if (stripos($search, 'reflab:') !== false) {
			$reflabname = str_replace('reflab:', '', $search);
			$ref_lab_id = CI_Base_Lab::getLabIDByKey($reflabname);
			$where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
		} else if ($search == 'rejected') {
			$where .= " and (ba.batch_status like 're\_%' or ba.batch_status='rejected')";
		} else {
			$search_like = '%' . $wpdb->esc_like($search) . '%';

			if (strpos($_REQUEST['s'], ',') > 0) {
				list($firstname, $lastname) = explode(',', $_REQUEST['s']);
				$firstname = trim($firstname);
				$lastname = trim($lastname);
				$explodeString = "%{$firstname}%{$lastname}%";
				$where .= $wpdb->prepare(
					" 
                and (ba.patient_name like %s 
                or ba.requesting_practice like %s
                or ba.requesting_physician like %s)",
					$explodeString,
					$search_like,
					$search_like
				);
			} else {
				$pa_ids = $wpdb->get_col("select pa_ID from ci_patients where pa_mrn = '{$search}'  or pa_ssn = '{$search}' ");
				if (count($pa_ids) > 0) {
					$pa_ids_str = implode(', ', $pa_ids);
					$where .= " and ba.patient_id in ( {$pa_ids_str} )";
				} else {
					$where .= $wpdb->prepare(
						" 
                and (ba.patient_name like %s 
                or ba.assen like %s 
                or ba.requesting_practice like %s
                or ba.requesting_physician like %s)",
						$search_like,
						$search_like,
						$search_like,
						$search_like
					);
				}
			}
		}
	}

	if (isset($_REQUEST['panelid']) && $_REQUEST['panelid'] > 0) {
		$where .= " and find_in_set( 'GROUP_{$_REQUEST['panelid']}', ba.panel_tests_requested )";
	}

	$groups = array();
	$manual_groups = array();
	$sql = "select 
            g.tg_ID,
            g.tg_popup_approve,
            g.tg_popup_verified,
            g.tg_manual_edit,
            g.tg_parent
        from ci_test_groups g
        where g.tg_type = 'pcr' ";

	$rs = $wpdb->get_results($sql);
	foreach ($rs as $r) {
		if (strlen($r->tg_popup_approve) > 10) {
			$groups[$r->tg_ID] = array(
				'approve' => $r->tg_popup_approve,
				'verified' => $r->tg_popup_verified
			);
		}
		if ($r->tg_manual_edit && $r->tg_parent == 0) {
			$manual_groups[] = $r->tg_ID;
		}
	}
	$where_manual_groups = '';
	if (count($manual_groups) > 0) {
		$where_mchils = array();
		foreach ($manual_groups as $gid) {
			$where_mchils[] = " find_in_set( 'GROUP_{$gid}', ba.panel_tests_requested ) ";
		}
		$where_mchils = implode(' or ', $where_mchils);
		$where_manual_groups = " or (re.id is null and ba.batch_status!='archived' and  ({$where_mchils}) ) ";
	}


	if ($pending) {
		$where_orderable = '';
		if (tr_get_option('ci_req_settings_' . $btype, 'e_orderable_test')) {
			$where_orderable = ' or (ba.orderable_test = 1 and re.final_results != 1 )';
			if ($orderable) {
				$where .= " and ba.orderable_test = 1 ";
			}
		}
		$where .= " and (re.id is null or re.estatus not in ('C') or re.estatus is null {$where_orderable}) ";
		//$where .= " and ba.cli_ID > 0  and ba.patient_id > 0 ";
	} else {

		$where .= " and ba.cli_ID > 0 ";
		$where .= " and (
		            re.id > 0 
		            or ( re.id is null and " . tr_pdd_where_batch_rejected() . ") 
		            or (ba.result_pdf != '' and ba.result_pdf is not null and ba.batch_status!='archived')
		            {$where_manual_groups}
		            ) ";
	}


	$more_fields = '';
	$more_joins = '';



	$sql = "select
        re.id,
        re.estatus,
        re.created,
        re.report_ref,
        re.box_uploaded,
        re.approve_date,
        re.users_printed,
        re.upload_files,
        re.verified,
        re.final_results,
        re.faxed,
        re.missingcovid,
        re.has_pos,
        re.covid_pos,
        re.sublab,
        re.instrument,
        re.created_date,
        re.rerun_count,
        re.rerun_modified,
        re.modified,
        re.has_uti,
        
        ba.box_uploaded as ba_box_uploaded,
        ba.doc,
        ba.mid,
        ba.batch_id,
        ba.assen,
        ba.patient_name,
        ba.requesting_practice as clinic,
        ba.requesting_physician as doctor,
        ba.panel_tests_requested,
        ba.type_of_specimen,
        ba.current_medication,
        ba.position,
        ba.current_medication as medications,
        ba.batch_status,
        ba.culture,
		ba.ref_lab_ID,
        
        ba.result_pdf,
        lab.lab_ID,
        lab.lab_dba_init
        {$more_fields}
        
    from ci_batches_pcr ba
    left JOIN ci_reports_pcr re ON ba.assen = re.patient_id
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    where 1 {$where} 
    group by ba.mid 
    {$orderby}";


	$time_start  = microtime(true);
	$results = $wpdb->get_results($sql);
	$count_rows = count($results);



	if (isset($_GET['debug'])) {
		echo '<div>';
		var_dump($wpdb->last_error);
		echo '<br><br>' . "\n\n";
		echo microtime(true) - $time_start;
		echo '<br><br>' . "\n\n";
		echo $sql;
		echo '<br><br>' . "\n\n";
		var_dump($results);
		echo '<br><br>' . "\n\n";
		echo '</div>';
	}

	$last_position = 0;
	$count_rows = 0;
	$count_updated = 0;

	//get plates
	$plates_files = array();
	$approved_ids = array();


	foreach ($results as $i => $row) {
		$mstatus = '';
		$faxed_rejected = '';
		$more_text_estatus = '';
		$approve_by = '';
		$update = array();

		$row->classes = '';
		$row->has_covid = false;
		$row->assen_title = '';
		$row->no_action = false;

		$ref_lab_init = isset($all_labs[$row->ref_lab_ID]) ? $all_labs[$row->ref_lab_ID]->lab_dba_init : '';

		if (in_array($row->assen, $exclude_assens)) {
			continue;
		}

		if (isset($row->culture) && $row->culture) {
			$mstatus = '<ogr>Cu</ogr>';
		}


		if ($row->faxed) {
			$mstatus .= '<span class="ricon faxed" title="Faxed">F</span>';
		}

		if (isset($rejected_status[$row->batch_status])) {
			$mstatus .= '<span class="ricon rejected" title="Rejected">X</span>';
		}

		if ($row->has_uti > 0 && tr_get_option('ci_pdf_data', 'new_abx_woral')) {
			$no_oral_match = trpdd_get_batch_meta($row->mid, 'pcr', '_not_match_oral');
			if ($no_oral_match) {
				$mstatus .= '<span class="triangle_warning" title="No oral antibiotics in ABX results ">!</span>';
			}
		}


		$status_class = '';


		//get groups
		$popup_msg = '';
		$popup_verified = '';
		$panels = array();
		$tests_ids = array();
		$row->groups = array();
		foreach (explode(',', $row->panel_tests_requested) as $t) {
			if (stripos($t, 'group') !== false) {
				$group_id = str_ireplace('group_', '', $t);
				if (isset($groups[$group_id])) {
					$popup_msg = $groups[$group_id]['approve'];
					$popup_verified = $groups[$group_id]['verified'];
				}

				if (isset($all_groups[$group_id])) {
					$panels[] = $all_groups[$group_id]->tg_name;
					$row->groups[] = $all_groups[$group_id];
				}


				if ($covid_group_id == $group_id) {
					$row->has_covid = true;
				}
			} else if ($t > 0) {
				$tests_ids[] = $t;
			}
		}

		if (($verify && (strlen($popup_msg) < 4 || empty($row->upload_files))) && !$pending) {
			continue;
		}

		if ($row->verified && !empty($popup_msg)) {
			$popup_msg = $popup_verified;
		} else if (empty($popup_msg) && isset($result_settings['notfify_approve']) && $result_settings['notfify_approve'] && strlen($row->upload_files) == 0) {
			$popup_msg = 'Sample Data File Missing. <br>Please upload sample data before Approving.';
		}

		if (empty($row->doc) || $row->doc == '0000-00-00 00:00:00') {
			$row->doc = $row->collection_date;
		}
		//insert if no id
		if (!$pending) {
			if ($row->id == 0 && !empty($row->assen) && !empty($row->batch_id) && $row->batch_status != 'hold') {
				$report_data = array(
					'patient_id' => $row->assen,
					'batch_id' => $row->batch_id,
					'created' => date_i18n('Y-m-d H:i:s'),
					'created_date' => $today,
					'sublab' => $row->lab_dba_init,
					'lab_id' => $row->lab_ID
				);
				if (isset($rejected_status[$row->batch_status])) {
					$report_data['estatus'] = 'C';
					$report_data['final_results'] = '1';
					$report_data['approve_date'] = date_i18n('Y-m-d H:i:s');
					$row->estatus = 'C';
					$approve_by = 'Rejected';
				}
				$wpdb->insert('ci_reports_pcr', $report_data);
				$row->id = $wpdb->insert_id;
			} else if (isset($rejected_status[$row->batch_status]) && !in_array($row->estatus, array('C', 'M'))) {
				$update['estatus'] = "C";
				$update['final_results'] = '1';
				$update['approve_date'] = date_i18n('Y-m-d H:i:s');
				$row->estatus = 'C';
				$approve_by = 'Rejected';
			}

			if (empty($row->sublab)) {
				$update['sublab'] =  $row->lab_dba_init;
			}


			if ($row->position == 0) {
				$last_position++;
			} else {
				$last_position = $row->position;
			}

			if ($row->has_covid) {
				$row->odl_covid_pos = $row->covid_pos;

				$arr = trpdd_cal_ncovid19(array(), $row->id, $row->instrument, $row->created_date);
				if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PCH', 'PCL', 'PVH', 'PVL'))) {
					$row->covid_pos = 1;
				} else if ($arr['pos-neg'] == 'RER' || $arr['pos-neg'] == 'INV') {
					$row->covid_pos = 2;
				} else if ($arr['pos-neg'] == 'NEG') {
					$row->covid_pos = 0;
				}

				if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PCH', 'PCL', 'PVH', 'PVL'))) {
					$row->classes .= " has_pos ";
					if ($pcr_cv_file && $arr['cal_posneg'] == 'NEG') {
						$more_text_estatus .= ' <checkmark class="white"></checkmark>';
					}
				} else if ($pcr_cv_file && in_array($arr['cal_posneg'], array('POS', 'POL', 'POH', 'POM', 'PCH', 'PCL', 'PVH', 'PVL'))) {
					$more_text_estatus .= ' <checkmark class="red"></checkmark>';
				}

				if ($row->odl_covid_pos != $row->covid_pos && $row->covid_pos > 0) {
					$update['covid_pos'] = $row->covid_pos;
					if ($row->covid_pos == 2) {
						$update['rerun_count'] = $row->rerun_count + 1;
					}
				}

				if ($pcr_dcvbrerun && $row->rerun_count == 1 && $row->covid_pos == 2) {
					$row->covid_pos = 0;
				}
			} else if (is_null($row->has_pos)) {
				if (empty($tests_ids)) $tests_ids = array('-1');
				$tests_ids_str = implode(',', $tests_ids);
				$sql_has_pos = "select count(*) from ci_results_pcr rs
										JOIN ci_test_panels te ON te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug
										JOIN ci_test_groups tg ON tg.tg_ID = te.te_group_id
	                                    where report_id = {$row->id}
	                                    and `pos-neg` in ('POS','POM','POL','POH','PCH','PCL','PVH','PVL') 
	                                    and te.te_ID in ({$tests_ids_str})
										and te.te_is_control = 0
										and tg.tg_type = 'pcr'
										and tg.tg_antibiotic != 1
	                                    and drug not in ('{$inconclusive_drugs}')";
				if (!empty($tests_in_reflex)) {
					$sql_has_pos .= " and te.te_ID not in ({$tests_in_reflex}) ";
				}
				$row->has_pos = $wpdb->get_var($sql_has_pos);



				if ($row->has_pos) {
					$update['has_pos'] = '1';
					$update['has_pos_modified'] = time();
				}
			}


			if (
				(empty($row->estatus) || !in_array($row->estatus, array('C'))) //, 'M'
				&& count($tests_ids) > 0
				&& !in_array($row->id, $approved_ids)
				&& (strtotime($row->created_date) > $limit_time_auto_approve || $row->modified > $limit_time_auto_approve)
			) {
				//check for auto approve
				$all_neg = true;
				$has_group_neg = false;
				$count_neg = 0;
				$not_neg_by = '';
				foreach ($tests_ids as $tid) {
					if (isset($all_tests[$tid])) {
						$testobj = $all_tests[$tid];
						if ($testobj->te_is_control) continue;

						$group_id = $testobj->tg_parent > 0 ? $testobj->tg_parent : $testobj->te_group_id;


						$arr = $wpdb->get_row("select * from ci_results_pcr rs
												JOIN ci_test_panels te ON te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug
												 where report_id = {$row->id} 
												 and te.te_ID = {$tid}
												and `pos-neg`!='INV'", ARRAY_A);

						if ($arr) {
							if (empty($arr['pos-neg'])) {

								$arr['type_of_specimen'] = $row->type_of_specimen;
								$arr = tr_pdd_parse_pcr_test($arr, $row->instrument, $testobj->te_group_id, $row->created_date);
							}


							if ($arr['pos-neg'] != 'NEG') {
								$not_neg_by = $testobj->te_name . '(' . $testobj->te_ID . ')';
								$all_neg = false;
								if (isset($_GET['dee'])) {
									echo '<div>';
									echo $row->assen;
									var_dump($arr);
									echo '</div>';
									echo '<br><br>';
								}
								break;
							} else if (isset($group_all_negs[$group_id])) {
								$has_group_neg = true;
								$count_neg++;
							}
						} else {
							if (isset($_GET['dee'])) {
								echo '<div>';
								echo $row->assen;
								echo '<br><br>';
								echo '<div>';
								var_dump($drugnames_str);
								echo '</div>';
								echo '</div>';
							}
							//$all_neg = false;
							//break;
						}
					} else {
						$all_neg = false;
						break;
					}
				}

				if (isset($_GET['dee'])) {
					echo '<div>';
					echo $row->assen;
					var_dump($all_neg);
					var_dump($has_group_neg);
					var_dump($count_neg);
					var_dump(count($tests_ids));
					echo '</div>';
					echo '<br><br>';
				}



				if (
					$all_neg && $has_group_neg
					&& ((!empty($row->upload_files) && $count_neg >= count($tests_ids) - 2) || $count_neg >= count($tests_ids))
				) { // && $row->estatus != 'M'
					$update['estatus'] = 'C';
					$update['final_results'] = '1';
					$update['approve_date'] = date_i18n('Y-m-d H:i:s');
					$update['modified'] = time();
					$approve_by = 'All Not-Detected rule';
					$row->estatus = 'C';
				} else if (time() < strtotime('2025-03-30')) {
					//log
					$fl = fopen(WP_CONTENT_DIR . '/log/log_auto_approve.txt', 'a+');
					if ($fl) {
						$logauto = date_i18n('Y-m-d H:i:s') . ": {$row->assen} :\n";
						$logauto .= "$group_id: " . ($group_id) . "\n";
						$logauto .= "all_neg: " . ($all_neg ? 'y' : 'n') . "\n";
						$logauto .= "has_group_neg: " . ($has_group_neg ? 'y' : 'n') . "\n";
						$logauto .= "count_neg: " . $count_neg . "\n";
						$logauto .= "count tests_ids: " . count($tests_ids) . "\n";
						$logauto .= "not neg by: " . $not_neg_by . "\n";
						fwrite($fl, $logauto . "\n\n");
						fclose($fl);
					}
				} else if (file_exists(WP_CONTENT_DIR . '/log/log_auto_approve.txt')) {
					unlink(WP_CONTENT_DIR . '/log/log_auto_approve.txt');
				}
			}

			//check for state
			if (count($state_testids) > 0 && $row->id > 0) {
				$state_testids_str = implode(',', $state_testids);
				$has_state_test_pos = $wpdb->get_var("select count(*) from ci_results_pcr rs
											JOIN ci_test_panels te ON te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug
											 where report_id = {$row->id} 
											 and te.te_ID in({$state_testids_str})
											and `pos-neg` in ('POS','POM','POL','POH','PCH','PCL','PVH','PVL')");

				if (!$has_state_test_pos) {
					echo $wpdb->last_error;
					continue;
				}
			}

			if (isset($result_settings['e_reruntest']) && $result_settings['e_reruntest']) {

				if (is_null($row->rerun_count) || ($row->rerun_modified > 0 && $row->rerun_modified < $row->modified)) {
					//check rerun

					$is_rerun = tr_pdd_check_rerun('pcr', $row);

					if ($is_rerun !== false) {

						if ($row->rerun_count >= 1 && $is_rerun && $row->rerun_modified < $row->modified) {
							$row->rerun_count += 1;
							$update['rerun_count'] = $row->rerun_count;
						} else {
							$update['rerun_count'] = $is_rerun;
							$row->rerun_count = $is_rerun;
						}


						if ($rereun_by_both_rna_fail && $update['rerun_count'] == 1) {
							$sql_has_pos = "select count(*) from ci_results_pcr rs
										JOIN ci_test_panels te ON te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug
	                                    where report_id = {$row->id}
	                                    and `pos-neg` in ('POS','POM','POL','POH','PCH','PCL','PVH','PVL') 
	                                    and te.te_ID in ({$tests_ids_str})
										and te.te_is_control = 0
	                                    and drug not in ('{$inconclusive_drugs}')";

							$has_pos_rnafail = $wpdb->get_var($sql_has_pos);
							if ($has_pos_rnafail) {
								$row->rerun_count = 2;
								$update['rerun_count'] = 2; //allow approve green rerun
							}
						}
					} else {
						$update['rerun_count'] = null;
					}
					$update['rerun_modified'] = time();
				} else if ($row->rerun_count >= 1 && empty($row->rerun_modified)) {
					$update['rerun_modified'] = time();
				}
			}

			if (count($update) > 0) {
				if (isset($update['estatus']) && $update['estatus'] === 'C' && !empty($update['approve_date'])) {
					trpdd_log_user(-1, 'approve_auto_rs', $approve_by, $row->mid, 'pcr', '', '', $row->assen);
					$approved_ids[] = $row->id;
				}

				$wpdb->update('ci_reports_pcr', $update, array('id' => $row->id));
				$count_updated++;
			}

			if ($result_settings['e_listby_plate']) {

				$files = explode(',', $row->upload_files);
				$plates_files = array_merge($plates_files, $files);
			}

			if ($result_settings['reflab_status'] && !empty($row->ref_lab_ID) && isset($all_labs[$row->ref_lab_ID])) {

				if ($row->instrument == $ref_lab_init) {
					$more_text_estatus .= '<span class="textinpos small ' . ($row->estatus != 'C' ? 'red' : 'black') . '">' . $ref_lab_init . '</span>';
				}
			}
		}

		if ($row->covid_pos === '1' || $row->covid_pos === 1) {
			$row->has_pos = true;
		}



		$row->req_link = Tr_Base_Class_V6::link(array('act' => 'edit_req', 'id' => $row->mid), false);
		if ($is_test_results) {
			$row->req_link = Tr_Base_Class_V6::link(array('act' => 'view_req', 'id' => $row->mid), false);
		}

		if ($row->has_pos) {
			$row->classes .= ' has_pos ';
		}

		//check rack4
		if ($allow_rack4s) {
			$row = trpdd_pcr_parse_rack4($row, $results);
		}

		if ($result_settings['list_show_rlab'] && !empty($ref_lab_init)) {

			//check sent
			$sent_req = $wpdb->get_var("select b_status from ci_wsdl_batch where b_mid = {$row->mid} and btype = 'pcr' ");
			if ($sent_req) {
				$row->lab_dba_init = $ref_lab_init;
				$row->rerun_count = 0;
			}
		}

		$count_rows++;
		$row->classes .= ' bs_' . $row->batch_status;

		if (isset($row->need_reload) && $row->need_reload) {
			$need_reload = true;
		}
		ob_start();
	?>
		<tr class="childp <?php echo $row->classes ?>" title="<?php if ($row->batch_status == 'hold') echo 'On Hold' ?>" data-id="<?php echo $row->id ?>" data-popupapprove="<?php echo $popup_msg ?>">
			<td style="width: 40px" class="cid" title="<?php echo $row->mid ?>" data-id="<?php echo $row->id ?>"></td>
			<td style="width: 8%;">
				<a href="<?php echo $row->req_link ?>" title="<?php echo $row->assen_title ?>" target="_blank"><?php echo $row->assen ?></a>
				<?php if (!$is_test_results && !$pending) {
					echo '<div class="row-actions"><div class="actionswrap">';
					echo '<a class="thickbox" title="History ' . $row->assen . '" href="' . Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_history', 'id' => $row->id, 'btype' => 'pcr'), false) . '">History</a>';
					if (current_user_can('ci_custody_chain')) {
						echo ' | <a target="_blank" href="' . Tr_Base_Class_V6::link(array('page' => 'ci_custody_chain', 'assen' => $row->assen), false) . '">Custody Chain</a>';
					}
					echo '</div></div>';
				}
				?>
			</td>
			<?php if (!$is_test_results) { ?>
				<td style="width: 76px;"><?php echo $row->position ?></td>
				<td style="width: 40px;"><?php echo $row->lab_dba_init ?></td>
			<?php } ?>
			<td style="width: 13%;"><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
			<td style="width: 13%;" title="<?php echo $row->doctor ?>"><?php echo stripslashes($row->clinic) ?></td>
			<td style="width: 12%" class="panels"><?php
													foreach ($panels as $panel) {
														echo '<li><span>' . $panel . '</span></li>';
													} ?></td>
			<td style="width: 8%;"><span class="finaltext"><?php echo tr_pdd_get_result_status($row->final_results), '</span><div>', $faxed_rejected, '</div>'; ?></td>
			<td style="width: 80px;"><?php echo tr_pdd_display_date($row->doc, 'Y-m-d') ?></td>
			<?php if ($is_test_results) { ?>
				<td style="width: 10%"><?php echo $row->batch_status ?></td>
			<?php } ?>
			<td style="width: 30px" class="mstatus"><?php echo $mstatus ?></td>
			<?php
			if ($is_test_results) {
				$approve_time = strtotime($row->approve_date);
				if ($row->estatus == 'C') {
					$row->batch_status = 'Approved';
				} else if (!empty($row->result_pdf) && $approve_time <= 0) {
					$row->batch_status = 'Approved';
					$fpath = $result_dir['path'] . '/' . $row->result_pdf;
					if (file_exists($fpath)) {
						$approve_time = filemtime($fpath);
						$row->approve_date = date('Y-m-d H:i:s', $approve_time);
					}
				}

				if ($approve_time > 0) {
					if ($last_login_time < $approve_time) {
						$status_class .= ' new_results ';
					}
				}
				if (!empty($row->internal_notes)) {
					$status_class .= ' revised_results ';
				}
				$users_printed = explode(',', $row->users_printed);
			?>
				<td style="width:70px" class="statuscol2">
					<span title="<?php echo $row->approve_date ?>" class="<?php echo $status_class ?>"><?php echo $more_text_estatus ?></span>
					<span class="linepos"></span>
				</td>
				<td>
					<a class="thickbox" href="?tr_action=ci_view_req&id=<?php echo $row->mid ?>&btype=pcr&width=1000&height=600">View</a>
					- <a class="iprint" data-href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'type' => 'pcr', 'tab' => '')) ?>">Print Req</a>
					<?php
					if (!in_array($row->batch_status, array('hold', 'refused'))) {
						if ($row->estatus == 'C') {
					?>
							- <a class="download_pdf" target="_blank" href="?tr_action=ci_results_pdf&btype=pcr&id=<?php echo $row->id;
																													if ($hide_patient_info) echo '&hidepatient=1';
																													if ($shownewabx) echo '&shownewabx';
																													if ($pdf_browser) echo '&view=1'; ?>">PDF</a>
							- <a class="print_pdf" href="?tr_action=ci_results_pdf&btype=pcr&id=<?php echo $row->id ?>&pdf=test_results">Print</a>
							<?php if (in_array($user_id, $users_printed)) { ?>
								<span class="dashicons dashicons-yes"></span>
							<?php } ?>
						<?php
						} else if (!empty($row->result_pdf)) {
						?>
							- <a href="<?php echo $result_dir['url'] . '/' . $row->result_pdf . '?t=' . time() ?>" target="_blank">PDF</a>
							- <a class="print_pdf" href="?tr_action=ci_results_pdf&btype=pcr&mid=<?php echo $row->mid ?>&print=test_results">Print</a>
							<?php if (in_array($user_id, $users_printed)) { ?>
								<span class="dashicons dashicons-yes"></span>
							<?php } ?>
					<?php
						}
					}

					?>
				</td>
			<?php
			} else {
				$rs_status = $row->estatus;
				if ($row->covid_pos == 2 && $row->estatus !== 'C') {
					$rs_status = 'ReRun';
				} else if ($row->rerun_count >= 1 && $row->estatus !== 'C') {
					$rs_status = 'ReRun';
				}
			?>
				<td style="width: 70px" class="statuscol result_<?php echo $row->estatus . ' ' . $rs_status ?>">
					<?php
					if ($rs_status == 'ReRun') {
						echo '<span class="rerunspan rerurncount_' . $row->rerun_count . '">' . $rs_status . '</span>';
					} else {
						echo $rs_status;
					}
					echo $more_text_estatus ?>
				</td>
				<td class="cactions aright">
					<?php
					if ($pending || $row->no_action) {
						//later
					} else if ($verify) {
						if ($row->estatus == 'C') {
							echo '<red>Approved</red>';
						} else if (!$only_view && $row->covid_pos !== 2) {
							$text_approve = $row->verified ? 'Reverse' : 'Approve';
					?>
							<a class="verifybtn bsty1" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a>
						<?php
						}
					} else if ($row->report_ref > 0) {
						?>
						<a class="bsty3" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $row->id, 'btype' => 'pcr')) ?>">PDF</a>
						<?php
					} else if ($row->estatus != 'Z') {

						$text_approve = $row->estatus == 'C' ? 'Reverse' : 'Approve';
						$show_approve = !$only_view && $row->covid_pos !== 2 && $row->covid_pos !== '2';

						if (!$only_view && $row->rerun_count >= 2) {
							$show_approve = true;
						}
						if ($row->estatus != 'C' && stripos($rs_status, 'rerun') !== false && $row->rerun_count <= 1) {
							$show_approve = false;
						}


						if ($show_approve) {
						?>

							<span class="approvediv" data-rerurn="<?php echo $row->rerun_count ?>">
								<a class="approvebtn bsty4" href="#" data-onlyapprove="<?php echo $only_approve ? 1 : 0 ?>" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a>
							</span>



							<?php if ($show_final_btn && $row->final_results !== '1') { ?>
								<a class="finalbtn bsty4" href="#" data-id="<?php echo $row->id ?>">Final</a>
							<?php } ?>
						<?php
						} else if (!$only_view && $row->covid_pos == 2 && $row->estatus == 'C') {
						?>
							<a class="approvebtn bsty4" data-onlyreverse="1" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a>
						<?php
						}
						?>

						<?php if ($show_view_btn) { ?>
							<span class="viewBtn">
								<a class=" thickbox" title="Quick View <?php echo $row->assen; ?>" href="<?php echo Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_quick_view', 'act' => 'view', 'id' => $row->id, 'btype' => 'pcr', 'width' => 1400, 'height' => 450), false); ?>">View</a>
							</span>
						<?php } ?>

						<span class="pdflink" <?php if ($row->estatus != 'C') echo 'style="display:none"' ?>>
							<a class="bsty2" <?php echo $pdf_browser ? 'target=_blank' : '' ?> href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $row->id, 'btype' => 'pcr'));
																										if ($pdf_browser) echo '&view=1'  ?>">PDF</a>
						</span>

						<?php if (!$only_view) {
							if (
								$can_edit
								&& ($row->covid_pos != 2
									|| ($row->covid_pos == 2 && tr_current_role_in(array('administrator', 'Admin', 'administrator_p', 'AdminCIDW', 'lab')))
								)
							) {
						?>
								<span class="editlink" <?php if ($row->estatus == 'C')
															echo 'style="display:none"' ?>>
									<a class="bsty3" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
								</span>
							<?php
							} else if ($can_view || $can_edit) {
							?>
								<span class="editlink" <?php if ($row->estatus == 'C')
															echo 'style="display:none"' ?>>
									<a class="bsty4" href="<?php Tr_Base_Class_V6::link(array('act' => 'view', 'id' => $row->id)) ?>">View</a>
								</span>
							<?php
							}

							echo '<div class="row-actions">';
							?>

							<?php
							if ($admin_view && $row->estatus == 'C') {
							?>
								<a class="bsty3" target="_blank" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
							<?php
							}
							if ($can_delete && $row->estatus != 'C') {
							?>

								<a class="pdelete bsty6" href="#" data-warning="Both the Req and Result will be Deleted?" data-id="<?php echo $row->id ?>">Delete</a>

							<?php
							}

							echo '</div>';
							?>
						<?php } ?>
					<?php
					} else if ($can_edit) {
					?>
						<a class="bsty3" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
					<?php
					}
					?>
					<?php if ($row->box_uploaded > 0 || $row->ba_box_uploaded > 0) { ?>
						<span class="dashicons dashicons-yes"></span>
					<?php } ?>
				</td>
			<?php
			}
			?>
		</tr>
		<?php
		$row_html = ob_get_clean();
		if ($row->has_pos && $show_pos_top) {
			$rows_top_html_arr[$row->assen] = $row_html;
		} else {
			$rows_html_arr[$row->assen] = $row_html;
		}
	}


	if ($need_reload) {
		echo '<div class="need_reload">Need reload</div>';
		exit;
	}

	echo '<table class="report_tbl child_list" data-total="' . $count_rows . '">';

	if ($result_settings['e_listby_plate'] && count($plates_files) > 0) {
		$plates_files_str = implode("','", $plates_files);
		$plates = $wpdb->get_results("select * 
			from ci_reports_files 
			where rf_file in ('{$plates_files_str}')
			and rf_covid_type = 'PWH' 
			order by rf_ID desc");

		foreach ($plates as $plate) {
			//check && build
			if (is_null($plate->rf_meta_data) || empty($plate->rf_meta_data)) {
				trpdd_pcr_build_WH_plate_pos($plate->rf_ID);
			}
			$assens = explode(',', $plate->rf_assen_list);
		?>
			<tr class="parent_c" data-p="<?php echo 'plate_' . $plate->rf_ID ?>">
				<td style="width: 42px"><i></i></td>
				<td colspan="10"><?php echo $plate->rf_file ?></td>
				<td colspan="2">
					<a title="<?php echo $plate->rf_file ?>" href="<?php Tr_Base_Class_V6::link(array('act' => 'list_plate_qc', 'trajax' => 1, 'rfid' => $plate->rf_ID, 'bid' => $batch_id, 'width' => 1400, 'height' => 500)) ?>" class="thickbox button button-white">QC</a>
				</td>
			</tr>
			<tr class="child_c childc2 <?php echo 'p_plate_' . $plate->rf_ID ?>">
				<td class="nopadding" colspan="13">
					<table class="report_tbl child_list">
						<?php
						foreach ($assens as $assen) {
							$exclude_assens[] = $assen;
							if (isset($rows_top_html_arr[$assen])) {
								echo $rows_top_html_arr[$assen];
							}
							if (isset($rows_html_arr[$assen])) {
								echo $rows_html_arr[$assen];
							}
						}
						?>
					</table>
				</td>
			</tr>
<?php
		}
	}

	foreach ($rows_top_html_arr as $assen => $html) {
		if (!in_array($assen, $exclude_assens)) {
			echo $html;
		}
	}
	foreach ($rows_html_arr as $assen => $html) {
		if (!in_array($assen, $exclude_assens)) {
			echo $html;
		}
	}
	if (count($results) == 0 || $count_rows == 0) {
		echo '<tr class="childp"><td colspan=5><p class="center">Not found</p></td></tr>';
	}

	echo '</table>';

	echo '<div data-countupdated="' . $count_updated . '"></div>';
}
