<?php
foreach ($plates_96_wide as $row) {

    $base_row       = $row;
    $com_max_col    = 12;
    $com_max_row    = 8;
    $config_plate   = trpdd_plate_map_config($row['re_is_uti_panel']);

    $plate_name     = 'Plate ' . $row['re_subtitle'];
    $subname        = '';

    if (isset($config_plate['add384']) && $config_plate['add384']) {
        $plate_name = 'Elution '.$plate_name;
    }

    if ($row['re_is_uti_panel'] == 18) {
        $plate_name = 'ELUTION ' . str_replace('plate', 'Plate ', $row['pl_blank1_text']);
        if ($row['re_relate_id'] > 0) {
            $plate_name = str_replace(array('Plate A', 'Plate B'), array('Plate C', 'Plate D'), $plate_name);
        }
    }
    
    $row['is_wide'] = true;
    $row['is_96'] = true;
    $assens = isset($row['assens']) ? $row['assens'] : trpdd_get_assens_plate($row);

?>
    <div class="outerplate">
        <div class=" combinedblock plate_96h combinecol_<?php echo $com_max_col ?>" data-id="<?php echo $row['pl_ID'] ?>">
            <div class="header">
                <label style="float: left;"><input type="checkbox"></label>
                <?php if ($is_plate_view) { ?>
                    <a class="button printbtn1 small" style="float:left;margin-left:15px;margin-right:15px" href="#">Print</a>
                <?php } ?>
                <div class="title aleft"><?php echo $plate_name ?></div>

                <div class="stitle aleft"><?php echo $subname ?></div>
            </div>

            <div class="ccontent">
                <table class="platetbl" data-type="<?php echo $row['re_is_uti_panel'] ?>">
                    <?php
                    $t_pos          = 0;
                    echo '<tr>';
                    for ($i = 0; $i <= 12; $i++) {
                        echo '<th>' . ($i > 0 ? $i : '') . '</th>';
                    }
                    echo '</tr>';
                    for ($r = 1; $r <= $com_max_row; $r++) {
                        $cellth_class = '';
                        echo '<th class="' . $cellth_class . '">' . chr(64 + $r) . '</th>';
                        for ($c = 1; $c <= $com_max_col; $c++) {
                            $t_pos++;

                            $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');
                            $assen = str_replace(',', '<br>', $arr['assen']);

                            $cell_class = isset($arr['class']) ? $arr['class'] . ' ' : '';
                            if (empty($assen)) {
                                $cell_class .= ' emptyc ';
                            }


                            echo '<td class="' . $cell_class . '" title="' . $t_pos . '">';
                            if (!empty($assen)) {
                                echo $assen;
                                if (isset($arr['is_rerun']) && $arr['is_rerun']) {
                                    echo '<span class="scircle greencircle fright sscircle" title="Rerun">R</span>';
                                }
                            }
                            echo '</td>';
                        }
                        echo '</tr>';
                    }

                    ?>
                </table>

            </div>
            <div class="cfooter" style="text-align: left">

            </div>
        </div>
    </div>
<?php

    if (isset($config_plate['add384']) && $config_plate['add384']) {
        $old_plate_384 = $plates_384;
        $plates_384 = array();
        $tmp_row = $base_row;
        $tmp_row['is_add384'] = 1;
        $tmp_row['is_96'] = true;
        $plates_384[] = $tmp_row;

        include(trpdd_get_file_path('plate', 'view_384'));
        $plates_384 = $old_plate_384;
    }
}
