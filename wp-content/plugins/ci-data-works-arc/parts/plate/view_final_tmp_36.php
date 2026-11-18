<?php
if (!isset($assens) || empty($assens)) {
    return;
}

$groups = array();
foreach ($assens as $pos => $arr) {
    $nblock = ceil($pos / 8);

    if (!isset($groups[$nblock])) {
        $groups[$nblock] = array(0=>array('assen' => ''));
    }
    $groups[$nblock][] = $arr;
}


foreach ($groups as $ig => $assens) {
    $com_max_col = 12;
    $com_max_row = 8;

    $plate_name = 'Final Plate  ' . chr($ig + 64);
    $subname = '';

    $show_BioRad_export = isset($show_BioRad_export)? $show_BioRad_export : true;


?>
    <div class="outerplate">
        <div class="plateblock combinedblock plate_96h combinecol_<?php echo $com_max_col ?>" data-id="<?php echo $row['pl_ID'] ?>">
            <div class="header">
                <label style="float: left;"></label>
                <?php if ($is_plate_view) { ?>
                    <a class="button printbtn1 small" style="float:left;margin-left:15px;margin-right:15px" href="#">Print</a>
                <?php } ?>
                <div class="title aleft"><?php echo $plate_name ?></div>

                <div class="stitle aleft"><?php echo $subname ?></div>
            </div>

            <div class="ccontent">
                <table class="platetbl" data-type="<?php echo $row['re_is_uti_panel'] ?>">
                    <?php

                    echo '<tr>';
                    for ($i = 0; $i <= 12; $i++) {
                        echo '<th>' . ($i > 0 ? $i : '') . '</th>';
                    }
                    echo '</tr>';
                    for ($r = 1; $r <= $com_max_row; $r++) {
                        $t_pos = $r ;
                        $cellth_class = '';
                        echo '<th class="' . $cellth_class . '">' . chr(64 + $r) . '</th>';
                        for ($c = 1; $c <= $com_max_col; $c++) {


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
                <?php if ($show_BioRad_export) { ?>
                    <a class="button pl_biorad_exportbtn " data-blockid="<?php echo 'abc_'.$ig?>">BioRad Export</a>
                <?php } ?>
                <a class="button pl_studio_exportbtn" data-blockid="<?php echo 'abc_'.$ig?>">Studio Export</a>
            </div>
        </div>
    </div>
<?php
}
