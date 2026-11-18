<?php

global $wpdb, $current_role, $ci_all_btypes;

$from_date = !empty($_GET['from']) ? $_GET['from'] : date_i18n('Y-m-1');
$to_date = !empty($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');
$sel_status = isset($_REQUEST['sel_status']) ? $_REQUEST['sel_status'] : 'all';
$sel_clinic = isset($_REQUEST['sel_clinic']) ? $_REQUEST['sel_clinic'] : 'all';
$btype_selected = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : 'pcr';
$sel_panel = isset($_REQUEST['sel_panel']) ? $_REQUEST['sel_panel'] : 'all';
$sel_bucket = isset($_REQUEST['sel_bucket']) ? $_REQUEST['sel_bucket'] : 'all';
$date_type = isset($_REQUEST['datetype']) ? $_REQUEST['datetype'] : 'doc';

$sel_date_rep = isset($_REQUEST['sel_date_rep']) ? $_REQUEST['sel_date_rep'] : '90_days';
$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

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

$panels = array();
$panels['uti'] = array('title' => 'UTI');
$panels['resp'] = array('title' => 'RESP');
$panels['wound'] = array('title' => 'WOUND');
$panels['sti'] = array('title' => 'STI');

$bucket = array();
$bucket['timer_72'] = array('title' => '> 72 hours', 'txt' => '>72h', 'class_pdf' => 'bucket-badge bucket-critical', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-red-100 text-red-800');
$bucket['timer_48_to_72'] = array('title' => '48-72 hours', 'txt' => '48-72h', 'class_pdf' => 'bucket-badge bucket-high', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-orange-100 text-orange-800');
$bucket['timer_24_to_48'] = array('title' => '24-48 hours', 'txt' => '24-48h', 'class_pdf' => 'bucket-badge bucket-moderate', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-yellow-100 text-yellow-800');
$bucket['timer_24'] = array('title' => '< 24 hours', 'txt' => '<24h', 'class_pdf' => 'bucket-badge bucket-normal', 'class' => 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90 bg-green-100 text-green-800');


function get_bucket_txt($doc_time_str, $bucket)
{
    $doc_time = new DateTime($doc_time_str);
    $now = new DateTime();

    // Tính tuổi theo giờ
    $interval = $now->diff($doc_time);
    $age_in_hours = $interval->days * 24 + $interval->h + $interval->i / 60;

    // So sánh với các mốc thời gian
    if ($age_in_hours > 72) {
        return $bucket['timer_72'];
    } elseif ($age_in_hours > 48) {
        return $bucket['timer_48_to_72'];
    } elseif ($age_in_hours > 24) {
        return $bucket['timer_24_to_48'];
    } else {
        return $bucket['timer_24'];
    }
}


$status = array();
$status['not_received'] = array('title' => 'NOT RECEIVED');
$status['pending_result'] = array('title' => 'PENDING RESULT');

$count_batch = [
    'not_received_over_72' => 0,
    'not_received_48_72'   => 0,
    'not_received_24_48'   => 0,
    'not_received_under_24' => 0,
    'pending_over_72'       => 0,
    'pending_48_72'         => 0,
    'pending_24_48'         => 0,
    'pending_under_24'      => 0,
];

foreach ($ci_all_btypes as $btype) {
    $batch_tbl = tr_pdd_get_table_by_btype($btype);
    $report_tbl = tr_pdd_get_table_report_by_btype($btype);

    $sql_count = "
        SELECT
            SUM(CASE WHEN ba.batch_id = 0 AND TIMESTAMPDIFF(HOUR, ba.doc, NOW()) > 72 THEN 1 ELSE 0 END) AS not_received_over_72,
            SUM(CASE WHEN ba.batch_id = 0 AND TIMESTAMPDIFF(HOUR, ba.doc, NOW()) BETWEEN 48 AND 72 THEN 1 ELSE 0 END) AS not_received_48_72,
            SUM(CASE WHEN ba.batch_id = 0 AND TIMESTAMPDIFF(HOUR, ba.doc, NOW()) BETWEEN 24 AND 48 THEN 1 ELSE 0 END) AS not_received_24_48,
            SUM(CASE WHEN ba.batch_id = 0 AND TIMESTAMPDIFF(HOUR, ba.doc, NOW()) < 24 THEN 1 ELSE 0 END) AS not_received_under_24,
            SUM(CASE WHEN ba.batch_id > 0 AND TIMESTAMPDIFF(HOUR, ba.doc, NOW()) > 72 THEN 1 ELSE 0 END) AS pending_over_72,
            SUM(CASE WHEN ba.batch_id > 0 AND TIMESTAMPDIFF(HOUR, ba.doc, NOW()) BETWEEN 48 AND 72 THEN 1 ELSE 0 END) AS pending_48_72,
            SUM(CASE WHEN ba.batch_id > 0 AND TIMESTAMPDIFF(HOUR, ba.doc, NOW()) BETWEEN 24 AND 48 THEN 1 ELSE 0 END) AS pending_24_48,
            SUM(CASE WHEN ba.batch_id > 0 AND TIMESTAMPDIFF(HOUR, ba.doc, NOW()) < 24 THEN 1 ELSE 0 END) AS pending_under_24
        FROM {$batch_tbl} ba
        left JOIN {$report_tbl} re ON re.patient_id = ba.assen and  re.estatus = 'C'
        where ba.is_saved = 0 
            and ba.batch_status not in ('deleted', 'archived')
            and re.id IS NULL
            
    ";

    $count_b = $wpdb->get_row($sql_count, ARRAY_A);
    echo $wpdb->last_error;

    // cộng vào tổng
    foreach ($count_batch as $key => $val) {
        $count_batch[$key] += (int)$count_b[$key];
    }
}

$count_batch = (object) $count_batch;
$reports = array();

foreach ($ci_all_btypes as $btype) {
    $batch_tbl = tr_pdd_get_table_by_btype($btype);

    $all_panels = array();
    $all_tests = array();
    if ($btype == 'tox') {
        $rs = $wpdb->get_results("select te_group_id , te_name from ci_test_panels where te_type = '{$btype}' ");
        foreach ($rs as $r) {
            $all_tests[strtoupper($r->te_name)] = $r->te_group_id;
        }
    }

    $rs = $wpdb->get_results("select tg_ID, tg_name
                    from ci_test_groups 
                    where tg_type = '{$btype}' 
                    and tg_parent = 0
                    and tg_show_req = 1
                    order by tg_position asc");


    foreach ($rs as $r) {
        $all_panels[$r->tg_ID] = $r->tg_name;
    }

    $where = array();

    // Điều kiện batch_id theo $sel_status
    if ($sel_status == 'pending_result') {
        $where[] = "ba.batch_id > 0";
    } elseif ($sel_status == 'not_received') {
        $where[] = "ba.batch_id = 0";
    }

    // Điều kiện doc theo $sel_bucket
    if ($sel_bucket == 'timer_72') {
        $where[] = "ba.doc < NOW() - INTERVAL 72 HOUR"; // doc > 72 giờ trước
    } elseif ($sel_bucket == 'timer_48_to_72') {
        $where[] = "ba.doc BETWEEN NOW() - INTERVAL 72 HOUR AND NOW() - INTERVAL 48 HOUR";
    } elseif ($sel_bucket == 'timer_24_to_48') {
        $where[] = "ba.doc BETWEEN NOW() - INTERVAL 48 HOUR AND NOW() - INTERVAL 24 HOUR";
    } elseif ($sel_bucket == 'timer_24') {
        $where[] = "ba.doc > NOW() - INTERVAL 24 HOUR"; // doc < 24 giờ trước
    }

    if ($sel_clinic != 'all') {
        $where[] = "ba.cli_ID=$sel_clinic";
    }

    if ($sel_panel != 'all') {
        if ($btype == 'tox') {
            $where[] = " ( ";
            $panel_conditions = array();
            foreach ($all_tests as $test_name => $group_id) {
                if ($group_id == $sel_panel) {
                    $panel_conditions[] = " ba.panel_tests_requested like '%{$test_name}%' ";
                }
            }
            $where[] = implode(' OR ', $panel_conditions);
            $where[] = " ) ";
        } else {
            $where[] = " ba.panel_tests_requested like '%GROUP_{$sel_panel}%' ";
        }
    }

    $sql = "
        SELECT
            ba.*,
            ba.doc,
            ba.assen,
            ba.panel_tests_requested,
            ba.cli_ID,
            c.cli_clinic,
            IF(rb.mid IS NULL, 'no', 'yes') AS received
        FROM {$batch_tbl} ba
        left JOIN {$report_tbl} re ON re.patient_id = ba.assen and  re.estatus = 'C'
        LEFT JOIN ci_receiving_batches rb 
            ON rb.mid = ba.mid 
        LEFT JOIN ci_clinic c 
            ON c.cID = ba.cli_ID
           where ba.is_saved = 0 
            and ba.batch_status not in ('deleted', 'archived')
            and re.id IS NULL
    ";

    if (!empty($where)) {
        $sql .= " and " . implode(" AND ", $where);
    }

    /*
    if ($nor) {
        $sql .= " AND rb.mid IS NULL";
    }
    */

    $sql .= " ORDER BY ba.doc DESC
    
     limit 1000";

    $rows_batch = $wpdb->get_results($sql, ARRAY_A);


    foreach ($rows_batch as $row) {
        $groupsids = array();

        // Lấy các group id từ panel_tests_requested
        foreach (explode(',', $row['panel_tests_requested']) as $t) {
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
            }
        }

        // Build tên panels
        $panels_tmp = array();
        foreach ($groupsids as $gid) {
            if (isset($all_panels[$gid])) {
                $panels_tmp[] = $all_panels[$gid];
            }
        }
        $row['panels'] = implode(', ', $panels_tmp);

        // Kiểm tra điều kiện $sel_panel
        if ($sel_panel == 'all' || in_array($sel_panel, $groupsids)) {
            $doc_time = new DateTime($row['doc']);
            $now = new DateTime();

            $interval = $now->diff($doc_time);
            $age_in_hours = $interval->days * 24 + $interval->h + $interval->i / 60;

            $row['age_in_hours'] = round($age_in_hours);
            $reports[] = $row;
        }
    }
}

if ($export_func == 'export_report') {
    if ($export_type == "pdf") {
        $orientation = 'portrait';
        $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
        $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
        ob_start();
        $outfile = CI_Base_Lab::get_current_lab_init() . '-IGN_pending-outstanding-' . $period_export . '.pdf';
        require TRCPDD_PATH . 'parts/reports/pending_and_outstanding_pdf.php';
        $html = ob_get_clean();
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

    if ($export_type == "csv") {
        $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
        $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
        $outfile = CI_Base_Lab::get_current_lab_init() . ' IGN_pending-outstanding-' . $period_export . '.csv';
        tr_pdd_header('csv', $outfile);
        $f = fopen('php://output', 'w');
        fputcsv($f, ["PENDING & OUTSTANDING REPORT - $period_export"]);
        fputcsv($f, [""]);
        fputcsv($f, ["Filters"]);
        fputcsv($f, ["Panel", "All Panels"]);
        fputcsv($f, ["Clinic", "All Clinics"]);
        fputcsv($f, ["Status", "All Statuses"]);
        fputcsv($f, ["Bucket", "All Buckets"]);
        fputcsv($f, [""]);
        fputcsv($f, ["SUMMARY - NOT RECEIVED"]);
        fputcsv($f, ["> 72 hours", $count_batch->not_received_over_72]);
        fputcsv($f, ["48-72 hours", $count_batch->not_received_48_72]);
        fputcsv($f, ["24-48 hours", $count_batch->not_received_24_48]);
        fputcsv($f, ["< 24 hours", $count_batch->not_received_under_24]);
        fputcsv($f, [""]);
        fputcsv($f, ["SUMMARY - PENDING RESULT"]);
        fputcsv($f, ["> 72 hours", $count_batch->pending_over_72]);
        fputcsv($f, ["48-72 hours", $count_batch->pending_48_72]);
        fputcsv($f, ["24-48 hours", $count_batch->pending_24_48]);
        fputcsv($f, ["< 24 hours", $count_batch->pending_under_24]);
        fputcsv($f, [""]);
        fputcsv($f, ["DETAILED LIST"]);
        fputcsv($f, ["STATUS", "ACC #", "PANEL", "CLINIC", "COLLECTION", "RECEIVED", "AGE", "BUCKET", "PLATED"]);
        foreach ($reports as $row) {
            $bc = get_bucket_txt($row['doc'], $bucket);
            $status = '';
            if (intVal($row['batch_id']) > 0) $status = 'PENDING';
            else $status = 'NOT RECEIVED';

            $date_create = date_i18n('m/d H:i', strtotime($row['batch_created']));
            $date_rec = date_i18n('m/d H:i', strtotime($row['spec_received']));
            fputcsv($f, [$status, $row['assen'], $row['panels'], $row['cli_clinic'], $row['panels'], $date_create, $date_rec, $row['age_in_hours'], $bc['txt'], 'No']);
        }
        fclose($f);
        exit;
    }
}

?>
<style>
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

    .font-bold {
        --tw-font-weight: var(--font-weight-bold) !important;
        font-weight: var(--font-weight-bold) !important;
    }

    .text-2xl {
        font-size: var(--text-2xl);
        line-height: var(--tw-leading, var(--text-2xl--line-height));
    }
</style>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>

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
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-chevron-right">
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-chevron-right">
                                <path d="m9 18 6-6-6-6"></path>
                            </svg>
                        </li>

                        <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
                                data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
                                class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="lucide lucide-package h-3 w-3" aria-hidden="true">
                                    <path
                                        d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z">
                                    </path>
                                    <path d="M12 22V12"></path>
                                    <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                    <path d="m7.5 4.27 9 5.15"></path>
                                </svg><span class="truncate max-w-[120px] sm:max-w-none">Pending &amp;
                                    Outstanding</span></span></li>
                    </ol>
                </nav>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                        Pending &amp; Outstanding Report</h1>
                    <p class="text-muted-foreground mt-1">Combined view of Not Received + Pending
                        Result samples</p>
                </div>
            </div>
            <div>
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-red-100">
                            <div data-slot="card-header"
                                class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                                <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received &gt;72h
                                </h4>
                                <div class="p-1 md:p-1.5 bg-red-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4 text-red-600"
                                        aria-hidden="true">
                                        <path
                                            d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14">
                                        </path>
                                        <path d="m7.5 4.27 9 5.15"></path>
                                        <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                        <line x1="12" x2="12" y1="22" y2="12"></line>
                                        <path d="m17 13 5 5m-5 0 5-5"></path>
                                    </svg></div>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                                <div class="text-xl md:text-2xl font-bold"><?php echo $count_batch->not_received_over_72; ?></div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Critical - needs attention</p>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-orange-100">
                            <div data-slot="card-header"
                                class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                                <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received 48-72h
                                </h4>
                                <div class="p-1 md:p-1.5 bg-orange-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4 text-orange-600"
                                        aria-hidden="true">
                                        <path
                                            d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14">
                                        </path>
                                        <path d="m7.5 4.27 9 5.15"></path>
                                        <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                        <line x1="12" x2="12" y1="22" y2="12"></line>
                                        <path d="m17 13 5 5m-5 0 5-5"></path>
                                    </svg></div>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                                <div class="text-xl md:text-2xl font-bold"><?php echo $count_batch->not_received_48_72; ?></div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">High priority</p>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-yellow-100">
                            <div data-slot="card-header"
                                class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                                <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received 24-48h
                                </h4>
                                <div class="p-1 md:p-1.5 bg-yellow-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4 text-amber-500"
                                        aria-hidden="true">
                                        <path
                                            d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14">
                                        </path>
                                        <path d="m7.5 4.27 9 5.15"></path>
                                        <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                        <line x1="12" x2="12" y1="22" y2="12"></line>
                                        <path d="m17 13 5 5m-5 0 5-5"></path>
                                    </svg></div>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                                <div class="text-xl md:text-2xl font-bold"><?php echo $count_batch->not_received_24_48; ?></div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Moderate</p>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-header"
                                class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                                <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Not Received &lt;24h
                                </h4>
                                <div class="p-1 md:p-1.5 bg-green-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-package-x h-3 w-3 md:h-4 md:w-4 text-green-600"
                                        aria-hidden="true">
                                        <path
                                            d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14">
                                        </path>
                                        <path d="m7.5 4.27 9 5.15"></path>
                                        <polyline points="3.29 7 12 12 20.71 7"></polyline>
                                        <line x1="12" x2="12" y1="22" y2="12"></line>
                                        <path d="m17 13 5 5m-5 0 5-5"></path>
                                    </svg></div>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                                <div class="text-xl md:text-2xl font-bold"><?php echo $count_batch->not_received_under_24; ?></div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Normal range</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-red-100">
                            <div data-slot="card-header"
                                class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                                <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending &gt;72h</h4>
                                <div class="p-1 md:p-1.5 bg-red-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-clock h-3 w-3 md:h-4 md:w-4 text-red-600" aria-hidden="true">
                                        <path d="M12 6v6l4 2"></path>
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg></div>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                                <div class="text-xl md:text-2xl font-bold"><?php echo $count_batch->pending_over_72; ?></div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Critical - needs attention</p>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-orange-100">
                            <div data-slot="card-header"
                                class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                                <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending 48-72h</h4>
                                <div class="p-1 md:p-1.5 bg-orange-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-clock h-3 w-3 md:h-4 md:w-4 text-orange-600"
                                        aria-hidden="true">
                                        <path d="M12 6v6l4 2"></path>
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg></div>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                                <div class="text-xl md:text-2xl font-bold"><?php echo $count_batch->pending_48_72; ?></div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">High priority</p>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card border-yellow-100">
                            <div data-slot="card-header"
                                class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                                <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending 24-48h</h4>
                                <div class="p-1 md:p-1.5 bg-yellow-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-clock h-3 w-3 md:h-4 md:w-4 text-amber-500" aria-hidden="true">
                                        <path d="M12 6v6l4 2"></path>
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg></div>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                                <div class="text-xl md:text-2xl font-bold"><?php echo $count_batch->pending_24_48; ?></div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Moderate</p>
                            </div>
                        </div>
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-header"
                                class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 flex flex-row items-center justify-between space-y-0 pb-1 md:pb-2">
                                <h4 data-slot="card-title" class="text-sm md:text-base font-semibold">Pending &lt;24h</h4>
                                <div class="p-1 md:p-1.5 bg-green-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg"
                                        width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-clock h-3 w-3 md:h-4 md:w-4 text-green-600" aria-hidden="true">
                                        <path d="M12 6v6l4 2"></path>
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg></div>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pb-1 md:pb-2">
                                <div class="text-xl md:text-2xl font-bold"><?php echo $count_batch->pending_under_24; ?></div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Normal range</p>
                            </div>
                        </div>
                    </div>
                    <div data-slot="card"
                        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header"
                            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-funnel h-5 w-5 text-primary" aria-hidden="true">
                                        <path
                                            d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z">
                                        </path>
                                    </svg>
                                    <h4 data-slot="card-title" class="leading-none">Filters</h4>
                                </div>

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

                            </div>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label class="text-xs text-gray-500 mb-1.5 block">Status</label>

                                    <select id="sel_status" name="sel_status">
                                        <option value="all">All Status</option>
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
                                        <?php foreach ($bucket as $key => $item): ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php if ($sel_bucket == $key) { ?>selected<?php } ?>>
                                                <?php echo $item['title']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs text-gray-500 mb-1.5 block">Panel</label>

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
                            </div>
                        </div>
                    </div>
                    <div data-slot="card"
                        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header"
                            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                            <h4 data-slot="card-title" class="leading-none">Detailed Pending List</h4>
                            <p data-slot="card-description" class="text-muted-foreground"><?php echo count($reports); ?> samples</p>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <div class="overflow-x-auto">
                                <table id="reports_table" class="w-full">
                                    <thead>
                                        <tr class="border-b bg-gray-50">
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Status <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Accession <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Panel <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Clinic <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Collection <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Received <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Age <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Bucket <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Plated <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                    stroke-linecap="round" stroke-linejoin="round"
                                                    class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40"
                                                    aria-hidden="true">
                                                    <path d="m21 16-4 4-4-4"></path>
                                                    <path d="M17 20V4"></path>
                                                    <path d="m3 8 4-4 4 4"></path>
                                                    <path d="M7 4v16"></path>
                                                </svg></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($reports as $row) {

                                        ?>
                                            <tr class="bg-gray-50">
                                                <td class="py-3 px-4">
                                                    <?php if (intVal($row['batch_id']) > 0) { ?>
                                                        <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground bg-blue-100 text-blue-800 border-blue-200">PENDING</span>
                                                    <?php } else { ?>
                                                        <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-secondary/90 bg-yellow-100 text-yellow-800">
                                                            NOT RECEIVED
                                                        </span>
                                                    <?php } ?>
                                                </td>
                                                <td class="py-3 px-4 text-sm"><?php echo $row['assen']; ?></td>
                                                <td class="py-3 px-4 text-sm"><?php echo $row['panels']; ?></td>
                                                <td class="py-3 px-4 text-sm"><?php echo $row['cli_clinic']; ?></td>
                                                <td class="py-3 px-4 text-sm"><?php echo date_i18n('m/d H:i', strtotime($row['batch_created'])); ?></td>
                                                <td class="py-3 px-4 text-sm text-gray-500"><?php echo date_i18n('m/d H:i', strtotime($row['spec_received'])); ?></td>
                                                <td class="py-3 px-4 text-sm"><?php echo $row['age_in_hours']; ?>h</td>
                                                <td class="py-3 px-4">
                                                    <?php
                                                    $bc = get_bucket_txt($row['doc'], $bucket);
                                                    ?>
                                                    <span data-slot="badge"
                                                        class="<?php echo $bc['class']; ?>">
                                                        <?php echo $bc['txt']; ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 text-sm">No</td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div data-slot="card"
                        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-4 pb-4">
                            <h4 class="text-sm font-semibold mb-2">Notes</h4>
                            <ul class="text-sm text-muted-foreground space-y-1 list-disc list-inside">
                                <li>Age = hours since collection</li>
                                <li>Bucket = &lt;24h, 24-48h, 48-72h, &gt;72h</li>
                                <li>Received timestamp is blank for NOT RECEIVED rows</li>
                            </ul>
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
    var sel_clinic = '<?php echo $sel_clinic; ?>';
    var btype = '<?php echo $btype_selected; ?>';
    var sel_status = '<?php echo $sel_status; ?>';
    var sel_bucket = '<?php echo $sel_bucket; ?>';
    var sel_panel = '<?php echo $sel_panel; ?>';

    function goto_url() {
        var $url = location.href;
        $url += '&sel_clinic=' + sel_clinic;
        $url += '&sel_status=' + sel_status;
        $url += '&sel_bucket=' + sel_bucket;
        $url += '&sel_panel=' + sel_panel;
        window.location.href = $url;
    }

    jQuery(function($) {
        window.$ = jQuery;
        update_ui_select();

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

            $('#reports_table th').click(function() {
                var table = $(this).parents('table').eq(0);
                var rows = table.find('tbody tr').toArray();
                var index = $(this).index();
                var type = $(this).data('type'); // 'number' hoặc 'string'
                var asc = !$(this).data('asc'); // toggle

                rows.sort(function(a, b) {
                    var A = $(a).children('td').eq(index).text();
                    var B = $(b).children('td').eq(index).text();

                    if (type === 'number') {
                        A = parseFloat(A);
                        B = parseFloat(B);
                    }

                    if (A < B) return asc ? -1 : 1;
                    if (A > B) return asc ? 1 : -1;
                    return 0;
                });

                $.each(rows, function(i, row) {
                    table.children('tbody').append(row);
                });

                $(this).data('asc', asc);
            });
        });

        $(".export_item").click(async function() {
            var export_func = $(this).attr("export_func");
            var export_type = $(this).attr("export_type");
            var $url = location.href + '&trajax=export_report&export_type=' + export_type;
            $url += '&export_func=' + export_func;
            $url += '&sel_clinic=' + sel_clinic;
            $url += '&sel_status=' + sel_status;
            $url += '&sel_bucket=' + sel_bucket;
            $url += '&sel_panel=' + sel_panel;
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
        });


        $("#sel_status").change(function() {
            sel_status = $(this).val();
            goto_url();
        });

        $("#sel_status").change(function() {
            sel_status = $(this).val();
            goto_url();
        });

        $("#sel_bucket").change(function() {
            sel_bucket = $(this).val();
            goto_url();
        });

        $("#sel_panel").change(function() {
            sel_panel = $(this).val();
            goto_url();
        });

        $("#sel_clinic").change(function() {
            sel_clinic = $(this).val();
            goto_url();
        });
    });
</script>