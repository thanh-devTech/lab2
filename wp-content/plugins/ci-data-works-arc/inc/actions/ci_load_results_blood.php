<?php
global $wpdb, $blog_id, $current_role, $is_ci_admin;

$btype				= 'blood';
$user_id            = get_current_user_id();
$batch_id           = isset($_REQUEST['batch_id']) ? $_REQUEST['batch_id'] : '';
$lab_id             = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : '';
$ref_lab_id         = isset($_REQUEST['ref_lab_id']) ? $_REQUEST['ref_lab_id'] : '';
$verify             = isset($_REQUEST['verify']);
$pending            = isset($_REQUEST['pending']) || (isset($_REQUEST['tab']) && $_REQUEST['tab'] == 'pending');
$orderable      	= isset($_REQUEST['orderable']) ? $_REQUEST['orderable'] : '';
$time_show_dl       = strtotime('2016-05-16');
$today              = Tr_Base_Class_V6::date('Y-m-d');


$admin_view         = isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']) || $is_ci_admin;
$is_test_results    = isset($_GET['page']) && $_GET['page'] == 'ci_test_results';
$last_login         = get_user_meta($user_id, '_last_login', true);
$blood_spcode       = tr_get_option('ci_pdf_data', 'blood_spcode');
$auto_fax           = tr_get_option('ci_pdf_data', 'auto_fax_approve_blood');
$blood_settings     = get_option('ci_req_settings_blood', array());

$result_dir         = tr_get_result_upload_dir();
$rejected_status    = tr_pdd_get_batch_statuses(false, true, true, 'blood');
$plate_coating 		= tr_get_option('ci_pdf_data', 'plate_coating');
$show_final_btn          = tr_get_option('ci_pdf_data', 'rs_show_finalbtn');
$show_view_btn          = tr_get_option('ci_pdf_data', 'rs_show_viewbtn');
$pdf_browser            = tr_get_option('ci_pdf_data', 'pdf_browser', true) || $admin_view;

if (empty($last_login)) {
	$last_login = date('Y-m-d H:i:s', strtotime('-2days'));
}
$last_login_time = strtotime($last_login);

$limit_time_auto_approve = time() - 86400;
$all_tests = array();
$all_drugs = array();
$all_groups = array();
$group_all_negs = array();

