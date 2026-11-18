<?php
global $have_urine;

$screenings = tr_pdd_get_result_validity(1, $report['batch_created'], $report['mid']);

if (count($screenings) > 0 && $report['matrix'] != "oral") {
    $show_screening_summary = false;

    ob_start();
?>
    <table>
        <tr>
            <td class="dontbreak" style="padding:0">
                <table class="  marginbottom small_table ">
                    <tr class="">
                        <th colspan="<?php echo $urine_settings['paper_strip'] ? 3 : 4 ?>" class="header_black">
                            <h4 class="h4">Screening Summary</h4>
                        </th>
                        <th class="header_none"></th>
                    </tr>
                    <tr class="title_tbl">
                        <th style="width:20%">
                            Test
                        </th>
                        <?php if (!$urine_settings['paper_strip']): ?>
                            <th style="width:10%">
                                Value
                            </th>
                        <?php endif; ?>
                        <th style="width:10%">
                            Neg/Pos
                        </th>
                        <th style="width:10%">
                            Cutoff (ng/mL)
                        </th>
                        <th>
                            Comments
                        </th>
                    </tr>

                    <?php

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
                            //  if (empty($arr['outcome'])) $arr['outcome'] = 'NEG';

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


                        if (empty($arr['u_range'])) {
                            $arr['u_range'] = $arr['u_low'] . ' - ' . $arr['u_high'];
                        }
                        if (!empty($arr['conc']) && ($arr['conc'] < $arr['u_low'] || ($arr['conc'] > $arr['u_high'] && $arr['u_high'] > 0))) {
                            $arr['outcome'] = $arr['u_neg_pos'] ? 'POS' : 'ABNORMAL';
                        } else if (empty($arr['conc']) || empty($arr['outcome'])) {
                            $arr['outcome'] = $arr['u_neg_pos'] ? 'NEG' : 'NORMAL';
                        }


                        if (($arr['outcome'] == 'POS' || $arr['outcome'] == 'ABNORMAL')) {
                            $k = $arr['conc'] < $arr['u_low'] ? 'low' : 'high';
                            $cm = $arr['u_comments'];
                            if (stripos($cm, '||') > 0) {
                                if (stripos($arr['comments'], ($k == 'low' ? 'high' : 'low')) === 0) {
                                    $arr['comments'] = '';
                                }
                                if (empty($arr['comments'])) {
                                    $cms = explode('||', $cm);
                                    $has_cm = false;
                                    foreach ($cms as $c) {
                                        $c = trim($c);
                                        if (stripos($c, $k) === 0) {
                                            $cm = $c;
                                            $has_cm = true;
                                            break;
                                        }
                                    }
                                    if (!$has_cm) {
                                        $cm = $arr['conc'] < $arr['u_low'] ? $cms[0] : $cms[1];
                                    }
                                }
                                $arr['comments'] = $cm;
                            } else if (empty($arr['comments'])) {
                                $arr['comments'] = $arr['u_comments'];
                            }
                        } else {
                            continue;
                        }
                        $show_screening_summary = true;
                        if ($arr['outcome'] == 'POS') {
                            $arr['outcome'] = 'POS';
                        }
                        if (strtolower($arr['comments']) == 'description') {
                            $arr['comments'] = '';
                        }

                        if (empty($arr['outcome']) && empty($arr['conc'])) {
                            continue;
                        }

                    ?>
                        <tr class="font_red">
                            <td>
                                <?php echo htmlentities2($arr['u_name']); ?>
                            </td>
                            <?php if (!$urine_settings['paper_strip']): ?>
                                <td>

                                </td>
                            <?php endif; ?>
                            <td class="center  pn_<?php echo $arr['outcome'] ?>">
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
                    ?>
                </table>
            </td>
        </tr>
    </table>
<?php
    $content = ob_get_clean();
    if ($show_screening_summary || isset($_GET['debug'])) {
        $have_screening = true;
        echo $content;
    }
}
