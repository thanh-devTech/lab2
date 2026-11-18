<?php
global $wpdb;

$schedule_report = new CI_Report_Schedule('prevalence_rates', 'Prevalence Rates Report');

$sel_clinic = isset($_REQUEST['sel_clinic']) ? $_REQUEST['sel_clinic'] : 'all';
$sel_panel = isset($_REQUEST['sel_panel']) ? $_REQUEST['sel_panel'] : 'all';
$month_range    = isset($_REQUEST['month_range']) ? $_REQUEST['month_range'] : 'last_0_month';
$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';
$trajax = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

$date_from = date_i18n('Y-m-01');
$date_to = date_i18n('Y-m-t');

$ftimethismonth = strtotime(date_i18n('Y-m-01'));
$label_date_range = date_i18n('F Y');

switch ($month_range) {
    case 'last_0_month':
        $date_from = date_i18n('Y-m-01');
        $date_to = date_i18n('Y-m-t');
        break;

    case 'last_1_month':
    case 'last_2_month':
    case 'last_3_month':
    case 'last_4_month':
    case 'last_5_month':
        $offset = intval(str_replace(['last_', '_month'], '', $month_range));
        $date_from = date_i18n('Y-m-01', strtotime("first day of -$offset month", $ftimethismonth));
        $date_to = date_i18n('Y-m-t', strtotime("last day of -$offset month", $ftimethismonth));
        $label_date_range = date_i18n('F Y', strtotime($date_from));
        break;

    case 'last_6_month':
    case 'last_6_months':
        $date_from = date_i18n('Y-m-01', strtotime("first day of -5 months", $ftimethismonth));
        $date_to = date_i18n('Y-m-t', strtotime("last day of this month", $ftimethismonth));
        $label_date_range = "Last 6 months";
        break;

    default:
        $date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : date_i18n('Y-m-01');
        $date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : date_i18n('Y-m-t');
        break;
}

$conditions = trpdd_get_batch_conditions(0, 'pcr');
$where_clinic = $conditions['where_clinic'];

$where_group = "tg_type = 'pcr' and tg_parent = 0 and tg_show_req = 1";

$clinics_rs = $wpdb->get_results("select cID, cli_clinic, cli_status from ci_clinic where {$where_clinic}");
$all_panels = [];
$sql_groups = "
            SELECT tg_ID, tg_name, tg_parent
            FROM ci_test_groups
            WHERE {$where_group}
            ORDER BY tg_position ASC
        ";

$rsgroups = $wpdb->get_results($sql_groups);
foreach ($rsgroups as $r) {
    $all_panels[$r->tg_ID] = str_replace('(new)', '', $r->tg_name);
}

$filter_date_select = [];

$ftimethismonth = strtotime(date_i18n('Y-m-01'));
for ($i = 0; $i <= 5; $i++) {
    $filter_date_select[] = [
        'value' => 'last_' . $i . '_month',
        'text' => date_i18n('F Y', strtotime("first day of -$i month", $ftimethismonth))
    ];
}

$filter_date_select[] = [
    'value' => 'last_6_month',
    'text' => 'Last 6 Months'
];

