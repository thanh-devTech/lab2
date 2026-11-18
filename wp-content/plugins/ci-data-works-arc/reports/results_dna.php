<?php
global $wpdb, $ci_cutoffs, $report;
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;
$sum = isset($_REQUEST['sum']) ? true : $sum;
$sum = true; //now live sum


$upload_dir = tr_wp_upload_dir();
$logo_dir = $upload_dir['basedir'] . '/logos/';
$logo_url = $upload_dir['baseurl'] . '/logos/';
if ($id == 0) {
    $mid = isset($_REQUEST['mid']) ? $_REQUEST['mid'] : 0;
}

$report = tr_pdd_get_report_by_id($id, $btype, $mid);




$clinic_address = array();
if (!empty($report['cli_street'])) {
    $clinic_address[] = $report['cli_street'];
}
if (!empty($report['cli_city'])) {
    $clinic_address[] = $report['cli_city'];
}
$report['cli_state'] = trim($report['cli_state'] . ' ' . $report['cli_zip']);
if (!empty($report['cli_state'])) {
    $clinic_address[] = $report['cli_state'];
}

$clinic_address = implode(', ', $clinic_address);
//parse panels



$all_tests = array();
$rs = $wpdb->get_results("select * from ci_test_panels where te_type='dna' ");
foreach ($rs as $r) {
    $r->te_name = strtoupper($r->te_name);
    $all_tests[$r->te_name] = $r;
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
$test_ids = implode(',', $in_panel_tests_requested);

$sql = "SELECT *
        FROM ci_results_dna rs 
        join ci_test_panels tp ON (tp.te_name = rs.drug or tp.te_abrev = rs.drug) and tp.te_type='dna' 
        WHERE report_id='" . $id . "' 
        and tp.te_ID in ({$test_ids})
        ";
$all_results_q = $wpdb->get_results($sql, ARRAY_A);



foreach ($all_results_q as $arr) {
    $arr['drug'] = strtoupper($arr['te_name']);
    $all_res[$arr['drug']] = $arr;
}

$drug_groups = tr_pdd_get_drug_groups('dna', $group_ids);

$antibiotic_drugs = array();


$pos_neg = array(
    '' => '',
    'NEG' => 'Not Detected',
    'POS' => 'Detected',
    'POL' => 'Detected-Low',
    'POM' => 'Detected-Medium',
    'POH' => 'Detected-High',
    'PEN' => 'Pending'
);

$no_collection_date = (empty($report['collection_date']) || $report['collection_date'] == '0000-00-00' || $report['collection_date'] == '0000-00-00 00:00:00') && $report['doc'] == '0000-00-00';
$notes = get_option('ci_notes_result');

$text_PRELIMINARY = '<span class="blue">FINAL COPY</span>';
if ($report['final_results'] === '2') {
    $text_PRELIMINARY = '<span class="red">PRELIMINARY</span>';
}
$final_adding_font = '';
if (isset($result_settings['final_bold']) && $result_settings['final_bold']) {
    $final_adding_font .= 'font-weight:bold;text-transform: uppercase;';
}
if (isset($_REQUEST['debug']) && isset($_REQUEST['html'])) {
    var_export($report);
    echo '<br><br>';
    echo 'Tests:';
    var_export($in_panel_tests_requested);
    echo '<br><br>';
    echo '$all_res:';
    var_export($all_res);
    echo '<br><br>';
    echo '$drug_groups: ';
    var_dump($drug_groups);
    echo '<br><br>';
}



/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */



?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta id="final" content="<?php echo $report['final_results'] ?>" />
    <title>Result</title>
    <style>
        @page {
            margin: 0.5cm 0.5cm 1.8cm 0.5cm;
        }

        body {
            margin-top: 5.3cm;
        }

        break,
        .break {
            page-break-after: always;
            border: 0;
        }

        * {
            font-family: 'Oswald', Arial, Helvetica, sans-serif;
            font-size: 9pt;
        }

        .font8 {
            font-size: 8pt;
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

        table.nopadding td {
            padding: 0
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

        .upper {
            text-transform: uppercase;
        }

        .in_medications {
            background: #92ccff
        }

        .bg_red {
            background: #ff9495
        }

        table tr.headernoborder th {
            border: 3px solid #000;
            border-left: 0;
            border-right: 0;
            font-weight: bold;
            font-size: 12px;
            padding-top: 4px;
            padding-bottom: 4px
        }

        table.noboder,
        table tr.noborder td {
            border: none;
        }

        table .font_blue td {
            color: blue
        }

        h4,
        .h4 {
            font-size: 12px;
            margin: 0;
            padding: 0;
            line-height: 12px;
            font-weight: bold
        }

        h5 {
            font-weight: normal;
            font-size: 12px;
            color: green;
            margin: 10px 0 0 0
        }

        .right {
            text-align: right;
        }

        .right_pre {
            float: right;
            margin-top: -15px;
            color: red;
            text-align: right
        }

        .blue {
            color: blue
        }

        .red,
        .rowred td {
            color: red
        }

        .small_table th,
        .small_table td {
            font-size: 8pt;
            padding: 2px;
        }

        .small_padding_table th,
        .small_padding_table td {
            padding: 2px;
            color: #000;
        }

        .address_right div {
            font-size: 10px;
            line-height: 12px;
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

        <?php if (!isset($_REQUEST['html'])): ?>#header,
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
            padding-top: 5px;
        }

        #footer,
        #footer * {
            font-size: 10px;
        }
    </style>
</head>

<body>


    <div id="header">
        <table style="width: 100%;border:0;border-collapse:collapse">
            <tr>
                <td style="width:30%;height:50px;" valign="top">
                    <?php
                    $logo_path = $logo_dir . $report['lab_logo'];
                    $logo_main = $report['lab_logo'];


                    $logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

                    $has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
                    if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
                        $logo_main = '';
                    }

                    if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
                        $size = getimagesize($logo_dir . $logo_main);
                        $max_width = 250;
                        $max_height = ($size[0] > $size[1]) ? 50 : 60;
                        if ($has_logo2) {
                            // $max_height-=30;
                            $max_width = 150;
                        }
                    ?>
                        <img border="0" style="max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_dir . $logo_main ?>" />
                    <?php
                    }

                    ?>

                </td>
                <td style="width:45%">
                    <h2 style="color:green;font-size:18px;font-weight: normal;">Molecular PGx Summary Lab Report</h2>
                </td>
                <td class="address_right" style="color:green;text-align: right;">
                    <div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
                    <div><?php echo $report['header_lab_address'] ?></div>
                    <div>Phone: <?php echo $report['header_lab_phone'] ?></div>
                    <div>CLIA ID: <?php echo $report['header_lab_clia'] ?></div>
                </td>
            </tr>
        </table>
        <table class="small_padding_table" style="border-top:4px solid green;border-bottom:4px solid green">
            <tr>
                <td style="width:30%">
                    <h5>CLINIC INFORMATION</h5>
                </td>
                <td style="width:30%">
                    <h5>PATIENT INFORMATION</h5>
                </td>
                <td>
                    <h5>SPECIMEN INFORMATION</h5>
                </td>
            </tr>
            <tr>
                <td style="border-right:1px solid #ddd">
                    <table class="noboder">
                        <tr>
                            <td style="width:30%">Name:</td>
                            <td><?php echo $report['requesting_practice'] ?></td>
                        </tr>
                        <tr>
                            <td>Address:</td>
                            <td><?php echo $clinic_address ?></td>
                        </tr>
                        <tr>
                            <td>Provider:</td>
                            <td><?php echo htmlspecialchars($report['physician']) ?></td>
                        </tr>
                    </table>
                </td>
                <td style="border-right:1px solid #ddd;padding-left:10px;">
                    <table class="noboder">
                        <tr>
                            <td style="width:25%">Name:</td>
                            <td><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
                        </tr>
                        <tr>
                            <td>DOB:</td>
                            <td><?php echo tr_pdd_display_date($report['patient_dob']) ?></td>
                        </tr>
                        <tr>
                            <td>Gender:</td>
                            <td><?php echo htmlspecialchars($report['pa_gender']) ?></td>
                        </tr>
                        <tr>
                            <td>ID:</td>
                            <td><?php echo htmlspecialchars($report['pa_ssn']) ?></td>
                        </tr>
                    </table>
                </td>
                <td style="padding-left:10px;">
                    <table class="noboder">
                        <tr>
                            <td style="width:50%">Lab Accession Number:</td>
                            <td><?php echo $report['assen'] ?></td>
                        </tr>
                        <tr>
                            <td>Date Collected:</td>
                            <td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
                        </tr>
                        <tr>
                            <td>Date Received by Lab:</td>
                            <td><?php echo tr_pdd_display_date($report['received_in_lab']) ?></td>
                        </tr>
                        <tr>
                            <td>Date Accessioned:</td>
                            <td><?php echo tr_pdd_display_date($report['collection_date']) ?></td>
                        </tr>
                        <tr>
                            <td>Date Reported:</td>
                            <td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <?php if (!empty($report['notes_report'])): ?>
            <div style="margin-top:-5px;margin-bottom:5px;color:red">
                Note: <?php echo $report['notes_report'] ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    ob_start();
    ?>

    <div id="footer">
        <?php
        $footer_text = stripslashes($report['rl_lab_additional']);

        if (empty($footer_text)) {
            $footer_text = stripslashes($report['lab_results_footer']);
        }

        $footer_text = str_replace(array('[lab]', '{original_footer}'), array($report['lab_dba_init'], @$report['lab_additional']), $footer_text);
        echo apply_filters('the_content', $footer_text);
        ?>
    </div>

    <?php
    $footer_html = ob_get_clean();

    if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
        echo stripslashes($footer_html);
    }

    if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
        $rejected = tr_pdd_get_batch_statuses($report['batch_status'], false, false, 'dna');

    ?>
        <br>
        <br><br><br><br>
        <h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
        <p style="text-align: center;"><?php echo $report['billing_notes'] ?></p>
        <p style="text-align: center;"><?php echo $report['notes_report'] ?></p>
    <?php
    } else {

        if (count($all_res) == 0) {
            $has_report = false;
        }
    ?>
        <table class="controls" style="margin-top:0;margin-bottom:15px">
            <tr>
                <td style="width:32%;border:2px solid #000">
                    <div style="text-decoration: underline;font-weight: bold;font-size:14px;">Controls</div>
                    <table class="nopadding" style="line-height: 12px">
                        <tr>
                            <td>Patient Extraction Control <sup>1</sup></td>
                            <td class="right">PASS</td>
                        </tr>
                        <tr>
                            <td>Endogenous Positive Control <sup>1</sup></td>
                            <td class="right">PASS</td>
                        </tr>
                        <tr>
                            <td>Pathogen Positive Control <sup>2</sup></td>
                            <td class="right">PASS</td>
                        </tr>
                        <tr>
                            <td>Pathogen Negative Control <sup>3</sup></td>
                            <td class="right">PASS</td>
                        </tr>
                    </table>
                </td>
                <td style="padding-left:20px;line-height: 16px;">
                    <div class="font8">(1) Endogenous control confirms sample collection, DNA/RNA extraction, and assay enzyme activity</div>
                    <div class="font8">(2) Positive control is synthetic inactive pathogen</div>
                    <div class="font8">(3) Negative Control contains primers, probe, and enzymes with no DNA/RNA template</div>
                    <div class="font8">(4) A "Detected" result indicates the presence of a pathogen (99.99% confidence) above the assay cutoff.
                        Assay cutoff is represented by CFU (bacteria), PFU (viruses) or Copy Number (DNA)</div>
                </td>
            </tr>
        </table>
        <?php
        //summary
        ob_start();
        foreach ($drug_groups as $groupid => $groupdata) {
            $drugs = $groupdata['drugs'];
            if ($groupdata['name'] == 'Antibiotic Resistance') {
                $drugs = $antibiotic_drugs;
            }

            ob_start();
            foreach ($drugs as $d) {
                $arr = $all_res[$d];
                if (!empty($arr)) {
                    $arr = tr_pdd_parse_dna_test($arr, $report['instrument'], $groupdata['name']);
                    if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM'))) {
                        if ($arr['antibiotic'] && $groupdata['name'] != 'Antibiotic Resistance') {
                            if (!in_array($d, $antibiotic_drugs)) {
                                $antibiotic_drugs[] = $d;
                            }

                            $all_res[$d] = $arr;
                            continue;
                        }
        ?>
                        <tr class="rowred">
                            <td><?php echo htmlspecialchars($arr['drug']) ?></td>
                            <td class="center upper"><?php echo htmlspecialchars($pos_neg[$arr['pos-neg']]) ?></td>
                            <td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
                            <td><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
                        </tr>
                <?php
                    }
                }
            }
            $drug_content = ob_get_clean();
            if (!empty($drug_content)) {
                ?>
                <tr class="headernoborder">
                    <th colspan="4" class="center upper" style="border:none;font-size:14px">SUMMARY <?php echo htmlspecialchars($group . ' Results') ?></th>
                </tr>
                <tr>
                    <th style="width:30%">Test Performed</th>
                    <th class="center" style="width:20%">Lab Result(4)<br>(Low/Medium/High)</th>
                    <th class="center" style="width:20%">DNA Copy Number</th>
                    <th class="center">Comments</th>
                </tr>
            <?php
                echo $drug_content;
            }
        }

        $summary = ob_get_clean();
        if ($summary) {
            ?>
            <table class="tdborder small_table">
                <?php echo $summary; ?>
            </table>
            <div class="break"></div>
        <?php
        }
        ?>


        <?php
        foreach ($drug_groups as $groupid => $groupdata) {
            $drugs = $groupdata['drugs'];
            if ($groupdata['name'] == 'Other') {
                $drugs = array();
                foreach ($all_res as $d => $arr) {
                    $drugs[] = $d;
                }
            } else if ($groupdata['name'] == 'Antibiotic Resistance') {
                $drugs = $antibiotic_drugs;
            }

            ob_start();
            foreach ($drugs as $d) {
                $arr = $all_res[$d];

                if (!empty($arr)) {
                    $arr = tr_pdd_parse_dna_test($arr, $report['instrument'], $groupid);


        ?>
                    <tr class="<?php if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM'))) echo 'rowred' ?>">
                        <td><?php echo htmlspecialchars($arr['drug']) ?></td>
                        <td class="center upper"><?php echo htmlspecialchars($pos_neg[$arr['pos-neg']]) ?></td>
                        <td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
                        <td><?php echo nl2br(htmlspecialchars($arr['comments'])) ?></td>
                    </tr>
                <?php
                }

                unset($all_res[$d]);
            }
            $drug_content = ob_get_clean();
            if (!empty($drug_content)) {
                ?>
                <table class="tdborder small_table">
                    <tr class="headernoborder">
                        <th colspan="4" class="center upper" style="border:none;font-size:14px"><?php echo htmlspecialchars($group . ' Results') ?></th>
                    </tr>
                    <thead>
                        <tr>
                            <th style="width:30%">Test Performed</th>
                            <th class="center" style="width:20%">Lab Result(4)<br>(Low/Medium/High)</th>
                            <th class="center" style="width:20%">DNA Copy Number</th>
                            <th class="center">Comments</th>
                        </tr>
                    </thead>
                    <?php
                    echo $drug_content;
                    ?>

                </table>
        <?php
                if (!empty($groupdata['comments'])) {
                    echo '<p>' . $groupdata['comments'] . '</p>';
                }
            }
        }
        ?>



    <?php

        if (!empty($report['notes_report'])) {
            echo '<p>';
            echo nl2br(stripslashes($report['notes_report']));
            echo '</p>';
        }
    } //end if rejected
    ?>




    <?php
    if (isset($_REQUEST['html'])) {
        echo stripslashes($footer_html);
    }
    ?>
    <?php include('_pdf_pagination.php'); ?>

</body>

</html>