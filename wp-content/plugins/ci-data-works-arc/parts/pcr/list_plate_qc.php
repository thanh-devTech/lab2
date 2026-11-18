<?php
global $wpdb;
$id = $_REQUEST['rfid'];
$bid = isset($_REQUEST['bid']) ? $_REQUEST['bid'] : '';
$assen_include = array();
if (!empty($bid)) {
    $assen_include = $wpdb->get_col("select assen from ci_batches_pcr 
    where batch_id = '{$bid}' ");
}

if (isset($_REQUEST['trajax']) && $_REQUEST['trajax'] == 'ci_save_plate_qc_cell') {
    $return     = array('status' => 'ok');
    
    $rowkey     = $_REQUEST['rowkey'];
    $field      = $_REQUEST['field'];
    $value      = $_REQUEST['value'];
    list($assen, $target, $wellpos) = explode('|', $rowkey);
    $file       = $wpdb->get_row("select * from ci_reports_files where rf_ID = {$id} ");


    if ($file) {

        $meta_data     = json_decode($file->rf_meta_data, true);

        $row_t_num = 1;
        $current_key = '';

        foreach ($meta_data['rows'] as $i => $row) {


            $checkkey = $row['samplename'] . $row['targetname'];
            if($current_key!=$checkkey){
                $current_key = $checkkey;
                $row_t_num = 1;
            }else{
                $row_t_num++;
            }


            if ($row['wellposition'] == $wellpos && $target==$row['targetname'] && $assen == $row['samplename']) {
                $row[$field] = $value;

                //update rs
                if (in_array($field, array('crt', 'ampscore', 'cqconf'))) {
                    $drugs = array($row['targetname'], $row['test_name']);
                    if ($row_t_num>1) {
                        $drugs = array($row['targetname'] . '-'.$row_t_num, $row['test_name'] . '-'.$row_t_num);
                    }

                    $drugs_str = implode("','", $drugs);

                    $rs_id = $wpdb->get_var("select rs.id from ci_results_pcr rs
                    join ci_reports_pcr re ON re.id = rs.report_id
                    where re.patient_id = '{$assen}'
                    and rs.drug in ('{$drugs_str}')
                    ");

                    $return['$rs_id'] = $rs_id;
                    if ($rs_id > 0) {
                        $rs_field = 'conc';
                        if ($field == 'ampscore') {
                            $rs_field = 'amp_score';
                        } else if ($field == 'cqconf') {
                            $rs_field = 'cq';
                        }
                        $rsu = $wpdb->update('ci_results_pcr', array(
                            $rs_field => $value
                        ), array(
                            'id' => $rs_id,
                        ));
                        $return['rs'] = $rsu;
                        $return['$rs_field'] = $rs_field;
                        $return['q'] = $wpdb->last_query;
                    }
                }
            }
            $meta_data['rows'][$i] = $row;
        }

        $wpdb->update('ci_reports_files', array('rf_meta_data' => json_encode($meta_data)), array('rf_ID' => $id));
    }
    wp_send_json($return);
}

$data           = trpdd_pcr_build_WH_plate_pos($id, isset($_REQUEST['recalculate']));
$columns_3_rows = array('AS', 'BD', 'BF', 'AD', 'BH');
$editable_columns = array('crt', 'ampscore', 'cqconf', 'crtsd', 'crtmean', 'ampstatus', 'highsd', 'roxsignal');
$assens         = array();
$targets         = array();

foreach ($data['rows'] as $row) {
    $assen = $row['samplename'];
    $target = $row['test_name'];

    if (count($assen_include) > 0 && !in_array($assen, $assen_include)) {
        continue;
    }
    if (!empty($assen)) {
        $assens[$assen] = $assen;
    }

    if (!empty($target)) {
        $targets[$target] = $target;
    }
}
?>
<div class="wrap_thickbox">
    <div class="contant_thickbox">
        <div class="second_thickbox">
            <div id="donutchart" style="width: 700px; height: 500px;"></div>
        </div>
        <div class="main_thickbox">
            <div class="searchbox">
                <label>Accession</label>
                <select class="auto_filter_qc_assen">
                    <?php
                    echo '<option value="">Select</option>';
                    foreach ($assens as $assen) {
                        echo '<option value="' . $assen . '">' . $assen . '</option>';
                    }
                    ?>
                </select>
                &nbsp;&nbsp;
                <label>Target Name</label>
                <select class="auto_filter_qc_target">
                    <?php
                    echo '<option value="">Select</option>';
                    foreach ($targets as $target) {
                        echo '<option value="' . $target . '">' . $target . '</option>';
                    }
                    ?>
                </select>

                &nbsp;&nbsp;
                <a class="button recalculatebtn" href="<?php Tr_Base_Class_V6::Link(array('recalculate' => 1)) ?>">ReCalculate</a>
            </div>
            <div class="wraptbl">
                <table class="report_tbl posts plate_qc" style="min-width:1400px">
                    <thead class="sticky">
                        <tr>
                            <?php
                            foreach ($data['headers'] as $k => $txt) {
                                echo '<th>' . ucfirst($txt) . '</th>';
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $chart_all_data = array();
                        $in_array = array();
                        $start_3rows = array();
                        foreach ($data['rows'] as $row) {
                            $assen = $row['samplename'];
                            $target = $row['targetname'];
                            $testname = $row['test_name'];

                            if (count($assen_include) > 0 && !in_array($assen, $assen_include)) {
                                continue;
                            }

                            if ($row['replicatecall'] == 'DETECTED' || $row['finalcall'] == 'DETECTED') {
                                $row['class'] .= ' has_detected';
                            }
                            $rowkey = $assen . '|' . $target . '|' . $row['wellposition'];
                            echo '<tr data-rowkey="' . $rowkey . '" class="editrow childp ' . $row['class'] . '">';

                            foreach ($data['headers'] as $k => $txt) {
                                $need3rows  = in_array($k, $columns_3_rows);
                                $rowspan    = 0;
                                if ($need3rows) {

                                    if (!$start_3rows[$assen . $target . $k]) {
                                        $rowspan = $testname == '16S' ? 5 : 3;
                                        if (isset($row['rowcount']) && $row['rowcount'] > 0) {
                                            $rowspan = $row['rowcount'];
                                        }
                                        $start_3rows[$assen . $target . $k] = true;
                                    } else {
                                        continue;
                                    }
                                }
                                if ($rowspan > 1) {
                                    $rowspan = ' rowspan = "' . $rowspan . '" ';
                                }
                                $class = 'f_' . str_replace(' ', '', $k) . ' vl_' . str_replace(' ', '', strtolower($row[$k])) . ' ';
                                if (in_array($k, $editable_columns)) {
                                    $class .= ' editable ';
                                }
                                echo '<td ' . $rowspan . ' data-field="' . $k . '" class=" ' . $class . '">';
                                $aftermtext = '';
                                if ($k == 'samplename') {
                                    echo '<a class="showchart" href="#" data-assen="' . $row['samplename'] . '">';
                                    $aftermtext = '</a>';
                                }
                                echo '<span>' . $row[$k] . '</span>';
                                if ($k == 'targetname') {
                                    echo '<br><small>' . $testname . '</small>';
                                }
                                echo $aftermtext;
                                echo '</td>';
                            }
                            echo '</tr>';



                            if (!empty($row['AS'])) {
                                $in_array[] = $row['test_name'];
                                $row['AS'] = str_replace('%', '', $row['AS']);
                                $row['AS'] = floatval($row['AS']);

                                $chart_all_data[$row['samplename']][$row['test_name']] = $row['AS'];
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<style>
    .plate_qc th,
    .plate_qc td {
        border: 1px solid #ddd;
        text-align: center;
    }

    .plate_qc td.vl_detected span {
        color: red !important;
        font-weight: bold;
        font-size: 12px;
    }

    .plate_qc td.vl_notdetected span,
    .plate_qc td.vl_undetermined span,
    .plate_qc td.vl_inconclusive span {
        font-size: 10px;
    }

    body .plate_qc tr.bacteria td {
        background: #f8f3f2 !important;
    }

    body .plate_qc tr.ba_lactobacillus td {
        background: #e5faef !important;
    }

    body .plate_qc tr.has_detected td.f_crt,
    body .plate_qc tr.has_detected td.f_crtmean,
    body .plate_qc tr.has_detected td.f_ampscore,
    body .plate_qc tr.has_detected td.f_cqconf {
        background: #abf0ab !important;
    }

    .wrap_thickbox.show_second {
        *overflow-x: hidden;
    }

    .second_thickbox {
        display: none;
    }

    .show_second .second_thickbox {
        display: block;
        float: left;
        width: 700px;
        height: 700px;
        z-index: 2;
        position: relative;
    }

    .show_second .main_thickbox {
        float: left;
        max-width: 1400px;
    }

    .show_second .second_thickbox>div {
        position: fixed;
    }

    .show_second .contant_thickbox {
        width: 3000px;
    }

    .canvasjs-chart-credit {
        display: none !important;
    }

    td.editable input[type="text"] {
        width: 150px;
    }
</style>

<?php
echo '<script src="' . TRCPDD_URL . 'js/canvasjs.min.js"></script>';
?>


<script>
    var $tt = false;
    var $current_assen = '';
    var $chart_all_data = <?php echo json_encode($chart_all_data) ?>;


    function build_chart() {
        var $data = [];
        for (i in $chart_all_data[$current_assen]) {
            $data.push({
                y: $chart_all_data[$current_assen][i],
                label: i
            });
        }

        var chart = new CanvasJS.Chart("donutchart", {
            animationEnabled: true,
            title: {
                text: $current_assen
            },
            borderWidth: 5,
            data: [{
                type: "doughnut",
                startAngle: 240,
                yValueFormatString: "##0.00\"%\"",
                indexLabel: "{label} {y}",
                dataPoints: $data
            }]
        });
        chart.render();
    }


    jQuery(function($) {

        function tr_filter() {
            var assen = $('.auto_filter_qc_assen').val().toLowerCase();
            var target = $('.auto_filter_qc_target').val().toLowerCase();
            if (assen != '' || target != '') {

                $('.plate_qc tbody tr').each(function() {
                    var $tdassen = $('td.f_samplename', $(this)).html().toLowerCase();
                    var $tdtarget = $('td.f_targetname', $(this)).html().toLowerCase();
                    if ((assen == '' || $tdassen.search(assen) > -1) && (target == '' || $tdtarget.indexOf(target) > -1)) {
                        $(this).show();

                    } else {
                        $(this).hide();
                    }
                })
                if (assen != '' && $('.wrap_thickbox').hasClass('show_second')) {
                    $current_assen = $('.auto_filter_qc_assen').val();
                    build_chart();
                }
            } else {
                $('.plate_qc tbody tr').show();
            }
        }

        $('.auto_filter_qc_assen, .auto_filter_qc_target').change(function() {
            tr_filter();
        })
        $('.showchart').on('click', function() {
            console.log('show chart');
            $current_assen = $(this).data('assen');
            $('.wrap_thickbox').addClass('show_second');
            $('.main_thickbox .actions_thickbox').remove();
            $('.main_thickbox').prepend('<div class="actions_thickbox"><a class="aback dashicons dashicons-arrow-left-alt button">Back</a></div>');

            build_chart();

            return false;
        })
        $('a.aback').on('click', function() {
            $('.wrap_thickbox').removeClass('show_second');
            $('.main_thickbox .actions_thickbox').remove();

        });
        $('.plate_qc td.editable').on('click', function() {
            if ($(this).hasClass('editing')) {
                return false;
            }
            var $vl = $('span', $(this)).html();

            $(this).data('vl', $vl).addClass('editing');
            $('span', $(this)).html('<input type="text" value="' + $vl + '"/>');
        })
        $('.plate_qc').on('keypress', '.editable input', function(e) {
            var code = e.keyCode || e.which;
            if (code == 13) {
                //save data
                var $tr = $(this).parents('tr.editrow');
                var $span = $(this).parent();
                var $td = $span.parent();
                var $field = $td.data('field');
                $value = $(this).val();
                $rowkey = $tr.data('rowkey');
                $span.append('<div class="sloading"></div>');
                $.ajax({
                    url: '<?php Tr_Base_Class_V6::link(array()) ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        trajax: 'ci_save_plate_qc_cell',
                        rowkey: $rowkey,
                        value: $value,
                        field: $field
                    },
                    success: function(rs) {
                        $span.html($value);
                        $td.removeClass('editing');
                    }
                })
            }
        })
        $('.recalculatebtn').click(function() {
            tb_remove();
            var $url = $(this).attr('href');
            setTimeout(function() {
                tb_show('', $url);
            }, 500);
            return false;
        })

    })
</script>