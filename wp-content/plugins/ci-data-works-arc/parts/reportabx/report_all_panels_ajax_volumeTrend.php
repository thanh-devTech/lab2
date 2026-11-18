<?php
// Chart Line Trend - Luôn 6 tháng gần nhất
$chart_line_by_tab = [];

// Xác định khoảng thời gian 6 tháng gần nhất
$thismonthstamp = strtotime(date_i18n('Y-m-01'));
$mto_date = date_i18n('Y-m-t'); // cuối tháng hiện tại
$mfrom_date = date('Y-m-01', strtotime('-5 months', $thismonthstamp)); // đầu tháng của tháng cách đây 5 tháng

$fieldpositive = 're.has_uti > 0';
if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $fieldpositive .= ' or re.has_pos > 0';
}
if (!function_exists('buildGroupWhereClause')) {
    function buildGroupWhereClause(array $groupids): string
    {
        $conds = array_map(function ($gid) {
            return "FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)";
        }, $groupids);

        return implode(' OR ', $conds);
    }
}

if (!function_exists('buildRateTrendQuery')) {
    function buildRateTrendQuery($from_date, $to_date, $where_clinic, $fieldpositive, $where_conditions = "")
    {
        return "
        SELECT 
            " . trpdd_abx_query_field_date('%Y-%m') . " AS month_label,
            COUNT(*) AS total,
            SUM(IF(re.estatus='C' AND ({$fieldpositive}), 1, 0)) AS total_positive
        FROM ci_batches_pcr ba
        JOIN ci_reports_pcr re ON re.patient_id = ba.assen
        WHERE " . trpdd_abx_query_date_range($from_date, $to_date) . "
            AND ({$where_conditions})  
            {$where_clinic}
        GROUP BY month_label
        ORDER BY month_label ASC";
    }
}


foreach ($tabs as $tabkey => $tabdata) {
    if (in_array($tabkey, ['all']))
        continue;

    $groupids = $tabdata['groupids'];
    if (empty($groupids))
        continue;

    if ($view_by == 'type') {
        $tabdata['where_group'] = '(' . buildGroupWhereClause($groupids) . ')';
        $tabdata['where_group'] .= ' AND ba.is_saved = 0 ' . $conditions['where'];
        $sql_monthly_line = buildRateTrendQuery($mfrom_date, $mto_date, $where_clinic, $fieldpositive, $tabdata['where_group']);

        if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

            $cache = new Tr_Cache_Query('reportabx');
            $rows = $cache->get_results($sql_monthly_line, OBJECT, 1);
        } else {
            $rows = $wpdb->get_results($sql_monthly_line);
        }


        if (stripos($_SERVER['HTTP_REFERER'] ?? '', 'debug4') !== false) {
            var_dump($wpdb->last_error);
            var_dump($sql_monthly_line);
        }

        $dataset_values = [];

        // Tạo 6 tháng gần nhất (hiện tại và 5 tháng trước)
        for ($i = 5; $i >= 0; $i--) {
            $month_key = date('Y-m', strtotime("-{$i} months", $thismonthstamp));
            $found = false;

            foreach ($rows as $r) {
                if ($r->month_label == $month_key) {
                    $found = true;
                    $positive_pc = $r->total > 0 ? round(($r->total_positive / $r->total) * 100, 1) : 0;
                    $dataset_values[] = $positive_pc;
                    break;
                }
            }

            if (!$found)
                $dataset_values[] = 0;
        }
        $groups_data = [];

        $chart_line_by_tab[] = [
            'label' => $tabdata['title'],
            'data' => $dataset_values,
            'fill' => false,
            'tension' => 0.3,
            'borderColor' => $colors_type[$tabkey],
            'backgroundColor' => $colors_type[$tabkey],
            'bk_color' => $colors_type[$tabkey] . '30'
        ];
    } else {
        foreach ($groupids as $index => $gid) {
            $where_condition = "FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)";
            $where_condition .= ' AND ba.is_saved = 0 ' . $conditions['where'];

            $sql_monthly_line = buildRateTrendQuery($mfrom_date, $mto_date, $where_clinic, $fieldpositive, $where_condition);
            if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
                $cache = new Tr_Cache_Query('reportabx');
                $rows = $cache->get_results($sql_monthly_line, OBJECT, $cache_query_seconds);
            } else {
                $rows = $wpdb->get_results($sql_monthly_line);
            }

            if (stripos($_SERVER['HTTP_REFERER'] ?? '', 'debug4') !== false) {
                var_dump($wpdb->last_error);
                var_dump($sql_monthly_line);
            }

            $dataset_values = [];

            for ($i = 5; $i >= 0; $i--) {
                $month_key = date('Y-m', strtotime("-{$i} months", $thismonthstamp));
                $found = false;

                foreach ($rows as $r) {
                    if ($r->month_label == $month_key) {
                        $found = true;
                        $positive_pc = $r->total > 0 ? round(($r->total_positive / $r->total) * 100, 1) : 0;
                        $dataset_values[] = $positive_pc;
                        break;
                    }
                }
                if (!$found)
                    $dataset_values[] = 0;
            }
            $group_color = CI_Base_Panel::generateColorVariationHSL($colors_type[$tabkey], $index, count($groupids));

            if (isset($groups_mapping[$gid]['group_color']) && $groups_mapping[$gid]['group_color']) {
                $group_color = $groups_mapping[$gid]['group_color'];
            }
            $bk_color = $group_color . '30';

            $chart_line_by_tab[] = [
                'label' => $groups_mapping[$gid]['short_name'] ?? 'Group ' . $gid,
                'data' => $dataset_values,
                'fill' => false,
                'tension' => 0.3,
                'borderColor' => $group_color,
                'backgroundColor' => $group_color,
                'bk_color' => $bk_color
            ];
        }
    }
}

// Labels cho 6 tháng gần nhất
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = [
        'label' => date('M', strtotime("-{$i} months", $thismonthstamp))
    ];
}

$chartDataLine = [
    'labels' => array_column($months, 'label'),
    'datasets' => $chart_line_by_tab
];
