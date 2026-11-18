<?php
global $wpdb, $report, $ci_cutoffs;


$id         = $_GET['id'];
$btype      = 'tox';
$user_id    = get_current_user_id();

include_once(trpdd_get_file_path($btype, 'actions'));


include_once(TRCPDD_PATH . 'pages/formdata.php');



$urine_settings  = get_option('ci_urine_validity_settings', array());
$drug_pairs = tr_pdd_get_drug_pairs();
// codeine, morphine, hydrocodone override
/*
$drug_pairs['CODEINE'] = array("MORPHINE","HYDROCODONE");
$drug_pairs['MORPHINE'] = array("HYDROMORPHONE");
$drug_pairs['6-MAM'] = array("MORPHINE","HYDROMORPHONE","CODEINE");
$drug_pairs['HYDROCODONE'] = array("HYDROMORPHONE");
*/

if (!$id && isset($_GET['createnew'])) {
    tr_tox_create_report();
}

$report = tr_pdd_get_report_by_id($id, $btype);



if (!$report['id']) {
    echo '<h2>No Results Found!</h2>';
    return;
}
$use_style    = tr_get_option('ci_pdf_data', 'tox_pdf_style', 1);
if ($report['cus_result_style'] > 1) {
    $use_style = $report['cus_result_style'];
}
if ($report['cli_result_style'] > 1) {
    $use_style = $report['cli_result_style'];
}






$has_screening = false;
$screening_html = '';
$update         = array();

$screenings = tr_pdd_get_result_validity(1, '', $report['mid']);

if (count($screenings) > 0) {
    ob_start();
    include('results_edit_screening.php');
    $screening_html = ob_get_clean();

    $update['has_reflex'] = '0';

    $test_requested = explode(',', strtoupper($report['panel_tests_requested']));

    $arr_rs = trpdd_filter_tests_reflex_by_screening($test_requested, $report);

    if (isset($_GET['check_reflex'])) {
        var_dump($arr_rs);
    }


    if (isset($arr_rs['reflex_tests'])) {
        if (count($arr_rs['reflex_tests']) > 0) {
            $report['has_reflex']   = $arr_rs['has_reflex'];
            $update['has_reflex']   = $arr_rs['has_reflex'];
            $test_requested         = $arr_rs['test_requested'];
            $report['panel_tests_requested'] = implode(',', $test_requested);
        }

        $wpdb->update('ci_reports', array('has_reflex' => $update['has_reflex']), array('id' => $report['id']));
    }
}


$report['panel_tests_requested'] = str_replace('[+]POC confirm +', '', $report['panel_tests_requested']);

$lcms_tests         = tr_pdd_get_profiles_tests_requesteds('tox', true, false, $report['doc']);
$methamphetamine    = $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $id . "' ", ARRAY_A);
$all_res            = array();

