<?php
global $ci_all_btypes, $wpdb;
$schedule_report  = new CI_Report_Schedule('not_received', 'Not Received');
$from_date        = isset($_REQUEST['from']) ? $_REQUEST['from'] : date_i18n('Y-m-d');
$sel_clinic       = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 'all';
$key_search       = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
$trajax           = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';
$nor              = false;

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$schedule_report->ajax();

$clinics = array();
$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic  ORDER BY cli_clinic ASC");
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

        $txt_test_name = [];
        foreach ($panels_tmp as $t) {
            $txt_test_name[] = $t->tg_name;
        }

        return implode(',', $txt_test_name);
    }
}


$panels_tests = get_panels_and_tests($ci_all_btypes);
$all_panels = $panels_tests['panels'];
$all_tests = $panels_tests['tests'];

$test_type_str =   implode(',', array_map(function ($type) {
    return "'$type'";
}, tr_pdd_get_batch_types()));

$rs_groups = $wpdb->get_results("
    SELECT tg_ID, 
    REPLACE(tg_name,'(new)','') as tg_name,
    tg_only_group
    FROM ci_test_groups
    WHERE tg_parent = 0
    AND tg_show_req = 1
    AND tg_type IN ({$test_type_str})
    ORDER BY tg_name ASC
");

$all_panels = array();
$group_tests = array();

foreach ($rs_groups as $i => $panel) {

    $rs_tests = $wpdb->get_results("select te.te_ID, 
        te.te_name,
        te.te_abrev
        from ci_test_panels te
        JOIN ci_test_groups g ON te.te_group_id = g.tg_ID
        where (te_group_id = {$panel->tg_ID} or g.tg_parent = {$panel->tg_ID})
        and te.te_in_req = 1 
        and te.te_is_control = 0
        order by te.te_name asc");

    foreach ($rs_tests as $t) {
        $tkey = strtolower($t->te_name);
        $t->is_ecoli = false;
        if (stripos($t->te_name, 'escherichia coli') !== false || stripos($t->te_abrev, 'E. coli') !== false) {
            $t->is_ecoli = true;
        }
        if (isset($group_tests[$tkey])) {
            $group_tests[$tkey]->te_IDs[] = $t->te_ID;
            $group_tests[$tkey]->tg_IDs[] = $panel->tg_ID;
        } else {
            $t->te_IDs = array($t->te_ID);
            $t->tg_IDs = array($panel->tg_ID);
            $group_tests[$tkey] = $t;
        }
    }

    if ($panel->tg_only_group) {
        $panel->testids = $wpdb->get_col("select te_ID from ci_test_panels where te_group_id = {$panel->tg_ID} and te_in_req = 1 and te_is_control = 0");
    }

    $all_panels[$panel->tg_ID] = $panel;
}

if (!function_exists('getClinicNameById')) {
    function getClinicNameById($clinics, $cID)
    {
        foreach ($clinics as $clinic) {
            if ((string)$clinic->cID === (string)$cID) {
                return $clinic->cli_clinic;
            }
        }
        return null; // Or 'N/A'
    }
}


$where = " 1=1 ";
$where .= " AND doc >= '{$from_date}' and doc <= '{$from_date} 23:59:59' and ba.batch_id = 0 ";
if ($sel_clinic != 'all') {
    $where .= " AND ba.cli_ID=$sel_clinic";
}

if ($key_search !== '') {
    $search = esc_sql($wpdb->esc_like($key_search));
    $where .= " AND (
        ba.assen LIKE '%{$search}%'
        OR ba.panel_tests_requested LIKE '%{$search}%'
    )";
}

$rows = [];

foreach (tr_pdd_get_batch_types() as $btype) {

    $where .= ' AND ' . tr_pdd_where_batch_rejected(
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
        cli_ID,
        '{$btype}' as btype,
        IF(rb.mid IS NULL, 'no', 'yes') as received
    FROM {$batch_tbl} ba
    LEFT JOIN ci_receiving_batches rb 
        ON rb.mid = ba.mid AND rb.btype = '{$btype}'
    WHERE {$where}";

    if ($nor) {
        $sql .= " AND rb.mid IS NULL";
    }

    $sql .= " ORDER BY doc ASC";

    $result = $wpdb->get_results($sql, ARRAY_A);

    if (!empty($result)) {
        $rows = array_merge($rows, $result);
    }
}

foreach ($rows as $index => $row) {
    $row['cli_clinic'] = getClinicNameById($clinics, $row['cli_ID']);
    $groupsids = extract_group_ids($row['panel_tests_requested'], $row['btype'], $all_tests);
    $row['panels'] = build_panel_names($groupsids, $all_panels);
    $rows[$index] = $row;
}

if (isset($_GET['debug'])) {
    var_dump($rows[0]);
    echo '<br/>';
    'last_error:' . $wpdb->last_error;
    echo '<br/>';
    echo 'total: ' . count($rows);
    echo '<br/>';
    echo 'Sql:' . $sql;
}

if ($trajax == 'export_report') {
    $period_export =  (new DateTime($from_date))->format('m_d_Y');
    $outfile = CI_Base_Lab::get_current_lab_init() . ' IGN_not_received_' . $period_export . '.csv';
    tr_pdd_header('csv', $outfile);

    if ($filepath_save_csv != '') {
        $f = fopen($filepath_save_csv, 'w');
        $csv_out_file = basename($outfile);
    } else {
        $f = fopen('php://output', 'w');
    }
    fputcsv($f, ["DOC", "Accession", "Clinic", "Panel", "Status"]);

    foreach ($rows as $r) {
        $status = $r['received'] == 'no' ? 'Not Received' : 'Received';
        fputcsv($f, [$r['doc'], $r['assen'], $r['cli_clinic'], $r['panels'], $status]);
    }
    fclose($f);
    if ($filepath_save_csv == '') exit;
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
<style>
    h4 {
        margin: unset !important;
    }

    .icon_sort {
        color: #898787ff;
    }
</style>
<div class="wrap flex-1 p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]">
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">Not Received</h1>
                <p class="text-muted-foreground mt-1s">Samples collected but not yet received for the selected date of collection.</p>
            </div>
        </div>
        <div>
            <div class="space-y-4">
                <div data-slot="card" class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                    <div data-slot="card-content" class="[&amp;:last-child]:pb-6 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-6">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-bold text-gray-500 uppercase tracking-tight">DOC</span>
                                    <input type="date" id="from_date" data-slot="input" class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 flex min-w-0 rounded-md border px-3 py-1 bg-input-background transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-9 w-44 text-sm border-gray-200 focus:ring-[#D83D00]" value="<?php echo $from_date; ?>">
                                </div>

                                <div class="flex items-center">
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
                                    <span class="text-xs text-gray-400">(<?php echo $from_date; ?>)</span>
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
                                <?php if (empty($rows)) { ?>
                                    <div class="w-full text-center" style="padding-top:10px;padding-bottom:10px">The list is empty.</div>
                                <?php } else { ?>
                                    <table id="table_report" data-slot="table" class="w-full caption-bottom text-sm">
                                        <thead data-slot="table-header" class="[&amp;_tr]:border-b bg-gray-100">
                                            <tr data-slot="table-row" class="data-[state=selected]:bg-muted transition-colors hover:bg-transparent border-b border-gray-200">
                                                <th data-slot="table-head" class="text-left align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-3 text-[12px] font-bold text-gray-700">
                                                    <div class="flex items-center gap-1 cursor-pointer">DOC <?php echo icon_sort(); ?></div>
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
                                                <th data-slot="table-head" class="align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-3 text-right text-[12px] font-bold text-gray-700">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody data-slot="table-body" class="[&amp;_tr:last-child]:border-0">
                                            <?php foreach ($rows as $r) { ?>
                                                <tr data-slot="table-row" class="data-[state=selected]:bg-muted hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-0  bg-white">
                                                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-2 text-[13px] text-gray-600">
                                                        <?php echo $r['doc']; ?>
                                                    </td>
                                                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-2 text-[13px] text-gray-900">
                                                        <a href="?page=ci_custody_chain&assen=<?php echo $r['assen']; ?>&clinic&from&to" target=_blank><?php echo $r['assen']; ?></a>
                                                    </td>
                                                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-2 text-[13px] text-gray-700">
                                                        <?php echo $r['cli_clinic']; ?>
                                                    </td>
                                                    <td data-slot="table-cell" class="p-2 align-middle [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-2 text-[13px] text-gray-700">
                                                        <?php
                                                        echo $r['panels'];
                                                        ?>
                                                    </td>
                                                    <td data-slot="table-cell" class="p-2 align-middle whitespace-nowrap [&amp;:has([role=checkbox])]:pr-0 [&amp;&gt;[role=checkbox]]:translate-y-[2px] px-4 py-2 text-right">
                                                        <?php
                                                        if ($r['received'] == 'no') {
                                                        ?>
                                                            <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-secondary/90 bg-yellow-100 text-yellow-800">
                                                                NOT RECEIVED
                                                            </span>
                                                        <?php } else { ?>
                                                            <span data-slot="badge" class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-secondary/90 text-green-700 bg-green-50 border-green-200">RECEIVED</span>
                                                        <?php } ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                <?php } ?>
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
    var sel_clinic = '<?php echo $cli_ID; ?>';
    var key_search = '<?php echo $key_search; ?>';
    var url_main_page = 'ci_reports_not_received';

    function loadData() {
        var url = location.href;
        if (sel_clinic != '') url = url + '&cli_ID=' + sel_clinic;
        url = url + '&from=' + from_date;
        url = url + '&s=' + key_search;
        window.location = url;
    }

    jQuery(function($) {
        window.$ = jQuery;
        update_ui_select();

        $("#sel_clinic").change(function() {
            sel_clinic = $(this).val();
            loadData();
        });

        $("#from_date").change(function() {
            from_date = $(this).val();
            loadData();
        });

        $("#inp_search").change(function() {
            key_search = $(this).val();
            loadData();
        });

        $('#table_report th').off("click").click(function() {
            var table = $(this).parents('table').eq(0);
            var tbody = table.children('tbody');

            var allRows = tbody.find('tr').toArray();

            var rows = allRows;
            var index = $(this).index();
            const currentSortColumn = $('#table_report th').filter(function() {
                return $(this).data('is-sorting') === true;
            });

            var isSameColumn = currentSortColumn.length > 0 && currentSortColumn.index() === index;

            var asc = isSameColumn ? !$(this).data('asc') : true;

            $('#table_report th').find(".icon_sort").css("color", "#898787ff");
            $('#table_report th').find(".lucide-arrow-up-down").addClass("inline").show();
            $('#table_report th').find(".lucide-arrow-up").removeClass("inline").hide();
            $('#table_report th').find(".lucide-arrow-down").removeClass("inline").hide();

            $('#table_report th').data('is-sorting', false);
            $(this).find(".lucide-arrow-up-down").removeClass("inline").hide();
            $(this).find(".icon_sort").css("color", "#313131ff");

            rows.sort(function(a, b) {
                var cellA = $(a).children('td').eq(index);
                var cellB = $(b).children('td').eq(index);

                var A = cellA.text().trim();
                var B = cellB.text().trim();

                if (index === 0) {
                    return asc ?
                        B.localeCompare(A, undefined, {
                            sensitivity: 'base'
                        }) :
                        A.localeCompare(B, undefined, {
                            sensitivity: 'base'
                        });
                }

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

            tbody.empty().append(rows);

            if (asc) {
                $(this).find(".lucide-arrow-down").addClass('inline').show();
            } else {
                $(this).find(".lucide-arrow-up").addClass('inline').show();
            }

            $(this).data('asc', asc);
            $(this).data('is-sorting', true);
        });



        $("#btn_export").click(async function() {
            var export_func = 'not_rec';
            var export_type = 'csv';
            var $url = location.href + '&trajax=export_report&export_type=' + export_type;
            $url += '&export_func=' + export_func;
            $url += '&sel_clinic=' + sel_clinic;
            $url += '&from_date=' + from_date;
            const build_pdf_in_seconds = Math.round(<?php echo count($rows) ?> / 100, 0) + 3;
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
    })
</script>
<?php $schedule_report->js(); ?>