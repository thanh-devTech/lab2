<?php
global $wpdb, $current_role, $blog_id;

if (isset($_GET['trajax']) && $_GET['trajax'] == 'Export') {
    $act = 'export';
} else if (!isset($act)) {
    $act = '';
}

$btype          = 'blood';
$batch_tbl      = tr_pdd_get_table_by_btype($btype);
$report_tbl     = tr_pdd_get_table_report_by_btype($btype);
$result_tbl     = tr_pdd_get_table_result_by_btype($btype);
$user_id    = get_current_user_id();
$limit_date = date('Y-m-d', strtotime('-60days'));
$blood_spcode = tr_get_option('ci_pdf_data', 'blood_spcode');
$plate_coating     = tr_get_option('ci_pdf_data', 'plate_coating');
$conditions     = trpdd_get_batch_conditions(0, 'blood');
$where          = ' is_saved = 0 ' . $conditions['where'];

$orderby        = ' order by ba.batch_id desc';
$order          = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';

$overby         = isset($_REQUEST['overby']) ? $_REQUEST['overby'] : 'doc';
$over           = isset($_REQUEST['over']) ? $_REQUEST['over'] : '';
$panelid        = isset($_REQUEST['panelid']) ? $_REQUEST['panelid'] : 0;
$orderable      = isset($_REQUEST['orderable']) ? $_REQUEST['orderable'] : '';


$joins          = " left JOIN {$report_tbl} re ON re.patient_id = ba.assen ";


$where_orderable = '';
if (tr_get_option('ci_req_settings_' . $btype, 'e_orderable_test')) {
    $where_orderable = ' or (ba.orderable_test = 1 and re.final_results != 1 )';

    if ($orderable) {
        $where .= " and ba.orderable_test = 1 ";
    }
}

//$where      .= " and ba.cli_ID > 0 and ba.patient_id > 0 ";
$where      .= " and ba.batch_id > 0  ";
$where      .= " and ba.doc >'{$limit_date}' ";
$where .= " 
and (
        re.id is null 
        or (
            (re.estatus is null or re.estatus!='C')
            and re.is_pending = 1
        )
        {$where_orderable}
    ) 
";


$where .= " and ba.batch_status !='archived' ";
$where .= " and (ba.batch_status not like 're\_%' and ba.batch_status!='rejected')";

if ($plate_coating) {
    $where .= " and ba.mid not in (
        select mid from ci_rack_batches rb  
        JOIN ci_racks ra ON ra.re_ID = rb.re_ID
        JOIN ci_plate_coating pc ON ra.re_barcode = pc.barcode
        where rb.btype='blood' and pc.type IN ('VPV', 'VT', 'PT')
        ) ";
}




if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and ba.lab_id in ( {$lab_str} ) ";
}