$rs = $wpdb->get_results("select te_ID, 
                        te_name, 
						te_abrev,
                        te_group_id,
                        tg_name,
						tg_only_group,
						tg_notestcheckbox,
						tg_is_covid,
						tg_auto_approve_neg

                        from ci_test_panels te
                         join ci_test_groups tg ON tg.tg_ID = te.te_group_id
                         where te_type='blood' ");
foreach ($rs as $r) {
	$r->drug = strtoupper($r->te_name);
	$r->te_abrev = strtoupper($r->te_abrev);

	$all_tests[$r->te_ID] = $r;
	$all_groups[$r->te_group_id] = $r->tg_name;

	if ($r->tg_auto_approve_neg) {
		if (!isset($group_all_negs[$r->te_group_id])) {
			$group_all_negs[$r->te_group_id] = 0;
		}

		$group_all_negs[$r->te_group_id]++;
	}

	$all_drugs[$r->drug] = $r->te_ID;
	if (!empty($r->te_abrev)) {
		$all_drugs[$r->te_abrev] = $r->te_ID;
	}


	if ($r->tg_is_covid == 1 || $r->tg_only_group || $r->tg_notestcheckbox) {
		if (!isset($all_tests['group_' . $r->te_group_id])) {
			$r->tests = array();
			$all_tests['group_' . $r->te_group_id] = $r;
		}
		$all_tests['group_' . $r->te_group_id]->tests[] = $r;
	}
}



$where = " and ba.batch_id = '{$batch_id}' ";
$where .= " and ba.batch_status != 'archived' ";
if ($ref_lab_id > 0) {
	$where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
} else if ($lab_id > 0) {
	$where .= " and (lab.lab_ID = {$lab_id} or ba.lab_id = {$lab_id} or ba.ref_lab_ID={$lab_id}) and (re.report_ref = 0 or re.id is null) ";
} else if (CI_Base_Lab::has_user_labs()) {
	$lab_str = CI_Base_Lab::get_user_lab_ids_str();
	$where .= " and (ba.lab_id in ({$lab_str})  or ba.ref_lab_ID in ({$lab_str}) )";
}

$orderby = ' order by ba.position asc';
$order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$joins = '';


if ($pending) {
	$where_orderable = '';
	if (tr_get_option('ci_req_settings_' . $btype, 'e_orderable_test')) {
		$where_orderable = ' or (ba.orderable_test = 1 and re.final_results != 1 )';
		if ($orderable) {
			$where .= " and ba.orderable_test = 1 ";
		}
	}
	//$where .= " and ba.cli_ID > 0  and ba.patient_id > 0 ";
	$where .= " and (
	                    re.id is null 
	                or (
	                    (re.estatus is null or re.estatus!='C')
	                    and re.is_pending = 1
	                    )
						{$where_orderable}
	                )  ";

	if ($plate_coating) {
		$where .= " and ba.mid not in (
        select mid from ci_rack_batches rb  
        JOIN ci_racks ra ON ra.re_ID = rb.re_ID
        JOIN ci_plate_coating pc ON ra.re_barcode = pc.barcode
        where rb.btype='blood' and pc.type IN ('VPV', 'VT', 'PT')
        ) ";
	}
} else {
	$where .= " and (
               re.id > 0 
            or ( re.id is null and " . tr_pdd_where_batch_rejected() . ") 
            or (ba.result_pdf != '' and ba.result_pdf is not null and ba.batch_status!='archived')
        ) ";
}


if (isset($_REQUEST['panelid']) && $_REQUEST['panelid'] > 0) {
	$where .= " and find_in_set( 'GROUP_{$_REQUEST['panelid']}', ba.panel_tests_requested )";
}


$conditions = trpdd_get_batch_conditions(0, 'blood');
$where      .=  $conditions['where'];

$only_view 		= $conditions['only_view_result'];
$can_delete 	= $conditions['can_delete_result'];
$can_approve 	= $conditions['can_approve_result'];
$can_req_edit 	= $conditions['can_view_req_result'];

if ($pending) {
	$can_approve = false;
	$can_delete = false;
}
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
	} else if (stripos($search, 'test:') !== false) {
		$pn = str_replace('test:', '', $search);
		$pn = trim($pn);
		$test_ids = $wpdb->get_col("select te_ID from ci_test_panels where te_name like '%{$pn}%' ");
		$wheretests = array();
		foreach ($test_ids as $tid) {
			$wheretests[] = "  find_in_set('{$tid}', ba.panel_tests_requested  ) ";
		}
		$wheretests = implode(' or ', $wheretests);
		$where .= " and ({$wheretests})";
	} else if (stripos($search, 'panel:') !== false) {
		$pn = str_replace('panel:', '', $search);
		$pn = trim($pn);
		$group_ids = $wpdb->get_col("select tg_ID from ci_test_groups where tg_name like '{$pn}%' ");
		$wheregroups = array();

		foreach ($group_ids as $gid) {
			$wheregroups[] = "  find_in_set('GROUP_{$gid}', ba.panel_tests_requested  ) ";
		}
		$wheregroups = implode(' or ', $wheregroups);
		$where .= " and ({$wheregroups})";
	} else if (stripos($search, 'reflab:') !== false) {
		$reflabname = str_replace('reflab:', '', $search);
		$ref_lab_id = CI_Base_Lab::getLabIDByKey($reflabname);
		$where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
	} else if ($search == 'rejected') {
		$where .= " and (ba.batch_status like 're\_%' or ba.batch_status='rejected')";
	} else {
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
				$search . '%',
				$search . '%'
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
					$search . '%',
					$search . '%',
					$search . '%',
					$search . '%'
				);
			}
		}
	}
}

$groups = array();

