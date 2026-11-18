<?php
global $wpdb, $current_role;

$display_chart  = isset($_REQUEST['display']) ? $_REQUEST['display'] : 'line';

$month          = isset($_REQUEST['month']) ? $_REQUEST['month'] : '';
$test           = isset($_REQUEST['testc']) ? $_REQUEST['testc'] : '';
$Instrument     = isset($_REQUEST['Instrument']) ? $_REQUEST['Instrument'] : '';
$lab_report     = isset($_REQUEST['lab']) && !empty($_REQUEST['lab']) ? $_REQUEST['lab'] : '';
$lot            = isset($_REQUEST['lot']) ? $_REQUEST['lot'] : '';
$lots           = array();

$options        = get_option('tr_pcr_qc_st');
$options['key'] = tr_get_option('ci_pdf_data', 'barcode_key');
$qc_barcodes = array();
for ($i = 1001; $i <= 1033; $i++) {
    $qcbarcode = $options['key'] . '0000' . $i;
    if (isset($options[$qcbarcode]) && !empty($options[$qcbarcode])) {
        $qc_barcodes[$qcbarcode] = $options[$qcbarcode];
    }
}
if (empty($qc_barcodes)) {
    $qc_barcodes['NEG'] = 'NEGATIVE CONTROL';
    $qc_barcodes['POS'] = 'POSITIVE CONTROL';
}

