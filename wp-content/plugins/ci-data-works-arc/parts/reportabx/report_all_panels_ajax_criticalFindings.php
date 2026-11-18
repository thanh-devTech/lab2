<?php
$category_cf = CI_ABX_Report::get_critical_findings_categories($main_abx_group_gids);


$date_from_compare  = date_i18n('Y-m-01', strtotime('-1 month', strtotime($date_from)));
$date_to_compare    = date_i18n('Y-m-t', strtotime($date_from_compare));

$current_data       = CI_ABX_Report::count_critical_markers($conditions, $category_cf, $where_group, $date_from, $date_to);
$compare_data       = CI_ABX_Report::count_critical_markers($conditions, $category_cf, $where_group, $date_from_compare, $date_to_compare);

$critical_summary   = [];
$critical_total     = array_fill_keys(array_keys($category_cf), 0);
$total_critical     = 0;

if (isset($c_cid) && $c_cid > 0) {
    $filtered_clinics = array_filter($clinics, function ($clinic) use ($c_cid) {
        return $clinic->cID == $c_cid;
    });
} else {
    $filtered_clinics = $clinics;
}

foreach ($filtered_clinics as $clinic) {
    $clinic_name = $clinic->cli_clinic;

    if (!isset($current_data[$clinic->cID]) && !isset($compare_data[$clinic->cID])) {
        continue;
    }
    $current = isset($current_data[$clinic->cID]) ? $current_data[$clinic->cID] : [];
    $compare = isset($compare_data[$clinic->cID]) ? $compare_data[$clinic->cID] : [];


    $trend = [];
    $current['Total'] = 0;
    $compare['Total'] = 0;

    foreach ($category_cf as $cf_name => $cf_data) {

        if (!isset($current[$cf_name])) {
            $current[$cf_name] = 0;
        }
        if (!isset($compare[$cf_name])) {
            $compare[$cf_name] = 0;
        }


        $current_count  = $current[$cf_name];
        $compare_count  = $compare[$cf_name];



        $current['Total'] += $current_count;
        $compare['Total'] += $compare_count;

        $diff           = $current_count - $compare_count;

        $trend[$cf_name]['arrow'] = ($diff > 0) ? 'up' : (($diff < 0) ? 'down' : 'equal');
        $trend[$cf_name]['diff'] = $diff;
        $critical_total[$cf_name] += $current_count;
    }

    $diff = $current['Total'] - $compare['Total'];
    $trend['Total']['arrow'] = ($diff > 0) ? 'up' : (($diff < 0) ? 'down' : 'equal');
    $trend['Total']['diff'] = $diff;
    $total_critical += $current['Total'];

    if ($current['Total'] > 0 || $compare['Total'] > 0) {
        $critical_summary[] = [
            'clinic'    => $clinic_name,
            'data'      => $current,
            'compare'   => $compare,
            'trend'     => $trend
        ];
    }
}

// --- Kết quả cuối cùng
$critical_findings_summary = [
    'table' => $critical_summary,
    'total_summary' => $critical_total,
    'total_all' => $total_critical
];
