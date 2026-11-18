<?php
global $pdf_header_text;
// thewayfindernetwork
$margin_bottom = 0;
$bottom_comments = array();
//$pdf_title = 'Health and Wellness Report';

$html_class = 'dompdf_old';
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
    $margin_bottom = $footer_height;
    $footer_height = 0.3;
    $html_class = 'dompdf_new';
}
?>
<!DOCTYPE html>
<html class="<?php echo $html_class ?>">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta id="final" content="<?php echo $report['final_results'] ?>" />
    <title><?php echo $report['assen'] ?></title>
    <style>
        @page {
            margin: 0.5cm 0.5cm <?php echo $footer_height ?>cm 0.5cm;
        }

        body {
            margin-top: 5.4cm;
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

        table.bordertopbot {
            border-top: 2px solid #222;
            border-bottom: 1px solid #222
        }

        table.bordertop {
            border-top: 2px solid #222;
        }

        .borderout th {
            border: 1px solid #222;
            border-top-width: 2px
        }

        table.tdborder td,
        table.tdborder th {
            border: 1px solid #777;
        }

        table.borderline td {
            border-bottom: 2px solid #D8D8D8
        }

        table.borderline tr.has_desc td {
            border: 0
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
            font-size: 9pt;
            color: #000000;
        }

        .fontNormalRed {
            font-family: 'Arial';
            font-size: 9pt;
            font-weight: bold;
        }

        .fontSmall {
            font-family: 'Arial';
            font-size: 9pt;
            color: #000000;
        }

        .fontBold {
            font-family: 'Arial';
            font-size: 9pt;
            color: #000000;
            font-weight: bold;
        }

        .fontBoldSmall {
            font-family: 'Arial';
            font-size: 9pt;
            color: #000000;
            font-weight: bold;
        }

        .fontBoldWhite {
            font-family: 'Arial';
            font-size: 9pt;
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

        .right {
            text-align: right;
        }

        .right_pre {
            float: right;
            margin-top: -15px;
            color: red;
            text-align: right;
            <?php echo $final_adding_font ?>
        }

        .has_flag td {
            font-weight: bold;
        }

        .has_flag .drug,
        .has_flag .conc {
            background: #eee
        }

        table .font_blue td,
        .blue {
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

        .group_name {
            font-weight: bold;
        }

        .group_name .normal {
            text-transform: none;
            font-weight: normal;
        }

        .grouppos {
            padding-left: 30px;
            font-size: 12px;
        }

        .small_table th,
        .small_table td {
            font-size: 8pt;
            padding: 2px;
            color: #000;
        }

        .small_padding_table th,
        .small_padding_table td {
            padding: 2px;
            color: #000;
        }

        .address_right div {
            font-size: 10px;
            line-height: 10px;
        }

        .fontDejavu {
            font-family: "DejaVu Sans";
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
            padding-top: 5px;
            padding-right: 0px;
        }

        .drug_flag {
            color: blue;
        }

        .te_desc td {
            font-style: italic;
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

        .has_covid td {
            color: red;
        }

        ul {
            margin-top: 2px;
            margin-bottom: 0px;
        }

        tr.posneg_PCL .conc,
        tr.posneg_PCL .pos_neg,
        tr.posneg_PCH .conc,
        tr.posneg_PCH .pos_neg,
        tr.posneg_POS .conc,
        tr.posneg_POS .pos_neg,
        tr.posneg_POL .conc,
        tr.posneg_POL .pos_neg,
        tr.posneg_POH .conc,
        tr.posneg_POH .pos_neg,
        tr.posneg_POM .conc,
        tr.posneg_POM .pos_neg {
            color: #CC5500 !important;
            font-weight: bold;
            text-transform: uppercase;
        }

        tr.posneg_PCL .conc,
        tr.posneg_PCL .pos_neg,
        tr.posneg_PCH .conc,
        tr.posneg_PCH .pos_neg {
            color: red !important;
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

        table.comment_next_row td.conc {
            background: #fbfbfb;
        }

        td.bold {
            font-weight: bold;
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
            margin-left: -12px;
            white-space: nowrap;
            margin-top: 150px;
            content: attr(title);
            position: absolute;
            -moz-transform: translateX(-50%) translateY(-50%) rotate(-90deg);
            -webkit-transform: translateX(-50%) translateY(-50%) rotate(-90deg);
            transform: translateX(-50%) translateY(-50%) rotate(-90deg);
        }

        .dompdf_new body .rotate:after {
            margin-left: 0px;
            margin-top: 80px;
        }

        .valignmid {
            vertical-align: middle;
        }

        .symbol {
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
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

        .header_tbl th {
            text-transform: uppercase;
            background: #555;
            text-align: center;
            height: 18px;
            color: #fff;
        }


        .table1 {
            margin-bottom: 15px;
        }

        .table1 th {
            background: #e2efda;
            height: 30px;
        }

        .table1 tr {
            border-bottom: 1px solid #ddd;
            border-top: 1px solid #ddd;
        }

        .table1 tr.yellow th,
        .bg_yellow {
            background: #fff2cc;
        }

        .table1 tr.green th,
        .bg_green {
            background: #e2efda;
        }

        .table1 tr.orange th,
        .bg_orange {
            background: #ebbca1;
        }

        .table1 tr.dark_orange th,
        .bg_dark_orange {
            background: #d9910d;
        }

        .table1 tr.red th,
        .bg_red {
            background: #e85151;
        }

        .table1 tr.dark_red th,
        .bg_dark_red {
            background: #d51d1d;
        }

        .table1 .hco1 {
            width: 31%;
        }

        .table1 .hco2 {
            width: 12%;
            text-align: center;
        }

        .table1 .hco11 {
            width: 43%;
        }

        .table1 .sumcol1 {
            width: 9%;
        }

        .table1 .sumcol2 {
            width: 22%;
        }

        .table1 .sumcol3 {
            width: 12%;
        }

        .detailtbl {
            margin-bottom: 15px;
        }

        .detailtbl td.col2 {

            width: 50%;
        }

        .detailtbl td th {
            width: 40%;
            vertical-align: top;
            padding: 2px;
        }

        .detailtbl td td {
            padding: 2px;
        }

        .mchartbl {}

        .mchartbl td.col3 {
            width: 33%;
            text-align: center;
        }

        .mchartbl h4 {
            margin-top: 15px;
            margin-bottom: 20px;
        }

        .chartbl1 {
            width: 80%;
        }

        .chartbl1 tr td.legend {
            height: 18px;
            padding: 0 3px 0 0;
            text-align: right;
            border-right: 2px solid #aaa;
            width: 40px;
        }

        .clinical_pharmacy_guidance table {
            border: none;
        }

        .clinical_pharmacy_guidance table td {
            border: none;
        }

        .notes_report p {
            margin: 0;
            padding: 0;

        }

        .notes_report br {
            line-height: 4px;
        }
    </style>
</head>

<body>


    <div id="header">
        <table style="width: 100%;border:0;border-collapse:collapse">
            <tr>
                <td style="width:34%;height:60px;" valign="top">
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
                        $max_width = 250;
                        $max_height = ($size[0] > $size[1]) ? 70 : 88;

                        echo '<img border="0" style="max-width: ' . $max_width . 'px; height: auto;max-height:' . $max_height . 'px;margin-top:-5px" src="' . $logo_dir . $logo_main . '" />';
                    }

                    ?>

                </td>
                <td style="width:38%;text-align: center">
                    <h2 style="color:<?php echo $report['color'] ?>;font-size:18px;font-weight: normal;"><?php echo $pdf_title ?></h2>
                </td>
                <td class="address_right" style="color:<?php echo $report['color'] ?>;text-align: right;">
                    <div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
                    <?php if (!empty($pdf_header_text)) {
                        echo apply_filters('the_content', $pdf_header_text);
                    } else { ?>
                        <div><?php echo $report['header_lab_name'] ?></div>
                        <div><?php echo $report['header_lab_address'] ?></div>
                        <?php if (!tr_get_option('ci_pdf_data', 'report_hilaphone')) { ?>
                            <div>Phone: <?php echo $report['header_lab_phone'] ?></div>
                        <?php } ?>
                        <div>CLIA ID: <?php echo $report['header_lab_clia'] ?></div>
                    <?php } ?>
                </td>
            </tr>
        </table>
        <table class="small_padding_table" style="border-top:4px solid <?php echo $report['color'] ?>;border-bottom:4px solid <?php echo $report['color'] ?>">
            <tr>
                <td style="width:32%">
                    <h5>CLINIC INFORMATION</h5>
                </td>
                <td style="width:42%">
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
                            <td style="width:23%">Name:</td>
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
                    <table class="noboder small_table">
                        <tr>
                            <td style="width:20%">Name:</td>
                            <td colspan="3"><?php echo tr_pdd_capitalized($report['patient_name']) ?></td>
                        </tr>
                        <tr>
                            <td>DOB:</td>
                            <td><?php echo tr_pdd_display_date($report['patient_dob']) ?></td>
                            <td style="width:60px">Gender:</td>
                            <td style="width:20px"><?php echo htmlspecialchars($report['pa_gender']) ?></td>
                        </tr>
                        <tr>
                            <td>Phone:</td>
                            <td colspan="3"><?php echo $report['pa_phone'] ?></td>
                        </tr>

                        <tr>
                            <td>Address:</td>
                            <td colspan="3" class="smalltext"><?php echo $patient_adress ?></td>
                        </tr>
                        <?php if ($show_location && !empty($report['pa_location'])) { ?>
                            <tr>
                                <td>Location:</td>
                                <td colspan="3"><?php echo htmlspecialchars($report['pa_location']) ?></td>
                            </tr>
                        <?php } else { ?>
                            <tr>
                                <td>ID:</td>
                                <td colspan="3"><?php echo htmlspecialchars($report['pa_code']) ?></td>
                            </tr>
                        <?php } ?>
                        <?php if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) { ?>
                            <tr>
                                <td>Passport:</td>
                                <td colspan="3"><?php echo htmlspecialchars($report['pa_passport']) ?></td>
                            </tr>
                        <?php } ?>

                    </table>
                </td>
                <td style="padding-left:5px;">
                    <table class="noboder">
                        <tr>
                            <td style="width:50%">Accession Number:</td>
                            <td><?php echo $report['assen'] ?></td>
                        </tr>
                        <?php if (!empty($report['oldreq'])) { ?>
                            <tr>
                                <td>Ref ID:</td>
                                <td><?php echo $report['oldreq'] ?></td>
                            </tr>
                        <?php } ?>
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
                        if (!empty($report['dagnostic_codes1']) && tr_get_option('ci_pdf_data', 'bloodpdf_show_diagnostic')) {
                        ?>
                            <tr>
                                <td>Diagnostic code:</td>
                                <td><?php echo $report['dagnostic_codes1'] ?></td>
                            </tr>
                        <?php
                        }
                        $pr_code = tr_get_option('ci_pdf_data', 'blood_proceduralcode');
                        if (!empty($pr_code)) {
                            //86328, 36416 for Vir
                        ?>
                            <tr>
                                <td>Procedural codes:</td>
                                <td><?php echo $pr_code ?></td>
                            </tr>
                        <?php
                        }
                        if (tr_get_option('ci_pdf_data', 'show_result_mrn')) {
                        ?>
                            <tr>
                                <td>MRN#:</td>
                                <td><?php echo $report['pa_mrn'] ?></td>
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
    ob_start();
    ?>

    <div id="footer">
        <?php echo $footer_text ?>
    </div>

    <?php
    $footer_html = ob_get_clean();

    if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
        echo stripslashes($footer_html);
    }
    ?>


    <?php
    if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
        include('rejected_default.php');
    } else {
        //page 1
        $low_m_h = array('L' => 'Low', 'M' => 'Medium', 'H' => 'High');
        $wellness_score = trpdd_get_batch_meta($report['mid'], 'blood', 'wellness_score');
        $heart_disease_risk = trpdd_get_batch_meta($report['mid'], 'blood', 'heart_disease_risk');
        $heart_disease_risk_color = 'yellow';
        if ($heart_disease_risk == 'M') $heart_disease_risk_color = 'green';
        else if ($heart_disease_risk == 'H') $heart_disease_risk_color = 'orange';
        $heart_disease_risk = isset($low_m_h[$heart_disease_risk]) ? $low_m_h[$heart_disease_risk] : '';

        $type2_diabetes_risk = trpdd_get_batch_meta($report['mid'], 'blood', 'type2_diabetes_risk');
        $type2_diabetes_risk_color = 'yellow';
        if ($heart_disease_risk == 'M') $type2_diabetes_risk_color = 'green';
        else if ($heart_disease_risk == 'H') $type2_diabetes_risk_color = 'orange';
        $type2_diabetes_risk = isset($low_m_h[$type2_diabetes_risk]) ? $low_m_h[$type2_diabetes_risk] : '';
    ?>
        <h2 class="center">Health and Wellness Review</h2>
        <table class="table1">
            <tr class="yellow">
                <th class="hco1">Overall Health Score:</th>
                <th class="hco2"><?php echo $wellness_score ?></th>
                <td>Score is a subjective assessment from a senior drug specialist base on the entirety of the information provided including laboratory results and vitals.</td>
            </tr>
            <tr class="<?php echo $heart_disease_risk_color ?>">
                <th class="hco1">Heart Disease Risk:</th>
                <th class="hco2"><?php echo $heart_disease_risk ?></th>
                <td>ACC and AHA 10-year risk calculator.<br> https://static.heart.org/riskcalc</td>
            </tr>
            <tr class="<?php echo $type2_diabetes_risk_color ?>">
                <th class="hco1">Type 2 Diabetes Risk:</th>
                <th class="hco2"><?php echo $type2_diabetes_risk ?></th>
                <td>ADA risk calculator for developing T2D. https://diabetes.org/diabetes-risk-test</td>
            </tr>

        </table>

        <table class="table1">
            <tr class="yellow">
                <th class="hco11">Medication Review</th>
                <td><?php echo trpdd_get_batch_meta($report['mid'], 'blood', 'medication_review') ?></td>
            </tr>
            <tr class="yellow">
                <th class="hco11">Symptom Review</th>
                <td><?php echo trpdd_get_batch_meta($report['mid'], 'blood', 'symptom_review') ?></td>
            </tr>
            <tr class="green">
                <th class="hco11">Family History Review</th>
                <td><?php echo trpdd_get_batch_meta($report['mid'], 'blood', 'family_history_review') ?></td>
            </tr>
            <tr class="orange">
                <th class="hco11">Lifestyle Review</th>
                <td><?php echo trpdd_get_batch_meta($report['mid'], 'blood', 'lifestyle_review') ?></td>
            </tr>
            <tr class="green">
                <th class="hco11">Vitals & BMI Review</th>
                <td><?php echo trpdd_get_batch_meta($report['mid'], 'blood', 'vital_bmi_review') ?></td>
            </tr>
        </table>
        <?php

        ob_start();
        foreach ($drug_groups as $groupid => $groupdata) {
            foreach ($groupdata['tests'] as $arr) {


                if (!in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM', 'PCL', 'PCH', 'PVL', 'PVH'))) {
                    continue;
                }

                $arr['flag'] = str_replace('Critical-', '', $arr['flag']);
                $bgcolor = 'yellow';
                if ($arr['pos-neg'] == 'POH' || $arr['pos-neg'] == 'PCH') {
                    $bgcolor = 'red';
                } else if ($arr['pos-neg'] == 'POL') {
                    $bgcolor = 'orange';
                }
        ?>
                <tr class="<?php echo $bgcolor ?>">
                    <th class="sumcol1">Out of Range Lab</th>
                    <th class="sumcol2"><?php echo $arr['te_name'] ?></th>
                    <th class="sumcol3 center"><?php echo $arr['flag'] ?></th>
                    <td><?php echo $arr['comments'] ?></td>
                </tr>
            <?php
                $has_test = true;
            }
        }
        $childsum = ob_get_clean();

        if ($has_test) {
            echo '<table class="table1">';
            echo $childsum;
            echo '</table>';
        }


        echo '<div class="break"></div>';


        $report['bmi'] = trpdd_get_bmi($report['pa_height'], $report['pa_weight']);
        $report['age'] = trdd_dob2ages($report['pa_dob'], $report['created']);
        $marital_statuses = array(
            's' => 'Single',
            'm' => 'Married',
            'd' => 'Decline to respond',
            'o' => 'Other'
        );
        $Ethnicitis = array(
            ''  => 'Select',
            'H' => 'Hispanic or Latino',
            'N' => 'Not Hispanic or Latino',
            'U' => 'Unknown'
        );
        $medications  = array();
        $otc_medications  = array();
        $med1 = trpdd_get_batch_meta($report['mid'], 'blood', 'prescriptionmedications');
        foreach ($med1[0] as $i1 => $med) {
            $medications[] = $med . ' ' . $med1[1][$i1] . ' ' . $med1[2][$i1];
        }
        $otcmed1 = trpdd_get_batch_meta($report['mid'], 'blood', 'otcmedications');
        foreach ($otcmed1[0] as $i1 => $med) {
            $otc_medications[] = $med . ' ' . $otcmed1[1][$i1] . ' ' . $otcmed1[2][$i1];
        }
        $medications = implode('<br>', $medications);
        $otc_medications = implode('<br>', $otc_medications);

        $Tobacco = trpdd_get_batch_meta($report['mid'], 'blood', 'tobacco');
        if (strtolower($Tobacco) == 'yes') {
            $Tobacco = 'Yes';
            $tobacco_often = trpdd_get_batch_meta($report['mid'], 'blood', 'tobacco_often');
            if (!empty($tobacco_often)) {
                $Tobacco = $tobacco_often;
            }
            $Tobacco .= ' per ';
            $Tobacco .= trpdd_get_batch_meta($report['mid'], 'blood', 'tobacco_unit');
        }
        $Alcohol = trpdd_get_batch_meta($report['mid'], 'blood', 'alcohol');
        if (strtolower($Alcohol) == 'yes') {
            $Alcohol = 'Yes';
            $Alcohol_often = trpdd_get_batch_meta($report['mid'], 'blood', 'alcohol_often');
            if (!empty($Alcohol_often)) {
                $Alcohol = $Alcohol_often;
            }
            $Alcohol .= ' per ';
            $Alcohol .= trpdd_get_batch_meta($report['mid'], 'blood', 'alcohol_unit');
        }

        $doyouexercise = trpdd_get_batch_meta($report['mid'], 'blood', 'exercise');
        if (strtolower($doyouexercise) == 'yes') {
            $doyouexercise = 'Yes';
            $doyouexercise_often = trpdd_get_batch_meta($report['mid'], 'blood', 'howmanytimesperweek');
            if (!empty($doyouexercise_often)) {
                $doyouexercise = $doyouexercise_often . ' per week';
            }
            $howmanyminpersession = trpdd_get_batch_meta($report['mid'], 'blood', 'howmanyminpersession');
            if (!empty($howmanyminpersession)) {
                $doyouexercise .= '<br>' . $howmanyminpersession;
            }
            $whattype = trpdd_get_batch_meta($report['mid'], 'blood', 'whattype');
            if (!empty($whattype)) {
                $doyouexercise .= '<br>' . $whattype;
            }
        }

        $detail1 = array(
            'Name' => $report['patient_name'],
            'Date of Birth' => tr_pdd_display_date($report['pa_dob']),
            'Age' => $report['age'],
            'Gender at Birth' => $report['pa_gender'],
            'Height' => $report['pa_height'],
            'Weight' => $report['pa_weight'],
            'Calculated BMI' => $report['bmi'],
            'Marital Status' => $marital_statuses[$report['pa_marital_status']],
            'Ethnicity' => $Ethnicitis[$report['pa_ethnicity']],
            'Current Medications' => $medications,
            'Current OTC Medications' => $otc_medications,
            'Tobacco Use' => $Tobacco,
            'Alcohol Use' => $Alcohol,
            'Exercise' => $doyouexercise,
        );

        $treatments         = trpdd_get_batch_meta($report['mid'], 'blood', 'treatments');
        $haveyouhadanyofthefollowingsur = trpdd_get_batch_meta($report['mid'], 'blood', 'haveyouhadanyofthefollowingsur');
        $hasanyoneinyourimmediatefamily = trpdd_get_batch_meta($report['mid'], 'blood', 'hasanyoneinyourimmediatefamily');
        $symptoms           = trpdd_get_batch_meta($report['mid'], 'blood', 'symptoms');

        if (!is_array($treatments)) $treatments = array();
        if (!is_array($haveyouhadanyofthefollowingsur)) $haveyouhadanyofthefollowingsur = array();
        if (!is_array($hasanyoneinyourimmediatefamily)) $hasanyoneinyourimmediatefamily = array();
        if (!is_array($symptoms)) $symptoms = array();


        $detail2 = array(
            'Current Diagnosis or Treatments' => implode('<br>', $treatments),
            'Surgical History' => implode('<br>', $haveyouhadanyofthefollowingsur),
            'Immediate Family Health History' => implode('<br>', $hasanyoneinyourimmediatefamily),
            'Symptom Review' => implode('<br>', $symptoms),
        );

        echo '<table class="detailtbl">';
        echo '<tr>';
        echo '<td class="col2">';
        echo '<table>';
        foreach ($detail1 as $k => $txt) {
            echo '<tr>';
            echo '<th>' . $k . '</th>';
            echo '<td>' . $txt . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</td>';
        echo '<td class="col2">';
        echo '<table>';
        foreach ($detail2 as $k => $txt) {
            echo '<tr>';
            echo '<th>' . $k . '</th>';
            echo '<td>' . $txt . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';


        //chart
        $Pulse          = trpdd_get_batch_meta($report['mid'], 'blood', 'Pulse');

        $Pressure1      = trpdd_get_batch_meta($report['mid'], 'blood', 'Pressure1');
        $Pressure1_2    = trpdd_get_batch_meta($report['mid'], 'blood', 'Pressure1_2');
        $Arm            = trpdd_get_batch_meta($report['mid'], 'blood', 'Arm');

        $Pressure2      = trpdd_get_batch_meta($report['mid'], 'blood', 'Pressure2');
        $Pressure2_2    = trpdd_get_batch_meta($report['mid'], 'blood', 'Pressure2_2');
        $Arm2           = trpdd_get_batch_meta($report['mid'], 'blood', 'Arm2');

        $leftright = array('left' => 'Left Arm Sitting', 'right' => 'Right Arm Sitting');


        $Pressureavg = ($Pressure1 + $Pressure2) / 2;
        $Pressureavg2 = ($Pressure1_2 + $Pressure2_2) / 2;


        echo '<table class="mchartbl">';
        echo '<tr>';
        echo '<td class="col3">';
        echo '<h4 class="center">Blood Pressure</h4>';
        echo '<table class="chartbl1">';
        for ($i2 = 220; $i2 >= 60; $i2 -= 10) {
            echo '<tr>';
            echo '<td class="legend">' . $i2 . '</td>';
            if ($i2 == 220) {
                $P1color = 'yellow';
                $P2color = 'yellow';
                $p1height = 16 * 18 * ($Pressureavg - 60) / (220 - 60) + 12;
                $p2height = 16 * 18 * ($Pressureavg2 - 60) / (220 - 60) + 12;
                echo '<td rowspan="17" style="vertical-align:bottom;padding:0">';
                echo '<div class="bg_' . $P1color . '" style="height:' . $p1height . 'px"></div>';
                echo '</td>';
                echo '<td rowspan="17" style="vertical-align:bottom;padding:0">';
                echo '<div class="bg_' . $P2color . '" style="height:' . $p2height . 'px"></div>';
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '<tr><td></td><td class="center">Systolic</td><td class="center">Diastolic</td></tr>';
        echo '<tr><td></td><td colspan=2 class="center">' . $Pressureavg . '/ ' . $Pressureavg2 . '</td></tr>';
        echo '</table>';
        echo '</td>';
        echo '<td class="col3">';
        echo '<h4 class="center">Heart Rate</h4>';

        echo '<table class="chartbl1">';
        for ($i2 = 120; $i2 >= 40; $i2 -= 5) {
            echo '<tr>';
            echo '<td class="legend">' . $i2 . '</td>';
            if ($i2 == 120) {
                $Bcolor = 'green';
                $p1height = 16 * 18 * ($Pulse - 40) / (120 - 40) + 12;
                echo '<td rowspan="17" style="vertical-align:bottom;padding:0">';
                echo '<div class="bg_' . $Bcolor . '" style="height:' . $p1height . 'px"></div>';
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '<tr><td></td><td class="center">' . $Pulse . ' BPM</td></tr>';
        echo '</table>';

        echo '</td>';
        echo '<td class="col3">';
        echo '<h4 class="center">BMI</h4>';
        echo '<table class="chartbl1">';
        for ($i2 = 34; $i2 >= 18; $i2--) {
            echo '<tr>';
            echo '<td class="legend">' . $i2 . '</td>';
            if ($i2 == 34) {
                $Bcolor = 'yellow';
                $report['bmi'] = floatval($report['bmi']);
                if ($report['bmi'] >= 40) {
                    $Bcolor = 'dark_red';
                } else if ($report['bmi'] >= 35) {
                    $Bcolor = 'red';
                } else if ($report['bmi'] >= 30) {
                    $Bcolor = 'dark_orange';
                } else if ($report['bmi'] >= 25) {
                    $Bcolor = 'orange';
                } else if ($report['bmi'] >= 18.5) {
                    $Bcolor = 'green';
                }

                $p1height = 16 * 18 * ($report['bmi'] - 18) / (34 - 18) + 12;
                echo '<td rowspan="17" style="vertical-align:bottom;padding:0">';
                echo '<div class="bg_' . $Bcolor . '" style="height:' . $p1height . 'px"></div>';
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '<tr><td></td><td class="center">Score: ' . $report['bmi'] . '</td></tr>';
        echo '</table>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        /*
        Less than 18.5: Underweight     - Yellow
Between 18.5 and 24.9: Healthy BMI  - Green
From 25 to 29.9: Overweight  - orange
Between 30 and 34.9: Class I obesity - dark orange
From 35 to 39.9: Class II obesity   - red
More than 40: Class III obesity   - dark red
*/


        echo '<div class="break"></div>';
        if ($has_manual_antibiotic) {
            include('manual_antibiotic_blood.php');
        }

        //main report

        foreach ($drug_groups as $groupid => $groupdata) {
            ob_start();
            $has_test       = false;
            $has_pos        = false;
            $has_group_flag = false;
            $units_header_text = 'Units';
            $column_count   = 4;

            if ($groupdata['hide_units']) {
                $column_count--;
            }
            if (isset($groupdata['other_options']['show_value_in_result']) && $groupdata['other_options']['show_value_in_result']) {
                $blood_pdf_show_conc = true;
            }

            if ($blood_pdf_show_conc) {
                $column_count++;
            }


            foreach ($groupdata['tests'] as $arr) {
                $class = '';
                $range = 'Negative';

                if (stripos($arr['te_name'], 'control') > 0) {
                    $range = 'Positive';
                    if ($arr['pos-neg'] == 'NEG') {
                        $class .= ' has_covid ';
                    }
                } else if ($arr['pos-neg'] == 'POS') {
                    $has_pos = true;
                    if (stripos($arr['te_name'], 'CoV-2') !== false || stripos($arr['te_name'], 'COVID') !== false) {
                        $class .= ' has_covid ';
                    }
                }
                $value_or_units = $arr['te_units'];
                if (strlen($arr['te_units']) > 20 && stripos($arr['te_units'], 'sup') === false) {
                    $units_header_text = $arr['te_units'];
                    $value_or_units = $arr['conc'];
                }
                if (!empty($arr['range'])) {
                    $range = $arr['range'];
                }

                $class .= ' posneg_' . $arr['pos-neg'];
                $arr['conc_dis'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];
                if ($arr['critical'] == 'L') {
                    $arr['pos-neg'] = 'PCL';
                } else if ($arr['critical'] == 'H') {
                    $arr['pos-neg'] = 'PCH';
                }
                if (empty($arr['pos-neg']) && $arr['conc'] === '') {
                    continue;
                }

                if (empty($arr['comments'])) {
                    $arr['comments'] = $arr['te_desc'];
                }

                if (!empty($arr['comments'])) {
                    $class .= ' comment_at_bottom ';
                }

                if (isset($pos_neg_vls[$arr['pos-neg']])) {
                    $has_group_flag = true;
                }
            ?>
                <tr class="<?php echo $class ?>">
                    <td class="drug bold"><?php echo $arr['te_name'] ?></td>
                    <?php if ($blood_pdf_show_conc) {
                        echo '<td class="conc center">' . htmlentities($arr['conc_dis']) . '</td>';
                    } ?>
                    <td class="pos_neg fontDejavu center"><?php echo $arr['flag'] ?></td>
                    <?php if (!$groupdata['hide_units']) { ?>
                        <td class="center fontDejavu"><?php echo htmlentities(trpdd_show_symbol($value_or_units)) ?></td>
                    <?php } ?>
                    <td class="center fontDejavu"><?php echo htmlentities(trpdd_show_symbol($range)) ?></td>
                </tr>
                <?php

                if (!empty($arr['comments'])) {
                ?>
                    <tr>
                        <td class="fontDejavu" colspan="<?php echo $column_count ?>" style="border-top:0"><?php echo trpdd_filter_comments_in_pdf($arr['comments']) ?></td>
                    </tr>
                <?php
                }

                $has_test = true;
            }


            $content = ob_get_clean();

            if ($has_test) {
                $has_report = true;

                ?>
                <table class="small_table tdborder marginbottom comment_next_row">
                    <thead style="page-break-inside:avoid !important;">
                        <tr class="headernoborder">
                            <th colspan="<?php echo $column_count ?>" class="group_name center" style="border:none;font-size:14px">
                                <?php echo $groupdata['name'] ?>
                            </th>
                        </tr>
                        <tr class="title_tbl">
                            <th style="width:25%">Test Name</th>
                            <th style="width:9%">Result</th>
                            <?php if ($blood_pdf_show_conc) {
                                echo '<th style="width:12%">' . ($has_group_flag ? 'Flag' : '') . '</th>';
                            } ?>
                            <?php if (!$groupdata['hide_units']) { ?>
                                <th style="width:9%"><?php echo $units_header_text ?></th>
                            <?php } ?>
                            <th style="width:10%">Reference Range</th>
                        </tr>
                    </thead>
                    <?php echo $content; ?>
                </table>
    <?php
                if (!empty($groupdata['comments'])) {
                    if (strpos($groupdata['comments'], 'atbottom') > 0) {
                        $bottom_comments[] = $groupdata['comments'];
                    } else {
                        echo '<div>' . trpdd_filter_comments_in_pdf($groupdata['comments']) . '</div>';
                    }
                }
            }
        }
    } //end if rejected

    /*
    $clinical_pharmacy_guidance = trpdd_get_batch_meta($report['mid'],'blood','clinical_pharmacy_guidance');

    if(strlen($clinical_pharmacy_guidance)>10)
    {
        echo '<div class="clinical_pharmacy_guidance">';
        echo '<br><h4 class="center" style="font-size:14px">Pharmacist Recommendations</h4>';
        echo $clinical_pharmacy_guidance;
        echo '</div>';
    }
    */



    if (!empty($report['notes_report'])) {
        echo '<div style="padding-top:15px" class="notes_report">';
        //echo 'Notes: ';
        echo trpdd_filter_comments_in_pdf($report['notes_report']);
        echo '</div>';
    }

    if (!empty($pdf_notes)) {
        echo '<div class="notes_report" >';
        echo trpdd_filter_comments_in_pdf($pdf_notes);
        echo '</div>';
    }

    if (count($bottom_comments) > 0) {
        $showed_cmm = array();
        foreach ($bottom_comments as $cm) {
            $idcm = md5($cm);
            if (in_array($idcm, $showed_cmm)) {
                continue;
            }
            $showed_cmm[] = $idcm;
            echo '<div>' . trpdd_filter_comments_in_pdf($cm) . '</div>';
        }
    }

    if (isset($_REQUEST['html'])) {
        echo stripslashes($footer_html);
    }
    ?>

    <?php
    if (!isset($add_page)) {
        include('_pdf_pagination.php');
    }
    ?>

</body>

</html>