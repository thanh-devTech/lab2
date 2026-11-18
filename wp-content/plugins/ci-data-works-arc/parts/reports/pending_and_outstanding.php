<?php

global $wpdb, $current_role, $ci_all_btypes;
global $export_func;
if (isset($_GET['debug'])) {
    var_dump(tr_pdd_where_batch_rejected(
        false,
        true,
        $btype,
        ['deleted', 'archived', 'cancelled']
    ));
}
$schedule_report = new CI_Report_Schedule('current_delays', 'Current Delays');

ini_set('memory_limit', '1024M');
$start_time = microtime(true);

$to_date = !empty($_GET['to']) ? $_GET['to'] : date_i18n('Y-m-d');

$sel_status = isset($_REQUEST['sel_status']) ? $_REQUEST['sel_status'] : 'all';
$sel_clinic = isset($_REQUEST['sel_clinic']) ? $_REQUEST['sel_clinic'] : 'all';
$sel_panel = isset($_REQUEST['sel_panel']) ? $_REQUEST['sel_panel'] : 'all';
$sel_bucket = isset($_REQUEST['sel_bucket']) ? $_REQUEST['sel_bucket'] : 'all';
$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : '1';

$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';
$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$conditions = trpdd_get_batch_conditions(0, 'pcr');
$where_clinic = $conditions['where_clinic'];

$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic}");
foreach ($clinics_rs as $r) {
    $clinics[$r->cID] = $r;
}
$days_of_operation_lab = CI_Base_Lab::get_lab_operations();

$panels = [
    'uti'   => ['title' => 'UTI'],
    'resp'  => ['title' => 'RESP'],
    'wound' => ['title' => 'WOUND'],
    'sti'   => ['title' => 'STI'],
];

$bucketBaseClass = 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&]:hover:bg-secondary/90';

$bucket = [
    'timer_72' => [
        'title'     => '> 72 hours',
        'txt'       => '> 72h',
        'class_pdf' => 'bucket-badge bucket-critical',
        'class'     => $bucketBaseClass . ' bg-red-100 text-red-800',
    ],
    'timer_48_to_72' => [
        'title'     => '48-72 hours',
        'txt'       => '48 - 72h',
        'class_pdf' => 'bucket-badge bucket-high',
        'class'     => $bucketBaseClass . ' bg-orange-100 text-orange-800',
    ],
    'timer_24_to_48' => [
        'title'     => '24-48 hours',
        'txt'       => '24 - 48h',
        'class_pdf' => 'bucket-badge bucket-moderate',
        'class'     => $bucketBaseClass . ' bg-yellow-100 text-yellow-800',
    ],
    'timer_24' => [
        'title'     => '< 24 hours',
        'txt'       => '< 24h',
        'class_pdf' => 'bucket-badge bucket-normal',
        'class'     => $bucketBaseClass . ' bg-green-100 text-green-800',
    ],
];
if ($sel_bucket == 'over_24') {
    $sel_bucket = 'timer_over_24';
    $bucket['timer_over_24'] = ['title' => '≥ 24 hours'];
}
$status = [
    'not_received'   => ['title' => 'Not Received'],
    'pending' => ['title' => 'Pending Results'],
];

$debug_reports = [];

if (!function_exists('get_panels_and_tests')) {
    function get_panels_and_tests($ci_all_btypes)
    {
        global $wpdb;

        $all_panels = [];
        $all_tests = [];

        foreach ($ci_all_btypes as $btype) {
            // Get test panels for tox type
            if ($btype == 'tox') {
                $sql_tests = "SELECT te_group_id, te_name FROM ci_test_panels WHERE te_type = '{$btype}'";

                if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
                    $cache = new Tr_Cache_Query();
                    $rs = $cache->get_results($sql_tests, OBJECT, 300);
                } else {
                    $rs = $wpdb->get_results($sql_tests);
                }

                foreach ($rs as $r) {
                    $all_tests[$btype][strtoupper($r->te_name)] = $r->te_group_id;
                }
            }

            // Get test groups
            $sql_groups = "
                SELECT tg_ID, tg_name
                FROM ci_test_groups
                WHERE tg_type = '{$btype}'
                AND tg_parent = 0
                AND tg_show_req = 1
                ORDER BY tg_position ASC
            ";

            if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
                $cache = new Tr_Cache_Query();
                $rs = $cache->get_results($sql_groups, OBJECT, 300);
            } else {
                $rs = $wpdb->get_results($sql_groups);
            }

            foreach ($rs as $r) {
                $all_panels[$r->tg_ID] = str_replace('(new)', '', $r->tg_name);
            }
        }

        return ['panels' => $all_panels, 'tests' => $all_tests];
    }
}

