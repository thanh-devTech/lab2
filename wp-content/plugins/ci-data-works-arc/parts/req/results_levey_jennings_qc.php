<?php
global $wpdb, $current_role;

wp_enqueue_script('jquery-ui');
wp_enqueue_script('jquery-ui-dialog');
wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), TRCPDD_VS);

$display_chart  = isset($_REQUEST['display']) ? $_REQUEST['display'] : 'line';

$month          = isset($_REQUEST['month']) ? $_REQUEST['month'] : '';
$test           = isset($_REQUEST['testc']) ? $_REQUEST['testc'] : '';
$instrument     = isset($_REQUEST['instrument']) ? $_REQUEST['instrument'] : '';
$lab_report     = isset($_REQUEST['lab']) && !empty($_REQUEST['lab']) ? $_REQUEST['lab'] : '';
$lot            = isset($_REQUEST['lot']) ? $_REQUEST['lot'] : '';
$show2          = isset($_REQUEST['show2']) ? 1 : '';
$qct            = !empty($_GET['qct']) ? $_GET['qct'] : 'standard';

$qct_setting    = get_option('tr_lj_lcms_qct', 'standard');

if ($qct_setting != 'both') {
    $qct = $qct_setting;
}
$instrument_types = get_option('trinstruments_req', array());
$result_settings = get_option('ci_result_settings_req', array());


if (empty($lab_report)) {
    $current_lab = CI_Base_Lab::get_current_lab();
    $lab_report = $current_lab['lab_dba_init'];
}
$lab_report = '';


$user_id = get_current_user_id();
if (isset($_REQUEST['upload'])) {
    include(trpdd_get_file_path('req', 'levey_upload'));
    return;
}
$instruments = array('Thunderbolt');
$instruments_cache = get_option('tr_levey_jennings_instruments', array());


