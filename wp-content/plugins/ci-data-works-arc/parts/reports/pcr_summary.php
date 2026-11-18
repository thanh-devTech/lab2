<?php
global $wpdb, $current_role;

if (empty($_REQUEST['month'])) {
    $_REQUEST['month'] = date_i18n('Y-m');
}
$month = $_REQUEST['month'];
$user_id = get_current_user_id();

$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;

if (empty($cli_ID)) {
    $cli_ID = get_user_meta($user_id, '_use_current_clinic_id', true);
}

$report_html = '';
if (!empty($month) && !empty($cli_ID)) {

    if (isset($_GET['trajax']) && stripos($_GET['trajax'], 'csv') !== false) {
        include(TRCPDD_PATH . 'reports/clinic_monthly_summary_csv.php');
        exit;
    }
    $has_report = false;
    ob_start();
    include(TRCPDD_PATH . 'reports/clinic_monthly_summary.php');
    $report_html = ob_get_clean();

    if (isset($_GET['trajax'])) {
        if ($has_report) {
            ob_clean();
            $filename = 'pcr_summary.pdf';
            try {
                trpdd_build_pdf(trpdd_pdf_html($report_html), array('filename' => $filename));
            } catch (Error $e) {
                Tr_Session_Class::add_error("Error while export PDF file. " . $e->getMessage());
                if (isset($_REQUEST['trajax'])) {
                    wp_redirect(Tr_Base_Class_V6::link(array('trajax' => ''), false));
                }
            }
            exit;
        } else {
            Tr_Session_Class::add_error('Results Not found!');
            if (isset($_REQUEST['trajax'])) {
                wp_redirect(Tr_Base_Class_V6::link(array('trajax' => ''), false));
            }
        }
    }
}

wp_enqueue_script('jquery-ui-core');
wp_enqueue_script('jquery-ui-slider');
wp_enqueue_script('jquery-ui', '', array('jquery'));
wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
$google_charts = array();

$conditions = trpdd_get_batch_conditions(0);

$where_clinic = $conditions['where_clinic'];

$clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli
                                    where   {$where_clinic}
                                    group by cli.cID
                                    order by cli_clinic asc");
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
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL . 'js/admin_dashboard.js?' . TRCPDD_VS ?>"></script>
<style>
    .editable input {
        width: 80px;
    }

    .ui-datepicker-calendar {
        display: none;
        color: #94cc58;
    }

    .update-nag {
        display: none
    }

    .select_month .ui-datepicker .ui-datepicker-header,
    .select_month .ui-datepicker {
        padding: 0;
    }

    .select_month .ui-widget-header,
    .select_month .ui-widget-content,
    .select_month .ui-datepicker {
        background: none;
        border: 0
    }

    .select_month .ui-datepicker .ui-datepicker-prev,
    .select_month .ui-datepicker .ui-datepicker-next {
        background: #35afe3
    }

    .ui-datepicker-calendar {
        display: none;
    }

    .patient_report {
        width: 100%;
        border: 1px solid #999;
    }

    .patient_report th {
        background: #999;
        color: #fff;
    }

    .frow .title {
        text-align: center;
        font-weight: 700;
        font-size: 16px;
    }

    #reportwrap .viewreport {
        background: #fff;
        max-width: 800px;
        margin: 0 auto;
        padding: 30px;
        border-radius: 10px;
    }

    #reportwrap .wrapform {
        background: #fff;
        max-width: 800px;
        margin: 0 auto;
        padding: 2px 30px;
        border-radius: 10px;
        margin-bottom: 30px;
    }

    #reportwrap .ui-widget.ui-widget-content {
        border: none;
    }

    #reportwrap select {
        border-radius: 5px !important;
        margin-left: 1px;
        margin-right: 1px;
    }

    #reportwrap .select2-container--default .select2-selection--single {

        border-radius: 5px !important;
    }
</style>


<script>
    jQuery(function($) {
        $('.select2').select2();
    })
</script>
<script>
    jQuery(function($) {
        $('.select_month').each(function() {
            $date = $(this).data('month') + '-01';
            $(this).datepicker({
                dateFormat: 'yy-mm-dd',
                setDate: $date,
                defaultDate: 0,
                minDate: '2020-01-01',
                maxDate: $date,
                changeMonth: true,
                changeYear: true,
                onChangeMonthYear: function(year, month, inst) {
                    $('#select_month').val(year + '-' + month);
                }
            });
            $(this).datepicker('setDate', $date);
        });

    })
</script>

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
                                stroke-linejoin="round" class="lucide lucide-chart-column h-3 w-3" aria-hidden="true">
                                <path d="M3 3v16a2 2 0 0 0 2 2h16"></path>
                                <path d="M18 17V9"></path>
                                <path d="M13 17V5"></path>
                                <path d="M8 17v-3"></path>
                            </svg><span class="truncate max-w-[120px] sm:max-w-none">PCR Summary Report</span></span>
                    </li>
                </ol>
            </nav>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 style="font-size: 23px; font-weight: 400; margin: 0px; padding: 9px 0px 4px; line-height: 1.3;">
                    PCR Summary Report</h1>
                <p class="text-muted-foreground mt-1">Comprehensive PCR testing results and antibiotic interpretation
                    analytics</p>
            </div>
        </div>
        <div>
            <div class="space-y-5">
                <div class="">
                    <?php Tr_Session_Class::show() ?>

                    <div class=" wrapform">
                        <table style="margin:20px auto;width:auto">
                            <tr>
                                <td>
                                    <form method="get">
                                        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

                                        <input type="hidden" id="select_month" name="month"
                                            value="<?php echo $month ?>" />
                                        <div class="select_month" style="float:left;margin-right:10px;"
                                            data-month="<?php echo $month ?>"></div>
                                        <?php
                                        if (isset($clinics)) {
                                        ?>
                                            <select name="cli_ID" class="select2" data-placeholder="Select Clinic">
                                                <option value="all">All Clinics</option>
                                                <?php
                                                foreach ($clinics as $cli) {
                                                    $selected = $cli->cID == $cli_ID ? 'selected' : '';
                                                    echo '<option value="' . $cli->cID . '" ' . $selected . '>' . $cli->cli_clinic . '</option>';
                                                }
                                                ?>
                                            </select>
                                        <?php
                                        }
                                        ?>
                                        <input type="submit" class="button bsty6" value="Report"
                                            onclick="jQuery(this).parents('form').removeAttr('target')" />
                                        <input type="submit" class="button bsty6" name="trajax" value="PDF"
                                            onclick="jQuery(this).parents('form').attr('target','_viewpdf')" />
                                        <?php /*<input type="submit" class="button bsty6" name="trajax" value="CSV" onclick="jQuery(this).parents('form').attr('target','_viewpdf')" />*/ ?>
                                    </form>
                                </td>
                            </tr>
                        </table>
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
                                        </svg></div>Volume by PCR Summary Report
                                </h4>
                            </div>

                        </div>

                    </div>
                    <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6 space-y-6">
                        <div class="border rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <?php if (!empty($report_html)) {
                                    echo '<div class="viewreport" style="padding: 20px;">';
                                    echo $report_html;
                                    echo '</div>';
                                }
                                ?>
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