<?php
global $wpdb, $current_role;
include_once(TRCPDD_PATH . 'pages/formdata.php');


$pos_neg_vls = array(
    '' => '',
    'POS' => 'Positive',
    'NEG' => 'Negative',
    'POL' => 'Detected-Low',
    'POM' => 'Detected-Med',
    'POH' => 'Detected-High',
);

$from_date      = !empty($_REQUEST['from']) ? $_REQUEST['from'] : date_i18n('Y-m-d');
$to_date        = !empty($_REQUEST['to']) ? $_REQUEST['to'] : $from_date;
$testIDs        = isset($_REQUEST['tests']) ? $_REQUEST['tests'] : array();
$meds           = isset($_REQUEST['meds']) ? $_REQUEST['meds'] : array();
$onlypos        = isset($_REQUEST['onlypos']) && $_REQUEST['onlypos'];
$selected_labid = isset($_REQUEST['labid']) ? $_REQUEST['labid'] : '';

$cli_IDs        = array();
$cli_arr        = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : array();
foreach ($cli_arr as $cid) {
    if ($cid != 'all') {
        $cli_IDs[] = $cid;
    }
}

if (!empty($from_date)) {
    $from_date = date('Y-m-d', strtotime($from_date));
}

if (!empty($to_date)) {
    $to_date = date('Y-m-d', strtotime($to_date));
}

$orderby        = isset($_REQUEST['orderby']) && !empty($_REQUEST['orderby']) ? $_REQUEST['orderby'] : 'doc';
if ($orderby == 'patient_name') {
    $orderby = 'ba.patient_name';
} else if ($orderby == 'age') {
    $orderby = 'pa.pa_dob';
}

$btype_selected = isset($_REQUEST['btype']) && !empty($_REQUEST['btype']) ? $_REQUEST['btype'] : '';
if (empty($btype_selected)) {
    $btypes         = tr_pdd_get_batch_types();
    $btype_selected = array_shift($btypes);
}
if ($btype_selected == 'pcr') {
    $pos_neg_vls['POS'] = 'Detected';
    $pos_neg_vls['NEG'] = 'Not-Detected';
}
$clinics    = array();
$batch_tbl  = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);

$user_id = get_current_user_id();
$labs       = CI_Base_Lab::get_all_labs();

if ($act == 'view') {

    $btype = $btype_selected;
    ob_start();
    $id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
    $mid = isset($_REQUEST['mid']) ? intval($_REQUEST['mid']) : 0;
    if ($id == 0 && $mid > 0) {
        $id = $mid;
        $_REQUEST['id'] = $mid;
        $view_file = trpdd_get_file_path($btype_selected, 'req_form');
    } else {
        $view_file = trpdd_get_file_path($btype_selected, 'results_edit');
    }


    include($view_file);
    $content = ob_get_clean();


    $content = str_replace('<h2>Edit', '<h2>View', $content);
    $content = str_replace('<form', '<div', $content);
    $content = str_replace('</form>', '</div>', $content);
    $content = str_replace('<input type="submit"', '<input type="hidden"', $content);
    $content = str_replace('<textarea', '<textarea readonly=1 ', $content);
    $content = str_replace('<input type="radio"', '<input type="radio" disabled readonly=1 ', $content);
    $content = str_replace('<input type="checkbox"', '<input type="checkbox" disabled readonly=1 ', $content);
    $content = str_replace('<input', '<input readonly=1 ', $content);
    $content = str_replace('<select', '<select disabled readonly=1 ', $content);
    $content = str_replace('<a class="button"', '<a class="button" style="display:none!important" ', $content);
    echo $content;
    return;
}

