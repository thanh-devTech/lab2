<?php
global $wpdb;
global $rack_uti_type;

wp_enqueue_style('jquery-ui', TRCPDD_URL . 'css/jquery-ui.css', array(), '1.2');
wp_enqueue_style('admin_plate', TRCPDD_URL . 'css/admin_plate.css', array(), time());
wp_enqueue_script('jquery-ui-autocomplete');
wp_enqueue_script('jquery-ui-datepicker');
wp_enqueue_script('jquery-ui-dialog');

wp_enqueue_script('data-admin', TRCPDD_URL . 'js/admin.min.js', array(), TRCPDD_VS);

$is_plate_view  = true;
$user_id        = get_current_user_id();
$rack_ABC       = tr_get_option('ci_pdf_data', 'rack_ABC');
$rack_16        = tr_get_option('ci_pdf_data', 'rack_16');
$rack_6controls = tr_get_option('ci_pdf_data', 'rack_6controls');
$ntc_at_end     = tr_get_option('ci_pdf_data', 'rack_ntc_at_end');
$allow_rack4s   = tr_get_option('ci_pdf_data', 'rack_4s_well');
$allow_rack4s_posneg = tr_get_option('ci_pdf_data', 'rack_4s_well_posneg');
$rack_printht   = tr_get_option('ci_pdf_data', 'rack_printht');
$rack_uti_type  = tr_get_option('ci_pdf_data', 'rack_uti_type');

$max_well       = 96;
$first_ntc      = 1;
$second_ntc_pos = $rack_ABC ? 2 : 13;
$ntc3           = 0;
$ntc4           = 0;

if ($rack_16) {
    $max_well   = 16;
    $first_ntc  = 16;
    $second_ntc_pos = 15;
} else if ($allow_rack4s_posneg) {
    $first_ntc = 95;
    $second_ntc_pos = 93;
    $ntc3_pos = 94;
    $ntc4_pos = 96;
} else if ($rack_6controls) {
    $rack_ABC   = false;
    $second_ntc_pos = 93;
    $first_ntc  = 95;
    $ntc3       = 94;
    $ntc4       = 96;
} else if ($ntc_at_end) {
    $first_ntc  = 95;
    $second_ntc_pos = 96;
}



$today  = isset($_REQUEST['date']) ? $_REQUEST['date'] : '';
$bid    = isset($_REQUEST['bid']) ? trim($_REQUEST['bid']) : '';
$assen  = isset($_REQUEST['assen']) ? trim($_REQUEST['assen']) : '';

$results_96     = array();
$results_384 = array();

if (!empty($today) || !empty($bid) || !empty($assen)) {
    $where = '';
    if (!empty($today)) {
        $where .= " and pl_today='{$today}' ";
    }

    if (!empty($assen) || !empty($bid)) {
        $child_sql = array();
        $where_child = '';
        if (!empty($assen)) {
            $where_child .= " and ba.assen = '{$assen}' ";
        }
        if (!empty($bid)) {
            $where_child .= " and ba.batch_id = '{$bid}' ";
        }
        foreach (tr_pdd_get_batch_types() as $btype) {
            $batch_tbl = tr_pdd_get_table_by_btype($btype);

            $child_sql[] = "(select rb.pl_ID
                                    from {$batch_tbl} ba
                                        JOIN ci_plates_batches rb ON rb.mid=ba.mid and rb.btype='{$btype}'
                                        where 1 {$where_child} ) ";
        }

        $child_sql = implode(' UNION ALL ', $child_sql);

        $sql = "select * from ({$child_sql}) as ball";
        $pl_IDs = $wpdb->get_col($sql);
        $pl_IDs = implode(',', $pl_IDs);
        $where .= " and pl.pl_ID in ({$pl_IDs}) ";
    }

    $sql = "select pl.*,
        ra.*
        from ci_plates pl 
        left join ci_racks ra ON ra.re_ID = pl.pl_rack_ID
        where 1 {$where}
        order by pl_number asc";

    $results_96 = $wpdb->get_results($sql, ARRAY_A);

    $plate_384_ids = array();
    foreach ($results_96 as $r) {
        if ($r['pl_combine_ID'] > 0) {
            $plate_384_ids[] = $r['pl_combine_ID'];
        }
    }

    $plate_384_ids_str = implode(',', $plate_384_ids);
    if (empty($plate_384_ids_str)) {
        $plate_384_ids_str = '-1';
    }
    $results_384 = $wpdb->get_results("select * from ci_plates_combine 
	                            where ID in ({$plate_384_ids_str}) 
	                            order by pl_number asc", ARRAY_A);
}


