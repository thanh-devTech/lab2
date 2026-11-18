<?php
$is_load_table = filter_var($_REQUEST['is_load_table'] ?? null, FILTER_VALIDATE_BOOLEAN);
$prevalence_option = sanitize_text_field($_REQUEST['prevalence'] ?? '0');
$chart4 = new CI_Chart(array(
    'width' => 600,
    'height' => 300,
    'pieSliceText' => 'value',
    'title' => ''
));
$chart4->setName_Unit('', '');
$chart4->showTotal(true);

$tests_colors   = array();
$map_tests      = array();
$alltestdrugs   = array();
$where_main     = '';
$where_test     = '';

$main_abx_group_gids_str = implode(',', $main_abx_group_gids);

$reflex_source_groups = $wpdb->get_results(
    "SELECT DISTINCT tg_ID, tg_reflex_group_ID, tg_parent
    FROM ci_test_groups 
    WHERE tg_reflex_group_ID IS NOT NULL 
    AND tg_reflex_group_ID != 0
    AND tg_parent IN ({$main_abx_group_gids_str})"
);

$testrs =  $wpdb->get_results(
    "select te_name, te_abrev, te_abrev2, te_color
        from ci_test_panels te
        JOIN ci_test_groups g ON g.tg_ID = te.te_group_id
        where te.te_in_req = 1
        and (g.tg_antibiotic = 1 or g.tg_name like '%Antibiotic Resistance%')
        and (tg_parent in ({$main_abx_group_gids_str}) or tg_ID in ({$main_abx_group_gids_str}) )"
);

$reflex_drugs = array();

foreach ($testrs as $tr) {

    $testname = strtoupper($tr->te_name);
    $testabrev = strtoupper($tr->te_abrev);
    $testabrev2 = strtoupper($tr->te_abrev2);

    if (!isset($tests_colors[strtoupper($testname)]) && $tr->te_color)
        $tests_colors[strtoupper($testname)] = $tr->te_color;


    if (!in_array($testname, $alltestdrugs)) {
        $alltestdrugs[] = $testname;
        $map_tests[$testname] = $tr->te_name;
    }
    if (!empty($testabrev) && !in_array($testabrev, $alltestdrugs)) {
        $alltestdrugs[] = $testabrev;
        $map_tests[$testabrev] = $tr->te_name;
        if (!isset($tests_colors[strtoupper($testabrev)]) && $tr->te_color)
            $tests_colors[strtoupper($testabrev)] = $tr->te_color;
    }
    if (!empty($testabrev2) && !in_array($testabrev2, $alltestdrugs)) {
        $alltestdrugs[] = $testabrev2;
        $map_tests[$testabrev2] = $tr->te_name;
        if (!isset($tests_colors[strtoupper($testabrev2)]) && $tr->te_color)
            $tests_colors[strtoupper($testabrev2)] = $tr->te_color;
    }
}
$parents_with_reflex = array();
foreach ($reflex_source_groups as $rg) {
    $reflex_to_groups = array_filter(array_map('trim', explode(',', $rg->tg_reflex_group_ID)));

    if (!empty($reflex_to_groups) && !empty($rg->tg_parent)) {
        $parents_with_reflex[$rg->tg_parent] = true;
    }
}

$mkr_groups = array();
foreach ($main_abx_group_gids as $gid) {
    $condition = " FIND_IN_SET('GROUP_{$gid}', panel_tests_requested) ";
    if (isset($parents_with_reflex[$gid])) {
        $condition = " ( {$condition} AND (re.has_pos > 0 OR re.has_uti > 0) ) ";
    }
    $mkr_groups[] = $condition;
}
$mkr_groups = implode(' or ', $mkr_groups);


$where_main = ' ba.is_saved = 0 ' . $conditions['where'];
if ($c_cid > 0) {
    $where_main .= " AND ba.cli_ID = " . intval($c_cid);
}
$where_main .= " and ( {$mkr_groups} ) ";
$where_main .= " and re.estatus = 'C' ";

if (!empty($alltestdrugs)) {
    $alltestdrugs_str = implode("','", $alltestdrugs);

    $where_test = " rs.drug in ('{$alltestdrugs_str}')
                and rs.`pos-neg` in ('POS','POH','POL','POM','PVH','PVL') 
                and rs.has_order = 1 ";
}



