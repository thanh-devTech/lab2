<?php
/*
Name: Mic style 1
*/
global $pdf_header_text;

$margin_top = 5.4;
if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) {
    $margin_top += 0.5;
}
if (tr_get_option('ci_pdf_data', 'pcr_patient_fvaccine') && !empty($report['pa_fully_vaccinated'])) {
    $margin_top += 0.5;
}

/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */


$footer_height = 1.6;
ob_start();
?>

<div id="footer">
    <?php
    $footer_text = trpdd_get_footer_text($report, 'pcr');
    $director_signature = '';
    if (!empty($report['lab_director_signature'])) {
        $sign_dir = tr_get_signature_dir();
        $director_signature_url = $sign_dir['path'] . '/' . $report['lab_director_signature'];
        $director_signature = '<img src="' . $director_signature_url . '" style="height:20px">';
        if ($footer_height < 2) {
            $footer_height = 2.2;
        }
    }

    $footer_text = str_replace('[signature]', $director_signature, $footer_text);

    if (preg_match("/height\s?:\s?([0-9]+)px/", $footer_text, $match_footer_height)) {

        $new_footer_height = ($match_footer_height[1] + 10) * 0.0261;
        if ($new_footer_height > $footer_height) {
            $footer_height = $new_footer_height;
        }
    }

    $footer_text = str_replace('<br>', '<br />', $footer_text);
    if ((strlen($footer_text) > 300 || substr_count($footer_text, '<br />') > 3) && $footer_height < 3) {
        $footer_height = 3;
    }

    echo $footer_text;
    ?>
</div>

<?php
$footer_html = ob_get_clean();

