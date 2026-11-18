<?php
global $wpdb, $ci_cutoffs, $report;
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;
$sum = isset($_REQUEST['sum']) ? true : $sum;
$sum = true; //now live sum
if (isset($_REQUEST['confirmation_pending'])) {
    $sum = false;
}



$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos/';
$logo_url = $upload_dir['baseurl'] . '/logos/';

if (!$report) {
    $report = tr_pdd_get_report_by_id($id, $btype);
    if ($id == 0) {
        $id = $report['id'];
    }
}

$drug_pairs = tr_pdd_get_drug_pairs();

$drug_groups = tr_pdd_get_drug_groups('tox');
$group_Illicits = array();
foreach ($drug_groups as $groupid => $groupdata) {
    if (!$groupdata['show']) continue;

    if (stripos($groupdata['name'], 'Illicits') !== false) {
        $group_Illicits = $groupdata;
        break;
    }
}


$in_panel_tests_requested = array();
$panel_batch = '';



//parse panels
$panel_batch = $report['custom_profile'];
if (empty($panel_batch)) {
    list($panel_batch) = explode(' ', $report['panel_tests_requested']);
}
$panel_batch = strtolower($panel_batch);

$all_res = array();
$all_results_q = $wpdb->get_results("
SELECT rs.* , 
te.te_name,
te.te_abrev,
te.te_ID
FROM ci_results rs 
JOIN ci_test_panels te ON (te.te_name = rs.drug or te.te_abrev = rs.drug) 
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
}

$all_total = count($all_results_q);

if (stripos($report['panel_tests_requested'], 'Ethanol*') !== false) {
    //$drug_groups['Natural and Semi-Synthetic Opioids'][] = 'Ethanol*';
    if (!isset($all_res['Ethanol*']) && !isset($all_res['ETHANOL*'])) {
        $all_res['zEthanol*'] = array('drug' => 'Ethanol*', 'id' => 'new', 'pos-neg' => 'NEG');
    }
}
$methamphetamine = $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $id . "' ", ARRAY_A);


$ci_cutoffs  = tr_pdd_get_cutoffs($btype, $report['created_date']);

$exclude_tests = tr_pdd_get_test_outdate($report['doc']);
$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($report['panel_tests_requested'], $drug_pairs);
$medications = tr_pdd_parse_medications($report['medications'], $drug_pairs);
$has_Methamphetamine = $has_Methamphetamine_dl = stripos($report['panel_tests_requested'], 'Methamphetamine d/l Isomers') !== false;
$no_collection_date = (empty($report['collection_date']) || $report['collection_date'] == '0000-00-00' || $report['collection_date'] == '0000-00-00 00:00:00') && $report['doc'] == '0000-00-00';
$notes = get_option('ci_notes_result');

$text_PRELIMINARY = tr_pdd_get_result_status($report['final_results'], true);
$final_adding_font = '';
if (isset($result_settings['final_bold']) && $result_settings['final_bold']) {
    $final_adding_font .= 'font-weight:bold;text-transform: uppercase;';
}

$pdf_notes                 = trpdd_get_pdf_note($report, 'tox');

if (isset($_REQUEST['debug']) && isset($_REQUEST['html'])) {
    var_export($report);
    echo '<br><br>';
    echo 'Tests:';
    var_export($in_panel_tests_requested);
    echo '<br><br>';
    echo 'Medications:';
    var_export($medications);
    echo '<br><br>';
    echo '$drug_pairs:';
    var_export($drug_pairs);
    echo '<br><br>';
    echo '$all_res:';
    var_export($all_res);
    echo '<br><br>';
    echo '$panel_batch: ' . $panel_batch;
    echo '<br><br>';
    echo '$ci_cutoffs: ';
    var_dump($ci_cutoffs);
    echo '<br><br>';

    echo '$drug_groups: ';
    var_dump($drug_groups);
    echo '<br><br>';
}


$footer_height = 1.5;
$margin_bottom = 0;

