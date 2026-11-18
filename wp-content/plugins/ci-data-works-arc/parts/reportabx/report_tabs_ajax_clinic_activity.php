<?php
//Clinic Activity Overview
$clinic_activity_overview = [];
$clinic_summary = [];

$fieldpositive = 're.has_uti > 0';
if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $fieldpositive .= ' or re.has_pos > 0';
}

$clinic_data = array();
foreach ($tabs as $key => $tabdata) {
    $groupids = $tabdata['groupids'];
    if ($key == 'all' || empty($groupids))
        continue;

    $tabdata['where_group'] = [];
    foreach ($groupids as $gid) {
        $tabdata['where_group'][] = "FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)";
    }
    $tabdata['where_group'] = implode(' OR ', $tabdata['where_group']);

    $subwhere = ' ba.is_saved = 0 ' . $conditions['where'];
    $subwhere .= " AND ({$tabdata['where_group']}) ";

    $sql = "
        SELECT 
            ba.cli_ID,
            COUNT(*) AS total,
            SUM(IF(re.estatus='C' AND ({$fieldpositive}),1,0)) AS positive
        FROM ci_batches_pcr ba
        JOIN ci_reports_pcr re ON re.patient_id = ba.assen
        WHERE {$subwhere}
            AND " . trpdd_abx_query_date_range($date_from, $date_to) . "
           group by ba.cli_ID
            ";

    if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

        $cache = new Tr_Cache_Query('reportabx');
        $rows = $cache->get_results($sql, OBJECT, $cache_query_seconds);
    } else {
        $rows = $wpdb->get_results($sql, OBJECT);
    }


    foreach ($rows as $row) {
        if (!isset($clinic_data[$row->cli_ID])) {
            $clinic_data[$row->cli_ID] = [];
        }
        $clinic_data[$row->cli_ID][$key] = [
            'total' => intval($row->total),
            'positive' => intval($row->positive)
        ];
    }
}

foreach ($clinics as $clinic) {
    $cID        = intval($clinic->cID);
    $clinic_name = $clinic->cli_clinic;

    if (!isset($clinic_data[$cID])) {
        continue;
    }

    $panel_counts = [];
    $panel_counts_rate = [];

    foreach ($tabs as $key => $tabdata) {
        $groupids = $tabdata['groupids'];
        if ($key == 'all' || empty($groupids))
            continue;

        $panel_counts[$key] = $clinic_data[$cID][$key]['total'] ?? 0;
        $panel_counts_rate[$key] = $clinic_data[$cID][$key]['positive'] ?? 0;
    }

    $total_samples_volume   = array_sum($panel_counts);
    $total_positive_ao      = array_sum($panel_counts_rate);

    $positivity_rate = $total_samples_volume > 0 ? round($total_positive_ao / $total_samples_volume * 100, 1) : 0;

    $top_panel_key = array_keys($panel_counts, max($panel_counts))[0] ?? 'None';
    $top_panel = $tabs[$top_panel_key]['title'] ?? '';

    $clinic_summary[] = [
        'clinic'        => $clinic_name,
        'total_volume'  => $total_samples_volume,
        'positivity_rate' => $positivity_rate,
        'top_panel'     => $top_panel,
        'panels'        => $panel_counts
    ];
}
usort($clinic_summary, function ($a, $b) {
    return $b['total_volume'] <=> $a['total_volume'];
});

// Tổng cộng cuối bảng
$total_clinics = count($clinic_summary);
$total_all_samples = array_sum(array_column($clinic_summary, 'total_volume'));
$total_all_positive = 0;
foreach ($clinic_summary as $c) {
    $total_all_positive += $c['total_volume'] * $c['positivity_rate'] / 100;
}
$avg_positivity = $total_all_samples > 0 ? round($total_all_positive / $total_all_samples * 100, 1) : 0;

$clinic_activity_overview['total_clinics'] = $total_clinics;
$clinic_activity_overview['total_all_samples'] = $total_all_samples;
$clinic_activity_overview['clinic_summary'] = $clinic_summary;
$clinic_activity_overview['avg_positivity'] = $avg_positivity;
