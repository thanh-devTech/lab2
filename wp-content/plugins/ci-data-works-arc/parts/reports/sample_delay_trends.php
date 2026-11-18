<?php

global $wpdb, $current_role, $ci_all_btypes;
$schedule_report = new CI_Report_Schedule('delay', 'Specimen Delay Trends');
$last_item_btypes =  reset($ci_all_btypes);
ini_set('memory_limit', '1024M');
$cache_query_seconds = 3600 * 2; //2 hours

$from_date = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-1');
$to_date = !empty($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');
$sel_status = isset($_REQUEST['sel_status']) ? $_REQUEST['sel_status'] : 'all';
$sel_clinic = isset($_REQUEST['sel_clinic']) ? $_REQUEST['sel_clinic'] : 'all';
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : $last_item_btypes;
$sel_panel = isset($_REQUEST['sel_panel']) ? $_REQUEST['sel_panel'] : 'all';
$sel_bucket = isset($_REQUEST['sel_bucket']) ? $_REQUEST['sel_bucket'] : 'all';
$is_include_12 = isset($_REQUEST['is_include_12']) ? $_REQUEST['is_include_12'] : 1;
$sel_clinic_month = isset($_REQUEST['sel_clinic_month']) ? trim((string) $_REQUEST['sel_clinic_month']) : date_i18n('Y-m');

$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';
$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

if (count($ci_all_btypes) === 1 || ($btype_selected == 'pcr' && !in_array('pcr', $ci_all_btypes))) {
    $btype_selected = array_shift(array_values($ci_all_btypes));
}

$bucket_filter = array();
$bucket_filter['over_72'] = array('title' => '> 72 hours', 'txt' => '> 72h', 'class_pdf' => 'bucket-badge bucket-critical', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-red-100 text-red-800');
$bucket_filter['48_72'] = array('title' => '48-72 hours', 'txt' => '48 - 72h', 'class_pdf' => 'bucket-badge bucket-high', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-orange-100 text-orange-800');
$bucket_filter['24_48'] = array('title' => '24-48 hours', 'txt' => '24 - 48h', 'class_pdf' => 'bucket-badge bucket-moderate', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-yellow-100 text-yellow-800');
$bucket_filter['12_24'] = array('title' => '12-24 hours', 'txt' => '12 - 24h', 'class_pdf' => 'bucket-badge bucket-moderate', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-yellow-100 text-yellow-800');
$bucket_filter['under_12'] = array('title' => '< 12 hours', 'txt' => '< 12h', 'class_pdf' => 'bucket-badge bucket-normal', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-green-100 text-green-800');

$delay_bucket_palette = array(
    'not_received' => array(
        'over_72' => array('chart' => '#b42200', 'icon_bg' => '#f8e6e1'),
        '48_72' => array('chart' => '#d83d00', 'icon_bg' => '#fbe9e1'),
        '24_48' => array('chart' => '#ff5632', 'icon_bg' => '#ffe9e4'),
        '12_24' => array('chart' => '#ff9685', 'icon_bg' => '#fff1ed'),
        'under_12' => array('chart' => '#ffe0da', 'icon_bg' => '#fcf4f2'),
    ),
    'pending' => array(
        'over_72' => array('chart' => '#336567', 'icon_bg' => '#e8f1f1'),
        '48_72' => array('chart' => '#208b83', 'icon_bg' => '#e3f2f1'),
        '24_48' => array('chart' => '#07b9aa', 'icon_bg' => '#e2f7f5'),
        '12_24' => array('chart' => '#86dcd5', 'icon_bg' => '#ebf8f7'),
        'under_12' => array('chart' => '#d5f5f2', 'icon_bg' => '#f2fbfb'),
    ),
);

function trpdd_get_palette_value(array $palette, string $status_key, string $bucket_key, string $value_key): string
{
    return isset($palette[$status_key][$bucket_key][$value_key]) ? $palette[$status_key][$bucket_key][$value_key] : '';
}

$status = array();
$status['not_received'] = array('title' => 'Not Received');
$status['pending'] = array('title' => 'Pending Results');


$conditions = trpdd_get_batch_conditions(0, $btype_selected);
$where = ' is_saved = 0 ' . $conditions['where'];
$where_clinic = $conditions['where_clinic'];
if ($conditions['check_author']) {
    $where .= $conditions['where_author'];
}



//exclude rejected, archived and deleted samples
$where .= ' AND ' . tr_pdd_where_batch_rejected(false, true, $btype_selected, array('deleted', 'archived', 'cancelled'));

//only show 12 months data
$date_prev_12month = date('Y-m-01', strtotime('-11 months', strtotime(date_i18n('Y-m-01'))));
$where .= " AND ba.doc >= '{$date_prev_12month}' ";


$archive_date = tr_get_option('ci_pdf_data', 'archive_date', '');
if (!empty($archive_date) && $date_prev_12month < $archive_date) {
    $where .= " AND ba.doc > '{$archive_date}' ";
}


$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic} ORDER BY cli_clinic ASC");
foreach ($clinics_rs as $r) {
    $clinics[$r->cID] = $r;
}
$days_of_operation_lab = CI_Base_Lab::get_lab_operations();

$all_panels = array();

if (!empty($export_func) || $trajax == 'loadData') {

    function filter_status_bucket_keep_columns(array $row, string $sel_status, string $sel_bucket): array
    {
        $buckets = ['over_72', '48_72', '24_48', '12_24', 'under_12'];
        $statuses = ['not_received', 'pending'];

        // --- status được phép ---
        if ($sel_status === 'not_received') {
            $statuses = ['not_received'];
        } elseif ($sel_status === 'pending_result') {
            $statuses = ['pending'];
        }

        // --- bucket được phép ---
        if ($sel_bucket !== 'all' && in_array($sel_bucket, $buckets, true)) {
            $buckets = [$sel_bucket];
        }

        $allowed_keys = [];

        foreach ($statuses as $status) {
            foreach ($buckets as $bucket) {
                $allowed_keys[] = "{$status}_{$bucket}";
            }
        }

        // --- chỉ giữ key hợp lệ ---
        return array_intersect_key(
            $row,
            array_flip($allowed_keys)
        ) + $row; // giữ các field khác (month, ym...)
    }

    function trpdd_init_delay_counter(?array &$data): void
    {
        $keys = [
            'month_total',

            'not_received_over_72',
            'not_received_48_72',
            'not_received_24_48',
            'not_received_12_24',
            'not_received_under_12',

            'pending_over_72',
            'pending_48_72',
            'pending_24_48',
            'pending_12_24',
            'pending_under_12',
        ];

        foreach ($keys as $k) {
            if (!isset($data[$k])) {
                $data[$k] = 0;
            }
        }
    }

    function trpdd_process_sample_delay_trend_row(array &$month_data,  ?array &$clinic_data, array $row, $days_of_operation_lab): void
    {
        trpdd_init_delay_counter($month_data);
        trpdd_init_delay_counter($clinic_data);

        $month_data['month_total'] += 1;
        $clinic_data['month_total'] += 1;

        $doc_time       = $row['doc'];
        $approve_time   = $row['approve_date'];
        $received_time  =  !empty($row['spec_received']) ? $row['spec_received'] : $doc_time;

        $diff_hours = CI_Base_Lab::calculate_business_hours($doc_time, $received_time, $row['lab_ID'], $days_of_operation_lab);

        $not_received_key = 'not_received_under_12';
        if ($diff_hours >= 72) {
            $not_received_key = 'not_received_over_72';
        } elseif ($diff_hours >= 48) {
            $not_received_key = 'not_received_48_72';
        } elseif ($diff_hours >= 24) {
            $not_received_key = 'not_received_24_48';
        } elseif ($diff_hours >= 12) {
            $not_received_key = 'not_received_12_24';
        }
        $month_data[$not_received_key] += 1;
        $clinic_data[$not_received_key] += 1;

        $diff_hours = $approve_time > $received_time  ?  CI_Base_Lab::calculate_business_hours($received_time, $approve_time, $row['lab_ID'], $days_of_operation_lab) : 0;

        $pending_key = 'pending_under_12';
        if ($diff_hours >= 72) {
            $pending_key = 'pending_over_72';
        } elseif ($diff_hours >= 48) {
            $pending_key = 'pending_48_72';
        } elseif ($diff_hours >= 24) {
            $pending_key = 'pending_24_48';
        } elseif ($diff_hours >= 12) {
            $pending_key = 'pending_12_24';
        }
        $month_data[$pending_key] += 1;
        $clinic_data[$pending_key] += 1;
    }

    function trpdd_calculate_time_bucket_percent_only(array $months, ?string $current_month = null): array
    {
        $sum = [
            'not_received_under_12' => 0,
            'not_received_12_24'    => 0,
            'not_received_24_48'    => 0,
            'not_received_48_72'    => 0,
            'not_received_over_72'  => 0,
            'month_total'           => 0,
        ];

        $sum_pct_under_24_per_month = 0;
        $months_with_data = 0;
        foreach ($months as $month_data) {
            $month_total = (int)($month_data['month_total'] ?? 0);

            if ($month_total > 0) {
                $months_with_data++;
                $total_under_24 =
                    ($month_data['not_received_under_12'] ?? 0) +
                    ($month_data['not_received_12_24'] ?? 0);
                $pct_under_24_this_month = ($total_under_24 / ($month_total)) * 100;
                $sum_pct_under_24_per_month += $pct_under_24_this_month;
            }
        }
        if ($current_month === null) {
            $current_month = max(array_keys($months));
        }
        $current = $months[$current_month] ?? [];

        foreach ($sum as $k => $v) {
            $sum[$k] += (int) ($current[$k] ?? 0);
        }

        $avg_month_total = $months_with_data > 0 ? round($sum_pct_under_24_per_month / $months_with_data, 2) : 0;
        $month_total = $sum['month_total'];
        if ($month_total === 0) {
            return [
                'pct_over_72'  => null,
                'pct_48_72'    => null,
                'pct_24_48'    => null,
                'pct_12_24'    => null,
                'pct_under_12' => null,
                'pct_under_24' => null,
                'avg_month_total' => $avg_month_total,
                'months_with_data' => $months_with_data,
                'month_total' => 0,
                'no_data' => true,
            ];
        }

        // Tính %
        $result = [
            'pct_over_72'     => round(($sum['not_received_over_72']) / $month_total * 100, 2),
            'pct_48_72'       => round(($sum['not_received_48_72']) / $month_total * 100, 2),
            'pct_24_48'       => round(($sum['not_received_24_48']) / $month_total * 100, 2),
            'pct_12_24'       => round(($sum['not_received_12_24']) / $month_total * 100, 2),
            'pct_under_12'    => round(($sum['not_received_under_12']) / $month_total * 100, 2),
            'avg_month_total' => $avg_month_total,
            'months_with_data' => $months_with_data,
            'month_total' => $month_total,
        ];
        $result['pct_under_24'] = round($result['pct_under_12'] + $result['pct_12_24'], 2);
        if ($result['pct_under_24'] > 100) {
            $result['pct_under_24'] = 100;
        }

        return $result;
    }

    function trpdd_build_clinic_performance_report(array $count_by_clinic_months, array $clinics, string $selected_month_key): array
    {
        $report_clinic = [];
        foreach ($count_by_clinic_months as $id_c => $months) {
            $item = trpdd_calculate_time_bucket_percent_only($months, $selected_month_key);
            $name_c = isset($clinics[$id_c]) ? $clinics[$id_c]->cli_clinic : '';
            if ($name_c === '') {
                continue;
            }

            $item['name'] = $name_c;
            $item['compare_under_24'] = compare_percent_with_icon($item['pct_under_24'], $item['avg_month_total']);
            $item['compare_under_24_12m'] = compare_percent_with_icon($item['avg_month_total'], 0);
            $report_clinic[] = $item;
        }

        usort($report_clinic, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $fields = [
            "pct_over_72",
            "pct_48_72",
            "pct_24_48",
            "pct_12_24",
            "pct_under_12",
            "pct_under_24",
            "avg_month_total"
        ];

        $totals = array_fill_keys($fields, 0);
        $clinics_with_data = 0;
        $clinics_months_with_data = 0;

        foreach ($report_clinic as $item) {
            if (isset($item['months_with_data']) && $item['months_with_data'] > 0) {
                $clinics_months_with_data += 1;
            }
            if (isset($item['month_total']) && $item['month_total'] > 0) {
                $clinics_with_data += 1;
            }
            foreach ($fields as $f) {
                $totals[$f] += $item[$f] ?? 0;
            }
        }

        $averages_clinic = [];
        foreach ($totals as $f => $total) {
            if ($f === 'avg_month_total') {
                $averages_clinic[$f] = $clinics_months_with_data > 0 ? round($total / $clinics_months_with_data, 2) : 0;
            } else {
                $averages_clinic[$f] = $clinics_with_data > 0 ? round($total / $clinics_with_data, 2) : 0;
            }
        }

        $averages_clinic['color_under_24'] = get_color_percent($averages_clinic['pct_under_24']);
        $averages_clinic['color_avg_month_total'] = get_color_percent($averages_clinic['avg_month_total']);

        return [
            'report_clinic' => $report_clinic,
            'averages_clinic' => $averages_clinic,
        ];
    }

    function get_color_percent($diff)
    {
        // Clean nếu là string
        if (is_string($diff)) {
            $diff = strip_tags($diff);             // bỏ HTML
            $diff = str_replace(['%', ' '], '', $diff);
            $diff = str_replace(',', '.', $diff);  // dấu thập phân locale
        }

        $diff = (float)$diff;

        // Nếu >100 thì log cảnh báo (nhân sai trước đó)
        if ($diff > 100) {
            $diff = $diff / 100; // sửa tạm nếu muốn
        }

        if ($diff >= 90) {
            return 'bg-green-100 text-green-800 border-green-300';
        } elseif ($diff >= 80) {
            return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        } elseif ($diff >= 70) {
            return 'bg-orange-100 text-orange-800 border-orange-300';
        } else {
            return 'bg-red-100 text-red-800 border-red-300';
        }
    }

    function compare_percent_with_icon(?float $value1, ?float $value2): array
    {
        if ($value1 === null || $value2 === null) {
            return [
                'diff' => null,
                'direction' => 'na',
                'icon' => '',
                'class' => 'text-gray-400',
            ];
        }

        $diff = round(abs($value2 - $value1), 2);

        // Xác định hướng tăng hay giảm
        if ($value1 > $value2) {
            $direction = 'up';
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3.5 w-3.5 text-green-600" aria-hidden="true"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>';
        } elseif ($value1 < $value2) {
            $direction = 'down';
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3.5 w-3.5 text-red-600" aria-hidden="true"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>';
        } else {
            $direction = 'same';
            $icon = ''; // không hiển thị icon nếu bằng nhau
        }

        // Xác định class màu theo mức chênh lệch

        $class = get_color_percent($value1); //Nếu lấy theo diff thì đổi

        return [
            'diff' => $diff,
            'direction' => $direction,
            'icon' => $icon,
            'class' => $class
        ];
    }

    function trpdd_fill_month_array(array &$month): void
    {
        $month['month_total'] = 0;

        $month['not_received_over_72'] = 0;
        $month['not_received_48_72'] = 0;
        $month['not_received_24_48'] = 0;
        $month['not_received_12_24'] = 0;
        $month['not_received_under_12'] = 0;
        $month['pending_over_72'] = 0;
        $month['pending_48_72'] = 0;
        $month['pending_24_48'] = 0;
        $month['pending_12_24'] = 0;
        $month['pending_under_12'] = 0;
    }


    $batch_tbl = tr_pdd_get_table_by_btype($btype_selected);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);


    $all_tests = array();

    if ($btype_selected == 'tox') {
        $rs = $wpdb->get_results("select te_group_id , te_name from ci_test_panels where te_type = '{$btype_selected}' ");
        foreach ($rs as $r) {
            $all_tests[strtoupper($r->te_name)] = $r->te_group_id;
        }
    }
    $sql = "select tg_ID, tg_name
                    from ci_test_groups 
                    where tg_type = '{$btype_selected}' 
                    and tg_parent = 0
                    and tg_show_req = 1
                    order by tg_position asc";

    if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

        $cache = new Tr_Cache_Query('delay_trend');
        $rs = $cache->get_results($sql, OBJECT, $cache_query_seconds);
    } else {
        $rs = $wpdb->get_results($sql);
    }

    foreach ($rs as $r) {
        $all_panels[$r->tg_ID] = str_replace('(new)', '', $r->tg_name);
    }

    if ($sel_panel != 'all') {
        if ($btype_selected == 'tox') {
            $wheretox = " ( ";
            $panel_conditions = array();
            foreach ($all_tests as $test_name => $group_id) {
                if ($group_id == $sel_panel) {
                    $panel_conditions[] = " ba.panel_tests_requested like '%{$test_name}%' ";
                }
            }
            $wheretox .= implode(' OR ', $panel_conditions);
            $wheretox .= " ) ";
            $where .= " AND " . $wheretox;
        } else {
            $where .= " AND ba.panel_tests_requested like '%GROUP_{$sel_panel}%' ";
        }
    }

    if ($sel_clinic != 'all') {
        $where .= " AND ba.cli_ID=$sel_clinic";
    }



    $sql_count = "
    SELECT
       ba.doc,
       IF(ba.batch_id>0, ba.spec_received, NULL) as spec_received,
       ba.cli_ID,
       ba.lab_ID,
       re.approve_date
    FROM {$batch_tbl} ba
    left JOIN {$report_tbl} re ON re.patient_id = ba.assen and re.estatus='C'
    WHERE {$where}
    
    ";


    $count_by_months = array();
    $count_by_clinic_months = array();
    $current_month_key = date_i18n('Y-m');
    $first_month = '';
    $last_month = '';


    if (class_exists('Tr_Cache_Query')) {
        $cache       = new Tr_Cache_Query('delay_trend');
        $cachedata  = $cache->_get_cache(md5($sql_count) . '_data', $cache_query_seconds, true, ARRAY_A);
        if ($cachedata) {
            $first_month = $cachedata['first_month'];
            $last_month = $cachedata['last_month'];
            $count_by_months = $cachedata['data'];
            $count_by_clinic_months = $cachedata['cli_data_raw'] ?? array();
            if (!array_key_exists('cli_data_raw', $cachedata)) {
                $count_by_months = array();
            }
        }
    }

    if (empty($count_by_months)) {
        $rs = $wpdb->get_results($sql_count, ARRAY_A);

        for ($i = 11; $i >= 0; $i--) {
            $timeofmonth = strtotime("-{$i} months", strtotime(date_i18n('Y-m-01')));
            $ym     = date_i18n('Y-m', $timeofmonth);
            $month_label = date_i18n('M', $timeofmonth);
            if ($i == 11) {
                $first_month = $month_label;
            } else if ($i == 0) {
                $last_month = $month_label;
            }

            $count_by_months[$ym] = array(
                'ym' => $ym,
                'month_label' => $month_label,
            );
            trpdd_fill_month_array($count_by_months[$ym]);
        }

        foreach ($rs as $r) {

            $ym = date_i18n('Y-m', strtotime($r['doc']));

            if (!isset($count_by_clinic_months[$r['cli_ID']])) {
                $count_by_clinic_months[$r['cli_ID']] = array();
            }

            if (!isset($count_by_clinic_months[$r['cli_ID']][$ym])) {
                $count_by_clinic_months[$r['cli_ID']][$ym] = array();
            }

            trpdd_process_sample_delay_trend_row($count_by_months[$ym], $count_by_clinic_months[$r['cli_ID']][$ym], $r,  $days_of_operation_lab);
        }


        // Lưu cache
        if (class_exists('Tr_Cache_Query')) {
            $cache->_set_cache(md5($sql_count) . '_data', array('data' => $count_by_months, 'cli_data_raw' => $count_by_clinic_months, 'last_month' => $last_month, 'first_month' => $first_month), true);
        }
    }

    $clinic_month_options = [];
    $available_month_keys = array_keys($count_by_months);
    foreach ($count_by_months as $ym => $month_data) {
        $clinic_month_options[] = [
            'value' => $ym,
            'label' => $month_data['month_label'],
        ];
    }

    if (!in_array($sel_clinic_month, $available_month_keys, true)) {
        $sel_clinic_month = !empty($available_month_keys) ? end($available_month_keys) : $current_month_key;
    }

    $selected_clinic_month_label = isset($count_by_months[$sel_clinic_month]['month_label'])
        ? $count_by_months[$sel_clinic_month]['month_label']
        : date_i18n('M', strtotime($sel_clinic_month . '-01'));


    $all_items = [];
    foreach ($count_by_months as $ym => $m) {
        $month_totals = array_map(function ($m) {
            return intval($m['month_total'] ?? 0);
        }, $count_by_months);
        $m = filter_status_bucket_keep_columns($m, $sel_status, $sel_bucket);

        $total = max(1, intval($m['month_total'])); // tránh chia cho 0
        $total_events = $total * 2;

        $total_nr = $m['not_received_over_72'] + $m['not_received_48_72'] + $m['not_received_24_48'] + $m['not_received_12_24'] + $m['not_received_under_12'];
        $total_pending = $m['pending_over_72'] + $m['pending_48_72'] + $m['pending_24_48'] + $m['pending_12_24'] + $m['pending_under_12'];

        // --- Tính % từng bucket ---
        $m['pct_not_received_over_72'] = $total_nr  > 0 ? round($m['not_received_over_72'] / $total_nr * 100, 2) : 0;
        $m['pct_not_received_48_72']   = $total_nr  > 0 ? round($m['not_received_48_72'] / $total_nr * 100, 2) : 0;
        $m['pct_not_received_24_48']   = $total_nr  > 0 ? round($m['not_received_24_48'] / $total_nr * 100, 2) : 0;
        $m['pct_not_received_12_24']   = $total_nr  > 0 ? round($m['not_received_12_24'] / $total_nr * 100, 2) : 0;
        $m['pct_not_received_under_12'] = $total_nr > 0 ? round($m['not_received_under_12'] / $total_nr * 100, 2) : 0;

        $m['pct_pending_over_72']      = $total_pending > 0 ? round($m['pending_over_72'] / $total_pending * 100, 2) : 0;
        $m['pct_pending_48_72']        = $total_pending > 0 ? round($m['pending_48_72'] / $total_pending * 100, 2) : 0;
        $m['pct_pending_24_48']        = $total_pending > 0 ? round($m['pending_24_48'] / $total_pending * 100, 2) : 0;
        $m['pct_pending_12_24']        = $total_pending > 0 ? round($m['pending_12_24'] / $total_pending * 100, 2) : 0;
        $m['pct_pending_under_12']     = $total_pending > 0 ? round($m['pending_under_12'] / $total_pending * 100, 2) : 0;

        // --- Gom nhóm NOT RECEIVED ---
        $m['not_received'] = [
            'over_72'  => intval($m['not_received_over_72']),
            '48_72'    => intval($m['not_received_48_72']),
            '24_48'   => intval($m['not_received_24_48']),
            '12_24'   => intval($m['not_received_12_24']),
            'under_12' => intval($m['not_received_under_12']),
        ];
        $m['not_received_total'] = array_sum($m['not_received']);
        $m['pct_not_received_total'] = round($m['not_received_total'] / $total * 100, 2);

        // --- Gom nhóm PENDING ---
        $m['pending'] = [
            'over_72'  => intval($m['pending_over_72']),
            '48_72'    => intval($m['pending_48_72']),
            '24_48'   => intval($m['pending_24_48']),
            '12_24'   => intval($m['pending_12_24']),
            'under_12' => intval($m['pending_under_12']),
        ];
        $m['pending_total'] = array_sum($m['pending']);
        $m['pct_pending_total'] = round($m['pending_total'] / $total * 100, 2);

        // --- % từng bucket ---
        foreach ($m['not_received'] as $k => $v) {
            $m['pct_not_received'][$k] = round($v / $total * 100, 2);
        }
        foreach ($m['pending'] as $k => $v) {
            $m['pct_pending'][$k] = round($v / $total * 100, 2);
        }

        if ($ym == $current_month_key) {
            $month_last = $m;
            $total_last = max(1, intval($month_last['month_total']));
        }

        $all_items[] = $m;
    }

    $count_by_months = $all_items;
    $total_last = max($total_last, 1);

    $percent_last = [
        'month_label'            => $month_last['month_label'],
        'not_received_over_72'   => round($month_last['not_received_over_72'] / $total_last * 100, 2),
        'not_received_48_72'     => round($month_last['not_received_48_72'] / $total_last * 100, 2),
        'not_received_24_48'     => round($month_last['not_received_24_48'] / $total_last * 100, 2),
        'not_received_12_24'     => round($month_last['not_received_12_24'] / $total_last * 100, 2),
        'not_received_under_12'  => round($month_last['not_received_under_12'] / $total_last * 100, 2),

        'pending_over_72'        => round($month_last['pending_over_72'] / $total_last * 100, 2),
        'pending_48_72'          => round($month_last['pending_48_72'] / $total_last * 100, 2),
        'pending_24_48'          => round($month_last['pending_24_48'] / $total_last * 100, 2),
        'pending_12_24'          => round($month_last['pending_12_24'] / $total_last * 100, 2),
        'pending_under_12'       => round($month_last['pending_under_12'] / $total_last * 100, 2),
    ];


    $month_last_grouped = [
        "not_received" => [
            "over_72"   => $month_last['not_received_over_72'],
            "48_72"     => $month_last['not_received_48_72'],
            "24_48"     => $month_last['not_received_24_48'],
            "12_24"     => $month_last['not_received_12_24'],
            "under_12"  => $month_last['not_received_under_12'],
        ],
        "pending" => [
            "over_72"   => $month_last['pending_over_72'],
            "48_72"     => $month_last['pending_48_72'],
            "24_48"     => $month_last['pending_24_48'],
            "12_24"     => $month_last['pending_12_24'],
            "under_12"  => $month_last['pending_under_12'],
        ]
    ];

    $month_last_grouped["not_received_total"] = array_sum($month_last_grouped["not_received"]);
    $month_last_grouped["pending_total"] = array_sum($month_last_grouped["pending"]);

    $month_last_grouped["pct_not_received_total"] =
        round($month_last_grouped["not_received_total"] / $total_last * 100, 2);

    $month_last_grouped["pct_pending_total"] =
        round($month_last_grouped["pending_total"] / $total_last * 100, 2);

    // % từng bucket
    foreach ($month_last_grouped["not_received"] as $k => $v) {
        $month_last_grouped["pct_not_received"][$k] = round($v / $total_last * 100, 2);
    }
    foreach ($month_last_grouped["pending"] as $k => $v) {
        $month_last_grouped["pct_pending"][$k] = round($v / $total_last * 100, 2);
    }

    $avg12 = [
        "not_received" => [
            'over_72'  => 0,
            '48_72'    => 0,
            '24_48'    => 0,
            '12_24'    => 0,
            'under_12' => 0,
        ],
        "pending" => [
            'over_72'  => 0,
            '48_72'    => 0,
            '24_48'    => 0,
            '12_24'    => 0,
            'under_12' => 0,
        ],
    ];

    $total12 = [
        'not_received' => [
            'over_72'   => 0,
            '48_72'     => 0,
            '24_48'     => 0,
            '12_24'     => 0,
            'under_12'  => 0
        ],
        'pending' => [
            'over_72'   => 0,
            '48_72'     => 0,
            '24_48'     => 0,
            '12_24'     => 0,
            'under_12'  => 0
        ]
    ];

    foreach ($count_by_months as $m) {

        foreach ($total12 as $group => $buckets) {
            foreach ($buckets as $bucket => $_) {
                $key = "{$group}_{$bucket}";
                $total12[$group][$bucket] += (int)$m[$key];
            }
        }
    }
    foreach ($total12 as $group => $buckets) {

        $sum_group = array_sum($buckets);

        foreach ($buckets as $bucket => $val) {
            $avg12[$group][$bucket] = round(
                $val / max(1, $sum_group) * 100,
                2
            );
        }
    }

    function compare_pct($current, $avg12)
    {
        $diff = round($current - $avg12, 2);

        if ($diff > 0) return ["status" => "up", "diff" => $diff, "icon" => '<span>↑</span>'];
        if ($diff < 0) return ["status" => "down", "diff" => abs($diff), "icon" => '<span>↓</span>'];
        return ["status" => "equal", "diff" => 0, "icon" => '<span>=</span>'];
    }

    $compare = [
        "not_received" => [],
        "pending" => []
    ];

    foreach ($month_last_grouped['pct_not_received'] as $bucket => $pct) {
        $compare['not_received'][$bucket] = compare_pct($pct, $avg12['not_received'][$bucket]);
    }

    foreach ($month_last_grouped['pct_pending'] as $bucket => $pct) {
        $compare['pending'][$bucket] = compare_pct($pct, $avg12['pending'][$bucket]);
    }

    $compare['not_received_total'] = compare_pct(
        $month_last_grouped['pct_not_received_total'],
        array_sum($avg12['not_received'])
    );

    $compare['pending_total'] = compare_pct(
        $month_last_grouped['pct_pending_total'],
        array_sum($avg12['pending'])
    );

    $clinic_report_data = trpdd_build_clinic_performance_report($count_by_clinic_months, $clinics, $sel_clinic_month);
    $report_clinic = $clinic_report_data['report_clinic'];
    $averages_clinic = $clinic_report_data['averages_clinic'];

    if (isset($_GET['debug'])) {
        echo 'Count All:';
        var_dump($count_by_months);

        echo '<hr/>';
        echo 'avg12:';
        var_dump($avg12);

        echo '<hr/>';
        echo 'compare:';
        var_dump($compare);

        echo '<hr/>Month Last';
        var_dump($month_last_grouped);

        echo '<hr/>clinic';
        var_dump($report_clinic);
    }

    // object để tiện xử lý
    $avg_current = (object) $percent_last;
}

$schedule_report->ajax();


if ($trajax == 'loadData') {
    $data_result = [
        'avg_current' => $avg_current,
        'all_panel' => $all_panels,
        'compare' => $compare,
        'avg12' => $avg12,
        'count_by_months' => $count_by_months,
        'report_clinic' => $report_clinic,
        'averages_clinic' => $averages_clinic,
        'month_last_grouped' => $month_last_grouped,
        'clinic_month_options' => $clinic_month_options,
        'selected_clinic_month' => $sel_clinic_month,
        'selected_clinic_month_label' => $selected_clinic_month_label,

    ];

    echo json_encode($data_result);
    exit;
}



//var_dump($history_12_m);

if ($export_func != '') {

    if ($filepath_save_csv == '' && $filepath_save_pdf == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Delay Trends Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    }

    //Name for Filters Export
    $cur_name_filter_panel = 'All Panels';
    $cur_name_clinic = 'All Clinics';
    $cur_name_status = 'All Statuses';
    $cur_name_bucket = 'All Buckets';

    if ($sel_panel != 'all') $cur_name_filter_panel = isset($all_panels[$sel_panel]) ? $all_panels[$sel_panel] : 'All Panels';
    if ($sel_clinic != "all") {
        foreach ($clinics as $clinic) {
            if ($clinic->cID == $sel_clinic) {
                $cur_name_clinic = $clinic->cli_clinic;
                break;
            }
        }
    }
    if ($sel_status != 'all') $cur_name_status = isset($status[$sel_status]) ? $status[$sel_status]['title'] : 'All Statuses';
    if ($sel_bucket != 'all') $cur_name_bucket = isset($bucket_filter[$sel_bucket]) ? $bucket_filter[$sel_bucket]['title'] : 'All Buckets';

    $bucket_render = $bucket_filter;
    //Lọc lại khung giờ trước khi export
    if ($sel_bucket !== 'all' && isset($bucket_filter[$sel_bucket])) {
        $bucket_render = [
            $sel_bucket => $bucket_filter[$sel_bucket]
        ];
    }

    if ($sel_bucket == 'all' && $is_include_12 == 0) {
        unset($bucket_render['under_12']);
    }

    //Tính trung avg cho các cột ở table 6 tháng export
    $sum = [
        'not_received_over_72' => 0,
        'not_received_48_72'   => 0,
        'not_received_24_48'   => 0,
        'not_received_12_24'   => 0,
        'not_received_under_12' => 0,

        'pending_over_72' => 0,
        'pending_48_72'   => 0,
        'pending_24_48'   => 0,
        'pending_12_24'   => 0,
        'pending_under_12' => 0,

        'total_recv'   => 0,
        'total_pending' => 0,
        'grand_total'  => 0,
    ];

    $count_month = count($count_by_months);
    if ($count_month > 0) {
        foreach ($count_by_months as $item) {

            // Tổng từng cột
            foreach ($sum as $key => $value) {
                if (isset($item[$key])) {
                    $sum[$key] += $item[$key];
                }
            }

            // Tính total
            $total_recv =
                $item['not_received_over_72'] +
                $item['not_received_48_72'] +
                $item['not_received_24_48'] +
                $item['not_received_12_24'] +
                $item['not_received_under_12'];

            $total_pending =
                $item['pending_over_72'] +
                $item['pending_48_72'] +
                $item['pending_24_48'] +
                $item['pending_12_24'] +
                $item['pending_under_12'];

            $grand_total = $total_recv + $total_pending;

            // Cộng dồn
            $sum['total_recv']    += $total_recv;
            $sum['total_pending'] += $total_pending;
            $sum['grand_total']   += $grand_total;
        }

        // Tính trung bình
        $avg = [];
        foreach ($sum as $key => $value) {
            $avg[$key] = round($value / $count_month);
        }
    }

    if ($export_type == "pdf" || $filepath_save_pdf != '') {
        $orientation = 'portrait';
        $generated_on = date_i18n("m/d/Y \\a\\t h:i A");
        $period_export = $to_date;
        ob_start();
        $outfile = generate_report_filename("RPT", 'DelayTrends', $period_export, '', 'pdf');
        require TRCPDD_PATH . 'parts/reports/sample_delay_trends_pdf.php';
        $html = ob_get_clean();

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

    if ($export_type == "csv" || $filepath_save_csv != '') {
        $generated_on = date_i18n("m/d/Y \\a\\t h:i A");
        $period_export =  $to_date;
        $outfile = generate_report_filename("RPT", 'DelayTrends', $period_export, '', 'csv');
        tr_pdd_header('csv', $outfile);
        if ($filepath_save_csv != '') {
            $f = fopen($filepath_save_csv, 'w');
            $csv_out_file = basename($outfile);
        } else {
            $f = fopen('php://output', 'w');
        }

        fputcsv($f, ["Generated on: - $generated_on"]);
        fputcsv($f, [""]);
        fputcsv($f, ["Filter Settings"]);
        fputcsv($f, ["Period:", "Historical 12 Months ($first_month - $last_month)"]);
        fputcsv($f, ["Test Type:", strtoupper($btype_selected)]);
        fputcsv($f, ["Panels:", $cur_name_filter_panel]);
        fputcsv($f, ["Clinic:", $cur_name_clinic]);
        fputcsv($f, ["Status:", $cur_name_status]);
        fputcsv($f, ["Bucket:", $cur_name_bucket]);

        //Current Month vs 12-Month Average Delay Rates"
        fputcsv($f, [""]);
        fputcsv($f, ["Current Month vs 12-Month Average Delay Rates"]);
        if ($sel_status == 'all' || $sel_status == 'not_received') {
            fputcsv($f, [""]);
            fputcsv($f, ["Not Received (Pre-Lab)"]);
            fputcsv($f, ["Time Bracket", "Current Month (" . $last_month . ")", "12-Month Average"]);
            foreach ($bucket_render as $key => $item) {
                $month_last = $month_last_grouped['not_received'] ?? [];
                $avg_last   = $month_last_grouped['pct_not_received'] ?? [];

                $cur_pct = $avg_last[$key]   ?? 'N/A';
                $cur_cnt = $month_last[$key] ?? 'N/A';

                $last12_pct = $avg12['not_received'][$key]  ?? 'N/A';
                $last12_cnt = $total12['not_received'][$key] ?? 'N/A';
                $avg_12m = $avg['not_received_' . $key] ?? 0;

                $s_cur = ($cur_pct === 'N/A' || $cur_cnt === 'N/A')
                    ? 'N/A'
                    : $cur_pct . '% (' . $cur_cnt . ')';

                $s_12 = ($last12_pct === 'N/A' || $last12_cnt === 'N/A')
                    ? 'N/A'
                    : $last12_pct . '% (' . $avg_12m . ')';

                fputcsv($f, [$item['title'], $s_cur, $s_12]);
            }
        }

        if ($sel_status == 'all' || $sel_status == 'pending') {
            fputcsv($f, [""]);
            fputcsv($f, ["Pending (In-Lab)"]);
            fputcsv($f, ["Time Bracket", "Current Month (" . $last_month . ")", "12-Month Average"]);
            foreach ($bucket_render as $key => $item) {
                $month_last = $month_last_grouped['pending'] ?? [];
                $avg_last   = $month_last_grouped['pct_pending'] ?? [];

                $cur_pct = $avg_last[$key]   ?? 'N/A';
                $cur_cnt = $month_last[$key] ?? 'N/A';

                $last12_pct = $avg12['pending'][$key]  ?? 'N/A';
                $last12_cnt = $total12['pending'][$key] ?? 'N/A';
                $avg_12m = $avg['pending_' . $key] ?? 0;

                $s_cur = ($cur_pct === 'N/A' || $cur_cnt === 'N/A')
                    ? 'N/A'
                    : $cur_pct . '% (' . $cur_cnt . ')';

                $s_12 = ($last12_pct === 'N/A' || $last12_cnt === 'N/A')
                    ? 'N/A'
                    : $last12_pct . '% (' . $avg_12m . ')';

                fputcsv($f, [$item['title'], $s_cur, $s_12]);
            }
        }

        //Specimens by Status & Time Bracket (12 Months)
        fputcsv($f, [""]);
        fputcsv($f, ["Specimens by Status & Time Bracket (12 Months)"]);
        if ($sel_status == 'all' || $sel_status == 'not_received') {
            fputcsv($f, [""]);
            fputcsv($f, ["Not Received (Pre-Lab)"]);
            $headers = ["Month", "Total", ">72h", "48-72h", "24-48h", "12-24h"];
            if ($is_include_12 == 1) $headers[] = "<12h";
            fputcsv($f, $headers);
            $h = ["Average", $avg['total_recv'], $avg['not_received_over_72'], $avg['not_received_48_72'], $avg['not_received_24_48'], $avg['not_received_12_24']];
            if ($is_include_12 == 1) $h[] = $avg['not_received_under_12'];
            fputcsv($f, $h);
            foreach ($count_by_months as $key => $item) {
                if ($is_include_12 == 1) {
                    $total_recv = $item['not_received_over_72'] + $item['not_received_48_72'] + $item['not_received_24_48'] + $item['not_received_12_24'] + $item['not_received_under_12'];
                } else {
                    $total_recv = $item['not_received_over_72'] + $item['not_received_48_72'] + $item['not_received_24_48'] + $item['not_received_12_24'];
                }

                $r = [$item['month_label']];
                $r[] = $total_recv;
                $r[] = $item['not_received_over_72'];
                $r[] = $item['not_received_48_72'];
                $r[] = $item['not_received_24_48'];
                $r[] = $item['not_received_12_24'];
                if ($is_include_12 == 1) $r[] = $item['not_received_under_12'];
                fputcsv($f, $r);
            }
        }

        if ($sel_status == 'all' || $sel_status == 'pending') {
            fputcsv($f, [""]);
            fputcsv($f, ["Pending (In-Lab)"]);
            $headers = ["Month", "Total", ">72h", "48-72h", "24-48h", "12-24h"];
            if ($is_include_12 == 1) $headers[] = "<12h";
            fputcsv($f, $headers);
            $h = ["Average", $avg['total_pending'], $avg['pending_over_72'], $avg['pending_48_72'], $avg['pending_24_48'], $avg['pending_12_24']];
            if ($is_include_12 == 1) $h[] = $avg['pending_under_12'];
            fputcsv($f, $h);
            foreach ($count_by_months as $key => $item) {
                if ($is_include_12 == 1) {
                    $total_pending = $item['pending_over_72'] + $item['pending_48_72'] + $item['pending_24_48'] + $item['pending_12_24'] + $item['pending_under_12'];
                } else {
                    $total_pending = $item['pending_over_72'] + $item['pending_48_72'] + $item['pending_24_48'] + $item['pending_12_24'];
                }

                $r = [$item['month_label']];
                $r[] = $total_pending;
                $r[] = $item['pending_over_72'];
                $r[] = $item['pending_48_72'];
                $r[] = $item['pending_24_48'];
                $r[] = $item['pending_12_24'];
                if ($is_include_12 == 1) $r[] = $item['pending_under_12'];
                fputcsv($f, $r);
            }
        }

        //Delay Rates by Status & Time Bracket (12 Months)
        fputcsv($f, [" "]);
        fputcsv($f, ["Delay Rates by Status & Time Bracket (12 Months)"]);
        if ($sel_status == 'all' || $sel_status == 'not_received') {
            fputcsv($f, [" "]);
            fputcsv($f, ["Not Received (Pre-Lab)"]);
            $headers = ["Month", ">72h", "48-72h", "24-48h", "12-24h"];
            if ($is_include_12 == 1) $headers[] = "<12h";
            fputcsv($f, $headers);
            $count = count($count_by_months);
            $total_over_72 = $total_48_72 = $total_24_48 = $total_12_24 = $total_under_12 = 0;
            foreach ($count_by_months as $item) {
                $total_over_72   += $item['pct_not_received_over_72'];
                $total_48_72     += $item['pct_not_received_48_72'];
                $total_24_48     += $item['pct_not_received_24_48'];
                $total_12_24     += $item['pct_not_received_12_24'];
                $total_under_12  += $item['pct_not_received_under_12'];
            }
            if ($count > 0) {
                $r = [
                    'Average',
                    round($total_over_72 / $count, 2) . '%',
                    round($total_48_72 / $count, 2) . '%',
                    round($total_24_48 / $count, 2) . '%',
                    round($total_12_24 / $count, 2) . '%'
                ];
                if ($is_include_12 == 1) $r[] = round($total_under_12 / $count, 2) . '%';
                fputcsv($f, $r);
            }
            foreach ($count_by_months as $item) {
                $r = [
                    $item['month_label'],
                    $item['pct_not_received_over_72'] . '%',
                    $item['pct_not_received_48_72'] . '%',
                    $item['pct_not_received_24_48'] . '%',
                    $item['pct_not_received_12_24'] . '%'
                ];
                if ($is_include_12 == 1) $r[] = $item['pct_not_received_under_12'] . '%';
                fputcsv($f, $r);
            }
        }

        if ($sel_status == 'all' || $sel_status == 'pending') {
            fputcsv($f, [" "]);
            fputcsv($f, ["Pending (In-Lab)"]);
            $headers = ["Month", ">72h", "48-72h", "24-48h", "12-24h"];
            if ($is_include_12 == 1) $headers[] = "<12h";
            fputcsv($f, $headers);
            $count = count($count_by_months);

            $total_over_72 = $total_48_72 = $total_24_48 = $total_12_24 = $total_under_12 = 0;

            foreach ($count_by_months as $item) {
                $total_over_72   += $item['pct_pending_over_72'];
                $total_48_72     += $item['pct_pending_48_72'];
                $total_24_48     += $item['pct_pending_24_48'];
                $total_12_24     += $item['pct_pending_12_24'];
                $total_under_12  += $item['pct_pending_under_12'];
            }

            if ($count > 0) {
                $ave_row = ['Average'];
                $ave_row[] = round($total_over_72 / $count, 2) . '%';
                $ave_row[] = round($total_48_72 / $count, 2) . '%';
                $ave_row[] = round($total_24_48 / $count, 2) . '%';
                $ave_row[] = round($total_12_24 / $count, 2) . '%';
                if ($is_include_12 == 1) $ave_row[] = round($total_under_12 / $count, 2) . '%';
                fputcsv($f, $ave_row);
            }
            foreach ($count_by_months as $item) {
                $r_m = [$item['month_label']];
                $r_m[] = $item['pct_pending_over_72'] . '%';
                $r_m[] = $item['pct_pending_48_72'] . '%';
                $r_m[] = $item['pct_pending_24_48'] . '%';
                $r_m[] = $item['pct_pending_12_24'] . '%';
                if ($is_include_12) $r_m[] = $item['pct_pending_under_12'] . '%';
                fputcsv($f, $r_m);
            }
        }

        fputcsv($f, [" "]);
        fputcsv($f, ["Clinic Pre-Lab Performance by Time Bracket"]);
        fputcsv($f, [" "]);
        if ($is_include_12 == 1) {
            fputcsv($f, ["Clinic", ">72h", "48-72h", "24-48h", "12-24h", "<12h", "Under 24h Target (" . $selected_clinic_month_label . ")", "12-month"]);
            fputcsv($f, [
                "Average (All Specimens)",
                $averages_clinic['pct_over_72'] . '%',
                $averages_clinic['pct_48_72'] . '%',
                $averages_clinic['pct_24_48'] . '%',
                $averages_clinic['pct_12_24'] . '%',
                $averages_clinic['pct_under_12'] . '%',
                $averages_clinic['pct_under_24'] . '%',
                $averages_clinic['avg_month_total'] . '%'
            ]);

            foreach ($report_clinic as $item) {
                fputcsv($f, [
                    $item['name'],
                    $item['pct_over_72'] === null ? 'N/A' : $item['pct_over_72'] . '%',
                    $item['pct_48_72'] === null ? 'N/A' : $item['pct_48_72'] . '%',
                    $item['pct_24_48'] === null ? 'N/A' : $item['pct_24_48'] . '%',
                    $item['pct_12_24'] === null ? 'N/A' : $item['pct_12_24'] . '%',
                    $item['pct_under_12'] === null ? 'N/A' : $item['pct_under_12'] . '%',
                    $item['pct_under_24'] === null ? 'N/A' : $item['pct_under_24'] . '%',
                    $item['avg_month_total'] === null ? 'N/A' : $item['avg_month_total'] . '%',
                ]);
            }
        } else {
            fputcsv($f, ["Clinic", ">72h", "48-72h", "24-48h", "12-24h", "Under 24h Target (" . $selected_clinic_month_label . ")", "12-month"]);
            fputcsv($f, [
                "Average (All Specimens)",
                $averages_clinic['pct_over_72'] . '%',
                $averages_clinic['pct_48_72'] . '%',
                $averages_clinic['pct_24_48'] . '%',
                $averages_clinic['pct_12_24'] . '%',
                $averages_clinic['pct_under_24'] . '%',
                $averages_clinic['avg_month_total'] . '%'
            ]);

            foreach ($report_clinic as $item) {
                fputcsv($f, [
                    $item['name'],
                    $item['pct_over_72'] === null ? 'N/A' : $item['pct_over_72'] . '%',
                    $item['pct_48_72'] === null ? 'N/A' : $item['pct_48_72'] . '%',
                    $item['pct_24_48'] === null ? 'N/A' : $item['pct_24_48'] . '%',
                    $item['pct_12_24'] === null ? 'N/A' : $item['pct_12_24'] . '%',
                    $item['pct_under_24'] === null ? 'N/A' : $item['pct_under_24'] . '%',
                    $item['avg_month_total'] === null ? 'N/A' : $item['avg_month_total'] . '%',
                ]);
            }
        }

        fclose($f);
        if ($filepath_save_csv == '') exit;
    }
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
    return;
}

if (!function_exists('icon_sort')) {
    function icon_sort()
    {
        $icon_down_up = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up-down inline h-3 w-3 ml-1 opacity-40" aria-hidden="true"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>';
        $icon_up = '<svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-up h-3 w-3 ml-1 text-primary" aria-hidden="true"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>';
        $icon_down = '<svg style="display:none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon_sort lucide lucide-arrow-down h-3 w-3 ml-1 text-primary" aria-hidden="true"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>';
        return $icon_down_up . $icon_up . $icon_down;
    }
}
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>

<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS; ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    var is_include_m12_rate = !!Number('<?php echo $is_include_12; ?>');
    var is_include_m12 = !!Number('<?php echo $is_include_12; ?>');
    var sel_clinic_month = '<?php echo esc_js($sel_clinic_month); ?>';

    function applyRoundedTop(chart) {
        const ds = chart.data.datasets;
        const count = ds.length;
        const dataCount = chart.data.labels.length;

        for (let i = 0; i < count; i++) {
            const dset = ds[i];
            dset.borderRadius = Array(dataCount).fill(0);
            // thêm đường viền mảnh giữa các stack
            dset.borderWidth = -1;
        }

        // xác định dataset nằm trên cùng của từng cột và bo góc trên
        for (let col = 0; col < dataCount; col++) {
            for (let i = count - 1; i >= 0; i--) {
                if (ds[i].data[col] > 0) {
                    ds[i].borderRadius[col] = {
                        topLeft: 4,
                        topRight: 4
                    };
                    break;
                }
            }
        }
    }

    var verticalLinePlugin = {
        id: 'verticalLine',
        afterDraw(chart) {
            const active = chart.tooltip?._active;
            if (!active?.length) return;

            const index = active[0].index;

            // ❌ nếu cả cột không có dữ liệu
            const columnHasData = chart.data.datasets.some(ds => {
                const v = ds.data?.[index];
                return v !== null && v !== undefined;
                // nếu muốn coi 0 là trống → thêm: && v !== 0
            });

            if (!columnHasData) return;

            const ctx = chart.ctx;
            const x = active[0].element.x;
            const topY = chart.scales.y.top;
            const bottomY = chart.scales.y.bottom;

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(x, topY);
            ctx.lineTo(x, bottomY);
            ctx.lineWidth = 1;
            ctx.strokeStyle = '#aaa';
            ctx.stroke();
            ctx.restore();
        }
    };

    var noDataPlugin = {
        id: 'noData',
        beforeDraw(chart, args, options) {
            const {
                ctx,
                data,
                chartArea
            } = chart;
            if (!chartArea) return;

            const hasData = data.datasets.some(ds => ds.data && ds.data.length > 0);

            if (!hasData) {
                const {
                    top,
                    left,
                    width,
                    height
                } = chartArea;

                ctx.save();
                ctx.clearRect(left, top, width, height);

                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.font = '16px sans-serif';
                ctx.fillStyle = '#999';
                ctx.fillText('No Data Available', left + width / 2, top + height / 2);
                ctx.restore();
            }
        }
    };
</script>
<style>
    body {
        overflow-x: hidden;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        margin-bottom: 6px !important;
    }

    h1,
    h4 {
        margin: unset;
    }

    h1 {
        margin-bottom: 2px;
    }

    h4 {
        margin-bottom: 5px;
    }

    h5 {
        font-weight: var(--font-weight-medium);
        margin: 0px;
        --tw-font-weight: var(--font-weight-medium);
        font-size: 13px;
        margin-bottom: 10px;
    }

    .font-bold {
        --tw-font-weight: var(--font-weight-bold) !important;
        font-weight: var(--font-weight-bold) !important;
    }

    .text-2xl {
        font-size: var(--text-2xl);
        line-height: var(--tw-leading, var(--text-2xl--line-height));
    }

    button {
        --tw-font-weight: 500 !important;
        font-weight: 500 !important;
    }

    .icon_sort {
        color: #898787ff;
    }

    #box_clinic .clinic-month-filter {
        width: 170px;
        min-width: 170px;
        flex-shrink: 0;
    }

    #box_clinic .clinic-month-filter .custom-select {
        width: 100%;
    }

    #box_clinic .clinic-month-filter .custom-select .selected {
        width: 100%;
        box-sizing: border-box;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #box_clinic .clinic-month-filter .custom-select .options {
        left: auto;
        right: 0;
        min-width: 100%;
        max-width: min(220px, calc(100vw - 48px));
    }

    @media (max-width: 1023px) {
        #box_clinic .clinic-month-filter {
            width: 100%;
            min-width: 0;
        }

        #box_clinic .clinic-month-filter .custom-select .options {
            left: 0;
            right: auto;
            max-width: 100%;
        }
    }
