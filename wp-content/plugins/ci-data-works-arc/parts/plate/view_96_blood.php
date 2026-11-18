<?php
foreach ($plates_96_blood as $row) {
    $com_max_col = 12;
    $com_max_row = 6;
    $row['is_96']   = true;
    $assens = trpdd_get_assens_plate($row);

    $row['other_data'] = json_decode($row['other_data'], true);
    if (!is_array($row['other_data'])) $row['other_data'] = array();
    if(isset($_GET['debug'])){
        var_dump($row['other_data']);
    }
    $excludes = isset($row['other_data']['excludes']) && is_array($row['other_data']['excludes'])? $row['other_data']['excludes'] : array();
    $crackeds = isset($row['other_data']['cracked']) && $row['other_data']['cracked'] && isset($row['other_data']['cracked_channel'])? explode(',',$row['other_data']['cracked_channel']) : array();
    $noflows = isset($row['other_data']['noflow']) && $row['other_data']['noflow'] && isset($row['other_data']['noflow_channel'])? explode(',',$row['other_data']['noflow_channel']) : array();
    $aggregates = isset($row['other_data']['aggregate']) && $row['other_data']['aggregate'] && isset($row['other_data']['aggregate_channel'])? explode(',',$row['other_data']['aggregate_channel']) : array();
    $others = isset($row['other_data']['other']) && $row['other_data']['other'] && isset($row['other_data']['other_channel'])? explode(',',$row['other_data']['other_channel']) : array();

?>
    <div class="outerplate">
        <div class=" combinedblock plateblock plate_96h combinecol_<?php echo $com_max_col ?>" data-id="<?php echo $row['pl_ID'] ?>">
            <div class="header">
                <label style="float: left;"><input type="checkbox" title="<?php echo $row['pl_ID']?>"></label>
                <?php if ($is_plate_view) { ?>
                    <a class="button printbtn1 small" style="float:left;margin-left:15px;margin-right:15px" href="#">Print</a>
                <?php } ?>
                <div class="title aleft">Plate <?php echo $row['re_subtitle'] ?></div>
                <div class="frow">
                    <div class="col4">
                        <label>Coated date: </label> <span class="re_priority"><?php echo $row['re_priority'] ?></span>
                    </div>
                    <div class="col4">
                        <label>Plate Serial Number: </label> <span class="re_barcode"><?php echo $row['re_barcode'] ?></span>
                    </div>
                    <div class="col4">
                        <a class="edit_plate thickbox2 dontprint" title="Plate <?php echo $row['re_subtitle'] ?>" href="#" data-href="<?php Tr_Base_Class_V6::link(array('trajax' => 'consumables', 'id' => $row['pl_ID'], 'width' => '1000')) ?>"><i class="ricon">i</i> Consumables</a>
                    </div>
                    <div class="col4">
                        <a class="issue_exclude thickbox2 dontprint" title="Plate <?php echo $row['re_subtitle'] ?>" href="#" data-href="<?php Tr_Base_Class_V6::link(array('trajax' => 'issue_exclude', 'id' => $row['pl_ID'], 'width' => '600')) ?>"> Issues & Excludes</a>
                    </div>
                </div>
                <div class="stitle aleft"></div>
            </div>

            <div class="ccontent">
                <table class="platetbl" data-type="<?php echo $row['re_is_uti_panel'] ?>">
                    <?php
                    $t_pos          = 0;
                    $group_row      = 0;
                    $last_com_pos   = 0;
                    $current_chanel = 0;
                    for ($r = 1; $r <= $com_max_row; $r++) {
                        $bold_num   = $r;
                        $current_chanel = $bold_num;
                        $row_class  = 'color_' . $r;

                        if(in_array($row['re_rack_subtype'], array('M'))){
                            $row_class = '';
                            if(isset($assens[$t_pos+1]) )
                            {
                                $row_class = 'bgsubtype_'.strtolower($assens[$t_pos+1]['subtype']);
                            }
                        }
                        echo '<tr class="' . $row_class . '">';
                        
                        $cellth_class = 'chanel_'.$current_chanel;
                        if(in_array($current_chanel,$excludes))
                        {
                            $cellth_class.= ' exclude_bg ';
                        }else if(in_array($current_chanel,$crackeds))
                        {
                            $cellth_class.= ' cracked_bg ';
                        }else if(in_array($current_chanel,$noflows))
                        {
                            $cellth_class.= ' noflows_bg ';
                        }else if(in_array($current_chanel,$aggregates))
                        {
                            $cellth_class.= ' aggregates_bg ';
                        }else if(in_array($current_chanel,$others))
                        {
                            $cellth_class.= ' other_bg ';
                        }
                        echo '<th class="'.$cellth_class.'">' . $bold_num . '</th>';
                        for ($c = 1; $c <= $com_max_col; $c++) {
                            $t_pos++;

                            $arr = isset($assens[$t_pos]) ? $assens[$t_pos] : array('assen' => '');
                            $other_data = isset($arr['other_data'])? $arr['other_data'] : array();
                            $assen = str_replace(',', '<br>', $arr['assen']);

                            $cell_class = isset($arr['class']) ? $arr['class'] . ' ' : '';
                            if (empty($assen)) {
                                $cell_class .= ' emptyc ';
                            }

                            if ($c > 1 && $c % 3 == 1) {
                                $current_chanel = ($bold_num + ($c - 1) * 2);
                                $cellth_class= 'chanel_'.$current_chanel;
                                if(in_array($current_chanel,$excludes))
                                {
                                    $cellth_class.= ' exclude_bg ';
                                }else if(in_array($current_chanel,$crackeds))
                                {
                                    $cellth_class.= ' cracked_bg ';
                                }
                                else if(in_array($current_chanel,$noflows))
                                {
                                    $cellth_class.= ' noflows_bg ';
                                }else if(in_array($current_chanel,$aggregates))
                                {
                                    $cellth_class.= ' aggregates_bg ';
                                }else if(in_array($current_chanel,$others))
                                {
                                    $cellth_class.= ' other_bg ';
                                }
                                echo '<th class="'.$cellth_class.'">' . $current_chanel . '</th>';
                            }
                            $cell_class.= 'chanel_'.$current_chanel;
                            if(in_array($current_chanel,$excludes))
                            {
                                $cell_class.= ' exclude_bg ';
                            }else if(in_array($current_chanel,$crackeds))
                            {
                                $cell_class.= ' cracked_bg ';
                            }else if(in_array($current_chanel,$noflows))
                            {
                                $cell_class.= ' noflows_bg ';
                            }else if(in_array($current_chanel,$aggregates))
                            {
                                $cell_class.= ' aggregates_bg ';
                            }else if(in_array($current_chanel,$others))
                            {
                                $cell_class.= ' other_bg ';
                            }
                            echo '<td class="' . $cell_class . '" title="' . $t_pos . '">';
                            if (!empty($assen)) {
                                echo $assen;
                                if (isset($arr['is_rerun']) && $arr['is_rerun']) {
                                    echo '<span class="scircle greencircle fright sscircle" title="Rerun">R</span>';
                                }
                                echo '<div class="s_num">S' . sprintf('%03d', $arr['s_num']) . '</div>';

                                if(isset($other_data['vp_type_name']) && !empty($other_data['vp_type_name'])){
                                    echo '<div class="vp_typet">'.$other_data['vp_type_name'].'</div>';
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
}
