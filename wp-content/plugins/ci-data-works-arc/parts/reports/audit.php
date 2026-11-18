<?php
global $wpdb, $current_role, $blog_id;

$schedule_report = new CI_Report_Schedule('audit', 'Audit Report');
wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
wp_enqueue_script('jquery-validate', TRCPDD_URL . 'admin/js/jquery.validate.min.js', array('jquery'), true);
wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);


$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';
$lab_id = isset($_REQUEST['lab_id']) ? $_REQUEST['lab_id'] : 0;
$sort = isset($_REQUEST['sort']) && $_REQUEST['sort'] == 'desc' ? 'desc' : 'asc';
$sortby = isset($_REQUEST['sortby']) ? $_REQUEST['sortby'] : 'doc';

$trajax         = isset($_REQUEST['trajax']) ? $_REQUEST['trajax'] : '';

$export_func = isset($_REQUEST['export_func']) ? $_REQUEST['export_func'] : '';
$export_type = isset($_REQUEST['export_type']) ? $_REQUEST['export_type'] : '';

$filepath_save_pdf = isset($_REQUEST['filepath_save_pdf']) ? $_REQUEST['filepath_save_pdf'] : '';
$filepath_save_csv = isset($_REQUEST['filepath_save_csv']) ? $_REQUEST['filepath_save_csv'] : '';

if (isset($_REQUEST['submit']) || $export_func != '') {
    if (empty($date_from))
        $date_from = date_i18n('Y-m-d');
    if (empty($date_to))
        $date_to = date_i18n('Y-m-d');


    $where = " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to}' ";


    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $where .= " and ba.lab_id in ( {$lab_str} )  ";
    }

    $child_q = array();
    foreach (tr_pdd_get_batch_types() as $btype) {
        $batch_tbl = tr_pdd_get_table_by_btype($btype);
        $child_q[] = "(
            SELECT
                ba.batch_id,
                ba.assen,
                ba.doc,
                ba.patient_id,
                ba.patient_name,
                ba.requesting_physician AS dname,
                DIS.dis_name AS distributor,
                concat(LR.rep_first,' ',LR.rep_last) as rep,
                0 as ref_idno,
                '' as refname,
                ba.insurance AS pinsname,
                '' as sinsname,
                0 as approved,
                bb.received_amount as collected,
                0 as adjusted,
                0 as patpay,
                0 as patadj,
                0 as insadj,
                '' as patpayd,
                0 as insbal,
                0 as cashbal

                from {$batch_tbl} ba
                left JOIN ci_batch_billings bb ON (bb.claim_no!='' and bb.claim_no is not null and bb.claim_no = ba.claim_id)
                                                        or (bb.assen = ba.assen)
                left join `ci_clinic` as CLI ON CLI.cID = ba.cli_ID
                left JOIN `ci_lab_reps` as LR ON LR.rep_ID = CLI.`cli_rep-ID` 
                left JOIN ci_distributors DIS On DIS.dis_ID = CLI.cli_distributor_ID
                where  ba.batch_id !='' and ba.batch_id!='0' 
                {$where} 
                group by ba.mid 
                )";
    }

    $child_q = implode(' UNION ALL ', $child_q);
    $sql = " select * from ({$child_q}) ba
                order by {$sortby} {$sort}";


    if (isset($_GET['debug'])) {
        echo $sql;
        echo $wpdb->last_error;
    }
    $rows = $wpdb->get_results($sql);

    if (count($rows) == 0) {
        Tr_Session_Class::add_error('Not found!');
        echo $wpdb->last_error;
    }

    if (isset($_REQUEST['debug'])) {
        var_dump($sql);
    }
}

$schedule_report->ajax();

