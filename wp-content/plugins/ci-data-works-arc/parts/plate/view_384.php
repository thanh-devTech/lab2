<?php
foreach ($plates_384 as $row) {
    $exportblockid = '';
    $com_max_col    = 24;
    $com_max_row    = 16;
    $row['is_384']  = true;

    if (empty($row['re_number'])) {
        continue;
    }

    $assens         = isset($row['assens']) ? $row['assens'] : trpdd_get_assens_plate($row);

    $plate_name     = isset($row['plate_name']) ? $row['plate_name'] : 'Final Plate ' . $row['re_subtitle'] . ' ' . $row['pl_number'];
    $sub_plate_name = isset($row['sub_plate_name']) ? $row['sub_plate_name'] : '';

    if (isset($row['sub_number']) && $row['sub_number'] > 0) {
        $exportblockid = 'num_' . $row['sub_number'];
    }
    $is_view = false;
    if (isset($row['is_view']) && $row['is_view']) {
        $is_view = true;
    }
    if ((in_array($row['re_is_uti_panel'], array(30, 31, 32, -300)) || !$is_view) && empty($sub_plate_name)) {
        $sub_plate_name = 'Rack #' . $row['re_number'] . ' - ' . $row['re_subtitle'];
    }

?>
    <div class="outerplate">
        <div class="combinedblock plateblock plate_384 combinecol_<?php echo $com_max_col ?>" data-id="<?php echo $row['pl_ID'] ?>">
            <div class="header">
                <?php if (!isset($row['disable_cb'])) { ?>
                    <label style="float: left;"><input type="checkbox"></label>
                <?php } ?>
                <?php if ($is_view) {
                    echo '<a class="button printbtn1 small" style="float:left;margin-left:15px" href="#">Print</a>';
                } ?>

                <div class="title <?php if (!$is_view) echo 'aleft' ?>"><?php echo $plate_name ?></div>
                <div class="stitle <?php if (!$is_view) echo 'aleft' ?>"><?php echo $sub_plate_name ?></div>
                <?php if (empty($row['re_number'])) echo '<span class="norackicon"></span>' ?>
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

                            if ($is_view) {
                                if (strpos($assen, ',') !== false) {
                                    $assen = str_replace(',', '<br>', $assen);
                                } else if (!$arr['ntc'] && stripos($assen, 'ic') === false) {
                                    preg_match("/([a-zA-Z]*)([0-9]*)/", $assen, $matches);
                                    if (!empty($matches[0])) {
                                        $assen = $matches[1] . '<br>' . $matches[2];
                                    }
                                }
                            }

                            $cell_class = isset($arr['class']) ? $arr['class'] . ' ' : '';
                            if (empty($assen)) {
                                $cell_class .= ' emptyc ';
                            }
                            if (stripos($assen, 'PC:') === 0) {
                                $assen = '<span class="whitetxt">' . str_replace('PC:', 'PC:</span>', $assen);
                            } else  if (stripos($assen, 'NC:') === 0) {
                                $assen = '<span class="whitetxt">' . str_replace('NC:', 'NC:</span>', $assen);
                            }
                            $posabc = trpdd_pos_aaa2abc($t_pos, 384);

                            echo '<td class="' . $cell_class . '"  title="' . $t_pos . '-' . $posabc . '">';
                            echo $assen;
                            if (isset($arr['is_rerun']) && $arr['is_rerun']) {
                                echo '<span class="scircle greencircle fright sscircle" title="Rerun">R</span>';
                            }
                            echo '</td>';
                        }
                        echo '</tr>';
                    }

                    ?>
                </table>

            </div>
            <?php if (!$is_view) { ?>
                <div class="cfooter" style="text-align: left">
                    <?php if (in_array($row['re_is_uti_panel'], array(30, 31, 32))) {
                        echo '<a class="button pl_qtower_exportbtn">qTower Export</a>';
                    } else { ?>
                        <a class="button pl_biorad_exportbtn" data-blockid="<?php echo $exportblockid ?>">BioRad Export</a>
                        <a class="button pl_studio_exportbtn" data-blockid="<?php echo $exportblockid ?>">Studio Export</a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>

<?php
}
