<?php
if (empty($row['re_number'])) {
    return;
}
$base_row       = $row;


$otherdata      = !empty($row['other_data']) ? json_decode($row['other_data'], true)  : array();


$show_BioRad_export = isset($show_BioRad_export) ? $show_BioRad_export : true;
$config_plate   = trpdd_plate_map_config($row['re_is_uti_panel']);
$tbl_classes    = 'platetbl';
$show_export_96 = isset($row['show_export_96']) ? $row['show_export_96'] : true;
$exportblockid  = '';
if ($row['re_is_uti_panel'] == -2) {
    $show_BioRad_export = false;
}

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
    if ($config_plate['type'] == 384) {
        $show_export_96 = false;
        $tbl_classes .= ' notmoventc ';
    }

    if (isset($config_plate['notmoventc']) && $config_plate['notmoventc']) {
        $tbl_classes .= ' notmoventc ';
    }
    $plate_controls = array(
        'neg' => 'NTC',
        'pos' => 'POS',
        'nec' => 'NEC',
    );
} else if ($row['re_is_uti_panel'] == -1) {
    //Respiratory Light
    $row['pl_ntc_position'] = 0;
    $row['pl_ntc2_position'] = 0;
    $class .= ' notmoventc ';
} else if ($rack_max6_fun && $row['re_is_uti_panel'] == -3) {
    //Respiratory Light
    $row['pl_ntc_position'] = 0;
    $row['pl_ntc2_position'] = 0;
    $class .= ' notmoventc ';
} else if ($rack_uti_type == 3 && $row['re_is_uti_panel'] == 1) {
    $row['pl_ntc_position'] = 0;
    $row['pl_ntc2_position'] = 0;
    $class .= ' notmoventc ';
} else if ($row['re_is_uti_panel'] == 1) {
    $plate_controls = array(
        'neg' => 'NC',
        'pos' => 'PC'
    );
}



?>
<div class="plateblock <?php echo $class ?>" data-id="<?php echo $row['pl_ID'] ?>">
    <div class="header">
        <div class="maincolheader">
            <label style="float: left;"><input type="checkbox"></label>
            <div class="title"><?php echo $plate_name ?></div>
            <div class="stitle">Rack #<?php echo $row['re_number'] . ' - ' . $row['re_subtitle'] ?></div>
            <span class="dashicons dashicons-flag flag-<?php echo $row['re_priority'] ?>"></span>
            <?php if (empty($row['re_number'])) echo '<span class="norackicon"></span>' ?>
        </div>
    </div>
    <div class="ccontent">
        <table class=" <?php echo $tbl_classes ?>" data-type="<?php echo $row['re_is_uti_panel'] ?>">
            <?php
            $ntc_num = 0;
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
            ?>
                <tr class="<?php echo $class ?>">
                    <td class="position"><?php echo $j ?></td>
                    <td class="assen"><input type="text" value="<?php echo $assen; ?>" readonly /></td>
                    <td class="well">
                        <?php
                        echo $welld;
                        if (isset($arr['is_rerun']) && $arr['is_rerun']) {
                            echo '<span class="scircle greencircle sscircle" title="Rerun">R</span>';
                        }
                        ?>
                    </td>
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

<?php
if (isset($config_plate['add384']) && $config_plate['add384']) {
    $plates_384 = array();
    $tmp_row = $base_row;
    $tmp_row['is_add384'] = 1;
    $tmp_row['is_96'] = true;
    $plates_384[] = $tmp_row;

    include(trpdd_get_file_path('plate', 'view_384'));
}
