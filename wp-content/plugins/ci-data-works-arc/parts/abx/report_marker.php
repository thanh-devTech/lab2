<?php
global $wpdb;

$chart = new CI_Chart(array(
    'width' => 600,
    'height' => 300,
    'pieSliceText' => 'value',
    'title' => ''
));
$chart->setName_Unit('', '');
$chart->showTotal(true);

$wherebygroups = array();
foreach ($main_abx_group_gids as $mgid) {

    $maker_test_IDs = $wpdb->get_col(
        "select te_ID 
        from ci_test_panels te
        JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
        where (g.tg_antibiotic = 1 or g.tg_name like '%Antibiotic Resistance%')
        and (tg_parent in ({$mgid}) or tg_ID in ({$mgid}) ) "
    );
    if (empty($maker_test_IDs)) {
        $wherebygroups[$mgid]  = ' 1 = 2 '; //no tests found
        continue;
    }
    $maker_test_IDs_str = implode(',', $maker_test_IDs);


    $wherebygroups[$mgid]      = ' ba.is_saved = 0 ' . $conditions['where'];

    $wherebygroups[$mgid]      .= " and  FIND_IN_SET('GROUP_{$mgid}', panel_tests_requested)  ";

    $wherebygroups[$mgid]      .= " and re.estatus = 'C' ";

    $wherebygroups[$mgid]      .= " and te.te_ID in ({$maker_test_IDs_str})
    and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') 
    and rs.has_order = 1 ";
}

if (isset($_GET['debugabxmarker'])) {
    var_dump($wherebygroups);
}



$months     = array();
$all_tests = array();
$monthname = date_i18n('M');

for ($i = 5; $i >= 0; $i--) {

    $mfrom  = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
    $mto    = date_i18n('Y-m-t', strtotime($mfrom));
    $monthname  = date('M', strtotime($mfrom));
    //var_dump($mfrom);echo '<br>';

    $childqrs = array();
    foreach ($wherebygroups as $mgid => $cwhere) {
        $childqrs[] = "(select te.te_name,
        LOWER(te.te_abrev) as te_abrev,
        count(DISTINCT ba.mid) as count1
        from ci_test_panels te
        join ci_results_pcr rs ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
        join ci_reports_pcr re ON re.id = rs.report_id
        join ci_batches_pcr ba ON re.patient_id = ba.assen
        where {$cwhere} 
        AND " . trpdd_abx_query_date_range($mfrom, $mto) . "
        group by te.te_name )";
    }
    $childqr_str = implode(' UNION ALL ', $childqrs);
    $sqlmk = "select lmk.te_name, 
        lmk.te_abrev,
        sum(lmk.count1) as count 
        from ({$childqr_str}) as lmk 
        group by te_name
        order by count desc, te_name asc";


    $cache_data = array();
    $filemtime = filemtime(__FILE__);
    $cache_file = $cache_dir . md5($sqlmk . $filemtime) . '.cache';
    if (strtotime($mto) < time()) {
        //check cache
        if (file_exists($cache_file)) {
            $cache_data = json_decode(file_get_contents($cache_file));
        }
    }

    if (empty($cache_data)) {
        $tests = $wpdb->get_results($sqlmk);
        if (!empty($tests) && strtotime($mto) < time()) {
            file_put_contents($cache_file, json_encode($tests));
        }
    } else {
        $tests = $cache_data;
    }





    if (isset($_GET['debug2']) && $i == 5) {
        var_dump($sqlmk);
        echo '<br><br>';
        var_dump($tests);
    }



    $total = 0;
    $new_tests = array();

    foreach ($tests as $m) {
        $total += $m->count;

        if (isset($abrevbynames[$m->te_abrev])) {
            $m->te_name = $abrevbynames[$m->te_abrev];
        } else if (!empty($m->te_abrev)) {
            $abrevbynames[$m->te_abrev] = $m->te_name;
        }

        if (isset($new_tests[$m->te_name])) {
            $m->count += $new_tests[$m->te_name]->count;
        }

        $new_tests[$m->te_name] = $m;
    }


    $chart->setTotal($total);

    $months[$monthname] = $monthname;
    $month_charts = array(array('', ''));

    foreach ($tests as $m) {

        if (!isset($all_tests[$m->te_name])) {
            $all_tests[$m->te_name] = array(
                'name' => $m->te_name,
                'months' => array()
            );
        }
        $pc = round($m->count / $total * 100, 1);
        $all_tests[$m->te_name]['months'][$monthname] = array('label' => $pc  . '%', 'class' => 'acenter');


        if ($i == 0) {
            $chart->addData($m->te_name, intval($m->count));
        }

        $month_charts[] = array($m->te_name, intval($m->count));
    }

    $chart->addMonthData($monthname, $month_charts, $total);
}




//6 months table
$table      = new CI_Report_Table();
$header = array('marker' => array('label' => 'Most Detected Resistance', 'class' => 'aleft'));
foreach ($months as $m) {
    $header[$m] = $m;
}

$table->setHeader($header);

foreach ($all_tests as $mk => $arrs) {
    $arr = array();
    $arr['marker'] = $arrs['name'];
    foreach ($months as $m) {
        if (!isset($arrs['months'][$m])) {
            $arr[$m] = array('label' => '0%', 'class' => 'acenter');
        } else {
            $arr[$m] = $arrs['months'][$m];
        }
    }

    $table->addRow($arr);
}



$chart->setupMonthSelect('#selectmonthmk', 'displaymonthmk');

?>
<div class="clear"></div>
<div class="postbox full">
    <div class="postbox-header">
        <h2 class="hndle">Resistance Markers<span id="selectmonthmk" class="selectmonth">Select Month</span></h2>
    </div>
    <table class="postbox_wrap" style="width:100%">
        <tr>
            <td style="width:52%">
                <h2 class="nomargin" style="padding-left:20px">Resistance Detected in <span class="displaymonth" id="displaymonthmk"><?php echo $monthname ?></span></h2>
                <?php $chart->display(); ?>
            </td>
            <td style="width:46%">
                <?php $table->display(); ?>
            </td>
        </tr>
    </table>
</div>