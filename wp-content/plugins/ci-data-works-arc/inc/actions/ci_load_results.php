<?php
global $wpdb, $blog_id, $current_role, $is_ci_admin;

$time_start         = microtime(true);
$count_check_reflex = 0;
$count_updated      = 0;

$user_id            = get_current_user_id();
$batch_id           = @$_REQUEST['batch_id'];
$lab_id             = @$_REQUEST['lab_id'];
$ref_lab_id         = @$_REQUEST['ref_lab_id'];
$time_show_dl       = strtotime('2016-05-16');
$today              = date_i18n('Y-m-d');
$confirmation_pending = isset($_REQUEST['confirmation_pending']);
$rejected_status    = tr_pdd_where_batch_rejected(true);
$admin_view         = isset($_REQUEST['view']) || isset($_COOKIE['trquery_monitor']) || $is_ci_admin;
$can_approve        = !tr_get_option('ci_result_settings_tox', 'hide_approve', false);
$show_view_btn      = tr_get_option('ci_pdf_data', 'rs_show_viewbtn');
$pdf_browser        = tr_get_option('ci_pdf_data', 'pdf_browser', true) || $admin_view;
$is_test_results    = $is_test_results ?? false;


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
                         where te_type='tox' and te.te_in_req = 1");
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
}

$tests_alcohols     = $wpdb->get_col("select te_name 
                    from ci_test_panels t 
                    JOIN ci_test_groups g ON g.tg_ID = t.te_group_id 
                                and g.tg_name = 'Alcohol'");
$tests_alcohols_str = implode("','", $tests_alcohols);
$adding_fields  = '';
$where          = " and ba.batch_id = '{$batch_id}' ";
$where .= " and ba.batch_status != 'archived' ";
if ($ref_lab_id > 0) {
    $where .= " and ba.ref_lab_ID = {$ref_lab_id} ";
} else {
    if ($lab_id > 0) {
        $where .= " and (lab.lab_ID = {$lab_id} or ba.lab_id = {$lab_id} or ba.ref_lab_ID={$lab_id})  ";
        //and (re.report_ref = 0 or re.id is null)
    } else if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $where .= " and (ba.lab_id in ({$lab_str})  or ba.ref_lab_ID in ({$lab_str}) )";
    }
}


$orderby = ' order by has_met_pos desc, ba.position asc';
$order = @$_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$joins = '';


if ($confirmation_pending) {
    $where .= " and (re.id is null or re.estatus !='C' or re.estatus is null )  and ba.cli_ID > 0  and ba.patient_id > 0 ";
    //$joins .= " join ci_result_test_speciments ts ON ts.mid = ba.mid and ts.conc!='' ";
} else {
    $where .= " and (re.id > 0 or  ( re.id is null and (" . tr_pdd_where_batch_rejected() . " or ba.batch_status = 'resulted' or ba.only_screening = 1)) ) ";
}

$where .= " and ba.cli_ID > 0 ";

