<?php
/*
Name: Default
*/

global $wpdb, $ci_cutoffs, $all_res;
global $pdf_header_text;

$btype                  = 'blood';
$id                     = isset($_REQUEST['id']) ? $_REQUEST['id'] : $id;
$bid                    = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : (isset($bid) ? $bid : 0);
$mid                    = isset($mid) ? $mid : 0;


$sum                    = true; //now live sum
$has_report             = false;
$has_style2             = false;
$has_manual_antibiotic  = false;
$group_manual_antibiotic_name = '';
$bottom_comments        = array();
$upload_dir             = tr_wp_upload_dir();
$logo_dir               = $upload_dir['basedir'] . '/logos/';
$logo_url               = $upload_dir['baseurl'] . '/logos/';


$req_settings           = get_option('ci_req_settings_blood', array());
$settings               = get_option('ci_result_settings_blood', array());
$notes                  = get_option('ci_notes_result_blood', array());

$style_num              = tr_get_option('ci_pdf_data', 'blood_pdf_style', 4);
$blood_pdf_show_conc    = tr_get_option('ci_pdf_data', 'blood_pdf_show_conc');

$pdf_title              = tr_get_option('ci_pdf_data', 'blood_pdf_title');
$showdatetime           = tr_get_option('ci_pdf_data', 'results_dtime');
$show_summary           = isset($settings['result_e_summary']) ? $settings['result_e_summary'] : false;
$hide_au_neg_flag       = isset($settings['onlyposua']) ? $settings['onlyposua'] : '';

if (empty($pdf_title)) {
    $pdf_title = 'Test Lab Report';
}


if ($id == 0) {
    $mid = isset($_REQUEST['mid']) ? $_REQUEST['mid'] : $mid;
} else {
    $mid  = 0;
}

if (!$report) {
    $report = tr_pdd_get_report_by_id($id, 'blood', $mid);
}

if ($id == 0) {
    $id = $report['id'];
}


$reflex_main_tests = tr_pdd_check_group_reflex($report, 'blood');
$show_location             = tr_get_option('ci_pdf_data', 'req_patient_location') && !empty($report['pa_location']);
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
if (tr_get_option('ci_pdf_data', 'results_sp_adoctor') && !empty($report['doc_specialty'])) {
    $report['physician'] .= ' ' . $report['doc_specialty'];
}



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
        $group_ids[] = str_ireplace('group_', '', $t);
    }
}
$test_ids = implode(',', $in_panel_tests_requested);
if (empty($test_ids)) {
    $test_ids = '-1';
}

$adding_test_arrs = array();
/*
if (tr_get_option('ci_pdf_data', 'api_all_tests_same_code') && $report['paid'] < 0) {
    $orderinfo = $wpdb->get_var("select other_info from ci_sync_api_orders 
    where mo_file_id > 0 and mo_mid = {$report['mid']} and mo_btype = 'blood' limit 1");
    if (!empty($orderinfo)) {
        $orderinfo_arr = json_decode($orderinfo, true);
        if (is_array($orderinfo_arr) && isset($orderinfo_arr['OBR_code']) && !empty($orderinfo_arr['OBR_code'])) {
            if (!is_array($orderinfo_arr['OBR_code'])) {
                $orderinfo_arr['OBR_code'] = explode(',', $orderinfo_arr['OBR_code']);
            }


            foreach ($orderinfo_arr['OBR_code'] as $code) {
                $code = trim($code);
                if (empty($code)) {
                    continue;
                }
                $testarr = $wpdb->get_results("select * from ci_test_panels 
                where (te_name = '{$code}' or te_abrev = '{$code}' or te_abrev2 = '{$code}' or te_loinc_code = '{$code}')
                and te_type = 'blood' ", ARRAY_A);

                foreach ($testarr as $testarr) {
                    if (!in_array($testarr['te_ID'], $in_panel_tests_requested)) {
                        $in_panel_tests_requested[] = $testarr['te_ID'];
                        $adding_test_arrs[$testarr['te_ID']] = $testarr;

                        if (isset($_GET['debug22'])) {
                            var_dump($testarr['te_ID']);
                            echo '<br>';
                        }
                    }
                }
            }


            if (count($in_panel_tests_requested) > 0) {
                $test_ids = implode(',', $in_panel_tests_requested);
            }
        }
    }
} */

