<?php
global $wpdb;

$offset_seconds = get_option('gmt_offset') * HOUR_IN_SECONDS;

$chart = new CI_Chart(array(
    'width' => 500,
    'height' => 400,
    'pieSliceText' => 'value',
    'title' => ''
));
$chart->setName_Unit('', '');
$chart->showTotal(true);


$from_date = date_i18n('Y-m-01');
$end_date = date_i18n('Y-m-t');
$monthname  = date('M', strtotime($from_date));


$wherebygroups = array();

foreach ($main_abx_group_gids as $mgid) {


    $sql_bac_ids =  "select te_ID 
        from ci_test_panels te
        join ci_test_groups g ON g.tg_ID = te.te_group_id
        left join ci_test_meta tm on tm.test_id = te.te_ID and tm.meta_key = 'bac_tests' and tm.meta_value !='' and tm.meta_value is not null
        where tg_antibiotic = 0  and tg_name not like '%Antibiotic%' 
        and te.te_is_control = 0
        and te.te_in_req = 1
        and tg_parent = {$mgid} 
        and ((tg_name like '%Bacteria%' or tg_name like '%Pathogen%' or tg_name like '%PARASITE%' or tg_name like '%Gram%') or tm.test_id is not null )";

    if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
        $sql_bac_ids =  "select te_ID 
            from ci_test_panels te
            join ci_test_groups g ON g.tg_ID = te.te_group_id
            where tg_antibiotic = 0  and tg_name not like '%Antibiotic%' 
            and te.te_is_control = 0
            and te.te_in_req = 1
            and tg_parent = {$mgid} 
        ";
    }

    $bacteria_test_IDs = $wpdb->get_col($sql_bac_ids);

    if (count($bacteria_test_IDs) == 0) {
        //get in root folder
        $bacteria_test_IDs = $wpdb->get_col(
            "select te_ID 
            from ci_test_panels te
            JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
            where g.tg_antibiotic = 0 and tg_name not like '%Antibiotic%'
            and te.te_is_control = 0
            and te.te_in_req = 1
            and tg_ID in ({$mgid}) "
        );
    }

    if (empty($bacteria_test_IDs)) {
        $wherebygroups[$mgid]  = ' 1 = 2 '; //no tests found
        continue;
    }


    $bacteria_test_IDs_str = implode(',', $bacteria_test_IDs);

    $wherebygroups[$mgid]  = ' ba.is_saved = 0 ' . $conditions['where'];

    $wherebygroups[$mgid] .= " and FIND_IN_SET('GROUP_{$mgid}', panel_tests_requested)  ";

    $wherebygroups[$mgid] .= " and te.te_ID in ({$bacteria_test_IDs_str}) ";
    $wherebygroups[$mgid] .= " and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') ";
    $wherebygroups[$mgid] .= " and rs.has_order = 1 ";
    $wherebygroups[$mgid] .= " and re.estatus = 'C' ";
}

if (isset($_GET['debugabxbacteria'])) {
    var_dump($wherebygroups);
}


$months     = array();
$all_tests = array();
$abrevbynames = array();

for ($i = 5; $i >= 0; $i--) {

    $mfrom  = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
    $mto    = date_i18n('Y-m-t', strtotime($mfrom));
    $monthname  = date('M', strtotime($mfrom));

    $childqrs = array();
    foreach ($wherebygroups as $mgid => $cwhere) {
        $childqrs[] = " ( select te.te_name,
        LOWER(te.te_abrev) as te_abrev,
        count(DISTINCT ba.mid) as count1
        from ci_test_panels te
        join ci_results_pcr rs ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
        join ci_reports_pcr re ON re.id = rs.report_id
        join ci_batches_pcr ba ON re.patient_id = ba.assen
        where {$cwhere}
        AND " . trpdd_abx_query_date_range($mfrom, $mto) . "
        group by te_name
       ) ";
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



    if (isset($_GET['debug']) && $i == 0) {
        var_dump($sqlmk);
        echo '<br><br>';
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

    foreach ($new_tests as $m) {

        if (!isset($all_tests[$m->te_name])) {
            $all_tests[$m->te_name] = array(
                'name' => $m->te_name,
                'sum' => 0
            );
        }


        $pc = $total > 0 ? round($m->count / $total * 100, 1) : 0;


        $all_tests[$m->te_name][$monthname] = array('label' => $pc  . '%', 'class' => 'acenter');
        $all_tests[$m->te_name]['sum'] += $pc;


        if ($i == 0) {
            $chart->addData($m->te_name, intval($m->count));
        }
        $month_charts[] = array($m->te_name, intval($m->count));
    }



    $chart->addMonthData($monthname, $month_charts, $total);
}






//6 months table
$table      = new CI_Report_Table();
$header = array('testname' => array('label' => 'Most Detected Pathogens', 'class' => 'aleft'));
foreach ($months as $m) {
    $header[$m] = $m;
}

$table->setHeader($header);


uasort($all_tests, function ($a, $b) {
    return $b['sum'] - $a['sum'];
});

foreach ($all_tests as $arr) {
    $arr['testname'] = $arr['name'];
    foreach ($months as $m) {
        if (!isset($arr[$m])) {
            $arr[$m] = array('label' => '0%', 'class' => 'acenter');
        }
    }

    $table->addRow($arr);
}

$chart->setupMonthSelect('#selectmonthbat', 'displaymonthbat');
?>
<div class="clear "></div>
<div class="postbox full">
    <div class="postbox-header">
        <h2 class="hndle">Pathogens<span id="selectmonthbat" class="selectmonth">Select Month</span></h2>
    </div>
    <div class="postbox_wrap frow">
        <div class="col2" style="width:52%">
            <h2 class="nomargin">Pathogens Detected in <span class="displaymonth" id="displaymonthbat"><?php echo $monthname ?></span></h2>
            <?php $chart->display(); ?>
        </div>
        <div class="col2" style="width:46%">
            <?php $table->display(); ?>
        </div>
    </div>
</div>