?>
<div class="wrap">
    <h2>Plates</h2>

    <form method="get" style="float:left">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>" />
        <input type="hidden" name="tab" value="<?php echo $_GET['tab'] ?>" />
        <label>Date:</label>
        <input type="search" class="hasdate" name="date" value="<?php echo $today ?>" />
        <label>Batch</label>
        <input type="search" name="bid" value="<?php echo isset($batch_id) ? $batch_id : '' ?>" placeholder="Batch" />
        <label>Accession #:</label>
        <input type="search" name="assen" value="<?php echo $assen ?>" placeholder="Accession #" />
        <input type="submit" class="button bsty6" value="Go" />
        <a class="button print_btn">Print</a>
    </form>
    <div class="clear"></div>
    <?php
    if (count($results_96) == 0) {
        echo '<p>Not found results</p>';
    } else {
        $plates_384 = array();
        $plates_96_blood = array();

        echo '<div class="list_plate_96">';
        foreach ($results_96 as $row) {
            $plate_name = 'ELUTION PLATE ' . $row['pl_number'];

            $config_plate = trpdd_plate_map_config($row['re_is_uti_panel']);

            if (!empty($config_plate)) {
                if ($config_plate['type'] == 96 && isset($config_plate['add96'])) {
                    global $ci_lab_count_plate_batches;
                    $ci_lab_count_plate_batches = 0;
                    $row['is_wide'] = true;
                    if ($row['re_is_uti_panel'] == 82) {
                        $plate_name = 'HpaII Digestion Plate ' . $row['pl_number'];
                    }
                    include(trpdd_get_file_path('plate', 'view_96_print'));
                    $count = ceil($ci_lab_count_plate_batches / $config_plate['add96map']['max']);

                    for ($i1 = 1; $i1 <= $count; $i1++) {
                        $row['sub_number'] = $i1;
                        $row['subwellsplate'] = $config_plate['add96map']['max'];
                        if ($row['re_is_uti_panel'] == 82) {
                            $plate_name = 'ddPCR Plate ' . $i1;
                        }
                        include(trpdd_get_file_path('plate', 'view_96_print'));
                    }
                    continue;
                } else if ($config_plate['type'] == 384) {
                    if ($config_plate['rack'] != 96) {
                        $plates_384[] = $row;
                        continue;
                    } else if ($config_plate['subwellsplate'] > 0) {
                        //get count subplates
                        $countsp = $wpdb->get_var("select count(*) from ci_plates_batches where pl_ID = '{$row['pl_ID']}' ");
                        $countsub = ceil($countsp / $config_plate['subwellsplate']);
                        for ($isub = 1; $isub <= $countsub; $isub++) {
                            $row['sub_number'] = $isub;
                            $row['subwellsplate'] = $config_plate['subwellsplate'];
                            $plates_384[] = $row;
                        }
                        unset($row['sub_number']);
                        unset($row['subwellsplate']);
                    } else {
                        $plates_384[] = $row;
                        continue;
                    }
                } else {
                    if (isset($config_plate['add384']) && $config_plate['add384']) {
                        $old_plate_384 = $plates_384;
                        $plates_384 = array();
                        $tmp_row = $row;
                        $tmp_row['is_add384'] = 1;
                        $tmp_row['is_96'] = true;
                        $tmp_row['is_view'] = true;
                        $plates_384[] = $tmp_row;
                        echo '</div><div class="list_plate_384">';
                        include(trpdd_get_file_path('plate', 'view_384'));
                        echo '</div>';
                        echo '<div class="list_plate_96">';
                        $plates_384 = $old_plate_384;
                        continue;
                    }
                    $row['is_wide'] = true;
                }
            } else if ($rack_uti_type == 14 && $row['re_is_uti_panel'] == 1) {
                $plates_384[] = $row;
                continue;
            } else if (in_array($row['re_is_uti_panel'], array(-5, 10, 11, 12, 13, 15, 30, 31, 38, 43, 44, 50))) {
                $plates_384[] = $row;
                continue;
            } else if ($row['re_is_uti_panel'] == 21) {
                //blood FA
                $plates_96_blood[] = $row;
                continue;
            } else if (in_array($row['re_is_uti_panel'], array(17, 18)) && $row['pl_blank1_text'] == 'plateAcc') {
                $plates_384[] = $row;
                continue;
            } else if (in_array($row['re_is_uti_panel'], array(17, 18))) {
                //$plate_name = 'Extraction '. str_replace('plate','Plate ',$row['pl_blank1_text']);
            }

            if ($row['re_is_uti_panel'] == -1) {
                //Respiratory Light
                $row['pl_ntc_position'] = 0;
                $row['pl_ntc2_position'] = 0;
            } else if ($rack_uti_type == 3 && $row['re_is_uti_panel'] == 1) {
                $row['pl_ntc_position'] = 0;
                $row['pl_ntc2_position'] = 0;
            }


            include(trpdd_get_file_path('plate', 'view_96_print'));
        }
        echo '</div>';

        if (count($plates_96_blood) > 0) {
            include(trpdd_get_file_path('plate', 'view_96_blood'));
        }

        if (count($plates_384) > 0) {
            echo '<div class="list_plate_384">';
            foreach ($plates_384 as $row) {
                $com_max_col = 24;
                $com_max_row = 16;
                $row['is_384'] = true;
                $assens = trpdd_get_assens_plate($row);

                $plate_name     = isset($row['plate_name']) ? $row['plate_name'] : 'Final Plate ' . $row['pl_number'];
    ?>
                <div class="outerplate">
                    <div class="combinedblock plateblock plate_384 combinecol_<?php echo $com_max_col ?>" data-id="<?php echo $row['pl_ID'] ?>">
                        <div class="header">
                            <label style="float: left;"><input type="checkbox"></label>
                            <a class="button printbtn1 small" style="float:left;margin-left:15px" href="#">Print</a>

                            <div class="title" title="<?php echo $row['pl_today'] ?>"><?php echo $plate_name ?></div>
                            <div class="stitle">Rack #<?php echo $row['re_number'] . ' - ' . $row['re_subtitle'] ?></div>

                            <?php if ($rack_printht) { ?>
                                <div class="onlyprint stitle" style="line-height: 30px">
                                    Hood #:___________ &nbsp;&nbsp;&nbsp;Tech. Initials: _____________
                                    Positives___________ 
                                    <br>
                                    Reruns___________
                                    Need changed to NaN___________
                                    Rejected___________
                                </div>
                            <?php } ?>
                        </div>
                        <div class="ccontent">
                            <table class="platetbl" data-type="<?php echo $row['re_is_uti_panel'] ?>">
                                <thead>
                                    <tr>
                                        <?php
                                        echo '<th></th>';
                                        for ($c = 1; $c <= $com_max_col; $c++) {
                                            echo '<th>' . $c . '</th>';
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <?php
                                $t_pos = 0;
                                for ($r = 1; $r <= $com_max_row; $r++) {
                                    $char = chr($r + 64);
                                    $row_class = 'color_' . $r;
                                    if ($r >= 15) $row_class = 'pos_neg_row';
                                    echo '<tr class="' . $row_class . '">';
                                    echo '<th>' . $char . '</th>';
                                    for ($c = 1; $c <= $com_max_col; $c++) {
                                        $t_pos++;

                                        $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');
                                        $assen = str_replace(',', '<br>', $arr['assen']);

                                        $cell_class = isset($arr['class']) ? $arr['class'] . ' ' : '';
                                        if (empty($assen)) {
                                            $cell_class .= ' emptyc ';
                                        }
                                        if (stripos($assen, 'PC:') === 0) {
                                            $assen = '<span class="whitetxt">' . str_replace('PC:', 'PC:</span>', $assen);
                                        } else  if (stripos($assen, 'NC:') === 0) {
                                            $assen = '<span class="whitetxt">' . str_replace('NC:', 'NC:</span>', $assen);
                                        }

                                        echo '<td class="' . $cell_class . '" title="' . $t_pos . '">';
                                        echo $assen;
                                        if (isset($arr['is_rerun']) && $arr['is_rerun']) echo '<span class="scircle greencircle fright sscircle" title="Rerun">R</span>';
                                        echo '</td>';
                                    }
                                    echo '</tr>';
                                }

                                ?>
                            </table>

                        </div>
                    </div>
                </div>
            <?php
            }
            echo '</div>';
        }


        echo '<div class="list_plate_384">';
        foreach ($results_384 as $row) {
            $plates = $wpdb->get_results(
                "select p.*,
				ra.re_panel_id,
				ra.re_is_uti_panel
				from ci_plates p
				JOIN ci_racks ra ON ra.re_ID = p.pl_rack_ID
				where p.pl_combine_ID = {$row['ID']} 
				order by p.pl_number asc",
                ARRAY_A
            );
            $plates_ids = array();
            $assens = array();
            foreach ($plates as $i => $plate) {
                $assens = trpdd_get_assens_plate($plate, true, $assens);
                $plates_ids[] = $plate['pl_ID'];
            }

            $com_max_col = $row['combine96'] ? 12 : 24;
            $com_max_row = $row['combine96'] ? 8 : 16;

            if ($row['combine96']) {
                $assens[81] = array(
                    'assen' => 'Positive IC',
                    'pl_number' => 'orange',
                );
                $assens[82] = array(
                    'assen' => 'Negative IC',
                    'pl_number' => 'yellow',
                );
                $assens[83] = array(
                    'assen' => 'NTC',
                    'pl_number' => 'green',
                );
            }
            ?>
            <div class="outerplate">
                <div class="combinedblock combinecol_<?php echo $com_max_col ?>" data-id="<?php echo $row['ID'] ?>">
                    <div class="header">
                        <label style="float: left;margin-right:10px"><input type="checkbox"></label>
                        <a class="button printbtn1 small" style="float:left;margin-left:15px" href="#">Print</a>
                        <div class="title aleft pleft200">Final Plate <?php echo $row['pl_number'] ?></div>
                        <div class="stitle aleft pleft200"><?php echo tr_pdd_display_date($row['created'], 'm/d/Y H:i:s') ?>
                            - Plates <?php echo implode(', ', $plates_ids) ?></div>
                    </div>

                    <div class="ccontent">
                        <table class="platetbl">
                            <thead>
                                <tr>
                                    <?php
                                    echo '<th></th>';
                                    for ($c = 1; $c <= $com_max_col; $c++) {
                                        echo '<th>' . $c . '</th>';
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <?php
                            $t_pos = 0;
                            for ($r = 1; $r <= $com_max_row; $r++) {
                                $char = chr($r + 64);
                                echo '<tr>';
                                echo '<th>' . $char . '</th>';
                                for ($c = 1; $c <= $com_max_col; $c++) {
                                    $t_pos++;

                                    $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');
                                    $assen = str_replace(',', '<br>', $arr['assen']);
                                    $plate_num = $arr['pl_number'];


                                    if (strpos($assen, ',') !== false) {
                                        $assen = str_replace(',', '<br>', $assen);
                                    } else if (!$arr['ntc'] && stripos($assen, 'ic') === false) {
                                        preg_match("/([a-zA-Z]*)([0-9]*)/", $assen, $matches);
                                        if (!empty($matches[0])) {
                                            $assen = $matches[1] . '<br>' . $matches[2];
                                        }
                                    }
                                    $bg_class = 'color_' . $plate_num;
                                    if (isset($arr['class']) && !empty($arr['class'])) {
                                        $bg_class = $arr['class'];
                                    }
                                    if (stripos($assen, 'PC:') === 0) {
                                        $assen = '<span class="whitetxt">' . str_replace('PC:', 'PC:</span>', $assen);
                                    } else  if (stripos($assen, 'NC:') === 0) {
                                        $assen = '<span class="whitetxt">' . str_replace('NC:', 'NC:</span>', $assen);
                                    }
                                    echo '<td class="' . $bg_class . ' ' . (empty($assen) ? 'emptyc' : '') . '" title="' . $t_pos . '">';
                                    echo $assen;
                                    if ($arr['is_rerun']) echo '<span class="scircle greencircle fright sscircle" title="Rerun">R</span>';
                                    echo '</td>';
                                }
                                echo '</tr>';
                            }

                            ?>
                        </table>

                    </div>
                </div>
            </div>
    <?php
        }
        echo '</div>';
    }

    ?>
</div>

<div id="style_html">
    <style>
        @media print {
            @page {
                size: landscape;
            }

            body {
                zoom: 80%;
                margin: 1cm;
            }

            body .combinedblock {
                zoom: 100% !important;
                padding-left: 10px;
            }

            body * {
                -webkit-print-color-adjust: exact !important;
            }
        }

        .print_body {
            font-family: Arial, sans-serif;
        }

        .onlyprint {
            display: none;
        }

        .print_body .onlyprint {
            display: block;
        }

        table.platetbl {
            border-collapse: collapse;
        }

        table.platetbl tr th {
            border: 0.5px solid #ddd !important;

        }

        table.platetbl tr td {
            width: 8%;
            height: 40px;
            text-align: center;
            font-size: 13px;
            border: 0.5px solid #ddd !important;
        }

        .combinecol_24 table.platetbl tr td {
            width: 4%;
        }

        table.platetbl tr:nth-child(2n) td {
            background: #f2f2f2;
        }


        table.platetbl td.ntc1,
        table.platetbl td.negative {
            background: #ffc522 !important;
        }

        table.platetbl td.ntc2,
        table.platetbl td.positive {
            background: darkorange !important;
        }

        tr.ntc2 td,
        tr.ntc4 td,
        .combinedblock tr td.color_orange {
            background: darkorange !important;
        }

        .combinedblock tr td.color_yellow {
            background: yellow !important;
        }

        .combinedblock tr td.color_green {
            background-color: #0cae0cc9 !important;
        }

        .scircle {
            display: inline-block !important;
            width: 18px !important;
            height: 18px !important;
            line-height: 18px !important;
            -webkit-border-radius: 50%;
            -moz-border-radius: 50%;
            border-radius: 50%;
            color: #fff;
            background: #000;
            padding: 0 !important;
            text-align: center !important;
            font-size: 10px !important;
        }

        .scircle.greencircle {
            background: green;
            color: #fff;
        }

        .scircle.redcircle {
            background: red;
            color: #fff;
        }

        .assen .scircle,
        .sscircle {
            float: right;
            width: 16px !important;
            height: 16px !important;
            line-height: 16px !important;
        }

        <?php
        foreach ($colors as $i => $color) {
            $color2 = '#efefef';
            echo '.color_' . $i . ' .header,.color_' . $i . ' table th, .combinedblock td.color_' . $i . '{background:' . $color . ' !important}';
            //echo '.print_body .combinedblock td.color_'.$i.'{background:'.$color2.' !important}';
        }
        ?>.emptyc .header,
        .combinedblock td.emptyc {
            background: #fff !important;
        }

        .print_body input[type="checkbox"],
        .print_body a.button,
        .print_body .dontprint {
            display: none !important;
        }

        .print_body .aleft {
            text-align: center !important;
        }

        .print_body .pleft200 {
            padding-left: 0;
        }

        .print_body .combinedblock,
        .print_body .plateblock {
            border: none;
            margin: 0;
            padding: 0;
        }

        .print_body .combinedblock tr th {
            background: #ddd;
        }

        .print_body .combinedblock {
            max-width: 100% !important;
            width: 100% !important;
        }

        .print_body .combinedblock td {
            font-size: 11px !important;
        }

        .print_body .combinedblock.plate_96h td {
            font-size: 13px !important
        }

        .print_body .combinedblock .scircle {
            width: 12px !important;
            height: 12px !important;
            line-height: 12px !important;
            font-size: 8px !important;
        }
    </style>
</div>
<script>
    jQuery(function($) {
        $('body').on('click', 'a.print_btn', function() {
            var $html = '';
            $('.plateblock input:checked').each(function() {
                $html += '<div style="page-break-after: always;">';
                $html += $(this).parents('.outerplate').html();
                $html += '</div>';
            })
            $('.combinedblock input:checked').each(function() {
                $html += '<div style="page-break-after: always;">';
                $html += $(this).parents('.outerplate').html();
                $html += '</div>';
            })
            if ($html) {
                $html = '<body class="print_body" onload="window.print()">' + $html + '</body>';
                $html += $('#style_html').html();
                var iframe = document.getElementById('print_frame_mu');
                iframe = iframe.contentWindow || (iframe.contentDocument.document || iframe.contentDocument);

                iframe.document.open();
                iframe.document.write($html);
                iframe.document.close();

                $('.plateblock input:checked').prop('checked', false);
                $('.combinedblock input:checked').prop('checked', false);
            } else {
                alert('Please select Plate');
            }
            return false;
        }).on('click', 'a.printbtn1', function() {
            var $html = $(this).parents('.outerplate').html();
            $html = '<body class="print_body" onload="window.print()">' + $html + '</body>';
            $html += $('#style_html').html();
            var iframe = document.getElementById('print_frame_mu');
            iframe = iframe.contentWindow || (iframe.contentDocument.document || iframe.contentDocument);

            iframe.document.open();
            iframe.document.write($html);
            iframe.document.close();
            return false;
        })
    })
</script>