switch ($current_role) {
    case 'lab_rep':
    case 'lab_rep_w2':

        $rep_id = get_user_meta($user_id, '_rep_id', true);
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                        where cli.cli_status = 1 and `cli_rep-ID`={$rep_id} order by cli_clinic asc");


        break;
    case 'doctor':
        $doc_ID = get_user_meta($user_id, '_doc_id', true);

        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
		                                join ci_clinic_doc cd ON cli.cID = cd.cli_ID
                                    where cli.cli_status = 1 and cd.doc_ID = {$doc_ID}
                                     order by cli.cli_clinic asc");
        break;

    case 'collector':
        $col_id = get_user_meta($user_id, '_collector_id', true);
        $cids = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID ={$col_id}");
        $cids = implode(',', $cids);
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 and cli.cID in ({$cids}) order by cli_clinic asc");
        break;

    case 'clinic':
        $cli_ID = get_user_meta($user_id, '_clinic_id', true);
        break;

    case 'office_manager':
    case 'multi_site_manager':
        $cid = get_user_meta($user_id, '_clinic_id', true);
        $cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");

        $cids = implode(',', $cids);
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 and cli.cID in ({$cids}) order by cli_clinic asc");

        break;

    default:
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic 
		                                    from ci_clinic cli
                                            where cli.cli_status = 1
                                            group by cli.cID
                                            order by cli_clinic asc");
        $show_all = true;
        break;
}

$order        = isset($_REQUEST['order']) && $_REQUEST['order'] == 'asc' ? 'asc' : 'desc';

