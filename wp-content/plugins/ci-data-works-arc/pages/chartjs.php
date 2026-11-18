<?php
$is_percent = false;
$colors = array(
    2 => 'rgba(200, 0, 0, .9)',
    1 => 'rgba(255, 147, 40, .9)',
    0 => 'rgba(0, 255, 0, .5)',
);

if ($display_chart == 'bg') {
    $colors = array(
        3 => 'rgba(200, 0, 0, .5)',
        2 => 'rgba(255, 147, 40, .5)',
        1 => 'rgba(255,255,153,0.5)',
        0 => 'rgba(0, 255, 0, .5)',
        'center' => 'rgba(255, 0, 0, .5)',
    );
}

if ($qct == 'high_low') {
    $colors = array(
        'High' => 'rgba(200, 0, 0, .8)',
        0 => 'rgba(0, 255, 0, .8)',
        'Low' => 'rgba(255, 147, 40, .5)',
    );

    if ($display_chart == 'bg') {
        $colors = array(
            3 => 'rgba(200, 0, 0, .5)',
            2 => 'rgba(255, 147, 40, .5)',
            1 => 'rgba(255,255,153,0.5)',
            0 => 'rgba(0, 255, 0, .5)',
            'center' => 'rgba(255, 0, 0, .5)',
        );
    }
} else  if ($qct == 'theoretical') {
    $is_percent = true;
    $colors = array(
        25 => 'rgba(200, 0, 0, .8)',

        0 => 'rgba(0, 255, 0, .8)',
    );

    if ($display_chart == 'bg') {
        $colors = array(
            25 => 'rgba(200, 0, 0, .3)',
            0 => 'rgba(0, 255, 0, .5)',
            'center' => 'rgba(0, 255, 0, .1)',
        );
    }
}

?>

<style>
    .linewithline-legend li {
        display: inline-block;
        margin: 8px;
    }

    .linewithline-legend li span {
        border-radius: 5px;
        display: inline-block;
        height: 15px;
        width: 15px;
        margin-right: 5px;
    }
</style>

<script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/Chart.min.js?v=<?php echo TRCPDD_VS ?>"></script>
<script type="text/javascript" src="<?php echo TRCPDD_URL ?>js/trChart.js?v=<?php echo time() ?>"></script>
<script type="text/javascript">
    var $colors = <?php echo json_encode($colors) ?>;
</script>

<?php

if (!empty($chart_data['logo'])) {
?>
    <div class="logo" style="text-align: center;margin-bottom:30px;margin-top:30px;"><img src="<?php echo $chart_data['logo'] ?>" /></div>
<?php
}
if (!isset($chart_data['charts']) || !is_array($chart_data['charts'])) {
    $chart_data['charts'] = array();
}

$time_start     = 500;
$count_charts   = count($chart_data['charts']);

if (isset($_GET['debug'])) {
    $count_charts = 8;
}
$count = 0;
foreach ($chart_data['charts'] as $i => $data) {
    $count++;

    if (isset($_GET['debug']) && $count > 10) {
        break;
    }

    $values = array();
    $dates = array();
    $values2 = array();
    $values3 = array();


    foreach ($data['value'] as $date => $value) {
        if (is_numeric($date)) {
            $date = date('Y-m-d', $date);
        }
        $dates[] = $date;
        $values[] = floatval(number_format($value, 3, '.', ''));
    }


    $data['dates'] = $dates;
    $data['values'] = $values;

    if (is_array($data['value2']) && count($data['value2']) > 0) {
        foreach ($data['value'] as $date => $value) {
            if (isset($data['value2'][$date])) {
                $value = $data['value2'][$date];
            } else {
            }

            $values2[] = floatval(number_format($value, 3, '.', ''));
        }
        $data['values2'] = $values2;
    }

    if (is_array($data['value3']) && count($data['value3']) > 0) {
        foreach ($data['value'] as $date => $value) {
            if (isset($data['value3'][$date])) {
                $value = $data['value3'][$date];
            } else {
            }

            $values3[] = floatval(number_format($value, 3, '.', ''));
        }
        $data['values3'] = $values3;
    }

    $allvalues = array_merge($values, $values2, $values3);

    $data['average'] = array_sum($allvalues) / count($allvalues);

    $data['is_percent'] = $is_percent;

    unset($data['value']);
    unset($data['value2']);
    unset($data['value3']);

?>

    <h3 class="center" id="curve_chart<?php echo $i ?>_title" style="text-align: center;margin-top:5px;color:#35afe3"><?php echo $data['name'] ?></h3>
    <div style="margin-bottom:40px; text-align: center;">
        <ul class="linewithline-legend">
            <?php if (isset($data['values3'])) {
                echo '<li><span style="background-color:' . $data['color'] . '"></span>Value #1</li>';
                echo '<li><span style="background-color:' . $data['color2'] . '"></span>Value #2</li>';
                echo '<li><span style="background-color:' . $data['color3'] . '"></span>Value #3</li>';
            } else {
                echo '<li><span style="background-color:' . $data['color'] . '"></span>Your chart data</li>';
            }
            ?>

            <?php
            $more = '';
            foreach ($colors as $k => $c) {
                if ($k === 'center') continue;

                if ($qct == 'high_low' && $display_chart != 'bg') {
                    $txt = $k;
                } else if ($k > 10) {
                    $txt = $k . '%';
                } else {
                    $txt = $k . ' sigma';
                }

                if ($k == '0') {
                    echo '<li><span style="background-color:' . $colors[$k] . '"></span>' . ($qct == 'theoretical' ? 'Theoretical' : 'Average') . '</li>';
                } else if ($qct == 'high_low' && $display_chart != 'bg') {
                    echo '<li><span style="background-color:' . $colors[$k] . '"></span>' . $txt . '</li>';
                } else {
                    $more = '<li><span style="background-color:' . $colors[$k] . '"></span>-' . $txt . '</li>' . $more;
                    echo '<li><span style="background-color:' . $colors[$k] . '"></span>+' . $txt . '</li>';
                }
            }
            echo $more;
            ?>
        </ul>
        <canvas class="chart_pic" id="curve_chart<?php echo $i ?>"
            data-center="<?php echo $data['center'] ?>"
            data-is_percent="<?php echo $data['is_percent'] ?>"
            data-average="<?php echo $data['average'] ?>"
            width="<?php echo $chart_data['width'] ?>" height="<?php echo $chart_data['height'] ?>" style="background:#fff;margin:0 auto"></canvas>
    </div>

    <script>
        <?php
        if ($display_chart == 'bg') {
        ?>
            setup_chat_bg('<?php echo $i ?>', <?php echo json_encode($data) ?>);
        <?php
        } else {
        ?>
            setup_chat_line('<?php echo $i ?>', <?php echo json_encode($data) ?>);
        <?php
        }
        if ($count >= $count_charts) {
        ?>
            jQuery('.print_pdf').removeAttr('disabled');
        <?php
        }
        ?>
    </script>
<?php
    $time_start += 200;
    get_bloginfo();
}
?>