$sql = "select 
            g.tg_ID,
            g.tg_popup_approve,
            g.tg_popup_verified
        from ci_test_groups g
        where g.tg_type = 'blood' ";

$rs = $wpdb->get_results($sql);
foreach ($rs as $r) {
	if (strlen($r->tg_popup_approve) > 10) {
		$groups[$r->tg_ID] = array(
			'approve' => $r->tg_popup_approve,
			'verified' => $r->tg_popup_verified
		);
	}
}

$adding_fields = '';
$joins = '';

if ($blood_spcode) {
	$adding_fields .= ',pa.pa_code';
}

if ($pending) {
	$adding_fields .= ', (select group_concat(rs.drug) from ci_results_blood rs 
	                        where rs.report_id = re.id 
							and (rs.conc!=\'\' or rs.`pos-neg`!=\'\')
							group by rs.report_id) as resulted_tests ';
}


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
        re.instrument,
        re.faxed,
        re.has_pos,
        re.covid_pos,
        re.created_date,
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
        ba.result_pdf,
        ba.notes_report,
        ba.batch_status,
        ba.doc_ID,
        lab.lab_ID,
        lab.lab_dba_init,
        pa.pa_gender,
        pa.pa_dob
        {$adding_fields}
    from ci_batches_blood ba
    left JOIN ci_reports_blood re ON ba.assen = re.patient_id
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    left join ci_patients pa ON pa.pa_ID = ba.patient_id 
    {$joins}
    where 1 {$where} group by ba.mid {$orderby}";





$time_start  = microtime(true);
$results = $wpdb->get_results($sql);
$count_rows = count($results);

if (isset($_GET['debug1'])) {
	echo '<div>';
	var_dump($wpdb->last_error);
	echo '<br><br>' . "\n\n";
	echo microtime(true) - $time_start;
	echo '<br><br>' . "\n\n";
	echo $sql;
	echo '<br><br>' . "\n\n";
	echo '</div>';
}

echo '<table class="report_tbl child_list" data-total="' . $count_rows . '">';

$last_position = 0;
$count_rows = 0;
$approved_ids = array();

