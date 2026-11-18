<?php
$total_samples = 0;
$total_positive = 0;
$total_positive_rate = 0;
$total_negative = 0;
$summary_data = [];
$summary_6months = [];
$chart_monthly = [];
$chart_daily = [];
$thismonthstamp = strtotime(date_i18n('Y-m-01'));
$fieldpositive = 're.has_uti > 0';
if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $fieldpositive .= ' or re.has_pos > 0';
}
if (!function_exists('buildGroupWhereClause')) {

    function buildGroupWhereClause(array $groupids): string
    {
        $cons = array_map(function ($gid) {
            return "FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)";
        }, $groupids);

        return implode(' OR ', $cons);
    }
}

if (!function_exists('executeQuery')) {
    function executeQuery(string $sql, string $type = 'row', int $cache_seconds = 0)
    {
        global $wpdb;

        $use_cache = class_exists('Tr_Cache_Query') && !isset($_GET['nocache']);

        if ($use_cache) {
            $cache = new Tr_Cache_Query('reportabx');
            return $type === 'row'
                ? $cache->get_row($sql, OBJECT, 0, $cache_seconds)
                : $cache->get_results($sql, OBJECT, $cache_seconds);
        }

        return $type === 'row'
            ? $wpdb->get_row($sql, OBJECT)
            : $wpdb->get_results($sql);
    }
}
if (!function_exists('calculateStats')) {
    function calculateStats($rs): array
    {
        $total = intval($rs->total);
        $positive = intval($rs->total_positive);
        $negative = $total - $positive;
        $positive_pc = $total > 0 ? round($positive / $total * 100, 1) : 0;

        return compact('total', 'positive', 'negative', 'positive_pc');
    }
}
if (!function_exists('buildTotalQuery')) {
    function buildTotalQuery(
        $user_conditions,
        string $where_conditions,
        string $date_from,
        string $date_to,
        string $where_clinic,
        string $fieldpositive
    ): string {

        $where       = ' ba.is_saved = 0 ' . $user_conditions['where'];

        return "
        SELECT COUNT(*) AS total, SUM(is_positive) AS total_positive
        FROM (
            SELECT IF(re.estatus='C' AND ({$fieldpositive}), 1, 0) AS is_positive
            FROM ci_batches_pcr ba
            JOIN ci_reports_pcr re ON re.patient_id = ba.assen
            WHERE {$where}
              AND " . trpdd_abx_query_date_range($date_from, $date_to) . "
              AND ({$where_conditions})
              {$where_clinic}
        ) AS rs";
    }
}




