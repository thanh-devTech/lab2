<?php
global $wpdb;

$date_from = isset($_REQUEST['date_from']) ? $_REQUEST['date_from'] : '';
$date_to   = isset($_REQUEST['date_to']) ? $_REQUEST['date_to'] : '';

include(TRCPDD_PATH . 'inc/define_apis.php');

$api_ids = array();
foreach ($define_apis as $k => $arr) {
    if (tr_get_option('ci_pdf_data', $k)) {
        $api_ids[] = $arr['id'];
    }
}

$show_by_clinic = count($api_ids) == 0;

wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');


$results = array();
if ($date_from && $date_to) {

    $conditions = trpdd_get_batch_conditions(0, '');
    $where      = ' is_saved = 0 ' . $conditions['where'];

    $where      .= " and ba.doc >= '{$date_from}' and ba.doc <= '{$date_to} 23:59:59' ";
    $where      .= " and ba.cli_ID > 0 ";


    $byinterfacequery = '';
    $totalinterfaceqr = '';
    if (count($api_ids) > 0) {
        //by api
        $api_ids_str    = implode(',', $api_ids);
        $byinterfacequery = " IF(mo.mo_ID is not null and mo_file_id in ({$api_ids_str}), 1, 0) byinterface, ";
        $totalinterfaceqr = " sum(byinterface) as numbers_interface, ";
    } else {
        //by clinic
        $clinic_user_ids = $wpdb->get_col("select DISTINCT(ma_user_ID) from ci_clinic_manager");
        $clinic_user_ids_str = implode(',', $clinic_user_ids);
        $byinterfacequery = " IF(ba.author_ID in ({$clinic_user_ids_str}), 1, 0) byclinic, ";
        $totalinterfaceqr = " sum(byclinic) as numbers_byclinic, ";
    }


    $child_q = array();
    foreach (tr_pdd_get_batch_types() as $btype) {


        $batch_tbl          = tr_pdd_get_table_by_btype($btype);
        $report_tbl         = tr_pdd_get_table_report_by_btype($btype);
        $where_rejected     = tr_pdd_where_batch_rejected(false);


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

if (isset($_REQUEST['trajax'])) {

    ob_clean();
    header("Content-disposition: attachment; filename=collectors_report.csv");
    header("Content-Type: text/csv");
    $output = fopen("php://output", 'w');
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

    exit;
}

?>
<div class="wrap">
    <h2>Collectors Report</h2>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? $_GET['tab'] : '' ?>" />
        <label>From: </label>
        <input type="text" name="date_from" id="from" value="<?php echo $date_from ?>" />
        <label>To: </label>
        <input type="text" name="date_to" id="to" value="<?php echo $date_to ?>" />

        <input type="submit" value="Run Report" class="button bsty6" />
        <input type="submit" name="trajax" value="Export" class="button" />
    </form>
    <br>

    <table class="report_tbl">
        <thead>
            <tr>
                <th style="width:20px"></th>
                <th>Name</th>
                <th>Lab Employee</th>
                <th>Clinic Name</th>
                <th style="width:10%;line-height:14px">The number of reqs completed</th>
                <?php
                if ($show_by_clinic) {
                    echo '<th style="width:80px">By Clinic</th>';
                } else {
                    echo '<th style="width:80px">By Interface</th>';
                }
                ?>
                <th style="width:80px">Processed</th>
                <th style="width:80px">Rejected</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (count($results) > 0) {
                $totalarr = array('all' => 0, 'interface' => 0, 'proccessed' => 0, 'rejected' => 0);
                foreach ($results as $row) {
                    $collectname = trim($row->col_first . ' ' . $row->col_last);
                    if (empty($collectname)) {
                        $collectname = 'N/A';
                    }

                    $row->proccessed = $row->proccessed - $row->rejected;


                    $totalarr['all']        += $row->numbers;
                    $totalarr['proccessed']  += $row->proccessed;
                    $totalarr['rejected']  += $row->rejected;

                    if ($show_by_clinic) {
                        $row->numbers_interface = $row->numbers_byclinic;
                    }

                    $totalarr['interface']  += $row->numbers_interface;
            ?>
                    <tr class="childp">
                        <td></td>
                        <td><?php echo $collectname ?></td>
                        <td class="center"><?php echo $row->col_employee ? '<span class="checkmark"></span>' : '' ?></td>
                        <td><?php echo $row->requesting_practice ?></td>
                        <td class="center"><?php echo $row->numbers ?></td>
                        <td class="center"><?php echo $row->numbers_interface ?></td>
                        <td class="center"><?php echo $row->proccessed ?></td>
                        <td class="center"><?php echo $row->rejected ?></td>
                    </tr>
                <?php
                }

                ?>
                <tr class="childp">
                    <td colspan="4" style="text-align: right">Total</td>
                    <td class="center"><?php echo $totalarr['all'] ?></td>
                    <td class="center"><?php echo $totalarr['interface'] ?></td>
                    <td class="center"><?php echo $totalarr['proccessed'] ?></td>
                    <td class="center"><?php echo $totalarr['rejected'] ?></td>
                </tr>
            <?php
            }
            ?>


        </tbody>
    </table>

</div>


<script>
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
    })
</script>