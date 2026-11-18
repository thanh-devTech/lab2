<?php
$btypes = tr_pdd_get_batch_types();

$selDay         = isset($_REQUEST['selDay']) ?  $_REQUEST['selDay'] :  '14_days';
$export_type    = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';
$btype          = isset($_REQUEST['btype']) ? $_REQUEST['btype'] : '';
$cli_id = isset($_REQUEST['cid']) ? $_REQUEST['cid'] : '';
$to_date        = date_i18n('Y-m-d');
$from_date      = $to_date;
if (empty($btype)) {
    $btype = reset($btypes);
}

$selDay_title = '';

if ($selDay == '14_days') {
    $selDay_title = 'Last 14 days';
    $from_date = date('Y-m-d', strtotime(date_i18n('Y-m-d')) - 13 * 86400);
} elseif ($selDay == '30_days') {
    $selDay_title = 'Last 30 days';
    $from_date = date('Y-m-d', strtotime(date_i18n('Y-m-d')) - 29 * 86400);
} elseif ($selDay == '90_days') {
    $selDay_title = 'Last 90 days';
    $from_date = date('Y-m-d', strtotime(date_i18n('Y-m-d')) - 89 * 86400);
} elseif ($selDay == 'custom_range') {
    $from_date = $_REQUEST['from'] ?? $to_date;
    $to_date   = $_REQUEST['to'] ?? $to_date;
    $selDay_title = date_i18n("M j, Y", strtotime($from_date)) . ' - ' . date_i18n("M j, Y", strtotime($to_date));
}
$conditions = trpdd_get_batch_conditions();
$clinics = $wpdb->get_results("select cli.* from ci_clinic cli where {$conditions['where_clinic']} order by cli.cli_clinic asc");
$current_clinic_name = 'All Clinics';
if (!empty($cli_id)) {
    foreach ($clinics as $clinic) {
        if ($clinic->cID == $cli_id) {
            $current_clinic_name = $clinic->cli_clinic;
            break;
        }
    }
}


$where_batch_id = ' and ' . tr_pdd_build_batch_id_where($from_date, $to_date);

