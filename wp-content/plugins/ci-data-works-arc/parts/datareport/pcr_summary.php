<?php
global $wpdb, $current_role;

return include(trpdd_get_file_path('reports', 'prevalence_rates'));

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
<div class="wrap" id="reportwrap">
    <h2 class="center">Summary Report</h2>
    <?php Tr_Session_Class::show() ?>

    <div class="wrapform">
        <table style="margin:20px auto;width:auto">
            <tr>
                <td>
                    <form method="get">
                        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />

                        <input type="hidden" id="select_month" name="month" value="<?php echo $month ?>" />
                        <div class="select_month" style="float:left;margin-right:10px;" data-month="<?php echo $month ?>"></div>
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
                        <input type="submit" class="button bsty6" value="Report" onclick="jQuery(this).parents('form').removeAttr('target')" />
                        <input type="submit" class="button bsty6" name="trajax" value="PDF" onclick="jQuery(this).parents('form').attr('target','_viewpdf')" />
                        <?php /*<input type="submit" class="button bsty6" name="trajax" value="CSV" onclick="jQuery(this).parents('form').attr('target','_viewpdf')" />*/ ?>
                    </form>
                </td>
            </tr>
        </table>
    </div>

    <?php if (!empty($report_html)) {
        echo '<div class="viewreport">';
        echo $report_html;
        echo '</div>';
    }
    ?>
</div>




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