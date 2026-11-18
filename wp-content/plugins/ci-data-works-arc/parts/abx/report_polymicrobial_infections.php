<?php
global $wpdb;



$monthname = date_i18n('M');

$chart = new CI_Chart(array(
    'width' => 500,
    'height' => 250,
    'pieSliceText' => 'value',
    'title' => ''
));

$chart->showTotal(true);


$from_date = date_i18n('Y-m-01');
$end_date = date_i18n('Y-m-t');


$main_abx_group_gidstr = implode(' , ', $main_abx_group_gids);

$bacteria_test_IDs = $wpdb->get_col(
    "select te_ID 
        from ci_test_panels te
        JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
        where g.tg_antibiotic = 0
            and te.te_is_control = 0
            and te.te_in_req = 1
            and (tg_parent in ({$main_abx_group_gidstr}) or tg_ID in ({$main_abx_group_gidstr})) "
);

$bacteria_test_IDs_str = implode(',', $bacteria_test_IDs);


$where_group = array();
foreach ($main_abx_group_gids as $gid) {
    $where_group[] = " FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)  ";
}
$where_group = implode(' or ', $where_group);

$poly_where      = ' ba.is_saved = 0 ' . $conditions['where'];
$poly_where     .= " and ({$where_group}) ";
$poly_where     .= " and re.estatus = 'C' ";


if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $poly_where     .= " and (re.has_uti > 0 or re.has_pos > 0) ";
} else {
    $poly_where     .= " and re.has_uti > 0 ";
}

$poly_where     .= " and te.te_ID in ({$bacteria_test_IDs_str})
            and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') 
            and rs.has_order = 1 ";

for ($i = 5; $i >= 0; $i--) {

    $mfrom  = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
    $mto    = date_i18n('Y-m-t', strtotime($mfrom));
    $monthname  = date('M', strtotime($mfrom));
    $sqlmk = "select 
    count(DISTINCT rs.drug) as count
    from ci_test_panels te
    join ci_results_pcr rs ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
    join ci_reports_pcr re ON re.id = rs.report_id
    join ci_batches_pcr ba ON re.patient_id = ba.assen
    where {$poly_where}
    AND " . trpdd_abx_query_date_range($mfrom, $mto) . "
    group by ba.mid ";


    $cache_data = array();
    $filemtime = filemtime(__FILE__);
    $cache_file = $cache_dir . md5($sqlmk . $filemtime) . '.cache';
    if (strtotime($mto) < time()) {
        //check cache
        if (file_exists($cache_file)) {
            $cache_data = json_decode(file_get_contents($cache_file), true);
        }
    }

    if (empty($cache_data)) {
        $counts = $wpdb->get_col($sqlmk);
        if (!empty($counts) && strtotime($mto) < time()) {
            file_put_contents($cache_file, json_encode($counts));
        }
    } else {
        $counts = $cache_data;
    }


    if (isset($_GET['debug']) && $i == 5) {
        var_dump($sqlmk);
        echo '<br><br>';
    }


    $counts = array_count_values($counts);

    ksort($counts);

    if ($i == 0) {
        foreach ($counts as $n => $c) {
            $chart->addData($n . ' Pathogen' . ($n > 1 ? 's' : ''), $c);
        }
    }




    $months[$monthname] = $monthname;
    $month_charts = array(array('', ''));
    $total = 0;
    foreach ($counts as $n => $c) {

        $month_charts[] = array($n . ' Pathogen' . ($n > 1 ? 's' : ''), intval($c));

        $total += $c;
    }

    $chart->setTotal($total);

    $chart->addMonthData($monthname, $month_charts, $total);
}
$chart->setupMonthSelect('#selectmonthpoly', 'displaymonthpoly');

?>
<div class="postbox " style="height:340px">
    <div class="postbox-header">
        <h2 class="hndle">Polymicrobial Infections <span class="selectmonth" id="selectmonthpoly">Select Month</span></h2>
    </div>
    <div class="postbox_wrap">
        <h2 class="nomargin">Polymicrobial Infections in <text class="displaymonth" id="displaymonthpoly"><?php echo $monthname ?></text></h2>
        <?php $chart->display(); ?>
    </div>
</div>