if (!function_exists('extract_group_ids')) {
    function extract_group_ids($panel_tests_requested, $btype, $all_tests)
    {
        $groupsids = [];

        foreach (explode(',', $panel_tests_requested) as $t) {
            $t = trim($t);

            if (stripos($t, 'GROUP_') !== false) {
                $gid = str_replace('GROUP_', '', $t);
                if (!in_array($gid, $groupsids)) {
                    $groupsids[] = $gid;
                }
            } elseif ($btype == 'tox' && isset($all_tests['tox'][strtoupper($t)])) {
                $gid = $all_tests['tox'][strtoupper($t)];
                if (!in_array($gid, $groupsids)) {
                    $groupsids[] = $gid;
                }
            }
        }

        return $groupsids;
    }
}

if (!function_exists('build_panel_names')) {
    function build_panel_names($groupsids, $all_panels)
    {
        $panels_tmp = [];

        foreach ($groupsids as $gid) {
            if (isset($all_panels[$gid])) {
                $panels_tmp[] = $all_panels[$gid];
            }
        }

        return implode(', ', $panels_tmp);
    }
}

if (!function_exists('get_bucket_info')) {
    function get_bucket_info($row)
    {
        $age = (float)$row['bucket_hours'];
        $bucket_key = '';
        $bucket_vl = '';

        if ($age >= 72) {
            $bucket_vl = 4;
            $bucket_key = 'timer_72';
        } elseif ($age >= 48) {
            $bucket_vl = 3;
            $bucket_key = 'timer_48_to_72';
        } elseif ($age >= 24) {
            $bucket_vl = 2;
            $bucket_key = 'timer_24_to_48';
        } else {
            $bucket_vl = 1;
            $bucket_key = 'timer_24';
        }

        return ['bucket_key' => $bucket_key, 'bucket_vl' => $bucket_vl];
    }
}

if (!function_exists('filter_data')) {
    function filter_data($all_data, $all_tests, $all_panels, $bucket, $days_of_operation_lab, $filters, &$count_batch)
    {
        global $export_func;

        $filtered = [];

        foreach ($all_data as $row) {
            $from_date = $row['doc'];
            $completed_time = date_i18n('Y-m-d H:i:s');

            if (isset($_GET['dev_debug'])) {
                $row['from_date'] = $from_date;
                $row['to_date'] = $completed_time;
            }

            $row['age_in_hours'] = floor(CI_Base_Lab::calculate_business_hours($from_date, $completed_time, $row['lab_ID'], $days_of_operation_lab));
            if ($row['status'] === 'pending') {
                $row['bucket_hours'] = floor(CI_Base_Lab::calculate_business_hours($row['spec_received'], $completed_time, $row['lab_ID'], $days_of_operation_lab));
            } else {
                $row['bucket_hours'] = $row['age_in_hours'];
            }

            // Extract group IDs
            $groupsids = extract_group_ids($row['panel_tests_requested'], $row['btype'], $all_tests);

            // Add panels info
            $row['panels'] = build_panel_names($groupsids, $all_panels);
            $row['panels_id'] = $groupsids;

            // get bucket info
            $bk_info = get_bucket_info($row);
            $row['bucket'] = $bk_info['bucket_vl'];


            if ($row['bucket_hours'] >= 72) {
                $count_batch[$row['status'] . '_over_72']++;
            } elseif ($row['bucket_hours'] >= 48) {
                $count_batch[$row['status'] . '_48_72']++;
            } elseif ($row['bucket_hours'] >= 24) {
                $count_batch[$row['status'] . '_24_48']++;
            } else {
                $count_batch[$row['status'] . '_under_24']++;
            }

            //only filter when export
            if (!empty($export_func)) {
                // ===== FILTER BUCKET =====
                if ($filters['bucket']  == 'timer_over_24' && $bk_info['bucket_vl'] > 1) {
                } else if (!empty($filters['bucket']) && $filters['bucket'] !== 'all' && $filters['bucket'] !== $bk_info['bucket_key']) {
                    continue;
                }

                // ===== FILTER STATUS =====
                if (!empty($filters['status']) && $filters['status'] !== 'all' && $filters['status'] !== $row['status']) {
                    continue;
                }
            }




            $row['doc'] = date_i18n('m/d H:i', strtotime($row['doc']));
            $row['spec_received'] = date_i18n('m/d H:i', strtotime($row['spec_received']));

            $bk = $bucket[$bk_info['bucket_key']];

            $row['bucket_txt'] = isset($bk) ?  $bk['txt'] : '';
            $row['bucket_class'] = isset($bk) ?  $bk['class'] : '';
            $row['bucket_class_pdf'] = isset($bk) ?  $bk['class_pdf'] : '';

            unset($row['panel_tests_requested']);

            $filtered[] = $row;
        }

        return $filtered;
    }
}

