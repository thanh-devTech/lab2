<?php

global $wpdb, $current_role, $ci_all_btypes;
$schedule_report = new CI_Report_Schedule('test_volumes', 'Volume Report');
$from_date = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-1');
$to_date = !empty($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');
$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
$date_type = isset($_REQUEST['datetype']) ? $_REQUEST['datetype'] : 'doc';
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

$user_id = get_current_user_id();
$adding_tests_markers = array();
$clinics = array();
$clinicname = '';

$filter_date_select = [];
$filter_date_select[] = ['value' => '7_days', 'text' => '7 days'];
$filter_date_select[] = ['value' => '14_days', 'text' => '14 days'];
$filter_date_select[] = ['value' => '30_days', 'text' => '30 days'];
$filter_date_select[] = ['value' => '90_days', 'text' => '90 days'];
$filter_date_select[] = ['value' => 'custom', 'text' => 'Custom'];

$labname = CI_Base_Lab::getCurrentLabName();


if (count($ci_all_btypes) === 1 || ($btype_selected == 'pcr' && !in_array('pcr', $ci_all_btypes))) {
    $btype_selected = array_shift(array_values($ci_all_btypes));
}

$conditions = trpdd_get_batch_conditions(0, 'pcr');
$edit_time = $conditions['edit_time'];
$can_edit = $conditions['can_edit'];
$can_delete = $conditions['can_delete'];
$can_hold = $conditions['can_hold_req'];
$where = ' is_saved = 0 ' . $conditions['where'];
$where_clinic = $conditions['where_clinic'];
if ($conditions['check_author']) {
    $where .= $return['where_author'];
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

if (isset($_GET['debugconditions'])) {
    var_dump($conditions);
    var_dump($clinics);
    var_dump($inactive_clinics);
}

$batch_tbl = tr_pdd_get_table_by_btype($btype_selected);
$report_tbl = tr_pdd_get_table_report_by_btype($btype_selected);
$result_tbl = tr_pdd_get_table_result_by_btype($btype_selected);
$panel = array();
$submited = false;
$reports = array();
$reports_clinics = array();
$tests = array();

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

$all_rejected_status = tr_pdd_where_batch_rejected(true);

$report_clids = array();
if (!empty($from_date)) {

    $coldate = $date_type == 'doc' ? 'ba.doc' : 're.approve_date';
    if ($date_type == 'received') {
        $coldate = 'ba.spec_received';
    }


    if ($date_type == 'bid') {
        $coldate = 'ba.batch_id';
        $where .= ' and ' . tr_pdd_build_batch_id_where($from_date, $to_date);
    } else {
        $where .= " and {$coldate} >= '{$from_date}' ";
        if (!empty($to_date)) {
            $where .= " and {$coldate} <= '{$to_date} 23:59:59' ";
        }
    }

    if ($cli_ID > 0) {
        $where .= " and ba.cli_ID = {$cli_ID} ";
    }

    $where .= " and re.estatus = 'C' ";

    $rerunfield = 're.covid_pos';
    if ($btype_selected == 'pcr') {
        $rerunfield .= ',re.rerun_count';
    }

    $sql_reports = "select 
					ba.panel_tests_requested,
					ba.cli_ID,
					ba.ref_lab_ID,
					ba.batch_status,
					re.id,
					re.estatus,
					{$rerunfield}
					from {$batch_tbl} ba
					join {$report_tbl} re ON re.patient_id = ba.assen
					where {$where}";
    $rows = $wpdb->get_results($sql_reports);

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
                    'panel' => $all_panels[$panelid],
                    'processed' => 0,
                    'main' => 0,
                    'ref' => 0,
                    'refs' => array(),
                    'rejected' => 0,
                    'rerun' => 0,
                    'clinics' => [],
                );
            }

            $reports[$gid]['processed']++;

            // lưu clinic ID
            if (!in_array($row->cli_ID, $reports[$gid]['clinics'])) {
                $reports[$gid]['clinics'][] = $row->cli_ID;
            }

            if ($row->ref_lab_ID > 0) {

                $reports[$gid]['ref']++;
                if (!isset($reports[$gid]['refs'][$row->ref_lab_ID])) {
                    $reports[$gid]['refs'][$row->ref_lab_ID] = 0;
                }
                $reports[$gid]['refs'][$row->ref_lab_ID]++;
            } else {
                $reports[$gid]['main']++;
            }

            if (isset($all_rejected_status[$row->batch_status])) {
                $reports[$gid]['rejected']++;
                continue;
            }


            if (isset($row->rerun_count) && $row->rerun_count > 0) {
                $reports[$gid]['rerun']++;
            } else if (isset($row->covid_pos) && $row->covid_pos == -2) {
                $reports[$gid]['rerun']++;
            }
        }
    }
}