if ($btype === 'study') {
    $panels = $wpdb->get_results("select ID as tg_ID, 
        abrev as tg_name, 
        '' astg_bgcolor, 
        abrev as tg_short_name, 
        abrev as tg_label_name
        from ci_study_tests 
        order by ID asc");
} else {
    $panels = $wpdb->get_results("select tg_ID, 
    tg_name, 
    tg_bgcolor, 
    tg_short_name, 
    tg_label_name
    from ci_test_groups 
    where tg_type = '{$btype}' and tg_parent = 0 and tg_show_req = 1 
    order by tg_position asc");
}

$report = array();
$batch_tbl = tr_pdd_get_table_by_btype($btype);
$report_tbl = tr_pdd_get_table_report_by_btype($btype);


foreach ($panels as $panel) {
    $where = '1=1';
    if (!empty($cli_id)) {
        $where .= " and ba.cli_ID = {$cli_id} ";
    }
    if ($btype == 'tox') {
        $tests = $wpdb->get_col(
            "select te_name from ci_test_panels 
						where te_type = '{$btype}'
						and te_group_id = {$panel->tg_ID}"
        );
        $where_tests = [];
        foreach ($tests as $test) {
            $test_name = strtoupper($test);
            $where_tests[] = "FIND_IN_SET('{$test_name}',panel_tests_requested)";
        }
        if (!empty($where_tests)) {
            $where .= ' and (' . implode(' OR ', $where_tests) . ')';
        }
    } else if ($btype == 'study') {
        $where .= " and FIND_IN_SET('{$panel->tg_ID}', ba.panel_tests_requested) ";
    } else {
        $where .= " and FIND_IN_SET('GROUP_{$panel->tg_ID}', ba.panel_tests_requested) ";
    }
    $sql = "select count(*) as countall,
            SUM(IF(re.has_pos>0, 1, 0)) as countpos
        from {$batch_tbl} ba 
        left join {$report_tbl} re on re.patient_id = ba.assen
        where {$where}
        {$where_batch_id}";
    $countobj = $wpdb->get_row($sql);

    if ($countobj && $countobj->countall > 0) {
        $abbrev = !empty($panel->tg_short_name) ? $panel->tg_short_name : $panel->tg_label_name;
        if (empty($panel->tg_bgcolor)) {
            $panel->tg_bgcolor = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
            $wpdb->update('ci_test_groups', ['tg_bgcolor' => $panel->tg_bgcolor], ['tg_ID' => $panel->tg_ID]);
        }
        $report[$panel->tg_ID] = array(
            'name'  => str_replace('(new)', '', $panel->tg_name),
            'abbrev' => $abbrev,
            'color' => $panel->tg_bgcolor,
            'count' => $countobj->countall,
            'pos_count' => $countobj->countpos,
            'total_per' => 0,
            'pos_per' => 0,
        );
    }
}

uasort($report, function ($a, $b) {
    return $a['count'] > $b['count'] ? -1 : 1;
});

$report = array_values($report);
$where_accession = '1=1';
if (!empty($cli_id)) {
    $where_accession .= " and ba.cli_ID = {$cli_id} ";
}
$sql_total = "SELECT 
    COUNT(DISTINCT ba.assen) as total_accessions,
    COUNT(DISTINCT CASE WHEN re.has_pos > 0 THEN ba.assen END) as total_pos_accessions
FROM {$batch_tbl} ba
LEFT JOIN {$report_tbl} re ON re.patient_id = ba.assen
WHERE {$where_accession}
{$where_batch_id}";

$total_obj = $wpdb->get_row($sql_total);
$total_all = $total_obj ? (int)$total_obj->total_accessions : 0;
$total_pos = $total_obj ? (int)$total_obj->total_pos_accessions : 0;
foreach ($report as $id => &$row) {
    $row['total_per'] = $total_all > 0  ? number_format(($row['count'] / $total_all) * 100, 1) : 0;
    $row['pos_per'] =  $row['count'] > 0  ? number_format(($row['pos_count'] / $row['count']) * 100, 1) : 0;
}
unset($row);

// build data cho chart
$labels = array_values(array_map(function ($r) {
    return $r['abbrev'];
}, $report));
$data   = array_values(array_map(function ($r) {
    return (int)$r['count'];
}, $report));
$colors = array_values(array_map(function ($r) {
    return $r['color'];
}, $report));

$obj_dis = [
    'total_samples' => $total_all,
    'total_pos'     => $total_pos,
    'overall_rate'  => $total_all > 0 ? number_format(($total_pos / $total_all) * 100, 1) : 0
];

if ($export_type === 'pdf') {
    trpdd_log_user(get_current_user_id(), 'download_report', 'Test Type Positivity Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
    ob_start();
    require TRCPDD_PATH . 'parts/dashboard/type_distribution_template_pdf.php';
    $html = ob_get_clean();
    $outfile = generate_report_filename('DASH', 'TestTypePositivity', $from_date, $to_date, 'pdf');
    trpdd_build_pdf($html, [
        'size'        => 'letter',
        'orientation' => 'portrait',
        'filename'    => $outfile,
        'download'  => 1,
        'compress' => 1
    ]);
    exit;
}

if ($export_type === 'csv') {
    trpdd_log_user(get_current_user_id(), 'download_report', 'Test Type Positivity Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    $generated_on = date_i18n("l, F d, Y \\a\\t h:i A");
    $period_export = ($from_date == $to_date) ? 'Today' : $from_date . " to " . $to_date;
    $outfile = generate_report_filename('DASH', 'TestTypePositivity', $from_date, $to_date, 'csv');
    tr_pdd_header('csv', $outfile);
    $f = fopen('php://output', 'w');

    // ==== Executive Summary ====
    fputcsv($f, ["Test Type & Positivity Analysis Report"]);
    fputcsv($f, ['Generated:', $generated_on]);
    fputcsv($f, ['Period:', $period_export]);
    fputcsv($f, ['Test Type:', strtoupper($btype)]);
    fputcsv($f, ['Clinic:', $current_clinic_name]);
    fputcsv($f, [""]);
    fputcsv($f, ["Executive Summary"]);
    fputcsv($f, ["Total Samples Analyzed", $obj_dis['total_samples']]);
    fputcsv($f, ["Overall Positivity Rate", $obj_dis['overall_rate'] . "%"]);
    fputcsv($f, ["Total Positive Results", $obj_dis['total_pos']]);
    fputcsv($f, []);

    // ==== Table Header ====
    fputcsv($f, ["Test Type", "Sample Count", "Distribution %", "Positive Results", "Positivity Rate"]);

    // ==== Table Rows ====
    foreach ($report as $row) {
        fputcsv($f, [
            $row['name'],
            $row['count'],
            $row['total_per'] . "%",
            $row['pos_count'],
            $row['pos_per'] . "%"
        ]);
    }

    fclose($f);
    exit;
}

$chart_data = [
    "labels" => $labels,
    "datasets" => [[
        "data" => $data,
        "backgroundColor" => $colors,
        "borderWidth" => 1
    ]]
];

?>
<div data-slot=card-header
    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-3">
    <div class="flex items-center justify-between">
        <div>
            <h4 data-slot=card-title class="leading-none flex items-center gap-2">
                <div class="p-1.5 bg-green-50 rounded-lg"><svg xmlns=http://www.w3.org/2000/svg width=24
                        height=24 viewBox="0 0 24 24" fill=none stroke=currentColor stroke-width=2
                        stroke-linecap=round stroke-linejoin=round
                        class="lucide lucide-activity h-4 w-4 text-green-600">
                        <path
                            d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                        </path>
                    </svg></div>Test Type & Positivity
                <p style="font-weight: normal;" data-slot="card-description" class="text-muted-foreground">
                    (<?php echo $selDay_title; ?>)
                </p>
                <svg id="dis_loading" xmlns="http://www.w3.org/2000/svg" width="64" height="18" viewBox="0 0 64 18" aria-hidden="true" role="img">
                    <circle cx="9" cy="9" r="6" fill="#6B7280">
                        <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0s" />
                    </circle>
                    <circle cx="32" cy="9" r="6" fill="#6B7280">
                        <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.15s" />
                    </circle>
                    <circle cx="55" cy="9" r="6" fill="#6B7280">
                        <animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.3s" />
                    </circle>
                </svg>
            </h4>

        </div>
        <div class="flex items-center gap-2 dis-bar">
            <button data-slot="button"
                class="run_test_type_report inline-flex items-center justify-center whitespace-nowrap text-sm font-medium _high disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 has-[>svg]:px-2.5 h-7 px-2 text-muted-foreground hover:text-primary hover:bg-transparent transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-activity h-3 w-3 mr-1" aria-hidden="true">
                    <path
                        d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2">
                    </path>
                </svg>
                <textshow>Live Report</textshow>
            </button>

            <div style="position:relative;display:inline-block;">
                <button id="dis_export_btn" data-slot="dropdown-menu-trigger"
                    class="none_border inline-flex items-center justify-center whitespace-nowrap text-sm font-medium _high disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:hover:bg-accent/50 rounded-md gap-1.5 has-[&gt;svg]:px-2.5 h-7 px-2 text-muted-foreground hover:text-primary hover:bg-transparent transition-colors"
                    type="button" id="radix-:r9:" aria-haspopup="menu" aria-expanded="false" data-state="closed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                        <path d="M12 15V3"></path>
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <path d="m7 10 5 5 5-5"></path>
                    </svg>Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                        <path d="m6 9 6 6 6-6"></path>
                    </svg>
                </button>


                <div id="menu_dis_export" data-radix-popper-content-wrapper="" dir="ltr" style="position:absolute;left:-17px;top:100%;margin-top:4px;display:none;min-width: max-content; will-change: transform; z-index: 50; --radix-popper-available-width: 1424px; --radix-popper-available-height: 568.25px; --radix-popper-anchor-width: 115.609375px; --radix-popper-anchor-height: 31.5px; --radix-popper-transform-origin: 100% 0px;">
                    <div data-side="bottom" data-align="end" role="menu" aria-orientation="vertical" data-state="open" data-radix-menu-content="" dir="ltr" id="radix-:rm:" aria-labelledby="radix-:rl:" data-slot="dropdown-menu-content" class="bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 max-h-(--radix-dropdown-menu-content-available-height) min-w-[8rem] origin-(--radix-dropdown-menu-content-transform-origin) overflow-x-hidden overflow-y-auto rounded-md border p-1 shadow-md" tabindex="-1" data-orientation="vertical" style="outline: none; --radix-dropdown-menu-content-transform-origin: var(--radix-popper-transform-origin); --radix-dropdown-menu-content-available-width: var(--radix-popper-available-width); --radix-dropdown-menu-content-available-height: var(--radix-popper-available-height); --radix-dropdown-menu-trigger-width: var(--radix-popper-anchor-width); --radix-dropdown-menu-trigger-height: var(--radix-popper-anchor-height); pointer-events: auto;">
                        <div export_type="pdf" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" class="dis_export_item focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4" tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                <path d="M10 9H8"></path>
                                <path d="M16 13H8"></path>
                                <path d="M16 17H8"></path>
                            </svg>Export as PDF</div>
                        <div export_type="csv" role="menuitem" data-slot="dropdown-menu-item" data-variant="default" class="dis_export_item focus:bg-accent focus:text-accent-foreground data-[variant=destructive]:text-destructive data-[variant=destructive]:focus:bg-destructive/10 dark:data-[variant=destructive]:focus:bg-destructive/20 data-[variant=destructive]:focus:text-destructive data-[variant=destructive]:*:[svg]:!text-destructive [&amp;_svg:not([class*='text-'])]:text-muted-foreground relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4" tabindex="-1" data-orientation="vertical" data-radix-collection-item=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
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
    <div class="flex flex-wrap items-center gap-3 mt-4">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <div class="p-1.5 bg-gray-50 rounded-lg">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="lucide lucide-funnel h-4 w-4 text-gray-600" aria-hidden="true">
                    <path
                        d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z">
                    </path>
                </svg>

            </div>
        </div>
        <div class="flex items-center gap-2 tat-bar">
            <select id="sel_dis_day" class="none_border">
                <option value="14_days" <?= $selDay === '14_days' ? 'selected' : '' ?>>14 Days</option>
                <option value="30_days" <?= $selDay === '30_days' ? 'selected' : '' ?>>30 Days</option>
                <option value="90_days" <?= $selDay === '90_days' ? 'selected' : '' ?>>90 Days</option>
                <option value="custom_range" <?= $selDay === 'custom_range' ? 'selected' : '' ?>>Custom Range</option>
            </select>
            <?php
            if ($selDay == 'custom_range') {
            ?>
                <button id="daterange-btn" style="width:auto" type=button role=combobox aria-controls=radix-:rf:aria-expanded=false aria-autocomplete=none dir=ltr data-state=closed data-slot=select-trigger data-size=default class="border-input data-[placeholder]:text-muted-foreground [&amp;_svg:not([class*='text-'])]:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 dark:hover:bg-input/50 flex items-center justify-between gap-2 rounded-md px-3 py-2 whitespace-nowrap outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 data-[size=default]:h-9 data-[size=sm]:h-8 *:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2 [&amp;_svg]:pointer-events-none [&amp;_svg]:shrink-0 [&amp;_svg:not([class*='size-'])]:size-4 w-32 h-8 text-sm border-0 bg-muted/50 hover:bg-muted transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" role="img">
                        <rect x="3" y="4" width="18" height="17" rx="2"></rect>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="7" y1="14" x2="7" y2="14"></line>
                        <line x1="12" y1="14" x2="12" y2="14"></line>
                        <line x1="17" y1="14" x2="17" y2="14"></line>
                        <line x1="7" y1="18" x2="7" y2="18"></line>
                        <line x1="12" y1="18" x2="12" y2="18"></line>
                        <line x1="17" y1="18" x2="17" y2="18"></line>
                    </svg>

                    <span class="txt_show_customer_day" data-slot=select-value style=pointer-events:none><?php echo date("M j", strtotime($from_date)) . '-' . date("M j", strtotime($to_date)); ?></span>
                </button>
                <script>
                    jQuery(function($) {
                        $('#daterange-btn').daterangepicker({
                                opens: 'right',
                                autoUpdateInput: true,
                                startDate: '<?php echo $from_date; ?>',
                                endDate: '<?php echo $to_date; ?>',
                                locale: {
                                    format: 'YYYY-MM-DD'
                                }
                            },
                            function(start, end, label) {
                                dis_day_from = start.format('YYYY-MM-DD');
                                dis_day_to = end.format('YYYY-MM-DD');
                                $('#daterange-btn span').html(start.format('MMM D') + '-' + end.format('MMM D'));
                                $.ajax({
                                    data: {
                                        trajax: 'type_distribution_ajax',
                                        selDay: 'custom_range',
                                        from: start.format('YYYY-MM-DD'),
                                        to: end.format('YYYY-MM-DD')
                                    },
                                    success: function(rs) {
                                        $('#dis_content').html(rs);
                                        $("#dis_loading").hide();
                                    }
                                });
                            }
                        );
                        $('#daterange-btn').data('daterangepicker').show();
                    })
                </script>
            <?php } ?>
            <div class="flex items-center gap-2">
                <select id="test_type_btype" class="none_border select-btype">
                    <?php
                    foreach ($btypes as $bt) {
                        echo '<option value="' . $bt . '" ' . ($bt == $btype ? 'selected' : '') . '>' . tr_pdd_get_typename($bt) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <?php if (count($clinics) > 0) { ?>
                    <select id="test_type_clinic" class="none_border">
                        <?php if (count($clinics) > 1) : ?>
                            <option value="0">All Clinics</option>
                        <?php endif; ?>
                        <?php
                        foreach ($clinics as $c) {
                            $selected = isset($cli_id) && $cli_id == $c->cID ? 'selected' : '';
                            echo '<option value="' . $c->cID . '" ' . $selected . '>' . $c->cli_clinic . '</option>';
                        }
                        ?>
                    </select>
                <?php }
                ?>
            </div>
        </div>
    </div>
</div>
<div data-slot=card-content class="px-6 [&amp;:last-child]:pb-6 pt-0 pb-3">
    <?php if (!empty($report)) : ?>
        <div id="test_type_analysis" style="display: none;" class="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
            <h4 style="margin-bottom: 13px;margin-top: 0px;" class="font-semibold text-base mb-4 text-slate-800">Live Test Type & Positivity Report</h4>
            <div class="mb-4 p-3 bg-white rounded-lg border">
                <h5 class="font-medium _high text-sm mb-2" style="margin-top: 6px;margin-bottom: 6px;">Current Analysis Settings</h5>
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                    <div><span class="font-medium _high">Time Period:</span>
                        <div class="text-gray-600"><?php echo $selDay_title; ?></div>
                    </div>
                    <div><span class="font-medium _high">Test Type:</span>
                        <div class="text-gray-600"><?php echo strtoupper($btype); ?></div>
                    </div>
                    <div>
                        <span class="font-medium _high">Clinic: </span>
                        <div class="text-gray-600"><?php echo !empty($current_clinic_name) ? $current_clinic_name : 'All Clinics'; ?></div>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <h5 class="font-medium _high text-sm mb-2">Test Type Performance Summary</h5>
                <div class="bg-white rounded-lg border overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-3 font-medium _high">Test Type</th>
                                <th class="text-center p-3 font-medium _high">Sample Count</th>
                                <th class="text-center p-3 font-medium _high">Distribution %</th>
                                <th class="text-center p-3 font-medium _high">Positive Results</th>
                                <th class="text-center p-3 font-medium _high">Positivity Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($report as $row) {
                            ?>
                                <tr class="border-t">
                                    <td class="p-2 font-medium _high"><?php echo $row['name'] ?></td>
                                    <td class="p-3 text-center"><?php echo $row['count'] ?></td>
                                    <td class="p-3 text-center"><?php echo $row['total_per'] ?>%</td>
                                    <td class="p-3 text-center"><?php echo $row['pos_count'] ?></td>
                                    <td class="p-3 font-medium _high text-center text-red-600"><?php echo $row['pos_per'] ?>%</td>
                                </tr>
                            <?php } ?>
                            <tr class="border-t bg-gray-50 font-bold">
                                <td class="p-3">TOTAL</td>
                                <td class="p-3 text-center"><?php echo $total_all ?></td>
                                <td class="p-3 text-center">100.0%</td>
                                <td class="p-3 text-center"><?php echo $total_pos ?></td>
                                <td class="p-3 text-center text-red-600"><?php echo $total_pos > 0 ? number_format(($total_pos / $total_all * 100), 1) : 0 ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="text-xs text-slate-600 bg-slate-100 p-2 rounded">💡 This report updates automatically when you change filters above. Use the Export button for PDF/CSV versions.</div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div>
                <div class=recharts-responsive-container style=width:100%;height:260px;min-width:0px>
                    <div class=recharts-wrapper style=position:relative;cursor:default;width:100%;height:100%;max-height:260px;max-width:534px>

                        <div style="width:100%; display:flex; justify-content:center; align-items:center; margin-top:40px;">
                            <canvas id="pie_chart"></canvas>
                        </div>
                        <div id="custom-tooltip" class="recharts-tooltip-wrapper">
                        </div>
                        <script>
                            var tooltipEl = document.getElementById('custom-tooltip');
                            var report = <?php echo json_encode($report); ?>;
                            ctxPie = document.getElementById("pie_chart").getContext("2d");
                            new Chart(ctxPie, {
                                type: 'doughnut',
                                data: <?php echo json_encode($chart_data); ?>,
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    cutout: '50%',
                                    layout: {
                                        padding: 33
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        datalabels: {
                                            display: false
                                        },
                                        tooltip: {
                                            mode: "nearest",
                                            enabled: false,
                                            external: function(context) {
                                                let tooltipModel = context.tooltip;
                                                if (tooltipModel.opacity === 0) {
                                                    tooltipEl.classList.remove("active");
                                                    return;
                                                } else {
                                                    tooltipEl.classList.add("active");
                                                }
                                                const index = tooltipModel.dataPoints[0].dataIndex;
                                                const panelId = Object.keys(report)[index];
                                                const item = report[panelId];

                                                tooltipEl.innerHTML = `
                                                    <div><strong>${item.name}</strong></div>
                                                    <div class="flex items-center justify-between gap-2 text-sm"><div>Volume:</div> <span class="text-right font-medium _high">${item.count} samples (${item.total_per}%)</span></div>
                                                    <div class="flex items-center justify-between gap-2 text-sm"><div>Positivity Rate:</div> <span class="text-right font-medium _high">${item.pos_per}%</span></div>
                                                    <div class="flex items-center justify-between gap-2 text-sm"><div>Positive Results:</div><span class="text-right font-medium _high">${item.pos_count} samples</span></div>
                                                `;

                                                tooltipEl.style.display = 'block';
                                                tooltipEl.style.left = tooltipModel.caretX + 'px';
                                                tooltipEl.style.top = tooltipModel.caretY + 'px';
                                            }
                                        }
                                    }
                                },
                                plugins: [{
                                    id: 'centerText',
                                    beforeDraw(chart) {
                                        const {
                                            width
                                        } = chart;
                                        const {
                                            height
                                        } = chart;
                                        const ctx = chart.ctx;
                                        ctx.restore();

                                        const dataset = chart.data.datasets[0].data;
                                        const total = <?php echo $total_all; ?>;

                                        ctx.font = "bold 22px sans-serif";
                                        ctx.fillStyle = "#000";
                                        ctx.textAlign = "center";
                                        ctx.textBaseline = "middle";
                                        ctx.fillText(total, width / 2, height / 2 - 10);

                                        ctx.font = "14px sans-serif";
                                        ctx.fillStyle = "#666";
                                        ctx.fillText("Total Samples", width / 2, height / 2 + 15);

                                        ctx.save();
                                    }
                                }]
                            });
                        </script>
                    </div>
                </div>
            </div>

            <div class="space-y-1.5 -mt-6" style="margin-bottom: 15px;">
                <?php
                foreach ($report as $r) { ?>
                    <div class="p-2 rounded-md bg-gray-50/50">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: <?php echo $r['color'] ?>"></div>
                                <div class="min-w-0">
                                    <p class="font-medium _high text-sm leading-tight truncate"><?php echo $r['name'] ?></p>
                                    <p class="text-sm text-muted-foreground">
                                        <span class="font-bold text-red-700"><?php echo $r['pos_per'] ?>%</span> positivity rate
                                    </p>
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-base font-semibold"><?php echo $r['count'] ?> (<span class="text-gray-500"><?php echo $r['total_per'] ?>%</span>)</p>
                                <p class="text-sm text-muted-foreground">samples</p>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>

        </div>
    <?php else: ?>
        <p style="width: 100%;text-align: center;">
            <svg style="display: unset;" xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" aria-hidden="true" role="img">
                <ellipse cx="12" cy="5" rx="7" ry="2.5" fill="none" stroke="#6B7280" stroke-width="1.5" />
                <path d="M5 5v6c0 1.4 3.1 2.5 7 2.5s7-1.1 7-2.5V5" fill="none" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <ellipse cx="12" cy="13.5" rx="7" ry="2.5" fill="none" stroke="#6B7280" stroke-width="1.5" opacity="0.9" />
                <line x1="6" y1="18" x2="18" y2="6" stroke="#6B7280" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <br />
            No Data
        </p>
    <?php endif; ?>
    <script>
        jQuery(function($) {
            update_ui_select();
        });
    </script>
</div>