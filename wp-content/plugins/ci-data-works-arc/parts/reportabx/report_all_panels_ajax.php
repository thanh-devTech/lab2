<?php
global $wpdb;


$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';
$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_tab = isset($_REQUEST['export_tab']) ? $_REQUEST['export_tab'] : '';
$sub_ajax = isset($_REQUEST['sub_ajax']) ? $_REQUEST['sub_ajax'] : '';

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}


$where       = ' ba.is_saved = 0 ' . $conditions['where'];
$where_group = array();
foreach ($main_abx_group_gids as $gid) {
    $where_group[] = " FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)  ";
}
$where_group = implode(' or ', $where_group);

$where .= " and ( {$where_group} ) ";

$where_clinic = '';
if ($c_cid > 0) {
    $where_clinic = " AND ba.cli_ID = " . intval($c_cid);
}

if ($sub_ajax == 'AllTestResult' || $sub_ajax == 'all' || $export_func != "") {
    include "report_all_panels_ajax_allTestResult.php";
}

if ($sub_ajax == 'VolumeTrend' || $sub_ajax == 'all' || $export_func != "") {
    include "report_all_panels_ajax_volumeTrend.php";
}

//Clinic Activity Overview
if ($sub_ajax == 'ActivityOverview' || $sub_ajax == 'all' || $export_func != "") {
    include "report_all_panels_ajax_activityOverview.php";
}

// Critical Findings Summary
if ($sub_ajax == 'CriticalFindings' || $sub_ajax == 'all' || $export_func != "") {
    include "report_all_panels_ajax_criticalFindings.php";
}

$results = [];
if (isset($chartDataPi))
    $results['chart'] = $chartDataPi;
if (isset($chartDataBar))
    $results['chartBar'] = $chartDataBar;
if (isset($chartDataLine))
    $results['chartLine'] = $chartDataLine;
if (isset($summary_data))
    $results['table'] = $summary_data;
if (isset($total_samples))
    $results['total_samples'] = $total_samples;
if (isset($total_positive))
    $results['total_positive'] = $total_positive;
if (isset($clinic_activity_overview))
    $results['clinic_activity_overview'] = $clinic_activity_overview;
if (isset($critical_findings_summary))
    $results['critical_findings_summary'] = $critical_findings_summary;

