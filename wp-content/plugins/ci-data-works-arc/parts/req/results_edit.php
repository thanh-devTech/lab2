<?php
global $wpdb, $report, $ci_cutoffs, $all_res;

include_once(TRCPDD_PATH . 'pages/formdata.php');

$db_version = get_option('TRCPDD_VS');
if ($db_version != TRCPDD_VS) {
    trpdd_plugin_activate();
}

$has_style2     = false;
$btype          = 'req';
$id             = isset($_GET['id']) ? $_GET['id'] : 0;

$user_id        = get_current_user_id();
$rs_settings    = get_option('ci_result_settings_req', array());

$conditions     = trpdd_get_batch_conditions(0, $btype);
$can_approve    = $conditions['can_approve_result'];

$report         = tr_pdd_get_report_by_id($id, $btype);

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'mf_file') {
    $metadata = $wpdb->get_var("select comments from ci_results_req 
    where report_id = {$id}
    and drug = '_mfdata' ");
    $metadata = json_decode($metadata, true);

    $file = isset($metadata['file']) ? $metadata['file'] : $report['assen'] . '.xlsx';
    $fileurl = '/wp-content/log/results_req/' . $file;
    wp_redirect($fileurl);
    exit;
} else if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'reimport_mf') {
    $mf_data = array();
    $mfrow = $wpdb->get_row("select * from ci_results_req where report_id = {$id}
                                        and drug = '_mfdata' ");

    if (!$mfrow) {
        $wpdb->insert('ci_results_req', array('report_id' => $id, 'drug' => '_mfdata', 'has_order' => '0'));
        $mf_id = $wpdb->insert_id;
    } else {
        $mf_id = $mfrow->id;
        $mf_data = json_decode($mfrow->comments, true);
    }

    $meta = $_POST['data_results'][$mf_id]['comments'];
    foreach ($meta as $k => $vl) {
        $mf_data[$k] = $vl;
    }

    $wpdb->update('ci_results_req', array('comments' => json_encode($mf_data)), array('id' => $mf_id));


    $return = array('status' => 'ok');
    //$_GET['debug1'] = $_REQUEST['debug1'] = true;

    $metadata = $wpdb->get_var("select comments from ci_results_req 
    where report_id = {$id}
    and drug = '_mfdata' ");
    $metadata = json_decode($metadata, true);

    $file = isset($metadata['file']) ? $metadata['file'] : $report['assen'] . '.xlsx';
    $filepath = $log_dir . $file;
    $return['file'] = $file;

    if (file_exists($filepath)) {
        $files = array($file => array());
        include(trpdd_get_file_path('req', 'results_import_mf'));
    }

    wp_send_json($return);
    exit;
}


if (!$report['id'] && isset($_GET['createnew'])) {
    $mid = $_GET['createnew'];

    $bat = $wpdb->get_row("select * from ci_batches_req where mid = {$mid} ");

    if ($bat) {
        $id = $wpdb->get_var("select id from ci_reports_req where patient_id='{$bat->assen}'");
        if ($id == 0) {
            if (empty($bat->batch_id)) {
                $bat->batch_id = tr_pdd_get_current_batch_id();
                $wpdb->update('ci_batches_req', array('batch_id' => $bat->batch_id), array('mid' => $mid));
            }
            $_GET['add_covid_demo'] = true;
            $data = array(
                'batch_id' => $bat->batch_id,
                'patient_id' => $bat->assen,
                'created_date' => date_i18n('Y-m-d'),
                'created' => date_i18n('Y-m-d H:i:s'),
            );
            $wpdb->insert('ci_reports_req', $data);
            $id = $wpdb->insert_id;
        }

        $report = tr_pdd_get_report_by_id($id, 'req');
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

$csv_file = '/wp-content/log/results_req/' . end($csv_files);
$req_form_url = 'admin.php?page=req_req_form&act=edit&id=' . $report['mid'];

if (empty($test_ids)) {
    $test_ids = '-1';
}


$where_group     = '';
$group_ids_str     = implode(',', $group_ids);
if (count($group_ids) > 0) {
    $where_group .= " and tg_ID in ({$group_ids_str}) ";
} else {
    $where_group .= ' and 1=2';
}

$sql = "SELECT *
        FROM ci_test_panels tp
        left join ci_results_req rs ON (tp.te_name = rs.drug or tp.te_abrev = rs.drug) 
                                        and rs.report_id= {$id}
        WHERE  tp.te_type in ('blood','pcr')
        and( 
            (
                (
                    rs.id >0
                    OR
                    (
                        rs.id is null 
                        AND 
                        tp.te_group_id in (select tg_ID from ci_test_groups 
                                            where tg_type = 'req' 
                                            and  tg_manual_edit=1 {$where_group} )
                     )
                ) 
                AND 
                (   
                    tp.te_ID in ({$test_ids}) 
                    OR tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'req' 
                                                and (tg_only_group = 1 or tg_notestcheckbox=1 or tg_manual_edit=1) {$where_group})
                 ) 
            ) 
            OR
            (
                tp.te_calculation!='' 
                and tp.te_calculation is not null
                and  tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'req' 
                                             {$where_group})
            )
        )
        ";

$date_test = date('Y-m-d', strtotime($report['created']));
$sql .= " and (te_start <= '{$date_test}' or te_start is null or te_start = '')";
$sql .= " and (te_end > '{$date_test}' or te_end is null or te_end = '')";

$all_results_q = $wpdb->get_results($sql, ARRAY_A);

$all_res = array();
foreach ($all_results_q as $arr) {
    if (empty($arr['drug'])) {
        $arr['drug'] = $arr['te_name'];
    }
    $arr['drug'] = strtoupper($arr['drug']);

    $all_res[$arr['te_ID']] = $arr;
}

$rsg = $wpdb->get_results(
    "select * from ci_results_req where report_id = {$id}
               and drug like '%|%'",
    ARRAY_A
);
$results_bygroup = array();
foreach ($rsg as $arr) {
    $arr['drug'] = strtoupper($arr['drug']);

    list($gid, $dr) = explode('|', $arr['drug']);
    $arr['drug'] = strtoupper($dr);
    $results_bygroup[$gid][$dr] = $arr;
}

$drug_groups = tr_pdd_get_drug_groups('req', $group_ids, true);


if (empty($report['instrument'])) {
    //Tr_Session_Class::add_error("Empty Instrument Ranges");
}
$inew       = 0;
$has_pos    = '';

foreach ($drug_groups as $groupid => $groupdata) {

    $te_IDs = isset($groupdata['te_IDs']) && is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();

    $groupdata['tests'] = array();

    foreach ($te_IDs as $te_ID) {

        $arr = $all_res[$te_ID];

        if (in_array($groupdata['id'], $group_ids) && ($groupdata['only_group'] || $groupdata['notestcheckbox']) && !in_array($te_ID, $missing_tests)) {
            $missing_tests[] = $te_ID;
        }

        if (!empty($arr)) {

            $arr['showed'] = true;
            if (empty($arr['id'])) {
                $arr['id'] = 'new' . $inew++;
            }
            $arr = tr_pdd_parse_req_test($arr, $report['instrument'], $groupdata['id'], $report);
            $all_res[$te_ID] = $arr;

            if (isset($results_bygroup[$groupdata['id']][$arr['drug']])) {
                $arr2 = $results_bygroup[$groupdata['id']][$arr['drug']];
                $arr['id'] = $arr2['id'];
                $arr['conc'] = $arr2['conc'];
                $arr['pos-neg'] = $arr2['pos-neg'];
                $arr['comments'] = $arr2['comments'];
                $arr = tr_pdd_parse_req_test($arr, $report['instrument'], $groupdata['id'], $report);
            }


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
        }
    }

    $drug_groups[$groupid] = $groupdata;
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
    $wpdb->update('ci_reports_req', $update_rp, array('id' => $report['id']));
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

if ($rs_settings['result_cancel_rejected']) {
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

    <h2>Edit </h2>
    <?php Tr_Session_Class::show() ?>

    <form method="post" id="result_form">

        <div class="frow">
            <h4>Patient Info</h4>
        </div>

        <div class="frow">
            <div class="col4">
                <label title="<?php echo $report['mid'] ?>">Patient Name</label>
                <input type="text" value="<?php echo tr_pdd_capitalized($report['patient_name']) ?>" readonly>
            </div>
            <div class="col4">
                <label>Physician <small style="float:right"><?php echo $report['doc_npi'] ?></small></label>
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
                <input type="text" class="hasdatetime" name="updatebat[spec_received]" value="<?php echo $report['spec_received'] ?>">
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
            <div title="<?php echo $count_all_test ?>"><?php echo str_replace(',', ', ', $report['panel_tests_requested']) ?></div>
        </div>


        <?php
        if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
            $rejected = tr_pdd_get_batch_statuses($report['batch_status'], false, false, 'req');
        ?>
            <h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
            <div style="text-align: center;"><?php echo $report['billing_notes'] ?></div>
        <?php
        } else { ?>


            <div class="frow">
                <h4>&nbsp;</h4>
            </div>

            <table style="width:100%" class="tbl_results">
                <?php
                foreach ($drug_groups as $groupid => $groupdata) {
                    ob_start();
                    foreach ($groupdata['tests'] as $arr) {
                        $count_test_show++;
                        unset($miss_test[$arr['drug']]);

                        $arr['conc_dis'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];

                        $pos_neg_vls = $pos_neg;
                        if ($arr['te_is_detected']) {
                            $pos_neg_vls['POS'] = 'High';
                            $pos_neg_vls['NEG'] = 'Normal';
                            if (!isset($arr['only_detected']) || !$arr['only_detected']) {
                                $pos_neg_vls['POL'] = 'Low';
                                $pos_neg_vls['POM'] = 'Medium';
                                $pos_neg_vls['POH'] = 'High';
                                unset($pos_neg_vls['POS']);
                                if ($arr['pos-neg'] == 'POS') {
                                    $arr['pos-neg'] = 'POL';
                                }
                            }
                        }
                        if (isset($arr['has_critical']) && $arr['has_critical']) {
                            $pos_neg_vls['PCL'] = 'Critical Low';
                            $pos_neg_vls['PCH'] = 'Critical High';
                        }
                        if (isset($arr['cal_missing']) && $arr['cal_missing']) {
                            continue;
                        }


                        $arr['score'] = '';
                        if (!empty($arr['te_optimal_value'])) {
                            if ($arr['conc'] < $arr['te_optimal_value']) {
                                $arr['score'] = '100%';
                            } else if ($arr['conc'] > $arr['te_ref_max']) {
                                $arr['score'] = '0%';
                            } else {
                                $d = abs($arr['conc'] - $arr['te_optimal_value']);
                                $d2 = $arr['te_ref_max'] - $arr['te_optimal_value'];
                                $arr['score'] = (1 - ($d / $d2)) * 100;
                                $arr['score'] = number_format(max($arr['score'], 0), 2) . '%';
                            }
                        }

                ?>
                        <tr class="result_row">
                            <td title="<?php echo @$arr['tt'] . $arr['pos-neg'] ?>">
                                <input type="text" <?php if (stripos($arr['id'], 'new') !== false) echo 'name="data_results[' . $arr['id'] . '][drug]"' ?> value="<?php echo !empty($arr['te_name']) ? strtoupper($arr['te_name']) : $arr['drug'] ?>" readonly />
                            </td>
                            <td>
                                <?php if (isset($arr['values']) && count($arr['values']) > 0) {
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
                                } elseif (is_array($arr['pos_neg_list']) && count($arr['pos_neg_list']) > 0) {
                                ?>
                                    <select class="pos_neg" name="data_results[<?php echo $arr['id'] ?>][pos-neg]" <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>>
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

                            <td>
                                <?php if (!isset($arr['values']) || count($arr['values']) == 0) { ?>
                                    <input type="text" class="conc" name="data_results[<?php echo $arr['id'] ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                                <?php } ?>
                            </td>
                            <?php if (!$groupdata['other_options']['normal_pdf']) { ?>
                                <td class="center"><?php echo $arr['score'] ?></td>
                            <?php } ?>
                            <td class="center">
                                <?php echo trim($arr['range']) ?>
                            </td>

                            <td data-did="<?php echo $arr['drug_comment_id'] ?>">
                                <?php
                                if ($rs_settings['result_cancel_rejected']) {
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
                    ?>
                        <tr class="group_header">
                            <th style="min-width:200px"><strong><?php echo htmlspecialchars($groupdata['name']) ?></strong></th>
                            <th style="min-width:100px"><strong>Result</strong></th>
                            <th style="min-width:100px"><strong>CT</strong></th>
                            <?php if (!$groupdata['other_options']['normal_pdf']) { ?>
                                <th style="min-width:100px"><strong>Score</strong></th>
                            <?php } ?>
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
                                        $mfrow          = $wpdb->get_row("select * from ci_results_req where report_id = {$id}
                                                            and drug = '_mfdata' ");
                                        if (!$mfrow) {
                                            $wpdb->insert('ci_results_req', array('report_id' => $id, 'drug' => '_mfdata', 'has_order' => '0'));
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
                    }
                }
                ?>

            </table>

        <?php
        }
        ?>
        <div class="frow" title="<?php echo $count_test_show ?>">
            <h4>Notes (Show on Report)</h4>
            <textarea name="updatebat[notes_report]"><?php echo $report['notes_report'] ?></textarea>
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
                tr_select_option('updatebat[batch_status]', tr_pdd_get_batch_statuses(false, false, false, 'req', 'show_in_result_form'), @$report['batch_status']);
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
                where g.tg_type = 'req' ";

            $rs = $wpdb->get_results($sql);
            $pop_msg = '';
            foreach ($rs as $r) {
                if (strlen($r->tg_popup_approve) > 10 && in_array($r->tg_ID, $group_ids)) {
                    $pop_msg = $r->tg_popup_approve;
                }
            }

            $next = tr_pdd_next_result($id, $report['batch_id'], $report['lab_id'], 'req');

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

            <a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $report['id'], 'btype' => 'req', 'view' => 1)) ?>">PDF</a>

            <input type="hidden" name="tr_action" value="ci_save_result_edit" />
            <input type="hidden" name="btype" value="req" />
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