if (count($lcms_tests) > 0) {
    $all_results_q = $wpdb->get_results("SELECT rs.* , 
    te.te_name,
    te.te_ID,
    te.te_abrev
    FROM ci_results rs 
    left JOIN ci_test_panels te ON (te.te_name = rs.drug or te.te_abrev = rs.drug or te.te_abrev2 = rs.drug) 
                                and te.te_type='tox' and te.te_in_req = 1
    WHERE rs.report_id='" . $id . "'
    group by rs.id ", ARRAY_A);

    foreach ($all_results_q as $arr) {
        $arr['drug'] = strtoupper($arr['drug']);
        $all_res[$arr['drug']] = $arr;

        if (!empty($arr['te_name'])) {
            $arr['drug'] = strtoupper($arr['te_name']);
            $all_res[$arr['drug']] = $arr;
        }

        if (!empty($arr['te_abrev'])) {
            //  $arr['te_abrev'] = strtoupper($arr['te_abrev']);
            //  $all_res[$arr['te_abrev']] = $arr;
        }
    }
}


$drug_groups = tr_pdd_get_drug_groups();

$ci_cutoffs = tr_pdd_get_cutoffs($btype, $report['created_date']);

$in_panel_tests_requested = array();

if (stripos(@$report['panel_tests_requested'], 'Ethanol') !== false) {
    if (!isset($all_res['Ethanol*']) && !isset($all_res['ETHANOL*'])) {
        $all_res['zEthanol*'] = array('drug' => 'Ethanol*', 'id' => 'new', 'pos-neg' => 'NEG');
    }
}

$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($report['panel_tests_requested'], $drug_pairs);
$medications = tr_pdd_parse_medications($report['medications'], $drug_pairs);
$has_Methamphetamine = stripos($report['panel_tests_requested'], 'Methamphetamine d/l') !== false;


if (isset($_GET['add_alltests']) || isset($_GET['add_all_tests'])) {
    $all_res = tr_tox_result_add_all_results($all_res, $report, $in_panel_tests_requested);
}


if (isset($_REQUEST['debug'])) {
    //echo 'Batch:';var_export($batch);echo '<br><br>';
    var_dump($report);
    echo '<br><br>';
    var_dump($report['panel_tests_requested']);
    echo '<br><br>';
    echo 'Tests:';
    var_export($in_panel_tests_requested);
    echo '<br><br>';
    echo 'Medications:';
    var_export($medications);
    echo '<br><br>';
    echo '$all_res:';
    var_export($all_res);
    echo '<br><br>';
    echo 'Cutoffs:';
    var_dump($ci_cutoffs);
    echo '<br><br>';
    echo 'groups: ';
    echo '<br>';
    var_dump($drug_groups);
}


$pos_neg = array(
    '' => 'Blank',
    'NEG' => 'NEG',
    'POS' => 'POS',
    'PEN' => 'Pending',
    'CAN' => 'Canceled',
);
$count_test_show = 0;
$test_array = explode(',', $report['panel_tests_requested']);
$count_all_test = count($test_array);
$miss_test = array();
foreach ($test_array as $t) {
    $t = strtoupper($t);
    $miss_test[$t] = $t;

    if (isset($_GET['testposall']) && !isset($all_res[$t])) {
        $all_res[$t] = array(
            'drug' => $t,
            'pos-neg' => 'POS',
            'conc' => 50,
            'id' => 'new' . $t
        );
    }
}

$has_pos = false;


//check missing test
$missing_tests = array();
foreach ($in_panel_tests_requested as $t) {
    if ($t == 'THC' && stripos($report['sample_type'], 'oral') === false) continue;
    //if($t=='METHAMPHETAMINE D/L ISOMERS' && !empty($methamphetamine))continue;
    if (!isset($all_res[$t])) {
        $in_pair = false;
        foreach ($drug_pairs as $k => $pr) {
            if (in_array($t, $pr)) {
                $in_pair = true;
            }
        }
        if (!$in_pair) {
            $missing_tests[] = $t;
        }
    }
}




$csv_files = explode(',', $report['upload_files']);

$csv_file = '/wp-content/log/results/' . end($csv_files);
$req_form_url = 'admin.php?page=tox_req_form&act=edit&id=' . $report['mid'];
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

    .specimen_row.isred select,
    .specimen_row.isred input {
        color: red;
    }
</style>



<h2>Edit <small style="float:right">Style: <?php echo $use_style ?></small></h2>
<?php Tr_Session_Class::show() ?>

<form method="post">

    <div class="frow">
        <h4>Patient Info</h4>
    </div>

    <div class="frow">
        <div class="col4">
            <label title="<?php echo $report['mid'] ?>">Patient Name<a href="?page=tox_req_form&act=edit&id=<?php echo $report['mid'] ?>" target="_blank">:</a></label>
            <input type="text" value="<?php echo tr_pdd_capitalized($report['patient_name']) ?>" readonly>
        </div>
        <div class="col4">
            <label>Physician</label>
            <input type="text" value="<?php echo $report['physician'] ?>" readonly>
        </div>

        <div class="col4">
            <label>Patient ID <a target="_blank" href="<?php echo $req_form_url ?>">.</a></label>
            <input type="text" name="info[patient_id]" value="<?php echo $report['assen'] ?>">
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
            <label>Acquisition Date</label>
            <input type="text" class="" name="info[acquisition_date]" placeholder="YYYY-mm-dd" value="<?php echo $report['acquisition_date'] ?>">
        </div>
        <div class="col4">
            <label>Sample Type</label>
            <input type="text" name="info[sample_type]" value="<?php echo $report['sample_type'] ?>">
        </div>
        <div class="col4">
            <label>Instrument</label>
            <input type="text" name="info[instrument]" value="<?php echo $report['instrument'] ?>">
        </div>
    </div>

    <div class="frow">
        <div class="col4">
            <label>Collection Date</label>
            <input type="text" value="<?php echo $report['collection_date'] ?>" readonly>
        </div>
        <div class="col4">
            <label>Received in Lab</label>
            <input type="text" class="hasdatetime" name="updatebat[spec_received]" value="<?php echo $report['spec_received'] ?>" />
        </div>
        <div class="col4">
            <label>Report Date</label>
            <input type="text" class="hasdatetime" name="info[approve_date]" value="<?php echo $report['approve_date'] ?>" />
        </div>
    </div>
    <div class="frow <?php if (!empty($report['medications']) && $report['medications'] != 'None Listed') echo 'in_medications' ?>">
        <label>Medications</label>
        <input type="text" value="<?php echo $report['medications'] ?>" readonly>
    </div>
    <div class="header_fix_top"></div>
    <div class="header_fix" style="display: none;">
        <span><tt>P:</tt> <?php echo $report['patient_name'] ?> </span>
        <span><tt>D:</tt> <?php echo $report['physician'] ?></span>
        <span><tt>A:</tt> <a class="show_test_panels" href="#"><?php echo $report['patient_id'] ?></a></span>
        <span><tt>L:</tt> <?php echo $report['sublab'] ?></span>

        <span><tt>C:</tt> <?php echo $report['collection_date'] ?></span>
        <span><tt>M:</tt> <?php echo $report['medications'] ?></span>
        <span><tt>I:</tt> <?php echo $report['instrument'] ?></span>
    </div>
    <div class="list_test_panels" style="display: none;">
        <div title="<?php echo $count_all_test ?>"><?php echo str_replace(',', ', ', $report['panel_tests_requested']) ?></div>
    </div>


    <?php
    $no_tox_tests = !empty($lcms_tests) &&
        (($report['custom_profile'] == 'Use Custom Profile' && !$report['cus_ID'])
            || count($in_panel_tests_requested) == 0);


    if ($no_tox_tests && !$report['only_screening'] && !$has_screening) {
    ?>
        <h1 title="<?php echo count($in_panel_tests_requested) ?>" style="color: red;">No Custom Profile/No Tests Requested Otherwise</h1>
    <?php
    } else if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
        $rejected = tr_pdd_get_batch_statuses($report['batch_status'], false, false, 'tox');
    ?>
        <h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
        <div style="text-align: center;"><?php echo $report['billing_notes'] ?></div>
        <?php
    } else {

        if ($has_screening && $no_tox_tests) {
            $wpdb->update('ci_batches', array('only_screening' => 1), array('mid' => $report['mid']));
        }


        //Urine Validity
        if ($report['matrix'] != "oral" && $report['lab_not_specimen'] != 1) {

            $validities = tr_pdd_get_result_validity(0, '', $report['mid']);

            if (count($validities) > 0) {
        ?>
                <div class="frow">
                    <h4>SPECIMEN VALIDITY TEST</h4>
                </div>
                <div class="frow">
                    <div class="col5">
                        <strong>Test</strong>
                    </div>
                    <?php if (!isset($urine_settings['paper_strip']) || !$urine_settings['paper_strip']) : ?>
                        <div class="col5">
                            <strong>Value</strong>
                        </div>
                    <?php endif; ?>
                    <div class="col5">
                        <strong>Test Outcome</strong>
                    </div>
                    <div class="col5">
                        <strong>Reference Range</strong>
                    </div>
                    <div class="col5">
                        <strong>Comments</strong>
                    </div>
                </div>
                <div class="frow">
                    <?php
                    foreach ($validities as $arr) {
                        $arr = trpdd_parse_tox_urine_test($arr, $report);
                    ?>
                        <div class="frow specimen_row <?php if (in_array($arr['outcome'], array('POS', 'ABNORMAL'))) echo 'isred' ?>">
                            <div class="col5">
                                <?php echo htmlspecialchars($arr['u_name']) ?>
                            </div>
                            <?php if (!isset($urine_settings['paper_strip']) || !$urine_settings['paper_strip']) : ?>
                                <div class="col5">
                                    <input type="text" data-min="<?php echo $arr['u_low'] ?>" data-max="<?php echo $arr['u_high'] ?>" class="speciment_value" name="data_specimen[<?php echo $arr['u_ID'] ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                                </div>
                            <?php endif; ?>
                            <div class="col5">
                                <select name="data_specimen[<?php echo $arr['u_ID'] ?>][outcome]" class="specimen_outcome" data-comments="<?php echo $arr['u_comments'] ?>">
                                    <?php
                                    foreach ($arr['outcome_options'] as $k => $vl) {
                                        $selected = $k == $arr['outcome'] ? 'selected' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col5" title="<?php echo isset($arr['instrument']) ? $arr['instrument'] : '' ?>">
                                <?php echo $arr['u_range'] ?>&nbsp;
                            </div>
                            <div class="col5">
                                <textarea class="specimen_comments" name="data_specimen[<?php echo $arr['u_ID'] ?>][comments]" style="height:50px;"><?php echo $arr['comments'] ?></textarea>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                </div>
            <?php
            }
        }

        $vdx = tr_pdd_get_screening_vdx($report['mid'], $report['batch_created'], $report['batch_id']);
        if ($vdx['checked']) {
            $vdxs = tr_pdd_get_result_validity(2, $report['batch_created'], $report['mid']);
            ?>
            <div class="frow">
                <h4>VDX Synthetic Urine</h4>
            </div>
            <div class="frow">
                <div class="col5">
                    <strong>Test</strong>
                </div>
                <div class="col5">
                    <strong>Value</strong>
                </div>
                <div class="col5">
                    <strong>Test Outcome</strong>
                </div>
                <div class="col5">
                    <strong>Reference Range</strong>
                </div>
                <div class="col5">
                    <strong>Comments</strong>
                </div>
            </div>
            <?php
            $CreVDX = 0;
            $SGVDX = 0;

            foreach ($vdxs as $arr) {

                $arr['conc_old'] = $arr['conc'];
                $arr['conc'] = preg_replace("/[^0-9-.,@]/", '', $arr['conc']);

                if ($arr['u_name'] == 'C/SGR') {

                    $arr['conc'] = $CreVDX / (($SGVDX - 1) * 6667);
                    if ($arr['conc'] > 1) {
                        $arr['conc'] = number_format($arr['conc'], 2);
                    } else {
                        $arr['conc'] = number_format($arr['conc'], 4);
                    }
                    $arr['u_high'] = 5;
                    $arr['u_range'] = '<5';
                }

                if ($arr['outcome'] != 'CAN') {
                    if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
                        $arr['outcome'] = 'POS';
                    } else if (!empty($arr['conc']) || empty($arr['outcome'])) {
                        $arr['outcome'] = 'NEG';
                    }

                    if ($arr['u_name'] == 'OxVDX' && strpos($arr['conc_old'], '@') !== false) {
                        if ($arr['conc'] > 200) {
                            $arr['outcome'] = 'POS';
                        }
                    }
                }

                if (stripos($arr['u_name'], 'cre') === 0) {
                    $CreVDX = (float) $arr['conc'];
                } else if (stripos($arr['u_name'], 'sg') === 0) {
                    $SGVDX = (float)$arr['conc'];
                }

            ?>
                <div class="frow specimen_row vdx_row <?php if (in_array($arr['outcome'], array('POS', 'ABNORMAL'))) echo 'isred' ?>" data-name="<?php echo strtolower($arr['u_name']) ?>">
                    <div class="col5">
                        <?php echo htmlspecialchars($arr['u_name']) ?>
                    </div>
                    <div class="col5">
                        <input type="text" data-min="<?php echo $arr['u_low'] ?>" data-max="<?php echo $arr['u_high'] ?>" class="speciment_value" name="data_specimen[<?php echo $arr['u_ID'] ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                    </div>
                    <div class="col5">

                        <select name="data_specimen[<?php echo $arr['u_ID'] ?>][outcome]" class="specimen_outcome" data-min="<?php echo $arr['u_low'] ?>" data-max="<?php echo $arr['u_high'] ?>" data-comments="<?php echo $arr['u_comments'] ?>" data-u_reflex="<?php echo $arr['u_reflex'] ?>" data-u_reflex_tests="<?php echo $arr['u_reflex_tests'] ?>" data-u_reflex_clinics="<?php echo $arr['u_reflex_clinics'] ?>">
                            <?php
                            $outcomes = array(
                                ''         => 'Select',
                                'NEG'   => 'NORMAL',
                                'POS' => 'ABNORMAL',
                                'CAN' => 'Canceled',
                            );
                            if ($arr['u_neg_pos']) {
                                $outcomes = array(
                                    ''    => 'Select',
                                    'NEG' => 'NEG',
                                    'POS' => 'POS',
                                    'CAN' => 'Canceled',
                                );
                            }
                            foreach ($outcomes as $k => $vl) {
                                $selected = $k == $arr['outcome'] ? 'selected' : '';
                                echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                            }
                            ?>
                        </select>

                    </div>
                    <div class="col5">
                        <?php echo $arr['u_range'] ?>&nbsp;
                    </div>
                    <div class="col5">
                        <?php if ($arr['u_name'] != 'C/SGR') { ?>
                            <textarea class="specimen_comments" name="data_specimen[<?php echo $arr['u_ID'] ?>][comments]" style="height:50px;"><?php echo $arr['comments'] ?></textarea>
                        <?php } ?>
                    </div>
                </div>
            <?php
            }

            if (isset($_GET['debug'])) {
            ?>
                <div class="frow">
                    <div class="col5">Result:</div>
                    <div class="col5"><?php echo $vdx['outcome'] ?></div>
                    <div class="col5">Conditions: <?php echo  $vdx['condition_match']  ?></div>
                    <div class="col5">
                        <?php var_dump($vdx['conditions']) ?>
                    </div>
                </div>
        <?php
            }
        }

        if ($has_screening) {
            echo $screening_html;
        }


        ?>

        <div class="frow">
            <h4></h4>
        </div>

        <table style="width:100%">
            <?php
            $ethanol = false;
            $inew = 0;

            foreach ($drug_groups as $groupid => $groupdata) {

                if (!$groupdata['show']) continue;


                $drugs = $groupdata['drugs'];

                $found = 0;
                foreach ($drugs as $d) {
                    if (isset($all_res[$d])) $found++;
                }
                if ($groupdata['manual_edit']) {
                    $found = 1;
                }
                if (empty($found)) continue;



                ob_start();
                foreach ($drugs as $d) {


                    $arr = isset($all_res[$d]) ? $all_res[$d] : array();


                    if ($groupdata['manual_edit'] && empty($arr)) {
                        $arr = array(
                            'drug' => strtoupper($d),
                            'id' => 'new' . $inew++
                        );
                    }

                    if (!empty($arr)) {
                        unset($all_res[$d]);

                        $arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);


                        if (in_array($arr['te_name'], $missing_tests)) {
                            unset($missing_tests[array_search($arr['te_name'], $missing_tests)]);
                        } else if (in_array($arr['te_abrev'], $missing_tests)) {
                            unset($missing_tests[array_search($arr['te_abrev'], $missing_tests)]);
                        } else if (in_array($arr['te_abrev2'], $missing_tests)) {
                            unset($missing_tests[array_search($arr['te_abrev2'], $missing_tests)]);
                        } else if (isset($arr['drug2']) && in_array($arr['drug2'], $missing_tests)) {
                            unset($missing_tests[array_search($arr['drug2'], $missing_tests)]);
                        }

                        if (strpos($arr['te_name'], '(') > 0) {
                            $drug2 = trim(substr($arr['te_name'], 0, strpos($arr['te_name'], '(')));
                            unset($missing_tests[array_search($drug2, $missing_tests)]);
                        }



                        if (stripos($arr['drug'], 'Ethanol*') !== false) {
                            $ethanol = $arr;
                            continue;
                        }
                        if ($arr['exclude']) {
                            if (isset($_GET['debu2g'])) {
                                echo '<tr><td>Exclude drug: ' . $arr['drug'] . '</td></tr>';
                            }
                            continue;
                        }

                        if ($arr['drug'] == 'METHAMPHETAMINE') {
                            if ($arr['pos-neg'] == 'NEG') {
                                $has_Methamphetamine = false;
                            } else {
                                $has_Methamphetamine = true;
                            }
                        }

                        $count_test_show++;
                        unset($miss_test[$arr['drug']]);

                        if ($arr['pos-neg'] == 'POS') {
                            $has_pos = true;
                        }

            ?>
                        <tr class="result_row <?php if (isset($arr['is_blue']) && $arr['is_blue']) echo 'in_medications';
                                                if (isset($arr['is_red']) && $arr['is_red']) echo 'bg_red' ?>">
                            <td title="<?php echo isset($arr['tt']) ? $arr['tt'] : '' ?>" class="tddrug">
                                <input type="text" name="data_results[<?php echo $arr['id'] ?>][drug]" value="<?php echo $arr['drug'] ?>" />
                            </td>
                            <?php if ($use_style == 2) : ?>
                                <td>
                                    <select class="poct" name="data_results[<?php echo $arr['id'] ?>][poct]">
                                        <?php
                                        foreach ($pos_neg as $k => $vl) {
                                            $selected = $k == $arr['poct'] ? 'selected="selected"' : '';
                                            echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td>
                                    <select class="ia" name="data_results[<?php echo $arr['id'] ?>][ia]">
                                        <?php
                                        foreach ($pos_neg as $k => $vl) {
                                            $selected = $k == $arr['ia'] ? 'selected="selected"' : '';
                                            echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
                            <?php endif; ?>
                            <td>
                                <input type="text" class="conc" name="data_results[<?php echo $arr['id'] ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                            </td>
                            <td>
                                <select class="pos_neg" name="data_results[<?php echo $arr['id'] ?>][pos-neg]" <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>>
                                    <?php
                                    foreach ($pos_neg as $k => $vl) {
                                        $selected = $k == $arr['pos-neg'] ? 'selected="selected"' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" class="cutoff" title="<?php echo $arr['instrument'] ?>" name="data_results[<?php echo $arr['id'] ?>][cutoff]" value="<?php echo $arr['cutoff'] ?>" />
                            </td>
                            <?php if ($use_style == 2) : ?>
                                <td>
                                    <input type="text" class="crea_adj" name="data_results[<?php echo $arr['id'] ?>][crea_adj]" value="<?php echo $arr['crea_adj'] ?>" maxlength="25" />
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php echo $arr['detection'] ?>&nbsp;
                            </td>
                            <td data-did="<?php echo isset($arr['drug_comment_id']) ? $arr['drug_comment_id'] : '' ?>">
                                <textarea name="data_results[<?php echo $arr['id'] ?>][comments]" data-posc="<?php echo @$arr['posc'] ?>"><?php echo $arr['comments'] ?></textarea>
                            </td>
                        </tr>
                    <?php
                    } else if (isset($_GET['debu2g'])) {
                        echo '<tr><td>Empty arr for drug: ' . $d . '</td></tr>';
                    }
                }
                $drug_content = ob_get_clean();
                if (!empty($drug_content)) {
                    ?>
                    <tr class="group_header">
                        <th><strong><?php echo htmlspecialchars($groupdata['name']) ?></strong></th>
                        <?php if ($use_style == 2) : ?>
                            <th>
                                <strong>POCT</strong><br>
                                <small>Pos/Neg</small>
                            </th>
                            <th>
                                <strong>IA</strong><br>
                                <small>Pos/Neg</small>
                            </th>
                        <?php endif; ?>
                        <th><strong>Conc (ng/mL)</strong></th>
                        <th><strong>Pos/Neg</strong></th>
                        <th><strong>Cutoff (ng/mL)</strong></th>
                        <?php if ($use_style == 2) : ?>
                            <th>
                                <strong>Crea Adj.</strong>
                            </th>
                        <?php endif; ?>
                        <th><strong>Detection Window</strong></th>
                        <th><strong>Comments</strong></th>
                    </tr>
                <?php
                    echo $drug_content;
                }
            }


            if ($ethanol && $report['matrix'] != 'oral') {
                $arr = $ethanol;

                if (empty($arr['detection'])) {
                    $arr['detection'] = '24 hours';
                }

                if (empty($arr['comments'])) {
                    $arr['posc'] = "Consistent with alcohol use.";
                    if ($arr['pos-neg'] == 'POS') {
                        $arr['comments'] = $arr['posc'];
                    }
                } else {
                    $arr['posc'] = $arr['comments'];
                }


                ?>

                <tr class="group_header">
                    <th>
                        <strong>Alcohol</strong>
                    </th>
                    <?php if ($use_style == 2) : ?>
                        <th>
                            <strong>POCT</strong><br>
                            <small>Pos/Neg</small>
                        </th>
                        <th>
                            <strong>IA</strong><br>
                            <small>Pos/Neg</small>
                        </th>
                    <?php endif; ?>
                    <th>
                        <strong>Conc %</strong>
                    </th>
                    <th>
                        <strong>Pos/Neg</strong>
                    </th>
                    <th>
                        <strong>Cutoff %</strong>
                    </th>
                    <?php if ($use_style == 2) : ?>
                        <th>
                            <strong>Crea Adj.</strong>
                        </th>
                    <?php endif; ?>
                    <th>
                        <strong>Detection Window</strong>
                    </th>
                    <th>
                        <strong>Comments</strong>
                    </th>
                </tr>
                <tr class="<?php if ($arr['is_blue']) echo 'in_medications' ?> <?php if ($arr['is_red']) echo 'bg_red' ?>">
                    <td class="tddrug">
                        <input type="text" name="data_results[<?php echo $arr['id'] ?>][drug]" value="<?php echo $arr['drug'] ?>" />
                    </td>
                    <?php if ($use_style == 2) : ?>
                        <td>
                            <select class="poct" name="data_results[<?php echo $arr['id'] ?>][poct]">
                                <?php
                                foreach ($pos_neg as $k => $vl) {
                                    $selected = $k == $arr['poct'] ? 'selected="selected"' : '';
                                    echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <select class="ia" name="data_results[<?php echo $arr['id'] ?>][ia]">
                                <?php
                                foreach ($pos_neg as $k => $vl) {
                                    $selected = $k == $arr['ia'] ? 'selected="selected"' : '';
                                    echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    <?php endif; ?>
                    <td>
                        <input type="text" class="conc" name="data_results[<?php echo $arr['id'] ?>][conc]" value="<?php echo $arr['conc'] ?>" />
                    </td>
                    <td>
                        <select class="pos_neg" name="data_results[<?php echo $arr['id'] ?>][pos-neg]">
                            <?php
                            foreach ($pos_neg as $k => $vl) {
                                $selected = $k == $arr['pos-neg'] ? 'selected="selected"' : '';
                                echo '<option value="' . $k . '" ' . $selected . '>' . $vl . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" class="cutoff" name="data_results[<?php echo $arr['id'] ?>][cutoff]" value="<?php echo $arr['cutoff'] ?>" />
                    </td>
                    <?php if ($use_style == 2) : ?>
                        <td>
                            <input type="text" class="crea_adj" name="data_results[<?php echo $arr['id'] ?>][crea_adj]" value="<?php echo $arr['crea_adj'] ?>" maxlength="25" />
                        </td>
                    <?php endif; ?>
                    <td>
                        <?php echo $arr['detection'] ?>&nbsp;
                    </td>
                    <td>
                        <textarea data-posc="<?php echo @$arr['posc'] ?>" name="data_results[<?php echo $arr['id'] ?>][comments]"><?php echo $arr['comments'] ?></textarea>
                    </td>
                </tr>
            <?php
            }
            ?>
        </table>


        <?php
        if ($has_Methamphetamine) {
            $cm = 'The d/l isomer identification is a follow up to a positive methamphetamine confirmation result. The d/l identification is reported as the percentage of Isomer present. A > 20 % concentration of d-isomer likely indicates the use of illicit Methamphetamine, unless the patient has been prescribed a D-Methamphetamine containing drug (desoxyn).';
            if (empty($methamphetamine['comments']) && ($methamphetamine['d'] > 0 ||  $methamphetamine['l'] > 0)) {
                $methamphetamine['comments'] = $cm;
            }
        ?>
            <div class="frow">
                <h4>METHAMPHETAMINE ISOMER RATIO TEST</h4>
                <table style="width:100%" class="tblborder">
                    <tr>
                        <th style="width:35%">Methamphetamine Isomer</th>
                        <th style="width:15%">%</th>
                        <th style="width:50%">Comments</th>
                    </tr>
                    <tr>
                        <td>D-Methamphetamine</td>
                        <td>
                            <input type="text" name="methamphetamine[d]" value="<?php echo $methamphetamine['d'] ?>" />
                        </td>
                        <td rowspan="2">
                            <textarea name="methamphetamine[comments]" data-cm="<?php echo $cm ?>" style="width:100%;height: 100px;"><?php echo $methamphetamine['comments'] ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td>L-Methamphetamine</td>
                        <td>
                            <input type="text" name="methamphetamine[l]" value="<?php echo $methamphetamine['l'] ?>" />
                        </td>
                    </tr>
                </table>
            </div>
        <?php
        }
        ?>


    <?php
    }
    ?>
    <div class="frow">
        <h4>Notes (visible on reports)</h4>
    </div>
    <div class="frow" title="<?php echo $count_test_show ?>" data-miss="<?php echo implode(',', $miss_test) ?>">
        <div class="">
            <textarea name="updatebat[notes_report]"><?php echo stripslashes($report['notes_report']) ?></textarea>
        </div>
    </div>
    <div class="frow">
        <h4>Internal Notes</h4>
    </div>
    <div class="frow" title="<?php echo $count_test_show ?>" data-miss="<?php echo implode(',', $miss_test) ?>">
        <div class="">
            <textarea name="updatebat[internal_notes]"><?php echo stripslashes($report['internal_notes']) ?></textarea>
        </div>
    </div>
    <br>

    <div class="frow">
        <div class="col3">
            <label>Status</label>
            <?php
            $status_options = tr_pdd_get_batch_statuses_on_hold(tr_pdd_get_batch_statuses(false, false, false, 'tox'), $report['batch_status'], $report['mid'], $table_batch, 'tox');
            $disabled_keys = tr_pdd_get_batch_statuses_disabled_keys('tox', 'show_in_result_form', $report['batch_status']);
            tr_select_option('updatebat[batch_status]', $status_options, @$report['batch_status'], ['disabled_options' => $disabled_keys]);
            ?>
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
    </div>

    <div class="frow">
        <label style="margin-right:20px"><input type="radio" name="info[final_results]" value="0" <?php checked($report['final_results'], '0') ?> /> None </label>
        <label style="margin-right:20px"><input type="radio" name="info[final_results]" value="2" <?php checked($report['final_results'], '2') ?> /> Preliminary </label>
        <label style="margin-right:20px"><input type="radio" name="info[final_results]" value="1" <?php checked($report['final_results'], '1') ?> /> Final </label>
        <label><input type="radio" name="info[final_results]" value="3" <?php checked($report['final_results'], '3') ?> /> Amended Report </label>
    </div>

    <div class="frow">
        <input type="submit" name="submit" value="Submit" class="button-primary" />

        <input type="submit" name="approval" value="Approve" class="button approve_btn">

        <?php

        $next = tr_pdd_next_result($id, $report['batch_id'], $report['lab_id'], 'tox');
        if ($next > 0) {
            $next_url = Tr_Base_Class_V6::link(array('id' => $next), false);
        ?>
            <input type="hidden" name="next_link" value="<?php echo $next_url ?>" />
            <input type="submit" name="approval_next" value="Approve & Next" class="button approve_btn">
            <a href="<?php echo $next_url ?>" class="button">Next</a>
        <?php
        }
        ?>

        <a class="button" target="_blank" href="<?php Tr_Base_Class_V6::link(array('tr_action' => 'ci_results_pdf', 'id' => $report['id'], 'view' => 1)) ?>">PDF</a>

        <input type="hidden" name="tr_action" value="ci_save_result_edit" />
        <input type="hidden" name="btype" value="tox" />

        <?php trpdd_form_unique_id() ?>
    </div>
</form>

<?php include(trpdd_get_file_path('global', 'report_history')); ?>


<div id="popup_selecter" style="display: none;"></div>


<div>
    <?php
    if (count($missing_tests) > 0) {
        echo '<br><br>Missing: ' . count($missing_tests);
        foreach ($missing_tests as $drug) {
            echo '<li>' . $drug . '</li>';
        }
    }
    ?>
</div>


<style>
    input.conc,
    input.cutoff {
        width: 80px;
    }

    tr.group_header {
        background: #ddd;
        height: 60px;
    }

    .tddrug input {
        width: 92%;
    }

    td textarea {
        width: 98%;
    }
</style>
<script>
    jQuery(function($) {
        var $count_missing_test = 0;

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
                var $oldposneg = $this.data('value') ? $this.data('value') : '';
                var $posneg = $this.val();
                var $cm = $this.parents('.specimen_row').find('.specimen_comments');
                $this.parents('.specimen_row').toggleClass('isred', $posneg == 'POS' || $posneg == 'ABNORMAL');
                if ($posneg == 'ABNORMAL') {
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

                //remove value if change NEG/POS
                if ($posneg == 'NEG' && $oldposneg == 'POS') {
                    var $conc = $this.parents('.specimen_row').find('.speciment_value');
                    $conc.val('');
                } else if ($posneg == 'POS' && $oldposneg == 'NEG') {
                    var $conc = $this.parents('.specimen_row').find('.speciment_value');
                    $conc.val('');
                }
            })
            .on('change', '.speciment_value', function() {
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
                $pr.toggleClass('isred', $status == 'POS' || $status == 'ABNORMAL');

            })
            .on('change', 'select.pos_neg', function() {
                var $pr = $(this).parents('.crow');
                if (!$pr.hasClass('in_medications')) {
                    if ($(this).val() == 'NEG') {
                        var $cutoff = $pr.find('input.cutoff').val();
                        $pr.data('ocomment', $pr.find('textarea').val());
                        $pr.data('conc', $pr.find('input.conc').val());
                        $pr.find('textarea').val('');
                        $pr.find('input.conc').val('<' + $cutoff);
                    } else if ($(this).val() == 'POS' && $pr.data('ocomment')) {
                        $pr.find('textarea').val($pr.data('ocomment'));
                        if ($pr.data('conc')) {
                            $pr.find('input.conc').val($pr.data('conc'));
                        }
                    } else if ($(this).val() == 'POS') {
                        $pr.find('input.conc').val('');
                        $pr.find('textarea').val($pr.find('textarea').data('posc'));
                    }
                }
            })
            .on('change', '[name="methamphetamine[d]"],[name="methamphetamine[l]"]', function() {
                $cm = $('[name="methamphetamine[comments]"]');
                if ($('[name="methamphetamine[d]"]').val() > 0 || $('[name="methamphetamine[l]"]').val() > 0) {
                    $cm.val($cm.data('cm'));
                } else {
                    $cm.val('');
                }
            })
            .on('click', '.approve_btn', function() {
                if ($('[name="info[final_results]"]:checked').val() == '1') {
                    //check missing
                    if (tr_has_missing()) {
                        if (!confirm('Are you sure you want to approve this result as final? There are ordered tests pending.')) {
                            $('[name="info[final_results]"][value="2"]').prop('checked', true);
                        }
                    }
                }
            })
        $('a.show_test_panels').click(function() {
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

        function tr_call_csgr() {
            var $crevdx = $('.vdx_row[data-name="crevdx"] .speciment_value').val();
            console.log($crevdx);
            var $sgvdx = $('.vdx_row[data-name="sgvdx"] .speciment_value').val();
            console.log($sgvdx);
            var $vl = Math.round(($crevdx / (($sgvdx - 1) * 6667)) * 10000) / 10000;
            $('.vdx_row[data-name="c/sgr"] input').val($vl).change();
        }
        $('.vdx_row[data-name="c/sgr"]').each(function() {
            $('.vdx_row[data-name="crevdx"] .speciment_value,.vdx_row[data-name="sgvdx"] .speciment_value').change(function() {
                tr_call_csgr();
            }).change();
        })
    })
</script>


<?php

//update has_pos

$has_pos = $has_pos ? 1 : 0;
$update = array();
$update['has_pos'] = $has_pos;
$update['has_pos_modified'] = time();
$wpdb->update('ci_reports', $update, array('id' => $report['id']));
