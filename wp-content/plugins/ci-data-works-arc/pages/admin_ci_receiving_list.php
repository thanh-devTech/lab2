<?php
global $wpdb;

wp_enqueue_script('jquery-ui', '', array('jquery'));
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.1.9');

$date       = isset($_REQUEST['date']) ? $_REQUEST['date'] : '';
$assen      = isset($_REQUEST['assen']) ? $_REQUEST['assen'] : '';
$month      = isset($_REQUEST['month']) ? $_REQUEST['month'] : '';

$results    = array();
$show_mani              = tr_get_option('ci_pdf_data', 'receiving_mani');
$base_positions         = trpdd_get_receiving_base_positions();

if (!empty($month)) {
    $from_date = date('Y-m-d', strtotime($month . '-01'));
    $to_date = date('Y-m-t', strtotime($from_date));

    $sql = "select re.*,lab.lab_dba_init as lab_name
            from ci_receiving re
            JOIN ci_labs as lab On lab.lab_ID = re.re_lab_ID
            where re.re_type = 0 ";

    $sql .= " and re.re_today >= '{$from_date}'  ";
    $sql .= " and re.re_today <= '{$to_date}'  ";
    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $sql .= " and re_lab_ID in ( {$lab_str} ) ";
    }
    $results = $wpdb->get_results($sql, ARRAY_A);
    $date = '';
    $assen = '';
} else if (!empty($date) || !empty($assen)) {

    $sql = "select re.*,lab.lab_dba_init as lab_name
            from ci_receiving re
            JOIN ci_labs as lab On lab.lab_ID = re.re_lab_ID
            where re.re_type = 0 ";

    if (!empty($date)) {
        $sql .= " and re.re_today='{$date}'  ";
    }

    if (!empty($assen)) {
        $childsql = array();
        foreach (tr_pdd_get_batch_types() as $btype) {
            if (in_array($btype, array('study'))) {
                continue;
            }
            $batch_tbl = tr_pdd_get_table_by_btype($btype);
            $childsql[] = " ( select re_ID from ci_receiving_batches rb
            join {$batch_tbl} ba ON ba.mid = rb.mid
            where assen like '{$assen}%' and rb.btype='{$btype}' ) ";
        }

        $sql_ids = implode(' UNION ALL ', $childsql);
        $sql_ids = "select re_ID from ({$sql_ids}) as allre limit 10";

        $re_ids = $wpdb->get_col($sql_ids);
        $re_ids = implode(',', $re_ids);
        $sql .= " and re.re_ID in ({$re_ids}) ";
    }
    if (CI_Base_Lab::has_user_labs()) {
        $lab_str = CI_Base_Lab::get_user_lab_ids_str();
        $sql .= " and re_lab_ID in ( {$lab_str} ) ";
    }

    if (isset($_GET['debug'])) {
        echo $sql;
    }
    $results = $wpdb->get_results($sql, ARRAY_A);
}

if (isset($_GET['trajax']) && stripos($_GET['trajax'], 'export') !== false) {

    tr_pdd_header('csv',  'Receiving_' . (!empty($date) ? $date : $assen) . '.csv');
    $f = fopen('php://output', 'w');
    $headers = array();
    if (!empty($month)) {
        $headers[] = 'Date';
    }
    $headers[] = 'Tracking Number Barcode';
    $headers[] = 'Lab';
    $headers[] = 'Priority';
    $headers[] = 'Timing';
    $headers[] = 'Practice';
    $headers[] = 'Collector';

    if ($show_mani) {
        $headers[] = 'Manifest';
    }
    foreach ($base_positions as $k => $arr) {
        $name = empty($arr['subtype']) ? $arr['name'] : strtoupper($arr['subtype']);
        $headers[] = 'Qty ' . $name;
    }
    fputcsv($f, $headers);
    foreach ($results as $row) {
        $datarow = array();
        if (!empty($month)) {
            $datarow[] = $row['re_today'];
        }
        $datarow[] = $row['re_tracking_number_barcode'];
        $datarow[] = $row['lab_name'];
        $datarow[] = $priorities[$row['re_priority']];
        $datarow[] = $timings[$row['re_timing']];
        $datarow[] = $row['re_practice'];
        $datarow[] = $row['re_collector'];

        if ($show_mani) {
            $datarow[] = $row['re_manifest'] ? 'Yes' : 'No';
        }
        foreach ($base_positions as $k => $arr) {
            $qty_key = 're_qty_' . $k;
            $datarow[] = $row[$qty_key];
        }
        fputcsv($f, $datarow);
    }
    exit;
}

?>
<br>
<form method="get">
    <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
    <label>Date:</label>
    <input type="search" class="hasdate" autocomplete="off" data-write="1" name="date" value="<?php echo $date ?>" />
    <label>Month:</label>
    <input type="search" class="select_month" id="select_month" name="month" value="<?php echo $month ?>" style="width:110px" />
    <label>Accession</label>
    <input type="search" name="assen" value="<?php echo $assen ?>" placeholder="Search by Accession" />
    <input type="submit" class="button bsty6" value="Search" />
    <input type="submit" class="button " name="trajax" value="Export Pkg" />