if (!function_exists('get_tests_tabs_ajax_resistance_markers_query')) {
    function get_tests_tabs_ajax_resistance_markers_query($where_main, $where_test, $date_from, $date_to)
    {
        global $wpdb, $cache_query_seconds;

        if (!empty($where_test)) {
            $sqlmk =  "  
            select rs.drug,
                count(DISTINCT re2.id) as count
                from ci_results_pcr rs 
                JOIN (
                    select re.id, re.has_pos, re.has_uti from ci_reports_pcr re 
                        join ci_batches_pcr ba ON re.patient_id = ba.assen
                    where {$where_main}
                        AND " . trpdd_abx_query_date_range($date_from, $date_to) . "
                ) as re2 ON re2.id = rs.report_id
                    where {$where_test}
                group by rs.drug
            ";


            if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {

                $cache = new Tr_Cache_Query('reportabx');
                $tests = $cache->get_results($sqlmk, OBJECT, $cache_query_seconds);
            } else {
                $tests = $wpdb->get_results($sqlmk);
            }
        } else {
            $tests = array();
        }

        return $tests;
    }
}
$fieldpositive = 're.has_uti > 0';
if (tr_get_option('ci_pdf_data', 'abx_db_positive_by_all_pathogens')) {
    $fieldpositive .= ' or re.has_pos > 0';
}

$sql = "
SELECT 
    COUNT(*) AS countreqs,
    SUM(is_positive) AS count_positive
FROM (
    SELECT
        IF(re.estatus='C' AND ({$fieldpositive}), 1, 0) AS is_positive
    FROM ci_batches_pcr ba
    JOIN ci_reports_pcr re ON re.patient_id = ba.assen
    WHERE {$where}
        AND " . trpdd_abx_query_date_range($date_from, $date_to) . "
) AS rs
";

if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
    $cache = new Tr_Cache_Query('reportabx');
    $rs_pn = $cache->get_row($sql, OBJECT, 0, $cache_query_seconds);
} else {
    $rs_pn = $wpdb->get_row($sql, OBJECT);
}


//Data for Chart
$tests = get_tests_tabs_ajax_resistance_markers_query($where_main, $where_test, $date_from, $date_to);

$total = 0;
$total_positive = $rs_pn->count_positive;
$total_sample = $rs_pn->countreqs;
$new_tests = [];

foreach ($tests as $m) {

    $m->te_name = $map_tests[strtoupper($m->drug)] ?? $m->drug;

    $total += $m->count;

    if (!isset($new_tests[$m->te_name])) {
        $new_tests[$m->te_name] = clone $m;
    } else {
        $new_tests[$m->te_name]->count += $m->count;
    }
}

usort($new_tests, function ($a, $b) {
    return $b->count - $a->count;
});

$chart4->setTotal($total);
$colors = [];
foreach ($new_tests as $m) {

    if (!isset($all_tests[$m->te_name])) {
        $color_row = $tests_colors[strtoupper($m->te_name)] ?? '';
        if (empty($color_row)) {
            $r = rand(10, 255);
            $g = rand(10, 255);
            $b = rand(10, 255);
            $color_row = "rgba($r, $g, $b, 1)";
        }

        $all_tests[$m->te_name] = array(
            'name' => $m->te_name,
            'months' => array(),
            'color' => $color_row,
            's' => 'aaaaaa',
        );
    }
    $pc = $total > 0 ? round($m->count / $total * 100, 1) : 0;
    $pc_all = $total_sample > 0 ? round($m->count / $total_sample * 100, 1) : 0;
    $pc_pos = $total_positive > 0 ? round($m->count / $total_positive * 100, 1) : 0;
    $all_tests[$m->te_name]['months'][$monthname] = array('label' => $pc . '%', 'rate' => $pc);

    $chart4->addData($m->te_name, intval($m->count), $pc, $pc_all, $pc_pos, $total, $total_sample, $total_positive);
    $colors[] = $color_row;
}

$chart4->config['colors'] = $colors;