if (empty($instruments_cache) || empty($instruments_cache['instruments']) || $instruments_cache['time'] < time() - 86400 || isset($_GET['clearcache'])) {

    $news = $wpdb->get_col("select instrument from `ci_req_qc` re 
	                    
	                    group by instrument ");

    foreach ($news as $ins) {
        if (!empty($ins) && !in_array($ins, $instruments)) {
            if ($ins == -33) {
                $ins = 'Thunderbolt';
            }
            $instruments[] = $ins;
        }
    }
    update_option('tr_levey_jennings_instruments', array(
        'instruments' => $instruments,
        'time'        => time(),
    ), false);
} else {
    $instruments = $instruments_cache['instruments'];
    foreach ($instruments as $i => $ins) {
        if ($ins == -33) {
            unset($instruments[$i]);
        }
    }
}

$instruments = array_unique($instruments);

$Average_arr = array();

if (empty($instrument)) {
    //$instrument = $instruments[0];
}
$where = $where_qc = " ";


$orderby = ' order by re.DateTime desc,rs.TestName asc';
if ($display_chart == 'grid') {
    $orderby .= ", rs.TestResult asc ";
}
$order = isset($_REQUEST['order']) && $_REQUEST['order'] == 'desc' ? 'desc' : 'asc';


$instrumentnum = 0;
if ($instrument == 'Thunderbolt') {
    $instrumentnum = -33;
}
$where .= " and re.Instrument = '{$instrumentnum}' ";

if (!empty($month)) {
    $date_from = $month . '-01';
    $date_to = $month . '-31';
} else {
    $date_from = date_i18n('Y-m-01');
    $date_to = date_i18n('Y-m-31');
    $month = date_i18n('Y-m');
}

$where .= " and re.DateTime >= '{$date_from}' and re.DateTime <= '{$date_to} 23:59:59' ";
$where .= " and  (assen like 'qch%' or assen like 'qcl%' ) ";

$drugs = $wpdb->get_col("select te_name from ci_test_panels where te_type in ('blood') ");
$drugs_str = implode("','", $drugs);


$sql = "select re.*,
    rs.TestName as drug, 
    rs.TestResult as conc,
    rs.TestResult_number as conc_number,
    rs.comments,
    rs.ID as rs_id,
    re.DateTime as DateTime
    from ci_req_qc re
    join ci_req_qc_tests rs ON re.ID = rs.QC_ID AND rs.TestName in ('{$drugs_str}')
    where 1 {$where}  
    {$orderby}";



$results = $wpdb->get_results($sql);

if (!empty($instrument)) {
    $end_chart_title = $instrument;
}

if (isset($_REQUEST['debug'])) {
    echo microtime(true) - $time_start, '<br>';
    echo $sql, '<br>';
    var_dump($results);
    echo '<br><br>';
}


$labs_all = $wpdb->get_results("select lab.* from ci_labs lab 
            WHERE (lab.lab_is_ref = 0 or lab.lab_is_ref = 2) 
            order by lab.lab_dba_init");


include_once(TRCPDD_PATH . 'pages/formdata.php');

?>
<form method="get" style="margin:10px 5px;">
    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
    <input type="hidden" name="tab" value="<?php echo $_REQUEST['tab'] ?>" />
    <input type="hidden" name="new" value="1">
    <?php if ($qct_setting == 'both') { ?>
        <div style="padding:5px 0">
            <label>
                <input type="radio" name="qct" value="standard" <?php checked($qct, 'standard') ?> /> Standard Deviation
            </label>
            <label>
                <input type="radio" name="qct" value="theoretical" <?php checked($qct, 'theoretical') ?> /> Percent Deviation from Theoretical Values
            </label>
        </div>
    <?php } ?>
    <br>


    <input type="hidden" id="select_month" name="month" value="<?php echo $month ?>" />
    <div class="select_month" style="float:left;margin-right:10px;" data-month="<?php echo $month ?>" data-href="<?php Tr_Base_Class_V6::link(array('month' => '')) ?>"></div>
    <input type="hidden" name="display" value="<?php echo $display_chart ?>" />

    <div style="float: left">
        <select name="instrument" id="instrument" required>
            <option value="">Select Machine</option>
            <?php

            //$instruments['MS-4500'] = 'MS-4500';
            foreach ($instruments as $in) {
                $selected = $in == $instrument ? 'selected' : '';
                echo '<option value="' . $in . '" ' . $selected . '>' . $in . '</option>';
            }
            ?>
        </select>

    </div>




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
                        <th style="width: 6%;">Batch</th>
                        <th style="width: 6%;"></th>
                        <th style="width: 10%;">Date</th>
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

                        $row->drug = strtoupper($row->drug);
                        $row->instrument = strtoupper($row->instrument);

                        $date = $row->DateTime;

                        $date = date('Y-m-d H:i:s', strtotime($date));

                        if (empty($row->conc)) $row->conc = 0;

                        if (!isset($batches[$row->DateTime]) || !is_array($batches[$row->DateTime])) {
                            $batches[$row->DateTime] = array();
                        }
                        $batches[$row->DateTime]['date'] = $date;

                        $k = (stripos($row->assen, 'low') !== false || stripos($row->assen, 'qcl') !== false) ? 'low' : 'high';
                        if (stripos($row->assen, 'medium') !== false || stripos($row->assen, 'mqc') !== false) {
                            $k = 'medium';
                        }

                        $batches[$row->DateTime]['tests'][$row->drug][$k] = array($row->rs_id, $row->conc);
                        $datetime = strtotime($date);

                        $centerarr = array('low' => '', 'high' => '');
                        if (stripos($row->drug, 'A1C') !== false) {
                            $centerarr['low'] = 5;
                            $centerarr['high'] = 12;
                        } else if (stripos($row->drug, 'C-peptide') !== false) {
                            $centerarr['low'] = 5;
                            $centerarr['high'] = 9;
                        } else if (stripos($row->drug, 'INSULIN') !== false) {
                            $centerarr['low'] = 50;
                            $centerarr['high'] = 150;
                        }

                        if (strpos($row->drug, ' 2') > 0) {
                            $drug = str_replace(' 2', '', $row->drug);
                            $labs[$drug][$k][$datetime]['conc2'] = $row->conc;
                        } else {
                            $labs[$row->drug][$k][$datetime] = array(
                                'conc' => $row->conc,
                                'conc2' => null,
                                'instrument' => $row->instrument,
                                'center' => $centerarr[$k],
                            );
                        }

                        if (empty($row->conc_number)) {
                            $row->conc_number = $row->conc;
                        }

                        $tests[$row->drug][$k][] = array(
                            'id' => $row->rs_id,
                            'drug' => $row->drug,
                            'low_high' => $k,
                            'old' => $row->conc_number,
                            'new' => $row->conc,
                            'center' =>  $centerarr[$k],
                            'date' => $date,
                            'comments' => $row->comments,
                            'instrument' => $row->instrument,
                        );
                    }


                    $chart_data = array(
                        'width' => '860',
                        'height' => '350'
                    );
                    foreach ($labs as $test => $rows) {
                        $lows = array();
                        $mediums = array();
                        $highs = array();
                        $lows2 = array();
                        $mediums2 = array();
                        $highs2 = array();

                        $dates = array();

                        $rows['low'] = isset($rows['low']) ? $rows['low'] : array();
                        ksort($rows['low']);
                        $l_ins = '';
                        foreach ($rows['low'] as $created => $vl) {
                            $date = date('Y-m-d H:i:s', $created);
                            $lows[$date] = $vl['conc'];
                            if ($vl['conc2'] !== null) {
                                $lows2[$date] = $vl['conc2'];
                            }

                            if (empty($l_ins) && $lab_report != 'CTL') {
                                $l_ins = $vl['instrument'];
                            }
                        }

                        if (count($lows) > 0) {
                            $average = isset($Average_arr[$l_ins . '_' . $test . '_qcl']) ? $Average_arr[$l_ins . '_' . $test . '_qcl'] : 0;
                            $specimen = 'urine';
                            $l_ins_title = empty($l_ins) ? $end_chart_title : $l_ins;
                            $l_ins_title = strtoupper($l_ins_title);

                            if (!empty($l_ins_title)) {
                                $l_ins_title = ' - ' . $l_ins_title;
                            }
                            $k = sanitize_title('low' . $lab_report . $test);
                            $center = $vl['center'];
                            $average = $center;

                            $chart_data['charts'][$k] = array(
                                'name' => 'Low QC' . ' - ' . $test . $l_ins_title,
                                'label' => 'Conc. ng/ml',
                                'color' => 'blue',
                                'color2' => 'yellowgreen',
                                'value' => $lows,
                                'value2' => $lows2,
                                'center' => $center,
                                'average' => floatval($average),
                            );
                        }

                        if ($result_settings['enable_req_mqc']) {
                            $rows['medium'] = isset($rows['medium']) ? $rows['medium'] : array();
                            ksort($rows['medium']);
                            $l_ins = '';
                            foreach ($rows['medium'] as $created => $vl) {
                                $date = date('Y-m-d H:i:s', $created);
                                $mediums[$date] = $vl['conc'];
                                if ($vl['conc2'] !== null) {
                                    $mediums2[$date] = $vl['conc2'];
                                }

                                if (empty($l_ins) && $lab_report != 'CTL') {
                                    $l_ins = $vl['instrument'];
                                }
                            }

                            if (count($mediums) > 0) {
                                $average = isset($Average_arr[$l_ins . '_' . $test . '_MQC']) ? $Average_arr[$l_ins . '_' . $test . '_MQC'] : 0;
                                $specimen = 'urine';
                                $l_ins_title = empty($l_ins) ? $end_chart_title : $l_ins;
                                $l_ins_title = strtoupper($l_ins_title);

                                if (!empty($l_ins_title)) {
                                    $l_ins_title = ' - ' . $l_ins_title;
                                }
                                $k = sanitize_title('Medium' . $lab_report . $test);
                                $center = '';

                                $chart_data['charts'][$k] = array(
                                    'name' => 'Medium QC' . ' - ' . $test . $l_ins_title,
                                    'label' => 'Conc. ng/ml',
                                    'color' => 'blue',
                                    'color2' => 'yellowgreen',
                                    'value' => $mediums,
                                    'value2' => $medium2,
                                    'center' => $center,
                                    'average' => floatval($average),
                                );
                            }
                        }

                        $rows['high'] = isset($rows['high']) ? $rows['high'] : array();
                        ksort($rows['high']);
                        $h_ins = '';
                        foreach ($rows['high'] as $created => $vl) {
                            $date = date('Y-m-d H:i:s', $created);
                            $highs[$date] = $vl['conc'];
                            if ($vl['conc2'] !== null) {
                                $highs2[$date] = $vl['conc2'];
                            }
                            if (empty($h_ins) && $lab_report != 'CTL') {
                                $h_ins = $vl['instrument'];
                            }
                        }

                        if (count($highs) > 0) {
                            $average = $Average_arr[$h_ins . '_' . $test . '_qch'];
                            $specimen = 'urine';
                            $h_ins_title = empty($h_ins) ? $end_chart_title : $h_ins;
                            $h_ins_title = strtoupper($h_ins_title);

                            if (!empty($h_ins_title)) {
                                $h_ins_title = ' - ' . $h_ins_title;
                            }
                            $k = sanitize_title('high' . $lab_report . $test);
                            $center = $vl['center'];
                            $average = $center;

                            $chart_data['charts'][$k] = array(
                                'name' => 'High QC' . ' - ' . $test . $h_ins_title,
                                'label' => 'Conc. ng/ml',
                                'color' => 'red',
                                'color2' => 'yellowgreen',
                                'value' => $highs,
                                'value2' => $highs2,
                                'center' => $center,
                                'average' => floatval($average),
                            );
                        }
                    }
                ?>

                    <?php
                    foreach ($batches as $DateTime => $d) {
                        $DateTimekey = str_replace(array(' ', ':'), '_', $DateTime);
                    ?>
                        <tr class="parent" data-p="batch_<?php echo $DateTimekey ?>">
                            <td><i></i></td>
                            <td><?php echo $DateTime ?></td>
                            <td></td>
                            <td><?php echo $d['date'] ?></td>
                        </tr>
                        <tr class="child p_batch_<?php echo $DateTimekey ?>">
                            <td colspan="4" class="nopadding">
                                <table class="report_tbl">
                                    <tr class="childp">
                                        <td><b>TEST</b></td>
                                        <td><b>High</b></td>
                                        <?php if ($result_settings['enable_req_mqc']) { ?>
                                            <td><b>Medium</b></td>
                                        <?php } ?>
                                        <td><b>Low</b></td>
                                        <td><b>Notes</b></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                    <?php
                                    $count = 0;
                                    foreach ($d['tests'] as $drug => $rows) {
                                        $count++;
                                    ?>
                                        <tr class="childp">
                                            <td><?php echo $drug ?></td>
                                            <td class="editable" data-id="<?php echo $rows['high'][0] ?>"><?php echo $rows['high'][1] ?></td>
                                            <?php if ($result_settings['enable_req_mqc']) { ?>
                                                <td class="editable" data-id="<?php echo $rows['medium'][0] ?>"><?php echo $rows['medium'][1] ?></td>
                                            <?php } ?>
                                            <td class="editable" data-id="<?php echo $rows['low'][0] ?>"><?php echo $rows['low'][1] ?></td>
                                            <td><?php echo $rows['comments'] ?></td>
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
                        <th style="width:18%">Test</th>
                        <th style="width:10%">Low/High</th>
                        <th style="width:9%">Old Value</th>
                        <th style="width:9%">New Value</th>
                        <th style="width:12%">Date</th>
                        <th style="width:10%">Mean <br><?php echo ($qct == 'standard') ? '+/- 1SD' : '' ?></th>
                        <th style="width:10%">Mean <br><?php echo ($qct == 'standard') ? '+/- 2SD' : '' ?></th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $kp = $qct == 'standard' ? '1P' : '1P';
                    $kp2 = $qct2 == 'standard' ? '2P' : '2P';

                    foreach ($tests as $drug => $rows) {
                        foreach ($rows as $hl => $t_rows) {

                            $values = array();
                            foreach ($t_rows as $row) {
                                $values[] = $row['new'];
                            }


                            $arg = $row['center'];
                            if (empty($arg)) {
                                $arg = array_sum($values) / count($values);
                            }
                            if ($arg > 0) {
                                $values[] = $arg;
                            }

                            $sigma = tr_pdd_standard_deviation($values);


                            $a1 = round($arg + 1 * $sigma, 3);
                            $b1 = round($arg - 1 * $sigma, 3);

                            $a2 = round($arg + 2 * $sigma, 3);
                            $b2 = round($arg - 2 * $sigma, 3);

                            foreach ($t_rows as $row) {

                                if ($row['new'] < $a1 && $row['new'] > $b1) {
                                    continue;
                                }
                    ?>
                                <tr class="tt_<?php echo $hl ?>">
                                    <td><?php echo $row['drug'] ?></td>
                                    <td><?php echo $row['low_high'] ?></td>
                                    <td><?php echo $row['old'] ?></td>
                                    <td><?php echo $row['new'] ?></td>
                                    <td><?php echo $row['date'] ?></td>
                                    <td style="white-space: nowrap;"><?php echo (($row['new'] > $a1) ? '+' . $kp . ' ' . $a1 : '-' . $kp . ' ' . $b1) ?></td>
                                    <td style="white-space: nowrap;"><?php echo (($row['new'] > $a1) ? '+' . $kp2 . ' ' . $a2 : '-' . $kp2 . ' ' . $b2) ?></td>
                                    <td class="comment_edit" data-id="<?php echo $row['id'] ?>"><?php echo $row['comments'] ?></td>
                                </tr>
                        <?php
                            }
                        }
                        ?>
                        <tr>
                            <td colspan="8">&nbsp;</td>
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

<div id="notesdialog" title="Notes" style="display: none;">
    <form id="form_notes">
        <div class="frow">
            <label for="email">Notes</label>
            <textarea id="notesvalue"></textarea>
        </div>
    </form>
</div>
<script>
    jQuery(function($) {

        function show_dialog_notes($id) {

            var dialog = $("#notesdialog").dialog({
                autoOpen: false,
                closeOnEscape: false,
                width: 400,
                modal: true,
                buttons: {
                    "Comfirm": function() {
                        $.ajax({
                            data: {
                                tr_action: 'ci_save_qc_comments',
                                btype: 'req',
                                id: $id,
                                value: $('#notesvalue').val()

                            },
                            type: 'post',
                            dataType: 'json',
                            success: function(json) {
                                dialog.dialog("close");
                            },
                            error: function() {
                                alert('Error. Please try again!');

                            }
                        })
                    },
                    "Abort": function() {
                        dialog.dialog("close");
                    }
                }
            });
            dialog.dialog("open");
        }

        $('#select_lab2').change(function() {

            $('#instrument').hide();
            $('#instrument option').hide();
            try {
                var $ins = $(this).find('option[value="' + $(this).val() + '"]').data('in');
                $ins = $ins.split(',');
                $('#instrument option[value=""]').show();
                for ($i in $ins) {
                    if ($ins[$i] != '') {
                        $('#instrument').show();
                    }

                    $('#instrument').val('');
                    $('#instrument option[value="' + $ins[$i] + '"]').show();
                }
            } catch (e) {}

        }).change();
        <?php
        if (!empty($instrument)) {
            echo '$(\'#instrument\').val(\'' . $instrument . '\');';
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
                $DateTime = $obj.data('DateTime');
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
                        btype: 'req',
                        id: $id,
                        value: $value
                    },
                    success: function(rs) {
                        $pr.html($value);
                        $pr.removeClass('editing');
                        //redraw chart
                        try {
                            if (rs.charttype == 'bg') {
                                setup_chat_bg(rs.chart.id, rs.chart);
                            } else {
                                setup_chat_line(rs.chart.id, rs.chart);
                            }
                        } catch (e) {}

                        show_dialog_notes($id);

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
                    btype: 'req',
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
                    btype: 'req',
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
                    btype: 'req',
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
                $DateTime = $obj.data('DateTime');
                $lab_id = $obj.data('lab_id');
            } catch (e) {}
        }

        $('.print_pdf').click(function() {
            tb_show("Creating PDF", "#TB_inline?height=200&amp;width=300&amp;inlineId=show_print_pdf");
            var $form = $('#form_post_images');
            $form.html('<input type="hidden" name="btype" value="req">');
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