ob_start();
?>

<div id="footer">
    <?php
    $footer_text = trpdd_get_footer_text($report, 'tox');

    $director_signature = '';
    if (!empty($report['lab_director_signature'])) {
        $sign_dir = tr_get_signature_dir();
        $director_signature_url = $sign_dir['path'] . '/' . $report['lab_director_signature'];
        $director_signature = '<img src="' . $director_signature_url . '" style="height:30px">';
    }

    $footer_text = str_replace('[signature]', $director_signature, $footer_text);

    $lines = 0;
    foreach (explode('<br', $footer_text) as $ftx) {
        $lines += ceil(strlen($ftx) / 150);
    }
    $footer_height = $lines * 0.45;
    echo $footer_text;
    ?>
</div>

<?php
$footer_html = ob_get_clean();


if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
    $margin_bottom = $footer_height;
    $footer_height = 0.3;
}
?>
<!DOCTYPE html>
<html class="style4">

<head>
    <meta charset="UTF-8" />
    <meta id="final" content="<?php echo $report['final_results'] ?>" />
    <title>Result</title>
    <style>
        @page {
            margin: 1cm 1cm <?php echo $footer_height ?>cm 1cm;
        }

        body {
            margin-top: 5.6cm;
            margin-bottom: <?php echo $margin_bottom ?>cm;
        }

        break,
        .break {
            page-break-after: always;
            border: 0;
        }

        * {
            font-family: 'Oswald', Arial, Helvetica, sans-serif;
            font-size: 8pt;
        }

        a.NoteRef {
            text-decoration: none;
        }

        hr {
            height: 1px;
            padding: 0;
            margin: 1em 0;
            border: 0;
            border-top: 1px solid #CCC;
        }

        table {
            width: 100%;
            border-spacing: 0px;
            page-break-inside: auto;
            border-collapse: collapse;
        }

        table th {
            text-align: left;
            padding: 5px;
        }

        table td {
            padding: 5px;
            vertical-align: top
        }

        table.border {
            border: 1px solid black;
            border-spacing: 0px;
            width: 100%;
        }

        table.tdborder td,
        table.tdborder th {
            border: 1px solid black;
        }

        table.border2.border.tdborder {
            border-top: 1px solid black;
            border-left: 1px solid black;
            border-right: 2px solid black;
            border-bottom: 2px solid #000
        }

        table.noborder td,
        table.noborder th {
            border: 0 !important
        }

        .marginbottom {
            margin-bottom: 10px;
        }

        .fontNormal {
            font-family: 'Arial';
            font-size: 8pt;
            color: #000000;
        }

        .fontNormalRed {
            font-family: 'Arial';
            font-size: 8pt;
            font-weight: bold;
        }

        .fontSmall {
            font-family: 'Arial';
            font-size: 8pt;
            color: #000000;
        }

        .fontBold {
            font-family: 'Arial';
            font-size: 8pt;
            color: #000000;
            font-weight: bold;
        }

        .fontBoldSmall {
            font-family: 'Arial';
            font-size: 8pt;
            color: #000000;
            font-weight: bold;
        }

        .fontBoldWhite {
            font-family: 'Arial';
            font-size: 8pt;
            color: #FFFFFF;
            font-weight: bold;
        }

        .fontHeader {
            font-family: 'Arial';
            font-size: 11pt;
            color: #000000;
            font-weight: bold;
        }

        .normal {
            margin-top: 2.5pt;
            margin-bottom: 2.5pt;
        }

        .table {
            margin-top: 0pt;
            margin-bottom: 0pt;
        }

        .tableCenter {
            text-align: center;
            margin-top: 0pt;
            margin-bottom: 0pt;
        }

        .header {
            margin-top: 10pt;
            margin-bottom: 10pt;
        }

        .center {
            text-align: center
        }

        .font_red .pn_POS,
        .font_red td.conc {
            color: red !important;
            font-weight: bold
        }

        .red {
            color: red
        }

        .bold {
            font-weight: bold
        }

        table.nobordertop td {
            border-top: 0 !important;
        }

        table tr.nobottomborder td {
            border-bottom: 0 !important;
        }

        h4,
        .h4 {
            font-size: 11px;
            margin: 0;
            padding: 0;
            line-height: 12px;
            font-weight: bold
        }

        .small_table th,
        .small_table td {
            font-size: 7pt;
            padding: 2px;
            color: #000;
        }

        .small_padding_table th,
        .small_padding_table td {
            padding: 2px;
            color: #000;
        }

        table tr th.header_black {
            background: #444;
            color: #fff;
            padding: 4px;
        }

        table tr th.header_none {
            border-top: none;
            border-right: none
        }

        <?php if (!isset($_REQUEST['html'])) : ?>#header,
        #footer {
            position: fixed;
            left: 0;
            right: 0;
            color: #666;
            font-size: 0.9em;
        }

        <?php endif; ?>#header {
            top: 0;
        }

        #footer {
            bottom: 5px;
            border-top: 0.1pt solid #aaa;
            line-height: 13px;
        }

        #footer,
        #footer * {
            font-size: 10px;
        }

        .right_pre {
            float: right;
            margin-top: -15px;
            color: red;
            text-align: right;
            <?php echo $final_adding_font ?>
        }

        .blue {
            color: blue
        }

        .red {
            color: red
        }

        .title_tbl th {
            text-align: center;
            border-bottom: 1px solid #ddd;
            height: 18px;
            background: #ddd;
            color: #000;
            padding-top: 0;
            padding-bottom: 0;
        }
    </style>
