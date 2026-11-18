<?php

$screenings = tr_pdd_get_result_validity(1, $report['batch_created'], $report['mid']);

if (count($screenings) > 0 && $report['matrix'] != "oral") {
    $show_validity = false;
    ob_start();
    $neg_screening = array();
    foreach ($screenings as $arr) {
        $key = $arr['u_ID'];

        if (!$arr['checked']) {
            continue;
        }

        $arr['conc'] = preg_replace("/[^0-9-.,]/", '', $arr['conc']);

        if (isset($arr['readonly']) && $arr['readonly'] && !empty($arr['outcome'])) {
            //dont change pos/neg
        } elseif (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
            $arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
        } else if (!empty($arr['conc']) || $arr['conc'] === '0') {
            $arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
        }

        if ($arr['u_neg_pos']) {

            if (!empty($arr['conc']) && $arr['conc'] < $arr['u_low']) {
                $arr['conc'] = 'Low';
            } else if (!empty($arr['conc']) && $arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0) {
                $arr['conc'] = 'High';
            } else {
                //$arr['conc'] = '';
            }
        } else if (!empty($arr['conc']) && $arr['conc'] < $arr['u_low']) {
            $arr['conc'] = '<' . $arr['u_low'];
        } else if (!empty($arr['conc']) && $arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0) {
            $arr['conc'] = '>' . $arr['u_high'];
        }


        $arr['u_range'] = str_ireplace('Cutoff :', '', $arr['u_range']);
        $arr['u_range'] = str_ireplace('Cutoff:', '', $arr['u_range']);
        if (empty($arr['u_range'])) {
            $arr['u_range'] = $arr['u_low'] . ' - ' . $arr['u_high'];
        }

        if (empty($arr['outcome']) && empty($arr['conc'])) {
            continue;
        }
        $show_validity = true;
        if ($arr['outcome'] == 'NORMAL' || $arr['outcome'] == 'NEG') {
            $neg_screening[] = '<b>' . $arr['u_name'] . '</b> ' . $arr['outcome'] . '(' . htmlentities2($arr['u_range']) . ')';

            continue;
        }




?>
        <tr>
            <td class="bold">
                <?php echo htmlentities2($arr['u_name']); ?>
            </td>
            <?php if (!$urine_settings['paper_strip']) : ?>
                <td class="center">
                    <?php echo htmlentities2($arr['conc']) ?>
                </td>
            <?php endif; ?>
            <td class="center">
                <?php echo $arr['outcome'] ?>
            </td>
            <td class="center">
                <?php echo htmlentities2($arr['u_range']) ?>
            </td>
            <td>
                <?php echo nl2br(htmlentities2($arr['comments'])) ?>
            </td>
        </tr>
    <?php
    }

    $content_main = ob_get_clean();

    if ($show_validity) {
        ob_start();
    ?>
        <table>
            <tr>
                <td style="padding: 0;">
                    <table class="border  marginbottom small_table">
                        <tr class="header_tbl header_tbl2">
                            <th colspan="<?php echo (!$urine_settings['paper_strip']) ? 5 : 4 ?>">
                                <h4>DRUG SCREENING</h4>
                            </th>
                        </tr>
                        <?php
                        if ($content_main) {
                        ?>
                            <tr class="title_tbl">
                                <th style="width:20%">
                                    Test
                                </th>
                                <?php if (!$urine_settings['paper_strip']) : ?>
                                    <th style="width:10%">
                                        Value
                                    </th>
                                <?php endif; ?>
                                <th style="width:10%">
                                    Test Outcome
                                </th>
                                <th style="width:10%">
                                    Reference Range
                                </th>
                                <th>
                                    Comments
                                </th>
                            </tr>
                        <?php
                            echo $content_main;
                        }
                        if (count($neg_screening) > 0) {
                        ?>
                            <tr>
                                <td class="blocknr" colspan="<?php echo (!$urine_settings['paper_strip']) ? 5 : 4 ?>">
                                    <b>Negative Results:</b> <?php echo implode(', ', $neg_screening) ?>
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                    </table>
                </td>
            </tr>
        </table>
<?php
        return ob_get_clean();
    }
}

return '';