if (!empty($_REQUEST['s']) || !empty($_REQUEST['drug'])) {


    if (stripos($_REQUEST['s'], 'medication:') !== false) {
        $pn = str_replace('medication:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and re.medications like %s ", '%' . $pn . '%');
    } else if (stripos($_REQUEST['s'], 'position:') !== false) {
        $pn = str_replace('position:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and ba.position=%s ", $pn);
    } else if (stripos($_REQUEST['s'], 'panel:') !== false) {
        $pn = str_replace('panel:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $pn . '%');
    } else if ($_REQUEST['s'] == 'rejected') {
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
                $_REQUEST['s'] . '%',
                $_REQUEST['s'] . '%'
            );
        } else {
            $where .= $wpdb->prepare(
                " 
            and (ba.patient_name like %s 
            or ba.assen=%s 
            or ba.requesting_practice like %s
            or ba.requesting_physician like %s)",
                $_REQUEST['s'] . '%',
                $_REQUEST['s'],
                $_REQUEST['s'] . '%',
                $_REQUEST['s'] . '%'
            );
        }
    }
}

if ($panelid > 0) {
    $where .= " and find_in_set( 'GROUP_{$panelid}', ba.panel_tests_requested )";
}

if ($over > 0) {

    $orderby = " order by ba.{$overby} desc";
    $timenow = strtotime(date_i18n('Y-m-d H:i:s'));

    $overdatetime = date('Y-m-d H:i:s', $timenow - $over * 3600);

    $where .= " and ba.{$overby} <= '{$overdatetime}' ";

    $over_time_24 = $timenow - 24 * 3600;
    $over_time_48 = $timenow  - 48 * 3600;
    $over_time_72 = $timenow - 72 * 3600;


    $covid_group_id = 0;
    $all_groups = array();
    $all_tests = array();
    $rs = $wpdb->get_results("select te_ID, 
                        te_name, 
						te_abrev,
                        te_group_id,
                        tg_name,
                         tg_only_group,
                         tg_notestcheckbox,
                         tg_is_covid
                        from ci_test_panels te
                         join ci_test_groups tg ON tg.tg_ID = te.te_group_id
                         where te_type='blood' ");
    foreach ($rs as $r) {
        $r->drug = strtoupper($r->te_name);
        $r->te_abrev = strtoupper($r->te_abrev);

        $all_tests[$r->te_ID] = $r;
        $all_groups[$r->te_group_id] = $r->tg_name;

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

    //page
    $row_per_page = 100;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $offset = $pagination->offset();

    $sql_total = "select count(*) as c from ci_batches_blood ba
    {$joins}
        where {$where}";


    if (class_exists('Tr_Cache_Query')) {
        $cache_time = 3600;
        $cache = new Tr_Cache_Query();
        $total = $cache->get_var($sql_total, 0, 0, $cache_time);
    } else {
        $total = $wpdb->get_var($sql_total);
    }

    $time_start  = microtime(true);
    $pagination->setTotal($total);
    $q_total = microtime(true) - $time_start;

    $adding_fields .= ', (select group_concat(rs.drug) from ci_results_blood rs 
	                        where rs.report_id = re.id 
							and (rs.conc!=\'\' or rs.`pos-neg`!=\'\')
							group by rs.report_id) as resulted_tests ';

    $sql = "select 
	    ba.mid,
	    ba.batch_id,
	    ba.position,
	    ba.assen,
	    ba.patient_name,
	    ba.requesting_practice as clinic,
        ba.requesting_physician as doctor,
        ba.panel_tests_requested,
        ba.doc,
        ba.spec_received,
        ba.batch_status,
        lab.lab_ID,
        lab.lab_dba_init,
        re.id,
	    re.created_date,
        re.report_ref
        {$adding_fields}
    from ci_batches_blood ba
    {$joins}
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    where {$where} 
    {$orderby} 
    limit {$offset},{$row_per_page}";


    $time_start  = microtime(true);
    $results = $wpdb->get_results($sql);
    $count_rows = count($results);
} else if ($act == 'export') {
    $sql = "select 
    ba.mid,
    ba.batch_id,
    ba.position,
    ba.assen,
    ba.patient_name,
    ba.requesting_practice as clinic,
    ba.requesting_physician as doctor,
    ba.panel_tests_requested,
    ba.doc,
    ba.spec_received,
    ba.batch_status,
    lab.lab_ID,
    lab.lab_dba_init,
    re.report_ref,
    re.created_date,
    re.id
    from {$batch_tbl} ba
    {$joins}
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    where {$where} 
    {$orderby} 
    ";
    $results = $wpdb->get_results($sql);
} else {


    //page
    $row_per_page = 10;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $offset = $pagination->offset();

    $sql_total = "select count(distinct(ba.batch_id)) as c from ci_batches_blood ba
    {$joins}
        where {$where} and ba.batch_id > 0";

    $sql_limit = "select distinct(ba.batch_id) from ci_batches_blood ba
    {$joins}
        where  {$where} and ba.batch_id > 0
        {$orderby}  
        limit {$offset},{$row_per_page}";


    $total = $wpdb->get_var($sql_total);

    $time_start = microtime(true);
    $pagination->setTotal($total);
    $q_total = microtime(true) - $time_start;

    $time_start = microtime(true);
    $batch_ids  = $wpdb->get_col($sql_limit);
    $q_limit    = microtime(true) - $time_start;

    $batch_ids = implode(',', $batch_ids);
    if (empty($batch_ids)) {
        $batch_ids = '-1';
    }


    $sql = "select ba.batch_id,re.created_date,
        lab.lab_ID,
        lab.lab_dba_init,
        re.report_ref,
        count(*) as count_result,
        0 as count_uploaded
    from (select * from ci_batches_blood where batch_id in({$batch_ids}) ) ba
    {$joins}
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    where {$where} 
    group by ba.batch_id,lab.lab_dba_init
    {$orderby} ";


    $time_start = microtime(true);
    $results = $wpdb->get_results($sql);
    $count_rows = count($results);
}



if ($act == 'export') {
    tr_pdd_header('csv', 'export.csv');
    $headers = array('Batch', 'Accession', 'Position', 'Lab', 'Patient Name', 'Clinic', 'Missing Panels', 'Result Type', 'DOC', 'Status');
    $f = fopen('php://output', 'w');
    fputcsv($f, $headers);
    foreach ($results as $row) {
        $panels = array();
        $missing_tests = array();
        foreach (explode(',', $row->panel_tests_requested) as $t) {
            if (stripos($t, 'group') !== false) {
                $group_id = str_ireplace('group_', '', $t);
                if (isset($groups[$group_id])) {
                    $popup_msg = $groups[$group_id]->approve;
                    $popup_verified = $groups[$group_id]->verified;
                }

                $panels[] = $all_groups[$group_id];
            } else if (isset($all_tests[$t])) {
                $missing_tests[$all_tests[$t]->drug] = $all_tests[$t];
            }
        }

        //search tests pending
        if ($row->id > 0) {
            $rs_tests = $wpdb->get_results("select * from  where report_id = {$row->id}");
            foreach ($rs_tests as $rs_test) {
                $rs_test->drug = strtoupper($rs_test->drug);

                if (isset($missing_tests[$rs_test->drug])) {
                    unset($missing_tests[$rs_test->drug]);
                }
            }

            foreach ($missing_tests as $drug => $missing_test) {
                if (!isset($panels[$missing_test->te_group_id])) {
                    $panels['test_' . $drug] = 'Test: ' . $missing_test->te_name;
                }
            }
        }

        $row->panels = implode('; ', $panels);
        $datarow = array(
            $row->batch_id,
            $row->assen,
            $row->position,
            $row->lab,
            $row->patient_name,
            $row->clinic,
            $row->panels,
            tr_pdd_get_result_status($row->final_results),
            tr_pdd_display_date($row->doc, 'Y-m-d H:i'),
            tr_pdd_get_batch_statuses($row->batch_status, false, false, true)
        );


        fputcsv($f, $datarow);
    }
    $data_log = [
        'type' => 'Blood Pending Results',
        'file_name' => 'blood_pending_' . $over . '.csv',
        'total_records' => count($results),
    ];
    trpdd_log_user(get_current_user_id(), 'download_results', $data_log, '', '', '', '', 'Results_' . date_i18n('mdY'));
    exit;
}

$groups = $wpdb->get_results("select * from ci_test_groups 
					where tg_type='blood' 
					and tg_show_req = 1
					order by tg_name asc");
if (isset($_REQUEST['debug'])) {
    echo microtime(true) - $time_start, '<br>';
    echo $sql_total, '<br>';
    echo 'qtotal: ' . $q_total, '<br>';
    echo $sql_limit, '<br>';
    echo 'qlimit: ' . $q_limit, '<br>';
    echo $sql, '<br>';
}
?>
<div class="wrap">
    <h2>Pending Results</h2>
    <?php Tr_Session_Class::show() ?>
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <label>Patient/Clinic/Doctor:</label>
        <input type="text" name="s" value="<?php echo isset($_REQUEST['s']) ? $_REQUEST['s'] : '' ?>" />
        <select name="panelid" class="njs_select2">
            <option value="">Select Panel</option>
            <?php
            foreach ($groups as $group) {
                $selected = $group->tg_ID == $panelid ? 'selected' : '';
                echo '<option value="' . $group->tg_ID . '" ' . $selected . '>' . $group->tg_name . '</group>';
            }
            ?>
        </select>

        <label> <input type="radio" name="overby" value="doc" <?php checked($overby, 'doc') ?> /> DOC </label>
        <label> <input type="radio" name="overby" value="spec_received" <?php checked($overby, 'spec_received') ?> /> Received </label>
        <select name="over">
            <option value="">Over</option>
            <?php
            foreach (array(24, 48, 72) as $hr) {
                $selected = $_REQUEST['over'] == $hr ? 'selected' : '';
                echo '<option value="' . $hr . '" ' . $selected . '>' . $hr . 'hr</option>';
            }
            ?>
        </select>
        <?php
        if (tr_get_option('ci_req_settings_' . $btype, 'e_orderable_test')) {
            echo '<label class="checkboxbor"><input type="checkbox" name="orderable" value="1" ' . checked($orderable, true, false) . '/> Only Orderable</label>';
        }
        ?>
        <input type="submit" class="button bsty6" value="Search" />
        <input type="submit" class="button bsty2" name="trajax" value="Export" />


    </form>
    <div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>

    <div class="wraptbl">
        <?php
        $batches = array();
        if (count($results) > 0) {

            if ($over > 0) {
        ?>
                <table class="wp-list-table widefat striped posts blue_tbl hover_tbl " data-count="<?php echo @$count_rows ?>">
                    <thead class="sticky">
                        <tr>
                            <th style="width: 7%;">Batch</th>
                            <th style="width: 7%;">Accession</th>
                            <th style="width: 54px;">Position</th>
                            <th style="width: 40px">Lab</th>
                            <th style="width: 14%;">Patient Name</th>
                            <th style="width: 13%;">Clinic</th>
                            <th style="width: 20%;">Missing Panels</th>
                            <th style="width: 80px;">DOC</th>
                            <th style="width:100px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($results as $i => $row) {
                            $classes = '';
                            $classes .= ' bs_' . $row->batch_status;

                            $batchdate = $overby == 'doc' ? $row->doc : $row->spec_received;
                            $batchtime = strtotime($batchdate);

                            if ($batchtime <= $over_time_24 && $batchtime > $over_time_48) {
                                $classes .= " over24 ";
                            } else if ($batchtime <= $over_time_48 && $batchtime > $over_time_72) {
                                $classes .= ' over48 ';
                            } else if ($batchtime <= $over_time_72) {
                                $classes .= ' over72 ';
                            }
                            $panels = array();
                            $missing_tests = array();
                            $missing_panels = array();

                            foreach (explode(',', $row->panel_tests_requested) as $t) {
                                if (stripos($t, 'group') !== false) {
                                    $group_id = str_ireplace('group_', '', $t);
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
                                } else if (isset($all_tests[$t])) {
                                    $missing_tests[$t] = $t;
                                }
                            }


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
                                            unset($missing_tests['group_' . $gid]);
                                        } else {
                                            unset($missing_tests['group_' . $gid]);
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

                            if ($panelid > 0 && !isset($panels[$panelid])) {
                                continue;
                            }

                        ?>
                            <tr class=" <?php echo $classes ?>" title="<?php if ($row->batch_status == 'hold') echo 'On Hold' ?>" data-id="<?php echo $row->id ?>">
                                <td class="bid"><?php echo $row->batch_id ?></td>
                                <td><a href="admin.php?page=form_blood&act=edit&id=<?php echo $row->mid ?>" target="_blank"><?php echo $row->assen ?></a></td>
                                <td><?php echo $row->position ?></td>
                                <td><?php echo $row->lab_dba_init ?></td>
                                <td><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
                                <td><?php echo stripslashes($row->clinic) ?></td>
                                <td class="panels">
                                    <?php
                                    foreach ($panels as $groupid => $arr) {
                                        $class = '';
                                        if (isset($arr['has_rs']) && $arr['has_rs']) {
                                            $class .= ' has_rs ';
                                        }
                                        if (!empty($arr['missing_tests'])) {
                                            $class .= ' has_missing_tests ';
                                        }
                                        echo '<li class="' . $class . '" title="' . $arr['missing_tests'] . '"><span>' . $arr['name'] . '</span></li>';
                                    } ?>
                                </td>

                                <td><?php echo tr_pdd_display_date($row->doc, 'Y-m-d') ?></td>
                                <td class="mstatus"><?php echo tr_pdd_get_batch_statuses($row->batch_status, false, false, true) ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
                <style>
                    tr.over24 .bid {
                        background: yellow !important;
                    }

                    tr.over48 .bid {
                        background: orange !important;
                    }

                    tr.over72 .bid {
                        background: red !important;
                    }
                </style>
            <?php
            } else {
            ?>
                <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
                    <thead class="sticky">
                        <tr>
                            <th style="width:3%">Pos</th>
                            <th style="width: 4%">Batch</th>
                            <th style="width: 8%;">Accession</th>
                            <th style="width: 16%;">Patient Name</th>
                            <th style="width: 18%;">Clinic</th>
                            <th style="width: 12%;">Doctor</th>
                            <?php if ($blood_spcode) { ?>
                                <th style="width: 8%;">ID</th>
                            <?php  } ?>
                            <th style="width: 80px;">Doc</th>
                            <th style="width:20%">Missing Tests/Panels</th>
                            <th style="width:72px">Status</th>
                            <th class="center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($results as $i => $row) {

                            if (!isset($batches[$row->batch_id]) || !is_array($batches[$row->batch_id])) {
                                $batches[$row->batch_id] = array();
                            }

                            $batches[$row->batch_id]['date'] = $row->created_date;

                            $batches[$row->batch_id]['uploaded']       = $row->count_uploaded >= $row->count_result && $row->count_uploaded > 0;
                            $batches[$row->batch_id]['count_uploaded'] = $row->count_uploaded;
                            $batches[$row->batch_id]['count_result']   = $row->count_result;
                            if ($row->report_ref == 0) {
                                $batches[$row->batch_id]['labs'][$row->lab_ID] = $row;
                            } else {
                                $batches[$row->batch_id]['labs'][$row->lab_ID . $row->ref_lab_ID] = $row;
                            }
                        }
                        ?>

                        <?php
                        foreach ($batches as $batch_id => $d) {
                            $classes = '';
                            if ($d['date'] == '1970-01-01') {
                                $d['date'] = preg_replace('/([0-9]{4})([0-9]{2})([0-9]{2})/', '$1-$2-$3', $batch_id);
                            }

                        ?>
                            <tr class="parent <?php echo $classes ?>" data-p="batch_<?php echo $batch_id ?>" data-uped="<?php echo $d['count_uploaded'] ?>" data-count="<?php echo $d['count_result'] ?>">
                                <td><i></i></td>
                                <td><?php echo $batch_id ?></td>
                                <td></td>
                                <td><a class="show_batch_result_files dashicons dashicons-archive" data-batch_id="<?php echo $batch_id ?>"></a></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td><?php echo $d['date'] ?></td>
                                <td colspan="2" style="text-align: right">
                                    <span class="countrs"><?php echo $d['count_result'] ?></span>
                                </td>
                            </tr>
                            <tr class="child p_batch_<?php echo $batch_id ?>">
                                <td colspan="11" class="nopadding">
                                    <table class="report_tbl">
                                        <?php

                                        foreach ($d['labs'] as $lab_key => $row) {
                                            $lab_id = $row->lab_ID;
                                        ?>
                                            <tr class="parent_c" data-p="lab_<?php echo $lab_key . $batch_id ?>">
                                                <td style="width: 40px;"><i></i></td>
                                                <td style="width:7%"></td>
                                                <td style="width:54px"></td>
                                                <td style="width:6%"><?php echo $row->lab_dba_init ?></td>
                                                <td colspan="7" align="right" class="bactions">

                                                </td>
                                            </tr>
                                            <tr class="child_c p_lab_<?php echo $lab_key . $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>" data-lab_id="<?php echo $lab_id ?>">
                                                <td colspan="11" class="nopadding"></td>
                                            </tr>
                                        <?php
                                        }

                                        ?>
                                    </table>
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            <?php
            }
        } else {
            ?>
            <p>No Results Found.</p>
        <?php
        }



        ?>

    </div>

</div>

<script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/pdf.js"></script>
<script>
    PDFJS.workerSrc = '<?php echo TRCPDD_URL ?>/js/pdf.worker.js';
    jQuery(function($) {
        function trcpp_load_results($batch_id, $lab_id, $ref_lab_id) {
            var $obj = $('tr.p_lab_' + $lab_id + $ref_lab_id + $batch_id + ' td');
            var $trpr = $('tr[data-p="lab_' + $lab_id + $ref_lab_id + $batch_id + '"]');
            if ($obj.html() != '') {
                $('a.printbatch', $trpr).show();
                return;
            } else {
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data: {
                    tr_action: 'ci_load_results_blood',
                    batch_id: $batch_id,
                    lab_id: $lab_id,
                    ref_lab_id: $ref_lab_id,
                    pending: true
                },
                type: 'post',
                success: function(rs) {
                    var $html = $(rs);
                    $obj.html($html);
                    $('.cid', $html).each(function() {
                        $(this).prepend('');
                    })
                },
                error: function(c) {
                    trcpp_load_results($batch_id, $lab_id, $ref_lab_id);
                }
            })
        }
        $('body').on('click', 'a.delete', function() {
            if (confirm('Do you want delete?') == false) {
                return false;
            }
        })
        $('a.approve_all').click(function() {
            if (confirm('Do you want Approve all?') == true) {
                location = $(this).attr('href');
            }
            return false;
        })

        $('a.btn_sell_all').click(function() {
            var sleat = $(this).attr("sel");
            var $pid = $(this).parents('.parent_c').data('p');

            if (sleat == "true") {
                $(".p_" + $pid + " input:checkbox").prop('checked', true);
                $(this).attr("sel", false);
                $(this).html("Unselect All")
            } else {
                $(".p_" + $pid + " input:checkbox").prop('checked', false);
                $(this).attr("sel", true);
                $(this).html("Select All")
            }
            return false;
        })

        $('a.uploadpdf').click(function() {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $ref_lab_id + $bid + ' input:checked').each(function() {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
            }
            var $url = '?tr_action=ci_upload_result_pdf_to_box&type=blood&batch_id=' + $bid + '&lab_id=' + $labid + '&ref_lab_id=' + $ref_lab_id + '&kt=<?php echo time() ?>&ids=' + $ids;
            <?php if (isset($_GET['debug'])) : ?>
                $url += '&rsdb=1';
            <?php endif; ?>
            tb_show('Upload to Box', $url + '&TB_iframe=true&width=400&height=300');
            return false;
        });

        $('a.delete_selected').click(function() {
            if (!confirm('Do You want delete selected?')) return false;
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            var $childpr = $('.p_lab_' + $labid + $ref_lab_id + $bid);
            $('input:checked', $childpr).each(function() {
                $ids += $(this).val() + ',';
            });
            if ($ids === '') {
                alert('Please select rows to delete!');
                return false;
            }
            $.ajax({
                data: {
                    trajax: '1',
                    act: 'delete_selected',
                    ids: $ids
                },
                type: 'post',
                dataType: 'json',
                success: function(json) {
                    if (json.status === 'ok') {
                        for (var i in json.ids) {
                            $('.childp[data-id="' + json.ids[i] + '"]', $childpr).remove();
                        }
                    } else {
                        alert('Error. Please try again!');
                    }
                },
                error: function() {
                    alert('Error. Please try again!');
                }
            })
            return false;
        });


        $('a.fax_pdf').click(function() {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $ref_lab_id + $bid + ' input:checked').each(function() {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
            }
            var $url = '?tr_action=ci_upload_result_pdf_to_efax&type=blood&batch_id=' + $bid + '&lab_id=' + $labid + '&ref_lab_id=' + $ref_lab_id + '&kt=<?php echo time() ?>&ids=' + $ids;
            <?php if (isset($_GET['debug'])) : ?>
                $url += '&rsdb=1';
            <?php endif; ?>
            tb_show('Fax PDF', $url + '&TB_iframe=true&width=600&height=500');
            return false;
        });

        $('a.hold_btn').click(function() {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $ref_lab_id + $bid + ' input:checked').each(function() {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
            }
            $('body .loadingbody').remove();
            $('body').append('<div class="loading loadingbody"></div>');
            $.ajax({
                data: {
                    "batch_id": $bid,
                    "lab_id": $labid,
                    "ref_lab_id": $ref_lab_id,
                    "ids": $ids,
                    "trajax": 1,
                    "act": "change_hold"
                },
                type: 'post',
                dataType: 'json',
                success: function(json) {
                    $('body .loadingbody').remove();
                    for (i in json.ids) {
                        var $id = json.ids[i];
                        var $tr = $('tr.childp[data-id="' + $id + '"]');
                        $tr.addClass('bs_hold');
                        $('input[type="checkbox"]', $tr).removeAttr('checked');
                    }
                }
            });
            return false;
        });

        $('a.removehold_btn').click(function() {
            var $bid = $(this).data('batch_id');
            var $labid = $(this).data('lab_id');
            var $ref_lab_id = $(this).data('ref_lab_id');
            var $ids = '';
            if ($labid) {
                //find checked
                $('.p_lab_' + $labid + $ref_lab_id + $bid + ' input:checked').each(function() {
                    $ids += $(this).val() + ',';
                })

            } else {
                $labid = 0;
            }
            $('body .loadingbody').remove();
            $('body').append('<div class="loading loadingbody"></div>');
            $.ajax({
                data: {
                    "batch_id": $bid,
                    "lab_id": $labid,
                    "ref_lab_id": $ref_lab_id,
                    "ids": $ids,
                    "trajax": 1,
                    "act": "remove_hold"
                },
                type: 'post',
                dataType: 'json',
                success: function(json) {
                    $('body .loadingbody').remove();
                    for (i in json.ids) {
                        var $id = json.ids[i];
                        var $tr = $('tr.childp[data-id="' + $id + '"]');
                        $tr.removeClass('bs_hold');
                        $('input[type="checkbox"]', $tr).removeAttr('checked');

                    }
                }
            });
            return false;
        });


        $('a.show_batch_result_miss').click(function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&type=blood&width=800');
            return false;
        })
        $('body').on('click', 'a.show_batch_result_miss_back', function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&type=blood&width=800');
            return false;
        })
        $('body').on('click', 'a.show_batch_result_miss_upload', function() {
            var $bid = $(this).data('batch_id');
            var $lab_id = $(this).data('lab_id');
            tb_show('miss upload', '?tr_action=ci_show_batch_result_miss_upload&batch_id=' + $bid + '&lab_id=' + $lab_id + '&type=blood&width=800');
            return false;
        })
        $('a.show_batch_result_files').click(function() {
            var $bid = $(this).data('batch_id');
            tb_show('Uploaded Files in Batch ' + $bid, '?tr_action=ci_show_batch_result_files&batch_id=' + $bid + '&type=blood&width=800');
            return false;
        })
        $('body').on('click', 'a.print_pdf', function() {
                var $url = '<?php echo admin_url('/') ?>' + $(this).attr('href');
                $(this).html('Loading');
                $.print_Pdf($url, $(this));
                return false;
            })
            .on('printed', 'a.print_pdf', function() {
                var $obj = $(this);
                $obj.parent().find('.dashicons-yes').remove();
                $obj.parent().append('<span class="dashicons dashicons-yes"></span>');
                $obj.html('Printed');
            })
            .on('print_error', 'a.print_pdf,a.printbatch', function() {
                $(this).html('Print');
            })
            .on('click', 'a.printbatch', function(e) {
                e.preventDefault();
                var $urls = [];
                var $trpr = $(this).parents('tr.parent_c');
                var $trchild = $('tr.p_' + $trpr.data('p'));
                $('a.print_pdf', $trchild).each(function() {
                    $urls.push($(this).attr('href'));
                })
                if ($urls.length == 0) {
                    alert('Not found');
                    return false;
                }
                $.print_Pdfs($urls, $(this));
                return false;
            })
            .on('printed', 'a.printbatch', function() {
                var $obj = $(this);
                $obj.html('Printed');
            }).on('click', '[name="orderable"]', function() {
                $(this).parents('form').submit();
            })


        $('tr.parent').click(function(e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            $('tr.child').hide();
            if ($(this).data('showed') != '1') {
                var $pr = $('tr.p_' + $(this).data('p'));
                $pr.show();
                $('tr.parent').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_rs', $(this).data('p'));
                var $obj = $(this);
                $('body,html').scrollTop($obj.offset().top - 150);
                if ($('.parent_c', $pr).length == 1) {
                    $('.parent_c', $pr).click();
                }
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('tr.parent_c').click(function(e) {
            if (e.target.tagName == 'A') return;
            $('tr.child_c').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_rs', $(this).data('p'));
                var $obj = $('tr.p_' + $(this).data('p'));
                $('body,html').scrollTop($obj.offset().top - 150);
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
                $ref_lab_id = $obj.data('ref_lab_id');
                trcpp_load_results($batch_id, $lab_id, $ref_lab_id);
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');

                $('a.printbatch', $(this)).hide();
            }
            return false;
        });


        var $old_active = getCookie('active_rs');
        if ($old_active && $old_active != null) {
            var $obj = $('tr.p_' + $old_active);
            try {
                $obj.show();
                $('[data-p="' + $old_active + '"]').data('showed', '1');
                if ($old_active.indexOf('lab_') > -1) {
                    $obj.parents('.child').show();
                }
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
                $ref_lab_id = $obj.data('ref_lab_id');
                setTimeout(function() {
                    trcpp_load_results($batch_id, $lab_id, $ref_lab_id);
                }, 500);
            } catch (e) {}

        }

    })
</script>