if (!function_exists('trpcr_sum_get_tests_by_groups')) {
    function trpcr_sum_get_tests_by_groups($parent_gid, $gid, $results)
    {
        global $wpdb;

        $tests = $wpdb->get_results("select te.te_name,
			te.te_abrev,
			te.te_abrev2
			from ci_test_panels te
			where te.te_group_id = {$gid} 
			and te_is_control = 0
            and te_in_req = 1
			order by te.te_position asc");

        $test_rs         = array();

        foreach ($tests as $test) {

            $drugs = array();
            $drugs[]     = strtoupper($test->te_name) . '_' . $gid;
            $drugs[]     = strtoupper($test->te_name) . '_' . $parent_gid;
            if (!empty($test->te_abrev)) {
                $drugs[]     = strtoupper($test->te_abrev) . '_' . $gid;
                $drugs[]     = strtoupper($test->te_abrev) . '_' . $parent_gid;
            }

            if (!empty($test->te_abrev2)) {
                $drugs[]     = strtoupper($test->te_abrev2) . '_' . $gid;
                $drugs[]     = strtoupper($test->te_abrev2) . '_' . $parent_gid;
            }

            if (!isset($test_rs[$test->te_name])) {
                $test_rs[$test->te_name] = array(
                    'H' => 0,
                    'M' => 0,
                    'VL' => 0,
                    'VH' => 0,
                    'L' => 0,
                    'P' => 0,
                    'N' => 0,
                );
            }

            foreach ($drugs as $drug) {

                if (isset($results[$drug])) {
                    $arr = $results[$drug];

                    $test_rs[$test->te_name]['H'] += $arr['H'];
                    $test_rs[$test->te_name]['VH'] += $arr['VH'];
                    $test_rs[$test->te_name]['VL'] += $arr['VL'];
                    $test_rs[$test->te_name]['M'] += $arr['M'];
                    $test_rs[$test->te_name]['L'] += $arr['L'];
                    $test_rs[$test->te_name]['P'] += $arr['P'];
                    $test_rs[$test->te_name]['N'] += $arr['N'];
                }
            }
        }

        return $test_rs;
    }
}

if (!function_exists('calculatePercentage')) {
    function calculatePercentage($positive, $total): string
    {
        if ((int)$total === 0)  return '0';
        return (string) round(($positive / $total) * 100, 2);
    }
}

if (!function_exists('renderCell')) {
    function renderCell($total, $percent)
    {
        return ($total > 0) ? $total . ' (' . $percent . '%)' : '-';
    }
}

if ($trajax == 'loadData' || !empty($export_func) || isset($_GET['dev_debug'])) {

    $where = trpdd_abx_query_date_range($date_from, $date_to);

    $archive_date   = tr_get_option('ci_pdf_data', 'archive_date', '');
    if (!empty($archive_date)) {
        $where .= " and ba.batch_created > '{$archive_date}' ";
    }

    if ($sel_clinic != 'all') {
        $where .= " and ba.cli_ID = {$sel_clinic}";
    }
    $reflex_groups = $wpdb->get_col(
        "SELECT DISTINCT tg_reflex_group_ID
        FROM ci_test_groups 
        WHERE tg_reflex_group_ID IS NOT NULL 
        AND tg_reflex_group_ID != ''
        AND tg_reflex_group_ID != '0'"
    );

    $reflex_group_ids = array();
    foreach ($reflex_groups as $rg) {
        $reflex_group_ids[] = $rg;
    }
    $reflex_group_ids_str = !empty($reflex_group_ids) ? implode(',', $reflex_group_ids) : '0';


    $sql = "select rs.drug, 
        rs.`pos-neg` as pos_neg,
        rs.group_id,
        count(*) as count
		from ci_results_pcr rs
		JOIN ci_reports_pcr re On re.id = rs.report_id and re.estatus = 'C'
		JOIN ci_batches_pcr ba ON re.patient_id = ba.assen
		where {$where}
        and (
            rs.group_id NOT IN ({$reflex_group_ids_str})
            OR
            (rs.group_id IN ({$reflex_group_ids_str}) AND (re.has_pos > 0 OR re.has_uti > 0))
        )
		group by rs.drug, rs.group_id, pos_neg
		";

    $rs = $wpdb->get_results($sql);
    $results  = array();

    foreach ($rs as $r) {
        $key = strtoupper($r->drug) . '_' . $r->group_id;

        $r->high_med_low = '';
        if ($r->pos_neg === 'PVH') {
            $r->high_med_low = 'VH';
        } else if ($r->pos_neg === 'PVL') {
            $r->high_med_low = 'VL';
        } else if ($r->pos_neg === 'POH') {
            $r->high_med_low = 'H';
        } else if ($r->pos_neg === 'POL') {
            $r->high_med_low = 'L';
        } else if ($r->pos_neg === 'POM') {
            $r->high_med_low = 'M';
        } else if ($r->pos_neg === 'POS') {
            $r->high_med_low = 'P';
        } else if ($r->pos_neg === 'NEG') {
            $r->high_med_low = 'N';
        }
        if (!isset($results[$key])) {
            $results[$key] = array(
                'H' => 0,
                'VH' => 0,
                'VL' => 0,
                'M' => 0,
                'L' => 0,
                'P' => 0,
                'N' => 0,
                'batch_count' => 0
            );
        }
        if (!empty($r->high_med_low))
            $results[$key][$r->high_med_low] += $r->count;
    }
    unset($rs);

    $group_ids = array();
    $group_reports = array();

    if ($sel_panel != 'all') {
        $where_group .= " and tg_ID = {$sel_panel}";
    }
    $sql_groups = "
            SELECT tg_ID, tg_name, tg_parent
            FROM ci_test_groups
            WHERE {$where_group}
            ORDER BY tg_position ASC
        ";

    $rsgroups = $wpdb->get_results($sql_groups);

    foreach ($rsgroups as $r) {
        $r->tg_name = trim(str_replace('(new)', '', $r->tg_name));
        if (stripos($r->tg_name, 'Control') !== false) {
            continue;
        }

        $group_ids[$r->tg_ID] = $r->tg_ID;
        $tests     = array();
        $subs     = array();
        $subgroups = $wpdb->get_results("select * from ci_test_groups where tg_parent = {$r->tg_ID} order by tg_position asc");
        if (count($subgroups) > 0) {
            foreach ($subgroups as $sub) {
                $tests = trpcr_sum_get_tests_by_groups($sub->tg_parent, $sub->tg_ID, $results);

                $subs[$sub->tg_ID] = array(
                    'id'     => $sub->tg_ID,
                    'parent_id' => $sub->tg_parent,
                    'name'     => $sub->tg_name,
                    'tests' => $tests
                );
            }
        } else {
            $tests = trpcr_sum_get_tests_by_groups(0, $r->tg_ID, $results);
        }

        $group_reports[$r->tg_ID] = array(
            'id'     => $r->tg_ID,
            'name'     => $r->tg_name,
            'subs'     => $subs,
            'tests' => $tests
        );
    }
    $fieldpositive = 're.has_pos > 0';
    if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
        $fieldpositive .= ' or re.has_uti > 0';
    }

    $group_pos_negs = array();
    foreach ($group_ids as $gid) {
        $sql = "
        SELECT 
            COUNT(*) AS countreqs,
            SUM(is_positive) AS count_positive
        FROM (
            SELECT
                IF(re.estatus='C' AND ({$fieldpositive}) > 0, 1, 0) AS is_positive
            FROM ci_batches_pcr ba
            JOIN ci_reports_pcr re ON re.patient_id = ba.assen
            WHERE {$where}
            and FIND_IN_SET('GROUP_{$gid}',ba.panel_tests_requested)
        ) AS rs";

        $rs    = $wpdb->get_row($sql);
        $group_pos_negs[$gid] = array(
            'POS' => $rs->count_positive,
            'NEG' => $rs->countreqs - $rs->count_positive,
            'total' => $rs->countreqs
        );
    }
}

