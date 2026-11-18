<?php
$chart6 = new CI_Chart(array(
    'width' => 500,
    'height' => 250,
    'pieSliceText' => 'value',
    'title' => '',
    'colors' => array('#bcf0f0', '#86cbcc', '#d9a3e0', '#c96cd5', '#c031d3', '#e1237f', '#ff4242', '#f00000', '#8d0202', '#4e1304')
));
$chart6->setName_Unit('', '');
$chart6->showTotal(true);

$mk_rate = [];
$maker_test_IDs_str = '';

$main_abx_group_gids_str = implode(' , ', $main_abx_group_gids);
$maker_test_IDs = $wpdb->get_col(
    "select te_ID 
        from ci_test_panels te
        JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
        where (g.tg_antibiotic = 1 or g.tg_name like '%Antibiotic Resistance%')
        and (tg_parent in ({$main_abx_group_gids_str}) or tg_ID in ({$main_abx_group_gids_str}) ) "
);



if (count($maker_test_IDs) > 0) {
    $maker_test_IDs_str = implode(',', $maker_test_IDs);


    $maker_where = $where; //where in report_tabs_ajax.php
    $maker_where .= " and re.estatus = 'C' ";
    $maker_where .= " and te.te_ID in ({$maker_test_IDs_str}) ";
    $maker_where .= " and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') ";
    $maker_where .= " and rs.has_order = 1 ";

    //Data for Chart


    $sqlmk = "select 
        count(DISTINCT rs.drug) as count
        from ci_test_panels te
        join ci_results_pcr rs ON rs.drug IN( te.te_name, te.te_abrev, te.te_abrev2)
        join ci_reports_pcr re ON re.id = rs.report_id
        join ci_batches_pcr ba ON re.patient_id = ba.assen
        where {$maker_where}
        AND " . trpdd_abx_query_date_range($date_from, $date_to) . "
        group by ba.mid ";

    if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

        $cache = new Tr_Cache_Query('reportabx');
        $counts = $cache->get_col($sqlmk, 0, $cache_query_seconds);
    } else {
        $counts = $wpdb->get_col($sqlmk);
    }
} else {
    $counts = [];
}



$counts = array_count_values($counts);

ksort($counts);

$total = 0;

foreach ($counts as $n => $c) {
    $chart6->addData($n . ' Marker' . ($n > 1 ? 's' : ''), $c);
    $mk_rate[] = $c;
    $total += $c;
}


$month_charts = array(array('', ''));

foreach ($counts as $n => $c) {
    $month_charts[] = array($n . ' Marker' . ($n > 1 ? 's' : ''), intval($c));
}
$chart6->setTotal($total);

$mk_total_rate = [];
foreach ($mk_rate as $m) {
    $mk_total_rate[] = $m > 0 ? round($m / $total * 100, 1) : 0;
}
$chart6->total_rate = $mk_total_rate;
$chart6->id_id = $maker_test_IDs_str;
$chart6->date_sel = $cur_name_month_range;
