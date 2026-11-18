<?php
global $wpdb, $current_role;


$btype          = 'tox';
$batch_tbl      = tr_pdd_get_table_by_btype($btype);
$report_tbl     = tr_pdd_get_table_report_by_btype($btype);
$result_tbl     = tr_pdd_get_table_result_by_btype($btype);



$user_id        = get_current_user_id();
$time_show_dl   = ('2016-05-16');
$box_options    = get_option('ci_box_settings', array());

if (isset($_GET['trajax']) && $_GET['trajax'] == 'Export') {
    $act = 'export';
} else if (!isset($act)) {
    $act = '';
}

$conditions     = trpdd_get_batch_conditions(0, 'tox');
$where          = ' is_saved = 0 ' . $conditions['where'];
$orderby        = ' order by ba.batch_id desc';

$order          = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';
$overby         = isset($_REQUEST['overby']) ? $_REQUEST['overby'] : 'doc';
$over           = isset($_REQUEST['over']) ? $_REQUEST['over'] : '';

$joins          = " left JOIN {$report_tbl} re ON re.patient_id = ba.assen ";

$joins          .= " left join ci_result_test_speciments ts ON ts.mid = ba.mid and ts.conc!='' ";
//page
$row_per_page   = 10;
$pagination     = new Tr_Pagination_class2($row_per_page);
$offset         = $pagination->offset();

if (CI_Base_Lab::has_user_labs()) {
    $lab_str = CI_Base_Lab::get_user_lab_ids_str();
    $where .= " and ( ba.lab_id in ( {$lab_str} ) or ba.ref_lab_ID in ( {$lab_str} ) )  ";
}


$where  .= " and ba.cli_ID > 0 and ba.patient_id > 0 ";
$where  .= " and (re.id is null or re.estatus !='C' or re.estatus is null )";
$where  .= " and ba.batch_status !='archived' ";
$where  .= " and (ba.batch_status not like 're\_%' and ba.batch_status!='rejected')";


