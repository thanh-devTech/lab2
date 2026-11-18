<?php

include_once(trpdd_get_file_path('req', 'functions_req_qc'));


if (empty($row['re_number'])) {
    return;
}
$base_row       = $row;


$otherdata      = !empty($row['other_data']) ? json_decode($row['other_data'], true)  : array();

$show_dx11_file = false;
$show_qx200_file = false;
$show_reason_column = false;
$qc_comments    = array();
$qcs_data       = array();

$qc2_pass       = true;
$qc3_pass       = true;
$qc4_pass       = true;
$qc5_pass       = true;


$show_BioRad_export = isset($show_BioRad_export) ? $show_BioRad_export : true;
$config_plate   = trpdd_plate_map_config($row['re_is_uti_panel']);
$tbl_classes    = 'platetbl';
$show_export_96 = isset($row['show_export_96']) ? $row['show_export_96'] : true;
$exportblockid  = '';

$plate_controls = array(
    'neg' => 'Covid-19 Negative IC',
    'pos' => 'Covid-19 Positive IC'
);
if (isset($row['sub_number']) && $row['sub_number'] > 0) {
    $exportblockid = 'num_' . $row['sub_number'];
}
$plate_max_well = $max_well;
$row['is_96']   = true;

$assens = trpdd_get_assens_plate($row);

$class = 'color_' . $row['pl_number'];
if ($row['pl_combine_ID'] > 0) {
    $class .= ' combined ';
}
if ($row['pl_lock']) {
    $class .= ' locked ';
}