$rotate_margin_left = -14;
$rotate_margin_top = 150;
$margin_bottom = 0;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
    $margin_bottom = $footer_height;
    $footer_height = 0.3;
    $rotate_margin_top = 80;
    $rotate_margin_left = -1;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta id="final" content="<?php echo $report['final_results'] ?>" />
    <title><?php echo $report['assen'] ?></title>
    <style>
        @page {
            margin: 0.5cm 0.5cm <?php echo $footer_height ?>cm 0.5cm;
        }

        body {
            margin-top: <?php echo $margin_top ?>cm;
            margin-bottom: <?php echo $margin_bottom ?>cm;
        }

        break,
        .break {
            page-break-after: always;
            border: 0;
        }

        * {
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            font-size: 9pt;
            line-height: 10px;
        }

        .font8 {
            font-size: 8pt;
        }

        table .sample_type {
            font-size: 7pt;
            text-transform: none;
            font-weight: normal;
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
            color: <?php echo $report['color'] ?>;
            margin: 10px 0 0 0
        }

        .small_padding_table h5 {
            margin-top: 3px;
        }

        .right {
            text-align: right;
        }

        .right_pre {
            margin-top: -3px;
            color: red;
            text-align: right;
            <?php echo $final_adding_font ?>
        }

        .blue {
            color: blue
        }

        .red,
        .rowred td,
        tr td.rowred {
            color: red
        }

        .rowbold td {
            font-weight: bold;
        }

        .rowneg .posneg {
            color: darkgreen;
        }

        .small_table th,
        .small_table td {
            font-size: 8pt;
            padding: 2px;
        }

        .small_padding_table th,
        .small_padding_table td {
            padding: 1px;
            color: #000;
        }

        .address_right div {
            font-size: 10px;
            line-height: 10px;
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
            padding-top: 5px;
        }

        #footer,
        #footer * {
            font-size: 10px;
            line-height: 12px;
            margin: 0;
        }

        .header_tbl th {
            text-transform: uppercase;
            background: #555;
            text-align: center;
            height: 18px;
            color: #fff;
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

        tr th.title_tbl {
            background: #eee;
        }

        .smalltext {
            font-size: 9px;
        }

        .istyle {
            padding-top: 3px;
            font-size: 10px;
            line-height: 12px !important;
            font-style: italic;
        }

        body table th.rotate {
            text-align: left !important;
            min-width: 30px !important;
            position: relative;
            height: 160px;
            vertical-align: bottom;
        }

        body table th.rotate.labrsrotate {
            height: auto;
        }

        .rotate span {
            display: none;
        }

        body .rotate:after {
            left: 50%;
            white-space: nowrap;
            margin-left: <?php echo $rotate_margin_left ?>px;
            margin-top: <?php echo $rotate_margin_top ?>px;
            content: attr(title);
            position: absolute;
            -moz-transform: translateX(-50%) translateY(-50%) rotate(-90deg);
            -webkit-transform: translateX(-50%) translateY(-50%) rotate(-90deg);
            transform: translateX(-50%) translateY(-50%) rotate(-90deg);
        }

        body .rotate.labrsrotate:after {
            margin-top: 185px;
        }

        .antibiotic_tbl td.vl_R,
        .antibiotic_tbl td.vlmix_X {
            background: #ff3d35 !important;
            color: #fff !important;
        }

        .antibiotic_tbl td.vl_I,
        .antibiotic_tbl td.vlmix_V {
            background: #d6d6d6 !important;
        }

        body tr.thicklineabove td {
            border-top: 2px solid #000;
        }

        .controls sup,
        .resultcol sup {
            font-size: 9px;
            font-weight: normal;
        }

        table.comment_next_row tr td {
            border-color: #ddd;
            border-left: 0;
            border-right: 0;
        }

        table.comment_next_row tr th {
            border: none;
        }

        table tr.comment_at_bottom td {
            border-bottom: 0;
        }

        table.comment_next_row td.pos_neg {
            font-weight: bold;
        }

        table.comment_next_row td.conc {
            background: #fbfbfb;
        }

        table.border.comment_next_row {
            border: none;
        }

        .bottom_notes {
            padding-top: 8px;
        }

        .bottom_notes *,
        .group_notes,
        .group_notes * {
            font-size: 8pt;
        }

        .bottom_notes br,
        .group_notes br {
            line-height: 5px;
        }
    </style>
</head>

<body>

    <div id="header">
        <table style="width: 100%;border:0;border-collapse:collapse">
            <tr>
                <td style="width:25%;height:60px;" valign="top">
                    <?php
                    $logo_path = $logo_dir . $report['lab_logo'];
                    $logo_main = $report['lab_logo'];

                    if (!empty($notes['pdf_logo'])) {
                        $logo_main = $notes['pdf_logo'];
                    }
                    if (!empty($group_logo)) {
                        $logo_main = $group_logo;
                    }


                    $logo_second = ($report['ref_logo'] != $report['lab_logo']) ? $report['ref_logo'] : null;

                    $has_logo2 = file_exists($logo_dir . $logo_second) && !empty($logo_second);
                    if ($has_logo2 && $report['lab_ref_only_logo'] == 1) {
                        $logo_main = $logo_second;
                    }

                    if (file_exists($logo_dir . $logo_main) && !empty($logo_main)) {
                        $size = getimagesize($logo_dir . $logo_main);
                        $max_width = 200;
                        $max_height = ($size[0] > $size[1]) ? 60 : 70;
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
                <td style="width:44%;text-align: center">
                    <h2 style="color:<?php echo $report['color'] ?>;font-size:17px;font-weight: normal;line-height: 18px;"><?php echo $pdf_title ?></h2>
                </td>
                <td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">

                    <div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
                    <?php if (!empty($pdf_header_text)) {
                        echo apply_filters('the_content', $pdf_header_text);
                    } else { ?>
                        <div><?php echo $report['header_lab_name'] ?></div>
                        <div><?php echo $report['header_lab_address'] ?></div>
                    <?php
                        if (!empty($report['header_lab_second_line_hpdf'])) {
                            echo '<div>' . $report['header_lab_second_line_hpdf'] . '</div>';
                        }
                        if (!empty($report['header_lab_phone']) && !tr_get_option('ci_pdf_data', 'report_hilaphone')) {
                            echo '<div>Phone: ' . $report['header_lab_phone'] . '</div>';
                        }
                        if (!empty($report['header_lab_clia'])) {
                            echo '<div>CLIA ID: ' . $report['header_lab_clia'] . '</div>';
                        }
                    }

                    ?>
                </td>
            </tr>
        </table>

        <table class="small_padding_table" style="border-top:4px solid <?php echo $report['color'] ?>;border-bottom:4px solid <?php echo $report['color'] ?>">
            <tr>
                <td style="width:30%">
                    <h5>CLINIC INFORMATION</h5>
                </td>
                <td style="width:39%">
                    <h5>PATIENT INFORMATION</h5>
                </td>
                <td>
                    <h5>SPECIMEN INFORMATION</h5>
                </td>
            </tr>
            <tr>
                <td style="border-right:1px solid #ddd">
                    <table class="noboder small_table">
                        <tr>
                            <td style="width:23%">Name:</td>
                            <td><?php echo $report['requesting_practice'] ?></td>
                        </tr>
                        <tr>
                            <td>Address:</td>
                            <td><?php echo $clinic_address ?></td>
                        </tr>
                        <?php
                        if (tr_get_option('ci_pdf_data', 'pdf_show_clinic_phone') && !empty($report['cli_om-phone'])) {
                        ?>
                            <tr>
                                <td>Phone:</td>
                                <td><?php echo htmlspecialchars($report['cli_om-phone']) ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                        <tr>
                            <td>Provider:</td>
                            <td><?php echo htmlspecialchars($report['physician']) ?></td>
                        </tr>

                    </table>
                </td>
                <td style="border-right:1px solid #ddd;padding-left:10px;">
                    <table class="noboder small_table">
                        <tr>
                            <td style="width:18%">Name:</td>
                            <td><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
                        </tr>
                        <tr>
                            <td>DOB:</td>
                            <td>
                                <?php echo tr_pdd_display_date($report['patient_dob']) ?>
                                <span style="padding-left:30px">Gender:</span>
                                <span><?php echo htmlspecialchars($report['pa_gender']) ?></span>
                            </td>
                        </tr>
                        <?php if (!$hide_patient_phone) { ?>
                            <tr>
                                <td>Phone:</td>
                                <td><?php echo $report['pa_phone'] ?></td>
                            </tr>
                        <?php } ?>
                        <?php if (!$hide_patient_address) { ?>
                            <tr>
                                <td>Address:</td>
                                <td class="smalltext"><?php echo $patient_adress ?></td>
                            </tr>
                        <?php } ?>
                        <?php
                        if (tr_get_option('ci_pdf_data', 'pcr_patient_fvaccine') && !empty($report['pa_fully_vaccinated'])) {
                        ?>
                            <tr>
                                <td colspan="2">Vaccination Status: <?php echo $report['pa_fully_vaccinated'] == 'yes' ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php
                        }
                        if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) { ?>
                            <tr>
                                <td>Passport:</td>
                                <td><?php echo htmlspecialchars($report['pa_passport']) ?></td>
                            </tr>
                        <?php }

                        if (tr_get_option('ci_pdf_data', 'show_pa_mrnasid') && !empty($report['pa_mrn'])) { ?>
                            <tr>
                                <td>Patient ID:</td>
                                <td><?php echo htmlspecialchars($report['pa_mrn']) ?></td>
                            </tr>
                        <?php }
                        ?>
                        <?php if ($show_location && !empty($report['pa_location'])) { ?>
                            <tr>
                                <td>Loc:</td>
                                <td colspan="2"><?php echo htmlspecialchars($report['pa_location']) ?></td>
                            </tr>
                        <?php } ?>

                    </table>
                </td>
                <td style="padding-left:10px;">
                    <table class="noboder small_table" style="margin-bottom:1px;">
                        <tr>
                            <td style="width:38%">Accession:</td>
                            <td><?php echo $report['assen'] ?></td>
                        </tr>
                        <tr>
                            <td>Date Collected:</td>
                            <td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
                        </tr>
                        <tr>
                            <td>Date Received:</td>
                            <td><?php echo tr_pdd_display_date($report['spec_received'], 'm/d/Y H:i', false, true) ?></td>
                        </tr>
                        <tr>
                            <td>Date Reported:</td>
                            <td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
                        </tr>
                        <?php
                        if (tr_get_option('ci_pdf_data', 'report_st_top')) {
                        ?>
                            <tr>
                                <td>Specimen Type:</td>
                                <td><?php echo $report['sample_type'] ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                        <?php
                        if (!empty($report['dagnostic_codes1']) && tr_get_option('ci_pdf_data', 'pcrpdf_show_diagnostic')) {
                        ?>
                            <tr>
                                <td>Diagnostic code:</td>
                                <td><?php echo $report['dagnostic_codes1'] ?></td>
                            </tr>
                        <?php
                        }

                        if (!empty($pr_code) && $pr_code !== '0') {
                            //87635, 99211 for Vir
                        ?>
                            <tr>
                                <td>Procedural codes:</td>
                                <td><?php echo $pr_code ?></td>
                            </tr>
                        <?php
                        }
                        if (tr_get_option('ci_pdf_data', 'show_ehr')) {
                        ?>
                            <tr>
                                <td>EHR:</td>
                                <td><?php echo $report['p_ehr'] ?></td>
                            </tr>
                        <?php
                        }
                        ?>
                    </table>
                </td>
            </tr>
        </table>

    </div>
    <?php
    if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
        echo stripslashes($footer_html);
    }

    if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
        include('rejected_default.php');
    } else {
        $npos = 1;

        if (!$hide_controls) {
    ?>
            <table class="controls" style="margin-top:0;margin-bottom:2px;">
                <tr>
                    <td style="width:32%;border:2px solid #000">
                        <div style="display:inline-block;width:80px;border-bottom:1px solid #000;font-weight: bold;font-size:14px;">Controls</div>
                        <table class="nopadding" style="line-height: 12px">
                            <?php if ($remove_crossed == false) { ?>
                                <tr>
                                    <td>Patient Extraction Control <sup><?php echo $npos ?></sup></td>
                                    <td class="right">PASS</td>
                                </tr>
                                <tr>
                                    <td>Endogenous Positive Control <sup><?php echo $npos++ ?></sup></td>
                                    <td class="right">PASS</td>
                                </tr>
                            <?php } ?>
                            <tr>
                                <td>Pathogen Positive Control <sup><?php echo $npos++ ?></sup></td>
                                <td class="right">PASS</td>
                            </tr>
                            <tr>
                                <td>Pathogen Negative Control <sup><?php echo $npos++ ?></sup></td>
                                <td class="right">PASS</td>
                            </tr>
                        </table>
                    </td>
                    <td style="padding-left:20px;line-height: 16px;">
                        <?php
                        $npos = 1;
                        if ($remove_crossed == false) {
                        ?>
                            <div class="font8">(<?php echo $npos++ ?>) Endogenous control confirms sample collection,
                                DNA/RNA extraction, and assay enzyme activity
                            </div>
                        <?php } ?>
                        <div class="font8">(<?php echo $npos++ ?>) Positive control is synthetic inactive pathogen</div>
                        <div class="font8">(<?php echo $npos++ ?>) Negative control contains primers, probe, and enzymes
                            with no DNA/RNA template
                        </div>

                        <?php if ($remove_crossed == false) { ?>
                            <div class="font8">(<?php echo $npos++ ?>) A "Detected" result indicates the presence of a
                                pathogen (99.99% confidence) above the assay cutoff.
                            </div>
                            <?php /*<div class="font8">Assay cutoff is represented by CFU (bacteria), PFU (viruses) or Copy Number
                            (DNA)
                        </div>*/ ?>
                        <?php } ?>
                    </td>
                </tr>
            </table>

            <?php
        }
        //summary
        if (count($found_root_groups) > 1 && !$has_covid && isset($show_summary) && $show_summary) {
            ob_start();

            foreach ($drug_groups as $groupid => $groupdata) {

                $detected_type = true;
                if ($groupdata['showed']) continue;

                ob_start();
                foreach ($groupdata['tests'] as $arr) {
                    if (!$arr['detected_type']) {
                        $detected_type = false;
                    }
                    $pos_neg_vls = $pos_neg;

                    if ($arr['te_is_detected'] == '2') {

                        $pos_neg_vls['POL'] = 'Low';
                        $pos_neg_vls['NEG'] = 'Normal';
                        $pos_neg_vls['POH'] = 'High';
                        unset($pos_neg_vls['POS']);
                        if ($arr['pos-neg'] == 'POS') {
                            $arr['pos-neg'] = 'POH';
                        }
                    } else if ($arr['te_is_detected'] == '1') {
                        $pos_neg_vls['POS'] = 'Detected';
                        $pos_neg_vls['PVL'] = 'Detected-Very Low';
                        $pos_neg_vls['POL'] = 'Detected-Low';
                        $pos_neg_vls['POM'] = $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium';
                        $pos_neg_vls['POH'] = 'Detected-High';
                        $pos_neg_vls['PVH'] = 'Detected-Very High';
                        $pos_neg_vls['NEG'] = 'Not Detected';
                    }

                    $pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
                    if (isset($arr['flag_adding']) && !empty($arr['flag_adding'])) {
                        $pos_neg_str .= '<div>' . $arr['flag_adding'] . '</div>';
                    }


                    if (in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVL', 'PVH'))) {
            ?>
                        <tr class="<?php echo $report['bold_text_positive'] ? 'rowbold' : 'rowred' ?>">
                            <td>
                                <?php
                                echo htmlspecialchars($arr['drug']);
                                if (!empty($arr['te_desc'])) {
                                    echo '<div class="istyle">' . $arr['te_desc'] . '</div>';
                                }
                                ?>
                            </td>
                            <td class="center upper"><?php echo ($pos_neg_str) ?></td>
                            <?php if (!$groupdata['is_covid'] && !$hide_Cycle_Threshold) { ?>
                                <td class="center"><?php if ($groupdata['parent'] == 0) echo htmlspecialchars($arr['conc']) ?></td>
                            <?php } ?>
                            <td><?php echo trpdd_filter_comments_in_pdf($arr['comments']); ?></td>
                        </tr>
                    <?php
                    }
                    $has_report = true;
                }
                $drug_content = ob_get_clean();
                if (!empty($drug_content)) {
                    $type_text = '';
                    if ($arr['te_is_detected']) {
                        $type_text = '(Low/Medium/High)';
                    }

                    if ($detected_type || strtoupper($groupdata['name']) == 'RESPIRATORY') {
                        $type_text = 'reported as detected versus not detected';
                    }
                    ?>
                    <tr class="headernoborder">
                        <th colspan="<?php echo $groupdata['is_covid'] || $hide_Cycle_Threshold ? 3 : 4 ?>" class="center upper" style="border:none;font-size:14px">
                            SUMMARY <?php echo htmlspecialchars($groupdata['name'] . ($groupdata['parent'] > 0 || stripos($groupdata['name'], ', pcr') > 0 ? '' : ' Results')) ?>
                            <?php if (!empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
                                <span class="sample_type">Specimen: <?php echo $groupdata['sample_type'] ?></span>
                            <?php } ?>
                            <?php
                            if (function_exists('trpdd_pcr_get_specimen_source')) {
								$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
                                if (!empty($specimen_source)) {
                                    echo '<span class="sample_type" style="float:right;text-align:right">Specimen Source: ' . $specimen_source . '</span>';
                                }
                            }
                            ?>
                        </th>
                    </tr>
                    <tr class="title_tbl">
                        <th style="width:35%">Test Performed</th>
                        <th class="center" style="width:20%">Lab Result
                            <?php
                            if (!$remove_crossed) {
                                echo ' <sup>4</sup>';
                            }

                            if (!$groupdata['is_covid'] && $groupdata['parent'] == 0 && !$hide_Cycle_Threshold && !empty($type_text)) {
                                echo '<div>' . $type_text . '</div>';
                            }
                            ?>
                        </th>
                        <?php if (!$groupdata['is_covid'] && !$hide_Cycle_Threshold) { ?>
                            <th class="center" style="width:20%">Cycle Threshold*</th>
                        <?php } ?>
                        <th class="center">Comments</th>
                    </tr>
                <?php
                    echo $drug_content;
                }
            }

            $summary = ob_get_clean();
            if ($summary) {
                ?>
                <table class="tdborder small_table comment_next_row">
                    <?php echo $summary; ?>
                </table>
                <div class="break"></div>
        <?php
            }
        }
        if ($has_manual_antibiotic) {
            include('manual_antibiotic.php');
        }

        if ($need_break_summary_page) {
            echo '<div class="break"></div>';
        }
        ?>


        <?php

        $need_break_page = false;

        foreach ($drug_groups as $group => $groupdata) {
            $has_subgroup            = false;
            $count_row               = 0;
            $detected_type           = true;
            $show_group_range        = isset($groupdata['other_options']['show_range']) && $groupdata['other_options']['show_range'];
            $show_value_in_result    = isset($groupdata['other_options']['show_value_in_result']) && $groupdata['other_options']['show_value_in_result'];
            $show_units_column       = isset($groupdata['other_options']['show_units_col']) && $groupdata['other_options']['show_units_col'];
            $percent_positive        = isset($groupdata['other_options']['percent_positive']) && $groupdata['other_options']['percent_positive'];
            $dont_show_comments          = isset($groupdata['other_options']['split_table']) && $groupdata['other_options']['split_table'];

            if (isset($groupdata['parent']) && $groupdata['parent'] > 0 && (!$groupdata['show'] || $groupdata['showed'])) continue;


            if (count($groupdata['subgroups']) > 0) {
                ob_start();

                $showed_smalltbl = false;
                $closed_smalltbl = false;
                $has_nograme = false;

                $icount = 0;
                $lastsmallcol = 0;


                foreach ($groupdata['subgroups'] as $subid => $subname) {
                    $subgroupdata = isset($drug_groups[$subid]) ? $drug_groups[$subid] : false;

                    if (!$subgroupdata || count($subgroupdata['tests']) == 0) continue;

                    $is_UA_RESULTING = stripos($subgroupdata['name'], 'UA RESULTING') !== false;

                    $is_split_tbl     = false;
                    $small_col         = 0;

                    $comment_below  = isset($subgroupdata['other_options']['comment_below']) && $subgroupdata['other_options']['comment_below'] ? true : false;
                    if (isset($groupdata['other_options']['comment_below']) && $groupdata['other_options']['comment_below']) {
                        $comment_below = true;
                    }

                    $sub_show_value_in_result     = isset($subgroupdata['other_options']['show_value_in_result']) && $subgroupdata['other_options']['show_value_in_result'];
                    $sub_show_range_in_result     = isset($subgroupdata['other_options']['show_range']) && $subgroupdata['other_options']['show_range'];

                    if ($is_UA_RESULTING) {
                        $sub_show_value_in_result = true;
                    }

                    $is_smalltbl = in_array(strtoupper($subname), array('GRAM NEGATIVE', 'GRAM POSITIVE', 'NO GRAM STAIN', 'FUNGI', 'ATYPICAL', 'PROTOZOA', 'OTHER'));
                    if (isset($subgroupdata['other_options']['pdf_small_tbl']) && $subgroupdata['other_options']['pdf_small_tbl'] == 3) {
                        $smalltbl_col = 0;
                        $is_smalltbl = false;
                    } else if (isset($subgroupdata['other_options']['pdf_small_tbl']) && $subgroupdata['other_options']['pdf_small_tbl'] > 0) {
                        $small_col = $subgroupdata['other_options']['pdf_small_tbl'];
                        $is_smalltbl = true;
                    }
                    if ($subgroupdata['other_options']['split_table']) {
                        $is_smalltbl = false;
                    }

                    if (isset($_GET['debugsm'])) {
                        var_dump($subname);
                        var_dump($is_smalltbl);
                        echo '<br><br>';
                    }


                    $table_class = ' is_smalltbl';
                    $style = '';

                    if ($is_smalltbl) {
                        if (!$showed_smalltbl) {
                            echo '<table><tr><td style="width:50%;padding-left:0">';
                            $showed_smalltbl = true;
                        }

                        $icount++;


                        if (($has_nograme && $icount == 3) || ($small_col == 1 && $icount > 1) || ($small_col > 0 && $lastsmallcol == $small_col)) {
                            echo '<br style="line-height:4px;"/>';
                        } else if ($icount == 2 && stripos($subname, 'no gram stain') !== false) {
                            $has_nograme = true;
                            $icount--;
                            echo '<br style="line-height:4px;"/>';
                        } else if ($icount == 2 || $small_col == 2) {
                            echo '</td><td style="width:50%;padding-right:0">';
                        } else if ($icount == 3 && count($groupdata['subgroups']) == 4) {
                            echo '<br style="line-height:4px;"/>';
                        } else if ($icount == 3) {
                            echo '</tr><tr><td style="width:50%;padding-left:0">';
                        } else if ($icount == 4) {
                            echo '</td><td style="width:50%;padding-right:0">';
                        } else if ($icount >= 5) {
                            echo '<br style="line-height:4px;"/>';
                        }
                        //echo $icount;
                        $lastsmallcol = $small_col;
                    } else if ($showed_smalltbl) {
                        echo '</td></tr></table>';
                        if ($enable_utinrow) {
                            echo '<br style="line-height:4px;"/>';
                        }
                        $closed_smalltbl = true;
                        $showed_smalltbl = false;

                        if ($subgroupdata['other_options']['split_table']) {
                            $is_split_tbl = true;
                            $table_class = ' split_tbl ';
                        }
                    }

                    $cols_count = $is_smalltbl ? 2 : 3;

                    $colwidths = array(
                        1 => $is_smalltbl ? '70%' : '40%',
                        2 => '30%',
                    );

                    if ($is_split_tbl) {
                        $cols_count = 5;
                        $colwidths = array(
                            1 => '35%',
                            2 => '15%',
                        );
                    }
                    if ($sub_show_value_in_result) {
                        $cols_count++;
                        if ($is_split_tbl) {
                            $cols_count++;
                        }
                        $colwidths[1] = $is_split_tbl ? '35%' : ($is_smalltbl ? '55%' : '40%');
                        $colwidths[2] = $is_split_tbl ? '15%' : '18%';
                    }

                    if ($percent_positive) {
                        $cols_count++;
                        if ($is_split_tbl) {
                            $cols_count++;
                        }
                    }

                    if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
                        $cols_count++;
                        if ($is_split_tbl) {
                            $cols_count++;
                        }

                        $colwidths[1] = $is_split_tbl ? '25%' : ($is_smalltbl ? '55%' : '38%');
                        $colwidths[2] = $is_split_tbl ? '11%' : ($is_smalltbl ? '24%' : '16%');
                    }

                    $subname_tt = $subname;
                    if (count($groupdata['subgroups']) == 1) {
                        $subname_tt .= ' Results';
                    }
        ?>
                    <div style="<?php echo $style ?>">
                        <table class="border tdborder small_table <?php echo $table_class ?>">
                            <thead>
                                <?php if (count($groupdata['subgroups']) > 1) { ?>
                                    <tr class="header_tbl">
                                        <th colspan="<?php echo $cols_count ?>"><?php echo $subname_tt ?></th>
                                    </tr>
                                <?php } ?>

                                <tr class="title_tbl">
                                    <th style="width:<?php echo $colwidths[1] ?>"><?php echo stripos($subname, 'ANTIBIOTIC RESISTANCE') !== false ? 'Marker' : $group_title ?></th>

                                    <th class="resultcol" style="width:<?php echo $colwidths[2] ?>">Result<?php if (!$remove_crossed) echo ' <sup>4</sup>' ?></th>
                                    <?php

                                    if ($sub_show_value_in_result) {
                                        echo '<th style="width:' . $colwidths[2] . '">Flag</th>';
                                    }
                                    if (!$is_split_tbl && $percent_positive) {
                                        echo '<th style="width:10%">% Positive</th>';
                                    }

                                    if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
                                        echo '<th class="small" style="width:' . $colwidths[2] . '">Reference Range</th>';
                                    } else if ($groupdata['is_covid'] && tr_get_option('ci_pdf_data', 'pcrcovid_rangecl')) {
                                        echo '<th>Reference Range</th>';
                                    }

                                    if ($is_split_tbl) {
                                        echo '<th class="space_cell" style="width:1% !important;border:none;background:none"></th>';
                                        echo '<th style="width:' . $colwidths[1] . '">' . $group_title . '</th>';
                                        echo '<th class="resultcol" style="width:' . $colwidths[2] . '">Result</th>';
                                        if ($sub_show_value_in_result) {
                                            echo '<th style="width:' . $colwidths[2] . '">Flag</th>';
                                        }
                                        if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
                                            echo '<th class="small" style="width:' . $colwidths[2] . '">Reference Range</th>';
                                        }
                                    } else if (!$is_smalltbl) {
                                        echo '<th>' . ($enable_utinrow ? '' : 'Comments') . '</th>';
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($is_split_tbl) {
                                    //echo '<tr><td colspan=2 class="splittd" style="margin:0;border:0;padding:0"><table class="small_table split_firsttbl">';
                                    $tmp_tests = array();
                                    foreach ($subgroupdata['tests'] as $arr) {
                                        $tmp_tests[] = $arr;
                                    }
                                    $subgroupdata['tests'] = $tmp_tests;
                                    $has_close_row = false;

                                    $comments_row = array();
                                    foreach ($subgroupdata['tests'] as $isub => $arr) {

                                        $pos_neg_vls = $pos_neg;

                                        if ($arr['te_is_detected']) {
                                            $pos_neg_vls['POS'] = 'Detected';
                                            $pos_neg_vls['PVL'] = 'Detected-Very Low';
                                            $pos_neg_vls['POL'] = 'Detected-Low';
                                            $pos_neg_vls['POM'] = $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium';
                                            $pos_neg_vls['POH'] = 'Detected-High';
                                            $pos_neg_vls['PVH'] = 'Detected-Very High';
                                            $pos_neg_vls['NEG'] = 'Not Detected';
                                        }
                                        $count_row++;

                                        $pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
                                        if (isset($arr['flag_adding']) && !empty($arr['flag_adding'])) {
                                            $pos_neg_str .= '<div>' . $arr['flag_adding'] . '</div>';
                                        }

                                        $row_class      = in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVL', 'PVH')) ? ($report['bold_text_positive'] ? 'rowbold' : 'rowred') : '';
                                        if ($arr['pos-neg'] == 'NEG' && $report_green_neg) {
                                            $row_class = 'rowneg';
                                        }

                                        if ($isub % 2 == 0) {
                                            echo '<tr>';
                                            $has_close_row = false;
                                            $comments_row = array();
                                        }

                                        echo '<td class="' . $row_class . '">' . htmlspecialchars($arr['te_name']) . '</td>';
                                        echo '<td class="center ' . $row_class . '" >' . ($pos_neg_str) . '</td>';
                                        if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
                                            echo '<td class="center">Not Detected</td>';
                                        }
                                        if (!empty($arr['comments']) && $comment_below) {
                                            $comments_row[$isub % 2] = trpdd_filter_comments_in_pdf($arr['comments']);
                                        }
                                        if ($isub % 2 == 1) {
                                            echo '</tr>';
                                            $has_close_row = true;

                                            if ($comment_below && !empty($comments_row)) {
                                                echo '<tr class="rowcomments">';
                                                echo '<td colspan="' . ($cols_count / 2) . '" >' . (isset($comments_row[0]) ? $comments_row[0] : '') . '</td>';
                                                echo '<td class="space_cell"></td>';
                                                echo '<td colspan="' . ($cols_count / 2) . '" >' . (isset($comments_row[1]) ? $comments_row[1] : '') . '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<td class="space_cell"></td>';
                                        }




                                        $has_report = true;
                                    }

                                    if (!$has_close_row) {
                                        if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
                                            echo '<td></td>';
                                        }
                                        echo '<td></td><td></td></tr>';

                                        if ($comment_below && !empty($comments_row)) {
                                            echo '<tr class="rowcomments">';
                                            echo '<td colspan="' . ($cols_count / 2) . '" >' . (isset($comments_row[0]) ? $comments_row[0] : '') . '</td>';
                                            echo '<td class="space_cell"></td>';
                                            echo '<td colspan="' . ($cols_count / 2) . '" >' . (isset($comments_row[1]) ? $comments_row[1] : '') . '</td>';
                                            echo '</tr>';
                                        }
                                    }
                                } else {


                                    foreach ($subgroupdata['tests'] as $arr) {
                                        if ($arr['te_abrev'] == 'RppH') {
                                            continue;
                                        }

                                        $pos_neg_vls = $pos_neg;

                                        if ($arr['te_is_detected']) {
                                            $pos_neg_vls['POS'] = 'Detected';
                                            $pos_neg_vls['PVL'] = 'Detected-Very Low';
                                            $pos_neg_vls['POL'] = 'Detected-Low';
                                            $pos_neg_vls['POM'] = $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium';
                                            $pos_neg_vls['POH'] = 'Detected-High';
                                            $pos_neg_vls['PVH'] = 'Detected-Very High';
                                            $pos_neg_vls['NEG'] = 'Not Detected';
                                        }
                                        //var_dump($pos_neg_vls);
                                        $count_row++;
                                        $pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
                                        if (isset($arr['flag_adding']) && !empty($arr['flag_adding'])) {
                                            $pos_neg_str .= '<div>' . $arr['flag_adding'] . '</div>';
                                        }

                                        $row_class      = in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVL', 'PVH')) ? ($report['bold_text_positive'] ? 'rowbold' : 'rowred') : '';
                                        if ($arr['pos-neg'] == 'NEG' && $report_green_neg) {
                                            $row_class = 'rowneg';
                                        }

                                        echo '<tr class="' . $row_class . '">';
                                        echo '<td style="width:' . $colwidths[1] . '">' . htmlspecialchars($arr['te_name']) . '</td>';
                                        if ($sub_show_value_in_result) {
                                            echo '<td class="center" style="width:' . $colwidths[2] . '">' . $arr['conc'] . '</td>';
                                        }

                                        echo '<td class="center" style="width:' . $colwidths[2] . '">' . ($pos_neg_str) . '</td>';
                                        if ($percent_positive) {
                                            echo '<td class="center" style="width:10%">' . $arr['percent_positive'] . '</td>';
                                        }

                                        if (tr_get_option('ci_result_settings_pcr', 'range_not_col')) {
                                            echo '<td class="center">Not Detected</td>';
                                        } else if ($groupdata['is_covid'] && tr_get_option('ci_pdf_data', 'pcrcovid_rangecl')) {
                                            echo '<td class="center">Negative</td>';
                                        }
                                        if (!$is_smalltbl) {
                                            echo '<td>' . trpdd_filter_comments_in_pdf($arr['comments']) . '</td>';
                                        }

                                        echo '</tr>';
                                        if ($is_smalltbl && $comment_below && !empty($arr['comments'])) {
                                            echo '<tr class="rowcomments"><td colspan="' . $cols_count . '" >' . trpdd_filter_comments_in_pdf($arr['comments']) . '</td></tr>';
                                        }
                                        $has_report = true;
                                    }
                                }

                                ?>
                            </tbody>
                        </table>
                        <?php
                        if (!empty($subgroupdata['comments'])) {
                            $subgroupdata['comments'] = str_replace('[report_date]', date('m/d/Y h:iA', strtotime($report['approve_date'])), $subgroupdata['comments']);
                            echo '<div style="padding-top:3px;" class="subgroupnotes">' . trpdd_filter_comments_in_pdf($subgroupdata['comments']) . '</div>';
                        }
                        ?>
                    </div>
                <?php
                }
                if (isset($_GET['ddd33'])) {
                    var_dump($showed_smalltbl);
                    var_dump($closed_smalltbl);
                }
                if ($showed_smalltbl && !$closed_smalltbl) {
                    echo '</td></tr></table>';
                }

                $subcontent = ob_get_clean();


                $group_tt = $groupdata['name'];
                if (stripos($groupdata['name'], 'panel') === false) {
                    $group_tt = $groupdata['name'] . ' Results';
                }
                if (stripos($groupdata['name'], 'antigen') !== false && count($groupdata['tests']) < 2) {
                    $group_tt = $groupdata['name'];
                } else if (stripos($groupdata['name'], ', pcr') > 0) {
                    $group_tt = $groupdata['name'];
                }
                if (isset($groupdata['other_options']['panel_pdf_title']) && !empty($groupdata['other_options']['panel_pdf_title'])) {
                    $group_tt = $groupdata['other_options']['panel_pdf_title'];
                }

                if (count($groupdata['subgroups']) == 1) {
                    $group_tt = $subname_tt;
                }
                $has_subgroup = true;

                if ($need_break_page) {
                    echo '<div class="break"></div>';
                    $need_break_page = false;
                }



                ?>

                <table class="noboder small_table">
                    <tr class="headernoborder">
                        <th style="width:<?php echo strlen($group_tt) > 33 ? '20%' : '30%' ?>;border:none"></th>
                        <th colspan="4" class="center " style="border:none;font-size:14px;">
                            <?php echo htmlspecialchars($group_tt) ?>
                        </th>
                        <th style="width:<?php echo strlen($group_tt) > 33 ? '20%' : '30%' ?>;border:none">
                            <?php if (!tr_get_option('ci_pdf_data', 'report_st_top') && !empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
                                <span class="sample_type">Specimen: <?php echo $groupdata['sample_type'] ?></span>
                                <?php } ?>&nbsp;
                            <?php
                            if (function_exists('trpdd_pcr_get_specimen_source')) {
								$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
                                if (!empty($specimen_source)) {
                                    echo '<span class="sample_type" style="float:right;text-align:right">Specimen Source: ' . $specimen_source . '</span>';
                                }
                            }
                            ?>
                        </th>
                    </tr>

                </table>
                <?php if ($enable_utinrow) {
                    echo '<br>';
                }

                ?>
                <?php
                echo $subcontent;
                ?>
                <div style="clear:both;margin-bottom:2px"></div>

            <?php
            }


            ob_start();
            $is_UA_RESULTING = stripos($groupdata['name'], 'UA RESULTING') !== false;
            $has_main_tests = false;
            foreach ($groupdata['tests'] as $arr) {
                if (!$arr['detected_type']) {
                    $detected_type = false;
                }
                $pos_neg_vls = $pos_neg;
                if ($arr['te_is_detected'] == '2') {

                    $pos_neg_vls['POL'] = 'Low';
                    $pos_neg_vls['NEG'] = 'Normal';
                    $pos_neg_vls['POH'] = 'High';
                    unset($pos_neg_vls['POS']);
                    if ($arr['pos-neg'] == 'POS') {
                        $arr['pos-neg'] = 'POH';
                    }
                } else if ($arr['te_is_detected'] == '1') {
                    $pos_neg_vls['POS'] = 'Detected';
                    $pos_neg_vls['PVL'] = 'Detected-Very Low';
                    $pos_neg_vls['POL'] = 'Detected-Low';
                    $pos_neg_vls['POM'] = $result_settings['pos_moderate'] ? 'Detected-Moderate' : 'Detected-Medium';
                    $pos_neg_vls['POH'] = 'Detected-High';
                    $pos_neg_vls['PVH'] = 'Detected-Very High';
                    $pos_neg_vls['NEG'] = 'Not Detected';
                }

                $pos_neg_str = $pos_neg_vls[$arr['pos-neg']];
                if (isset($arr['flag_adding']) && !empty($arr['flag_adding'])) {
                    $pos_neg_str .= '<div>' . $arr['flag_adding'] . '</div>';
                }


                $count_row++;
                $row_class = in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PRP', 'PVL', 'PVH')) ? ($report['bold_text_positive'] ? 'rowbold' : 'rowred') : '';
                if ($arr['pos-neg'] == 'NEG' && $report_green_neg) {
                    $row_class = 'rowneg';
                }
            ?>
                <tr class="<?php echo $row_class ?>">
                    <?php
                    if ($is_UA_RESULTING) {
                    ?>
                        <td><?php
                            echo htmlspecialchars($arr['te_name']);
                            if (!empty($arr['te_desc'])) {
                                echo '<div class="istyle">' . $arr['te_desc'] . '</div>';
                            }
                            ?></td>
                        <td class="center posneg"><?php echo ($pos_neg_str) ?></td>
                        <td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
                        <td class="center"><?php echo htmlspecialchars($arr['te_units']) ?></td>
                        <td class="center"><?php echo $arr['range']; ?></td>
                    <?php
                    } else {
                    ?>
                        <td><?php
                            echo htmlspecialchars($arr['te_name']);
                            if (!empty($arr['te_desc'])) {
                                echo '<div class="istyle">' . $arr['te_desc'] . '</div>';
                            }
                            ?>
                        </td>
                        <?php
                        if ($show_value_in_result) {
                            echo '<td class="center  conc bg_col">' . $arr['conc'] . '</td>';
                        } else {
                            echo '<td class="center posneg">' . ($pos_neg_str) . '</td>';
                        }
                        ?>
                        <?php if (!$groupdata['is_covid'] && !$hide_Cycle_Threshold) { ?>
                            <td class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
                        <?php }
                        if ($show_value_in_result) {
                            echo '<td class="center  posneg bg_col">' . ($pos_neg_str) . '</td>';
                        }
                        if ($show_group_range) {
                            echo '<td class="center">' . $arr['range'] . '</td>';
                        }
                        if (!$dont_show_comments) {
                        ?>
                            <td><?php echo trpdd_filter_comments_in_pdf($arr['comments']); ?></td>
                    <?php
                        }
                    }
                    ?>
                </tr>
            <?php
                $has_report = true;
                $has_main_tests = true;
            }



            $drug_content = ob_get_clean();
            if (!empty($drug_content)) {
                $type_text = '';
                if ($detected_type || strtoupper($groupdata['name']) == 'RESPIRATORY') {
                    $type_text = 'reported as detected versus not detected';
                }

                $group_tt = $groupdata['name'];
                if (stripos($groupdata['name'], 'panel') === false) {
                    $group_tt = $groupdata['name'] . ' Results';
                }

                if (stripos($groupdata['name'], 'antigen') !== false && count($groupdata['tests']) < 2) {
                    $group_tt = $groupdata['name'];
                } else if (stripos($groupdata['name'], ', pcr') > 0) {
                    $group_tt = $groupdata['name'];
                } else if (stripos($one_group_name, 'Express 1hr PCR') !== false || stripos($one_group_name, 'Express PCR') !== false) {
                    $group_tt = 'SARS-COV-2 PCR NUCLEIC ACID';
                }

                $cols_num = 4;
                if ($groupdata['is_covid'] || $hide_Cycle_Threshold) {
                    $cols_num = 3;
                }
                if ($show_group_range) {
                    $cols_num++;
                }
                if ($show_value_in_result) {
                    $cols_num++;
                }
                if ($dont_show_comments) {
                    $cols_num--;
                }

                if ($need_break_page) {
                    $need_break_page = false;
                    if (!$has_subgroup) {
                        echo '<div class="break"></div>';
                    }
                }
            ?>
                <div style="<?php if (($count_row > 0 && $count_row < 5) || $is_UA_RESULTING) echo 'page-break-inside: avoid;' ?>padding-bottom:8px">
                    <?php
                    if ($is_UA_RESULTING) {
                    ?>
                        <table class="tdborder small_table comment_next_row">
                            <tr class="header_tbl">
                                <th colspan="5" class="center upper">
                                    <?php echo htmlspecialchars($group_tt) ?>
                                    <?php if (!empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
                                        <span class="sample_type">Specimen: <?php echo $groupdata['sample_type'] ?></span>
                                    <?php } ?>
                                    <?php
                                    if (function_exists('trpdd_pcr_get_specimen_source')) {
								$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
                                        if (!empty($specimen_source)) {
                                            echo '<span class="sample_type" style="float:right;text-align:right">Specimen Source: ' . $specimen_source . '</span>';
                                        }
                                    }
                                    ?>
                                </th>
                            </tr>
                            <thead>
                                <tr class="title_tbl">
                                    <th>Test</th>
                                    <th>Test Outcome</th>
                                    <th>Measured Result</th>
                                    <th>Units</th>
                                    <th>Reference Range</th>
                                </tr>
                            </thead>
                        <?php
                    } else {
                        ?>
                            <table class="tdborder small_table comment_next_row" <?php if ($has_subgroup) echo 'style="margin-top:10px"' ?>>
                                <?php if (!$has_subgroup) { ?>
                                    <tr class="headernoborder">
                                        <th colspan="<?php echo $cols_num ?>" class="center upper" style="border:none;font-size:14px;">
                                            <?php echo htmlspecialchars($group_tt) ?>
                                            <?php if (!empty($groupdata['sample_type']) && (!function_exists('trpdd_pcr_show_specimen_source') || !trpdd_pcr_show_specimen_source($report, $groupdata))) { ?>
                                                <span class="sample_type">Specimen: <?php echo $groupdata['sample_type'] ?></span>
                                            <?php } ?>
                                            <?php
                                            if (function_exists('trpdd_pcr_get_specimen_source')) {
								$specimen_source = trpdd_pcr_get_specimen_source($report, $groupdata);
                                                if (!empty($specimen_source)) {
                                                    echo '<span class="sample_type" style="float:right;text-align:right">Specimen Source: ' . $specimen_source . '</span>';
                                                }
                                            }
                                            ?>
                                        </th>
                                    </tr>
                                <?php } else {
                                ?>
                                    <tr class="header_tbl">
                                        <th colspan="<?php echo $cols_num ?>"><?php echo $group_tt ?></th>
                                    </tr>
                                <?php
                                } ?>
                                <thead>
                                    <tr class="<?php echo !$has_subgroup ? 'header_tbl' : 'title_tbl' ?>">
                                        <th style="width:<?php echo $show_value_in_result ? 35 : 37 ?>%">Test Performed</th>
                                        <th class="center resultcol" style="width:20%">Result <?php if (!$remove_crossed) echo ' <sup>4</sup>' ?></th>
                                        <?php if (!$groupdata['is_covid'] && !$hide_Cycle_Threshold) { ?>
                                            <th class="center" style="width:20%">Cycle Threshold*</th>
                                        <?php }
                                        if ($show_value_in_result) {
                                            echo '<th>Flag</th>';
                                        }
                                        if ($show_group_range) {
                                            echo '<th>Reference Range</th>';
                                        } ?>
                                        <?php if (!$dont_show_comments) { ?>
                                            <th class="center">Comments</th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                            <?php
                        }
                            ?>
                            <?php
                            echo $drug_content;
                            ?>

                            </table>
                </div>
    <?php
            }

            if ((!empty($drug_content) || !empty($subcontent)) && !empty($groupdata['comments'])) {
                $groupdata['comments'] = str_replace('[report_date]', date('m/d/Y h:iA', strtotime($report['approve_date'])), $groupdata['comments']);
                echo '<div style="padding-top:3px;" class="group_notes">' . trpdd_filter_comments_in_pdf($groupdata['comments']) . '</div>';
            }

            if (isset($groupdata['other_options']['breakpage']) && $groupdata['other_options']['breakpage'] && ($has_main_tests || $has_subgroup)) {
                $need_break_page = true;
            }

            //end group
        }


        $pdf_notes = trpdd_get_pdf_note($report, 'pcr');
        if (!empty($pdf_file_adding)) {
            $report['pdf2file'] = $pdf_file_adding;
        }
        if (isset($_GET['debug_note'])) {
            var_dump($pdf_file_adding);
        }

        if (!empty($pdf_notes)) {
            echo '<div  class="bottom_notes">';
            echo trpdd_filter_comments_in_pdf($pdf_notes);
            echo '</div>';
        }
    } //end if rejected

    include('_approved_by.php');


    if (!empty($notes_pos_neg)) {
        echo '<div class="bottom_notes">';
        echo trpdd_filter_comments_in_pdf($notes_pos_neg);
        echo '</div>';
    }

    if (!empty($report['notes_report'])) {
        echo '<div style="padding-top:6px" class="notes_report">';
        //echo '<span style="font-weight:bold;padding:0;line-height:22px;">Notes: </span>';
        echo trpdd_filter_comments_in_pdf($report['notes_report']);
        echo '</div>';
    }

    if (!empty($notes['result_imagenote'])) {

        echo '<div style="text-align:center">';
        echo '<img src="' . $logo_dir . '/' . $notes['result_imagenote'] . '" style="width:auto;margin:0 auto;height:auto"/>';
        echo '</div>';
    }

    if (!isset($notes['onlyscovid'])) {
        $notes['onlyscovid'] = 0;
    }
    if (!empty($notes['result_image']) && (!@$notes['onlyscovid'] || $has_covid)) {

        echo '<div class="break"></div>';
        echo '<div style="background-color: white; z-index: 2;position:fixed;height:1200px">';
        echo '<img src="' . $logo_dir . '/' . $notes['result_image'] . '" style="width:100%;margin:0 auto;height:auto"/>';
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
