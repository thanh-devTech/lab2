<?php
global $wpdb, $current_role;
wp_enqueue_script('jquery-ui-core');
wp_enqueue_script('jquery-ui-slider');
wp_enqueue_script('jquery-ui', '', array('jquery'));
wp_enqueue_script('jquery-ui-datepicker');

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');
$google_charts = array();
if (empty($_REQUEST['month'])) {
    $_REQUEST['month'] = date_i18n('Y-m');
}
$month = $_REQUEST['month'];

$cli_ID = isset($_REQUEST['cli_ID']) ? $_REQUEST['cli_ID'] : 0;
$user_id = get_current_user_id();
if (empty($cli_ID)) {
    $cli_ID = get_user_meta($user_id, '_use_current_clinic_id', true);
}
switch ($current_role) {
    case 'lab_rep':
    case 'lab_rep_w2':

        $rep_id = get_user_meta($user_id, '_rep_id', true);
        $rep = $wpdb->get_row("select * from ci_lab_reps where rep_ID = {$rep_id}", ARRAY_A);


        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                        where cli.cli_status = 1 and `cli_rep-ID`={$rep_id} order by cli_clinic asc");


        break;
    case 'doctor':
        $doc_ID = get_user_meta($user_id, '_doc_id', true);
        $cli_ID = $wpdb->get_col("select cli_ID from ci_clinic_doc where doc_ID = {$doc_ID} ");
        $cli_ID = implode(',', $cli_ID);
        break;

    case 'collector':
        $col_id = get_user_meta($user_id, '_collector_id', true);
        $cli_ID = $wpdb->get_col("select cli_ID from ci_clinic_collector where col_ID ={$col_id}");
        $cli_ID = implode(',', $cli_ID);
        break;

    case 'clinic':
        $cli_ID = get_user_meta($user_id, '_clinic_id', true);
        break;

    case 'office_manager':
    case 'multi_site_manager':
        $cid = get_user_meta($user_id, '_clinic_id', true);
        $cids = $wpdb->get_col("select ma_cli_ID from ci_clinic_manager where ma_user_ID={$user_id} or ma_cli_ID = '{$cid}' ");

        $cids = implode(',', $cids);
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli 
                                    where cli.cli_status = 1 and cli.cID in ({$cids}) order by cli_clinic asc");

        break;

    default:
        $clinics = $wpdb->get_results("select cli.cID, cli.cli_clinic from ci_clinic cli
                                    JOIN ci_batches ba ON ba.cli_ID = cli.cID 
                                    where cli.cli_status = 1
                                    group by cli.cID
                                    order by cli_clinic asc");
        break;
}



?>


<h2>Toxicology Patient Statistics (*using collection date) </h2>
<form method="get" style="margin-bottom:20px">
    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
    <label style="float:left">Month: </label>
    <input type="hidden" id="select_month" name="month" value="<?php echo $month ?>" />
    <div class="select_month" style="float:left;margin-right:10px;" data-month="<?php echo $month ?>"></div>
    <?php
    if (isset($clinics)) {
    ?>
        <select name="cli_ID" class="select2" data-placeholder="Select Clinic">
            <option value=""></option>
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

    <input type="submit" class="button-primary" value="Report" />

</form>


<?php

if ($month &&  !empty($cli_ID)) {
    $data = array(
        'month' => $month,
        'cli_ID' => $cli_ID,
        'report_type' => 'tox_report_stats'
    );
    $report = trpdd_get_html_report($data);

?>
    <?php if ($report['hasreport']) { ?>
        <div class="frow report_actions center" style="margin-bottom:15px">
            <a class="button print_report">Print</a>
            <a class="button pdf_report" href="?tr_action=ci_report_pdf&report_type=tox_report_stats&month=<?php echo $month ?>&cli_ID=<?php echo $cli_ID ?>" target="_blank">PDF</a>

        </div>
    <?php
    }
    ?>
    <div id="print_wrap" class="wrap_<?php echo $report['orientation'] ?>">
        <style media="print">
            @media print {
                @page {
                    size: letter <?php echo $report['orientation'] ?>
                }

                body {
                    zoom: 100%
                }

                .notprint {
                    display: none;
                }
            }

            .center {
                text-align: center;
            }

            .right {
                text-align: right;
            }

            .bold {
                font-weight: bold;
            }

            @page {
                margin: 1cm 1cm 1cm 1cm;
            }

            body {
                zoom: 100%;
                margin: 0;
            }

            break,
            .break {
                page-break-after: always;
                border: 0;
            }

            body,
            table td,
            table,
            th {
                font-family: 'Oswald', Arial, Helvetica, sans-serif;
                font-size: 9pt;
            }

            h2 {
                font-size: 16px;
                font-weight: bold;
                ;
            }
        </style>
        <?php echo $report['html']; ?>
    </div>
<?php
}
?>


<style>
    #print_wrap #header,
    #print_wrap #footer {
        display: none !important;
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
</style>
<script src="<?php echo TRCPDD_URL . '/js/select2.min.js?' . TRCPDD_VS ?>"></script>
<link href="<?php echo TRCPDD_URL . '/css/select2.min.css?' . TRCPDD_VS ?>" type='text/css' media='all' rel='stylesheet' />

<script>
    jQuery(function($) {
        function printElement(e) {
            var ifr = document.createElement('iframe');
            ifr.style = 'height: 5px; width: 5px; position: absolute;left:-999999px'
            document.body.appendChild(ifr);

            $(e).clone().appendTo(ifr.contentDocument.body);
            setTimeout(function() {
                ifr.contentWindow.print();

                ifr.parentElement.removeChild(ifr);
            }, 500);
        }

        $('.select2').select2();

        $('.select_month').each(function() {
            $date = $(this).data('month') + '-01';
            $(this).datepicker({
                dateFormat: 'yy-mm-dd',
                setDate: $date,
                defaultDate: 0,
                changeMonth: true,
                changeYear: true,
                onChangeMonthYear: function(year, month, inst) {
                    $('#select_month').val(year + '-' + month);
                }
            });
            $(this).datepicker('setDate', $date);
        });
        $('a.print_report').click(function() {
            printElement('#print_wrap');
            return false;
        })
    })
</script>