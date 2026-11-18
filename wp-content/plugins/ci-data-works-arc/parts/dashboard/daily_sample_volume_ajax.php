<?php
global $wpdb, $current_role;
$user_id = get_current_user_id();
$selDay = $_REQUEST['selDay'] ?? '14_days';
$btype          = $_REQUEST['btype'] ? $_REQUEST['btype'] : '';
if (empty($btype)) {
    $btype = reset(tr_pdd_get_batch_types());
}
$panel_id       = $_REQUEST['panel_id'] ? $_REQUEST['panel_id'] : 0;
$panel_label   = isset($_REQUEST['panel_label']) ? $_REQUEST['panel_label'] : 'All Panels';
$clinic            = $_REQUEST['clinic'] ? $_REQUEST['clinic'] : 0;
$cid_label        = isset($_REQUEST['cid_label']) ? $_REQUEST['cid_label'] : 'All Clinics';
$export         = isset($_REQUEST['export']);
$export_type    = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : 'csv';

$to_date   = date_i18n('Y-m-d'); // default to today

$report_time_label = "Last 14 Days"; // default label

if ($selDay == '14_days') {
    $from_date = date('Y-m-d', strtotime(date_i18n('Y-m-d')) - 13 * 86400);
    $report_time_label = "Last 14 Days";
} elseif ($selDay == '30_days') {
    $from_date = date('Y-m-d', strtotime(date_i18n('Y-m-d')) - 29 * 86400);
    $report_time_label = "Last 30 Days";
} elseif ($selDay == '90_days') {
    $from_date = date('Y-m-d', strtotime(date_i18n('Y-m-d')) - 89 * 86400);
    $report_time_label = "Last 90 Days";
} elseif ($selDay == 'customer_range') {
    $from_date = $_REQUEST['from'] ?? $to_date;
    $to_date   = $_REQUEST['to'] ?? $to_date;
    $report_time_label = date_i18n('m/d/Y', strtotime($from_date)) . " - " . date_i18n('m/d/Y', strtotime($to_date));
}

//get daily sample volumes data for chart
$daily_sample_volumes = [
    'labels' => [],
    'datasets' => [
        [
            'label' => "Completed",
            'data' => [],
            'backgroundColor' => "#10b981"
        ],
        [
            'label' => "Rejected",
            'data' => [],
            'backgroundColor' => "#f59e0b"
        ],
        [
            'label' => "Received",
            'data' => [],
            'backgroundColor' => "#9da2a8"
        ]
    ]
];
$panel_distribution = []; //Panel Breakdown Summary
$summary = []; //summary data

$period = new DatePeriod(
    new DateTime($from_date),
    new DateInterval('P1D'),
    (new DateTime($to_date))->modify('+1 day')
);
$where_global = "";
$where_panel = '';
if ($clinic > 0) $where_global .= " and ba.cli_ID in ({$clinic}) ";
if ($panel_id > 0) {
    if ($btype == 'tox') {
        $tests = $wpdb->get_col("select te_name from ci_test_panels where te_group_id = {$panel_id}");
        $where_tests = [];
        foreach ($tests as $t) {
            $where_tests[] = "find_in_set('{$t}', ba.panel_tests_requested)";
        }
        if (!empty($where_tests)) {
            $where_panel .= " AND ( " . implode(' OR ', $where_tests) . " ) ";
        }
    } elseif ($btype == 'study') {
        $where_panel .= " and find_in_set('{$panel_id}', ba.panel_tests_requested)";
    } else {
        $where_panel .= " and find_in_set('GROUP_{$panel_id}', ba.panel_tests_requested)";
    }
}

