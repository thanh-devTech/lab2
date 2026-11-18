<?php
global $wpdb, $ci_cutoffs, $all_res;
global $pdf_header_text;

$id                     = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid                    = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : $bid;


$sum                    = true; //now live sum
$has_report             = false;
$has_style2             = false;
$upload_dir             = tr_wp_upload_dir();
$logo_dir               = $upload_dir['basedir'] . '/logos/';
$logo_url               = $upload_dir['baseurl'] . '/logos/';


$settings               = get_option('ci_result_settings_gene', array());
$notes                  = get_option('ci_notes_result_gene', array());

$style_num              = tr_get_option('ci_pdf_data', 'gene_pdf_style', 4);
$gene_pdf_show_conc    = tr_get_option('ci_pdf_data', 'gene_pdf_show_conc');
$pdf_title                 = tr_get_option('ci_pdf_data', 'gene_pdf_title');
$showdatetime           = tr_get_option('ci_pdf_data', 'results_dtime');

if (isset($settings['gene_result_off']) && $settings['gene_result_off']) {
    $has_report = false;
    return;
}
if (empty($pdf_title)) {
    $pdf_title = 'Test Lab Report';
}


if (empty($report['mid']) && $mid > 0) {
    $report['mid'] = $mid;
}
$report = tr_pdd_get_report_by_id($id, 'gene', $report['mid']);
if ($id == 0) {
    $id = $report['id'];
}

$main_color = $report['color'];

if (tr_get_option('ci_pdf_data', 'results_sp_adoctor') && !empty($report['doc_specialty'])) {
    $report['physician'] .= ' ' . $report['doc_specialty'];
}
$show_location             = tr_get_option('ci_pdf_data', 'req_patient_location') && !empty($report['pa_location']);
$panel_batch = '';



$patient_adress = trpdd_format_address($report['pa_address'], $report['pa_city'], $report['pa_state'], $report['pa_zip']);
$clinic_address = trpdd_format_address($report['cli_street'], $report['cli_city'], $report['cli_state'], $report['cli_zip']);