$where_group = '';


$group_ids_str = implode(',', $group_ids);
if (empty($group_ids_str)) {
    $group_ids_str = '-1';
}

$group_ids_str_w_childs = $wpdb->get_col("select tg_ID from ci_test_groups 
	where tg_ID in ({$group_ids_str}) or tg_parent in ({$group_ids_str}) ");

$group_ids_str = implode(',', $group_ids_str_w_childs);
if (empty($group_ids_str)) {
    $group_ids_str = '-1';
}

if (count($group_ids) > 0) {
    $where_group .= "  and tg_ID in ({$group_ids_str})";
} else {
    $where_group .= "  and 1=2";
}


$sql = "SELECT *
        FROM ci_test_panels tp
        left join ci_results_blood rs ON (tp.te_name = rs.drug or tp.te_abrev = rs.drug or tp.te_abrev2 = rs.drug) 
                                and rs.report_id= {$id} and (rs.group_id = 0 or rs.group_id is null or rs.group_id  = tp.te_group_id)
        WHERE  tp.te_type='blood' 
        and ( 
                (rs.id >0 
                    and (   
                            tp.te_ID in ({$test_ids}) 
                            OR tp.te_group_id in (select tg_ID from ci_test_groups where tg_type = 'blood' 
                                                        and (tg_only_group = 1  or tg_notestcheckbox=1) {$where_group})
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



$all_res = isset($pre_all_res) ? $pre_all_res : array();
foreach ($all_results_q as $arr) {
    $arr['drug'] = strtoupper($arr['te_name']);
    $all_res[$arr['te_ID']] = $arr;
}

if (tr_get_option('ci_pdf_data', 'api_all_tests_same_code')) {
    foreach ($adding_test_arrs as $tid => $arr) {
        if (!isset($all_res[$tid])) {

            if (isset($_GET['debug22'])) {
                echo '<br>added test:';
                var_dump($tid);
                var_dump($arr['te_name']);
            }
            foreach ($all_res as $tid2 => $arr2) {
                if ($tid != $tid2 && $arr['te_name'] == $arr2['te_name'] && $arr['te_loinc_code'] == $arr2['te_loinc_code'] && $arr['te_type_id'] == $arr2['te_type_id'] && !empty($arr2['conc'])) {
                    $arr['conc'] = $arr2['conc'];
                    $arr['pos-neg'] = $arr2['pos-neg'];

                    $all_res[$tid] = $arr;

                    if (!in_array($arr['te_group_id'], $group_ids)) {
                        $group_ids[] = $arr['te_group_id'];
                    }

                    if (isset($_GET['debug22'])) {
                        var_dump($arr2['conc']);
                        echo '<br>';
                    }
                    break;
                }
            }
        }
    }
}



$drug_groups = tr_pdd_get_drug_groups('blood', $group_ids, true);


$inew = 0;
foreach ($drug_groups as $groupid => $groupdata) {

    if (!isset($drug_groups[$groupid]['subgroups'])) {
        $drug_groups[$groupid]['subgroups'] = array();
    }
    if (isset($groupdata['parent']) && $groupdata['parent'] > 0) {
        $groupparentname = $drug_groups[$groupdata['parent']]['name'];

        if (!empty($groupparentname)) {
            $groupdata['showed'] = true;
            $drug_groups[$groupdata['parent']]['subgroups'][$groupdata['id']] = $groupdata['name'];
        }
    }

    $te_IDs = is_array($groupdata['te_IDs']) ? $groupdata['te_IDs'] : array();

    if (isset($groupdata['manual_antibiotic']) && $groupdata['manual_antibiotic']) {
        $has_manual_antibiotic = true;
        $group_manual_antibiotic_name = $groupdata['name'];
    }
    if ($hide_au_neg_flag && (stripos($groupdata['name'], '(UA)') !== false || stripos($groupdata['name'], ' UA ') !== false)) {
        $groupdata['hidenegflag'] = true;
    }
    $groupdata['tests'] = array();

    foreach ($te_IDs as $te_ID) {
        $arr = isset($all_res[$te_ID]) ? $all_res[$te_ID] : array();

        if (!empty($arr)) {

            $arr['showed'] = true;
            if (empty($arr['id'])) {
                $arr['id'] = 'new' . $inew++;
            }
            if (is_null($arr['conc'])) {
                $arr['conc'] = '';
            }
            $arr = tr_pdd_parse_blood_test($arr, $report['instrument'], $groupdata['id'], $report);

            if ($arr['pos-neg'] == 'REJ' || $arr['pos-neg'] == 'CAN') {
                $arr['conc'] = '';
            }
            if (isset($groupdata['hidenegflag']) && $groupdata['hidenegflag'] && !in_array($arr['pos-neg'], array('POS', 'POL', 'POH', 'POM'))) {
                $arr['flag'] = '';
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

    if (isset($_GET['debug_group'])) {
        var_dump($groupdata);
        echo '<br><br>';
    }

    if (tr_get_option('ci_result_settings_blood', 'pos_at_top')) {
        uasort($groupdata['tests'], function ($a, $b) {
            $pos_neg_order = array(
                'PVH' => 1,
                'POH' => 2,
                'POM' => 3,
                'POL' => 4,
                'POS' => 5,
                'PVL' => 6,
                'NEG' => 7,
                '' => 8
            );
            $a_order = isset($pos_neg_order[$a['pos-neg']]) ? $pos_neg_order[$a['pos-neg']] : 9;
            $b_order = isset($pos_neg_order[$b['pos-neg']]) ? $pos_neg_order[$b['pos-neg']] : 9;
            return $a_order - $b_order;
        });
    }


    $drug_groups[$groupid] = $groupdata;

    //get group style
    if ($groupdata['pdf_style'] > 0 && count($groupdata['tests']) > 0 && !$has_style2) {
        $style_num = $groupdata['pdf_style'];
        if ($style_num == 2) {
            $has_style2 = true;
        }
    }

    if (stripos($groupdata['name'], 'antibody') !== false) {
        $pdf_title = 'Covid-19 Antibody Test Lab Report';
    }

    if (!empty($groupdata['pdf_title'])) {
        $pdf_title = $groupdata['pdf_title'];
    }

    if ($groupdata['parent'] == 0) {
        $report['group_id'] = $groupdata['id'];
    }

    if (isset($groupdata['other_options']['pdf_show_summary']) && $groupdata['other_options']['pdf_show_summary']) {
        $show_summary = true;
    }
}



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

$footer_height = 1.8;

$footer_text    = trpdd_get_footer_text($report, 'blood');
$pdf_notes      = trpdd_get_pdf_note($report, 'blood');

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
    $new_template_file = TRCPDD_PATH . 'reports/results_blood_' . $style_num . '.php';
    if (file_exists($new_template_file)) {
        if (isset($_GET['debug']) || isset($_GET['html'])) {
            echo 'file: ' . $new_template_file . '<br>';
            echo 'TRCDPDF_NEW: ' . TRCDPDF_NEW . '<br>';
        }
        include($new_template_file);
        return;
    }
} else if (isset($_GET['html'])) {
    echo 'style:1<br>';
    echo 'TRCDPDF_NEW: ' . TRCDPDF_NEW . '<br>';
}

/* =========================== */
/* ==  Begin creating html  == */
/* =========================== */



$margin_bottom = 0;
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
            margin-top: 6cm;
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

        .notes_report p {
            margin: 0;
            padding: 0;

        }

        .notes_report br {
            line-height: 4px;
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
                            <td style="width:18%">Name:</td>
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



    if (!empty($report['batch_status']) && ($report['batch_status'] == 'rejected' || strpos($report['batch_status'], 're_') !== false)) {
        include('rejected_default.php');
    } else {

        if ($has_manual_antibiotic) {
            include('manual_antibiotic_blood.php');
        }


        $showed_title_parents = array();

        foreach ($drug_groups as $groupid => $groupdata) {


            $has_test = false;
            $has_pos = false;
            $units_header_text = 'Units';
            $column_count = 5;
            if ($groupdata['comment_next_row']) {
                $column_count--;
            }

            $group_blood_pdf_show_conc = $blood_pdf_show_conc;

            if (isset($groupdata['hide_ct']) && $groupdata['hide_ct']) {
                $group_blood_pdf_show_conc = false;
            }
            if (isset($groupdata['other_options']['show_value_in_result']) && $groupdata['other_options']['show_value_in_result']) {
                $group_blood_pdf_show_conc = true;
            }

            if ($group_blood_pdf_show_conc) {
                $column_count++;
            }
            ob_start();
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
                    $units_header_text  = $arr['te_units'];
                    $value_or_units     = $arr['conc'];
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
                if (($arr['conc'] === '' && empty($arr['pos-neg'])) || $arr['pos-neg'] == 'CAN') {
                    continue;
                }
                if (empty($arr['comments'])) {
                    $arr['comments'] = $arr['te_desc'];
                }

                if ($groupdata['comment_next_row'] && !empty($arr['comments'])) {
                    $class .= ' comment_at_bottom ';
                }


    ?>
                <tr class="<?php echo $class ?>">
                    <td class="drug"><?php echo $arr['te_name'] ?></td>
                    <?php if ($group_blood_pdf_show_conc) {
                        echo '<td class="conc center">' . htmlentities($arr['conc_dis']) . '</td>';
                    } ?>
                    <td class="pos_neg fontDejavu center"><?php echo $arr['flag'] ?></td>
                    <td class="center fontDejavu"><?php echo trpdd_show_symbol($value_or_units, true) ?></td>
                    <td class="center fontDejavu"><?php echo trpdd_show_symbol($range, true) ?></td>
                    <?php if (!$groupdata['comment_next_row']) { ?>
                        <td><?php echo trpdd_filter_comments_in_pdf($arr['comments'], true) ?></td>
                    <?php } ?>
                </tr>
                <?php

                if ($groupdata['comment_next_row'] && !empty($arr['comments'])) {
                ?>
                    <tr>
                        <td colspan="<?php echo $column_count ?>" style="border-top:0"><?php echo trpdd_filter_comments_in_pdf($arr['comments'], true) ?></td>
                    </tr>
                <?php
                }

                $has_test = true;
            }


            $content = ob_get_clean();

            if ($has_test) {
                $has_report = true;
                ?>
                <table class="small_table tdborder marginbottom <?php if ($groupdata['comment_next_row']) echo 'comment_next_row' ?>">
                    <thead>
                        <?php
                        if ($groupdata['parent'] == 0 || ($groupdata['parent'] > 0 && !in_array($groupdata['parent'], $showed_title_parents))) {
                            $parent_tt = $groupdata['name'];
                            if ($groupdata['parent'] > 0) {
                                $parent_tt = $drug_groups[$groupdata['parent']]['name'];
                                $showed_title_parents[] = $groupdata['parent'];
                            } else {
                                $showed_title_parents[] = $groupdata['id'];
                            }
                        ?>
                            <tr class="header_tbl">
                                <th colspan="<?php echo $column_count ?>">
                                    <?php echo $parent_tt ?>
                                </th>
                            </tr>
                        <?php } else  if ($groupdata['parent'] > 0) {
                        ?>
                            <tr class="header_tbl">
                                <th colspan="<?php echo $column_count ?>"><?php echo $groupdata['name'] ?></th>
                            </tr>
                        <?php
                        }
                        ?>
                        <tr class="title_tbl">
                            <th style="width:25%">Test Name</th>
                            <th style="width:9%">Result</th>
                            <?php if ($group_blood_pdf_show_conc) {
                                echo '<th style="width:12%">Flag</th>';
                            } ?>
                            <th style="width:9%"><?php echo trpdd_show_symbol($units_header_text, true) ?></th>
                            <th style="width:10%">Reference Range</th>
                            <?php if (!$groupdata['comment_next_row']) { ?>
                                <th>Comments</th>
                            <?php } ?>
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

    include('_approved_by.php');

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
        foreach ($bottom_comments as $cm) {
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