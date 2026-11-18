<?php
global $wpdb;
$schedule_report = new CI_Report_Schedule('collectors', 'Collectors Report');

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

$filter_date_select = [];
$filter_date_select[] = ['value' => '7_days', 'text' => '7 days'];
$filter_date_select[] = ['value' => '14_days', 'text' => '14 days'];
$filter_date_select[] = ['value' => '30_days', 'text' => '30 days'];
$filter_date_select[] = ['value' => '90_days', 'text' => '90 days'];
$filter_date_select[] = ['value' => 'custom', 'text' => 'Custom'];


include(TRCPDD_PATH . 'inc/define_apis.php');

$api_ids = array();
foreach ($define_apis as $k => $arr) {
    if (tr_get_option('ci_pdf_data', $k) && isset($arr['id']) && $arr['id'] !== '') {
        $api_ids[] = absint($arr['id']);
    }
}
$api_ids = array_filter(array_unique($api_ids));

$show_by_clinic = count($api_ids) == 0;

wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');


$results = array();
if ($from_date && $to_date) {

    $conditions = trpdd_get_batch_conditions(0, '');
    $where = ' is_saved = 0 ' . $conditions['where'];

    $where .= " and ba.doc >= '{$from_date}' and ba.doc <= '{$to_date} 23:59:59' ";
    $where .= " and ba.cli_ID > 0 ";


    $byinterfacequery = '';
    $totalinterfaceqr = '';
    if (count($api_ids) > 0) {
        //by api
        $api_ids_str = implode(',', $api_ids);
        $byinterfacequery = " IF(mo.mo_ID is not null and mo_file_id in ({$api_ids_str}), 1, 0) byinterface, ";
        $totalinterfaceqr = " sum(byinterface) as numbers_interface, ";
    } else {
        //by clinic
        $clinic_user_ids = $wpdb->get_col("select DISTINCT(ma_user_ID) from ci_clinic_manager");
        $clinic_user_ids_str = implode(',', array_map('absint', $clinic_user_ids));
        if (empty($clinic_user_ids_str)) {
            $clinic_user_ids_str = '0';
        }
        $byinterfacequery = " IF(ba.author_ID in ({$clinic_user_ids_str}), 1, 0) byclinic, ";
        $totalinterfaceqr = " sum(byclinic) as numbers_byclinic, ";
    }


    $child_q = array();
    foreach (tr_pdd_get_batch_types() as $btype) {


        $batch_tbl = tr_pdd_get_table_by_btype($btype);
        $report_tbl = tr_pdd_get_table_report_by_btype($btype);
        $where_rejected = tr_pdd_where_batch_rejected(false);


        $child_q[] = "(select 
            col.col_ID,
            col.col_first,
            col.col_last,
            col.col_employee,
            ba.cli_ID,
            ba.requesting_practice,
            {$byinterfacequery}
            IF(re.estatus = 'C', 1, 0) proccessed,
            IF(re.estatus = 'C' and {$where_rejected}, 1, 0) rejected
            from {$batch_tbl} ba
            left join {$report_tbl} re ON re.patient_id = ba.assen
            left join ci_collector col ON col.col_ID = ba.col_ID
            left join ci_sync_api_orders mo ON ba.mid = mo.mo_mid 
            where {$where})";
    }
    $child_q = implode(" UNION ALL ", $child_q);
    $sql = "
	    select *,
            count(*) as numbers,
            {$totalinterfaceqr}
            sum(proccessed) as proccessed,
            sum(rejected) as rejected
            from ({$child_q}) ba
         group by col_ID, cli_ID
         order by col_first asc
         ";
    $results = $wpdb->get_results($sql);

    if (isset($_GET['debug'])) {
        var_dump($sql);
    }
}

$schedule_report->ajax();