</form>
<br>
<div class="wraptbl">
    <table class="wp-list-table widefat striped fixed posts blue_tbl hover_tbl report_tbl" id="rec_tbl_add">
        <thead>
            <tr>
                <?php if (!empty($month)) {
                    echo '<th style="width:120px">Date</th>';
                } ?>
                <th>Tracking Number Barcode</th>
                <th style="width:40px">Lab</th>
                <th style="width:50px">Priority</th>
                <th style="width:50px">Timing</th>
                <th>Practice</th>
                <th style="width:100px">Collector</th>
                <?php
                if ($show_mani) {
                    echo '<th style="width:60px">Manifest</th>';
                }
                foreach ($base_positions as $k => $arr) {
                    $name = empty($arr['subtype']) ? $arr['name'] : strtoupper($arr['subtype']);
                    echo '<th class="smaller" style="width:45px">Qty<br>' . $name . '</th>';
                }
                ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $colcount = count($base_positions) + ($show_mani ? 1 : 0) + 6;
            foreach ($results as $i => $row) {
            ?>
                <tr class="parent_c" data-p="batch_<?php echo $i ?>">
                    <?php if (!empty($month)) {
                        echo '<td>' . $row['re_today'] . '</td>';
                    } ?>
                    <td><?php echo $row['re_tracking_number_barcode'] ?></td>
                    <td><?php echo $row['lab_name'] ?></td>
                    <td><?php echo $priorities[$row['re_priority']] ?></td>
                    <td><?php echo $timings[$row['re_timing']] ?></td>
                    <td><?php echo $row['re_practice'] ?></td>
                    <td><?php echo $row['re_collector'] ?></td>
                    <?php
                    if ($show_mani) {
                        echo '<td class="center">' . ($row['re_manifest'] ? '<span class="checkmark green"></span>' : '<span class="red">X</span>') . '</td>';
                    }
                    foreach ($base_positions as $k => $arr) {
                        $qty_key = 're_qty_' . $k;
                    ?>
                        <td><?php echo $row[$qty_key] ?></td>
                    <?php
                    }
                    ?>
                </tr>
                <tr class="child  p_batch_<?php echo $i ?>" data-id="<?php echo $i ?>">
                    <td class="nopadding" colspan="<?php echo $colcount ?>">
                        <table class="posts report_tbl">
                            <?php

                            $child_sql = array();
                            foreach (tr_pdd_get_batch_types() as $btype) {
                                $batch_tbl = tr_pdd_get_table_by_btype($btype);

                                $child_sql[] = "(select ba.mid,ba.type_of_specimen,ba.initials1,ba.ref_from_lab_ID,ba.position,ba.assen,ba.doc,ba.patient_name,
                                ba.requesting_practice,ba.requesting_physician,ba.panel_tests_requested,ba.culture,ba.dob,ba.priority_hours,
                                rb.btype,rb.created,rb.subtype
                                from {$batch_tbl} ba
                                    JOIN ci_receiving_batches rb ON rb.mid=ba.mid and rb.btype='{$btype}'
                                    where rb.re_ID = '{$row['re_ID']}' ) ";
                            }

                            $child_sql = implode(' UNION ALL ', $child_sql);

                            $sql = "select * from ({$child_sql}) as ball
                                order by ball.position desc";




                            $batches = $wpdb->get_results($sql);
                            foreach ($batches as $batch) {
                                $class = '';
                                if (!empty($assen) && stripos($batch->assen, $assen) !== false) {
                                    $class = 'found_assen';
                                }
                            ?>
                                <tr class="childp assen_<?php echo $batch->assen . ' ' . $class ?>">
                                    <td><?php echo $ii ?><input type="hidden" name="re_mid[<?php echo $id ?>][]" value="<?php echo $batch->mid ?>" /></td>
                                    <td><?php echo $batch->position ?></td>
                                    <td><?php echo $batch->assen ?></td>
                                    <td><?php echo $batch->doc ?></td>
                                    <td><?php echo $batch->patient_name ?></td>
                                    <td><?php echo $batch->requesting_practice ?></td>
                                    <td><?php echo $batch->requesting_physician ?></td>
                                </tr>
                            <?php
                            }
                            ?>
                        </table>
                    </td>
                </tr>
            <?php
            }
            ?>
        </tbody>
    </table>
</div>

<style>
    .report_tbl tbody tr.found_assen.childp td {
        background: #ffffe0 !important;
    }

    .select_month .ui-datepicker-calendar {
        display: none;
    }
</style>
<script>
    jQuery(function($) {
        $('body').on('click', 'tr.parent_c', function(e) {
            if (e.target.tagName != 'TD' && e.target.tagName != 'TR' && e.target.tagName != 'I') return;
            $('tr.child').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('.select_month').each(function() {
            var $t = $(this);

            $t.datepicker({
                dateFormat: 'yy-mm',
                defaultDate: 0,
                changeMonth: true,
                changeYear: true,
                onChangeMonthYear: function(year, month, inst) {
                    if (month < 10) month = '0' + month;
                    $t.val(year + '-' + month);
                },
                beforeShow: function(input, inst) {
                    $('#ui-datepicker-div').removeClass('select_month');
                    $('#ui-datepicker-div').addClass('select_month');
                },
                onClose: function(dateText, inst) {
                    $('#ui-datepicker-div').removeClass('select_month');
                }
            });
            $t.on('click focus', function() {
                var $datevl = $(this).val();
                if ($datevl != '') {
                    $datevl += '-01';
                    console.log($datevl);
                    $t.datetimepicker('setDate', new Date($datevl));
                }
            })


        });
    })
</script>