</style>
<?php
$schedule_report->style();
?>

<div class="wrap flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Specimen Delay Trends</h1>
                <p class="text-muted-foreground">12-Month Analysis of Specimen Delays (Receiving vs Processing)</p>
            </div>

            <button onclick="window.location.href='admin.php?page=ci_reports_main'" data-slot="button" class="cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-foreground"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left h-4 w-4 mr-2" aria-hidden="true">
                    <path d="m12 19-7-7 7-7"></path>
                    <path d="M19 12H5"></path>
                </svg>Dashboard
            </button>
        </div>
        <div class="space-y-4">
            <div id="box_not_received" class="grid grid-cols-5 gap-5">
                <div id="box_count_not_received_over_72" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received <span class="font-bold">&gt;72h</span></h4>
                        <div class="p-1 md:p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', 'over_72', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', 'over_72', 'chart')); ?>;">
                                <path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"></path>
                                <path d="m7.5 4.27 9 5.15"></path>
                                <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                <line x1="12" x2="12" y1="22" y2="12"></line>
                                <path d="m17 13 5 5m-5 0 5-5"></path>
                            </svg></div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_not_received_over_72" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_not_received_over_72_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>
                <div id="box_count_not_received_48_72" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received <span class="font-bold">48-72h</span></h4>
                        <div class="p-1 md:p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '48_72', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '48_72', 'chart')); ?>;">
                                <path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"></path>
                                <path d="m7.5 4.27 9 5.15"></path>
                                <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                <line x1="12" x2="12" y1="22" y2="12"></line>
                                <path d="m17 13 5 5m-5 0 5-5"></path>
                            </svg></div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_not_received_48_72" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_not_received_48_72_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>
                <div id="box_count_not_received_24_48" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received <span class="font-bold">24-48h</span></h4>
                        <div class="p-1 md:p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '24_48', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '24_48', 'chart')); ?>;">
                                <path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"></path>
                                <path d="m7.5 4.27 9 5.15"></path>
                                <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                <line x1="12" x2="12" y1="22" y2="12"></line>
                                <path d="m17 13 5 5m-5 0 5-5"></path>
                            </svg></div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_not_received_24_48" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_not_received_24_48_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>
                <div id="box_count_not_received_12_24" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received <span class="font-bold">12-24h</span></h4>
                        <div class="p-1 md:p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '12_24', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', '12_24', 'chart')); ?>;">
                                <path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"></path>
                                <path d="m7.5 4.27 9 5.15"></path>
                                <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                <line x1="12" x2="12" y1="22" y2="12"></line>
                                <path d="m17 13 5 5m-5 0 5-5"></path>
                            </svg></div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_not_received_12_24" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_not_received_12_24_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>

                <div id="box_not_12m" data-slot="card" class="bg-card text-card-foreground  flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received <span class="font-bold">&lt;12h</span></h4>
                        <div style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', 'under_12', 'icon_bg')); ?>;" class="p-1 md:p-1.5 bg-gray-50 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'not_received', 'under_12', 'chart')); ?>;">
                                <path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14"></path>
                                <path d="m7.5 4.27 9 5.15"></path>
                                <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                <line x1="12" x2="12" y1="22" y2="12"></line>
                                <path d="m17 13 5 5m-5 0 5-5"></path>
                            </svg>
                        </div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_not_received_under_12" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_not_received_under_12_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>

            </div>
            <div id="box_pending" class="grid grid-cols-5 gap-5">
                <div id="box_count_pending_over_72" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending <span class="font-bold">&gt;72h</span></h4>
                        <div class="p-1 md:p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', 'over_72', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', 'over_72', 'chart')); ?>;">
                                <path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path>
                                <path d="M6.453 15h11.094"></path>
                                <path d="M8.5 2h7"></path>
                            </svg></div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_pending_over_72" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_pending_over_72_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>
                <div id="box_count_pending_48_72" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending <span class="font-bold">48-72h</span></h4>
                        <div class="p-1 md:p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '48_72', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '48_72', 'chart')); ?>;">
                                <path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path>
                                <path d="M6.453 15h11.094"></path>
                                <path d="M8.5 2h7"></path>
                            </svg></div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_pending_48_72" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_pending_48_72_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>
                <div id="box_count_pending_24_48" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending <span class="font-bold">24-48h</span></h4>
                        <div class="p-1 md:p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '24_48', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '24_48', 'chart')); ?>;">
                                <path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path>
                                <path d="M6.453 15h11.094"></path>
                                <path d="M8.5 2h7"></path>
                            </svg></div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_pending_24_48" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_pending_24_48_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>
                <div id="box_count_pending_12_24" data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending <span class="font-bold">12-24h</span></h4>
                        <div class="p-1 md:p-1.5 rounded-lg" style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '12_24', 'icon_bg')); ?>;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', '12_24', 'chart')); ?>;">
                                <path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path>
                                <path d="M6.453 15h11.094"></path>
                                <path d="M8.5 2h7"></path>
                            </svg></div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_pending_12_24" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_pending_12_24_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>

                <div id="box_12m" data-slot="card" class="bg-card text-card-foreground flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                        <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending <span class="font-bold">&lt;12h</span></h4>
                        <div style="background-color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', 'under_12', 'icon_bg')); ?>;" class="p-1 md:p-1.5 bg-gray-50 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flask-conical h-3 w-3 md:h-4 md:w-4" aria-hidden="true" style="color: <?php echo esc_attr(trpdd_get_palette_value($delay_bucket_palette, 'pending', 'under_12', 'chart')); ?>;">
                                <path d="M14 2v6a2 2 0 0 0 .245.96l5.51 10.08A2 2 0 0 1 18 22H6a2 2 0 0 1-1.755-2.96l5.51-10.08A2 2 0 0 0 10 8V2"></path>
                                <path d="M6.453 15h11.094"></path>
                                <path d="M8.5 2h7"></path>
                            </svg>
                        </div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                        <p class="text-xs text-muted-foreground">
                            <span id="rate_pending_under_12" class="text-xl md:text-2xl font-bold text-foreground">--</span>
                            <span class="current_month_text text-xs text-gray-400 ml-1.5">--</span>
                        </p>
                        <p id="rate_pending_under_12_m" class="text-xs text-muted-foreground/70 mt-0.5">----</p>
                    </div>
                </div>

            </div>
        </div>
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel h-4 w-4 text-primary" aria-hidden="true">
                            <path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path>
                        </svg>
                        <h4 data-slot="card-title" class="leading-none">Filters</h4>
                        <svg id="filter_loading" xmlns="http://www.w3.org/2000/svg" width="64" height="18" viewBox="0 0 64 18" aria-hidden="true" role="img" style="display: none;">
                            <circle cx="9" cy="9" r="6" fill="#6B7280">
                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0s"></animate>
                            </circle>
                            <circle cx="32" cy="9" r="6" fill="#6B7280">
                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.15s"></animate>
                            </circle>
                            <circle cx="55" cy="9" r="6" fill="#6B7280">
                                <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.3s"></animate>
                            </circle>
                        </svg>
                    </div>

                    <div class="flex items-center gap-3">
                        <div style="position:relative;display:inline-block;">

                            <button id="export_report" ; data-slot="dropdown-menu-trigger"
                                class="inline-flex btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 bg-primary hover:bg-primary/90"
                                type="button" id="radix-:rkg:" aria-haspopup="menu" aria-expanded="false"
                                data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                                    <path d="M12 15V3"></path>
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <path d="m7 10 5 5 5-5"></path>
                                </svg>Export Report<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                                    <path d="m6 9 6 6 6-6"></path>
                                </svg>
                            </button>

                            <div id="menu_export_report" class="menu-export" data-radix-popper-content-wrapper=""
                                dir="ltr"
                                style="display: none;position: absolute;left: 14px; top: 100%; margin-top: 4px; min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                                <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical"
                                    data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:"
                                    aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content"
                                    class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                                    tabindex="-1" data-orientation="vertical"
                                    style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                                    <div export_func="export_report" export_type="pdf" role="menuitem"
                                        data-slot="dropdown-menu-item" data-variant="default"
                                        class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                        tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                            </path>
                                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                            <path d="M10 9H8"></path>
                                            <path d="M16 13H8"></path>
                                            <path d="M16 17H8"></path>
                                        </svg>Export as PDF</div>
                                    <div export_func="export_report" export_type="csv" role="menuitem"
                                        data-slot="dropdown-menu-item" data-variant="default"
                                        class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                        tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg
                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                            </path>
                                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                            <path d="M10 9H8"></path>
                                            <path d="M16 13H8"></path>
                                            <path d="M16 17H8"></path>
                                        </svg>Export as CSV</div>
                                </div>
                            </div>
                        </div>
                        <?php $schedule_report->btn_schedule(); ?>
                    </div>

                </div>
            </div>
            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="text-xs text-gray-500 mb-1.5 block">Test Type</label>

                        <select id="sel_test_type" name="sel_test_type">
                            <?php foreach ($ci_all_btypes as $item): ?>
                                <option value="<?php echo $item; ?>"
                                    <?php if ($btype_selected == $item) { ?>selected<?php } ?>>
                                    <?php echo tr_pdd_get_typename($item); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>

                    <div>
                        <label class="text-xs text-gray-500 mb-1.5 block">Test Panels</label>
                        <select id="sel_panel" name="sel_panel">
                            <option value="all">All Panels</option>
                            <?php foreach ($all_panels as $id => $name): ?>
                                <option value="<?php echo $id; ?>"
                                    <?php if ($sel_panel == $id) { ?>selected<?php } ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 mb-1.5 block">Clinic</label>
                        <select id="sel_clinic" name="sel_clinic">
                            <option value="all">All Clinics</option>
                            <?php foreach ($clinics_rs as $item): ?>
                                <option value="<?php echo $item->cID; ?>"
                                    <?php if ($sel_clinic == $item->cID) { ?>selected<?php } ?>>
                                    <?php echo $item->cli_clinic; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500 mb-1.5 block">Status</label>

                        <select id="sel_status" name="sel_status">
                            <option value="all">All Statuses</option>
                            <?php foreach ($status as $key => $item): ?>
                                <option value="<?php echo $key; ?>"
                                    <?php if ($sel_status == $key) { ?>selected<?php } ?>>
                                    <?php echo $item['title']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>
                    <div>
                        <label class="text-xs text-gray-500 mb-1.5 block">Bucket</label>
                        <select id="sel_bucket" name="sel_bucket">
                            <option value="all">All Buckets</option>
                            <?php foreach ($bucket_filter as $key => $item): ?>
                                <option value="<?php echo $key; ?>"
                                    <?php if ($sel_bucket == $key) { ?>selected<?php } ?>>
                                    <?php echo $item['title']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 mb-1.5 block">Include &lt;12h</label>
                        <div class="flex items-center h-10">
                            <button id="btn_switch_include_12m" onclick="switch_include_12m();" class="cursor-pointer relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 bg-gray-300" role="switch" aria-checked="true">
                                <span id="btn_switch_include_12m_control" class="cursor-pointer inline-block h-4 w-4 transform rounded-full bg-white transition-transform translate-x-1"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php include "sample_delay_trends_historical_12m.php"; ?>

        <div class="grid grid-cols-2 gap-5">
            <?php include "sample_delay_trends_not_received_rate.php"; ?>
            <?php include "sample_delay_trends_ pending_rate.php"; ?>
        </div>

        <?php include "sample_delay_trends_clinic.php"; ?>

        <div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
            <p class="text-xs text-gray-600 text-center">
                <strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
                igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
            </p>
        </div>

    </div>
