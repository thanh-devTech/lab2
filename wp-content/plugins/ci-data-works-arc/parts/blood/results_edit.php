<?php
global $wpdb, $report, $ci_cutoffs, $all_res;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$imagefiledir = WP_CONTENT_DIR . '/results/images/';
$imagefileurl = '/wp-content/results/images/';
if (!file_exists($imagefiledir)) {
    wp_mkdir_p($imagefiledir);
}



$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
    trpdd_plugin_activate();
}
$can_edit       = true;
$btype          = 'blood';
$table_batch    = 'ci_batches_blood';
$has_style2     = false;
$id             = isset($_GET['id']) ? $_GET['id'] : 0;

$user_id        = get_current_user_id();
$rs_settings    = get_option('ci_result_settings_blood', array());

$conditions     = trpdd_get_batch_conditions(0, 'blood');
$can_approve    = $conditions['can_approve_result'];

$report         = tr_pdd_get_report_by_id($id, $btype);
$mid            = $report['mid'];
$reflex_main_tests = tr_pdd_check_group_reflex($report, $btype);
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

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'mf_file') {
    $metadata = $wpdb->get_var("select comments from ci_results_blood 
    where report_id = {$id}
    and drug = '_mfdata' ");
    $metadata = json_decode($metadata, true);

    $file = isset($metadata['file']) ? $metadata['file'] : $report['assen'] . '.xlsx';
    $fileurl = '/wp-content/log/results_blood/' . $file;
    wp_redirect($fileurl);
    exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'reimport_mf') {
    $mf_data = array();
    $mfrow = $wpdb->get_row("select * from ci_results_blood where report_id = {$id}
                                        and drug = '_mfdata' ");

    if (!$mfrow) {
        $wpdb->insert('ci_results_blood', array('report_id' => $id, 'drug' => '_mfdata', 'has_order' => '0'));
        $mf_id = $wpdb->insert_id;
    } else {
        $mf_id = $mfrow->id;
        $mf_data = json_decode($mfrow->comments, true);
    }

    $meta = $_POST['data_results'][$mf_id]['comments'];
    foreach ($meta as $k => $vl) {
        $mf_data[$k] = $vl;
    }

    $wpdb->update('ci_results_blood', array('comments' => json_encode($mf_data)), array('id' => $mf_id));


    $return = array('status' => 'ok');
    //$_GET['debug1'] = $_REQUEST['debug1'] = true;

    $metadata = $wpdb->get_var("select comments from ci_results_blood 
    where report_id = {$id}
    and drug = '_mfdata' ");
    $metadata = json_decode($metadata, true);

    $file = isset($metadata['file']) ? $metadata['file'] : $report['assen'] . '.xlsx';
    $filepath = $log_dir . $file;
    $return['file'] = $file;

    if (file_exists($filepath)) {
        $files = array($file => array());
        include(trpdd_get_file_path('blood', 'results_import_mf'));
    }

    wp_send_json($return);
    exit;
}


if (!$report['id'] && isset($_GET['createnew'])) {
    $mid = $_GET['createnew'];

    $bat = $wpdb->get_row("select * from ci_batches_blood where mid = {$mid} ");

    if ($bat) {
        $id = $wpdb->get_var("select id from ci_reports_blood where patient_id='{$bat->assen}'");
        if ($id == 0) {
            if (empty($bat->batch_id)) {
                $bat->batch_id = tr_pdd_get_current_batch_id();
                $wpdb->update('ci_batches_blood', array('batch_id' => $bat->batch_id), array('mid' => $mid));
            }
            $_GET['add_covid_demo'] = true;
            $_GET['add_alltests'] = true;
            $data = array(
                'batch_id' => $bat->batch_id,
                'patient_id' => $bat->assen,
                'created_date' => date_i18n('Y-m-d'),
                'created' => date_i18n('Y-m-d H:i:s'),
            );
            $wpdb->insert('ci_reports_blood', $data);
            $id = $wpdb->insert_id;
        }

        $report = tr_pdd_get_report_by_id($id, $btype);
        echo '<meta http-equiv="refresh" content="1;url=' . Tr_Base_Class_V6::link(array('id' => $id, 'add_covid_demo' => true), false) . '" />';
        exit;
    }
}

if ($report['final_results'] == 0) {
    $report['final_results'] = 2;
}

if (!$report['id']) {
    echo '<h2>No Results Found!</h2>';
    return;
}
$style_num          = tr_get_option('ci_pdf_data', 'blood_pdf_style', 4);

if (isset($report['cli_result_style_blood']) && $report['cli_result_style_blood'] > 1) {
    $style_num = $report['cli_result_style_blood'];
}



$all_test_groups        = array();
$in_panel_tests_requested = array();
$group_ids = array();
foreach (explode(',', $report['panel_tests_requested']) as $t) {
    if (stripos($t, 'group') === false) {
        if ($t > 0) {
            $in_panel_tests_requested[] = $t;
        }
    } else {
        $group_ids[] = str_ireplace('group_', '', $t);
    }
}


$missing_tests  = $in_panel_tests_requested;
$test_ids         = implode(',', $in_panel_tests_requested);
$csv_files = explode(',', $report['upload_files']);

$csv_file = '/wp-content/log/results_blood/' . end($csv_files);
$req_form_url = 'admin.php?page=blood_req_form&act=edit&id=' . $report['mid'];

if (empty($test_ids)) {
    $test_ids = '-1';
}



$where_group     = '';
$group_ids_str     = implode(',', $group_ids);

$group_ids_str_w_childs = $wpdb->get_col("select tg_ID from ci_test_groups 
	where tg_ID in ({$group_ids_str}) or tg_parent in ({$group_ids_str}) ");

$group_ids_str = implode(',', $group_ids_str_w_childs);
if (empty($group_ids_str)) {
    $group_ids_str = '-1';
}

if (count($group_ids) > 0) {
    $where_group .= " and tg_ID in ({$group_ids_str}) ";
} else {
    $where_group .= ' and 1=2';
}

$sql = "SELECT tp.*, rs.*, st.ts_name
        FROM ci_test_panels tp
        left join ci_test_specimens st on tp.te_type_id = st.ts_ID and st.ts_type='blood'
        left join ci_results_blood rs ON (tp.te_name = rs.drug or tp.te_abrev = rs.drug or tp.te_abrev2 = rs.drug) 
                                    and rs.report_id= {$id} and (rs.group_id = 0 or rs.group_id is null or rs.group_id  = tp.te_group_id)
        WHERE  tp.te_type='blood' 
        and( 
            (
                (
                    rs.id > 0
                    OR
                    (
                        rs.id is null 
                        AND 
                        tp.te_group_id in (select tg_ID from ci_test_groups 
                                            where tg_type = 'blood' 
                                            and  tg_manual_edit=1 {$where_group} )
                     )
                ) 
                AND 
                (   
                    tp.te_ID in ({$test_ids}) 
                    OR ( tp.te_in_req = 1 and tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'blood' 
                                                and (tg_only_group = 1 or tg_notestcheckbox=1) {$where_group})
                        )
                 ) 
            ) 
            OR
            (
                tp.te_calculation!='' 
                and tp.te_calculation is not null
                and  tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'blood' 
                                             {$where_group})
            )
        )
        ";

$date_test = date('Y-m-d', strtotime($report['created']));
$sql .= " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
$sql .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";

$all_results_q = $wpdb->get_results($sql, ARRAY_A);

if (isset($_GET['debugtest'])) {
    echo $sql;
    echo '<br><br><br>';
}

$all_res = array();
foreach ($all_results_q as $arr) {
    if (empty($arr['drug'])) {
        $arr['drug'] = $arr['te_name'];
    }
    $arr['drug'] = strtoupper($arr['drug']);

    $all_res[$arr['te_ID']] = $arr;
}

if (tr_get_option('ci_pdf_data', 'api_all_tests_same_code')) {
    foreach ($all_res as $tid => $arr) {
        if (empty($arr['id'])) {
            if (empty($arr['ts_name'])) {
                $arr['ts_name'] = 'Serum';
            }
            foreach ($all_res as $tid2 => $arr2) {
                if ($tid == $tid2) continue;
                if (empty($arr2['ts_name'])) {
                    $arr2['ts_name'] = 'Serum';
                }
                if ($arr['te_name'] == $arr2['te_name'] && $arr['te_loinc_code'] == $arr2['te_loinc_code'] && $arr['ts_name'] == $arr2['ts_name'] && !empty($arr2['conc'])) {

                    //add new row
                    $wpdb->insert('ci_results_blood', array(
                        'report_id' => $id,
                        'drug' => $arr['drug'],
                        'group_id' => $arr['te_group_id'],
                        'conc' => $arr2['conc'],
                        'pos-neg' => $arr2['pos-neg'],
                        'instrument' => $arr2['instrument'],
                    ));

                    $all_res[$tid]['id'] = $wpdb->insert_id;
                    $all_res[$tid]['conc'] = $arr2['conc'];
                    $all_res[$tid]['pos-neg'] = $arr2['pos-neg'];
                    $all_res[$tid]['instrument'] = $arr2['instrument'];

                    break;
                }
            }
        }
    }
}

if (isset($_GET['add_alltests']) || isset($_GET['add_all_tests'])) {
    foreach ($all_res as $k => $arr) {
        if (empty($arr['conc'])) {
            $all_res[$k]['conc'] = 40;
        }
    }
}

$drug_groups = tr_pdd_get_drug_groups('blood', $group_ids, true);

if (isset($_GET['debug1'])) {
    echo '<br><br>';
    var_dump($drug_groups);
    echo '<br><br>';
}
if (empty($report['instrument'])) {
    //Tr_Session_Class::add_error("Empty Instrument Ranges");
}
$inew       = 0;
$has_pos    = '';
$has_manual_antibiotic = false;
foreach ($drug_groups as $groupid => $groupdata) {

    $te_IDs = isset($groupdata['te_IDs']) && is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();
    if ($groupdata['manual_antibiotic']) {
        $has_manual_antibiotic = true;
    }
    $groupdata['tests'] = array();

    foreach ($te_IDs as $te_ID) {

        $arr = isset($all_res[$te_ID]) ? $all_res[$te_ID] : array();

        if (in_array($groupdata['id'], $group_ids) && ($groupdata['only_group'] || $groupdata['notestcheckbox']) && !in_array($te_ID, $missing_tests)) {
            $missing_tests[] = $te_ID;
        }

        if (!empty($arr)) {

            $arr['showed'] = true;
            if (empty($arr['id'])) {
                $arr['id'] = 'new' . $inew++;
            }
            $arr = tr_pdd_parse_blood_test($arr, $report['instrument'], $groupdata['id'], $report);
            $all_res[$te_ID] = $arr;


            if ($arr['notfound']) {
                //Tr_Session_Class::add_error('Cutoff not found for : "'.$arr['drug'].'" ');
            } else {
                if ($has_pos < 1 && in_array($arr['pos-neg'], array('POS', 'POL', 'POM', 'POH', 'PVH', 'PVL'))) {
                    $has_pos = 1;
                } else if (in_array($arr['pos-neg'], array('PCH', 'PCL'))) {
                    $has_pos = 2;
                } else if ($has_pos === '' && $arr['pos-neg'] == 'NEG') {
                    $has_pos = 0;
                }
            }

            $groupdata['tests'][$arr['drug']] = $arr;

            if (in_array($arr['te_ID'], $missing_tests) && ($arr['conc'] !== '' || ($arr['pos-neg'] !== '' && $arr['pos-neg'] !== null))) {
                $idx_test = array_search($arr['te_ID'], $missing_tests);
                // var_dump($idx_test);
                //var_dump($arr);echo '<br><br>';
                unset($missing_tests[$idx_test]);
            }

            if (in_array($arr['te_ID'], $in_panel_tests_requested)) {
                $all_test_groups[] = $arr['te_name'];
            }
        }
    }

    $drug_groups[$groupid] = $groupdata;

    if ($groupdata['pdf_style'] > 0 && count($groupdata['tests']) > 0 && !$has_style2) {
        $style_num = $groupdata['pdf_style'];
        if ($style_num == 2) {
            $has_style2 = true;
        }
    }
}

$update_rp = array();
if (empty($missing_tests)) {
    $update_rp['is_pending'] = 0;
} else {
    $update_rp['is_pending'] = 1;
}

if ($has_pos !== '') {
    $update_rp['has_pos'] = $has_pos;
    $update_rp['has_pos_modified'] = time();
}

if (count($update_rp) > 0) {
    $wpdb->update('ci_reports_blood', $update_rp, array('id' => $report['id']));
}

if (isset($_REQUEST['debug'])) {
    var_dump(str_replace("\n", '<br>', $sql));
    //echo 'Batch:';var_export($batch);echo '<br><br>';
    var_dump($report['matrix']);
    var_dump($report['panel_tests_requested']);
    echo '<br><br>';
    echo 'Tests:';
    echo '<br><br>';
    var_export($in_panel_tests_requested);
    echo '<br><br>';
    echo '$all_res:';
    echo '<br><br>';
    var_export($all_res);
    echo '<br><br>';
    var_dump($drug_groups);
}


$pos_neg = array(
    '' => 'Select',
    'NEG' => 'Negative',
    'POS' => 'Positive',
    'PEN' => 'Pending',
    'INV' => 'Invalid',
    'CAN' => 'Test Cancelled',
);
$status_rejected = array();

if (isset($rs_settings['result_cancel_rejected']) && $rs_settings['result_cancel_rejected']) {
    $pos_neg['CAN'] = 'Cancel';
    $pos_neg['REJ'] = 'Rejected';
    $pos_neg['INC'] = 'Incomplete';

    $status_rejected = array('CAN', 'REJ', 'INC', 'INV');
}

$count_test_show = 0;
$count_all_test = count($in_panel_tests_requested);
$miss_test = array();
foreach ($in_panel_tests_requested as $t) {
    $t = strtoupper($t);
    $miss_test[$t] = $t;
}
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
</style>

<div class="wrap">

    <h2>Edit <small style="float:right">Style: <?php echo $style_num ?></small></h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" id="result_form" enctype="multipart/form-data">

        <div class="frow">
            <h4>Patient Info</h4>
        </div>

        <div class="frow">
            <div class="col4">
                <label title="<?php echo $report['mid'] ?>">Patient Name</label>
                <input type="text" value="<?php echo tr_pdd_capitalized($report['patient_name']) ?>" readonly>
            </div>
            <?php if (isset($rs_settings['show_questions']) && $rs_settings['show_questions']) {

            ?>
                <div class="col4">
                    <label>Married</label>
                    <input type="text" value="<?php echo $report['pa_marital_status'] ?>" readonly>
                </div>
            <?php
            } else {
            ?>
                <div class="col4">
                    <label>Physician &nbsp;&nbsp;&nbsp;<small><?php echo $report['doc_npi'] ?></small></label>
                    <input type="text" value="<?php echo $report['physician'] ?>" readonly>
                </div>
            <?php } ?>

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
                <input type="text" value="<?php echo $report['received_in_lab'] ?>" readonly>
            </div>
            <div class="col4">
                <label>Report Date</label>
                <input type="text" class="hasdatetime" name="info[approve_date]" value="<?php echo $report['approve_date'] ?>" />
            </div>
            <div class="col4">
                <label>Instrument<a target="_blank" href="<?php echo $csv_file ?>">.</a></label>
                <input type="text" name="info[instrument]" value="<?php echo $report['instrument'] ?>">
            </div>

        </div>
        <div class="frow">
            <div class="col4">
                <label>Fasting:</label>
                <input type="text" value="<?php echo $report['fasting'] ? 'Yes' : 'No' ?>">
            </div>
        </div>

        <?php

        if (isset($rs_settings['show_questions']) && $rs_settings['show_questions']) {
            $report['bmi'] = trpdd_get_bmi($report['pa_height'], $report['pa_weight']);

            $Pulse          = trpdd_get_batch_meta($report['mid'], 'blood', 'Pulse');

            $Pressure1      = trpdd_get_batch_meta($report['mid'], 'blood', 'Pressure1');
            $Pressure1_2    = trpdd_get_batch_meta($report['mid'], 'blood', 'Pressure1_2');
            $Arm            = trpdd_get_batch_meta($report['mid'], 'blood', 'Arm');

            $Pressure2      = trpdd_get_batch_meta($report['mid'], 'blood', 'Pressure2');
            $Pressure2_2    = trpdd_get_batch_meta($report['mid'], 'blood', 'Pressure2_2');
            $Arm2           = trpdd_get_batch_meta($report['mid'], 'blood', 'Arm2');

            $leftright = array('left' => 'Left Arm Sitting', 'right' => 'Right Arm Sitting');


            $Pressureavg = ($Pressure2 + $Pressure1) / 2;
            $Pressureavg2 = ($Pressure2_2 + $Pressure1_2) / 2;
        ?>
            <div class="frow">
                <div class="col4">
                    <label>Height</label>
                    <input type="text" value="<?php echo esc_attr($report['pa_height']) ?>" readonly>
                </div>
                <div class="col4">
                    <label>Weight</label>
                    <input type="text" value="<?php echo $report['pa_weight'] ?>" readonly>
                </div>
                <div class="col4">
                    <label>BMI</label>
                    <input type="text" value="<?php echo $report['bmi'] ?>">
                </div>

            </div>

            <div class="frow">

                <div class="col4">
                    <label>Pulse Rate:</label>
                    <input type="text" value="<?php echo $Pulse ?>" readonly>
                </div>
                <div class="col4">
                    <label>#1 Blood Pressure</label>
                    <input type="text" value="<?php echo $Pressure1 . '/' . $Pressure1_2 . '  ' . $leftright[$Arm] ?>">
                </div>
                <div class="col4">
                    <label>#2 Blood Pressure:</label>
                    <input type="text" value="<?php echo $Pressure2 . '/' . $Pressure2_2 . '  ' . $leftright[$Arm2] ?>">
                </div>
                <div class="col4">
                    <label>Ave Blood Pressure:</label>
                    <input type="text" value="<?php echo $Pressureavg . '/' . $Pressureavg2  ?>">
                </div>
            </div>
        <?php
            $only_display_questions = true;
            include('questions.php');
        }
        ?>
        <div class="header_fix_top"></div>
        <div class="header_fix" style="display: none;">
            <span><tt>P:</tt> <?php echo $report['patient_name'] ?> </span>
            <span><tt>D:</tt> <?php echo $report['physician'] ?></span>
            <span><tt>A:</tt> <a class="show_test_panels" href="#"><?php echo $report['patient_id'] ?></a></span>
            <span><tt>L:</tt> <?php echo $report['sublab'] ?></span>

            <span><tt>C:</tt> <?php echo $report['collection_date'] ?></span>
            <span><tt>M:</tt> <?php echo $report['medications'] ?></span>
        </div>
        <div class="list_test_panels" style="display: none;">
            <div title="<?php echo $count_all_test ?>">
                <div><?php echo implode(', ', $all_test_groups); ?></div>
            </div>
        </div>


        <?php

        if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
            $rejected = tr_pdd_get_batch_statuses($report['batch_status'], false, false, 'blood');
        ?>
            <h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
            <div style="text-align: center;"><?php echo $report['billing_notes'] ?></div>
            <?php
        } else {


            if ($has_manual_antibiotic) {

                $vlkeys = array(
                    '' => '',
                    'I' => 'I',
                    'R' => 'R',
                    'S' => 'S'
                );

                $anti_pathogen = array();
                foreach ($drug_groups as $group => $groupdata) {
                    if ($groupdata['manual_antibiotic']) {
                        foreach ($groupdata['tests'] as $arr) {
                            if (empty($arr['te_name']) || empty($arr['te_ID'])) continue;
                            $anti_pathogen[$arr['te_name']] = $arr['te_ID'];
                        }
                    }
                }
                //more pathgeno
                $morepathogens = $wpdb->get_results("select te_ID,te_name from ci_test_panels where te_type='cupat'");
                foreach ($morepathogens as $r) {
                    $anti_pathogen[$r->te_name] = $r->te_ID;
                }
                ksort($anti_pathogen);

                $repdb = $wpdb->get_row("select * from ci_reports_blood where id = {$report['id']}");
                $show3pathogen_note = false;
                $shownogrowth_note = false;
                if ($repdb) {
                    $show3pathogen_note = $repdb->show3pathogen_note;
                    $shownogrowth_note = $repdb->shownogrowth_note;
                }

                $antibiotics = $wpdb->get_results("select te_ID as ID,
			                                            te_name  as ta_name
			                                        from ci_test_panels 
                                                    where te_type='cuant'
                                                    order by te_name asc");


                $anti_rs = $wpdb->get_results("select ra.*,te.te_name
                                                from ci_results_antibiotics ra
                                                join ci_test_panels te ON te.te_ID = ra.te_ID
                                                where report_id = {$id}
                                                and btype = 'blood'
                                                order by te_name asc");

                $labresults = $wpdb->get_results("select ra.*,te.te_name
                                            from ci_results_labresults ra
                                            join ci_test_panels te ON te.te_ID = ra.te_ID
                                            where report_id = {$id}
                                                order by te_name asc ");


                $anti_data = array();
                foreach ($anti_rs as $r) {
                    $kt = 'k' . $r->te_ID;
                    if (!isset($anti_data[$kt])) {
                        $anti_data[$kt] = array(
                            'te_ID' => $r->te_ID,
                            'name' => $r->te_name,
                            'rs' => array()
                        );
                    }
                    $anti_data[$kt]['rs'][$r->ta_ID] = array($r->result, $r->mic_value);
                }

                foreach ($labresults as $r) {
                    $kt = 'k' . $r->te_ID;
                    if (!isset($anti_data[$kt])) {
                        $anti_data[$kt] = array(
                            'te_ID' => $r->te_ID,
                            'name' => $r->te_name,
                            'rs' => array()
                        );
                    }
                    $anti_data[$kt]['labresult'] = $r->result;
                }

            ?>
                <div class="frow">
                    <h4>Antibiotics</h4>
                </div>

                <select id="list_pathogen"></select>

                <a class="button add_antibiotics">+</a>
                <label style="padding-left: 16px;">
                    <input type="hidden" name="info[show3pathogen_note]" value="0" />
                    <input type="checkbox" name="info[show3pathogen_note]" value="1" <?php checked($show3pathogen_note) ?> /> 3 or more pathogen note
                </label>
                <label style="padding-left: 16px;">
                    <input type="hidden" name="info[shownogrowth_note]" value="0" />
                    <input type="checkbox" name="info[shownogrowth_note]" value="1" <?php checked($shownogrowth_note) ?> /> No Growth
                </label>
                <div id="display_antibiotics" style="margin:10px 0;display:none"></div>
                <input type="hidden" name="has_antibiotics" value="1" />
                <script>
                    var $vlkeys = <?php echo json_encode($vlkeys) ?>;
                    var $anti_pathogen = <?php echo json_encode($anti_pathogen) ?>;
                    var $antibiotics = <?php echo json_encode($antibiotics) ?>;
                    var $anti_data = <?php echo json_encode($anti_data) ?>;
                    var $symbols = ['>', '<', '>=', '<='];

                    jQuery(function($) {
                        function tr_fill_antibio_drugcol() {
                            var $html = '<div class="wraptbl"><table class="tbl_results">';
                            $html += '<thead class="sticky top2 style2"><tr class="group_header"><th class="drugs_col" style="width:20%">DRUGS</th>';
                            $html += '</tr></thead>';
                            $html += '<tr class="rowdata labvalue"><td class="drugs_col"><b>Lab Result</b></td>';
                            $html += '</tr>';
                            for (var j in $antibiotics) {
                                var $ta_ID = $antibiotics[j].ID;
                                var $ta_name = $antibiotics[j].ta_name;
                                $html += '<tr class="rowdata" data-taid="' + $ta_ID + '"><td class="drugs_col">' + $ta_name + '</td>';
                                $html += '</tr>';
                            }
                            $html += '</table></div>';
                            $('#display_antibiotics').append($html);

                        }

                        function tr_fill_antibio_col($data) {
                            var $header = '<th data-teid="' + $data.te_ID + '" class="te_head">' + $data['name'] + '<br><a class="removecol button">-</a></th>';
                            $('#display_antibiotics tr.group_header').append($header);

                            //add labresult
                            var $lrs = '<td data-teid="' + $data.te_ID + '" class="te_row">';
                            $lrs += '<input type="text" name="labresult[' + $data.te_ID + ']" value="' + ($data.labresult || '') + '" style="width:49%"/>';
                            $lrs += '<div style="width:50%;display:inline-block" class="center">Mic Data</div>';
                            $lrs += '</td>';
                            $('#display_antibiotics tr.rowdata.labvalue').append($lrs);

                            for (var j in $antibiotics) {
                                var $ta_ID = $antibiotics[j].ID;
                                var $itemdata = $data.rs[$ta_ID] ? $data.rs[$ta_ID] : '';
                                var $dresult = $itemdata ? $itemdata[0] : '';
                                var $mic_vl = $itemdata ? $itemdata[1] : '';

                                var $nrow = 'antibioticsdata[' + $data.te_ID + '][' + $ta_ID + ']';
                                var $html = '<td data-teid="' + $data.te_ID + '" class="te_row">';
                                $html += '<select name="' + $nrow + '" style="width:49%">';
                                for (var k in $vlkeys) {
                                    var $selected = k == $dresult ? 'selected' : '';
                                    $html += '<option value="' + k + '" ' + $selected + '>' + $vlkeys[k] + '</option>';
                                }
                                $html += '</select>';

                                var $nrowmic = 'micdata[' + $data.te_ID + '][' + $ta_ID + ']';
                                $html += '<div style="width:49%;display:inline-block;margin-left:1%">';
                                $html += '<select name="symbol_' + $nrowmic + '" style="width:46%;"><option value=""></option>';
                                for (isb in $symbols) {
                                    var sb = $symbols[isb];
                                    var sbt = sb;
                                    if (sbt == '>=') {
                                        sbt = '&ge;';
                                    } else if (sbt == '<=') {
                                        sbt = '&le;';
                                    }
                                    var $selected = '';
                                    if ($mic_vl.indexOf(sb) == 0 && (sb.length == 2 || (sb.length == 1 && $mic_vl.indexOf('=') !== 1))) {
                                        $selected = 'selected';
                                        $mic_vl = $mic_vl.replace(sb, '');
                                    }


                                    $html += '<option value="' + sb + '" ' + $selected + '>' + sbt + '</option>';
                                }
                                $html += '</select>';
                                $html += '<input type="text" name="' + $nrowmic + '" value="' + $mic_vl + '" style="width:49%"/>';
                                $html += '</div>';
                                $html += '</td>';
                                $('#display_antibiotics tr.rowdata[data-taid="' + $ta_ID + '"]').append($html);
                            }
                            $('#display_antibiotics').show();
                        }

                        function tr_fill_pathogen_dd() {
                            var $html = '<option value="">Select Pathogen</option>';
                            for (var tname in $anti_pathogen) {
                                $html += '<option value="' + $anti_pathogen[tname] + '">' + tname + '</option>';
                            }
                            $('#list_pathogen').html($html);
                        }
                        $('body').on('click', '.add_antibiotics', function() {
                            var $vl = $('#list_pathogen').val();
                            if ($vl == '') return false;
                            var $opt = $('#list_pathogen option[value="' + $vl + '"]');
                            var $tname = $opt.html();
                            tr_fill_antibio_col({
                                'name': $tname,
                                'te_ID': $vl,
                                'rs': []
                            });
                            $opt.remove();
                            $('#list_pathogen').val('');
                        }).on('click', '#display_antibiotics .removecol', function() {
                            if (confirm("Do you want remove?") == false) return false;
                            var teid = $(this).parent().data('teid');
                            $(this).parent().remove();
                            $('#display_antibiotics td.te_row[data-teid="' + teid + '"]').remove();
                            if ($('#display_antibiotics tr.group_header th').length <= 1) {
                                $('#display_antibiotics').hide();
                            }
                        });
                        tr_fill_pathogen_dd();
                        tr_fill_antibio_drugcol();
                        for (var tkey in $anti_data) {
                            var item = $anti_data[tkey];
                            tr_fill_antibio_col(item);
                            var $opt = $('#list_pathogen option[value="' + item.te_ID + '"]');
                            $opt.remove();
                        }
                    })
                </script>
            <?php
            }
            ?>


            <div class="frow">
                <h4>&nbsp;</h4>
            </div>


            <?php
            $show_group_parents = array();
            foreach ($drug_groups as $groupid => $groupdata) {
                ob_start();
                foreach ($groupdata['tests'] as $arr) {
                    $count_test_show++;
                    unset($miss_test[$arr['drug']]);

                    $arr['conc_dis'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];


                    if (isset($arr['cal_missing']) && $arr['cal_missing']) {
                        continue;
                    }


            ?>
                    <tr class="result_row">
                        <td title="<?php echo @$arr['te_ID'] . $arr['pos-neg'] ?>">
                            <input type="text" <?php if (stripos($arr['id'], 'new') !== false) echo 'name="data_results[' . $arr['id'] . '][drug]"' ?> value="<?php echo !empty($arr['te_name']) ? strtoupper($arr['te_name']) : $arr['drug'] ?>" readonly />
                            <input type="hidden" <?php if (stripos($arr['id'], 'new') !== false) echo 'name="data_results[' . $arr['id'] . '][group_id]"' ?> value="<?php echo $groupid ?>" readonly />
                        </td>
                        <td>
                            <?php
                            if (is_array($arr['pos_neg_list']) && count($arr['pos_neg_list']) > 0) {
                            ?>
                                <select class="pos_neg" name="data_results[<?php echo $arr['id'] ?>][pos-neg]" data-value="<?php echo $arr['pos-neg'] ?>" <?php if (isset($arr['is_blue']) && $arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>>
                                    <?php
                                    echo '<option value="">Select</option>';
                                    foreach ($arr['pos_neg_list'] as $k => $vl) {
                                        $selected = $k == $arr['pos-neg'] ? 'selected="selected"' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                    }
                                    ?>
                                </select>
                            <?php
                            }
                            ?>
                        </td>

                        <td class="center" style="padding-left:2px">
                            <?php
                            if (in_array($arr['te_is_detected'], array('7', '8')) && isset($arr['values']) && count($arr['values']) > 0 && count($arr['values']) <= 3) {
                                foreach ($arr['values'] as $k => $vl) {
                                    $checked = ($k == $arr['conc']) ? 'checked="checked"' : '';
                            ?>
                                    <label style="margin-right:10px;display:inline-block">
                                        <input type="radio"
                                            class="conc_radio"
                                            name="data_results[<?php echo $arr['id'] ?>][conc]"
                                            value="<?php echo $k ?>"
                                            <?php echo $checked ?> />
                                        <?php echo $vl ?>
                                    </label>
                                <?php
                                }
                            } else if (isset($arr['values']) && count($arr['values']) > 0) {
                                ?>
                                <select class="pos_neg" name="data_results[<?php echo $arr['id'] ?>][conc]">
                                    <?php
                                    echo '<option value="">Select</option>';
                                    foreach ($arr['values'] as $k => $vl) {
                                        $selected = $k == $arr['conc'] ? 'selected="selected"' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                    }
                                    ?>
                                </select>
                            <?php
                            } else { ?>
                                <input type="text" class="conc" name="data_results[<?php echo $arr['id'] ?>][conc]" value="<?php echo $arr['conc_dis'] ?>" />
                            <?php } ?>
                        </td>
                        <td class="center">
                            <?php echo isset($arr['range']) ? trim(trpdd_show_symbol($arr['range'])) : '' ?>
                        </td>

                        <td data-did="<?php echo isset($arr['drug_comment_id']) ? $arr['drug_comment_id'] : '' ?>">
                            <?php
                            if (isset($rs_settings['result_cancel_rejected']) && $rs_settings['result_cancel_rejected']) {
                                foreach ($status_rejected as $forkey) {
                                    $rjcomments = $wpdb->get_var("select meta_value from ci_test_meta 
	                                                    where test_id='-1' 
	                                                    and meta_key='comments_{$forkey}' ");
                                    $rjcomments = !empty($rjcomments) ? json_decode($rjcomments, false) : array();
                                    if (is_array($rjcomments) && count($rjcomments) > 0) {
                            ?>
                                        <select class="rejected_comments rejected_comments_<?php echo $forkey ?>" style="display:none">
                                            <option value="">Select</option>
                                            <?php
                                            foreach ($rjcomments as $rjc) {
                                                echo '<option value="' . $rjc . '">' . $rjc . '</option>';
                                            }
                                            ?>
                                        </select>
                            <?php
                                    }
                                }
                            }
                            ?>
                            <textarea class="commentfield" name="data_results[<?php echo $arr['id'] ?>][comments]" data-posc="<?php echo @$arr['posc'] ?>"><?php echo $arr['comments'] ?></textarea>
                            <?php
                            if (isset($_GET['debug2'])) {
                                var_dump($arr);
                            }
                            ?>
                        </td>
                    </tr>
                <?php
                }

                $drug_content = ob_get_clean();


                if (!empty($drug_content)) {

                    if ($groupdata['parent'] > 0 && !in_array($groupdata['parent'], $show_group_parents)) {
                        $parentgname = $drug_groups[$groupdata['parent']]['name'];
                        echo '<div class="frow"><h4 class="center">' . $parentgname . '</h4></div>';
                        $show_group_parents[] = $groupdata['parent'];
                    }
                ?>
                    <table style="width:100%" class="tbl_results">
                        <tr class="group_header">
                            <th style="min-width:200px" title="<?php echo $groupdata['id'] ?>"><strong><?php echo htmlspecialchars($groupdata['name']) ?></strong></th>
                            <th style="min-width:100px"><strong>Result</strong></th>
                            <th style="min-width:100px;width:200px"><strong>Value</strong></th>
                            <th style="min-width:100px"><strong>Range</strong></th>
                            <th style="max-width:500px;width:28%"><strong>Comments</strong></th>
                        </tr>
                        <?php
                        echo $drug_content;
                        if ($groupdata['name'] == 'Mechanical Fragility') {
                        ?>
                            <tr>
                                <td colspan="5">
                                    <div class="frow">
                                        <h4 class="center">NanoDrop <a target="_blank" href="<?php Tr_Base_Class_V6::link(array('trajax' => 'mf_file')) ?>">.</a></h4>
                                    </div>
                                    <?php
                                    if ($rs_settings['result_import_mf']) {

                                        $mf_fields      = array('i12' => 1, 'j12' => 100, 'k12' => 2, 'l12' => 200, 'm12' => 3, 'n12' => 300);
                                        $vmax_fields    = array('f9' => 'Hb', 'k3' => 'Zero (undiluted)');
                                        $mf_data        = array();
                                        $mfrow          = $wpdb->get_row("select * from ci_results_blood where report_id = {$id}
                                                            and drug = '_mfdata' ");
                                        if (!$mfrow) {
                                            $wpdb->insert('ci_results_blood', array('report_id' => $id, 'drug' => '_mfdata', 'has_order' => '0'));
                                            $mf_id = $wpdb->insert_id;
                                        } else {
                                            $mf_id = $mfrow->id;
                                            $mf_data = json_decode($mfrow->comments, true);
                                        }

                                        if (isset($_GET['debug1'])) {
                                            var_dump($mf_data);
                                        }
                                    ?>
                                        <div style="width:300px;margin:0 auto">
                                            <?php
                                            if (!empty($mf_data['auto_excl_tube'])) {
                                                echo '<div class="center">';
                                                echo 'Auto Exclude Tube ' . $mf_data['auto_excl_tube'];
                                                echo '</div>';
                                            }

                                            ?>
                                            <select name="<?php echo 'data_results[' . $mf_id . '][comments][excl_tube]' ?>">
                                                <option value="">Select Exclude Tube:</option>
                                                <?php
                                                for ($i = 1; $i <= 3; $i++) {
                                                    $selected = $mf_data['excl_tube'] == $i ? 'selected' : '';
                                                    echo '<option value="' . $i . '" ' . $selected . '>Tube ' . $i . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <table class="wp-list-table widefat fixed striped posts" style="margin:10px auto;width:500px">
                                            <thead>
                                                <tr>
                                                    <?php
                                                    foreach ($vmax_fields as $mff) {
                                                        echo '<th class="center">' . $mff . '</th>';
                                                    }
                                                    ?>
                                                    <th>Dilution Calculation</th>
                                                </tr>
                                                <tr>
                                            </thead>
                                            <tr>
                                                <?php
                                                foreach ($vmax_fields as $vk => $mff) {
                                                    echo '<td><input type="text" id="field_' . $vk . '" data-required="true" name="data_results[' . $mf_id . '][comments][' . $vk . ']" value="' . (isset($mf_data[$vk]) ? $mf_data[$vk] : '') . '"></td>';
                                                }

                                                echo '<td><input type="text" data-calculator="3000*0.5/[field_f9]" readonly value="';
                                                if (($mf_data['f9']) > 0) echo round(3000 * 0.5 / $mf_data['f9'], 3);
                                                echo '"></td>';
                                                ?>
                                            </tr>
                                        </table>

                                        <?php
                                        if (!empty($mf_data['rerunby'])) {
                                            echo '<div class="center" style="color:red">';
                                            echo $mf_data['rerunby'];
                                            echo '</div>';
                                        }

                                        ?>

                                        <br>

                                        <table class="wp-list-table widefat fixed striped posts" style="margin:10px auto;width:500px">
                                            <thead>
                                                <tr>
                                                    <?php
                                                    foreach ($mf_fields as $mff) {
                                                        echo '<th class="center">' . $mff . '</th>';
                                                    }
                                                    ?>
                                                </tr>
                                                <tr>
                                            </thead>
                                            <tr>
                                                <?php
                                                foreach ($mf_fields as $mfk => $mff) {
                                                    echo '<td><input type="text" data-required="true" name="data_results[' . $mf_id . '][comments][' . $mfk . ']" value="' . (isset($mf_data[$mfk]) ? $mf_data[$mfk] : '') . '"></td>';
                                                }
                                                ?>
                                            </tr>
                                        </table>

                                        <div class="frow center">
                                            <a class="center button recalculate_btn">Recalculate</a>
                                        </div>
                                        <script>
                                            jQuery(function($) {
                                                $('a.recalculate_btn').click(function() {
                                                    var $this = $(this);
                                                    if ($this.html() != 'Recalculate') {
                                                        return false;
                                                    }
                                                    $this.html('Calculating...');
                                                    $.ajax({
                                                        data: $('form#result_form').serialize() + '&trajax=reimport_mf',
                                                        type: 'post',
                                                        dataType: 'json',
                                                        success: function(json) {
                                                            $this.html('Reloading....');
                                                            location.reload();
                                                        }
                                                    });
                                                    return false;
                                                })
                                            })
                                        </script>
                                        <br>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                    </table>
                <?php
                }
            }
            if ($groupdata['name'] == 'OncoSure') {

                $oncosurefile = $report['assen'] . '_oncosure.png';
                ?>
                <div class="frow">
                    <h4>Probe(s) located on Oncosome(s) </h4>
                </div>
                <div class="frow">
                    <div class="card_file">
                        <a class="button selectfile">Choose Image</a>

                        <?php
                        if (!empty($oncosurefile) && file_exists($imagefiledir . $oncosurefile) && filesize($imagefiledir . $oncosurefile) > 0) {
                            if (filesize($imagefiledir . $oncosurefile) > 1000000) {
                                trpdd_get_resized_image($imagefiledir . $oncosurefile, 500, 500, true);
                            }
                            echo '<span>';
                            echo '<img src="' . $imagefileurl . $oncosurefile . '?r=' . time() . '" style="width:300px">';
                            echo '</span>';
                            echo ' <a class="button remove">Remove</a>';
                        } else {
                            echo '<span></span>';
                        }
                        ?>
                        <input type="file" name="resultimages[oncosure]" accept="image/*" />
                        <input type="hidden" name="resultimages[oncosure]" value="1" />
                    </div>
                </div>
        <?php
            }
        }
        ?>

        <?php

        if (isset($rs_settings['show_questions']) && $rs_settings['show_questions']) {
            $wellness_score = trpdd_get_batch_meta($report['mid'], 'blood', 'wellness_score');
            $heart_disease_risk = trpdd_get_batch_meta($report['mid'], 'blood', 'heart_disease_risk');
            $type2_diabetes_risk = trpdd_get_batch_meta($report['mid'], 'blood', 'type2_diabetes_risk');
        ?>
            <div class="frow">
                <div class="col3">
                    <h4>Wellness Score</h4>
                    <select name="batmeta[wellness_score]">
                        <?php
                        foreach (array('A', 'B', 'C', 'D', 'E', 'F') as $k) {
                            $selected = $k == $wellness_score ? 'selected' : '';
                            echo '<option value="' . $k . '" ' . $selected . '>' . $k . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col3">
                    <h4>Heart Disease Risk</h4>
                    <select name="batmeta[heart_disease_risk]">
                        <?php
                        foreach (array('' => 'Select', 'L' => 'Low', 'M' => 'Medium', 'H' => 'High') as $k => $txt) {
                            $selected = $k == $heart_disease_risk ? 'selected' : '';
                            echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col3">
                    <h4>Type 2 Diabetes Risk</h4>
                    <select name="batmeta[type2_diabetes_risk]">
                        <?php
                        foreach (array('' => 'Select', 'L' => 'Low', 'M' => 'Medium', 'H' => 'High') as $k => $txt) {
                            $selected = $k == $type2_diabetes_risk ? 'selected' : '';
                            echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>


            <?php
            $comment_fields = array(
                'medication_review' => 'Medication Review',
                'symptom_review' => 'Symptom Review',
                'family_history_review' => 'Family History Review',
                'lifestyle_review' => 'Lifestyle Review',
                'vital_bmi_review' => 'Vitals & BMI Review',
                // 'body_mass_index' => 'Body Mass Index ',
            );

            foreach ($comment_fields as $k => $txt) {
            ?>
                <div class="frow">
                    <h4><?php echo $txt ?></h4>
                    <textarea name="batmeta[<?php echo $k ?>]" style="height:50px"><?php echo trpdd_get_batch_meta($report['mid'], 'blood', $k); ?></textarea>
                </div>
        <?php
            }
        }
        ?>

        <div class="frow" title="<?php echo $count_test_show ?>" data-miss="<?php echo implode(',', $miss_test) ?>">
            <h4>Notes (Show on Report)</h4>
            <textarea name="updatebat[notes_report]"><?php echo $report['notes_report'] ?></textarea>
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
            <div class="col2">
                <label>Status</label>
                <?php
                $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'blood'), $report['batch_status'], $report['mid'], $table_batch, 'blood');
                $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('blood', 'show_in_result_form', $report['batch_status']);
                tr_select_option('updatebat[batch_status]', $status_options, @$report['batch_status'], ['disabled_options' => $disabled_keys]);
                ?>
            </div>
        </div>

        <div class="frow">
            <div class="col2">
                <label style="margin-right:20px"><input type="radio" name="info[final_results]" value="0" <?php checked($report['final_results'], '0') ?> /> None </label>
                <label style="margin-right:20px"><input type="radio" name="info[final_results]" value="2" <?php checked($report['final_results'], '2') ?> /> Preliminary </label>
                <label style="margin-right:20px"><input type="radio" name="info[final_results]" value="1" <?php checked($report['final_results'], '1') ?> /> Final </label>
                <label><input type="radio" name="info[final_results]" value="3" <?php checked($report['final_results'], '3') ?> /> Amended Report </label>
            </div>


        </div>
        <br>


        <div class="frow">
            <?php
            $sql = "select 
                    g.tg_ID,
                    g.tg_popup_approve
                from ci_test_groups g
                where g.tg_type = 'blood' ";

            $rs = $wpdb->get_results($sql);
            $pop_msg = '';
            foreach ($rs as $r) {
                if (strlen($r->tg_popup_approve) > 10 && in_array($r->tg_ID, $group_ids)) {
                    $pop_msg = $r->tg_popup_approve;
                }
            }

            $next = tr_pdd_next_result($id, $report['batch_id'], $report['lab_id'], 'blood');

            echo ' <input type="submit" name="submit" value="Submit" class="button-primary" /> ';
            if ($next > 0) {
                echo ' <input type="submit" name="submit_next" value="Submit & Next" class="button"> ';
            }

            if ($can_approve && $report['estatus'] != 'C') {
                echo ' <input type="submit" name="approval" value="Approve" class="button approve_btn"> ';
            }

            if ($next > 0) {
                $next_url = Tr_Base_Class_V6::link(array('id' => $next), false);
            ?>
                <input type="hidden" name="next_link" value="<?php echo $next_url ?>" />
                <?php if ($can_approve) { ?>
                    <input type="submit" name="approval_next" value="Approve & Next" class="button approve_btn">
                <?php } ?>
                <a href="<?php echo $next_url ?>" class="button">Next</a>
            <?php
            }
            ?>

            <a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $report['id'], 'btype' => 'blood', 'view' => 1, 'act' => '', 'nocache' => 1)) ?>">PDF</a>

            <input type="hidden" name="tr_action" value="ci_save_result_edit" />
            <input type="hidden" name="btype" value="blood" />
            <input type="hidden" id="pop_msg" value="<?php echo $pop_msg ?>" />

            <?php trpdd_form_unique_id() ?>
        </div>
    </form>
</div>

<?php include(trpdd_get_file_path('global', 'report_history')); ?>

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

    th.center {
        text-align: center;
    }
</style>
<script>
    var $status_rejected = <?php echo json_encode($status_rejected) ?>;
    var $count_missing_test = <?php echo intval(count($miss_test)) ?>;
    jQuery(function($) {
        function tr_has_missing() {
            if ($count_missing_test > 0) return true;
            var $has_missing = false;
            $('tr.result_row').each(function() {
                var $tr = $(this);
                var $pos_neg = $('select.pos_neg', $tr).val();
                var $conc = $('input.conc', $tr).val();
                if ($conc == '' && ($pos_neg == '' || $pos_neg == 'PEN')) {
                    $has_missing = true;
                }
            })
            return $has_missing;
        }
        $('body').on('change', '.specimen_outcome', function() {
            var $this = $(this);
            var $cm = $this.parents('.specimen_row').find('.specimen_comments');
            if ($this.val() == 'ABNORMAL') {
                var $vl = $(this).data('comments');
                if ($vl.indexOf('||') > -1) {
                    var $html = '<select id="select_comments" style="width:100%"><option value="">Choose a Comment</option>';
                    var $vls = $vl.split('||');
                    for (i in $vls) {
                        $html += '<option value="' + $vls[i] + '">' + $vls[i] + '</option>';
                    }
                    $html += '</select>';
                    $('#popup_selecter').html($html);
                    tb_remove();
                    tb_show("Choose a Comment", "#TB_inline?height=50&amp;width=400&amp;inlineId=popup_selecter");
                    $('#select_comments').change(function() {
                        if ($(this).val() != '') {
                            $cm.val($(this).val());
                            tb_remove();
                        }
                    })
                } else {
                    $cm.val($vl);
                }

            } else {
                $cm.val('');
            }
        });
        $('body').on('change', '.speciment_value', function() {
            var $min = parseFloat($(this).data('min'));
            var $max = parseFloat($(this).data('max'));
            var $vl = parseFloat($(this).val());
            var $status = 'NORMAL';

            if (isNaN($vl)) {
                $status = '';
            } else if ($(this).data('min') == 'neg') {
                $status = ($vl >= $max) ? 'POS' : 'NEG';
            } else if ($(this).val() == '') {
                $status = '';
            } else if ($vl < $min || $vl > $max) {
                $status = 'ABNORMAL';
            }
            var $pr = $(this).parents('.specimen_row');
            $('.specimen_outcome', $pr).val($status);

        }).on('change', 'select.pos_neg', function() {
            var $pr = $(this).parents('tr');
            $('textarea', $pr).val('');
            $('.rejected_comments', $pr).hide();
            if ($status_rejected.includes($(this).val())) {
                $('.rejected_comments_' + $(this).val(), $pr).show();
                $('input.conc', $pr).val('');
            }
        }).on('change', '[name="methamphetamine[d]"],[name="methamphetamine[l]"]', function() {
            $cm = $('[name="methamphetamine[comments]"]');
            if ($('[name="methamphetamine[d]"]').val() > 0 || $('[name="methamphetamine[l]"]').val() > 0) {
                $cm.val($cm.data('cm'));
            } else {
                $cm.val('');
            }
        }).on('change', 'select.rejected_comments', function() {
            var $pr = $(this).parents('tr');
            $('textarea', $pr).val($(this).val());
        })

        $('.approve_btn').click(function() {
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
            } else if ($('[name="info[final_results]"]:checked').val() == '0') {
                alert('Status must be Final!');
                return false;
            }
        })
        $('a.show_test_panels').click(function() {
            $('.list_test_panels').toggle();
            return false;
        })
        $('a.add_tbl_pha').click(function() {
            var $html = $('#df_pha_table').html();
            tinymce.activeEditor.execCommand('mceInsertContent', false, $html);
            tinymce.execCommand("fontSize", false, "8pt");
            return false;
        })
        $('a.add_signedby_pha').click(function() {
            var $html = $('#df_pha_signedby').html();
            tinymce.activeEditor.execCommand('mceInsertContent', false, $html);
            tinymce.execCommand("fontSize", false, "8pt");
            return false;
        })
        $(window).scroll(function() {
            var $t = $(this).scrollTop();
            console.log($t);
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