$schedule_report->ajax();

if ($trajax == 'loadData') {
    echo json_encode(
        [
            "group_pos_negs" => $group_pos_negs,
            "group_reports" => $group_reports,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'sql' => $sql,
            'label_date_range' => $label_date_range
        ]
    );
    exit;
}
if ($export_func != '') {
    if ($filepath_save_csv == '' && $filepath_save_pdf == '') {
        trpdd_log_user(get_current_user_id(), 'download_report', 'Prevalence Rates Report', '', '', '', '', 'Report_' . date_i18n('mdY'));
    }
    $generated_on = date_i18n("m/d/Y \\a\\t h:i A");
    $cur_name_filter_panel = 'All Panels';
    $cur_name_clinic = 'All Clinics';
    $period_export = $date_to;
    $lab_name = CI_Base_Lab::get_current_lab_name();

    if ($sel_panel != 'all') $cur_name_filter_panel = isset($all_panels[$sel_panel]) ? $all_panels[$sel_panel] : 'All Panels';
    if ($sel_clinic != "all") {
        foreach ($clinics_rs as $clinic) {
            if ($clinic->cID == $sel_clinic) {
                $cur_name_clinic = $clinic->cli_clinic;
                break;
            }
        }
    }

    if ($export_type == "pdf" || $filepath_save_pdf != "") {
        $orientation = 'portrait';
        ob_start();
        $outfile = generate_report_filename('RPT', 'PrevalenceRates', $period_export, '', 'pdf');
        require TRCPDD_PATH . 'parts/reports/prevalence_rates_pdf.php';
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
        $outfile = generate_report_filename('RPT', 'PrevalenceRates', $period_export, '', 'csv');
        if ($filepath_save_csv == "") tr_pdd_header('csv', $outfile);
        if ($filepath_save_csv != '')
            $f = fopen($filepath_save_csv, 'w');
        else
            $f = fopen('php://output', 'w');


        fputcsv($f, ["Prevalence Rates Report"], TR_CSV_SEPARATER);
        fputcsv($f, [$lab_name], TR_CSV_SEPARATER);
        fputcsv($f, ["Generated on: $generated_on"], TR_CSV_SEPARATER);
        fputcsv($f, [""], TR_CSV_SEPARATER);
        fputcsv($f, ["Report Parameters"], TR_CSV_SEPARATER);
        fputcsv($f, ["Panels:", $cur_name_filter_panel], TR_CSV_SEPARATER);
        fputcsv($f, ["Location:", $cur_name_clinic], TR_CSV_SEPARATER);
        fputcsv($f, ["Time Period:", $label_date_range], TR_CSV_SEPARATER);

        foreach ($group_reports as $g) {
            $group_pos_neg = $group_pos_negs[$g['id']];
            if ($sel_panel == 'all' && (empty($g['tests']) && empty($g['subs']))) continue;
            fputcsv($f, [""], TR_CSV_SEPARATER);
            fputcsv($f, [$g['name']], TR_CSV_SEPARATER);
            fputcsv($f, [($group_pos_neg['total'] ?? 0) . ' specimens • ' . ($group_pos_neg['POS'] ?? 0) . ' positive • ' . $label_date_range], TR_CSV_SEPARATER);
            fputcsv($f, [""], TR_CSV_SEPARATER);
            fputcsv($f, ['TARGET', 'POSITIVES', 'PREVALENCE', 'V.LOW', 'LOW', 'MED', 'HIGH', 'V.HIGH'], TR_CSV_SEPARATER);
            if (!empty($g['subs'])) {
                foreach ($g['subs'] as $sub) {
                    $testCount = is_array($sub['tests']) ? count($sub['tests']) : 0;

                    if ($testCount === 0) {
                        continue;
                    }
                    $totalH  = 0;
                    $totalM  = 0;
                    $totalVH = 0;
                    $totalVL = 0;
                    $totalL  = 0;
                    $totalP  = 0;
                    $totalN  = 0;

                    $totalPositives = 0;
                    $totalTests     = 0;

                    foreach ($sub['tests'] as $name => $test) {

                        $totalH  += $test['H']  ?? 0;
                        $totalM  += $test['M']  ?? 0;
                        $totalVH += $test['VH'] ?? 0;
                        $totalVL += $test['VL'] ?? 0;
                        $totalL  += $test['L']  ?? 0;
                        $totalP  += $test['P']  ?? 0;
                        $totalN  += $test['N']  ?? 0;
                    }

                    $totalPositives = $totalH + $totalM + $totalL + $totalVL + $totalVH + $totalP;
                    $totalTests     = $totalPositives + $totalN;
                    $percentH  = $totalPositives > 0 ? calculatePercentage($totalH,  $totalPositives) : '0';
                    $percentM  = $totalPositives > 0 ? calculatePercentage($totalM,  $totalPositives) : '0';
                    $percentL  = $totalPositives > 0 ? calculatePercentage($totalL,  $totalPositives) : '0';
                    $percentVL = $totalPositives > 0 ? calculatePercentage($totalVL, $totalPositives) : '0';
                    $percentVH = $totalPositives > 0 ? calculatePercentage($totalVH, $totalPositives) : '0';
                    fputcsv($f, [$sub['name'], $totalPositives, "-", renderCell($totalVL ?? 0, $percentVL),  renderCell($totalL ?? 0, $percentL), renderCell($totalM ?? 0, $percentM), renderCell($totalH ?? 0, $percentH), renderCell($totalVH ?? 0, $percentVH)], TR_CSV_SEPARATER);

                    foreach ($sub['tests'] as $name => $test) {
                        $totalPositives = 0;
                        $totalTests     = 0;

                        $totalPositives = $test['H'] + $test['M'] + $test['L'] + $test['VL'] + $test['VH'] + $test['P'];
                        $totalTests     = $totalPositives + $test['N'];
                        $positiveRate = calculatePercentage($totalPositives, $totalTests);

                        $percentH  = $totalPositives > 0 ? calculatePercentage($test['H'],  $totalPositives) : '0';
                        $percentM  = $totalPositives > 0 ? calculatePercentage($test['M'],  $totalPositives) : '0';
                        $percentL  = $totalPositives > 0 ? calculatePercentage($test['L'],  $totalPositives) : '0';
                        $percentVL = $totalPositives > 0 ? calculatePercentage($test['H'], $totalPositives) : '0';
                        $percentVH = $totalPositives > 0 ? calculatePercentage($test['VH'], $totalPositives) : '0';
                        fputcsv($f, ["  " . $name, $totalPositives, $positiveRate . "%", renderCell($test['VL'] ?? 0, $percentVL),  renderCell($test['L'] ?? 0, $percentL), renderCell($test['M'] ?? 0, $percentM), renderCell($test['H'] ?? 0, $percentH), renderCell($test['VH'] ?? 0, $percentVH)], TR_CSV_SEPARATER);
                    }
                }
            } else {
                foreach ($g['tests'] as $name => $test) {
                    $totalPositives = 0;
                    $totalTests     = 0;

                    $totalPositives = $test['H'] + $test['M'] + $test['L'] + $test['VL'] + $test['VH'] + $test['P'];

                    $totalTests     = $totalPositives + $test['N'];

                    $positiveRate = calculatePercentage($totalPositives, $totalTests);

                    $percentH  = $totalPositives > 0 ? calculatePercentage($test['H'],  $totalPositives) : '0';
                    $percentM  = $totalPositives > 0 ? calculatePercentage($test['M'],  $totalPositives) : '0';
                    $percentL  = $totalPositives > 0 ? calculatePercentage($test['L'],  $totalPositives) : '0';
                    $percentVL = $totalPositives > 0 ? calculatePercentage($test['H'], $totalPositives) : '0';
                    $percentVH = $totalPositives > 0 ? calculatePercentage($test['VH'], $totalPositives) : '0';
                    fputcsv($f, ["  " . $name, $totalPositives, $positiveRate . "%", renderCell($test['VL'] ?? 0, $percentVL),  renderCell($test['L'] ?? 0, $percentL), renderCell($test['M'] ?? 0, $percentM), renderCell($test['H'] ?? 0, $percentH), renderCell($test['VH'] ?? 0, $percentVH)], TR_CSV_SEPARATER);
                }
            }
        }
        fclose($f);
        if ($filepath_save_csv == '') exit;
    }
}