// Query panels and tests mapping
$panels_tests = get_panels_and_tests($ci_all_btypes);
$all_panels = $panels_tests['panels'];
$all_tests = $panels_tests['tests'];

$schedule_report->ajax();

// Sort data
if ($trajax == 'loadData' || !empty($export_func) || isset($_GET['dev_debug'])) {

    function get_all_batch_data($filters = [])
    {
        global $wpdb, $ci_all_btypes, $export_func;

        $archive_date = tr_get_option('ci_pdf_data', 'archive_date', '');
        $date_prev_1month = date('Y-m-01', strtotime('-1 months', strtotime(date_i18n('Y-m-d'))));

        $all_data = [];

        foreach ($ci_all_btypes as $btype) {

            // filter btype
            if (!empty($filters['btype']) && $filters['btype'] !== 'all' && $filters['btype'] !== $btype) {
                continue;
            }

            $batch_tbl  = tr_pdd_get_table_by_btype($btype);
            $report_tbl = tr_pdd_get_table_report_by_btype($btype);

            $conditions = trpdd_get_batch_conditions(0, $btype);

            $where = ' ba.is_saved = 0 ' . $conditions['where'];

            if ($conditions['check_author']) {
                $where .= $conditions['where_author'];
            }

            // chưa completed
            $where .= " AND (re.id IS NULL OR re.estatus != 'C')";


            // loại rejected / archived / deleted / cancelled
            $where .= ' AND ' . tr_pdd_where_batch_rejected(
                false,
                true,
                $btype,
                ['deleted', 'archived', 'cancelled']
            );

            // 30 ngày gần nhất
            $where .= " AND ba.doc >= '{$date_prev_1month}' ";

            if (!empty($archive_date) && $date_prev_1month < $archive_date) {
                $where .= " AND ba.doc > '{$archive_date}' ";
            }


            // ===== FILTER CLINIC =====
            if (!empty($export_func)) { //only filter when export
                if (!empty($filters['clinic']) && $filters['clinic'] !== 'all') {
                    $where .= " AND ba.cli_ID = " . intval($filters['clinic']);
                }

                // ===== FILTER PANEL =====
                if (!empty($filters['panel']) && $filters['panel'] !== 'all') {
                    $where .= " AND FIND_IN_SET('GROUP_" . intval($filters['panel']) . "', ba.panel_tests_requested)";
                }
            }



            $sql = "
                SELECT
                    ba.doc,
                    ba.spec_received,
                    ba.assen,
                    ba.panel_tests_requested,
                    ba.cli_ID,
                    ba.lab_ID,
                    c.cli_clinic,
                    IF(ba.batch_id > 0, 'pending', 'not_received') as status,
                    IF(ba.batch_status='plated', 'Yes', 'No') as plated,
                    IF(rb.mid IS NULL, 'no', 'yes') AS received,
                    '{$btype}' AS btype
                FROM {$batch_tbl} ba
                LEFT JOIN {$report_tbl} re 
                    ON re.patient_id = ba.assen AND re.estatus = 'C'
                LEFT JOIN ci_receiving_batches rb ON rb.mid = ba.mid
                LEFT JOIN ci_clinic c ON c.cID = ba.cli_ID
                WHERE {$where}
                GROUP BY ba.mid
            ";

            if (stripos($_SERVER['HTTP_REFERER'], 'debug') !== false) {
                var_dump($sql);
            }
            if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
                $cache = new Tr_Cache_Query();
                $rows = $cache->get_results($sql, ARRAY_A, 300);
            } else {
                $rows = $wpdb->get_results($sql, ARRAY_A);
            }

            $all_data = array_merge($all_data, $rows);
        }

        return $all_data;
    }


    $filters = [
        'clinic' => $sel_clinic,
        'panel'  => $sel_panel,
        'bucket' => $sel_bucket,
        'status' => $sel_status,
    ];
    $count_batch = [
        'not_received_over_72'   => 0,
        'not_received_48_72'     => 0,
        'not_received_24_48'     => 0,
        'not_received_under_24'  => 0,
        'pending_over_72'        => 0,
        'pending_48_72'          => 0,
        'pending_24_48'          => 0,
        'pending_under_24'       => 0,
    ];

    $all_data   = get_all_batch_data($filters);

    $reports    = filter_data($all_data, $all_tests, $all_panels, $bucket, $days_of_operation_lab, $filters, $count_batch);

    $count_batch = (object)$count_batch;

    // Debug output
    if (isset($_GET['dev_debug'])) {
        $fields = ['assen', 'age_in_hours', 'bucket_hours', 'from_date', 'to_date'];
        foreach ($reports as $r) {
            $debug_reports[] = array_intersect_key($r, array_flip($fields));
        }
        echo '<pre>';
        echo "Total records: " . count($reports) . "\n";
        echo "Count batch: " . print_r($count_batch, true) . "\n";
        echo "Reports: " . print_r($debug_reports, true) . "\n";
        echo '</pre>';
    }
    $total_count = count($reports);
}