$total_cinics = 0;
foreach ($all_panels as $panel_id => $panel_name) {
    $gid = $panel_id;
    if ($cli_ID == -1) {
        // nếu muốn tách theo clinic
        foreach ($report_clids as $clid) {
            $full_gid = $gid . '_' . $clid;
            $r = $reports[$full_gid];
            if ($r == null)
                continue;
            $num_clinics = isset($r['clinics']) && is_array($r['clinics']) ? count($r['clinics']) : 0;
            $total_cinics += $num_clinics;
        }
    } else {
        $r = $reports[$gid];
        if ($r == null)
            continue;
        $num_clinics = isset($r['clinics']) && is_array($r['clinics']) ? count($r['clinics']) : 0;
        $total_cinics += $num_clinics;
    }
}
ksort($reports);

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
        $trrows = '';
        $totals = array(
            'processed' => 0,
            'main' => 0,
            'ref' => 0,
            'rejected' => 0,
            'rejected_percent' => 0,
            'rerun' => 0,
        );

        foreach ($reports as $gid => $row) {
            list($panelid, $rcliid) = explode('_', $gid);

            if ($rcliid === $cid) {
                $row['rejected_percent'] = $row['processed'] > 0 ? $row['rejected'] / $row['processed'] * 100 : 0;
                foreach ($totals as $k => $vl) {
                    $totals[$k] += $row[$k];
                }
            }
        }

        $totals['rejected_percent'] = $totals['processed'] > 0 ? $totals['rejected'] / $totals['processed'] * 100 : 0;
        $totals['rejected_percent'] = number_format($totals['rejected_percent'], 2) . '%';
    }
} else {
    $totals = array(
        'processed' => 0,
        'main' => 0,
        'ref' => 0,
        'rejected' => 0,
        'rejected_percent' => 0,
        'rerun' => 0,
    );
    $totalrefs = array();
    foreach ($reports as $row) {
        $row['rejected_percent'] = $row['processed'] > 0 ? $row['rejected'] / $row['processed'] * 100 : 0;

        foreach ($totals as $k => $vl) {
            $totals[$k] += $row[$k];
        }

        $ref_by_labs = array();
        foreach ($row['refs'] as $lid => $vl) {
            $ref_by_labs[] = $vl . $all_labs[$lid]->lab_dba_init;
            $totalrefs[$lid] += $vl;
        }
        if (empty($ref_by_labs)) {
            $ref_by_labs[] = 0;
        }
    }

    $totals['rejected_percent'] = $totals['processed'] > 0 ? $totals['rejected'] / $totals['processed'] * 100 : 0;
    $totals['rejected_percent'] = number_format($totals['rejected_percent'], 2) . '%';
    foreach ($totals as $k => $vl) {
        if ($k == 'ref') {
            $ref_by_labs = array();
            foreach ($totalrefs as $lid => $vl) {
                $ref_by_labs[] = $vl . $all_labs[$lid]->lab_dba_init;
            }
            $vl = implode(' &nbsp;&nbsp; ', $ref_by_labs);
        }
    }
}
$totals['clinics'] = $total_cinics;

$schedule_report->ajax();