foreach ($period as $date) {
    $dailyfrom_date = $date->format('Y-m-d');
    $dailyto_date   = $dailyfrom_date . ' 23:59:59';
    $daily_batch_id = $date->format('Ymd');

    $bids = [$daily_batch_id];
    if (tr_get_option('ci_pdf_data', 'multi_batch_id')) {
        for ($i = 1; $i <= 10; $i++) {
            $bids[] = $daily_batch_id . '-' . $i;
        }
    }
    $bids_str = implode("','", $bids);
    $where_daily_batch_id = " and ba.batch_id in ('{$bids_str}') ";

    $dailyrp = [
        'in_processing' => 0,
        'resulted'      => 0,
        'rejected'      => 0,
    ];

    // $dailywhere_plate    = " and pl.created >= '{$dailyfrom_date}' and pl.created <= '{$dailyto_date}' ";


    $batch_tbl  = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);


    // In process
    $dailyrp['in_processing'] = (int) $wpdb->get_var("
            select count(distinct ba.mid)
            from {$batch_tbl} ba
            LEFT join {$report_tbl} re on ba.assen = re.patient_id and re.estatus = 'C'
            where 1=1
            {$where_daily_batch_id}
            and (re.id is null or re.estatus != 'C')
            {$where_panel} {$where_global}
        ");

    // Completed
    $dailyrp['resulted'] = (int) $wpdb->get_var("
            select count(*) from (
                select ba.mid
                from {$batch_tbl} ba
                join {$report_tbl} re on ba.assen = re.patient_id
                where 1=1
                {$where_daily_batch_id}
                and re.estatus = 'C'
                {$where_panel}
                {$where_global}
                group by ba.mid
            ) as rs
        ");

    // Rejected
    $dailyrp['rejected'] = (int) $wpdb->get_var("
            select count(*)
            from {$batch_tbl} ba
            where 1=1
            {$where_daily_batch_id}
            and " . tr_pdd_where_batch_rejected(false, false) . "
            {$where_panel}
            {$where_global}
        ");


    $daily_sample_volumes['labels'][]                = $date->format('m/d');
    $daily_sample_volumes['datasets'][0]['data'][]   = $dailyrp['resulted'];
    $daily_sample_volumes['datasets'][1]['data'][]   = $dailyrp['rejected'];
    $daily_sample_volumes['datasets'][2]['data'][]   = $dailyrp['in_processing'];
}


//summary
$total_completed = array_sum($daily_sample_volumes['datasets'][0]['data']);
$total_rejected  = array_sum($daily_sample_volumes['datasets'][1]['data']);
$total_inprocess = array_sum($daily_sample_volumes['datasets'][2]['data']);

$total_samples   = $total_completed;

// Số ngày trong kỳ
$total_days = count($daily_sample_volumes['labels']);
$average_daily = $total_days > 0 ? round($total_samples / $total_days, 1) : 0;

// Tỷ lệ %
$success_rate   = $total_samples > 0 ? round(($total_completed / $total_samples) * 100, 1) : 0;
$rejection_rate = $total_samples > 0 ? round(($total_rejected / $total_samples) * 100, 1) : 0;


//Report By Panel Distribution
if ($btype === 'study') {
    $panel_groups = $wpdb->get_results("
        select ID as tg_ID,
        abrev as tg_name,
        abrev as tg_short_name
        from ci_study_tests
        order by ID asc
    ");
} else {
    $panel_groups = $wpdb->get_results("
        select tg_ID, 
        REPLACE(tg_name,'(new)','') as tg_name,
        tg_short_name
        from ci_test_groups 
        where tg_parent = 0 and tg_show_req = 1 and tg_type = '{$btype}'
        order by tg_position asc
    ");
}

$from_batch_id  = date('Ymd', strtotime($from_date));
$to_batch_id    = date('Ymd', strtotime($to_date));

if (tr_get_option('ci_pdf_data', 'multi_batch_id')) {
    $all_bids = [];
    $period_tmp = new DatePeriod(
        new DateTime($from_date),
        new DateInterval('P1D'),
        (new DateTime($to_date))->modify('+1 day')
    );
    foreach ($period_tmp as $d) {
        $bid = $d->format('Ymd');
        $all_bids[] = $bid;
        for ($i = 1; $i <= 10; $i++) {
            $all_bids[] = $bid . '-' . $i;
        }
    }
    $all_bids_str = implode("','", $all_bids);
    $where_batch_id = " and ba.batch_id in ('{$all_bids_str}') ";
} else {
    $where_batch_id = " and ba.batch_id >= {$from_batch_id} and ba.batch_id <= {$to_batch_id} ";
}
$total_days     = count($daily_sample_volumes['labels']);
$total_all_panels = 0;
foreach ($panel_groups as $pg) {
    if ($btype == 'tox') {
        $tests = $wpdb->get_col("select te_name from ci_test_panels where te_group_id = {$pg->tg_ID}");
        $where_tests = [];
        foreach ($tests as $t) {
            $where_tests[] = "find_in_set('{$t}', ba.panel_tests_requested)";
        }
        $where_panel_dist = !empty($where_tests) ? " AND ( " . implode(' OR ', $where_tests) . " ) " : " AND 1=0 ";
    } elseif ($btype == 'study') {
        $where_panel_dist = " and find_in_set('{$pg->tg_ID}', ba.panel_tests_requested)";
    } else {
        $where_panel_dist = " and find_in_set('GROUP_{$pg->tg_ID}', ba.panel_tests_requested)";
    }

    $count_completed = (int) $wpdb->get_var("
        select count(distinct ba.mid)
        from " . tr_pdd_get_table_by_btype($btype) . " ba
        join " . tr_pdd_get_table_report_by_btype($btype) . " re 
            on ba.assen = re.patient_id
        where re.estatus = 'C'
        {$where_panel_dist}
        {$where_global}
        {$where_batch_id}
    ");

    $daily_avg = $total_days > 0 ? round($count_completed / $total_days, 1) : 0;
    $total_all_panels += $count_completed;

    if (empty($pg->tg_short_name)) {
        $pg->tg_short_name =  $pg->tg_name;
    }
    $panel_distribution[] = [
        'name'       => $pg->tg_short_name,
        'short_name' => $pg->tg_short_name,
        'selected'   => $pg->tg_ID == $panel_id,
        'count'      => $count_completed,
        'percent'    => 0,
        'daily_avg'  => $daily_avg
    ];
}

foreach ($panel_distribution as $idis => $dis) {
    $panel_distribution[$idis]['percent'] = $total_all_panels > 0 ? round(($dis['count'] / $total_all_panels) * 100, 1) : 0;
}

$average_daily_all_panel = $total_days > 0 ? round($total_all_panels / $total_days, 1) : 0;

$summary = [
    'total_completed' => $total_completed,
    'success_rate'   => $success_rate,
    'total_rejected' => $total_rejected,
    'total_samples'  => $total_samples,
    'average_daily'  => $average_daily,
    'rejection_rate' => $rejection_rate,
    'total_all_panels' => $total_all_panels,
    'average_daily_all_panel' => $average_daily_all_panel,
];


$daily_panel_rows = []; // lưu dữ liệu cho table

foreach ($period as $date) {
    $daily_batch_id = $date->format('Ymd');
    $bids = [$daily_batch_id];
    if (tr_get_option('ci_pdf_data', 'multi_batch_id')) {
        for ($i = 1; $i <= 10; $i++) {
            $bids[] = $daily_batch_id . '-' . $i;
        }
    }
    $bids_str = implode("','", $bids);
    $where_daily_batch_id = " and ba.batch_id in ('{$bids_str}') ";

    $row = [
        'date' => $date->format('n/j'),
        'panels' => [],
        'total' => 0
    ];

    foreach ($panel_groups as $p) {
        if ($btype == 'tox') {
            $tests = $wpdb->get_col("select te_name from ci_test_panels where te_group_id = {$p->tg_ID}");
            $where_tests = [];
            foreach ($tests as $t) {
                $where_tests[] = "find_in_set('{$t}', ba.panel_tests_requested)";
            }
            $where_panel_row = !empty($where_tests) ? " AND ( " . implode(' OR ', $where_tests) . " ) " : " AND 1=0 ";
        } elseif ($btype == 'study') {
            $where_panel_row = " and find_in_set('{$p->tg_ID}', ba.panel_tests_requested)";
        } else {
            $where_panel_row = " and find_in_set('GROUP_{$p->tg_ID}', ba.panel_tests_requested)";
        }

        $count_completed = (int) $wpdb->get_var("
            select count(distinct ba.mid)
            from " . tr_pdd_get_table_by_btype($btype) . " ba
            join " . tr_pdd_get_table_report_by_btype($btype) . " re 
                on ba.assen = re.patient_id
            where 1=1
                {$where_daily_batch_id}
            and re.estatus = 'C'
            {$where_panel_row}
            {$where_global}
        ");

        if (empty($p->tg_short_name)) $p->tg_short_name = $p->tg_name;
        $row['panels'][$p->tg_short_name] = $count_completed;
        $row['total'] += $count_completed;
    }

    $daily_panel_rows[] = $row;
}


if ($export && $export_type === 'pdf') {
    trpdd_log_user(get_current_user_id(), 'download_report', 'Daily Sample Volume Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    ob_start();
    require TRCPDD_PATH . 'parts/dashboard/daily_sample_volume_temp_pdf.php';
    $html = ob_get_clean();
    $outfile = generate_report_filename('DASH', 'DailySampleVolume', $from_date, $to_date, 'pdf');
    //$orientation = (count($panel_distribution) > 6) ? 'landscape' : 'portrait';
    trpdd_build_pdf($html, [
        'size'        => 'letter',
        'orientation' => 'portrait',
        'filename'    => $outfile,
        'download'  => 1,
        'compress' => 1
    ]);
    exit;
}

if ($export && $export_type === 'csv') {
    trpdd_log_user(get_current_user_id(), 'download_report', 'Daily Sample Volume Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    $filename = generate_report_filename('DASH', 'DailySampleVolume', $from_date, $to_date, 'csv');

    tr_pdd_header('csv', $filename);
    $f = fopen('php://output', 'w');

    // ===== HEADER INFO =====
    fputcsv($f, [$labname . " - Daily Report"]);
    fputcsv($f, ["Generated on:", date_i18n("m/d/Y h:i:s A")]);
    fputcsv($f, []);
    fputcsv($f, ["Filter Settings"]);
    fputcsv($f, ["Date Range", $report_time_label]);
    fputcsv($f, ["Test Type", strtoupper($btype)]);
    fputcsv($f, ["Panels", $panel_label]);
    fputcsv($f, ["Clinic", $cid_label]);
    fputcsv($f, []);

    // =====Panel Breakdown Summary=====
    fputcsv($f, []);
    fputcsv($f, ["Panel Breakdown Summary"]);
    fputcsv($f, ["Panel", "Total", "Percent", "Daily Avg"]);

    //Tính lấy 6 panel có tổng số cao nhất
    usort($panel_distribution, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    //$panel_distribution = array_slice($panel_distribution, 0, 6);

    foreach ($panel_distribution as $pd) {
        $row = [
            $pd['name'],
            $pd['count'],
            $pd['percent'] . '%',
            $pd['daily_avg']
        ];
        fputcsv($f, $row);
    }

    // =====Daily Volume by Panel=====
    $panel_totals = [];
    foreach ($daily_panel_rows as $row) {
        foreach ($row['panels'] as $panelName => $val) {
            $panel_totals[$panelName] = ($panel_totals[$panelName] ?? 0) + $val;
        }
    }
    // sort desc
    arsort($panel_totals);
    // lấy tối đa 20 panels
    $top_panels = array_slice(array_keys($panel_totals), 0, 20, true);

    fputcsv($f, []);
    fputcsv($f, ["Daily Volume by Panel Type"]);

    $header = ["Date"];
    foreach ($top_panels as $panelName) {
        $header[] = $panelName;
    }
    $header[] = "Total";
    fputcsv($f, $header);

    // data rows
    foreach ($daily_panel_rows as $row) {
        $csvRow = [$row['date']];
        foreach ($top_panels as $panelName) {
            $csvRow[] = $row['panels'][$panelName] ?? 0;
        }
        $csvRow[] = $row['total'];
        fputcsv($f, $csvRow);
    }
    fputcsv($f, []);

    // ===== DAILY VOLUME=====
    fputcsv($f, ["Daily Volume"]);
    fputcsv($f, ["Date", "Completed", "Rejected", "Received", "Total"]);

    foreach ($daily_sample_volumes['labels'] as $i => $label) {
        $row = [
            $label,
            $daily_sample_volumes['datasets'][0]['data'][$i] ?? 0,
            $daily_sample_volumes['datasets'][1]['data'][$i] ?? 0,
            $daily_sample_volumes['datasets'][2]['data'][$i] ?? 0,
            ($daily_sample_volumes['datasets'][0]['data'][$i] ?? 0) +
                ($daily_sample_volumes['datasets'][1]['data'][$i] ?? 0) +
                ($daily_sample_volumes['datasets'][2]['data'][$i] ?? 0)
        ];
        fputcsv($f, $row);
    }

    fclose($f);
    exit;
}

$dailys = array();
$dailys['data'] = $daily_sample_volumes;
$emp_dropw_down = '<option value="">All Panels</option>';


foreach ($panel_groups as $panel) {
    if ($panel_id == $panel->tg_ID) {
        $emp_dropw_down .= '<option value="' . $panel->tg_ID . '" selected="selected">' . $panel->tg_name . '</option>';
    } else {
        $emp_dropw_down .= '<option value="' . $panel->tg_ID . '">' . $panel->tg_name . '</option>';
    }
}
$dailys['panels_emp']           = $emp_dropw_down;
$dailys['summary']              = $summary;
$dailys['panel_distribution']   = $panel_distribution;
ob_start();
require TRCPDD_PATH . 'parts/dashboard/daily_sample_volume_live_report.php';
$html_live_report = ob_get_clean();
$dailys['live_report']          = $html_live_report;
echo json_encode($dailys);