if ($filepath_save_csv != '' || $filepath_save_pdf != '') {
    return;
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

    .wrap-select .custom-select {
        width: 100%;
    }

    p.text-sm,
    td.text-sm {
        font-size: 0.875rem;
    }

    tbody tr:nth-child(odd) {
        background-color: #f9fafb;
    }

    tbody tr:nth-child(even) {
        background-color: #ffffff;
    }
</style>
<?php $schedule_report->style(); ?>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8 bg-[#f8f9fa]" style="min-height: 80vh;">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">Prevalence Rates Report</h1>
                    <p class="text-muted-foreground mt-1">Pathogen and resistance markers detection rates across all laboratory panels</p>
                </div>
            </div>
            <div data-slot="card"
                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                <div data-slot="card-header"
                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-funnel h-4 w-4 text-primary" aria-hidden="true">
                                <path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"></path>
                            </svg>
                            <h4 data-slot="card-title" class="leading-none m-0">Filters</h4>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="flex items-center gap-2 wrap-select">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-dna h-4 w-4 text-gray-500 flex-shrink-0" aria-hidden="true">
                                <path d="m10 16 1.5 1.5"></path>
                                <path d="m14 8-1.5-1.5"></path>
                                <path d="M15 2c-1.798 1.998-2.518 3.995-2.807 5.993"></path>
                                <path d="m16.5 10.5 1 1"></path>
                                <path d="m17 6-2.891-2.891"></path>
                                <path d="M2 15c6.667-6 13.333 0 20-6"></path>
                                <path d="m20 9 .891.891"></path>
                                <path d="M3.109 14.109 4 15"></path>
                                <path d="m6.5 12.5 1 1"></path>
                                <path d="m7 18 2.891 2.891"></path>
                                <path d="M9 22c1.798-1.998 2.518-3.995 2.807-5.993"></path>
                            </svg>
                            <select id="sel_panel" name="sel_panel" class="flex-1">
                                <option value="all">All Panels</option>
                                <?php foreach ($all_panels as $id => $name): ?>
                                    <option value="<?php echo $id; ?>"
                                        <?php if ($sel_panel == $id) { ?>selected<?php } ?>>
                                        <?php echo $name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 wrap-select">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-building2 lucide-building-2 h-4 w-4 text-gray-500 flex-shrink-0" aria-hidden="true">
                                <path d="M10 12h4"></path>
                                <path d="M10 8h4"></path>
                                <path d="M14 21v-3a2 2 0 0 0-4 0v3"></path>
                                <path d="M6 10H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2"></path>
                                <path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"></path>
                            </svg>
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
                        <div class="flex items-center gap-2 wrap-select">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="lucide lucide-calendar h-4 w-4 text-muted-foreground" aria-hidden="true">
                                <path d="M8 2v4"></path>
                                <path d="M16 2v4"></path>
                                <rect width="18" height="18" x="3" y="4" rx="2"></rect>
                                <path d="M3 10h18"></path>
                            </svg>
                            <select id="month_range" name="month_range">
                                <?php foreach ($filter_date_select as $item): ?>
                                    <option value="<?php echo esc_attr($item['value']); ?>">
                                        <?php echo esc_html($item['text']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div id="reports_container" class="space-y-6" style="min-height: 100px;"></div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg py-4 px-4">
                <p class="text-xs text-gray-600 text-center">
                    <strong style="display: block;padding-bottom:3px;">Ignite Laboratory Information System</strong>
                    igniteLIS analytics reports are intended for authorized laboratory and administrative users. These reports provide aggregated operational data to support workflow monitoring, performance oversight, and applicable compliance or public-health reporting.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
    var sel_clinic = '<?php echo $sel_clinic; ?>';
    var sel_panel = '<?php echo $sel_panel; ?>';
    var month_range = '<?php echo $month_range; ?>';
    var label_date_range = '';
    const url_main_page = 'ci_reports_prevalence_rates';
    let allData = null;
    const sortIcon = ` 
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 ml-1 inline opacity-40">
        <path d="m21 16-4 4-4-4"></path>
        <path d="M17 20V4"></path>
        <path d="m3 8 4-4 4 4"></path>
        <path d="M7 4v16"></path>
    </svg>
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3 ml-1" style="display:none">
        <path d="m5 12 7-7 7 7"></path>
        <path d="M12 19V5"></path>
    </svg>
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3 ml-1" style="display:none">
        <path d="M12 5v14"></path>
        <path d="m19 12-7 7-7-7"></path>
    </svg>`;

    // Hàm tính phần trăm
    function calculatePercentage(positive, total) {
        if (total === 0) return '0';
        return ((positive / total) * 100).toFixed(2);
    }

    function renderCell(total, percent) {
        total = Number(total) || 0;
        return total > 0 ? `${total} (${percent}%)` : '-';
    }

    // Hàm render một bảng cho group
    function renderGroupTable(group, group_pos_neg) {
        const groupId = group.id;
        const groupName = group.name;
        const hasSubs = group.subs && Object.keys(group.subs).length > 0;

        let html = `
        <div data-slot="card" class="bg-card text-card-foreground flex flex-col rounded-xl border shadow-card gap-2">
            <div data-slot="card-header" class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6 pb-2">
                <h4 data-slot="card-title" class="text-base" style="font-weight: 400;">${groupName}</h4>
                <p data-slot="card-description" class="text-muted-foreground text-sm">${group_pos_neg.total || 0} specimens • ${group_pos_neg.POS || 0} positive • ${label_date_range || 'December 2025'}</p>
            </div>
            <div data-slot="card-content" class="[&:last-child]:pb-6 p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs table-fixed reports_table" data-group-id="${groupId}">
                        <colgroup>
                            <col class="w-[35%]">
                            <col class="w-[13%]">
                            <col class="w-[13%]">
                            <col class="w-[13%]">
                            <col class="w-[13%]">
                            <col class="w-[13%]">
                            <col class="w-[13%]">
                            <col class="w-[13%]">
                        </colgroup>
                        <thead>
                            <tr class="bg-[#D83D00] text-white">
                                <th class="py-2 px-3 text-left text-xs font-semibold cursor-pointer hover:bg-[#c23500] select-none" data-col="0">
                                    Target 
                                   ${sortIcon}
                                </th>
                                <th class="py-2 px-3 text-right text-xs font-semibold cursor-pointer hover:bg-[#c23500] select-none" data-col="1">
                                    Positives
                                     ${sortIcon}
                                </th>
                                <th class="py-2 px-3 text-right text-xs font-semibold cursor-pointer hover:bg-[#c23500] select-none" data-col="2">
                                    Prevalence
                                     ${sortIcon}
                                </th>
                                <th class="py-2 px-3 text-right text-xs font-semibold cursor-pointer hover:bg-[#c23500] select-none" data-col="3">
                                    V.Low
                                     ${sortIcon}
                                </th>
                                <th class="py-2 px-3 text-right text-xs font-semibold cursor-pointer hover:bg-[#c23500] select-none" data-col="4">
                                    Low
                                     ${sortIcon}
                                </th>
                                <th class="py-2 px-3 text-right text-xs font-semibold cursor-pointer hover:bg-[#c23500] select-none" data-col="5">
                                    Med
                                     ${sortIcon}
                                </th>
                                <th class="py-2 px-3 text-right text-xs font-semibold cursor-pointer hover:bg-[#c23500] select-none" data-col="6">
                                    High
                                     ${sortIcon}
                                </th>
                                <th class="py-2 px-3 text-right text-xs font-semibold cursor-pointer hover:bg-[#c23500] select-none" data-col="7">
                                    V.High
                                     ${sortIcon}
                                </th>
                            </tr>
                        </thead>
                        <tbody>`;

        if (hasSubs) {
            Object.values(group.subs).forEach((sub, subIndex) => {
                html += renderSubgroupRows(sub, subIndex);
            });
        } else {
            html += renderTestRows(group.tests, 0);
        }

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        </div>`;

        return html;
    }
    const specialTests = ['Atopobium vaginae', 'Gardnerella vaginalis'];

    // Hàm render subgroup header và tests
    function renderSubgroupRows(sub, subIndex) {
        const tests = sub.tests;
        const testCount = Object.keys(tests).length;

        if (testCount === 0) return '';

        // Tính giá trị trung bình cho subgroup
        let totalH = 0,
            totalM = 0,
            totalVH = 0,
            totalVL = 0,
            totalL = 0,
            totalP = 0,
            totalN = 0;


        Object.entries(tests).forEach(([name, test]) => {

            totalH += test.H || 0;
            totalM += test.M || 0;
            totalVH += test.VH || 0;
            totalVL += test.VL || 0;
            totalL += test.L || 0;
            totalP += test.P || 0;
            totalN += test.N || 0;
        });


        let totalPositives = totalH + totalM + totalL + totalVL + totalVH + totalP;
        if (totalPositives == 0) {
            totalPositives = totalP;
        }
        let totalTests = totalPositives + totalN;

        const percentH = totalPositives > 0 ? calculatePercentage(totalH, totalPositives) : '0';
        const percentM = totalPositives > 0 ? calculatePercentage(totalM, totalPositives) : '0';
        const percentL = totalPositives > 0 ? calculatePercentage(totalL, totalPositives) : '0';
        const percentVL = totalPositives > 0 ? calculatePercentage(totalVL, totalPositives) : '0';
        const percentVH = totalPositives > 0 ? calculatePercentage(totalVH, totalPositives) : '0';

        let html = `
        <tr style="background: #f6f3f4;" class="subgroup-header" data-subgroup="${subIndex}">
            <td style="font-weight: 600;" class="py-1.5 px-3 font-semibold text-gray-700 text-sm">${sub.name}</td>
            <td style="font-weight: 600;" class="py-1.5 px-3 text-right text-sm text-gray-600">${totalPositives}</td>
            <td style="font-weight: 600;" class="py-1.5 px-3 text-right text-sm text-gray-600">-</td>
            <td style="font-weight: 600;" class="py-1.5 px-3 text-right text-sm text-gray-600">
                ${renderCell(totalVL, percentVL)}
            </td>
            <td style="font-weight: 600;" class="py-1.5 px-3 text-right text-sm text-gray-600">
                ${renderCell(totalL, percentL)}
            </td>
            <td style="font-weight: 600;" class="py-1.5 px-3 text-right text-sm text-gray-600">
                ${renderCell(totalM, percentM)}
            </td>
            <td style="font-weight: 600;" class="py-1.5 px-3 text-right text-sm text-gray-600">
                ${renderCell(totalH, percentH)}
            </td>
            <td style="font-weight: 600;" class="py-1.5 px-3 text-right text-sm text-gray-600">
                ${renderCell(totalVH, percentVH)}
            </td>
        </tr>`;

        // Render tests của subgroup
        html += renderTestRows(tests, subIndex);

        return html;
    }

    // Hàm render test rows
    function renderTestRows(tests, subgroupIndex) {
        let html = '';
        let rowIndex = 0;

        Object.entries(tests).forEach(([testName, test]) => {
            const rowClass = rowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-50';

            let totalPositives = (test.H || 0) + (test.M || 0) + (test.L || 0) + (test.VL || 0) + (test.VH || 0) + (test.P || 0);

            let totalTests = totalPositives + (test.N || 0);
            const positiveRate = calculatePercentage(totalPositives, totalTests);

            // Tính phần trăm cho từng level
            const percentH = totalPositives > 0 ? calculatePercentage(test.H || 0, totalPositives) : '0';
            const percentM = totalPositives > 0 ? calculatePercentage(test.M || 0, totalPositives) : '0';
            const percentL = totalPositives > 0 ? calculatePercentage(test.L || 0, totalPositives) : '0';
            const percentVL = totalPositives > 0 ? calculatePercentage(test.VL || 0, totalPositives) : '0';
            const percentVH = totalPositives > 0 ? calculatePercentage(test.VH || 0, totalPositives) : '0';

            html += `
            <tr class="test-row" data-subgroup="${subgroupIndex}" 
                data-positive-rate="${positiveRate}" 
                data-positives="${totalPositives}"
                data-low="${test.L || 0}" 
                data-vlow="${test.VL || 0}" 
                data-vhigh="${test.VH || 0}" 
                data-med="${test.M || 0}" 
                data-high="${test.H || 0}"
                data-neg="${test.N || 0}">
                <td class="py-2 px-3 text-sm" style="padding-left: 20px;">${testName}</td>
                <td class="py-2 px-3 text-right text-sm">${totalPositives}</td>
                <td class="py-2 px-3 text-right text-sm" style="font-weight: 600;">${positiveRate}%</td>
                <td class="py-2 px-3 text-right text-sm">
                    ${renderCell(test.VL, percentVL)}
                </td>
                <td class="py-2 px-3 text-right text-sm">
                    ${renderCell(test.L, percentL)}
                </td>
                <td class="py-2 px-3 text-right text-sm">
                    ${renderCell(test.M, percentM)}
                </td>
                <td class="py-2 px-3 text-right text-sm">
                    ${renderCell(test.H, percentH)}
                </td>
                <td class="py-2 px-3 text-right text-sm">
                    ${renderCell(test.VH, percentVH)}
                </td>
            </tr>`;
            rowIndex++;
        });

        return html;
    }

    // Hàm render tất cả tables
    function renderAllTables(data) {
        const container = $('#reports_container');
        container.empty();

        if (!data || !data.group_reports) {
            container.html('<p class="text-center py-8 text-gray-500">No data available</p>');
            return;
        }
        const pos_negs = data.group_pos_negs;

        Object.values(data.group_reports).forEach(group => {
            const group_pos_neg = pos_negs[group.id];

            if (sel_panel == 'all' && (group.subs && Object.keys(group.subs).length > 0 || group.tests && Object.keys(group.tests).length > 0) || sel_panel !== 'all')
                container.append(renderGroupTable(group, group_pos_neg));
        });

        // Gắn sự kiện sort cho tất cả bảng
        attachSortEvents();
    }

    function attachSortEvents() {
        $('.reports_table thead th').off('click').on('click', function() {
            const $th = $(this);
            const $table = $th.closest('table');
            const colIndex = parseInt($th.data('col'));
            const isAsc = !$th.data('asc');

            // Reset tất cả icons trong table này
            $table.find('thead th .lucide-arrow-up-down').show().css('opacity', '0.4').addClass('inline');
            $table.find('thead th .lucide-arrow-up, thead th .lucide-arrow-down').removeClass('inline').hide();

            $th.find('.lucide-arrow-up-down').removeClass('inline').hide();

            // Hiện icon phù hợp
            if (isAsc) {
                $th.find('.lucide-arrow-up').show().addClass('inline');
            } else {
                $th.find('.lucide-arrow-down').show().addClass('inline');
            }

            // Lấy tất cả subgroups
            const subgroups = {};
            $table.find('tbody tr.test-row').each(function() {
                const subgroupIndex = $(this).data('subgroup');
                if (!subgroups[subgroupIndex]) {
                    subgroups[subgroupIndex] = [];
                }
                subgroups[subgroupIndex].push($(this));
            });

            // Sort từng subgroup riêng biệt
            Object.keys(subgroups).forEach(subgroupIndex => {
                const rows = subgroups[subgroupIndex];

                rows.sort(function(a, b) {
                    let valA, valB;

                    switch (colIndex) {
                        case 0: // Target name
                            valA = a.find('td').eq(0).text().trim().toLowerCase();
                            valB = b.find('td').eq(0).text().trim().toLowerCase();
                            break;
                        case 1: // Positives
                            valA = parseInt(a.data('positives')) || 0;
                            valB = parseInt(b.data('positives')) || 0;
                            break;
                        case 2: // % Positive
                            valA = parseFloat(a.data('positive-rate')) || 0;
                            valB = parseFloat(b.data('positive-rate')) || 0;
                            break;
                        case 3: // vLow
                            valA = parseInt(a.data('vlow')) || 0;
                            valB = parseInt(b.data('vlow')) || 0;
                            break;
                        case 4: // Low/Neg
                            valA = parseInt(a.data('low')) || 0;
                            valB = parseInt(b.data('low')) || 0;
                            break;
                        case 5: // Med
                            valA = parseInt(a.data('med')) || 0;
                            valB = parseInt(b.data('med')) || 0;
                            break;
                        case 6: // High
                            valA = parseInt(a.data('high')) || 0;
                            valB = parseInt(b.data('high')) || 0;
                            break;
                        case 7: // vHigh
                            valA = parseInt(a.data('vhigh')) || 0;
                            valB = parseInt(b.data('vhigh')) || 0;
                            break;
                    }

                    if (typeof valA === 'string') {
                        return isAsc ? valA.localeCompare(valB) : valB.localeCompare(valA);
                    } else {
                        return isAsc ? (valA - valB) : (valB - valA);
                    }
                });

                const $subgroupHeader = $table.find(`tr.subgroup-header[data-subgroup="${subgroupIndex}"]`);
                if ($subgroupHeader.length) {
                    let insertAfter = $subgroupHeader;
                    rows.forEach(row => {
                        insertAfter.after(row);
                        insertAfter = row;
                    });
                } else {
                    let insertPoint = $table.find('tbody');
                    rows.forEach(row => {
                        insertPoint.append(row);
                    });
                }
            });

            $th.data('asc', isAsc);
        });
    }

    // Main jQuery code
    jQuery(function($) {
        window.$ = jQuery;

        if (typeof update_ui_select === 'function') {
            update_ui_select();
        }

        $("#daterange-btn-all-tab").removeClass("flex").hide();

        function LoadData() {
            $("#filter_loading").show();
            showLoading('reports_container');

            jQuery.post("admin.php?page=" + url_main_page, {
                trajax: 'loadData',
                sel_clinic: sel_clinic,
                sel_panel: sel_panel,
                month_range: month_range
            }, function(response) {
                try {
                    const res = JSON.parse(response);
                    if (res == null) return false;
                    label_date_range = res.label_date_range;
                    renderAllTables(res);

                } catch (e) {
                    console.error('Error parsing response:', e);
                } finally {
                    $("#filter_loading").hide();
                    hideLoading('reports_container');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $("#filter_loading").hide();
            });
        }

        // Event handlers cho filters
        $('#month_range').off('change').on('change', function() {
            month_range = $(this).val();
            label_date_range = $('option:selected', '#month_range').text();
            LoadData();
        });

        $("#sel_panel").off('change').on('change', function() {
            sel_panel = $(this).val();
            LoadData();
        });

        $("#sel_clinic").off('change').on('change', function() {
            sel_clinic = $(this).val();
            LoadData();
        });

        // Export handlers
        $(".btn-export").on("click", function(e) {
            e.stopPropagation();
            var id_btn = $(this).attr("id");
            $("#menu_" + id_btn).toggle();
        });

        $(document).on("click", function(e) {
            if (!$(e.target).closest(".menu-export").length) {
                $(".menu-export").hide();
            }
        });
        $(".export_item").click(async function() {
            var export_func = $(this).attr("export_func");
            var export_type = $(this).attr("export_type");
            var $url = location.href + '&trajax=export_report&export_type=' + export_type;
            $url += '&export_func=' + export_func;
            $url += '&sel_clinic=' + sel_clinic;
            $url += '&sel_panel=' + sel_panel;
            $url += '&month_range=' + month_range;
            const loadingToast = showToast('info', 'Preparing your download...', 'Generating...', 0, 'top_right');

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
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }

                showToast('success', 'Download ready!', 'Your file "' + filename + '" has been downloaded.', 4000, 'top_right');

            } catch (err) {
                showToast('error', 'Error', 'Download failed.', 4000, 'top_right');
                if (loadingToast) {
                    loadingToast.classList.remove("show");
                    setTimeout(() => loadingToast.remove(), 300);
                }
            }
        });
        LoadData();
    });
</script>
<?php $schedule_report->js(); ?>