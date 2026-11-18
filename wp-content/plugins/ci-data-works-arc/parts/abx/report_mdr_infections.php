<?php
global $wpdb;
$monthname = date_i18n('M');
$chart = new CI_Chart(array(
    'width' => 500,
    'height' => 250,
    'pieSliceText' => 'value',
    'title' => ''
));
$chart->setName_Unit('', '');
$chart->showTotal(true);

$from_date = date_i18n('Y-m-01');
$end_date = date_i18n('Y-m-t');


$main_abx_group_gidstr = implode(' , ', $main_abx_group_gids);
$maker_test_IDs = $wpdb->get_col(
    "select te_ID 
    from ci_test_panels te
    JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
    where g.tg_antibiotic = 1 
    and (tg_parent in ({$main_abx_group_gidstr}) or tg_ID in ({$main_abx_group_gidstr})) "
);
if (empty($maker_test_IDs)) {
    //no tests found
    $maker_test_IDs  = array(-1);
}
$maker_test_IDs_str = implode(',', $maker_test_IDs);

$where_group = array();
foreach ($main_abx_group_gids as $gid) {
    $where_group[] = " FIND_IN_SET('GROUP_{$gid}', panel_tests_requested)  ";
}
$where_group = implode(' or ', $where_group);

$where      = ' ba.is_saved = 0 ' . $conditions['where'];
$where  .= " and re.estatus = 'C' ";
$where  .= " and ({$where_group})";
$where  .= "  and te.te_ID in ({$maker_test_IDs_str})
            and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') 
            and rs.has_order = 1 ";

for ($i = 5; $i >= 0; $i--) {

    $mfrom      = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
    $mto        = date_i18n('Y-m-t', strtotime($mfrom));
    $monthname  = date('M', strtotime($mfrom));
    $sqlmk = "select 
                count(DISTINCT rs.drug) as count
                from ci_test_panels te
                join ci_results_pcr rs ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
                join ci_reports_pcr re ON re.id = rs.report_id
                join ci_batches_pcr ba ON re.patient_id = ba.assen
                where {$where}
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
            $chart->addData($n . ' Marker' . ($n > 1 ? 's' : ''), $c);
        }
    }

    $total = 0;
    foreach ($counts as $n => $c) {
        $total += $c;
    }

    $chart->setTotal($total);

    $months[$monthname] = $monthname;
    $month_charts = array(array('', ''));
    foreach ($counts as $n => $c) {

        $month_charts[] = array($n . ' Marker' . ($n > 1 ? 's' : ''), intval($c));
    }

    $chart->addMonthData($monthname, $month_charts, $total);
}

$chart->setupMonthSelect('#selectmonthmdr', 'displaymonthmdr');
?>
<div class="postbox postbox2" style="height:340px">
    <div class="postbox-header">
        <h2 class="hndle">MDR Infections <span class="selectmonth" id="selectmonthmdr">Select Month</span></h2>
    </div>
    <div class="postbox_wrap">
        <h2 class="nomargin">MDR Infections in <text class="displaymonth" id="displaymonthmdr"><?php echo $monthname ?></text></h2>
        <?php $chart->display(); ?>
    </div>
</div>