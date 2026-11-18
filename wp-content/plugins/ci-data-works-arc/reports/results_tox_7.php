<?php
/*
    Name: One page 2 (one Detected area, Urine Validity/Screening in page 2)
*/
global $wpdb, $ci_cutoffs, $report;
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;
$sum = isset($_REQUEST['sum']) ? true : $sum;
$can_show_lcms = isset($can_show_lcms) ? $can_show_lcms : true;
$sum = true; //now live sum
$btype = 'tox';
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

$nocache = true;

$drug_pairs     = tr_pdd_get_drug_pairs();
$drug_groups    = tr_pdd_get_drug_groups($btype);
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

$all_total = count($all_results_q);
foreach ($all_results_q as $arr) {
    $arr['drug'] = strtoupper($arr['drug']);
    $all_res[$arr['drug']] = $arr;

    if (!empty($arr['te_name'])) {
        $arr['drug'] = strtoupper($arr['te_name']);
        $all_res[$arr['drug']] = $arr;
    }
}

if (stripos($report['panel_tests_requested'], 'Ethanol*') !== false) {

    if (!isset($all_res['Ethanol*']) && !isset($all_res['ETHANOL*'])) {
        $all_res['zEthanol*'] = array('drug' => 'Ethanol*', 'id' => 'new', 'pos-neg' => 'NEG');
    }
}
$methamphetamine = $wpdb->get_row("select * from ci_result_metha WHERE report_id='" . $id . "' ", ARRAY_A);


$ci_cutoffs  = tr_pdd_get_cutoffs($btype, $report['created_date']);
$exclude_tests = tr_pdd_get_test_outdate($report['doc']);

$in_panel_tests_requested = tr_pdd_parse_panel_tests_requested($report['panel_tests_requested'], $drug_pairs, $exclude_tests);
$medications = tr_pdd_parse_medications($report['medications'], $drug_pairs);
$report['medications'] = str_replace(',', ', ', $report['medications']);
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
    echo 'style3<br><br>';
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

$margin_top = 4.5;

$cline = max(ceil(strlen($report['medications']) / 110), 1);
$margin_top += $cline * 0.3;
/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */



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
    $footer_height = $lines * 0.38;
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
<html class="style7">