if (!$is_load_table) {
    //Data for table
    $months             = array();
    $all_tests          = array();
    $pos_neg_6months = array();
    for ($i = 5; $i >= 0; $i--) {
        $mfrom = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
        $mto = date_i18n('Y-m-t', strtotime($mfrom));
        $monthkey = date('Y-m', strtotime($mfrom));
        $sql = "
        SELECT 
            COUNT(*) AS countreqs,
            SUM(is_positive) AS count_positive
        FROM (
            SELECT
                IF(re.estatus='C' AND ({$fieldpositive}), 1, 0) AS is_positive
            FROM ci_batches_pcr ba
            JOIN ci_reports_pcr re ON re.patient_id = ba.assen
            WHERE {$where}
                AND " . trpdd_abx_query_date_range($mfrom, $mto) . "
        ) AS rs ";
        if (class_exists('Tr_Cache_Query') && !isset($_GET['nocache'])) {
            $cache = new Tr_Cache_Query('reportabx');
            $rs_pnm = $cache->get_row($sql, OBJECT, 0, $cache_query_seconds);
        } else {
            $rs_pnm = $wpdb->get_row($sql, OBJECT);
        }
        $pos_neg_6months[$monthkey] = array(
            'POS' => intval($rs_pnm->count_positive),
            'total' => intval($rs_pnm->countreqs),
        );
    }

    for ($i = 5; $i >= 0; $i--) {

        $mfrom      = date('Y-m-01', strtotime('-' . $i . ' months', strtotime($from_date)));
        $mto        = date_i18n('Y-m-t', strtotime($mfrom));
        $monthkey   = date('Y-m', strtotime($mfrom));
        $monthname  = date('M', strtotime($mfrom));
        $months[$monthkey] = $monthname;

        $tests = get_tests_tabs_ajax_resistance_markers_query($where_main, $where_test, $mfrom, $mto);

        $total = 0;
        $total_sample = $pos_neg_6months[$monthkey]['total'];
        $total_positive = $pos_neg_6months[$monthkey]['POS'];
        $new_tests = array();

        foreach ($tests as $m) {

            $m->te_name = $map_tests[strtoupper($m->drug)] ?? $m->drug;

            $total += $m->count;

            if (!isset($new_tests[$m->te_name])) {
                $new_tests[$m->te_name] = clone $m;
            } else {
                $new_tests[$m->te_name]->count += $m->count;
            }
        }

        usort($new_tests, function ($a, $b) {
            return $b->count - $a->count;
        });

        $month_charts = array(array('', ''));

        foreach ($new_tests as $m) {

            if (!isset($all_tests[$m->te_name])) {
                $color_row = $tests_colors[strtoupper($m->te_name)] ?? '';

                if (empty($color_row)) {
                    $r = rand(10, 255);
                    $g = rand(10, 255);
                    $b = rand(10, 255);
                    $color_row = "rgba($r, $g, $b, 1)";
                }

                $all_tests[$m->te_name] = array(
                    'name' => $m->te_name,
                    'months' => array(),
                    'color' => $color_row,
                    's' => 'aaaaaa',
                );
            }
            $pc = $total > 0 ?  round($m->count / $total * 100, 1) : 0;
            $pc_all = $total_sample > 0 ?  round($m->count / $total_sample * 100, 1) : 0;
            $pc_pos = $total_positive > 0 ?  round($m->count / $total_positive * 100, 1) : 0;

            $all_tests[$m->te_name][$monthkey] = array(
                'value' => $pc,
                'rate' => $pc,
                'value_all' => $pc_all,
                'value_pos' => $pc_pos,
                "count" => $m->count,
                "total" => $total,
                "total_sample" => $total_sample,
                'total_positive' => $total_positive
            );
            $month_charts[] = array($m->te_name, intval($m->count));
        }
    }

    //6 months table
    $table_marker = new CI_Report_Table();
    $table_marker->setHeader($months);
    $cur_monthkey = date_i18n('Y-m');
    uasort($all_tests, function ($a, $b) use ($cur_monthkey) {
        return ($b[$cur_monthkey]['value'] ?? 0) - ($a[$cur_monthkey]['value'] ?? 0);
    });

    foreach ($all_tests as $mk => $arr) {
        foreach ($months as $mk => $mlabel) {
            if (!isset($arr[$mk])) {
                $arr[$mk] = array(
                    'value' => '0',
                    'rate' => '0',
                    'value_pos' => '0',
                    'count' => '0',
                    'total' => '0',
                    'total_sample' => '0',
                );
            }
        }
        $table_marker->addRow($arr);
    }
}
