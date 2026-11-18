<?php
global $wpdb;

$enable_genotypic_antibiogram = tr_get_option('ci_pdf_data', 'abx_genotypic_antibiogram');

$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';
$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$sub_ajax = isset($_REQUEST['sub_ajax']) ? $_REQUEST['sub_ajax'] : '';
$table1config = array();

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

$numtestsofm = 6;
$pc_positive = 50;
$pc_negative = 50;
$monthname = date_i18n('M');
$monthfullname = date_i18n('F');
$total_p_6 = 0;
$total_p = 0;
$total_n_6 = 0;
$pcpos_6 = 0;
$pcpos = 0;
$total_n = 0;
$p_rate = 0;
$n_rate = 0;

$from_date      = date_i18n('Y-m-01');
$where_clinic   = '';
$where_group    = '';
$where          = ' ba.is_saved = 0 ' . $conditions['where'];



if ($c_cid > 0) {
    $where .= " AND ba.cli_ID = " . intval($c_cid);
    $where_clinic .= " AND ba.cli_ID = " . intval($c_cid);
}
$filter_group_ids = array();

if ($g_id) {
    $main_abx_group_gids = array($g_id);
}

$where_group = array();
foreach ($main_abx_group_gids as $gid) {
    $where_group[] = " FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)  ";
}
$where_group = implode(' or ', $where_group);

$where .= " and ( {$where_group} ) ";


$archive_date   = tr_get_option('ci_pdf_data', 'archive_date', '');
if (!empty($archive_date)) {
    $where .= " and ba.batch_created > '{$archive_date}' ";
}

$is_export_request =
    !empty($export_type)
    && in_array($export_type, ['pdf', 'csv'], true)
    && (!empty($export_func) && ($filepath_save_pdf === '' && $filepath_save_csv === ''));

if (!$is_export_request) {
    if ($sub_ajax == "test_result" || $sub_ajax == "all" || $export_func != "") {
        include "report_tabs_ajax_test_result.php";
    }

    if ($sub_ajax == 'volume_trend_6_month' || $sub_ajax == "all" || $export_func != "") {
        include "report_tabs_ajax_volume_trend.php";
    }

    if ($sub_ajax == 'pathogens' || $sub_ajax == "all" || $export_func != "") {
        include "report_tabs_ajax_pathogens.php";
    }

    if ($sub_ajax == 'resistance_markers' || $sub_ajax == "all" || $export_func != "") {
        include "report_tabs_ajax_resistance_markers.php";
    }

    if ($sub_ajax == 'polymicrobial_infections' || $sub_ajax == "all" || $export_func != "") {
        include "report_tabs_ajax_polymicrobial_infections.php";
    }

    if ($sub_ajax == 'mdr' || $sub_ajax == "all" || $export_func != "") {
        include "report_tabs_ajax_mdr.php";
    }

    if ($enable_genotypic_antibiogram && ($sub_ajax == 'genotypic_antibiogram' || $sub_ajax == "all" || $export_func != "")) {
        $genotypic_antibiogram = CI_ABX_Report::get_genotypic_antibiogram_summary(
            $where,
            $main_abx_group_gids,
            $where_group,
            $date_from,
            $date_to,
            $tab
        );

        $genotypic_antibiogram['date_sel'] = $cur_name_month_range;
    }

    if ($sub_ajax == 'clinic_activity' || $sub_ajax == "all" || $export_func != "") {
        include "report_tabs_ajax_clinic_activity.php";
    }

    if ($sub_ajax == 'critical_findings' || $sub_ajax == "all" || $export_func != "") {
        include "report_tabs_ajax_critical_findings.php";
    }
}