if (!empty($_REQUEST['s']) || !empty($_REQUEST['drug'])) {


    if (@$_REQUEST['drug'] == 'Alcohol') {
        $where .= " and ba.panel_tests_requested like '%ethanol*%' and ba.type_of_specimen != 'oral' ";
    } else if (@$_REQUEST['drug'] == 'Methamphetamine DL') {
        $where .= " and (ba.type_of_specimen not like 'oral%' or ( ba.type_of_specimen like 'oral%' and re.created_date > '{$time_show_dl}' )) and rs.id > 0 ";
        $joins .= "  join {$result_tbl} rs ON rs.report_id = re.id and rs.drug='METHAMPHETAMINE' and rs.`pos-neg`='POS' ";
    }


    if (stripos($_REQUEST['s'], 'medication:') !== false) {
        $pn = str_replace('medication:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and re.medications like %s ", '%' . $pn . '%');
    } else if (stripos($_REQUEST['s'], 'position:') !== false) {
        $pn = str_replace('position:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and ba.position=%s ", $pn);
    } else if (stripos($_REQUEST['s'], 'panel:') !== false) {
        $pn = str_replace('panel:', '', $_REQUEST['s']);
        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $pn . '%');
    } else if ($_REQUEST['s'] == 'ethanol*') {

        $where .= $wpdb->prepare(" and ba.panel_tests_requested like %s ", '%' . $_REQUEST['s'] . '%');
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

if ($over > 0) {
    $orderby = " order by ba.{$overby} desc";
    $timenow = strtotime(date_i18n('Y-m-d H:i:s'));

    $overdatetime = date('Y-m-d H:i:s', $timenow - $over * 3600);

    $where .= " and ba.{$overby} <= '{$overdatetime}' ";

    $over_time_24 = $timenow - 24 * 3600;
    $over_time_48 = $timenow  - 48 * 3600;
    $over_time_72 = $timenow - 72 * 3600;

    //page
    $row_per_page = 100;
    $pagination = new Tr_Pagination_class2($row_per_page);
    $offset = $pagination->offset();

    $sql_total = "select count(*) as c from {$batch_tbl} ba
    {$joins}
        where {$where}";


    if (class_exists('Tr_Cache_Query')) {
        $cache_time = 86400;
        $cache = new Tr_Cache_Query();
        $total = $cache->get_var($sql_total, 0, 0, $cache_time);
    } else {
        $total = $wpdb->get_var($sql_total);
    }

    $time_start  = microtime(true);
    $pagination->setTotal($total);
    $q_total = microtime(true) - $time_start;

    $adding_fields = ', (select group_concat(rs.drug) from ci_results rs 
	                        where rs.report_id = re.id 
							and (rs.conc!=\'\' or (rs.`pos-neg`!=\'\' and rs.`pos-neg`!=\'PEN\') )
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
	    re.created_date,
        lab.lab_ID,
        lab.lab_dba_init,
        re.id,
        re.report_ref
        {$adding_fields}
    from {$batch_tbl} ba
    {$joins}
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    where {$where} 
    {$orderby} 
    ";
    if ($act != 'export') {
        $sql .= " limit {$offset},{$row_per_page} ";
    }


    $time_start  = microtime(true);
    $results = $wpdb->get_results($sql);
    $count_rows = count($results);
} else if ($act == 'export') {

    $adding_fields = ', (select group_concat(rs.drug) from ci_results rs 
	                        where rs.report_id = re.id 
							and (rs.conc!=\'\' or (rs.`pos-neg`!=\'\' and rs.`pos-neg`!=\'PEN\') )
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
    re.created_date,
    lab.lab_ID,
    lab.lab_dba_init,
    re.id,
    re.report_ref
    {$adding_fields}
    from {$batch_tbl} ba
    {$joins}
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    where {$where} 
    {$orderby} 
    ";
    $results = $wpdb->get_results($sql);
} else {

    $sql_total = "select count(distinct(ba.batch_id)) as c from {$batch_tbl} ba
    {$joins}
        where {$where}";

    $sql_limit = "select distinct(ba.batch_id) from {$batch_tbl} ba
    {$joins}
        where {$where}
        {$orderby}  limit {$offset},{$row_per_page}";


    if (class_exists('Tr_Cache_Query')) {
        $cache_time = 86400;
        $cache = new Tr_Cache_Query();
        $total = $cache->get_var($sql_total, 0, 0, $cache_time);
    } else {
        $total = $wpdb->get_var($sql_total);
    }

    $time_start  = microtime(true);
    $pagination->setTotal($total);
    $q_total = microtime(true) - $time_start;

    $time_start  = microtime(true);
    $batch_ids = $wpdb->get_col($sql_limit);
    $q_limit = microtime(true) - $time_start;

    $batch_ids = implode(',', $batch_ids);

    if (empty($batch_ids)) {
        $batch_ids = '-1';
    }

    $sql = "select ba.batch_id,re.created_date,
        lab.lab_ID,
        rl.lab_ID as ref_lab_ID,
        if(re.report_ref = 0 or re.report_ref is null, lab.lab_dba_init, rl.lab_dba_init) as lab_dba_init,
        re.report_ref,
        sum(if(re.estatus='C',1,0)) as count_result,
        sum(if(re.box_uploaded>0,1,0)) as count_uploaded
    from (select * from {$batch_tbl} where batch_id in({$batch_ids}) ) ba
    {$joins}
    JOIN ci_labs lab ON ba.lab_id = lab.lab_ID
    LEFT JOIN ci_labs rl ON rl.lab_ID = ba.ref_lab_ID and re.report_ref >0
    where {$where} 
    group by ba.batch_id,lab.lab_dba_init, rl.lab_dba_init
    {$orderby} ";


    $time_start  = microtime(true);
    if (class_exists('Tr_Cache_Query2')) {
        $cache_time = 500;
        if (isset($_REQUEST['m'])) {
            $cache_time = 5000;
        } else if (isset($_GET['created'])) {
            $cache_time = 0;
        }

        $cache = new Tr_Cache_Query();
        $results = $cache->get_results($sql, OBJECT, $cache_time);
    } else {
        $results = $wpdb->get_results($sql);
    }
    $count_rows = count($results);
}

if ($act == 'export') {
    tr_pdd_header('csv', 'export.csv');
    $headers = array('Batch', 'Accession', 'Position', 'Lab', 'Patient Name', 'Clinic', 'Missing Panels', 'Result Type', 'DOC', 'Status');
    $f = fopen('php://output', 'w');
    fputcsv($f, $headers);
    foreach ($results as $row) {
        $panels = array();

        $missing_panels = array();
        $missing_tests = array();

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


                if (in_array($gid, $group_has_rs ?? [])) {
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
        $panels = array();
        foreach ($missing_panels as $r) {
            $panels[] = $r['missing_tests'];
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
        'type' => 'Tox Pending Results',
        'file_name' => 'export.csv',
        'total_records' => count($results),
    ];
    trpdd_log_user(get_current_user_id(), 'download_results', $data_log, '', '', '', '', 'Results_' . date_i18n('mdY'));
    exit;
}


if (isset($_REQUEST['debug'])) {
    echo microtime(true) - $time_start, '<br>';
    echo $sql_total, '<br>';
    echo 'qtotal: ' . $q_total, '<br>';
    echo $sql_limit, '<br>';
    echo 'qlimit: ' . $q_limit, '<br>';
    echo $sql, '<br>';
}
?>
<h2>Pending Results</h2>
<?php Tr_Session_Class::show() ?>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
    <label>Patient/Clinic/Doctor:</label>
    <input type="text" name="s" value="<?php echo $_REQUEST['s'] ?>" />
    <select name="over">
        <?php
        echo '<option value="">Over</option>';
        foreach (array(24, 48, 72) as $t) {
            $selected = $t == $over ? 'selected' : '';
            echo '<option value="' . $t . '" ' . $selected . '>' . $t . 'hrs</option>';
        }
        ?>
    </select>


    <?php if (!tr_get_option('ci_pdf_data', 'disable_dl')): ?>
        <label><input type="radio" class="drugs" name="drug" value="Methamphetamine DL" <?php checked(@$_REQUEST['drug'], 'Methamphetamine DL') ?> /> Methamphetamine DL</label>
    <?php endif; ?>
    <?php if (!tr_get_option('ci_pdf_data', 'disable_Alcohol')): ?>
        <label><input type="radio" class="drugs" name="drug" value="Alcohol" <?php checked(@$_REQUEST['drug'], 'Alcohol') ?> /> Alcohol</label>
    <?php endif; ?>

    <input type="submit" class="button bsty6" value="Search" />

    <input type="submit" class="button bsty2" name="trajax" value="Export" />


</form>


<div class="wraptbl">
    <?php
    $batches = array();
    if (count($results) > 0) {
        if ($over > 0) {
    ?>
            <table class="wp-list-table widefat striped posts blue_tbl hover_tbl pendingtbl " data-count="<?php echo @$count_rows ?>">
                <thead class="sticky">
                    <tr>
                        <th style="width: 7%;">Batch</th>
                        <th style="width: 7%;">Accession</th>
                        <th style="width: 54px;">Position</th>
                        <th style="width: 40px">Lab</th>
                        <th style="width: 14%;">Patient Name</th>
                        <th style="width: 13%;">Clinic</th>
                        <th style="width: 15%;">Missing Panels</th>
                        <th style="width: 10%;">Result Type</th>
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
                        $missing_panels = array();
                        $missing_tests = array();
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
                    ?>
                        <tr class=" <?php echo $classes ?>" title="<?php if ($row->batch_status == 'hold') echo 'On Hold' ?>" data-id="<?php echo $row->id ?>">
                            <td class="bid"><?php echo $row->batch_id ?></td>
                            <td><a href="admin.php?page=<?php echo $btype ?>_req_form&act=edit&id=<?php echo $row->mid ?>" target="_blank"><?php echo $row->assen ?></a></td>
                            <td><?php echo $row->position ?></td>
                            <td><?php echo $row->lab_dba_init ?></td>
                            <td><?php echo tr_pdd_capitalized($row->patient_name) ?></td>
                            <td><?php echo stripslashes($row->clinic) ?></td>
                            <td class="panels"><?php
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
                                                } ?></td>
                            <td><?php echo tr_pdd_get_result_status($row->final_results) ?></td>
                            <td><?php echo tr_pdd_display_date($row->doc, 'Y-m-d H:i') ?></td>
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
                    background: rgba(255, 0, 0, 0.5) !important;
                }
            </style>
        <?php
        } else {
        ?>
            <div class="tablenav"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
            <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl" data-count="<?php echo @$count_rows ?>">
                <thead class="sticky">
                    <tr>
                        <th style="width:7%">#</th>
                        <th style="width: 7%;">Batch</th>
                        <th style="width: 54px;">Position</th>
                        <th style="width: 6%;">Lab</th>
                        <th style="width: 10%;">Patient Name</th>
                        <th style="width: 10%;">Clinic</th>
                        <th style="width: 9%;">Doctor</th>
                        <th style="width: 16%;">Missing Tests</th>
                        <th style="width: 80px;">Date</th>
                        <th style="width:30px"></th>
                        <th style="width:42px;">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $batches = array();
                    if (count($results) > 0) {
                        foreach ($results as $i => $row) {

                            if (!is_array($batches[$row->batch_id])) {
                                $batches[$row->batch_id] = array();
                            }

                            $batches[$row->batch_id]['date'] = $row->created_date;

                            $batches[$row->batch_id]['uploaded'] = $row->count_uploaded >= $row->count_result && $row->count_uploaded > 0;
                            $batches[$row->batch_id]['count_uploaded'] = $row->count_uploaded;
                            $batches[$row->batch_id]['count_result'] = $row->count_result;
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
                                <td><a class="show_batch_result_miss" data-batch_id="<?php echo $batch_id ?>"><?php echo $batch_id ?></a></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td><?php echo $d['date'] ?></td>
                                <td></td>
                                <td colspan="2">

                                </td>
                            </tr>
                            <tr class="child p_batch_<?php echo $batch_id ?>">
                                <td colspan="12" class="nopadding">
                                    <table class="report_tbl">
                                        <?php

                                        foreach ($d['labs'] as $lab_key => $row) {
                                            $lab_id = $row->lab_ID;
                                        ?>
                                            <tr class="parent_c" data-p="lab_<?php echo $lab_key . $batch_id ?>">
                                                <td style="width: 7%;"><i></i></td>
                                                <td style="width:7%"></td>
                                                <td style="width:54px"></td>
                                                <td style="width:6%"><?php echo $row->lab_dba_init ?></td>
                                                <td colspan="8" align="right">

                                                </td>
                                            </tr>
                                            <tr class="child_c p_lab_<?php echo $lab_key . $batch_id ?>" data-batch_id="<?php echo $batch_id ?>" data-ref_lab_id="<?php echo $row->ref_lab_ID ?>" data-lab_id="<?php echo $lab_id ?>">
                                                <td colspan="12" class="nopadding"></td>
                                            </tr>
                                        <?php
                                        }

                                        ?>
                                    </table>
                                </td>
                            </tr>
                        <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="12">No Results Found.</td>
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


<style>
    .report_tbl .dashicons-yes {
        color: #00a700 !important;
        font-size: 24px;
        padding: 4px 2px !important;
        float: right;
    }

    .report_tbl .dashicons-yes.orange {
        color: orange !important;
    }

    .dashicons.dashicons-warning {
        font-size: 18px;
        padding: 0 !important;
        line-height: 30px;
    }

    span.red {
        color: red;
    }

    span.yellow {
        color: #ffe74e;
    }

    .report_tbl tr.parent td>span,
    .tls>span {
        white-space: nowrap
    }
</style>
<script>
    jQuery(function($) {
        function trcpp_load_results($batch_id, $lab_id, $ref_lab_id) {
            var $obj = $('tr.p_lab_' + $lab_id + $ref_lab_id + $batch_id + ' td');
            if ($obj.html() != '') {
                return;
            } else {
                $obj.html('<div class="loading center"></div>');
            }
            $.ajax({
                data: {
                    tr_action: 'ci_load_results',
                    batch_id: $batch_id,
                    lab_id: $lab_id,
                    ref_lab_id: $ref_lab_id,
                    confirmation_pending: true
                },
                type: 'post',
                success: function(rs) {
                    var $html = $(rs);
                    $obj.html($html);
                    $('.cid', $html).each(function() {
                        $(this).prepend('<input type="checkbox" value="' + $(this).html() + '"/>');
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

        $('a.show_batch_result_miss').click(function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&width=800');
            return false;
        })
        $('body').on('click', 'a.show_batch_result_miss_back', function() {
            var $bid = $(this).data('batch_id');
            tb_show('Batch ' + $bid, '?tr_action=ci_show_batch_result_miss&batch_id=' + $bid + '&width=800');
            return false;
        })
        $('body').on('click', 'a.show_batch_result_miss_upload', function() {
            var $bid = $(this).data('batch_id');
            var $lab_id = $(this).data('lab_id');
            tb_show('miss upload', '?tr_action=ci_show_batch_result_miss_upload&batch_id=' + $bid + '&lab_id=' + $lab_id + '&width=800');
            return false;
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
                $('body,html').scrollTop($obj.offset().top - 50);
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
        $('tr.parent_c').click(function() {
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
            }
            return false;
        });
        $('.drugs').click(function() {
            $(this).parents('form').submit();
        })
        $old_active = getCookie('active_rs');
        if ($old_active && $old_active != null) {
            var $obj = $('tr.p_' + $old_active);
            try {
                $obj.show();
                $('[data-p="' + $old_active + '"]').data('showed', '1');
                if ($old_active.indexOf('lab_') > -1) {
                    $obj.parents('.child').show();
                }
                $('body,html').scrollTop($obj.offset().top - 150);
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