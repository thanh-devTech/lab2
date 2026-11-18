<?php

global $wpdb, $current_role, $ci_all_btypes;
global $cache_query_seconds;

$schedule_report = new CI_Report_Schedule('tat_report', 'Turnaround Time Report');

$is_ajax   = isset($_REQUEST['trajax']) && $_REQUEST['trajax'] !== 'export_report';
$is_export = isset($_REQUEST['export_func']) || (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] === 'export_report');

if ($is_export) {
    $input = $_REQUEST;
} elseif ($is_ajax) {
    $input = $_POST;
}

$from_date       = !empty($input['from']) ? $input['from'] : date_i18n('Y-m-1');
$to_date         = !empty($input['to']) ? $input['to'] : date_i18n('Y-m-d');
$cli_ID          = isset($input['cli_ID']) ? intval($input['cli_ID']) : 0;
$btype_selected  = isset($input['btype']) ? $input['btype'] : 'pcr';
$sel_date_rep    = isset($input['sel_date_rep']) ? $input['sel_date_rep'] : '90_days';

$export_func     = $is_export ? ($input['export_func'] ?? $input['trajax'] ?? '') : '';
$export_type     = $is_export ? ($input['export_type'] ?? '') : '';
$trajax          = $is_ajax ? ($input['trajax'] ?? '') : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$tat_targets_hours   = 36;
$cache_query_seconds = 3600 * 2;

$text_sel_date_rep = 'Customer';
if ($sel_date_rep === 'custom') {
    $from_date = !empty($input['from']) ? $input['from'] : date_i18n('Y-m-01');
    $to_date   = !empty($input['to'])   ? $input['to']   : date_i18n('Y-m-d');
    $text_sel_date_rep =
        date_i18n('M j', strtotime($from_date)) .
        ' - ' .
        date_i18n('M j', strtotime($to_date));
} else {
    $to_date = date_i18n('Y-m-d');
    switch ($sel_date_rep) {
        case '7_days':
            $text_sel_date_rep = 'Last 7 days';
            $from_date = date_i18n('Y-m-d', strtotime('-6 days'));
            break;
        case '14_days':
            $text_sel_date_rep = 'Last 14 days';
            $from_date = date_i18n('Y-m-d', strtotime('-13 days'));
            break;
        case '30_days':
            $text_sel_date_rep = 'Last 30 days';
            $from_date = date_i18n('Y-m-d', strtotime('-29 days'));
            break;
        case '90_days':
            $text_sel_date_rep = 'Last 90 days';
            $from_date = date_i18n('Y-m-d', strtotime('-89 days'));
            break;
        default:
            $from_date = date_i18n('Y-m-01');
            break;
    }
}

$days_of_operation_lab = CI_Base_Lab::get_lab_operations();


$clinics = array();
$filter_date_select = [];
$filter_date_select[] = ['value' => '7_days', 'text' => '7 days'];
$filter_date_select[] = ['value' => '14_days', 'text' => '14 days'];
$filter_date_select[] = ['value' => '30_days', 'text' => '30 days'];
$filter_date_select[] = ['value' => '90_days', 'text' => '90 days'];
$filter_date_select[] = ['value' => 'custom', 'text' => 'Custom'];

if (count($ci_all_btypes) === 1 || ($btype_selected == 'pcr' && !in_array('pcr', $ci_all_btypes))) {
    $btype_selected = array_shift(array_values($ci_all_btypes));
}

$conditions = trpdd_get_batch_conditions(0, $btype_selected);
$where = ' is_saved = 0 ' . $conditions['where'];
$where_clinic = $conditions['where_clinic'];
if ($conditions['check_author'] && !$is_export) {
    $where .= $conditions['where_author'];
}
$where .=  ' and ' . tr_pdd_where_batch_rejected(false, true, $btype_selected, array('deleted', 'archived', 'cancelled'));

$archive_date = tr_get_option('ci_pdf_data', 'archive_date', '');
if (!empty($archive_date)) {
    $where .= " AND ba.doc > '{$archive_date}' ";
}

$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic}");
foreach ($clinics_rs as $r) {
    $clinics[$r->cID] = $r;
}