$in_panel_tests_requested = array();
$group_ids = array();
foreach (explode(',', $report['panel_tests_requested']) as $t) {
    if (stripos($t, 'group') === false) {
        if ($t > 0) {
            $in_panel_tests_requested[] = $t;
        }
    } else {
        $gid = str_ireplace('group_', '', $t);
        $group_ids[] = $gid;

        $group_childs = $wpdb->get_col("select tg_ID from ci_test_groups g 
			where g.tg_parent in (select tg_ID from ci_test_groups g2 where g2.tg_ID in ({$gid}) )");

        if (count($group_childs) > 0) {
            $group_ids = array_merge($group_ids, $group_childs);
        }
    }
}
$test_ids = implode(',', $in_panel_tests_requested);
if (empty($test_ids)) {
    $test_ids = '-1';
}

$where_group = '';
$group_ids_str = implode(',', $group_ids);
if (empty($group_ids_str)) {
    $group_ids_str = '0';
}
if (count($group_ids) > 0) {
    $where_group .= "  and tg_ID in ({$group_ids_str})";
} else {
    $where_group .= "  and 1=2";
}


$sql = "SELECT *
        FROM ci_test_panels tp
        left join ci_results_gene rs ON (tp.te_name = rs.drug or tp.te_abrev = rs.drug) and rs.report_id= {$id}
        WHERE  tp.te_type='gene' 
        and ( 
                (rs.id >0 
                    and (   
                            tp.te_ID in ({$test_ids}) 
                            OR tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'gene' 
                                                        and (tg_only_group = 1  or tg_notestcheckbox=1) {$where_group})
                         ) 
                )
                OR
                (
                    tp.te_calculation!='' 
                    and tp.te_calculation is not null
                    and  tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'gene' 
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
    $arr['drug'] = strtoupper($arr['te_name']);
    $all_res[$arr['te_ID']] = $arr;
}



$drug_groups = tr_pdd_get_drug_groups('gene', $group_ids, true);


$inew = 0;
foreach ($drug_groups as $groupid => $groupdata) {

    $te_IDs = is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();


    $groupdata['tests'] = array();

    foreach ($te_IDs as $te_ID) {
        $arr = isset($all_res[$te_ID]) ? $all_res[$te_ID] : array();

        if (!empty($arr)) {

            $arr['showed'] = true;
            if (empty($arr['id'])) {
                $arr['id'] = 'new' . $inew++;
            }

            $arr = tr_pdd_parse_gene_test($arr, $report['instrument'], $groupdata['id'], $report);

            if ($arr['pos-neg'] == 'REJ' || $arr['pos-neg'] == 'CAN') {
                $arr['conc'] = '';
            }
            $all_res[$te_ID] = $arr;


            if ($arr['te_hide_pdf']) {
                continue;
            }
            if (($arr['conc'] === '' && empty($arr['pos-neg'])) || $arr['pos-neg'] == 'CAN') {
                continue;
            }

            $groupdata['tests'][$te_ID] = $arr;
        }
    }

    $drug_groups[$groupid] = $groupdata;

    //get group style
    if ($groupdata['pdf_style'] > 0 && count($groupdata['tests']) > 0 && !$has_style2) {
        $style_num = $groupdata['pdf_style'];
        if ($style_num == 2) {
            $has_style2 = true;
        }
    }

    if (count($groupdata['tests']) > 0 && $groupdata['pdf_style'] > 1) {
        $pdf_title = $groupdata['name'];
    } else if (stripos($groupdata['name'], 'antibody') !== false) {
        $pdf_title = 'Covid-19 Antibody Test Lab Report';
    }

    if (!empty($groupdata['pdf_title'])) {
        $pdf_title = $groupdata['pdf_title'];
    }

    if ($groupdata['parent'] == 0) {
        $report['group_id'] = $groupdata['id'];
    }
}


$pos_neg = array(
    'INV' => 'Invalid',
    'NEG' => 'Negative',
    'POS' => 'Positive',
    'PEN' => 'Pending',
    'POL' => 'Low',
    'POM' => 'Medium',
    'POH' => 'High',
    'PCL' => 'Critical Low',
    'PCH' => 'Critical High',
    'CAN' => 'Cancel',
    'REJ' => 'Rejected',
    'INC' => 'Inconlusive',
);

$races = array(
    'american' => 'American Indian / Alaskan Native',
    'white' => 'White / Caucasian (Non-Hispanic)',
    'black' => 'Black / African American',
    'hispanic' => 'Hispanic / Latino',
    'other' => 'Other'
);

$text_PRELIMINARY = tr_pdd_get_result_status($report['final_results'], true);
$final_adding_font = '';
if (isset($result_settings['final_bold']) && $result_settings['final_bold']) {
    $final_adding_font .= 'font-weight:bold;text-transform: uppercase;';
}

$footer_height = 1.2;

$footer_text = trpdd_get_footer_text($report, 'gene');


$director_signature = '';
if (!empty($report['lab_director_signature'])) {
    $sign_dir = tr_get_signature_dir();
    $director_signature_url = $sign_dir['path'] . '/' . $report['lab_director_signature'];
    $director_signature = '<img src="' . $director_signature_url . '" style="height:30px">';
}
$footer_text = str_replace('[signature]', $director_signature, $footer_text);

if (preg_match("/height\s?:\s?([0-9]+)px/", $footer_text, $match_footer_height)) {

    $new_footer_height = ($match_footer_height[1] + 10) * 0.027;
    if ($new_footer_height > $footer_height) {
        $footer_height = $new_footer_height;
    }
}

if (strlen($footer_text) > 600 && $footer_height < 3.5) {
    $footer_height = 3.5;
} else if (strlen($footer_text) < 1) {
    $footer_height = 0.5;
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
    echo '$drug_groups:';
    var_export($drug_groups);
    echo '<br><br>';
}


if (isset($_GET['style']) && $_GET['style'] > 0) {
    $style_num = $_GET['style'];
}
if ($style_num > 1) {
    $new_template_file = TRCPDD_PATH . 'reports/results_gene_' . $style_num . '.php';
    if (file_exists($new_template_file)) {
        if (isset($_GET['debug']) || isset($_GET['html'])) {
            echo 'file: ' . $new_template_file . '<br>';
        }
        include($new_template_file);
        return;
    }
} else if (isset($_GET['html'])) {
    echo 'style:1';
}

/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */



$margin_bottom = 0;
if (defined('TRCDPDF_NEW') && TRCDPDF_NEW) {
    $margin_bottom = $footer_height;
    $footer_height = 0.3;
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
            margin-top: 5.4cm;
            margin-bottom: <?php echo $margin_bottom ?>cm;
        }

        break {
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
            border: 1px solid #aaa;
        }

        table.borderline td {
            border-bottom: 2px solid #D8D8D8
        }

        table.borderline tr.has_desc td {
            border: 0
        }

        table.border2.border.tdborder {
            border-top: 1px solid #aaa;
            border-left: 1px solid #aaa;
            border-right: 2px solid #aaa;
            border-bottom: 2px solid #aaa
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

        .header_tbl th {
            text-transform: uppercase;
            background: #555;
            border-color: #555 !important;
            text-align: center;
            height: 16px;
            color: #fff;
        }

        .smallheader th {
            font-size: 8pt;
            line-height: 10px;
        }

        .title_tbl th {
            text-align: center;
            border-bottom: 1px solid #ddd;
            height: 15px;
            background: #ddd;
            color: #333;
            padding-top: 0;
            padding-bottom: 0;
        }

        tr th.title_tbl {
            background: #eee;
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
            text-align: right
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
            font-family: "DejaVu Sans", 'Oswald', Arial;
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

        table.comment_next_row td.pos_neg {
            font-weight: bold;
        }

        table.comment_next_row td.conc {
            background: #fbfbfb;
        }

        .space_cell {
            border-bottom: none !important;
            border-top: none !important;
        }

        td.bolded {
            font-weight: bold;
        }

        h4.group_parent {
            margin-bottom: 10px;
            font-size: 14px;
        }

        <?php
        if (tr_get_option('ci_pdf_data', 'pdf_bold_assen')) {
            echo '.assen_number{font-weight:bold;font-size:9pt !important}';
        }
        ?>
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
                        <?php if (tr_get_option('ci_pdf_data', 'show_patient_passport') && !empty($report['pa_passport'])) { ?>
                            <tr>
                                <td>Passport:</td>
                                <td colspan="3"><?php echo htmlspecialchars($report['pa_passport']) ?></td>
                            </tr>
                        <?php } ?>
                        <?php if ($show_location && !empty($report['pa_location'])) { ?>
                            <tr>
                                <td>Loc:</td>
                                <td colspan="2"><?php echo htmlspecialchars($report['pa_location']) ?></td>
                            </tr>
                        <?php } ?>
                    </table>
                </td>
                <td style="padding-left:5px;">
                    <table class="noboder">
                        <tr>
                            <td style="width:50%">Accession Number:</td>
                            <td class="assen_number"><?php echo $report['assen'] ?></td>
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
                            <td><?php echo tr_pdd_display_date($report['spec_received'], 'm/d/Y H:i') ?></td>
                        </tr>

                        <tr>
                            <td>Date Reported:</td>
                            <td><?php echo tr_pdd_display_date($report['approve_date'], 'm/d/Y H:i') ?></td>
                        </tr>
                        <?php
                        if (!empty($report['dagnostic_codes1']) && tr_get_option('ci_pdf_data', 'genepdf_show_diagnostic')) {
                        ?>
                            <tr>
                                <td>Diagnostic code:</td>
                                <td><?php echo $report['dagnostic_codes1'] ?></td>
                            </tr>
                        <?php
                        }
                        $pr_code = tr_get_option('ci_pdf_data', 'gene_proceduralcode');
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
        $rejected = tr_pdd_get_batch_statuses($report['batch_status'], false, false, 'gene');
        $has_report = true;
    ?>
        <br>
        <br><br><br><br>
        <h1 style="color:red;font-size:20px;text-align: center"><?php echo $rejected ?></h1><br>
        <div style="text-align: center;">
            Date: <?php echo tr_pdd_display_date($report['created_date'], 'm/d/Y'); ?>
            <?php if (!empty($report['notes'])): ?>
                Reason: <?php echo $report['notes'] ?>
            <?php endif; ?>
        </div>
        <br>
        <?php
    } else {

        $showed_parent_group = array();
        foreach ($drug_groups as $groupid => $groupdata) {
            ob_start();
            $has_test = false;
            $has_pos = false;
            $is_split_tbl = false;
            $result_header_text = 'Result';
            $units_header_text = 'Units';
            $column_count = 3;

            $gene_pdf_show_conc = false;
            $show_group_range         = isset($groupdata['other_options']['show_range']) && $groupdata['other_options']['show_range'];
            $hide_units_col = isset($groupdata['other_options']['hide_units_col']) && $groupdata['other_options']['hide_units_col'];
            if (isset($groupdata['other_options']['show_value_in_result']) && $groupdata['other_options']['show_value_in_result']) {
                $gene_pdf_show_conc = true;
            }

            if ($gene_pdf_show_conc) {
                $column_count++;
            }
            if ($show_group_range) {
                $column_count++;
            }

            if (!$hide_units_col) {
                $column_count++;
            }

            if (isset($groupdata['other_options']['split_table']) && $groupdata['other_options']['split_table']) {
                $is_split_tbl = true;
                $table_class = ' split_tbl ';
                $groupdata['comment_next_row'] = false;
                $column_count =  ($column_count - 1) * 2 + 1;
            }

            if ($groupdata['comment_next_row']) {
                $column_count--;
            }

            $isub = -1;
            $has_close_row = false;
            foreach ($groupdata['tests'] as $arr) {
                $isub++;
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
                $pos_neg_vls = $pos_neg;
                if ($arr['te_is_detected']) {
                    $pos_neg_vls['POS'] = 'Detected';
                    $pos_neg_vls['NEG'] = 'Not Detected';
                    if (!$arr['only_detected']) {
                        $pos_neg_vls['POL'] = 'Detected-Low';
                        $pos_neg_vls['POM'] = 'Detected-Medium';
                        $pos_neg_vls['POH'] = 'Detected-High';
                        unset($pos_neg_vls['POS']);
                        if ($arr['pos-neg'] == 'POS') {
                            $arr['pos-neg'] = 'POL';
                        }
                    }
                }
                if ($arr['te_inconclusive_ID'] === '-1') {
                    $pos_neg_vls['POS'] = 'SUFFICIENT';
                    $pos_neg_vls['NEG'] = 'INSUFFICIENT';
                }
                if (stripos($arr['te_name'], 'MonkeyPox') !== false) {
                    $pos_neg_vls['EQU'] = 'Equivocal';
                    $pos_neg_vls['INC'] = 'Inconclusive';
                }
                $class .= ' posneg_' . $arr['pos-neg'];
                $arr['conc_dis'] = isset($arr['conc_dis']) ? $arr['conc_dis'] : $arr['conc'];
                if ($arr['critical'] == 'L') {
                    $arr['pos-neg'] = 'PCL';
                } else if ($arr['critical'] == 'H') {
                    $arr['pos-neg'] = 'PCH';
                }
                if (($arr['conc'] === '' && empty($arr['pos-neg'])) || $arr['pos-neg'] == 'CAN') {
                    continue;
                }
                if (empty($arr['comments'])) {
                    $arr['comments'] = $arr['te_desc'];
                }

                if ($groupdata['comment_next_row'] && !empty($arr['comments'])) {
                    $class .= ' comment_at_bottom ';
                }
                $result_class = '';
                $result_vl = isset($pos_neg_vls[$arr['pos-neg']]) ? $pos_neg_vls[$arr['pos-neg']] : $arr['pos-neg'];
                if (!$gene_pdf_show_conc && !isset($pos_neg_vls[$arr['pos-neg']])) {
                    $result_vl = $arr['conc'];
                    if (strpos($result_vl, '/') !== false) {
                        $result_class .= ' bolded ';
                        $result_header_text = 'Variants';
                    }
                    if (stripos($result_vl, 'undetermined') !== false) {
                        $result_class .= ' undetermined ';
                    } else if ($arr['te_conc_decimal'] === '1') {
                        $result_vl = number_format($arr['conc'], 0);
                        $result_header_text = 'Copy';
                    }
                }



                ///TR
                if ($is_split_tbl) {
                    if ($isub % 2 == 0) {
                        echo '<tr>';
                        $has_close_row = false;
                    }
                } else {
                    echo '<tr class="' . $class . '">';
                }

                ////TD
                echo '<td class="drug">' . $arr['te_name'] . '</td>';
                if ($gene_pdf_show_conc) {
                    echo '<td class="conc center">' . htmlentities($arr['conc_dis']) . '</td>';
                }
                echo '<td class="pos_neg center ' . $result_class . '">' . htmlentities($result_vl) . '</td>';
                if (!$hide_units_col) {
                    echo '<td class="center fontDejavu">' . htmlentities($value_or_units) . '</td>';
                }
                if ($show_group_range) {
                    echo '<td class="center fontDejavu">' . htmlentities(trpdd_show_symbol($range)) . '</td>';
                }

                if (!$is_split_tbl && !$groupdata['comment_next_row']) {
                    echo '<td>' . nl2br(htmlentities($arr['comments'])) . '</td>';
                }

                if ($is_split_tbl) {
                    if ($isub % 2 == 1) {
                        echo '</tr>';
                        $has_close_row = true;
                    } else {
                        echo '<td class="space_cell"></td>';
                    }
                } else {
                    echo '</tr>';
                }


                if (!$is_split_tbl && $groupdata['comment_next_row'] && !empty($arr['comments'])) {
        ?>
                    <tr>
                        <td colspan="<?php echo $column_count ?>" style="border-top:0"><?php echo nl2br(htmlentities($arr['comments'])) ?></td>
                    </tr>
                <?php
                }

                $has_test = true;
            }

            if ($is_split_tbl && !$has_close_row) {
                echo '<td></td><td></td></tr>';
            }


            $content = ob_get_clean();

            if ($has_test) {
                $has_report = true;
                if ($groupdata['parent'] > 0 && !in_array($groupdata['parent'], $showed_parent_group)) {
                    $panel_name = isset($drug_groups[$groupdata['parent']]) ? $drug_groups[$groupdata['parent']]['name'] : '';
                    echo '<h4 class=" center group_parent">' . $panel_name . '</h4>';
                    $showed_parent_group[] = $groupdata['parent'];
                }
                ?>
                <table class="small_table smallheader tdborder marginbottom <?php if ($groupdata['comment_next_row']) echo 'comment_next_row' ?>">
                    <thead>
                        <tr class="header_tbl">
                            <th colspan="<?php echo $column_count ?>" class="group_name center">
                                <?php echo $groupdata['name'] ?>
                            </th>
                        </tr>
                        <tr class="title_tbl">
                            <?php
                            echo '<th style="width:25%">Test Name</th>';
                            echo '<th style="width:9%">' . $result_header_text . '</th>';

                            if ($gene_pdf_show_conc) {
                                echo '<th style="width:12%">Flag</th>';
                            }

                            if (!$hide_units_col) {
                                echo '<th style="width:9%">' . $units_header_text . '</th>';
                            }
                            if ($show_group_range) {
                                echo '<th style="width:10%">Reference Range</th>';
                            }
                            if (!$is_split_tbl && !$groupdata['comment_next_row']) {
                                echo '<th>Comments</th>';
                            }

                            if ($is_split_tbl) {
                                echo '<th class="space_cell" style="width:1.4% !important;border:none;background:none"></th>';
                                echo '<th style="width:25%">Test Name</th>';
                                echo '<th style="width:9%">' . $result_header_text . '</th>';

                                if ($gene_pdf_show_conc) {
                                    echo '<th style="width:12%">Flag</th>';
                                }

                                if (!$hide_units_col) {
                                    echo '<th style="width:9%">' . $units_header_text . '</th>';
                                }
                                if ($show_group_range) {
                                    echo '<th style="width:10%">Reference Range</th>';
                                }
                            }
                            ?>
                        </tr>
                    </thead>
                    <?php echo $content; ?>
                </table>
    <?php
                if (!empty($groupdata['comments'])) {
                    echo '<div>' . nl2br($groupdata['comments']) . '</div>';
                }
            }
        }
    } //end if rejected
    ?>

    <?php
    if (!empty($report['notes_report'])) {
        echo '<div style="padding-top:15px">';
        //echo 'Notes: ';
        echo nl2br(stripslashes($report['notes_report']));
        echo '</div>';
    }
    ?>


    <?php
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