if ($trajax == 'loadData') {
    echo json_encode([
        'total_count' => $total_count,
        'reports' => $reports,
        'count_batch' => $count_batch
    ]);
    exit;
}

if ($export_func != '') {
    if ($filepath_save_csv == '' && $filepath_save_pdf == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Current Delays Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
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
    if ($sel_bucket != 'all') $cur_name_bucket = isset($bucket[$sel_bucket]) ? $bucket[$sel_bucket]['title'] : 'All Buckets';
    if ($sel_bucket == 'timer_over_24') $cur_name_bucket = '>= 24 hours';

    if ($export_func == 'export_report') {
        if ($export_type == "pdf" || $filepath_save_pdf != '') {
            $orientation = 'portrait';
            $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
            $period_export = $to_date;
            ob_start();
            $outfile = generate_report_filename("RPT", 'CurrentDelays', $period_export, '', 'pdf');
            require TRCPDD_PATH . 'parts/reports/pending_and_outstanding_pdf.php';
            $html = ob_get_clean();

            $ci_time_query_pdf = microtime(true) - $start_time;
            $start_time = microtime(true);

            if ($filepath_save_pdf != '') {
                if (function_exists('trdomepdf_build_pdf')) {
                    $result = trdomepdf_build_pdf($html, [
                        'size' => 'letter',
                        'orientation' => $orientation,
                        'filepath' => $filepath_save_pdf,
                        'download' => 0,
                        'compress' => 0,
                        'dpi' => 110,
                        'isHtml5ParserEnabled' => false,
                        'isJavascriptEnabled' => false,
                        'isPhpEnabled' => false,
                        'isFontSubsettingEnabled' => false,
                    ]);
                }
            } else {
                $pdf_opts = [
                    'size' => 'letter',
                    'orientation' => $orientation,
                    'filename' => $outfile,
                    'download' => 1,
                    'compress' => 1,
                    'dpi' => 110,
                    'isHtml5ParserEnabled' => false,
                    'isJavascriptEnabled' => false,
                    'isPhpEnabled' => false,
                    'isFontSubsettingEnabled' => false,
                ];
                if (isset($_GET['dbtime'])) {
                    $pdf_opts['download'] = 0;
                    unset($pdf_opts['filename']);
                    $pdf_opts['filepath'] = WP_CONTENT_DIR . '/cache/debug_pending_outstanding.pdf';
                }
                trpdd_build_pdf($html, $pdf_opts);
                if (isset($_GET['dbtime'])) {
                    global $ci_last_pdf_options;
                    $ci_time_build_pdf = microtime(true) - $start_time;
                    echo 'query time: ' . $ci_time_query_pdf . '<br>';
                    echo 'build pdf time: ' . $ci_time_build_pdf . '<br>';
                    echo 'pdf version: ' . (TRCDPDF_NEW ? 1 : 0) . '<br>';
                    echo 'total: ';
                    echo microtime(true) - $start_time;
                    echo '<br><br>';
                    echo 'options:';
                    echo '<pre>';
                    var_export($ci_last_pdf_options);
                    echo '</pre>';
                }
                exit;
            }
        }

        if ($export_type == "csv" || $filepath_save_csv != '') {
            $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
            $period_export =  $to_date;
            $outfile = generate_report_filename('RPT', 'CurrentDelays', $period_export, '', 'csv');
            tr_pdd_header('csv', $outfile);

            if ($filepath_save_csv != '') {
                $f = fopen($filepath_save_csv, 'w');
                $csv_out_file = basename($outfile);
            } else {
                $f = fopen('php://output', 'w');
            }

            fputcsv($f, ["Current Delays - $generated_on", ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ']);
            fputcsv($f, [' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ']);
            fputcsv($f, ["Filters"]);
            fputcsv($f, ["Panel", $cur_name_filter_panel]);
            fputcsv($f, ["Clinic", $cur_name_clinic]);
            fputcsv($f, ["Status", $cur_name_status]);
            fputcsv($f, ["Bucket", $cur_name_bucket]);
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
            fputcsv($f, ["STATUS", "ACCESSION", "PANEL", "CLINIC", "COLLECTION", "RECEIVED", "AGE", "BUCKET", "PLATED"]);
            foreach ($reports as $row) {
                $status = $row['status'] == 'pending' ? 'PENDING' : 'NOT RECEIVED';

                $clinic = $row['cli_clinic'] ?? '';
                $clinic = preg_replace('/[\r\n\t\s]+/', ' ', $clinic);
                $clinic = trim($clinic);

                $row['panels'] = str_replace('/', '', $row['panels']);

                fputcsv($f, [
                    $status ?? '',
                    $row['assen'] ?? '',
                    $row['panels'] ?? ' ',
                    $clinic ?? '',
                    $row['doc'] ?? ' ',
                    $row['spec_received'] ?? ' ',
                    $row['age_in_hours'] ?? '',
                    $row['bucket_txt'] ?? '',
                    $row['plated'] ?? ''
                ]);
            }
            fclose($f);
            if ($filepath_save_csv == '') exit;
        }
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

    .pagination-links a {
        background-color: #f1f1f1;
        padding: 8px;
        border-radius: 3px;
        padding-left: 10px;
        padding-right: 10px;
    }

    table tbody tr:nth-child(odd) {
        background-color: #ffffff;
    }

    table tbody tr:nth-child(even) {
        background-color: #f9fafb;
    }

    table tr th {
        white-space: nowrap;
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
            <?php if (!isset($hide_nav) || !$hide_nav) { ?>
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
            <?php } ?>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                        Current Delays</h1>
                    <p class="text-muted-foreground mt-1">Live view of delayed specimens by stage and time bracket</p>
                </div>
            </div>
            <div>
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div data-slot="card" id="box_not_received_over_72"
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
                                <div class="text-xl md:text-2xl font-bold count-batch">---</div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Critical Delay</p>
                            </div>
                        </div>
                        <div data-slot="card" id="box_not_received_48_72"
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
                                <div class="text-xl md:text-2xl font-bold count-batch">---</div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">High Delay</p>
                            </div>
                        </div>
                        <div data-slot="card" id="box_not_received_24_48"
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
                                <div class="text-xl md:text-2xl font-bold count-batch">---</div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Moderate Delay</p>
                            </div>
                        </div>
                        <div data-slot="card" id="box_not_received_under_24"
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
                                <div class="text-xl md:text-2xl font-bold count-batch">---</div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Normal range</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div data-slot="card" id="box_pending_over_72"
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
                                <div class="text-xl md:text-2xl font-bold count-batch">---</div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Critical Delay</p>
                            </div>
                        </div>
                        <div data-slot="card" id="box_pending_48_72"
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
                                <div class="text-xl md:text-2xl font-bold count-batch">---</div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">High Delay</p>
                            </div>
                        </div>
                        <div data-slot="card" id="box_pending_24_48"
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
                                <div class="text-xl md:text-2xl font-bold count-batch">---</div>
                                <p class="text-xs text-muted-foreground mt-0.5 md:mt-1">Moderate Delay</p>
                            </div>
                        </div>
                        <div data-slot="card" id="box_pending_under_24"
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
                                <div class="text-xl md:text-2xl font-bold count-batch">---</div>
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
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                    <div data-slot="card" id="box_detailed_pending_list"
                        class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-header"
                            class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                            <h4 data-slot="card-title" class="leading-none">Specimen List</h4>
                            <p data-slot="card-description" class="text-muted-foreground"><span id="total_samples">---</span> samples</p>
                        </div>
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                            <div class="overflow-x-auto">
                                <div class="table_nav top flex justify-end"></div>
                                <table id="reports_table" class="w-full">
                                    <thead>
                                        <tr class="border-b bg-gray-50">
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Status <?php echo icon_sort(); ?>
                                            </th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Accession <?php echo icon_sort(); ?>
                                            </th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Panel <?php echo icon_sort(); ?></th>
                                            <th
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Clinic <?php echo icon_sort(); ?></th>
                                            <th style="width:80px"
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Collection <?php echo icon_sort(); ?></th>
                                            <th style="width:80px"
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Received <?php echo icon_sort(); ?></th>
                                            <th style="width:40px"
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Age <?php echo icon_sort(); ?></th>
                                            <th style="width:60px"
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Bucket <?php echo icon_sort(); ?></th>
                                            <th style="width:60px"
                                                class="text-left py-3 px-4 text-xs text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none">
                                                Plated <?php echo icon_sort(); ?></th>
                                        </tr>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                                <div class="table_nav bottom flex justify-end"></div>
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

<div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
    <p class="text-xs text-gray-600 text-center">
        <strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
        igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
    </p>
</div>

<script>
    var sel_clinic = '<?php echo $sel_clinic; ?>';
    var sel_status = '<?php echo $sel_status; ?>';
    var sel_bucket = '<?php echo $sel_bucket; ?>';
    var sel_panel = '<?php echo $sel_panel; ?>';

    var allData = null;
    const url_main_page = 'ci_reports_pending_and_outstanding';
    const times = ['over_72', '48_72', '24_48', 'under_24']

    let filteredData = []; // Data sau khi filter
    let currentPage = '<?php echo $page; ?>';
    const itemsPerPage = 500;

    jQuery(function($) {
        window.$ = jQuery;
        update_ui_select();

        function toggleBoxesLoading(loading = true) {
            times.forEach(function(t) {
                if (loading) {
                    showLoading('box_not_received_' + t);
                    showLoading('box_pending_' + t);
                } else {
                    hideLoading('box_not_received_' + t);
                    hideLoading('box_pending_' + t);
                }
            })
            if (loading) {
                showLoading('box_detailed_pending_list');
            } else {
                hideLoading('box_detailed_pending_list');
            }

        }

        function renderCountBatchTimes(countBatch) {
            times.forEach(function(t) {
                $('#box_not_received_' + t).find('.count-batch').text(countBatch['not_received_' + t]);
                $('#box_pending_' + t).find('.count-batch').text(countBatch['pending_' + t]);
            })
        }

        function filterData(data) {
            return data.filter(function(item) {
                if (sel_status !== 'all') {
                    if (item.status !== sel_status) return false;
                }

                // Filter by bucket
                if (sel_bucket !== 'all') {
                    const bucketMap = {
                        'timer_72': 4,
                        'timer_48_to_72': 3,
                        'timer_24_to_48': 2,
                        'timer_24': 1
                    };
                    if (sel_bucket === 'timer_over_24' && item.bucket > 1) {} else if (item.bucket !== bucketMap[sel_bucket]) return false;
                }

                // Filter by panel
                if (sel_panel !== 'all') {
                    if (!item.panels_id.includes(sel_panel)) return false;
                }

                // Filter by clinic
                if (sel_clinic !== 'all') {
                    if (item.cli_ID != sel_clinic) return false;
                }

                return true;
            });
        }

        function renderPagination() {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);

            if (totalPages <= 1) {
                $('.table_nav').attr('style', 'display: none !important');
                return;
            }

            $('.table_nav').attr('style', 'display: flex');

            const btnClass = 'inline-flex items-center justify-center px-3 py-1.5 text-sm font-medium rounded-md transition-colors';
            const btnActive = 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50';
            const btnDisabled = 'bg-gray-100 border border-gray-200 text-gray-400 cursor-not-allowed';

            let paginationHtml = `
                <div class="flex items-center justify-between px-4 py-3 bg-white">
                    <div class="flex items-center gap-2">`;

            // First & Previous
            if (currentPage > 1) {
                paginationHtml += `
                    <a class="pagination-btn ${btnClass} ${btnActive}" href="#" data-page="1" title="First page">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg>
                    </a>
                    <a class="pagination-btn ${btnClass} ${btnActive}" href="#" data-page="${currentPage - 1}" title="Previous page">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </a>
                `;
            } else {
                paginationHtml += `
                    <span class="${btnClass} ${btnDisabled}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg>
                    </span>
                    <span class="${btnClass} ${btnDisabled}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </span>
                `;
            }

            // Page numbers
            paginationHtml += `
                <span class="px-3 py-1.5 text-sm text-gray-700">
                    Page <span class="font-medium">${currentPage}</span> of <span class="font-medium">${totalPages}</span>
                </span>
            `;

            // Next & Last
            if (currentPage < totalPages) {
                paginationHtml += `
                    <a class="pagination-btn ${btnClass} ${btnActive}" href="#" data-page="${currentPage + 1}" title="Next page">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                    <a class="pagination-btn ${btnClass} ${btnActive}" href="#" data-page="${totalPages}" title="Last page">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/></svg>
                    </a>
                `;
            } else {
                paginationHtml += `
                    <span class="${btnClass} ${btnDisabled}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </span>
                    <span class="${btnClass} ${btnDisabled}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 17 5-5-5-5"/><path d="m13 17 5-5-5-5"/></svg>
                    </span>
                `;
            }

            paginationHtml += `</div></div>`;

            $('.table_nav').html(paginationHtml);
        }

        function renderCurrentPage(filteredData) {
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageData = filteredData.slice(start, end);

            renderPendingList(pageData, filteredData.length);
            renderPagination(filteredData);
        }
        const val = v => (v === null || v === undefined || v === '' ? '-' : v);

        function renderPendingList(reports = [], totalCount = 0) {
            $('#total_samples').text(totalCount ?? 0);
            const tbBody = $('#reports_table tbody');
            tbBody.empty();
            reports.forEach(function(r, index) {
                var linkAssen = 'admin.php?page=ci_custody_chain&tab=list&assen=' + r.assen;
                const statusClass = 'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden';
                const rItem = `
                     <tr>
                        <td class="py-3 px-4">
                            ${r.status === 'pending' ? 
                            `<span data-slot="badge" class="${statusClass} [a&amp;]:hover:bg-accent [a&amp;]:hover:text-accent-foreground bg-blue-100 text-blue-800 border-blue-200">PENDING</span>` 
                            : `<span data-slot="badge" class="${statusClass} border-transparent [a&amp;]:hover:bg-secondary/90 bg-yellow-100 text-yellow-800">NOT RECEIVED</span>`
                            }
                
                        </td>
                        <td class="py-3 px-4 text-sm"><a href="${linkAssen}" target="_blank">${r.assen}</a></td>
                        <td class="py-3 px-4 text-sm">${r.panels}</td>
                        <td class="py-3 px-4 text-sm">${val(r.cli_clinic)}</td>
                        <td class="py-3 px-4 text-sm">${val(r.doc)}</td>
                        <td class="py-3 px-4 text-sm text-gray-500">${r.status === 'pending' ? r.spec_received : '-'}</td>
                        <td class="py-3 px-4 text-sm">${val(r.age_in_hours)}h</td>
                        <td class="py-3 px-4" data-bucket="${val(r.bucket)}">
                            <span data-slot="badge" class="${r.bucket_class}">${r.bucket_txt}</span>
                        </td>
                        <td class="py-3 px-4 text-sm">${r.plated}</td>
                     </tr>`;
                tbBody.append(rItem);
            })
        }

        function LoadData() {
            toggleBoxesLoading()

            $("#filter_loading").show();

            jQuery.post("admin.php?page=" + url_main_page, {
                trajax: 'loadData',
                sel_clinic: sel_clinic,
                sel_status: sel_status,
                sel_bucket: sel_bucket,
                sel_panel: sel_panel
            }, function(response) {
                const res = JSON.parse(response);
                if (res == null) return false;
                allData = res;
                filteredData = filterData(res.reports);
                currentPage = 1;

                renderCountBatchTimes(res.count_batch);
                renderCurrentPage(filteredData);
                $("#filter_loading").hide();
                toggleBoxesLoading(false)
            });
        }

        $(".btn-export").on("click", function(e) {
            e.stopPropagation();
            var id_btn = $(this).attr("id");
            $("#menu_" + id_btn).toggle();
        });

        $(document).on('click', '.pagination-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const newPage = parseInt($(this).data('page'));

            if (newPage && newPage !== currentPage && newPage > 0) {
                const isBottomPagination = $(this).closest('.table_nav.bottom').length > 0;

                currentPage = newPage;
                renderCurrentPage(filteredData);
                if (isBottomPagination) {
                    const tableOffset = $("#box_detailed_pending_list").offset();
                    if (tableOffset) {
                        $('html, body').animate({
                            scrollTop: tableOffset.top - 20
                        }, 400);
                    }
                }
            }

            return false;
        });

        $(document).on("click", function(e) {
            if (!$(e.target).closest(".custom-select").length) {
                $(".custom-select .options").hide();
            }

            if (!$(e.target).closest(".menu-export").length) {
                $(".menu-export").hide();
            }
        });

        function parseMonthDayTime(str) {
            if (!str) return 0;

            const parts = str.split(' ');
            if (parts.length !== 2) return 0;

            const [month, day] = parts[0].split('/').map(Number);
            const [hour, minute] = parts[1].split(':').map(Number);

            const year = new Date().getFullYear();
            return new Date(year, month - 1, day, hour, minute).getTime();
        }

        $('#reports_table th').click(function() {
            var table = $(this).parents('table').eq(0);
            var rows = table.find('tbody tr').toArray();
            var index = $(this).index();
            var asc = !$(this).data('asc');

            $('#reports_table th').find(".lucide-arrow-up-down").addClass("inline").show();
            $('#reports_table th').find(".lucide-arrow-up").removeClass("inline").hide();
            $('#reports_table th').find(".lucide-arrow-down").removeClass("inline").hide();
            $(this).find(".lucide-arrow-up-down").removeClass("inline").hide();

            rows.sort(function(a, b) {
                var cellA = $(a).children('td').eq(index);
                var cellB = $(b).children('td').eq(index);
                var A = cellA.text().trim();
                var B = cellB.text().trim();
                if (index === 4 || index === 5) {
                    A = parseMonthDayTime(A);
                    B = parseMonthDayTime(B);
                } else if (index === 7) {
                    const bucketOrder = {
                        '>72h': 4,
                        '48-72h': 3,
                        '24-48h': 2,
                        '<24h': 1
                    };
                    A = Number(cellA.data('bucket')) || 0;
                    B = Number(cellB.data('bucket')) || 0;
                } else {
                    const numA = parseFloat(A);
                    const numB = parseFloat(B);
                    if (!isNaN(numA) && !isNaN(numB)) {
                        A = numA;
                        B = numB;
                    }
                }

                if (A < B) return asc ? -1 : 1;
                if (A > B) return asc ? 1 : -1;
                return 0;
            });

            $.each(rows, function(i, row) {
                table.children('tbody').append(row);
            });

            if (asc) {
                $(this).find(".lucide-arrow-down").addClass('inline').show();
            } else {
                $(this).find(".lucide-arrow-up").addClass('inline').show();
            }

            $(this).data('asc', asc);
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
            const build_pdf_in_seconds = allData ? Math.round(allData.total_count / 100, 0) + 3 : 0;
            const loadingToast = showToast('info', 'Preparing your download...', `Generating...<span class="countdown">(${build_pdf_in_seconds})</span>`, 0, 'top_right');
            const timedown = setInterval(() => {
                if (loadingToast) {
                    const countdownSpan = loadingToast.querySelector('.countdown');
                    if (countdownSpan) {
                        let current = parseInt(countdownSpan.textContent.replace('(', '').replace(')', ''));
                        if (current > 0) {
                            countdownSpan.textContent = `(${current - 1})`;
                        } else {
                            clearInterval(timedown);
                        }
                    }
                }
            }, 1000);
            $('#menu_export_report').hide();
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
                    if (timedown) {
                        clearInterval(timedown);
                    }

                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }

                showToast('success', 'Download ready!', 'Your file "' + filename +
                    '" has been downloaded.', 4000, 'top_right');

            } catch (err) {
                showToast('error', 'Error', 'Download failed.', 4000, 'top_right');
            }
        });

        $("#sel_status").change(function() {
            sel_status = $(this).val();
            currentPage = 1;
            filteredData = filterData(allData.reports);
            renderCurrentPage(filteredData);
        });

        $("#sel_bucket").change(function() {
            sel_bucket = $(this).val();

            currentPage = 1;
            filteredData = filterData(allData.reports);
            renderCurrentPage(filteredData);
        });

        $("#sel_panel").change(function() {
            sel_panel = $(this).val();
            currentPage = 1;
            filteredData = filterData(allData.reports);
            renderCurrentPage(filteredData);
        });

        $("#sel_clinic").change(function() {
            sel_clinic = $(this).val();
            currentPage = 1;
            filteredData = filterData(allData.reports);
            renderCurrentPage(filteredData);
        });
        LoadData()
    });
</script>
<?php
$schedule_report->js();
?>