foreach ($tabs as $tabkey => $tabdata) {
    if (in_array($tabkey, ['all']))
        continue;
    $groupids = $tabdata['groupids'];
    if (empty($groupids))
        continue;
    $tabdata['where_group'] = buildGroupWhereClause($groupids);

    if ($view_by == 'type') {
        $sql = buildTotalQuery($conditions, $tabdata['where_group'], $date_from, $date_to, $where_clinic, $fieldpositive);
        $rs = executeQuery($sql, 'row', $cache_query_seconds);
        $stats = calculateStats($rs);

        $summary_data[] = [
            'title' => $tabdata['title'],
            'total' => $stats['total'],
            'positive' => $stats['positive'],
            'positive_pc' => $stats['positive_pc'],
            'negative' => $stats['negative'],
            'color' => $colors_type[$tabkey]
        ];

        $total_samples += $stats['total'];
        $total_positive += $stats['positive'];
        $total_negative += $stats['negative'];
    } else {
        foreach ($groupids as $index => $gid) {
            $where_condition = "FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)";
            $sql = buildTotalQuery($conditions, $where_condition, $date_from, $date_to, $where_clinic, $fieldpositive);
            $rs = executeQuery($sql, 'row', $cache_query_seconds);
            $stats = calculateStats($rs);

            if (isset($groups_mapping[$gid]['group_color'])) {
                $group_color = $groups_mapping[$gid]['group_color'];
            }
            if (!isset($group_color)) {
                $group_color = CI_Base_Panel::generateColorVariationHSL($colors_type[$tabkey], $index, count($groupids));
            }
            $summary_data[] = [
                'title' => $groups_mapping[$gid]['name'] ?? 'Group ' . $gid,
                'abbreviation' => $tabkey,
                'total' => $stats['total'],
                'positive' => $stats['positive'],
                'positive_pc' => $stats['positive_pc'],
                'negative' => $stats['negative'],
                'color' => $group_color
            ];

            $total_samples += $stats['total'];
            $total_positive += $stats['positive'];
            $total_negative += $stats['negative'];
        }
    }

    // Dữ liệu theo ngày (Volume Trend)
    $where       = ' ba.is_saved = 0 ' . $conditions['where'];

    $sql_daily = "
        SELECT 
            " . trpdd_abx_query_field_date('%Y-%m-%d') . " AS day_label,
            COUNT(*) AS total,
            SUM(IF(re.estatus='C' AND ({$fieldpositive}), 1, 0)) AS total_positive
        FROM ci_batches_pcr ba
        JOIN ci_reports_pcr re ON re.patient_id = ba.assen
        WHERE {$where}
            AND " . trpdd_abx_query_date_range($date_from, $date_to) . "
            AND ({$tabdata['where_group']})
            {$where_clinic}
        GROUP BY day_label
        ORDER BY day_label ASC
    ";



    if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

        $cache = new Tr_Cache_Query('reportabx');
        $rows = $cache->get_results($sql_daily, OBJECT, $cache_query_seconds);
    } else {
        $rows = $wpdb->get_results($sql_daily);
    }

    if (stripos($_SERVER['HTTP_REFERER'] ?? '', 'debug3') !== false) {
        var_dump($wpdb->last_error);
        var_dump($sql_daily);
    }

    foreach ($rows as $r) {
        $day = $r->day_label;
        if (!isset($chart_daily[$day])) {
            $chart_daily[$day] = ['positive' => 0, 'negative' => 0];
        }
        $chart_daily[$day]['positive'] += intval($r->total_positive);
        $chart_daily[$day]['negative'] += intval($r->total) - intval($r->total_positive);
    }
}

$total_positive_rate = $total_samples > 0 ? round($total_positive / $total_samples * 100, 1) : 0;

for ($i = 0; $i < count($summary_data); $i++) {
    $total_p = $summary_data[$i]['total'];
    $total_pc = $total_p > 0 ? round($total_p / $total_samples * 100, 1) : 0;
    $summary_data[$i]['total_pc'] = $total_pc;
}

$days_daily = [];
$start = strtotime($date_from);
$end = strtotime($date_to);

for ($i = $start; $i <= $end; $i += 86400) {
    $day_key = date_i18n('Y-m-d', $i);
    $p = isset($chart_daily[$day_key]) ? $chart_daily[$day_key]['positive'] : 0;
    $n = isset($chart_daily[$day_key]) ? $chart_daily[$day_key]['negative'] : 0;

    $days_daily[$day_key] = [
        'label' => date_i18n('M d', $i), // hiển thị dạng "Aug 18"
        'positive' => isset($chart_daily[$day_key]) ? $chart_daily[$day_key]['positive'] : 0,
        'negative' => isset($chart_daily[$day_key]) ? $chart_daily[$day_key]['negative'] : 0
    ];
}


// 14 day
$volume_14days = [];
for ($i = 13; $i >= 0; $i--) {
    $day_key = date_i18n('Y-m-d', strtotime("-{$i} days", $thismonthstamp));
    $volume_14days[$day_key] = [
        'label' => date_i18n('M d', strtotime($day_key)), // ví dụ: Oct 13
        'positive' => isset($chart_daily[$day_key]) ? $chart_daily[$day_key]['positive'] : 0,
        'negative' => isset($chart_daily[$day_key]) ? $chart_daily[$day_key]['negative'] : 0
    ];
}

//Doughnut
$chartDataPi = [
    'labels' => array_column($summary_data, 'title'),
    'datasets' => [
        [
            'data' => array_column($summary_data, 'total'),
            'backgroundColor' => array_column($summary_data, 'color'),
        ]
    ]
];

//Stacked Bar
$chartDataBar = [
    'labels' => array_column($days_daily, 'label'),
    'datasets' => [
        [
            'label' => 'Negative',
            'data' => array_column($days_daily, 'negative'),
            'backgroundColor' => '#70d4d6'
        ],
        [
            'label' => 'Positive',
            'data' => array_column($days_daily, 'positive'),
            'backgroundColor' => '#ff5f43'
        ]
    ]
];