if (tr_current_role_in(array('office_manager')) || $current_role == 'clinic') {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    } else {
        if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
            $cid = get_user_meta($user_id, '_clinic_id', true);
            $cli_ids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");
            $cli_ids = implode(',', $cli_ids);

            $where .= " and ba.cli_ID in ( {$cli_ids} )";
        }
    }
} else if (tr_current_role_in(array('multi_site_manager'))) {

    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    } else  if (tr_get_option('ci_pdf_data', 'msm_can_show_all')) {
        //can see all clinics
    } else if (!tr_get_option('ci_pdf_data', 'off_see_all') || get_user_meta(get_current_user_id(), '_only_one_clinic', true)) {
        $ms_cli_ids = get_user_meta($user_id, '_ms_clinic_ids', true);
        if (!is_array($ms_cli_ids) || empty($ms_cli_ids)) {
            $ms_cli_ids = $wpdb->get_col("select cID from ci_clinic where cli_status = 1 ");
            update_user_meta($user_id, '_ms_clinic_ids', $ms_cli_ids);
        }
        $ms_cli_ids_str = implode(',', $ms_cli_ids);
        $where .= " and ba.cli_ID in ( {$ms_cli_ids_str} )";
    }
} else if ($current_role == 'doctor') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }
    $doc_id = get_user_meta($user_id, '_doc_id', true);
    if (!tr_get_option('ci_pdf_data', 'doctor_see_all')) {
        $where .= " and ba.doc_ID = {$doc_id} ";
    }
} else if ($current_role == 'lab_rep' || $current_role == 'lab_rep_w2') {
    $show_doctor = true;
    $rep_id = get_user_meta($user_id, '_rep_id', true);
    $clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` = {$rep_id}");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in  ({$clinics}) ";
} else if ($current_role == 'distributor') {
    $dis_ID = $wpdb->get_var("SELECT dis_ID FROM `ci_distributors` WHERE `dis_user_ID`=$user_id ");
    $reps = $wpdb->get_col("SELECT rep.rep_ID FROM ci_lab_reps rep
                            left JOIN `ci_marketing_groups` mg ON mg.gr_ID=rep.rep_marketing_group_ID
                             where (mg.`gr_distributor_ID`= {$dis_ID})
                             or (rep.`rep_distributor_ID`={$dis_ID} and rep.`rep_marketing_group_ID`='0') ");


    $reps = implode(',', $reps);
    $clinics = $wpdb->get_col("select cli.cID from ci_clinic cli where cli.`cli_rep-ID` in ($reps)");
    $clinics = implode(',', $clinics);
    $where .= " and ba.cli_ID in ({$clinics}) ";
} else if ($current_role == 'collector') {
    $current_clinic = get_user_meta($user_id, '_use_current_clinic_id', true);
    if ($current_clinic > 0) {
        $where .= " and ba.cli_ID = {$current_clinic} ";
    }
    $col_id = get_user_meta($user_id, '_collector_id', true);
    if (!tr_get_option('ci_pdf_data', 'collector_see_all')) {
        $where .= " and ba.col_ID = {$col_id} ";
    }
    $check_author = false;
}


if (!tr_current_role_in(array('administrator', 'lab', 'lab_rep')) && current_user_can('only_rs_final')) {
    $where .= " and re.final_results = 1 ";
}

$search = isset($_REQUEST['s']) ? trim($_REQUEST['s']) : '';

if (!empty($search) || !empty($_REQUEST['drug'])) {
    if (@$_REQUEST['drug'] == 'Alcohol') {
        $where_al = array();
        foreach ($tests_alcohols as $t) {
            $where_al[] = " ba.panel_tests_requested like '%{$t}%' ";
        }
        $where_al = implode(' or ', $where_al);
        $where .= " and ({$where_al}) ";
    } else if (@$_REQUEST['drug'] == 'Methamphetamine DL') {
        $where .= " and (ba.type_of_specimen not like 'oral%' or ( ba.type_of_specimen like 'oral%' and re.created > {$time_show_dl})) and rs.id > 0 ";
    }


    if (stripos($search, 'medication:') !== false) {
        $pn = str_replace('medication:', '', $search);
        $where .= $wpdb->prepare(" and re.medications like %s ", '%' . $pn . '%');
    } else if (stripos($search, 'position:') !== false) {
        $pn = str_replace('position:', '', $search);
        $where .= $wpdb->prepare(" and ba.position=%s ", $pn);
    } else if (stripos($search, 'panel:') !== false) {
        $pn = str_replace('panel:', '', $search);
        $pn = trim($pn);
        if ($pn == 'screening only') {
            $where .= " and ba.only_screening = '1' ";
        } else  if ($pn == 'screening') {
            $where .= " and ba.has_screening = '1' ";
        } else {
            $testnames = $wpdb->get_col("select te_name from ci_test_panels t
            JOIn ci_test_groups g ON g.tg_ID = t.te_group_id
            where g.tg_name like '%{$pn}%'");
            $wherechilds = array();
            foreach ($testnames as $tname) {
                $wherechilds[] = " find_in_set('{$tname}' , ba.panel_tests_requested ) ";
            }
            $wherechilds = implode(" or ", $wherechilds);
            if (!empty($wherechilds)) {
                $wherechilds = ' or ' . $wherechilds;
            }

            $where .= $wpdb->prepare(" and (ba.custom_profile like %s  {$wherechilds})", '%' . $pn . '%');
        }
    } else if ($search == 'ethanol*') {

        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $search . '%');
    } else if ($search == 'rejected') {
        $where .= " and (ba.batch_status like 're\_%' or ba.batch_status='rejected')";
    } else {

        $search_like = '%' . $wpdb->esc_like($search) . '%';
        if (strpos($search, ',') > 0) {
            list($firstname, $lastname) = explode(',', $search);
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

if ($confirmation_pending) {
    $adding_fields .= ', (select group_concat(rs.drug) from ci_results rs 
	                        where rs.report_id = re.id 
							and (rs.conc!=\'\' or (rs.`pos-neg`!=\'\' and rs.`pos-neg`!=\'PEN\') )
							group by rs.report_id) as resulted_tests ';
}


$sql = "select
        re.id,
        re.collection_date,
        re.estatus,
        re.sublab,
        re.created,
        re.report_ref,
        re.box_uploaded,
        re.faxed,
        re.has_pos,
        re.has_reflex,
        re.final_results,
        
        ba.doc,
        ba.mid,
        ba.cli_ID,
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
        ba.has_vdx,
        ba.batch_created,
        ba.custom_profile,
        ba.only_screening,
        
        lab.lab_ID,
        lab.lab_dba_init,
        if(rs.id>0, 1, 0) as has_met_pos
        {$adding_fields}
    from ci_batches ba
    left JOIN ci_reports re ON ba.assen = re.patient_id
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    LEFT JOIN ci_results rs ON rs.report_id = re.id 
        and rs.drug  = 'Methamphetamine' and rs.`pos-neg`= 'POS'
    where 1 {$where} 
    group by ba.mid 
    {$orderby}";





$results = $wpdb->get_results($sql);
$count_rows = count($results);

if (isset($_GET['debug'])) {
    echo '<br><br>' . "\n\n";
    echo microtime(true) - $time_start;
    echo '<br><br>' . "\n\n";
    echo $sql;
    echo '<br><br>' . "\n\n";
}

echo '<table class="report_tbl child_list" data-total="' . $count_rows . '">';
$oral_content = '';
$last_position = 0;
$has_auto_approve = false;
foreach ($results as $i => $row) {

    $classes = '';
    $mstatus = '';
    $update = array();
    $panels = array();
    $missing_tests = array();
    $missing_panels = array();
    $group_has_rs = array();


    //insert if no id
    if ($confirmation_pending) {

        $row->panel_tests_requested = strtoupper($row->panel_tests_requested);
        foreach (explode(',', $row->panel_tests_requested) as $t) {

            if (isset($all_drugs[$t])) {
                $teid = $all_drugs[$t];
                $missing_tests[$teid] = $teid;
                $group_id = $all_tests[$teid]->te_group_id;

                if (isset($all_groups[$group_id]) && !isset($panels[$group_id])) {
                    $panels[$group_id] = array(
                        'name' => $all_groups[$group_id]
                    );
                }
            }
        }


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

        foreach ($missing_tests as $tid) {

            $test = false;
            $test = $all_tests[$tid];
            $gid = $test->te_group_id;

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
    } else if ($row->id == 0 && !empty($row->assen) && !empty($row->batch_id)) {
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
            $report_data['approve_date'] = date_i18n('Y-m-d H:i:s');
            $row->estatus = 'C';
            $has_auto_approve = true;
        }
        $wpdb->insert('ci_reports', $report_data);
        $row->id = $wpdb->insert_id;
    } else if (empty($row->sublab)) {
        $update['sublab'] = $row->lab_dba_init;
    } else if (isset($rejected_status[$row->batch_status]) && !in_array($row->estatus, array('C', 'M'))) {
        $update['estatus'] = 'C';
        $update['approve_date'] = date_i18n('Y-m-d H:i:s');
        $row->estatus = 'C';
        $has_auto_approve = true;
    }

    $is_oral = (stripos($row->type_of_specimen, 'oral') !== false);
    if ($row->has_met_pos && (!$is_oral || ($is_oral && $row->created > $time_show_dl))) {
        $mstatus .= '<ogr>DL</ogr>';
    }

    $test_requested = explode(',', strtoupper($row->panel_tests_requested));

    if (!$is_oral) {
        foreach ($tests_alcohols as $t) {
            if (in_array($t, $test_requested)) {
                $mstatus .= '<red>AL</red>';
                break;
            }
        }
    }

    if ($row->faxed) {
        $mstatus .= '<span class="ricon faxed" title="Faxed">F</span>';
    }

    if (isset($rejected_status[$row->batch_status])) {
        $mstatus .= '<span class="ricon rejected" title="Rejected">X</span>';
    }

    $row->Alcohol_pos = $wpdb->get_var("select report_id from ci_results 
	                                    where report_id = '{$row->id}'
	                                    and drug in ('{$tests_alcohols_str}')
	                                    and `pos-neg` = 'POS' ");

    if ($row->Alcohol_pos) {
        $mstatus .= '<span class="ricon rejected" title="Alcohol/ETG/ETS is Positive">A</span>';
    }

    if ($row->has_vdx == '0') {
        $r = tr_pdd_get_screening_vdx($row->mid, $row->batch_created, $row->batch_id);
        $row->has_vdx = $r['has_vdx'] ?? false;
    }
    if ($row->has_vdx == '2') {
        $mstatus .= '<span class="ricon rejected" title="VDX is abnormal">V</span>';
    }

    if (empty($row->doc) || $row->doc == '0000-00-00 00:00:00') {
        $row->doc = $row->collection_date;
    }


    //$row->has_reflex = '';
    if (is_null($row->has_reflex) || $row->has_reflex === '' || ($row->has_reflex === '0' && $row->estatus != 'C')) {
        $count_check_reflex++;

        $update['has_reflex'] = '0';
        $arr_rs = trpdd_filter_tests_reflex_by_screening($test_requested, $row);

        if (isset($arr_rs['reflex_tests'])) {
            if (count($arr_rs['reflex_tests']) > 0) {
                $row->has_reflex        = $arr_rs['has_reflex'];
                $update['has_reflex']   = $arr_rs['has_reflex'];
                $test_requested         = $arr_rs['test_requested'];
            }
        } else {
            unset($update['has_reflex']);
        }
    }

    if (is_null($row->has_pos) || $row->has_pos === '') {
        $test_requested_str = implode("','", $test_requested);
        if (!empty($test_requested_str)) {
            $has_pos = $wpdb->get_var("select count(*) from ci_results 
            where report_id = {$row->id}  and `pos-neg`= 'POS' 
            and drug in ('{$test_requested_str}') ");
        } else {
            $has_pos = 0;
        }



        $row->has_pos = $has_pos > 0 ? 1 : 0;

        $update['has_pos'] = $row->has_pos;
        $update['has_pos_modified'] = time();
    }

    if ($row->has_pos) {
        $classes .= ' has_pos ';
    }

    if ($row->has_reflex) {
        $classes .= ' reflex ';
    }

    $classes .= ' bs_' . $row->batch_status;

    if (count($update) > 0) {
        $wpdb->update('ci_reports', $update, array('id' => $row->id));
        $count_updated++;
    }

    if ($row->final_results == 1) {
        $mstatus .= 'Final';
    } else if ($row->final_results == 2) {
        $mstatus .= 'Preliminary';
    } else if ($row->final_results == 3) {
        $mstatus .= 'Amended Report';
    }

    ob_start();




    $row->req_link = Tr_Base_Class_V6::link(array('act' => 'edit_req', 'id' => $row->mid), false);
    if ($is_test_results) {
        $row->req_link = Tr_Base_Class_V6::link(array('act' => 'view_req', 'id' => $row->mid), false);
    }

?>
    <tr class="childp <?php echo $classes ?>" data-id="<?php echo $row->id ?>">
        <td style="width: 7%;" class="cid" title="<?php echo $row->mid ?>"><?php echo $row->id ?></td>
        <td class="assen" style="width: 7%;">
            <a href="<?php echo $row->req_link ?>" target="_blank"><?php echo $row->assen ?></a>
            <?php if (!$is_test_results && !$confirmation_pending) {
                echo '<div class="row-actions"><div class="actionswrap">';
                echo '<a class="thickbox" title="History" href="' . Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_history', 'id' => $row->id, 'btype' => 'tox'), false) . '">History</a>';
                if (current_user_can('ci_custody_chain')) {
                    echo ' | <a target="_blank" href="' . Tr_Base_Class_V6::link(array('page' => 'ci_custody_chain', 'assen' => $row->assen), false) . '">Custody Chain</a>';
                }
                echo '</div></div>';
            }
            ?>
        </td>
        <td style="width: 54px;"><?php echo $row->position ?></td>
        <td style="width: 6%;"><?php echo $row->lab_dba_init ?></td>
        <td style="width: 10%;"><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
        <td style="width: 10%;"><?php echo stripslashes($row->clinic) ?></td>
        <td style="width: 9%"><?php echo $row->doctor ?></td>
        <?php if ($confirmation_pending) { ?>
            <td style="width: 16%;" class="panels">
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
                }
                ?>
            </td>
        <?php } else { ?>
            <td style="width: 16%;"><?php echo str_replace(',', ', ', $row->medications) ?></td>
        <?php } ?>
        <td style="width: 80px;"><?php echo tr_pdd_display_date($row->doc, 'Y-m-d') ?></td>
        <td style="width: 30px"><?php echo $mstatus ?></td>
        <td style="width: 42px;" class="statuscol result_<?php echo $row->estatus ?>"><?php echo $row->estatus ?></td>
        <td class="actions">
            <?php
            if ($confirmation_pending) {
            ?>
                <span class="pdflink" <?php if ($row->estatus != 'C') echo 'style="display:none"' ?>>
                    <a href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $row->id));
                                if ($admin_view) echo '&view=1'; ?>">PDF</a> -
                </span>

                <span class="editlink" <?php if ($row->estatus == 'C' && !$admin_view) echo 'style="display:none"' ?>>
                    <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
                </span>
            <?php
            } else if ($row->report_ref > 0) {
            ?>
                <a href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $row->id));
                            if ($pdf_browser) echo '&view=1'; ?>">PDF</a>
                <?php
            } else {
                $text_approve = $row->estatus == 'C' ? 'Reverse' : 'Approve';
                if ($can_approve || $row->estatus == 'C') {
                ?>
                    <a class="approvebtn" data-id="<?php echo $row->id ?>"><?php echo $text_approve ?></a> -
                <?php } ?>
                <?php if ($show_view_btn) { ?>
                    <span class="viewBtn">
                        <a class="thickbox" title="Quick View <?php echo $row->assen; ?>" href="<?php echo Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_quick_view', 'act' => 'view', 'id' => $row->id, 'btype' => 'tox', 'width' => 1400, 'height' => 450), false); ?>">View</a> -
                    </span>
                <?php } ?>

                <span class="pdflink" <?php if ($row->estatus != 'C') echo 'style="display:none"' ?>>
                    <a href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $row->id));
                                if ($pdf_browser) echo '&view=1'; ?>">PDF</a> -
                </span>

                <span class="editlink" <?php if ($row->estatus == 'C' && !$admin_view) echo 'style="display:none"' ?>>
                    <a href="<?php Tr_Base_Class_V6::link(array('act' => 'edit', 'id' => $row->id)) ?>">Edit</a>
                </span>
                <?php
                if ($row->estatus != 'C' && in_array($current_role, array('lab', 'lab_tech', 'AdminCIDW', 'administrator'))) {
                ?>
                    - <a class="pdelete" href="#" data-id="<?php echo $row->id ?>">Delete</a>
            <?php
                }
            }
            ?>
            <?php if ($row->box_uploaded > 0) : ?>
                <span class="dashicons dashicons-yes"></span>
            <?php endif; ?>

        </td>
    </tr>
    <?php
    $content = ob_get_clean();


    if ($is_oral && !tr_get_option('ci_pdf_data', 'disable_Oral')) {
        $oral_content .= $content;
    } else {
        echo $content;
    }
}
if (!empty($oral_content)) {
    ?>
    <tr class="childp">
        <td colspan="12" align="center" style="background:#eee;font-weight: bold;">-------- Orals -------</td>
    </tr>
<?php
    echo $oral_content;
}


echo '</table>';

if ($has_auto_approve) {
    trpdd_add_cron_after_approve('tox');
}

if (isset($_GET['debug'])) {
    echo '<br><br>' . "\n\n";
    echo microtime(true) - $time_start;
    echo '<br><br>' . "\n\n";
    echo '$count_check_reflex:' . $count_check_reflex;
    echo '<br><br>' . "\n\n";
}