if ($export_func != "" || $filepath_save_csv != "") {

    ob_clean();
    header("Content-disposition: attachment; filename=collectors_report.csv");
    header("Content-Type: text/csv");
    $output = null;
    if ($filepath_save_csv != ''){
        $output = fopen($filepath_save_csv, 'w');
        $csv_out_file = basename('Collectors Report.csv');
    }else{
        $output = fopen('php://output', 'w');
    }
    $headers = array(
        'Name',
        'Lab Employee',
        'Clinic Name',
        'The number of reqs completed',
    );

    if ($show_by_clinic) {
        $headers[] = 'By Clinic';
    } else {
        $headers[] = 'By Interface';
    }
    $headers[] = 'Processed';
    $headers[] = 'Rejected';


    fputcsv($output, $headers);

    foreach ($results as $row) {
        $collectname = trim($row->col_first . ' ' . $row->col_last);
        if (empty($collectname)) {
            $collectname = 'N/A';
        }

        $row->proccessed = $row->proccessed - $row->rejected;

        $datarow = array(
            $collectname,
            $row->col_employee,
            $row->requesting_practice,
            $row->numbers
        );

        if ($show_by_clinic) {
            $datarow[] = $row->numbers_byclinic;
        } else {
            $datarow[] = $row->numbers_interface;
        }

        $datarow[] = $row->proccessed;
        $datarow[] = $row->rejected;

        fputcsv($output, $datarow);
    }
    @fclose($output);

    if ($filepath_save_csv == "") exit;
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
<?php
$totalarr = array('all' => 0, 'interface' => 0, 'proccessed' => 0, 'rejected' => 0);
if (count($results) > 0) {

    foreach ($results as $row) {
        $row->proccessed = $row->proccessed - $row->rejected;


        $totalarr['all'] += $row->numbers;
        $totalarr['proccessed'] += $row->proccessed;
        $totalarr['rejected'] += $row->rejected;

        if ($show_by_clinic) {
            $row->numbers_interface = $row->numbers_byclinic;
        }

        $totalarr['interface'] += $row->numbers_interface;
    }
}
?>

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
                        class="[&amp;&gt;svg]:size-3.5"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right">
                            <path d="m9 18 6-6-6-6"></path>
                        </svg></li>
                    <li data-slot="breadcrumb-item" class="inline-flex items-center gap-1.5"><span
                            data-slot="breadcrumb-page" role="link" aria-disabled="true" aria-current="page"
                            class="text-foreground font-normal flex items-center gap-1 text-xs sm:text-sm"><svg
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3" aria-hidden="true">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                <path d="M18 17V9"></path>
                                <path d="M13 17V5"></path>
                                <path d="M8 17v-3"></path>
                            </svg><span class="truncate max-w-[120px] sm:max-w-none">Collectors Report</span></span>
                    </li>
                </ol>
            </nav>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                    Collectors Report</h1>
                <p class="text-muted-foreground mt-1">Sample collection statistics by collector
                    and location</p>
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
                                    <p class="text-2xl font-bold"><?php echo $totalarr['all'] ?></p>
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
                                    <p class="text-sm text-muted-foreground">Interface</p>
                                    <p class="text-2xl font-bold"><?php echo $totalarr['interface'] ?></p>
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
                                    <p class="text-sm text-muted-foreground">Proccessed</p>
                                    <p class="text-2xl font-bold"><?php echo $totalarr['proccessed'] ?></p>
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
                                    <p class="text-sm text-muted-foreground">Rejected</p>
                                    <p class="text-2xl font-bold"><?php echo $totalarr['rejected'] ?></p>
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
                                            width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-chart-column h-4 w-4 text-blue-600" aria-hidden="true">
                                            <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                            <path d="M18 17V9"></path>
                                            <path d="M13 17V5"></path>
                                            <path d="M8 17v-3"></path>
                                        </svg></div>Volume by Test Panel<span
                                        class="text-sm text-muted-foreground ml-2">(<?php echo $text_sel_date_rep; ?>)</span>
                                </h4>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <button data-slot="dropdown-menu-trigger" export_func="export_collector"
                                    export_type="pdf"
                                    class="inline-flex export_item cursor-pointer items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive text-primary-foreground h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5 bg-primary hover:bg-primary/90"
                                    type="button" id="radix-:reo:" aria-haspopup="menu" aria-expanded="false"
                                    data-state="closed">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-download h-4 w-4 mr-1" aria-hidden="true">
                                        <path d="M12 15V3"></path>
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <path d="m7 10 5 5 5-5"></path>
                                    </svg>
                                    Export<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-chevron-down h-3 w-3 ml-1" aria-hidden="true">
                                        <path d="m6 9 6 6 6-6"></path>
                                    </svg>
                                </button>

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
                                            <th class="px-4 py-3 text-left font-medium text-gray-800">Name</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-800">Lab Employee
                                            </th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-800">Clinic Name</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-800">The number of
                                                reqs completed</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-800">By Clinic</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-800">Processed</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-800">Rejected</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php
                                        if (count($results) > 0) {
                                            $totalarr = array('all' => 0, 'interface' => 0, 'proccessed' => 0, 'rejected' => 0);
                                            foreach ($results as $row) {
                                                $collectname = trim($row->col_first . ' ' . $row->col_last);
                                                if (empty($collectname)) {
                                                    $collectname = 'N/A';
                                                }

                                                $row->proccessed = $row->proccessed - $row->rejected;


                                                $totalarr['all'] += $row->numbers;
                                                $totalarr['proccessed'] += $row->proccessed;
                                                $totalarr['rejected'] += $row->rejected;

                                                if ($show_by_clinic) {
                                                    $row->numbers_interface = $row->numbers_byclinic;
                                                }

                                                $totalarr['interface'] += $row->numbers_interface;
                                        ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 font-medium text-gray-900"><?php echo $collectname ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center font-medium text-gray-900">
                                                        <?php echo $row->col_employee ? '<span class="checkmark"></span>' : '' ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-green-600 font-medium">
                                                        <?php echo $row->requesting_practice ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-gray-600"><?php echo $row->numbers ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-red-600 font-medium">
                                                        <?php echo $row->numbers_interface ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-orange-600 font-medium">
                                                        <?php echo $row->proccessed ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-center text-orange-600 font-medium">
                                                        <?php echo $row->rejected ?>
                                                    </td>
                                                </tr>
                                        <?php
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center py-6 text-sm text-muted-foreground border-t">
                    <p><strong>Ignite Laboratory Information System</strong> - Collectors Report</p>
                    <p class="mt-1">For authorized laboratory and clinical use only. Generated on
                        <?php echo date_i18n('F j, Y'); ?>
                    </p>
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
    var url_main_page = 'ci_reports_collectors';

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
<?php
$schedule_report->js();
?>