if (!empty($config_plate)) {

    if (isset($config_plate['dx11_file']) && $config_plate['dx11_file']) {
        $has_qc1_pass = false;
        $has_qc1_fail = false;
        foreach ($assens as $arr) {
            if (isset($arr['pass'])) {
                if ($arr['pass']) {
                    $has_qc1_pass = true;
                } else {
                    $has_qc1_fail = true;
                }
            }
        }

        if ($row['sub_number'] == 0) { //Hpall
            $show_dx11_file = true;
            if ($has_qc1_fail) {
                $class .= ' plate_hascolcomment platecommentcol1';
            }
        } else if ($row['sub_number'] > 0) { //ddPCR


            
            if ($has_qc1_pass) {
                


                $show_qx200_file = true;

                //check pass qc2
                $has_fail_qc2   = false;
                $has_fail_qc3   = false;
                $qc2_rs         = $wpdb->get_results("select * from ci_req_qx200 
                where plate_ID = {$row['pl_ID']} 
                and (pass_qc2 = 0 or (pass_qc3=0 and desc1='NTC')) 
                and pctype='P'");
                foreach ($qc2_rs as $r) {
                    $r->well = strtoupper($r->well);
                    if ($r->desc1 == 'NTC' && $r->pass_qc3 == 0) {
                        $has_fail_qc3 = true;
                    }

                    if ($r->pass_qc2 == 0) {
                        $has_fail_qc2 = true;
                    }
                    $qcs_data[$r->well] = $r;
                }

                if ($has_fail_qc2) {
                    $qc_comments[2] = 'QC-2';
                }

                if ($has_fail_qc3) {
                    $qc_comments[3] = 'QC-3';
                }

                if ($otherdata['qc4']===0) {
                    $qc_comments[4] = 'QC-4';
                }

                if ($otherdata['qc5'] === 0) {
                    $qc_comments[5] = 'QC-5';
                }

              
            }




            if (count($qc_comments) > 0) {
                ksort($qc_comments);
                $class .= ' plate_hascolcomment platecommentcol' . count($qc_comments);
            }
        }
    }

    if (isset($config_plate['notmoventc']) && $config_plate['notmoventc']) {
        $tbl_classes .= ' notmoventc ';
    }
    $plate_controls = array(
        'neg' => 'NTC',
        'pos' => 'POS',
        'nec' => 'NEC',
    );
}



?>
<div class="plateblock <?php echo $class ?>" data-id="<?php echo $row['pl_ID'] ?>">
    <div class="header">
        <div class="maincolheader">
            <label style="float: left;"><input type="checkbox"></label>
            <div class="title"><?php echo $plate_name ?></div>
            <div class="stitle">Rack #<?php echo $row['re_number'] . ' - ' . $row['re_subtitle'] ?></div>
            <?php if ($show_dx11_file) {
                echo '<div class="center">';
                echo '<a class="button bsmall upload_plate_file upload_plate_file_dx11_' . $row['pl_ID'] . '" data-type="dx11" href="#">Upload DX11</a>';
                echo '</div>';
            } else if ($show_qx200_file) {
                echo '<div class="center">';
                echo '<a class="button bsmall upload_plate_file upload_plate_file_qx200_' . $row['pl_ID'] . '" data-type="qx200" href="#">Upload QX200</a>';
                echo '</div>';
            }
            ?>

            <span class="dashicons dashicons-flag flag-<?php echo $row['re_priority'] ?>"></span>
            <?php if (empty($row['re_number'])) echo '<span class="norackicon"></span>' ?>
        </div>
        <?php
        if ($show_dx11_file && $has_qc1_fail) {
            echo '<div class="columncomment">Comment</div>';
        } else if (count($qc_comments) > 0) {
            foreach ($qc_comments as $qck => $qct) {
                echo '<div class="columncomment">Comment ' . $qct . '</div>';
            }
        }
        ?>
    </div>
    <div class="ccontent">
        <table class=" <?php echo $tbl_classes ?>" data-type="<?php echo $row['re_is_uti_panel'] ?>">
            <?php
            for ($j = 1; $j <= $plate_max_well; $j++) {

                $arr = isset($assens[$j]) ? $assens[$j] : array('assen' => '');
                $assen = $arr['assen'];
                $class = isset($arr['class']) ? $arr['class'] : '';


                if ($rack_uti_type == 14 && $row['re_is_uti_panel'] == 1) {
                    $num12 = floor(($j - 1) / 14) + 1;
                    $welld = chr(floor(($j - 1) % 14) + 65) . $num12;
                } else if ($rack_16) {
                    $num12 = floor(($j - 1) / 8) + 1;
                    if ($num12 > 1) $num12 = 7;
                    $welld = chr(floor(($j - 1) % 8) + 65) . $num12;
                } else if ($rack_ABC) {
                    $num12 = floor(($j - 1) / 8) + 1;
                    $welld = chr(floor(($j - 1) % 8) + 65) . $num12;
                } else {
                    $num12 = ($j - 1) % 12 + 1;
                    $welld = chr(floor(($j - 1) / 12) + 65) . $num12;
                }


                if ($row['pl_ntc_position'] == $j || (isset($arr['ntc']) && $arr['ntc'] === 1)) {
                    if (empty($assen)) {
                        $assen = !empty($row['pl_ntc_text']) ? $row['pl_ntc_text'] : $plate_controls['neg'];
                    }

                    $class .= ' ntc1 ';
                } else if ($row['pl_ntc2_position'] == $j || (isset($arr['ntc']) && $arr['ntc'] === 2)) {
                    if (empty($assen)) {
                        $assen = !empty($row['pl_ntc2_text']) ? $row['pl_ntc2_text'] : $plate_controls['pos'];
                    }

                    $class .= ' ntc2 ';
                } else if ($row['pl_ntc3_position'] == $j) {
                    $assen = !empty($row['pl_ntc3_text']) ? $row['pl_ntc3_text'] : $plate_controls['neg'];
                    $class .= ' ntc3 ';
                } else if ($row['pl_ntc4_position'] == $j) {
                    $assen = !empty($row['pl_ntc4_text']) ? $row['pl_ntc4_text'] : $plate_controls['pos'];
                    $class .= ' ntc4 ';
                } else if ($row['pl_blank1_position'] == $j) {
                    $assen = !empty($row['pl_blank1_text']) ? $row['pl_blank1_text'] : 'Blank 1';
                    $class .= ' blank1 ';
                } else if ($row['pl_blank2_position'] == $j) {
                    $assen = !empty($row['pl_blank2_text']) ? $row['pl_blank2_text'] : 'Blank 2';
                    $class .= ' blank2 ';
                }

                if ($row['sub_number'] > 0) {

                    //check qc2,3,4,5
                    $qc = isset($qcs_data[$welld]) ? $qcs_data[$welld] : array();

                    if ($qc) {
                        if ($assen == 'NTC') {
                            if ($qc->pass_qc3 == 0) {
                                $class .= ' dx11_fail';
                                $arr['pass_qc3']            = false;
                                $arr['pass_qc3_hand']       = $qc->pass_qc3_hand;
                                $arr['comment_pass_qc3']    = $qc->comments_qc3;
                            }
                        }

                        if ($qc->pass_qc2 == 0) {
                            $class .= ' dx11_fail';
                            $arr['pass_qc2']            = false;
                            $arr['pass_qc2_hand']       = $qc->pass_qc2_hand;
                            $arr['comment_pass_qc2']    = $qc->comments_qc2;
                        } else {
                            $arr['pass_qc2'] = true;
                        }
                    }

                    if ($otherdata['qc4'] === 0 && $welld == 'A1') {
                        $class .= ' dx11_fail';
                        $arr['pass_qc4']            = false;
                        $arr['pass_qc4_hand']       = $otherdata['pass_qc4_hand'];
                        $arr['comment_pass_qc4']    = $otherdata['comment_pass_qc4'];
                    }

                    
                     if ($otherdata['qc5'] === 0 && $welld == 'A1') {
                        $class .= ' dx11_fail';
                        $arr['pass_qc5']            = false;
                        $arr['pass_qc5_hand']       = $otherdata['pass_qc5_hand'];
                        $arr['comment_pass_qc5']    = $otherdata['comment_pass_qc5'];
                    }
                }
            ?>
                <tr class="<?php echo $class ?>">
                    <td class="position"><?php echo $j ?></td>
                    <td class="assen"><input type="text" value="<?php echo $assen; ?>" readonly /></td>
                    <td class="well">
                        <?php
                        echo '<span class="wellnum">' . $welld . '</span>';
                        if (isset($arr['is_rerun']) && $arr['is_rerun']) {
                            echo '<span class="scircle greencircle sscircle" title="Rerun">R</span>';
                        }
                        ?>
                    </td>
                    <?php
                    if ($show_dx11_file && $has_qc1_fail && ($j - 1) % 2 == 0) {
                        echo '<td class="commentcol" rowspan=2>';
                        if (isset($arr['pass_qc1'])) {
                            echo $arr['comment_pass_qc1'];
                        } else if (isset($arr['pass']) && !$arr['pass']) {
                            echo '<a class="button pass_qc" data-qc="1">PASS</a>';
                        }
                        echo '</td>';
                    } else if (count($qc_comments) > 0) { //&& ((($j - 1) % 4 == 0 && $arr['ntc'] > 0) || (($j - 1) % 8 == 0 && $arr['ntc'] == 0))) {
                        foreach ($qc_comments as $qck => $qct) {
                            echo '<td class="commentcol" >';

                            if (isset($arr['pass_qc' . $qck . '_hand']) && $arr['pass_qc' . $qck . '_hand']) {
                                echo $arr['comment_pass_qc' . $qck];
                            } else if (isset($arr['pass_qc' . $qck]) && !$arr['pass_qc' . $qck]) {
                                echo '<a class="button pass_qc" data-qc="' . $qck . '">PASS</a>';
                            }

                            echo '</td>';
                        }
                    } ?>
                </tr>
            <?php
            }
            ?>
        </table>
    </div>
    <?php if ($show_export_96) { ?>
        <div class="cfooter">
            <?php if (in_array($row['re_is_uti_panel'], array(33, 34))) {
                echo '<a class="button pl_qtower_exportbtn">qTower Export</a>';
            } else if (!$rack_16) { ?>
                <?php if ($show_BioRad_export) { ?>
                    <a class="button pl_biorad_exportbtn " data-blockid="<?php echo $exportblockid ?>">BioRad Export</a>
                <?php } ?>
                <a class="button pl_studio_exportbtn" data-blockid="<?php echo $exportblockid ?>">Studio Export</a>
            <?php } ?>
        </div>
    <?php } ?>
</div>