$inactive_clinics = array();
$where_clinic_inactive = str_replace('cli_status = 1', 'cli_status = 0', $where_clinic);
$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic_inactive}");
foreach ($clinics_rs as $r) {
    $inactive_clinics[$r->cID] = $r;
}

$batch_tbl = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);

$reports = array();
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
    $rs = $wpdb->get_results("select tg_ID, tg_name from ci_test_groups where tg_parent = 0 AND tg_type = '{$btype_selected}'  AND tg_show_req = 1 order by tg_position asc");
}

foreach ($rs as $r) {
    $all_panels[$r->tg_ID] = str_replace('(new)', '', $r->tg_name);
}

$rs = $wpdb->get_results("select * from ci_labs");
foreach ($rs as $r) {
    $all_labs[$r->lab_ID] = $r;
}

$schedule_report->ajax();

// Xử lý AJAX load data
if ($trajax == 'loadData' || !empty($export_func) || isset($_GET['debug'])) {

    $report_clids = array();

    if (!empty($from_date)) {
        $where .= ' and ' . tr_pdd_build_batch_id_where($from_date, $to_date);

        if ($cli_ID > 0) {
            $where .= " and ba.cli_ID = {$cli_ID} ";
        }

        $where .= " and re.estatus = 'C' ";

        $sql_reports = "select 
                        ba.panel_tests_requested,
                        ba.cli_ID,
                        ba.ref_lab_ID,
                        ba.lab_ID,
                        re.id,
                        re.estatus,
                        re.approve_date,
                        ba.doc
                        from {$batch_tbl} ba
                        join {$report_tbl} re ON re.patient_id = ba.assen
                        where {$where}";
        if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

            $cache = new Tr_Cache_Query('tatreport');
            $rows = $cache->get_results($sql_reports, OBJECT, $cache_query_seconds);
        } else {
            $rows = $wpdb->get_results($sql_reports);
        }

        if (isset($_GET['debug'])) {
            var_dump($sql_reports);
        }

        foreach ($rows as $row) {
            $groupsids = array();
            foreach (explode(',', $row->panel_tests_requested) as $t) {
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
                $gid = $panelid;
                if ($cli_ID == -1) {
                    $gid .= '_' . $row->cli_ID;
                    $report_clids[$row->cli_ID] = $row->cli_ID;
                }
                if (!isset($reports[$gid])) {
                    $reports[$gid] = array(
                        'panel' => isset($all_panels[$panelid]) ? $all_panels[$panelid] : '',
                        'processed' => 0,
                        'main' => 0,
                        'ref' => 0,
                        'clinics' => [],
                        'tat_total_hours' => 0,
                        'tat_count' => 0,
                        'tat_min' => null,
                        'tat_max' => null,
                        'tat_target_hours' => null,
                        'tat_meet_target' => 0,
                        'tat_delayed' => 0
                    );
                }

                $reports[$gid]['processed']++;

                $received = $row->doc;
                $completed = $row->approve_date;

                if (!empty($received) && !empty($completed)) {
                    $tat_hours = 0;
                    if ($completed > $received) {
                        $tat_hours = CI_Base_Lab::calculate_business_hours($received, $completed, $row->lab_ID, $days_of_operation_lab);
                    } else {
                        $tat_hours = CI_Base_Lab::calculate_business_hours($completed, $received, $row->lab_ID, $days_of_operation_lab);
                    }
                    if ($tat_hours > 24 * 7) $tat_hours = 24 * 7;
                    $tat_target_hours = CI_Base_Lab::get_target_tat_hours($row->lab_ID, $received, $days_of_operation_lab);
                    if ($reports[$gid]['tat_target_hours'] === null) {
                        $reports[$gid]['tat_target_hours'] = $tat_target_hours;
                    } else if ($reports[$gid]['tat_target_hours'] !== $tat_target_hours) {
                        $reports[$gid]['tat_target_hours'] = 'Mixed';
                    }

                    $reports[$gid]['tat_total_hours'] += $tat_hours;
                    $reports[$gid]['tat_count']++;

                    if ($reports[$gid]['tat_min'] === null || $tat_hours < $reports[$gid]['tat_min']) {
                        $reports[$gid]['tat_min'] = $tat_hours;
                    }

                    if ($reports[$gid]['tat_max'] === null || $tat_hours > $reports[$gid]['tat_max']) {
                        $reports[$gid]['tat_max'] = $tat_hours;
                    }

                    if ($tat_hours <= $tat_target_hours) {
                        $reports[$gid]['tat_meet_target']++;
                    } else {
                        $reports[$gid]['tat_delayed']++;
                    }

                    if ($reports[$gid]['processed'] > 0) {
                        $reports[$gid]['on_time_rate'] = ($reports[$gid]['tat_meet_target'] / $reports[$gid]['processed']) * 100;
                    } else {
                        $reports[$gid]['on_time_rate'] = 0;
                    }
                }

                if ($row->ref_lab_ID == 0) {
                    $reports[$gid]['main']++;
                }
            }
        }
    }

    ksort($reports);

    $totals = array(
        'processed' => 0,
        'main' => 0,
        'tat_total_hours' => 0,
        'on_time_rate' => 0,
        'tat_delayed' => 0,
        'tat_meet_target' => 0,
        'tat_count' => 0
    );

    if ($cli_ID == -1) {
        foreach ($report_clids as $cid) {
            $cli = isset($clinics[$cid]) ? $clinics[$cid] : $inactive_clinics[$cid];
            if (!$cli) {
                $cli = new stdClass();
                $cli->cli_clinic = $wpdb->get_var("select requesting_practice from {$batch_tbl} where cli_ID = {$cid} limit 1");
                $cli->cli_status = 0;
                $cli->cli_clinic .= ' - Deleted';
                if (!empty($cli->cli_clinic)) {
                    $inactive_clinics[$cid] = $cli;
                }
            }

            foreach ($reports as $gid => $row) {
                list($panelid, $rcliid) = explode('_', $gid);
                if ($rcliid === $cid) {
                    foreach ($totals as $k => $vl) {
                        $totals[$k] += $row[$k];
                    }
                }
            }
        }
    } else {
        foreach ($reports as $row) {
            foreach ($totals as $k => $vl) {
                $totals[$k] += $row[$k];
            }
        }
    }

    if ($totals['processed'] > 0) {
        $totals['on_time_rate'] = ($totals['tat_meet_target'] / $totals['processed']) * 100;
    } else {
        $totals['on_time_rate'] = 0;
    }

    if ($totals['tat_count'] > 0) {
        $totals['tat_avg_hours'] = $totals['tat_total_hours'] / $totals['tat_count'];
    } else {
        $totals['tat_avg_hours'] = 0;
    }

    $best_panel = null;
    $best_rate = -1;
    $fastest_panel = null;
    $fastest_tat = null;
    $total_meet = 0;
    $total_tat = 0;

    foreach ($reports as $gid => $r) {
        if ($r['tat_count'] > 0) {
            $reports[$gid]['tat_avg'] = round($r['tat_total_hours'] / $r['tat_count'], 2);
            $reports[$gid]['tat_meet_percent'] = round(($r['tat_meet_target'] / $r['tat_count']) * 100, 2);

            $rate = $r['tat_meet_target'] / $r['tat_count'];
            if ($rate > $best_rate) {
                $best_rate = $rate;
                $best_panel = $r['panel'];
            }

            $avg_tat = $r['tat_total_hours'] / $r['tat_count'];
            if ($fastest_tat === null || $avg_tat < $fastest_tat) {
                $fastest_tat = $avg_tat;
                $fastest_panel = $r['panel'];
            }
        } else {
            $reports[$gid]['tat_avg'] = 0;
            $reports[$gid]['tat_meet_percent'] = 0;
        }
        if ($reports[$gid]['tat_target_hours'] === null) {
            $reports[$gid]['tat_target_hours'] = $tat_targets_hours;
        }

        $total_meet += $r['tat_meet_target'];
        $total_tat += $r['tat_count'];
        $reports[$gid]['on_time_rate'] = number_format($reports[$gid]['on_time_rate'], 2) . '%';
    }
}
if ($trajax == 'loadData') {
    $best_rate_percent = round($best_rate * 100, 2);
    $fastest_tat = round($fastest_tat, 2);
    $overall_on_time_rate = $total_tat > 0 ? round(($total_meet / $total_tat) * 100, 2) : 0;

    $volumes = array_column($reports, 'tat_avg');
    $volumes = array_filter($volumes, function ($v) {
        return is_numeric($v) && $v > 0;
    });

    if (empty($volumes)) {
        $min_volume = 0;
        $max_volume = 0;
    } else {
        $min_volume = min($volumes);
        $max_volume = max($volumes);
    }
    $all_panels_array = [];
    foreach ($all_panels as $panel_id => $panel_name) {
        $all_panels_array[] = [
            'id' => $panel_id,
            'name' => $panel_name
        ];
    }
    echo json_encode([
        'reports' => $reports,
        'totals' => $totals,
        'all_panels' => $all_panels_array,
        'best_panel' => $best_panel,
        'best_rate_percent' => $best_rate_percent,
        'fastest_panel' => $fastest_panel,
        'fastest_tat' => $fastest_tat,
        'overall_on_time_rate' => $overall_on_time_rate,
        'min_volume' => $min_volume,
        'max_volume' => $max_volume,
        'tat_targets_hours' => $tat_targets_hours
    ]);
    exit;
}