if ($export_func != '') {

    //Lấy tên panel để export file pdf,csv khi 1 tab chỉ có panel
    $single_panel_name = '';
    if (!empty($main_abx_group_gids) && count($main_abx_group_gids) === 1) {
        $only_id = reset($main_abx_group_gids);
        $single_panel_name = $groups_mapping[$only_id]['name'] ?? '';
    }

    function crop_center_base64($base64_image, $crop_width, $crop_height)
    {

        list($type, $data) = explode(';', $base64_image);
        list(, $data)      = explode(',', $data);

        return "data:image/png;base64," . $data;

        $data = base64_decode($data);
        $src = imagecreatefromstring($data);
        if (!$src) return false;

        $width  = imagesx($src);
        $height = imagesy($src);

        $x = ($width  - $crop_width)  / 2;
        $y = ($height - $crop_height) / 2;
        if ($x < 0) $x = 0;
        if ($y < 0) $y = 0;

        $dst = imagecreatetruecolor($crop_width, $crop_height);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $crop_width, $crop_height, $white);

        imagecopy(
            $dst,
            $src,
            0,
            0,
            $x,
            $y,
            $crop_width,
            $crop_height
        );

        ob_start();
        imagepng($dst);
        $output = ob_get_clean();

        return "data:image/png;base64," . base64_encode($output);
    }

    if ($filepath_save_pdf == '' || $filepath_save_csv == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Ignite ABX Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
        $image_test_results = isset($_POST['image_test_results']) ? $_POST['image_test_results'] : null;
        $image_pathogens = isset($_POST['image_pathogens']) ? $_POST['image_pathogens'] : null;
        $image_marker = isset($_POST['image_marker']) ? $_POST['image_marker'] : null;

        $chart = isset($_POST['chart1_data']) ? json_decode(stripslashes($_POST['chart1_data']), true) : null;
        $chart2 = isset($_POST['chart2_data']) ? json_decode(stripslashes($_POST['chart2_data']), true) : null;
        $chart3 = isset($_POST['chart3_data']) ? json_decode(stripslashes($_POST['chart3_data']), true) : null;
        $chart4 = isset($_POST['chart4_data']) ? json_decode(stripslashes($_POST['chart4_data']), true) : null;
        $chart5 = isset($_POST['chart5_data']) ? json_decode(stripslashes($_POST['chart5_data']), true) : null;
        $chart6 = isset($_POST['chart6_data']) ? json_decode(stripslashes($_POST['chart6_data']), true) : null;

        $table_pathogens = isset($_POST['table_pathogens']) ? json_decode(stripslashes($_POST['table_pathogens']), true) : null;
        $table_marker = isset($_POST['table_marker']) ? json_decode(stripslashes($_POST['table_marker']), true) : null;
        $genotypic_antibiogram = isset($_POST['genotypic_antibiogram']) ? json_decode(stripslashes($_POST['genotypic_antibiogram']), true) : null;
        $critical_findings_summary = isset($_POST['critical_findings']) ? json_decode(stripslashes($_POST['critical_findings']), true) : null;
        $days_daily = isset($_POST['days_daily_data']) ? json_decode(stripslashes($_POST['days_daily_data']), true) : null;

        if ($chart && is_array($chart)) {
            $chart = (object) $chart;
        }
        if ($chart2 && is_array($chart2)) {
            $chart2 = (object) $chart2;
            if (isset($chart2->monthdata)) {
                $chart2->monthdata = $chart2->monthdata;
            }
        }
        if ($chart3 && is_array($chart3)) {
            $chart3 = (object) $chart3;
        }
        if ($chart4 && is_array($chart4)) {
            $chart4 = (object) $chart4;
        }
        if ($chart5 && is_array($chart5)) {
            $chart5 = (object) $chart5;
        }
        if ($chart6 && is_array($chart6)) {
            $chart6 = (object) $chart6;
        }

        if ($chart && isset($chart->chartTotal)) {
            $numtestsofm = $chart->chartTotal;
            $total_p = $chart->data[2][1] ?? 0;
            $total_n = $chart->data[1][1] ?? 0;

            $pcpos = $numtestsofm > 0 ? round(($total_p / $numtestsofm) * 100, 1) : 0;
        }
        if ($chart2 && isset($chart2->monthdata)) {
            foreach ($chart2->monthdata as $row) {
                if (isset($row[1])) $total_p_6 += $row[1];
                if (isset($row[2])) $total_n_6 += $row[2];
            }
            $all = $total_p_6 + $total_n_6;
            $pcpos_6 = $all > 0 ? round(($total_p_6 / $all) * 100, 1) : 0;
        }

        $critical_total = array();
        if ($critical_findings_summary && isset($critical_findings_summary['total_summary'])) {
            $critical_total = $critical_findings_summary['total_summary'];
        }
    }
    $prevalence_pathogen = isset($_POST['prevalence_pathogen']) ? $_POST['prevalence_pathogen'] : '0';
    $prevalence_rmk = isset($_POST['prevalence_rmk']) ? $_POST['prevalence_rmk'] : '0';

    $orientation = 'portrait';
    $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
    $current_date = date_i18n("Y-m-d");
    $period_month = date_i18n("F Y");
    $period_export = ($date_from == $date_to) ? 'Today' : $date_from . " to " . $date_to;
    $lab_init = CI_Base_Lab::get_current_lab_init();
    $mappingTabs = [
        'UTI' => "UTI",
        'Wound' => "Wound",
        'Respiratory' => "Resp",
        "Vaginal" => "Vag",
        "GI" => "GI",
        "ENT" => "ENT",
        "Nail" => "Nail",
        "STI" => "STI",
    ];
    $t_title = isset($mappingTabs[$tab_title]) ? $mappingTabs[$tab_title] : $tab_title;
    $view_mode_path = 'Distribution';
    $sub_title_path = 'Relative share of total targets';
    if ($prevalence_pathogen == 'all') {
        $view_mode_path = 'Prevalence (All)';
        $sub_title_path = 'Target % of tested specimens';
    } elseif ($prevalence_pathogen == 'pos') {
        $view_mode_path = 'Prevalence (Pos)';
        $sub_title_path = 'Target % of positive specimens';
    }
    $view_mode_rm = 'Distribution';
    $sub_title_rm = 'Relative share of total targets';
    if ($prevalence_rmk == 'all') {
        $view_mode_rm = 'Prevalence (All)';
        $sub_title_rm = 'Target % of tested specimens';
    } elseif ($prevalence_rmk == 'pos') {
        $view_mode_rm = 'Prevalence (Pos)';
        $sub_title_rm = 'Target % of positive specimens';
    }

    if ($export_type == 'pdf' || $filepath_save_pdf != '') {

        if ($filepath_save_pdf != '') {
            //Tạo các ảnh biểu đồ để gửi mail
            $img_chart = new CI_Pix_Chart();
            $img_chart->setWith(800);
            $img_chart->setHeight(800);
            $image_test_results = $img_chart->get_doughnut_base64_by_chart_old($chart, "Total Samples", 30);

            $image_pathogens = $img_chart->get_doughnut_base64_by_chart_old($chart3);

            $image_marker = $img_chart->get_doughnut_base64_by_chart_old($chart4);
            //file_put_contents(WP_CONTENT_DIR . '/all_chart.txt',  print_r($chart5, true));
            //exit;
        }
        ob_start();

        if ($export_func == 'all_panels_positivity_rate_trends') {
            require TRCPDD_PATH . 'parts/reportabx/report_tabs_volume_trend_pdf.php';
            $html = ob_get_clean();
            $outfile = generate_report_filename('igniteABX', $t_title . 'VolumeTrends', $date_from, $date_to);
        } else if ($export_func == 'all_panels_critical_findings') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'CriticalFindings', $date_from, $date_to);
            require TRCPDD_PATH . 'parts/reportabx/report_tabs_critical_findings_pdf.php';
            $html = ob_get_clean();
        } else if ($export_func == 'all_panels_pathogens') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'PathogenDistribution', $date_from, $date_to);
            require TRCPDD_PATH . 'parts/reportabx/report_tabs_pathogens_pdf.php';
            $html = ob_get_clean();
        } else if ($export_func == 'all_panels_rmk') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'ResistanceDistribution', $date_from, $date_to);
            require TRCPDD_PATH . 'parts/reportabx/report_tabs_rmk_pdf.php';
            $html = ob_get_clean();
        } else if ($export_func == 'genotypic_antibiogram') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'MolecularResistanceSummary', $date_from, $date_to);
            require TRCPDD_PATH . 'parts/reportabx/report_genotypic_antibiogram_pdf.php';
            $html = ob_get_clean();
        } else {
            if ($single_panel_name != "") {
                $name_report = preg_replace('/[^A-Za-z0-9\.\-_]/', '', $single_panel_name);
            } else {
                if ($cur_name_filter_panel == "All Panels")
                    $name_report = $tab_title . "Panels";
                else
                    $name_report = preg_replace('/[^A-Za-z0-9\.\-_]/', '', $cur_name_filter_panel);;
            }
            $outfile = generate_report_filename('igniteABX', $name_report, $date_from, $date_to);
            require TRCPDD_PATH . 'parts/reportabx/report_tabs_pdf.php';
            $html = ob_get_clean();
        }

        if ($filepath_save_pdf != '') {
            if (function_exists('trdomepdf_build_pdf')) {
                $result = trdomepdf_build_pdf($html, [
                    'size' => 'letter',
                    'orientation' => $orientation,
                    'filepath' => $filepath_save_pdf,
                    'download' => 0,
                    'compress' => 0,
                    'dpi' => 110,
                ]);
                $pdf_out_file = basename($outfile);
            }
        } else {
            trpdd_build_pdf($html, [
                'size' => 'letter',
                'orientation' => $orientation,
                'filename' => $outfile,
                'download' => 1,
                'compress' => 1,
                'dpi' => 110,
            ]);
            exit;
        }
    }

    if ($export_type === 'csv' || $filepath_save_csv != '') {

        if ($filepath_save_csv != '')
            $f = fopen($filepath_save_csv, 'w');
        else
            $f = fopen('php://output', 'w');

        if ($export_func == 'all_panels_positivity_rate_trends') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'PositivityTrends', date_i18n('Ymd'), '', 'csv');
            tr_pdd_header('csv', $outfile);

            fputcsv($f, [" "]);
            fputcsv($f, ["Date ", "Total Sample ", "Negative ", "Positive ", "Positivity Rate", "\t\t\t\t\t"]);

            $sum_samples = 0;
            $sum_neg = 0;
            $sum_pos = 0;
            $count_months = count($chart2->monthdata);

            foreach ($chart2->monthdata as $index => $row) {
                $sum_samples += $row[5];
                $sum_neg     += $row[2];
                $sum_pos     += $row[1];

                $month = $row[0];
                $negative_count = $row[2];
                $positive_count = $row[1];
                $total = $row[5];
                $positive_rate = $row[3];
                fputcsv($f, [$month, $total, $negative_count, $positive_count, $positive_rate . '%']);
            }


            // Tính AVERAGE
            $avg_samples = $count_months > 0 ? round($sum_samples / $count_months) : 0;
            $avg_neg     = $count_months > 0 ? round($sum_neg / $count_months) : 0;
            $avg_pos     = $count_months > 0 ? round($sum_pos / $count_months) : 0;

            // Average positivity rate
            $avg_rate = $avg_samples > 0 ? round(($avg_pos / $avg_samples) * 100, 1) : 0;
            fputcsv($f, ["Average", $avg_samples, $avg_neg, $avg_pos, $avg_rate . '%']);
        } else if ($export_func == 'all_panels_critical_findings') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'CriticalFindings', $date_from, $date_to, 'csv');
            tr_pdd_header('csv', $outfile);

            $most_common = '';
            $most_common_count = 0;

            foreach ($critical_total as $type => $count) {
                if ($count > $most_common_count) {
                    $most_common = $type;
                    $most_common_count = $count;
                }
            }

            $totals = $critical_findings_summary['total_summary'];
            $total_clinic = 0;
            foreach ($critical_findings_summary['table'] as $item) {
                if (intval($item['data']['Total']) > 0)
                    $total_clinic += 1;
            }

            fputcsv($f, [" "]);
            fputcsv($f, ["Clinic", "MRSA ", "VRE ", "ESBL ", "CRE ", "MDR AB ", "MDR PA ", "Total", "\t\t\t\t\t"]);
            foreach ($critical_findings_summary['table'] as $item) {
                $row = $item['data'];
                if (intval($row['Total']) == 0)
                    continue;
                fputcsv($f, [$item['clinic'], $row['MRSA'], $row['VRE'], $row['ESBL'], $row['CRE'], $row['MDR_AB'], $row['MDR_PA'], $row['Total']]);
            }
            fputcsv($f, ["Total", $totals['MRSA'], $totals['VRE'], $totals['ESBL'], $totals['CRE'], $totals['MDR_AB'], $totals['MDR_PA'], $critical_findings_summary['total_all']]);
        } else if ($export_func == 'all_panels_pathogens') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'PathogenDistribution', $current_date, '', 'csv');
            tr_pdd_header('csv', $outfile);
            if ($cur_name_filter_panel == "All Panels") {
                $cur_name_filter_panel = "All " . $cur_name_panel . " Panels";
            }

            fputcsv($f, ["igniteABX Pathogen " . $view_mode_path . " Trends Report"]);
            fputcsv($f, ['Report Parameters']);
            fputcsv($f, ['Time Period:', $period_export]);
            fputcsv($f, ['Clinic Filter:', $cur_name_clinic]);
            fputcsv($f, ['Panel Type:', $cur_name_filter_panel]);
            fputcsv($f, ['View Mode:', $view_mode_path . ': ' . $sub_title_path]);
            fputcsv($f, ['Generated:', $generated_on]);

            fputcsv($f, [""]);
            fputcsv($f, ["Pathogen Distribution Trends"]);
            $headers = ["Pathogen"];
            $rows = $table_pathogens['rows'];

            $months = $table_pathogens['columns'];
            foreach ($months as $m) {
                $headers[] = $m;
            }
            $headers[] = "Average";
            fputcsv($f, $headers);

            foreach ($rows as $index => $row) {
                $total_denominator = 0;
                $total_numerator = 0;
                $row_data = [$row['name']];
                foreach ($months as $key => $m) {
                    $cell = isset($row[$key]) ? (array)$row[$key] : [];
                    $key_value = $prevalence_pathogen == '0' ? 'value' : ($prevalence_pathogen == 'all' ? 'value_all' : 'value_pos');
                    $cellValue = isset($cell[$key_value]) ? $cell[$key_value] : 0;
                    $key_denominator = $prevalence_pathogen == '0' ? 'total' : ($prevalence_pathogen == 'all' ? 'total_sample' : 'total_positive');
                    $total_denominator += isset($cell[$key_denominator]) ? $cell[$key_denominator] : 0;
                    $total_numerator += isset($cell['count']) ? $cell['count'] : 0;
                    $row_data[] = $cellValue;
                }
                $avg = $total_denominator ? round($total_numerator / $total_denominator * 100, 1) : 0;
                $row_data[] = $avg;
                fputcsv($f, $row_data);
            }
        } else if ($export_func == 'all_panels_rmk') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'ResistanceDistribution', $current_date, '', 'csv');
            tr_pdd_header('csv', $outfile);
            if ($cur_name_filter_panel == "All Panels") {
                $cur_name_filter_panel = "All " . $cur_name_panel . " Panels";
            }
            fputcsv($f, ["igniteABX Resistance Markers " . $view_mode_rm . " Trends Report"]);
            fputcsv($f, ['Report Parameters']);
            fputcsv($f, ['Time Period:', $period_export]);
            fputcsv($f, ['Clinic Filter:', $cur_name_clinic]);
            fputcsv($f, ['Panel Type:', $cur_name_filter_panel]);
            fputcsv($f, ['View Mode:', $view_mode_rm . ': ' . $sub_title_rm]);
            fputcsv($f, ['Generated:', $generated_on]);

            fputcsv($f, [""]);
            fputcsv($f, ["Resistance Markers Prevalence Trends"]);
            $headers = ["Resistance Marker"];
            $rows = $table_marker['rows'];

            $months = $table_marker['columns'];
            foreach ($months as $m) {
                $headers[] = $m;
            }
            $headers[] = "Average";
            fputcsv($f, $headers);

            foreach ($rows as $index => $row) {
                $total_denominator = 0;
                $total_numerator = 0;
                $row_data = [$row['name']];
                foreach ($months as $key => $m) {
                    $cell = isset($row[$key]) ? (array)$row[$key] : [];
                    $key_value = $prevalence_rmk == '0' ? 'value' : ($prevalence_rmk == 'all' ? 'value_all' : 'value_pos');
                    $cellValue = isset($cell[$key_value]) ? $cell[$key_value] : '0';
                    $key_denominator = $prevalence_rmk == '0' ? 'total' : ($prevalence_rmk == 'all' ? 'total_sample' : 'total_positive');
                    $total_denominator += isset($cell[$key_denominator]) ? $cell[$key_denominator] : 0;
                    $total_numerator += isset($cell['count']) ? $cell['count'] : 0;
                    $row_data[] = $cellValue;
                }
                $avg = $total_denominator ? round($total_numerator / $total_denominator * 100, 1) : 0;
                $row_data[] = $avg;
                fputcsv($f, $row_data);
            }
        } else if ($export_func == 'genotypic_antibiogram') {
            $outfile = generate_report_filename('igniteABX', $t_title . 'MolecularResistanceSummary', $date_from, $date_to, 'csv');
            tr_pdd_header('csv', $outfile);
            $headers = ["Pathogen", "n (samples)"];
            foreach (($genotypic_antibiogram['columns'] ?? array()) as $column) {
                $headers[] = $column;
            }
            fputcsv($f, $headers);
            foreach (($genotypic_antibiogram['rows'] ?? array()) as $row) {
                $row_data = array($row['full_name'] ?? $row['name'], $row['samples']);
                foreach (($genotypic_antibiogram['columns'] ?? array()) as $column) {
                    $cell = $row['values'][$column] ?? null;
                    $row_data[] = $cell && !empty($cell['applicable']) ? $cell['resistant'] . '%' : '-';
                }
                fputcsv($f, $row_data);
            }
        } else {
            // Export full report CSV
            if ($single_panel_name != "") {
                $name_report = preg_replace('/[^A-Za-z0-9\.\-_]/', '', $single_panel_name);
            } else {
                if ($cur_name_filter_panel == "All Panels")
                    $name_report = $tab_title . "Panels";
                else
                    $name_report = preg_replace('/[^A-Za-z0-9\.\-_]/', '', $cur_name_filter_panel);;
            }
            $outfile = generate_report_filename('igniteABX', $name_report, $date_from, $date_to, 'csv');
            tr_pdd_header('csv', $outfile);

            // EXECUTIVE SUMMARY
            fputcsv($f, ["igniteABX Report Export"]);
            fputcsv($f, ['Generated:', $generated_on]);
            fputcsv($f, [' ']);
            fputcsv($f, ['FILTER SETTINGS']);
            fputcsv($f, ['Period:', $period_export]);
            fputcsv($f, ['Clinic:', $cur_name_clinic]);
            fputcsv($f, ['Panel:', $cur_name_filter_panel]);
            fputcsv($f, [""]);

            fputcsv($f, ["EXECUTIVE SUMMARY"]);
            fputcsv($f, ['Total Samples:', $numtestsofm]);
            fputcsv($f, ['Positive Results:', $total_p]);
            fputcsv($f, ['Negative Results:', $total_n]);
            fputcsv($f, ['Positivity Rate:', $pcpos . '%']);
            fputcsv($f, [""]);

            // VOLUME TRENDS BY MONTH
            fputcsv($f, ["VOLUME TRENDS BY MONTH"]);
            fputcsv($f, ["Month", "Total", "Negative", "Positive", "Positivity Rate"]);

            $sum_samples = 0;
            $sum_neg = 0;
            $sum_pos = 0;
            $count_months = !empty($chart2->monthdata) && is_array($chart2->monthdata) ? count($chart2->monthdata) : 0;

            foreach ($chart2->monthdata as $index => $row) {
                $sum_samples += $row[5];
                $sum_neg     += $row[2];
                $sum_pos     += $row[1];

                $month = $row[0];
                $negative_count = $row[2];
                $positive_count = $row[1];
                $total = $row[5];
                $positive_rate = $row[3];
                fputcsv($f, [$month, $total, $negative_count, $positive_count, $positive_rate . '%']);
            }

            // Tính AVERAGE
            $avg_samples = $count_months > 0 ? round($sum_samples / $count_months) : 0;
            $avg_neg     = $count_months > 0 ? round($sum_neg / $count_months) : 0;
            $avg_pos     = $count_months > 0 ? round($sum_pos / $count_months) : 0;

            // Average positivity rate
            $avg_rate = $avg_samples > 0 ? round(($avg_pos / $avg_samples) * 100, 1) : 0;
            fputcsv($f, ["Average", $avg_samples, $avg_neg, $avg_pos, $avg_rate . '%']);

            // PATHOGEN DISTRIBUTION
            fputcsv($f, [""]);
            fputcsv($f, ["PATHOGEN DISTRIBUTION - CURRENT PERIOD"]);
            fputcsv($f, ["Total Pathogens Detected", $chart3->chartTotal]);
            fputcsv($f, [""]);
            fputcsv($f, ["Pathogen", "Count", "Percentage"]);
            foreach ($chart3->data as $index => $row) {
                if ($index == 0) continue;
                fputcsv($f, [$row[0], $row[1], $row[2] . '%']);
            }

            // PATHOGEN DETECTION TRENDS
            fputcsv($f, [""]);
            fputcsv($f, ["PATHOGEN DETECTION TRENDS (6 MONTHS)"]);
            fputcsv($f, [$view_mode_path . ' View : ' . $sub_title_path]);
            $headers = ["Pathogen"];
            if ($filepath_save_csv != '') {
                $rows = $table_pathogens->rows;
                $months = $table_pathogens->columns;
            } else {
                $rows = $table_pathogens['rows'];
                $months = $table_pathogens['columns'];
            }
            foreach ($months as $m) {
                $headers[] = $m;
            }
            $headers[] = "Average";
            fputcsv($f, $headers);

            foreach ($rows as $index => $row) {
                $total_denominator = 0;
                $total_numerator = 0;
                $row_data = [$row['name']];
                foreach ($months as $key => $m) {
                    $cell = isset($row[$key]) ? (array)$row[$key] : [];
                    $key_value = $prevalence_pathogen == '0' ? 'value' : ($prevalence_pathogen == 'all' ? 'value_all' : 'value_pos');
                    $cellValue = isset($cell[$key_value]) ? $cell[$key_value] : 0;
                    $key_denominator = $prevalence_pathogen == '0' ? 'total' : ($prevalence_pathogen == 'all' ? 'total_sample' : 'total_positive');
                    $total_denominator += isset($cell[$key_denominator]) ? $cell[$key_denominator] : 0;
                    $total_numerator += isset($cell['count']) ? $cell['count'] : 0;
                    $row_data[] = $cellValue;
                }
                $avg = $total_denominator ? round($total_numerator / $total_denominator * 100, 1) : 0;
                $row_data[] = $avg;
                fputcsv($f, $row_data);
            }

            // ANTIBIOTIC RESISTANCE MARKERS
            fputcsv($f, [""]);
            fputcsv($f, ["ANTIBIOTIC RESISTANCE MARKERS - CURRENT PERIOD"]);
            fputcsv($f, ["Total Resistance Markers Detected", $chart4->chartTotal]);
            fputcsv($f, [""]);
            fputcsv($f, ["Resistance Marker", "Count", "Percentage"]);
            foreach ($chart4->data as $index => $row) {
                if ($index == 0) continue;
                if ($chart4->chartTotal) {
                    $percentage = round($row[1] / $chart4->chartTotal * 100, 1) . '%';
                } else {
                    $percentage = '0%';
                }
                fputcsv($f, [$row[0], $row[1], $percentage]);
            }

            // RESISTANCE MARKER TRENDS
            fputcsv($f, [""]);
            fputcsv($f, ["RESISTANCE MARKER TRENDS (6 MONTHS)"]);
            fputcsv($f, [$view_mode_rm . ' View : ' . $sub_title_rm]);
            $headers = ["Resistance Marker"];

            if ($filepath_save_csv != '') {
                $rows = $table_marker->rows;
                $months = $table_marker->columns;
            } else {
                $rows = $table_marker['rows'];
                $months = $table_marker['columns'];
            }
            foreach ($months as $m) {
                $headers[] = $m;
            }
            $headers[] = "Average";
            fputcsv($f, $headers);

            foreach ($rows as $index => $row) {
                $total_denominator = 0;
                $total_numerator = 0;
                $row_data = [$row['name']];
                foreach ($months as $key => $m) {
                    $cell = isset($row[$key]) ? (array)$row[$key] : [];
                    $key_value = $prevalence_rmk == '0' ? 'value' : ($prevalence_rmk == 'all' ? 'value_all' : 'value_pos');
                    $cellValue = isset($cell[$key_value]) ? $cell[$key_value] : '0';
                    $key_denominator = $prevalence_rmk == '0' ? 'total' : ($prevalence_rmk == 'all' ? 'total_sample' : 'total_positive');
                    $total_denominator += isset($cell[$key_denominator]) ? $cell[$key_denominator] : 0;
                    $total_numerator += isset($cell['count']) ? $cell['count'] : 0;
                    $row_data[] = $cellValue;
                }
                $avg = $total_denominator ? round($total_numerator / $total_denominator * 100, 1) : 0;
                $row_data[] = $avg;
                fputcsv($f, $row_data);
            }

            // POLYMICROBIAL DETECTIONS
            fputcsv($f, [""]);
            fputcsv($f, ["POLYMICROBIAL DETECTIONS"]);
            fputcsv($f, ["Pathogen count per specimen."]);
            fputcsv($f, ["Total Positive Infections Analyzed", $chart5->chartTotal]);
            fputcsv($f, [""]);
            fputcsv($f, ["Pathogen Count", "Infections", "Percentage"]);
            foreach ($chart5->data as $index => $row) {
                if ($index == 0) continue;
                if ($chart5->chartTotal) {
                    $percentage = round($row[1] / $chart5->chartTotal * 100, 1) . '%';
                } else {
                    $percentage = '0%';
                }
                fputcsv($f, [$row[0], $row[1], $percentage]);
            }

            // RESISTANCE MARKER DISTRIBUTION
            fputcsv($f, [""]);
            fputcsv($f, ["RESISTANCE MARKER DISTRIBUTION"]);
            fputcsv($f, ["Resistance marker count per specimen."]);
            fputcsv($f, ["Total Samples with Resistance Markers", $chart6->chartTotal]);
            fputcsv($f, [""]);
            fputcsv($f, ["Resistance Marker Count", "Samples", "Percentage"]);
            foreach ($chart6->data as $index => $row) {
                if ($index == 0) continue;
                if ($chart6->chartTotal) {
                    $percentage = round($row[1] / $chart6->chartTotal * 100, 1) . '%';
                } else {
                    $percentage = '0%';
                }
                fputcsv($f, [$row[0], $row[1], $percentage]);
            }

            if ($enable_genotypic_antibiogram && !empty($genotypic_antibiogram['rows']) && !empty($genotypic_antibiogram['columns'])) {
                fputcsv($f, [""]);
                fputcsv($f, ["MOLECULAR RESISTANCE SUMMARY"]);
                fputcsv($f, ["Pathogen-Resistance Marker Co-Detection Rates"]);
                $headers = ["Pathogen", "n (samples)"];
                foreach ($genotypic_antibiogram['columns'] as $column) {
                    $headers[] = $column;
                }
                fputcsv($f, $headers);
                foreach ($genotypic_antibiogram['rows'] as $row) {
                    $row_data = array($row['full_name'] ?? $row['name'], $row['samples']);
                    foreach ($genotypic_antibiogram['columns'] as $column) {
                        $cell = $row['values'][$column] ?? null;
                        $row_data[] = $cell && !empty($cell['applicable']) ? $cell['resistant'] . '%' : '-';
                    }
                    fputcsv($f, $row_data);
                }
            }

            // CRITICAL FINDINGS
            fputcsv($f, [""]);
            fputcsv($f, ["CRITICAL FINDINGS"]);
            $most_common = '';
            $most_common_count = 0;

            foreach ($critical_total as $type => $count) {
                if ($count > $most_common_count) {
                    $most_common = $type;
                    $most_common_count = $count;
                }
            }

            $totals = $critical_findings_summary['total_summary'];
            $total_clinic = 0;
            foreach ($critical_findings_summary['table'] as $item) {
                if (intval($item['data']['Total']) > 0)
                    $total_clinic += 1;
            }

            fputcsv($f, ["Clinic", "MRSA", "VRE", "ESBL", "CRE", "MDR AB", "MDR PA", "Total"]);
            foreach ($critical_findings_summary['table'] as $item) {
                $row = $item['data'];
                if (intval($row['Total']) == 0) continue;
                fputcsv($f, [
                    $item['clinic'],
                    $row['MRSA'],
                    $row['VRE'],
                    $row['ESBL'],
                    $row['CRE'],
                    $row['MDR_AB'],
                    $row['MDR_PA'],
                    $row['Total']
                ]);
            }
            fputcsv($f, [
                "Total",
                $totals['MRSA'],
                $totals['VRE'],
                $totals['ESBL'],
                $totals['CRE'],
                $totals['MDR_AB'],
                $totals['MDR_PA'],
                $critical_findings_summary['total_all']
            ]);
        }

        fclose($f);
        if ($filepath_save_csv == '') exit;
    }
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
    $csv_out_file = basename($outfile);
    return;
} else {
    // Return JSON cho các request thông thường (không phải export)
    $results = [];
    if (isset($chart))
        $results['chart1'] = $chart;
    if (isset($chart2))
        $results['chart2'] = $chart2;
    if (isset($chart3))
        $results['pathogens_chart'] = $chart3;
    if (isset($table_pathogens))
        $results['pathogens_table'] = $table_pathogens;
    if (isset($chart4))
        $results['marker_chart'] = $chart4;
    if (isset($table_marker))
        $results['marker_table'] = $table_marker;
    if (isset($chart5))
        $results['polymicrobial_chart'] = $chart5;
    if (isset($chart6))
        $results['mdr_chart'] = $chart6;
    if ($enable_genotypic_antibiogram && isset($genotypic_antibiogram))
        $results['genotypic_antibiogram'] = $genotypic_antibiogram;
    if (isset($critical_findings_summary))
        $results['critical_findings_summary'] = $critical_findings_summary;


    $results['date_from'] = $date_from;
    $results['date_to'] = $date_to;

    echo json_encode($results);
}