<head>
    <meta charset="UTF-8" />
    <meta id="final" content="<?php echo $report['final_results'] ?>" />
    <title>Result</title>
    <style>
        @page {
            margin: 0.6cm 0.5cm <?php echo $footer_height ?>cm 0.5cm;
        }

        body {
            margin-top: <?php echo $margin_top ?>cm;
            margin-bottom: <?php echo $margin_bottom ?>cm;
        }

        .break {
            page-break-after: always;
            border: 0;
        }

        * {
            font-family: 'Oswald', Arial, Helvetica, sans-serif;
            font-size: 8pt;
        }

        p {
            margin-top: 0;
            margin-bottom: 7px
        }

        a.NoteRef {
            text-decoration: none;
        }

        hr {
            height: 0;
            padding: 0;
            margin: 0.6em 0;
            border: 0;
            border-top: 0.1pt solid #aaa;
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

        table.border_top_bottom {
            border-top: 1px solid #555;
            border-bottom: 1px solid #555
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

        table.nomarginbottom {
            margin-bottom: 0 !important;
        }

        .marginbottom {
            margin-bottom: 8px;
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

        table .font_red td.comment {
            color: #000;
        }

        table .font_bold td {
            font-weight: bold
        }

        table .font_blue td.comment {
            color: #000
        }

        table .font_bold td.comment {
            font-weight: normal
        }

        table tr.titleleft th {
            text-align: left;
        }

        h4,
        .h4 {
            font-size: 12px;
            margin: 0;
            padding: 0;
            line-height: 12px;
            font-weight: bold
        }

        .small_table th,
        .small_table td,
        .small_div {
            font-size: 7pt;
            padding: 1px 2px;
            color: #000;
        }

        .small_div {
            border: 1px solid #000;
            border-top: 0;
            margin-bottom: 10px
        }

        .small_div * {
            font-size: 7pt;
        }

        .small_padding_table th,
        .small_padding_table td {
            padding: 1px 2px;
            color: #000;
        }

        table td.comments {
            font-size: 9px;
            color: #000;
        }

        .logo_title h1 {
            text-align: center;
            font-size: 18px;
            color: #444;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .logo_title h2 {
            text-align: center;
            font-size: 12px;
            color: #555;
            text-transform: uppercase;
            margin: 0;
        }

        table tr td.dontbreak,
        table tr th.dontbreak {
            page-break-inside: avoid;
            border: 0;
            padding: 1px 0;
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

        .bold {
            font-weight: bold
        }

        .transform {
            text-transform: uppercase
        }

        .line {
            text-decoration: underline;
        }

        .baseline {
            margin-bottom: 2px;
        }

        .blocknr b,
        .blocknr {
            line-height: 11px;
        }

        <?php if (!isset($_REQUEST['html'])) : ?>#header,
        #footer {
            position: fixed;
            left: 0;
            right: 0;
            color: #333;
            font-size: 0.9em;
        }

        <?php endif; ?>#header {
            top: 0;
        }

        #footer {
            bottom: 15px;
            padding-top: 5px;
            border-top: 0.1pt solid #aaa;
        }

        .tblprevtest table td,
        .tblprevtest table td div {
            font-size: 7px;
            line-height: 8px;
            padding: 1px;
        }

        #footer,
        #footer * {
            font-size: 10px;
            line-height: 10px;
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

        span.normal {
            font-weight: normal
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
                <td style="width:26%;height:60px;" valign="top">
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
                        $max_width = 200;
                        $max_height = ($size[0] > $size[1]) ? 70 : 80;

                    ?>
                        <img border="0" style="max-width: <?php echo $max_width ?>px; height: auto;max-height:<?php echo $max_height ?>px" src="<?php echo $logo_dir . $logo_main ?>" />
                    <?php
                    }


                    ?>
                </td>
                <td style="width:42%" class="logo_title">
                    <h1><?php echo $report['lab_dba_init'] ?> RESULTS REPORT</h1>

                </td>
                <td style="vertical-align: top;text-align: right;line-height:15px">
                    <div class="right_pre"><?php echo $text_PRELIMINARY ?></div>
                    <div style="clear:both"><?php echo $report['header_lab_address'] ?></div>
                    <?php if (!empty($report['header_lab_phone'])) : ?>
                        <div>Phone: <?php echo $report['header_lab_phone'] ?></div>
                    <?php endif; ?>
                    <?php if (!empty($report['header_lab_fax'])) : ?>
                        <div>Fax: <?php echo $report['header_lab_fax'] ?></div>
                    <?php endif; ?>
                    <?php if (!empty($report['header_lab_clia'])) : ?>
                        <div>CLIA # <?php echo $report['header_lab_clia'] ?></div>
                    <?php endif; ?>

                </td>
            </tr>
        </table>
        <table class="border_top_bottom small_padding_table">
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
                        echo tr_pdd_display_date($report['patient_dob'], 'm/d/Y');
                    }
                    ?>
                </td>
                <th>Clinic</th>
                <td><?php echo $report['requesting_practice'] ?></td>
            </tr>
            <tr>
                <th>Results Date</th>
                <td>
                    <?php
                    if (empty($report['approve_date']) || $report['approve_date'] == '0000-00-00' || $report['approve_date'] == '0000-00-00 00:00:00' || $report['approve_date'] == '00/00/0000') {
                        echo 'Not Provided';
                    } else {
                        echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i');
                    }
                    ?>
                </td>
                <th>Sample Type</th>
                <td><?php echo htmlspecialchars($report['sample_type']) ?></td>
            </tr>
            <tr>
                <th>Collection Date</th>
                <td><?php echo tr_pdd_display_date($report['collection_date'], 'm/d/Y H:i') ?></td>
                <th>Received in Lab</th>
                <td><?php echo tr_pdd_display_date($report['received_in_lab'], 'm/d/Y H:i') ?></td>
            </tr>
            <tr>
                <th>Prescribed Medications</th>
                <td colspan="3" <?php if (!empty($report['medications'])) echo 'style="color:blue"' ?>><?php echo (!empty($report['medications'])) ? htmlspecialchars(str_replace(',', ', ', $report['medications'])) : "No reported medications" ?></td>
            </tr>
        </table>
    </div>
    <?php


    if (!isset($_REQUEST['html']) && !isset($use_footer_file)) {
        echo $footer_html;
    }
    ?>


    <?php
    if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
        include('rejected_default.php');
    } else if (count($in_panel_tests_requested) == 0 && !$report['only_screening']) {
        if (!empty($report['result_pdf'])) {
            $has_tox_result = false;
            $has_report = false;
            return;
        }
    ?>
        <h1 style="color: red;">No Custom Profile/No Tests Requested Otherwise</h1>
        <?php
    } else {
        if (isset($_REQUEST['d2'])) {
            $no_collection_date = true;
        }
        if ($no_collection_date) {
        ?>
            <h1 style="color:red">*** Insufficient Information: No Date of Collection Indicated.</h1>
            <?php
        }

        $showed_lcms = false;

        if ($sum && !$report['only_screening'] && $can_show_lcms) {


            $summary_all = array(
                'detected' => array('title' => 'Detected', 'drugs' => array()),
                'Illicits' => array('title' => 'Illicits - Detected', 'drugs' => array()),
            );
            $colwidths = array('32%', '12%', '12%', '12%', '32%');

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
                $all_res[$i] = $arr;

                if ($arr['pos-neg'] != 'POS') {
                    continue;
                }
                if (isset($_REQUEST['pos_results']) && $arr['pos-neg'] == 'NEG') {
                    continue;
                }

                $k = 'detected';
                if ($arr['is_red'] && isset($summary_all['Illicits'])) {
                    $k = $arr['pos-neg'] == 'POS' ? 'Illicits' : 'Illicits_normal';
                }

                if (!isset($summary_all[$k])) {
                    continue;
                }

                $summary_all[$k]['drugs'][] = $arr;
                unset($all_res[$i]);
            }

            foreach ($summary_all as $k => $grouparr) {
                if (count($grouparr['drugs']) == 0) continue;
                $showed_lcms = true;
            ?>
                <table class="small_table border marginbottom">
                    <tr class="header_tbl">
                        <th colspan="5"><?php echo $grouparr['title'] ?></th>
                    </tr>
                    <tr class="title_tbl">
                        <th style="width:<?php echo $colwidths[0] ?>">Test</th>
                        <th style="width:<?php echo $colwidths[1] ?>" class="center">Conc(ng/mL)</th>
                        <th style="width:<?php echo $colwidths[2] ?>" class="center">Pos/Neg</th>
                        <th style="width:<?php echo $colwidths[3] ?>" class="center">Cutoff(ng/mL)</th>
                        <th style="width:<?php echo $colwidths[4] ?>" class="center">Detection Window</th>
                    </tr>
                    <?php
                    foreach ($grouparr['drugs'] as $arr) {
                        $has_tox_result = true;
                    ?>
                        <tr class="<?php if ($arr['red_color']) echo 'font_red' ?> <?php if ($arr['is_blue']) echo ' font_blue' ?>">
                            <td style="width:<?php echo $colwidths[0] ?>" class="bold"><?php echo htmlspecialchars($arr['drug']) ?></td>
                            <td style="width:<?php echo $colwidths[1] ?>" class="center"><?php echo htmlspecialchars($arr['conc']) ?></td>
                            <td style="width:<?php echo $colwidths[2] ?>" class="center" <?php if ($arr['is_blue'] && $arr['pos-neg'] == 'NEG') echo 'style="font-weight:600"' ?>><?php echo htmlspecialchars($arr['pos-neg']) ?></td>
                            <td style="width:<?php echo $colwidths[3] ?>" class="center"><?php echo htmlspecialchars($arr['cutoff']) ?></td>
                            <td style="width:<?php echo $colwidths[4] ?>" class="center"><?php echo $arr['detection'] ?></td>
                        </tr>
                        <?php
                        if (!empty($arr['comments'])) {
                        ?>
                            <tr>
                                <td colspan="5" class="comments"><?php echo $arr['comments'] ?></td>
                            </tr>
                    <?php
                        }
                    }
                    ?>
                </table>
            <?php
            }
        }

        if (!$report['cli_no_history'] && !$report['only_screening'] && $can_show_lcms) {


            $sql = "select re.id,re.created_date, re.instrument,
                    ba.panel_tests_requested,
                    ba.current_medication as medications, 
                    ba.type_of_specimen as matrix,
                    ba.doc
            from ci_reports re  
            JOIN ci_batches ba ON ba.assen = re.patient_id
            where ba.patient_id = '{$report['pa_ID']}' 
            and ba.doc <= '{$report['doc']}' 
            and re.id != {$report['id']}
            and ba.cli_ID = '{$report['cli_ID']}'
            Order by ba.doc desc limit 6";
            $rs = $wpdb->get_results($sql, ARRAY_A);
            if (isset($_GET['debug'])) {
                var_dump($rs);
                var_dump($sql);
            }
            if ($rs) {
                $prev = array();
                $prev_title = array();
                $prev_subtitle = array();

                $icount = 6 - count($rs);
                $old_report = $report;
                foreach ($rs as $i => $row) {
                    $report = $row;
                    $tests_requested2 = tr_pdd_parse_panel_tests_requested($row['panel_tests_requested'], $drug_pairs);
                    $medications2 = tr_pdd_parse_medications($row['medications'], $drug_pairs);
                    $exclude_tests2 = tr_pdd_get_test_outdate($row['doc']);
                    $all_results_q = $wpdb->get_results("SELECT * FROM ci_results WHERE report_id='" . $row['id'] . "' ", ARRAY_A);

                    $pos_arr = array();
                    $neg_arr = array();
                    $txt = '';

                    foreach ($all_results_q as $arr) {
                        $arr = tr_pdd_get_drug_class_comments($arr, $medications2, $row['matrix'], $tests_requested2, $ci_cutoffs);

                        if ($arr['exclude'] || in_array($arr['drug'], $exclude_tests2)) {
                            continue;
                        }
                        if ($arr['is_blue'] || $arr['is_red'] || $arr['pos-neg'] == 'POS') {
                            if ($arr['pos-neg'] == 'POS' && count($pos_arr) < 7) {
                                $pos_arr[] = $arr;
                            } else if ($arr['pos-neg'] == 'NEG') {
                                $neg_arr[] = $arr;
                            }
                        }
                    }
                    if (count($pos_arr) > 0) {
                        $txt .= '<div class="bold">Positive</div>';
                        foreach ($pos_arr as $arr) {
                            $txt .= '<div>' . $arr['drug'] . ' (' . htmlentities($arr['conc']) . ' ng/mL)</div>';
                        }
                    }

                    $typeou = !empty($report['instrument']) ? ' (' . strtoupper(substr($row['matrix'], 0, 1)) . ')' : '';
                    $prev_title[$icount] = 'Collected ' . tr_pdd_display_date($row['doc'], 'm/d/Y');
                    $prev_subtitle[$icount] = 'Resulted ' . tr_pdd_display_date($row['created_date'], 'm/d/Y') . $typeou;
                    $prev[$icount] = $txt;
                    $has_tox_result = true;

                    $icount++;
                }
                $report = $old_report;
            ?>
                <table class="small_table border marginbottom tblprevtest">
                    <tr class="header_tbl">
                        <th>Previous Results</th>
                    </tr>
                    <tr>
                        <td style="text-align: center;padding:3px">
                            <table class=" small_table">
                                <tr class="title_tbl">
                                    <th style="width:16.6%;border:1px solid #ddd;border-bottom:0"><?php echo $prev_title[5] ?></th>
                                    <td></td>
                                    <th style="width:16.6%;border:1px solid #ddd;border-bottom:0"><?php echo $prev_title[4] ?></th>
                                    <td></td>
                                    <th style="width:16.6%;border:1px solid #ddd;border-bottom:0"><?php echo $prev_title[3] ?></th>
                                    <td></td>
                                    <th style="width:16.6%;border:1px solid #ddd;border-bottom:0"><?php echo $prev_title[2] ?></th>
                                    <td></td>
                                    <th style="width:16.6%;border:1px solid #ddd;border-bottom:0"><?php echo $prev_title[1] ?></th>
                                    <td></td>
                                    <th style="width:16.6%;border:1px solid #ddd;border-bottom:0"><?php echo $prev_title[0] ?></th>
                                </tr>
                                <tr>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev_subtitle[5] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev_subtitle[4] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev_subtitle[3] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev_subtitle[2] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev_subtitle[1] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev_subtitle[0] ?></td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev[5] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev[4] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev[3] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev[2] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev[1] ?></td>
                                    <td></td>
                                    <td style="text-align: center;border:1px solid #ddd;border-top:0;padding:0"><?php echo $prev[0] ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            <?php
            }
        }


        if (!isset($_REQUEST['pos_results']) && !$report['only_screening'] && $can_show_lcms) {
            $showed_lcms = true;
            ?>
            <table class="small_table border nomarginbottom">
                <tr class="header_tbl">
                    <th>Summary of Negative Tests Performed</th>
                </tr>
            </table>
            <div class="small_div">
                <?php
                foreach ($drug_groups as $groupid => $groupdata) {

                    if (!$groupdata['show']) continue;

                    $drugs = $groupdata['drugs'];
                    $childs = array();
                    foreach ($drugs as $d) {
                        if (isset($all_res[$d])) {
                            $arr = $all_res[$d];
                            unset($all_res[$d]);
                            $childs[] = '<span>' . $arr['drug'] . '</span> (' . htmlspecialchars($arr['conc']) . ' ng/mL)';
                        }
                    }
                    if (count($childs) > 0) {
                        $has_tox_result = true;
                        echo '<div class="baseline"><span class="bold transform">' . $groupdata['name'] . ': </span>' . implode(', ', $childs) . '</div>';
                    }
                }

                $childs = array();
                foreach ($all_res as $arr) {
                    $has_tox_result = true;
                    $childs[] = '<span class="bold">' . $arr['drug'] . '</span> (' . htmlspecialchars($arr['conc']) . ' ng/mL)';
                }
                if (count($childs) > 0) {
                    $has_tox_result = true;
                    echo '<div class="baseline"><span class="bold transform">Others: </span>' . implode(', ', $childs) . '</div>';
                }
                ?>
            </div>

    <?php
        }

        $urine_content = '';
        if ($report['matrix'] != "oral" && $report['lab_not_specimen'] != 1) {

            $header_bg_tb = true;
            $return_urine_content = true;
            $urine_content = include(TRCPDD_PATH . 'reports/results_urine.php');
        }

        $header_bg_tb = true;
        $return_screening_content = true;
        $screening_content = include(TRCPDD_PATH . 'reports/results_screening_new.php');

        if (!empty($urine_content) || !empty($screening_content)) {
            if ($showed_lcms) {
                echo '<div style="page-break-before:always"></div>';
            }
            echo $urine_content;
            echo $screening_content;
        }


        if (!$report['only_screening']) {
            include(TRCPDD_PATH . 'reports/methamphetamine_dl.php');
        }


        $notes = get_option('ci_notes_result');
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

        $time_doc = strtotime($report['collection_date']);
        $time_rec = strtotime($report['received_in_lab']);


        if (abs($time_doc - $time_rec) >= 7 * 86400) {
            echo '<div><p>';
            echo nl2br(stripslashes($notes[3]));
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
        echo $footer_html;
    }
    ?>

</body>

</html>