if ($export_func != '') {
    if ($filepath_save_csv == '' && $filepath_save_pdf == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Volume Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    }
    if ($export_type == "pdf" || $filepath_save_pdf != "") {
        $orientation = 'portrait';
        $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
        $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
        ob_start();
        $outfile = CI_Base_Lab::get_current_lab_init() . '-igniteABX Sample Volume Report -' . $period_export . '.pdf';
        require TRCPDD_PATH . 'parts/reports/test_volumes_pdf.php';
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
        $outfile = CI_Base_Lab::get_current_lab_init() . ' IGN_Volume_Report_' . $period_export . '.csv';
        tr_pdd_header('csv', $outfile);
        if ($filepath_save_csv != '') {
            $f = fopen($filepath_save_csv, 'w');
            $csv_out_file = basename($outfile);
        } else {
            $f = fopen('php://output', 'w');
        }

        fputcsv($f, ["Panel", "All Clinics", "Processed", "Physicians Immediate Care Chicago PLLC", "Referenced", "Rejected", "Rejected %", "Rerun Rate"]);
        foreach ($all_panels as $panel_id => $panel_name) {
            $r = $reports[$panel_id];
            if ($r == null)
                continue;
            $rejected_percent = $r['processed'] > 0 ? $r['rejected'] / $r['processed'] * 100 : 0;
            $clinic_count = isset($r['clinics']) && is_array($r['clinics']) ? count($r['clinics']) : 0;
            fputcsv($f, [$r['panel'], $clinic_count, $r['processed'], "-", $r['ref'], $r['rejected'], $totals['rejected_percent'], "0"]);
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
        font-weight: unset !important;
        font-size: inherit;
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

    .text-primary {
        color: var(--primary);
    }

    ol {
        list-style-type: none;
        margin: 0px;
        margin-top: px;
        padding-top: 5px;
        padding-left: 5px;
    }

    ol li a {
        color: unset;
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

<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
        <div class="space-y-6">
            <div class="bg-white border rounded-lg px-3 sm:px-6 py-2 sm:py-3 shadow-sm">
                <nav aria-label="breadcrumb" data-slot="breadcrumb">
                    <ol data-slot="breadcrumb-list"
                        class="text-muted-foreground flex items-center gap-1.5 text-sm break-words sm:gap-2.5 flex-wrap">
                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                            <a data-slot="breadcrumb-link"
                                class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                                href="?page=dashboard_main">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-house h-3 w-3" aria-hidden="true">
                                    <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                    <path
                                        d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
                                    </path>
                                </svg>
                                <span class="truncate max-w-[120px] sm:max-w-none">Dashboard</span>
                            </a>
                        </li>

                        <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                            class="[&amp;&gt;svg]:size-3.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-chevron-right">
                                <path d="m9 18 6-6-6-6"></path>
                            </svg>
                        </li>

                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                            <a data-slot="breadcrumb-link"
                                class="hover:text-foreground transition-colors flex items-center gap-1 text-xs sm:text-sm"
                                href="?page=ci_reports_main">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-file-text h-3 w-3" aria-hidden="true">
                                    <path
                                        d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z">
                                    </path>
                                    <path d="M14 2v5a1 1 0 0 0 1 1h5"></path>
                                    <path d="M10 9H8"></path>
                                    <path d="M16 13H8"></path>
                                    <path d="M16 17H8"></path>
                                </svg>
                                <span class="truncate max-w-[120px] sm:max-w-none">Reports</span>
                            </a>
                        </li>

                        <li data-slot="breadcrumb-separator" role="presentation" aria-hidden="true"
                            class="[&amp;&gt;svg]:size-3.5">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-chevron-right">
                                <path d="m9 18 6-6-6-6"></path>
                            </svg>
                        </li>

                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5">
                            <span data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
                                class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3"
                                    aria-hidden="true">
                                    <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                    <path d="M18 17V9"></path>
                                    <path d="M13 17V5"></path>
                                    <path d="M8 17v-3"></path>
                                </svg>
                                <span class="truncate max-w-[120px] sm:max-w-none">Volume Report</span>
                            </span>
                        </li>

                    </ol>
                </nav>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                        Volume Report</h1>
                    <p class="text-muted-foreground mt-1">Sample volume analysis by test panel and
                        clinic</p>
                </div>
            </div>
            <div>
                <div class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-muted-foreground">Total Samples</p>
                                        <p class="text-2xl font-bold"><?php echo $totals['main']; ?></p>
                                    </div>
                                    <div class="p-2.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
                                            <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                            <path d="M18 17V9"></path>
                                            <path d="M13 17V5"></path>
                                            <path d="M8 17v-3"></path>
                                        </svg></div>
                                </div>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-muted-foreground">Processed</p>
                                        <p class="text-2xl font-bold"><?php echo $totals['processed']; ?></p>
                                    </div>
                                    <div class="p-2.5 bg-green-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-activity h-4 w-4 text-green-600" aria-hidden="true">
                                            <path
                                                d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                                            </path>
                                        </svg></div>
                                </div>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-muted-foreground">Rejected</p>
                                        <p class="text-2xl font-bold"><?php echo $totals['rejected']; ?></p>
                                    </div>
                                    <div class="p-2.5 bg-red-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-trending-up h-4 w-4 text-red-600" aria-hidden="true">
                                            <path d="M16 7h6v6"></path>
                                            <path d="m22 7-8.5 8.5-5-5L2 17"></path>
                                        </svg></div>
                                </div>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-muted-foreground">Rejection Rate</p>
                                        <p class="text-2xl font-bold"><?php echo $totals['rejected_percent']; ?></p>
                                    </div>
                                    <div class="p-2.5 bg-orange-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-file-text h-4 w-4 text-orange-600" aria-hidden="true">
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
                    </div>
                    <div data-slot="card"
                        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header"
                            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h4 data-slot="card-title" class="leading-none flex items-center gap-2">
                                        <div class="p-1.5 bg-blue-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                                width="24" height="24" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="lucide lucide-chart-column h-4 w-4 text-blue-600"
                                                aria-hidden="true">
                                                <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                                <path d="M18 17V9"></path>
                                                <path d="M13 17V5"></path>
                                                <path d="M8 17v-3"></path>
                                            </svg></div>Volume by Test Panel<span
                                            class="text-sm text-muted-foreground ml-2">(<?php echo $text_sel_date_rep; ?>)</span>
                                    </h4>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <div style="position:relative;display:inline-block;">
                                        <button data-slot="dropdown-menu-trigger" id="export_volume_panel"
                                            class="btn-export  cursor-pointer inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 text-muted-foreground hover:text-primary hover:bg-transparent"
                                            type="button" id="radix-:r4u:" aria-haspopup="menu" aria-expanded="false"
                                            data-state="closed"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                                height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
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
                                        <div id="menu_export_volume_panel" class="menu-export"
                                            data-radix-popper-content-wrapper="" dir="ltr"
                                            style="display: none;position: absolute; left: -38px; top: 100%; margin-top: 4px; min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                                            <div data-side="bottom" data-align="end" role="menu"
                                                aria-orientation="vertical" data-state="open" data-radix-menu-content=""
                                                dir="ltr" id="radix-:rm:" aria-labelledby="radix-:rl:"
                                                data-slot="dropdown-menu-content"
                                                class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md"
                                                tabindex="-1" data-orientation="vertical"
                                                style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                                                <div export_func="volume_panel" export_type="pdf" role="menuitem"
                                                    data-slot="dropdown-menu-item" data-variant="default"
                                                    class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                                    tabindex="-1" data-orientation="vertical"
                                                    data-radix-collection-item=""><svg
                                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                                        <path
                                                            d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                        </path>
                                                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                        <path d="M10 9H8"></path>
                                                        <path d="M16 13H8"></path>
                                                        <path d="M16 17H8"></path>
                                                    </svg>Export as PDF</div>
                                                <div export_func="volume_panel" export_type="csv" role="menuitem"
                                                    data-slot="dropdown-menu-item" data-variant="default"
                                                    class="export_item cursor-pointer hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4"
                                                    tabindex="-1" data-orientation="vertical"
                                                    data-radix-collection-item=""><svg
                                                        xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                        class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                                        <path
                                                            d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                        </path>
                                                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                        <path d="M10 9H8"></path>
                                                        <path d="M16 13H8"></path>
                                                        <path d="M16 17H8"></path>
                                                    </svg>Export as CSV</div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php echo $schedule_report->btn_schedule(); ?>
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

                                <select id="sel_datetype" name="datetype">
                                    <?php
                                    foreach (array('doc' => 'DOC', 'approve' => 'Approved Date', 'received' => 'Received', 'bid' => 'Batch#') as $k => $txt) {
                                        $selected = $k == $date_type ? 'selected' : '';
                                        echo '<option value="' . $k . '" ' . $selected . '>' . $txt . '</option>';
                                    }
                                    ?>
                                </select>

                                <select id="sel_clinic_rep" name="sel_clinic_rep">
                                    <option value="0">All Clinics</option>
                                    <?php foreach ($clinics_rs as $item): ?>
                                        <option value="<?php echo $item->cID; ?>"
                                            <?php if ($cli_ID == $item->cID) { ?>selected<?php } ?>>
                                            <?php echo $item->cli_clinic; ?>
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
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round" class="lucide lucide-calendar h-3 w-3 mr-1"
                                        aria-hidden="true">
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
                                                <th class="px-4 py-3 text-left font-medium text-gray-800">Panel</th>
                                                <th class="px-4 py-3 text-center font-medium text-gray-800">All Clinics
                                                </th>
                                                <th class="px-4 py-3 text-center font-medium text-gray-800">Processed
                                                </th>
                                                <th class="px-4 py-3 text-center font-medium text-gray-800">Referenced
                                                </th>
                                                <th class="px-4 py-3 text-center font-medium text-gray-800">Rejected
                                                </th>
                                                <th class="px-4 py-3 text-center font-medium text-gray-800">Rejected %
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr class="hover:bg-gray-50 bg-gray-50">
                                                <td class="px-4 py-3 font-medium text-gray-900">All Clinics</td>
                                                <td class="px-4 py-3 text-center font-medium text-gray-900">
                                                    <?php echo $totals['clinics']; ?>
                                                </td>
                                                <td class="px-4 py-3 text-center text-green-600 font-medium">
                                                    <?php echo $totals['processed']; ?>
                                                </td>
                                                <td class="px-4 py-3 text-center text-gray-600">
                                                    <?php echo $totals['ref']; ?>
                                                </td>
                                                <td class="px-4 py-3 text-center text-red-600 font-medium">
                                                    <?php echo $totals['rejected']; ?>
                                                </td>
                                                <td class="px-4 py-3 text-center text-orange-600 font-medium">
                                                    <?php echo $totals['rejected_percent']; ?>
                                                </td>
                                            </tr>
                                            <?php
                                            foreach ($all_panels as $panel_id => $panel_name) {
                                                $r = $reports[$panel_id];
                                                if ($r == null)
                                                    continue;
                                                $rejected_percent = $r['processed'] > 0 ? $r['rejected'] / $r['processed'] * 100 : 0;
                                                $clinic_count = isset($r['clinics']) && is_array($r['clinics']) ? count($r['clinics']) : 0;
                                            ?>
                                                <tr class="hover:bg-gray-50 ">
                                                    <td class="px-4 py-3 font-medium text-gray-900">
                                                        <?php echo $r['panel']; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center font-medium text-gray-900">
                                                        <?php echo $clinic_count; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-green-600 font-medium">
                                                        <?php echo $r['processed']; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-gray-600"><?php echo $r['ref']; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-red-600 font-medium">
                                                        <?php echo $r['rejected']; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-orange-600 font-medium">
                                                        <?php echo number_format($rejected_percent, 2); ?>%
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
                        <p class="text-xs text-gray-600 text-center">
                            <strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
                            igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
                        </p>
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
    var url_main_page = 'ci_reports_test_volumes';

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

        $("#sel_clinic_rep").change(function() {
            cli_ID = $(this).val();
            goto_url();
        });

        $("#sel_btype").change(function() {
            btype = $(this).val();
            goto_url();
        });

        $("#sel_datetype").change(function() {
            date_type = $(this).val();
            goto_url();
        });

    })
</script>
<?php $schedule_report->js(); ?>