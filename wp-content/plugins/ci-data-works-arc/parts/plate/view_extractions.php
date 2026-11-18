<?php
global $plateAccs;

foreach ($plates_extractions as $row) {
    $com_max_col = 12;
    $com_max_row = 8;
    $plateAB  = $row['re_relate_id'] == 0 ? 'A' : 'B';
    
    $row['is_wide'] = true;
    $assens = trpdd_get_assens_plate($row);

    

?>
    <div class="outerplate">
        <div class=" combinedblock plate_96h combinecol_<?php echo $com_max_col ?>">
            <div class="header">
                <?php if ($is_plate_view) { ?>
                    <a class="button printbtn1 small" style="float:left;margin-left:15px;margin-right:15px" href="#">Print</a>
                <?php } ?>
                <div class="title aleft">Extraction Plate <?php echo $plateAB ?></div>

                <div class="stitle aleft"><?php echo 'Rack '.$row['re_subtitle']?></div>
            </div>

            <div class="ccontent">
                <table class="platetbl" data-type="extraction">
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
    //plate A,B,C,D
    $old_96w = $plates_96_wide;
    $plates_96_wide = array();
    
    if($row['re_relate_id']==0)
    {
        $plateAccs = array();
    }

    if(isset($row['plateA'])){
        $row['plateA']['is_wide'] = true;
        $row['plateA']['assens'] = trpdd_get_assens_plate($row['plateA']);

        $plates_96_wide[] = $row['plateA'];
        $plateAccs[] = $row['plateA'];
    }
    if(isset($row['plateB'])){
        $row['plateB']['is_wide'] = true;
        $row['plateB']['assens'] = trpdd_get_assens_plate($row['plateB']);

        $plates_96_wide[] = $row['plateB'];
        $plateAccs[] = $row['plateB'];
    }
    include(trpdd_get_file_path('plate', 'view_96_wide'));

    $plates_96_wide = $old_96w;

    

    //plateAcc

    if($row['re_relate_id']>0)
    {
        $row['assens'] = array();
        foreach($plateAccs as $i => $pl)
        {
            foreach($pl['assens'] as $j => $arr)
            {
                $pos = $j + $i * 96;
                $row['assens'][$pos] = $arr;
            }
            
        }
        $row['plate_name'] = 'Accufill';
        
        if(count($row['assens'])>0)
        {
            $old_plates_384 = $plates_384;
            $plates_384 = array($row);
            include(trpdd_get_file_path('plate', 'view_384'));
            $plates_384 = $old_plates_384;
        }
    }

    
    

}