$Instrument_types = get_option('trInstruments', array());
$result_settings = get_option('ci_result_settings_pcr', array());
$qct = 'high_low';
$theoretical = array();
$rst = $wpdb->get_results("select t.*,
	                                ti.qc_value as ins_value,
	                                ti.qc_value2 as ins_value2,
	                                ti.Instrument
	                                from ci_theoreticals t
	                                left join ci_theoretical_instruments ti ON t.ID = ti.theo_ID
	                                and (ti.start_date is null or ti.start_date ='0000-00-00' or ti.start_date <= '{$month}-01' )
	                                and (ti.end_date is null or ti.end_date = '0000-00-00' or ti.end_date >= '{$month}-31' )
                                    where qc_specimen = 'pcr'
	                                ");

foreach ($rst as $r) {
    $r->qc_drug = strtoupper($r->qc_drug);


    $theoretical[$r->qc_drug]['default'] = array(
        'low' => $r->qc_value,
        'high' => $r->qc_value2,
    );
    if (!empty($r->ins_value)) {
        $r->instrument = strtoupper($r->instrument);
        $theoretical[$r->qc_drug][$r->instrument] = array(
            'low' => $r->ins_value,
            'high' => $r->ins_value2,
        );
    }
}

if (empty($lab_report)) {
    $current_lab = CI_Base_Lab::get_current_lab();
    $lab_report = $current_lab['lab_dba_init'];
}


$user_id = get_current_user_id();
if (isset($_REQUEST['upload'])) {
    include(trpdd_get_file_path('pcr', 'levey_upload'));
    return;
}
$Instruments = get_option('trinstruments_pcr', array());


if (empty($Instrument)) {
    $Instrument = $Instruments[0];
}


if (!empty($month)) {
    $date_from = $month . '-01';
    $date_to = $month . '-31';
} else {
    $date_from = date_i18n('Y-m-01');
    $date_to = date_i18n('Y-m-31');
    $month = date_i18n('Y-m');
}
$orderby = '';
$where = " and re.Instrument = '{$Instrument}' ";

if (!empty($lot)) {
    $where .= " and re.patient_name like '% {$lot}' ";
}

$where .= " and re.DateTime >= '{$date_from}' and re.DateTime <= '{$date_to}' ";


$sql = "select re.*,rs.*
    from ci_pcr_qc re
    join ci_pcr_qc_tests rs ON re.ID = rs.QC_ID
    where 1 {$where}  {$orderby}";


$results = $wpdb->get_results($sql);

if (!empty($Instrument)) {
    $end_chart_title = $Instrument;
}

if (isset($_REQUEST['debug'])) {
    echo microtime(true) - $time_start, '<br>';
    echo $sql, '<br>';
}





include_once(TRCPDD_PATH . 'pages/formdata.php');

?>
<form method="get" style="margin:10px 5px;">
    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_REQUEST['tab'] ?>" />
    <input type="hidden" name="new" value="1">
    <br>


    <input type="hidden" id="select_month" name="month" value="<?php echo $month ?>" />
    <div class="select_month" style="float:left;margin-right:10px;" data-month="<?php echo $month ?>" data-href="<?php Tr_Base_Class_V6::link(array('month' => '')) ?>"></div>
    <input type="hidden" name="display" value="<?php echo $display_chart ?>" />

    <div style="float: left">
        <select name="Instrument" id="Instrument" required>
            <option value="">Select Machine</option>
            <?php

            //$Instruments['MS-4500'] = 'MS-4500';
            foreach ($Instruments as $in) {
                $selected = $in == $Instrument ? 'selected' : '';
                echo '<option value="' . $in . '" ' . $selected . '>' . $in . '</option>';
            }
            ?>
        </select>

    </div>
    <?php if (count($lots) > 0) { ?>
        <div style="float: left">
            <select name="lot">
                <option value="">All Lot</option>
                <?php foreach ($lots as $in) {
                    if (empty($in) || stripos($in, 'qc') !== false) continue;
                    $selected = $in == $lot ? 'selected' : '';
                    echo '<option value="' . $in . '" ' . $selected . '>' . $in . '</option>';
                }
                ?>
            </select>
        </div>
    <?php } ?>


    <input type="submit" class="button" value="Report" />

    <?php if (count($results) > 0) : ?>
        <input type="button" class="button print_pdf" value="Print" disabled />
    <?php endif; ?>

    <div class="display_bg_line" style="float:right;margin-right:20px;">
        <?php
        $displays = array(
            'bg' => 'BG',
            'line' => 'LINE',
            'grid' => 'Grid'
        );
        foreach ($displays as $k => $vl) {
            $active_cl = $k == $display_chart ? 'active' : '';
            $link = $k == $display_chart ? '' : 'href="' . Tr_Base_Class_V6::link(array('display' => $k), false) . '" ';
            echo '<a class="' . $k . '-view ' . $active_cl . '" ' . $link . '></a>';
        }
        ?>
    </div>
    <div class="clear"></div>
</form>

<?php if (isset($results)) : ?>

    <div style="float:right;width:29%">
        <div class="wraptbl">
            <table class="wp-list-table widefat striped posts blue_tbl hover_tbl report_tbl levey_list">
                <thead>
                    <tr>
                        <th style="width:6%">#</th>
                        <th style="width: 6%;">Date</th>
                        <th style="width: 6%;"></th>
                        <th style="width: 10%;"></th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
                <?php
                $batches = array();
                $chart_data = array();
                $tests = array();
                if (count($results) > 0) {
                    foreach ($results as $i => $row) {

                        $row->TestName = strtoupper($row->TestName);
                        $row->Instrument = strtoupper($row->Instrument);
                        $row->batch_id = $row->DateTime;

                        $date = $row->DateTime;

                        $date = date('Y-m-d', strtotime($date));

                        if (empty($row->TestResult)) $row->TestResult = 0;

                        if (!isset($batches[$row->batch_id]) || !is_array($batches[$row->batch_id])) {
                            $batches[$row->batch_id] = array();
                        }
                        $batches[$row->batch_id]['date'] = $date;

                        $k = 'TestResult';

                        $batches[$row->batch_id]['tests'][$row->TestName][$k] = array($row->ID, $row->TestResult);
                        $datetime = strtotime($date);

                        $labs[$row->TestName][$k][$datetime] = array(
                            'TestResult' => $row->TestResult,
                            'Instrument' => $row->Instrument
                        );

                        $tests[$row->TestName][$k][] = array(
                            'id' => $row->ID,
                            'TestName' => $row->TestName,
                            'old' => $row->TestResult_number,
                            'new' => $row->TestResult,
                            'date' => $date,
                            'comments' => $row->comments,
                            'Instrument' => $row->Instrument
                        );
                    }


                    $chart_data = array(
                        'width' => '860',
                        'height' => '350'
                    );
                    foreach ($labs as $test => $rows) {

                        $dates = array();
                        $TestResults = array();


                        $rows['TestResult'] = isset($rows['TestResult']) ? $rows['TestResult'] : array();
                        ksort($rows['TestResult']);
                        $h_ins = '';
                        foreach ($rows['TestResult'] as $created => $vl) {
                            $date = date('Y-m-d', $created);
                            $TestResults[$date] = $vl['TestResult'];

                            if (empty($h_ins) && $lab_report != 'CTL') {
                                $h_ins = $vl['Instrument'];
                            }
                        }

                        if (count($TestResults) > 0) {
                            $h_ins_title = empty($h_ins) ? $end_chart_title : $h_ins;
                            $h_ins_title = strtoupper($h_ins_title);

                            if (!empty($h_ins_title)) {
                                $h_ins_title = ' - ' . $h_ins_title;
                            }
                            $k = sanitize_title('TestResult' . $lab_report . $test);
                            $avgs = array();
                            if (isset($theoretical[$test][$h_ins])) {
                                $avgs = $theoretical[$test][$h_ins];
                            } else {
                                $avgs = $theoretical[$test]['default'];
                            }
                            $avgs['high'] = floatval($avgs['high']);
                            $avgs['low'] = floatval($avgs['low']);
                            $center = $avgs['high'] + ($avgs['low'] - $avgs['high']) / 2;
                            $chart_data['charts'][$k] = array(
                                'name' =>  $test . $h_ins_title,
                                'label' => 'CT. ng/ml',
                                'color' => 'red',
                                'color2' => 'yellowgreen',
                                'value' => $TestResults,
                                'center' => $center,
                                'high_cutoff' => $avgs['high'],
                                'low_cutoff' => $avgs['low'],
                                'charttype' => 'high_low'
                            );
                        }
                    }
                ?>

                    <?php
                    foreach ($batches as $batch_id => $d) {
                    ?>
                        <tr class="parent" data-p="batch_<?php echo $batch_id ?>">
                            <td><i></i></td>
                            <td><?php echo $d['date']  ?></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class="child p_batch_<?php echo $batch_id ?>">
                            <td colspan="4" class="nopadding">
                                <table class="report_tbl">
                                    <tr class="childp">
                                        <td><b>TEST</b></td>
                                        <td><b>CT</b></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <?php
                                    $count = 0;
                                    foreach ($d['tests'] as $TestName => $rows) {
                                        $count++;
                                    ?>
                                        <tr class="childp">
                                            <td><?php echo $TestName ?></td>
                                            <td class="editable" data-id="<?php echo $rows['TestResult'][0] ?>"><?php echo $rows['TestResult'][1] ?></td>
                                            <td><a class="undo" title="Undo Graphic"> </a></td>
                                            <td><a class="delete" title="Delete Graphic"></a></td>
                                        </tr>
                                    <?php
                                    }
                                    ?>
                                </table>
                            </td>
                        </tr>
                    <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="12">No Results Found.</td>
                    </tr>
                <?php
                }
                ?>
            </table>
        </div>

    </div>

    <div style="float:left;width:70%;">

        <?php
        if ($display_chart == 'grid') {
        ?>
            <table class="wp-list-table widefat striped posts blue_tbl hover_tbl levey_list">
                <thead>
                    <tr>
                        <th style="width:20%">Test</th>
                        <th style="width:12%">Old Value</th>
                        <th style="width:12%">New Value</th>
                        <th style="width:12%">Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $kp = $qct == 'standard' ? '1P' : '1P';

                    foreach ($tests as $TestName => $rows) {
                        foreach ($rows as $hl => $t_rows) {

                            $values = array();
                            foreach ($t_rows as $row) {
                                $values[] = $row['new'];
                            }

                            foreach ($t_rows as $row) {

                                if ($row['new'] < $a1 && $row['new'] > $b1) {
                                    continue;
                                }
                    ?>
                                <tr class="tt_<?php echo $hl ?>">
                                    <td><?php echo $row['TestName'] ?></td>
                                    <td><?php echo $row['old'] ?></td>
                                    <td><?php echo $row['new'] ?></td>
                                    <td><?php echo $row['date'] ?></td>
                                    <td class="comment_edit" data-id="<?php echo $row['id'] ?>"><?php echo $row['comments'] ?></td>
                                </tr>
                        <?php
                            }
                        }
                        ?>
                        <tr>
                            <td colspan="5">&nbsp;</td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
            <style>
                tr.tt_low td:first-child {
                    border-left: 2px solid rgba(255, 147, 40, 0.5);
                }

                tr.tt_medium td:first-child {
                    border-left: 2px solid rgba(255, 147, 40, 0.5);
                }

                tr.tt_high td:first-child {
                    border-left: 2px solid rgba(200, 0, 0, 0.5);
                }
            </style>
        <?php
        } else {
            include(TRCPDD_PATH . 'pages/chartjs.php');
        }
        ?>

    </div>
<?php endif; ?>


<script>
    jQuery(function($) {
        $('#select_lab2').change(function() {

            $('#Instrument').hide();
            $('#Instrument option').hide();
            try {
                var $ins = $(this).find('option[value="' + $(this).val() + '"]').data('in');
                $ins = $ins.split(',');
                $('#Instrument option[value=""]').show();
                for ($i in $ins) {
                    if ($ins[$i] != '') {
                        $('#Instrument').show();
                    }

                    $('#Instrument').val('');
                    $('#Instrument option[value="' + $ins[$i] + '"]').show();
                }
            } catch (e) {}

        }).change();
        <?php
        if (!empty($Instrument)) {
            echo '$(\'#Instrument\').val(\'' . $Instrument . '\');';
        }
        ?>
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
        $('tr.parent').click(function() {
            $('tr.child').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_rs', $(this).data('p'));
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('tr.parent_c').click(function() {
            $('tr.child_c').hide();
            if ($(this).data('showed') != '1') {
                $('tr.p_' + $(this).data('p')).show();
                $('tr.parent_c').data('showed', '0');
                $(this).data('showed', '1');
                $(this).addClass('active');
                setCookie('active_rs', $(this).data('p'));
                var $obj = $('tr.p_' + $(this).data('p'));
                $('body,html').scrollTop($obj.offset().top - 150);
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
            } else {
                $('tr.p_' + $(this).data('p')).hide();
                $(this).data('showed', '0');
                $(this).removeClass('active');
            }
            return false;
        });
        $('.editable').click(function() {
            if ($(this).hasClass('editing')) {
                return false;
            }

            $(this).data('vl', $(this).html()).addClass('editing');
            $(this).html('<input type="text" value="' + $(this).html() + '"/>');
        });
        $('body').on('keypress', '.editable input', function(e) {
            var code = e.keyCode || e.which;
            if (code == 13) {
                //save data
                var $pr = $(this).parent();
                $value = $(this).val();
                $id = $pr.data('id');
                $pr.append('<div class="sloading"></div>');
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        tr_action: 'ci_save_qc_value',
                        id: $id,
                        value: $value
                    },
                    success: function(rs) {
                        $pr.html($value);
                        $pr.removeClass('editing');
                        //redraw chart
                        if (rs.charttype == 'bg') {
                            setup_chat_bg(rs.chart.id, rs.chart);
                        } else {
                            setup_chat_line(rs.chart.id, rs.chart);
                        }

                    }
                })
            }
        });
        $('.comment_edit').click(function() {
            if ($(this).hasClass('editing')) {
                return false;
            }

            $(this).data('vl', $(this).html()).addClass('editing');
            $(this).html('<textarea>' + $(this).html() + '</textarea><input type="button" class="button-primary save" value="Save"/> <input type="button" class="button cancel" value="Cancel"/>');
        });
        $('.comment_edit').on('click', '.save', function() {
            var $pr = $(this).parent();
            $value = $('textarea', $pr).val();
            $id = $pr.data('id');
            $pr.append('<div class="sloading"></div>');
            $.ajax({
                type: 'POST',
                dataType: 'json',
                data: {
                    tr_action: 'ci_save_qc_comments',
                    id: $id,
                    value: $value
                },
                success: function(rs) {
                    $pr.html($value);
                    $pr.removeClass('editing');
                }
            });
            return false;
        }).on('click', '.cancel', function() {
            var $pr = $(this).parents('td');
            $value = $('textarea', $pr).val();
            $pr.html($value);
            $pr.removeClass('editing');
            return false;
        });
        $('body').on('click', '.report_tbl .undo', function() {
            var $this = $(this);
            var $tp = $(this).parent();
            var $pr = $(this).parents('.childp');
            var $ids = '';
            $('.editable', $pr).each(function() {
                $ids += $(this).data('id') + ',';
            })
            $('.sloading', $tp).remove();
            $tp.append('<div class="sloading"></div>');
            $.ajax({
                type: 'POST',
                dataType: 'json',
                data: {
                    tr_action: 'ci_save_qc_undo_value',
                    ids: $ids
                },
                success: function(rs) {
                    $('.sloading', $tp).remove();
                    //redraw chart
                    for ($i in rs.charts) {
                        var $chart = rs.charts[$i];
                        if (rs.charttype == 'bg') {
                            setup_chat_bg($chart.id, $chart);
                        } else {
                            setup_chat_line($chart.id, $chart);
                        }
                        var $rsid = $chart.rsid;
                        $('.editable[data-id="' + $rsid + '"]').html($chart.rsvl);
                    }


                }
            })

        })
        $('body').on('click', '.report_tbl .delete', function() {
            if (confirm("Do you want to delete the point?") == false) {
                return false;
            }
            var $this = $(this);
            var $tp = $(this).parent();
            var $pr = $(this).parents('.childp');
            var $ids = '';
            $('.editable', $pr).each(function() {
                $ids += $(this).data('id') + ',';
            })
            $('.sloading', $tp).remove();
            $tp.append('<div class="sloading"></div>');
            $.ajax({
                type: 'POST',
                dataType: 'json',
                data: {
                    tr_action: 'ci_save_qc_delete_value',
                    ids: $ids
                },
                success: function(rs) {
                    $this.parents('tr.childp').remove();
                    //redraw chart
                    for ($i in rs.charts) {
                        var $chart = rs.charts[$i];
                        if (rs.charttype == 'bg') {
                            setup_chat_bg($chart.id, $chart);
                        } else {
                            setup_chat_line($chart.id, $chart);
                        }

                    }

                }
            })

        })

        $old_active = getCookie('active_rs');
        if ($old_active && $old_active != null) {
            var $obj = $('tr.p_' + $old_active);
            try {
                $obj.show();
                $('[data-p="' + $old_active + '"]').data('showed', '1');
                if ($old_active.indexOf('lab_') > -1) {
                    $obj.parents('.child').show();
                }
                $batch_id = $obj.data('batch_id');
                $lab_id = $obj.data('lab_id');
            } catch (e) {}
        }

        $('.print_pdf').click(function() {
            tb_show("Creating PDF", "#TB_inline?height=200&amp;width=300&amp;inlineId=show_print_pdf");
            var $form = $('#form_post_images');
            $form.html('<input type="hidden" name="tr_action" value="ci_print_qc_pdf">');
            $form.append('<input type="hidden" name="lab_id" value="<?php echo $lab_db->lab_ID ?>">');

            $('.chart_pic').each(function() {
                var id_chart = $(this).attr('id');
                var cv = document.getElementById(id_chart);
                var canvasData = cv.toDataURL("image/png");
                var $title = $('#' + id_chart + '_title').html();
                $form.append('<input type="hidden" name="images[' + id_chart + ']" value="' + canvasData + '"/>');
                $form.append('<input type="hidden" name="titles[' + id_chart + ']" value="' + $title + '"/>');
            });

            $form.submit();
            $('#TB_ajaxContent').append('<div class="loading">Loading...</div>');
            $('#post_images').on('load', function() {
                tb_remove();
            })
            setTimeout(function() {
                tb_remove();
            }, 40000);
        })
        $('.print_pdf2').click(function() {
            //var $form = $('#form_post_images');
            //$form.html('');

            var previewFrame = document.getElementById('post_images');
            var preview = previewFrame.contentDocument || previewFrame.contentWindow.document;
            preview.open();
            preview.write("<style>.w50{float:left;width:50%;height:800px}.rotate img{width:600px;height:auto}.rotate{-webkit-transform: rotate(-90deg);-moz-transform: rotate(-90deg);-ms-transform: rotate(-90deg);-o-transform: rotate(-90deg);position: relative;top:40%}h3{width:600px;text-align: center;}</style>")

            var $i = 0;
            $('.chart_pic').each(function() {
                var id_chart = $(this).attr('id');

                var cv = document.getElementById(id_chart);
                var canvasData = cv.toDataURL("image/png");
                //$form.append('<input type="hidden" name="images['+id_chart+']" value="'+canvasData+'"/>');
                var $title = $('#' + id_chart + '_title').html();
                preview.write('<div class="w50"><div class="rotate"><h3>' + $title + '</h3><img src="' + canvasData + '"></div> </div>');
                $i++;
            });
            preview.write('<scr' + 'ipt>window.print()</scr' + 'ipt>');
            preview.close();

            //$form.submit();
        })
    })
</script>
<div style="display:none" id="show_print_pdf">Please Wating</div>
<form id="form_post_images" method="post" target="post_images"></form>
<iframe id="post_images" name="post_images" style="width:0;height:0;display: none;"></iframe>
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
</style>