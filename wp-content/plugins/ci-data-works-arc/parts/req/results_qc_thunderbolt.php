<?php
global $wpdb;
$options = get_option('tr_blood_qc_st');

$orderby        = "DateTime desc";
$where            = " Instrument=-33 ";
$row_per_page    = 10;

if ($act == 'load_table_child' || $act == 'print_qc') {

    $date = $_REQUEST['id'];
    $return = array('status' => 'ok', 'rows' => array());

    $rows = $wpdb->get_results("select qc.*, 
    te.ID as teID,
    te.* 
    from ci_req_qc qc 
    join ci_req_qc_tests te ON te.QC_ID = qc.ID
    where {$where} and qc.DateTime = '{$date}'
     ", ARRAY_A);


    foreach ($rows as $row) {
        $trid = str_replace(array(' ', ':', '-'), '', $row['DateTime']);
        $row['assen'] = strtoupper(trim($row['assen']));
        $row['QCTest'] = isset($options[$row['assen']]) ? $options[$row['assen']] : '';
        ob_start();
?>
        <tr class="trparent_<?php echo $trid ?>" data-id="<?php echo $row['teID'] ?>">
            <td></td>
            <td><?php echo $row['DateTime'] ?></td>
            <td><?php echo $row['assen'] ?></td>
            <td><?php echo $row['QCTest'] ?></td>
            <td><?php echo $row['TestName'] ?></td>
            <td><?php echo $row['TestResult'] ?></td>
            <td><?php echo $row['Units'] ?></td>
            <?php if ($act != 'print_qc') {
                echo '<td><a class="baction button" data-action="delete_test_qc" data-confirm="Do you want delete?">Delete</a></td>';
            }
            ?>
        </tr>
    <?php
        $return['rows'][] = ob_get_clean();
    }

    if ($act == 'print_qc') {
    ?>
        <html>
        <link rel="stylesheet" href="<?php echo TRCPDD_URL . 'css/print.css?' . TRCPDD_VS ?>">
        <style>
            .accordion_tbl td {
                text-align: center;
            }
        </style>

        <body>
            <h2 class="center">QC Centaur <?php echo $date ?></h2>
            <table class="accordion_tbl wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
                <thead class="sticky">
                    <tr>
                        <th></th>
                        <th>Date/Time</th>
                        <th>QC</th>
                        <th>QC Name</th>
                        <th>Sample Type</th>
                        <th>Value</th>
                        <th>Units</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($return['rows'] as $row) {
                        echo $row;
                    }
                    ?>
                </tbody>
            </table>
        </body>

        <script>
            window.print()
        </script>

        </html>
<?php

        exit;
    }

    wp_send_json($return);
    exit;
} else if ($act == 'delete_qc') {
    $return = array('status' => 'ok');
    $date = $_REQUEST['id'];
    $trid = str_replace(array(' ', ':', '-'), '', $date);
    $qcs = $wpdb->get_results("select *
    from ci_req_qc qc 
    where {$where} and qc.DateTime = '{$date}'");

    if (count($qcs) > 0) {
        foreach ($qcs as $qc) {
            $wpdb->delete('ci_req_qc', array('ID' => $qc->ID));
            $wpdb->delete('ci_req_qc_tests', array('qc_ID' => $qc->ID));
            $return['error'] = $wpdb->last_error;
        }

        $return['removeE'] = array();
        $return['removeE'][] = 'tr.parent[data-trid="' . $trid . '"]';
        $return['removeE'][] = 'tr.child.trparent_' . $trid;
    }

    wp_send_json($return);
} else if ($act == 'delete_test_qc') {
    $return = array('status' => 'ok');
    $id = $_REQUEST['id'];
    $trid = str_replace(array(' ', ':', '-'), '', $date);

    $qct = $wpdb->get_row("select *
    from ci_req_qc_tests qc 
    where ID = '{$id}'");

    if ($qct) {
        $wpdb->delete('ci_req_qc_tests', array('ID' => $id));

        $return['removeE'] = array();
        $return['removeE'][] = 'tr.child[data-id="' . $id . '"]';
    }

    wp_send_json($return);
}

$pagination    = new Tr_Pagination_class2($row_per_page);
$offset        = $pagination->offset();
$sql_total        = "select count(DISTINCT(DateTime)) from ci_req_qc qc where {$where}";
$pagination->setTotal($wpdb->get_var($sql_total));
$sql            = "select 
DISTINCT(DateTime) as DateTime
from ci_req_qc qc
where {$where}
order by {$orderby}
limit {$offset},{$row_per_page}";
$rows            = $wpdb->get_results($sql, ARRAY_A);


?>
<div class="wrap">
    <a class="button print_qc_btn">Print QC Barcodes</a>
    <br>
    <div class="tablenav top"><?php if (isset($pagination)) echo $pagination->pagination(array('m')) ?></div>
    <div class="wraptbl">
        <table id="qc_tbl" class="accordion_tbl wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl">
            <thead class="sticky">
                <tr>
                    <th></th>
                    <th>Date/Time</th>
                    <th>QC</th>
                    <th>QC Name</th>
                    <th>Test Name</th>
                    <th>Value</th>
                    <th>Units</th>
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($rows as $row) {
                    $trid = str_replace(array(' ', ':', '-'), '', $row['DateTime']);
                ?>
                    <tr class="parent" data-trid="<?php echo $trid ?>" data-id="<?php echo $row['DateTime'] ?>">
                        <td><i></i></td>
                        <td><?php echo $row['DateTime'] ?></td>
                        <td colspan="6" class="bactions">
                            <a class="baction styp3" data-stext="Printing..." data-action="print_qc" data-type="iframe">Print</a>
                            <a class="baction bsty4" data-action="delete_qc" data-confirm="Do you want delete?">Delete</a>
                        </td>
                    </tr>
                <?php
                }
                ?>
            </tbody>
        </table>
    </div>


    <script src="<?php echo TRCPDD_URL . 'js/admin_accordion.js?v=' . time() ?>"></script>

    <script>
        jQuery(function($) {
            var $options = <?php echo json_encode($options) ?>;
            $('body').on('click', 'a.print_qc_btn', function() {
                var $btn = $(this);
                if ($btn.html() == 'Printing...') {
                    return false;
                }
                $btn.data('oldtext', $btn.html());
                $btn.html('Printing...');

                $timeprint = 1500;
                var a = 1001;
                console.log($options);
                for (var i = 1; i <= 33; i++) {
                    setTimeout(function() {

                        var $barcode = 'ION0000' + a;
                        var $test = $options[$barcode] ? $options[$barcode] : '';
                        console.log($barcode);
                        console.log($test);

                        rs = jQuery.tr_print_barcode($barcode, "QC 2023/08/08", $test + "", "");
                        // console.log(rs);
                        a++;
                    }, $timeprint);
                    $timeprint += 1500;
                }
                console.log($timeprint);
                setTimeout(function() {
                    $btn.html($btn.data('oldtext'));
                }, $timeprint);

                return false;
            });
            $('#qc_tbl').admin_accordion();
        })
    </script>
</div>