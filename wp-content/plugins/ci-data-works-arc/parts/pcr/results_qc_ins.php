<?php
global $wpdb;

$options    = get_option('tr_pcr_qc_st');
$options['key'] = tr_get_option('ci_pdf_data', 'barcode_key');
$today      = date_i18n('Y/m/d');
$orderby        = "DateTime desc";
$where           = " Instrument='{$instrument}' ";
$row_per_page    = 10;

$qc_barcodes = array();
for ($i = 1001; $i <= 1033; $i++) {
    $qcbarcode = $options['key']  . '0000' . $i;
    if (isset($options[$qcbarcode]) && !empty($options[$qcbarcode])) {
        $qc_barcodes[$qcbarcode] = $options[$qcbarcode];
    }
}
if (empty($qc_barcodes)) {
    $qc_barcodes['NEG'] = 'NEGATIVE CONTROL';
    $qc_barcodes['POS'] = 'POSITIVE CONTROL';
}

if ($act == 'load_table_child' || $act == 'print_qc') {

    $date = $_REQUEST['id'];
    $return = array('status' => 'ok', 'rows' => array());

    $rows = $wpdb->get_results("select qc.*, te.* 
    from ci_pcr_qc qc 
    join ci_pcr_qc_tests te ON te.QC_ID = qc.ID
    where {$where} and qc.DateTime = '{$date}'
     ", ARRAY_A);


    foreach ($rows as $row) {
        $row['assen'] = strtoupper(trim($row['assen']));
        $row['QCTest'] = isset($qc_barcodes[$row['assen']]) ? $qc_barcodes[$row['assen']] : '';
        ob_start();
?>
        <tr>
            <td></td>
            <td><?php echo $row['DateTime'] ?></td>
            <td><?php echo $row['assen'] ?></td>
            <td title="<?php echo $row['PatientID'] ?>"><?php echo $row['QCTest'] ?></td>
            <td><?php echo $row['TestName'] ?></td>
            <td><?php echo $row['TestResult'] ?></td>
            <td><?php echo $row['Units'] ?></td>
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
}

$pagination    = new Tr_Pagination_class2($row_per_page);
$offset        = $pagination->offset();
$sql_total        = "select count(DISTINCT(DateTime)) from ci_pcr_qc qc where {$where}";
$pagination->setTotal($wpdb->get_var($sql_total));
$sql            = "select 
DISTINCT(DateTime) as DateTime
from ci_pcr_qc qc
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
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($rows as $row) {
                ?>
                    <tr class="parent" data-id="<?php echo $row['DateTime'] ?>">
                        <td><i></i></td>
                        <td><?php echo $row['DateTime'] ?></td>
                        <td colspan="5" class="bactions">
                            <a class="baction styp3" data-stext="Printing..." data-action="print_qc" data-type="iframe">Print</a>
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
            var $printmax = 1033;
            var $printstart = 1001;
            var $today = '<?php echo $today ?>';

            function prinbarcode(a) {
                var $barcode = $options['key'] + '0000' + a;
                var $test = $options[$barcode] ? $options[$barcode] : '';
                console.log($barcode);
                console.log($test);
                var $timeout = 1500;
                if ($test != '') {
                    rs = jQuery.tr_print_barcode($barcode, "QC " + $today, $test + "", "");
                } else {
                    rs = true;
                    $timeout = 100;
                }

                a++;
                if (rs && a <= $printmax) {
                    setTimeout(function() {
                        prinbarcode(a);
                    }, $timeout);
                } else {
                    $btn = $('a.print_qc_btn');
                    $btn.html($btn.data('oldtext'));
                }
                console.log(rs);
            }
            $('body').on('click', 'a.print_qc_btn', function() {
                var $btn = $(this);
                if ($btn.html() == 'Printing...') {
                    return false;
                }
                $btn.data('oldtext', $btn.html());
                $btn.html('Printing...');

                if (typeof jQuery.ensure_printer === 'function') {
                    jQuery.ensure_printer(function() {
                        prinbarcode($printstart);
                    }, function() {
                        alert('Please connect printer!');
                        $btn.html($btn.data('oldtext'));
                    });
                } else {
                    jQuery.setup_printer();
                    setTimeout(function() {
                        prinbarcode($printstart);
                    }, 1500);
                }
                console.log($options);

                return false;
            });
            $('#qc_tbl').admin_accordion();
        })
    </script>
</div>
