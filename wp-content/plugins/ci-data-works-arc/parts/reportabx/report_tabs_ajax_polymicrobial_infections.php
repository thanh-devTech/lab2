<?php
$monthname = date_i18n('M');

$chart5 = new CI_Chart(array(
    'width' => 500,
    'height' => 250,
    'pieSliceText' => 'value',
    'title' => '',
    'colors' => array('#bcf0f0', '#86cbcc', '#d9a3e0', '#c96cd5', '#c031d3', '#e1237f', '#ff4242', '#f00000', '#8d0202', '#4e1304')
));

$chart5->showTotal(true);


$bacteria_test_IDs = array();

foreach ($main_abx_group_gids as $mgid) {

    $sql_bac_ids = "select te_ID 
            from ci_test_panels te
            join ci_test_groups g ON g.tg_ID = te.te_group_id
            left join ci_test_meta tm on tm.test_id = te.te_ID and tm.meta_key = 'bac_tests' and tm.meta_value !='' and tm.meta_value is not null
            where tg_antibiotic = 0  and tg_name not like '%Antibiotic%' 
            and te.te_is_control = 0
            and te.te_in_req = 1
            and tg_parent = {$mgid} 
            and ((tg_name like '%Bacteria%' or tg_name like '%Pathogen%' or tg_name like '%PARASITE%' or tg_name like '%Gram%') or tm.test_id is not null )";


    if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
        $sql_bac_ids = "select te_ID 
            from ci_test_panels te
            join ci_test_groups g ON g.tg_ID = te.te_group_id
            where tg_antibiotic = 0  and tg_name not like '%Antibiotic%' 
            and te.te_is_control = 0
            and te.te_in_req = 1
            and tg_parent = {$mgid} ";
    }
    $test_IDs = $wpdb->get_col($sql_bac_ids);

    if (count($test_IDs) == 0) {
        //get in root folder
        $test_IDs = $wpdb->get_col(
            "select te_ID 
                from ci_test_panels te
                JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
                where g.tg_antibiotic = 0 and tg_name not like '%Antibiotic%'
                and te.te_is_control = 0
                and te.te_in_req = 1
                and tg_ID in ({$mgid}) "
        );
    }

    $bacteria_test_IDs = array_merge($bacteria_test_IDs, $test_IDs);
}

if (count($bacteria_test_IDs) > 0) {
    $bacteria_test_IDs_str = implode(',', $bacteria_test_IDs);

    $poly_where = $where; //where in report_tabs_ajax.php
    $poly_where .= " and re.estatus = 'C' ";


    if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
        $poly_where .= " and (re.has_uti > 0 or re.has_pos > 0) ";
    } else {
        $poly_where .= " and re.has_uti > 0 ";
    }


    $poly_where .= " 
    and te.te_ID in ({$bacteria_test_IDs_str})
    and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') 
    and rs.has_order = 1 ";


    $pi_total_rate = [];

    $sqlmk = "select 
        count(DISTINCT rs.drug) as count
        from ci_test_panels te
        join ci_results_pcr rs ON rs.drug = te.te_name or rs.drug = te.te_abrev or rs.drug = te.te_abrev2
        join ci_reports_pcr re ON re.id = rs.report_id
        join ci_batches_pcr ba ON re.patient_id = ba.assen
        where {$poly_where}
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

foreach ($counts as $n => $c) {
    $chart5->addData($n . ' Pathogen' . ($n > 1 ? 's' : ''), $c);
}

$months[$monthname] = $monthname;
$month_charts = array(array('', ''));
$total = 0;
foreach ($counts as $n => $c) {

    $month_charts[] = array($n . ' Pathogen' . ($n > 1 ? 's' : ''), intval($c));

    $total += $c;
    $pi_total_rate[] = $c;
}

$chart5->setTotal($total);
$chart5->addMonthData($monthname, $month_charts, $total);


$tt_rate = [];
foreach ($pi_total_rate as $v) {
    if ($total > 0) {
        $tt_rate[] = round($v / $total * 100, 1);
    } else {
        $tt_rate[] = 0;
    }
}
$chart5->data_rate = $tt_rate;
$chart5->grd = $main_abx_group_gids;
$chart5->id_id = $bacteria_test_IDs_str;
$chart5->date_sel = $cur_name_month_range;