if ($export_func != '' || $filepath_save_csv != '') {
    header("Content-disposition: attachment; filename=audit_report.csv");
    header("Content-Type: text/csv");
    $f = null;
    if ($filepath_save_csv != '') {
        $f = fopen($filepath_save_csv, 'w');
        $csv_out_file = basename('audit_report.csv');
    } else {
        $f = fopen('php://output', 'w');
    }

    $headers = array(
        // '#',
        'batch_id' => 'Batch#',
        'assen' => 'Accession',
        'doc' => 'D.O.C',
        // 'id'=> 'ID#',
        'patient_id' => 'Patient ID',
        'patient_name' => 'Patient Name',
        'requesting_physician' => 'dname',
        'distributor' => 'Distributor',
        'rep' => 'Rep',
        'ref_idno' => 'ref_idno',
        'refname' => 'refname',
        'pinsname' => 'pinsname',
        'insurance' => 'sinsname',
        'approved' => 'approved',
        'collected' => 'collected',
        'adjusted' => 'adjusted',
        'patpay' => 'patpay',
        'patadj' => 'patadj',
        'insadj' => 'insadj',
        'patpayd' => 'patpayd',
        'insbal' => 'insbal',
        'cashbal' => 'cashbal'
    );
    fputcsv($f, $headers);
    $count = 0;
    foreach ($rows as $r) {
        $r = array_values(get_object_vars($r));
        fputcsv($f, $r);
        $count++;
    }
    fclose($f);
    if ($filepath_save_csv == "") {
        $data_log = [
            'type' => 'Audit Report',
            'file_name' => 'audit_report.csv',
            'total_records' => $count,
            'from_date' => $date_from,
            'to_date' => $date_to,
        ];
        trpdd_log_user(get_current_user_id(), 'download_report', $data_log, '', '', '', '', 'Report_' . date_i18n('mdY'));
        exit;
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
                            </svg><span class="truncate max-w-[120px] sm:max-w-none">Audit Report</span></span></li>
                </ol>
            </nav>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                    Audit Report</h1>
                <p class="text-muted-foreground mt-1 text-xs sm:text-[13px]">Sample volume analysis by Audit Report</p>
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
                                    <p class="text-sm text-muted-foreground">Total</p>
                                    <p class="text-2xl font-bold"><?php echo isset($rows) ? count($rows) : 0; ?></p>
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
                                        </svg></div>Volume by Audit Report
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
                                    Export
                                </button>

                                <?php $schedule_report->btn_schedule(); ?>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 mt-4">

                            <form method="get">
                                <span class="onelinemobile">
                                    <label>From: </label>
                                    <input type="search" autocomplete="off" name="date_from" id="from"
                                        value="<?php echo $date_from ?>" />
                                </span>
                                <span class="onelinemobile">
                                    <label>To: </label>
                                    <input type="search" autocomplete="off" name="date_to" id="to"
                                        value="<?php echo $date_to ?>" />
                                </span>

                                <input type="submit" name="submit" value="Run Report" class="button bsty6" />
                                <input type="hidden" name="tr_action" value="ci_export_audit_report" />
                                <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
                            </form>

                        </div>
                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
                        <div class="border rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <?php Tr_Session_Class::show() ?>

                                <?php
                                if (isset($_REQUEST['submit']) && count($rows) > 0) { ?>
                                    <div class="wraptbl">
                                        <table class="wp-list-table widefat striped posts blue_tbl">
                                            <thead>
                                                <tr>
                                                    <?php foreach ($headers as $k => $h): ?>
                                                        <th class="sortable <?php echo $sort ?> <?php if ($sortby == $k)
                                                                                                    echo 'sorted' ?>">
                                                            <a
                                                                href="<?php Tr_Base_Class_V6::link(array('sort' => ($sort == 'desc' ? 'asc' : 'desc'), 'sortby' => $k)) ?>">
                                                                <span><?php echo $h ?></span>
                                                                <span class="sorting-indicator"></span>
                                                            </a>
                                                        </th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rows as $row): ?>
                                                    <tr>
                                                        <td><?php echo $row->batch_id ?></td>
                                                        <td><?php echo $row->assen ?></td>
                                                        <td><?php echo $row->doc ?></td>
                                                        <td><?php echo $row->patient_id ?></td>
                                                        <td><?php echo $row->patient_name ?></td>
                                                        <td><?php echo $row->dname ?></td>
                                                        <td><?php echo $row->distributor ?></td>
                                                        <td><?php echo $row->rep ?></td>
                                                        <td><?php echo $row->ref_idno ?></td>
                                                        <td><?php echo $row->refname ?></td>
                                                        <td><?php echo $row->pinsname ?></td>
                                                        <td><?php echo $row->sinsname ?></td>
                                                        <td><?php echo $row->approved ?></td>
                                                        <td><?php echo $row->collected ?></td>
                                                        <td><?php echo $row->adjusted ?></td>
                                                        <td><?php echo $row->patpay ?></td>
                                                        <td><?php echo $row->patadj ?></td>
                                                        <td><?php echo $row->insadj ?></td>
                                                        <td><?php echo $row->patpayd ?></td>
                                                        <td><?php echo $row->insbal ?></td>
                                                        <td><?php echo $row->cashbal ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                <?php } ?>
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
<script>
    var url_main_page = 'ci_reports_audit';
    var from_date = '<?php echo $date_from; ?>';
    var to_date = '<?php echo $date_to; ?>';

    jQuery(function($) {

        $("#from").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function(selectedDate) {
                $("#to").datepicker("option", "minDate", selectedDate);
            }
        });
        $("#to").datepicker({
            changeMonth: true,
            changeYear: true,
            numberOfMonths: 1,
            dateFormat: 'yy-mm-dd',
            onClose: function(selectedDate) {
                $("#from").datepicker("option", "maxDate", selectedDate);
            }
        });

        $(".export_item").click(async function() {
            var export_type = "csv";
            var $url = location.href + '&trajax=export_report&export_type=' + export_type;
            $url += '&export_func=all';
            $url += '&date_from=' + from_date;
            $url += '&date_to=' + to_date;
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
    });
</script>
<?php $schedule_report->js(); ?>