</head>

<body>

    <div id="header">
        <table style="width: 100%;border:0;border-collapse:collapse">
            <tr>
                <td style="width:60%;height:80px;" valign="top">
                    <?php
                    $logo_path = $logo_dir . $report['lab_logo'];
                    $logo_main = $report['lab_logo'];


                    $logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

                    $has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
                    if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
                        $logo_main = $logo_second;
                    }

                    if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
                        $size = getimagesize($logo_dir . $logo_main);
                        $max_width = 400;
                        $max_height = ($size[0] > $size[1]) ? 70 : 80;

                    ?>
                        <img border="0" style="max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_dir . $logo_main ?>" />
                    <?php
                    }


                    ?>

                </td>
                <td>
                </td>
                <td style="width:60%;vertical-align: top;min-width: 300px;padding-right:0;">
                    <div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
                    <table class="border" style="width:100%;clear:both;">
                        <tr>
                            <td style="padding:5px 10px 2px 10px;">
                                <?php if (!empty($report['header_lab_address'])) : ?>
                                    <div><strong><?php echo $report['header_lab_address'] ?></strong></div>
                                <?php endif; ?>
                                <table class="noborder">
                                    <tr>
                                        <td>
                                            <div>Phone: <?php echo $report['header_lab_phone'] ?></div>
                                            <div>CLIA # <?php echo $report['header_lab_clia'] ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($report['header_lab_fax'])) { ?>
                                                <div>Fax: <?php echo $report['header_lab_fax'] ?></div>
                                            <?php } ?>
                                            <?php if (!empty($report['header_lab_npi'])) { ?>
                                                <div>NPI # <?php echo $report['header_lab_npi'] ?></div>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <table class="border tdborder small_padding_table">
            <tr>
                <th style="width:20%">Patient Name</th>
                <td style="width:30%"><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
                <th style="width:20%">Physician/Provider</th>
                <td style="width:30%"><?php echo htmlspecialchars($report['physician']) ?></td>
            </tr>
            <tr>
                <th>Accession</th>
                <td><?php echo htmlspecialchars($report['assen']) ?></td>
                <th>Patient Code</th>
                <td><?php echo htmlspecialchars($report['pa_code']) ?></td>
            </tr>
            <tr>
                <th>Patient DOB</th>
                <td>
                    <?php
                    if (empty($report['patient_dob']) || $report['patient_dob'] == '00000' || $report['patient_dob'] == '0000-00-00' || $report['patient_dob'] == '00/00/0000') {
                        echo 'Not Provided';
                    } else {
                        echo tr_pdd_display_date($report['patient_dob']);
                    }
                    ?>
                </td>
                <th>Sample Type</th>
                <td><?php echo htmlspecialchars($report['sample_type']) ?></td>
            </tr>
            <tr>
                <th>Results Date</th>
                <td>
                    <?php
                    if (empty($report['approve_date']) || $report['approve_date'] == '00000' || $report['approve_date'] == '0000-00-00' || $report['approve_date'] == '00/00/0000') {
                        echo 'Not Provided';
                    } else {
                        echo tr_pdd_display_date($report['approve_date']);
                    }
                    ?>
                </td>
                <th>Received in Lab</th>
                <td><?php echo tr_pdd_display_date($report['received_in_lab']) ?></td>
            </tr>
            <tr>
                <th>Collection Date</th>
                <td><?php echo tr_pdd_display_date($report['collection_date']) ?></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <th>Medications</th>
                <td colspan="3"><?php echo (!empty($report['medications'])) ? htmlspecialchars(str_replace(',', ', ', $report['medications'])) : "No reported medications" ?></td>
            </tr>
        </table>
    </div>
    <?php


    if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
        echo stripslashes($footer_html);
    }

    if (count($in_panel_tests_requested) == 0 && !$report['only_screening']) {
        if (!empty($report['result_pdf'])) {
            $has_tox_result = false;
            $has_report = false;
            return;
        }
    ?>
        <h1 style="color: red;">No Custom Profile/No Tests Requested Otherwise</h1>
        <?php
    } else if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
        include('rejected_default.php');
    } else {

        if (isset($_REQUEST['d2'])) {
            $no_collection_date = true;
        }
        if ($no_collection_date) {
        ?>
            <h1 style="color:red">*** Insufficient Information: No Date of Collection Indicated.</h1>
        <?php
        }

        if ($sum && !$report['only_screening']) {
        ?>

            <h4>Summary Report</h4>
            <h3>LCMS CONFIRMATION TEST</h3>

            <?php

            $summary_all = array(
                'blue' => array('title' => 'Consistent Results - Reported Medication Detected', 'drugs' => array()),
                'normal' => array('title' => 'Inconsistent Results -  Expected Positives for Reported Medication', 'drugs' => array()),
                'red' => array('title' => 'Inconsitent Results -  Expected Negatives for Drugs that Test Positive', 'drugs' => array()),
            );
            $colwidths = array('18%', '18%', '10%', '10%', '10%', '44%');

            $sortkey = 0;

            foreach ($all_res as $i => $arr) {

                $arr['drug'] = strtoupper($arr['drug']);

                $arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

                if (stripos($arr['drug'], 'Ethanol*') !== false) {
                    $ethanol = $arr;
                    unset($all_res[$i]);
                    continue;
                }

                if ($arr['exclude'] || in_array($arr['drug'], $exclude_tests)) {
                    unset($all_res[$i]);
                    continue;
                }


                if ($arr['pos-neg'] != 'POS' && !$arr['is_red'] && !$arr['is_blue']) {
                    continue;
                }
                if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
                    continue;
                }

                $k = $arr['is_red'] ? 'red' : 'normal';
                $k = $arr['is_blue'] ? 'blue' : $k;
                if ($k == 'blue' && $arr['pos-neg'] == 'NEG') {
                    $k = 'normal';
                }
                $arr['k'] = $k;
                $all_res[$i] = $arr;
                if (empty($arr['med'])) {
                    $arr['med'] = 'Other';
                }

                $sortkeyc = $sortkey;
                if (in_array($arr['drug'], $medications['meds'])) {
                    $sortkeyc = array_search($arr['drug'], $medications['meds']);
                }

                $summary_all[$k]['drugs'][$arr['med']][$sortkeyc] = $arr;
                $sortkey++;
                //unset($all_res[$i]);
            }

            foreach ($summary_all as $k => $grouparr) {
            ?>
                <table class="small_table tdborder marginbottom nobordertop">
                    <tr class="header_tbl">
                        <th colspan="<?php echo ($hide_comments_col) ? 3 : 4 ?>" class="header_black"><?php echo $grouparr['title'] ?></th>
                        <th colspan="<?php echo $k == 'red' ? 1 : 2 ?>" class="header_none"></th>
                    </tr>
                    <tr class="title_tbl">
                        <?php if ($k != 'red') : ?>
                            <th style="width:<?php echo $colwidths[0] ?>">Prescription</th>
                        <?php endif ?>
                        <th style="width:<?php echo $colwidths[1] ?>">Anticipated</th>
                        <th style="width:<?php echo $colwidths[2] ?>" class="center">Neg/Pos</th>
                        <th style="width:<?php echo $colwidths[3] ?>" class="center">Detected(ng/mL)</th>
                        <th style="width:<?php echo $colwidths[4] ?>" class="center">Cutoff(ng/mL)</th>
                        <?php if (!$hide_comments_col) { ?>
                            <th style="width:<?php echo $colwidths[5] ?>" class="center">Comments</th>
                        <?php } ?>
                    </tr>
                    <?php
                    foreach ($grouparr['drugs'] as $med => $arrs) {
                        ksort($arrs);
                        $countarr = count($arrs);
                        $i2 = 0;
                        foreach ($arrs as $arr) {
                            $i2++;
                            $class = '';
                            if ($k == 'red' || $k == 'normal') $class .= ' font_red ';
                            if ($arr['is_blue']) $class .= ' font_blue ';
                            if ($i2 < $countarr) $class .= 'nobottomborder';
                            if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
                                continue;
                            }
                            $has_tox_result = true;
                    ?>
                            <tr class="<?php echo $class ?>">
                                <?php if ($k != 'red') { ?>
                                    <td style="width:<?php echo $colwidths[0] ?>" class="bold"><?php if ($i2 <= 1) echo htmlspecialchars($arr['med']) ?></td>
                                <?php } ?>
                                <td style="width:<?php echo $colwidths[1] ?>" class="bold">
                                    <?php echo htmlspecialchars($arr['drug']) ?>
                                </td>
                                <td style="width:<?php echo $colwidths[2] ?>" class="conc center">
                                    <?php echo htmlspecialchars($arr['pos-neg']) ?>
                                </td>
                                <td style="width:<?php echo $colwidths[3] ?>" class="conc center">
                                    <?php echo htmlspecialchars($arr['conc']) ?>
                                </td>
                                <td style="width:<?php echo $colwidths[4] ?>" class="center">
                                    <?php echo htmlspecialchars($arr['cutoff']) ?>
                                </td>
                                <?php if (!$hide_comments_col) { ?>
                                    <td style="width:<?php echo $colwidths[5] ?>"><?php if ($arr['pos-neg'] != 'NEG') echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
                                <?php } ?>
                            </tr>
                    <?php
                        }
                    }
                    ?>
                </table>
            <?php
            }
            ?>
            <div class="break"></div>


        <?php
        }
        //include urine validate & screening
        include(TRCPDD_PATH . 'reports/results_urine.php');
        include(TRCPDD_PATH . 'reports/results_screening.php');

        if (!$report['only_screening']) {
        ?>

            <h4 class="h4">LCMS CONFIRMATION</h4>
            <table class="border tdborder small_table marginbottom">
                <?php
                $comments = "Comments";

                foreach ($drug_groups as $groupid => $groupdata) {

                    if (!$groupdata['show']) continue;


                    $found = 0;
                    $drugs = array_map('strtoupper', $groupdata['drugs']);
                    foreach ($drugs as $d) {
                        if (isset($all_res[$d])) $found++;
                    }
                    if (empty($found)) continue;


                    $comments = "";
                    ob_start();
                    foreach ($drugs as $d) {
                        $arr = $all_res[$d];
                        if (!empty($arr)) {
                            unset($all_res[$d]);

                            $arr['group'] = isset($groupdata['name']) ? $groupdata['name'] : '';
                            $arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

                            if ($arr['drug'] == 'METHAMPHETAMINE') {
                                if ($arr['pos-neg'] == 'NEG') {
                                    $has_Methamphetamine = false;
                                } else {
                                    $has_Methamphetamine = true;
                                }
                            }

                            if (stripos($arr['drug'], 'Ethanol*') !== false) {
                                $ethanol = $arr;
                                continue;
                            }
                            if ($arr['exclude']) {
                                continue;
                            }
                            if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
                                continue;
                            }
                            $has_tox_result = true;
                ?>
                            <tr class="<?php if ($arr['is_blue']) echo 'in_medications' ?> <?php if ($arr['k'] == 'red' || $arr['k'] == 'normal') echo 'font_red' ?>">
                                <td><?php echo htmlspecialchars($arr['drug']) ?></td>
                                <td class="center conc"><?php echo htmlspecialchars($arr['conc']) ?></td>
                                <td class="center pn_<?php echo $arr['pos-neg'] ?>" <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
                                <td class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
                                <?php if (!$hide_detectionwindow_col) { ?>
                                    <td><?php echo ($arr['detection']) ?></td>
                                <?php } ?>
                                <?php if (!$hide_comments_col) { ?>
                                    <td><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
                                <?php } ?>
                            </tr>
                        <?php
                        }
                    }
                    $drug_content = ob_get_clean();
                    if (!empty($drug_content)) {
                        ?>
                        <tr class="title_tbl">
                            <th style="width:20%"><?php echo htmlspecialchars($groupdata['name']) ?></th>
                            <th style="width:10%" class="center">Conc <br> (ng/mL)</th>
                            <th style="width:10%" class="center">Pos/Neg</th>
                            <th style="width:10%" class="center">Cutoff<br> (ng/mL)</th>
                            <?php if (!$hide_detectionwindow_col) { ?>
                                <th style="width:10%" class="center">Detection Window</th>
                            <?php } ?>
                            <?php if (!$hide_comments_col) { ?>
                                <th><?php echo htmlspecialchars($comments) ?></th>
                            <?php } ?>
                        </tr>
                    <?php
                        echo $drug_content;
                    }
                }


                if (is_array($all_res) && count($all_res) > 0) {

                    ksort($all_res);
                    ob_start();
                    foreach ($all_res as $arr) {
                        $arr = tr_pdd_get_drug_class_comments($arr, $medications, $report['matrix'], $in_panel_tests_requested, $ci_cutoffs);

                        if ($arr['drug'] == 'METHAMPHETAMINE') {
                            if ($arr['pos-neg'] == 'NEG') {
                                $has_Methamphetamine = false;
                            } else {
                                $has_Methamphetamine = true;
                            }
                        }
                        if (stripos($arr['drug'], 'Ethanol*') !== false) {
                            $ethanol = $arr;
                            continue;
                        }
                        if ($arr['exclude']) {
                            continue;
                        }
                        if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
                            continue;
                        }
                        $has_tox_result = true;
                    ?>
                        <tr class="<?php if ($arr['is_blue']) echo 'in_medications' ?> <?php if ($arr['k'] == 'red' || $arr['k'] == 'normal') echo 'font_red' ?>">
                            <td><?php echo htmlspecialchars($arr['drug']) ?></td>
                            <td class="center conc"><?php echo htmlspecialchars($arr['conc']) ?></td>
                            <td class="center" <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
                            <td class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
                            <?php if (!$hide_detectionwindow_col) { ?>
                                <td><?php echo $arr['detection'] ?></td>
                            <?php } ?>
                            <?php if (!$hide_comments_col) { ?>
                                <td><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
                            <?php } ?>
                        </tr>
                    <?php
                    }
                    $other_content = ob_get_clean();
                    if (!empty($other_content)) {
                    ?>
                        <tr class="title_tbl">
                            <th>Other</th>
                            <th class="center">Conc <br> (ng/mL)</th>
                            <th class="center">Pos/Neg</th>
                            <th class="center">Cutoff<br> (ng/mL)</th>
                            <?php if (!$hide_detectionwindow_col) { ?>
                                <th class="center">Detection Window</th>
                            <?php } ?>
                            <?php if (!$hide_comments_col) { ?>
                                <th><?php echo $comments ?></th>
                            <?php } ?>
                        </tr>
                <?php
                        echo $other_content;
                    }
                }
                ?>
                <?php
                if ($ethanol && $report['matrix'] != 'oral') :
                    $arr = $ethanol;
                    if (empty($arr['cutoff'])) {
                        $arr['cutoff'] = '.010';
                    }
                    if (empty($arr['detection'])) {
                        $arr['detection'] = '24 hours';
                    }
                    if (empty($arr['conc']) || $arr['conc'] == '<') {
                        $arr['conc'] = '<' . $arr['cutoff'];
                    }
                ?>
                    <tr class="title_tbl">
                        <th>Alcohol</th>
                        <th class="center">Conc %</th>
                        <th class="center">Pos/Neg</th>
                        <th class="center">Cutoff %</th>
                        <?php if (!$hide_detectionwindow_col) { ?>
                            <th class="center">Detection Window</th>
                        <?php } ?>
                        <?php if (!$hide_comments_col) { ?>
                            <th><?php echo $comments ?></th>
                        <?php } ?>
                    </tr>
                    <tr class="<?php if ($arr['is_blue']) echo 'in_medications' ?> <?php if ($arr['is_red']) echo 'font_red' ?>">
                        <td><?php echo htmlspecialchars($arr['drug']) ?></td>
                        <td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
                        <td class="center pn_<?php echo $arr['pos-neg'] ?>"><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
                        <td class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
                        <?php if (!$hide_detectionwindow_col) { ?>
                            <td><?php echo $arr['detection'] ?></td>
                        <?php } ?>
                        <?php if (!$hide_comments_col) { ?>
                            <td><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
                        <?php } ?>
                    </tr>
                <?php
                endif;
                ?>
            </table>


            <?php include(TRCPDD_PATH . 'reports/methamphetamine_dl.php') ?>

            <div style="text-align: right;margin-top:15px;">
                <img style="max-width: 100%;height:auto;" src="<?php echo TRCPDD_PATH ?>images/last-panel.png" />
            </div>
            <div style="page-break-inside: auto;">
                <table>
                    <tr>
                        <td>
                            <?php echo stripslashes($notes['last_page_text']) ?>
                        </td>
                    </tr>
                </table>
            </div>

    <?php
        }

        if ($report['show_comment1']) {
            echo '<div><p>';
            echo nl2br(stripslashes($notes[1]));
            echo '</p></div>';
        }
        if ($report['show_comment2']) {
            echo '<div><p>';
            echo nl2br(stripslashes($notes[2]));
            echo '</p></div>';
        }
    } //end if rejected

    include('_approved_by.php');

    if (!empty($report['notes_report'])) {
        echo '<div style="padding-top:15px">';
        echo '<div style="font-weight:bold;padding:0;line-height:22px;">Notes</div>';
        echo nl2br(stripslashes($report['notes_report']));
        echo '</div>';
    }

    if ($has_tox_result && !empty($pdf_notes)) {
        echo '<div class="bottom_notes">';
        echo nl2br(stripslashes(($pdf_notes)));
        echo '</div>';
    }
    ?>




    <?php
    if (isset($_REQUEST['html'])) {
        echo stripslashes($footer_html);
    }
    ?>


    <?php include('_pdf_pagination.php'); ?>
</body>

</html>