</div>

<script>
    var from_date = '<?php echo $from_date; ?>';
    var to_date = '<?php echo $to_date; ?>';
    var sel_clinic = '<?php echo $sel_clinic; ?>';
    var btype = '<?php echo $btype_selected; ?>';
    var sel_status = '<?php echo $sel_status; ?>';
    var sel_bucket = '<?php echo $sel_bucket; ?>';
    var sel_panel = '<?php echo $sel_panel; ?>';
    var url_main_page = 'ci_reports_sample_delay_trends';
    var historyData = null;
    var type_view_pending = 'stack';
    var type_view_not_received = 'stack';
    const delayBucketPalette = <?php echo wp_json_encode($delay_bucket_palette); ?>;

    function getDelayChartColor(statusKey, bucketKey) {
        return delayBucketPalette?.[statusKey]?.[bucketKey]?.chart || '#999999';
    }

    function goto_url(hold_panel = true) {
        let url = new URL(window.location.href);
        url.searchParams.set("btype", btype);
        url.searchParams.set("sel_clinic", sel_clinic);
        url.searchParams.set("sel_status", sel_status);
        url.searchParams.set("sel_bucket", sel_bucket);
        url.searchParams.set("is_include_12", is_include_m12 ? 1 : 0);
        if (hold_panel) {
            url.searchParams.set("sel_panel", sel_panel);
        } else {
            url.searchParams.delete("sel_panel");
        }
        window.location.href = url.toString();
    }

    function ui_control_switch(id_control, on, tr_x = '6') {
        if (on) {
            $(id_control).removeClass("bg-gray-300").addClass("bg-primary");
            $(id_control + "_control").removeClass("translate-x-1").addClass("translate-x-" + tr_x);
        } else {
            $(id_control).removeClass("bg-primary").addClass("bg-gray-300");
            $(id_control + "_control").removeClass("translate-x-" + tr_x).addClass("translate-x-1");
        }
    }

    function filterStatusBucketKeepColumns(row, sel_status, sel_bucket, type) {
        const bucketTypes = ["over_72", "48_72", "24_48", "12_24", "under_12"];
        const statusTypes = ["not_received", "pending"];

        let filtered = structuredClone(row);

        statusTypes.forEach(status => {
            bucketTypes.forEach(bucket => {
                const keepBucket = (sel_bucket === "all" || sel_bucket === bucket);
                const keepStatus = (type != '12' || sel_status === "all" || sel_status === status);

                const allow = keepBucket && keepStatus;

                // flat columns: pending_over_72, pct_pending_over_72
                const flatKey = `${status}_${bucket}`;
                const pctFlatKey = `pct_${status}_${bucket}`;

                if (!allow) {
                    if (flatKey in filtered) filtered[flatKey] = 0;
                    if (pctFlatKey in filtered) filtered[pctFlatKey] = 0;
                }

                // nested: pending[bucket], pct_pending[bucket]
                if (filtered[status] && bucket in filtered[status]) {
                    if (!allow) filtered[status][bucket] = 0;
                }
                const pctKey = `pct_${status}`;
                if (filtered[pctKey] && bucket in filtered[pctKey]) {
                    if (!allow) filtered[pctKey][bucket] = 0;
                }
            });
        });

        return filtered;
    }

    //Chart Type : 12 - not_recv - pending
    function loadDataChart(data_chart_filters, obj_chart, type = '12', is_include_12 = false, type_view = 'line') {

        let result = data_chart_filters.map(r =>
            filterStatusBucketKeepColumns(r, sel_status, sel_bucket, type)
        );
        data_chart_filters = result;
        if (type == '12') {
            var pending_over_72 = data_chart_filters.map(item => Number(item.pending_over_72));
            var pending_48_72 = data_chart_filters.map(item => Number(item.pending_48_72));
            var pending_24_48 = data_chart_filters.map(item => Number(item.pending_24_48));
            var pending_12_24 = data_chart_filters.map(item => Number(item.pending_12_24));
            var pending_under_12 = data_chart_filters.map(item => Number(item.pending_under_12));

            var not_over_72 = data_chart_filters.map(item => Number(item.not_received_over_72));
            var not_48_72 = data_chart_filters.map(item => Number(item.not_received_48_72));
            var not_24_48 = data_chart_filters.map(item => Number(item.not_received_24_48));
            var not_12_24 = data_chart_filters.map(item => Number(item.not_received_12_24));
            var not_under_12 = data_chart_filters.map(item => Number(item.not_received_under_12));

            var data_chart = [{
                    label: 'Not Received >72h',
                    data: not_over_72,
                    backgroundColor: getDelayChartColor('not_received', 'over_72'),
                    stack: 'pending',
                },
                {
                    label: 'Not Received 48-72h',
                    data: not_48_72,
                    backgroundColor: getDelayChartColor('not_received', '48_72'),
                    stack: 'pending',
                },
                {
                    label: 'Not Received 24-48h',
                    data: not_24_48,
                    backgroundColor: getDelayChartColor('not_received', '24_48'),
                    stack: 'pending',
                },
                {
                    label: 'Not Received 12-24h',
                    data: not_12_24,
                    backgroundColor: getDelayChartColor('not_received', '12_24'),
                    stack: 'pending',
                },
                {
                    label: 'Not Received <12h',
                    data: not_under_12,
                    backgroundColor: getDelayChartColor('not_received', 'under_12'),
                    stack: 'pending',
                },
                {
                    label: 'Pending >72h',
                    data: pending_over_72,
                    backgroundColor: getDelayChartColor('pending', 'over_72'),
                    stack: 'pending',
                },
                {
                    label: 'Pending 48-72h',
                    data: pending_48_72,
                    backgroundColor: getDelayChartColor('pending', '48_72'),
                    stack: 'pending',
                },
                {
                    label: 'Pending 24-48h',
                    data: pending_24_48,
                    backgroundColor: getDelayChartColor('pending', '24_48'),
                    stack: 'pending',
                },
                {
                    label: 'Pending 12-24h',
                    data: pending_12_24,
                    backgroundColor: getDelayChartColor('pending', '12_24'),
                    stack: 'pending',
                },
                {
                    label: 'Pending <12h',
                    data: pending_under_12,
                    backgroundColor: getDelayChartColor('pending', 'under_12'),
                    stack: 'pending',
                },
            ];
        }

        if (type == "pending") {
            var pending_over_72 = data_chart_filters.map(item => Number(item.pct_pending_over_72));
            var pending_48_72 = data_chart_filters.map(item => Number(item.pct_pending_48_72));
            var pending_24_48 = data_chart_filters.map(item => Number(item.pct_pending_24_48));
            var pending_12_24 = data_chart_filters.map(item => Number(item.pct_pending_12_24));
            var pending_under_12 = data_chart_filters.map(item => Number(item.pct_pending_under_12));

            var data_chart = [{
                    label: 'Pending >72h',
                    bucket: 'over_72',
                    data: pending_over_72,
                    borderColor: getDelayChartColor('pending', 'over_72'),
                    backgroundColor: getDelayChartColor('pending', 'over_72'),
                    tension: 0.3
                },
                {
                    label: 'Pending 48-72h',
                    bucket: '48_72',
                    data: pending_48_72,
                    borderColor: getDelayChartColor('pending', '48_72'),
                    backgroundColor: getDelayChartColor('pending', '48_72'),
                    tension: 0.3
                },
                {
                    label: 'Pending 24-48h',
                    bucket: '24_48',
                    data: pending_24_48,
                    borderColor: getDelayChartColor('pending', '24_48'),
                    backgroundColor: getDelayChartColor('pending', '24_48'),
                    tension: 0.3
                },
                {
                    label: 'Pending 12-24h',
                    bucket: '12_24',
                    data: pending_12_24,
                    borderColor: getDelayChartColor('pending', '12_24'),
                    backgroundColor: getDelayChartColor('pending', '12_24'),
                    tension: 0.3
                },
                {
                    label: 'Pending <12h',
                    bucket: 'under_12',
                    data: pending_under_12,
                    borderColor: getDelayChartColor('pending', 'under_12'),
                    backgroundColor: getDelayChartColor('pending', 'under_12'),
                    tension: 0.3
                }
            ];
        }

        if (type == "not_recv") {
            var not_over_72 = data_chart_filters.map(item => Number(item.pct_not_received_over_72));
            var not_48_72 = data_chart_filters.map(item => Number(item.pct_not_received_48_72));
            var not_24_48 = data_chart_filters.map(item => Number(item.pct_not_received_24_48));
            var not_12_24 = data_chart_filters.map(item => Number(item.pct_not_received_12_24));
            var not_under_12 = data_chart_filters.map(item => Number(item.pct_not_received_under_12));

            var data_chart = [{
                    label: 'Not Received >72h',
                    bucket: 'over_72',
                    data: not_over_72,
                    borderColor: getDelayChartColor('not_received', 'over_72'),
                    backgroundColor: getDelayChartColor('not_received', 'over_72'),
                    tension: 0.3
                },
                {
                    label: 'Not Received 48-72h',
                    bucket: '48_72',
                    data: not_48_72,
                    borderColor: getDelayChartColor('not_received', '48_72'),
                    backgroundColor: getDelayChartColor('not_received', '48_72'),
                    tension: 0.3
                },
                {
                    label: 'Not Received 24-48h',
                    bucket: '24_48',
                    data: not_24_48,
                    borderColor: getDelayChartColor('not_received', '24_48'),
                    backgroundColor: getDelayChartColor('not_received', '24_48'),
                    tension: 0.3
                },
                {
                    label: 'Not Received 12-24h',
                    bucket: '12_24',
                    data: not_12_24,
                    borderColor: getDelayChartColor('not_received', '12_24'),
                    backgroundColor: getDelayChartColor('not_received', '12_24'),
                    tension: 0.3
                },
                {
                    label: 'Not Received <12h',
                    bucket: 'under_12',
                    data: not_under_12,
                    borderColor: getDelayChartColor('not_received', 'under_12'),
                    backgroundColor: getDelayChartColor('not_received', 'under_12'),
                    tension: 0.3
                }
            ];
        }

        var labels = data_chart_filters.map(item => item.month_label);
        obj_chart.data.labels = labels;

        function hexToRgba(hex, alpha = 0.6) {
            const r = parseInt(hex.substring(1, 3), 16);
            const g = parseInt(hex.substring(3, 5), 16);
            const b = parseInt(hex.substring(5, 7), 16);
            return `rgba(${r},${g},${b},${alpha})`;
        }

        function setStacked(chart, enable = true) {
            if (!chart.options.scales) chart.options.scales = {};

            // X axis
            chart.options.scales.x = Object.assign(
                chart.options.scales.x || {}, {
                    stacked: enable
                }
            );

            // Y axis
            chart.options.scales.y = Object.assign(
                chart.options.scales.y || {}, {
                    stacked: enable,
                    max: 100,
                    beginAtZero: true
                }
            );
        }

        if (type_view == "line") {
            if (is_include_12) {
                obj_chart.data.datasets = data_chart;
            } else {
                obj_chart.data.datasets = data_chart.filter(item =>
                    item.label !== 'Not Received <12h' &&
                    item.label !== 'Pending <12h'
                );
            }
            if (type != "12") setStacked(obj_chart, false);
        } else {
            obj_chart.data.datasets = data_chart;
            var is_under_12 = false;
            data_chart.forEach(ds => {
                ds.backgroundColor = hexToRgba(ds.borderColor, 0.6);
                ds.fill = true;
                ds.stack = 'pending';
                ds.pointRadius = 0;
                ds.borderWidth = 1.2;
            });
            setStacked(obj_chart, true);
        }


        if (type == '12') applyRoundedTop(obj_chart);

        obj_chart.update();
    }

    function loadClinic(report_clinic, averages_clinic) {
        $("#all_clinic").empty();
        var item_average = `
                    <tr class="bg-gray-100 border-b border-gray-200">
                        <td class="py-3 px-4">
                            <div class="text-sm font-medium text-gray-900">Average (All Specimens)</div>
                        </td>
                        <td class="text-center py-3 px-4 text-sm text-gray-900">${averages_clinic.pct_over_72}%</td>
                        <td class="text-center py-3 px-4 text-sm text-gray-900">${averages_clinic.pct_48_72}%</td>
                        <td class="text-center py-3 px-4 text-sm text-gray-900">${averages_clinic.pct_24_48}%</td>
                        <td class="text-center py-3 px-4 text-sm text-gray-900">${averages_clinic.pct_12_24}%</td>
                        <td class="text-center py-3 px-4 text-sm text-gray-900">${averages_clinic.pct_under_12}%</td>
                        <td class="text-center py-3 px-4">
                            <div class="flex items-center justify-center gap-1.5">
                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground ${averages_clinic.color_under_24} text-xs">
                                   ${averages_clinic.pct_under_24}%
                                </span>
                                <div class="w-3.5"></div>
                            </div>
                        </td>
                        <td class="text-center py-3 px-4">
                            <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground ${averages_clinic.color_avg_month_total} text-xs">
                            ${averages_clinic.avg_month_total}%
                            </span>
                        </td>
                    </tr>
        `;
        $("#all_clinic").append(item_average);

        $.each(report_clinic, function(index, item) {
            const hasData = item.month_total > 0;
            const displayValue = item.months_with_data > 0 ? item.pct_under_24 + '%' : '-';
            const displayValue12m = item.months_with_data > 0 ? item.avg_month_total + '%' : '-';

            const compareIcon = hasData ? item.compare_under_24.icon : '';

            const item_row = `
                    <tr class="hover:bg-gray-50 border-b border-gray-200">
                        <td class="py-3 px-4"><div class="text-sm font-medium text-gray-900">${item.name}</div></td>
                        <td class="text-center py-3 px-4 text-sm text-gray-600">${hasData ? item.pct_over_72 + '%' : '-'}</td>
                        <td class="text-center py-3 px-4 text-sm text-gray-600">${hasData ? item.pct_48_72 + '%' : '-'}</td>
                        <td class="text-center py-3 px-4 text-sm text-gray-600">${hasData ? item.pct_24_48 + '%' : '-'}</td>
                        <td class="text-center py-3 px-4 text-sm text-gray-600">${hasData ? item.pct_12_24 + '%' : '-'}</td>
                        <td class="text-center py-3 px-4 text-sm text-gray-600">${hasData ? item.pct_under_12 + '%' : '-'}</td>
                        <td class="text-center py-3 px-4">
                            <div class="flex items-center justify-center gap-1.5">
                            ${hasData ? `
                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 ${item.compare_under_24.class} text-xs">
                                    ${displayValue}
                                </span>
                                ${compareIcon}
                            ` : '<span class="text-gray-400">-</span>'}
                            </div>
                        </td>
                        <td class="text-center py-3 px-4">
                            ${item.months_with_data > 0 ? `
                                <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 ${item.compare_under_24_12m.class} text-xs">
                                    ${displayValue12m}
                                </span>
                            ` : '<span class="text-gray-400">-</span>'}
                        </td>
                    </tr>
                `;
            $("#all_clinic").append(item_row);
        });

        $('#table_clinic th').off("click").click(function() {
            var table = $(this).parents('table').eq(0);
            var tbody = table.children('tbody');

            var allRows = tbody.find('tr').toArray();

            // 👉 giữ nguyên hàng đầu tiên
            var firstRow = allRows.shift();

            var rows = allRows;
            var index = $(this).index();
            const currentSortColumn = $('#table_clinic th').filter(function() {
                return $(this).data('is-sorting') === true;
            });

            var isSameColumn = currentSortColumn.length > 0 && currentSortColumn.index() === index;

            var asc = isSameColumn ? !$(this).data('asc') : true;

            $('#table_clinic th').find(".icon_sort").css("color", "#898787ff");
            $('#table_clinic th').find(".lucide-arrow-up-down").addClass("inline").show();
            $('#table_clinic th').find(".lucide-arrow-up").removeClass("inline").hide();
            $('#table_clinic th').find(".lucide-arrow-down").removeClass("inline").hide();

            $('#table_clinic th').data('is-sorting', false);
            $(this).find(".lucide-arrow-up-down").removeClass("inline").hide();
            $(this).find(".icon_sort").css("color", "#313131ff");

            rows.sort(function(a, b) {
                var cellA = $(a).children('td').eq(index);
                var cellB = $(b).children('td').eq(index);

                var A = cellA.text().trim();
                var B = cellB.text().trim();

                //CỘT ĐẦU TIÊN: luôn alphabetically
                if (index === 0) {
                    return asc ?
                        B.localeCompare(A, undefined, {
                            sensitivity: 'base'
                        }) :
                        A.localeCompare(B, undefined, {
                            sensitivity: 'base'
                        });
                }

                //CÁC CỘT KHÁC: ưu tiên numeric nếu có
                const numA = parseFloat(A.replace(/,/g, ''));
                const numB = parseFloat(B.replace(/,/g, ''));

                if (!isNaN(numA) && !isNaN(numB)) {
                    return asc ? numA - numB : numB - numA;
                }

                return asc ?
                    A.localeCompare(B, undefined, {
                        sensitivity: 'base'
                    }) :
                    B.localeCompare(A, undefined, {
                        sensitivity: 'base'
                    });
            });

            // 👉 append lại: hàng đầu tiên + các hàng đã sort
            tbody.empty().append(firstRow).append(rows);

            if (asc) {
                $(this).find(".lucide-arrow-down").addClass('inline').show();
            } else {
                $(this).find(".lucide-arrow-up").addClass('inline').show();
            }

            $(this).data('asc', asc);
            $(this).data('is-sorting', true);
        });
        hideLoading("box_clinic");
    }

    function loadClinicMonthOptions(options, selectedValue) {
        var select = $("#sel_clinic_month");
        if (!select.length) {
            return;
        }

        select.empty();
        if (!Array.isArray(options) || options.length === 0) {
            select.append(new Option("No months", "", true, true));
            select.prop("disabled", true);
            if (select.data("customized")) {
                refreshCustomSelect("sel_clinic_month");
            } else {
                initCustomSelect("#sel_clinic_month");
            }
            return;
        }

        select.prop("disabled", false);
        options.forEach(function(item) {
            var isSelected = item.value === selectedValue;
            select.append(new Option(item.label, item.value, isSelected, isSelected));
        });

        if (select.data("customized")) {
            refreshCustomSelect("sel_clinic_month");
        } else {
            initCustomSelect("#sel_clinic_month");
        }
    }

    function switch_include_12m() {
        if (is_include_m12 == false) {
            ui_control_switch("#btn_switch_include_12m", true);

            $("#box_not_received").removeClass("grid-cols-4").removeClass("gap-4");
            $("#box_not_received").addClass("grid-cols-5").addClass("gap-5");
            $("#box_not_12m").addClass("flex").show();

            $("#box_pending").removeClass("grid-cols-4").removeClass("gap-4");
            $("#box_pending").addClass("grid-cols-5").addClass("gap-5");
            $("#box_12m").addClass("flex").show();

            $("#chart_legend_include_12_a").addClass("flex").show();
            $("#chart_legend_include_12_b").addClass("flex").show();

            is_include_m12 = true;
            is_include_m12_rate = true;
        } else {
            ui_control_switch("#btn_switch_include_12m", false);

            $("#box_not_received").removeClass("grid-cols-5").removeClass("gap-5");
            $("#box_not_received").addClass("grid-cols-4").addClass("gap-4");
            $("#box_not_12m").removeClass("flex").hide();

            $("#box_pending").removeClass("grid-cols-5").removeClass("gap-5");
            $("#box_pending").addClass("grid-cols-4").addClass("gap-4");
            $("#box_12m").removeClass("flex").hide();

            $("#chart_legend_include_12_a").removeClass("flex").hide();
            $("#chart_legend_include_12_b").removeClass("flex").hide();

            is_include_m12 = false;
            is_include_m12_rate = false;
        }

        loadDataChart(historyData, chart_12_m, '12', is_include_m12);
        loadDataChart(historyData, chart_pending_12_m, 'pending', is_include_m12_rate, type_view_pending);
        loadDataChart(historyData, chart_notReceived_rate, 'not_recv', is_include_m12_rate, type_view_not_received);
    }

    function fmtPct(v) {
        return (v === null || v === undefined) ? 'N/A' : v + '%';
    }

    function LoadData() {
        showLoading("box_count_not_received_over_72");
        showLoading("box_count_not_received_48_72");
        showLoading("box_count_not_received_24_48");
        showLoading("box_count_not_received_12_24");
        showLoading("box_not_12m");

        showLoading("box_count_pending_over_72");
        showLoading("box_count_pending_48_72");
        showLoading("box_count_pending_24_48");
        showLoading("box_count_pending_12_24");
        showLoading("box_12m");

        showLoading("box_historical_12m");
        showLoading("box_not_received_rate");
        showLoading("box_pending_rate");
        showLoading("box_clinic");

        $("#filter_loading").show();
        $("#rate_not_received_over_72").html("--");
        $("#rate_not_received_over_72_m").html("----");

        $("#rate_not_received_48_72").html("--");
        $("#rate_not_received_48_72_m").html("----");

        $("#rate_not_received_24_48").html("--");
        $("#rate_not_received_24_48_m").html("----");

        $("#rate_not_received_12_24").html("--");
        $("#rate_not_received_12_24_m").html("----");

        $("#rate_not_received_under_12").html("--");
        $("#rate_not_received_under_12_m").html("----");

        $("#rate_pending_over_72").html("--");
        $("#rate_pending_over_72_m").html("----");

        $("#rate_pending_48_72").html("--");
        $("#rate_pending_48_72_m").html("----");

        $("#rate_pending_24_48").html("--");
        $("#rate_pending_24_48_m").html("----");

        $("#rate_pending_12_24").html("--");
        $("#rate_pending_12_24_m").html("----");

        $("#rate_pending_under_12").html("--");
        $("#rate_pending_under_12_m").html("----");

        jQuery.post("admin.php?page=" + url_main_page, {
            trajax: 'loadData',
            sub_ajax: 'loadData_count',
            sel_clinic: sel_clinic,
            btype: btype,
            sel_status: sel_status,
            sel_bucket: sel_bucket,
            sel_panel: sel_panel,
            sel_clinic_month: sel_clinic_month
        }, function(response) {
            var res = JSON.parse(response);
            if (res == null) return false;
            historyData = res.count_by_months;
            $("#filter_loading").hide();

            hideLoading("box_count_not_received_over_72");
            hideLoading("box_count_not_received_48_72");
            hideLoading("box_count_not_received_24_48");
            hideLoading("box_count_not_received_12_24");
            hideLoading("box_not_12m");

            hideLoading("box_count_pending_over_72");
            hideLoading("box_count_pending_48_72");
            hideLoading("box_count_pending_24_48");
            hideLoading("box_count_pending_12_24");
            hideLoading("box_12m");

            hideLoading("box_historical_12m");
            hideLoading("box_not_received_rate");
            hideLoading("box_pending_rate");

            $("#rate_not_received_over_72").html(fmtPct(res.avg_current.not_received_over_72));
            $("#rate_not_received_over_72_m").html(res.compare.not_received.over_72.icon + " vs 12 mo avg " + fmtPct(res.avg12.not_received.over_72));

            $("#rate_not_received_48_72").html(res.avg_current.not_received_48_72 + "%");
            $("#rate_not_received_48_72_m").html(res.compare.not_received["48_72"].icon + " vs 12 mo avg " + fmtPct(res.avg12.not_received["48_72"]));

            $("#rate_not_received_24_48").html(res.avg_current.not_received_24_48 + "%");
            $("#rate_not_received_24_48_m").html(res.compare.not_received["24_48"].icon + " vs 12 mo avg " + fmtPct(res.avg12.not_received["24_48"]));

            $("#rate_not_received_12_24").html(res.avg_current.not_received_12_24 + "%");
            $("#rate_not_received_12_24_m").html(res.compare.not_received["12_24"].icon + " vs 12 mo avg " + fmtPct(res.avg12.not_received["12_24"]));

            $("#rate_not_received_under_12").html(res.avg_current.not_received_under_12 + "%");
            $("#rate_not_received_under_12_m").html(res.compare.not_received["under_12"].icon + " vs 12 mo avg " + fmtPct(res.avg12.not_received["under_12"]));

            $("#rate_pending_over_72").html(res.avg_current.pending_over_72 + "%");
            $("#rate_pending_over_72_m").html(res.compare.pending["over_72"].icon + " vs 12 mo avg " + fmtPct(res.avg12.pending["over_72"]));

            $("#rate_pending_48_72").html(res.avg_current.pending_48_72 + "%");
            $("#rate_pending_48_72_m").html(res.compare.pending["48_72"].icon + " vs 12 mo avg " + fmtPct(res.avg12.pending["48_72"]));

            $("#rate_pending_24_48").html(res.avg_current.pending_24_48 + "%");
            $("#rate_pending_24_48_m").html(res.compare.pending["24_48"].icon + " vs 12 mo avg " + fmtPct(res.avg12.pending["24_48"]));

            $("#rate_pending_12_24").html(res.avg_current.pending_12_24 + "%");
            $("#rate_pending_12_24_m").html(res.compare.pending["12_24"].icon + " vs 12 mo avg " + fmtPct(res.avg12.pending["12_24"]));

            $("#rate_pending_under_12").html(res.avg_current.pending_under_12 + "%");
            $("#rate_pending_under_12_m").html(res.compare.pending["under_12"].icon + " vs 12 mo avg " + fmtPct(res.avg12.pending["under_12"]));

            $("#sel_panel").empty();
            $("#sel_panel").append(new Option("All Panels", "all"));

            if (res.all_panel && Object.keys(res.all_panel).length > 0) {
                $.each(res.all_panel, function(key, val) {
                    if (key == sel_panel) {
                        $("#sel_panel").append(new Option(val, key, true, true));
                    } else {
                        $("#sel_panel").append(new Option(val, key));
                    }
                });
            }

            refreshCustomSelect("sel_panel");
            $("#emp_sel_panel").find(".selected").html($("#sel_panel option:selected").text());

            loadClinicMonthOptions(res.clinic_month_options, res.selected_clinic_month);
            sel_clinic_month = res.selected_clinic_month;

            loadDataChart(historyData, chart_12_m, '12', is_include_m12);
            loadDataChart(historyData, chart_pending_12_m, 'pending', is_include_m12_rate, type_view_pending);
            loadDataChart(historyData, chart_notReceived_rate, 'not_recv', is_include_m12_rate, type_view_not_received);

            loadClinic(res.report_clinic, res.averages_clinic);
            $('.clinic_selected_month_text').html(res.selected_clinic_month_label);

            $('.current_month_text').html(res.avg_current.month_label);
        });
    }

    jQuery(function($) {
        window.$ = jQuery;
        update_ui_select();

        LoadData();

        if (is_include_m12) {
            ui_control_switch("#btn_switch_include_12m", true);
            $("#box_not_received").removeClass("grid-cols-4").removeClass("gap-4");
            $("#box_not_received").addClass("grid-cols-5").addClass("gap-5");
            $("#box_not_12m").addClass("flex").show();

            $("#box_pending").removeClass("grid-cols-4").removeClass("gap-4");
            $("#box_pending").addClass("grid-cols-5").addClass("gap-5");
            $("#box_12m").addClass("flex").show();

            $("#chart_legend_include_12_a").addClass("flex").show();
            $("#chart_legend_include_12_b").addClass("flex").show();

        }

        $(".btn-export").on("click", function(e) {
            e.stopPropagation();
            var id_btn = $(this).attr("id");
            $("#menu_" + id_btn).toggle();
        });

        $(document).on("click", function(e) {
            if (!$(e.target).closest(".custom-select").length) {
                $(".custom-select .options").hide();
            }

            if (!$(e.target).closest(".menu-export").length) {
                $(".menu-export").hide();
            }
        });

        $(".export_item").click(async function() {
            var export_func = $(this).attr("export_func");
            var export_type = $(this).attr("export_type");
            var $url = location.href + '&trajax=export_report&export_type=' + export_type;
            $url += '&export_func=' + export_func;
            $url += '&btype=' + btype;
            $url += '&sel_clinic=' + sel_clinic;
            $url += '&sel_status=' + sel_status;
            $url += '&sel_bucket=' + sel_bucket;
            $url += '&sel_panel=' + sel_panel;
            $url += '&sel_clinic_month=' + sel_clinic_month;
            $url += '&is_include_12=' + (is_include_m12 ? 1 : 0);
            var loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0, 'top_right');
            try {
                const response = await fetch($url);

                // 🔥 Lấy filename từ header
                const disposition = response.headers.get("Content-Disposition");
                let filename = "download." + export_type;

                if (disposition && disposition.includes("filename=")) {
                    const match = disposition.match(/filename="?([^"]+)"?/);
                    if (match && match[1]) filename = match[1];
                }

                const blob = await response.blob();

                const a = document.createElement("a");
                a.href = URL.createObjectURL(blob);
                a.download = filename; // giữ nguyên tên file
                a.click();

                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }

                showToast('success', 'Download ready!', 'Your file "' + filename +
                    '" has been downloaded.', 4000, 'top_right');

            } catch (err) {
                showToast('error', 'Error', 'Download failed.', 4000, 'top_right');
            }
        });

        $("#sel_test_type").change(function() {
            btype = $(this).val();
            sel_panel = 'all';
            LoadData();
        });

        $("#sel_status").change(function() {
            sel_status = $(this).val();
            loadDataChart(historyData, chart_12_m, '12', is_include_m12);
            loadDataChart(historyData, chart_pending_12_m, 'pending', is_include_m12_rate, type_view_pending);
            loadDataChart(historyData, chart_notReceived_rate, 'not_recv', is_include_m12_rate, type_view_not_received);
        });

        $("#sel_bucket").change(function() {
            sel_bucket = $(this).val();
            loadDataChart(historyData, chart_12_m, '12', is_include_m12);
            loadDataChart(historyData, chart_pending_12_m, 'pending', is_include_m12_rate, type_view_pending);
            loadDataChart(historyData, chart_notReceived_rate, 'not_recv', is_include_m12_rate, type_view_not_received);
        });

        $("#sel_clinic").change(function() {
            sel_clinic = $(this).val();
            LoadData();
        });

        $("#sel_panel").change(function() {
            sel_panel = $(this).val();
            LoadData();
        });

        $(document).on("change", "#sel_clinic_month", function() {
            sel_clinic_month = $(this).val();
            LoadData();
        });
    });

    function swith_type_view_chart(id_group_chart, type_view) {
        $("#btn_view_line_" + id_group_chart).removeClass("bg-white text-gray-900 shadow-sm").addClass("text-gray-600 hover:text-gray-900");
        $("#btn_view_stack_" + id_group_chart).removeClass("bg-white text-gray-900 shadow-sm").addClass("text-gray-600 hover:text-gray-900");
        $("#btn_view_" + type_view + "_" + id_group_chart).addClass("bg-white text-gray-900 shadow-sm").removeClass("text-gray-600 hover:text-gray-900");
        if (id_group_chart == "pending") {
            type_view_pending = type_view;
            loadDataChart(historyData, chart_pending_12_m, id_group_chart, is_include_m12, type_view_pending);
        }

        if (id_group_chart == "not_recv") {
            type_view_not_received = type_view;
            loadDataChart(historyData, chart_notReceived_rate, id_group_chart, is_include_m12, type_view_not_received);
        }
    }
</script>
<?php
$schedule_report->js();
?>