// Export functionality
if ($export_func != '') {
    if ($filepath_save_csv == '' && $filepath_save_pdf == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Tat Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    }
    $orientation = 'portrait';
    if ($export_type == "pdf" || $filepath_save_pdf != '') {
        $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
        ob_start();
        $outfile = CI_Base_Lab::get_current_lab_init() . '-igniteABX TAT Report -' . $period_export . '.pdf';
        require TRCPDD_PATH . 'parts/reports/tat_report_pdf.php';
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

    if ($export_type == "csv" || $filepath_save_csv != '') {
        $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
        $outfile = CI_Base_Lab::get_current_lab_init() . ' IGN_TAT_report_' . $period_export . '.csv';
        tr_pdd_header('csv', $outfile);
        if ($filepath_save_csv != '') {
            $f = fopen($filepath_save_csv, 'w');
            $csv_out_file = basename($outfile);
        } else {
            $f = fopen('php://output', 'w');
        }

        fputcsv($f, ["Test Panel", "Avg TAT (hrs)", "Target TAT (hrs)", "Meeting Target %", "Sample Count"]);
        foreach ($all_panels as $panel_id => $panel_name) {
            if (!isset($reports[$panel_id])) continue;
            $r = $reports[$panel_id];
            fputcsv($f, [$r['panel'], $r['tat_avg'], $r['tat_target_hours'], $r['tat_meet_percent'], $r['processed']]);
        }
        fclose($f);
        if ($filepath_save_csv == "") exit;
    }
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
    return;
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
        font-size: 13px;
        margin-bottom: 10px;
    }

    .font-bold {
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
                class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive hover:bg-accent dark:hover:bg-accent/50 h-9 px-4 py-2 has-[&gt;svg]:px-3 text-muted-foreground hover:text-primary cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-arrow-left h-4 w-4 mr-2" aria-hidden="true">
                    <path d="m12 19-7-7 7-7"></path>
                    <path d="M19 12H5"></path>
                </svg>Back to Reports
            </button>
            <div class="h-6 w-px bg-border"></div>
            <div>
                <h1 class="text-2xl font-semibold">Turnaround Time Report</h1>
                <p class="text-sm text-muted-foreground">Analyze sample processing times and workflow efficiency</p>
            </div>
        </div>
        <div class="space-y-5" id="tat-content">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div data-slot="card" id="total-sample"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground">Total Samples</p>
                                <p class="text-2xl font-bold" id="total_samples">---</p>
                            </div>
                            <div class="p-3 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-chart-no-axes-column-increasing h-6 w-6 text-blue-600"
                                    aria-hidden="true">
                                    <path d="M5 21v-6"></path>
                                    <path d="M12 21V9"></path>
                                    <path d="M19 21V3"></path>
                                </svg></div>
                        </div>
                    </div>
                </div>
                <div data-slot="card" id="avg-tat"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground">Avg TAT</p>
                                <p class="text-2xl font-bold" id="avg_tat">---</p>
                            </div>
                            <div class="p-3 bg-green-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-clock h-6 w-6 text-green-600" aria-hidden="true">
                                    <path d="M12 6v6l4 2"></path>
                                    <circle cx="12" cy="12" r="10"></circle>
                                </svg></div>
                        </div>
                    </div>
                </div>
                <div data-slot="card" id="on-time-rate"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground">On-Time Rate</p>
                                <p class="text-2xl font-bold" id="on_time_rate">---</p>
                            </div>
                            <div class="p-3 bg-emerald-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-activity h-6 w-6 text-emerald-600" aria-hidden="true">
                                    <path
                                        d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                                    </path>
                                </svg></div>
                        </div>
                    </div>
                </div>
                <div data-slot="card" id="delayed"
                    class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-muted-foreground">Delayed</p>
                                <p class="text-2xl font-bold" id="delayed_count">---</p>
                            </div>
                            <div class="p-3 bg-red-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                    height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round"
                                    class="lucide lucide-clock h-6 w-6 text-red-600" aria-hidden="true">
                                    <path d="M12 6v6l4 2"></path>
                                    <circle cx="12" cy="12" r="10"></circle>
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
                                <div class="p-1.5 bg-blue-50 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-clock h-4 w-4 text-blue-600"
                                        aria-hidden="true">
                                        <path d="M12 6v6l4 2"></path>
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg>
                                </div>TAT by Test Panel
                                <span class="text-sm text-muted-foreground ml-2" id="date_range_text">(<?php echo $text_sel_date_rep; ?>)</span>
                            </h4>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <div style="position:relative;display:inline-block;">
                                <button id="export_report" data-slot="dropdown-menu-trigger"
                                    class="inline-flex btn-export cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                                    type="button">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                                        <path d="M12 15V3"></path>
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <path d="m7 10 5 5 5-5"></path>
                                    </svg>Export
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6"></path>
                                    </svg>
                                </button>
                                <div id="menu_export_report" class="menu-export" style="display: none;position: absolute; left: -38px; top: 100%; margin-top: 4px; min-width: max-content; will-change: transform; z-index: 50;">
                                    <div data-slot="dropdown-menu-content" class="bg-popover text-popover-foreground z-50 min-w-[8rem] overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md">
                                        <div export_func="export_report" export_type="pdf" class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground relative flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                <path d="M10 9H8"></path>
                                                <path d="M16 13H8"></path>
                                                <path d="M16 17H8"></path>
                                            </svg>Export as PDF
                                        </div>
                                        <div export_func="export_report" export_type="csv" class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground relative flex items-center gap-2 rounded-sm px-2 py-1.5 text-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                <path d="M10 9H8"></path>
                                                <path d="M16 13H8"></path>
                                                <path d="M16 17H8"></path>
                                            </svg>Export as CSV
                                        </div>
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
                            title="Select custom date range" type="button" style="display:none;">
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
                            <table class="w-full text-sm" id="tat_table">
                                <thead class="bg-gray-100 border-b">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-800">Test Panel</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-800">Avg TAT (hrs)</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-800">Target TAT (hrs)</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-800">Meeting Target</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-800">Sample Count</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg" id="key_insights">
                        <h4 class="font-medium text-blue-800 mb-2">Key Insights</h4>
                        <div class="text-sm text-blue-700 space-y-1" id="insights_content">
                            <p>Loading insights...</p>
                        </div>
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
    const url_main_page = 'ci_reports_tat_report';

    function buildUrl() {
        var baseUrl = location.href.split('&trajax')[0].split('&sel_date_rep')[0].split('&from=')[0].split('&to=')[0].split('&cli_ID=')[0].split('&btype=')[0];
        var params = [
            'sel_date_rep=' + sel_date_rep,
            'from=' + from_date,
            'to=' + to_date,
            'cli_ID=' + cli_ID,
            'btype=' + btype,
        ];
        return baseUrl + '&' + params.join('&');
    }

    function getGradientColor(value, min, max) {
        if (value <= min) return 'rgb(16, 185, 129)';
        if (value >= max) return 'rgb(244, 63, 94)';

        const colors = [
            'rgb(16, 185, 129)',
            'rgb(139, 92, 246)',
            'rgb(59, 130, 246)',
            'rgb(6, 182, 212)',
            'rgb(132, 204, 22)',
            'rgb(245, 158, 11)',
            'rgb(236, 72, 153)',
            'rgb(244, 63, 94)'
        ];

        const ratio = (value - min) / (max - min);
        const idx = Math.floor(ratio * (colors.length - 1));
        return colors[idx];
    }

    function updateDateRangeText() {
        let text = '';
        if (sel_date_rep === 'custom') {
            text = moment(from_date).format('MMM D') + ' - ' + moment(to_date).format('MMM D');
        } else {
            const dateMap = {
                '7_days': 'Last 7 days',
                '14_days': 'Last 14 days',
                '30_days': 'Last 30 days',
                '90_days': 'Last 90 days'
            };
            text = dateMap[sel_date_rep] || 'Customer';
        }
        $('#date_range_text').text('(' + text + ')');
    }

    jQuery(function($) {
        window.$ = jQuery;

        function toggleLoading(show = true) {
            if (show) {
                showLoading('total-sample');
                showLoading('avg-tat');
                showLoading('on-time-rate');
                showLoading('delayed');
                showLoading('tat_table');
            } else {
                hideLoading('total-sample');
                hideLoading('avg-tat');
                hideLoading('on-time-rate');
                hideLoading('delayed');
                hideLoading('tat_table');
            }
        }

        function renderSummaryCards(totals) {
            $('#total_samples').text(totals.main || 0);
            $('#avg_tat').text(totals.tat_avg_hours ? totals.tat_avg_hours.toFixed(2) + ' hrs' : '0 hrs');
            $('#on_time_rate').text(totals.on_time_rate ? totals.on_time_rate.toFixed(2) + '%' : '0%');
            $('#delayed_count').text(totals.tat_delayed || 0);
        }

        function renderTable(data) {
            const tbody = $('#tat_table tbody');
            tbody.empty();

            if (!data.all_panels || data.all_panels.length === 0) {
                tbody.append('<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No data available</td></tr>');
                return;
            }

            data.all_panels.forEach(function(panel) {
                const r = data.reports[panel.id];
                if (!r) return;

                const color = getGradientColor(r.tat_avg, data.min_volume, data.max_volume);
                const row = `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">${r.panel}</td>
                        <td class="px-4 py-3 text-center" style="color: ${color}; font-weight: 600;">
                            ${r.tat_avg}
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">
                            ${r.tat_target_hours}
                        </td>
                        <td class="px-4 py-3 text-center text-green-600 font-medium">
                            ${r.tat_meet_percent}%
                        </td>
                        <td class="px-4 py-3 text-center text-gray-700">${r.processed}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function renderInsights(data) {
            const content = $('#insights_content');
            content.empty();

            let html = '';
            if (data.best_panel) {
                html += `<p>• Best performing panel: ${data.best_panel} Panel (${data.best_rate_percent}% on-time)</p>`;
            }
            if (data.fastest_panel) {
                html += `<p>• Fastest turnaround: ${data.fastest_panel} Panel (${data.fastest_tat} hrs average)</p>`;
            }
            html += `<p>• Overall on-time delivery rate: ${data.overall_on_time_rate}% of samples processed within target TAT</p>`;

            content.html(html);
        }

        function LoadData() {
            toggleLoading(true);
            updateUrlParams()
            $.post("admin.php?page=" + url_main_page, {
                trajax: 'loadData',
                sel_date_rep: sel_date_rep,
                from: from_date,
                to: to_date,
                cli_ID: cli_ID,
                btype: btype
            }, function(response) {
                try {
                    const res = JSON.parse(response);
                    renderSummaryCards(res.totals);
                    renderTable(res);
                    renderInsights(res);
                    updateDateRangeText();

                } catch (err) {
                    console.error('Parse error:', err);
                    showToast('error', 'Error', 'Failed to load data', 4000);
                }
            }).fail(function() {
                showToast('error', 'Error', 'Failed to load data', 4000);
            }).always(function() {
                toggleLoading(false);
            });
        }

        function initTatReportEvents() {
            sel_date_rep = $('#sel_date_rep').val() || sel_date_rep;
            btype = $('#sel_btype').val() || btype;

            $('#sel_date_rep').off('change');
            $('#sel_btype').off('change');
            $('.btn-export').off('click');
            $('.export_item').off('click');
            $('#btn_day_customer').off('change');

            if (sel_date_rep != "custom") {
                $("#btn_day_customer").removeClass("inline-flex").hide();
            } else {
                $("#btn_day_customer").addClass("inline-flex").show();
            }

            $("#sel_date_rep").change(function() {
                sel_date_rep = $(this).val();
                if (sel_date_rep == "custom") {
                    $("#btn_day_customer").addClass("inline-flex").show();
                    $("#btn_day_customer").data('daterangepicker').show();
                } else {
                    $("#btn_day_customer").removeClass("inline-flex").hide();
                    LoadData();
                }
            });

            $("#sel_btype").change(function() {
                btype = $(this).val();
                updateUrlParams();
                LoadData();
            });

            if ($('#btn_day_customer').length && !$('#btn_day_customer').data('daterangepicker')) {
                $('#btn_day_customer').daterangepicker({
                    opens: 'right',
                    startDate: moment(from_date),
                    endDate: moment(to_date)
                }, function(start, end) {
                    $('.txt_show_customer_day').html(start.format('MMM D') + ' - ' + end.format('MMM D'));
                    from_date = start.format('YYYY-MM-DD');
                    to_date = end.format('YYYY-MM-DD');
                    LoadData();
                });
            }

            $(".btn-export").on("click", function(e) {
                e.stopPropagation();
                var id_btn = $(this).attr("id");
                $("#menu_" + id_btn).toggle();
            });

            $(document).on("click", function(e) {
                if (!$(e.target).closest(".menu-export, .btn-export").length) {
                    $(".menu-export").hide();
                }
            });

            $(".export_item").click(async function() {
                var export_func = $(this).attr("export_func");
                var export_type = $(this).attr("export_type");

                var exportUrl = buildUrl();
                exportUrl += '&trajax=export_report&export_type=' + export_type;
                exportUrl += '&export_func=' + export_func;

                const loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0);
                $('#menu_export_report').hide();

                try {
                    const response = await fetch(exportUrl);
                    const disposition = response.headers.get("Content-Disposition");
                    let filename = "download." + export_type;

                    if (disposition && disposition.includes("filename=")) {
                        const match = disposition.match(/filename="?([^"]+)"?/);
                        if (match && match[1]) filename = match[1];
                    }

                    const blob = await response.blob();
                    const a = document.createElement("a");
                    a.href = URL.createObjectURL(blob);
                    a.download = filename;
                    a.click();
                    if (loadingToast) {
                        loadingToast.classList.remove("show");
                        setTimeout(() => loadingToast.remove(), 300);
                    }
                    showToast('success', 'Download ready!', 'Your file "' + filename + '" has been downloaded.', 4000);

                } catch (err) {
                    if (loadingToast) {
                        loadingToast.classList.remove("show");
                        setTimeout(() => loadingToast.remove(), 300);
                    }
                    showToast('error', 'Error', 'Download failed.', 4000);
                    console.error('Export error:', err);
                }
            });
        }

        function updateUrlParams() {
            var url = new URL(window.location.href);
            var params = url.searchParams;

            params.set('sel_date_rep', sel_date_rep);
            params.set('from', from_date);
            params.set('to', to_date);
            params.set('cli_ID', cli_ID);
            params.set('btype', btype);

            var newUrl = url.pathname + '?' + params.toString();

            if (window.history.pushState) {
                window.history.pushState({
                    path: newUrl
                }, '', newUrl);
            }
        }

        initTatReportEvents();
        update_ui_select();
        LoadData();

        window.initTatReportEvents = initTatReportEvents;
    });
</script>
<?php $schedule_report->js(); ?>