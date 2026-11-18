<?php
global $wpdb, $current_role, $ci_all_btypes;
$schedule_report = new CI_Report_Schedule('providers', 'Provider Report');

$from_date = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-1');
$to_date = !empty($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
$sel_date_rep = isset($_REQUEST['sel_date_rep']) ? $_REQUEST['sel_date_rep'] : '90_days';
$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$text_sel_date_rep = 'Customer';
if ($sel_date_rep === 'custom') {
    $from_date = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-01');
    $to_date = !empty($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');
    $text_sel_date_rep = date_i18n('M j', strtotime($from_date)) . " - " . date_i18n('M j', strtotime($to_date));
} else {
    $to_date = date_i18n('Y-m-d');
    switch ($sel_date_rep) {
        case '7_days':
            $text_sel_date_rep = 'Last 7 days';
            $from_date = date_i18n('Y-m-d', strtotime('-7 days', strtotime($to_date)));
            break;
        case '14_days':
            $text_sel_date_rep = 'Last 14 days';
            $from_date = date_i18n('Y-m-d', strtotime('-14 days', strtotime($to_date)));
            break;
        case '30_days':
            $text_sel_date_rep = 'Last 30 days';
            $from_date = date_i18n('Y-m-d', strtotime('-30 days', strtotime($to_date)));
            break;
        case '90_days':
            $text_sel_date_rep = 'Last 90 days';
            $from_date = date_i18n('Y-m-d', strtotime('-90 days', strtotime($to_date)));
            break;
        default:
            $from_date = date_i18n('Y-m-01');
            break;
    }
}

$filter_date_select = [];
$filter_date_select[] = ['value' => '7_days', 'text' => '7 days'];
$filter_date_select[] = ['value' => '14_days', 'text' => '14 days'];
$filter_date_select[] = ['value' => '30_days', 'text' => '30 days'];
$filter_date_select[] = ['value' => '90_days', 'text' => '90 days'];
$filter_date_select[] = ['value' => 'custom', 'text' => 'Custom'];

$user_id = get_current_user_id();


$batch_tbl = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);

if (!function_exists('get_previous_period')) {
    function get_previous_period($sel_date_rep, $from_date, $to_date)
    {
        $prev_from_date = $from_date;
        $prev_to_date = $to_date;

        switch ($sel_date_rep) {
            case '7_days':
            case '14_days':
            case '30_days':
            case '90_days':
                // Độ dài kỳ hiện tại
                $days = (strtotime($to_date) - strtotime($from_date)) / 86400 + 1;
                $prev_to_date = date('Y-m-d', strtotime('-1 day', strtotime($from_date)));
                $prev_from_date = date('Y-m-d', strtotime("-" . ($days - 1) . " days", strtotime($prev_to_date)));
                break;

            case 'custom':
                // Nếu custom, dùng cùng độ dài kỳ trước
                $days = (strtotime($to_date) - strtotime($from_date)) / 86400 + 1;
                $prev_to_date = date('Y-m-d', strtotime('-1 day', strtotime($from_date)));
                $prev_from_date = date('Y-m-d', strtotime("-" . ($days - 1) . " days", strtotime($prev_to_date)));
                break;

            default:
                // Mặc định dùng 90 ngày
                $days = 90;
                $prev_to_date = date('Y-m-d', strtotime('-90 days'));
                $prev_from_date = date('Y-m-d', strtotime('-179 days')); // 90 ngày trước đó
                break;
        }

        return [
            'prev_from_date' => $prev_from_date,
            'prev_to_date' => $prev_to_date,
        ];
    }
}

if (!function_exists('get_gradient_color')) {
    function get_gradient_color($value, $min = 0, $max = 3000)
    {
        if ($value <= $min)
            return 'rgb(244, 63, 94)'; // màu yếu nhất
        if ($value >= $max)
            return 'rgb(16, 185, 129)'; // màu mạnh nhất

        $colors = [
            'rgb(244, 63, 94)',   // 1
            'rgb(236, 72, 153)',  // 2
            'rgb(245, 158, 11)',  // 3
            'rgb(132, 204, 22)',  // 4
            'rgb(6, 182, 212)',   // 5
            'rgb(59, 130, 246)',  // 6
            'rgb(139, 92, 246)',  // 7
            'rgb(16, 185, 129)'   // 8
        ];

        // Tính ratio 0–1
        $ratio = ($value - $min) / ($max - $min);

        // Tính index màu theo tỉ lệ
        $idx = floor($ratio * (count($colors) - 1));

        return $colors[$idx];
    }
}

$all_panels = array();
$all_labs = array();
$all_tests = array();

if ($btype_selected == 'tox') {
    $rs = $wpdb->get_results("select te_group_id , te_name from ci_test_panels where te_type = '{$btype_selected}' ");
    foreach ($rs as $r) {
        $all_tests[strtoupper($r->te_name)] = $r->te_group_id;
    }
}

if ($btype_selected == 'study') {
    $rs = $wpdb->get_results("select ID as tg_ID, abrev as tg_name from ci_study_tests order by ID asc");
} else {
    $rs = $wpdb->get_results("select tg_ID, tg_name
                    from ci_test_groups 
                    where tg_type = '{$btype_selected}' 
                    order by tg_position asc");
}


foreach ($rs as $r) {
    $all_panels[$r->tg_ID] = $r->tg_name;
}

$rs = $wpdb->get_results("select * from ci_labs");
foreach ($rs as $r) {
    $all_labs[$r->lab_ID] = $r;
}


$main_groups = $wpdb->get_results("
select tg_ID, tg_name, tg_other_options
from ci_test_groups
where tg_parent = 0
and tg_type = '$btype_selected'
order by tg_position asc
");

foreach ($main_groups as $g) {
    $tabkey = 'uti';
    $other_options = json_decode($g->tg_other_options, true);

    if (isset($other_options['abx_type']) && !empty($other_options['abx_type'])) {
        $tabkey = $other_options['abx_type'];
    } else if (stripos($g->tg_name, 'wound') !== false) {
        $tabkey = 'wound';
    } else if (stripos($g->tg_name, 'gi') !== false) {
        $tabkey = 'gi';
    } else if (stripos($g->tg_name, 'Respiratory') !== false || stripos($g->tg_name, 'rpp') !== false) {
        $tabkey = 'r';
    }

    if (in_array($tabkey, array('sm', 'sr', 'es'))) {
        $tabkey = 'sti';
    }

    $panels[$tabkey]['groupids'][] = $g->tg_ID;
}

$rerunfield = 'SUM(re.covid_pos) AS count_rerun';
if ($btype_selected == 'pcr') {
    $rerunfield = 'SUM(re.rerun_count) AS count_rerun';
}


$clinics = $wpdb->get_results("
SELECT 
    c.cID,
    c.cli_clinic,
    c.cli_status,

    -- Đếm batch theo clinic
    COUNT(ba.cli_ID) AS total_batches,

SUM(ba.batch_status = 'received') AS count_rejected,

$rerunfield ,

    -- Các field chi tiết từ batch table
    ba.batch_status,
    ba.spec_received,
    ba.panel_tests_requested,

    -- Các field từ report
    re.approve_date,
    re.estatus

FROM ci_clinic c

-- JOIN batch
LEFT JOIN {$batch_tbl} ba 
    ON ba.cli_ID = c.cID
    AND ba.is_saved = 0
    AND ba.doc BETWEEN '{$from_date}' AND '{$to_date}'

-- JOIN report
LEFT JOIN {$report_tbl} re 
    ON re.patient_id = ba.assen

GROUP BY 
    c.cID

ORDER BY c.cli_clinic ASC
");

$count_clinic_active = 0;
$count_sample = 0;

$top_provider_panel = null;
$top_provider_val = 0;

$highest_growth_pane = null;
$highest_growth_val = 0;

$top_panel = null;

$clinic_summary = [];

$date_prev = get_previous_period($sel_date_rep, $from_date, $to_date);

$prev_from_date = $date_prev['prev_from_date'];
$prev_to_date = $date_prev['prev_to_date'];

$total_samples_volume = 0;
$total_avg_tat = 0;
$total_avg_success_rate = 0;
$count_clinics_with_data = 0;

foreach ($clinics as $clinic) {

    $cID = intval($clinic->cID);
    $clinic_name = $clinic->cli_clinic;

    $panel_counts = [];
    $count_samples_volume = 0;

    $tat_count = 0;
    $tat_total_hours = 0;

    $count_rejected = 0;
    $count_rerun = 0;

    if ($clinic->cli_status == 1)
        $count_clinic_active++;

    $groupsids = array();
    foreach (explode(',', $clinic->panel_tests_requested) as $t) {
        if (stripos($t, 'GROUP_') !== false) {
            $gid = str_replace('GROUP_', '', $t);
            if (!in_array($gid, $groupsids)) {
                $groupsids[] = $gid;
            }
        } else if ($btype_selected == 'tox' && isset($all_tests[strtoupper($t)])) {
            $gid = $all_tests[strtoupper($t)];

            if (!in_array($gid, $groupsids)) {
                $groupsids[] = $gid;
            }
        } else if ($btype_selected == 'study' && is_numeric($t)) {
            if (!in_array($t, $groupsids)) {
                $groupsids[] = $t;
            }
        }
    }

    foreach ($groupsids as $panelid) {
        if (!isset($panel_counts[$panelid])) {
            $panel_counts[$panelid] = 0;
        }
        $panel_counts[$panelid]++;
    }

    if (!empty($panel_counts)) {
        $top_panel_id = array_keys($panel_counts, max($panel_counts))[0];
        $top_panel = $all_panels[$top_panel_id] ?? 'Unknown';
    } else {
        $top_panel = 'None';
    }


    $count_total = intval($clinic->total_batches);

    $sql_prev_count = "
    SELECT 
        COUNT(ba.cli_ID) AS total_batches
    FROM {$batch_tbl} ba
    WHERE ba.is_saved = 0
        AND ba.doc BETWEEN '{$prev_from_date}' AND '{$prev_to_date}'
        AND ba.cli_ID = {$cID}
        {$where_group_sql}
";

    $row_prev = $wpdb->get_row($sql_prev_count);
    $count_total_prev = intval($row_prev->total_batches);

    $received = $clinic->spec_received;
    $completed = $clinic->approve_date;
    // Calculate TAT hrs

    if (!empty($received) && !empty($completed)) {
        $tat_hours = 0;

        if ($completed > $received)
            $tat_hours = (strtotime($completed) - strtotime($received)) / 3600;
        else
            $tat_hours = (strtotime($received) - strtotime($completed)) / 3600;

        if ($count_total > 0) {
            $tat_total_hours += $tat_hours / $count_total;
        }
        $tat_count++;
        $total_avg_tat += $tat_total_hours;
    }

    if ($clinic->batch_status === 'received') {
        $count_rejected++;
    }

    $count_samples_volume += $count_total;
    $total_samples_volume += $count_total;

    if ($top_provider_panel === null || $count_samples_volume > $top_provider_val) {
        $top_provider_val = $count_samples_volume;
        $top_provider_panel = $clinic_name;
    }

    if ($count_total > 0) {
        $success_rate = ($count_total - $clinic->count_rejected - $clinic->count_rerun) / $count_total * 100;
        $total_avg_success_rate += $success_rate;
        $count_clinics_with_data++;
    } else {
        $success_rate = 0;
    }

    if ($count_total_prev == 0) {
        $growth = 0;
    } else {
        $growth = ($count_samples_volume - $count_total_prev) / $count_total_prev * 100;
    }

    if ($highest_growth_pane === null || $growth > $highest_growth_val) {
        $highest_growth_pane = $clinic_name;
        $highest_growth_val = $growth;
    }

    $clinic_summary[] = [
        'clinic' => $clinic_name,
        'total_volume' => $count_samples_volume,
        'top_panel' => $top_panel,
        'panels' => $panel_counts,
        'avg_tat' => $tat_total_hours,
        'success_rate' => $success_rate,
        'growth' => $growth
    ];
}

if ($count_clinics_with_data > 0) {
    $total_avg_success_rate = $total_avg_success_rate / $count_clinics_with_data;
} else {
    $total_avg_success_rate = 0;
}

$schedule_report->ajax();

if ($export_func != '') {
    if ($filepath_save_csv == '' && $filepath_save_pdf == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Provider Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    }
    if ($export_type == "pdf" || $filepath_save_pdf != "") {
        $orientation = 'portrait';
        $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
        $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
        ob_start();
        $outfile = CI_Base_Lab::get_current_lab_init() . '-igniteABX Providers Report -' . $period_export . '.pdf';
        require TRCPDD_PATH . 'parts/reports/providers_pdf.php';
        $html = ob_get_clean();
        if ($filepath_save_pdf != '') {
            if (function_exists('trdomepdf_build_pdf')) {
                $result = trdomepdf_build_pdf($html, [
                    'size' => 'letter',
                    'orientation' => $orientation,
                    'filepath' => $filepath_save_pdf,
                    'download' => 0,
                    'compress' => 0,
                    'dpi' => 110
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

    if ($export_type == "csv" || $filepath_save_csv != "") {
        $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
        $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
        $outfile = CI_Base_Lab::get_current_lab_init() . ' IGN_Providers_report_' . $period_export . '.csv';
        tr_pdd_header('csv', $outfile);
        if ($filepath_save_csv != '') {
            $f = fopen($filepath_save_csv, 'w');
            $csv_out_file = basename($outfile);
        } else {
            $f = fopen('php://output', 'w');
        }
        fputcsv($f, ["Provider", "Sample Count", "Avg TAT (hrs)", "Success Rate", "Top Panel", "Growth"]);
        foreach ($clinic_summary as $c) {
            $s_val_growth = 0;
            if ($c['growth'] > 0)
                $s_val_growth = '+' . number_format($c['growth'], 2);
            else
                $s_val_growth = number_format($c['growth'], 2);

            fputcsv(
                $f,
                [
                    $c['clinic'],
                    $c['total_volume'],
                    number_format($c['avg_tat'], 2),
                    number_format($c['success_rate'], 2),
                    $c['top_panel'],
                    $s_val_growth
                ]
            );
        }
        fclose($f);
        if ($filepath_save_csv == '') exit;
    }
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
    return;
}

$volumes = array_column($clinic_summary, 'total_volume');
$min_volume = min($volumes);
$max_volume = max($volumes);

if ($total_samples_volume <= 0) {
    $total_avg_tat_all_panel = 0;
} else {
    $total_avg_tat_all_panel = $total_avg_tat / $total_samples_volume;
}

?>
<style>
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
</style>
<?php $schedule_report->style(); ?>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
    <div class="space-y-5">
        <div class="flex items-center gap-4">
            <button onclick="window.location.href='admin.php?page=ci_reports_main'" data-slot="button"
                class="inline-flex items-center justify-center cursor-pointer gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent dark:hover:bg-accent/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 text-muted-foreground hover:text-primary"><svg
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-arrow-left h-4 w-4 mr-2" aria-hidden="true">
                    <path d="m12 19-7-7 7-7"></path>
                    <path d="M19 12H5"></path>
                </svg>Back to Reports
            </button>
            <div class="h-6 w-px bg-border"></div>
            <div>
                <h1 class="text-2xl font-semibold">Provider Report</h1>
                <p class="text-sm text-muted-foreground">View test volume and performance metrics by healthcare provider
                </p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">Total Samples</p>
                            <p class="text-2xl font-bold"><?php echo $total_samples_volume; ?></p>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-file-text h-6 w-6 text-blue-600" aria-hidden="true">
                                <path
                                    d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
                                </path>
                                <path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
                                <path d="M10 9H8"></path>
                                <path d="M16 13H8"></path>
                                <path d="M16 17H8"></path>
                            </svg></div>
                    </div>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">Active Providers</p>
                            <p class="text-2xl font-bold"><?php echo $count_clinic_active; ?></p>
                        </div>
                        <div class="p-3 bg-purple-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-users h-6 w-6 text-purple-600" aria-hidden="true">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg></div>
                    </div>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">Avg per Provider</p>
                            <p class="text-2xl font-bold"><?php echo number_format($total_avg_tat, 2); ?></p>
                        </div>
                        <div class="p-3 bg-green-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-building2 lucide-building-2 h-6 w-6 text-green-600"
                                aria-hidden="true">
                                <path d="M10 12h4"></path>
                                <path d="M10 8h4"></path>
                                <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                                <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2">
                                </path>
                                <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                            </svg></div>
                    </div>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-muted-foreground">Avg Success Rate</p>
                            <p class="text-2xl font-bold"><?php echo number_format($total_avg_success_rate, 2); ?>%</p>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-trending-up h-6 w-6 text-emerald-600" aria-hidden="true">
                                <path d="M16 7h6v6"></path>
                                <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                            </svg></div>
                    </div>
                </div>
            </div>
        </div>
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
            <div data-slot="card-header"
                class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                            <div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-users h-4 w-4 text-blue-600" aria-hidden="true">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <path d="M16 3.128a4 4 0 0 1 0 7.744"></path>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                </svg></div>Provider Performance<span
                                class="text-sm text-muted-foreground ml-2">(<?php echo $text_sel_date_rep; ?>)</span>
                        </h4>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">

                        <div style="position:relative;display:inline-block;">
                            <button id="export_report" ; data-slot="dropdown-menu-trigger"
                                class="inline-flex btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                                type="button" id="radix-:rkg:" aria-haspopup="menu" aria-expanded="false"
                                data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                                    <path d="M12 15V3"></path>
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <path d="m7 10 5 5 5-5"></path>
                                </svg>Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                                    <path d="m6 9 6 6 6-6"></path>
                                </svg>
                            </button>
                            <div id="menu_export_report" class="menu-export" data-radix-popper-content-wrapper=""
                                dir="ltr"
                                style="display: none;position: absolute; left: -38px; top: 100%; margin-top: 4px; min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
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
                <div class="flex flex-wrap items-center gap-3 mt-4">

                    <select id="sel_date_rep" name="sel_date_rep">
                        <?php foreach ($filter_date_select as $item): ?>
                            <option <?php if ($item['value'] == $sel_date_rep) { ?>selected<?php } ?>
                                value="<?php echo $item['value']; ?>">
                                <?php echo $item['text']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if (count($ci_all_btypes) > 1) { ?>
                        <select name="btype" id="sel_btype" required>
                            <?php
                            foreach ($ci_all_btypes as $bt) {
                                $selected = $btype_selected == $bt ? 'selected' : '';
                                echo '<option value="' . $bt . '" ' . $selected . '>' . tr_pdd_get_typename($bt) . '</option>';
                            }
                            ?>
                        </select>
                    <?php } ?>

                    <button id="btn_day_customer" data-slot="popover-trigger"
                        class="items-center justify-center whitespace-nowrap font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-8 px-2 text-sm inline-flex"
                        title="Select custom date range" type="button" style="">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-calendar h-3 w-3 mr-1" aria-hidden="true">
                            <path d="M8 2v4"></path>
                            <path d="M16 2v4"></path>
                            <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                            <path d="M3 10h18"></path>
                        </svg>
                        <span class="txt_show_customer_day" data-slot="select-value"
                            style="pointer-events:none"><?php echo date_i18n('M j', strtotime($from_date)) . " - " . date_i18n('M j', strtotime($to_date)); ?></span>
                    </button>

                </div>
            </div>
            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
                <div class="border rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-100 border-b">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-gray-800">Provider</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-800">Sample Count</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-800">Avg TAT (hrs)</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-800">Success Rate</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-800">Top Panel</th>
                                    <th class="px-4 py-3 text-center font-medium text-gray-800">Growth</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php
                                foreach ($clinic_summary as $c) {
                                    $class_color_growth = '';
                                    if ($c['growth'] == 0)
                                        $class_color_growth = 'text-gray-600';
                                    if ($c['growth'] > 0)
                                        $class_color_growth = 'text-green-600';
                                    if ($c['growth'] < 0)
                                        $class_color_growth = 'text-red-600';
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-900"><?php echo $c['clinic']; ?></td>
                                        <td class="px-4 py-3 text-center font-bold"
                                            style="color:<?php echo get_gradient_color($c['total_volume'], $min_volume, $max_volume); ?>">
                                            <?php echo $c['total_volume']; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-700">
                                            <?php echo number_format($c['avg_tat'], 2); ?>
                                        </td>
                                        <td class="px-4 py-3 text-center text-green-600 font-medium">
                                            <?php echo number_format($c['success_rate'], 2); ?>%
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-600"><?php echo $c['top_panel']; ?></td>
                                        <td class="px-4 py-3 text-center <?php echo $class_color_growth; ?> font-medium">
                                            <?php if ($c['growth'] == 0) {
                                                echo $c['growth'] . '%';
                                            } else {
                                                if ($c['growth'] > 0)
                                                    echo sprintf('%+.1f%%', $c['growth'],);
                                                if ($c['growth'] < 0)
                                                    echo sprintf('%.1f%%', $c['growth'],);
                                            } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-medium text-blue-800 mb-2">Key Insights</h4>
                    <div class="text-sm text-blue-700 space-y-1">
                        <?php if ($top_provider_panel != null) { ?>
                            <p>• Top provider: <?php echo $top_provider_panel; ?> (<?php echo $top_provider_val; ?> samples)
                            </p><?php } ?>
                        <?php
                        if ($highest_growth_pane != null) {
                            $highest_growth_val_fmt = sprintf('%+.1f', $highest_growth_val);
                        ?>
                            <p>• Highest growth: <?php echo $highest_growth_pane; ?>
                                (<?php echo $highest_growth_val_fmt; ?>%
                                increase)</p><?php } ?>
                        <p>• Average TAT across all providers: <?php echo number_format($total_avg_tat_all_panel); ?>
                            hours</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var sel_date_rep = '<?php echo $sel_date_rep; ?>';
    var from_date = '<?php echo $from_date; ?>';
    var to_date = '<?php echo $to_date; ?>';
    var cli_ID = '<?php echo $cli_ID; ?>';
    var btype = '<?php echo $btype_selected; ?>';
    var date_type = '<?php echo $date_type; ?>';
    var url_main_page = 'ci_reports_providers';

    function goto_url() {
        var $url = location.href;
        $url += '&sel_date_rep=' + sel_date_rep;
        $url += '&from=' + from_date;
        $url += '&to=' + to_date;
        $url += '&cli_ID=' + cli_ID;
        $url += '&btype=' + btype;
        $url += '&datetype=' + date_type;
        window.location.href = $url;
    }

    jQuery(function($) {
        window.$ = jQuery;

        if (sel_date_rep != "custom") $("#btn_day_customer").removeClass("inline-flex").hide();

        update_ui_select();

        $("#sel_date_rep").change(function() {
            sel_date_rep = $(this).val();
            if (sel_date_rep == "custom") {
                $("#btn_day_customer").addClass("inline-flex").show();
                $("#btn_day_customer").data('daterangepicker').show();
            } else {
                $("#btn_day_customer").removeClass("inline-flex").hide();
                goto_url();
            }
        });

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
            $url += '&sel_date_rep=' + sel_date_rep;
            $url += '&from=' + from_date;
            $url += '&to=' + to_date;
            $url += '&cli_ID=' + cli_ID;
            $url += '&btype=' + btype;
            $url += '&datetype=' + date_type;
            showToast('info', 'Preparing your download...', 'Generating...', 4000);
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

                showToast('success', 'Download ready!', 'Your file "' + filename +
                    '" has been downloaded.', 4000);

            } catch (err) {
                showToast('error', 'Error', 'Download failed.', 4000);
            }
            return false;
        });

        $('#btn_day_customer').daterangepicker({
                opens: 'right'
            },
            function(start, end, label) {
                $('.txt_show_customer_day').html(start.format('MMM D') + ' - ' + end.format('MMM D'));
                from_date = start.format('YYYY-MM-DD');
                to_date = end.format('YYYY-MM-DD');
                goto_url();
            }
        );

        $("#sel_btype").change(function() {
            btype = $(this).val();
            goto_url();
        });

    })
</script>
<?php $schedule_report->js(); ?>