foreach ($results as $i => $row) {

	$update = array();
	$mstatus = '';
	$status_class = '';
	$row_class = '';


	if ($row->faxed) {
		$mstatus .= '<span class="ricon faxed" title="Faxed">F</span>';
	}

	if (isset($_GET['resetcache']) && strtotime($row->doc) > strtotime($_GET['resetcache'])) {
		$dirpath = WP_CONTENT_DIR . '/results/html/' . strtolower($row->assen);
		$files = scandir($dirpath);
		foreach ($files as $f) {
			unlink($dirpath . '/' . $f);
		}
	}


	//get groups
	$approve_by = '';
	$popup_msg = '';
	$popup_verified = '';
	$tests_ids = array();
	$missing_tests = array();
	$missing_panels = array();
	$panels =  array();
	foreach (explode(',', $row->panel_tests_requested) as $t) {
		if (stripos($t, 'group') !== false) {
			$group_id = str_ireplace('group_', '', $t);
			if (isset($groups[$group_id])) {
				$popup_msg = $groups[$group_id]['approve'];
				$popup_verified = $groups[$group_id]['verified'];
			}

			if (isset($all_groups[$group_id])) {
				$panels[$group_id] = array(
					'name' => $all_groups[$group_id]
				);
			}

			if (isset($all_tests['group_' . $group_id])) { //only show group, no test

				$missing_tests['group_' . $group_id] = $group_id;
				foreach ($all_tests['group_' . $group_id]->tests as $tobj) {
					$missing_tests[$tobj->te_ID] = $tobj->te_ID;
				}
			}
		} else {
			$tests_ids[] = $t;

			if (isset($all_tests[$t])) {
				$missing_tests[$t] = $t;
			}
		}
	}


	if ($pending) {

		$group_has_rs = array();
		//search tests pending
		if ($row->id > 0) {
			$resulted_tests = explode(',', $row->resulted_tests);

			foreach ($resulted_tests as $rs_test) {
				$rs_test = strtoupper($rs_test);

				if (isset($all_drugs[$rs_test])) {
					$teid = $all_drugs[$rs_test];
					$gid = $all_tests[$teid]->te_group_id;

					if (isset($missing_tests[$teid])) {
						$group_has_rs[] = $gid;
						unset($missing_tests[$teid]);
						//unset($missing_tests['group_'.$gid]);
					} else {
						//unset($missing_tests['group_'.$gid]);
					}
				}
			}
		}


		foreach ($missing_tests as $drug => $tid) {

			$gid = $tid;
			$test = false;
			if (stripos($drug, 'group_') === false) {
				$test = $all_tests[$tid];
				$gid = $test->te_group_id;
			}

			if (isset($panels[$gid])) {
				if (!isset($missing_panels[$gid])) {
					$missing_panels[$gid] = $panels[$gid];
				}

				if ($test) {
					$missing_panels[$gid]['missing_tests'] .= $test->te_name . "\n";
				}


				if (in_array($gid, $group_has_rs)) {
					$missing_panels[$gid]['has_rs'] = true;
				}
			}
		}



		if (count($missing_panels) == 0) {
			if (in_array($row->batch_status, array('re_prabrp', 're_prabre'))) {
				$missing_panels['status'] = array(
					'name' => 'Status:' . tr_pdd_get_batch_statuses($row->batch_status)
				);
			} else if (!empty($row->panel_tests_requested) && $_REQUEST['stab'] != 'cu_ot') {
				if (isset($_GET['debug1'])) {
					echo '<div>by missing panel</div>';
				}
				//continue;
			}
		}

		$panels = $missing_panels;
	} else {

		if ($row->verified && !empty($popup_msg)) {
			$popup_msg = $popup_verified;
		}

		if (empty($row->doc) || $row->doc == '0000-00-00 00:00:00') {
			$row->doc = $row->collection_date;
		}



		$auto_approve = false;
		if (isset($blood_settings['auto_approve_rs']) && $blood_settings['auto_approve_rs'] && !empty($row->result_pdf)) {
			$auto_approve = true;
		}


		//insert if no id
		if ($row->id == 0 && !empty($row->assen) && !empty($row->batch_id)) {
			$report_data = array(
				'patient_id' => $row->assen,
				'batch_id' => $row->batch_id,
				'created' => date_i18n('Y-m-d H:i:s'),
				'created_date' => $today,
				'sublab' => $row->lab_dba_init,
				'lab_id' => $row->lab_ID
			);
			$wpdb->insert('ci_reports_blood', $report_data);
			$row->id = $wpdb->insert_id;
		}

		if (
			($auto_approve || isset($rejected_status[$row->batch_status]))
			&& !in_array($row->estatus, array('C', 'M'))
		) {
			$update['final_results'] = '1';
			$update['estatus']      = 'C';
			$update['approve_date'] = date_i18n('Y-m-d H:i:s');
			$row->estatus           = 'C';
			$approve_by				= 'Rejected';
		}
	}


	if (
		!in_array($row->estatus, array('C', 'M'))
		&& count($tests_ids) > 0
		&& !in_array($row->id, $approved_ids)
		&& strtotime($row->created_date) > $limit_time_auto_approve
	) {
		//check for auto approve
		$all_neg = true;
		$has_group_neg = false;
		foreach ($tests_ids as $tid) {
			if (isset($all_tests[$tid])) {
				$testobj = $all_tests[$tid];
				$drugname = $testobj->te_name;
				$group_id = $testobj->te_group_id;


				$arr = $wpdb->get_row("select * from ci_results_blood where report_id = {$row->id} 
                                        and drug = '{$drugname}' and `pos-neg`!='INV'", ARRAY_A);
				if ($arr) {
					if (empty($arr['pos-neg'])) {
						$arr = tr_pdd_parse_blood_test($arr, $row->instrument, $group_id, array(
							'pa_dob' => $row->pa_dob,
							'pa_gender' => $row->pa_gender,
							'doc' => $row->doc
						));
					}
					if ($arr['pos-neg'] != 'NEG') {
						$all_neg = false;
						break;
					} else if (isset($group_all_negs[$group_id])) {
						$has_group_neg = true;
					}
				} else {
					$all_neg = false;
					break;
				}
			} else {
				$all_neg = false;
				break;
			}
		}

		if ($all_neg && $has_group_neg && $row->estatus != 'M') {
			$update['estatus'] = 'C';
			$update['final_results'] = '1';
			$update['approve_date'] = date_i18n('Y-m-d H:i:s');
			$update['modified'] = time();
			$approve_by = 'All Not-Detected rule';

			$row->estatus = 'C';
		}
	}

	if (is_null($row->covid_pos) || $row->covid_pos === '') {
		$covid_drugs = array();
		foreach ($tests_ids as $tid) {
			if (isset($all_tests[$tid])) {
				$testobj  = $all_tests[$tid];
				$drugname = $testobj->te_name;
				if (stripos($drugname, 'cov-2') !== false || stripos($drugname, 'covid') !== false) {
					$covid_drugs[] = $drugname;
				}
			}
		}
		if (count($covid_drugs) > 0) {
			$covid_drugs = implode("','", $covid_drugs);
			$row->covid_pos = $wpdb->get_var("select count(*) from ci_results_blood 
		                            where report_id = {$row->id} 
	                                and `pos-neg` in( 'POS' ,'POH','POL','POM','PCL','PCH','PVH','PVL') 
	                                and drug in ('{$covid_drugs}')");

			$row->covid_pos = $row->covid_pos > 0 ? 1 : 0;
			$update['covid_pos'] = $row->covid_pos;
		}
	}
	if (count($update) > 0) {
		if (isset($update['estatus']) && $update['estatus'] === 'C' && !empty($update['approve_date'])) {
			trpdd_log_user(0, 'approve_auto_rs', $approve_by, $row->mid, 'blood', '', '', $row->assen);
			$approved_ids[] = $row->id;

			if ($auto_fax && !isset($rejected_status[$row->batch_status])) {
				trpdd_add_auto_fax((array) $row, 'blood');
				if (empty($row->batch_status) || $row->batch_status == 'received') {
					$wpdb->update('ci_batches_blood', array('batch_status' => 'completed'), array('mid' => $row->mid));
				}
			}
		}

		$wpdb->update('ci_reports_blood', $update, array('id' => $row->id));
	}

	if ($row->position == 0) {
		$last_position++;
	} else {
		$last_position = $row->position;
	}

	$text_approve = $row->estatus == 'C' ? 'Reverse' : 'Approve';
	$count_rows++;
	$row_class .= ' bs_' . $row->batch_status;

	if ($row->batch_status == 're_inco') {
		$mstatus .= '<span class="ricon inconclusive" title="Inconclusive">!</span>';
	} else if (isset($rejected_status[$row->batch_status])) {
		$mstatus .= '<span class="ricon rejected" title="Rejected">X</span>';
	} else if ($row->covid_pos == -2) {
		$mstatus .= '<span class=" rerun">Rerun</span>';
	}

	if ($row->covid_pos == 1 || $row->has_pos > 0) {
		$row_class .= ' has_pos ';
		$row_class .= ' has_pos_' . $row->has_pos;
	}

	$req_link = Tr_Base_Class_V6::link(array('act' => $is_test_results ? 'view_req' : 'edit_req', 'id' => $row->mid), false);

	//fix doctor name
	if (strpos($row->doctor, ',') === false && strpos($row->doctor, ' ') > 0 && $row->doc_ID > 0) {
		$doctor = $wpdb->get_row("select doc_first, doc_last from ci_doc where doc_ID = {$row->doc_ID} ");
		if ($doctor) {
			$row->doctor = $doctor->doc_last . ', ' . $doctor->doc_first;
			$wpdb->update('ci_batches_blood', array('requesting_physician' => $row->doctor), array('mid' => $row->mid));
		}
	}

?>
	<tr class="childp  <?php echo $row_class ?>" title="<?php if ($row->batch_status == 'hold') echo 'On Hold' ?>" data-id="<?php echo $row->id ?>" data-popupapprove="<?php echo $popup_msg ?>">
		<td style="width: 7%;" class="cid" title="<?php echo $row->mid ?>" data-id="<?php echo $row->id ?>"><?php echo $row->position ?></td>
		<td style="width: 8%;">
			<?php if ($can_req_edit) { ?>
				<a href="<?php echo $req_link ?>" target="_blank"><?php echo $row->assen ?></a>
			<?php
			} else {
				echo $row->assen;
			}
			if ($can_req_edit && !$pending) {
				echo '<div class="row-actions"><div class="actionswrap">';
				echo '<a class="thickbox" title="History" href="' . Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_history', 'id' => $row->id, 'btype' => 'blood'), false) . '">History</a>';
				if (current_user_can('ci_custody_chain')) {
					echo ' | <a target="_blank" href="' . Tr_Base_Class_V6::link(array('page' => 'ci_custody_chain', 'assen' => $row->assen), false) . '">Custody Chain</a>';
				}
				echo '</div></div>';
			}
			?>
		</td>

		<td style="width: 16%;"><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
		<td style="width: 18%;"><?php echo stripslashes($row->clinic) ?></td>
		<td style="width: 12%"><?php echo $row->doctor ?></td>
		<?php if ($blood_spcode) { ?>
			<td style="width: 8%"><?php echo $row->pa_code ?></td>
		<?php } ?>
		<td style="width: 80px;"><?php echo tr_pdd_display_date($row->doc, 'Y-m-d') ?></td>
		<?php
		if ($pending) {
		?>
			<td style="width: 20%;" class="panels">
				<?php
				foreach ($panels as $groupid => $arr) {
					$class = '';
					if (isset($arr['has_rs']) && $arr['has_rs']) {
						$class .= ' has_rs ';
					}

					if (!empty($arr['missing_tests'])) {
						$class .= ' has_missing_tests ';
					} else {
						$class .= ' resulted ';
					}
					echo '<li class="' . $class . '" title="' . $arr['missing_tests'] . '"><span>' . $arr['name'] . '</span>';
					if (!empty($arr['missing_tests'])) {
						echo '<ul class="tests">';
						$mssintests = explode("\n", $arr['missing_tests']);
						foreach ($mssintests as $t) {
							echo '<li>' . $t . '</li>';
						}
						echo '</ul>';
					}
					echo '</li>';
				} ?>
			</td>
		<?php

		} else {
		?>
			<td style="width: 72px;"><?php echo tr_pdd_get_result_status($row->final_results) ?></td>
		<?php
		}
		?>
		<?php if ($is_test_results) { ?>
			<td style="width: 10%"><?php echo $row->batch_status ?></td>
		<?php } ?>
		<td style="width: 42px" class="mstatus"><?php echo $mstatus ?></td>
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
			if (!empty($row->notes_report)) {
				$status_class .= ' revised_results ';
			}
			$users_printed = explode(',', $row->users_printed);
		?>
			<td style="width:30px" class="statuscol2">
				<span title="<?php echo $row->approve_date ?>" class="<?php echo $status_class ?>"></span>
				<span class="linepos"></span>
			</td>
			<td class="cactions aright">
				<a class="thickbox" href="?tr_action=ci_view_req&id=<?php echo $row->mid ?>&btype=blood&width=1000&height=600">View</a>
				<a class="iprint" data-href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_print_req', 'id' => $row->mid, 'type' => 'blood', 'tab' => '')) ?>">Print Req</a>
				<?php
				if ($row->estatus == 'C' || !empty(!empty($row->result_pdf))) {
				?>
					<a class="bsty2 download_pdf" target="_blank" href="?tr_action=ci_results_pdf&btype=blood&id=<?php echo $row->id;
																													if ($admin_view) echo '&view=1&pdf=test_results'; ?>">PDF</a>
					<a class="print_pdf bsty3" href="?tr_action=ci_results_pdf&btype=blood&id=<?php echo $row->id ?>&print=test_results">Print</a>
					<?php if (in_array($user_id, $users_printed)) { ?>
						<span class="dashicons dashicons-yes"></span>
					<?php } ?>
				<?php
				}
				?>
			</td>
		<?php
		} else {
		?>
			<td style="width: 30px" title="<?php echo $row->approve_date ?>" class="statuscol result_<?php echo $row->estatus ?>"><?php echo $row->estatus ?></td>
			<td class="cactions aright">
				<?php
				if ($verify) {
					if ($row->estatus == 'C') {
						echo '<red>Approved</red>';
					} else {
						$text_approve = $row->verified ? 'Reverse' : 'Approve';
				?>
						<a class="verifybtn bsty5" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a>
					<?php
					}
				} else if ($row->report_ref > 0) {
					?>
					<a class="bsty2" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $row->id));
											if ($pdf_browser) echo '&view=1'; ?>">PDF</a>
					<?php
				} else if (!empty($row->result_pdf) && empty($row->upload_files)) {
					if ($can_approve) { ?>

						<span class="approvediv" <?php if ($only_view) echo 'style="display:none"' ?>><a class="approvebtn bsty5" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a></span>

						<?php if ($show_final_btn && $row->final_results !== '1') { ?>
							<a class="finalbtn bsty4" href="#" data-id="<?php echo $row->id ?>">Final</a>
						<?php } ?>
					<?php } ?>
					<?php if ($show_view_btn) { ?>
						<span class="viewBtn"><a class="thickbox" title="Quick View <?php echo $row->assen; ?>" href="<?php echo Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_quick_view', 'act' => 'view', 'id' => $row->id, 'btype' => 'blood', 'width' => 1400, 'height' => 450), false); ?>">View</a></span>
					<?php } ?>
					<a class="bsty2" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $row->id, 'btype' => 'blood'));
											if ($pdf_browser) echo '&view=1'; ?>">PDF</a>
					<span class="editlink" <?php if ($row->estatus == 'C' || $only_view) echo 'style="display:none"' ?>>
						<a class="bsty3" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
					</span>
					<?php
				} else if ($row->estatus != 'Z') {
					if ($can_approve) {
					?>

						<span class="approvediv" <?php if ($only_view) echo 'style="display:none"' ?>><a class="approvebtn bsty5" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a></span>

						<?php if ($show_final_btn && $row->final_results !== '1') { ?>
							<a class="finalbtn bsty4" href="#" data-id="<?php echo $row->id ?>">Final</a>
						<?php } ?>
					<?php } ?>
					<?php if ($show_view_btn) { ?>
						<span class="viewBtn"><a class="thickbox" title="Quick View <?php echo $row->assen; ?>" href="<?php echo Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_quick_view', 'act' => 'view', 'id' => $row->id, 'btype' => 'blood', 'width' => 1400, 'height' => 450), false); ?>">View</a></span>
					<?php } ?>
					<span class="pdflink" <?php if ($row->estatus != 'C') echo 'style="display:none"' ?>>
						<a class="bsty2" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $row->id, 'btype' => 'blood'));
												if ($pdf_browser) echo '&view=1'; ?>">PDF</a>
					</span>

					<span class="editlink" <?php if (($row->estatus == 'C' || $only_view) && !$admin_view) echo 'style="display:none"' ?>>
						<a class="bsty3" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
					</span>
					<?php if ($can_delete) { ?>
						<a class="pdelete bsty6" <?php if ($only_view) echo 'style="display:none"' ?> href="#" data-id="<?php echo $row->id ?>">Delete</a>
					<?php
					}
				} else if (!$only_view) {
					?>
					<a class="bsty2" href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
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

}

if (count($results) == 0 || $count_rows == 0) {
	echo '<tr class="childp"><td colspan=5><p class="center">Not found</p></td></tr>';
}

echo '</table>';
