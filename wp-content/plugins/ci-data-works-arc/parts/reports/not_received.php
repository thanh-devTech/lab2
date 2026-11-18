<?php
global $ci_all_btypes, $wpdb;
$schedule_report  = new CI_Report_Schedule('not_received', 'Not Received');
$dispatch_schedule = new CI_Report_Dispatch_Schedule('not_received', 'Not Received');

$input = $_REQUEST;

$from_date        = !empty($input['from']) ? $input['from'] : '';
$sel_clinic       = isset($input['cli_ID']) ? $input['cli_ID'] : 'all';
$key_search       = isset($input['s']) ? $input['s'] : '';
$trajax           = isset($input['trajax']) ? $input['trajax'] : '';
$export_func      = isset($input['export_func']) ? $input['export_func'] : '';
$export_type      = isset($input['export_type']) ? $input['export_type'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';
$dispatch_only = !empty($_REQUEST['dispatch_only']);

$schedule_report->ajax();
$dispatch_schedule->ajax();

$clinics = array();
$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic ORDER BY cli_clinic ASC");
foreach ($clinics_rs as $r) {
    $clinics[$r->cID] = $r;
}

if (!function_exists('get_panels_and_tests')) {
    function get_panels_and_tests($ci_all_btypes)
    {
        global $wpdb;

        $all_panels = [];
        $all_tests = [];

        foreach ($ci_all_btypes as $btype) {
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
            if ($btype == 'study') {
                $sql_study = "SELECT ID as tg_ID, abrev as tg_name FROM ci_study_tests ORDER BY ID ASC";

                if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
                    $cache = new Tr_Cache_Query();
                    $rs = $cache->get_results($sql_study, OBJECT, 300);
                } else {
                    $rs = $wpdb->get_results($sql_study);
                }

                foreach ($rs as $r) {
                    $key = $btype . '_' . $r->tg_ID;
                    $all_panels[$key] = trim($r->tg_name);
                }
                continue;
            }


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
                $key = $btype . '_' . $r->tg_ID;
                $all_panels[$key] = str_replace('(new)', '', $r->tg_name);
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
                $rawgid = str_replace('GROUP_', '', $t);
                $gid = $btype . '_' . $rawgid;
                if (!in_array($gid, $groupsids)) {
                    $groupsids[] = $gid;
                }
            } elseif ($btype == 'tox' && isset($all_tests['tox'][strtoupper($t)])) {
                $rawgid = $all_tests['tox'][strtoupper($t)];
                $gid = $btype . '_' . $rawgid;
                if (!in_array($gid, $groupsids)) {
                    $groupsids[] = $gid;
                }
            } elseif ($btype == 'study' && is_numeric($t)) {
                $gid = $btype . '_' . $t;
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

if (!function_exists('getClinicNameById')) {
    function getClinicNameById($clinics, $cID)
    {
        foreach ($clinics as $clinic) {
            if ((string)$clinic->cID === (string)$cID) {
                return $clinic->cli_clinic;
            }
        }
        return null;
    }
}
$days_of_operation_lab = CI_Base_Lab::get_lab_operations();
$panels_tests = get_panels_and_tests($ci_all_btypes);
$all_panels = $panels_tests['panels'];
$all_tests = $panels_tests['tests'];

// AJAX Load Data
if ($trajax == 'loadData' || !empty($export_func) || isset($_GET['debug'])) {
    $conditions = trpdd_get_batch_conditions(0, '');
    $where = ' is_saved = 0 ' . $conditions['where'];
    $where .= " AND ba.batch_id = 0 ";

    $thirty_days_ago = date_i18n('Y-m-d', strtotime('-33 days'));
    $where .= " AND DATE(ba.doc) >= '{$thirty_days_ago}'";

    $rows = [];

    foreach (tr_pdd_get_batch_types() as $btype) {
        $where_btype = $where;
        $where_btype .= ' AND ' . tr_pdd_where_batch_rejected(
            false,
            true,
            $btype,
            ['deleted', 'archived', 'cancelled']
        );

        $batch_tbl = tr_pdd_get_table_by_btype($btype);

        $sql = "SELECT
            doc,
            assen,
            panel_tests_requested,
            lab_ID,
            cli_ID,
            '{$btype}' as btype,
            IF(rb.mid IS NULL, 'no', 'yes') as received
        FROM {$batch_tbl} ba
        LEFT JOIN ci_receiving_batches rb 
            ON rb.mid = ba.mid AND rb.btype = '{$btype}'
        WHERE {$where_btype}
        ORDER BY doc DESC";
        $result = $wpdb->get_results($sql, ARRAY_A);

        if (!empty($result)) {
            $rows = array_merge($rows, $result);
        }
    }

    if (isset($_GET['debug'])) {
        var_dump($sql);
        echo '<br/>total: ' . count($rows);
    }

    // Process data
    foreach ($rows as $index => $row) {
        if (empty($row['cli_ID'])) {
            unset($rows[$index]);
            continue;
        }
        $row['cli_clinic'] = getClinicNameById($clinics, $row['cli_ID']);
        $groupsids = extract_group_ids($row['panel_tests_requested'], $row['btype'], $all_tests);
        $row['panels'] = build_panel_names($groupsids, $all_panels);

        // Calculate hours since DOC
        if ($row['doc']) {
            $completed_time = date_i18n('Y-m-d H:i:s');
            $row['hours_since_doc'] = round(CI_Base_Lab::calculate_business_hours($row['doc'], $completed_time, $row['lab_ID'], $days_of_operation_lab));
        } else {
            $row['hours_since_doc'] = 0;
        }

        $rows[$index] = $row;
    }

    // Sort by doc DESC
    usort($rows, function ($a, $b) {
        return strtotime($b['doc']) - strtotime($a['doc']);
    });
}

if ($trajax == 'loadData') {
    // Calculate statistics on all data
    $not_received_rows = array_filter($rows, function ($r) {
        return $r['received'] == 'no';
    });
    $total_not_received = count($not_received_rows);

    // Calculate longest delay
    $longest_delay_hours = 0;
    $oldest_doc = null;
    foreach ($not_received_rows as $r) {
        if ($r['hours_since_doc'] > $longest_delay_hours) {
            $longest_delay_hours = $r['hours_since_doc'];
            $oldest_doc = $r['doc'];
        }
    }

    // Calculate clinics affected (not received > 24h)
    $clinics_affected = [];
    $clinic_counts = [];
    foreach ($not_received_rows as $r) {
        if ($r['hours_since_doc'] > 24) {
            if (!in_array($r['cli_ID'], $clinics_affected)) {
                $clinics_affected[] = $r['cli_ID'];
            }
        }

        if (!isset($clinic_counts[$r['cli_ID']])) {
            $clinic_counts[$r['cli_ID']] = 0;
        }
        $clinic_counts[$r['cli_ID']]++;
    }

    // Find most impacted clinic
    $most_impacted_clinic = null;
    $max_count = 0;
    foreach ($clinic_counts as $cli_id => $count) {
        if ($count > $max_count) {
            $max_count = $count;
            $most_impacted_clinic = $cli_id;
        }
    }
    $most_impacted_clinic_name = $most_impacted_clinic ? getClinicNameById($clinics, $most_impacted_clinic) : 'N/A';

    // Breakdown by date
    $date_breakdown = [];
    foreach ($not_received_rows as $r) {
        $date = substr($r['doc'], 0, 10);
        if (!isset($date_breakdown[$date])) {
            $date_breakdown[$date] = 0;
        }
        $date_breakdown[$date]++;
    }
    arsort($date_breakdown);

    // Breakdown by clinic
    $clinic_breakdown = [];
    foreach ($not_received_rows as $r) {
        $clinic_name = $r['cli_clinic'];
        if (!isset($clinic_breakdown[$r['cli_ID']])) {
            $clinic_breakdown[$r['cli_ID']] = [
                'name' => $clinic_name,
                'count' => 0
            ];
        }
        $clinic_breakdown[$r['cli_ID']]['count']++;
    }
    uasort($clinic_breakdown, function ($a, $b) {
        return $b['count'] - $a['count'];
    });

    echo json_encode([
        'rows' => $rows,
        'statistics' => [
            'total_not_received' => $total_not_received,
            'longest_delay_hours' => round($longest_delay_hours, 1),
            'clinics_affected_count' => count($clinics_affected),
            'most_impacted_clinic_name' => $most_impacted_clinic_name,
            'date_breakdown' => $date_breakdown,
            'clinic_breakdown' => $clinic_breakdown
        ]
    ]);
    exit;
}

if ($export_func != '') {
    if ($filepath_save_csv == '' && $filepath_save_pdf == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Not Received Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    }
    // For export, apply filters
    $filtered_rows = $rows;

    if ($dispatch_only) {
        $filtered_rows = array_filter($filtered_rows, function ($r) {
            return $r['received'] == 'no';
        });
    }

    if ($from_date != '') {
        $filtered_rows = array_filter($filtered_rows, function ($r) use ($from_date) {
            return substr($r['doc'], 0, 10) == $from_date;
        });
    }

    if ($sel_clinic != 'all') {
        $filtered_rows = array_filter($filtered_rows, function ($r) use ($sel_clinic) {
            return $r['cli_ID'] == $sel_clinic;
        });
    }

    if ($key_search !== '') {
        $filtered_rows = array_filter($filtered_rows, function ($r) use ($key_search) {
            return stripos($r['assen'], $key_search) !== false ||
                stripos($r['panels'], $key_search) !== false;
        });
    }

    if ($dispatch_only) {
        usort($filtered_rows, function ($a, $b) {
            $clinic_compare = strcasecmp($a['cli_clinic'] ?? '', $b['cli_clinic'] ?? '');
            if ($clinic_compare !== 0) {
                return $clinic_compare;
            }

            return strtotime($a['doc']) - strtotime($b['doc']);
        });
    } else {
        usort($filtered_rows, function ($a, $b) {
            return strtotime($a['doc']) - strtotime($b['doc']);
        });
    }

    if ($export_type == "csv" || $filepath_save_csv != '') {
        $period_export = date_i18n('Y-m-d');
        $outfile = generate_report_filename('RPT', 'NotReceived', $period_export, '', 'csv');
        tr_pdd_header('csv', $outfile);

        if ($filepath_save_csv != '') {
            $f = fopen($filepath_save_csv, 'w');
            $csv_out_file = basename($outfile);
        } else {
            $f = fopen('php://output', 'w');
        }
        fputcsv($f, ["DOC", "Age", "Accession", "Clinic", "Panel", "Status"]);

        foreach ($filtered_rows as $r) {
            $status = $r['received'] == 'no' ? 'Not Received' : 'Received';
            fputcsv($f, [$r['doc'], $r['hours_since_doc'] . 'h', $r['assen'], $r['cli_clinic'], $r['panels'], $status]);
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
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php $schedule_report->style(); ?>
<?php $dispatch_schedule->style(); ?>
<style>
    h4 {
        margin: unset !important;
    }

    .icon_sort {
        color: #898787ff;
    }

    .bg-orange-50\/40 {
        background-color: color-mix(in oklab, #fff7ed 40%, transparent);
    }

    .mt-4 {
        margin-top: 0.75rem;
    }

    .text-gray-6 {
        color: var(--color-gray-600);
    }

    .text-white {
        color: var(--color-white);
    }

    .shadow-primary {
        box-shadow: 0 0 0 1px #d83d00;
    }

    .hover\:border-gray-200:hover {
        border-color: var(--color-gray-200);
    }

    .font-black {
        font-weight: 900;
    }

    .text-\[12px\] {
        font-size: 12px;
    }

    .text-\[13px\] {
        font-size: 13px;
    }

    #btn_export {
        padding-left: 1.5rem !important;
        padding-right: 1.85rem !important;
        transition: background-color 0.2s ease, box-shadow 0.2s ease;
    }

    #btn_export:hover {
        background-color: #c73600 !important;
        box-shadow: 0 6px 18px rgba(216, 61, 0, 0.18);
    }
</style>
<div class="wrap flex-1 p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">Not Received</h1>
                <p class="text-muted-foreground mt-1s">Samples collected in the last 30 days that have not yet been received.</p>
            </div>
            <div class="flex items-center justify-start sm:justify-end">
                <?php $dispatch_schedule->btn_schedule(); ?>
            </div>
        </div>
        <div>
            <div class="space-y-4">
                <!-- Top Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div data-slot="card" id="stat-not-received" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-muted-foreground">Not Received</p>
                                    <strong class="text-2xl font-bold" id="total_not_received">---</strong>
                                </div>
                                <div class="p-3 bg-orange-50 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clipboard-list h-6 w-6 text-[#D83D00]">
                                        <rect width="8" height="4" x="8" y="2" rx="1" ry="1"></rect>
                                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                                        <path d="M12 11h4"></path>
                                        <path d="M12 16h4"></path>
                                        <path d="M8 11h.01"></path>
                                        <path d="M8 16h.01"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-slot="card" id="stat-longest-delay" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-muted-foreground">Longest Delay</p>
                                    <strong class="text-2xl" id="longest_delay">---</strong>
                                </div>
                                <div class="p-3 bg-amber-50 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-alert h-6 w-6 text-amber-600">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" x2="12" y1="8" y2="12"></line>
                                        <line x1="12" x2="12.01" y1="16" y2="16"></line>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-slot="card" id="stat-clinics-affected" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-muted-foreground">Clinics Affected</p>
                                    <strong class="text-2xl" id="clinics_affected">---</strong>
                                </div>
                                <div class="p-3 bg-indigo-50 rounded-lg" style="background-color: #eef2ff">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-house h-6 w-6 text-[#3C3D59]">
                                        <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"></path>
                                        <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div data-slot="card" id="stat-most-impacted" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                        <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-6">
                            <div class="flex items-center justify-between">
                                <div style="max-width:80%">
                                    <p class="text-sm text-muted-foreground">Most Impacted</p>
                                    <strong style="max-width: 100%;display: inline-block;" class="text-xl truncate max-w-[120px]" id="most_impacted" title="">---</strong>
                                </div>
                                <div class="p-3 bg-red-50 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-alert h-6 w-6 text-red-600">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" x2="12" y1="8" y2="12"></line>
                                        <line x1="12" x2="12.01" y1="16" y2="16"></line>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Breakdown Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Calendar -->
                    <div data-slot="card" class="bg-card text-card-foreground flex flex-col rounded-xl border shadow-card overflow-hidden gap-2">
                        <div data-slot="card-header" style="padding:24px 20px;" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 has-data-[slot=card-action]:grid-cols-[1fr_auto] h-14 border-b border-gray-100 flex flex-row items-center justify-between space-y-0">
                            <div class="flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar h-4 w-4 text-[#D83D00]">
                                    <path d="M8 2v4"></path>
                                    <path d="M16 2v4"></path>
                                    <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                    <path d="M3 10h18"></path>
                                </svg>
                                <div class="flex items-baseline gap-2">
                                    <h4 data-slot="card-title" class="text-[14px] font-bold text-gray-900 tracking-tight">Not Received</h4>
                                    <span class="text-[11px] font-medium text-gray-400">(Last 30 Days)</span>
                                </div>
                            </div>
                            <button style="font-size: 10px; font-weight: bold;line-height: 15px;" class="btn_show_all px-2 py-0.5 rounded-md border transition-all uppercase text-gray-500 bg-white border-gray-200 hover:border-gray-300">SHOW ALL</button>
                        </div>
                        <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-0">
                            <div style="height: 180px;" id="calendar_container_wrapper" class="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200 px-3 py-1 scroll-smooth">
                                <div class="grid gap-2" style="grid-template-columns: repeat(7, 1fr);" id="calendar_container">
                                    <div class="px-4 py-8 text-center text-sm text-gray-500 col-span-7">Loading...</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Breakdown by Clinic -->
                    <div data-slot="card" class="bg-card text-card-foreground flex flex-col rounded-xl border shadow-card overflow-hidden gap-2">
                        <div data-slot="card-header" style="padding:24px 20px;" class="@container/card-header auto-rows-min grid-rows-[auto_auto] gap-1.5 has-data-[slot=card-action]:grid-cols-[1fr_auto] h-14 border-b border-gray-100 flex flex-row items-center justify-between space-y-0">
                            <h4 data-slot="card-title" class="text-[14px] font-bold text-gray-900 tracking-tight">Breakdown by Clinic</h4>
                            <button style="font-size: 10px; font-weight: bold;line-height: 15px;" class="btn_show_all px-2 py-0.5 rounded-md border transition-all uppercase text-gray-500 bg-white border-gray-200 hover:border-gray-300">SHOW ALL</button>
                        </div>
                        <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-0">
                            <div style="height: 180px;" class="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200 scroll-smooth">
                                <div class="divide-y divide-gray-50 pt-1" id="clinic_breakdown_container">
                                    <div class="px-4 py-3 text-center text-sm text-gray-500">Loading...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Area -->
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-6">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-tight">DOC</span>
                                    <input type="date" id="from_date" data-slot="input" placeholder="yyyy-mm-dd" style="box-shadow: none; border-radius: 8px; background-color: #f8f9fb; border: 1px solid #e5e7eb;"
                                        class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex min-w-0 rounded-md border px-3 py-1 bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 w-44 text-sm border-gray-200 focus:ring-[#D83D00]" value="<?php echo $from_date; ?>">
                                </div>

                                <div class="flex items-center">
                                    <select id="sel_clinic" name="sel_clinic" style="box-shadow: none; border-radius: 8px; background-color: #f8f9fb; border: 1px solid #e5e7eb; padding: 0.25rem 0.75rem; height: 2.25rem; font-size: 0.875rem;" class="focus:ring-[#D83D00] focus:border-[#D83D00] outline-none transition-[color,box-shadow]">
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

                            <div class="flex inline-flex justify-center gap-3">
                                <button id="btn_export" data-slot="button" class="cursor-pointer inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive py-2 has-[&gt;svg]:px-3 bg-[#D83D00] hover:bg-[#D83D00]/90 text-white h-9 px-6 font-medium transition-all">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download h-4 w-4 mr-2" aria-hidden="true">
                                        <path d="M12 15V3"></path>
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <path d="m7 10 5 5 5-5"></path>
                                    </svg>Export
                                </button>

                                <?php $schedule_report->btn_schedule(); ?>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3 border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-orange-50 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-alert h-4 w-4 text-[#D83D00]" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" x2="12" y1="8" y2="12"></line>
                                        <line x1="12" x2="12.01" y1="16" y2="16"></line>
                                    </svg></div>
                                <div class="flex items-baseline gap-2">
                                    <h4 data-slot="card-title" class="text-[15px] font-semibold text-gray-900">Pending Receipt List</h4>
                                    <span class="text-xs text-gray-400" id="filter_text">(All Dates)</span>
                                </div>
                            </div>
                            <div class="relative w-64">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" aria-hidden="true">
                                    <path d="m21 21-4.34-4.34"></path>
                                    <circle cx="11" cy="11" r="8"></circle>
                                </svg>
                                <input
                                    id="inp_search" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex w-full min-w-0 rounded-md border px-3 py-1 bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive pl-9 h-8 text-xs border-gray-200 focus:ring-[#D83D00]"
                                    placeholder="Search samples..."
                                    value="<?php echo $key_search; ?>">
                            </div>
                        </div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 pt-4">
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div data-slot="table-container" class="relative w-full overflow-x-auto">
                                <table id="table_report" data-slot="table" class="w-full caption-bottom text-sm">
                                    <thead data-slot="table-header" class="[&amp;_tr]:border-b bg-gray-100">
                                        <tr data-slot="table-row" class="data-[state=selected]:bg-muted transition-colors hover:bg-transparent border-b border-gray-200">
                                            <th data-slot="table-head" class="text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-3 text-[12px] font-bold text-gray-700">
                                                <div class="flex items-center gap-1 cursor-pointer">DOC <?php echo icon_sort(); ?></div>
                                            </th>
                                            <th data-slot="table-head" class="text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-3 text-[12px] font-bold text-gray-700">
                                                <div class="flex items-center gap-1 cursor-pointer">Age <?php echo icon_sort(); ?></div>
                                            </th>
                                            <th data-slot="table-head" class="text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-3 text-[12px] font-bold text-gray-700">
                                                <div class="flex items-center gap-1 cursor-pointer">Accession <?php echo icon_sort(); ?></div>
                                            </th>
                                            <th data-slot="table-head" class="text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-3 text-[12px] font-bold text-gray-700">
                                                <div class="flex items-center gap-1 cursor-pointer">Clinic <?php echo icon_sort(); ?></div>
                                            </th>
                                            <th data-slot="table-head" class="text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-3 text-[12px] font-bold text-gray-700">
                                                <div class="flex items-center gap-1 cursor-pointer">Panel <?php echo icon_sort(); ?></div>
                                            </th>
                                            <th data-slot="table-head" class="text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-3 text-[12px] font-bold text-gray-700">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody data-slot="table-body" class="[&amp;_tr:last-child]:border-0">
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading data...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var from_date = '<?php echo $from_date; ?>';
    var sel_clinic = '<?php echo $sel_clinic; ?>';
    var key_search = '<?php echo $key_search; ?>';
    var url_main_page = 'ci_reports_not_received';
    var allData = [];
    var filteredData = [];
    var allStatistics = null;
    var currentSort = {
        index: 0,
        asc: true
    };

    function updateUrlParams() {
        var url = new URL(window.location.href);
        var params = url.searchParams;

        if (from_date) {
            params.set('from', from_date);
        } else {
            params.delete('from');
        }

        if (sel_clinic && sel_clinic != 'all') {
            params.set('cli_ID', sel_clinic);
        } else {
            params.delete('cli_ID');
        }

        if (key_search) {
            params.set('s', key_search);
        } else {
            params.delete('s');
        }

        var newUrl = url.pathname + '?' + params.toString();

        if (window.history.pushState) {
            window.history.pushState({
                path: newUrl
            }, '', newUrl);
        }
    }

    function filterData() {
        filteredData = allData.filter(function(row) {
            // Filter by date
            if (from_date && row.doc) {
                var rowDate = row.doc.substring(0, 10);
                if (rowDate !== from_date) {
                    return false;
                }
            }

            // Filter by clinic
            if (sel_clinic && sel_clinic !== 'all') {
                if (row.cli_ID != sel_clinic) {
                    return false;
                }
            }

            // Filter by search
            if (key_search) {
                var searchLower = key_search.toLowerCase();
                if (row.assen.toLowerCase().indexOf(searchLower) === -1 &&
                    row.panels.toLowerCase().indexOf(searchLower) === -1 &&
                    row.cli_clinic.toLowerCase().indexOf(searchLower) === -1
                ) {
                    return false;
                }
            }

            return true;
        });

        sortFilteredData();
        renderTable();
        updateFilterText();
        updateStatistics();
        updateCalendarUI();
        updateClinicBreakdownForDate();
    }

    function getDefaultSortDirection(index) {
        if (index === 1) {
            return false;
        }

        return true;
    }

    function compareRows(a, b, index, asc) {
        var valueA = '';
        var valueB = '';

        switch (index) {
            case 0:
                valueA = a.doc || '';
                valueB = b.doc || '';
                break;
            case 1:
                valueA = parseFloat(a.hours_since_doc) || 0;
                valueB = parseFloat(b.hours_since_doc) || 0;
                break;
            case 2:
                valueA = a.assen || '';
                valueB = b.assen || '';
                break;
            case 3:
                valueA = a.cli_clinic || '';
                valueB = b.cli_clinic || '';
                break;
            case 4:
                valueA = a.panels || '';
                valueB = b.panels || '';
                break;
            default:
                valueA = '';
                valueB = '';
                break;
        }

        if (typeof valueA === 'number' && typeof valueB === 'number') {
            return asc ? valueA - valueB : valueB - valueA;
        }

        valueA = String(valueA).toLowerCase();
        valueB = String(valueB).toLowerCase();

        return asc ?
            valueA.localeCompare(valueB, undefined, {
                sensitivity: 'base'
            }) :
            valueB.localeCompare(valueA, undefined, {
                sensitivity: 'base'
            });
    }

    function sortFilteredData() {
        filteredData.sort(function(a, b) {
            return compareRows(a, b, currentSort.index, currentSort.asc);
        });
    }

    function applySortIndicators() {
        var headers = $('#table_report th');

        headers.find(".icon_sort").css("color", "#898787ff");
        headers.find(".lucide-arrow-up-down").addClass("inline").show();
        headers.find(".lucide-arrow-up").removeClass("inline").hide();
        headers.find(".lucide-arrow-down").removeClass("inline").hide();
        headers.data('is-sorting', false);
        headers.data('asc', null);

        var activeHeader = headers.eq(currentSort.index);
        if (!activeHeader.find('.icon_sort').length) {
            return;
        }

        activeHeader.find(".lucide-arrow-up-down").removeClass("inline").hide();
        activeHeader.find(".icon_sort").css("color", "#313131ff");

        if (currentSort.asc) {
            activeHeader.find(".lucide-arrow-down").addClass('inline').show();
        } else {
            activeHeader.find(".lucide-arrow-up").addClass('inline').show();
        }

        activeHeader.data('asc', currentSort.asc);
        activeHeader.data('is-sorting', true);
    }

    function renderTable() {
        var tbody = $('#table_report tbody');
        tbody.empty();

        if (filteredData.length === 0) {
            tbody.append('<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">The list is empty.</td></tr>');
            return;
        }

        filteredData.forEach(function(r) {
            var statusClass = r.received == 'no' ? 'bg-yellow-100 text-yellow-800' : 'text-green-700 bg-green-50 border-green-200';
            var statusText = r.received == 'no' ? 'NOT RECEIVED' : 'RECEIVED';
            var rowClass = r.received == 'no' ? 'bg-white' : 'bg-white';

            var row = `
                <tr data-slot="table-row" class="data-[state=selected]:bg-muted hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0 ${rowClass}">
                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap px-4 py-2 text-[13px] text-gray-600">
                        ${r.doc}
                    </td>
                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap px-4 py-2 text-[13px] text-gray-600">
                        ${r.hours_since_doc}h
                    </td>
                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap px-4 py-2 text-[13px] text-gray-900">
                        <a href="?page=ci_custody_chain&assen=${r.assen}&clinic&from&to" target="_blank">${r.assen}</a>
                    </td>
                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap px-4 py-2 text-[13px] text-gray-700">
                        ${r.cli_clinic}
                    </td>
                    <td data-slot="table-cell" class="p-2 align-middle px-4 py-2 text-[13px] text-gray-700">
                        ${r.panels}
                    </td>
                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap px-4 py-2">
                        <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 gap-1 border-transparent ${statusClass}">
                            ${statusText}
                        </span>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        applySortIndicators();
    }

    function updateFilterText() {
        var text = '';
        if (from_date) {
            text = from_date;
        } else {
            text = 'All Dates';
        }
        $('#filter_text').text('(' + text + ')');
    }

    function updateStatistics() {
        if (allStatistics) {
            $('#total_not_received').text(allStatistics.total_not_received || 0);
            $('#longest_delay').text((allStatistics.longest_delay_hours ? Math.round(allStatistics.longest_delay_hours) : 0) + 'h');
            $('#clinics_affected').text(allStatistics.clinics_affected_count || 0);

            var mostImpacted = allStatistics.most_impacted_clinic_name || 'N/A';
            $('#most_impacted').text(mostImpacted).attr('title', mostImpacted === 'N/A' ? '' : mostImpacted);
            return;
        }

        $('#total_not_received').text(0);
        $('#longest_delay').text('0h');
        $('#clinics_affected').text(0);
        $('#most_impacted').text('N/A').attr('title', '');
    }

    function generateCalendar() {
        if (!allStatistics) return;

        var container = $('#calendar_container');
        container.empty();
        var todayStr = '<?php echo date_i18n('Y-m-d'); ?>';
        var parts = todayStr.split('-');
        var today = new Date(parts[0], parts[1] - 1, parts[2]); // year, month (0-indexed), day

        var startDate = new Date(today);
        startDate.setDate(today.getDate() - 33);

        var endDate = new Date(today);
        endDate.setDate(today.getDate() + 1);

        var lowerLimit = new Date(today);
        lowerLimit.setDate(today.getDate() - 29);

        var dates = [];
        for (var d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
            dates.push(new Date(d));
        }

        var dayNames = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
        var monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        dates.forEach(function(date) {
            var year = date.getFullYear();
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');
            var dateStr = year + '-' + month + '-' + day;
            var count = allStatistics.date_breakdown[dateStr] || 0;
            var dayName = dayNames[date.getDay()];
            var day = date.getDate();
            var isToday = dateStr === '<?php echo date_i18n('Y-m-d'); ?>';
            var isFuture = date > today;
            var isTooOld = date < lowerLimit;
            var isDisabled = isFuture || isTooOld;

            var showMonth = day === 1 || (date.getTime() === startDate.getTime());
            var monthLabel = showMonth ? `<span style="font-size: 7px;font-weight: 900;" class="text-[#D83D00] uppercase leading-none">${monthNames[date.getMonth()]}</span>` : '';

            var isSelected = from_date === dateStr;

            var btnClass = 'bg-white border-gray-100 hover:border-gray-200';
            var dayClass = 'text-[#3C3D59]';
            var badgeClass = 'bg-gray-50/50 text-gray-300';
            if (monthLabel == '') {
                badgeClass += ' mt-4';
            }

            if (isDisabled) {
                btnClass = 'opacity-30 cursor-not-allowed bg-gray-50/50 border-dashed grayscale';
            } else if (isToday) {
                btnClass = 'bg-orange-50/40 border-orange-200 today';
                dayClass = 'text-primary';
            } else if (isSelected) {
                btnClass = 'border-primary shadow-primary';
                dayClass = 'text-primary';
            }

            if (count > 0) {
                badgeClass = 'bg-gray-100 text-gray-6 positive';
            }

            var btn = `
                <button ${isDisabled ? 'disabled' : ''} style="justify-content: space-between; ${isDisabled ? 'cursor: not-allowed;' : ''}" class="date-btn flex flex-col items-center pt-4 pb-2 px-1 rounded-xl border transition-all h-[82px] w-full relative ${btnClass}" data-date="${dateStr}">
                    <div style="gap: 2px;" class="flex flex-col items-center">
                        <span style="font-size: 8px;" class="font-bold text-gray-400 leading-none">${dayName}</span>
                        ${monthLabel}
                        <span style="font-size: 15px; font-weight: 900;" class="leading-none mt-0.5 ${dayClass}">${day}</span>
                    </div>
                    <div style="font-size: 12px; font-weight: 900; width: auto; box-sizing: border-box;" class="h-6 mt-auto px-2 rounded-full flex items-center justify-center font-black transition-colors ${badgeClass}">${count}</div>
                </button>
            `;
            container.append(btn);
        });
        $('#calendar_container_wrapper').scrollTop($('#calendar_container_wrapper')[0]?.scrollHeight);

        bindCalendarEvents();
    }

    function bindCalendarEvents() {
        $('.date-btn').off('click').on('click', function(e) {
            e.preventDefault();
            var date = $(this).data('date');
            from_date = date;
            $('#from_date').val(date);
            $('#sel_clinic').val('all');
            sel_clinic = 'all';
            refreshCustomSelect('sel_clinic');
            filterData();
            updateUrlParams();
            updateButtonStates();
        });
    }

    function updateCalendarUI() {
        $('.date-btn').removeClass('border-primary shadow-primary')
            .addClass('bg-white border-gray-100 hover:border-gray-200')
            .find('span:last-of-type').removeClass('text-primary').addClass('text-gray-6');
        $('.date-btn').find('div').removeClass('text-white bg-primary');

        if (from_date) {
            $('.date-btn[data-date="' + from_date + '"]')
                .removeClass('bg-white border-gray-100 hover:border-gray-200')
                .addClass('border-primary shadow-primary')
                .find('span:last-of-type').removeClass('text-gray-6').addClass('text-primary');
            $('.date-btn[data-date="' + from_date + '"]').find('div.positive').addClass('text-white bg-primary');
        }
    }

    function updateClinicBreakdownForDate() {
        if (!allStatistics) return;

        var container = $('#clinic_breakdown_container');
        container.empty();

        // Get clinics for selected date or all dates
        var clinicCounts = {};

        allData.forEach(function(row) {
            if (row.received !== 'no') return;

            // Filter by date if selected
            if (from_date) {
                var rowDate = row.doc.substring(0, 10);
                if (rowDate !== from_date) return;
            }

            if (!clinicCounts[row.cli_ID]) {
                clinicCounts[row.cli_ID] = {
                    name: row.cli_clinic,
                    count: 0
                };
            }
            clinicCounts[row.cli_ID].count++;
        });

        // Sort by count
        var sortedClinics = Object.keys(clinicCounts).map(function(cliId) {
            return {
                cliId: cliId,
                name: clinicCounts[cliId].name,
                count: clinicCounts[cliId].count
            };
        }).sort(function(a, b) {
            return b.count - a.count;
        }).filter(function(c) {
            return c.count > 0;
        });

        if (sortedClinics.length === 0) {
            container.append('<div class="px-4 py-3 text-center text-sm text-gray-500">No data available</div>');
            return;
        }

        sortedClinics.forEach(function(clinic) {
            var isSelected = sel_clinic == clinic.cliId;
            var btnClass = isSelected ? 'bg-orange-50/50' : '';
            var barClass = isSelected ? 'bg-primary' : 'bg-gray-200 group-hover:bg-gray-300';
            var textClass = isSelected ? 'text-primary' : 'text-gray-600';
            var badgeClass = isSelected ? 'bg-primary text-white shadow-sm' : 'bg-gray-100 text-gray-500';

            var btn = `
                <button class="clinic-filter-btn w-full flex items-center justify-between px-4 py-2.5 hover:bg-gray-50 transition-colors cursor-pointer group text-left ${btnClass}" data-clinic-id="${clinic.cliId}">
                    <div class="flex items-center gap-2">
                        <div class="w-1 h-3.5 rounded-full transition-colors ${barClass}"></div>
                        <span class="text-[13px] font-medium ${textClass}">${clinic.name}</span>
                    </div>
                    <span class="text-[12px] font-black px-2.5 py-0.5 rounded-md min-w-[32px] text-center ${badgeClass}">${clinic.count}</span>
                </button>
            `;
            container.append(btn);
        });
        $('.clinic-filter-btn').off('click').on('click', function(e) {
            e.preventDefault();
            var clinicId = $(this).data('clinic-id');
            sel_clinic = clinicId;
            $('#sel_clinic').val(clinicId);
            refreshCustomSelect('sel_clinic');
            filterData();
            updateUrlParams();
            updateButtonStates();
        });
    }


    function LoadData() {
        showLoading('stat-not-received');
        showLoading('stat-longest-delay');
        showLoading('stat-clinics-affected');
        showLoading('stat-most-impacted');
        showLoading('table_report');

        $.get(`admin.php?page=${url_main_page}&trajax=loadData`, function(response) {
            try {
                var res = JSON.parse(response);
                allData = res.rows;
                allStatistics = res.statistics;

                generateCalendar();
                filterData();

            } catch (err) {
                console.error('Parse error:', err);
                showToast('error', 'Error', 'Failed to load data', 4000);
            }
        }).fail(function() {
            showToast('error', 'Error', 'Failed to load data', 4000);
        }).always(function() {
            hideLoading('stat-not-received');
            hideLoading('stat-longest-delay');
            hideLoading('stat-clinics-affected');
            hideLoading('stat-most-impacted');
            hideLoading('table_report');
        });
    }

    function updateButtonStates() {
        if (from_date == '' && sel_clinic == 'all') {
            $('.btn_show_all').addClass('bg-primary text-white').removeClass('bg-white text-gray-500');
        } else {
            $('.btn_show_all').removeClass('bg-primary text-white').addClass('bg-white text-gray-500');
        }
    }

    jQuery(function($) {
        window.$ = jQuery;
        update_ui_select();
        let searchTimeout = null;

        $("#sel_clinic").change(function() {
            sel_clinic = $(this).val();
            from_date = '';
            $('#from_date').val('');
            filterData();
            updateUrlParams();
            updateButtonStates();
        });

        $("#from_date").change(function() {
            from_date = $(this).val();
            $('#sel_clinic').val('all');
            sel_clinic = 'all';
            refreshCustomSelect('sel_clinic');
            filterData();
            updateUrlParams();
            updateButtonStates();
        });

        $("#inp_search").on('input', function() {
            key_search = $(this).val();

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                filterData();
                updateUrlParams();
            }, 500);
        });
        updateButtonStates();

        $('.btn_show_all').click(function() {
            from_date = '';
            $('#from_date').val('');
            $('#sel_clinic').val('all');
            sel_clinic = 'all';
            refreshCustomSelect('sel_clinic');
            filterData();
            updateUrlParams();
            $('.btn_show_all').addClass('bg-primary text-white').removeClass('bg-white text-gray-500');
        });

        $('#table_report th').off("click").click(function() {
            var index = $(this).index();
            if (!$(this).find('.icon_sort').length) {
                return;
            }

            const currentSortColumn = $('#table_report th').filter(function() {
                return $(this).data('is-sorting') === true;
            });

            var isSameColumn = currentSortColumn.length > 0 && currentSortColumn.index() === index;
            currentSort.index = index;
            currentSort.asc = isSameColumn ? !$(this).data('asc') : getDefaultSortDirection(index);

            sortFilteredData();
            renderTable();
        });

        $("#btn_export").click(async function() {
            var export_func = 'not_rec';
            var export_type = 'csv';
            var $url = '?page=' + url_main_page + '&trajax=export_report&export_type=' + export_type;
            $url += '&export_func=' + export_func;
            $url += '&cli_ID=' + sel_clinic;
            $url += '&from=' + from_date;
            $url += '&s=' + key_search;

            const loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0, 'top_right');

            try {
                const response = await fetch($url);
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

                showToast('success', 'Download ready!', 'Your file "' + filename + '" has been downloaded.', 4000, 'top_right');

            } catch (err) {
                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }
                showToast('error', 'Error', 'Download failed.', 4000, 'top_right');
            }
        });

        LoadData();
    });
</script>
<?php $schedule_report->js(); ?>
<?php $dispatch_schedule->js(); ?>