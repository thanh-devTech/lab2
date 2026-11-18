<?php
$tablecount = 1;


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
$plates_numbers = array();
$assens = array();

if (in_array($row['pl_type'], array(35))) {
    foreach ($plates as $i => $plate) {
        $assens = trpdd_get_assens_plate($plate, true, $assens);
        $plates_numbers[] = $plate['pl_number'];
        
    }
    $tablecount = ceil(count($assens)/6);
    
} else {
    foreach ($plates as $i => $plate) {
        $assens = trpdd_get_assens_plate($plate, true, $assens);
        $plates_numbers[] = $plate['pl_number'];
    }
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

$t_pos = 0;
for ($i = 1; $i <= $tablecount; $i++) {
    $final_id = $row['pl_number'];
    if($tablecount>1)
    {
        $final_id.= '-'.$i;
    }

?>
    <div class="combinedblock combinecol_<?php echo $com_max_col ?>" data-id="<?php echo $row['ID'] ?>" data-num="<?php echo $i?>">
        <div class="header">
            <label style="float: left;margin-right:10px"><input type="checkbox"></label>
            <div style="float: left;margin:0 10px">
                <?php if ($row['combine96']) {
                    echo '<a class="button expandbtn">Reverse to 16</a>';
                } else {
                    echo '<a class="button expandbtn">Reverse to 96</a>';
                } ?>

            </div>
            <div class="title aleft">Final Plate <?php echo $final_id ?></div>
            <div class="stitle aleft"><?php echo tr_pdd_display_date($row['created'], 'm/d/Y H:i:s') ?>
                - Plates <?php echo implode(', ', $plates_numbers) ?></div>
        </div>

        <div class="ccontent">
            <table class="platetbl" data-type="<?php echo $row->re_is_uti_panel ?>">
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
                $t_pos = ($i-1)*384;
                for ($r = 1; $r <= $com_max_row; $r++) {
                    $char = chr($r + 64);
                    echo '<tr>';
                    echo '<th>' . $char . '</th>';
                    for ($c = 1; $c <= $com_max_col; $c++) {
                        $t_pos++;

                        $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');
                        $assen = str_replace(',', '<br>', $arr['assen']);
                        $plate_num = $arr['pl_number'];
                        $bg_class = 'color_' . $plate_num;
                        if (isset($arr['class']) && !empty($arr['class'])) {
                            $bg_class = $arr['class'];
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
        <div class="cfooter">
            <?php
            if (in_array($row['pl_type'], array(33, 34))) {
                echo '<a class="button com_pl_qtower_exportbtn">qTower Export</a>';
            } else {
                if ($row['combine96']) {
                    echo '<a class="button com_studio_exportbtn" data-tem="96">Studio Export</a>';
                }
            ?>
                <a class="button com_biorad_exportbtn">BioRad Export</a>
            <?php
            }
            ?>
        </div>
    </div>
<?php
}