$headers = array(
    'doc' => 'Collection',
    'assen' => 'Accession',
    'patient_name' => 'Patient Name',
    'pa_mrn' => 'MRN',
    'pa_dob' => 'DOB',
    'age' => 'Age',
    'pa_gender' => 'Gender',
    'phase' => 'Phase',
);
$reports        = array();
$test_objects   = array();
$selected_testnames = array();
$submited       = false;
$total_reports = 0;
if ($from_date && $to_date) {

    $submited       = true;
    $adding_tests   = array();

    $where = " ba.doc >= '{$from_date}' and ba.doc <='{$to_date} 23:59:59' ";
    if (count($cli_IDs) > 0 && !in_array('all', $cli_IDs)) {
        $cli_IDstr = implode(',', $cli_IDs);
        $where .= " and ba.cli_ID in ({$cli_IDstr}) ";
    }

    if (!empty($selected_labid)) {
        $is_ref = stripos($selected_labid, 'ref') !== false;
        $lid = str_replace('_ref', '', $selected_labid);
        if ($is_ref) {
            $where .= " and ( ba.ref_lab_ID = {$lid} ) ";
        } else {
            $where .= " and (ba.lab_ID = {$lid} and ba.ref_lab_ID = 0 ) ";
        }
    }

    if (count($testIDs) > 0) {
        //filter group ids
        $q_test_ids = array();
        foreach ($testIDs as $tk => $tid) {
            if (stripos($tid, 'GROUP_') !== false) {
                $gid = str_ireplace('GROUP_', '', $tid);
                $tids = $wpdb->get_col("select t.te_ID from ci_test_panels t 
                JOIN ci_test_groups g ON g.tg_ID = t.te_group_id
                where g.tg_ID = {$gid} or g.tg_parent = {$gid} ");
                foreach ($tids as $ti1) {
                    if (!in_array($ti1, $q_test_ids)) {
                        $q_test_ids[] = $ti1;
                    }
                }
            } else {
                $q_test_ids[] = $tid;
            }
        }
        $testIDstr = implode(',', $q_test_ids);
        $testarr = $wpdb->get_results("select te.te_ID, 
        te.te_name, te.te_abrev, te.te_abrev2,
        g.tg_ID, g.tg_only_group
        from ci_test_panels te
         JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
        where te_ID in ({$testIDstr}) and te_is_control = 0");

        $wheretests = array();
        foreach ($testarr as $tobj) {
            $selected_testnames[] = $tobj->te_name;

            $test_objects[$tobj->te_ID] = $tobj;
            $test_objects[strtolower($tobj->te_name)] = $tobj;

            if ($btype_selected == 'tox') {
                $wheretests[] = " FIND_IN_SET('{$tobj->te_name}', panel_tests_requested) ";
            } else if ($tobj->tg_only_group) {
                $wheretests[] = " FIND_IN_SET('GROUP_{$tobj->tg_ID}', panel_tests_requested) ";
            } else {
                $wheretests[] = " FIND_IN_SET('{$tobj->te_ID}', panel_tests_requested) ";
            }

            $headers['result_' . strtolower($tobj->te_name)] = $tobj->te_name;
        }

        $where .= " and (" . implode(' or ', $wheretests) . ") ";
    } else {
        $testarr = $wpdb->get_results("select te_ID,te_name, te_abrev, te_abrev2
        from ci_test_panels 
        where te_type = '{$btype_selected}' and te_in_req = 1 and te_is_control = 0");

        foreach ($testarr as $tobj) {
            $selected_testnames[] = $tobj->te_name;

            $test_objects[$tobj->te_ID] = $tobj;
            $test_objects[strtolower($tobj->te_name)] = $tobj;

            //$headers['result_' . strtolower($tobj->te_name)] = $tobj->te_name;
        }
    }


    if (count($meds) > 0 && $btype_selected == 'tox') {
        $wheremeds = array();
        foreach ($meds as $med) {
            $wheremeds[] = " FIND_IN_SET('{$med}', current_medication) ";
        }

        $where .= " and (" . implode(' or ', $wheremeds) . ") ";
    }

    if ($onlypos) {
        // $where .= " and re.has_pos > 0 ";
    }


    $sql = "select 
    ba.doc, 
    ba.assen, 
    ba.mid,
    ba.panel_tests_requested,
    pa.pa_first,
    pa.pa_last,
    pa.pa_dob,
    pa.pa_mrn,
    pa.pa_gender,
    CONVERT(me.meta_value, SIGNED) as phase,
    re.id,
    (select group_concat(drug,'|',conc,'|',`pos-neg`)  
        from {$result_tbl} where report_id = re.id group by report_id) as results
    from {$batch_tbl} ba
    JOIN {$report_tbl} re ON re.patient_id = ba.assen and re.estatus = 'C'
    JOIN ci_patients pa ON pa.pa_ID = ba.patient_id 
    LEFT JOIN {$batch_tbl}_meta me ON me.mid = ba.mid and me.meta_key='treatment'
    where {$where}
    group by ba.mid
    order by {$orderby} {$order}";

    if (isset($_GET['debug'])) {
        echo '<br><br>';
        echo $sql;
        echo '<br><br>';
    }

    $reports = $wpdb->get_results($sql);

    $total_reports = count($reports);

    foreach ($reports as $i => $row) {
        foreach ($headers as $k => $col) {
            if ($k == 'doc' || $k == 'pa_dob') {
                $row->{$k} = date('m/d/Y', strtotime($row->{$k}));
            } else if ($k == 'age') {
                $row->{$k} = trdd_dob2ages($row->pa_dob);
            } else if ($k == 'patient_name') {
                $row->{$k} = $row->pa_last . ', ' . $row->pa_first;
            }
        }

        $res    = explode(',', $row->results);

        $resultarr = array();
        foreach ($res as $re) {
            if (empty($re)) {
                if (isset($_GET['debug1'])) {
                    echo 'empty re1 <br>';
                }
                continue;
            }
            $a = explode('|', $re);
            $resultarr[strtolower($a[0])] = array($a[1], $a[2]);
        }
        $resarr = array();
        $testids = array();
        foreach (explode(',', $row->panel_tests_requested) as $t) {
            if (stripos($t, 'GROUP_') !== false) {
                //is group
                $gid = str_ireplace('GROUP_', '', $t);
                $group = $wpdb->get_row("select * from ci_test_groups where tg_ID = " . $gid);
                if ($group->tg_only_group) {
                    $sub_tests = $wpdb->get_col("select te_ID from ci_test_panels 
                    where te_group_id = {$gid} and te_is_control = 0 ");
                    foreach ($sub_tests as $st) {
                        if (!in_array($st, $testids)) {
                            $testids[] = $st;
                        }
                    }
                }
                continue;
            } else {
                $t = strtolower($t);
                $testids[] = $t;
            }
        }
        foreach ($testids as $t) {
            if (isset($test_objects[$t])) {
                if ($btype_selected != 'tox') {
                    $testobj = $test_objects[$t];
                    $tk = strtolower($testobj->te_name);
                    $drugkey = $tk;
                    if (!isset($resultarr[$drugkey]) && !empty($testobj->te_abrev)) {
                        $drugkey = strtolower($testobj->te_abrev);
                    }
                    if (!isset($resultarr[$drugkey]) && !empty($testobj->te_abrev2)) {
                        $drugkey = strtolower($testobj->te_abrev2);
                    }
                } else {
                    //tox
                    $drugkey = $t;
                }
            } else {
                if (isset($_GET['debug1'])) {
                    echo 'not found test ' . $t . ' <br>';
                }
                continue;
            }



            $rsarr = isset($resultarr[$drugkey]) ? $resultarr[$drugkey] : array();
            $pos_neg    = '';
            $conc       = '';
            if (empty($tk)) {
                continue;
            } else if (!empty($rsarr) && !isset($resarr[$drugkey])) {
                $pos_neg = isset($rsarr[1]) ? $rsarr[1] : '';
                $conc = isset($rsarr[0]) ? $rsarr[0] : '';
                if ($onlypos && !in_array($pos_neg, array('POS', 'POH', 'POM', 'POL', 'PVH', 'PVL'))) {
                    continue;
                }
                $resarr[$drugkey] = $t . ': ' . (isset($pos_neg_vls[$pos_neg]) ? $pos_neg_vls[$pos_neg] : $pos_neg);
            } else {
                continue;
            }
            if ($btype_selected == 'pcr') {
                $conc = $pos_neg_vls[$pos_neg];
            }
            $row->{'result_' . $tk} = $conc;
            $row->{'pos_neg_' . $tk} = $pos_neg;
        }

        if (count($resarr) == 0) {
            unset($reports[$i]);
            continue;
        }


        $reports[$i] = $row;
    }
}

if (isset($_REQUEST['trajax'])) {


    if ($_REQUEST['trajax'] == 'ajaxtable') {
        $return = array('status' => 'ok', 'html' => '', 'url' => '');
        ob_start();
        include(trpdd_get_file_path('datareport', 'positive_negative_report_table'));
        $return['html'] = ob_get_clean();
        $return['url'] = Tr_Base_Class_V6::link(array(), false);
        wp_send_json($return);
    } else if (stripos($_REQUEST['trajax'], 'export pdf') !== false) {

        $selected_clinicname = $wpdb->get_col("select cli_clinic from ci_clinic where cID in ({$cli_IDstr}) ");

        ob_start();
        $export_pdf = true;
?>
        <style>
            table {
                width: 100%;
                border-spacing: 0px;
                page-break-inside: auto;
                border-collapse: collapse;
            }


            table td {
                padding: 5px;
                vertical-align: top
            }

            table.nopadding td {
                padding: 0
            }

            table {
                border: 1px solid black;
                border-spacing: 0px;
                width: 100%;
            }

            table td,
            table th {
                border: 1px solid black;

            }

            .center {
                text-align: center;
            }
        </style>
        <h3 class="center">Positive/Negative Report</h3>
        <h4 class="center">Clinics: <?php echo implode(', ', $selected_clinicname) ?></h4>

<?php
        if (count($selected_testnames) > 0) {
            echo '<h4 class="center">Tests: ' . implode(', ', $selected_testnames) . '</h4>';
        }
        if (count($meds) > 0) {
            echo '<h4 class="center">Medications: ' . implode(', ', $meds) . '</h4>';
        }
        include(trpdd_get_file_path('datareport', 'positive_negative_report_table'));
        $html = ob_get_clean();


        $html = trpdd_pdf_html($html);
        //$html = strip_tags($html,'<html><body><script><table><thead><tbody><tr><th><td><br><p><span>');

        if (isset($_GET['html'])) {
            var_dump($html);
            exit;
        }

        $filename = "positive_negative_report.pdf";

        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

        header('Content-Type: application/pdf');

        if (!isset($_GET['view'])) {
            header("Content-Disposition: attachment; filename=\"$filename\";");
        }


        $temp_pdf = tempnam(WP_CONTENT_DIR . '/tmp', "exportpdf");
        try {

            trpdd_build_pdf($html, array('filepath' => $temp_pdf, 'orientation' => 'landscape'));
        } catch (Exception $e) {
            Tr_Session_Class::add_error("Error while export PDF . " . $e->getMessage());
            return false;
        }
        readfile($temp_pdf);
        unlink($temp_pdf);

        exit;
    } else if (stripos($_REQUEST['trajax'], 'export csv') !== false) {
        ob_clean();
        $file_name = "positive_negative_report.csv";

        header("Content-disposition: attachment; filename={$file_name}");
        header("Content-Type: text/csv");
        $f = fopen('php://output', 'w');

        fputcsv($f, $headers);

        foreach ($reports as $i => $row) {
            $rowdata = array();
            foreach ($headers as $k => $col) {
                $rowdata[] = $row->{$k};
            }
            fputcsv($f, $rowdata);
        }
    }
    $data_log = [
        'type' => 'Positive/Negative Report',
        'file_name' => $file_name,
        'total_records' => $total_reports,
        'from_date' => $from_date,
        'to_date' => $to_date,
    ];
    trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
    exit;
}


$all_panels = $wpdb->get_results("select tg_ID, tg_name 
from ci_test_groups 
where tg_type = '{$btype_selected}' 
and tg_parent = 0
and tg_show_req = 1
order by tg_name asc");

foreach ($all_panels as $i => $panel) {
    $subs = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups where tg_parent = {$panel->tg_ID} order by tg_name asc");
    if (count($subs) > 0) {
        foreach ($subs as $i2 => $sub) {
            $subs[$i2]->tests = $wpdb->get_results("select te_ID, te_name 
            from ci_test_panels 
            where te_group_id = {$sub->tg_ID} 
            and te_in_req = 1 
            and te_is_control = 0
            order by te_name asc");
        }
        $all_panels[$i]->subs = $subs;
    } else {
        $all_panels[$i]->tests = $wpdb->get_results("select te_ID, te_name 
        from ci_test_panels 
        where te_group_id = {$panel->tg_ID} 
        and te_in_req = 1 
        and te_is_control = 0
        order by te_name asc");
    }
}


$list_medications = array();
if ($btype_selected == 'tox') {
    $list_medications = tr_pdd_get_current_medications();
}



?>
<div class="wrap">
    <h2>Positive/Negative Report</h2>
    <?php Tr_Session_Class::show() ?>
    <br>
    <form method="get" id="form_search">
        <?php
        if (isset($_GET['debug'])) {
            echo '<input type="hidden" name="debug" value="1"/>';
        }
        ?>
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <div>
            Collection Date From: <input type="text" class="hasdate" name="from" style="width:100px" value="<?php echo @$from_date ?>" required />
            <label id="summarytodate">To:</label>
            <input type="text" class="hasdate today" name="to" style="width:100px" value="<?php echo @$to_date ?>" required />
            <select name="btype">
                <?php
                foreach (tr_pdd_get_batch_types() as $bt) {
                    if (in_array($bt, array('gene', 'study', 'dna'))) continue;
                    $selected = $bt == $btype_selected ? 'selected' : '';
                    echo '<option value="' . $bt . '" ' . $selected . '>' . $bt . '</option>';
                }
                ?>
            </select>
            <label><input type="checkbox" name="onlypos" value="1" <?php checked($onlypos) ?> /> Only Positive</label>
            <span style="display:inline-block">
                <input type="submit" class="button-primary" value="Report" />

                <input type="submit" name="trajax" value="Export CSV" class="button" />
                <input type="submit" name="trajax" value="Export PDF" class="button" />
            </span>
        </div>
        <div style="margin-top:15px">
            <label>Clinic: </label>
            <select name="cli_ID[]" id="clinic_select" class="js_select2" style="width:300px" multiple>
                <?php
                echo '<option value="all" selected>All Clinics</option>';
                foreach ($clinics as $cli) {
                    $selected = in_array($cli->cID, $cli_IDs) ? 'selected' : '';
                    echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
                }
                ?>
            </select>
            &nbsp;&nbsp;&nbsp;
            <?php if (count($labs) > 1) { ?>
                <label>Lab: </label>
                <select name="labid" class="js_select2">
                    <?php
                    echo '<option value="">All Labs</option>';
                    foreach ($labs as $lab) {
                        if ($lab->lab_is_ref == 1) {
                            $lab->lab_ID .= '_ref';
                        }
                        $selected = $selected_labid == $lab->lab_ID ? 'selected' : '';
                        echo '<option value="' . $lab->lab_ID . '" ' . $selected . '>' . $lab->lab_dba_init . '</option>';
                    }
                    ?>
                </select>
                &nbsp;&nbsp;&nbsp;
            <?php } ?>
            <label> Tests: </label>
            <select name="tests[]" class="js_select2" style="width:400px" multiple>
                <?php
                foreach ($all_panels as $panel) {

                    $group_id = 'GROUP_' . $panel->tg_ID;
                    $selected = in_array($group_id, $testIDs) ? 'selected' : '';
                    echo '<option value="' . $group_id . '" ' . $selected . '>Panel: ' . $panel->tg_name . '</option>';

                    if (isset($panel->subs)) {
                        foreach ($panel->subs as $subpanel) {
                            $group_id = 'GROUP_' . $subpanel->tg_ID;
                            $selected = in_array($group_id, $testIDs) ? 'selected' : '';
                            echo '<option value="' . $group_id . '" ' . $selected . '> - SubPanel: ' . $subpanel->tg_name . ' (' . $panel->tg_name . ')' . '</option>';
                            foreach ($subpanel->tests as $t) {
                                $selected = in_array($t->te_ID, $testIDs) ? 'selected' : '';
                                echo '<option value="' . $t->te_ID . '" ' . $selected . '> -- ' . $t->te_name . '</option>';
                            }
                        }
                    } else {
                        foreach ($panel->tests as $t) {
                            $selected = in_array($t->te_ID, $testIDs) ? 'selected' : '';
                            echo '<option value="' . $t->te_ID . '" ' . $selected . '> - ' . $t->te_name . '</option>';
                        }
                    }
                }
                ?>
            </select>
            <?php if (count($list_medications) > 0) { ?>
                &nbsp;&nbsp;&nbsp;
                <label> Medications: </label>
                <select name="meds[]" class="js_select2 editable" style="width:300px" multiple>
                    <?php
                    foreach ($meds as $med) {
                        if (!in_array($med, $list_medications)) {
                            $list_medications[] = $med;
                        }
                    }

                    foreach ($list_medications as $t) {
                        $selected = in_array($t, $meds) ? 'selected' : '';
                        echo '<option value="' . $t . '" ' . $selected . '>' . $t . '</option>';
                    }
                    ?>
                </select>
            <?php } ?>
        </div>


    </form>
    <br><br>

    <div id="show_table_report">
        <?php
        if ($submited) {
            echo '<div class="right">' . count($reports) . ' items</div>';
            include(trpdd_get_file_path('datareport', 'positive_negative_report_table'));
        }
        ?>
    </div>

</div>

<style>
    body .select2-container .select2-selection--single {
        height: 34px;
        min-height: 32px;
        border-radius: 4px;
    }

    .select2-container .select2-selection--multiple .select2-selection__rendered {
        display: block;
    }

    .widefat thead tr th {
        text-align: center;
    }

    table.resulttbl {
        width: 100%;
        margin: 0 auto;
    }

    table.resulttbl tr td {
        width: 33%;
    }
</style>

<script>
    var $main_url = '?page=<?php echo $_GET['page'] ?>';
    jQuery(function($) {
        var $tout = false;

        function submit_search() {
            $('#show_table_report').html('<div class="loading"></div>');


            $.ajax({
                url: $main_url,
                dataType: 'json',
                type: 'post',
                data: $('#form_search').serialize() + '&trajax=ajaxtable',
                success: function(json) {
                    if (json.status == 'ok') {
                        $('#show_table_report').html(json.html);
                        window.history.pushState('', '', json.url);
                    }

                },
                error: function() {
                    submit_search();
                }
            });

        }
        $('body').on('change', '#clinic_select', function() {
            var $this = $(this);
            if ($this.data('bchange')) return;
            $this.data('bchange', true);
            var $vls = $this.val();
            $countvl = 0;
            for (i in $vls) {
                if ($vls[i] != 'all') {
                    $countvl++;
                }
            }
            console.log($this.val());
            console.log($countvl);
            if ($countvl >= 1) {
                $('option[value="all"]', $this).prop('selected', false).change();
            } else {
                $('option[value="all"]', $this).prop('selected', true).change();
            }
            setTimeout(function() {
                $this.data('bchange', false);
            }, 50);

        }).on('change', '#form_search input, #form_search select', function() {
            var $cansubmit = true;
            if ($tout) {
                clearTimeout($tout);
            }
            $('#form_search input[required], #form_search select[required]').each(function() {
                if ($(this).val() == '') {
                    $cansubmit = false;
                }
            })
            if ($cansubmit) {
                $tout = setTimeout(function() {
                    $tout = false;
                    //$('#form_search').submit();
                    submit_search();
                }, 3000);

            }

        }).on('change', 'select[name="btype"]', function() {
            location.href = '?' + $('#form_search').serialize();
        })

    })
</script>