//Export
if ($export_type == 'pdf' || $filepath_save_pdf != '') {
    $current_lab_name = CI_Base_Lab::get_current_lab_name();
    $orientation = 'portrait';
    $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
    $period_export = ($date_from == $date_to) ? 'Today' : $date_from . " to " . $date_to;
    ob_start();
    if ($export_func == 'all_test_type') {
        require TRCPDD_PATH . 'parts/reportabx/report_all_panels_volume_trend_pdf.php';
        $html = ob_get_clean();
        $outfile = generate_report_filename('igniteABX', $view_by == 'type' ? 'TestType' : 'TestPanel' . '&Positivity', $date_from, $date_to);
    } else if ($export_func == 'all_panels_positivity_rate_trends') {
        require TRCPDD_PATH . 'parts/reportabx/report_all_panels_positivity_rate_trends_pdf.php';
        $html = ob_get_clean();
        $outfile = generate_report_filename('igniteABX', 'PositivityTrends', date_i18n('Ymd'));
    } else if ($export_func == 'all_panels_clinic_activity') {
        require TRCPDD_PATH . 'parts/reportabx/report_all_panels_clinic_activity_pdf.php';
        $html = ob_get_clean();
        $outfile = generate_report_filename('igniteABX', 'ClinicActivity', $date_from, $date_to);
    } else if ($export_func == 'all_panels_critical_findings') {
        $outfile = generate_report_filename('igniteABX', 'CriticalFindings', $date_from, $date_to);
        require TRCPDD_PATH . 'parts/reportabx/report_all_panels_critical_findings_pdf.php';
        $html = ob_get_clean();
    } else {
        $outfile = generate_report_filename('igniteABX', ($view_by == 'type' ? 'AllTypes' : 'AllPanels'), $date_from, $date_to);
        require TRCPDD_PATH . 'parts/reportabx/report_all_panels_pdf.php';
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
        trpdd_log_user(get_current_user_id(), 'download_report', 'Ignite ABX Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
        exit;
    }
}

if ($export_type === 'csv' || $filepath_save_csv != '') {
    $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
    $period_export = ($date_from == $date_to) ? 'Today' : $date_from . " to " . $date_to;
    if ($filepath_save_csv != '')
        $f = fopen($filepath_save_csv, 'w');
    else
        $f = fopen('php://output', 'w');

    if ($export_func == 'all_test_type') {
        if ($view_by == 'type') {
            $outfile = generate_report_filename('igniteABX', 'TestType&Positivity', $date_from, $date_to, 'csv');
        } else {
            $outfile = generate_report_filename('igniteABX', 'PanelType&Positivity', $date_from, $date_to, 'csv');
        }
        tr_pdd_header('csv', $outfile);


        if ($view_by == 'type') {
            fputcsv($f, ["Test Type & Positivity Report"]);
            fputcsv($f, [" "]);
            fputcsv($f, ["Test Type", "Sample Count", "Percentage", "Positivity Rate", "Positive Results", "\t\t\t\t\t\t"]);
            foreach ($summary_data as $row) {
                $percentage = $row['total'] > 0 ? round($row['total'] / $total_samples * 100, 1) : 0;
                fputcsv($f, [$row['title'], $row['total'], $percentage . '%', $row['positive_pc'] . '%',  $row['positive'],]);
            }
        } else {
            fputcsv($f, ["Test Panel & Positivity Report"]);
            fputcsv($f, [" "]);
            fputcsv($f, ["Panel Name", "Abbreviation", "Sample Count", "Percentage", "Positivity Rate", "Positive Results", "\t\t\t\t\t\t"]);
            foreach ($summary_data as $row) {
                $percentage = $row['total'] > 0 ? round($row['total'] / $total_samples * 100, 1) : 0;
                fputcsv($f, [$row['title'], strtoupper($row['abbreviation']), $row['total'], $percentage . '%', $row['positive'], $row['positive_pc'] . '%']);
            }
        }
    } else if ($export_func == 'all_panels_positivity_rate_trends') {
        $outfile = generate_report_filename('igniteABX', 'PositivityTrends', date_i18n('Ymd'), '', 'csv');
        tr_pdd_header('csv', $outfile);


        if ($view_by == 'type') {
            fputcsv($f, ["Positivity Rate Trends - By Type"]);
        } else {
            fputcsv($f, ["Positivity Rate Trends - By Panel"]);
        }
        fputcsv($f, [" "]);
        $row_head = [];
        $row_head[] = 'Month';
        foreach ($tabs as $tabkey => $tabdata) {
            if (in_array($tabkey, ['all', 'uyr', 'review', 'contact']))
                continue;
            if ($view_by == 'type') {
                $row_head[] = $tabdata['title'];
            } else {
                $gp_ids = $tabdata['groupids'];
                if (empty($gp_ids)) continue;
                foreach ($gp_ids as $gid) {
                    $group_name = $groups_mapping[$gid]['short_name'] ?? $gid;
                    $row_head[] = $group_name;
                }
            }
        }
        $row_head[] = "\t\t\t\t\t\t";
        fputcsv($f, $row_head);
        foreach ($chartDataLine['labels'] as $index => $month) {
            $row = [$month];
            foreach ($chartDataLine['datasets'] as $dataset) {
                $value = $dataset['data'][$index];
                $row[] = $value > 0 ? number_format($value, 1) . '%' : '-';
            }
            fputcsv($f, $row);
        }
    } else if ($export_func == 'all_panels_clinic_activity') {
        $outfile = generate_report_filename('igniteABX', 'ClinicActivity', $date_from, $date_to, 'csv');
        tr_pdd_header('csv', $outfile);


        if ($view_by == 'type')
            fputcsv($f, ["Clinic Activity Report - By Type"]);
        else
            fputcsv($f, ["Clinic Activity Report - By Panel"]);


        fputcsv($f, [" "]);

        $row_head = [];
        $row_head[] = 'Clinic';
        $row_head[] = 'Total Volume';
        $row_head[] = 'Positivity Rate';
        $row_head[''] = 'Top Panel';
        foreach ($tabs as $tabkey => $tabdata) {
            if (in_array($tabkey, ['all', 'uyr', 'review', 'contact']))
                continue;
            if ($view_by == 'type') {
                $row_head[] = $tabdata['title'];
            } else {
                $gp_ids = $tabdata['groupids'];
                if (empty($gp_ids)) continue;
                foreach ($gp_ids as $gid) {
                    $group_name = $groups_mapping[$gid]['short_name'] ?? $gid;
                    $row_head[] = $group_name;
                }
            }
        }
        $row_head[] = "\t\t\t\t\t\t";
        fputcsv($f, $row_head);

        foreach ($clinic_summary as $i => $row) {
            $panels = $row['panels'];
            $row_table = [];
            $row_table[] = $row['clinic'];
            $row_table[] = $row['total_volume'];
            $row_table[] = $row['positivity_rate'];
            $row_table[] = $row['top_panel'];
            foreach ($panels as $key => $value) {
                $row_table[] = $value;
            }
            fputcsv($f, $row_table);
        }
    } else if ($export_func == 'all_panels_critical_findings') {
        $outfile = generate_report_filename('igniteABX', 'CriticalFindings', $date_from, $date_to, 'csv');
        tr_pdd_header('csv', $outfile);


        fputcsv($f, [" "]);

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

        fputcsv($f, ["Clinic", "MRSA", "VRE", "ESBL", "CRE", "MDR AB", "MDR PA", "Total", "\t\t\t\t\t\t"]);
        foreach ($critical_findings_summary['table'] as $item) {
            $row = $item['data'];
            if (intval($row['Total']) == 0)
                continue;
            fputcsv($f, [$item['clinic'], $row['MRSA'], $row['VRE'], $row['ESBL'], $row['CRE'], $row['MDR_AB'], $row['MDR_PA'], $row['Total']]);
        }
        fputcsv($f, ["Total", $totals['MRSA'], $totals['VRE'], $totals['ESBL'], $totals['CRE'], $totals['MDR_AB'], $totals['MDR_PA'], $critical_findings_summary['total_all']]);
    } else {
        $outfile = generate_report_filename('igniteABX', ($view_by == 'type' ? 'AllTypes' : 'AllPanels'), $date_from, $date_to, 'csv');
        if ($filepath_save_csv == '') tr_pdd_header('csv', $outfile);


        // ==== All Panels ====
        fputcsv($f, ["igniteABX Report Export"]);
        fputcsv($f, ['Generated:', $generated_on]);
        fputcsv($f, ['Panel:', $cur_name_panel]);
        fputcsv($f, ['Period:', $period_export]);
        fputcsv($f, ['Clinic:', $cur_name_clinic]);
        if ($view_by == 'type')
            fputcsv($f, ['View Mode', 'All Types']);
        else
            fputcsv($f, ['View Mode', 'All Panels']);
        fputcsv($f, [""]);
        fputcsv($f, ["EXECUTIVE SUMMARY"]);
        fputcsv($f, ['Total Samples:', $total_samples]);
        fputcsv($f, ['Overall Positivity Rate:', $total_positive_rate . '%']);
        fputcsv($f, ['Positive Results:', $total_positive]);
        fputcsv($f, ['Negative Results:', $total_negative]);
        fputcsv($f, ['Critical Findings:', $total_critical]);
        fputcsv($f, ['Active Clinics:', $total_clinics]);
        fputcsv($f, [""]);

        fputcsv($f, [$view_by == 'panel' ? "PANEL TYPE DISTRIBUTION" : "TEST TYPE DISTRIBUTION"]);
        fputcsv($f, [$view_by == 'panel' ? "Panel" : "Test Type", "Sample Count", "Percentage", "Positive", "Negative", "Positivity Rate"]);
        foreach ($summary_data as $row) {
            $percentage = $row['total'] > 0 ? round($row['total'] / $total_samples * 100, 1) : 0;
            fputcsv($f, [$row['title'], $row['total'], $percentage . '%', $row['positive'],  $row['negative'], $row['positive_pc'] . '%']);
        }

        fputcsv($f, [""]);
        fputcsv($f, ["VOLUME TRENDS (14-DAY)"]);
        fputcsv($f, ["Date", "Negative", "Positive", "Total", "Positivity Rate"]);

        foreach ($days_daily as $row) {
            $n = $row['negative'];
            $p = $row['positive'];
            $t = intval($n) + intval($p);
            $rate_p = $t > 0 ? ($row['positive'] / $t) * 100 : 0;
            fputcsv($f, [$row['label'], $n, $p, $t, round($rate_p, 1) . '%']);
        }

        fputcsv($f, [""]);
        fputcsv($f, ["POSITIVITY RATE TRENDS (6-MONTH)"]);
        $headers = ["Month"];
        foreach ($chartDataLine['datasets'] as $dataset) {
            $headers[] = $dataset['label'];
        }
        fputcsv($f, $headers);

        foreach ($chartDataLine['labels'] as $index => $month) {
            $row = [$month];
            foreach ($chartDataLine['datasets'] as $dataset) {
                $value = $dataset['data'][$index];
                $row[] = $value > 0 ? number_format($value, 1) . '%' : '-';
            }
            fputcsv($f, $row);
        }
        fputcsv($f, [""]);

        fputcsv($f, ["CLINIC ACTIVITY"]);
        $header_ca = [];
        $header_ca[] = "Clinic";
        $header_ca[] = "Total Volume";
        $header_ca[] = "Positivity Rate";
        $header_ca[] = "Top Panel";
        foreach ($tabs as $tabkey => $tabdata) {
            if (in_array($tabkey, ['all', 'uyr', 'review', 'contact']))
                continue;
            if ($view_by == 'type') {
                $header_ca[] = $tabdata['title'];
            } else {
                $gp_ids = $tabdata['groupids'];
                if (empty($gp_ids)) continue;
                foreach ($gp_ids as $gid) {
                    $group_name = $groups_mapping[$gid]['short_name'] ?? $gid;
                    $header_ca[] = $group_name;
                }
            }
        }
        fputcsv($f, $header_ca);

        foreach ($clinic_summary as $c) {
            $panels = $c['panels'];
            $row = [];
            $row[] = $c['clinic'];
            $row[] = $c['total_volume'];
            $row[] = $c['positivity_rate'] . '%';
            $row[] = $c['top_panel'];
            foreach ($panels as $key => $value) {
                $row[] = $value;
            }
            fputcsv($f, $row);
        }

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
            if (intval($row['Total']) == 0)
                continue;
            fputcsv($f, [$item['clinic'], $row['MRSA'], $row['VRE'], $row['ESBL'], $row['CRE'], $row['MDR_AB'], $row['MDR_PA'], $row['Total']]);
        }
        fputcsv($f, ["Total", $totals['MRSA'], $totals['VRE'], $totals['ESBL'], $totals['CRE'], $totals['MDR_AB'], $totals['MDR_PA'], $critical_findings_summary['total_all']]);
    }
    fclose($f);
    if ($filepath_save_csv == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Ignite ABX Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
        exit;
    }
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
    $csv_out_file = basename($outfile);
    return;
} else {
    echo json_encode($results);
}
